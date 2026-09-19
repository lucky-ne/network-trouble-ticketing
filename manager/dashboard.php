<?php
/**
 * Dashboard Eksekutif SLA & Monitoring B2B - PT. Visimedia Pratama Persada
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['manager']);

$pdo = get_db();

// 1. STATISTIK UTAMA KPI SLA
$kpi_query = $pdo->query("SELECT 
    COUNT(*) AS total_tickets,
    SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) AS total_resolved,
    SUM(CASE WHEN sla_status = 'within_sla' THEN 1 ELSE 0 END) AS total_within_sla,
    SUM(CASE WHEN sla_status = 'exempted' THEN 1 ELSE 0 END) AS total_exempted,
    SUM(CASE WHEN sla_status = 'breached' THEN 1 ELSE 0 END) AS total_breached,
    AVG(CASE WHEN status IN ('resolved', 'closed') THEN resolution_time_minutes ELSE NULL END) AS avg_resolution_time
FROM tickets");
$kpi = $kpi_query->fetch();

$total_completed = (int)$kpi['total_resolved'];
$within_sla = (int)$kpi['total_within_sla'];
$exempted_sla = (int)$kpi['total_exempted'];
$breached_sla = (int)$kpi['total_breached'];
$avg_mins = round((float)($kpi['avg_resolution_time'] ?? 0));

// SLA rate: Tiket exempted tidak dihitung sebagai penalti denda
$chargeable_completed = $total_completed - $exempted_sla;
$sla_compliance_rate = ($chargeable_completed > 0) ? round(($within_sla / $chargeable_completed) * 100, 1) : 100;

// 2. DATA PERFORMA SLA PER PERUSAHAAN KLIEN B2B
$client_sla_query = $pdo->query("SELECT 
    cl.id,
    cl.company_name,
    cl.company_code,
    cl.circuit_id,
    cl.service_type,
    cl.sla_target_pct,
    COUNT(t.id) AS total_tickets,
    SUM(CASE WHEN t.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) AS resolved_tickets,
    SUM(CASE WHEN t.sla_status = 'within_sla' THEN 1 ELSE 0 END) AS on_time_tickets,
    SUM(CASE WHEN t.sla_status = 'exempted' THEN 1 ELSE 0 END) AS exempted_tickets,
    SUM(CASE WHEN t.sla_status = 'breached' THEN 1 ELSE 0 END) AS breached_tickets,
    AVG(CASE WHEN t.status IN ('resolved', 'closed') THEN t.resolution_time_minutes ELSE NULL END) AS avg_mins
FROM clients cl
LEFT JOIN tickets t ON cl.id = t.client_id
GROUP BY cl.id, cl.company_name, cl.company_code, cl.circuit_id, cl.service_type, cl.sla_target_pct
ORDER BY cl.company_name ASC");
$client_sla = $client_sla_query->fetchAll();

// 3. DATA GRAFIK KATEGORI MASALAH
$cat_query = $pdo->query("SELECT c.name, COUNT(t.id) as total 
FROM categories c 
LEFT JOIN tickets t ON c.id = t.category_id 
GROUP BY c.id, c.name");
$cat_data = $cat_query->fetchAll();

$cat_labels = [];
$cat_counts = [];
foreach ($cat_data as $cd) {
    $cat_labels[] = $cd['name'];
    $cat_counts[] = (int)$cd['total'];
}

// 4. DATA PERFORMA TEKNISI
$tek_query = $pdo->query("SELECT 
    u.name,
    COUNT(t.id) AS total_assigned,
    SUM(CASE WHEN t.status IN ('resolved', 'closed') THEN 1 ELSE 0 END) AS total_done,
    SUM(CASE WHEN t.sla_status = 'within_sla' THEN 1 ELSE 0 END) AS on_time_sla,
    SUM(CASE WHEN t.sla_status = 'exempted' THEN 1 ELSE 0 END) AS exempted_sla,
    SUM(CASE WHEN t.sla_status = 'breached' THEN 1 ELSE 0 END) AS breached_sla,
    AVG(CASE WHEN t.status IN ('resolved', 'closed') THEN t.resolution_time_minutes ELSE NULL END) AS avg_minutes
FROM users u
LEFT JOIN tickets t ON u.id = t.technician_id
WHERE u.role = 'teknisi'
GROUP BY u.id, u.name");
$tek_performance = $tek_query->fetchAll();

$page_title = 'Executive SLA & NOC Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-chart-pie text-primary me-2"></i>Executive SLA Performance & Corporate NOC Dashboard
        </h4>
        <p class="text-secondary small mb-0">Indikator performa kunci (KPI), kepatuhan SLA jaringan B2B, dan evaluasi MTTR PT. Visimedia Pratama Persada.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('manager/kinerja_teknisi.php') ?>" class="btn btn-outline-secondary btn-sm fw-semibold">
            <i class="fas fa-user-check me-1"></i> Rekap Kinerja Teknisi
        </a>
        <a href="<?= base_url('manager/cetak_laporan.php') ?>" class="btn btn-primary btn-sm fw-semibold">
            <i class="fas fa-file-invoice me-1"></i> Cetak Laporan Bulanan SLA (PDF)
        </a>
    </div>
</div>

<!-- Kartu KPI Utama -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-success">
            <div class="stat-label">SLA Compliance Rate</div>
            <div class="stat-value text-success"><?= $sla_compliance_rate ?>%</div>
            <div class="stat-desc">Target Kontrak: &ge; 99.0%</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-primary">
            <div class="stat-label">Avg. MTTR (Waktu Perbaikan)</div>
            <div class="stat-value text-primary"><?= format_duration_minutes($avg_mins) ?></div>
            <div class="stat-desc">Rata-rata durasi penanganan link</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-info">
            <div class="stat-label">Tiket Selesai On-Time</div>
            <div class="stat-value text-info"><?= $within_sla ?> / <?= $total_completed ?></div>
            <div class="stat-desc">Kepatuhan waktu SLA terverifikasi</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-danger">
            <div class="stat-label">Tiket Breached (Terlambat)</div>
            <div class="stat-value text-danger"><?= $breached_sla ?></div>
            <div class="stat-desc">Melewati batas waktu SLA</div>
        </div>
    </div>
</div>

<!-- Tabel Kepatuhan SLA Per Perusahaan Klien B2B -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-building text-primary me-2"></i>Kepatuhan SLA per Perusahaan Klien Korporat</span>
        <span class="badge bg-light text-dark border">Total: <?= count($client_sla) ?> Klien Terdaftar</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-b2b table-hover align-middle datatable w-100">
                <thead>
                    <tr>
                        <th>Perusahaan Klien</th>
                        <th style="width: 140px;">Sirkit (CID)</th>
                        <th>Jenis Layanan B2B</th>
                        <th style="width: 95px;" class="text-center">Target SLA</th>
                        <th style="width: 90px;" class="text-center">Total Tiket</th>
                        <th style="width: 90px;" class="text-center">On-Time</th>
                        <th style="width: 90px;" class="text-center">Breached</th>
                        <th style="width: 120px;">Rata-rata MTTR</th>
                        <th style="width: 140px;">Realisasi SLA %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($client_sla as $cs): ?>
                        <?php 
                        $c_resolved = (int)$cs['resolved_tickets'];
                        $c_ontime = (int)$cs['on_time_tickets'];
                        $c_rate = ($c_resolved > 0) ? round(($c_ontime / $c_resolved) * 100, 1) : 100;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($cs['company_name']) ?></div>
                                <small class="text-secondary"><?= htmlspecialchars($cs['company_code']) ?></small>
                            </td>
                            <td>
                                <span class="circuit-badge"><?= htmlspecialchars($cs['circuit_id']) ?></span>
                            </td>
                            <td>
                                <span class="small text-dark"><?= htmlspecialchars($cs['service_type']) ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info text-dark"><?= $cs['sla_target_pct'] ?>%</span>
                            </td>
                            <td class="text-center fw-bold"><?= $cs['total_tickets'] ?></td>
                            <td class="text-center text-success fw-bold"><?= $c_ontime ?></td>
                            <td class="text-center text-danger fw-bold"><?= $cs['breached_tickets'] ?></td>
                            <td><?= format_duration_minutes($cs['avg_mins']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold <?= ($c_rate >= 90) ? 'text-success' : 'text-danger' ?>"><?= $c_rate ?>%</span>
                                    <div class="progress flex-grow-1" style="height: 6px;">
                                        <div class="progress-bar <?= ($c_rate >= 90) ? 'bg-success' : 'bg-danger' ?>" style="width: <?= $c_rate ?>%;"></div>
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

<div class="row g-4 mb-4">
    <!-- Grafik Distribusi Kategori Kendala -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <i class="fas fa-chart-bar text-primary me-2"></i>Distribusi Kategori Gangguan Sirkit
            </div>
            <div class="card-body p-4">
                <canvas id="categoryChart" style="max-height: 280px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Evaluasi Kinerja Field Engineer -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-ninja text-warning me-2"></i>Kinerja Tim Field Engineer</span>
                <span class="badge bg-light text-dark border">Tim Lapangan</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-b2b table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nama Teknisi</th>
                                <th class="text-center" style="width: 80px;">Ditugaskan</th>
                                <th class="text-center" style="width: 75px;">Selesai</th>
                                <th class="text-center" style="width: 75px;">On-Time</th>
                                <th class="text-center" style="width: 75px;">Breached</th>
                                <th style="width: 110px;">Avg. MTTR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tek_performance as $tp): ?>
                                <tr>
                                    <td class="fw-semibold text-dark">
                                        <i class="fas fa-user-circle text-primary me-1"></i> <?= htmlspecialchars($tp['name']) ?>
                                    </td>
                                    <td class="text-center"><?= $tp['total_assigned'] ?></td>
                                    <td class="text-center fw-bold text-success"><?= $tp['total_done'] ?></td>
                                    <td class="text-center text-success"><?= $tp['on_time_sla'] ?></td>
                                    <td class="text-center text-danger"><?= $tp['breached_sla'] ?></td>
                                    <td><?= format_duration_minutes($tp['avg_minutes']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var ctx = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($cat_labels) ?>,
            datasets: [{
                label: 'Jumlah Tiket Gangguan',
                data: <?= json_encode($cat_counts) ?>,
                backgroundColor: '#1e40af',
                borderColor: '#1e3a8a',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
