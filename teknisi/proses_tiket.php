<?php
/**
 * Pengerjaan & Input Berita Acara Troubleshooting oleh Field Engineer B2B
 * PT. Visimedia Pratama Persada
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/mailer.php';
check_auth(['teknisi']);

$pdo = get_db();
$tek_id = $_SESSION['user']['id'];
$ticket_id = (int)($_GET['id'] ?? 0);

// Ambil Detail Tiket & Klien B2B
$stmt = $pdo->prepare("SELECT 
    t.*, 
    c.name AS category_name, 
    p.name AS priority_name, 
    p.badge_color AS priority_color, 
    p.sla_hours,
    cl.company_name,
    cl.company_code,
    u_pelapor.name AS reporter_name,
    u_pelapor.email AS reporter_email,
    u_pelapor.phone AS reporter_phone
FROM tickets t
JOIN categories c ON t.category_id = c.id
JOIN priorities p ON t.priority_id = p.id
JOIN clients cl ON t.client_id = cl.id
JOIN users u_pelapor ON t.user_id = u_pelapor.id
WHERE t.id = ? AND t.technician_id = ?");
$stmt->execute([$ticket_id, $tek_id]);
$ticket = $stmt->fetch();

if (!$ticket) {
    set_flash('danger', 'Tiket tidak ditemukan atau belum ditugaskan kepada Anda.');
    header('Location: ' . base_url('teknisi/dashboard.php'));
    exit;
}

// Perlindungan jika tiket sudah Closed
if ($ticket['status'] === 'closed' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    set_flash('danger', 'Tiket ini sudah berstatus Closed (Terkunci) dan tidak dapat diedit kembali!');
    header('Location: ' . base_url('teknisi/proses_tiket.php?id=' . $ticket_id));
    exit;
}

// PROSES 1: Mulai Pengerjaan (In Progress)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_start'])) {
    try {
        $pdo->beginTransaction();
        
        $stmt_start = $pdo->prepare("UPDATE tickets SET status = 'in_progress', started_at = NOW() WHERE id = ?");
        $stmt_start->execute([$ticket_id]);

        $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, note, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt_log->execute([$ticket_id, $tek_id, 'Mulai Pengerjaan', 'Field Engineer telah tiba di lokasi site klien / memulai remote troubleshooting sirkit.']);

        $pdo->commit();

        send_ticket_notification($ticket_id, 'ticket_in_progress');

        set_flash('success', "Status tiket diubah menjadi In Progress (Sedang Dikerjakan).");
        header('Location: ' . base_url('teknisi/proses_tiket.php?id=' . $ticket_id));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', 'Gagal memulai pengerjaan: ' . $e->getMessage());
    }
}

// PROSES 2: Selesaikan Tiket & Hitung SLA Otomatis
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_resolve'])) {
    $root_cause   = trim($_POST['root_cause'] ?? '');
    $action_taken = trim($_POST['action_taken'] ?? '');
    $notes        = trim($_POST['technician_notes'] ?? '');
    
    if (empty($root_cause) || empty($action_taken) || empty($notes)) {
        set_flash('danger', 'Harap lengkapi semua rincian: Penyebab Gangguan, Tindakan Perbaikan, dan Catatan Teknis!');
    } else {
        try {
            $pdo->beginTransaction();

            $now_str = date('Y-m-d H:i:s');
            $created_time = strtotime($ticket['created_at']);
            $resolved_time = strtotime($now_str);
            $deadline_time = strtotime($ticket['sla_deadline']);

            // Hitung durasi pengerjaan dalam menit
            $duration_minutes = max(1, (int)round(($resolved_time - $created_time) / 60));

            // Logika Evaluasi SLA
            $sla_status = ($resolved_time <= $deadline_time) ? 'within_sla' : 'breached';

            $stmt_res = $pdo->prepare("UPDATE tickets SET 
                status = 'resolved',
                resolved_at = ?,
                sla_status = ?,
                resolution_time_minutes = ?,
                root_cause = ?,
                action_taken = ?,
                technician_notes = ?
            WHERE id = ?");
            $stmt_res->execute([
                $now_str, $sla_status, $duration_minutes, $root_cause, $action_taken, $notes, $ticket_id
            ]);

            $sla_text = ($sla_status === 'within_sla') ? 'Tepat Waktu (Within SLA)' : 'Terlambat (SLA Breached)';
            $log_text = "Perbaikan sirkit selesai. Kepatuhan SLA: {$sla_text}. Durasi: {$duration_minutes} menit. Tindakan: {$action_taken}";

            $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, note, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_log->execute([$ticket_id, $tek_id, 'Perbaikan Selesai', $log_text]);

            $pdo->commit();

            send_ticket_notification($ticket_id, 'ticket_resolved', $notes);

            set_flash('success', "Perbaikan sirkit {$ticket['circuit_id']} berhasil diselesaikan! Status SLA: {$sla_text}.");
            header('Location: ' . base_url('teknisi/proses_tiket.php?id=' . $ticket_id));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', 'Gagal menyelesaikan tiket: ' . $e->getMessage());
        }
    }
}

// Ambil Riwayat Log
$stmt_logs = $pdo->prepare("SELECT 
    tl.*, u.name AS user_name, u.role AS user_role 
FROM ticket_logs tl
JOIN users u ON tl.user_id = u.id
WHERE tl.ticket_id = ?
ORDER BY tl.created_at ASC");
$stmt_logs->execute([$ticket_id]);
$logs = $stmt_logs->fetchAll();

$page_title = 'Troubleshooting Tiket #' . $ticket['ticket_code'];
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <a href="<?= base_url('teknisi/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Antrian Tugas
        </a>
        <h4 class="fw-bold text-dark mb-0">
            <i class="fas fa-tools text-primary me-2"></i>Penanganan Tiket: <?= htmlspecialchars($ticket['ticket_code']) ?>
        </h4>
        <span class="text-secondary small">Klien: <strong><?= htmlspecialchars($ticket['company_name']) ?></strong> &bull; Sirkit: <span class="circuit-badge"><?= htmlspecialchars($ticket['circuit_id']) ?></span></span>
    </div>
    <div class="text-end">
        <?= get_status_badge($ticket['status']) ?>
        <div class="mt-1">
            <?= get_sla_badge($ticket['sla_status'], $ticket['sla_deadline'], $ticket['resolved_at']) ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Kolom Kiri: Form Troubleshooting & Aksi Teknisi -->
    <div class="col-lg-8">
        <!-- Informasi Sirkit -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-dark"><i class="fas fa-info-circle me-1"></i> Informasi Sirkit & Lokasi Site</span>
                <span class="badge bg-<?= htmlspecialchars($ticket['priority_color']) ?>">
                    Prioritas <?= htmlspecialchars($ticket['priority_name']) ?> (SLA <?= $ticket['sla_hours'] ?> Jam)
                </span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3 mb-3 bg-light p-3 rounded border">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Perusahaan Klien</small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($ticket['company_name']) ?> (<?= htmlspecialchars($ticket['company_code']) ?>)</span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Nomor Sirkit & Layanan</small>
                        <span class="circuit-badge"><?= htmlspecialchars($ticket['circuit_id']) ?></span> - <?= htmlspecialchars($ticket['service_type']) ?>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">PIC Klien</small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($ticket['reporter_name']) ?> (<?= htmlspecialchars($ticket['reporter_phone'] ?? '-') ?>)</span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Site / Lokasi Kantor</small>
                        <span class="fw-semibold text-dark"><i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($ticket['location']) ?></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted small mb-1">Gejala Gangguan yang Dilaporkan</label>
                    <div class="p-3 bg-white border rounded text-dark" style="font-size: 0.9rem;">
                        <strong><?= htmlspecialchars($ticket['title']) ?></strong>
                        <p class="mb-0 mt-1 text-secondary"><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>
                    </div>
                </div>

                <?php if (!empty($ticket['attachment'])): ?>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1">Lampiran Log / Bukti</label>
                        <div>
                            <a href="<?= base_url($ticket['attachment']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-paperclip me-1"></i> Lihat Lampiran Bukti
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Form Aksi Status Pengerjaan -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <span class="fw-semibold text-dark"><i class="fas fa-edit text-primary me-1"></i> Form Berita Acara & Tindakan Troubleshooting</span>
            </div>
            <div class="card-body p-4">
                <?php if ($ticket['status'] === 'assigned'): ?>
                    <!-- Tombol Mulai Pengerjaan -->
                    <div class="text-center py-4 bg-light rounded border p-4">
                        <i class="fas fa-play-circle fa-3x text-primary mb-3"></i>
                        <h5 class="fw-bold text-dark">Siap Memulai Pengerjaan?</h5>
                        <p class="text-secondary small mb-3">
                            Klik tombol di bawah untuk mencatat waktu mulai investigasi/kunjungan site klien.
                        </p>
                        <form method="POST" action="">
                            <button type="submit" name="action_start" class="btn btn-primary px-4 py-2 fw-semibold">
                                <i class="fas fa-play me-1"></i> Mulai Pengerjaan (In Progress)
                            </button>
                        </form>
                    </div>

                <?php elseif ($ticket['status'] === 'in_progress'): ?>
                    <!-- Form Input Solusi & Berita Acara Penyelesaian -->
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Penyebab Utama Gangguan (Root Cause) <span class="text-danger">*</span></label>
                            <input type="text" name="root_cause" class="form-control" placeholder="Contoh: Modul SFP 10G LR di POP mengalami degradasi laser optik / Patch cord OTB patah" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tindakan Perbaikan yang Dilakukan (Action Taken) <span class="text-danger">*</span></label>
                            <input type="text" name="action_taken" class="form-control" placeholder="Contoh: Penggantian modul transceiver SFP 10G LR baru dan pembersihan konektor ferrule" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catatan Teknis Lengkap / Hasil Pengukuran Parameter <span class="text-danger">*</span></label>
                            <textarea name="technician_notes" class="form-control" rows="4" placeholder="Uraikan hasil pengukuran optical power meter (dBm), uji ping 10.000 paket loss 0%, throughput speedtest, dan status interface router kembali Up." required></textarea>
                            <div class="form-text">Data ini akan dicatat ke Berita Acara resmi penyelesaian gangguan jaringan B2B.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <button type="submit" name="action_resolve" class="btn btn-success px-4 py-2 fw-semibold" onclick="return confirm('Apakah Anda yakin perbaikan sirkit telah selesai dan siap diserahkan ke klien?');">
                                <i class="fas fa-check-circle me-1"></i> Selesaikan Tiket & Kunci Waktu SLA
                            </button>
                        </div>
                    </form>

                <?php else: ?>
                    <!-- Status Resolved / Closed -->
                    <div class="alert alert-success mb-0 border-success">
                        <h6 class="fw-bold text-success mb-2"><i class="fas fa-check-circle me-1"></i> Tiket Telah Selesai Diperbaiki</h6>
                        <div class="small mb-1"><strong>Penyebab:</strong> <?= htmlspecialchars($ticket['root_cause'] ?? '-') ?></div>
                        <div class="small mb-1"><strong>Tindakan:</strong> <?= htmlspecialchars($ticket['action_taken'] ?? '-') ?></div>
                        <div class="small"><strong>Catatan Teknis:</strong> <?= nl2br(htmlspecialchars($ticket['technician_notes'] ?? '-')) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: SLA Timer & Timeline -->
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <span class="fw-semibold text-dark"><i class="fas fa-clock text-warning me-1"></i> Target Batas Waktu SLA</span>
            </div>
            <div class="card-body p-3">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Tiket Dibuat:</span>
                        <span class="fw-semibold text-dark"><?= format_date_indo($ticket['created_at']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Mulai Dikerjakan:</span>
                        <span class="fw-semibold text-dark"><?= format_date_indo($ticket['started_at']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Batas SLA:</span>
                        <span class="fw-semibold text-danger"><?= format_date_indo($ticket['sla_deadline']) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Waktu Selesai:</span>
                        <span class="fw-semibold text-success"><?= format_date_indo($ticket['resolved_at']) ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <span class="fw-semibold text-dark"><i class="fas fa-stream text-primary me-1"></i> Log Riwayat</span>
            </div>
            <div class="card-body p-3">
                <div class="b2b-timeline">
                    <?php foreach ($logs as $log): ?>
                        <div class="b2b-timeline-item">
                            <div class="b2b-timeline-marker <?= ($log['action'] === 'Tiket Diselesaikan' || $log['action'] === 'Perbaikan Selesai' || $log['action'] === 'Tiket Ditutup') ? 'success' : '' ?>"></div>
                            <div class="b2b-timeline-box">
                                <strong class="text-dark d-block"><?= htmlspecialchars($log['action']) ?></strong>
                                <small class="text-secondary d-block"><?= nl2br(htmlspecialchars($log['note'] ?? '')) ?></small>
                                <span class="text-muted" style="font-size: 0.72rem;"><?= format_date_indo($log['created_at']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
