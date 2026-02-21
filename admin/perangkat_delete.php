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

$staff_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($staff_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
    exit;
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("SELECT photo, ktp_photo FROM perangkat WHERE id = ?");
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        echo json_encode(['success' => false, 'message' => 'Staff not found']);
        exit;
    }

    $stmt = $conn->prepare("SELECT license_file FROM perangkat_licenses WHERE perangkat_id = ?");
    $stmt->execute([$staff_id]);
    $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($licenses as $license) {
        $file_path = '../uploads/perangkat/licenses/' . $license['license_file'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }

    if (!empty($staff['photo']) && file_exists('../' . $staff['photo'])) {
        @unlink('../' . $staff['photo']);
    }
    if (!empty($staff['ktp_photo']) && file_exists('../' . $staff['ktp_photo'])) {
        @unlink('../' . $staff['ktp_photo']);
    }

    $stmt = $conn->prepare("DELETE FROM perangkat WHERE id = ?");
    $success = $stmt->execute([$staff_id]);

    if ($success) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Staff berhasil dihapus']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus staff']);
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
