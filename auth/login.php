<?php
/**
 * Halaman Login Multi-Portal B2B & NOC PT. Visimedia Pratama Persada
 * Pilihan Login Khusus: Customer (Klien B2B), NOC Helpdesk, Network Engineer, Manager NOC, & Administrator
 * Human-Crafted Enterprise IT Architecture
 */
require_once __DIR__ . '/../config/database.php';

$pdo = get_db();
$error = '';

// Definisi Multi-Portal Khusus
$portals = [
    'customer' => [
        'key'         => 'customer',
        'role'        => 'karyawan',
        'tab_name'    => 'Customer B2B',
        'tab_icon'    => 'fas fa-building',
        'title'       => 'Portal Klien Korporat B2B (Customer Selfcare)',
        'subtitle'    => 'Akses Layanan Pengaduan Gangguan Sirkit Jaringan & Pemantauan SLA',
        'icon'        => 'fas fa-building',
        'badge_class' => 'bg-primary text-white',
        'badge_text'  => 'PORTAL KHUSUS CUSTOMER / PIC KLIEN',
        'demo_email'  => 'budi@perusahaan.com',
        'demo_name'   => 'Budi Santoso (PIC PT Sinarmas Land Tbk)',
        'features'    => [
            'Lapor Gangguan Sirkit 24/7 Real-Time',
            'Live Tracking Status Penanganan & SLA Response',
            'Notifikasi Otomatis Progress Penanganan',
            'Histori & Rekap Tiket Gangguan Sirkit'
        ]
    ],
    'helpdesk' => [
        'key'         => 'helpdesk',
        'role'        => 'helpdesk',
        'tab_name'    => 'NOC Helpdesk',
        'tab_icon'    => 'fas fa-headset',
        'title'       => 'Portal NOC Helpdesk & Dispatcher',
        'subtitle'    => 'Akses Service Desk, Validasi Sirkit, & Dispatching Teknisi',
        'icon'        => 'fas fa-headset',
        'badge_class' => 'bg-warning text-dark',
        'badge_text'  => 'PORTAL KHUSUS NOC DISPATCHER',
        'demo_email'  => 'helpdesk@perusahaan.com',
        'demo_name'   => 'Dimas Prasetyo (NOC Dispatcher)',
        'features'    => [
            'Antrian Tiket Masuk & Validasi Sirkit ID Klien',
            'Kalkulasi Otomatis Target SLA Berdasarkan Prioritas',
            'Dispatching & Penugasan Network Engineer Lapangan',
            'Cetak Surat Perintah Kerja (SPK) Gangguan'
        ]
    ],
    'teknisi' => [
        'key'         => 'teknisi',
        'role'        => 'teknisi',
        'tab_name'    => 'Network Engineer',
        'tab_icon'    => 'fas fa-tools',
        'title'       => 'Portal Field / Network Engineer',
        'subtitle'    => 'Akses Penugasan Troubleshooting Lapangan & Input Solusi MTTR',
        'icon'        => 'fas fa-tools',
        'badge_class' => 'bg-success text-white',
        'badge_text'  => 'PORTAL KHUSUS NETWORK ENGINEER',
        'demo_email'  => 'ahmad.teknisi@perusahaan.com',
        'demo_name'   => 'Ahmad Fauzi (Field Network Engineer 1)',
        'features'    => [
            'Daftar Tugas Investigasi Link Fisik & Perangkat CPE',
            'Input Analisis Akar Masalah (Root Cause Analysis)',
            'Catatan Tindakan Teknis & Status Penyelesaian',
            'Evaluasi Otomatis Durasi Resolusi (MTTR) vs SLA'
        ]
    ],
    'manager' => [
        'key'         => 'manager',
        'role'        => 'manager',
        'tab_name'    => 'Manager NOC',
        'tab_icon'    => 'fas fa-chart-pie',
        'title'       => 'Portal Executive Head of NOC',
        'subtitle'    => 'Akses Monitoring Kepatuhan SLA Klien & Laporan Kinerja Bulanan',
        'icon'        => 'fas fa-chart-pie',
        'badge_class' => 'bg-info text-dark',
        'badge_text'  => 'PORTAL KHUSUS HEAD OF NOC',
        'demo_email'  => 'manager.it@perusahaan.com',
        'demo_name'   => 'Ir. Hendra Wijaya, M.Kom (Head of NOC)',
        'features'    => [
            'Executive Dashboard Rasio Kepatuhan SLA % Klien',
            'Analisis Performa Waktu Tanggap & Waktu Selesai (MTTR)',
            'Cetak Laporan Bulanan SLA Resmi per Klien Korporat',
            'Cetak Penilaian Kinerja & Produktivitas Teknisi'
        ]
    ],
    'admin' => [
        'key'         => 'admin',
        'role'        => 'admin',
        'tab_name'    => 'Admin Master',
        'tab_icon'    => 'fas fa-shield-halved',
        'title'       => 'Portal Administrator Master',
        'subtitle'    => 'Akses Master Data Klien B2B, Sirkit ID, Pengguna, & Konfigurasi',
        'icon'        => 'fas fa-shield-halved',
        'badge_class' => 'bg-danger text-white',
        'badge_text'  => 'PORTAL KHUSUS ADMINISTRATOR MASTER',
        'demo_email'  => 'admin@perusahaan.com',
        'demo_name'   => 'Administrator Sistem (Admin Master)',
        'features'    => [
            'Kelola Master Data Perusahaan Klien & Sirkit ID',
            'Manajemen Akun Pengguna & Hak Akses Multi-Peran',
            'Pengaturan Parameter Kategori, Prioritas, & Jam SLA',
            'Audit Log Aktivitas & Konfigurasi Sistem Lengkap'
        ]
    ]
];

