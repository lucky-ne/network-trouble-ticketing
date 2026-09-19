<?php
/**
 * Dashboard Tugas Aktif Field / Network Engineer - PT. Visimedia Pratama Persada
 * Fokus: Antrian Penugasan Gangguan Sirkit Aktif (Belum Selesai: Assigned & In Progress)
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['teknisi']);

$pdo = get_db();
$tek_id = $_SESSION['user']['id'];

// Ambil Statistik Tugas Field Engineer
$stmt = $pdo->prepare("SELECT 
    COUNT(*) AS total_tasks,
    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) AS pending_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS progress_tasks,
    SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) AS done_tasks,
    SUM(CASE WHEN sla_status = 'breached' THEN 1 ELSE 0 END) AS breached_tasks
FROM tickets WHERE technician_id = ?");
$stmt->execute([$tek_id]);
$stats = $stmt->fetch();

// Ambil HANYA Tiket Aktif (Belum Selesai: Assigned & In Progress)
$stmt = $pdo->prepare("SELECT 
    t.*, 
    c.name AS category_name, 
    p.name AS priority_name, 
    p.badge_color AS priority_color,
    p.sla_hours,
    cl.company_name,
    cl.company_code,
    u_pelapor.name AS reporter_name,
    u_pelapor.phone AS reporter_phone
FROM tickets t
JOIN categories c ON t.category_id = c.id
JOIN priorities p ON t.priority_id = p.id
JOIN clients cl ON t.client_id = cl.id
JOIN users u_pelapor ON t.user_id = u_pelapor.id
WHERE t.technician_id = ? AND t.status IN ('assigned', 'in_progress')
ORDER BY 
    CASE 
        WHEN t.status = 'in_progress' THEN 1
        WHEN t.status = 'assigned' THEN 2
        ELSE 3
    END,
    t.sla_deadline ASC");
$stmt->execute([$tek_id]);
$active_tickets = $stmt->fetchAll();

$page_title = 'Tugas Lapangan Aktif (Belum Selesai)';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header Halaman -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-wrench text-warning me-2"></i>Daftar Tugas Aktif (Belum Selesai)
        </h4>
        <p class="text-secondary small mb-0">Antrian tiket gangguan sirkit jaringan yang membutuhkan tindakan troubleshooting dari Anda.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('teknisi/riwayat_tugas.php') ?>" class="btn btn-outline-success btn-sm fw-semibold">
            <i class="fas fa-check-double me-1"></i> Lihat Arsip Tugas Selesai (<?= (int)($stats['done_tasks'] ?? 0) ?>) &rarr;
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Tugas Aktif -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-primary">
            <div class="stat-label">Tugas Baru (Assigned)</div>
            <div class="stat-value text-primary"><?= $stats['pending_tasks'] ?? 0 ?></div>
            <div class="stat-desc">Menunggu Anda mulai troubleshooting</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-warning">
            <div class="stat-label">Sedang Troubleshooting</div>
            <div class="stat-value text-warning"><?= $stats['progress_tasks'] ?? 0 ?></div>
            <div class="stat-desc">Proses perbaikan di site / remote</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-danger">
            <div class="stat-label">Terlambat (SLA Breached)</div>
            <div class="stat-value text-danger"><?= $stats['breached_tasks'] ?? 0 ?></div>
            <div class="stat-desc">Melewati batas waktu toleransi SLA</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-success">
            <div class="stat-label">Tugas Telah Selesai</div>
            <div class="stat-value text-success"><?= $stats['done_tasks'] ?? 0 ?></div>
            <div class="stat-desc"><a href="<?= base_url('teknisi/riwayat_tugas.php') ?>" class="text-success text-decoration-none fw-semibold">Buka Arsip Selesai &rarr;</a></div>
        </div>
    </div>
</div>

<!-- Tabel Antrian Tugas Aktif -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold mb-0 text-dark">
                <i class="fas fa-clipboard-list text-primary me-2"></i>Antrian Pengerjaan Gangguan Jaringan
            </h6>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                <?= count($active_tickets) ?> Tugas Aktif
            </span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($active_tickets)): ?>
            <div class="text-center py-5">
                <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex p-3 mb-3 text-success">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">Semua Tugas Telah Selesai Dikerjakan!</h6>
                <p class="text-muted small mb-3">Tidak ada antrian gangguan aktif yang membutuhkan tindakan Anda saat ini.</p>
                <a href="<?= base_url('teknisi/riwayat_tugas.php') ?>" class="btn btn-outline-success btn-sm">
                    <i class="fas fa-archive me-1"></i> Buka Arsip Riwayat Tugas Selesai
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-b2b table-hover align-middle datatable w-100 mb-0">
                    <thead>
                        <tr>
                            <th style="width: 140px;">No. Tiket</th>
                            <th style="width: 160px;">Perusahaan Klien</th>
                            <th style="width: 150px;">Sirkit (CID) & Layanan</th>
                            <th>Kendala & Lokasi Site</th>
                            <th style="width: 120px;">Prioritas (SLA)</th>
                            <th style="width: 110px;">Status</th>
                            <th style="width: 140px;">Batas Waktu SLA</th>
                            <th style="width: 150px;" class="text-center">Aksi Pengerjaan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_tickets as $t): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('teknisi/proses_tiket.php?id=' . $t['id']) ?>" class="fw-bold text-primary font-monospace text-nowrap text-decoration-none">
                                        <?= htmlspecialchars($t['ticket_code']) ?>
                                    </a>
                                    <?php if (!empty($t['is_outage_massal'])): ?>
                                        <span class="badge bg-danger ms-1" title="Terkait Gangguan Massal"><i class="fas fa-broadcast-tower"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($t['company_name']) ?>"><?= htmlspecialchars($t['company_name']) ?></div>
                                    <small class="text-secondary">PIC: <?= htmlspecialchars($t['reporter_name']) ?></small>
                                </td>
                                <td>
                                    <span class="circuit-badge"><?= htmlspecialchars($t['circuit_id']) ?></span>
                                    <div class="small text-secondary mt-1 text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars($t['service_type']) ?>"><?= htmlspecialchars($t['service_type']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($t['title']) ?>"><?= htmlspecialchars($t['title']) ?></div>
                                    <small class="text-secondary text-truncate d-block" style="max-width: 250px;" title="<?= htmlspecialchars($t['location']) ?>">
                                        <i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($t['location']) ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-<?= htmlspecialchars($t['priority_color']) ?>">
                                        <?= htmlspecialchars($t['priority_name']) ?> (<?= $t['sla_hours'] ?>j)
                                    </span>
                                </td>
                                <td>
                                    <?= get_status_badge($t['status']) ?>
                                </td>
                                <td>
                                    <?= get_sla_badge($t['sla_status'], $t['sla_deadline'], $t['resolved_at'], $t['sla_exemption_reason'] ?? null) ?>
                                    <?php if (!empty($t['sla_paused_at'])): ?>
                                        <span class="badge bg-warning text-dark mt-1 d-block"><i class="fas fa-pause me-1"></i> SLA Terjeda</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($t['status'] === 'assigned'): ?>
                                        <a href="<?= base_url('teknisi/proses_tiket.php?id=' . $t['id']) ?>" class="btn btn-sm btn-primary fw-semibold px-2 py-1" style="font-size: 0.78rem;">
                                            <i class="fas fa-play me-1"></i> Mulai Kerjakan
                                        </a>
                                    <?php elseif ($t['status'] === 'in_progress'): ?>
                                        <a href="<?= base_url('teknisi/proses_tiket.php?id=' . $t['id']) ?>" class="btn btn-sm btn-warning text-dark fw-semibold px-2 py-1" style="font-size: 0.78rem;">
                                            <i class="fas fa-wrench me-1"></i> Selesaikan
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
