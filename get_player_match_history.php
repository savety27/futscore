<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$player_id = isset($_GET['player_id']) ? (int) $_GET['player_id'] : 0;
if ($player_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID player tidak valid.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Kompatibel jika kolom half belum ada pada tabel lineups
    $has_half_column = false;
    if ($result = $conn->query("SHOW COLUMNS FROM lineups LIKE 'half'")) {
        $has_half_column = $result->num_rows > 0;
        $result->close();
    }
    $has_event_id_column = false;
    if ($result = $conn->query("SHOW COLUMNS FROM challenges LIKE 'event_id'")) {
        $has_event_id_column = $result->num_rows > 0;
        $result->close();
    }

    $half_select = $has_half_column ? 'l.half' : 'NULL as half';
    $event_name_select = $has_event_id_column
        ? "TRIM(e.name) AS event_name,"
        : "'' AS event_name,";
    $event_join = $has_event_id_column ? "LEFT JOIN events e ON c.event_id = e.id" : "";
    $sql = "
        SELECT
            l.player_id,
            l.is_starting,
            l.position,
            $half_select,
            $event_name_select
            c.id AS challenge_id,
            c.challenge_code,
            c.sport_type,
            c.challenge_date,
            c.status,
            c.challenger_id,
            c.opponent_id,
            c.challenger_score,
            c.opponent_score,
            t1.name AS challenger_name,
            t2.name AS opponent_name,
            p.team_id AS player_team_id
        FROM lineups l
        INNER JOIN challenges c ON l.match_id = c.id
        $event_join
        INNER JOIN teams t1 ON c.challenger_id = t1.id
        INNER JOIN teams t2 ON c.opponent_id = t2.id
        INNER JOIN players p ON l.player_id = p.id
        WHERE l.player_id = ?
        ORDER BY c.challenge_date DESC, l.id DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Gagal menyiapkan query.');
    }
    $stmt->bind_param('i', $player_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $matches = [];
    $event_counter = [];
    while ($row = $res->fetch_assoc()) {
        $player_team_id = (int) $row['player_team_id'];
        $player_team_side = null;
        if ($player_team_id === (int) $row['challenger_id']) {
            $player_team_side = 'challenger';
        } elseif ($player_team_id === (int) $row['opponent_id']) {
            $player_team_side = 'opponent';
        }

        $challenge_date_fmt = '-';
        if (!empty($row['challenge_date'])) {
            $timestamp = strtotime($row['challenge_date']);
            if ($timestamp !== false) {
                $bulan = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agt', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
                $month_num = (int) date('n', $timestamp);
                $challenge_date_fmt = date('d', $timestamp) . ' ' . ($bulan[$month_num] ?? date('M', $timestamp)) . ' ' . date('Y H:i', $timestamp);
            }
        }

        $event_name = trim((string) ($row['event_name'] ?? ''));
        if ($event_name !== '') {
            if (!isset($event_counter[$event_name])) {
                $event_counter[$event_name] = 0;
            }
            $event_counter[$event_name]++;
        }

        $matches[] = [
            'challenge_id' => (int) $row['challenge_id'],
            'challenge_code' => $row['challenge_code'] ?? '',
            'event_name' => $event_name,
            'sport_type' => $row['sport_type'] ?? '',
            'challenge_date' => $row['challenge_date'] ?? '',
            'challenge_date_fmt' => $challenge_date_fmt,
            'status' => $row['status'] ?? '',
            'challenger_id' => (int) $row['challenger_id'],
            'opponent_id' => (int) $row['opponent_id'],
            'challenger_name' => $row['challenger_name'] ?? '',
            'opponent_name' => $row['opponent_name'] ?? '',
            'challenger_score' => $row['challenger_score'],
            'opponent_score' => $row['opponent_score'],
            'player_team_id' => $player_team_id,
            'player_team_side' => $player_team_side,
            'is_starting' => (int) $row['is_starting'],
            'position' => $row['position'] ?? '',
            'half' => $row['half'] !== null ? (int) $row['half'] : null,
        ];
    }

    $stmt->close();
    $conn->close();

    $event_summary = [];
    foreach ($event_counter as $event_name => $match_count) {
        $event_summary[] = [
            'name' => $event_name,
            'match_count' => (int) $match_count,
        ];
    }
    usort($event_summary, function ($a, $b) {
        return $b['match_count'] <=> $a['match_count'];
    });

    echo json_encode([
        'success' => true,
        'player_id' => $player_id,
        'total' => count($matches),
        'event_total' => count($event_summary),
        'event_summary' => $event_summary,
        'matches' => $matches,
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal memuat riwayat pertandingan.']);
}
