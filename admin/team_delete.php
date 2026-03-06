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

// Get team ID
$team_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($team_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID team tidak valid']);
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

function countByTeamRef(PDO $conn, string $table, string $column, int $teamId): int
{
    if (!columnExists($conn, $table, $column)) {
        return 0;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
    $stmt->execute([$teamId]);
    return (int) $stmt->fetchColumn();
}

function deleteByTeamRef(PDO $conn, string $table, string $column, int $teamId): void
{
    if (!columnExists($conn, $table, $column)) {
        return;
    }
    $stmt = $conn->prepare("DELETE FROM `{$table}` WHERE `{$column}` = ?");
    $stmt->execute([$teamId]);
}

function deleteByIds(PDO $conn, string $table, string $column, array $ids): void
{
    if (empty($ids) || !columnExists($conn, $table, $column)) {
        return;
    }
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $ids = array_filter($ids, function ($id) { return $id > 0; });
    if (empty($ids)) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("DELETE FROM `{$table}` WHERE `{$column}` IN ({$placeholders})");
    $stmt->execute($ids);
}

try {
    // Get team data for status and logo
    $stmt = $conn->prepare("SELECT id, is_active, logo FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        echo json_encode(['success' => false, 'message' => 'Team tidak ditemukan']);
        exit;
    }

    // Deteksi data turunan utama
    $usage_map = [
        ['table' => 'players', 'column' => 'team_id', 'label' => 'player'],
        ['table' => 'team_staff', 'column' => 'team_id', 'label' => 'staff'],
        ['table' => 'challenges', 'column' => 'challenger_id', 'label' => 'challenge'],
        ['table' => 'challenges', 'column' => 'opponent_id', 'label' => 'challenge'],
        ['table' => 'challenges', 'column' => 'winner_team_id', 'label' => 'challenge'],
        ['table' => 'matches', 'column' => 'team1_id', 'label' => 'match'],
        ['table' => 'matches', 'column' => 'team2_id', 'label' => 'match'],
        ['table' => 'goals', 'column' => 'team_id', 'label' => 'goal'],
        ['table' => 'lineups', 'column' => 'team_id', 'label' => 'lineup'],
        ['table' => 'transfers', 'column' => 'from_team_id', 'label' => 'transfer'],
        ['table' => 'transfers', 'column' => 'to_team_id', 'label' => 'transfer'],
        ['table' => 'match_staff_assignments', 'column' => 'team_id', 'label' => 'match_staff'],
        ['table' => 'player_event_cards', 'column' => 'team_id', 'label' => 'event_card']
    ];

    $usage_sources = [];
    foreach ($usage_map as $usage) {
        $count = countByTeamRef($conn, $usage['table'], $usage['column'], $team_id);
        if ($count > 0) {
            $usage_sources[] = $usage['label'];
        }
    }
    $usage_sources = array_values(array_unique($usage_sources));

    // Rule:
    // - Status aktif/nonaktif tidak berpengaruh
    // - Jika sudah ada data turunan => tidak bisa hapus
    // - Hanya team baru/tanpa turunan yang bisa dihapus
    if (!empty($usage_sources)) {
        echo json_encode([
            'success' => false,
            'message' => 'Team tidak bisa dihapus karena sudah terdaftar pada data turunan: ' . implode(', ', $usage_sources) . '.'
        ]);
        exit;
    }

    // Ambil id turunan untuk cleanup aman saat mode override nonaktif
    $player_ids = [];
    if (columnExists($conn, 'players', 'team_id')) {
        $stmt = $conn->prepare("SELECT id FROM players WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $player_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $staff_ids = [];
    if (columnExists($conn, 'team_staff', 'team_id')) {
        $stmt = $conn->prepare("SELECT id FROM team_staff WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $staff_ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    $conn->beginTransaction();

    // Cleanup data turunan agar delete team tidak mentok FK
    deleteByIds($conn, 'goals', 'player_id', $player_ids);
    deleteByIds($conn, 'lineups', 'player_id', $player_ids);
    deleteByIds($conn, 'transfers', 'player_id', $player_ids);
    deleteByIds($conn, 'player_event_cards', 'player_id', $player_ids);

    deleteByIds($conn, 'match_staff_assignments', 'staff_id', $staff_ids);
    deleteByIds($conn, 'staff_certificates', 'staff_id', $staff_ids);

    deleteByTeamRef($conn, 'goals', 'team_id', $team_id);
    deleteByTeamRef($conn, 'lineups', 'team_id', $team_id);
    deleteByTeamRef($conn, 'transfers', 'from_team_id', $team_id);
    deleteByTeamRef($conn, 'transfers', 'to_team_id', $team_id);
    deleteByTeamRef($conn, 'match_staff_assignments', 'team_id', $team_id);
    deleteByTeamRef($conn, 'event_team_values', 'team_id', $team_id);
    deleteByTeamRef($conn, 'player_event_cards', 'team_id', $team_id);
    deleteByTeamRef($conn, 'team_events', 'team_id', $team_id);
    deleteByTeamRef($conn, 'team_staff', 'team_id', $team_id);
    deleteByTeamRef($conn, 'players', 'team_id', $team_id);
    deleteByTeamRef($conn, 'matches', 'team1_id', $team_id);
    deleteByTeamRef($conn, 'matches', 'team2_id', $team_id);
    deleteByTeamRef($conn, 'challenges', 'challenger_id', $team_id);
    deleteByTeamRef($conn, 'challenges', 'opponent_id', $team_id);
    deleteByTeamRef($conn, 'challenges', 'winner_team_id', $team_id);

    if (columnExists($conn, 'admin_users', 'team_id')) {
        $stmt = $conn->prepare("UPDATE admin_users SET team_id = NULL WHERE team_id = ?");
        $stmt->execute([$team_id]);
    }

    $stmt = $conn->prepare("DELETE FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    if ($stmt->rowCount() <= 0) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus team']);
        exit;
    }

    // Delete team logo if exists
    if (!empty($team['logo'])) {
        $logo_path = (string) $team['logo'];
        $full_path = (strpos($logo_path, 'images/teams/') === false)
            ? ('../images/teams/' . $logo_path)
            : ('../' . $logo_path);
        if (file_exists($full_path)) {
            @unlink($full_path);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Team berhasil dihapus']);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $sqlState = (string) $e->getCode();
    $dbCode = (int) ($e->errorInfo[1] ?? 0);
    if ($sqlState === '23000' || $dbCode === 1451 || $dbCode === 1452) {
        echo json_encode(['success' => false, 'message' => 'Data team tidak bisa dihapus karena masih terhubung dengan data turunan.']);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menghapus team.']);
}
?>
