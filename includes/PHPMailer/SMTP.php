<?php
namespace PHPMailer\PHPMailer;

class SMTP
{
    const VERSION = '6.9.1';
    const LE = "\r\n";
    const DEFAULT_PORT = 25;
    const MAX_LINE_LENGTH = 998;
    const MAX_REPLY_LENGTH = 512;
    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;

    public $do_debug = self::DEBUG_OFF;
    public $Debugoutput = 'echo';
    public $do_verp = false;
    public $Timeout = 300;
    public $Timelimit = 300;

    protected $smtp_conn;
    protected $error = [
        'error' => '',
        'detail' => '',
        'smtp_code' => '',
        'smtp_code_ex' => '',
    ];
    protected $helo_rply;
    protected $server_caps;
    protected $last_reply = '';

    protected function edebug($str, $level = 0)
    {
        if ($level > $this->do_debug) {
            return;
        }
        if (is_callable($this->Debugoutput)) {
            call_user_func($this->Debugoutput, $str, $level);
            return;
        }
        switch ($this->Debugoutput) {
            case 'error_log':
                error_log($str);
                break;
            case 'html':
                echo htmlentities(
                    preg_replace('/[\r\n]+/', '', $str),
                    ENT_QUOTES,
                    'UTF-8'
                ) . "<br>\n";
                break;
            case 'echo':
            default:
                $str = preg_replace('/\r\n|\r/m', "\n", $str);
                echo gmdate('Y-m-d H:i:s') . "\t" . trim($str) . "\n";
        }
    }

    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        $this->setError('');
        if ($this->connected()) {
            $this->setError('Already connected to a server');
            return false;
        }
        if (empty($port)) {
            $port = self::DEFAULT_PORT;
        }
        $this->edebug("Connection: opening to $host:$port, timeout=$timeout, options=" . var_export($options, true), self::DEBUG_CONNECTION);
        
