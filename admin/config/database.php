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

    // Otomatis expired challenge yang sudah lewat tanggal
    try {
        $stmt_expire = $conn->prepare("UPDATE challenges SET status = 'expired' WHERE status = 'open' AND challenge_date < NOW()");
        $stmt_expire->execute();

        // Sync match_status based on status
        // Expired -> Abandoned
        $conn->exec("UPDATE challenges SET match_status = 'abandoned' WHERE status = 'expired' AND match_status != 'abandoned'");
        
        // Rejected -> Cancelled
        $conn->exec("UPDATE challenges SET match_status = 'cancelled' WHERE status = 'rejected' AND match_status != 'cancelled'");
        
        // Accepted -> Scheduled (Coming Soon)
        $conn->exec("UPDATE challenges SET match_status = 'scheduled' WHERE status = 'accepted' AND (match_status IS NULL OR match_status = '')");

    } catch (PDOException $e) {
        // Silent fail: prevent blocking admin access if this query fails
    }

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