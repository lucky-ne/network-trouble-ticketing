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

// PROSES 1.5: JEDA / LANJUTKAN SLA CLOCK (Pause / Resume SLA)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pause_sla'])) {
    $pause_reason = trim($_POST['pause_reason'] ?? 'Menunggu izin akses / pihak ketiga / vendor backbone');
    try {
        $pdo->beginTransaction();
        $stmt_pause = $pdo->prepare("UPDATE tickets SET sla_paused_at = NOW() WHERE id = ?");
        $stmt_pause->execute([$ticket_id]);

        $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, note, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt_log->execute([$ticket_id, $tek_id, 'SLA Clock Dijeda', "Perhitungan waktu SLA di-pause sementara. Alasan: {$pause_reason}"]);

        $pdo->commit();
        set_flash('warning', "Penghitungan SLA berhasil dijeda (Paused). Batas SLA akan disesuaikan saat dilanjutkan kembali.");
        header('Location: ' . base_url('teknisi/proses_tiket.php?id=' . $ticket_id));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', 'Gagal menjeda SLA: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_resume_sla'])) {
    try {
        $pdo->beginTransaction();
        if (!empty($ticket['sla_paused_at'])) {
            $paused_time = strtotime($ticket['sla_paused_at']);
            $now_time = time();
            $paused_mins = max(1, (int)round(($now_time - $paused_time) / 60));
            $total_paused = (int)($ticket['sla_paused_total_minutes'] ?? 0) + $paused_mins;

            // Perpanjang deadline SLA sejumlah menit yang dijeda
            $stmt_resume = $pdo->prepare("UPDATE tickets SET 
                sla_paused_at = NULL,
                sla_paused_total_minutes = ?,
                sla_deadline = DATE_ADD(sla_deadline, INTERVAL ? MINUTE)
                WHERE id = ?");
            $stmt_resume->execute([$total_paused, $paused_mins, $ticket_id]);

            $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, note, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_log->execute([$ticket_id, $tek_id, 'SLA Clock Dilanjutkan', "Jeda SLA berakhir (+{$paused_mins} menit ditambahkan ke toleransi batas SLA)."]);
        }
        $pdo->commit();
        set_flash('success', "Penghitungan SLA dilanjutkan kembali.");
        header('Location: ' . base_url('teknisi/proses_tiket.php?id=' . $ticket_id));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', 'Gagal melanjutkan SLA: ' . $e->getMessage());
    }
}

// PROSES 2: Selesaikan Tiket & Hitung SLA Otomatis (Termasuk SLA Exempted)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_resolve'])) {
    $root_cause           = trim($_POST['root_cause'] ?? '');
    $action_taken         = trim($_POST['action_taken'] ?? '');
    $notes                = trim($_POST['technician_notes'] ?? '');
    $is_sla_exempted      = isset($_POST['is_sla_exempted']) && $_POST['is_sla_exempted'] == '1';
    $sla_exemption_reason = trim($_POST['sla_exemption_reason'] ?? '');
    $is_outage_massal     = isset($_POST['is_outage_massal']) ? 1 : 0;
    
    if (empty($root_cause) || empty($action_taken) || empty($notes)) {
        set_flash('danger', 'Harap lengkapi semua rincian: Penyebab Gangguan, Tindakan Perbaikan, dan Catatan Teknis!');
    } elseif ($is_sla_exempted && empty($sla_exemption_reason)) {
        set_flash('danger', 'Harap pilih alasan klausul Pengecualian SLA jika klaim SLA Exempted diaktifkan!');
    } else {
        try {
            $pdo->beginTransaction();

            $now_str = date('Y-m-d H:i:s');
            $created_time = strtotime($ticket['created_at']);
            $resolved_time = strtotime($now_str);
            $deadline_time = strtotime($ticket['sla_deadline']);

            // Jika masih dalam status paused saat resolve, hitung sisa paused
            $extra_paused_mins = 0;
            if (!empty($ticket['sla_paused_at'])) {
                $extra_paused_mins = max(1, (int)round(($resolved_time - strtotime($ticket['sla_paused_at'])) / 60));
            }
            $total_paused_mins = (int)($ticket['sla_paused_total_minutes'] ?? 0) + $extra_paused_mins;

            // Hitung durasi pengerjaan dalam menit
            $duration_minutes = max(1, (int)round(($resolved_time - $created_time) / 60));

            // Logika Evaluasi SLA
            if ($is_sla_exempted) {
                $sla_status = 'exempted';
                $sla_text = 'Dikecualikan dari Penalti SLA (Force Majeure / SLA Exempted)';
            } else {
                $sla_status = ($resolved_time <= $deadline_time) ? 'within_sla' : 'breached';
                $sla_text = ($sla_status === 'within_sla') ? 'Tepat Waktu (Within SLA)' : 'Terlambat (SLA Breached)';
            }

            $stmt_res = $pdo->prepare("UPDATE tickets SET 
                status = 'resolved',
                resolved_at = ?,
                sla_status = ?,
                sla_exemption_reason = ?,
                is_outage_massal = ?,
                sla_paused_at = NULL,
                sla_paused_total_minutes = ?,
                resolution_time_minutes = ?,
                root_cause = ?,
                action_taken = ?,
                technician_notes = ?
            WHERE id = ?");
            $stmt_res->execute([
                $now_str, $sla_status, 
                $is_sla_exempted ? $sla_exemption_reason : null, 
                $is_outage_massal,
                $total_paused_mins,
                $duration_minutes, 
                $root_cause, 
                $action_taken, 
                $notes, 
                $ticket_id
            ]);

            $log_text = "Perbaikan sirkit selesai. Status Evaluasi SLA: {$sla_text}. Durasi: {$duration_minutes} menit." . 
                        ($is_sla_exempted ? " [Klausul Pengecualian: {$sla_exemption_reason}]" : "") . 
                        " Tindakan: {$action_taken}";

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

                <?php if (!empty($ticket['attachment'])): 
                    $att_ext = strtolower(pathinfo($ticket['attachment'], PATHINFO_EXTENSION));
                    $is_image = in_array($att_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    $att_url = base_url($ticket['attachment']);
                ?>
                    <div class="mb-3">
                        <label class="form-label text-muted small mb-1 fw-semibold">
                            <i class="fas fa-paperclip text-primary me-1"></i> Lampiran Log / Bukti Gangguan dari Klien
                        </label>
                        <div class="p-3 bg-light rounded border">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas <?= $is_image ? 'fa-file-image text-info' : 'fa-file-alt text-warning' ?> fs-4"></i>
                                    <div>
                                        <div class="fw-semibold text-dark small"><?= basename($ticket['attachment']) ?></div>
                                        <small class="text-muted">Format: <?= strtoupper($att_ext) ?></small>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAttachmentTeknisi">
                                        <i class="fas fa-eye me-1"></i> Lihat Bukti
                                    </button>
                                    <a href="<?= $att_url ?>" download class="btn btn-sm btn-outline-secondary" title="Unduh File">
                                        <i class="fas fa-download me-1"></i> Unduh
                                    </a>
                                </div>
                            </div>

                            <?php if ($is_image): ?>
                                <div class="mt-3 text-center bg-white p-2 rounded border" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#modalAttachmentTeknisi">
                                    <img src="<?= $att_url ?>" alt="Bukti Gangguan" class="img-fluid rounded shadow-sm" style="max-height: 220px; object-fit: contain;">
                                    <div class="text-muted small mt-1"><i class="fas fa-search-plus me-1"></i> Klik gambar untuk memperbesar</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Modal Pratinjau Lampiran Bukti untuk Teknisi -->
                    <div class="modal fade" id="modalAttachmentTeknisi" tabindex="-1" aria-labelledby="modalAttachmentTekLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content border-0 shadow">
                                <div class="modal-header bg-dark text-white py-2">
                                    <h6 class="modal-title mb-0" id="modalAttachmentTekLabel">
                                        <i class="fas <?= $is_image ? 'fa-image text-info' : 'fa-file-alt text-warning' ?> me-2"></i>
                                        Bukti Gangguan: <?= htmlspecialchars($ticket['ticket_code']) ?>
                                    </h6>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body text-center p-3 bg-light">
                                    <?php if ($is_image): ?>
                                        <img src="<?= $att_url ?>" alt="Bukti Gangguan" class="img-fluid rounded shadow-sm border" style="max-height: 75vh; object-fit: contain;">
                                    <?php elseif ($att_ext === 'pdf'): ?>
                                        <iframe src="<?= $att_url ?>" style="width: 100%; height: 70vh; border: none;" class="rounded border"></iframe>
                                    <?php else: ?>
                                        <div class="p-4 bg-white rounded border">
                                            <i class="fas fa-file-download text-primary display-4 mb-3"></i>
                                            <h5><?= basename($ticket['attachment']) ?></h5>
                                            <p class="text-muted small">File ini berformat <strong><?= strtoupper($att_ext) ?></strong>.</p>
                                            <a href="<?= $att_url ?>" download class="btn btn-primary">
                                                <i class="fas fa-download me-1"></i> Unduh Berkas
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer py-2 bg-white d-flex justify-content-between">
                                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i> <?= basename($ticket['attachment']) ?></small>
                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                </div>
                            </div>
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
                    <!-- KONTROL JEDA WAKTU SLA (PAUSE / RESUME SLA CLOCK) -->
                    <div class="card mb-4 border <?= !empty($ticket['sla_paused_at']) ? 'border-warning bg-warning-subtle' : 'border-secondary-subtle bg-light' ?>">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold text-dark small">
                                        <i class="fas <?= !empty($ticket['sla_paused_at']) ? 'fa-pause-circle text-warning' : 'fa-hourglass-start text-primary' ?> me-1"></i>
                                        Status Penghitungan SLA: 
                                        <?php if (!empty($ticket['sla_paused_at'])): ?>
                                            <span class="badge bg-warning text-dark"><i class="fas fa-pause me-1"></i> SLA DIJEDA SEMENTARA</span>
                                        <?php else: ?>
                                            <span class="badge bg-success text-white"><i class="fas fa-play me-1"></i> BERJALAN AKTIF</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-secondary small mt-1" style="font-size: 0.78rem;">
                                        <?php if (!empty($ticket['sla_paused_at'])): ?>
                                            Dijeda sejak: <strong><?= format_date_indo($ticket['sla_paused_at']) ?></strong> (Menunggu pihak ketiga / perizinan)
                                        <?php else: ?>
                                            Gunakan tombol jeda jika penanganan tertunda akibat menunggu perizinan dinas / pihak ketiga / vendor FO.
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <?php if (!empty($ticket['sla_paused_at'])): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <button type="submit" name="action_resume_sla" class="btn btn-sm btn-success fw-semibold" onclick="return confirm('Lanjutkan kembali penghitungan SLA?');">
                                                <i class="fas fa-play me-1"></i> Lanjutkan SLA (Resume)
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-semibold" data-bs-toggle="collapse" data-bs-target="#collapsePauseSla">
                                            <i class="fas fa-pause me-1"></i> Jeda SLA (Pause Clock)
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (empty($ticket['sla_paused_at'])): ?>
                                <div class="collapse mt-3 pt-3 border-top" id="collapsePauseSla">
                                    <form method="POST" action="">
                                        <label class="form-label small fw-semibold text-dark">Alasan Jeda Waktu SLA <span class="text-danger">*</span></label>
                                        <input type="text" name="pause_reason" class="form-control form-control-sm mb-2" placeholder="Contoh: Menunggu izin perbaikan galian kabel dari dinas / menunggu vendor FO konsorsium" required>
                                        <button type="submit" name="action_pause_sla" class="btn btn-sm btn-warning text-dark fw-semibold">
                                            <i class="fas fa-pause me-1"></i> Konfirmasi Jeda Waktu SLA
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Form Input Solusi & Berita Acara Penyelesaian -->
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Penyebab Utama Gangguan (Root Cause) <span class="text-danger">*</span></label>
                            <input type="text" name="root_cause" class="form-control" placeholder="Contoh: Kabel FO Backbone Putus terkena galian utilitas jalan / Patch cord OTB patah" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tindakan Perbaikan yang Dilakukan (Action Taken) <span class="text-danger">*</span></label>
                            <input type="text" name="action_taken" class="form-control" placeholder="Contoh: Splicing 24 core fiber optic backbone baru, OTDR test -18.5 dBm, link up normal" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Catatan Teknis Lengkap / Hasil Pengukuran Parameter <span class="text-danger">*</span></label>
                            <textarea name="technician_notes" class="form-control" rows="3" placeholder="Uraikan hasil pengukuran optical power meter (dBm), uji ping 10.000 paket loss 0%, throughput speedtest, dan status interface router kembali Up." required></textarea>
                            <div class="form-text">Data ini akan dicatat ke Berita Acara resmi penyelesaian gangguan jaringan B2B.</div>
                        </div>

                        <!-- KLAIM PENGECEUALIAN SLA / FORCE MAJEURE -->
                        <div class="p-3 mb-3 rounded border" style="background:#f5f3ff; border-color:#ddd6fe !important;">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="switchSlaExempted" name="is_sla_exempted" value="1" onchange="toggleExemption(this.checked)">
                                <label class="form-check-label fw-bold text-dark" for="switchSlaExempted" style="font-size:0.9rem;">
                                    <i class="fas fa-shield-alt text-purple me-1" style="color: #7c3aed;"></i> Klaim Pengecualian SLA / Gangguan Massal (SLA Exempted / Force Majeure)
                                </label>
                            </div>
                            <div id="exemptionReasonContainer" style="display: none;" class="mt-2 pt-2 border-top">
                                <label class="form-label small fw-semibold text-dark mb-1">
                                    Pilih Alasan Pengecualian SLA Sesuai Kontrak B2B <span class="text-danger">*</span>
                                </label>
                                <select name="sla_exemption_reason" id="selectExemptionReason" class="form-select form-select-sm mb-2">
                                    <option value="">-- Pilih Klausul Pengecualian SLA --</option>
                                    <?php foreach (get_sla_exemption_reasons() as $key_reas => $lbl_reas): ?>
                                        <option value="<?= htmlspecialchars($lbl_reas) ?>"><?= htmlspecialchars($lbl_reas) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="is_outage_massal" value="1" id="chkOutageMassal" checked>
                                    <label class="form-check-label small text-secondary" for="chkOutageMassal">
                                        Tandai sebagai Insiden Gangguan Massal (Backbone Outage)
                                    </label>
                                </div>
                                <div class="form-text small text-muted">
                                    <i class="fas fa-info-circle me-1"></i> Durasi perbaikan tiket ini <strong>tidak akan dihitung sebagai pelanggaran SLA</strong> dan persentase Uptime SLA Klien tetap terlindungi tanpa klaim denda penalti.
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <button type="submit" name="action_resolve" class="btn btn-success px-4 py-2 fw-semibold" onclick="return confirm('Apakah Anda yakin perbaikan sirkit telah selesai dan siap diserahkan ke klien?');">
                                <i class="fas fa-check-circle me-1"></i> Selesaikan Tiket & Kunci Waktu SLA
                            </button>
                        </div>
                    </form>

                    <script>
                    function toggleExemption(checked) {
                        var container = document.getElementById('exemptionReasonContainer');
                        var select = document.getElementById('selectExemptionReason');
                        if (checked) {
                            container.style.display = 'block';
                            select.required = true;
                        } else {
                            container.style.display = 'none';
                            select.required = false;
                            select.value = '';
                        }
                    }
                    </script>

                <?php else: ?>
                    <!-- Status Resolved / Closed -->
                    <div class="alert alert-success mb-0 border-success">
                        <h6 class="fw-bold text-success mb-2"><i class="fas fa-check-circle me-1"></i> Tiket Telah Selesai Diperbaiki</h6>
                        <div class="small mb-1"><strong>Penyebab:</strong> <?= htmlspecialchars($ticket['root_cause'] ?? '-') ?></div>
                        <div class="small mb-1"><strong>Tindakan:</strong> <?= htmlspecialchars($ticket['action_taken'] ?? '-') ?></div>
                        <div class="small mb-2"><strong>Catatan Teknis:</strong> <?= nl2br(htmlspecialchars($ticket['technician_notes'] ?? '-')) ?></div>
                        <?php if ($ticket['sla_status'] === 'exempted'): ?>
                            <div class="p-2 rounded border mt-2 small" style="background: #f5f3ff; border-color: #ddd6fe !important;">
                                <span class="badge" style="background:#7c3aed; color:#fff;"><i class="fas fa-shield-alt me-1"></i> SLA EXEMPTED</span>
                                <strong class="text-dark ms-1">Alasan Pengecualian:</strong> <?= htmlspecialchars($ticket['sla_exemption_reason'] ?? 'Force Majeure') ?>
                            </div>
                        <?php endif; ?>
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
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Status Evaluasi SLA:</span>
                        <?= get_sla_badge($ticket['sla_status'], $ticket['sla_deadline'], $ticket['resolved_at'], $ticket['sla_exemption_reason'] ?? null) ?>
                    </li>
                    <?php if (!empty($ticket['sla_paused_total_minutes']) && (int)$ticket['sla_paused_total_minutes'] > 0): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted"><i class="fas fa-pause-circle text-warning me-1"></i> Total Jeda SLA:</span>
                            <span class="badge bg-warning text-dark font-monospace"><?= (int)$ticket['sla_paused_total_minutes'] ?> Menit</span>
                        </li>
                    <?php endif; ?>
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
