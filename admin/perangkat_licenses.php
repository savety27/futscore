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

header('Content-Type: application/json');

$staff_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($staff_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, license_name, license_file, issuing_authority, issue_date, created_at
                            FROM perangkat_licenses
                            WHERE perangkat_id = ?
                            ORDER BY created_at DESC, id DESC");
    $stmt->execute([$staff_id]);
    $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'licenses' => $licenses,
        'count' => count($licenses)
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
