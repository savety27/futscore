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
    echo json_encode(['success' => false, 'message' => 'ID staff tidak valid']);
    exit;
}

try {
    // Get staff data first (including status)
    $stmt = $conn->prepare("SELECT id, photo, is_active FROM team_staff WHERE id = ?");
    $stmt->execute([$staff_id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        echo json_encode(['success' => false, 'message' => 'Data staff tidak ditemukan']);
        exit;
    }

    // Cek pemakaian staff pada data turunan operasional (match assignment)
    $assignment_count = 0;
    $stmt = $conn->prepare("SHOW TABLES LIKE 'match_staff_assignments'");
    $stmt->execute();
    $has_match_staff_assignments = (bool) $stmt->fetchColumn();

    if ($has_match_staff_assignments) {
        $stmt = $conn->prepare("SELECT COUNT(*) as assignment_count FROM match_staff_assignments WHERE staff_id = ?");
        $stmt->execute([$staff_id]);
        $usage = $stmt->fetch(PDO::FETCH_ASSOC);
        $assignment_count = (int) ($usage['assignment_count'] ?? 0);
    }

    // Rule:
    // - staff aktif + ada data turunan => tidak bisa hapus
    // - staff nonaktif => bisa hapus (override)
    // - staff baru / tanpa data turunan => bisa hapus
    $is_active = (int) ($staff['is_active'] ?? 0) === 1;
    if ($is_active && $assignment_count > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Tidak dapat menghapus staff aktif yang sudah terdaftar pada match. Nonaktifkan staff terlebih dahulu jika tetap ingin menghapus.'
        ]);
        exit;
    }

    // Start transaction for delete operation
    $conn->beginTransaction();
    
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
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $sqlState = (string) $e->getCode();
    $dbCode = (int) ($e->errorInfo[1] ?? 0);
    if ($sqlState === '23000' || $dbCode === 1451 || $dbCode === 1452) {
        echo json_encode(['success' => false, 'message' => 'Data staff tidak bisa dihapus karena masih terhubung dengan data turunan.']);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menghapus staff.']);
}
?>
