<?php
header('Content-Type: application/json');

$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    echo json_encode(['success' => false, 'error' => 'Database config not found']);
    exit;
}

$eventId   = isset($_GET['event_id'])   ? (int)$_GET['event_id']   : 0;
$playerId  = isset($_GET['player_id'])  ? (int)$_GET['player_id']  : 0;
$teamId    = isset($_GET['team_id'])    ? (int)$_GET['team_id']    : 0;
$sportType = isset($_GET['sport_type']) ? trim($_GET['sport_type']) : '';

if ($eventId <= 0 || $playerId <= 0 || $teamId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Parameter tidak valid']);
    exit;
}

try {
    // Get player info
    $stmtPlayer = $conn->prepare("SELECT name, photo, jersey_number FROM players WHERE id = ? LIMIT 1");
    $stmtPlayer->execute([$playerId]);
    $player = $stmtPlayer->fetch(PDO::FETCH_ASSOC);
    if (!$player) {
        echo json_encode(['success' => false, 'error' => 'Pemain tidak ditemukan']);
        exit;
    }

    // Get event name
    $stmtEvent = $conn->prepare("SELECT name FROM events WHERE id = ? LIMIT 1");
    $stmtEvent->execute([$eventId]);
    $eventRecord = $stmtEvent->fetch(PDO::FETCH_ASSOC);
    $eventName = $eventRecord ? $eventRecord['name'] : 'Event';

    // --- Build match history query ---
    // Strategy:
    //   1. Join lineups → challenges where player_id matches
    //   2. Filter by event_id (player is in lineup record tied to a challenge in this event)
    //   3. Optionally filter by sport_type
    //   If event_id filter returns 0 rows, fall back to sport_type-only filter
    //   so the feature still works even if event_id on challenges is not set.

    $baseSql = "
        SELECT
            c.id                AS match_id,
            c.challenge_date,
            c.notes             AS match_info,
            c.challenger_id,
            c.opponent_id,
            c.challenger_score,
            c.opponent_score,
            CASE WHEN c.challenger_id = ? THEN c.opponent_id        ELSE c.challenger_id    END AS opp_team_id,
            CASE WHEN c.challenger_id = ? THEN c.challenger_score   ELSE c.opponent_score   END AS my_score,
            CASE WHEN c.challenger_id = ? THEN c.opponent_score     ELSE c.challenger_score END AS their_score,
            t_opp.name          AS opp_team_name,
            t_opp.logo          AS opp_team_logo
        FROM lineups l
        INNER JOIN challenges c   ON l.match_id = c.id
        INNER JOIN teams t_opp    ON t_opp.id = CASE WHEN c.challenger_id = ? THEN c.opponent_id ELSE c.challenger_id END
        WHERE l.player_id = ?
          AND (c.challenger_id = ? OR c.opponent_id = ?)
          AND c.status IN ('accepted', 'completed')
    ";

    // Try with event_id filter first
    $sqlWithEvent  = $baseSql . " AND c.event_id = ?";
    $paramsWithEvent = [$teamId, $teamId, $teamId, $teamId, $playerId, $teamId, $teamId, $eventId];

    if ($sportType !== '') {
        $sqlWithEvent    .= " AND c.sport_type = ?";
        $paramsWithEvent[] = $sportType;
    }
    $sqlWithEvent .= " GROUP BY c.id ORDER BY c.challenge_date DESC";

    $stmtM = $conn->prepare($sqlWithEvent);
    $stmtM->execute($paramsWithEvent);
    $matchRows = $stmtM->fetchAll(PDO::FETCH_ASSOC);

    // Fallback: if no rows found with event_id, try with sport_type only (or team-based match)
    if (empty($matchRows) && $sportType !== '') {
        $sqlFallback = $baseSql . " AND c.sport_type = ?";
        $paramsFallback = [$teamId, $teamId, $teamId, $teamId, $playerId, $teamId, $teamId, $sportType];
        $sqlFallback .= " GROUP BY c.id ORDER BY c.challenge_date DESC";
        $stmtF = $conn->prepare($sqlFallback);
        $stmtF->execute($paramsFallback);
        $matchRows = $stmtF->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch goals per match for this player
    $goalsMap = [];
    if (!empty($matchRows)) {
        $matchIds  = array_column($matchRows, 'match_id');
        $inClause  = implode(',', array_fill(0, count($matchIds), '?'));
        $stmtGoals = $conn->prepare("SELECT match_id, COUNT(*) AS cnt FROM goals WHERE player_id = ? AND match_id IN ($inClause) GROUP BY match_id");
        $stmtGoals->execute(array_merge([$playerId], $matchIds));
        foreach ($stmtGoals->fetchAll(PDO::FETCH_ASSOC) as $gRow) {
            $goalsMap[(int)$gRow['match_id']] = (int)$gRow['cnt'];
        }
    }

    // Get aggregate player cards for this event
    $cardParams = [$eventId, $playerId];
    $cardWhere  = "WHERE event_id = ? AND player_id = ?";
    if ($sportType !== '') {
        $cardWhere   .= " AND sport_type = ?";
        $cardParams[] = $sportType;
    }
    $stmtCards = $conn->prepare("SELECT yellow_cards, red_cards, green_cards FROM player_event_cards $cardWhere LIMIT 1");
    $stmtCards->execute($cardParams);
    $cardsRow = $stmtCards->fetch(PDO::FETCH_ASSOC);

    $matches = [];
    foreach ($matchRows as $row) {
        $myScore    = $row['my_score'];
        $theirScore = $row['their_score'];
        $result     = '-';
        if ($myScore !== null && $theirScore !== null) {
            if ((int)$myScore > (int)$theirScore)     $result = 'W';
            elseif ((int)$myScore < (int)$theirScore) $result = 'L';
            else                                       $result = 'D';
        }

        $matchId   = (int)$row['match_id'];
        $matches[] = [
            'match_id'      => $matchId,
            'date'          => $row['challenge_date'],
            'match_info'    => trim((string)($row['match_info'] ?? '')),
            'opp_team_name' => (string)($row['opp_team_name'] ?? '-'),
            'opp_team_logo' => (string)($row['opp_team_logo'] ?? ''),
            'my_score'      => $myScore !== null ? (int)$myScore : null,
            'their_score'   => $theirScore !== null ? (int)$theirScore : null,
            'result'        => $result,
            'goals'         => $goalsMap[$matchId] ?? 0,
        ];
    }

    echo json_encode([
        'success'       => true,
        'event_name'    => $eventName,
        'player_name'   => $player['name'],
        'jersey_number' => $player['jersey_number'] ?? '-',
        'category'      => $sportType,
        'cards'         => [
            'yellow' => (int)($cardsRow['yellow_cards'] ?? 0),
            'red'    => (int)($cardsRow['red_cards']    ?? 0),
            'green'  => (int)($cardsRow['green_cards']  ?? 0),
        ],
        'matches'       => $matches,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
