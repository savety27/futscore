<?php
session_start();
header('Content-Type: application/json');

// Load database config
$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    echo json_encode(['success' => false, 'message' => 'Database configuration file not found']);
    exit;
}
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'operator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get challenge ID
$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$operator_id = (int)($_SESSION['admin_id'] ?? 0);
$operator_event_id = (int)($_SESSION['event_id'] ?? 0);

if ($challenge_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid challenge ID']);
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

try {
    $operator_event_is_active = true;
    if ($operator_id > 0) {
        try {
            $stmtOperator = $conn->prepare("
                SELECT au.event_id, COALESCE(e.is_active, 1) AS event_is_active
                FROM admin_users au
                LEFT JOIN events e ON e.id = au.event_id
                WHERE au.id = ?
                LIMIT 1
            ");
            $stmtOperator->execute([$operator_id]);
            $operator_row = $stmtOperator->fetch(PDO::FETCH_ASSOC);
            $operator_event_id = (int)($operator_row['event_id'] ?? $operator_event_id);
            $operator_event_is_active = ((int)($operator_row['event_is_active'] ?? 1) === 1);
            $_SESSION['event_id'] = $operator_event_id > 0 ? $operator_event_id : null;
        } catch (PDOException $e) {
            // keep session value
        }
    }

    $challenge_has_event_id = adminHasColumn($conn, 'challenges', 'event_id');

    // Start transaction
    $conn->beginTransaction();

    if (!$operator_event_is_active) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Event operator sedang non-aktif. Mode hanya lihat data.']);
        exit;
    }

    if ($challenge_has_event_id) {
        if ($operator_event_id <= 0) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Akun operator belum terhubung ke event']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM challenges WHERE id = ? AND event_id = ?");
        $success = $stmt->execute([$challenge_id, $operator_event_id]);
    } else {
        // Fallback schema lama tanpa event_id: tetap batasi by operator-created challenge is not possible.
        // Keep previous behavior to avoid blocking old schema.
        $stmt = $conn->prepare("DELETE FROM challenges WHERE id = ?");
        $success = $stmt->execute([$challenge_id]);
    }
    
    if ($success && $stmt->rowCount() > 0) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Challenge berhasil dihapus']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Challenge tidak ditemukan atau tidak punya akses']);
    }
    
} catch (PDOException $e) {
    $conn->rollBack();
    $message = $e->getMessage();

    // Constraint: challenge already referenced by lineup/score/event related tables.
    if (strpos($message, '1451') !== false || strpos(strtolower($message), 'foreign key constraint fails') !== false) {
        $message = 'Challenge tidak dapat dihapus karena sudah dipakai di data pertandingan (mis. lineup, skor, atau event terkait).';
    } else {
        $message = 'Terjadi kesalahan saat menghapus challenge.';
    }

    echo json_encode(['success' => false, 'message' => $message]);
}
?>
