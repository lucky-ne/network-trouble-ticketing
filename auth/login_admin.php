<?php
// Dedicated Login Gateway: Administrator Master
require_once __DIR__ . '/../config/database.php';
$_GET['portal'] = 'admin';
require_once __DIR__ . '/login.php';
