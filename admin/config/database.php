<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'futscore_db';

// Create PDO connection
try {
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $conn = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage());
}

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Function untuk log error
function logError($message) {
    $log_file = __DIR__ . '/error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Function untuk debug
function debug($data) {
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}

// Return connection object
return $conn;
?>