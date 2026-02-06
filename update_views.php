<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$newsId = isset($_POST['news_id']) ? intval($_POST['news_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($newsId <= 0 || $action !== 'increment') {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Start session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cegah multiple views dari user yang sama dalam 1 jam
$viewKey = 'news_viewed_' . $newsId;
if (isset($_SESSION[$viewKey])) {
    $lastViewTime = $_SESSION[$viewKey];
    $oneHourAgo = time() - 3600;
    
    if ($lastViewTime > $oneHourAgo) {
        echo json_encode(['success' => true, 'message' => 'View already counted recently']);
        exit;
    }
}

// Update views di database
$db = new Database();
$conn = $db->getConnection();

$sql = "UPDATE berita SET views = views + 1 WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $newsId);

if ($stmt->execute()) {
    // Simpan timestamp view untuk mencegah multiple views
    $_SESSION[$viewKey] = time();
    
    // Ambil view count terbaru
    $countSql = "SELECT views FROM berita WHERE id = ?";
    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param("i", $newsId);
    $countStmt->execute();
    $result = $countStmt->get_result();
    $row = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true, 
        'message' => 'View count updated',
        'new_count' => $row['views']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update database']);
}

$stmt->close();
$conn->close();
?>