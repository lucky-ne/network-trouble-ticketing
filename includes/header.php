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

// PROSES GLOBAL: Simpan / Update Pengaturan Broadcast Gangguan Massal oleh Admin / Helpdesk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_broadcast']) && in_array($current_role, ['admin', 'helpdesk'])) {
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
    $redirect_url = strtok($_SERVER['REQUEST_URI'], '#');
    header('Location: ' . $redirect_url);
    exit;
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
    
    <!-- Google Fonts: Geist & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
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
     ZINC PRECISION CORPORATE NAVIGATION & HEADER
     PT. VISIMEDIA PRATAMA PERSADA
     Ref: design.md
     ========================================================= -->
<header class="isp-header sticky-top no-print">
    
    <!-- TIER 1: TOP DARK UTILITY STRIP (Zinc Precision Top Bar) -->
    <div class="top-utility-bar py-1">
        <div class="container-fluid px-3 px-xl-4 d-flex align-items-center justify-content-between">
            <!-- Left Utility Links -->
            <div class="d-flex align-items-center gap-2 text-white-50 small" style="font-size: 0.72rem;">
                <span class="text-white fw-semibold"><i class="fas fa-network-wired text-secondary me-1"></i> <?= htmlspecialchars($app_name) ?></span>
                <span class="badge border rounded-md px-2 py-0 d-none d-sm-inline-block font-monospace" style="background:#27272a; border-color:#3f3f46 !important; color:#e4e4e7; font-size:0.65rem;"><?= htmlspecialchars($system_version) ?></span>
                <span class="opacity-25 text-secondary">|</span>
                <span class="d-none d-md-inline text-secondary"><i class="fas fa-headset text-secondary me-1"></i> NOC Hotline: <strong class="text-light"><?= htmlspecialchars($company_phone) ?></strong></span>
                <span class="opacity-25 text-secondary d-none d-md-inline">|</span>
                <?php if ($current_role === 'karyawan'): ?>
                    <span class="d-none d-lg-inline text-secondary"><i class="fas fa-headset text-success me-1"></i> NOC Support 24/7</span>
                <?php endif; ?>
                <?php if ($current_role === 'karyawan' && !empty($current_user['company_name'])): ?>
                    <span class="opacity-25 text-secondary d-none d-xl-inline">|</span>
                    <span class="text-light d-none d-xl-inline"><i class="fas fa-building text-secondary me-1"></i> <?= htmlspecialchars($current_user['company_name']) ?></span>
                <?php endif; ?>
            </div>
            
            <!-- Right System Live Status & Time -->
            <div class="d-flex align-items-center gap-3 text-secondary small" style="font-size: 0.72rem;">
                <div class="d-none d-sm-flex align-items-center">
                    <span class="status-dot me-1"></span>
                    <span>NOC Gateway: <strong class="text-success fw-semibold">ONLINE</strong></span>
                </div>
                <span class="opacity-25 text-secondary d-none d-sm-inline">|</span>
                <div class="d-none d-md-inline font-monospace text-light opacity-75">
                    <i class="far fa-clock me-1 text-secondary"></i> <?= date('d M Y, H:i') ?> WIB
                </div>
            </div>
        </div>
    </div>

    <!-- TIER 2: MAIN BRAND & NAVIGATION BAR -->
    <nav class="navbar navbar-expand-xl navbar-light bg-white border-bottom shadow-sm">
        <div class="container-fluid px-3 px-xl-4">
            
            <!-- Brand Logo -->
            <a class="navbar-brand d-flex align-items-center me-4 py-0 flex-shrink-0" href="<?= base_url('index.php') ?>">
                <div class="brand-logo-isp me-2">
                    <div class="logo-main">
                        <span class="fw-bold text-dark">visi<span class="text-dark">media</span></span><span class="text-secondary font-monospace" style="font-size:0.85rem;">.id</span>
                    </div>
                    <span class="logo-corp-sub"><?= htmlspecialchars($company_name) ?></span>
                </div>
                <div class="border-start ps-2 ms-1 d-none d-lg-block">
                    <div class="fw-semibold text-dark lh-1" style="font-size: 0.85rem;"><?= htmlspecialchars($app_name) ?></div>
                    <span class="badge rounded-md px-2 py-0 mt-1 font-monospace" style="background:#f4f4f5; color:#18181b; border:1px solid #e4e4e7; font-size: 0.65rem; font-weight: 500;">
                        <i class="fas fa-code-branch me-1"></i><?= htmlspecialchars($system_version) ?>
                    </span>
                </div>
            </a>
            
            <!-- Mobile Toggler -->
            <button class="navbar-toggler border shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarIspMain" aria-controls="navbarIspMain" aria-expanded="false" aria-label="Toggle navigation" style="padding:0.3rem 0.55rem; border-color:#e4e4e7 !important; border-radius:0.375rem;">
                <span class="navbar-toggler-icon" style="font-size: 0.85rem;"></span>
            </button>
            
            <!-- Navbar Content -->
            <div class="collapse navbar-collapse" id="navbarIspMain">
                
                <!-- Main Nav Links -->
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
                            <ul class="dropdown-menu shadow-sm">
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
                            <a class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false || strpos($_SERVER['PHP_SELF'], 'proses_tiket.php') !== false) ? 'active' : '' ?>" href="<?= base_url('teknisi/dashboard.php') ?>">
                                <i class="fas fa-wrench me-1"></i> Tugas Aktif (Belum Selesai)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'riwayat_tugas.php') !== false ? 'active' : '' ?>" href="<?= base_url('teknisi/riwayat_tugas.php') ?>">
                                <i class="fas fa-check-double me-1"></i> Riwayat Tugas Selesai
                            </a>
                        </li>

                    <?php elseif ($current_role === 'manager'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : '' ?>" href="<?= base_url('manager/dashboard.php') ?>">Monitoring SLA</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'cetak_laporan.php') !== false ? 'active' : '' ?>" href="<?= base_url('manager/cetak_laporan.php') ?>">Laporan SLA Klien</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= (strpos($_SERVER['PHP_SELF'], 'kinerja_teknisi.php') !== false || strpos($_SERVER['PHP_SELF'], 'cetak_kinerja_teknisi.php') !== false) ? 'active' : '' ?>" href="<?= base_url('manager/kinerja_teknisi.php') ?>">Kinerja Teknisi</a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Action Controls / User Account Dropdown Trigger -->
                <div class="d-flex align-items-center gap-2 mt-3 mt-xl-0 flex-shrink-0">
                    
                    <!-- User Account Dropdown Trigger -->
                    <div class="dropdown">
                        <button class="btn btn-user-account dropdown-toggle shadow-none" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar-initials-badge"><?= $user_initials ?></span>
                            <span class="d-none d-md-inline text-truncate" style="max-width: 130px;"><?= htmlspecialchars($current_user['name']) ?></span>
                            <span class="badge rounded-md font-monospace text-uppercase ms-1" style="background:#f4f4f5; color:#18181b; border:1px solid #e4e4e7; font-size:0.62rem; font-weight:600; padding:2px 5px;">
                                <?= htmlspecialchars($current_role === 'karyawan' ? 'KLIEN' : ($current_role === 'helpdesk' ? 'NOC' : strtoupper($current_role))) ?>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu" aria-labelledby="userMenuDropdown">
                            <li class="dropdown-header">
                                <div class="fw-semibold text-dark mb-0 text-truncate"><?= htmlspecialchars($current_user['name']) ?></div>
                                <div class="text-secondary small text-truncate" style="font-size:0.75rem;"><?= htmlspecialchars($current_user['email'] ?? '') ?></div>
                                <div class="badge font-monospace mt-1" style="background:#f4f4f5; color:#18181b; border:1px solid #e4e4e7; font-size:0.68rem; font-weight:500;">
                                    <?= $role_names[$current_role] ?? ucfirst($current_role) ?>
                                </div>
                                <?php if (!empty($current_user['company_name'])): ?>
                                    <div class="small text-secondary mt-1 text-truncate" style="font-size:0.75rem;">
                                        <i class="fas fa-building text-secondary me-1"></i> <?= htmlspecialchars($current_user['company_name']) ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                            <li><hr class="dropdown-divider my-1" style="border-color:#e4e4e7;"></li>
                            <li>
                                <a class="dropdown-item" href="<?= base_url('index.php') ?>">
                                    <i class="fas fa-home me-2 text-secondary"></i> Beranda Sistem
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#modalSlaHelp">
                                    <i class="fas fa-info-circle me-2 text-secondary"></i> Informasi SLA & Eskalasi
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1" style="border-color:#e4e4e7;"></li>
                            <li>
                                <a class="dropdown-item dropdown-item-danger fw-medium" href="<?= base_url('auth/logout.php') ?>" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem?');">
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

<!-- TIER 3: SUB-HEADER BREADCRUMB BAR (Zinc Precision Sub-Header) -->
<div class="sub-header-bar no-print">
    <div class="container-fluid px-3 px-xl-4 d-flex align-items-center justify-content-between">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 align-items-center">
                <li class="breadcrumb-item"><a href="<?= base_url('index.php') ?>" class="text-decoration-none">Beranda</a></li>
                <li class="breadcrumb-item text-secondary"><?= $role_names[$current_role] ?? ucfirst($current_role) ?></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($page_title) ?></li>
            </ol>
        </nav>
        <div class="d-none d-md-block text-secondary small font-monospace" style="font-size: 0.72rem; white-space: nowrap;">
            <i class="far fa-calendar-alt me-1"></i> <?= date('l, d F Y') ?> &bull; <span class="text-dark fw-medium">WIB (UTC+7)</span>
        </div>
    </div>
</div>

<!-- Modal Informasi Ketentuan SLA & Eskalasi B2B -->
<div class="modal fade no-print" id="modalSlaHelp" tabindex="-1" aria-labelledby="modalSlaHelpLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border shadow-sm" style="border-radius: 0.75rem; border-color: #e4e4e7 !important;">
            <div class="modal-header py-3" style="background: #18181b; color: #ffffff; border-top-left-radius: 0.75rem; border-top-right-radius: 0.75rem; border-bottom: 1px solid #27272a;">
                <h6 class="modal-title fw-semibold" id="modalSlaHelpLabel" style="font-size: 0.92rem; letter-spacing: -0.01em;">
                    <i class="fas fa-shield-alt text-white-50 me-2"></i> Standar Service Level Agreement (SLA) & Eskalasi Insiden
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" style="background: #ffffff;">
                <p class="text-secondary small mb-3" style="font-size: 0.8125rem;">
                    Berikut adalah matriks komitmen Service Level Agreement (SLA) penanganan gangguan jaringan B2B pada <strong><?= htmlspecialchars($company_name) ?></strong> sesuai kontrak layanan pelanggan:
                </p>
                <div class="table-responsive mb-3 border rounded-md" style="border-color: #e4e4e7 !important;">
                    <table class="table table-sm align-middle small mb-0" style="font-size: 0.8125rem;">
                        <thead style="background: #fafafa; border-bottom: 1px solid #e4e4e7;">
                            <tr class="text-secondary" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.02em;">
                                <th class="py-2 px-3">Tingkat Prioritas</th>
                                <th class="py-2 px-3">Definisi Dampak Operasional</th>
                                <th class="py-2 px-3">Target Respon</th>
                                <th class="py-2 px-3">Target Resolusi (MTTR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sla_modal_priorities)): ?>
                                <?php foreach ($sla_modal_priorities as $p_item): ?>
                                    <tr style="border-bottom: 1px solid #f4f4f5;">
                                        <td class="py-2 px-3"><span class="badge bg-<?= htmlspecialchars($p_item['badge_color'] ?? 'primary') ?>" style="border-radius: 4px; font-weight: 500;"><?= htmlspecialchars($p_item['name']) ?></span></td>
                                        <td class="py-2 px-3 text-secondary"><?= htmlspecialchars($p_item['description'] ?? '-') ?></td>
                                        <td class="py-2 px-3 font-monospace">&le; <?= ($p_item['sla_hours'] <= 1 ? '15 Menit' : ($p_item['sla_hours'] <= 2 ? '30 Menit' : ($p_item['sla_hours'] <= 4 ? '1 Jam' : '2 Jam'))) ?></td>
                                        <td class="py-2 px-3 font-monospace"><strong>&le; <?= (int)$p_item['sla_hours'] ?> Jam</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr style="border-bottom: 1px solid #f4f4f5;">
                                    <td class="py-2 px-3"><span class="badge bg-danger" style="border-radius: 4px;">Critical (P1)</span></td>
                                    <td class="py-2 px-3 text-secondary">Total link down, LOS fiber optik, backbone failure mempengaruhi seluruh cabang klien.</td>
                                    <td class="py-2 px-3 font-monospace">&le; 15 Menit</td>
                                    <td class="py-2 px-3 font-monospace"><strong>&le; 1 Jam</strong></td>
                                </tr>
                                <tr style="border-bottom: 1px solid #f4f4f5;">
                                    <td class="py-2 px-3"><span class="badge bg-warning text-dark" style="border-radius: 4px;">High (P2)</span></td>
                                    <td class="py-2 px-3 text-secondary">High latency (>100ms), packet loss (>5%), flapping BGP routing.</td>
                                    <td class="py-2 px-3 font-monospace">&le; 30 Menit</td>
                                    <td class="py-2 px-3 font-monospace"><strong>&le; 2 Jam</strong></td>
                                </tr>
                                <tr style="border-bottom: 1px solid #f4f4f5;">
                                    <td class="py-2 px-3"><span class="badge bg-primary" style="border-radius: 4px;">Medium (P3)</span></td>
                                    <td class="py-2 px-3 text-secondary">Penurunan throughput bandwidth, intermiten drop pada port CPE.</td>
                                    <td class="py-2 px-3 font-monospace">&le; 1 Jam</td>
                                    <td class="py-2 px-3 font-monospace"><strong>&le; 4 Jam</strong></td>
                                </tr>
                                <tr style="border-bottom: 1px solid #f4f4f5;">
                                    <td class="py-2 px-3"><span class="badge bg-secondary" style="border-radius: 4px;">Low (P4)</span></td>
                                    <td class="py-2 px-3 text-secondary">Permintaan perubahan routing, DNS query, reset password router, atau konsultasi teknis.</td>
                                    <td class="py-2 px-3 font-monospace">&le; 2 Jam</td>
                                    <td class="py-2 px-3 font-monospace"><strong>&le; 8 Jam</strong></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border rounded-md" style="background: #fafafa; border-color: #e4e4e7 !important;">
                    <div class="fw-semibold text-dark small mb-2"><i class="fas fa-headset text-secondary me-1"></i> Kontak Network Operations Center (NOC 24/7) & Dukungan IT:</div>
                    <div class="row g-2 text-secondary small" style="font-size: 0.8125rem;">
                        <div class="col-md-6">&bull; Nomor Telepon / Hotline IT: <strong class="text-dark"><?= htmlspecialchars($company_phone) ?></strong></div>
                        <div class="col-md-6">&bull; Email Helpdesk / NOC: <strong class="text-dark"><?= htmlspecialchars($company_email) ?></strong></div>
                        <?php if (!empty($company_address)): ?>
                            <div class="col-12 mt-1">&bull; Alamat Kantor: <strong class="text-dark"><?= htmlspecialchars($company_address) ?></strong></div>
                        <?php endif; ?>
                        <?php if (!empty($company_tagline)): ?>
                            <div class="col-12">&bull; Layanan: <strong class="text-dark"><?= htmlspecialchars($company_tagline) ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2" style="background: #fafafa; border-top: 1px solid #e4e4e7; border-bottom-left-radius: 0.75rem; border-bottom-right-radius: 0.75rem;">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal" style="font-size: 0.8125rem; font-weight: 500; border-radius: 0.375rem;">Tutup</button>
            </div>
        </div>
    </div>
