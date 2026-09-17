<?php
/**
 * Portal Klien Korporat B2B - PT. Visimedia Pratama Persada
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/modal_buat_tiket.php';
check_auth(['karyawan', 'customer']);

$pdo = get_db();
$user = $_SESSION['user'];
$user_id = $user['id'];
$client_id = $user['client_id'] ?? null;

// Ambil Data Perusahaan Klien
$client_info = null;
if ($client_id) {
    $stmt_c = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt_c->execute([$client_id]);
    $client_info = $stmt_c->fetch();
}

// PROSES BUAT TIKET BARU VIA MODAL POPUP
process_create_ticket_request($pdo, $user, $client_info, base_url('customer/dashboard.php'));

// PROSES HAPUS TIKET OLEH PIC KLIEN
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    
    // Verifikasi kepemilikan tiket
    if ($client_id) {
        $stmt_chk = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND client_id = ?");
        $stmt_chk->execute([$del_id, $client_id]);
    } else {
        $stmt_chk = $pdo->prepare("SELECT * FROM tickets WHERE id = ? AND user_id = ?");
        $stmt_chk->execute([$del_id, $user_id]);
    }
    $t_del = $stmt_chk->fetch();

    if ($t_del) {
        try {
            if (!empty($t_del['attachment']) && file_exists(__DIR__ . '/../' . $t_del['attachment'])) {
                @unlink(__DIR__ . '/../' . $t_del['attachment']);
            }
            $stmt_d = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
            $stmt_d->execute([$del_id]);
            set_flash('success', "Tiket gangguan <strong>#{$t_del['ticket_code']}</strong> berhasil dihapus.");
        } catch (Exception $e) {
            set_flash('danger', 'Gagal menghapus tiket: ' . $e->getMessage());
        }
    } else {
        set_flash('danger', 'Tiket tidak ditemukan atau Anda tidak memiliki hak akses untuk menghapus tiket ini.');
    }
    header('Location: ' . base_url('customer/dashboard.php'));
    exit;
}

// Ambil Statistik Tiket Klien Korporat
if ($client_id) {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status IN ('open', 'assigned', 'in_progress') THEN 1 ELSE 0 END) AS active_tickets,
        SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) AS resolved_tickets
    FROM tickets WHERE client_id = ?");
    $stmt->execute([$client_id]);
} else {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status IN ('open', 'assigned', 'in_progress') THEN 1 ELSE 0 END) AS active_tickets,
        SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) AS resolved_tickets
    FROM tickets WHERE user_id = ?");
    $stmt->execute([$user_id]);
}
$stats = $stmt->fetch();

// Ambil Daftar Tiket Klien
if ($client_id) {
    $stmt = $pdo->prepare("SELECT 
        t.*, 
        c.name AS category_name, 
        p.name AS priority_name, 
        p.badge_color AS priority_color,
        p.sla_hours,
        u_tek.name AS technician_name,
        cl.company_name
    FROM tickets t
    JOIN categories c ON t.category_id = c.id
    JOIN priorities p ON t.priority_id = p.id
    JOIN clients cl ON t.client_id = cl.id
    LEFT JOIN users u_tek ON t.technician_id = u_tek.id
    WHERE t.client_id = ?
    ORDER BY t.created_at DESC");
    $stmt->execute([$client_id]);
} else {
    $stmt = $pdo->prepare("SELECT 
        t.*, 
        c.name AS category_name, 
        p.name AS priority_name, 
        p.badge_color AS priority_color,
        p.sla_hours,
        u_tek.name AS technician_name,
        cl.company_name
    FROM tickets t
    JOIN categories c ON t.category_id = c.id
    JOIN priorities p ON t.priority_id = p.id
    JOIN clients cl ON t.client_id = cl.id
    LEFT JOIN users u_tek ON t.technician_id = u_tek.id
    WHERE t.user_id = ?
    ORDER BY t.created_at DESC");
    $stmt->execute([$user_id]);
}
$tickets = $stmt->fetchAll();

$page_title = 'Portal Klien B2B - Tiket Gangguan Sirkit';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header Info Perusahaan & Tombol Buat Tiket -->
<div class="row align-items-center mb-4">
    <div class="col-md-8">
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-building text-primary me-2"></i>Portal Layanan Klien Korporat B2B
        </h4>
        <p class="text-secondary small mb-0">
            <?= htmlspecialchars($client_info['company_name'] ?? $user['name']) ?> &bull; 
            Nomor Sirkit: <span class="circuit-badge"><?= htmlspecialchars($client_info['circuit_id'] ?? 'CID-DEFAULT') ?></span> &bull; 
            Layanan: <strong><?= htmlspecialchars($client_info['service_type'] ?? 'Dedicated B2B') ?> (<?= htmlspecialchars($client_info['bandwidth'] ?? '-') ?>)</strong>
        </p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <button type="button" class="btn btn-primary px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalBuatTiket">
            <i class="fas fa-plus-circle me-1"></i> Buat Laporan Gangguan
        </button>
    </div>
</div>

<!-- Kartu Ringkasan Sirkit & Status Layanan -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-primary">
            <div class="stat-label">Total Tiket Masuk</div>
            <div class="stat-value"><?= $stats['total_tickets'] ?? 0 ?></div>
            <div class="stat-desc">Riwayat gangguan sirkit</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-warning">
            <div class="stat-label">Dalam Penanganan NOC</div>
            <div class="stat-value text-warning"><?= $stats['active_tickets'] ?? 0 ?></div>
            <div class="stat-desc">Tiket aktif diproses teknisi</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-success">
            <div class="stat-label">Tiket Selesai / Resolved</div>
            <div class="stat-value text-success"><?= $stats['resolved_tickets'] ?? 0 ?></div>
            <div class="stat-desc">Link telah normal kembali</div>
        </div>
    </div>
    <div class="col-md-3">
        <?php $has_trouble = ($stats['active_tickets'] ?? 0) > 0; ?>
        <div class="card-stat-b2b <?= $has_trouble ? 'border-left-danger' : 'border-left-info' ?>">
            <div class="stat-label">Status Link Sirkit</div>
            <?php if ($has_trouble): ?>
                <div class="stat-value text-danger fs-5 mt-1"><i class="fas fa-exclamation-circle me-1"></i> GANGGUAN (<?= $stats['active_tickets'] ?>)</div>
                <div class="stat-desc text-danger">Sedang ditangani teknisi NOC</div>
            <?php else: ?>
                <div class="stat-value text-success fs-5 mt-1"><i class="fas fa-check-circle me-1"></i> NORMAL / AKTIF</div>
                <div class="stat-desc">Monitoring 24/7 NOC Visimedia</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabel Daftar Tiket Sirkit Klien -->
<div class="card" id="tabel-tiket">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list-alt text-secondary me-2"></i>Daftar Laporan Gangguan Jaringan</span>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border">Total: <?= count($tickets) ?> Tiket</span>
            <a href="<?= base_url('customer/riwayat_tiket.php') ?>" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.76rem;">
                <i class="fas fa-history me-1"></i> Lihat Semua Riwayat
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-b2b table-hover align-middle datatable w-100">
                <thead>
                    <tr>
                        <th>No. Tiket</th>
                        <th>Sirkit / Layanan</th>
                        <th>Kendala & Lokasi Site</th>
                        <th>Kategori</th>
                        <th>Waktu Lapor</th>
                        <th>Teknisi NOC</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td class="fw-bold text-primary">
                                <?= htmlspecialchars($t['ticket_code']) ?>
                            </td>
                            <td>
                                <span class="circuit-badge"><?= htmlspecialchars($t['circuit_id']) ?></span>
                                <div class="small text-muted mt-1"><?= htmlspecialchars($t['service_type']) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($t['title']) ?></div>
                                <div class="small text-muted">
                                    <i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($t['location']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="fas fa-tag text-primary me-1"></i> <?= htmlspecialchars($t['category_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="small text-secondary"><?= format_date_indo($t['created_at']) ?></span>
                            </td>
                            <td>
                                <?php if ($t['technician_name']): ?>
                                    <div class="small fw-semibold text-dark">
                                        <i class="fas fa-user-cog text-primary me-1"></i> <?= htmlspecialchars($t['technician_name']) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Menunggu Disposisi NOC</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= get_status_badge($t['status']) ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="btn btn-outline-secondary" title="Lihat Detail & Tracking Timeline">
                                        <i class="fas fa-eye me-1 text-secondary"></i> Detail
                                    </a>
                                    <a href="<?= base_url('customer/dashboard.php?action=delete&id=' . $t['id']) ?>" 
                                       class="btn btn-outline-danger" 
                                       title="Hapus Tiket" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus tiket #<?= htmlspecialchars($t['ticket_code']) ?>?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
render_modal_buat_tiket($pdo, $user, $client_info);
include __DIR__ . '/../includes/footer.php'; 
?>
