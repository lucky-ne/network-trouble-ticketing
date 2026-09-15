<?php
/**
 * Proses Logout Sistem
 */
require_once __DIR__ . '/../config/database.php';

// Hapus semua data sesi
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Mulai sesi baru hanya untuk flash message
session_start();
set_flash('success', 'Anda telah berhasil keluar dari sistem.');
header('Location: ' . base_url('auth/login.php'));
exit;
