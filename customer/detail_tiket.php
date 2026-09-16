<?php
/**
 * Detail Tiket & Tracking Timeline B2B - PT. Visimedia Pratama Persada
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/mailer.php';
check_auth(['karyawan', 'admin', 'helpdesk', 'manager', 'teknisi']);

$pdo = get_db();
$user_id = $_SESSION['user']['id'];
$current_role = $_SESSION['user']['role'];
$client_id = $_SESSION['user']['client_id'] ?? null;
$ticket_id = (int)($_GET['id'] ?? 0);

// Ambil Detail Tiket & Relasi Perusahaan Klien
$sql = "SELECT 
    t.*, 
    c.name AS category_name, 
    p.name AS priority_name, 
    p.badge_color AS priority_color,
    p.sla_hours,
    cl.company_name,
    cl.company_code,
    cl.pic_name AS client_pic_name,
    cl.pic_phone AS client_pic_phone,
    u_pelapor.name AS reporter_name,
    u_pelapor.department AS reporter_dept,
    u_pelapor.phone AS reporter_phone,
    u_pelapor.email AS reporter_email,
    u_tek.name AS technician_name,
    u_tek.phone AS technician_phone
FROM tickets t
JOIN categories c ON t.category_id = c.id
JOIN priorities p ON t.priority_id = p.id
JOIN clients cl ON t.client_id = cl.id
JOIN users u_pelapor ON t.user_id = u_pelapor.id
LEFT JOIN users u_tek ON t.technician_id = u_tek.id
WHERE t.id = ?";

if ($current_role === 'karyawan') {
    if ($client_id) {
        $sql .= " AND t.client_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticket_id, $client_id]);
    } else {
        $sql .= " AND t.user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticket_id, $user_id]);
    }
} else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ticket_id]);
}
$ticket = $stmt->fetch();

if (!$ticket) {
    set_flash('danger', 'Tiket tidak ditemukan atau Anda tidak memiliki akses ke tiket ini.');
    $fallback_url = ($current_role === 'admin') ? base_url('admin/semua_tiket.php') : base_url($current_role . '/dashboard.php');
    header('Location: ' . $fallback_url);
    exit;
}

// Proses Konfirmasi Tutup Tiket oleh PIC Klien (Jika status = resolved)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_close'])) {
    if ($ticket['status'] === 'resolved') {
        $feedback_note = trim($_POST['feedback_note'] ?? 'PIC Klien mengonfirmasi sirkit jaringan telah kembali normal dan stabil 100%.');
        
        try {
            $pdo->beginTransaction();
            
            $stmt_close = $pdo->prepare("UPDATE tickets SET status = 'closed', closed_at = NOW() WHERE id = ?");
            $stmt_close->execute([$ticket_id]);

            $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, note, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_log->execute([$ticket_id, $user_id, 'Tiket Ditutup', $feedback_note]);

            $pdo->commit();

            send_ticket_notification($ticket_id, 'ticket_closed', $feedback_note);

            set_flash('success', 'Konfirmasi diterima! Tiket resmi ditutup. Terima kasih telah mempercayakan layanan kepada PT. Visimedia Pratama Persada.');
            header('Location: ' . base_url('customer/detail_tiket.php?id=' . $ticket_id));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Gagal menutup tiket: ' . $e->getMessage());
        }
    }
}

// Proses Hapus Tiket oleh PIC Klien
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    try {
        if (!empty($ticket['attachment']) && file_exists(__DIR__ . '/../' . $ticket['attachment'])) {
            @unlink(__DIR__ . '/../' . $ticket['attachment']);
        }
        $stmt_del = $pdo->prepare("DELETE FROM tickets WHERE id = ?");
        $stmt_del->execute([$ticket_id]);
        
        set_flash('success', "Tiket gangguan <strong>#{$ticket['ticket_code']}</strong> berhasil dihapus.");
        $redirect_to = ($current_role === 'karyawan' || $current_role === 'customer') ? base_url('customer/dashboard.php') : base_url('admin/semua_tiket.php');
        header('Location: ' . $redirect_to);
        exit;
    } catch (Exception $e) {
        set_flash('danger', 'Gagal menghapus tiket: ' . $e->getMessage());
        header('Location: ' . base_url('customer/detail_tiket.php?id=' . $ticket_id));
        exit;
    }
}

// Ambil Riwayat Log Tiket
$stmt_logs = $pdo->prepare("SELECT 
    tl.*, u.name AS user_name, u.role AS user_role 
FROM ticket_logs tl
JOIN users u ON tl.user_id = u.id
WHERE tl.ticket_id = ?
ORDER BY tl.created_at ASC");
$stmt_logs->execute([$ticket_id]);
$logs = $stmt_logs->fetchAll();

// Tentukan tombol kembali
$back_url = base_url('customer/dashboard.php');
$back_label = 'Kembali ke Portal Klien';
if ($current_role === 'admin') {
    $back_url = base_url('admin/semua_tiket.php');
    $back_label = 'Kembali ke Semua Tiket';
} elseif ($current_role === 'helpdesk') {
    $back_url = base_url('helpdesk/kelola_tiket.php');
    $back_label = 'Kembali ke Antrian Tiket';
} elseif ($current_role === 'manager') {
    $back_url = base_url('manager/dashboard.php');
    $back_label = 'Kembali ke Dashboard SLA';
} elseif ($current_role === 'teknisi') {
    $back_url = base_url('teknisi/dashboard.php');
    $back_label = 'Kembali ke Tugas Teknisi';
}

$page_title = 'Detail Tiket #' . $ticket['ticket_code'];
include __DIR__ . '/../includes/header.php';
?>

<!-- Header Breadcrumb & Status -->
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <a href="<?= $back_url ?>" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="fas fa-arrow-left me-1"></i> <?= $back_label ?>
        </a>
        <h4 class="fw-bold text-dark mb-0">
            <i class="fas fa-file-invoice text-primary me-2"></i>Tiket: <?= htmlspecialchars($ticket['ticket_code']) ?>
        </h4>
        <span class="text-secondary small">Perusahaan: <strong><?= htmlspecialchars($ticket['company_name']) ?></strong> &bull; Sirkit: <span class="circuit-badge"><?= htmlspecialchars($ticket['circuit_id']) ?></span></span>
    </div>
    <div class="text-end">
        <div class="d-flex gap-2 justify-content-end align-items-center">
            <?= get_status_badge($ticket['status']) ?>
            <a href="<?= base_url('helpdesk/cetak_tiket.php?id=' . $ticket['id']) ?>" target="_blank" class="btn btn-sm btn-outline-success">
                <i class="fas fa-print me-1"></i> Cetak Berita Acara (PDF)
            </a>
            <a href="<?= base_url('customer/detail_tiket.php?action=delete&id=' . $ticket['id']) ?>" 
               class="btn btn-sm btn-outline-danger" 
               title="Hapus Tiket" 
               onclick="return confirm('Apakah Anda yakin ingin menghapus tiket #<?= htmlspecialchars($ticket['ticket_code']) ?> ini secara permanen?')">
                <i class="fas fa-trash-alt me-1"></i> Hapus
            </a>
        </div>
        <?php if ($current_role !== 'karyawan'): ?>
            <div class="mt-1">
                <?= get_sla_badge($ticket['sla_status'], $ticket['sla_deadline'], $ticket['resolved_at']) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Detail Sirkit & Troubleshooting -->
    <div class="col-lg-8">
        <!-- Informasi Sirkit & Kendala -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-dark"><i class="fas fa-info-circle me-1"></i> <?= htmlspecialchars($ticket['title']) ?></span>
                <?php if ($current_role !== 'karyawan'): ?>
                    <span class="badge bg-<?= htmlspecialchars($ticket['priority_color']) ?>">
                        <?= htmlspecialchars($ticket['priority_name']) ?> (SLA <?= $ticket['sla_hours'] ?> Jam)
                    </span>
                <?php else: ?>
                    <span class="badge bg-light text-dark border">
                        <i class="fas fa-tag text-primary me-1"></i> <?= htmlspecialchars($ticket['category_name']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-3 bg-light p-3 rounded border">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Layanan B2B</small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($ticket['service_type']) ?></span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Nomor Sirkit (CID)</small>
                        <span class="circuit-badge"><?= htmlspecialchars($ticket['circuit_id']) ?></span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Kategori Gangguan</small>
                        <span class="fw-semibold text-dark"><i class="fas fa-tag text-primary me-1"></i> <?= htmlspecialchars($ticket['category_name']) ?></span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Site / Lokasi Kantor Klien</small>
                        <span class="fw-semibold text-dark"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($ticket['location']) ?></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small mb-1">Deskripsi Gejala / Keluhan Klien</label>
                    <div class="p-3 bg-white border rounded text-dark" style="font-size: 0.9rem; line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($ticket['description'])) ?>
                    </div>
                </div>

                <?php if (!empty($ticket['attachment'])): ?>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Lampiran Log / Bukti Gangguan</label>
                        <div>
                            <a href="<?= base_url($ticket['attachment']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-paperclip me-1"></i> Lihat / Unduh Lampiran Bukti
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Catatan Berita Acara Perbaikan dari Field Engineer -->
                <?php if (!empty($ticket['technician_notes']) || !empty($ticket['root_cause'])): ?>
                    <div class="alert alert-success mt-4 border-success">
                        <h6 class="fw-bold text-success mb-2">
                            <i class="fas fa-check-circle me-1"></i> Berita Acara & Tindakan Perbaikan (Troubleshooting Report)
                        </h6>
                        <?php if (!empty($ticket['root_cause'])): ?>
                            <div class="mb-2">
                                <strong class="small text-dark">Penyebab Gangguan (Root Cause):</strong>
                                <p class="mb-1 text-dark small"><?= htmlspecialchars($ticket['root_cause']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($ticket['action_taken'])): ?>
                            <div class="mb-2">
                                <strong class="small text-dark">Tindakan Perbaikan (Action Taken):</strong>
                                <p class="mb-1 text-dark small"><?= htmlspecialchars($ticket['action_taken']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($ticket['technician_notes'])): ?>
                            <div>
                                <strong class="small text-dark">Catatan Teknis Field Engineer:</strong>
                                <p class="mb-0 text-dark small"><?= nl2br(htmlspecialchars($ticket['technician_notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Form Konfirmasi Tutup Tiket untuk PIC Klien -->
                <?php if ($current_role === 'karyawan' && $ticket['status'] === 'resolved'): ?>
                    <div class="card border-primary mt-4 bg-light">
                        <div class="card-body p-3">
                            <h6 class="fw-bold text-primary mb-2">
                                <i class="fas fa-thumbs-up me-1"></i> Konfirmasi Normalisasi Sirkit Jaringan
                            </h6>
                            <p class="small text-secondary mb-3">
                                Tim Network Engineer PT. Visimedia Pratama Persada telah menyelesaikan tindakan perbaikan. Silakan konfirmasi jika link koneksi kantor Anda telah berjalan normal.
                            </p>
                            <form method="POST" action="">
                                <div class="mb-2">
                                    <input type="text" name="feedback_note" class="form-control form-control-sm" placeholder="Catatan konfirmasi (contoh: Link dedicated internet sudah dites dan berjalan normal)." value="Link koneksi sudah kembali normal dan stabil 100%.">
                                </div>
                                <button type="submit" name="action_close" class="btn btn-sm btn-primary px-3 fw-semibold" onclick="return confirm('Apakah Anda yakin ingin mengonfirmasi penutupan tiket ini?');">
                                    <i class="fas fa-lock me-1"></i> Konfirmasi & Tutup Tiket
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Tracking SLA & Timeline NOC -->
    <div class="col-lg-4">
        <!-- SLA / Waktu Penanganan Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <span class="fw-semibold text-dark"><i class="fas fa-clock text-primary me-1"></i> <?= $current_role === 'karyawan' ? 'Informasi Waktu Penanganan' : 'Informasi SLA & Waktu' ?></span>
            </div>
            <div class="card-body p-3">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Waktu Lapor:</span>
                        <span class="fw-semibold text-dark"><?= format_date_indo($ticket['created_at']) ?></span>
                    </li>
                    <?php if ($current_role !== 'karyawan'): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Target SLA Deadline:</span>
                            <span class="fw-semibold text-danger"><?= format_date_indo($ticket['sla_deadline']) ?></span>
                        </li>
                    <?php endif; ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Status Penanganan:</span>
                        <span><?= get_status_badge($ticket['status']) ?></span>
                    </li>
                    <?php if (!empty($ticket['resolved_at'])): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Waktu Selesai:</span>
                            <span class="fw-semibold text-success"><?= format_date_indo($ticket['resolved_at']) ?></span>
                        </li>
                    <?php endif; ?>
                    <?php if (!empty($ticket['resolution_time_minutes']) && $ticket['resolution_time_minutes'] > 0): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Durasi Penanganan:</span>
                            <span class="fw-semibold text-dark"><?= format_duration_minutes($ticket['resolution_time_minutes']) ?></span>
                        </li>
                    <?php endif; ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Field Engineer:</span>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($ticket['technician_name'] ?? 'Menunggu Disposisi NOC') ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Timeline Log Aktivitas -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <span class="fw-semibold text-dark"><i class="fas fa-stream text-primary me-1"></i> Timeline Perjalanan Tiket</span>
            </div>
            <div class="card-body p-3">
                <div class="b2b-timeline">
                    <?php foreach ($logs as $log): ?>
                        <div class="b2b-timeline-item">
                            <div class="b2b-timeline-marker <?= ($log['action'] === 'Tiket Diselesaikan' || $log['action'] === 'Perbaikan Selesai' || $log['action'] === 'Tiket Ditutup') ? 'success' : '' ?>"></div>
                            <div class="b2b-timeline-box">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-dark"><?= htmlspecialchars($log['action']) ?></strong>
                                </div>
                                <div class="text-secondary small mb-1"><?= nl2br(htmlspecialchars($log['note'] ?? '')) ?></div>
                                <div class="text-muted" style="font-size: 0.72rem;">
                                    <i class="fas fa-user me-1"></i> <?= htmlspecialchars($log['user_name']) ?> &bull; <?= format_date_indo($log['created_at']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
