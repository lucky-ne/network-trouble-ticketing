<?php
/**
 * Evaluasi & Rekapitulasi Kinerja Field Engineer
 * Portal Manajemen Service Delivery - PT. Visimedia Pratama Persada
 */
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['manager', 'admin', 'helpdesk']);

$pdo = get_db();

$bulan = $_GET['bulan'] ?? date('m');
$tahun = $_GET['tahun'] ?? date('Y');

$bulan_nama = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    'all' => 'Semua Bulan'
];

$sql_tek = "SELECT 
    u.id,
    u.name,
    u.nip,
    u.department,
    u.phone,
    u.email,
    COUNT(t.id) AS total_assigned,
    SUM(CASE WHEN t.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) AS total_done,
    SUM(CASE WHEN t.status IN ('open', 'assigned', 'in_progress') THEN 1 ELSE 0 END) AS total_active,
    SUM(CASE WHEN t.sla_status = 'within_sla' THEN 1 ELSE 0 END) AS on_time_sla,
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
GROUP BY u.id, u.name, u.nip, u.department, u.phone, u.email
ORDER BY total_done DESC, on_time_sla DESC";

$stmt = $pdo->prepare($sql_tek);
$stmt->execute($params);
$tek_performance = $stmt->fetchAll();

// Hitung Agregasi KPI
$total_engineers = count($tek_performance);
$grand_assigned  = 0;
$grand_done      = 0;
$grand_ontime    = 0;
$grand_breached  = 0;
$total_minutes   = 0;
$engineers_with_time = 0;

foreach ($tek_performance as $tp) {
    $grand_assigned += (int)$tp['total_assigned'];
    $grand_done     += (int)$tp['total_done'];
    $grand_ontime   += (int)$tp['on_time_sla'];
    $grand_breached += (int)$tp['breached_sla'];
    if ($tp['avg_minutes'] !== null) {
        $total_minutes += (float)$tp['avg_minutes'];
        $engineers_with_time++;
    }
}

$grand_sla_rate = ($grand_done > 0) ? round(($grand_ontime / $grand_done) * 100, 1) : 100;
$overall_avg_minutes = ($engineers_with_time > 0) ? round($total_minutes / $engineers_with_time) : 0;

$page_title = 'Kinerja & Produktivitas Field Engineer';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header Halaman -->
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-user-check text-primary me-2"></i>Evaluasi & Rekapitulasi Kinerja Field Engineer
        </h4>
        <p class="text-secondary small mb-0">Pantau produktivitas penugasan, kepatuhan batas waktu SLA, dan rata-rata durasi perbaikan (MTTR) teknisi lapangan.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('manager/cetak_kinerja_teknisi.php?bulan=' . urlencode($bulan) . '&tahun=' . urlencode($tahun)) ?>" target="_blank" class="btn btn-success btn-sm fw-semibold">
            <i class="fas fa-print me-1"></i> Cetak Dokumen Rekap A4 (PDF)
        </a>
        <a href="<?= base_url('manager/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Dashboard SLA
        </a>
    </div>
</div>

