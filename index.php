<?php
/**
 * Root Router / Entrypoint Sistem
 */
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['user'])) {
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$role = $_SESSION['user']['role'];

switch ($role) {
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
