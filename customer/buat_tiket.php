<?php
/**
 * Form Lapor Gangguan Sirkit Jaringan B2B - PT. Visimedia Pratama Persada
 * Khusus PIC Klien Korporat B2B
 * Tingkat Prioritas & SLA Ditentukan oleh NOC Helpdesk
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/mailer.php';
check_auth(['karyawan', 'customer']);

$pdo = get_db();
$user = $_SESSION['user'];
$user_id = $user['id'];
$client_id = $user['client_id'] ?? null;

// Ambil Informasi Klien B2B
$stmt_c = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt_c->execute([$client_id]);
$client = $stmt_c->fetch();

// Ambil Kategori Gangguan
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = (int)($_POST['category_id'] ?? 0);
    $circuit_id  = trim($_POST['circuit_id'] ?? ($client['circuit_id'] ?? 'CID-DEFAULT'));
    $service_type= trim($_POST['service_type'] ?? ($client['service_type'] ?? 'Dedicated B2B'));
    $title       = trim($_POST['title'] ?? '');
    $location    = trim($_POST['location'] ?? ($client['site_address'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    
    if (empty($category_id) || empty($title) || empty($location) || empty($description)) {
        $error = 'Harap lengkapi semua kolom wajib yang bertanda (*)!';
    } else {
        // Prioritas default awal (Medium / ID 3). SLA final dan tingkat prioritas akan ditentukan oleh NOC Helpdesk saat verifikasi & disposisi.
        $stmt_def_p = $pdo->query("SELECT id, sla_hours FROM priorities WHERE id = 3 LIMIT 1");
        $p_data = $stmt_def_p->fetch();
        if (!$p_data) {
            $stmt_def_p = $pdo->query("SELECT id, sla_hours FROM priorities ORDER BY id ASC LIMIT 1");
            $p_data = $stmt_def_p->fetch();
        }
        $priority_id = $p_data['id'] ?? 3;
        $sla_hours   = $p_data['sla_hours'] ?? 8;

        // Generate Ticket Code yang Unik: TKT-YYYYMMDD-XXXX
        $date_code = date('Ymd');
        $stmt_codes = $pdo->prepare("SELECT ticket_code FROM tickets WHERE ticket_code LIKE ?");
        $stmt_codes->execute(["TKT-{$date_code}-%"]);
        $existing_codes = $stmt_codes->fetchAll(PDO::FETCH_COLUMN);

        $max_num = 0;
        foreach ($existing_codes as $code) {
            $parts = explode('-', $code);
            $num = (int)end($parts);
            if ($num > $max_num) {
                $max_num = $num;
            }
        }

        $next_num = $max_num + 1;
        $ticket_code = sprintf("TKT-%s-%03d", $date_code, $next_num);

        // Upload Attachment jika ada
        $attachment_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/';
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }
            @chmod($upload_dir, 0777);
            $file_tmp = $_FILES['attachment']['tmp_name'];
            $file_name = $_FILES['attachment']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'txt', 'log'];

            if (in_array($ext, $allowed)) {
                $new_file_name = 'TKT_' . time() . '_' . uniqid() . '.' . $ext;
                $target_path = $upload_dir . $new_file_name;
                if (@move_uploaded_file($file_tmp, $target_path)) {
                    $attachment_path = 'uploads/' . $new_file_name;
                    @chmod($target_path, 0666);
                }
            }
        }

        // Hitung SLA Deadline Sementara
        $sla_deadline = date('Y-m-d H:i:s', strtotime("+{$sla_hours} hours"));

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO tickets (
                ticket_code, client_id, user_id, category_id, priority_id, circuit_id, service_type, 
                title, description, location, attachment, status, created_at, sla_deadline, sla_status
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW(), ?, 'pending'
            )");
            $stmt->execute([
                $ticket_code, 
                $client_id ?? 1, 
                $user_id, 
                $category_id, 
                $priority_id, 
                $circuit_id, 
                $service_type, 
                $title, 
                $description, 
                $location, 
                $attachment_path, 
                $sla_deadline
            ]);
            $new_ticket_id = $pdo->lastInsertId();

            // Insert Ticket Log
            $company_label = $client['company_name'] ?? $user['name'];
            $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, note, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_log->execute([
                $new_ticket_id, $user_id, 'Tiket Dibuat', "PIC Klien ({$company_label}) menerbitkan laporan gangguan sirkit {$circuit_id}. Menunggu verifikasi tingkat gangguan dan penugasan teknisi oleh NOC Helpdesk."
            ]);

            $pdo->commit();

            // Kirim notifikasi email
            $mail_res = send_ticket_notification($new_ticket_id, 'ticket_created');
            $user_email = $user['email'] ?? '';
            $email_note = ($mail_res['success']) ? " Bukti laporan resmi telah dikirim ke email Anda ({$user_email})." : "";

            set_flash('success', "Laporan gangguan berhasil diterbitkan dengan nomor <strong>{$ticket_code}</strong>.{$email_note} Tim NOC 24/7 PT. Visimedia Pratama Persada segera memvalidasi dan menugaskan teknisi!");
            header('Location: ' . base_url('customer/dashboard.php'));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan saat menyimpan data tiket: ' . $e->getMessage();
        }
    }
}

$page_title = 'Buat Laporan Gangguan Sirkit B2B';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <a href="<?= base_url('customer/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
                </a>
                <h4 class="fw-bold text-dark mb-0">
                    <i class="fas fa-ticket-alt text-primary me-2"></i>Form Lapor Gangguan Sirkit Jaringan B2B
                </h4>
                <p class="text-secondary small mb-0">Laporkan kendala link, degradasi kecepatan, atau kegagalan perangkat sewa ke NOC PT. Visimedia Pratama Persada.</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2 small" role="alert">
                <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <span class="fw-semibold text-dark"><i class="fas fa-edit me-1"></i> Rincian Informasi Gangguan & Sirkit</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="" enctype="multipart/form-data">
                    
                    <!-- Info Klien & Sirkit Terdaftar -->
                    <div class="row mb-3 bg-light p-3 rounded border">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <label class="form-label text-muted small mb-1">Perusahaan Klien Pelapor</label>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($client['company_name'] ?? 'Mitra Korporat B2B') ?></div>
                            <small class="text-secondary">PIC: <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['phone'] ?? '-') ?>)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small mb-1">Nomor Sirkit & Layanan Terdaftar</label>
                            <div>
                                <span class="circuit-badge me-2"><?= htmlspecialchars($client['circuit_id'] ?? 'CID-VMI-0101') ?></span>
                                <span class="badge bg-primary"><?= htmlspecialchars($client['bandwidth'] ?? 'Dedicated') ?></span>
                            </div>
                            <small class="text-secondary"><?= htmlspecialchars($client['service_type'] ?? 'Dedicated Internet B2B') ?></small>
                            <input type="hidden" name="circuit_id" value="<?= htmlspecialchars($client['circuit_id'] ?? 'CID-VMI-0101') ?>">
                            <input type="hidden" name="service_type" value="<?= htmlspecialchars($client['service_type'] ?? 'Dedicated Internet B2B') ?>">
                        </div>
                    </div>

                    <!-- Kategori Gangguan -->
                    <div class="mb-3">
                        <label class="form-label">Kategori Gejala Gangguan <span class="text-danger">*</span></label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- Pilih Kategori Kendala Jaringan --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Pilih kategori yang paling sesuai dengan indikasi gangguan yang dialami di kantor Anda.</div>
                    </div>

                    <!-- Judul Keluhan -->
                    <div class="mb-3">
                        <label class="form-label">Ringkasan Kendala / Judul Tiket <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Contoh: Link Utama Dedicated Internet Down Total (LOS Merah pada Router)" required>
                    </div>

                    <!-- Lokasi Site Klien -->
                    <div class="mb-3">
                        <label class="form-label">Site / Lokasi Gedung Kantor Klien <span class="text-danger">*</span></label>
                        <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($client['site_address'] ?? '') ?>" placeholder="Nama gedung, lantai, dan ruangan rack server" required>
                    </div>

                    <!-- Deskripsi Detail & Log Ping -->
                    <div class="mb-3">
                        <label class="form-label">Deskripsi Kronologi & Gejala Gangguan <span class="text-danger">*</span></label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Jelaskan secara rinci: sejak jam berapa kendala terjadi, indikator lampu pada modem/CPE (apakah LOS merah/mati), hasil uji ping/traceroute jika ada." required></textarea>
                    </div>

                    <!-- Lampiran File -->
                    <div class="mb-3">
                        <label class="form-label">Lampiran Bukti (Opsional)</label>
                        <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.docx,.txt,.log">
                        <div class="form-text">Format: JPG, PNG, PDF, TXT, LOG (Maks. 5 MB). Lampirkan screenshot hasil ping, traceroute, atau foto fisik perangkat.</div>
                    </div>

                    <!-- Catatan Otomatisasi NOC -->
                    <div class="alert alert-info py-2 small mb-4">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle fa-lg text-primary me-2"></i>
                            <div>
                                <strong>Penetapan Tingkat Prioritas:</strong> Tim <strong>NOC Helpdesk 24/7</strong> akan langsung melakukan verifikasi link fisik dan menetapkan tingkat urgensi teknis serta menugaskan Network Engineer ke lokasi Anda.
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= base_url('customer/dashboard.php') ?>" class="btn btn-light border px-4">Batal</a>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            <i class="fas fa-paper-plane me-1"></i> Terbitkan Tiket Gangguan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
