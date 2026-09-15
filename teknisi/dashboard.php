<?php
/**
 * Dashboard Field / Network Engineer - PT. Visimedia Pratama Persada
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

// Ambil Semua Tiket Milik Field Engineer
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
WHERE t.technician_id = ?
ORDER BY 
    CASE 
        WHEN t.status = 'in_progress' THEN 1
        WHEN t.status = 'assigned' THEN 2
        WHEN t.status = 'resolved' THEN 3
        ELSE 4
    END,
    t.sla_deadline ASC");
$stmt->execute([$tek_id]);
$tickets = $stmt->fetchAll();

$page_title = 'Daftar Tugas Field Engineer B2B';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-tools text-warning me-2"></i>Daftar Penugasan Field / Network Engineer
        </h4>
        <p class="text-secondary small mb-0">Tiket gangguan sirkit klien korporat yang ditugaskan ke Anda oleh NOC PT. Visimedia Pratama Persada.</p>
    </div>
</div>

<!-- Kartu Statistik Tugas -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-primary">
            <div class="stat-label">Tugas Baru (Assigned)</div>
            <div class="stat-value text-primary"><?= $stats['pending_tasks'] ?? 0 ?></div>
            <div class="stat-desc">Menunggu mulai pengerjaan</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-warning">
            <div class="stat-label">Sedang Troubleshooting</div>
            <div class="stat-value text-warning"><?= $stats['progress_tasks'] ?? 0 ?></div>
            <div class="stat-desc">Dalam proses pengerjaan teknisi</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-success">
            <div class="stat-label">Selesai Diperbaiki</div>
            <div class="stat-value text-success"><?= $stats['done_tasks'] ?? 0 ?></div>
            <div class="stat-desc">Link normal & tiket resolved/closed</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-danger">
            <div class="stat-label">Terlambat (SLA Breached)</div>
            <div class="stat-value text-danger"><?= $stats['breached_tasks'] ?? 0 ?></div>
            <div class="stat-desc">Melewati batas waktu toleransi SLA</div>
        </div>
    </div>
</div>

<!-- Tabel Daftar Tugas -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-clipboard-list me-2"></i>Antrian Tugas Perbaikan Sirkit Jaringan</span>
        <span class="badge bg-light text-dark border">Total: <?= count($tickets) ?> Tugas</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-b2b table-hover align-middle datatable w-100">
                <thead>
                    <tr>
                        <th>No. Tiket</th>
                        <th>Perusahaan Klien</th>
                        <th>Sirkit (CID) & Layanan</th>
                        <th>Kendala & Lokasi Site</th>
                        <th>Prioritas (SLA)</th>
                        <th>Status</th>
                        <th>Batas Waktu SLA</th>
                        <th class="text-center">Aksi Pengerjaan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td class="fw-bold text-primary">
                                    <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($t['ticket_code']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($t['company_name']) ?></div>
                                    <small class="text-muted">PIC: <?= htmlspecialchars($t['reporter_name']) ?> (<?= htmlspecialchars($t['reporter_phone'] ?? '-') ?>)</small>
                                </td>
                                <td>
                                    <span class="circuit-badge"><?= htmlspecialchars($t['circuit_id']) ?></span>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($t['service_type']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($t['title']) ?></div>
                                    <small class="text-muted"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($t['location']) ?></small>
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
                                    <?= get_sla_badge($t['sla_status'], $t['sla_deadline'], $t['resolved_at']) ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($t['status'] === 'assigned'): ?>
                                        <a href="<?= base_url('teknisi/proses_tiket.php?id=' . $t['id']) ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-play me-1"></i> Mulai Kerjakan
                                        </a>
                                    <?php elseif ($t['status'] === 'in_progress'): ?>
                                        <a href="<?= base_url('teknisi/proses_tiket.php?id=' . $t['id']) ?>" class="btn btn-sm btn-warning text-dark fw-semibold">
                                            <i class="fas fa-wrench me-1"></i> Update / Selesaikan
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye me-1"></i> Lihat Detail
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
