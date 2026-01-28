<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

$challengeId = (int)$_GET['id'];
$challenge = getChallengeById($challengeId);

if (!$challenge) {
    echo json_encode(['success' => false, 'message' => 'Match not found']);
    exit;
}

// Get goals
$goalsRaw = getMatchGoals($challengeId);
$goals = [];
foreach ($goalsRaw as $g) {
    $goals[] = [
        'player' => $g['player_name'],
        'number' => $g['jersey_number'],
        'time' => $g['minute'] . '"',
        'team' => ($g['team_id'] == $challenge['challenger_id']) ? 'team1' : 'team2'
    ];
}

// Get lineups
$lineupsRaw = getMatchLineups($challengeId);
$lineups = [
    'team1' => [],
    'team2' => []
];

foreach ($lineupsRaw as $l) {
    $player = [
        'id' => $l['player_id'],
        'name' => $l['player_name'],
        'number' => $l['jersey_number'],
        'photo' => $l['player_photo'] ?: 'default-player.jpg'
    ];
    
    if ($l['team_id'] == $challenge['challenger_id']) {
        $lineups['team1'][] = $player;
    } else {
        $lineups['team2'][] = $player;
    }
}

// Timeline construction (basic version based on goals)
$timeline = [
    'Babak 1' => [],
    'Babak 2' => []
];

foreach ($goals as $g) {
    $minute = (int)str_replace('"', '', $g['time']);
    $half = ($minute <= 30) ? 'Babak 1' : 'Babak 2'; // Simple logic for futsal halves
    $timeline[$half][] = [
        'time' => $g['time'],
        'player' => $g['player'],
        'number' => $g['number'],
        'type' => 'goal'
    ];
}

$response = [
    'success' => true,
    'data' => [
        'id' => $challenge['id'],
        'title' => $challenge['challenger_name'] . ' vs ' . $challenge['opponent_name'],
        'team1' => $challenge['challenger_name'],
        'team1_logo' => $challenge['challenger_logo'],
        'team2' => $challenge['opponent_name'],
        'team2_logo' => $challenge['opponent_logo'],
        'score' => ($challenge['match_status'] == 'completed') ? $challenge['challenger_score'] . '-' . $challenge['opponent_score'] : 'VS',
        'date' => formatDate($challenge['challenge_date']),
        'time' => date('H:i', strtotime($challenge['challenge_date'])),
        'location' => $challenge['venue_name'] ?: 'Unknown Venue',
        'event' => $challenge['sport_type'], // Using sport_type as event name mapping
        'round' => $challenge['challenge_code'], // Using challenge_code as round info mapping
        'status' => $challenge['match_status'],
        'goals' => $goals,
        'timeline' => $timeline,
        'lineups' => $lineups,
        'jerseyInfo' => [
            'team1' => 'Jersey Home',
            'team2' => 'Jersey Away'
        ]
    ]
];

echo json_encode($response);
exit;
