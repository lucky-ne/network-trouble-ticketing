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
 * Render Badge Status SLA
 */
function get_sla_badge($sla_status, $deadline = null, $resolved_at = null) {
    if ($sla_status === 'within_sla') {
        return '<span class="badge bg-success text-white"><i class="fas fa-clock me-1"></i> Tepat Waktu (On-Time SLA)</span>';
    } elseif ($sla_status === 'breached') {
        return '<span class="badge bg-danger text-white"><i class="fas fa-exclamation-triangle me-1"></i> Terlambat (SLA Breached)</span>';
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
                return '<span class="badge bg-danger text-white pulse"><i class="fas fa-fire me-1"></i> Melewati SLA (' . $str . ')</span>';
            } else {
                $remain_mins = round($diff_seconds / 60);
                $hours = floor($remain_mins / 60);
                $mins = $remain_mins % 60;
                $str = $hours > 0 ? "{$hours}j {$mins}m" : "{$mins}m";
                return '<span class="badge bg-info text-dark"><i class="fas fa-hourglass-half me-1"></i> Sisa Waktu SLA: ' . $str . '</span>';
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
        
        echo '<div class="alert alert-' . htmlspecialchars($msg['type']) . ' alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-' . $icon . ' me-2"></i> ' . htmlspecialchars($msg['text']) . '
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
