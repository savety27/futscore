<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'futscore_db');

// Site configuration
define('SITE_URL', 'http://localhost/futscore');
define('SITE_NAME', 'Futscore');
define('SITE_DESC', 'Aplikasi berbasis web untuk pembinaan atlet futsal');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>