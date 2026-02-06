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

// Get pelatih ID
$pelatih_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pelatih_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid pelatih ID']);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Check if trying to delete self
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $pelatih_id) {
        echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus akun sendiri']);
        exit;
    }
    
    // Get pelatih data
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$pelatih_id]);
    $pelatih = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pelatih) {
        echo json_encode(['success' => false, 'message' => 'Pelatih tidak ditemukan']);
        exit;
    }
    
    // Check if it's the last superadmin
    if ($pelatih['role'] === 'superadmin') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_users WHERE role = 'superadmin'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] <= 1) {
            echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus superadmin terakhir']);
            exit;
        }
    }
    
    // Delete pelatih
    $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
    $success = $stmt->execute([$pelatih_id]);
    
    if ($success) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Pelatih berhasil dihapus']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus pelatih']);
    }
    
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>