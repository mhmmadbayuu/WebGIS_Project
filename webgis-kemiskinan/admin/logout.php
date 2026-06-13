<?php
require_once __DIR__ . '/../php/koneksi.php';
require_once __DIR__ . '/../php/middleware/auth.php';
doLogout($pdo);
header('Location: login.php');
exit;
