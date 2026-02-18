<?php
session_start();
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$event_id = (int) $_POST['id'];
if ($event_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid event ID']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT image FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    $conn->beginTransaction();

    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$event_id]);

    if ($stmt->rowCount() > 0) {
        $conn->commit();

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        if (!empty($event['image'])) {
            $image_path = __DIR__ . '/../images/events/' . $event['image'];
            if (file_exists($image_path)) {
                @unlink($image_path);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Event berhasil dihapus permanen', 'csrf_token' => $_SESSION['csrf_token']]);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan']);
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    $message = $e->getMessage();
    if (strpos($message, '1451') !== false || strpos(strtolower($message), 'foreign key constraint fails') !== false) {
        $message = 'Tidak dapat menghapus event yang sudah memiliki data terkait atau sudah berjalan.';
    } else {
        $message = 'Terjadi kesalahan saat menghapus event.';
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $message]);
}
?>
