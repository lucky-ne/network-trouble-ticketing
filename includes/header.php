<?php
/**
 * Header Template: Enterprise B2B Network Trouble Ticketing System
 * PT. Visimedia Pratama Persada
 * Human-Crafted Corporate Architecture
 */
require_once __DIR__ . '/../config/database.php';

$current_user = current_user();
$current_role = $current_user['role'] ?? 'guest';
$page_title   = $page_title ?? 'Sistem Network Trouble Ticketing & SLA Tracking';

$app_name        = get_setting('app_name', 'VMP-NetTicket');
$system_version  = get_setting('system_version', 'v1.0 Enterprise');
$company_name    = get_setting('company_name', 'PT. Visimedia Pratama Persada');
$company_tagline = get_setting('company_tagline', 'B2B Network Provider & Managed Service Solutions');
$company_phone   = get_setting('company_phone', '(021) 5854-601 / Hotline NOC: 0812-8340-0422');
$company_email   = get_setting('company_email', 'support@visimedia.co.id');
$company_address = get_setting('company_address', '');

$sla_modal_priorities = [];
try {
    $pdo_hdr = get_db();
    $sla_modal_priorities = $pdo_hdr->query("SELECT * FROM priorities ORDER BY sla_hours ASC")->fetchAll();
} catch (Exception $e) {
    $sla_modal_priorities = [];
}

// Helper nama role
$role_names = [
    'admin'    => 'Administrator Master',
    'karyawan' => 'PIC Klien Korporat',
    'helpdesk' => 'NOC Helpdesk Dispatcher',
    'teknisi'  => 'Field / Network Engineer',
    'manager'  => 'Head of NOC / Service Delivery'
];