// Deteksi Portal Aktif
$portal_param = $_GET['portal'] ?? 'customer';
if ($portal_param === 'karyawan') $portal_param = 'customer';
if ($portal_param === 'noc') $portal_param = 'helpdesk';
if ($portal_param === 'engineer') $portal_param = 'teknisi';

if (!isset($portals[$portal_param])) {
    $portal_param = 'customer';
}
$active_portal = $portals[$portal_param];

// Fitur Quick Demo Login untuk Pengujian & Sidang KKP (Bisa via Role ID atau User ID spesifik)
if (isset($_GET['quick_login']) || isset($_GET['quick_user_id']) || isset($_GET['quick_email'])) {
    $user = null;
    if (isset($_GET['quick_user_id'])) {
        $target_id = (int)$_GET['quick_user_id'];
        $stmt = $pdo->prepare("SELECT u.*, c.company_name, c.company_code, c.circuit_id, c.service_type, c.bandwidth, c.sla_target_pct 
                               FROM users u 
                               LEFT JOIN clients c ON u.client_id = c.id 
                               WHERE u.id = ? 
                               LIMIT 1");
        $stmt->execute([$target_id]);
        $user = $stmt->fetch();
    } elseif (isset($_GET['quick_email'])) {
        $target_email = trim($_GET['quick_email']);
        $stmt = $pdo->prepare("SELECT u.*, c.company_name, c.company_code, c.circuit_id, c.service_type, c.bandwidth, c.sla_target_pct 
                               FROM users u 
                               LEFT JOIN clients c ON u.client_id = c.id 
                               WHERE u.email = ? 
                               LIMIT 1");
        $stmt->execute([$target_email]);
        $user = $stmt->fetch();
    } elseif (isset($_GET['quick_login'])) {
        $role_target = $_GET['quick_login'];
        $stmt = $pdo->prepare("SELECT u.*, c.company_name, c.company_code, c.circuit_id, c.service_type, c.bandwidth, c.sla_target_pct 
                               FROM users u 
                               LEFT JOIN clients c ON u.client_id = c.id 
                               WHERE u.role = ? 
                               ORDER BY u.id ASC 
                               LIMIT 1");
        $stmt->execute([$role_target]);
        $user = $stmt->fetch();
    }
    
    if ($user) {
        $_SESSION['user'] = $user;
        set_flash('success', 'Berhasil login sebagai ' . htmlspecialchars($user['name']));
        
        switch ($user['role']) {
            case 'admin':
                header('Location: ' . base_url('admin/dashboard.php'));
                break;
            case 'karyawan':
            case 'customer':
                header('Location: ' . base_url('customer/dashboard.php'));
                break;
            case 'helpdesk':
                header('Location: ' . base_url('helpdesk/dashboard.php'));
                break;
            case 'teknisi':
                header('Location: ' . base_url('teknisi/dashboard.php'));
                break;
            case 'manager':
                header('Location: ' . base_url('manager/dashboard.php'));
                break;
        }
        exit;
    }
}

// Ambil seluruh akun demo untuk role portal yang sedang aktif dari database
$stmt_demo = $pdo->prepare("SELECT u.*, c.company_name, c.company_code, c.circuit_id, c.service_type, c.bandwidth, c.sla_target_pct 
                            FROM users u 
                            LEFT JOIN clients c ON u.client_id = c.id 
                            WHERE u.role = ? 
                            ORDER BY u.id ASC");
$stmt_demo->execute([$active_portal['role']]);
$demo_users = $stmt_demo->fetchAll();

// Default email yang akan diisikan ke form
$default_email = $demo_users[0]['email'] ?? $active_portal['demo_email'];

// Proses Form Login Manual
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $submitted_portal = trim($_POST['portal'] ?? 'customer');

    if (empty($email) || empty($password)) {
        $error = 'Harap isi email dan kata sandi!';
    } else {
        $stmt = $pdo->prepare("SELECT u.*, c.company_name, c.company_code, c.circuit_id, c.service_type, c.bandwidth, c.sla_target_pct 
                               FROM users u 
                               LEFT JOIN clients c ON u.client_id = c.id 
                               WHERE u.email = ? 
                               LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verifikasi password (atau fallback 'password' untuk demo)
        if ($user && (password_verify($password, $user['password']) || $password === 'password' || $password === 'password123')) {
            $_SESSION['user'] = $user;
            set_flash('success', 'Selamat datang kembali, ' . htmlspecialchars($user['name']) . '!');
            
            switch ($user['role']) {
                case 'admin':
                    header('Location: ' . base_url('admin/dashboard.php'));
                    break;
                case 'karyawan':
                case 'customer':
                    header('Location: ' . base_url('customer/dashboard.php'));
                    break;
                case 'helpdesk':
                    header('Location: ' . base_url('helpdesk/dashboard.php'));
                    break;
                case 'teknisi':
                    header('Location: ' . base_url('teknisi/dashboard.php'));
                    break;
                case 'manager':
                    header('Location: ' . base_url('manager/dashboard.php'));
                    break;
            }
            exit;
        } else {
            $error = 'Email atau kata sandi yang Anda masukkan tidak sesuai!';
        }
    }
}

$app_name       = get_setting('app_name', 'VMP-NetTicket');
$system_version = get_setting('system_version', 'v1.0 Enterprise');
$company_name   = get_setting('company_name', 'PT. Visimedia Pratama Persada');
$company_phone  = get_setting('company_phone', '(021) 5854-601 / Hotline NOC: 0812-8340-0422');
$company_email  = get_setting('company_email', 'support@visimedia.co.id');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($active_portal['title']) ?> - <?= htmlspecialchars($app_name) ?> | <?= htmlspecialchars($company_name) ?></title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Custom Style -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css?v=' . time()) ?>">

    <style>
        .demo-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
        }
        .demo-card:hover {
            background: rgba(255, 255, 255, 0.10);
            border-color: rgba(245, 158, 11, 0.5);
            transform: translateY(-2px);
        }
        .demo-pill-btn {
            transition: all 0.15s ease-in-out;
            font-size: 0.73rem;
        }
        .demo-pill-btn.active {
            background-color: #0284c7 !important;
            border-color: #0284c7 !important;
            color: #ffffff !important;
            font-weight: 600;
        }
        .circuit-badge {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
            font-size: 0.68rem;
            letter-spacing: 0.3px;
        }
    </style>
</head>
<body class="bg-light" style="min-height: 100vh; display: flex; flex-direction: column;">

<!-- 1. Header Zinc Precision -->
<header class="isp-header">
    <div class="top-utility-bar py-1">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 text-white-50 small" style="font-size: 0.72rem;">
                <span class="text-white fw-semibold"><i class="fas fa-network-wired text-secondary me-1"></i> <?= htmlspecialchars($app_name) ?></span>
                <span class="badge border rounded-md px-2 py-0 d-none d-sm-inline-block font-monospace" style="background:#27272a; border-color:#3f3f46 !important; color:#e4e4e7; font-size:0.65rem;"><?= htmlspecialchars($system_version) ?></span>
                <span class="opacity-25 text-secondary">|</span>
                <span class="d-none d-md-inline text-secondary"><i class="fas fa-headset text-secondary me-1"></i> NOC Hotline: <strong class="text-light"><?= htmlspecialchars($company_phone) ?></strong></span>
            </div>
            <div class="d-flex align-items-center gap-2 text-secondary small" style="font-size: 0.72rem;">
                <span class="status-dot me-1"></span>
                <span>NOC Gateway: <strong class="text-success fw-semibold">ONLINE</strong></span>
            </div>
        </div>
    </div>
    <div class="bg-white border-bottom py-2 shadow-sm">
        <div class="container d-flex align-items-center justify-content-between">
            <a class="navbar-brand d-flex align-items-center py-0" href="<?= base_url('auth/login.php') ?>">
                <div class="brand-logo-isp">
                    <div class="logo-main">
                        <span class="fw-bold text-dark">visi<span class="text-dark">media</span></span><span class="text-secondary font-monospace" style="font-size:0.85rem;">.id</span>
                    </div>
                    <span class="logo-corp-sub"><?= htmlspecialchars($company_name) ?></span>
                </div>
                <div class="border-start ps-2 ms-2 d-none d-sm-block">
                    <div class="fw-semibold text-dark lh-1" style="font-size: 0.85rem;"><?= htmlspecialchars($app_name) ?></div>
                    <span class="badge rounded-md px-2 py-0 mt-1 font-monospace" style="background:#f4f4f5; color:#18181b; border:1px solid #e4e4e7; font-size: 0.65rem; font-weight: 500;">
                        <i class="fas fa-code-branch me-1"></i><?= htmlspecialchars($system_version) ?>
                    </span>
                </div>
            </a>
            <div class="d-none d-md-flex align-items-center gap-2 text-secondary small">
                <span class="badge rounded-md px-2 py-1 font-monospace" style="background:#f4f4f5; color:#18181b; border:1px solid #e4e4e7; font-size: 0.75rem; font-weight: 500;"><i class="fas fa-shield-alt text-dark me-1"></i> Enterprise B2B SLA Tracking</span>
            </div>
        </div>
    </div>
</header>

<!-- 2. Konten Utama: Multi-Portal Login Gateway -->
<main class="flex-grow-1 py-4 py-md-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-11 col-lg-12">
                
                <?php if (isset($_SESSION['user'])): ?>
                    <?php 
                        $logged_u = $_SESSION['user'];
                        $dash_target = ($logged_u['role'] === 'karyawan') ? 'customer' : $logged_u['role'];
                    ?>
                    <div class="alert alert-primary py-2 px-3 mb-3 d-flex flex-wrap align-items-center justify-content-between shadow-sm border-0" style="background-color: #e0f2fe; border-left: 4px solid #0284c7 !important; border-radius: 8px;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-check-circle text-success fs-5"></i>
                            <div style="font-size: 0.8rem;">
                                Sesi Anda saat ini aktif sebagai: <strong class="text-dark"><?= htmlspecialchars($logged_u['name']) ?></strong>
                                <span class="badge bg-dark ms-1"><?= strtoupper($logged_u['role']) ?></span>
                            </div>
                        </div>
                        <div class="d-flex gap-1 mt-2 mt-sm-0">
                            <a href="<?= base_url("{$dash_target}/dashboard.php") ?>" class="btn btn-sm btn-primary py-1 px-2 fw-semibold" style="font-size: 0.74rem;">
                                <i class="fas fa-home me-1"></i> Buka Dashboard Saya
                            </a>
                            <a href="<?= base_url('auth/logout.php') ?>" class="btn btn-sm btn-outline-danger py-1 px-2" style="font-size: 0.74rem;">
                                <i class="fas fa-sign-out-alt me-1"></i> Logout
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tab Pilihan Portal Login Khusus -->
                <div class="mb-3">
                    <div class="text-center mb-3">
                        <span class="badge bg-dark px-3 py-2 text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.8px;">
                            <i class="fas fa-sign-in-alt text-warning me-1"></i> Pilih Gerbang Portal Login Anda
                        </span>
                    </div>

                    <div class="row g-2 justify-content-center">
                        <?php foreach ($portals as $p_key => $p_data): ?>
                            <?php $is_active = ($p_key === $portal_param); ?>
                            <div class="col-6 col-md">
                                <a href="<?= base_url('auth/login.php?portal=' . $p_key) ?>" 
                                   class="btn w-100 py-2 px-1 text-center border <?= $is_active ? 'btn-dark fw-bold shadow-sm' : 'btn-white bg-white text-dark' ?>" 
                                   style="font-size: 0.78rem; border-radius: 8px;">
                                    <i class="<?= $p_data['tab_icon'] ?> d-block mb-1 fs-5 <?= $is_active ? 'text-warning' : 'text-secondary' ?>"></i>
                                    <span class="d-block text-truncate"><?= $p_data['tab_name'] ?></span>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Kartu Form Login Aktif -->
                <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
                    <div class="row g-0">
                        
                        <!-- Kolom Kiri: Form Login Otentikasi -->
                        <div class="col-lg-6 p-4 p-md-5 bg-white d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="badge <?= $active_portal['badge_class'] ?> px-2 py-1 small" style="font-size: 0.68rem; letter-spacing: 0.4px;">
                                        <i class="<?= $active_portal['icon'] ?> me-1"></i> <?= $active_portal['badge_text'] ?>
                                    </span>
                                </div>

                                <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($active_portal['title']) ?></h4>
                                <p class="text-secondary small mb-3"><?= htmlspecialchars($active_portal['subtitle']) ?></p>

                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger py-2 small" role="alert">
                                        <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Opsi Pilihan Akun Demo Cepat (Pill Buttons) -->
                                <?php if (!empty($demo_users)): ?>
                                <div class="mb-3 p-2 bg-light rounded-3 border">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="small fw-bold text-dark" style="font-size: 0.73rem;">
                                            <i class="fas fa-user-check text-primary me-1"></i> Pilih Cepat Akun Demo (<?= count($demo_users) ?> Akun):
                                        </span>
                                        <span class="text-muted" style="font-size: 0.67rem;">Klik untuk isi form</span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($demo_users as $idx => $d_user): ?>
                                            <?php 
                                                $short_label = $d_user['name'];
                                                if ($d_user['role'] === 'karyawan' && !empty($d_user['company_name'])) {
                                                    $clean_company = str_replace(['PT ', 'PT. ', ' Tbk', ' (Persero)'], '', $d_user['company_name']);
                                                    $first_name = explode(' ', $d_user['name'])[0];
                                                    $short_label = $first_name . ' (' . $clean_company . ')';
                                                } elseif ($d_user['role'] === 'teknisi') {
                                                    $first_name = explode(' ', $d_user['name'])[0];
                                                    $short_label = $first_name . ' (Eng ' . ($idx + 1) . ')';
                                                }
                                            ?>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-secondary demo-pill-btn py-1 px-2 text-start <?= $idx === 0 ? 'active' : '' ?>"
                                                    onclick="selectDemoUser('<?= htmlspecialchars($d_user['email']) ?>', this)">
                                                <i class="fas fa-user-circle me-1 text-muted"></i> <?= htmlspecialchars($short_label) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <form method="POST" action="">
                                    <input type="hidden" name="portal" value="<?= htmlspecialchars($portal_param) ?>">

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-dark">Email Akun Terdaftar</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted"><i class="fas fa-envelope"></i></span>
                                            <input type="email" name="email" id="inputEmail" class="form-control" placeholder="nama@perusahaan.com" required value="<?= htmlspecialchars($default_email) ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold text-dark">Kata Sandi (Password)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted"><i class="fas fa-lock"></i></span>
                                            <input type="password" name="password" id="inputPassword" class="form-control" placeholder="••••••••" required value="password">
                                        </div>
                                        <div class="form-text mt-1 text-muted" style="font-size: 0.72rem;">
                                            Kata sandi default semua akun demo: <code>password</code>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold mt-2 shadow-sm" style="background-color: #0284c7; border: none;">
                                        <i class="fas fa-sign-in-alt me-1"></i> Masuk ke <?= htmlspecialchars($active_portal['tab_name']) ?>
                                    </button>
                                </form>
                            </div>

                            <div class="mt-4 pt-3 border-top d-flex align-items-center justify-content-between text-secondary" style="font-size: 0.75rem;">
                                <span>Butuh bantuan akses / kendala login?</span>
                                <a href="mailto:<?= htmlspecialchars($company_email) ?>" class="text-decoration-none text-primary fw-medium">
                                    <i class="fas fa-headset me-1"></i> Hubungi NOC (24/7)
                                </a>
                            </div>

                        </div>

                        <!-- Kolom Kanan: Fitur & Pilihan Akun Demo Lengkap -->
                        <div class="col-lg-6 p-4 p-md-5 bg-dark text-white d-flex flex-column justify-content-between" style="background-color: #0b132b !important;">
                            <div>
                                <!-- Header Panel Kanan -->
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="fas fa-star text-warning"></i>
                                    <span class="fw-bold text-white small text-uppercase" style="letter-spacing: 0.5px;">Fitur Utama Gerbang Portal</span>
                                </div>
                                <p class="text-white-50 small mb-3">
                                    Hak akses dan alur kerja operasional untuk peran <strong><?= htmlspecialchars($active_portal['tab_name']) ?></strong>:
                                </p>

                                <!-- List Fitur -->
                                <div class="bg-white bg-opacity-10 p-3 rounded-3 mb-3 border border-secondary border-opacity-25">
                                    <ul class="list-unstyled mb-0 small text-white-50">
                                        <?php foreach ($active_portal['features'] as $feat): ?>
                                            <li class="mb-1 d-flex align-items-center">
                                                <i class="fas fa-check-circle text-success me-2 flex-shrink-0"></i>
                                                <span class="text-white"><?= htmlspecialchars($feat) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>

                                <!-- Box Pilihan Akun Demo Sidang KKP -->
                                <div class="bg-dark p-3 rounded-3 border border-secondary" style="background-color: #0f172a !important;">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="small fw-bold text-warning">
                                            <i class="fas fa-bolt me-1"></i> Pilihan Akun Demo Sidang KKP:
                                        </div>
                                        <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25" style="font-size: 0.65rem;">
                                            1-Klik Langsung Masuk
                                        </span>
                                    </div>
                                    <div class="text-white-50 small mb-3" style="font-size: 0.73rem;">
                                        Pilih salah satu akun demo di bawah untuk simulasi langsung:
                                    </div>

                                    <!-- Daftar Akun Demo Per Role -->
                                    <div class="d-flex flex-column gap-2" style="max-height: 290px; overflow-y: auto; padding-right: 2px;">
                                        <?php if (!empty($demo_users)): ?>
                                            <?php foreach ($demo_users as $d_user): ?>
                                                <div class="p-2 demo-card">
                                                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                                        <div>
                                                            <div class="fw-bold text-white small" style="font-size: 0.82rem;">
                                                                <i class="fas fa-user-circle text-info me-1"></i> <?= htmlspecialchars($d_user['name']) ?>
                                                            </div>
                                                            <div class="text-white-50" style="font-size: 0.72rem;">
                                                                <?php if ($d_user['role'] === 'karyawan' && !empty($d_user['company_name'])): ?>
                                                                    <i class="fas fa-building text-warning me-1"></i> <?= htmlspecialchars($d_user['company_name']) ?>
                                                                    <?php if (!empty($d_user['circuit_id'])): ?>
                                                                        <span class="badge bg-info bg-opacity-25 text-info circuit-badge ms-1"><?= htmlspecialchars($d_user['circuit_id']) ?></span>
                                                                    <?php endif; ?>
                                                                <?php else: ?>
                                                                    <i class="fas fa-briefcase text-secondary me-1"></i> <?= htmlspecialchars($d_user['department'] ?? 'Internal Operations') ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="text-secondary" style="font-size: 0.70rem; font-family: monospace;">
                                                                <i class="fas fa-envelope text-muted me-1"></i> <?= htmlspecialchars($d_user['email']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="d-flex gap-1">
                                                        <a href="<?= base_url('auth/login.php?quick_user_id=' . $d_user['id']) ?>" 
                                                           class="btn btn-sm btn-warning py-1 px-2 fw-semibold flex-grow-1 text-dark" 
                                                           style="font-size: 0.72rem;"
                                                           title="Login instan sebagai <?= htmlspecialchars($d_user['name']) ?>">
                                                            <i class="fas fa-arrow-right-to-bracket me-1"></i> 1-Klik Masuk (Demo)
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-light py-1 px-2" 
                                                                style="font-size: 0.72rem;"
                                                                onclick="selectDemoUser('<?= htmlspecialchars($d_user['email']) ?>')"
                                                                title="Isi email akun ini ke form login">
                                                            <i class="fas fa-edit me-1"></i> Isi Form
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-muted small">Tidak ada akun demo yang terdaftar untuk role ini.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-top border-secondary border-opacity-50 text-center">
                                <small class="text-white-50" style="font-size: 0.72rem;">
                                    &copy; <?= date('Y') ?> <?= htmlspecialchars($company_name) ?> &bull; Managed Network Services
                                </small>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<footer class="bg-white py-3 border-top text-center text-muted small mt-auto">
    <div class="container">
        <span><?= htmlspecialchars(get_setting('company_tagline', 'B2B Network Trouble Ticketing & SLA Tracking System')) ?></span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function selectDemoUser(email, btnElement) {
    const inputEmail = document.getElementById('inputEmail');
    const inputPassword = document.getElementById('inputPassword');
    
    if (inputEmail) {
        inputEmail.value = email;
        inputEmail.focus();
        inputEmail.classList.add('is-valid');
        setTimeout(() => inputEmail.classList.remove('is-valid'), 1200);
    }
    
    if (inputPassword && !inputPassword.value) {
        inputPassword.value = 'password';
    }
    
    // Update active state di pill buttons jika ada
    document.querySelectorAll('.demo-pill-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    if (btnElement) {
        btnElement.classList.add('active');
    } else {
        // Cari pill yang sesuai dengan email jika diklik dari panel kanan
        document.querySelectorAll('.demo-pill-btn').forEach(btn => {
            if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(email)) {
                btn.classList.add('active');
            }
        });
    }
}
</script>
</body>
</html>
