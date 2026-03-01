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

function ensure_events_active_column(PDO $conn): void
{
    try {
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'is_active'");
        $exists = $check && $check->fetch(PDO::FETCH_ASSOC);
        if (!$exists) {
            $conn->exec("ALTER TABLE events ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER registration_status");
            try {
                $conn->exec("CREATE INDEX idx_is_active ON events (is_active)");
            } catch (PDOException $e) {
                // Index may already exist.
            }
        }
    } catch (PDOException $e) {
        // Keep delete flow running; query checks below will surface issues if any.
    }
}

function get_related_event_tables(PDO $conn, int $eventId): array
{
    $relatedTables = [];
    $usageTables = [
        'matches',
        'challenges',
        'team_events',
        'event_team_values',
        'player_event_cards'
    ];

    foreach ($usageTables as $tableName) {
        if (!table_has_column($conn, $tableName, 'event_id')) {
            continue;
        }

        $countStmt = $conn->prepare("SELECT COUNT(*) FROM `$tableName` WHERE event_id = ?");
        $countStmt->execute([$eventId]);
        $count = (int) $countStmt->fetchColumn();
        if ($count > 0) {
            $relatedTables[] = $tableName;
        }
    }

    return $relatedTables;
}

function table_has_column(PDO $conn, string $tableName, string $columnName): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName) || !preg_match('/^[a-zA-Z0-9_]+$/', $columnName)) {
        return false;
    }

    $stmt = $conn->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1"
    );
    $stmt->execute([$tableName, $columnName]);
    return (bool) $stmt->fetchColumn();
}

try {
    ensure_events_active_column($conn);

    $stmt = $conn->prepare("SELECT image FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan']);
        exit;
    }

    $relatedTables = get_related_event_tables($conn, $event_id);
    if (!empty($relatedTables)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Event tidak bisa dihapus karena sudah dipakai data terkait: ' . implode(', ', array_slice($relatedTables, 0, 4)) . (count($relatedTables) > 4 ? ', dll.' : '.')
        ]);
        exit;
    }

    $conn->beginTransaction();

    if (table_has_column($conn, 'admin_users', 'event_id')) {
        $clearAdminEvent = $conn->prepare("UPDATE admin_users SET event_id = NULL WHERE event_id = ?");
        $clearAdminEvent->execute([$event_id]);
    }

    $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$event_id]);

    if ($stmt->rowCount() > 0) {
        $conn->commit();

        if (!empty($event['image'])) {
            $image_path = __DIR__ . '/../images/events/' . $event['image'];
            if (file_exists($image_path)) {
                @unlink($image_path);
            }
        }

        echo json_encode(['success' => true, 'message' => 'Event berhasil dihapus permanen']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Event tidak ditemukan']);
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    $sqlState = (string) $e->getCode();
    $mysqlCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
    $rawMessage = strtolower((string) $e->getMessage());

    if ($sqlState === '23000' || $mysqlCode === 1451 || $mysqlCode === 1452 || strpos($rawMessage, 'foreign key') !== false) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Tidak dapat menghapus event karena sudah dipakai data lain (mis. pendaftaran/team/challenge terkait).'
        ]);
        exit;
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menghapus event.']);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menghapus event.']);
}
?>
