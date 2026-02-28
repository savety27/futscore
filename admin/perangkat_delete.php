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

    $stmt = $conn->prepare("SELECT name, photo, ktp_photo FROM perangkat WHERE id = ?");
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Staff not found']);
        exit;
    }

    // Cegah hapus jika perangkat masih dipakai pada data match (challenges.match_official)
    $stmt = $conn->prepare("SHOW COLUMNS FROM challenges LIKE 'match_official'");
    $stmt->execute();
    $has_match_official_column = (bool) $stmt->fetchColumn();

    if ($has_match_official_column) {
        $stmt = $conn->prepare("SELECT COUNT(*) as usage_count FROM challenges WHERE LOWER(TRIM(match_official)) = LOWER(TRIM(?))");
        $stmt->execute([(string) ($staff['name'] ?? '')]);
        $usage = $stmt->fetch(PDO::FETCH_ASSOC);
        $usage_count = (int) ($usage['usage_count'] ?? 0);

        if ($usage_count > 0) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            echo json_encode([
                'success' => false,
                'message' => 'Tidak dapat menghapus perangkat yang sudah terdaftar pada match. Nonaktifkan perangkat jika tidak dipakai lagi.'
            ]);
            exit;
        }
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
        echo json_encode(['success' => true, 'message' => 'Perangkat berhasil dihapus']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus perangkat']);
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
