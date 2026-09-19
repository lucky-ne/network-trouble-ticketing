<?php
/**
 * Dashboard Administrator (Admin Master Panel)
 * Sistem Network Trouble Ticketing & SLA Tracking (B2B)
 * PT. Visimedia Pratama Persada
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    set_flash('danger', 'Akses ditolak! Halaman ini hanya dapat diakses oleh Administrator Sistem.');
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$pdo = get_db();

// PROSES: Simpan / Update Pengaturan Broadcast Gangguan Massal oleh Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_broadcast'])) {
    $is_active = isset($_POST['outage_broadcast_active']) ? '1' : '0';
    $title     = trim($_POST['outage_broadcast_title'] ?? '');
    $message   = trim($_POST['outage_broadcast_message'] ?? '');
    $area      = trim($_POST['outage_broadcast_area'] ?? '');
    $eta       = trim($_POST['outage_broadcast_eta'] ?? '');
    $level     = trim($_POST['outage_broadcast_level'] ?? 'danger');

    update_setting('outage_broadcast_active', $is_active);
    update_setting('outage_broadcast_title', $title);
    update_setting('outage_broadcast_message', $message);
    update_setting('outage_broadcast_area', $area);
    update_setting('outage_broadcast_eta', $eta);
    update_setting('outage_broadcast_level', $level);

    if ($is_active === '1') {
        set_flash('danger', '<strong>Broadcast Gangguan Massal DIAKTIFKAN!</strong> Banner peringatan darurat kini tampil di seluruh portal klien & internal.');
    } else {
        set_flash('success', '<strong>Broadcast Gangguan Massal DINONAKTIFKAN!</strong> Status operasional kembali normal.');
    }
    header('Location: ' . base_url('admin/dashboard.php'));
    exit;
}

$page_title = 'Dashboard Administrator & Pengaturan B2B';

// 1. Statistik Pengguna per Role
$users_by_role = [
    'total'    => 0,
    'karyawan' => 0,
    'helpdesk' => 0,
    'teknisi'  => 0,
    'manager'  => 0,
    'admin'    => 0
];
$stmt_users = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
while ($row = $stmt_users->fetch()) {
    $users_by_role[$row['role']] = (int)$row['count'];
    $users_by_role['total'] += (int)$row['count'];
}

// 2. Statistik Tiket Gangguan B2B
$ticket_stats = [
    'total'       => 0,
    'open'        => 0,
    'assigned'    => 0,
    'in_progress' => 0,
    'resolved'    => 0,
    'closed'      => 0,
    'within_sla'  => 0,
    'breached'    => 0
];
$stmt_tickets = $pdo->query("SELECT status, sla_status, COUNT(*) as count FROM tickets GROUP BY status, sla_status");
while ($row = $stmt_tickets->fetch()) {
    $count = (int)$row['count'];
    $ticket_stats['total'] += $count;
    if (isset($ticket_stats[$row['status']])) {
        $ticket_stats[$row['status']] += $count;
    }
    if ($row['sla_status'] === 'within_sla') {
        $ticket_stats['within_sla'] += $count;
    } elseif ($row['sla_status'] === 'breached') {
        $ticket_stats['breached'] += $count;
    }
}

$total_finished = $ticket_stats['resolved'] + $ticket_stats['closed'];
$compliance_rate = $total_finished > 0 ? round(($ticket_stats['within_sla'] / $total_finished) * 100, 1) : 100;

// 3. Total Klien, Kategori & Prioritas
$total_clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
$total_categories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$total_priorities = $pdo->query("SELECT COUNT(*) FROM priorities")->fetchColumn();

// 4. Riwayat 5 Tiket Terbaru
$recent_tickets = $pdo->query("
    SELECT t.*, cl.company_name, c.name as category_name, p.name as priority_name, p.badge_color as priority_badge,
           tech.name as technician_name
    FROM tickets t
    JOIN clients cl ON t.client_id = cl.id
    JOIN categories c ON t.category_id = c.id
    JOIN priorities p ON t.priority_id = p.id
    LEFT JOIN users tech ON t.technician_id = tech.id
    ORDER BY t.created_at DESC
    LIMIT 5
")->fetchAll();

// 5. Riwayat 5 Log Aktivitas Sistem
$recent_logs = $pdo->query("
    SELECT l.*, u.name as user_name, u.role as user_role, t.ticket_code
    FROM ticket_logs l
    JOIN users u ON l.user_id = u.id
    JOIN tickets t ON l.ticket_id = t.id
    ORDER BY l.created_at DESC
    LIMIT 5
")->fetchAll();

$server_info = [
    'php_version'    => PHP_VERSION,
    'db_version'     => $pdo->query("SELECT VERSION()")->fetchColumn(),
    'server_software'=> $_SERVER['SERVER_SOFTWARE'] ?? 'Apache',
    'upload_max'     => ini_get('upload_max_filesize'),
    'memory_limit'   => ini_get('memory_limit')
];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-cogs text-primary me-2"></i>Dashboard Administrator Master
        </h4>
        <p class="text-secondary small mb-0">Kontrol penuh atas master data klien B2B, pengguna, kategori, matriks SLA, dan konfigurasi sistem.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn <?= (get_setting('outage_broadcast_active', 0) == 1) ? 'btn-danger' : 'btn-outline-danger' ?> btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalBroadcastOutage" id="broadcastPanel">
            <i class="fas fa-tower-broadcast me-1"></i> Broadcast Gangguan Massal
            <?php if (get_setting('outage_broadcast_active', 0) == 1): ?>
                <span class="badge bg-white text-danger ms-1">AKTIF</span>
            <?php endif; ?>
        </button>
        <a href="<?= base_url('admin/kelola_klien.php') ?>" class="btn btn-primary btn-sm fw-semibold">
            <i class="fas fa-building me-1"></i> Kelola Klien B2B
        </a>
        <a href="<?= base_url('admin/pengaturan_website.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-sliders-h me-1"></i> Konfigurasi
        </a>
    </div>
</div>

<!-- Kartu Statistik Utama -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-primary">
            <div class="stat-label">Klien B2B & Sirkit</div>
            <div class="stat-value text-primary"><?= $total_clients ?></div>
            <div class="stat-desc">Perusahaan mitra terdaftar</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-info">
            <div class="stat-label">Total Akun Pengguna</div>
            <div class="stat-value text-info"><?= $users_by_role['total'] ?></div>
            <div class="stat-desc">PIC Klien, NOC, Teknisi, Manager</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-warning">
            <div class="stat-label">Total Tiket Gangguan</div>
            <div class="stat-value text-warning"><?= $ticket_stats['total'] ?></div>
            <div class="stat-desc"><?= $ticket_stats['open'] ?> Open, <?= $ticket_stats['assigned'] + $ticket_stats['in_progress'] ?> Diproses</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-success">
            <div class="stat-label">Kepatuhan SLA (%)</div>
            <div class="stat-value text-success"><?= $compliance_rate ?>%</div>
            <div class="stat-desc"><?= $ticket_stats['within_sla'] ?> On-Time vs <?= $ticket_stats['breached'] ?> Breached</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Tiket Terbaru -->
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-ticket-alt text-primary me-2"></i>Tiket Gangguan B2B Terbaru</span>
                <a href="<?= base_url('admin/semua_tiket.php') ?>" class="small text-decoration-none">Lihat Semua &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-b2b table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 135px;">No. Tiket</th>
                                <th style="width: 150px;">Klien & Sirkit</th>
                                <th>Kendala</th>
                                <th style="width: 105px;">Status</th>
                                <th style="width: 70px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_tickets as $rt): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary font-monospace text-nowrap"><?= htmlspecialchars($rt['ticket_code']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars($rt['company_name']) ?>"><?= htmlspecialchars($rt['company_name']) ?></div>
                                        <span class="circuit-badge"><?= htmlspecialchars($rt['circuit_id']) ?></span>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-dark text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($rt['title']) ?>"><?= htmlspecialchars($rt['title']) ?></div>
                                        <small class="text-secondary"><?= htmlspecialchars($rt['category_name']) ?></small>
                                    </td>
                                    <td><?= get_status_badge($rt['status']) ?></td>
                                    <td class="text-center">
                                        <a href="<?= base_url('customer/detail_tiket.php?id=' . $rt['id']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                                            Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Server & Log Aktivitas -->
    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <i class="fas fa-server text-info me-2"></i>Informasi Lingkungan Server
            </div>
            <div class="card-body p-3">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">PHP Version:</span>
                        <span class="fw-semibold text-dark"><?= $server_info['php_version'] ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Database Engine:</span>
                        <span class="fw-semibold text-dark">MySQL <?= $server_info['db_version'] ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Web Server:</span>
                        <span class="fw-semibold text-dark"><?= $server_info['server_software'] ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-history text-secondary me-2"></i>Log Aktivitas Terakhir</span>
                <a href="<?= base_url('admin/log_aktivitas.php') ?>" class="small text-decoration-none">Semua &rarr;</a>
            </div>
            <div class="card-body p-3">
                <div class="b2b-timeline">
                    <?php foreach ($recent_logs as $rl): ?>
                        <div class="b2b-timeline-item">
                            <div class="b2b-timeline-marker"></div>
                            <div class="b2b-timeline-box">
                                <div class="fw-semibold text-dark small"><?= htmlspecialchars($rl['action']) ?> (#<?= htmlspecialchars($rl['ticket_code']) ?>)</div>
                                <div class="text-muted" style="font-size: 0.72rem;"><?= htmlspecialchars($rl['user_name']) ?> &bull; <?= format_date_indo($rl['created_at']) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
