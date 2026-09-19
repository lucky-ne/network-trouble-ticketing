<?php
/**
 * Laporan Rekapitulasi Kinerja & Kepatuhan SLA Field Engineer B2B
 * PT. Visimedia Pratama Persada
 * Format Resmi Cetak Dokumen A4 Portrait
 */
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['manager', 'helpdesk', 'admin']);

$pdo = get_db();

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

$sql_tek = "SELECT 
    u.id,
    u.name,
    u.department,
    u.phone,
    COUNT(t.id) AS total_assigned,
    SUM(CASE WHEN t.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) AS total_done,
    SUM(CASE WHEN t.sla_status = 'within_sla' THEN 1 ELSE 0 END) AS on_time_sla,
    SUM(CASE WHEN t.sla_status = 'exempted' THEN 1 ELSE 0 END) AS exempted_sla,
    SUM(CASE WHEN t.sla_status = 'breached' THEN 1 ELSE 0 END) AS breached_sla,
    AVG(CASE WHEN t.status IN ('resolved', 'closed') THEN t.resolution_time_minutes ELSE NULL END) AS avg_minutes
FROM users u
LEFT JOIN tickets t ON u.id = t.technician_id ";

$params = [];
if (!empty($bulan) && $bulan !== 'all') {
    $sql_tek .= " AND MONTH(t.created_at) = ? ";
    $params[] = $bulan;
}
if (!empty($tahun)) {
    $sql_tek .= " AND YEAR(t.created_at) = ? ";
    $params[] = $tahun;
}
$sql_tek .= " WHERE u.role = 'teknisi'
GROUP BY u.id, u.name, u.department, u.phone
ORDER BY total_done DESC, on_time_sla DESC";

$stmt = $pdo->prepare($sql_tek);
$stmt->execute($params);
$tek_performance = $stmt->fetchAll();

$company_name    = get_setting('company_name', 'PT. Visimedia Pratama Persada');
$company_tagline = get_setting('company_tagline', 'B2B Network Provider & Managed Service Solutions');
$company_address = get_setting('company_address', 'Cyber 2 Tower Lt. 12, Jl. HR Rasuna Said Blok X-5, Jakarta Selatan 12950');
$company_phone   = get_setting('company_phone', '(021) 5299-1234 / Hotline NOC: 0811-9876-543');
$company_email   = get_setting('company_email', 'noc@visimedia.co.id');

$bulan_nama = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    'all' => 'Semua Bulan'
];

$page_title = 'Laporan Kinerja Field Engineer B2B';
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
            margin-bottom: 12px;
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

        .table-doc {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 8pt;
        }

        .table-doc th, .table-doc td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
            vertical-align: middle;
        }

        .table-doc th {
            background-color: #f8fafc;
            color: #334155;
            font-weight: 600;
        }

        .signature-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            font-size: 8pt;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .doc-footer {
            margin-top: 15px;
            padding-top: 6px;
            border-top: 1px dashed #cbd5e1;
            text-align: center;
            font-size: 7pt;
            color: #94a3b8;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm 10mm 8mm 10mm;
            }

            html, body {
                background: #ffffff !important;
                font-size: 8pt !important;
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

            tr {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

<div class="container no-print mt-3" style="max-width: 210mm;">
    <div class="card shadow-sm border mb-3">
        <div class="card-body p-2 px-3">
            <form method="GET" action="" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label text-muted small mb-1">Pilih Bulan</label>
                    <select name="bulan" class="form-select form-select-sm">
                        <?php foreach ($bulan_nama as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($bulan == $k) ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small mb-1">Pilih Tahun</label>
                    <select name="tahun" class="form-select form-select-sm">
                        <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?= $y ?>" <?= ($tahun == $y) ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="fas fa-filter me-1"></i> Tampilkan
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-success btn-sm fw-semibold">
                        <i class="fas fa-print me-1"></i> Cetak PDF (A4)
                    </button>
                    <a href="<?= base_url('manager/kinerja_teknisi.php') ?>" class="btn btn-outline-secondary btn-sm" title="Kembali ke Halaman Kinerja">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="report-paper">
    <!-- Kop Surat -->
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

    <div class="doc-title-box">
        <div class="doc-title">LAPORAN REKAPITULASI KINERJA &amp; KEPATUHAN SLA FIELD ENGINEER</div>
        <div class="doc-meta">
            Periode: <strong><?= $bulan_nama[$bulan] ?? 'Semua Bulan' ?> <?= $tahun ?></strong> &bull; 
            Divisi: <strong>B2B Network Engineering &amp; Operations</strong> &bull;
            Tanggal Cetak: <span><?= date('d/m/Y H:i') ?> WIB</span>
        </div>
    </div>

    <table class="table-doc">
        <thead class="text-center">
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 28%; text-align: left;">Nama Field Engineer</th>
                <th style="width: 12%;">Ditugaskan</th>
                <th style="width: 12%;">Selesai</th>
                <th style="width: 11%;">On-Time</th>
                <th style="width: 11%;">Exempted</th>
                <th style="width: 10%;">Breached</th>
                <th style="width: 11%;">SLA Rate (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($tek_performance as $tp): ?>
                <?php 
                $done = (int)$tp['total_done'];
                $ontime = (int)$tp['on_time_sla'];
                $exempted = (int)$tp['exempted_sla'];
                $chargeable = $done - $exempted;
                $rate = ($chargeable > 0) ? round(($ontime / $chargeable) * 100, 1) : 100;
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($tp['name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($tp['department']) ?></small>
                    </td>
                    <td class="text-center"><?= $tp['total_assigned'] ?></td>
                    <td class="text-center fw-bold text-success"><?= $done ?></td>
                    <td class="text-center text-success"><?= $ontime ?></td>
                    <td class="text-center" style="color:#7c3aed;"><?= $exempted ?></td>
                    <td class="text-center text-danger"><?= $tp['breached_sla'] ?></td>
                    <td class="text-center fw-bold <?= ($rate >= 90) ? 'text-success' : 'text-danger' ?>"><?= $rate ?>%</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="mt-3 p-3 bg-light border rounded" style="font-size: 7.5pt; page-break-inside: avoid;">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <strong><i class="fas fa-clipboard-check text-primary me-1"></i> Catatan Evaluasi Kepala Divisi NOC:</strong>
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 6.8pt;">
                <i class="fas fa-check-double me-1"></i> Terverifikasi Digital
            </span>
        </div>
        <p class="mb-2 text-secondary">
            Evaluasi performa Field Engineer dalam menangani insiden link sirkit klien B2B telah sesuai dengan Service Level Agreement (SLA) operasional PT. Visimedia Pratama Persada.
        </p>
        <div class="d-flex justify-content-between text-muted border-top pt-2" style="font-size: 7pt;">
            <span>Diterbitkan: <strong><?= date('d/m/Y H:i') ?> WIB</strong></span>
            <span>Penanggung Jawab: <strong>Ir. Hendra Wijaya, M.Kom</strong> (Head of NOC &amp; Operations)</span>
        </div>
    </div>

    <div class="doc-footer">
        Dokumen ini diterbitkan secara elektronik oleh Sistem Informasi Network Trouble Ticketing B2B - <?= htmlspecialchars($company_name) ?>.
    </div>
</div>

</body>
</html>
