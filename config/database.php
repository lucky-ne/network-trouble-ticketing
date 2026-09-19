<?php
/**
 * Konfigurasi Database & Helper Functions
 * Sistem Network Trouble Ticketing & SLA Tracking
 */

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set Timezone default Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi Database MySQL (Mendukung Environment Variables Docker & Default XAMPP)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'net_ticketing_db');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

/**
 * Fungsi untuk mendapatkan koneksi Database PDO
 */
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Auto-Migration Runtime Checker: Menjamin kolom SLA Exemption & Outage Broadcast selalu tersedia
            static $migrated = false;
            if (!$migrated) {
                try {
                    $cols = $pdo->query("SHOW COLUMNS FROM tickets")->fetchAll(PDO::FETCH_COLUMN);
                    if (!in_array('sla_exemption_reason', $cols)) {
                        $pdo->exec("ALTER TABLE tickets ADD COLUMN `sla_exemption_reason` VARCHAR(255) NULL COMMENT 'Alasan Pengecualian SLA' AFTER `sla_status`");
                    }
                    if (!in_array('is_outage_massal', $cols)) {
                        $pdo->exec("ALTER TABLE tickets ADD COLUMN `is_outage_massal` TINYINT(1) DEFAULT 0 COMMENT 'Flag Gangguan Massal' AFTER `sla_exemption_reason`");
                    }
                    if (!in_array('sla_paused_at', $cols)) {
                        $pdo->exec("ALTER TABLE tickets ADD COLUMN `sla_paused_at` DATETIME NULL COMMENT 'Timestamp Jeda SLA' AFTER `is_outage_massal`");
                    }
                    if (!in_array('sla_paused_total_minutes', $cols)) {
                        $pdo->exec("ALTER TABLE tickets ADD COLUMN `sla_paused_total_minutes` INT DEFAULT 0 COMMENT 'Total Akumulasi Jeda SLA Menit' AFTER `sla_paused_at`");
                    }
                    // Update enum sla_status jika belum ada 'exempted'
                    $pdo->exec("ALTER TABLE tickets MODIFY COLUMN `sla_status` ENUM('pending', 'within_sla', 'breached', 'exempted') DEFAULT 'pending'");
                } catch (Exception $ex) {
                    // Ignore jika tabel belum di-create
                }
                $migrated = true;
            }
        } catch (PDOException $e) {
            die("<div style='font-family:sans-serif; padding:20px; background:#ffebee; color:#c62828; border:1px solid #ef9a9a; border-radius:8px;'>
                <h3>Gagal Terhubung ke Database!</h3>
                <p>Pastikan layanan Database MySQL / MariaDB (XAMPP atau Docker Container) sudah berjalan dengan baik.</p>
                <p>Pastikan juga database <strong>`" . htmlspecialchars(DB_NAME) . "`</strong> sudah di-import.</p>
                <small>Pesan Error: " . htmlspecialchars($e->getMessage()) . "</small>
            </div>");
        }
    }
    return $pdo;
}

/**
 * Mendapatkan Base URL relatif project
 */
function base_url($path = '') {
    // Deteksi subfolder otomatis
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    // Normalisasi slash
    $script_dir = str_replace('\\', '/', $script_dir);
    
    // Cari letak root project
    $parts = explode('/', trim($script_dir, '/'));
    $project_root = '';
    
    // Jika berada di subfolder (admin, customer, helpdesk, teknisi, manager, auth)
    $subfolders = ['admin', 'customer', 'helpdesk', 'teknisi', 'manager', 'auth', 'config', 'includes'];
    if (!empty($parts) && in_array(end($parts), $subfolders)) {
        array_pop($parts);
    }
    
    $project_root = '/' . implode('/', $parts);
    if ($project_root === '/') {
        $project_root = '';
    }
    
    return rtrim($project_root, '/') . '/' . ltrim($path, '/');
}

