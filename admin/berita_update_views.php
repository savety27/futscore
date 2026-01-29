<?php
// Load database config
$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die(json_encode(['success' => false, 'message' => 'Database configuration not found']));
}

// Get parameters
$berita_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

// Set header untuk JSON
header('Content-Type: application/json');

if ($berita_id <= 0 && empty($slug)) {
    die(json_encode(['success' => false, 'message' => 'Invalid parameters']));
}

try {
    // Update views based on ID or slug
    if ($berita_id > 0) {
        $stmt = $conn->prepare("UPDATE berita SET views = views + 1 WHERE id = ?");
        $stmt->execute([$berita_id]);
    } elseif (!empty($slug)) {
        $stmt = $conn->prepare("UPDATE berita SET views = views + 1 WHERE slug = ?");
        $stmt->execute([$slug]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Views updated']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>