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

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
    exit;
}

try {
    // Validasi challenge + status sebelum delete
    $stmt = $conn->prepare("SELECT id, status FROM challenges WHERE id = ?");
    $stmt->execute([$challenge_id]);
    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$challenge) {
        echo json_encode(['success' => false, 'message' => 'Challenge tidak ditemukan']);
        exit;
    }

    $status = strtolower(trim((string)($challenge['status'] ?? '')));
    if ($status !== 'open') {
        echo json_encode([
            'success' => false,
            'message' => 'Challenge tidak dapat dihapus karena status bukan OPEN. Hanya challenge status OPEN yang bisa dihapus.'
        ]);
        exit;
    }

    // Start transaction only for delete operation
    $conn->beginTransaction();
    
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
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $message = 'Terjadi kesalahan saat menghapus challenge.';
    $sqlState = $e->getCode();
    $dbCode = (int)($e->errorInfo[1] ?? 0);
    $rawMessage = strtolower((string)$e->getMessage());

    // Constraint: challenge already referenced by lineup/score/event related tables.
    if ($sqlState === '23000' || $dbCode === 1451 || strpos($rawMessage, 'foreign key constraint fails') !== false) {
        $message = 'Challenge tidak dapat dihapus karena sudah dipakai di data pertandingan (mis. lineup, skor, atau event terkait).';
    }

    echo json_encode(['success' => false, 'message' => $message]);
}
?>
