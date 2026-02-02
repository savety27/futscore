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

// Get challenge ID
$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($challenge_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid challenge ID']);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // PERUBAHAN: Hapus validasi cek skor, semua challenge bisa dihapus
    // Tidak perlu cek apakah sudah ada skor atau belum
    
    // Delete challenge
    $stmt = $conn->prepare("DELETE FROM challenges WHERE id = ?");
    $success = $stmt->execute([$challenge_id]);
    
    if ($success) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Challenge berhasil dihapus']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus challenge']);
    }
    
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>