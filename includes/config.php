<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'futscore_db');

// Site configuration (supports ngrok/reverse proxies)
$protocol = 'http';
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $protocol = 'https';
} elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $protocol = 'https';
}

$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']);
define('SITE_URL', rtrim($protocol . '://' . $host . $path, '/'));
define('SITE_NAME', 'Futscore');
define('SITE_DESC', 'Aplikasi berbasis web untuk pembinaan atlet futsal');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>