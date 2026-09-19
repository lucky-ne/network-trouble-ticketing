<?php
/**
 * Dashboard NOC Helpdesk B2B - PT. Visimedia Pratama Persada
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/mailer.php';
check_auth(['helpdesk']);

$pdo = get_db();

// Ambil Daftar Field Engineer & Prioritas untuk Modal Penugasan
$technicians = $pdo->query("SELECT id, nip, name, department, phone FROM users WHERE role = 'teknisi' ORDER BY name ASC")->fetchAll();
$priorities = $pdo->query("SELECT * FROM priorities ORDER BY sla_hours ASC")->fetchAll();

// PROSES: Disposisi Field Engineer & Update Prioritas SLA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_assign'])) {
    $ticket_id     = (int)$_POST['ticket_id'];
    $technician_id = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
    $priority_id   = (int)$_POST['priority_id'];
    $admin_note    = trim($_POST['admin_note'] ?? '');
    $user_id       = $_SESSION['user']['id'];

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
            header('Location: ' . base_url('helpdesk/dashboard.php'));
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
            $log_msg .= " Instruksi Teknis: " . $admin_note;
        }

        $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, note, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt_log->execute([$ticket_id, $user_id, 'Tiket Ditugaskan', $log_msg]);

        $pdo->commit();

        if ($technician_id) {
            send_ticket_notification($ticket_id, 'ticket_assigned', $admin_note);
        }

        set_flash('success', "Disposisi tiket #{$cur_ticket['ticket_code']} ({$cur_ticket['company_name']}) berhasil disimpan!");
        header('Location: ' . base_url('helpdesk/dashboard.php'));
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('danger', 'Gagal memproses disposisi teknisi: ' . $e->getMessage());
    }
}

// PROSES: Simpan / Update Pengaturan Broadcast Gangguan Massal
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
    header('Location: ' . base_url('helpdesk/dashboard.php'));
    exit;
}

// Statistik Keseluruhan B2B
$stats_query = $pdo->query("SELECT 
    COUNT(*) AS total_tickets,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_tickets,
    SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) AS assigned_tickets,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS progress_tickets,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_tickets,
    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_tickets
FROM tickets");
$stats = $stats_query->fetch();

// Ambil Antrian Tiket Gangguan Sirkit B2B Aktif
$urgent_tickets = $pdo->query("SELECT 
    t.*, 
    cl.company_name, 
    cl.company_code, 
    c.name AS category_name, 
    p.name AS priority_name, 
    p.badge_color AS priority_color,
    p.sla_hours,
    u_tek.name AS technician_name
FROM tickets t
JOIN categories c ON t.category_id = c.id
JOIN priorities p ON t.priority_id = p.id
JOIN clients cl ON t.client_id = cl.id
LEFT JOIN users u_tek ON t.technician_id = u_tek.id
WHERE t.status IN ('open', 'assigned', 'in_progress')
ORDER BY 
    CASE WHEN t.status = 'open' THEN 1 ELSE 2 END,
    t.sla_deadline ASC
LIMIT 15")->fetchAll();

$page_title = 'NOC Operations Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-dark mb-1">
            <i class="fas fa-headset text-primary me-2"></i>NOC & Service Desk B2B Dashboard
        </h4>
        <p class="text-secondary small mb-0">Pusat monitoring gangguan sirkit klien korporat & penugasan Field Engineer PT. Visimedia Pratama Persada.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn <?= (get_setting('outage_broadcast_active', 0) == 1) ? 'btn-danger' : 'btn-outline-danger' ?> btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalBroadcastOutage" id="broadcastPanel">
            <i class="fas fa-tower-broadcast me-1"></i> Broadcast Gangguan Massal
            <?php if (get_setting('outage_broadcast_active', 0) == 1): ?>
                <span class="badge bg-white text-danger ms-1">AKTIF</span>
            <?php endif; ?>
        </button>
        <a href="<?= base_url('helpdesk/kelola_tiket.php') ?>" class="btn btn-primary btn-sm fw-semibold">
            <i class="fas fa-tasks me-1"></i> Semua Antrian Tiket
        </a>
    </div>
</div>

<!-- Kartu Ringkasan Operasional NOC -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-danger">
            <div class="stat-label">Tiket Baru (Open / Unassigned)</div>
            <div class="stat-value text-danger"><?= $stats['open_tickets'] ?? 0 ?></div>
            <div class="stat-desc">Menunggu disposisi Field Engineer</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-warning">
            <div class="stat-label">Sedang Troubleshooting</div>
            <div class="stat-value text-warning"><?= ($stats['assigned_tickets'] + $stats['progress_tickets']) ?? 0 ?></div>
            <div class="stat-desc">Dalam penanganan teknisi di site/remote</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-success">
            <div class="stat-label">Link Pulih (Resolved / Closed)</div>
            <div class="stat-value text-success"><?= ($stats['resolved_tickets'] + $stats['closed_tickets']) ?? 0 ?></div>
            <div class="stat-desc">Koneksi telah normal kembali</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat-b2b border-left-primary">
            <div class="stat-label">Total Tiket B2B</div>
            <div class="stat-value text-dark"><?= $stats['total_tickets'] ?? 0 ?></div>
            <div class="stat-desc">Akumulasi seluruh sirkit klien</div>
        </div>
    </div>
</div>

<!-- Tabel Antrian Tiket Sirkit B2B Membutuhkan Penanganan -->
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-exclamation-triangle text-warning me-2"></i>Antrian Gangguan Sirkit B2B Aktif</span>
        <span class="badge bg-danger"><?= count($urgent_tickets) ?> Tiket Perlu Tindakan</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-b2b table-hover align-middle datatable w-100">
                <thead>
                    <tr>
                        <th style="width: 140px;">No. Tiket</th>
                        <th style="width: 160px;">Perusahaan Klien</th>
                        <th style="width: 150px;">Sirkit (CID) & Layanan</th>
                        <th>Kendala Jaringan</th>
                        <th style="width: 130px;">Prioritas SLA</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 140px;">Sisa Waktu SLA</th>
                        <th style="width: 140px;">Field Engineer</th>
                        <th style="width: 120px;" class="text-center">Aksi Disposisi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($urgent_tickets)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                                Luar biasa! Tidak ada antrian tiket gangguan sirkit yang pending saat ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($urgent_tickets as $t): 
                            $clean_tech_name = preg_replace('/\s*\(.*?\)/', '', $t['technician_name'] ?? '');
                        ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="fw-bold text-primary font-monospace text-nowrap text-decoration-none">
                                        <?= htmlspecialchars($t['ticket_code']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($t['company_name']) ?>"><?= htmlspecialchars($t['company_name']) ?></div>
                                    <small class="text-secondary"><?= htmlspecialchars($t['company_code']) ?></small>
                                </td>
                                <td>
                                    <span class="circuit-badge"><?= htmlspecialchars($t['circuit_id']) ?></span>
                                    <div class="small text-secondary mt-1 text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars($t['service_type']) ?>"><?= htmlspecialchars($t['service_type']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 230px;" title="<?= htmlspecialchars($t['title']) ?>"><?= htmlspecialchars($t['title']) ?></div>
                                    <small class="text-secondary text-truncate d-block" style="max-width: 230px;" title="<?= htmlspecialchars($t['location']) ?>">
                                        <i class="fas fa-map-marker-alt me-1 text-danger"></i> <?= htmlspecialchars($t['location']) ?>
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
                                    <?= get_sla_badge($t['sla_status'], $t['sla_deadline'], $t['resolved_at']) ?>
                                </td>
                                <td>
                                    <?php if ($t['technician_name']): ?>
                                        <div class="small fw-semibold text-dark text-truncate" style="max-width: 130px;" title="<?= htmlspecialchars($t['technician_name']) ?>">
                                            <i class="fas fa-user-cog text-primary me-1"></i> <?= htmlspecialchars($clean_tech_name) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-danger text-white">Belum Ditugaskan</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1 justify-content-center">
                                        <?php if ($t['status'] === 'closed'): ?>
                                            <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="btn btn-sm btn-outline-primary px-2 py-1" title="Lihat Detail & Tracking">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?= base_url('helpdesk/cetak_tiket.php?id=' . $t['id']) ?>" target="_blank" class="btn btn-sm btn-outline-success px-2 py-1" title="Cetak Berita Acara">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        <?php elseif ($t['status'] === 'open' || empty($t['technician_name'])): ?>
                                            <button type="button" class="btn btn-sm btn-primary fw-semibold px-2 py-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#assignModal<?= $t['id'] ?>"
                                                    title="Tugaskan ke Field Engineer" style="font-size: 0.78rem;">
                                                <i class="fas fa-user-plus me-1"></i> Tugaskan
                                            </button>
                                            <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="btn btn-sm btn-outline-secondary px-2 py-1" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary px-2 py-1" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#assignModal<?= $t['id'] ?>"
                                                    title="Ubah Disposisi Field Engineer" style="font-size: 0.78rem;">
                                                <i class="fas fa-user-cog me-1"></i> Disposisi
                                            </button>
                                            <a href="<?= base_url('customer/detail_tiket.php?id=' . $t['id']) ?>" class="btn btn-sm btn-outline-secondary px-2 py-1" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($t['status'] !== 'closed'): ?>
                                    <!-- Modal Disposisi Field Engineer -->
                                    <div class="modal fade" id="assignModal<?= $t['id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog text-start">
                                            <div class="modal-content">
                                                <form method="POST" action="">
                                                    <div class="modal-header bg-light">
                                                        <h6 class="modal-title fw-bold text-dark">
                                                            <i class="fas fa-user-tag text-primary me-1"></i> Disposisi Tiket: <?= htmlspecialchars($t['ticket_code']) ?>
                                                        </h6>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="bg-light p-3 rounded mb-3 border">
                                                            <div class="small"><strong>Klien:</strong> <?= htmlspecialchars($t['company_name']) ?></div>
                                                            <div class="small"><strong>Sirkit:</strong> <?= htmlspecialchars($t['circuit_id']) ?> (<?= htmlspecialchars($t['service_type']) ?>)</div>
                                                            <div class="small"><strong>Site:</strong> <?= htmlspecialchars($t['location']) ?></div>
                                                            <div class="small"><strong>Kendala:</strong> <?= htmlspecialchars($t['title']) ?></div>
                                                        </div>

                                                        <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">

                                                        <div class="mb-3">
                                                            <label class="form-label">Tugaskan Field / Network Engineer <span class="text-danger">*</span></label>
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
                                                            <label class="form-label fw-semibold text-dark">Tingkat Prioritas & Batas SLA (Ditentukan NOC) <span class="text-danger">*</span></label>
                                                            <select name="priority_id" class="form-select" required>
                                                                <?php foreach ($priorities as $pri): ?>
                                                                    <option value="<?= $pri['id'] ?>" <?= ($t['priority_id'] == $pri['id']) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($pri['name']) ?> (Target SLA: <?= $pri['sla_hours'] ?> Jam)
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <div class="form-text small">Pilih <strong>P1 Critical</strong> untuk link down total, <strong>P2 High</strong> untuk degradasi berat, atau <strong>P3/P4</strong> untuk kendala standar.</div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <label class="form-label">Instruksi Khusus NOC ke Teknisi</label>
                                                            <textarea name="admin_note" class="form-control" rows="3" placeholder="Contoh: Bawa optical power meter, modul SFP 10G LR cadangan, dan koordinasi dengan PIC site sebelum masuk ruang server."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer bg-light">
                                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                                                        <button type="submit" name="action_assign" class="btn btn-primary btn-sm fw-semibold">
                                                            <i class="fas fa-save me-1"></i> Simpan Disposisi
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
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
