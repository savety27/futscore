<?php
session_start();

// Load database config
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die(json_encode(['success' => false, 'message' => 'Database configuration not found']));
}

if (!isset($_SESSION['admin_logged_in'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Get berita ID
$berita_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($berita_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'Invalid berita ID']));
}

// Set header untuk JSON
header('Content-Type: application/json');

try {
    // Get gambar path before deletion
    $stmt = $conn->prepare("SELECT gambar FROM berita WHERE id = ?");
    $stmt->execute([$berita_id]);
    $berita = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$berita) {
        die(json_encode(['success' => false, 'message' => 'Berita not found']));
    }
    
    // Delete the berita
    $stmt = $conn->prepare("DELETE FROM berita WHERE id = ?");
    $stmt->execute([$berita_id]);
    
    // Delete associated image if exists
    if ($berita['gambar'] && file_exists('../images/berita/' . $berita['gambar'])) {
        @unlink('../images/berita/' . $berita['gambar']);
    }
    
    echo json_encode(['success' => true, 'message' => 'Berita berhasil dihapus']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>