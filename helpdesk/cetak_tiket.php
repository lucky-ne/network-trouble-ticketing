<?php
/**
 * Berita Acara & Lembar Kerja Penanganan Gangguan Jaringan B2B (Work Order Ticket Report)
 * PT. Visimedia Pratama Persada
 * Format Resmi Standar Cetak Dokumen A4 Portrait (Paperless / Electronic Document)
 */
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['helpdesk', 'admin', 'teknisi', 'manager', 'karyawan']);

$pdo = get_db();
$ticket_id = (int)($_GET['id'] ?? 0);

// Ambil Detail Tiket & Klien Lengkap
$stmt = $pdo->prepare("SELECT 
    t.*, 
    c.name AS category_name, 
    p.name AS priority_name, 
    p.badge_color AS priority_color,
    p.sla_hours,
    cl.company_name,
    cl.company_code,
    cl.circuit_id AS client_circuit_id,
    cl.service_type AS client_service_type,
    cl.bandwidth AS client_bandwidth,
    cl.pic_name AS client_pic_name,
    cl.pic_phone AS client_pic_phone,
    u_pelapor.name AS reporter_name,
    u_pelapor.department AS reporter_dept,
    u_pelapor.phone AS reporter_phone,
    u_pelapor.email AS reporter_email,
    u_tek.name AS technician_name,
    u_tek.department AS technician_dept,
    u_tek.phone AS technician_phone
FROM tickets t
JOIN categories c ON t.category_id = c.id
JOIN priorities p ON t.priority_id = p.id
JOIN clients cl ON t.client_id = cl.id
JOIN users u_pelapor ON t.user_id = u_pelapor.id
LEFT JOIN users u_tek ON t.technician_id = u_tek.id
WHERE t.id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    set_flash('danger', 'Data tiket gangguan tidak ditemukan.');
    header('Location: ' . base_url());
    exit;
}

