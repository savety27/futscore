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

// Get staff ID
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($staff_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Get staff data for file deletion
    $stmt = $conn->prepare("SELECT photo FROM team_staff WHERE id = ?");
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        echo json_encode(['success' => false, 'message' => 'Staff not found']);
        exit;
    }
    
    // Get certificates for deletion
    $stmt = $conn->prepare("SELECT certificate_file FROM staff_certificates WHERE staff_id = ?");
    $stmt->execute([$staff_id]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Delete certificates files
    foreach ($certificates as $cert) {
        $file_path = '../uploads/certificates/' . $cert['certificate_file'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }
    
    // Delete staff photo
    if (!empty($staff['photo']) && file_exists('../' . $staff['photo'])) {
        @unlink('../' . $staff['photo']);
    }
    
    // Delete staff (cascading will delete certificates from database)
    $stmt = $conn->prepare("DELETE FROM team_staff WHERE id = ?");
    $success = $stmt->execute([$staff_id]);
    
    if ($success) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Staff berhasil dihapus']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus staff']);
    }
    
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>