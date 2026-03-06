<?php
require_once __DIR__ . '/includes/auth_guard.php';

// Load database config
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
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

if (!function_exists('adminHasColumn')) {
    function adminHasColumn(PDO $conn, $tableName, $columnName) {
        try {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tableName);
            if ($safeTable === '') {
                return false;
            }
            $quotedColumn = $conn->quote((string) $columnName);
            $stmt = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quotedColumn}");
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('adminHasTable')) {
    function adminHasTable(PDO $conn, $tableName) {
        try {
            $quotedTable = $conn->quote((string) $tableName);
            $stmt = $conn->query("SHOW TABLES LIKE {$quotedTable}");
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('countByColumnValue')) {
    function countByColumnValue(PDO $conn, string $table, string $column, int $value): int
    {
        if (!adminHasTable($conn, $table) || !adminHasColumn($conn, $table, $column)) {
            return 0;
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
        $stmt->execute([$value]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('getChallengeDependencySources')) {
    function getChallengeDependencySources(PDO $conn, int $challengeId): array
    {
        $usageMap = [
            ['table' => 'lineups', 'column' => 'match_id', 'label' => 'lineup'],
            ['table' => 'goals', 'column' => 'match_id', 'label' => 'goal'],
            ['table' => 'match_stats', 'column' => 'match_id', 'label' => 'match_stats'],
            ['table' => 'match_staff_assignments', 'column' => 'match_id', 'label' => 'match_staff'],
            ['table' => 'predictions', 'column' => 'match_id', 'label' => 'prediction']
        ];

        $sources = [];
        foreach ($usageMap as $usage) {
            $count = countByColumnValue($conn, $usage['table'], $usage['column'], $challengeId);
            if ($count > 0) {
                $sources[] = $usage['label'];
            }
        }

        $bracketColumns = ['sf1_challenge_id', 'sf2_challenge_id', 'final_challenge_id', 'third_challenge_id'];
        foreach ($bracketColumns as $column) {
            $count = countByColumnValue($conn, 'event_brackets', $column, $challengeId);
            if ($count > 0) {
                $sources[] = 'event_bracket';
                break;
            }
        }

        return array_values(array_unique($sources));
    }
}

try {
    // Validasi challenge sebelum delete
    $stmt = $conn->prepare("SELECT id FROM challenges WHERE id = ?");
    $stmt->execute([$challenge_id]);
    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$challenge) {
        echo json_encode(['success' => false, 'message' => 'Challenge tidak ditemukan']);
        exit;
    }

    $usageSources = getChallengeDependencySources($conn, $challenge_id);
    if (!empty($usageSources)) {
        echo json_encode([
            'success' => false,
            'message' => 'Challenge tidak bisa dihapus karena sudah dipakai di data turunan: ' . implode(', ', $usageSources) . '.'
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