/**
 * Mendapatkan Nilai Pengaturan Sistem Dinamis dari Database
 */
function get_setting($key, $default = '') {
    static $settings_cache = null;
    $pdo = get_db();
    
    if ($settings_cache === null) {
        $settings_cache = [];
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch()) {
                $settings_cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Fallback jika tabel belum terbaca
        }
    }
    
    return $settings_cache[$key] ?? $default;
}

/**
 * Menyimpan / Update Nilai Pengaturan Sistem ke Database
 */
function update_setting($key, $value) {
    $pdo = get_db();
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) 
                           VALUES (?, ?, NOW()) 
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    return $stmt->execute([$key, $value]);
}

/**
 * Daftar Klausul Standar Alasan Pengecualian SLA (SLA Exemption / Force Majeure)
 */
function get_sla_exemption_reasons() {
    return [
        'Kabel FO Backbone Putus Akibat Pihak Ketiga / Galian Proyek' => 'Kabel FO Backbone Putus Akibat Pihak Ketiga / Galian Proyek',
        'Bencana Alam / Cuaca Ekstrem / Banjir / Gempa (Force Majeure)' => 'Bencana Alam / Cuaca Ekstrem / Banjir / Gempa (Force Majeure)',
        'Pemadaman Listrik Massal PLN Melampaui Backup UPS & Genset'   => 'Pemadaman Listrik Massal PLN Melampaui Backup UPS & Genset',
        'Gangguan Upstream Tier-1 / Submarine Cable Cut Internasional' => 'Gangguan Upstream Tier-1 / Submarine Cable Cut Internasional',
        'Pemeliharaan Darurat Terjadwal (Emergency Maintenance Window)'=> 'Pemeliharaan Darurat Terjadwal (Emergency Maintenance Window)'
    ];
}

/**
 * Mendapatkan Info Broadcast Gangguan Massal yang Sedang Aktif
 */
function get_active_outage_broadcast() {
    $active = (int)get_setting('outage_broadcast_active', 0);
    if ($active !== 1) {
        return null;
    }
    return [
        'active'  => true,
        'title'   => get_setting('outage_broadcast_title', 'Pemberitahuan: Gangguan Massal Jaringan Backbone Fiber Optic'),
        'message' => get_setting('outage_broadcast_message', 'Sedang terjadi gangguan massal pada kabel fiber optic backbone. Tim Fiber Optic Splicer sedang melakukan perbaikan darurat di lokasi.'),
        'area'    => get_setting('outage_broadcast_area', 'Seluruh Wilayah Terdampak'),
        'eta'     => get_setting('outage_broadcast_eta', 'Sedang dalam Penanganan (ETA: 4 Jam)'),
        'level'   => get_setting('outage_broadcast_level', 'danger')
    ];
}

/**
 * Format Tanggal & Waktu ke Format Bahasa Indonesia
 */
function format_date_indo($datetime, $with_time = true) {
    if (!$datetime) return '-';
    $timestamp = strtotime($datetime);
    if (!$timestamp) return '-';
    
    $months = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    
    $day = date('d', $timestamp);
    $month = $months[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);
    
    if ($with_time) {
        $time = date('H:i', $timestamp) . ' WIB';
        return "$day $month $year, $time";
    }
    return "$day $month $year";
}

/**
 * Render Badge Status Tiket
 */
function get_status_badge($status) {
    switch ($status) {
        case 'open':
            return '<span class="badge bg-secondary"><i class="fas fa-envelope-open me-1"></i> Open (Baru)</span>';
        case 'assigned':
            return '<span class="badge bg-primary"><i class="fas fa-user-check me-1"></i> Assigned</span>';
        case 'in_progress':
            return '<span class="badge bg-warning text-dark"><i class="fas fa-tools me-1"></i> In Progress</span>';
        case 'resolved':
            return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Resolved</span>';
        case 'closed':
            return '<span class="badge bg-dark"><i class="fas fa-lock me-1"></i> Closed</span>';
        default:
            return '<span class="badge bg-light text-dark">' . htmlspecialchars(ucfirst($status)) . '</span>';
    }
}

