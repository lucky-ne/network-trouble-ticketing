<?php
require_once __DIR__ . '/../config/database.php';
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ' . base_url('customer/buat_tiket.php' . $qs));
exit;