$user_initials = 'VP';
if ($current_user && !empty($current_user['name'])) {
    $words = explode(' ', trim($current_user['name']));
    if (count($words) >= 2) {
        $user_initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    } else {
        $user_initials = strtoupper(substr($words[0], 0, 2));
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= htmlspecialchars($app_name) ?> | <?= htmlspecialchars($company_name) ?></title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- DataTables Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom Style (with auto-cache busting) -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css?v=' . time()) ?>">
</head>
<body>

<?php if ($current_user): ?>
<!-- =========================================================
     OXYGEN.ID STYLE 2-TIER ISP / TELCO CORPORATE HEADER
     PT. VISIMEDIA PRATAMA PERSADA
     ========================================================= -->
<header class="isp-header sticky-top no-print">
    
    <!-- TIER 1: TOP DARK UTILITY STRIP (Oxygen.id Top Bar Style) -->
    <div class="top-utility-bar py-1">
        <div class="container-fluid px-3 px-xl-4 d-flex align-items-center justify-content-between">
            <!-- Left Utility Links -->
            <div class="d-flex align-items-center gap-2 text-white-50 small" style="font-size: 0.74rem;">
                <span class="text-white fw-semibold"><i class="fas fa-network-wired text-info me-1"></i> <?= htmlspecialchars($app_name) ?></span>
                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill px-2 py-0 d-none d-sm-inline-block" style="font-size:0.65rem;"><?= htmlspecialchars($system_version) ?></span>
                <span class="opacity-25">|</span>
                <span class="d-none d-md-inline"><i class="fas fa-headset text-warning me-1"></i> NOC Hotline: <strong><?= htmlspecialchars($company_phone) ?></strong></span>
                <span class="opacity-25 d-none d-md-inline">|</span>
                <?php if ($current_role === 'karyawan'): ?>
                    <span class="d-none d-lg-inline"><i class="fas fa-headset text-success me-1"></i> NOC Support 24/7</span>
                <?php else: ?>
                    <span class="d-none d-lg-inline"><i class="fas fa-shield-alt text-success me-1"></i> SLA Target 99.5%</span>
                <?php endif; ?>
                <?php if ($current_role === 'karyawan' && !empty($current_user['company_name'])): ?>
                    <span class="opacity-25 d-none d-xl-inline">|</span>
                    <span class="text-info d-none d-xl-inline"><i class="fas fa-building me-1"></i> <?= htmlspecialchars($current_user['company_name']) ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Right System Live Status & Time -->
            <div class="d-flex align-items-center gap-3 text-white-50 small" style="font-size: 0.74rem;">
                <div class="d-none d-sm-flex align-items-center">
                    <span class="status-dot me-1"></span>
                    <span>NOC Gateway: <strong class="text-success">ONLINE</strong></span>
                </div>
                <span class="opacity-25 d-none d-sm-inline">|</span>
                <div class="d-none d-md-inline">
                    <i class="far fa-clock me-1"></i> <?= date('d M Y, H:i') ?> WIB
                </div>
            </div>
        </div>
    </div>

    <!-- TIER 2: MAIN WHITE BRAND & NAVIGATION BAR -->
    <nav class="navbar navbar-expand-xl navbar-light bg-white py-2 border-bottom shadow-sm">
        <div class="container-fluid px-3 px-xl-4">
            
            <!-- ISP Telco Brand Logo (Oxygen.id Style Modern Logo) -->
            <a class="navbar-brand d-flex align-items-center me-4 py-0 flex-shrink-0" href="<?= base_url('index.php') ?>">
                <div class="brand-logo-isp me-2">
                    <span class="logo-visi">visi<span class="logo-media">media</span><span class="logo-tld">.id</span></span>
                    <span class="logo-corp-sub"><?= htmlspecialchars($company_name) ?></span>
                </div>
                <div class="border-start ps-2 ms-1 d-none d-lg-block">
                    <div class="fw-bold text-dark lh-1" style="font-size: 0.88rem;"><?= htmlspecialchars($app_name) ?></div>
                    <span class="badge bg-light text-primary border rounded-pill px-2 py-0 mt-1" style="font-size: 0.65rem; font-weight: 600;">
                        <i class="fas fa-code-branch me-1"></i><?= htmlspecialchars($system_version) ?>
                    </span>
                </div>
            </a>
            
            <!-- Mobile Toggler -->
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarIspMain" aria-controls="navbarIspMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navbar Content -->
            <div class="collapse navbar-collapse" id="navbarIspMain">
                
                <!-- Main Nav Links (Clean Dark Typography on White) -->
                <ul class="navbar-nav me-auto mb-2 mb-xl-0 align-items-center">
                    <?php if ($current_role === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : '' ?>" href="<?= base_url('admin/dashboard.php') ?>">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'kelola_klien.php') !== false ? 'active' : '' ?>" href="<?= base_url('admin/kelola_klien.php') ?>">Klien B2B</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'kelola_user.php') !== false ? 'active' : '' ?>" href="<?= base_url('admin/kelola_user.php') ?>">Pengguna</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?= (strpos($_SERVER['PHP_SELF'], 'kelola_kategori.php') !== false || strpos($_SERVER['PHP_SELF'], 'kelola_prioritas.php') !== false) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Master Data
                            </a>
                            <ul class="dropdown-menu shadow-sm border">
                                <li><a class="dropdown-item" href="<?= base_url('admin/kelola_kategori.php') ?>">Kategori Gangguan Link</a></li>
                                <li><a class="dropdown-item" href="<?= base_url('admin/kelola_prioritas.php') ?>">Parameter Prioritas & Target SLA</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'semua_tiket.php') !== false ? 'active' : '' ?>" href="<?= base_url('admin/semua_tiket.php') ?>">Semua Tiket</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pengaturan_website.php') !== false ? 'active' : '' ?>" href="<?= base_url('admin/pengaturan_website.php') ?>">Konfigurasi</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'log_aktivitas.php') !== false ? 'active' : '' ?>" href="<?= base_url('admin/log_aktivitas.php') ?>">Audit Log</a>
                        </li>

                    <?php elseif ($current_role === 'karyawan' || $current_role === 'customer'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : '' ?>" href="<?= base_url('customer/dashboard.php') ?>">Portal Klien</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'riwayat_tiket.php') !== false ? 'active' : '' ?>" href="<?= base_url('customer/riwayat_tiket.php') ?>">Riwayat Tiket</a>
                        </li>

                    <?php elseif ($current_role === 'helpdesk'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : '' ?>" href="<?= base_url('helpdesk/dashboard.php') ?>">Dashboard NOC</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'kelola_tiket.php') !== false ? 'active' : '' ?>" href="<?= base_url('helpdesk/kelola_tiket.php') ?>">Antrian Tiket</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'master_data.php') !== false ? 'active' : '' ?>" href="<?= base_url('helpdesk/master_data.php') ?>">Kategori & Standar SLA</a>
                        </li>

                    <?php elseif ($current_role === 'teknisi'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false || strpos($_SERVER['PHP_SELF'], 'proses_tiket.php') !== false) ? 'active' : '' ?>" href="<?= base_url('teknisi/dashboard.php') ?>">Dashboard Tugas Lapangan</a>
                        </li>

                    <?php elseif ($current_role === 'manager'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : '' ?>" href="<?= base_url('manager/dashboard.php') ?>">Monitoring SLA</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'cetak_laporan.php') !== false ? 'active' : '' ?>" href="<?= base_url('manager/cetak_laporan.php') ?>">Laporan SLA Klien</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'cetak_kinerja_teknisi.php') !== false ? 'active' : '' ?>" href="<?= base_url('manager/cetak_kinerja_teknisi.php') ?>">Kinerja Teknisi</a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Oxygen.id Style Action Pills (Green & Orange Buttons) -->
                <div class="d-flex align-items-center gap-2 mt-3 mt-xl-0 flex-shrink-0">
                    
                    <!-- Pill Action Button (Khusus Klien: Buat Tiket) -->
                    <?php if ($current_role === 'karyawan' || $current_role === 'customer'): ?>
                        <a href="<?= base_url('customer/buat_tiket.php') ?>" class="btn btn-pill-green shadow-sm">
                            <i class="fas fa-plus-circle me-1"></i> Buat Tiket
                        </a>
                    <?php endif; ?>
                    
                    <!-- Pill 2 (Orange Selfcare / User Account Button with Dropdown) -->
                    <div class="dropdown">
                        <button class="btn btn-pill-orange dropdown-toggle shadow-sm d-flex align-items-center gap-2" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar-pill-circle"><?= $user_initials ?></span>
                            <span class="user-pill-text d-none d-md-inline text-truncate" style="max-width: 140px;"><?= htmlspecialchars($current_user['name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border user-dropdown-menu" aria-labelledby="userMenuDropdown">
                            <li class="dropdown-header py-2">
                                <div class="fw-bold text-dark mb-0"><?= htmlspecialchars($current_user['name']) ?></div>
                                <div class="text-secondary small"><?= htmlspecialchars($current_user['email'] ?? '') ?></div>
                                <div class="badge bg-primary-subtle text-primary border mt-1 font-monospace">
                                    <?= $role_names[$current_role] ?? ucfirst($current_role) ?>
                                </div>
                                <?php if (!empty($current_user['company_name'])): ?>
                                    <div class="small text-dark mt-1">
                                        <i class="fas fa-building text-secondary me-1"></i> <?= htmlspecialchars($current_user['company_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <a class="dropdown-item py-1" href="<?= base_url('index.php') ?>">
                                    <i class="fas fa-home me-2 text-secondary"></i> Beranda Sistem
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-1" href="#" data-bs-toggle="modal" data-bs-target="#modalSlaHelp">
                                    <i class="fas fa-info-circle me-2 text-secondary"></i> Informasi SLA & Eskalasi
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <a class="dropdown-item py-1 text-danger fw-semibold" href="<?= base_url('auth/logout.php') ?>" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout (Keluar)
                                </a>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>
            
        </div>
    </nav>
</header>

<!-- TIER 3: SUB-HEADER BREADCRUMB BAR (Light Gray Corporate Strip) -->
<div class="sub-header-bar no-print">
    <div class="container-fluid px-3 px-xl-4 d-flex align-items-center justify-content-between">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 align-items-center">
                <li class="breadcrumb-item"><a href="<?= base_url('index.php') ?>" class="text-decoration-none text-secondary">Beranda</a></li>
                <li class="breadcrumb-item text-secondary"><?= $role_names[$current_role] ?? ucfirst($current_role) ?></li>
                <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page"><?= htmlspecialchars($page_title) ?></li>
            </ol>
        </nav>
        <div class="d-none d-md-block text-secondary small" style="font-size: 0.74rem; white-space: nowrap;">
            <i class="far fa-calendar-alt me-1"></i> <?= date('l, d F Y') ?> &bull; <span class="text-dark fw-medium">WIB (UTC+7)</span>
        </div>
    </div>
</div>

<!-- Modal Informasi Ketentuan SLA & Eskalasi B2B -->
<div class="modal fade no-print" id="modalSlaHelp" tabindex="-1" aria-labelledby="modalSlaHelpLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-3">
                <h6 class="modal-title fw-bold" id="modalSlaHelpLabel">
                    <i class="fas fa-shield-alt text-primary me-2"></i> Standar Service Level Agreement (SLA) & Eskalasi Insiden
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-secondary small mb-3">
                    Berikut adalah matriks komitmen Service Level Agreement (SLA) penanganan gangguan jaringan B2B pada <strong><?= htmlspecialchars($company_name) ?></strong> sesuai kontrak layanan pelanggan:
                </p>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tingkat Prioritas</th>
                                <th>Definisi Dampak Operasional</th>
                                <th>Target Respon</th>
                                <th>Target Resolusi (MTTR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sla_modal_priorities)): ?>
                                <?php foreach ($sla_modal_priorities as $p_item): ?>
                                    <tr>
                                        <td><span class="badge bg-<?= htmlspecialchars($p_item['badge_color'] ?? 'primary') ?>"><?= htmlspecialchars($p_item['name']) ?></span></td>
                                        <td><?= htmlspecialchars($p_item['description'] ?? '-') ?></td>
                                        <td>&le; <?= ($p_item['sla_hours'] <= 1 ? '15 Menit' : ($p_item['sla_hours'] <= 2 ? '30 Menit' : ($p_item['sla_hours'] <= 4 ? '1 Jam' : '2 Jam'))) ?></td>
                                        <td><strong>&le; <?= (int)$p_item['sla_hours'] ?> Jam</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td><span class="badge bg-danger">Critical (P1)</span></td>
                                    <td>Total link down, LOS fiber optik, backbone failure mempengaruhi seluruh cabang klien.</td>
                                    <td>&le; 15 Menit</td>
                                    <td><strong>&le; 1 Jam</strong></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning text-dark">High (P2)</span></td>
                                    <td>High latency (>100ms), packet loss (>5%), flapping BGP routing.</td>
                                    <td>&le; 30 Menit</td>
                                    <td><strong>&le; 2 Jam</strong></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-primary">Medium (P3)</span></td>
                                    <td>Penurunan throughput bandwidth, intermiten drop pada port CPE.</td>
                                    <td>&le; 1 Jam</td>
                                    <td><strong>&le; 4 Jam</strong></td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-secondary">Low (P4)</span></td>
                                    <td>Permintaan perubahan routing, DNS query, reset password router, atau konsultasi teknis.</td>
                                    <td>&le; 2 Jam</td>
                                    <td><strong>&le; 8 Jam</strong></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="bg-light p-3 rounded border">
                    <div class="fw-bold text-dark small mb-2"><i class="fas fa-headset text-primary me-1"></i> Kontak Network Operations Center (NOC 24/7) & Dukungan IT:</div>
                    <div class="row g-2 text-secondary small">
                        <div class="col-md-6">&bull; Nomor Telepon / Hotline IT: <strong><?= htmlspecialchars($company_phone) ?></strong></div>
                        <div class="col-md-6">&bull; Email Helpdesk / NOC: <strong><?= htmlspecialchars($company_email) ?></strong></div>
                        <?php if (!empty($company_address)): ?>
                            <div class="col-12 mt-1">&bull; Alamat Kantor: <strong><?= htmlspecialchars($company_address) ?></strong></div>
                        <?php endif; ?>
                        <?php if (!empty($company_tagline)): ?>
                            <div class="col-12">&bull; Layanan: <strong><?= htmlspecialchars($company_tagline) ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 bg-light">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Container Utama Halaman -->
<div class="container-fluid py-4 flex-grow-1">
    <div class="container">
        <!-- Notifikasi Flash Message -->
        <?php display_flash(); ?>

