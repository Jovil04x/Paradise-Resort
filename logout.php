<?php
require_once __DIR__ . '/utils.php';
$_SESSION = [];
session_destroy();
session_start();
set_flash('success', 'You have been logged out.');
header('Location: /paradiseresort/index.php');
exit;
?>