        $socket_context = stream_context_create($options);
        set_error_handler([$this, 'errorHandler']);
        $this->smtp_conn = stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $socket_context
        );
        restore_error_handler();

        if (!is_resource($this->smtp_conn)) {
            $this->setError(
                'Failed to connect to server',
                '',
                (string) $errno,
                $errstr
            );
            $this->edebug(
                'SMTP ERROR: ' . $this->error['error'] . ": $errstr ($errno)",
                self::DEBUG_CLIENT
            );
            return false;
        }

        $this->edebug('Connection: opened', self::DEBUG_CONNECTION);

        if (substr(PHP_OS, 0, 3) !== 'WIN') {
            $max = ini_get('max_execution_time');
            if (0 != $max && $timeout > $max) {
                @set_time_limit($timeout);
            }
            stream_set_timeout($this->smtp_conn, $timeout, 0);
        }

        $announce = $this->get_lines();
        $this->edebug('SERVER -> CLIENT: ' . $announce, self::DEBUG_SERVER);

        return true;
    }

    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }

        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            $crypto_method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }

        set_error_handler([$this, 'errorHandler']);
        $crypto_ok = stream_socket_enable_crypto(
            $this->smtp_conn,
            true,
            $crypto_method
        );
        restore_error_handler();

        return (bool) $crypto_ok;
    }

    public function authenticate(
        $username,
        $password,
        $authtype = null,
        $OAuth = null
    ) {
        if (!$this->server_caps) {
            $this->setError('No listing of host capabilities, have you called ehlo()?');
            return false;
        }

        if (empty($authtype)) {
            $authtype = 'LOGIN';
            if (isset($this->server_caps['AUTH'])) {
                if (in_array('PLAIN', $this->server_caps['AUTH'])) {
                    $authtype = 'PLAIN';
                } elseif (in_array('LOGIN', $this->server_caps['AUTH'])) {
                    $authtype = 'LOGIN';
                }
            }
        }

        switch ($authtype) {
            case 'PLAIN':
                if (!$this->sendCommand('AUTH', 'AUTH PLAIN', 334)) {
                    return false;
                }
                if (!$this->sendCommand(
                    'User & Password',
                    base64_encode("\0" . $username . "\0" . $password),
                    235
                )) {
                    return false;
                }
                break;
            case 'LOGIN':
                if (!$this->sendCommand('AUTH', 'AUTH LOGIN', 334)) {
                    return false;
                }
                if (!$this->sendCommand('Username', base64_encode($username), 334)) {
                    return false;
                }
                if (!$this->sendCommand('Password', base64_encode($password), 235)) {
                    return false;
                }
                break;
            default:
                $this->setError("Authentication method \"$authtype\" is not supported");
                return false;
        }

        return true;
    }

    public function connected()
    {
        if (is_resource($this->smtp_conn)) {
            $sock_status = stream_get_meta_data($this->smtp_conn);
            if ($sock_status['eof']) {
                $this->edebug('SMTP NOTICE: EOF caught while checking if connected', self::DEBUG_CLIENT);
                $this->close();
                return false;
            }
            return true;
        }
        return false;
    }

    public function close()
    {
        $this->setError('');
        $this->server_caps = null;
        $this->helo_rply = null;
        if (is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
            $this->smtp_conn = null;
            $this->edebug('Connection: closed', self::DEBUG_CONNECTION);
        }
    }

    public function data($msg_data)
    {
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $msg_data));
        $field = substr($lines[0], 0, strpos($lines[0], ':'));
        $in_headers = false;
        if (!empty($field) && strpos($field, ' ') === false) {
            $in_headers = true;
        }

        foreach ($lines as $line) {
            $lines_out = [];
            if ($in_headers && $line === '') {
                $in_headers = false;
            }
            while (isset($line[self::MAX_LINE_LENGTH])) {
                $pos = strrpos(substr($line, 0, self::MAX_LINE_LENGTH), ' ');
                if (!$pos) {
                    $pos = self::MAX_LINE_LENGTH - 1;
                    $lines_out[] = substr($line, 0, $pos);
                    $line = substr($line, $pos);
                } else {
                    $lines_out[] = substr($line, 0, $pos);
                    $line = substr($line, $pos + 1);
                }
                if ($in_headers) {
                    $line = "\t" . $line;
                }
            }
            $lines_out[] = $line;

            foreach ($lines_out as $line_out) {
                if (!empty($line_out) && $line_out[0] === '.') {
                    $line_out = '.' . $line_out;
                }
                $this->client_send($line_out . self::LE, 'DATA');
            }
        }

        $savetimelimit = $this->Timelimit;
        $this->Timelimit *= 2;
        $result = $this->sendCommand('DATA END', '.', 250);
        $this->Timelimit = $savetimelimit;

        return $result;
    }

    public function hello($host = '')
    {
        return (bool) ($this->sendHello('EHLO', $host) or $this->sendHello('HELO', $host));
    }

    protected function sendHello($hello, $host)
    {
        $noerror = $this->sendCommand($hello, $hello . ' ' . $host, 250);
        $this->helo_rply = $this->last_reply;
        if ($noerror) {
            $this->parseHelloFields($hello);
        } else {
            $this->server_caps = null;
        }

        return $noerror;
    }

    protected function parseHelloFields($type)
    {
        $this->server_caps = [];
        $lines = explode("\n", $this->helo_rply);

        foreach ($lines as $n => $s) {
            $s = trim(substr($s, 4));
            if (empty($s)) {
                continue;
            }
            $fields = explode(' ', $s);
            if (!empty($fields)) {
                if (!$n) {
                    $name = $type;
                    $fields = $fields[0];
                } else {
                    $name = array_shift($fields);
                    if ($name === 'SIZE') {
                        $fields = $fields ? explode(' ', $fields[0]) : [];
                    } elseif ($name === 'AUTH') {
                        // Keep auth fields
                    }
                }
                $this->server_caps[$name] = $fields;
            }
        }
    }

    public function mail($from)
    {
        return $this->sendCommand(
            'MAIL FROM',
            'MAIL FROM:<' . $from . '>',
            250
        );
    }

    public function quit($close_on_error = true)
    {
        $noerror = $this->sendCommand('QUIT', 'QUIT', 221);
        $err = $this->error;
        if ($noerror || $close_on_error) {
            $this->close();
            $this->error = $err;
        }

        return $noerror;
    }

    public function recipient($address)
    {
        return $this->sendCommand(
            'RCPT TO',
            'RCPT TO:<' . $address . '>',
            [250, 251]
        );
    }

    public function reset()
    {
        return $this->sendCommand('RSET', 'RSET', 250);
    }

    protected function sendCommand($command, $commandstring, $expect)
    {
        if (!$this->connected()) {
            $this->setError("Called $command() without being connected");
            return false;
        }
        $this->client_send($commandstring . self::LE, $command);

        $this->last_reply = $this->get_lines();
        $this->edebug('SERVER -> CLIENT: ' . $this->last_reply, self::DEBUG_SERVER);

        $matches = [];
        if (preg_match('/^([0-9]{3})[ -](?:([0-9]\\.[0-9]\\.[0-9]{1,2}) )?/', $this->last_reply, $matches)) {
            $code = (int) $matches[1];
            $code_ex = (count($matches) > 2 ? $matches[2] : null);
            $detail = preg_replace(
                "/{$code}[ -]" . ($code_ex ? str_replace('.', '\\.', $code_ex) . ' ' : '') . '/m',
                '',
                $this->last_reply
            );
        } else {
            $code = (int) substr($this->last_reply, 0, 3);
            $code_ex = null;
            $detail = substr($this->last_reply, 4);
        }

        $this->setError('', $detail, $code, $code_ex);

        if (!in_array($code, (array) $expect, true)) {
            $this->setError(
                "$command command failed",
                $detail,
                $code,
                $code_ex
            );
            $this->edebug(
                'SMTP ERROR: ' . $this->error['error'] . ': ' . $this->last_reply,
                self::DEBUG_CLIENT
            );
            return false;
        }

        return true;
    }

    protected function client_send($data, $command = '')
    {
        if (self::DEBUG_CLIENT <= $this->do_debug) {
            $masked_data = $data;
            if ($command === 'Username' || $command === 'Password' || $command === 'User & Password') {
                $masked_data = '***HIDDEN***' . self::LE;
            }
            $this->edebug('CLIENT -> SERVER: ' . $masked_data, self::DEBUG_CLIENT);
        }

        set_error_handler([$this, 'errorHandler']);
        $result = fwrite($this->smtp_conn, $data);
        restore_error_handler();

        return $result;
    }

    protected function get_lines()
    {
        if (!is_resource($this->smtp_conn)) {
            return '';
        }
        $data = '';
        $endtime = 0;
        stream_set_timeout($this->smtp_conn, $this->Timeout);
        if ($this->Timelimit > 0) {
            $endtime = time() + $this->Timelimit;
        }
        $selR = [$this->smtp_conn];
        $selW = null;
        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            set_error_handler([$this, 'errorHandler']);
            $n = stream_select($selR, $selW, $selW, $this->Timelimit);
            restore_error_handler();

            if ($n === false) {
                $this->edebug('SMTP -> get_lines(): stream_select either timed out or had error', self::DEBUG_LOWLEVEL);
                break;
            }
            if (!$n) {
                $this->edebug('SMTP -> get_lines(): timed-out (' . $this->Timeout . ' sec)', self::DEBUG_LOWLEVEL);
                break;
            }
            $str = @fgets($this->smtp_conn, self::MAX_REPLY_LENGTH);
            $this->edebug('SMTP -> get_lines(): $str is "' . $str . '"', self::DEBUG_LOWLEVEL);
            $data .= $str;
            if (!isset($str[3]) || $str[3] === ' ' || $str[3] === "\r" || $str[3] === "\n") {
                break;
            }
            $info = stream_get_meta_data($this->smtp_conn);
            if ($info['timed_out']) {
                $this->edebug('SMTP -> get_lines(): stream_socket_client timed-out', self::DEBUG_LOWLEVEL);
                break;
            }
            if ($endtime && time() > $endtime) {
                $this->edebug('SMTP -> get_lines(): timelimit reached (' . $this->Timelimit . ' sec)', self::DEBUG_LOWLEVEL);
                break;
            }
        }

        return $data;
    }

    protected function setError($message, $detail = '', $smtp_code = '', $smtp_code_ex = '')
    {
        $this->error = [
            'error' => $message,
            'detail' => $detail,
            'smtp_code' => $smtp_code,
            'smtp_code_ex' => $smtp_code_ex,
        ];
    }

    public function getError()
    {
        return $this->error;
    }

    public function getServerExtList()
    {
        return $this->server_caps;
    }

    public function getLastReply()
    {
        return $this->last_reply;
    }

    protected function errorHandler($errno, $errstr, $errfile = '', $errline = 0)
    {
        $notice = 'Connection failed.';
        $this->setError(
            $notice,
            $errstr,
            (string) $errno
        );
        $this->edebug(
            "$notice Error #$errno: $errstr [$errfile line $errline]",
            self::DEBUG_CONNECTION
        );
    }
}
