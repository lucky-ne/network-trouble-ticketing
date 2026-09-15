<?php
/**
 * Pengaturan Website, Identitas Sistem, & Konfigurasi Notifikasi Gmail
 * Panel Khusus Administrator untuk Mengedit Teks, Profil Perusahaan, SLA, & SMTP Email
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

// Proteksi Halaman
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    set_flash('danger', 'Akses ditolak! Halaman ini hanya dapat diakses oleh Administrator Sistem.');
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$pdo = get_db();
$page_title = 'Pengaturan Website & Notifikasi Gmail';

// PROSES 1: Tes Kirim Email Percobaan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_smtp') {
    $target_email = trim($_POST['target_email'] ?? '');
    if (empty($target_email) || !filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
        set_flash('danger', 'Harap masukkan alamat email tujuan pengujian yang valid!');
    } else {
        $result = send_test_email($target_email);
        if ($result['success']) {
            set_flash('success', $result['message']);
        } else {
            set_flash('danger', $result['message']);
        }
    }
    header('Location: ' . base_url('admin/pengaturan_website.php'));
    exit;
}

// PROSES 2: Simpan Perubahan Pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'save_settings')) {
    $fields = [
        'app_name',
        'system_version',
        'company_name',
        'company_tagline',
        'company_address',
        'company_phone',
        'company_email',
        'footer_text',
        'sla_alert_threshold',
        'smtp_enabled',
        'smtp_host',
        'smtp_port',
        'smtp_secure',
        'smtp_user',
        'smtp_pass',
        'smtp_from_name',
        'smtp_from_email'
    ];

    // Checkbox smtp_enabled fallback to 0 if not checked
    if (!isset($_POST['smtp_enabled'])) {
        $_POST['smtp_enabled'] = '0';
    }

    $updated_count = 0;
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $val = trim($_POST[$field]);
            update_setting($field, $val);
            $updated_count++;
        }
    }

    set_flash('success', 'Pengaturan website & notifikasi email berhasil disimpan dan langsung aktif!');
    header('Location: ' . base_url('admin/pengaturan_website.php'));
    exit;
}

// Ambil data pengaturan saat ini
$app_name            = get_setting('app_name', 'NetTicket SLA Track');
$system_version      = get_setting('system_version', 'v2.1 Pro');
$company_name        = get_setting('company_name', 'PT Visimedia Pratama Persada');
$company_tagline     = get_setting('company_tagline', 'Reliable Network & SLA Solutions');
$company_address     = get_setting('company_address', 'Gedung Cyber 2 Tower Lt. 12, Jl. HR Rasuna Said, Jakarta Selatan');
$company_phone       = get_setting('company_phone', '(021) 555-0199');
$company_email       = get_setting('company_email', 'support@visimedia.co.id');
$footer_text         = get_setting('footer_text', 'Proyek Kuliah Kerja Praktek (KKP) - Metode Waterfall & UML');
$sla_alert_threshold = get_setting('sla_alert_threshold', '80');

// Konfigurasi SMTP
$smtp_enabled        = get_setting('smtp_enabled', '0');
$smtp_host           = get_setting('smtp_host', 'smtp.gmail.com');
$smtp_port           = get_setting('smtp_port', '465');
$smtp_secure         = get_setting('smtp_secure', 'ssl');
$smtp_user           = get_setting('smtp_user', '');
$smtp_pass           = get_setting('smtp_pass', '');
$smtp_from_name      = get_setting('smtp_from_name', 'NetTicket SLA Support');
$smtp_from_email     = get_setting('smtp_from_email', '');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard.php') ?>">Dashboard Admin</a></li>
                <li class="breadcrumb-item active" aria-current="page">Pengaturan Website & Notifikasi Gmail</li>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-0 text-dark"><i class="fas fa-sliders-h text-danger me-2"></i>Pengaturan Website & Notifikasi Gmail</h3>
                <p class="text-muted small mb-0">Kelola identitas sistem, konfigurasi SLA, serta pengaturan notifikasi email otomatis ke Gmail pengguna.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTestEmail">
                    <i class="fas fa-paper-plane me-1"></i> Uji Coba / Tes Kirim Email
                </button>
                <a href="<?= base_url('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="">
    <input type="hidden" name="action" value="save_settings">
    <div class="row g-4">
        <!-- Kolom Kiri: Form Input -->
        <div class="col-lg-8">
            
            <!-- Bagian 1: Identitas Aplikasi -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-desktop text-primary me-2"></i>1. Identitas Aplikasi & Judul Header
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold small text-secondary">Nama Aplikasi / Brand Sistem <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-heading text-muted"></i></span>
                                <input type="text" name="app_name" class="form-control" value="<?= htmlspecialchars($app_name) ?>" required placeholder="Contoh: NetTicket SLA Track">
                            </div>
                            <small class="text-muted" style="font-size:0.75rem;">Akan muncul di navbar atas dan judul tab browser.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-secondary">Versi Aplikasi</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-code-branch text-muted"></i></span>
                                <input type="text" name="system_version" class="form-control" value="<?= htmlspecialchars($system_version) ?>" placeholder="v2.1 Pro">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bagian 2: Profil Perusahaan / Kantor -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-building text-success me-2"></i>2. Profil Perusahaan & Kontak Dukungan IT
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Nama Perusahaan / Instansi <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-building text-muted"></i></span>
                                <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($company_name) ?>" required placeholder="Contoh: PT Visimedia Pratama Persada">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Slogan / Tagline Perusahaan</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-quote-left text-muted"></i></span>
                                <input type="text" name="company_tagline" class="form-control" value="<?= htmlspecialchars($company_tagline) ?>" placeholder="Contoh: Reliable Network Solutions">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Email Helpdesk / IT Support</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-envelope text-muted"></i></span>
                                <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($company_email) ?>" placeholder="support@perusahaan.com">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Nomor Telepon / Hotline IT</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-phone text-muted"></i></span>
                                <input type="text" name="company_phone" class="form-control" value="<?= htmlspecialchars($company_phone) ?>" placeholder="(021) 555-0199">
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold small text-secondary">Alamat Kantor</label>
                            <textarea name="company_address" class="form-control" rows="2" placeholder="Alamat lengkap gedung kantor..."><?= htmlspecialchars($company_address) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bagian 3: Konfigurasi Notifikasi Gmail & SMTP -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-envelope-open-text text-primary me-2"></i>3. Konfigurasi Notifikasi Gmail & SMTP
                    </h5>
                    <span class="badge <?= ($smtp_enabled === '1') ? 'bg-success' : 'bg-secondary' ?>">
                        <?= ($smtp_enabled === '1') ? 'Fitur Aktif' : 'Fitur Nonaktif' ?>
                    </span>
                </div>
                <div class="card-body pt-0">
                    <p class="text-muted small mb-3">
                        Kirim notifikasi otomatis ke akun <strong>Gmail karyawan</strong> saat tiket baru dibuat, diproses oleh teknisi (In Progress), diselesaikan (Resolved), hingga ditutup (Closed).
                    </p>

                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="smtp_enabled" value="1" id="smtp_enabled" <?= ($smtp_enabled === '1') ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold text-dark" for="smtp_enabled">
                                Aktifkan Pengiriman Notifikasi Email Otomatis
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">Jika dinonaktifkan, tiket tetap tersimpan di database tanpa mengirimkan email.</small>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Email Gmail Pengirim (Akun Helpdesk / IT) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-at text-muted"></i></span>
                                <input type="email" name="smtp_user" class="form-control" value="<?= htmlspecialchars($smtp_user) ?>" placeholder="contoh: itsupport@gmail.com">
                            </div>
                            <small class="text-muted" style="font-size:0.72rem;">Alamat Gmail yang digunakan sebagai pengirim notifikasi.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Google App Password (16 Digit) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-key text-muted"></i></span>
                                <input type="password" name="smtp_pass" class="form-control" value="<?= htmlspecialchars($smtp_pass) ?>" placeholder="16 huruf sandi aplikasi Google">
                            </div>
                            <small class="text-muted" style="font-size:0.72rem;">Bukan password login Gmail biasa (Lihat panduan di samping).</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Nama Tampilan Pengirim</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-id-badge text-muted"></i></span>
                                <input type="text" name="smtp_from_name" class="form-control" value="<?= htmlspecialchars($smtp_from_name) ?>" placeholder="NetTicket SLA Support">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Alamat SMTP Server Host</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-server text-muted"></i></span>
                                <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($smtp_host) ?>" placeholder="smtp.gmail.com">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Port SMTP & Enkripsi</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($smtp_port) ?>" placeholder="465">
                                </div>
                                <div class="col-6">
                                    <select name="smtp_secure" class="form-select">
                                        <option value="ssl" <?= ($smtp_secure === 'ssl') ? 'selected' : '' ?>>SSL (Port 465)</option>
                                        <option value="tls" <?= ($smtp_secure === 'tls') ? 'selected' : '' ?>>TLS (Port 587)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Email Reply-To (Opsional)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-reply text-muted"></i></span>
                                <input type="email" name="smtp_from_email" class="form-control" value="<?= htmlspecialchars($smtp_from_email) ?>" placeholder="Kosongkan jika sama dengan Gmail pengirim">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bagian 4: Footer & Parameter SLA -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-cog text-warning me-2"></i>4. Keterangan Footer & Parameter SLA
                    </h5>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-secondary">Teks Keterangan Footer (Bawah Halaman)</label>
                            <input type="text" name="footer_text" class="form-control" value="<?= htmlspecialchars($footer_text) ?>" placeholder="Contoh: Proyek Kuliah Kerja Praktek (KKP) - Metode Waterfall & UML">
                            <small class="text-muted" style="font-size:0.75rem;">Akan tampil di bagian paling bawah setiap halaman aplikasi.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold small text-secondary">Ambang Batas Peringatan SLA (%)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fas fa-percentage text-muted"></i></span>
                                <input type="number" name="sla_alert_threshold" class="form-control" min="1" max="100" value="<?= htmlspecialchars($sla_alert_threshold) ?>">
                                <span class="input-group-text bg-light">%</span>
                            </div>
                            <small class="text-muted" style="font-size:0.75rem;">Target standar kepatuhan waktu pengerjaan tiket.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tombol Simpan -->
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger px-4 py-2 fw-semibold rounded-3 shadow-sm">
                    <i class="fas fa-save me-1"></i> Simpan Semua Pengaturan
                </button>
                <button type="reset" class="btn btn-light border px-3 py-2 rounded-3">
                    <i class="fas fa-undo me-1"></i> Reset Form
                </button>
            </div>
        </div>

        <!-- Kolom Kanan: Panduan App Password, Live Preview, & Tips -->
        <div class="col-lg-4">
            
            <!-- Card Panduan Google App Password -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 border-top border-4 border-warning">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fab fa-google text-danger me-2"></i>Panduan Google App Password
                    </h6>
                </div>
                <div class="card-body pt-0 small text-secondary">
                    <p class="mb-2">Untuk mengirim email via Gmail di XAMPP, gunakan <strong>Sandi Aplikasi (App Password)</strong>:</p>
                    <ol class="ps-3 mb-3">
                        <li class="mb-1">Buka <a href="https://myaccount.google.com/security" target="_blank" class="fw-bold text-decoration-none">Akun Google &rarr; Keamanan</a>.</li>
                        <li class="mb-1">Pastikan <strong>Verifikasi 2 Langkah (2-Step Verification)</strong> sudah <strong>Aktif</strong>.</li>
                        <li class="mb-1">Buka menu <a href="https://myaccount.google.com/apppasswords" target="_blank" class="fw-bold text-decoration-none">Sandi Aplikasi (App Passwords)</a>.</li>
                        <li class="mb-1">Beri nama (misal: <em>NetTicket XAMPP</em>) lalu klik <strong>Buat</strong>.</li>
                        <li>Salin 16 digit huruf sandi yang muncul dan tempel ke kolom <strong>Google App Password</strong> di form ini.</li>
                    </ol>
                    <div class="p-2 bg-warning bg-opacity-10 border border-warning rounded-2 text-dark" style="font-size: 0.75rem;">
                        <i class="fas fa-shield-alt text-warning me-1"></i> Sandi aplikasi ini aman dan tidak membagikan kata sandi utama akun Gmail Anda.
                    </div>
                </div>
            </div>

            <!-- Card Preview Navbar -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-eye text-info me-2"></i>Pratinjau Tampilan Header
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="p-3 bg-dark text-white rounded-3 shadow-sm" style="background: #0f172a !important;">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-primary text-white p-2 rounded-3">
                                <i class="fas fa-network-wired"></i>
                            </div>
                            <div>
                                <div class="fw-bold lh-1 text-white small"><?= htmlspecialchars($app_name) ?></div>
                                <small class="text-white-50" style="font-size:0.7rem;"><?= htmlspecialchars($company_name) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Preview Footer -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-window-minimize text-secondary me-2"></i>Pratinjau Tampilan Footer
                    </h6>
                </div>
                <div class="card-body pt-0">
                    <div class="p-3 bg-light border rounded-3 text-center">
                        <div class="small fw-semibold text-dark">&copy; <?= date('Y') ?> <?= htmlspecialchars($app_name) ?> - <?= htmlspecialchars($company_tagline) ?></div>
                        <small class="text-secondary" style="font-size:0.72rem;"><?= htmlspecialchars($footer_text) ?></small>
                    </div>
                </div>
            </div>

            <!-- Tips Presentasi Dosen -->
            <div class="card border-0 bg-primary bg-opacity-10 rounded-4 p-3 border-start border-4 border-primary">
                <div class="d-flex gap-2">
                    <i class="fas fa-lightbulb text-primary fa-lg mt-1"></i>
                    <div>
                        <div class="fw-bold text-primary small">Tips Saat Ujian / Sidang KKP:</div>
                        <p class="small text-secondary mb-0 mt-1">
                            Anda dapat mendemonstrasikan fitur notifikasi email ini di depan dosen penguji dengan memasukkan email penguji saat membuat tiket gangguan, lalu tunjukkan notifikasi real-time yang langsung masuk ke kotak masuk Gmail penguji!
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</form>

<!-- Modal Uji Coba Kirim Email -->
<div class="modal fade" id="modalTestEmail" tabindex="-1" aria-labelledby="modalTestEmailLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <form method="POST" action="">
                <input type="hidden" name="action" value="test_smtp">
                <div class="modal-header bg-light border-0">
                    <h5 class="modal-title fw-bold text-dark" id="modalTestEmailLabel">
                        <i class="fas fa-paper-plane text-primary me-2"></i>Uji Coba Pengiriman Email SMTP
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Kirimkan email uji coba ke alamat Gmail Anda untuk memastikan akun pengirim dan konfigurasi SMTP telah terhubung dengan benar.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small text-secondary">Kirim Email Tes ke Alamat: <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fas fa-envelope text-muted"></i></span>
                            <input type="email" name="target_email" class="form-control" required placeholder="nama_anda@gmail.com" value="<?= htmlspecialchars($_SESSION['user']['email'] ?? '') ?>">
                        </div>
                        <small class="text-muted" style="font-size:0.75rem;">Pastikan Anda sudah mengklik <strong>Simpan Semua Pengaturan</strong> sebelum menguji.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4">
                        <i class="fas fa-paper-plane me-1"></i> Kirim Email Tes Sekarang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