// Ambil Riwayat Log Tiket
$stmt_logs = $pdo->prepare("SELECT 
    tl.*, u.name AS user_name, u.role AS user_role 
FROM ticket_logs tl
JOIN users u ON tl.user_id = u.id
WHERE tl.ticket_id = ?
ORDER BY tl.created_at ASC");
$stmt_logs->execute([$ticket_id]);
$logs = $stmt_logs->fetchAll();

// Identitas Perusahaan PT. Visimedia Pratama Persada
$company_name    = get_setting('company_name', 'PT. Visimedia Pratama Persada');
$company_tagline = get_setting('company_tagline', 'B2B Network Provider & Managed Service Solutions');
$company_address = get_setting('company_address', 'Cyber 2 Tower Lt. 12, Jl. HR Rasuna Said Blok X-5, Jakarta Selatan 12950');
$company_phone   = get_setting('company_phone', '(021) 5299-1234 / Hotline NOC: 0811-9876-543');
$company_email   = get_setting('company_email', 'noc@visimedia.co.id');

$user_role = $_SESSION['user']['role'] ?? 'helpdesk';
$back_url = base_url('helpdesk/kelola_tiket.php');
if ($user_role === 'admin') {
    $back_url = base_url('admin/semua_tiket.php');
} elseif ($user_role === 'manager') {
    $back_url = base_url('manager/dashboard.php');
} elseif ($user_role === 'teknisi') {
    $back_url = base_url('teknisi/dashboard.php');
} elseif ($user_role === 'karyawan' || $user_role === 'customer') {
    $back_url = base_url('customer/detail_tiket.php?id=' . $ticket['id']);
}

$page_title = 'Berita Acara #' . $ticket['ticket_code'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($company_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        * {
            box-sizing: border-box;
        }
        
        body {
            background-color: #f1f5f9;
            color: #0f172a;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 8.5pt;
            line-height: 1.35;
            margin: 0;
            padding: 0;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .report-paper {
            background: #ffffff;
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 12mm 15mm;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .kop-header {
            display: flex;
            align-items: center;
            gap: 15px;
            padding-bottom: 6px;
        }

        .kop-logo {
            width: 48px;
            height: 48px;
            background: #0f172a;
            color: #ffffff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .kop-title {
            font-size: 12pt;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin: 0;
            line-height: 1.2;
        }

        .kop-sub {
            font-size: 8pt;
            font-weight: 600;
            color: #0284c7;
            margin: 1px 0;
        }

        .kop-address {
            font-size: 7pt;
            color: #475569;
            line-height: 1.25;
        }

        .kop-divider {
            border-top: 2px solid #0f172a;
            border-bottom: 0.75px solid #0f172a;
            height: 3px;
            margin: 4px 0 10px 0;
        }

        .doc-title-box {
            text-align: center;
            margin-bottom: 10px;
        }

        .doc-title {
            font-size: 10.5pt;
            font-weight: 700;
            text-transform: uppercase;
            color: #0f172a;
            letter-spacing: 0.4px;
            margin: 0 0 2px 0;
        }

        .doc-meta {
            font-size: 7.5pt;
            color: #64748b;
        }

        .section-title {
            font-size: 8pt;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            margin: 8px 0 4px 0;
            display: flex;
            align-items: center;
            gap: 5px;
            letter-spacing: 0.2px;
        }

        .table-doc {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 7px;
            font-size: 7.5pt;
        }

        .table-doc th, .table-doc td {
            border: 1px solid #cbd5e1;
            padding: 3.5px 6.5px;
            vertical-align: middle;
        }

        .table-doc th {
            background-color: #f8fafc;
            color: #334155;
            font-weight: 600;
            width: 22%;
        }

        .table-doc td {
            color: #0f172a;
        }

        .box-troubleshoot {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 5px 8px;
            background: #f8fafc;
            margin-bottom: 7px;
            font-size: 7.5pt;
        }

        .box-troubleshoot .item-title {
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1px;
        }

        .box-troubleshoot .item-content {
            color: #334155;
            margin-bottom: 3px;
            line-height: 1.3;
        }

        .box-troubleshoot .item-content:last-child {
            margin-bottom: 0;
        }

        .verification-panel {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background-color: #f8fafc;
            padding: 8px 12px;
            margin-top: 10px;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .doc-footer {
            margin-top: 10px;
            padding-top: 5px;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
            font-size: 6.8pt;
            color: #94a3b8;
        }

        .avoid-break {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm 10mm 8mm 10mm;
            }

            html, body {
                background: #ffffff !important;
                font-size: 7.5pt !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .report-paper {
                width: 100% !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
            }

            .kop-logo {
                background: #0f172a !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .table-doc th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .box-troubleshoot, .verification-panel {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

<!-- Action Bar Cetak (Non-Print) -->
<div class="container no-print mt-3" style="max-width: 210mm;">
    <div class="d-flex justify-content-between align-items-center bg-white p-2 px-3 rounded border shadow-sm">
        <a href="<?= $back_url ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small"><i class="fas fa-file-pdf text-danger me-1"></i> Format Siap Cetak A4 Portrait</span>
            <button onclick="window.print()" class="btn btn-primary btn-sm fw-semibold">
                <i class="fas fa-print me-1"></i> Cetak / Simpan PDF (A4)
            </button>
        </div>
    </div>
</div>

<div class="report-paper">
    <!-- Kop Surat Resmi PT. Visimedia Pratama Persada -->
    <div class="kop-header">
        <div class="kop-logo">
            <i class="fas fa-network-wired"></i>
        </div>
        <div class="flex-grow-1">
            <h1 class="kop-title"><?= htmlspecialchars($company_name) ?></h1>
            <div class="kop-sub"><?= htmlspecialchars($company_tagline) ?></div>
            <div class="kop-address">
                <?= htmlspecialchars($company_address) ?><br>
                Telp / Hotline NOC: <?= htmlspecialchars($company_phone) ?> | Email: <?= htmlspecialchars($company_email) ?>
            </div>
        </div>
    </div>

    <div class="kop-divider"></div>

    <!-- Judul Berita Acara -->
    <div class="doc-title-box">
        <div class="doc-title">BERITA ACARA & SURAT TUGAS PENANGANAN GANGGUAN (B2B)</div>
        <div class="doc-meta">
            Nomor Tiket: <strong class="text-primary font-monospace"><?= htmlspecialchars($ticket['ticket_code']) ?></strong> &bull; 
            Dokumen Ref: <span class="font-monospace">WO/VMI/<?= date('Ym', strtotime($ticket['created_at'])) ?>/<?= str_pad($ticket['id'], 4, '0', STR_PAD_LEFT) ?></span> &bull;
            Tanggal Terbit: <span><?= date('d/m/Y H:i') ?> WIB</span>
        </div>
    </div>

    <!-- 1. Informasi Klien & Sirkit -->
    <div class="section-title">
        <i class="fas fa-building text-primary"></i> I. INFORMASI PERUSAHAAN KLIEN &amp; SIRKIT LAYANAN
    </div>
    <table class="table-doc">
        <tr>
            <th>Perusahaan Klien</th>
            <td class="fw-bold" style="width: 28%;"><?= htmlspecialchars($ticket['company_name']) ?> (<?= htmlspecialchars($ticket['company_code']) ?>)</td>
            <th>Nomor Sirkit (CID)</th>
            <td class="fw-bold text-primary font-monospace" style="width: 28%;"><?= htmlspecialchars($ticket['circuit_id']) ?></td>
        </tr>
        <tr>
            <th>Jenis Layanan B2B</th>
            <td><?= htmlspecialchars($ticket['service_type']) ?></td>
            <th>Kapasitas Bandwidth</th>
            <td class="fw-semibold"><?= htmlspecialchars($ticket['client_bandwidth'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>PIC Pelapor Klien</th>
            <td><?= htmlspecialchars($ticket['reporter_name']) ?> (<?= htmlspecialchars($ticket['reporter_phone'] ?? '-') ?>)</td>
            <th>Email PIC</th>
            <td><?= htmlspecialchars($ticket['reporter_email'] ?? '-') ?></td>
        </tr>
        <tr>
            <th>Site / Lokasi Site</th>
            <td colspan="3"><?= htmlspecialchars($ticket['location']) ?></td>
        </tr>
    </table>

    <!-- 2. Parameter Gangguan & SLA -->
    <div class="section-title">
        <i class="fas fa-stopwatch text-warning"></i> II. PARAMETER GANGGUAN &amp; SERVICE LEVEL AGREEMENT (SLA)
    </div>
    <table class="table-doc">
        <tr>
            <th>Kategori Gangguan</th>
            <td style="width: 28%;"><?= htmlspecialchars($ticket['category_name']) ?></td>
            <th>Tingkat Prioritas SLA</th>
            <td style="width: 28%;"><span class="badge bg-<?= htmlspecialchars($ticket['priority_color']) ?>"><?= htmlspecialchars($ticket['priority_name']) ?> (<?= $ticket['sla_hours'] ?> Jam)</span></td>
        </tr>
        <tr>
            <th>Waktu Tiket Dibuka</th>
            <td><?= format_date_indo($ticket['created_at']) ?></td>
            <th>Target SLA Deadline</th>
            <td class="fw-bold text-danger"><?= format_date_indo($ticket['sla_deadline']) ?></td>
        </tr>
        <tr>
            <th>Waktu Selesai</th>
            <td><?= !empty($ticket['resolved_at']) ? format_date_indo($ticket['resolved_at']) : '-' ?></td>
            <th>Status Kepatuhan SLA</th>
            <td class="fw-bold">
                <?php if ($ticket['sla_status'] === 'within_sla'): ?>
                    <span class="text-success"><i class="fas fa-check-circle"></i> On-Time SLA (Tepat Waktu)</span>
                <?php elseif ($ticket['sla_status'] === 'breached'): ?>
                    <span class="text-danger"><i class="fas fa-times-circle"></i> Breached SLA (Melewati Batas)</span>
                <?php else: ?>
                    <span class="text-muted">Dalam Pengerjaan</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>Durasi Penanganan</th>
            <td><?= ($ticket['resolution_time_minutes'] > 0) ? format_duration_minutes($ticket['resolution_time_minutes']) : '-' ?></td>
            <th>Status Akhir Tiket</th>
            <td class="fw-bold text-uppercase"><?= htmlspecialchars($ticket['status']) ?></td>
        </tr>
    </table>

    <!-- 3. Keluhan & Troubleshooting -->
    <div class="avoid-break">
        <div class="section-title">
            <i class="fas fa-tools text-success"></i> III. KELUHAN AWAL &amp; TINDAKAN PERBAIKAN TEKNIS (TROUBLESHOOTING)
        </div>
        <div class="box-troubleshoot">
            <div class="item-title">Keluhan Awal Klien:</div>
            <div class="item-content"><?= nl2br(htmlspecialchars($ticket['description'])) ?></div>

            <?php if (!empty($ticket['root_cause'])): ?>
                <div class="item-title mt-1 pt-1 border-top">Penyebab Gangguan (Root Cause):</div>
                <div class="item-content"><?= htmlspecialchars($ticket['root_cause']) ?></div>
            <?php endif; ?>

            <?php if (!empty($ticket['action_taken'])): ?>
                <div class="item-title mt-1 pt-1 border-top">Tindakan Perbaikan yang Dilakukan:</div>
                <div class="item-content"><?= htmlspecialchars($ticket['action_taken']) ?></div>
            <?php endif; ?>

            <?php if (!empty($ticket['technician_notes'])): ?>
                <div class="item-title mt-1 pt-1 border-top">Catatan Teknis Field Engineer:</div>
                <div class="item-content"><?= nl2br(htmlspecialchars($ticket['technician_notes'])) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4. Log Riwayat Penanganan -->
    <div class="avoid-break">
        <div class="section-title">
            <i class="fas fa-history text-secondary"></i> IV. LOG RIWAYAT PENANGANAN (AUDIT TRAIL)
        </div>
        <table class="table-doc">
            <thead>
                <tr style="background-color: #f8fafc;">
                    <th style="width: 22%;">Waktu Eksekusi</th>
                    <th style="width: 24%;">Aktor / Eksekutor</th>
                    <th style="width: 18%;">Aksi</th>
                    <th style="width: 36%;">Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= format_date_indo($log['created_at']) ?></td>
                        <td><?= htmlspecialchars($log['user_name']) ?> (<?= ucfirst($log['user_role']) ?>)</td>
                        <td class="fw-semibold"><?= htmlspecialchars($log['action']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($log['note'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 5. Validasi & Pengesahan Dokumen Elektronik (Tanpa Tanda Tangan Fisik) -->
    <div class="avoid-break">
        <div class="verification-panel">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="fw-bold text-dark" style="font-size: 7.5pt;">
                    <i class="fas fa-certificate text-primary me-1"></i> VALIDASI &amp; KEABSAHAN DOKUMEN ELEKTRONIK
                </div>
                <div class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 6.8pt;">
                    <i class="fas fa-check-double me-1"></i> Terverifikasi Sistem
                </div>
            </div>
            <div class="row g-2 text-dark" style="font-size: 7.2pt;">
                <div class="col-4 border-end">
                    <span class="text-muted d-block">Pelapor Klien:</span>
                    <strong><?= htmlspecialchars($ticket['reporter_name']) ?></strong>
                    <div class="text-muted"><?= htmlspecialchars($ticket['company_name']) ?></div>
                </div>
                <div class="col-4 border-end">
                    <span class="text-muted d-block">Field Engineer:</span>
                    <strong><?= htmlspecialchars($ticket['technician_name'] ?? 'Field Engineer') ?></strong>
                    <div class="text-muted"><?= htmlspecialchars($company_name) ?></div>
                </div>
                <div class="col-4">
                    <span class="text-muted d-block">Dispatcher / Supervisor:</span>
                    <strong>Ir. Hendra Wijaya, M.Kom</strong>
                    <div class="text-muted">Head of NOC &amp; Operations</div>
                </div>
            </div>
        </div>

        <div class="doc-footer">
            Dokumen Berita Acara ini diterbitkan secara otomatis dan sah melalui Sistem Trouble Ticketing B2B &bull; <?= htmlspecialchars($company_name) ?>.
        </div>
    </div>
</div>

</body>
</html>
