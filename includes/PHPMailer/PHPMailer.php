<?php
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    const CHARSET_ISO88591 = 'iso-8859-1';
    const CHARSET_UTF8 = 'utf-8';
    const CONTENT_TYPE_PLAINTEXT = 'text/plain';
    const CONTENT_TYPE_TEXT_CALENDAR = 'text/calendar';
    const CONTENT_TYPE_TEXT_HTML = 'text/html';
    const CONTENT_TYPE_MULTIPART_ALTERNATIVE = 'multipart/alternative';
    const CONTENT_TYPE_MULTIPART_MIXED = 'multipart/mixed';
    const CONTENT_TYPE_MULTIPART_RELATED = 'multipart/related';
    const ENCODING_7BIT = '7bit';
    const ENCODING_8BIT = '8bit';
    const ENCODING_BASE64 = 'base64';
    const ENCODING_BINARY = 'binary';
    const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';

    public $Priority;
    public $CharSet = self::CHARSET_UTF8;
    public $ContentType = self::CONTENT_TYPE_TEXT_HTML;
    public $Encoding = self::ENCODING_8BIT;
    public $ErrorInfo = '';
    public $From = '';
    public $FromName = '';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $MIMEBody = '';
    public $MIMEHeader = '';
    public $mailHeader = '';
    public $WordWrap = 0;
    public $Mailer = 'smtp';
    public $Host = 'localhost';
    public $Port = 25;
    public $Helo = '';
    public $SMTPSecure = '';
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $AuthType = '';
    public $Timeout = 300;
    public $SMTPDebug = 0;
    public $Debugoutput = 'echo';
    public $SMTPKeepAlive = false;
    public $SingleTo = false;
    public $SMTPOptions = [];
    public $SMTPAutoTLS = true;

    protected $smtp;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $all_recipients = [];
    protected $attachment = [];
    protected $CustomHeader = [];
    protected $message_type = '';
    protected $boundary = [];
    protected $language = [];
    protected $error_count = 0;
    protected $sign_cert_file = '';
    protected $sign_key_file = '';
    protected $sign_extracerts_file = '';
    protected $sign_key_pass = '';
    protected $exceptions = false;

    public function __construct($exceptions = null)
    {
        if (null !== $exceptions) {
            $this->exceptions = (bool) $exceptions;
        }
    }

    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = static::CONTENT_TYPE_TEXT_HTML;
        } else {
            $this->ContentType = static::CONTENT_TYPE_PLAINTEXT;
        }
    }

    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    public function addAddress($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('to', $address, $name);
    }

    public function addCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('cc', $address, $name);
    }

    public function addBCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('bcc', $address, $name);
    }

    public function addReplyTo($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('ReplyTo', $address, $name);
    }

    protected function addOrEnqueueAnAddress($kind, $address, $name = '')
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!static::validateAddress($address)) {
            $this->setError("Invalid address: (to): $address");
            if ($this->exceptions) {
                throw new Exception($this->ErrorInfo);
            }
            return false;
        }
        if ('ReplyTo' !== $kind) {
            if (!array_key_exists(strtolower($address), $this->all_recipients)) {
                $this->{$kind}[] = [$address, $name];
                $this->all_recipients[strtolower($address)] = true;
                return true;
            }
        } else {
            if (!array_key_exists(strtolower($address), $this->ReplyTo)) {
                $this->ReplyTo[strtolower($address)] = [$address, $name];
                return true;
            }
        }
        return false;
    }

    public function setFrom($address, $name = '', $auto = true)
    {
        $address = trim($address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!static::validateAddress($address)) {
            $this->setError("Invalid address: (From): $address");
            if ($this->exceptions) {
                throw new Exception($this->ErrorInfo);
            }
            return false;
        }
        $this->From = $address;
        $this->FromName = $name;
        if ($auto && empty($this->Sender)) {
            $this->Sender = $address;
        }
        return true;
    }

    public static function validateAddress($address)
    {
        return (bool) filter_var($address, FILTER_VALIDATE_EMAIL);
    }

    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }
            return $this->postSend();
        } catch (Exception $exc) {
            $this->mailHeader = '';
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }

    public function preSend()
    {
        if ('smtp' === $this->Mailer || ('mail' === $this->Mailer && (\PHP_VERSION_ID >= 80000 || !ini_get('safe_mode')) && !empty($this->Sender))) {
            $this->Sender = trim($this->Sender);
        }
        if (empty($this->Sender)) {
            $this->Sender = $this->From;
        }
        if (empty($this->From)) {
            $this->setError('Empty from address');
            if ($this->exceptions) {
                throw new Exception($this->ErrorInfo);
            }
            return false;
        }
        if (count($this->to) + count($this->cc) + count($this->bcc) < 1) {
            $this->setError('You must provide at least one recipient email address.');
            if ($this->exceptions) {
                throw new Exception($this->ErrorInfo);
            }
            return false;
        }

        $this->MIMEHeader = $this->createHeader();
        $this->MIMEBody = $this->createBody();

        return true;
    }

    public function postSend()
    {
        try {
            if ('smtp' === $this->Mailer) {
                return $this->smtpSend($this->MIMEHeader, $this->MIMEBody);
            }
            return $this->mailPassthru($this->to, $this->Subject, $this->MIMEBody, $this->MIMEHeader, $this->Sender);
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }

    protected function smtpSend($header, $body)
    {
        $bad_rcpt = [];
        if (!$this->smtpConnect($this->SMTPOptions)) {
            throw new Exception('SMTP connect() failed: ' . $this->ErrorInfo);
        }
        if ('' === $this->Sender) {
            $smtp_from = $this->From;
        } else {
            $smtp_from = $this->Sender;
        }
        if (!$this->smtp->mail($smtp_from)) {
            $this->setError('MAIL FROM failed: ' . $this->smtp->getError()['error']);
            throw new Exception($this->ErrorInfo);
        }

        $callbacks = [$this->to, $this->cc, $this->bcc];
        foreach ($callbacks as $kind) {
            foreach ($kind as $to) {
                if (!$this->smtp->recipient($to[0])) {
                    $error = $this->smtp->getError();
                    $bad_rcpt[] = ['to' => $to[0], 'error' => $error['detail']];
                }
            }
        }
        if (count($bad_rcpt) > 0 && count($bad_rcpt) === count($this->all_recipients)) {
            $this->setError('All recipients failed');
            throw new Exception($this->ErrorInfo);
        }

        if (!$this->smtp->data($header . $body)) {
            $this->setError('DATA not accepted: ' . $this->smtp->getError()['error']);
            throw new Exception($this->ErrorInfo);
        }
        if ($this->SMTPKeepAlive) {
            $this->smtp->reset();
        } else {
            $this->smtp->quit();
            $this->smtp->close();
        }

        return true;
    }

    public function smtpConnect($options = null)
    {
        if (null === $this->smtp) {
            $this->smtp = $this->getSMTPInstance();
        }
        if (null === $options) {
            $options = $this->SMTPOptions;
        }
        if ($this->smtp->connected()) {
            return true;
        }
        $this->smtp->Timeout = $this->Timeout;
        $this->smtp->do_debug = $this->SMTPDebug;
        $this->smtp->Debugoutput = $this->Debugoutput;

        $hosts = explode(';', $this->Host);
        $lastexception = null;

        foreach ($hosts as $hostentry) {
            $hostinfo = [];
            if (!preg_match('/^(?:(ssl|tls):\/\/)?(.+?)(?::(\d+))?$/', trim($hostentry), $hostinfo)) {
                continue;
            }
            $prefix = $hostinfo[1];
            $host = $hostinfo[2];
            $port = $this->Port;
            if (isset($hostinfo[3]) && 0 < (int) $hostinfo[3]) {
                $port = (int) $hostinfo[3];
            }
            $tls = ('tls' === $this->SMTPSecure || 'tls' === $prefix);
            $ssl = ('ssl' === $this->SMTPSecure || 'ssl' === $prefix);

            if ($ssl) {
                $host = 'ssl://' . $host;
            }

            if ($this->smtp->connect($host, $port, $this->Timeout, $options)) {
                try {
                    if ($this->Helo) {
                        $hello = $this->Helo;
                    } else {
                        $hello = $this->serverHostname();
                    }
                    $this->smtp->hello($hello);

                    if ($this->SMTPAutoTLS && $tls && $this->smtp->getServerExtList() && array_key_exists('STARTTLS', $this->smtp->getServerExtList())) {
                        $tls = true;
                    }
                    if ($tls) {
                        if (!$this->smtp->startTLS()) {
                            throw new Exception('STARTTLS failed');
                        }
                        $this->smtp->hello($hello);
                    }
                    if ($this->SMTPAuth) {
                        if (!$this->smtp->authenticate(
                            $this->Username,
                            $this->Password,
                            $this->AuthType
                        )) {
                            throw new Exception('SMTP Error: Could not authenticate.');
                        }
                    }
                    return true;
                } catch (Exception $exc) {
                    $lastexception = $exc;
                    $this->setError($exc->getMessage());
                    $this->smtp->quit();
                    $this->smtp->close();
                }
            }
        }
        if ($this->exceptions && null !== $lastexception) {
            throw $lastexception;
        }
        return false;
    }

    public function getSMTPInstance()
    {
        if (!is_object($this->smtp)) {
            $this->smtp = new SMTP();
        }
        return $this->smtp;
    }

    protected function serverHostname()
    {
        $result = 'localhost.localdomain';
        if (!empty($this->Helo)) {
            $result = $this->Helo;
        } elseif (isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME'])) {
            $result = $_SERVER['SERVER_NAME'];
        }
        return $result;
    }

    protected function createHeader()
    {
        $result = '';
        $result .= 'Date: ' . static::rfcDate() . "\r\n";
        
        // Generate RFC compliant Message-ID
        $domain = !empty($this->From) ? substr(strrchr($this->From, "@"), 1) : 'gmail.com';
        $message_id = sprintf("<%s.%s@%s>", time(), bin2hex(random_bytes(8)), $domain);
        $result .= 'Message-ID: ' . $message_id . "\r\n";

        if ('mail' !== $this->Mailer) {
            if ($this->SingleTo) {
                foreach ($this->to as $toaddr) {
                    $this->SingleToArray[] = $this->addrFormat($toaddr);
                }
            } else {
                if (count($this->to) > 0) {
                    $result .= $this->addrAppend('To', $this->to);
                } elseif (count($this->cc) === 0) {
                    $result .= 'To: "undisclosed-recipients:;"' . "\r\n";
                }
            }
        }

        $result .= $this->addrAppend('From', [[$this->From, $this->FromName]]);
        if (count($this->cc) > 0) {
            $result .= $this->addrAppend('Cc', $this->cc);
        }
        if (count($this->ReplyTo) > 0) {
            $result .= $this->addrAppend('Reply-To', $this->ReplyTo);
        }
        $result .= 'Subject: =?UTF-8?B?' . base64_encode($this->Subject) . "?=\r\n";
        $result .= 'MIME-Version: 1.0' . "\r\n";
        $result .= 'Auto-Submitted: auto-generated' . "\r\n";
        $result .= 'Content-Type: ' . $this->ContentType . '; charset=' . $this->CharSet . "\r\n";
        $result .= 'Content-Transfer-Encoding: 8bit' . "\r\n";

        return $result . "\r\n";
    }

    protected function createBody()
    {
        return $this->Body;
    }

    public static function rfcDate()
    {
        return date('D, j M Y H:i:s O');
    }

    protected function addrAppend($type, $addr)
    {
        $addresses = [];
        foreach ($addr as $a) {
            $addresses[] = $this->addrFormat($a);
        }
        return $type . ': ' . implode(', ', $addresses) . "\r\n";
    }

    protected function addrFormat($addr)
    {
        if (empty($addr[1])) {
            return '<' . $addr[0] . '>';
        }
        return '=?UTF-8?B?' . base64_encode($addr[1]) . '?= <' . $addr[0] . '>';
    }

    protected function setError($msg)
    {
        ++$this->error_count;
        $this->ErrorInfo = $msg;
    }

    protected function mailPassthru($to, $subject, $body, $header, $params)
    {
        $toArr = [];
        foreach ($to as $t) {
            $toArr[] = $this->addrFormat($t);
        }
        $toStr = implode(', ', $toArr);
        return @mail($toStr, $subject, $body, $header, $params);
    }
}