/**
 * Render Badge Status SLA (Termasuk SLA Exempted / Force Majeure)
 */
function get_sla_badge($sla_status, $deadline = null, $resolved_at = null, $exemption_reason = null) {
    if ($sla_status === 'exempted') {
        $title_attr = $exemption_reason ? ' title="Alasan Pengecualian: ' . htmlspecialchars($exemption_reason) . '"' : '';
        return '<span class="badge" style="background:#7c3aed; color:#ffffff;"' . $title_attr . '><i class="fas fa-shield-alt me-1"></i> SLA Exempted (Force Majeure)</span>';
    } elseif ($sla_status === 'within_sla') {
        return '<span class="badge bg-success"><i class="fas fa-clock me-1"></i> Tepat Waktu (On-Time SLA)</span>';
    } elseif ($sla_status === 'breached') {
        return '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i> Terlambat (SLA Breached)</span>';
    } else {
        // Pending / Masih berjalan
        if ($deadline) {
            $now = time();
            $deadline_time = strtotime($deadline);
            $diff_seconds = $deadline_time - $now;
            
            if ($diff_seconds < 0) {
                $overdue_mins = abs(round($diff_seconds / 60));
                $hours = floor($overdue_mins / 60);
                $mins = $overdue_mins % 60;
                $str = $hours > 0 ? "{$hours}j {$mins}m" : "{$mins}m";
                return '<span class="badge bg-danger"><i class="fas fa-fire me-1"></i> Melewati SLA (' . $str . ')</span>';
            } else {
                $remain_mins = round($diff_seconds / 60);
                $hours = floor($remain_mins / 60);
                $mins = $remain_mins % 60;
                $str = $hours > 0 ? "{$hours}j {$mins}m" : "{$mins}m";
                return '<span class="badge bg-info"><i class="fas fa-hourglass-half me-1"></i> Sisa Waktu SLA: ' . $str . '</span>';
            }
        }
        return '<span class="badge bg-secondary"><i class="fas fa-clock me-1"></i> SLA Berjalan</span>';
    }
}

/**
 * Format Menit ke Format Jam & Menit
 */
function format_duration_minutes($minutes) {
    if ($minutes === null || $minutes === '') return '-';
    $minutes = (int)$minutes;
    if ($minutes < 60) {
        return "{$minutes} Menit";
    }
    $hours = floor($minutes / 60);
    $rem_minutes = $minutes % 60;
    if ($rem_minutes === 0) {
        return "{$hours} Jam";
    }
    return "{$hours} Jam {$rem_minutes} Menit";
}

/**
 * Flash Message Helper
 */
function set_flash($type, $message) {
    $_SESSION['flash_msg'] = [
        'type' => $type, // success, danger, warning, info
        'text' => $message
    ];
}

function display_flash() {
    if (isset($_SESSION['flash_msg'])) {
        $msg = $_SESSION['flash_msg'];
        unset($_SESSION['flash_msg']);
        $icon = 'info-circle';
        if ($msg['type'] === 'success') $icon = 'check-circle';
        if ($msg['type'] === 'danger') $icon = 'exclamation-circle';
        if ($msg['type'] === 'warning') $icon = 'exclamation-triangle';
        
        // Izinkan tag pemformatan teks aman seperti strong, b, i, code, span, br
        $safe_text = strip_tags($msg['text'], '<strong><b><i><code><span><br><small>');
        
        echo '<div class="alert alert-' . htmlspecialchars($msg['type']) . ' alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-' . $icon . ' me-2"></i> ' . $safe_text . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
}

/**
 * Ambil data user yang sedang login
 */
function current_user() {
    return $_SESSION['user'] ?? null;
}
