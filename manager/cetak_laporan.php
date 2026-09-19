<?php
/**
 * Laporan Bulanan Rekapitulasi Gangguan Jaringan B2B & Kepatuhan SLA
 * PT. Visimedia Pratama Persada
 * Format Resmi Cetak PDF / Hardcopy untuk Lampiran Dokumen KKP & Evaluasi Kontrak Klien
 */
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['manager', 'helpdesk', 'admin']);

$pdo = get_db();

// Filter Parameter
$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');
$client_id = (int)($_GET['client_id'] ?? 0);
$status = $_GET['status'] ?? '';

$clients = $pdo->query("SELECT id, company_name, company_code FROM clients ORDER BY company_name ASC")->fetchAll();

$sql = "SELECT 
    t.*, 
    c.name AS category_name, 
    p.name AS priority_name, 
    p.sla_hours,
    cl.company_name,
    cl.company_code,
    u_pelapor.name AS reporter_name,
    u_tek.name AS technician_name
FROM tickets t
JOIN categories c ON t.category_id = c.id
JOIN priorities p ON t.priority_id = p.id
JOIN clients cl ON t.client_id = cl.id
JOIN users u_pelapor ON t.user_id = u_pelapor.id
LEFT JOIN users u_tek ON t.technician_id = u_tek.id
WHERE 1=1 ";

$params = [];

if (!empty($bulan) && $bulan !== 'all') {
    $sql .= " AND MONTH(t.created_at) = ? ";
    $params[] = $bulan;
}
if (!empty($tahun)) {
    $sql .= " AND YEAR(t.created_at) = ? ";
    $params[] = $tahun;
}
if (!empty($client_id)) {
    $sql .= " AND t.client_id = ? ";
    $params[] = $client_id;
}
if (!empty($status)) {
    $sql .= " AND t.status = ? ";
    $params[] = $status;
}

$sql .= " ORDER BY t.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Hitung Ringkasan SLA
$total_t = count($tickets);
$total_resolved = 0;
$total_within_sla = 0;
$total_exempted = 0;
$total_breached = 0;
$total_minutes = 0;

foreach ($tickets as $t) {
    if (in_array($t['status'], ['resolved', 'closed'])) {
        $total_resolved++;
        $total_minutes += (int)$t['resolution_time_minutes'];
        if ($t['sla_status'] === 'within_sla') {
            $total_within_sla++;
        } elseif ($t['sla_status'] === 'exempted') {
            $total_exempted++;
        } elseif ($t['sla_status'] === 'breached') {
            $total_breached++;
        }
    }
}

// Persentase SLA Kepatuhan: Tiket yang berstatus Exempted tidak dihitung sebagai penalti denda
$chargeable_resolved = $total_resolved - $total_exempted;
$sla_rate = ($chargeable_resolved > 0) ? round(($total_within_sla / $chargeable_resolved) * 100, 1) : 100;
$avg_duration = ($total_resolved > 0) ? round($total_minutes / $total_resolved) : 0;

$bulan_nama = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    'all' => 'Semua Bulan'
];

$company_name    = get_setting('company_name', 'PT. Visimedia Pratama Persada');
$company_tagline = get_setting('company_tagline', 'B2B Network Provider & Managed Service Solutions');
$company_address = get_setting('company_address', 'Cyber 2 Tower Lt. 12, Jl. HR Rasuna Said Blok X-5, Jakarta Selatan 12950');
$company_phone   = get_setting('company_phone', '(021) 5299-1234 / Hotline NOC: 0811-9876-543');
$company_email   = get_setting('company_email', 'noc@visimedia.co.id');

$page_title = 'Rekap Laporan Bulanan SLA Jaringan B2B';
include __DIR__ . '/../includes/header.php';
?>

<style>
.report-paper {
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    padding: 25px 30px;
    margin: 15px auto;
}

.kop-divider {
    border-top: 2px solid #0f172a;
    border-bottom: 1px solid #0f172a;
    height: 3px;
    margin: 8px 0 15px 0;
}

