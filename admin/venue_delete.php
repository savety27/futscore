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

// Get venue ID
$venue_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venue_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid venue ID']);
    exit;
}

if (!isset($conn) || !$conn) {
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal']);
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
        if (!$tableExists($table)) {
            return false;
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
            return false;
        }
        $stmt = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE " . $conn->quote($column));
        return (bool) $stmt->fetchColumn();
    };

    // Get venue status first for business rule validation
    $stmt = $conn->prepare("SELECT id, is_active FROM venues WHERE id = ?");
    $stmt->execute([$venue_id]);
    $venue = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venue) {
        echo json_encode(['success' => false, 'message' => 'Venue tidak ditemukan']);
        exit;
    }

    $isActive = (int) ($venue['is_active'] ?? 0) === 1;

    // Hitung apakah venue pernah dipakai di challenge/match/event (jika tabel+kolom tersedia)
    $referenceTargets = [
        ['table' => 'challenges', 'column' => 'venue_id', 'label' => 'challenge'],
        ['table' => 'matches', 'column' => 'venue_id', 'label' => 'match'],
        ['table' => 'events', 'column' => 'venue_id', 'label' => 'event'],
    ];
    $usageTotal = 0;
    $usedIn = [];

    foreach ($referenceTargets as $target) {
        $table = $target['table'];
        $column = $target['column'];
        if (!$columnExists($table, $column)) {
            continue;
        }

        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM `{$table}` WHERE `{$column}` = ?");
        $stmt->execute([$venue_id]);
        $count = (int) ($stmt->fetchColumn() ?: 0);

        if ($count > 0) {
            $usageTotal += $count;
            $usedIn[] = $target['label'];
        }
    }

    // Rule:
    // - Venue belum pernah dipakai => bisa hapus
    // - Venue nonaktif => bisa hapus walau sudah pernah dipakai
    // - Venue aktif + sudah pernah dipakai => tidak bisa hapus
    if ($isActive && $usageTotal > 0) {
        $sourceText = !empty($usedIn) ? implode('/', array_unique($usedIn)) : 'match/challenge/event';
        echo json_encode([
            'success' => false,
            'message' => "Venue aktif yang sudah pernah terdaftar pada {$sourceText} tidak dapat dihapus. Nonaktifkan venue terlebih dahulu jika ingin menghapus."
        ]);
        exit;
    }

    // Start transaction only for delete operation
    $conn->beginTransaction();
    
    // Delete venue
    $stmt = $conn->prepare("DELETE FROM venues WHERE id = ?");
    $success = $stmt->execute([$venue_id]);
    
    if ($success) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Venue berhasil dihapus']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus venue']);
    }
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $message = 'Terjadi kesalahan saat memproses penghapusan venue. Silakan coba lagi.';
    $sqlState = $e->getCode();
    $dbCode = (int) ($e->errorInfo[1] ?? 0);

    if ($sqlState === '23000' || $dbCode === 1451 || $dbCode === 1452) {
        $message = 'Data venue tidak bisa dihapus karena masih terhubung dengan data lain.';
    } elseif ($sqlState === '42000' || $dbCode === 1064) {
        $message = 'Terjadi kesalahan konfigurasi query. Hubungi admin sistem.';
    }

    echo json_encode(['success' => false, 'message' => $message]);
}
?>
