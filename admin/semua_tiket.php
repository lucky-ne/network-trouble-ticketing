<?php
/**
 * Master Pengawasan Semua Tiket B2B (Admin Ticket Monitor)
 * PT. Visimedia Pratama Persada
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    set_flash('danger', 'Akses ditolak! Halaman ini hanya dapat diakses oleh Administrator Sistem.');
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$pdo = get_db();
$page_title = 'Pengawasan Semua Tiket Jaringan B2B';

// PROSES UBAH PENUGASAN TEKNISI ATAU STATUS OLEH ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_update_ticket') {
    $ticket_id     = (int)($_POST['ticket_id'] ?? 0);
    $technician_id = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
    $status        = trim($_POST['status'] ?? '');
    $admin_notes   = trim($_POST['admin_notes'] ?? '');

    if ($ticket_id > 0) {
        $stmt_cur = $pdo->prepare("SELECT status, ticket_code FROM tickets WHERE id = ?");
        $stmt_cur->execute([$ticket_id]);
        $cur_t = $stmt_cur->fetch();

        if ($cur_t && $cur_t['status'] === 'closed') {
            set_flash('danger', 'Tiket #' . $cur_t['ticket_code'] . ' sudah berstatus Closed dan tidak dapat diedit kembali!');
            header('Location: ' . base_url('admin/semua_tiket.php'));
            exit;
        }

        $extra_sql = "";
        if ($status === 'closed') {
            $extra_sql = ", closed_at = NOW()";
        }

        $stmt_upd = $pdo->prepare("UPDATE tickets SET technician_id = ?, status = ? $extra_sql WHERE id = ?");
        $stmt_upd->execute([$technician_id, $status, $ticket_id]);

        $log_note = "Admin mengupdate tiket (Status: " . strtoupper($status) . ")";
        if (!empty($admin_notes)) {
            $log_note .= " - Catatan: " . $admin_notes;
        }
        $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, note, created_at) VALUES (?, ?, 'Admin Override', ?, NOW())");
        $stmt_log->execute([$ticket_id, $_SESSION['user']['id'], $log_note]);

        set_flash('success', "Tiket berhasil diperbarui oleh Administrator!");
    }
    header('Location: ' . base_url('admin/semua_tiket.php'));
    exit;
}

// PROSES TUTUP TIKET OLEH ADMIN (Resolved -> Closed)
if (isset($_GET['close_id'])) {
    $close_id = (int)$_GET['close_id'];
    try {
        $stmt_check = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
        $stmt_check->execute([$close_id]);
        $ticket_data = $stmt_check->fetch();

        if ($ticket_data && $ticket_data['status'] === 'resolved') {
            $pdo->beginTransaction();
            $stmt_close = $pdo->prepare("UPDATE tickets SET status = 'closed', closed_at = NOW() WHERE id = ?");
            $stmt_close->execute([$close_id]);

            $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, note, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_log->execute([$close_id, $_SESSION['user']['id'], 'Tiket Ditutup', 'Administrator mengonfirmasi penutupan tiket secara resmi (Closed).']);

            $pdo->commit();

            send_ticket_notification($close_id, 'ticket_closed');
            set_flash('success', "Tiket #{$ticket_data['ticket_code']} berhasil ditutup secara resmi!");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', 'Gagal menutup tiket: ' . $e->getMessage());
    }
    header('Location: ' . base_url('admin/semua_tiket.php'));
    exit;
}

// PROSES HAPUS TIKET
if (isset($_GET['del_id'])) {
    $del_id = (int)$_GET['del_id'];
    try {
        $stmt_del = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
        $stmt_del->execute([$del_id]);
        set_flash('success', 'Tiket gangguan berhasil dihapus.');
    } catch (Exception $e) {
        set_flash('danger', 'Gagal menghapus tiket: ' . $e->getMessage());
    }
    header('Location: ' . base_url('admin/semua_tiket.php'));
    exit;
}

// Filter Pencarian
$filter_status   = $_GET['status'] ?? '';
$filter_client   = (int)($_GET['client_id'] ?? 0);
$filter_priority = (int)($_GET['priority_id'] ?? 0);

$where = ["1=1"];
$params = [];

if (!empty($filter_status)) {
    $where[] = "t.status = ?";
    $params[] = $filter_status;
}
if (!empty($filter_client)) {
    $where[] = "t.client_id = ?";
    $params[] = $filter_client;
}
if (!empty($filter_priority)) {
    $where[] = "t.priority_id = ?";
    $params[] = $filter_priority;
}

$where_sql = implode(" AND ", $where);

$stmt_t = $pdo->prepare("
    SELECT t.*, 
           cl.company_name, cl.company_code,
           u.name as reporter_name, 
           c.name as category_name, 
           p.name as priority_name, p.badge_color as priority_badge, p.sla_hours,
           tech.name as technician_name
    FROM tickets t
    JOIN clients cl ON t.client_id = cl.id
    JOIN users u ON t.user_id = u.id
    JOIN categories c ON t.category_id = c.id
    JOIN priorities p ON t.priority_id = p.id
    LEFT JOIN users tech ON t.technician_id = tech.id
    WHERE {$where_sql}
    ORDER BY t.created_at DESC
");
$stmt_t->execute($params);
$tickets = $stmt_t->fetchAll();

$clients = $pdo->query("SELECT * FROM clients ORDER BY company_name ASC")->fetchAll();
$technicians = $pdo->query("SELECT id, name FROM users WHERE role = 'teknisi' ORDER BY name ASC")->fetchAll();
$priorities = $pdo->query("SELECT * FROM priorities ORDER BY sla_hours ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-ticket-alt text-primary me-2"></i>Pengawasan & Arsip Seluruh Tiket B2B
        </h4>
        <p class="text-secondary small mb-0">Audit lengkap seluruh tiket gangguan jaringan sirkit klien, eskalasi, dan kontrol SLA.</p>
    </div>
</div>

<!-- Filter Card -->
<div class="card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted small mb-1">Perusahaan Klien B2B</label>
                <select name="client_id" class="form-select form-select-sm">
                    <option value="">-- Semua Klien --</option>
                    <?php foreach ($clients as $cl): ?>
                        <option value="<?= $cl['id'] ?>" <?= ($filter_client == $cl['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cl['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small mb-1">Status Tiket</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <option value="open" <?= ($filter_status === 'open') ? 'selected' : '' ?>>Open</option>
                    <option value="assigned" <?= ($filter_status === 'assigned') ? 'selected' : '' ?>>Assigned</option>
                    <option value="in_progress" <?= ($filter_status === 'in_progress') ? 'selected' : '' ?>>In Progress</option>
                    <option value="resolved" <?= ($filter_status === 'resolved') ? 'selected' : '' ?>>Resolved</option>
                    <option value="closed" <?= ($filter_status === 'closed') ? 'selected' : '' ?>>Closed</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small mb-1">Tingkat Prioritas</label>
                <select name="priority_id" class="form-select form-select-sm">
                    <option value="">-- Semua Prioritas --</option>
                    <?php foreach ($priorities as $pr): ?>
                        <option value="<?= $pr['id'] ?>" <?= ($filter_priority == $pr['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pr['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                    <i class="fas fa-filter me-1"></i> Terapkan
                </button>
                <a href="<?= base_url('admin/semua_tiket.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Semua Tiket -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Daftar Seluruh Tiket Terdaftar</span>
        <span class="badge bg-light text-dark border">Total: <?= count($tickets) ?> Tiket</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-b2b table-hover align-middle datatable w-100">
                <thead>
                    <tr>
                        <th>No. Tiket</th>
                        <th>Klien & Sirkit (CID)</th>
                        <th>Kendala & Lokasi</th>
                        <th>Prioritas SLA</th>
                        <th>Status</th>
                        <th>Kepatuhan SLA</th>
                        <th>Teknisi</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-primary">
                                <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($t['ticket_code']) ?>
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($t['company_name']) ?></div>
                                <span class="circuit-badge"><?= htmlspecialchars($t['circuit_id']) ?></span>
                            </td>
                            <td>
                                <div class="small fw-semibold"><?= htmlspecialchars($t['title']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($t['location']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= htmlspecialchars($t['priority_badge']) ?>">
                                    <?= htmlspecialchars($t['priority_name']) ?> (<?= $t['sla_hours'] ?>j)
                                </span>
                            </td>
                            <td><?= get_status_badge($t['status']) ?></td>
                            <td><?= get_sla_badge($t['sla_status'], $t['sla_deadline'], $t['resolved_at']) ?></td>
                            <td>
                                <?php if ($t['technician_name']): ?>
                                    <span class="small fw-semibold text-dark"><i class="fas fa-user-cog text-primary me-1"></i> <?= htmlspecialchars($t['technician_name']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger text-white">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <?php if ($t['status'] !== 'closed'): ?>
                                        <button type="button" class="btn btn-outline-warning text-dark" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editModal<?= $t['id'] ?>"
                                                title="Edit Penugasan / Status">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                    <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="btn btn-outline-primary" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?= base_url('helpdesk/cetak_tiket.php?id=' . $t['id']) ?>" target="_blank" class="btn btn-outline-success" title="Cetak Berita Acara (PDF)">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <?php if ($t['status'] === 'resolved'): ?>
                                        <a href="<?= base_url('admin/semua_tiket.php?close_id=' . $t['id']) ?>" class="btn btn-outline-dark" onclick="return confirm('Tutup tiket #<?= htmlspecialchars($t['ticket_code']) ?> secara resmi?');" title="Kunci & Tutup Tiket">
                                            <i class="fas fa-lock"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= base_url('admin/semua_tiket.php?del_id=' . $t['id']) ?>" class="btn btn-outline-danger" onclick="return confirm('Hapus permanen tiket #<?= htmlspecialchars($t['ticket_code']) ?>?');" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>

                                <?php if ($t['status'] !== 'closed'): ?>
                                <!-- Modal Edit / Override Tiket oleh Admin -->
                                <div class="modal fade" id="editModal<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog text-start">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <input type="hidden" name="action" value="admin_update_ticket">
                                                <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                                <div class="modal-header bg-light">
                                                    <h6 class="modal-title fw-bold text-dark">
                                                        <i class="fas fa-edit text-primary me-1"></i> Override Tiket: <?= htmlspecialchars($t['ticket_code']) ?>
                                                    </h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="bg-light p-3 rounded mb-3 border">
                                                        <div class="small"><strong>Klien:</strong> <?= htmlspecialchars($t['company_name']) ?></div>
                                                        <div class="small"><strong>Sirkit:</strong> <?= htmlspecialchars($t['circuit_id']) ?></div>
                                                        <div class="small"><strong>Kendala:</strong> <?= htmlspecialchars($t['title']) ?></div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Tugaskan Field Engineer</label>
                                                        <select name="technician_id" class="form-select">
                                                            <option value="">-- Belum Ditugaskan --</option>
                                                            <?php foreach ($technicians as $tek): ?>
                                                                <option value="<?= $tek['id'] ?>" <?= ($t['technician_id'] == $tek['id']) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($tek['name']) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Status Tiket</label>
                                                        <select name="status" class="form-select">
                                                            <option value="open" <?= ($t['status'] === 'open') ? 'selected' : '' ?>>Open</option>
                                                            <option value="assigned" <?= ($t['status'] === 'assigned') ? 'selected' : '' ?>>Assigned</option>
                                                            <option value="in_progress" <?= ($t['status'] === 'in_progress') ? 'selected' : '' ?>>In Progress</option>
                                                            <option value="resolved" <?= ($t['status'] === 'resolved') ? 'selected' : '' ?>>Resolved</option>
                                                            <option value="closed" <?= ($t['status'] === 'closed') ? 'selected' : '' ?>>Closed</option>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Catatan Tindakan Administrator</label>
                                                        <textarea name="admin_notes" class="form-control" rows="3" placeholder="Alasan override penugasan atau perubahan status..."></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                                                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
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
