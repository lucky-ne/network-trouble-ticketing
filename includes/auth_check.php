<?php
/**
 * Middleware Proteksi Hak Akses Halaman
 */

require_once __DIR__ . '/../config/database.php';

function check_auth($allowed_roles = []) {
    if (!isset($_SESSION['user'])) {
        set_flash('danger', 'Silakan login terlebih dahulu untuk mengakses sistem.');
        header('Location: ' . base_url('auth/login.php'));
        exit;
    }

    $current_role = $_SESSION['user']['role'];

    if (!empty($allowed_roles) && !in_array($current_role, $allowed_roles)) {
        set_flash('danger', 'Akses ditolak! Anda tidak memiliki izin untuk membuka halaman tersebut.');
        
        // Redirect ke dashboard masing-masing role
        switch ($current_role) {
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
            default:
                header('Location: ' . base_url('auth/login.php'));
                break;
        }
        exit;
    }
}
