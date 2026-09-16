<?php
/**
 * Riwayat Tiket Gangguan Klien Korporat B2B
 * PT. Visimedia Pratama Persada
 * Khusus PIC Klien (SLA & Prioritas dikelola internal oleh NOC)
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/modal_buat_tiket.php';
check_auth(['karyawan', 'customer']);

$pdo = get_db();
$user = $_SESSION['user'];
$user_id = $user['id'];
$client_id = $user['client_id'] ?? null;

// Filter Status & Kategori
$filter_status = trim($_GET['status'] ?? '');
$filter_cat    = (int)($_GET['category_id'] ?? 0);

// Ambil Data Perusahaan Klien
$client_info = null;
if ($client_id) {
    $stmt_c = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt_c->execute([$client_id]);
    $client_info = $stmt_c->fetch();
}

// PROSES BUAT TIKET BARU VIA MODAL POPUP
process_create_ticket_request($pdo, $user, $client_info, base_url('customer/riwayat_tiket.php'));

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
    header('Location: ' . base_url('customer/riwayat_tiket.php'));
    exit;
}

// Ambil Kategori untuk Filter Dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Bangun Query Tiket
$query = "SELECT 
    t.*, 
    c.name AS category_name, 
    u_tek.name AS technician_name,
    cl.company_name
FROM tickets t
JOIN categories c ON t.category_id = c.id
JOIN clients cl ON t.client_id = cl.id
LEFT JOIN users u_tek ON t.technician_id = u_tek.id
WHERE " . ($client_id ? "t.client_id = :client_id" : "t.user_id = :user_id");

$params = $client_id ? [':client_id' => $client_id] : [':user_id' => $user_id];

if (!empty($filter_status)) {
    $query .= " AND t.status = :status";
    $params[':status'] = $filter_status;
}

if ($filter_cat > 0) {
    $query .= " AND t.category_id = :cat_id";
    $params[':cat_id'] = $filter_cat;
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

// Hitung Statistik Tab Status
$stats_sql = "SELECT 
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS count_open,
    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) AS count_assigned,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS count_in_progress,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS count_resolved,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS count_closed
FROM tickets 
WHERE " . ($client_id ? "client_id = ?" : "user_id = ?");
$stmt_st = $pdo->prepare($stats_sql);
$stmt_st->execute([$client_id ?: $user_id]);
$tab_stats = $stmt_st->fetch();

$page_title = 'Riwayat Lengkap Tiket Gangguan - ' . ($client_info['company_name'] ?? 'Klien B2B');
include __DIR__ . '/../includes/header.php';
?>

<!-- Header Breadcrumb & Actions -->
<div class="row align-items-center mb-4">
    <div class="col-md-7">
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-history text-primary me-2"></i>Riwayat Laporan Gangguan Sirkit
        </h4>
        <p class="text-secondary small mb-0">
            <?= htmlspecialchars($client_info['company_name'] ?? $user['name']) ?> &bull; 
            Nomor Sirkit: <span class="circuit-badge"><?= htmlspecialchars($client_info['circuit_id'] ?? 'CID-DEFAULT') ?></span> &bull; 
            Layanan: <strong><?= htmlspecialchars($client_info['service_type'] ?? 'Dedicated B2B') ?></strong>
        </p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
        <a href="<?= base_url('customer/dashboard.php') ?>" class="btn btn-outline-secondary px-3 py-2 fw-medium">
            <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
        <button type="button" class="btn btn-primary px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalBuatTiket">
            <i class="fas fa-plus-circle me-1"></i> Buat Laporan Baru
        </button>
    </div>
</div>

<!-- Filter Tab Status -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            
            <!-- Quick Filter Pills -->
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= base_url('customer/riwayat_tiket.php') ?>" 
                   class="btn btn-sm <?= empty($filter_status) ? 'btn-dark fw-bold' : 'btn-outline-secondary' ?>">
                    Semua <span class="badge bg-secondary ms-1"><?= $tab_stats['total'] ?? 0 ?></span>
                </a>
                <a href="<?= base_url('customer/riwayat_tiket.php?status=open') ?>" 
                   class="btn btn-sm <?= $filter_status === 'open' ? 'btn-danger fw-bold' : 'btn-outline-danger' ?>">
                    <i class="fas fa-inbox me-1"></i> Open <span class="badge bg-danger ms-1"><?= $tab_stats['count_open'] ?? 0 ?></span>
                </a>
                <a href="<?= base_url('customer/riwayat_tiket.php?status=assigned') ?>" 
                   class="btn btn-sm <?= $filter_status === 'assigned' ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                    <i class="fas fa-user-check me-1"></i> Assigned <span class="badge bg-primary ms-1"><?= $tab_stats['count_assigned'] ?? 0 ?></span>
                </a>
                <a href="<?= base_url('customer/riwayat_tiket.php?status=in_progress') ?>" 
                   class="btn btn-sm <?= $filter_status === 'in_progress' ? 'btn-warning text-dark fw-bold' : 'btn-outline-warning' ?>">
                    <i class="fas fa-tools me-1"></i> Investigasi / NOC <span class="badge bg-warning text-dark ms-1"><?= $tab_stats['count_in_progress'] ?? 0 ?></span>
                </a>
                <a href="<?= base_url('customer/riwayat_tiket.php?status=resolved') ?>" 
                   class="btn btn-sm <?= $filter_status === 'resolved' ? 'btn-success fw-bold' : 'btn-outline-success' ?>">
                    <i class="fas fa-check me-1"></i> Selesai (Menunggu Konfirmasi) <span class="badge bg-success ms-1"><?= $tab_stats['count_resolved'] ?? 0 ?></span>
                </a>
                <a href="<?= base_url('customer/riwayat_tiket.php?status=closed') ?>" 
                   class="btn btn-sm <?= $filter_status === 'closed' ? 'btn-secondary fw-bold' : 'btn-outline-secondary' ?>">
                    <i class="fas fa-lock me-1"></i> Closed <span class="badge bg-dark ms-1"><?= $tab_stats['count_closed'] ?? 0 ?></span>
                </a>
            </div>

            <!-- Filter Kategori & Reset -->
            <form method="GET" action="" class="d-flex align-items-center gap-2">
                <?php if (!empty($filter_status)): ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                <?php endif; ?>
                <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 180px;">
                    <option value="">-- Semua Kategori --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filter_cat == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($filter_status) || $filter_cat > 0): ?>
                    <a href="<?= base_url('customer/riwayat_tiket.php') ?>" class="btn btn-sm btn-light border text-danger" title="Reset Filter">
                        <i class="fas fa-times"></i>
                    </a>
                <?php endif; ?>
            </form>

        </div>
    </div>
</div>

<!-- Tabel Riwayat Lengkap -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
        <div class="fw-bold text-dark">
            <i class="fas fa-table text-secondary me-2"></i>Daftar Laporan Rekapitulasi Gangguan Sirkit
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border px-2 py-1">Menampilkan <?= count($tickets) ?> Tiket</span>
            <button onclick="window.print()" class="btn btn-sm btn-outline-secondary d-none d-md-inline-block">
                <i class="fas fa-print me-1"></i> Cetak Rekap
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-b2b table-hover align-middle datatable w-100 mb-0">
                <thead>
                    <tr>
                        <th style="width: 130px;">No. Tiket</th>
                        <th style="width: 160px;">Sirkit & Layanan</th>
                        <th>Rincian Kendala & Lokasi Site</th>
                        <th style="width: 160px;">Kategori Gangguan</th>
                        <th style="width: 140px;">Waktu Lapor</th>
                        <th style="width: 140px;">Teknisi NOC</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 90px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td>
                                <span class="fw-bold text-primary font-monospace"><?= htmlspecialchars($t['ticket_code']) ?></span>
                            </td>
                            <td>
                                <span class="circuit-badge"><?= htmlspecialchars($t['circuit_id']) ?></span>
                                <div class="small text-secondary mt-1"><?= htmlspecialchars($t['service_type']) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($t['title']) ?></div>
                                <div class="small text-secondary mt-1">
                                    <span><i class="fas fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($t['location']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="fas fa-tag text-primary me-1"></i> <?= htmlspecialchars($t['category_name']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="small fw-medium text-dark"><?= date('d M Y', strtotime($t['created_at'])) ?></div>
                                <div class="small text-secondary"><?= date('H:i', strtotime($t['created_at'])) ?> WIB</div>
                            </td>
                            <td>
                                <?php if ($t['technician_name']): ?>
                                    <div class="small fw-semibold text-dark">
                                        <i class="fas fa-user-cog text-primary me-1"></i> <?= htmlspecialchars($t['technician_name']) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-light text-muted border">Menunggu NOC</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= get_status_badge($t['status']) ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="btn btn-outline-primary" title="Lihat Detail & Tracking Timeline">
                                        <i class="fas fa-eye me-1"></i> Detail
                                    </a>
                                    <a href="<?= base_url('customer/riwayat_tiket.php?action=delete&id=' . $t['id']) ?>" 
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
include __DIR__ . '/modal_buat_tiket.php';
include __DIR__ . '/../includes/footer.php'; 
?>
