<?php
/**
 * Modul Notifikasi Email Otomatis (PHPMailer & Gmail SMTP) B2B
 * Sistem Network Trouble Ticketing & SLA Tracking
 * PT. Visimedia Pratama Persada
 * Human-Crafted Enterprise IT Architecture
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Menginisialisasi objek PHPMailer dengan konfigurasi SMTP dari Database
 */
function get_configured_mailer($debug = false) {
    $mail = new PHPMailer(true);

    $smtp_enabled = get_setting('smtp_enabled', '0');
    $smtp_host    = get_setting('smtp_host', 'smtp.gmail.com');
    $smtp_port    = (int)get_setting('smtp_port', '465');
    $smtp_secure  = get_setting('smtp_secure', 'ssl');
    $smtp_user    = get_setting('smtp_user', '');
    $smtp_pass    = get_setting('smtp_pass', '');
    $app_name     = get_setting('app_name', 'NetTicket B2B - SLA Tracking');
    $from_name    = get_setting('smtp_from_name', 'NOC PT. Visimedia Pratama Persada');
    $from_email   = get_setting('smtp_from_email', $smtp_user);

    $real_from = $smtp_user;
    if (empty($real_from)) {
        $real_from = $from_email;
    }

    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtp_user;
    $mail->Password   = $smtp_pass;
    $mail->SMTPSecure = ($smtp_secure === 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $smtp_port;
    $mail->CharSet    = 'UTF-8';
    $mail->isHTML(true);

    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ];

    $mail->setFrom($real_from, $from_name);
    if (!empty($from_email) && $from_email !== $real_from) {
        $mail->addReplyTo($from_email, $from_name);
    } else {
        $mail->addReplyTo($real_from, $from_name);
    }

    if ($debug) {
        $mail->SMTPDebug = 2;
    }

    return [
        'mailer'       => $mail,
        'smtp_enabled' => ($smtp_enabled === '1'),
        'has_auth'     => (!empty($smtp_user) && !empty($smtp_pass))
    ];
}

/**
 * Mengirim notifikasi email tiket berdasarkan status dan event perjalanan tiket
 */
