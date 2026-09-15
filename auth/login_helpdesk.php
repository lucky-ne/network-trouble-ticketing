<?php
// Dedicated Login Gateway: NOC Helpdesk Dispatcher
require_once __DIR__ . '/../config/database.php';
$_GET['portal'] = 'helpdesk';
require_once __DIR__ . '/login.php';