@media print {
    @page {
        size: A4 landscape;
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
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
    }
    tr {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
}
</style>

<!-- Filter Pencarian & Tombol Cetak (Non-Print) -->
<div class="card shadow-sm mb-4 no-print">
    <div class="card-header bg-light">
        <i class="fas fa-filter text-primary me-2"></i>Filter Laporan Bulanan Performa SLA B2B
    </div>
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted small mb-1">Perusahaan Klien B2B</label>
                <select name="client_id" class="form-select form-select-sm">
                    <option value="">-- Seluruh Klien B2B --</option>
                    <?php foreach ($clients as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= ($client_id == $cl['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small mb-1">Bulan</label>
                <select name="bulan" class="form-select form-select-sm">
                    <?php foreach ($bulan_nama as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($bulan == $k) ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small mb-1">Tahun</label>
                <select name="tahun" class="form-select form-select-sm">
                    <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= ($tahun == $y) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small mb-1">Status Tiket</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Semua --</option>
                    <option value="resolved" <?= ($status === 'resolved') ? 'selected' : '' ?>>Resolved</option>
                    <option value="closed" <?= ($status === 'closed') ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fas fa-search me-1"></i> Tampilkan
                </button>
                <button type="button" onclick="window.print()" class="btn btn-success btn-sm fw-semibold">
                    <i class="fas fa-print me-1"></i> Cetak PDF
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lembar Laporan Formal Siap Cetak -->
<div class="report-paper">
    <!-- Kop Surat Resmi -->
    <div class="row align-items-center">
        <div class="col-2 text-center">
            <div class="p-3 bg-dark text-white rounded-2 d-inline-block">
                <i class="fas fa-network-wired fa-2x"></i>
            </div>
        </div>
        <div class="col-10">
            <h4 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($company_name) ?></h4>
            <div class="fw-semibold text-primary small mb-1"><?= htmlspecialchars($company_tagline) ?></div>
            <div class="text-muted" style="font-size: 0.78rem; line-height: 1.3;">
                <?= htmlspecialchars($company_address) ?><br>
                Telp / Hotline NOC: <?= htmlspecialchars($company_phone) ?> | Email: <?= htmlspecialchars($company_email) ?>
            </div>
        </div>
    </div>

    <div class="kop-divider"></div>

    <!-- Judul Laporan -->
    <div class="text-center mb-3">
        <h5 class="fw-bold text-dark text-uppercase mb-1">
            LAPORAN REKAPITULASI PENANGANAN GANGGUAN JARINGAN & KEPATUHAN SLA
        </h5>
        <div class="small text-muted">
            Periode: <strong><?= $bulan_nama[$bulan] ?? 'Semua Bulan' ?> <?= $tahun ?></strong> &bull; 
            Klasifikasi: <strong>Layanan Managed Network B2B</strong>
        </div>
    </div>

    <!-- Ringkasan Statistik KPI -->
    <div class="row g-2 mb-3">
        <div class="col-3">
            <div class="border rounded p-2 bg-light text-center">
                <div class="text-muted small">Total Tiket Gangguan</div>
                <div class="fw-bold text-dark fs-5"><?= $total_t ?></div>
            </div>
        </div>
        <div class="col-3">
            <div class="border rounded p-2 bg-light text-center">
                <div class="text-muted small">Tiket Selesai (Resolved/Closed)</div>
                <div class="fw-bold text-success fs-5"><?= $total_resolved ?></div>
            </div>
        </div>
        <div class="col-3">
            <div class="border rounded p-2 bg-light text-center">
                <div class="text-muted small">Tepat Waktu (On-Time SLA)</div>
                <div class="fw-bold text-primary fs-5"><?= $total_within_sla ?> (<?= $sla_rate ?>%)</div>
            </div>
        </div>
        <div class="col-3">
            <div class="border rounded p-2 bg-light text-center">
                <div class="text-muted small">Rata-rata MTTR</div>
                <div class="fw-bold text-dark fs-5"><?= format_duration_minutes($avg_duration) ?></div>
            </div>
        </div>
    </div>

    <!-- Tabel Data Tiket -->
    <table class="table table-bordered table-sm align-middle w-100" style="font-size: 0.8rem;">
        <thead class="table-light text-center">
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 12%;">No. Tiket</th>
                <th style="width: 16%;">Perusahaan Klien</th>
                <th style="width: 10%;">Sirkit (CID)</th>
                <th style="width: 18%;">Kendala & Site</th>
                <th style="width: 8%;">Prioritas</th>
                <th style="width: 12%;">Waktu Selesai</th>
                <th style="width: 8%;">Durasi</th>
                <th style="width: 12%;">Status SLA</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($tickets)): ?>
                <tr>
                    <td colspan="9" class="text-center py-3 text-muted">Tidak ada data tiket gangguan untuk periode filter ini.</td>
                </tr>
            <?php else: ?>
                <?php $no = 1; foreach ($tickets as $t): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="font-monospace fw-bold"><?= htmlspecialchars($t['ticket_code']) ?></td>
                        <td>
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($t['company_name']) ?></div>
                            <small class="text-muted">PIC: <?= htmlspecialchars($t['reporter_name']) ?></small>
                        </td>
                        <td class="font-monospace text-center"><?= htmlspecialchars($t['circuit_id']) ?></td>
                        <td>
                            <div><?= htmlspecialchars($t['title']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($t['location']) ?></small>
                        </td>
                        <td class="text-center"><?= htmlspecialchars($t['priority_name']) ?></td>
                        <td><?= format_date_indo($t['resolved_at']) ?></td>
                        <td class="text-center"><?= format_duration_minutes($t['resolution_time_minutes']) ?></td>
                        <td class="text-center fw-bold">
                            <?php if ($t['sla_status'] === 'exempted'): ?>
                                <span style="color:#7c3aed;">
                                    <i class="fas fa-shield-alt me-1"></i>Exempted
                                </span>
                                <?php if (!empty($t['sla_exemption_reason'])): ?>
                                    <div style="font-size: 0.68rem; font-weight: normal; color: #6b7280;"><?= htmlspecialchars($t['sla_exemption_reason']) ?></div>
                                <?php endif; ?>
                            <?php elseif ($t['sla_status'] === 'within_sla'): ?>
                                <span class="text-success">On-Time</span>
                            <?php elseif ($t['sla_status'] === 'breached'): ?>
                                <span class="text-danger">Breached</span>
                            <?php else: ?>
                                <span class="text-muted"><?= ucfirst($t['status']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Validasi & Pengesahan Dokumen Elektronik -->
    <div class="mt-3 p-3 bg-light border rounded" style="font-size: 8pt; page-break-inside: avoid;">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <strong><i class="fas fa-clipboard-check text-primary me-1"></i> Catatan Evaluasi Service Delivery:</strong>
            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 7pt;">
                <i class="fas fa-check-double me-1"></i> Terverifikasi Digital
            </span>
        </div>
        <p class="mb-2 text-secondary">
            Tingkat kepatuhan SLA bulan ini mencapai <strong><?= $sla_rate ?>%</strong>. Seluruh penanganan eskalasi dan tiket gangguan sirkit klien B2B telah ditangani sesuai SOP B2B &bull; PT. Visimedia Pratama Persada.
        </p>
        <div class="d-flex justify-content-between text-muted border-top pt-2" style="font-size: 7.5pt;">
            <span>Tanggal Penerbitan: <strong><?= date('d') ?> <?= $bulan_nama[date('m')] ?> <?= date('Y') ?></strong> (Pukul <?= date('H:i') ?> WIB)</span>
            <span>Penanggung Jawab: <strong>Ir. Hendra Wijaya, M.Kom</strong> (Head of NOC &amp; Operations)</span>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