<!-- Kartu Filter Periode -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label text-muted small mb-1 fw-semibold"><i class="fas fa-calendar-alt me-1"></i> Filter Bulan</label>
                <select name="bulan" class="form-select form-select-sm">
                    <?php foreach ($bulan_nama as $k => $v): ?>
                        <option value="<?= $k ?>" <?= ($bulan === $k) ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small mb-1 fw-semibold"><i class="fas fa-calendar me-1"></i> Filter Tahun</label>
                <select name="tahun" class="form-select form-select-sm">
                    <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= ($tahun == $y) ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold">
                    <i class="fas fa-filter me-1"></i> Terapkan Filter
                </button>
                <a href="<?= base_url('manager/kinerja_teknisi.php') ?>" class="btn btn-light btn-sm border ms-1">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Kartu KPI Ringkasan Field Engineer -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-primary">
            <div class="stat-label">Total Field Engineer Aktif</div>
            <div class="stat-value text-primary"><?= $total_engineers ?></div>
            <div class="stat-desc">Teknisi jaringan operasional</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-info">
            <div class="stat-label">Total Tiket Ditangani</div>
            <div class="stat-value text-info"><?= $grand_assigned ?> Tiket</div>
            <div class="stat-desc">Selesai: <strong><?= $grand_done ?></strong> | Aktif: <strong><?= $grand_assigned - $grand_done ?></strong></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-success">
            <div class="stat-label">Kepatuhan SLA (On-Time)</div>
            <div class="stat-value text-success"><?= $grand_sla_rate ?>%</div>
            <div class="stat-desc"><?= $grand_ontime ?> On-Time / <?= $grand_breached ?> Breached</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-warning">
            <div class="stat-label">Rata-rata MTTR Tim</div>
            <div class="stat-value text-warning"><?= format_duration_minutes($overall_avg_minutes) ?></div>
            <div class="stat-desc">Durasi rata-rata penyelesaian</div>
        </div>
    </div>
</div>

<!-- Tabel Evaluasi & Matriks Produktivitas Teknisi -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fas fa-users-cog text-primary me-2"></i>Matriks Produktivitas & Tingkat Kepatuhan SLA Teknisi (Periode: <?= $bulan_nama[$bulan] ?? 'Semua' ?> <?= htmlspecialchars($tahun) ?>)</span>
        <span class="badge bg-light text-dark border">Total: <?= count($tek_performance) ?> Engineer</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-b2b table-hover align-middle datatable w-100">
                <thead>
                    <tr>
                        <th style="width: 50px;">No</th>
                        <th>Field / Network Engineer</th>
                        <th>Kontak & WhatsApp</th>
                        <th class="text-center">Ditugaskan</th>
                        <th class="text-center">Sedang Proses</th>
                        <th class="text-center">Selesai (Resolved)</th>
                        <th class="text-center">On-Time SLA</th>
                        <th class="text-center">Breached (Late)</th>
                        <th>Avg. MTTR</th>
                        <th class="text-center">SLA Compliance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1; 
                    foreach ($tek_performance as $tek): 
                        $rate = ($tek['total_done'] > 0) ? round(($tek['on_time_sla'] / $tek['total_done']) * 100, 1) : 100;
                        $rate_badge = ($rate >= 95) ? 'bg-success' : (($rate >= 80) ? 'bg-warning text-dark' : 'bg-danger');
                    ?>
                        <tr>
                            <td class="text-center fw-semibold text-secondary"><?= $no++ ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($tek['name']) ?></div>
                                <small class="text-muted"><i class="fas fa-id-badge me-1"></i><?= htmlspecialchars($tek['nip']) ?> &bull; <?= htmlspecialchars($tek['department']) ?></small>
                            </td>
                            <td>
                                <div class="small"><i class="fas fa-phone text-success me-1"></i><?= htmlspecialchars($tek['phone'] ?? '-') ?></div>
                                <small class="text-muted"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($tek['email']) ?></small>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border fs-6"><?= $tek['total_assigned'] ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($tek['total_active'] > 0): ?>
                                    <span class="badge bg-warning text-dark"><?= $tek['total_active'] ?> Tiket</span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="fw-bold text-success fs-6"><?= $tek['total_done'] ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-success-subtle text-success border border-success-subtle"><?= $tek['on_time_sla'] ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($tek['breached_sla'] > 0): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?= $tek['breached_sla'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= format_duration_minutes($tek['avg_minutes']) ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex flex-column align-items-center">
                                    <span class="badge <?= $rate_badge ?> px-2 py-1 fw-bold"><?= $rate ?>%</span>
                                    <div class="progress w-100 mt-1" style="height: 4px; max-width: 70px;">
                                        <div class="progress-bar <?= ($rate >= 95 ? 'bg-success' : ($rate >= 80 ? 'bg-warning' : 'bg-danger')) ?>" role="progressbar" style="width: <?= $rate ?>%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
