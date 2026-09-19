<?php
/**
 * Kelola & Penugasan Tiket Gangguan Sirkit B2B - PT. Visimedia Pratama Persada
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/mailer.php';
check_auth(['helpdesk']);

$pdo = get_db();
$user_id = $_SESSION['user']['id'];

// Ambil Data Pendukung
$technicians = $pdo->query("SELECT id, nip, name, department, phone FROM users WHERE role = 'teknisi' ORDER BY name ASC")->fetchAll();
$priorities = $pdo->query("SELECT * FROM priorities ORDER BY sla_hours ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$clients = $pdo->query("SELECT * FROM clients ORDER BY company_name ASC")->fetchAll();

// PROSES: Disposisi Field Engineer & Update Prioritas SLA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_assign'])) {
    $ticket_id     = (int)$_POST['ticket_id'];
    $technician_id = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
    $priority_id   = (int)$_POST['priority_id'];
    $admin_note    = trim($_POST['admin_note'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt_p = $pdo->prepare("SELECT sla_hours, name FROM priorities WHERE id = ?");
        $stmt_p->execute([$priority_id]);
        $p_data = $stmt_p->fetch();
        $sla_hours = $p_data['sla_hours'] ?? 24;

        $stmt_cur = $pdo->prepare("SELECT t.*, cl.company_name FROM tickets t JOIN clients cl ON t.client_id = cl.id WHERE t.id = ?");
        $stmt_cur->execute([$ticket_id]);
        $cur_ticket = $stmt_cur->fetch();

        if ($cur_ticket && $cur_ticket['status'] === 'closed') {
            set_flash('danger', 'Tiket ini sudah berstatus Closed dan tidak dapat diedit kembali!');
            header('Location: ' . base_url('helpdesk/kelola_tiket.php'));
            exit;
        }

        $new_deadline = date('Y-m-d H:i:s', strtotime($cur_ticket['created_at'] . " +{$sla_hours} hours"));

        $new_status = $cur_ticket['status'];
        $assigned_at = $cur_ticket['assigned_at'];
        if ($technician_id && $cur_ticket['status'] === 'open') {
            $new_status = 'assigned';
            $assigned_at = date('Y-m-d H:i:s');
        }

        $stmt_upd = $pdo->prepare("UPDATE tickets SET 
            technician_id = ?, 
            priority_id = ?, 
            sla_deadline = ?,
            status = ?,
            assigned_at = ?
        WHERE id = ?");
        $stmt_upd->execute([
            $technician_id, $priority_id, $new_deadline, $new_status, $assigned_at, $ticket_id
        ]);

        $tek_name = 'Belum Ditentukan';
        if ($technician_id) {
            $stmt_tek = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $stmt_tek->execute([$technician_id]);
            $tek_name = $stmt_tek->fetchColumn();
        }

        $log_msg = "NOC Helpdesk menugaskan Field Engineer: {$tek_name} dengan prioritas {$p_data['name']}.";
        if (!empty($admin_note)) {
            $log_msg .= " Instruksi: " . $admin_note;
        }

        $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, note, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt_log->execute([$ticket_id, $user_id, 'Tiket Ditugaskan', $log_msg]);

        $pdo->commit();

        if ($technician_id) {
            send_ticket_notification($ticket_id, 'ticket_assigned', $admin_note);
        }

        set_flash('success', "Disposisi tiket #{$cur_ticket['ticket_code']} ({$cur_ticket['company_name']}) berhasil diperbarui!");
        header('Location: ' . base_url('helpdesk/kelola_tiket.php'));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', 'Gagal memproses penugasan: ' . $e->getMessage());
    }
}

// Filter Pencarian
$filter_status = $_GET['status'] ?? '';
$filter_client = (int)($_GET['client_id'] ?? 0);
$filter_priority = (int)($_GET['priority_id'] ?? 0);

$where_clauses = ["1=1"];
$params = [];

if (!empty($filter_status)) {
    $where_clauses[] = "t.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_client)) {
    $where_clauses[] = "t.client_id = ?";
    $params[] = $filter_client;
}

if (!empty($filter_priority)) {
    $where_clauses[] = "t.priority_id = ?";
    $params[] = $filter_priority;
}

$where_sql = implode(" AND ", $where_clauses);

$stmt_tickets = $pdo->prepare("SELECT 
    t.*, 
    c.name AS category_name, 
    p.name AS priority_name, 
    p.badge_color AS priority_color,
    p.sla_hours,
    cl.company_name,
    cl.company_code,
    u_pelapor.name AS reporter_name,
    u_pelapor.phone AS reporter_phone,
    u_tek.name AS technician_name
FROM tickets t
JOIN categories c ON t.category_id = c.id
JOIN priorities p ON t.priority_id = p.id
JOIN clients cl ON t.client_id = cl.id
JOIN users u_pelapor ON t.user_id = u_pelapor.id
LEFT JOIN users u_tek ON t.technician_id = u_tek.id
WHERE {$where_sql}
ORDER BY t.created_at DESC");
$stmt_tickets->execute($params);
$tickets = $stmt_tickets->fetchAll();

$page_title = 'Kelola & Penugasan Tiket Sirkit B2B';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-tasks text-primary me-2"></i>Pengelolaan & Disposisi Tiket Sirkit B2B
        </h4>
        <p class="text-secondary small mb-0">Tinjau seluruh tiket komplain gangguan jaringan masuk dari PT Klien, delegasikan ke teknisi, dan pantau SLA.</p>
    </div>
</div>

<!-- Filter Box -->
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
                <label class="form-label text-muted small mb-1">Status Penanganan</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <option value="open" <?= ($filter_status === 'open') ? 'selected' : '' ?>>Open (Baru / Belum Ditugaskan)</option>
                    <option value="assigned" <?= ($filter_status === 'assigned') ? 'selected' : '' ?>>Assigned (Sudah Ditugaskan)</option>
                    <option value="in_progress" <?= ($filter_status === 'in_progress') ? 'selected' : '' ?>>In Progress (Sedang Dikerjakan)</option>
                    <option value="resolved" <?= ($filter_status === 'resolved') ? 'selected' : '' ?>>Resolved (Perbaikan Selesai)</option>
                    <option value="closed" <?= ($filter_status === 'closed') ? 'selected' : '' ?>>Closed (Terkonfirmasi Tutup)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted small mb-1">Prioritas SLA</label>
                <select name="priority_id" class="form-select form-select-sm">
                    <option value="">-- Semua Prioritas --</option>
                    <?php foreach ($priorities as $pr): ?>
                        <option value="<?= $pr['id'] ?>" <?= ($filter_priority == $pr['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pr['name']) ?> (<?= $pr['sla_hours'] ?> Jam)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                    <i class="fas fa-filter me-1"></i> Terapkan Filter
                </button>
                <a href="<?= base_url('helpdesk/kelola_tiket.php') ?>" class="btn btn-outline-secondary btn-sm" title="Reset">
                    <i class="fas fa-undo"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Daftar Tiket -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list me-2"></i>Daftar Seluruh Tiket Sirkit B2B</span>
        <span class="badge bg-light text-dark border">Total Ditemukan: <?= count($tickets) ?> Tiket</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-b2b table-hover align-middle datatable w-100 mb-0">
                <thead>
                    <tr>
                        <th style="width: 120px;">No. Tiket</th>
                        <th style="width: 165px;">Perusahaan Klien</th>
                        <th style="width: 140px;">Sirkit &amp; Layanan</th>
                        <th style="min-width: 200px;">Ringkasan Masalah</th>
                        <th style="width: 110px;">Prioritas SLA</th>
                        <th style="width: 95px;">Status</th>
                        <th style="width: 140px;">Kepatuhan SLA</th>
                        <th style="width: 130px;">Field Engineer</th>
                        <th style="width: 125px;" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-primary">
                                <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($t['ticket_code']) ?>
                                </a>
                                <?php if (!empty($t['is_outage_massal'])): ?>
                                    <span class="badge bg-danger ms-1" title="Terkait Gangguan Massal"><i class="fas fa-tower-broadcast"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark text-truncate" style="max-width: 160px;" title="<?= htmlspecialchars($t['company_name']) ?>"><?= htmlspecialchars($t['company_name']) ?></div>
                                <small class="text-muted text-truncate d-block" style="max-width: 160px;">PIC: <?= htmlspecialchars($t['reporter_name']) ?></small>
                            </td>
                            <td>
                                <span class="circuit-badge"><?= htmlspecialchars($t['circuit_id']) ?></span>
                                <div class="small text-muted mt-1 text-truncate" style="max-width: 135px;"><?= htmlspecialchars($t['service_type']) ?></div>
                            </td>
                            <td>
                                <div class="small fw-semibold text-dark text-truncate" style="max-width: 230px;" title="<?= htmlspecialchars($t['title']) ?>"><?= htmlspecialchars($t['title']) ?></div>
                                <small class="text-muted text-truncate d-block mt-1" style="max-width: 230px; font-size: 0.75rem;" title="<?= htmlspecialchars($t['location']) ?>"><i class="fas fa-map-marker-alt me-1 text-danger"></i> <?= htmlspecialchars($t['location']) ?></small>
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
                            </td>
                            <td>
                                <?php if ($t['technician_name']): ?>
                                    <div class="small fw-semibold text-dark text-truncate" style="max-width: 125px;" title="<?= htmlspecialchars($t['technician_name']) ?>">
                                        <i class="fas fa-user-gear text-primary me-1"></i><?= htmlspecialchars(trim(preg_replace('/\(.*?\)/', '', $t['technician_name']))) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Belum Ditugaskan</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1 justify-content-center">
                                    <?php if ($t['status'] === 'closed'): ?>
                                        <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="btn btn-sm btn-outline-primary px-2 py-1" title="Lihat Detail & Tracking">
                                            <i class="fas fa-eye me-1"></i> Detail
                                        </a>
                                        <a href="<?= base_url('helpdesk/cetak_tiket.php?id=' . $t['id']) ?>" target="_blank" class="btn btn-sm btn-outline-success px-2 py-1" title="Cetak Berita Acara">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php elseif ($t['status'] === 'open' || empty($t['technician_name'])): ?>
                                        <button type="button" class="btn btn-primary fw-semibold" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#assignModal<?= $t['id'] ?>" 
                                                title="Tugaskan Teknisi & Tentukan SLA">
                                            <i class="fas fa-user-plus me-1"></i> Tugaskan
                                        </button>
                                        <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="btn btn-outline-secondary" title="Lihat Detail & Tracking">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#assignModal<?= $t['id'] ?>" 
                                                title="Ubah Prioritas SLA & Penugasan">
                                            <i class="fas fa-sliders-h me-1"></i> Atur SLA
                                        </button>
                                        <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="btn btn-outline-secondary" title="Lihat Detail & Tracking">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <?php if ($t['status'] !== 'closed'): ?>
                                <!-- Modal Tentukan Prioritas SLA & Disposisi Teknisi -->
                                <div class="modal fade" id="assignModal<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog text-start">
                                        <div class="modal-content">
                                            <form method="POST" action="">
                                                <div class="modal-header bg-light">
                                                    <h6 class="modal-title fw-bold text-dark">
                                                        <i class="fas fa-sliders-h text-primary me-1"></i> Penentuan SLA & Disposisi: <?= htmlspecialchars($t['ticket_code']) ?>
                                                    </h6>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <div class="bg-light p-3 rounded mb-3 border">
                                                        <div class="small"><strong>Klien:</strong> <?= htmlspecialchars($t['company_name']) ?></div>
                                                        <div class="small"><strong>Sirkit:</strong> <?= htmlspecialchars($t['circuit_id']) ?> (<?= htmlspecialchars($t['service_type']) ?>)</div>
                                                        <div class="small"><strong>Keluhan:</strong> <?= htmlspecialchars($t['title']) ?></div>
                                                        <div class="small"><strong>Site:</strong> <?= htmlspecialchars($t['location']) ?></div>
                                                    </div>

                                                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold text-dark">Tingkat Prioritas & Batas SLA (Ditentukan NOC) <span class="text-danger">*</span></label>
                                                        <select name="priority_id" class="form-select" required>
                                                            <?php foreach ($priorities as $pri): ?>
                                                                <option value="<?= $pri['id'] ?>" <?= ($t['priority_id'] == $pri['id']) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($pri['name']) ?> (Target SLA: <?= $pri['sla_hours'] ?> Jam)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <div class="form-text small">Pilih <strong>P1 Critical</strong> untuk link down total, <strong>P2 High</strong> untuk degradasi berat, atau <strong>P3/P4</strong> untuk kendala non-kritis.</div>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold text-dark">Tugaskan Field / Network Engineer <span class="text-danger">*</span></label>
                                                        <select name="technician_id" class="form-select" required>
                                                            <option value="">-- Pilih Field Engineer --</option>
                                                            <?php foreach ($technicians as $tek): ?>
                                                                <option value="<?= $tek['id'] ?>" <?= ($t['technician_id'] == $tek['id']) ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($tek['name']) ?> (<?= htmlspecialchars($tek['nip']) ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label">Instruksi Khusus Dispatcher NOC</label>
                                                        <textarea name="admin_note" class="form-control" rows="3" placeholder="Contoh: Periksa kabel patch cord di rack OTB gedung klien dan ukur redaman laser."></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="action_assign" class="btn btn-primary btn-sm fw-semibold">
                                                        <i class="fas fa-save me-1"></i> Simpan SLA & Disposisi
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
