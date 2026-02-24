<?php
header('Content-Type: application/json');

$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    echo json_encode(['success' => false, 'error' => 'Database config not found']);
    exit;
}

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$teamId  = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$sportType = isset($_GET['sport_type']) ? trim($_GET['sport_type']) : '';

if ($eventId <= 0 || $teamId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // 1. Get Team Info
    $stmtTeam = $conn->prepare("SELECT id, name, logo FROM teams WHERE id = ?");
    $stmtTeam->execute([$teamId]);
    $team = $stmtTeam->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        echo json_encode(['success' => false, 'error' => 'Team not found']);
        exit;
    }

    // 2. Get Team Event Info (Total matches, cards, goals for team from event_team_values)
    $stmtEventVal = $conn->prepare("SELECT mn, gm, gk, red_cards, yellow_cards, green_cards 
                                    FROM event_team_values 
                                    WHERE event_id = ? AND team_id = ? AND sport_type = ? LIMIT 1");
    $stmtEventVal->execute([$eventId, $teamId, $sportType]);
    $eventVal = $stmtEventVal->fetch(PDO::FETCH_ASSOC);

    // Get Event name
    $stmtEvent = $conn->prepare("SELECT name FROM events WHERE id = ?");
    $stmtEvent->execute([$eventId]);
    $eventRecord = $stmtEvent->fetch(PDO::FETCH_ASSOC);
    $eventName = $eventRecord ? $eventRecord['name'] : 'Event';

    // 3. Get Players
    $stmtPlayers = $conn->prepare("SELECT id, name, photo, jersey_number, sport_type FROM players WHERE team_id = ? AND (sport_type = ? OR ? = '') ORDER BY jersey_number ASC, name ASC");
    $stmtPlayers->execute([$teamId, $sportType, $sportType]);
    $players = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);

    // 4. Get Player Goals
    $stmtGoals = $conn->prepare("SELECT g.player_id, COUNT(g.id) AS total_goals 
                                 FROM goals g 
                                 INNER JOIN challenges c ON g.match_id = c.id 
                                 WHERE c.event_id = ? AND g.team_id = ? AND c.status IN ('accepted', 'completed')
                                 GROUP BY g.player_id");
    $stmtGoals->execute([$eventId, $teamId]);
    $goalsMap = [];
    while ($row = $stmtGoals->fetch(PDO::FETCH_ASSOC)) {
        $goalsMap[$row['player_id']] = (int)$row['total_goals'];
    }

    // 5. Get Player Cards
    $stmtCards = $conn->prepare("SELECT player_id, yellow_cards, red_cards, green_cards 
                                 FROM player_event_cards 
                                 WHERE event_id = ? AND team_id = ? AND sport_type = ?");
    $stmtCards->execute([$eventId, $teamId, $sportType]);
    $cardsMap = [];
    while ($row = $stmtCards->fetch(PDO::FETCH_ASSOC)) {
        $cardsMap[$row['player_id']] = [
            'yellow' => (int)$row['yellow_cards'],
            'red'    => (int)$row['red_cards'],
            'green'  => (int)$row['green_cards']
        ];
    }

    // 6. Per-player match count from lineups (how many matches each player actually played in this event)
    $mcSql = "
        SELECT l.player_id, COUNT(DISTINCT l.match_id) AS match_count
        FROM lineups l
        INNER JOIN challenges c ON l.match_id = c.id
        WHERE c.event_id = ?
          AND l.team_id  = ?
          AND c.status IN ('accepted', 'completed')
    ";
    $mcParams = [$eventId, $teamId];
    if ($sportType !== '') {
        $mcSql   .= " AND c.sport_type = ?";
        $mcParams[] = $sportType;
    }
    $mcSql .= " GROUP BY l.player_id";
    $stmtMatchCount = $conn->prepare($mcSql);
    $stmtMatchCount->execute($mcParams);
    $matchCountMap = [];
    while ($row = $stmtMatchCount->fetch(PDO::FETCH_ASSOC)) {
        $matchCountMap[(int)$row['player_id']] = (int)$row['match_count'];
    }

    $playerList = [];
    $totalPlayerGoals = 0;
    $totalRed = 0;
    $totalYellow = 0;
    $totalGreen = 0;

    foreach ($players as $p) {
        $pId = (int)$p['id'];
        $goals = isset($goalsMap[$pId]) ? $goalsMap[$pId] : 0;
        $c = isset($cardsMap[$pId]) ? $cardsMap[$pId] : ['yellow' => 0, 'red' => 0, 'green' => 0];
        
        $totalPlayerGoals += $goals;
        $totalRed += $c['red'];
        $totalYellow += $c['yellow'];
        $totalGreen += $c['green'];

        $playerList[] = [
            'id' => $pId,
            'name' => $p['name'],
            'photo' => $p['photo'] ? $p['photo'] : 'default-player.jpg',
            'jersey_number' => $p['jersey_number'] ? $p['jersey_number'] : '-',
            'matches' => $matchCountMap[$pId] ?? 0, // Per-player match count from lineups
            'goals' => $goals,
            'red' => $c['red'],
            'yellow' => $c['yellow'],
            'green' => $c['green'],
            'play_time' => 0 // Placeholder
        ];
    }

    $teamStats = [
        'total_players' => count($playerList),
        'total_goals' => $eventVal ? $eventVal['gm'] : $totalPlayerGoals,
        'red' => $eventVal ? $eventVal['red_cards'] : $totalRed,
        'yellow' => $eventVal ? $eventVal['yellow_cards'] : $totalYellow,
        'green' => $eventVal ? $eventVal['green_cards'] : $totalGreen,
        'pt' => 0,
        'matches' => $eventVal ? $eventVal['mn'] : 0
    ];

    echo json_encode([
        'success' => true,
        'team' => [
            'id' => $team['id'],
            'name' => $team['name'],
            'logo' => $team['logo'] ? $team['logo'] : 'default-team.png',
        ],
        'event_name' => $eventName,
        'category' => $sportType,
        'stats' => $teamStats,
        'players' => $playerList
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