function send_ticket_notification($ticket_id, $event_type, $custom_message = '') {
    try {
        $config = get_configured_mailer();
        if (!$config['smtp_enabled']) {
            return [
                'success' => false,
                'message' => 'Notifikasi email dimatikan (smtp_enabled = 0).'
            ];
        }

        if (!$config['has_auth']) {
            return [
                'success' => false,
                'message' => 'Akun SMTP / Gmail belum dikonfigurasi lengkap di panel admin.'
            ];
        }

        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT 
            t.*, 
            c.name AS category_name, 
            p.name AS priority_name, 
            p.sla_hours,
            cl.company_name,
            cl.company_code,
            u_pelapor.name AS reporter_name,
            u_pelapor.email AS reporter_email,
            u_pelapor.department AS reporter_dept,
            u_pelapor.phone AS reporter_phone,
            u_tek.name AS technician_name,
            u_tek.phone AS technician_phone,
            u_tek.email AS technician_email
        FROM tickets t
        JOIN categories c ON t.category_id = c.id
        JOIN priorities p ON t.priority_id = p.id
        JOIN clients cl ON t.client_id = cl.id
        JOIN users u_pelapor ON t.user_id = u_pelapor.id
        LEFT JOIN users u_tek ON t.technician_id = u_tek.id
        WHERE t.id = ?");
        $stmt->execute([(int)$ticket_id]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            return [
                'success' => false,
                'message' => 'Tiket tidak ditemukan di database.'
            ];
        }

        $app_name = get_setting('app_name', 'NetTicket B2B');
        $subject = '';
        $headline = '';
        $status_label = '';
        $status_color = '#1e40af';
        $status_bg = '#eff6ff';
        $status_desc = '';

        switch ($event_type) {
            case 'ticket_created':
                $subject      = "[{$app_name}] Tiket Gangguan Masuk: #{$ticket['ticket_code']} ({$ticket['company_name']})";
                $headline     = "Laporan Gangguan Sirkit B2B Telah Diterima";
                $status_label = "OPEN (MENUNGGU DISPOSISI)";
                $status_color = "#475569";
                $status_bg    = "#f1f5f9";
                $status_desc  = "Tiket laporan gangguan sirkit jaringan dari <strong>{$ticket['company_name']}</strong> telah masuk ke antrean NOC PT. Visimedia Pratama Persada. Tim kami sedang menyiapkan penugasan Field Engineer.";
                break;

            case 'ticket_assigned':
                $tek_name     = $ticket['technician_name'] ?? 'Field Engineer';
                $subject      = "[{$app_name}] Tiket Didisposisikan: #{$ticket['ticket_code']} ke {$tek_name}";
                $headline     = "Field Engineer Ditugaskan ke Site";
                $status_label = "ASSIGNED (DITUGASKAN)";
                $status_color = "#1e40af";
                $status_bg    = "#dbeafe";
                $status_desc  = "Tiket gangguan sirkit Anda telah ditugaskan kepada <strong>{$tek_name}</strong>. Field Engineer akan segera melakukan troubleshooting remote / kunjungan ke lokasi site.";
                break;

            case 'ticket_in_progress':
                $subject      = "[{$app_name}] Pengerjaan Dimulai: #{$ticket['ticket_code']}";
                $headline     = "Troubleshooting Sedang Berlangsung";
                $status_label = "IN PROGRESS (SEDANG DIKERJAKAN)";
                $status_color = "#d97706";
                $status_bg    = "#fef3c7";
                $status_desc  = "Field Engineer saat ini sedang aktif melakukan tindakan perbaikan dan pengetesan link sirkit di site Anda.";
                break;

            case 'ticket_resolved':
                $sla_text = ($ticket['sla_status'] === 'within_sla') ? 'Tepat Waktu Sesuai SLA' : 'SLA Breached';
                $subject      = "[{$app_name}] Sirkit Telah Normal: #{$ticket['ticket_code']} ({$sla_text})";
                $headline     = "Perbaikan Sirkit Jaringan Telah Selesai";
                $status_label = "RESOLVED (LINK NORMAL)";
                $status_color = "#059669";
                $status_bg    = "#d1fae5";
                $status_desc  = "Field Engineer telah menyelesaikan tindakan perbaikan. Parameter sirkit telah dites dan kembali normal 100%. Silakan konfirmasi di portal.";
                break;

            case 'ticket_closed':
                $subject      = "[{$app_name}] Tiket Resmi Ditutup: #{$ticket['ticket_code']}";
                $headline     = "Tiket Gangguan Resmi Ditutup";
                $status_label = "CLOSED (TERKONFIRMASI)";
                $status_color = "#0f172a";
                $status_bg    = "#e2e8f0";
                $status_desc  = "Tiket gangguan sirkit telah terkonfirmasi normal dan diarsipkan ke dalam log performa bulanan SLA.";
                break;

            default:
                $subject      = "[{$app_name}] Update Tiket: #{$ticket['ticket_code']}";
                $headline     = "Pembaruan Status Tiket Sirkit";
                $status_label = strtoupper($ticket['status']);
                $status_desc  = "Terdapat pembaruan informasi pada tiket gangguan sirkit jaringan Anda.";
                break;
        }

        // 1. Kirim ke PIC Klien
        if (!empty($ticket['reporter_email'])) {
            try {
                $rep_config = get_configured_mailer();
                $rep_mail = $rep_config['mailer'];

                $rep_html = generate_ticket_email_template($ticket, [
                    'headline'     => $headline,
                    'status_label' => $status_label,
                    'status_color' => $status_color,
                    'status_bg'    => $status_bg,
                    'status_desc'  => $status_desc,
                    'custom_msg'   => $custom_message,
                    'event_type'   => $event_type,
                    'target_role'  => 'karyawan'
                ]);

                $rep_mail->addAddress($ticket['reporter_email'], $ticket['reporter_name']);
                $rep_mail->Subject = $subject;
                $rep_mail->Body    = $rep_html;
                $rep_mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $rep_html));
                $rep_mail->send();
            } catch (Exception $e_rep) {
                error_log("[PHPMailer Error] Gagal kirim email ke PIC ({$ticket['reporter_email']}): " . $e_rep->getMessage());
            }
        }

        // 2. Kirim ke Field Engineer
        if ($event_type === 'ticket_assigned' && !empty($ticket['technician_email'])) {
            try {
                $tech_config = get_configured_mailer();
                $tech_mail = $tech_config['mailer'];

                $tech_subject = "[{$app_name}] [PENUGASAN BARU] #{$ticket['ticket_code']} - {$ticket['company_name']} ({$ticket['circuit_id']})";
                $tech_html = generate_ticket_email_template($ticket, [
                    'headline'     => "Penugasan Perbaikan Sirkit Klien",
                    'status_label' => "TUGAS BARU (ASSIGNED)",
                    'status_color' => "#1e40af",
                    'status_bg'    => "#dbeafe",
                    'status_desc'  => "Halo <strong>" . htmlspecialchars($ticket['technician_name']) . "</strong>, Anda ditugaskan oleh NOC untuk menangani gangguan sirkit <strong>{$ticket['circuit_id']} ({$ticket['company_name']})</strong>. Harap segera tangani sebelum batas waktu SLA.",
                    'custom_msg'   => $custom_message,
                    'event_type'   => 'tech_assigned',
                    'target_role'  => 'teknisi'
                ]);

                $tech_mail->addAddress($ticket['technician_email'], $ticket['technician_name']);
                $tech_mail->Subject = $tech_subject;
                $tech_mail->Body    = $tech_html;
                $tech_mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $tech_html));
                $tech_mail->send();
            } catch (Exception $e_tech) {
                error_log("[PHPMailer Error] Gagal kirim email ke teknisi ({$ticket['technician_email']}): " . $e_tech->getMessage());
            }
        }

        return ['success' => true, 'message' => 'Notifikasi email berhasil diproses.'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Generator Desain HTML Email Notifikasi Tiket Formal B2B
 */
function generate_ticket_email_template($ticket, $opts) {
    $app_name        = get_setting('app_name', 'NetTicket B2B');
    $company_name    = get_setting('company_name', 'PT. Visimedia Pratama Persada');
    $company_phone   = get_setting('company_phone', '(021) 5299-1234');
    $company_email   = get_setting('company_email', 'noc@visimedia.co.id');
    
    $target_role = $opts['target_role'] ?? 'karyawan';
    $host_url = (isset($_SERVER['HTTP_HOST'])) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST']) : "http://localhost";
    
    if ($target_role === 'teknisi') {
        $ticket_url = $host_url . base_url('teknisi/proses_tiket.php?id=' . $ticket['id']);
        $button_text = "Buka Lembar Kerja Teknisi &rarr;";
    } elseif ($target_role === 'helpdesk') {
        $ticket_url = $host_url . base_url('helpdesk/kelola_tiket.php');
        $button_text = "Buka Antrean NOC &rarr;";
    } else {
        $ticket_url = $host_url . base_url('customer/detail_tiket.php?id=' . $ticket['id']);
        $button_text = "Lihat Status Sirkit di Portal &rarr;";
    }

    $headline     = $opts['headline'];
    $status_label = $opts['status_label'];
    $status_color = $opts['status_color'];
    $status_bg    = $opts['status_bg'];
    $status_desc  = $opts['status_desc'];
    $custom_msg   = $opts['custom_msg'];

    $created_f = format_date_indo($ticket['created_at']);
    $deadline_f = format_date_indo($ticket['sla_deadline']);

    $solusi_section = '';
    if (!empty($ticket['technician_notes']) || !empty($custom_msg)) {
        $solusi_text = !empty($ticket['technician_notes']) ? $ticket['technician_notes'] : $custom_msg;
        $solusi_section = '
        <div style="background-color: #f0fdf4; border-left: 4px solid #059669; padding: 12px 16px; border-radius: 4px; margin-top: 15px; margin-bottom: 20px;">
            <div style="font-size: 12px; font-weight: bold; color: #047857; text-transform: uppercase; margin-bottom: 4px;">Catatan Berita Acara Perbaikan:</div>
            <div style="font-size: 13px; color: #065f46; line-height: 1.5;">' . nl2br(htmlspecialchars($solusi_text)) . '</div>
        </div>';
    }

    $tech_info = !empty($ticket['technician_name']) ? htmlspecialchars($ticket['technician_name']) : '<span style="color:#94a3b8; font-style:italic;">Dalam Penjadwalan NOC</span>';

    return '
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($headline) . '</title>
    </head>
    <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b;">
        <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
            
            <!-- HEADER KORPORAT -->
            <div style="background-color: #0f172a; border-bottom: 3px solid #2563eb; padding: 24px; text-align: left; color: #ffffff;">
                <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 3px;">NOC & SERVICE DESK B2B</div>
                <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: #ffffff;">' . htmlspecialchars($company_name) . '</h2>
                <p style="margin: 3px 0 0; font-size: 12px; color: #cbd5e1;">' . htmlspecialchars(get_setting('company_tagline', 'B2B Network Provider')) . '</p>
            </div>

            <!-- BODY CONTENT -->
            <div style="padding: 24px;">
                <div style="margin-bottom: 18px;">
                    <span style="display: inline-block; background-color: ' . $status_bg . '; color: ' . $status_color . '; font-weight: 700; font-size: 11px; padding: 5px 12px; border-radius: 4px; border: 1px solid ' . $status_color . '33;">
                        ' . $status_label . '
                    </span>
                    <h3 style="margin: 10px 0 6px; font-size: 17px; color: #0f172a; font-weight: 700;">' . htmlspecialchars($headline) . '</h3>
                    <p style="margin: 0; font-size: 13px; color: #475569; line-height: 1.5;">' . $status_desc . '</p>
                </div>

                ' . $solusi_section . '

                <!-- DETAIL TABEL SIRKIT & GANGGUAN -->
                <table style="width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; border: 1px solid #e2e8f0; background-color: #f8fafc;">
                    <tr>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; width: 35%; color: #64748b; font-weight: 600;">Nomor Tiket:</td>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #1e40af; font-family: monospace;">' . htmlspecialchars($ticket['ticket_code']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">Perusahaan Klien:</td>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #0f172a;">' . htmlspecialchars($ticket['company_name']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">Nomor Sirkit (CID):</td>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #0369a1; font-family: monospace;">' . htmlspecialchars($ticket['circuit_id']) . ' (' . htmlspecialchars($ticket['service_type']) . ')</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">Kendala:</td>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">' . htmlspecialchars($ticket['title']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">Site / Lokasi:</td>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">' . htmlspecialchars($ticket['location']) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">Kategori Gangguan:</td>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a; font-weight: 600;">' . htmlspecialchars($ticket['category_name']) . '</td>
                    </tr>
                    ' . ($target_role !== 'karyawan' ? '
                    <tr>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">Prioritas & SLA:</td>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #dc2626;">' . htmlspecialchars($ticket['priority_name']) . ' (' . $ticket['sla_hours'] . ' Jam)</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">Batas Waktu SLA:</td>
                        <td style="padding: 9px 12px; border-bottom: 1px solid #e2e8f0; color: #b91c1c; font-weight: 600;">' . $deadline_f . '</td>
                    </tr>' : '') . '
                    <tr>
                        <td style="padding: 9px 12px; color: #64748b; font-weight: 600;">Field Engineer:</td>
                        <td style="padding: 9px 12px; color: #0f172a; font-weight: 600;">' . $tech_info . '</td>
                    </tr>
                </table>

                <div style="text-align: center; margin: 25px 0 10px 0;">
                    <a href="' . $ticket_url . '" style="display: inline-block; background-color: #1e40af; color: #ffffff; text-decoration: none; padding: 11px 24px; border-radius: 6px; font-weight: 600; font-size: 13px;">
                        ' . $button_text . '
                    </a>
                </div>
            </div>

            <!-- FOOTER RESMI -->
            <div style="background-color: #f1f5f9; padding: 16px 24px; text-align: center; font-size: 11px; color: #64748b; border-top: 1px solid #e2e8f0;">
                <div>' . htmlspecialchars($company_name) . ' &bull; Hotline NOC 24/7: ' . htmlspecialchars($company_phone) . '</div>
                <div style="margin-top: 3px;">Email resmi dikirim secara otomatis oleh Sistem Informasi Network Trouble Ticketing B2B.</div>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Mengirim email uji coba SMTP untuk verifikasi konfigurasi admin
 */
function send_test_email($target_email) {
    try {
        $config = get_configured_mailer(false);
        if (!$config['smtp_enabled']) {
            return [
                'success' => false,
                'message' => 'Notifikasi email sedang dinonaktifkan (smtp_enabled = 0). Aktifkan switch terlebih dahulu lalu klik Simpan.'
            ];
        }

        if (!$config['has_auth']) {
            return [
                'success' => false,
                'message' => 'Akun Gmail atau Google App Password belum diisi lengkap pada pengaturan.'
            ];
        }

        $mail = $config['mailer'];
        $app_name = get_setting('app_name', 'NetTicket B2B - SLA Tracking');
        $company_name = get_setting('company_name', 'PT. Visimedia Pratama Persada');
        $company_phone = get_setting('company_phone', '(021) 5299-1234');

        $mail->addAddress($target_email);
        $mail->Subject = "[{$app_name}] Uji Coba Konfigurasi SMTP Berhasil";
        
        $body = '
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>Uji Coba SMTP Berhasil</title>
        </head>
        <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b;">
            <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;">
                <div style="background-color: #0f172a; border-bottom: 3px solid #16a34a; padding: 20px 24px; color: #ffffff;">
                    <h3 style="margin: 0; font-size: 18px; font-weight: 700; color: #ffffff;">' . htmlspecialchars($company_name) . '</h3>
                    <p style="margin: 3px 0 0; font-size: 12px; color: #cbd5e1;">' . htmlspecialchars(get_setting('company_tagline', 'B2B Network Provider')) . '</p>
                </div>
                <div style="padding: 24px;">
                    <span style="display: inline-block; background-color: #d1fae5; color: #059669; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 4px; border: 1px solid #05966933;">
                        TEST SMTP BERHASIL
                    </span>
                    <h3 style="margin: 12px 0 8px; font-size: 16px; color: #0f172a;">Koneksi Notifikasi Email Gmail Telah Terhubung Normal</h3>
                    <p style="font-size: 13px; color: #475569; line-height: 1.5; margin: 0 0 15px 0;">
                        Email ini dikirimkan secara otomatis sebagai pembuktian integrasi PHPMailer & SMTP Google Workspace pada sistem <strong>' . htmlspecialchars($app_name) . '</strong>.
                    </p>
                    <div style="background-color: #f0fdf4; border-left: 4px solid #16a34a; padding: 12px 16px; border-radius: 4px; font-size: 13px; color: #166534; line-height: 1.5;">
                        <strong>Status:</strong> Pengiriman email notifikasi tiket untuk PIC Klien, NOC Dispatcher, dan Field Engineer telah siap beroperasi.
                    </div>
                    <div style="margin-top: 15px; font-size: 12px; color: #64748b;">
                        Waktu Pengujian: <strong>' . date('d M Y, H:i:s') . ' WIB</strong>
                    </div>
                </div>
                <div style="background-color: #f1f5f9; padding: 12px 24px; text-align: center; font-size: 11px; color: #64748b; border-top: 1px solid #e2e8f0;">
                    ' . htmlspecialchars($company_name) . ' &bull; Hotline NOC: ' . htmlspecialchars($company_phone) . '
                </div>
            </div>
        </body>
        </html>';

        $mail->Body = $body;
        $mail->AltBody = "Uji coba konfigurasi email SMTP dari {$app_name} ({$company_name}) telah berhasil pada " . date('d M Y, H:i:s') . " WIB.";

        $mail->send();

        return [
            'success' => true,
            'message' => "Email pengujian berhasil dikirim ke '{$target_email}'! Silakan periksa inbox/spam Gmail Anda."
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Gagal mengirim email pengujian: " . $e->getMessage()
        ];
    }
}
