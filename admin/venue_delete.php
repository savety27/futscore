<?php
session_start();

// Load database config
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

// Set header for JSON response
header('Content-Type: application/json');

// Get venue ID
$venue_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venue_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid venue ID']);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Check if venue is used in events (if you have events table)
    // Uncomment if you have events table with venue_id foreign key
    /*
    $stmt = $conn->prepare("SELECT COUNT(*) as event_count FROM events WHERE venue_id = ?");
    $stmt->execute([$venue_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['event_count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus venue yang masih digunakan dalam event. Hapus event terkait terlebih dahulu.']);
        exit;
    }
    */
    
    // Delete venue
    $stmt = $conn->prepare("DELETE FROM venues WHERE id = ?");
    $success = $stmt->execute([$venue_id]);
    
    if ($success) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Venue berhasil dihapus']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus venue']);
    }
    
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>