<?php
require_once __DIR__ . '/includes/auth_guard.php';

// Hapus semua session
session_unset();
session_destroy();

// Redirect ke halaman utama
header("Location: ../index.php");
exit();
?>
