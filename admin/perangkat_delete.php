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

$perangkat_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($perangkat_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID perangkat tidak valid']);
    exit;
}

function tableExists(PDO $conn, string $table): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }
    $stmt = $conn->query("SHOW TABLES LIKE " . $conn->quote($table));
    return (bool) $stmt->fetchColumn();
}

function columnExists(PDO $conn, string $table, string $column): bool
{
    if (!tableExists($conn, $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }
    $stmt = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE " . $conn->quote($column));
    return (bool) $stmt->fetchColumn();
}

function countOfficialUsage(PDO $conn, string $table, string $column, string $officialName): int
{
    if (!columnExists($conn, $table, $column) || trim($officialName) === '') {
        return 0;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) FROM `{$table}` WHERE LOWER(TRIM(`{$column}`)) = LOWER(TRIM(?))");
    $stmt->execute([$officialName]);
    return (int) $stmt->fetchColumn();
}

try {
    $stmt = $conn->prepare("SELECT id, name, is_active, photo, ktp_photo FROM perangkat WHERE id = ?");
    $stmt->execute([$perangkat_id]);
    $perangkat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$perangkat) {
        echo json_encode(['success' => false, 'message' => 'Data perangkat tidak ditemukan']);
        exit;
    }

    $usageSources = [];
    $officialName = trim((string) ($perangkat['name'] ?? ''));

    $challengeUsage = countOfficialUsage($conn, 'challenges', 'match_official', $officialName);
    if ($challengeUsage > 0) {
        $usageSources[] = 'challenge';
    }

    $matchUsage = countOfficialUsage($conn, 'matches', 'match_official', $officialName);
    if ($matchUsage > 0) {
        $usageSources[] = 'match';
    }

    $eventUsage = countOfficialUsage($conn, 'events', 'match_official', $officialName);
    if ($eventUsage > 0) {
        $usageSources[] = 'event';
    }

    $isActive = (int) ($perangkat['is_active'] ?? 0) === 1;
    if ($isActive && !empty($usageSources)) {
        $usageSources = array_values(array_unique($usageSources));
        echo json_encode([
            'success' => false,
            'message' => 'Perangkat aktif tidak bisa dihapus karena sudah terdaftar pada data: ' . implode(', ', $usageSources) . '. Nonaktifkan dulu perangkat jika tetap ingin menghapus.'
        ]);
        exit;
    }

    $licenseFiles = [];
    if (tableExists($conn, 'perangkat_licenses') && columnExists($conn, 'perangkat_licenses', 'license_file')) {
        $stmt = $conn->prepare("SELECT license_file FROM perangkat_licenses WHERE perangkat_id = ?");
        $stmt->execute([$perangkat_id]);
        $licenseFiles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $conn->beginTransaction();

    $stmt = $conn->prepare("DELETE FROM perangkat WHERE id = ?");
    $success = $stmt->execute([$perangkat_id]);

    if ($success) {
        $conn->commit();

        foreach ($licenseFiles as $licenseFile) {
            $file_path = '../uploads/perangkat/licenses/' . (string) $licenseFile;
            if ($licenseFile && file_exists($file_path)) {
                @unlink($file_path);
            }
        }

        $photoPath = (string) ($perangkat['photo'] ?? '');
        $ktpPhotoPath = (string) ($perangkat['ktp_photo'] ?? '');

        if ($photoPath !== '' && file_exists('../' . $photoPath)) {
            @unlink('../' . $photoPath);
        }
        if ($ktpPhotoPath !== '' && file_exists('../' . $ktpPhotoPath)) {
            @unlink('../' . $ktpPhotoPath);
        }

        echo json_encode(['success' => true, 'message' => 'Perangkat berhasil dihapus']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus perangkat']);
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $sqlState = (string) $e->getCode();
    $dbCode = (int) ($e->errorInfo[1] ?? 0);
    if ($sqlState === '23000' || $dbCode === 1451 || $dbCode === 1452) {
        echo json_encode(['success' => false, 'message' => 'Perangkat tidak bisa dihapus karena masih terhubung dengan data turunan (match/challenge/event/dll).']);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menghapus perangkat.']);
}
?>
