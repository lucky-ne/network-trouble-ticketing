<?php
/**
 * Redirector Form Lapor Gangguan ke Modal Pop-up Dashboard
 * PT. Visimedia Pratama Persada
 */
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['karyawan', 'customer']);

header('Location: ' . base_url('customer/dashboard.php?open_modal=1'));
exit;
