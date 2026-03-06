<?php
require_once __DIR__ . '/../includes/auth_guard.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!admin_csrf_is_valid($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_POST['id']) || empty($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$player_id = (int)$_POST['id'];

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

function countByPlayerRef(PDO $conn, string $table, string $column, int $playerId): int
{
    if (!columnExists($conn, $table, $column)) {
        return 0;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
    $stmt->execute([$playerId]);
    return (int) $stmt->fetchColumn();
}

function deleteByPlayerRef(PDO $conn, string $table, string $column, int $playerId): void
{
    if (!columnExists($conn, $table, $column)) {
        return;
    }
    $stmt = $conn->prepare("DELETE FROM `{$table}` WHERE `{$column}` = ?");
    $stmt->execute([$playerId]);
}

try {
    // Get player data first (files are deleted only after DB delete succeeds)
    $stmt = $conn->prepare("SELECT id, status, photo, ktp_image, kk_image, birth_cert_image, diploma_image FROM players WHERE id = ?");
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Player tidak ditemukan']);
        exit;
    }

    // Data turunan utama yang menandakan player sudah dipakai di data pertandingan/event
    $usage_map = [
        ['table' => 'lineups', 'column' => 'player_id', 'label' => 'lineup'],
        ['table' => 'goals', 'column' => 'player_id', 'label' => 'goal'],
        ['table' => 'transfers', 'column' => 'player_id', 'label' => 'transfer'],
        ['table' => 'player_event_cards', 'column' => 'player_id', 'label' => 'event_card']
    ];

    $usage_sources = [];
    foreach ($usage_map as $usage) {
        $count = countByPlayerRef($conn, $usage['table'], $usage['column'], $player_id);
        if ($count > 0) {
            $usage_sources[] = $usage['label'];
        }
    }
    $usage_sources = array_values(array_unique($usage_sources));

    // Rule:
    // - Status aktif/nonaktif tidak berpengaruh
    // - Jika sudah ada data turunan, player tidak bisa dihapus
    if (!empty($usage_sources)) {
        echo json_encode([
            'success' => false,
            'message' => 'Player tidak bisa dihapus karena sudah terdaftar pada data turunan: ' . implode(', ', $usage_sources) . '.'
        ]);
        exit;
    }

    $conn->beginTransaction();

    // Cleanup data turunan agar delete player tidak mentok FK
    deleteByPlayerRef($conn, 'goals', 'player_id', $player_id);
    deleteByPlayerRef($conn, 'lineups', 'player_id', $player_id);
    deleteByPlayerRef($conn, 'transfers', 'player_id', $player_id);
    deleteByPlayerRef($conn, 'player_event_cards', 'player_id', $player_id);
    deleteByPlayerRef($conn, 'player_documents', 'player_id', $player_id);
    deleteByPlayerRef($conn, 'player_skills', 'player_id', $player_id);

    // HARD DELETE (benar-benar hapus dari database)
    $stmt = $conn->prepare("DELETE FROM players WHERE id = ?");
    $stmt->execute([$player_id]);

    if ($stmt->rowCount() > 0) {
        $conn->commit();

        if ($player) {
            // Delete files from server only after DB delete succeeds
            $upload_dir = '../../images/players/';
            $files_to_delete = [
                $player['photo'],
                $player['ktp_image'],
                $player['kk_image'],
                $player['birth_cert_image'],
                $player['diploma_image']
            ];

            foreach ($files_to_delete as $file) {
                if (!empty($file)) {
                    $possible_paths = [
                        $upload_dir . $file,
                        '../../' . $file,
                        'images/players/' . $file,
                        'uploads/players/' . $file,
                        $file
                    ];

                    foreach ($possible_paths as $file_path) {
                        if (file_exists($file_path)) {
                            @unlink($file_path);
                            break;
                        }
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'message' => 'Player berhasil dihapus']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus player']);
    }
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $sqlState = (string) $e->getCode();
    $dbCode = (int) ($e->errorInfo[1] ?? 0);
    if ($sqlState === '23000' || $dbCode === 1451 || $dbCode === 1452) {
        echo json_encode(['success' => false, 'message' => 'Data player tidak bisa dihapus karena masih terhubung dengan data turunan.']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menghapus player.']);
}
?>
