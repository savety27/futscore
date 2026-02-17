<?php
session_start();

$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

$create_table_sql = "
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    location VARCHAR(100) DEFAULT NULL,
    registration_status ENUM('open','closed') DEFAULT 'open',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    contact VARCHAR(50) DEFAULT NULL,
    category VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_registration_status (registration_status),
    INDEX idx_is_active (is_active),
    INDEX idx_start_date (start_date),
    INDEX idx_end_date (end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
";

try {
    $conn->exec($create_table_sql);
    $check_column_stmt = $conn->query("SHOW COLUMNS FROM events LIKE 'is_active'");
    $column_exists = $check_column_stmt && $check_column_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$column_exists) {
        $conn->exec("ALTER TABLE events ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER registration_status");
        $conn->exec("CREATE INDEX idx_is_active ON events (is_active)");
    }
    $message = "Tabel 'events' berhasil dibuat atau sudah ada.";
    $success = true;
} catch (PDOException $e) {
    $message = "Error membuat tabel events: " . $e->getMessage();
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup Events Table</title>
<style>
body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    color: #1f2937;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    margin: 0;
}
.card {
    max-width: 680px;
    background: #fff;
    border-radius: 16px;
    padding: 26px;
    box-shadow: 0 20px 35px rgba(15, 39, 68, 0.14);
}
.icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: 12px;
}
.ok { background: #dcfce7; color: #15803d; }
.fail { background: #fee2e2; color: #b91c1c; }
h1 { margin: 0 0 10px; font-size: 24px; color: #0f2744; }
p { margin: 0 0 16px; line-height: 1.5; }
.actions { display: flex; gap: 10px; flex-wrap: wrap; }
.btn {
    text-decoration: none;
    color: white;
    padding: 10px 14px;
    border-radius: 8px;
    font-weight: 600;
}
.btn-primary { background: #0f2744; }
.btn-secondary { background: #6b7280; }
</style>
</head>
<body>
    <div class="card">
        <div class="icon <?php echo $success ? 'ok' : 'fail'; ?>"><?php echo $success ? '✓' : '!'; ?></div>
        <h1>Setup Events Table</h1>
        <p><?php echo htmlspecialchars($message); ?></p>
        <div class="actions">
            <a href="event_create.php" class="btn btn-primary">Ke Form Event</a>
            <a href="challenge.php" class="btn btn-secondary">Ke Halaman Event</a>
        </div>
    </div>
</body>
</html>
