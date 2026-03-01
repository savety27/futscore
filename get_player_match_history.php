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

    $has_goals_table = false;
    if ($result = $conn->query("SHOW TABLES LIKE 'goals'")) {
        $has_goals_table = $result->num_rows > 0;
        $result->close();
    }

    $stmt_player = $conn->prepare("SELECT team_id FROM players WHERE id = ? LIMIT 1");
    if (!$stmt_player) {
        throw new Exception('Gagal menyiapkan query pemain.');
    }
    $stmt_player->bind_param('i', $player_id);
    $stmt_player->execute();
    $res_player = $stmt_player->get_result();
    $player_row = $res_player ? $res_player->fetch_assoc() : null;
    $stmt_player->close();

    if (!$player_row) {
        echo json_encode(['success' => false, 'message' => 'Pemain tidak ditemukan.']);
        exit;
    }
    $player_team_id = (int)($player_row['team_id'] ?? 0);

    $lineup_half_select = $has_half_column
        ? "MIN(CASE WHEN l.half IN (1, 2) THEN l.half ELSE NULL END) AS half,"
        : "NULL AS half,";

    $goals_join = $has_goals_table
        ? "LEFT JOIN (
                SELECT
                    g.match_id,
                    COUNT(*) AS goal_count
                FROM goals g
                WHERE g.player_id = ?
                GROUP BY g.match_id
           ) gs ON gs.match_id = c.id"
        : "LEFT JOIN (
                SELECT
                    NULL AS match_id,
                    0 AS goal_count
           ) gs ON 1=0";

    $event_name_select = $has_event_id_column
        ? "CASE WHEN e.name IS NULL OR TRIM(e.name) = '' THEN 'Tanpa Event' ELSE TRIM(e.name) END AS event_name,"
        : "'Tanpa Event' AS event_name,";
    $event_join = $has_event_id_column ? "LEFT JOIN events e ON c.event_id = e.id" : "";
    $sql = "
        SELECT
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
            COALESCE(ls.is_starting, 0) AS is_starting,
            COALESCE(ls.position, '') AS position,
            ls.half,
            COALESCE(ls.lineup_count, 0) AS lineup_count,
            COALESCE(gs.goal_count, 0) AS goal_count
        FROM challenges c
        $event_join
        INNER JOIN teams t1 ON c.challenger_id = t1.id
        INNER JOIN teams t2 ON c.opponent_id = t2.id
        LEFT JOIN (
            SELECT
                l.match_id,
                MAX(COALESCE(l.is_starting, 0)) AS is_starting,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(
                        NULLIF(TRIM(l.position), '')
                        ORDER BY COALESCE(l.is_starting, 0) DESC, l.id DESC
                        SEPARATOR '||'
                    ),
                    '||',
                    1
                ) AS position,
                $lineup_half_select
                COUNT(*) AS lineup_count
            FROM lineups l
            WHERE l.player_id = ?
            GROUP BY l.match_id
        ) ls ON ls.match_id = c.id
        $goals_join
        WHERE (
            ls.match_id IS NOT NULL
            OR gs.match_id IS NOT NULL
        )
          AND c.status IN ('accepted', 'completed')
        ORDER BY c.challenge_date DESC, c.id DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Gagal menyiapkan query.');
    }
    if ($has_goals_table) {
        $stmt->bind_param('ii', $player_id, $player_id);
    } else {
        $stmt->bind_param('i', $player_id);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $matches = [];
    $event_counter = [];
    while ($row = $res->fetch_assoc()) {
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
        if ($event_name === '') {
            $event_name = 'Tanpa Event';
        }
        if (!isset($event_counter[$event_name])) {
            $event_counter[$event_name] = 0;
        }
        $event_counter[$event_name]++;

        $lineup_count = (int)($row['lineup_count'] ?? 0);
        $goal_count = (int)($row['goal_count'] ?? 0);
        $appearance_source = 'team';
        if ($lineup_count > 0) {
            $appearance_source = 'lineup';
        } elseif ($goal_count > 0) {
            $appearance_source = 'goal';
        }

        $half_value = null;
        if ($row['half'] !== null) {
            $half_candidate = (int)$row['half'];
            if ($half_candidate === 1 || $half_candidate === 2) {
                $half_value = $half_candidate;
            }
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
            'is_starting' => $appearance_source === 'lineup' ? (int) $row['is_starting'] : null,
            'position' => $appearance_source === 'lineup' ? (string)($row['position'] ?? '') : '',
            'half' => $appearance_source === 'lineup' ? $half_value : null,
            'appearance_source' => $appearance_source,
            'goal_count' => $goal_count,
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