</div>

<?php if ($current_role === 'helpdesk' || $current_role === 'admin'): ?>
<!-- Modal Global: Manajemen Broadcast Gangguan Massal (Outage Alert) -->
<div class="modal fade no-print" id="modalBroadcastOutage" tabindex="-1" aria-labelledby="modalBroadcastOutageLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-3 bg-white" style="border-bottom: 1px solid #e4e4e7;">
                <h6 class="modal-title mb-0 fw-bold text-dark" id="modalBroadcastOutageLabel">
                    <span class="badge bg-danger me-2"><i class="fas fa-tower-broadcast me-1"></i> NOC BROADCAST</span>
                    Pengumuman Gangguan Massal (Outage Announcement)
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body p-4">
                    <div class="alert alert-warning small mb-3">
                        <i class="fas fa-info-circle me-1"></i> Saat diaktifkan, banner peringatan darurat akan otomatis tayang di bagian atas portal <strong>seluruh Klien Korporat B2B</strong> dan portal internal.
                    </div>

                    <div class="p-3 mb-3 rounded border" style="background:#fef2f2; border-color:#fecaca !important;">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="outage_broadcast_active_hdr" name="outage_broadcast_active" value="1" <?= (get_setting('outage_broadcast_active', 0) == 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold text-danger" for="outage_broadcast_active_hdr" style="font-size:0.95rem;">
                                Aktifkan Banner Pengumuman Gangguan Massal ke Seluruh Klien
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Judul Insiden Gangguan Massal <span class="text-danger">*</span></label>
                        <input type="text" name="outage_broadcast_title" class="form-control" value="<?= htmlspecialchars(get_setting('outage_broadcast_title', 'Pemberitahuan: Gangguan Massal Kabel Fiber Optic Backbone')) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark">Deskripsi Insiden & Penjelasan Teknis <span class="text-danger">*</span></label>
                        <textarea name="outage_broadcast_message" class="form-control" rows="3" required><?= htmlspecialchars(get_setting('outage_broadcast_message', 'Terjadi putus kabel Fiber Optic Backbone akibat pekerjaan galian utilitas kota pihak ketiga. Tim Fiber Optic Splicer sedang melakukan perbaikan darurat di lokasi.')) ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Wilayah / Area Site Terdampak</label>
                            <input type="text" name="outage_broadcast_area" class="form-control" value="<?= htmlspecialchars(get_setting('outage_broadcast_area', 'Sudirman, MH Thamrin, Kuningan & Sekitarnya')) ?>" placeholder="Contoh: Kawasan Sudirman - Thamrin">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-dark">Estimasi Waktu Normalisasi (ETA)</label>
                            <input type="text" name="outage_broadcast_eta" class="form-control" value="<?= htmlspecialchars(get_setting('outage_broadcast_eta', '4 Jam (Estimasi Normal: 16:30 WIB)')) ?>" placeholder="Contoh: 4 Jam (ETA: 16:30 WIB)">
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold text-dark">Tingkat Urgensi Peringatan</label>
                        <select name="outage_broadcast_level" class="form-select">
                            <option value="danger" <?= (get_setting('outage_broadcast_level', 'danger') === 'danger') ? 'selected' : '' ?>>Bahaya / Critical Outage (Merah)</option>
                            <option value="warning" <?= (get_setting('outage_broadcast_level', 'danger') === 'warning') ? 'selected' : '' ?>>Peringatan / Degradasi Ringan (Kuning)</option>
                            <option value="info" <?= (get_setting('outage_broadcast_level', 'danger') === 'info') ? 'selected' : '' ?>>Informasi / Scheduled Maintenance (Biru)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" name="action_save_broadcast" class="btn btn-danger btn-sm fw-semibold">
                        <i class="fas fa-save me-1"></i> Simpan Pengaturan Broadcast
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Container Utama Halaman -->
<div class="container-fluid py-4 flex-grow-1">
    <div class="container">
        <!-- Notifikasi Flash Message -->
        <?php display_flash(); ?>

        <?php
        $outage_alert = get_active_outage_broadcast();
        if ($outage_alert && $current_user):
            $alert_level = in_array($outage_alert['level'], ['danger', 'warning', 'info']) ? $outage_alert['level'] : 'danger';
            $bg_color = $alert_level === 'danger' ? '#fef2f2' : ($alert_level === 'warning' ? '#fffbeb' : '#eff6ff');
            $border_color = $alert_level === 'danger' ? '#f87171' : ($alert_level === 'warning' ? '#fcd34d' : '#93c5fd');
            $text_color = $alert_level === 'danger' ? '#991b1b' : ($alert_level === 'warning' ? '#92400e' : '#1e40af');
            $icon = $alert_level === 'danger' ? 'fa-exclamation-triangle' : ($alert_level === 'warning' ? 'fa-bullhorn' : 'fa-info-circle');
        ?>
        <!-- BROADCAST GANGGUAN MASSAL (OUTAGE ANNOUNCEMENT BANNER) -->
        <div class="card mb-4 border shadow-sm" style="background: <?= $bg_color ?>; border-color: <?= $border_color ?> !important; border-radius: 0.5rem;">
            <div class="card-body p-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="rounded-circle p-2 d-flex align-items-center justify-content-center flex-shrink-0" style="background: rgba(0,0,0,0.06); width: 42px; height: 42px;">
                        <i class="fas <?= $icon ?> fa-lg" style="color: <?= $text_color ?>;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                            <div class="fw-bold" style="color: <?= $text_color ?>; font-size: 0.95rem;">
                                <span class="badge bg-danger me-2"><i class="fas fa-broadcast-tower me-1"></i> GANGGUAN MASSAL AKTIF</span>
                                <?= htmlspecialchars($outage_alert['title']) ?>
                            </div>
                            <?php if (!empty($outage_alert['eta'])): ?>
                                <span class="badge rounded-pill font-monospace" style="background: <?= $text_color ?>; color: #fff; font-size: 0.75rem;">
                                    <i class="fas fa-stopwatch me-1"></i> <?= htmlspecialchars($outage_alert['eta']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="small mb-2" style="color: <?= $text_color ?>; opacity: 0.95; line-height: 1.5;">
                            <?= nl2br(htmlspecialchars($outage_alert['message'])) ?>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-3 font-monospace small" style="font-size: 0.75rem; color: <?= $text_color ?>; opacity: 0.9;">
                            <?php if (!empty($outage_alert['area'])): ?>
                                <span><i class="fas fa-map-marker-alt me-1"></i> <strong>Area:</strong> <?= htmlspecialchars($outage_alert['area']) ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-shield-alt me-1"></i> <strong>Status SLA:</strong> Dikecualikan dari Penalti SLA (Force Majeure)</span>
                            <?php if ($current_role === 'helpdesk' || $current_role === 'admin'): ?>
                                <button type="button" class="btn btn-sm btn-dark py-0 px-2 ms-auto text-white" data-bs-toggle="modal" data-bs-target="#modalBroadcastOutage" style="font-size: 0.7rem; border-radius: 4px;">
                                    <i class="fas fa-sliders-h me-1"></i> Kelola Broadcast
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>


