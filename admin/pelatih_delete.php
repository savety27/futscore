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

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
    exit;
}

// Get pelatih ID (support GET/POST)
$pelatih_id = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;

if ($pelatih_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid pelatih ID']);
    exit;
}

try {
    $tableExists = function (string $table) use ($conn): bool {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        $stmt = $conn->query("SHOW TABLES LIKE " . $conn->quote($table));
        return (bool) $stmt->fetchColumn();
    };

    $columnExists = function (string $table, string $column) use ($conn, $tableExists): bool {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !$tableExists($table)) {
            return false;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        $stmt = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE " . $conn->quote($column));
        return (bool) $stmt->fetchColumn();
    };

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

    // Check if trying to delete currently logged in account
    $sessionUsername = $_SESSION['admin_username'] ?? null;
    $sessionEmail = $_SESSION['admin_email'] ?? null;
    if (($sessionUsername && $pelatih['username'] === $sessionUsername) ||
        ($sessionEmail && $pelatih['email'] === $sessionEmail)) {
        echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus akun sendiri']);
        exit;
    }

    // Aturan konsisten:
    // - Akun aktif + sudah pernah login (last_login terisi) => tidak bisa dihapus
    // - Akun nonaktif => boleh dihapus
    $isActive = (int)($pelatih['is_active'] ?? 0) === 1;
    $hasLastLogin = !empty($pelatih['last_login']) && $pelatih['last_login'] !== '0000-00-00 00:00:00';
    if ($isActive && $hasLastLogin) {
        echo json_encode([
            'success' => false,
            'message' => 'Akun aktif yang sudah pernah login tidak dapat dihapus. Nonaktifkan akun terlebih dahulu jika ingin menghapus.'
        ]);
        exit;
    }

    // Start transaction only for delete operation
    $conn->beginTransaction();
    
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
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $message = 'Terjadi kesalahan saat memproses penghapusan pelatih. Silakan coba lagi.';
    $sqlState = $e->getCode();
    $dbCode = (int)($e->errorInfo[1] ?? 0);

    if ($sqlState === '23000' || $dbCode === 1451 || $dbCode === 1452) {
        $message = 'Data pelatih tidak bisa dihapus karena masih terhubung dengan data lain.';
    } elseif ($sqlState === '42000' || $dbCode === 1064) {
        $message = 'Terjadi kesalahan konfigurasi query. Hubungi admin sistem.';
    }

    echo json_encode(['success' => false, 'message' => $message]);
}
?>
