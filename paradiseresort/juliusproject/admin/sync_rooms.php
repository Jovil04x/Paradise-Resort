<?php
require_once __DIR__ . '/../utils.php';
require_admin();
set_flash('warning', 'The sync tool is disabled.');
header('Location: /paradiseresort/admin/rooms.php');
exit;
?>
