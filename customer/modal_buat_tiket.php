<?php
/**
 * Komponen Modal Pop-up & Handler Pembuatan Laporan Gangguan Sirkit B2B
 * PT. Visimedia Pratama Persada
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

if (!function_exists('process_create_ticket_request')) {
    function process_create_ticket_request($pdo, $user, $client_info, $redirect_url) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_ticket') {
            $category_id  = (int)($_POST['category_id'] ?? 0);
            $circuit_id   = trim($_POST['circuit_id'] ?? ($client_info['circuit_id'] ?? 'CID-DEFAULT'));
            $service_type = trim($_POST['service_type'] ?? ($client_info['service_type'] ?? 'Dedicated B2B'));
            $title        = trim($_POST['title'] ?? '');
            $location     = trim($_POST['location'] ?? ($client_info['site_address'] ?? ''));
            $description  = trim($_POST['description'] ?? '');
            $user_id      = $user['id'];
            $client_id    = $user['client_id'] ?? ($client_info['id'] ?? null);

            if (empty($category_id) || empty($title) || empty($location) || empty($description)) {
                set_flash('danger', 'Harap lengkapi semua kolom wajib yang bertanda (*)!');
                header('Location: ' . $redirect_url);
                exit;
            }

            // Prioritas default awal (Medium / ID 3). SLA final ditentukan oleh NOC Helpdesk.
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

                $stmt_ins = $pdo->prepare("INSERT INTO tickets (
                    ticket_code, client_id, user_id, category_id, priority_id, circuit_id, service_type, 
                    title, description, location, attachment, status, created_at, sla_deadline, sla_status
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW(), ?, 'pending'
                )");
                $stmt_ins->execute([
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
                $company_label = $client_info['company_name'] ?? $user['name'];
                $stmt_log = $pdo->prepare("INSERT INTO ticket_logs (ticket_id, user_id, action, note, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt_log->execute([
                    $new_ticket_id, $user_id, 'Tiket Dibuat', "PIC Klien ({$company_label}) menerbitkan laporan gangguan sirkit {$circuit_id} via Portal Klien. Menunggu verifikasi tingkat gangguan dan penugasan teknisi oleh NOC Helpdesk."
                ]);

                $pdo->commit();

                // Kirim notifikasi email
                $mail_res = send_ticket_notification($new_ticket_id, 'ticket_created');
                $user_email = $user['email'] ?? '';
                $email_note = ($mail_res['success']) ? " Bukti laporan resmi telah dikirim ke email Anda ({$user_email})." : "";

                set_flash('success', "Laporan gangguan berhasil diterbitkan dengan nomor <strong>{$ticket_code}</strong>.{$email_note} Tim NOC 24/7 PT. Visimedia Pratama Persada segera memvalidasi dan menugaskan teknisi!");
                header('Location: ' . $redirect_url);
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                set_flash('danger', 'Terjadi kesalahan saat menyimpan data tiket: ' . $e->getMessage());
                header('Location: ' . $redirect_url);
                exit;
            }
        }
    }
}

if (!function_exists('render_modal_buat_tiket')) {
    function render_modal_buat_tiket($pdo, $user, $client_info) {
        $modal_categories = [];
        try {
            $modal_categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
        } catch (Exception $e) {
            $modal_categories = [];
        }
        ?>
        <!-- Modal Pop-up Buat Laporan Gangguan Sirkit B2B -->
        <div class="modal fade" id="modalBuatTiket" tabindex="-1" aria-labelledby="modalBuatTiketLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="modal-header bg-primary text-white py-3 px-4">
                        <h5 class="modal-title fw-bold" id="modalBuatTiketLabel">
                            <i class="fas fa-ticket-alt me-2"></i> Form Lapor Gangguan Sirkit Jaringan B2B
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create_ticket">
                        <input type="hidden" name="circuit_id" value="<?= htmlspecialchars($client_info['circuit_id'] ?? 'CID-DEFAULT') ?>">
                        <input type="hidden" name="service_type" value="<?= htmlspecialchars($client_info['service_type'] ?? 'Dedicated B2B') ?>">

                        <div class="modal-body p-4">
                            <!-- Info Sirkit Terdaftar -->
                            <div class="row mb-3 bg-light p-3 rounded-3 border">
                                <div class="col-md-6 mb-2 mb-md-0">
                                    <label class="form-label text-muted small mb-1">Perusahaan Klien Pelapor</label>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($client_info['company_name'] ?? 'Mitra Korporat B2B') ?></div>
                                    <small class="text-secondary">PIC: <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['phone'] ?? '-') ?>)</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small mb-1">Nomor Sirkit & Layanan Terdaftar</label>
                                    <div>
                                        <span class="circuit-badge me-2"><?= htmlspecialchars($client_info['circuit_id'] ?? 'CID-DEFAULT') ?></span>
                                        <span class="badge bg-primary"><?= htmlspecialchars($client_info['bandwidth'] ?? 'Dedicated') ?></span>
                                    </div>
                                    <small class="text-secondary"><?= htmlspecialchars($client_info['service_type'] ?? 'Dedicated Internet B2B') ?></small>
                                </div>
                            </div>

                            <!-- Kategori Gangguan -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Kategori Gejala Gangguan <span class="text-danger">*</span></label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">-- Pilih Kategori Kendala Jaringan --</option>
                                    <?php foreach ($modal_categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>">
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Pilih kategori yang paling sesuai dengan indikasi gangguan di kantor / site Anda.</div>
                            </div>

                            <!-- Judul Kendala -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Ringkasan Kendala / Judul Tiket <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" placeholder="Contoh: Link Utama Dedicated Internet Down (LOS Merah pada Router)" required>
                            </div>

                            <!-- Lokasi Site Klien -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Site / Lokasi Gedung Kantor Klien <span class="text-danger">*</span></label>
                                <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($client_info['site_address'] ?? '') ?>" placeholder="Nama gedung, lantai, dan ruangan rack server" required>
                            </div>

                            <!-- Deskripsi Detail -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Deskripsi Kronologi & Gejala Gangguan <span class="text-danger">*</span></label>
                                <textarea name="description" class="form-control" rows="4" placeholder="Jelaskan secara rinci: sejak jam berapa kendala terjadi, indikator lampu pada modem/CPE (apakah LOS merah/mati), hasil uji ping/traceroute jika ada." required></textarea>
                            </div>

                            <!-- Lampiran File -->
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-dark">Lampiran Bukti (Opsional)</label>
                                <input type="file" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.docx,.txt,.log">
                                <div class="form-text">Format: JPG, PNG, PDF, TXT, LOG (Maks. 5 MB). Lampirkan screenshot hasil ping, traceroute, atau foto fisik perangkat.</div>
                            </div>

                            <!-- Catatan Otomatisasi NOC -->
                            <div class="alert alert-info py-2 small mb-0 rounded-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-info-circle fa-lg text-primary me-2"></i>
                                    <div>
                                        <strong>Penetapan Tingkat Prioritas:</strong> Tim <strong>NOC Helpdesk 24/7</strong> akan langsung melakukan verifikasi link fisik dan menetapkan tingkat urgensi teknis serta menugaskan Network Engineer ke lokasi Anda.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer py-2 px-4 bg-light">
                            <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary px-4 fw-semibold">
                                <i class="fas fa-paper-plane me-1"></i> Terbitkan Tiket Gangguan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('open_modal') === '1') {
                const modalEl = document.getElementById('modalBuatTiket');
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            }
        });
        </script>
        <?php
    }
}
