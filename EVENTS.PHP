<?php
$hideNavbars = true;
require_once 'includes/header.php';

$eventId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$conn = $db->getConnection();

function hasColumnMySQLi($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
    return $result && $result->num_rows > 0;
}
function hasTableMySQLi($conn, $table) {
    $table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $result && $result->num_rows > 0;
}

function normalizeMatchTokensPublic($raw) {
    $normalized = strtoupper(trim((string) $raw));
    if ($normalized === '') return [];

    $normalized = str_replace(["\r", "\n", ";", "|", "/", "\\"], ',', $normalized);
    $normalized = preg_replace('/\s+/', ',', $normalized);
    $parts = array_filter(array_map('trim', explode(',', $normalized)), static fn($v) => $v !== '');

    $mapped = [];
    foreach ($parts as $part) {
        $token = $part;
        if ($token === 'M' || $token === 'WIN') $token = 'W';
        if ($token === 'MP' || $token === 'WINP') $token = 'WP';
        if ($token === 'S' || $token === 'DRAW') $token = 'D';
        if ($token === 'KP' || $token === 'LOSEP') $token = 'LP';
        if ($token === 'K' || $token === 'LOSE') $token = 'L';
        if (in_array($token, ['W', 'WP', 'D', 'LP', 'L'], true)) {
            $mapped[] = $token;
        }
    }
    return $mapped;
}

function renderMatchHistoryBadgesPublic($history) {
    $tokens = normalizeMatchTokensPublic($history);
    if (empty($tokens)) {
        return '-';
    }

    $html = '<div class="match-seq">';
    foreach ($tokens as $token) {
        $cls = 'draw';
        if ($token === 'W' || $token === 'WP') $cls = 'win';
        if ($token === 'L' || $token === 'LP') $cls = 'lose';
        $html .= '<span class="match-pill ' . $cls . '">' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $html .= '</div>';
    return $html;
}

function resolveMatchOutcomePublic($team1, $team2, $score1, $score2) {
    $team1 = (int) $team1;
    $team2 = (int) $team2;
    if ($team1 <= 0 || $team2 <= 0) {
        return ['winner' => 0, 'loser' => 0, 'status' => 'pending'];
    }
    if ($score1 === null || $score2 === null) {
        return ['winner' => 0, 'loser' => 0, 'status' => 'pending'];
    }
    $score1 = (int) $score1;
    $score2 = (int) $score2;
    if ($score1 === $score2) {
        return ['winner' => 0, 'loser' => 0, 'status' => 'tie'];
    }
    if ($score1 > $score2) {
        return ['winner' => $team1, 'loser' => $team2, 'status' => 'done'];
    }
    return ['winner' => $team2, 'loser' => $team1, 'status' => 'done'];
}

function renderBracketTeamLabelPublic($teamMap, $teamLogoMap, $teamId) {
    $teamId = (int) $teamId;
    if ($teamId <= 0) {
        return '<span class="evb-team"><span class="evb-team-logo evb-team-logo-placeholder"></span><span class="evb-team-name">-</span></span>';
    }

    $name = (string) ($teamMap[$teamId] ?? ('Team #' . $teamId));
    $logoFile = trim((string) ($teamLogoMap[$teamId] ?? ''));
    $logoPath = SITE_URL . '/images/teams/default-team.png';
    if ($logoFile !== '') {
        $logoPath = SITE_URL . '/images/teams/' . ltrim($logoFile, '/');
    }

    return '<span class="evb-team"><img class="evb-team-logo" src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" onerror="this.onerror=null;this.src=\'' . htmlspecialchars(SITE_URL . '/images/teams/default-team.png', ENT_QUOTES, 'UTF-8') . '\'"><span class="evb-team-name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span></span>';
}

$hasIsActive = hasColumnMySQLi($conn, 'events', 'is_active');
$events = [];
$event = null;
$eventParticipantGroups = [];
$eventParticipantTotal = 0;
$eventDataReady = false;
$eventStandings = [];
$eventStandingsByCategory = [];
$eventPlayerCards = [];
$eventPlayerCardsByCategory = [];
$hasEventValueTables = false;
$hasEventBracketTable = false;
$eventBracketViews = [];
$eventTopScorersByCategory = [];
$eventTopScorersLiga = [];

if ($eventId > 0) {
    $sql = "SELECT * FROM events WHERE id = ?";
    if ($hasIsActive) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();

    if (!$event) {
        header('Location: ' . SITE_URL . '/EVENTS.PHP');
        exit;
    }
    $pageTitle = $event['name'];
} else {
    $sql = "SELECT * FROM events";
    if ($hasIsActive) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY start_date DESC, created_at DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
    $pageTitle = "Events";
}

if ($eventId > 0 && !empty($event)) {
    $hasChallengeEventId = hasColumnMySQLi($conn, 'challenges', 'event_id');
    if ($hasChallengeEventId) {
        $categorySql = "SELECT sport_type AS category_name, COUNT(*) AS total_matches
                        FROM challenges
                        WHERE event_id = ?
                          AND status IN ('accepted', 'completed')
                          AND sport_type IS NOT NULL
                          AND sport_type <> ''
                        GROUP BY sport_type
                        ORDER BY sport_type ASC";
        $categoryStmt = $conn->prepare($categorySql);
        $categoryStmt->bind_param("i", $eventId);
        $categoryStmt->execute();
        $categoryResult = $categoryStmt->get_result();

        $participantTotalRows = 0;
        while ($categoryRow = $categoryResult->fetch_assoc()) {
            $categoryName = trim((string) ($categoryRow['category_name'] ?? ''));
            if ($categoryName === '') {
                continue;
            }

            $teamSql = "SELECT t.id, t.name, t.logo
                        FROM teams t
                        INNER JOIN (
                            SELECT challenger_id AS team_id
                            FROM challenges
                            WHERE event_id = ? AND sport_type = ? AND status IN ('accepted', 'completed')
                            UNION
                            SELECT opponent_id AS team_id
                            FROM challenges
                            WHERE event_id = ? AND sport_type = ? AND status IN ('accepted', 'completed')
                        ) participant ON participant.team_id = t.id
                        ORDER BY t.name ASC";
            $teamStmt = $conn->prepare($teamSql);
            $teamStmt->bind_param("isis", $eventId, $categoryName, $eventId, $categoryName);
            $teamStmt->execute();
            $teamResult = $teamStmt->get_result();

            $teams = [];
            while ($teamRow = $teamResult->fetch_assoc()) {
                $teams[] = $teamRow;
            }
            $participantTotalRows += count($teams);

            $eventParticipantGroups[] = [
                'category_name' => $categoryName,
                'total_matches' => (int) ($categoryRow['total_matches'] ?? 0),
                'teams' => $teams
            ];
        }

        $eventParticipantTotal = $participantTotalRows;
        $eventDataReady = true;

        // Fetch Top Scorers per Category
        $topScorerSql = "SELECT g.player_id, p.name AS player_name, p.photo AS player_photo,
                                t.name AS team_name, t.logo AS team_logo, c.sport_type AS category_name,
                                COUNT(g.id) AS total_goals
                         FROM goals g
                         INNER JOIN challenges c ON g.match_id = c.id
                         INNER JOIN players p ON g.player_id = p.id
                         INNER JOIN teams t ON g.team_id = t.id
                         WHERE c.event_id = ? AND c.status IN ('accepted', 'completed')
                         GROUP BY g.player_id, c.sport_type
                         ORDER BY total_goals DESC";
        $tsStmt = $conn->prepare($topScorerSql);
        $tsStmt->bind_param("i", $eventId);
        $tsStmt->execute();
        $tsResult = $tsStmt->get_result();

        while ($tsRow = $tsResult->fetch_assoc()) {
            $category = trim((string)($tsRow['category_name'] ?? ''));
            if (!isset($eventTopScorersByCategory[$category])) {
                $eventTopScorersByCategory[$category] = [];
            }
            $eventTopScorersByCategory[$category][] = $tsRow;
        }

        // Fetch Liga Top Scorers
        $topLigaSql = "SELECT g.player_id, p.name AS player_name, p.photo AS player_photo,
                              t.name AS team_name, t.logo AS team_logo,
                              COUNT(g.id) AS total_goals
                       FROM goals g
                       INNER JOIN challenges c ON g.match_id = c.id
                       INNER JOIN players p ON g.player_id = p.id
                       INNER JOIN teams t ON g.team_id = t.id
                       WHERE c.event_id = ? AND c.status IN ('accepted', 'completed')
                       GROUP BY g.player_id
                       ORDER BY total_goals DESC
                       LIMIT 10";
        $tsLigaStmt = $conn->prepare($topLigaSql);
        $tsLigaStmt->bind_param("i", $eventId);
        $tsLigaStmt->execute();
        $tsLigaResult = $tsLigaStmt->get_result();
        while ($tsLigaRow = $tsLigaResult->fetch_assoc()) {
            $eventTopScorersLiga[] = $tsLigaRow;
        }
    }

    $hasEventBracketTable = hasTableMySQLi($conn, 'event_brackets');
    if ($hasEventBracketTable && !empty($eventParticipantGroups)) {
        $categoryTeamMapForBracket = [];
        $categoryLogoMapForBracket = [];
        $categoryOrderForBracket = [];
        foreach ($eventParticipantGroups as $group) {
            $categoryName = trim((string) ($group['category_name'] ?? ''));
            if ($categoryName === '') {
                continue;
            }
            if (!isset($categoryTeamMapForBracket[$categoryName])) {
                $categoryTeamMapForBracket[$categoryName] = [];
                $categoryLogoMapForBracket[$categoryName] = [];
                $categoryOrderForBracket[] = $categoryName;
            }
            foreach (($group['teams'] ?? []) as $team) {
                $teamId = (int) ($team['id'] ?? 0);
                if ($teamId <= 0) continue;
                $categoryTeamMapForBracket[$categoryName][$teamId] = (string) ($team['name'] ?? '-');
                $categoryLogoMapForBracket[$categoryName][$teamId] = (string) ($team['logo'] ?? '');
            }
        }

        $bracketByCategory = [];
        $bracketStmt = $conn->prepare("SELECT * FROM event_brackets WHERE event_id = ?");
        $bracketStmt->bind_param("i", $eventId);
        $bracketStmt->execute();
        $bracketResult = $bracketStmt->get_result();
        while ($bracketRow = $bracketResult->fetch_assoc()) {
            $categoryName = trim((string) ($bracketRow['sport_type'] ?? ''));
            if ($categoryName === '') continue;
            $bracketByCategory[$categoryName] = $bracketRow;
        }

        foreach ($categoryOrderForBracket as $categoryName) {
            $row = $bracketByCategory[$categoryName] ?? null;
            $sf1Team1 = (int) ($row['sf1_team1_id'] ?? 0);
            $sf1Team2 = (int) ($row['sf1_team2_id'] ?? 0);
            $sf2Team1 = (int) ($row['sf2_team1_id'] ?? 0);
            $sf2Team2 = (int) ($row['sf2_team2_id'] ?? 0);
            $sf1Score1 = isset($row['sf1_score1']) ? (int) $row['sf1_score1'] : null;
            $sf1Score2 = isset($row['sf1_score2']) ? (int) $row['sf1_score2'] : null;
            $sf2Score1 = isset($row['sf2_score1']) ? (int) $row['sf2_score1'] : null;
            $sf2Score2 = isset($row['sf2_score2']) ? (int) $row['sf2_score2'] : null;
            $finalScore1 = isset($row['final_score1']) ? (int) $row['final_score1'] : null;
            $finalScore2 = isset($row['final_score2']) ? (int) $row['final_score2'] : null;
            $thirdScore1 = isset($row['third_score1']) ? (int) $row['third_score1'] : null;
            $thirdScore2 = isset($row['third_score2']) ? (int) $row['third_score2'] : null;

            $sf1Outcome = resolveMatchOutcomePublic($sf1Team1, $sf1Team2, $sf1Score1, $sf1Score2);
            $sf2Outcome = resolveMatchOutcomePublic($sf2Team1, $sf2Team2, $sf2Score1, $sf2Score2);

            $finalTeam1 = (int) ($sf1Outcome['winner'] ?? 0);
            $finalTeam2 = (int) ($sf2Outcome['winner'] ?? 0);
            $thirdTeam1 = (int) ($sf1Outcome['loser'] ?? 0);
            $thirdTeam2 = (int) ($sf2Outcome['loser'] ?? 0);

            $finalOutcome = resolveMatchOutcomePublic($finalTeam1, $finalTeam2, $finalScore1, $finalScore2);
            $thirdOutcome = resolveMatchOutcomePublic($thirdTeam1, $thirdTeam2, $thirdScore1, $thirdScore2);

            $eventBracketViews[] = [
                'category' => $categoryName,
                'team_map' => $categoryTeamMapForBracket[$categoryName] ?? [],
                'team_logo_map' => $categoryLogoMapForBracket[$categoryName] ?? [],
                'sf1_team1' => $sf1Team1,
                'sf1_team2' => $sf1Team2,
                'sf2_team1' => $sf2Team1,
                'sf2_team2' => $sf2Team2,
                'sf1_score1' => $sf1Score1,
                'sf1_score2' => $sf1Score2,
                'sf2_score1' => $sf2Score1,
                'sf2_score2' => $sf2Score2,
                'final_team1' => $finalTeam1,
                'final_team2' => $finalTeam2,
                'final_score1' => $finalScore1,
                'final_score2' => $finalScore2,
                'third_team1' => $thirdTeam1,
                'third_team2' => $thirdTeam2,
                'third_score1' => $thirdScore1,
                'third_score2' => $thirdScore2,
                'sf1_outcome' => $sf1Outcome,
                'sf2_outcome' => $sf2Outcome,
                'final_outcome' => $finalOutcome,
                'third_outcome' => $thirdOutcome,
            ];
        }
    }

    $hasEventValueTables = hasTableMySQLi($conn, 'event_team_values') && hasTableMySQLi($conn, 'player_event_cards');
    if ($hasEventValueTables) {
        $categoryTeamMap = [];
        $categoryOrder = [];
        foreach ($eventParticipantGroups as $group) {
            $categoryName = trim((string) ($group['category_name'] ?? ''));
            if ($categoryName === '') {
                continue;
            }
            if (!isset($categoryTeamMap[$categoryName])) {
                $categoryTeamMap[$categoryName] = [];
                $categoryOrder[] = $categoryName;
            }
            foreach (($group['teams'] ?? []) as $team) {
                $teamId = (int) ($team['id'] ?? 0);
                if ($teamId <= 0) {
                    continue;
                }
                $categoryTeamMap[$categoryName][$teamId] = (string) ($team['name'] ?? '-');
            }
        }

        $standingSql = "SELECT etv.*, t.name AS team_name
                        FROM event_team_values etv
                        INNER JOIN teams t ON t.id = etv.team_id
                        WHERE etv.event_id = ?
                        ORDER BY etv.sport_type ASC, etv.kls ASC, etv.points DESC, etv.sg DESC, etv.gm DESC, t.name ASC";
        $standingStmt = $conn->prepare($standingSql);
        $standingStmt->bind_param("i", $eventId);
        $standingStmt->execute();
        $standingResult = $standingStmt->get_result();
        while ($standingRow = $standingResult->fetch_assoc()) {
            $eventStandings[] = $standingRow;
        }

        $standingsFromDbByCategory = [];
        foreach ($eventStandings as $standingRow) {
            $categoryName = trim((string) ($standingRow['sport_type'] ?? ''));
            if ($categoryName === '') {
                $categoryName = 'Lainnya';
            }
            if (!isset($standingsFromDbByCategory[$categoryName])) {
                $standingsFromDbByCategory[$categoryName] = [];
            }
            $standingsFromDbByCategory[$categoryName][] = $standingRow;
        }

        if (!empty($categoryOrder)) {
            foreach ($categoryOrder as $categoryName) {
                $rowsByTeamId = [];
                foreach (($standingsFromDbByCategory[$categoryName] ?? []) as $row) {
                    $rowsByTeamId[(int) ($row['team_id'] ?? 0)] = $row;
                }

                foreach (($categoryTeamMap[$categoryName] ?? []) as $teamId => $teamName) {
                    if ($teamId <= 0 || isset($rowsByTeamId[$teamId])) {
                        continue;
                    }
                    $rowsByTeamId[$teamId] = [
                        'team_id' => $teamId,
                        'team_name' => $teamName,
                        'mn' => 0,
                        'm' => 0,
                        'mp' => 0,
                        's' => 0,
                        'kp' => 0,
                        'k' => 0,
                        'gm' => 0,
                        'gk' => 0,
                        'sg' => 0,
                        'points' => 0,
                        'kls' => 0,
                        'red_cards' => 0,
                        'yellow_cards' => 0,
                        'green_cards' => 0,
                    ];
                }

                $rows = array_values($rowsByTeamId);
                usort($rows, static function (array $a, array $b): int {
                    if ((int) ($a['points'] ?? 0) !== (int) ($b['points'] ?? 0)) {
                        return (int) ($b['points'] ?? 0) <=> (int) ($a['points'] ?? 0);
                    }
                    if ((int) ($a['sg'] ?? 0) !== (int) ($b['sg'] ?? 0)) {
                        return (int) ($b['sg'] ?? 0) <=> (int) ($a['sg'] ?? 0);
                    }
                    if ((int) ($a['gm'] ?? 0) !== (int) ($b['gm'] ?? 0)) {
                        return (int) ($b['gm'] ?? 0) <=> (int) ($a['gm'] ?? 0);
                    }
                    return strcmp((string) ($a['team_name'] ?? ''), (string) ($b['team_name'] ?? ''));
                });
                foreach ($rows as $idx => &$row) {
                    $row['display_kls'] = $idx + 1;
                }
                unset($row);

                $eventStandingsByCategory[$categoryName] = $rows;
            }

            foreach ($standingsFromDbByCategory as $categoryName => $rows) {
                if (isset($eventStandingsByCategory[$categoryName])) {
                    continue;
                }
                usort($rows, static function (array $a, array $b): int {
                    if ((int) ($a['points'] ?? 0) !== (int) ($b['points'] ?? 0)) {
                        return (int) ($b['points'] ?? 0) <=> (int) ($a['points'] ?? 0);
                    }
                    if ((int) ($a['sg'] ?? 0) !== (int) ($b['sg'] ?? 0)) {
                        return (int) ($b['sg'] ?? 0) <=> (int) ($a['sg'] ?? 0);
                    }
                    if ((int) ($a['gm'] ?? 0) !== (int) ($b['gm'] ?? 0)) {
                        return (int) ($b['gm'] ?? 0) <=> (int) ($a['gm'] ?? 0);
                    }
                    return strcmp((string) ($a['team_name'] ?? ''), (string) ($b['team_name'] ?? ''));
                });
                foreach ($rows as $idx => &$row) {
                    $row['display_kls'] = $idx + 1;
                }
                unset($row);
                $eventStandingsByCategory[$categoryName] = $rows;
            }
        } else {
            foreach ($standingsFromDbByCategory as $categoryName => $rows) {
                usort($rows, static function (array $a, array $b): int {
                    if ((int) ($a['points'] ?? 0) !== (int) ($b['points'] ?? 0)) {
                        return (int) ($b['points'] ?? 0) <=> (int) ($a['points'] ?? 0);
                    }
                    if ((int) ($a['sg'] ?? 0) !== (int) ($b['sg'] ?? 0)) {
                        return (int) ($b['sg'] ?? 0) <=> (int) ($a['sg'] ?? 0);
                    }
                    if ((int) ($a['gm'] ?? 0) !== (int) ($b['gm'] ?? 0)) {
                        return (int) ($b['gm'] ?? 0) <=> (int) ($a['gm'] ?? 0);
                    }
                    return strcmp((string) ($a['team_name'] ?? ''), (string) ($b['team_name'] ?? ''));
                });
                foreach ($rows as $idx => &$row) {
                    $row['display_kls'] = $idx + 1;
                }
                unset($row);
                $eventStandingsByCategory[$categoryName] = $rows;
            }
        }

        $cardSql = "SELECT pec.*, p.name AS player_name, t.name AS team_name
                    FROM player_event_cards pec
                    INNER JOIN players p ON p.id = pec.player_id
                    INNER JOIN teams t ON t.id = pec.team_id
                    WHERE pec.event_id = ?
                    ORDER BY
                        CASE WHEN pec.suspension_until IS NOT NULL AND pec.suspension_until >= CURDATE() THEN 0 ELSE 1 END ASC,
                        pec.updated_at DESC";
        $cardStmt = $conn->prepare($cardSql);
        $cardStmt->bind_param("i", $eventId);
        $cardStmt->execute();
        $cardResult = $cardStmt->get_result();
        while ($cardRow = $cardResult->fetch_assoc()) {
            $eventPlayerCards[] = $cardRow;
        }

        foreach ($eventPlayerCards as $cardRow) {
            $categoryName = trim((string) ($cardRow['sport_type'] ?? ''));
            if ($categoryName === '') {
                $categoryName = 'Lainnya';
            }
            if (!isset($eventPlayerCardsByCategory[$categoryName])) {
                $eventPlayerCardsByCategory[$categoryName] = [];
            }
            $eventPlayerCardsByCategory[$categoryName][] = $cardRow;
        }

        if (!empty($categoryOrder)) {
            $orderedPlayerCards = [];
            foreach ($categoryOrder as $categoryName) {
                $orderedPlayerCards[$categoryName] = $eventPlayerCardsByCategory[$categoryName] ?? [];
            }
            foreach ($eventPlayerCardsByCategory as $categoryName => $rows) {
                if (!isset($orderedPlayerCards[$categoryName])) {
                    $orderedPlayerCards[$categoryName] = $rows;
                }
            }
            $eventPlayerCardsByCategory = $orderedPlayerCards;
        } else {
            ksort($eventPlayerCardsByCategory);
        }
    }
}
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/index_redesign.css?v=<?php echo time(); ?>">
<style>
.event-poster-profile {
    width: 160px;
    height: 160px;
    border-radius: 22px;
    object-fit: contain;
    background: #f8fafc;
    border: 4px solid #fff;
    box-shadow: 0 10px 25px rgba(15, 39, 68, 0.14);
    padding: 6px;
}
.event-card-image {
    width: 100%;
    height: 170px;
    object-fit: contain;
    background: #f8fafc;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    padding: 6px;
}
.event-chip {
    display: inline-block;
    margin-top: 8px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}
.event-chip.open { background: rgba(16, 185, 129, 0.12); color: #047857; }
.event-chip.closed { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }
.event-selector-search {
    display: flex;
    gap: 10px;
    align-items: center;
}
.event-selector-select {
    width: 100%;
    height: 44px;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    padding: 0 12px;
    font-size: 14px;
    color: #0f172a;
    background: #fff;
    margin-bottom: 10px;
}
.event-selector-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}
.event-selector-search input {
    flex: 1;
    height: 44px;
    border: 1px solid #cbd5e1;
    border-radius: 12px;
    padding: 0 12px;
    font-size: 14px;
    color: #0f172a;
    background: #fff;
}
.event-selector-search input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}
.event-desc {
    margin-top: 18px;
    padding: 18px;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    line-height: 1.65;
}
.event-participant-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 18px;
    margin-top: 16px;
}
.event-participant-card {
    border: 1px solid #dbe6f3;
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
}
.event-participant-head {
    background: #0f172a;
    color: #fff;
    padding: 10px 12px;
    font-weight: 700;
    font-size: 14px;
}
.event-participant-table {
    width: 100%;
    border-collapse: collapse;
}
.event-participant-table th,
.event-participant-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #edf2f7;
    font-size: 13px;
}
.event-participant-table th {
    text-align: left;
    background: #f8fafc;
}
.event-team-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.event-team-logo {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid #dbe6f3;
}
.event-team-info-link {
    color: #2563eb;
    text-decoration: none;
}
.event-standing-wrap { overflow-x: auto; margin-top: 14px; border: 2px solid #c8d7eb; border-radius: 12px; }
.event-standing-table { width: 100%; border-collapse: collapse; min-width: 980px; }
.event-standing-table th, .event-standing-table td { padding: 10px 10px; border-bottom: 2px solid #d9e3f1; font-size: 13px; text-align: center; }
.event-standing-table thead th { background: #0f172a; color: #fff; border-bottom: 2px solid #94a3b8; }
.event-standing-table th:nth-child(2), .event-standing-table td:nth-child(2) { text-align: left; }
.event-card-note { margin: 8px 0 0 0; color: #475569; font-size: 12px; }
.card-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 22px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    padding: 0 8px;
    border: 1px solid transparent;
}
.card-badge.red { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
.card-badge.yellow { background: #fef9c3; color: #a16207; border-color: #fde68a; }
.card-badge.green { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
.match-seq {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}
.match-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 20px;
    border-radius: 5px;
    padding: 0 5px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
}
.match-pill.win { background: #16a34a; }
.match-pill.lose { background: #dc2626; }
.match-pill.draw { background: #d97706; }
.suspend-pill {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}
.suspend-pill.active { background: rgba(239, 68, 68, 0.14); color: #991b1b; }
.suspend-pill.done { background: rgba(16, 185, 129, 0.14); color: #065f46; }
.event-section-divider {
    margin: 34px 0 22px;
    border-top: 7px dashed #9fb7d8;
    padding-top: 20px;
    padding-bottom: 12px;
}
.event-bracket-wrap {
    margin-top: 14px;
    background: radial-gradient(circle at 20% 0%, #f8fbff 0%, #eef4fb 48%, #e9f1f9 100%);
    border: 1px solid #dbe5f3;
    border-radius: 16px;
    padding: 18px;
}
.event-bracket-title { margin: 0 0 8px 0; color: #1e293b; }
.event-bracket-note { margin: 0 0 10px 0; color: #64748b; font-size: 12px; }
.evb-canvas {
    position: relative;
    min-height: 540px;
}
.evb-match {
    position: absolute;
    width: 260px;
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 10px 20px rgba(15,39,68,.08);
}
.evb-row {
    display: grid;
    grid-template-columns: 1fr 40px;
    border-bottom: 1px solid #e5e7eb;
    transition: background .2s ease;
}
.evb-row:last-child { border-bottom: none; }
.evb-row.win {
    background: linear-gradient(90deg,#0ea5e9 0%, #2563eb 100%);
    color: #fff;
}
.evb-row.lose { background: #eef2f7; }
.evb-row.linked-highlight {
    background: linear-gradient(90deg,#166534 0%, #15803d 45%, #16a34a 100%) !important;
    color: #fff !important;
}
.evb-name { padding: 8px 10px; font-size: 14px; }
.evb-score { text-align: center; padding: 8px 0; font-weight: 800; font-size: 20px; }
.evb-score.green { color: #16a34a; }
.evb-score.red { color: #b91c1c; }
.evb-row.win .evb-score.green,
.evb-row.linked-highlight .evb-score { color: #fff !important; }
.evb-rank {
    position: absolute;
    right: -62px;
    width: 52px;
    text-align: center;
    color: #fff;
    font-size: 12px;
    font-weight: 800;
    border-radius: 6px;
    padding: 4px 0;
    box-shadow: 0 8px 14px rgba(15,39,68,.18);
}
.evb-rank.first { top: 8px; background: #2563eb; }
.evb-rank.second { top: 44px; background: #334155; }
.evb-rank.third { top: 8px; background: #0f766e; }
.evb-rank.fourth { top: 44px; background: #7c2d12; }
.evb-line {
    position: absolute;
    border-color: #cbd5e1;
    border-style: solid;
    border-width: 0;
}
.evb-line.gold { border-color: #2563eb; }
.evb-team {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.evb-team-logo {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid rgba(148,163,184,.7);
    background: #fff;
    flex-shrink: 0;
}
.evb-team-logo-placeholder {
    display: inline-block;
    background: #cbd5e1;
}
@media (max-width: 1100px) {
    .evb-canvas { min-height: unset; display: grid; gap: 12px; }
    .evb-match { position: static; width: 100%; }
    .evb-line, .evb-rank { display: none; }
}

.top-scorer-grid {
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: visible;
    gap: 14px;
    padding-top: 10px;
    padding-bottom: 12px;
    position: relative;
}
.top-scorer-grid::-webkit-scrollbar {
    height: 6px;
}
.top-scorer-grid::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.top-scorer-card {
    flex: 0 0 160px;
    background: #e9eff5;
    border: 3px solid #c0d0e4;
    border-radius: 12px;
    padding: 12px;
    text-align: center;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
    position: relative;
    overflow: hidden;
    transition: transform .24s ease, box-shadow .24s ease, border-color .24s ease, background .24s ease;
}
.top-scorer-card::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(120deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.45) 45%, rgba(255,255,255,0) 70%);
    transform: translateX(-130%);
    opacity: 0;
    pointer-events: none;
}
.top-scorer-card:hover {
    transform: translateY(-8px);
    border-color: #60a5fa;
    background: #f4f8ff;
    box-shadow: 0 14px 26px rgba(15, 39, 68, 0.16);
    z-index: 3;
}
.top-scorer-card:hover::after {
    opacity: 1;
    animation: scorerShine .72s ease;
}
.top-scorer-photo {
    width: 100%;
    height: 140px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 10px;
    background: #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: transform .24s ease, filter .24s ease, box-shadow .24s ease;
}
.top-scorer-card:hover .top-scorer-photo {
    transform: scale(1.04);
    filter: saturate(1.08);
    box-shadow: 0 10px 20px rgba(15, 39, 68, 0.16);
}
.top-scorer-name {
    font-size: 13px;
    font-weight: 600;
    color: #334155;
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: color .2s ease;
}
.top-scorer-card:hover .top-scorer-name {
    color: #0f2744;
}
.top-scorer-team {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 11px;
    color: #64748b;
    margin-bottom: 8px;
}
.top-scorer-team-logo {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    object-fit: cover;
}
.top-scorer-goals {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 16px;
    font-weight: 700;
    color: #475569;
    transition: transform .22s ease, color .22s ease;
}
.top-scorer-card:hover .top-scorer-goals {
    transform: scale(1.05);
    color: #1d4ed8;
}
@keyframes scorerShine {
    from { transform: translateX(-130%); }
    to { transform: translateX(130%); }
}
</style>

<div class="dashboard-wrapper">
    <header class="mobile-dashboard-header">
        <div class="mobile-logo">
            <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Buka/Tutup Sidebar" aria-controls="sidebar" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <aside class="sidebar" id="sidebar" aria-hidden="true">
        <div class="sidebar-logo">
            <a href="<?php echo SITE_URL; ?>">
                <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo SITE_URL; ?>"><i class="fas fa-home"></i> <span>BERANDA</span></a>
            <a href="EVENTS.PHP" class="active"><i class="fas fa-calendar-alt"></i> <span>EVENT</span></a>
            <a href="all.php"><i class="fas fa-trophy"></i> <span>CHALLENGE</span></a>
            <a href="team.php"><i class="fas fa-users"></i> <span>TEAM</span></a>
            <div class="nav-item-dropdown">
                <a href="#" class="nav-has-dropdown" onclick="toggleDropdown(this, 'playerDropdown'); return false;">
                    <div class="nav-link-content">
                        <i class="fas fa-users"></i> <span>PEMAIN</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <div id="playerDropdown" class="sidebar-dropdown">
                    <a href="player.php">Pemain</a>
                    <a href="staff.php">Staf Team</a>
                </div>
            </div>
            <a href="news.php"><i class="fas fa-newspaper"></i> <span>BERITA</span></a>
            <a href="bpjs.php"><i class="fas fa-shield-alt"></i> <span>BPJSTK</span></a>
            <a href="contact.php"><i class="fas fa-envelope"></i> <span>KONTAK</span></a>

            <div class="sidebar-divider" style="margin: 15px 0; border-top: 1px solid rgba(255,255,255,0.1);"></div>

            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
                <a href="<?php echo ($_SESSION['admin_role'] === 'pelatih' ? SITE_URL.'/pelatih/dashboard.php' : SITE_URL.'/admin/dashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i> <span>DASHBOARD</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/logout.php" style="color: #e74c3c;">
                    <i class="fas fa-sign-out-alt"></i> <span>KELUAR</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn-login-sidebar">
                    <i class="fas fa-sign-in-alt"></i> <span>MASUK</span>
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <div class="main-content-dashboard">
        <header class="dashboard-header dashboard-header-home dashboard-header-team">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">ALVETRIX</div>
                    <h1>EVENT</h1>
                    <p class="header-subtitle">
                        <?php echo $eventId > 0
                            ? 'Detail lengkap event yang didaftarkan melalui panel admin.'
                            : 'Jelajahi event terbaru dari data yang telah diinput admin.'; ?>
                    </p>
                </div>
                <div class="header-actions">
                    <?php if ($eventId > 0): ?>
                        <a href="event.php" class="btn-primary"><i class="fas fa-sitemap"></i> Event Kategori</a>
                        <a href="EVENTS.PHP" class="btn-secondary"><i class="fas fa-arrow-left"></i> Semua Event</a>
                        <a href="team.php" class="btn-primary"><i class="fas fa-users"></i> Lihat Team</a>
                    <?php else: ?>
                        <a href="event.php" class="btn-primary"><i class="fas fa-sitemap"></i> Event Kategori</a>
                        <a href="team.php" class="btn-secondary"><i class="fas fa-users"></i> Lihat Team</a>
                        <a href="player.php" class="btn-primary"><i class="fas fa-running"></i> Lihat Player</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="dashboard-body">
            <?php if ($eventId > 0): ?>
                <div class="container section-container section-elevated team-profile-section">
                    <div class="section-header">
                        <h2 class="section-title">PROFIL EVENT</h2>
                        <div class="section-tabs">
                            <span class="team-status-pill"><i class="fas fa-calendar-day"></i> <?php echo htmlspecialchars(formatDate($event['start_date'] ?? '')); ?></span>
                            <span class="team-status-pill"><i class="fas fa-calendar-check"></i> <?php echo htmlspecialchars(formatDate($event['end_date'] ?? '')); ?></span>
                            <span class="team-status-pill">
                                <i class="fas fa-toggle-on"></i>
                                <?php echo strtolower((string) ($event['registration_status'] ?? 'closed')) === 'open' ? 'Pendaftaran Open' : 'Pendaftaran Closed'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="team-profile-card">
                        <div class="team-profile-identity">
                            <div class="team-logo-shell" style="border-radius:26px;">
                                <?php if (!empty($event['image'])): ?>
                                    <img src="<?php echo SITE_URL; ?>/images/events/<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['name']); ?>" class="event-poster-profile" onerror="this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                                <?php else: ?>
                                    <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="<?php echo htmlspecialchars($event['name']); ?>" class="event-poster-profile">
                                <?php endif; ?>
                            </div>
                            <div class="team-profile-text">
                                <h2 class="team-name-display"><?php echo htmlspecialchars($event['name']); ?></h2>
                                <p class="team-subtitle"><?php echo !empty($event['category']) ? htmlspecialchars($event['category']) : 'Kategori belum diisi'; ?></p>
                                <p class="team-tagline">Lokasi: <?php echo !empty($event['location']) ? htmlspecialchars($event['location']) : '-'; ?></p>
                            </div>
                        </div>

                        <div class="team-meta-grid">
                            <div class="team-meta-item">
                                <div class="team-meta-label">Tanggal Mulai</div>
                                <div class="team-meta-value"><?php echo htmlspecialchars(formatDate($event['start_date'] ?? '')); ?></div>
                            </div>
                            <div class="team-meta-item">
                                <div class="team-meta-label">Tanggal Selesai</div>
                                <div class="team-meta-value"><?php echo htmlspecialchars(formatDate($event['end_date'] ?? '')); ?></div>
                            </div>
                            <div class="team-meta-item">
                                <div class="team-meta-label">Kontak</div>
                                <div class="team-meta-value"><?php echo !empty($event['contact']) ? htmlspecialchars($event['contact']) : '-'; ?></div>
                            </div>
                            <div class="team-meta-item">
                                <div class="team-meta-label">Status Pendaftaran</div>
                                <div class="team-meta-value">
                                    <span class="event-chip <?php echo strtolower((string) ($event['registration_status'] ?? 'closed')) === 'open' ? 'open' : 'closed'; ?>">
                                        <?php echo strtoupper((string) ($event['registration_status'] ?? 'closed')); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="event-desc">
                            <?php
                            $desc = trim((string) ($event['description'] ?? ''));
                            echo $desc !== '' ? nl2br(htmlspecialchars($desc)) : 'Belum ada deskripsi event.';
                            ?>
                        </div>

                        <div class="event-desc" id="eventParticipants">
                            <h3 style="margin:0 0 10px 0;">Peserta (<?php echo (int) $eventParticipantTotal; ?>)</h3>
                            <p style="margin:0 0 12px 0; color:#475569;">*klik icon info untuk melihat statistik team di kategori ini</p>

                            <?php if (!$eventDataReady): ?>
                                <p style="margin:0; color:#64748b;">Data peserta belum tersedia (kolom relasi event belum aktif).</p>
                            <?php elseif (empty($eventParticipantGroups)): ?>
                                <p style="margin:0; color:#64748b;">Belum ada challenge yang terhubung ke event ini.</p>
                            <?php else: ?>
                                <div class="event-participant-grid">
                                    <?php foreach ($eventParticipantGroups as $group): ?>
                                        <article class="event-participant-card">
                                            <div class="event-participant-head">
                                                <?php echo htmlspecialchars($group['category_name'] ?? '-'); ?>
                                            </div>
                                            <table class="event-participant-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width:44px;">No</th>
                                                        <th>Team</th>
                                                        <th style="width:44px;">Info</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($group['teams'])): ?>
                                                        <tr><td colspan="3" style="color:#64748b;">Belum ada team</td></tr>
                                                    <?php else: ?>
                                                        <?php foreach ($group['teams'] as $idx => $team): ?>
                                                            <?php
                                                            $teamId = (int) ($team['id'] ?? 0);
                                                            $teamLink = 'all.php?status=result&event=' . urlencode((string) ($group['category_name'] ?? '')) . '&team=' . urlencode((string) $teamId);
                                                            ?>
                                                            <tr>
                                                                <td><?php echo $idx + 1; ?></td>
                                                                <td>
                                                                    <div class="event-team-row">
                                                                        <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($team['logo'] ?? 'default-team.png'); ?>"
                                                                             alt="<?php echo htmlspecialchars($team['name'] ?? ''); ?>"
                                                                             class="event-team-logo"
                                                                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                                                        <span><?php echo htmlspecialchars($team['name'] ?? '-'); ?></span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <a class="event-team-info-link" href="<?php echo htmlspecialchars($teamLink); ?>" title="Lihat pertandingan team">
                                                                        <i class="fas fa-circle-info"></i>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="event-desc" id="eventStandings">
                            <h3 style="margin:0 0 8px 0;">Klasemen & Disiplin</h3>
                            <p style="margin:0 0 8px 0; color:#475569;">Poin: M=3, MP=2, KP=1, Seri=0, Kalah=0.</p>
                            <p class="event-card-note">Catatan kartu: hanya dihitung jumlahnya (tanpa poin).</p>
                            <p class="event-card-note">Aturan: 2x kartu kuning, pemain tidak boleh tanding 1 minggu lalu boleh lagi.</p>

                            <?php if (!$hasEventValueTables): ?>
                                <p style="margin:10px 0 0 0; color:#64748b;">Tabel nilai event belum tersedia. Jalankan migration untuk mulai input data dari admin.</p>
                            <?php elseif (empty($eventStandingsByCategory)): ?>
                                <p style="margin:10px 0 0 0; color:#64748b;">Belum ada data klasemen untuk event ini.</p>
                            <?php else: ?>
                                <?php foreach ($eventStandingsByCategory as $categoryName => $rows): ?>
                                    <h4 style="margin:18px 0 8px 0;">Klasemen <?php echo htmlspecialchars($categoryName); ?></h4>
                                    <div class="event-standing-wrap">
                                        <table class="event-standing-table">
                                            <thead>
                                                <tr>
                                                    <th>Team</th>
                                                    <th>MN</th>
                                                    <th>M</th>
                                                    <th>MP</th>
                                                    <th>S</th>
                                                    <th>KP</th>
                                                    <th>K</th>
                                                    <th>GM</th>
                                                    <th>GK</th>
                                                    <th>SG</th>
                                                    <th>P</th>
                                                    <th>KLS</th>
                                                    <th>Match</th>
                                                    <th>🟥</th>
                                                    <th>🟨</th>
                                                    <th>🟩</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($rows)): ?>
                                                    <tr>
                                                        <td colspan="16" style="text-align:left;">Belum ada data.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($rows as $standing): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($standing['team_name'] ?? '-'); ?></td>
                                                            <td><?php echo (int) ($standing['mn'] ?? 0); ?></td>
                                                            <td><?php echo (int) ($standing['m'] ?? 0); ?></td>
                                                            <td><?php echo (int) ($standing['mp'] ?? 0); ?></td>
                                                            <td><?php echo (int) ($standing['s'] ?? 0); ?></td>
                                                            <td><?php echo (int) ($standing['kp'] ?? 0); ?></td>
                                                            <td><?php echo (int) ($standing['k'] ?? 0); ?></td>
                                                            <td><?php echo (int) ($standing['gm'] ?? 0); ?></td>
                                                            <td><?php echo (int) ($standing['gk'] ?? 0); ?></td>
                                                            <td><?php echo (int) ($standing['sg'] ?? 0); ?></td>
                                                            <td><strong><?php echo (int) ($standing['points'] ?? 0); ?></strong></td>
                                                            <td><?php echo (int) ($standing['display_kls'] ?? 0); ?></td>
                                                            <td><?php echo renderMatchHistoryBadgesPublic($standing['match_history'] ?? ''); ?></td>
                                                            <td><span class="card-badge red"><?php echo (int) ($standing['red_cards'] ?? 0); ?></span></td>
                                                            <td><span class="card-badge yellow"><?php echo (int) ($standing['yellow_cards'] ?? 0); ?></span></td>
                                                            <td><span class="card-badge green"><?php echo (int) ($standing['green_cards'] ?? 0); ?></span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <?php if (!empty($eventTopScorersByCategory[$categoryName])): ?>
                                        <div style="margin-top:20px;">
                                            <div class="top-scorer-grid">
                                                <?php foreach ($eventTopScorersByCategory[$categoryName] as $scorer): ?>
                                                    <div class="top-scorer-card">
                                                        <img src="<?php echo SITE_URL; ?>/images/players/<?php echo htmlspecialchars($scorer['player_photo'] ?: 'default-player.jpg'); ?>" alt="Photo" class="top-scorer-photo" onerror="this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                                                        <div class="top-scorer-name" title="<?php echo htmlspecialchars($scorer['player_name']); ?>"><?php echo htmlspecialchars($scorer['player_name']); ?></div>
                                                        <div class="top-scorer-team" title="<?php echo htmlspecialchars($scorer['team_name']); ?>">
                                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($scorer['team_logo'] ?: 'default-team.png'); ?>" alt="Logo" class="top-scorer-team-logo" onerror="this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                                            <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($scorer['team_name']); ?></span>
                                                        </div>
                                                        <div class="top-scorer-goals">
                                                            <i class="fas fa-futbol"></i> <?php echo (int) $scorer['total_goals']; ?> goal
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="event-section-divider"></div>
                            <h4 style="margin:0 0 8px 0;">Status Suspend Pemain</h4>
                            <?php if (!$hasEventValueTables): ?>
                                <p style="margin:0; color:#64748b;">Data kartu pemain belum tersedia.</p>
                            <?php elseif (empty($eventPlayerCardsByCategory)): ?>
                                <p style="margin:0; color:#64748b;">Belum ada data kartu pemain.</p>
                            <?php else: ?>
                                <?php foreach ($eventPlayerCardsByCategory as $categoryName => $rows): ?>
                                    <h4 style="margin:18px 0 8px 0;">Suspend <?php echo htmlspecialchars($categoryName); ?></h4>
                                    <div class="event-standing-wrap">
                                        <table class="event-standing-table" style="min-width:760px;">
                                            <thead>
                                                <tr>
                                                    <th>Pemain</th>
                                                    <th>Team</th>
                                                    <th>Kuning</th>
                                                    <th>Merah</th>
                                                    <th>Hijau</th>
                                                    <th>Suspend Sampai</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($rows)): ?>
                                                    <tr>
                                                        <td colspan="7" style="text-align:left;">Belum ada data.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($rows as $card): ?>
                                                        <?php
                                                        $suspendUntil = !empty($card['suspension_until']) ? $card['suspension_until'] : null;
                                                        $isSuspended = $suspendUntil && $suspendUntil >= date('Y-m-d');
                                                        ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($card['player_name'] ?? '-'); ?></td>
                                                            <td><?php echo htmlspecialchars($card['team_name'] ?? '-'); ?></td>
                                                            <td><span class="card-badge yellow"><?php echo (int) ($card['yellow_cards'] ?? 0); ?></span></td>
                                                            <td><span class="card-badge red"><?php echo (int) ($card['red_cards'] ?? 0); ?></span></td>
                                                            <td><span class="card-badge green"><?php echo (int) ($card['green_cards'] ?? 0); ?></span></td>
                                                            <td><?php echo $suspendUntil ? htmlspecialchars(date('d M Y', strtotime($suspendUntil))) : '-'; ?></td>
                                                            <td>
                                                                <span class="suspend-pill <?php echo $isSuspended ? 'active' : 'done'; ?>">
                                                                    <?php echo $isSuspended ? 'Tidak Boleh Main' : 'Boleh Main'; ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="event-desc" id="eventBracket">
                            <h3 style="margin:0 0 8px 0;">Preview Bracket</h3>
                            <p style="margin:0 0 8px 0; color:#475569;">Bracket semifinal, final, dan perebutan juara 3 per kategori.</p>

                            <?php if (!$hasEventBracketTable): ?>
                                <p style="margin:10px 0 0 0; color:#64748b;">Tabel bracket belum tersedia.</p>
                            <?php elseif (empty($eventBracketViews)): ?>
                                <p style="margin:10px 0 0 0; color:#64748b;">Belum ada data bracket untuk event ini.</p>
                            <?php else: ?>
                                <?php foreach ($eventBracketViews as $view): ?>
                                    <?php
                                    $categoryName = (string) ($view['category'] ?? '');
                                    $teamMap = (array) ($view['team_map'] ?? []);
                                    $teamLogoMap = (array) ($view['team_logo_map'] ?? []);
                                    $sf1Team1 = (int) ($view['sf1_team1'] ?? 0);
                                    $sf1Team2 = (int) ($view['sf1_team2'] ?? 0);
                                    $sf2Team1 = (int) ($view['sf2_team1'] ?? 0);
                                    $sf2Team2 = (int) ($view['sf2_team2'] ?? 0);
                                    $finalTeam1 = (int) ($view['final_team1'] ?? 0);
                                    $finalTeam2 = (int) ($view['final_team2'] ?? 0);
                                    $thirdTeam1 = (int) ($view['third_team1'] ?? 0);
                                    $thirdTeam2 = (int) ($view['third_team2'] ?? 0);
                                    $sf1Score1 = $view['sf1_score1'] ?? null;
                                    $sf1Score2 = $view['sf1_score2'] ?? null;
                                    $sf2Score1 = $view['sf2_score1'] ?? null;
                                    $sf2Score2 = $view['sf2_score2'] ?? null;
                                    $finalScore1 = $view['final_score1'] ?? null;
                                    $finalScore2 = $view['final_score2'] ?? null;
                                    $thirdScore1 = $view['third_score1'] ?? null;
                                    $thirdScore2 = $view['third_score2'] ?? null;
                                    $sf1Outcome = (array) ($view['sf1_outcome'] ?? []);
                                    $sf2Outcome = (array) ($view['sf2_outcome'] ?? []);
                                    $finalOutcome = (array) ($view['final_outcome'] ?? []);
                                    $thirdOutcome = (array) ($view['third_outcome'] ?? []);
                                    $sf1Done = ((string) ($sf1Outcome['status'] ?? '') === 'done');
                                    $sf2Done = ((string) ($sf2Outcome['status'] ?? '') === 'done');
                                    $finalDone = ((string) ($finalOutcome['status'] ?? '') === 'done');
                                    $thirdDone = ((string) ($thirdOutcome['status'] ?? '') === 'done');
                                    ?>
                                    <div class="event-bracket-wrap">
                                        <h4 class="event-bracket-title"><?php echo htmlspecialchars($categoryName); ?></h4>
                                        <p class="event-bracket-note">Preview kategori <?php echo htmlspecialchars($categoryName); ?>.</p>
                                        <div class="evb-canvas">
                                            <div class="evb-match" style="left:10px; top:48px;">
                                                <div class="evb-row <?php echo $sf1Done && (int) ($sf1Outcome['winner'] ?? 0) === $sf1Team1 ? 'win' : 'lose'; ?>" data-team-id="<?php echo $sf1Team1; ?>">
                                                    <div class="evb-name"><?php echo renderBracketTeamLabelPublic($teamMap, $teamLogoMap, $sf1Team1); ?></div>
                                                    <div class="evb-score <?php echo $sf1Done && (int) ($sf1Outcome['winner'] ?? 0) === $sf1Team1 ? 'green' : 'red'; ?>"><?php echo $sf1Score1 !== null ? (int) $sf1Score1 : '-'; ?></div>
                                                </div>
                                                <div class="evb-row <?php echo $sf1Done && (int) ($sf1Outcome['winner'] ?? 0) === $sf1Team2 ? 'win' : 'lose'; ?>" data-team-id="<?php echo $sf1Team2; ?>">
                                                    <div class="evb-name"><?php echo renderBracketTeamLabelPublic($teamMap, $teamLogoMap, $sf1Team2); ?></div>
                                                    <div class="evb-score <?php echo $sf1Done && (int) ($sf1Outcome['winner'] ?? 0) === $sf1Team2 ? 'green' : 'red'; ?>"><?php echo $sf1Score2 !== null ? (int) $sf1Score2 : '-'; ?></div>
                                                </div>
                                            </div>

                                            <div class="evb-match" style="left:10px; top:240px;">
                                                <div class="evb-row <?php echo $sf2Done && (int) ($sf2Outcome['winner'] ?? 0) === $sf2Team1 ? 'win' : 'lose'; ?>" data-team-id="<?php echo $sf2Team1; ?>">
                                                    <div class="evb-name"><?php echo renderBracketTeamLabelPublic($teamMap, $teamLogoMap, $sf2Team1); ?></div>
                                                    <div class="evb-score <?php echo $sf2Done && (int) ($sf2Outcome['winner'] ?? 0) === $sf2Team1 ? 'green' : 'red'; ?>"><?php echo $sf2Score1 !== null ? (int) $sf2Score1 : '-'; ?></div>
                                                </div>
                                                <div class="evb-row <?php echo $sf2Done && (int) ($sf2Outcome['winner'] ?? 0) === $sf2Team2 ? 'win' : 'lose'; ?>" data-team-id="<?php echo $sf2Team2; ?>">
                                                    <div class="evb-name"><?php echo renderBracketTeamLabelPublic($teamMap, $teamLogoMap, $sf2Team2); ?></div>
                                                    <div class="evb-score <?php echo $sf2Done && (int) ($sf2Outcome['winner'] ?? 0) === $sf2Team2 ? 'green' : 'red'; ?>"><?php echo $sf2Score2 !== null ? (int) $sf2Score2 : '-'; ?></div>
                                                </div>
                                            </div>

                                            <div class="evb-match" style="left:460px; top:145px;">
                                                <div class="evb-row <?php echo $finalDone && (int) ($finalOutcome['winner'] ?? 0) === $finalTeam1 ? 'win' : 'lose'; ?>" data-team-id="<?php echo $finalTeam1; ?>">
                                                    <div class="evb-name"><?php echo renderBracketTeamLabelPublic($teamMap, $teamLogoMap, $finalTeam1); ?></div>
                                                    <div class="evb-score <?php echo $finalDone && (int) ($finalOutcome['winner'] ?? 0) === $finalTeam1 ? 'green' : 'red'; ?>"><?php echo $finalScore1 !== null ? (int) $finalScore1 : '-'; ?></div>
                                                </div>
                                                <div class="evb-row <?php echo $finalDone && (int) ($finalOutcome['winner'] ?? 0) === $finalTeam2 ? 'win' : 'lose'; ?>" data-team-id="<?php echo $finalTeam2; ?>">
                                                    <div class="evb-name"><?php echo renderBracketTeamLabelPublic($teamMap, $teamLogoMap, $finalTeam2); ?></div>
                                                    <div class="evb-score <?php echo $finalDone && (int) ($finalOutcome['winner'] ?? 0) === $finalTeam2 ? 'green' : 'red'; ?>"><?php echo $finalScore2 !== null ? (int) $finalScore2 : '-'; ?></div>
                                                </div>
                                                <span class="evb-rank first">1st</span>
                                                <span class="evb-rank second">2nd</span>
                                            </div>

                                            <div class="evb-match" style="left:460px; top:380px;">
                                                <div class="evb-row <?php echo $thirdDone && (int) ($thirdOutcome['winner'] ?? 0) === $thirdTeam1 ? 'win' : 'lose'; ?>" data-team-id="<?php echo $thirdTeam1; ?>">
                                                    <div class="evb-name"><?php echo renderBracketTeamLabelPublic($teamMap, $teamLogoMap, $thirdTeam1); ?></div>
                                                    <div class="evb-score <?php echo $thirdDone && (int) ($thirdOutcome['winner'] ?? 0) === $thirdTeam1 ? 'green' : 'red'; ?>"><?php echo $thirdScore1 !== null ? (int) $thirdScore1 : '-'; ?></div>
                                                </div>
                                                <div class="evb-row <?php echo $thirdDone && (int) ($thirdOutcome['winner'] ?? 0) === $thirdTeam2 ? 'win' : 'lose'; ?>" data-team-id="<?php echo $thirdTeam2; ?>">
                                                    <div class="evb-name"><?php echo renderBracketTeamLabelPublic($teamMap, $teamLogoMap, $thirdTeam2); ?></div>
                                                    <div class="evb-score <?php echo $thirdDone && (int) ($thirdOutcome['winner'] ?? 0) === $thirdTeam2 ? 'green' : 'red'; ?>"><?php echo $thirdScore2 !== null ? (int) $thirdScore2 : '-'; ?></div>
                                                </div>
                                                <span class="evb-rank third">3rd</span>
                                                <span class="evb-rank fourth">4th</span>
                                            </div>

                                            <div class="evb-line gold" style="left:270px; top:84px; width:90px; border-top-width:3px;"></div>
                                            <div class="evb-line gold" style="left:360px; top:84px; height:92px; border-right-width:3px;"></div>
                                            <div class="evb-line gold" style="left:360px; top:176px; width:100px; border-top-width:3px;"></div>

                                            <div class="evb-line" style="left:270px; top:276px; width:90px; border-top-width:3px;"></div>
                                            <div class="evb-line" style="left:360px; top:176px; height:100px; border-right-width:3px;"></div>
                                            <div class="evb-line" style="left:360px; top:176px; width:100px; border-top-width:3px;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($eventTopScorersLiga)): ?>
                            <div class="event-desc" id="eventTopScorerLiga">
                                <h3 style="margin:0 0 16px 0;">Top Scorer Liga</h3>
                                <div class="top-scorer-grid">
                                    <?php foreach ($eventTopScorersLiga as $scorer): ?>
                                        <div class="top-scorer-card">
                                            <img src="<?php echo SITE_URL; ?>/images/players/<?php echo htmlspecialchars($scorer['player_photo'] ?: 'default-player.jpg'); ?>" alt="Photo" class="top-scorer-photo" onerror="this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                                            <div class="top-scorer-name" title="<?php echo htmlspecialchars($scorer['player_name']); ?>"><?php echo htmlspecialchars($scorer['player_name']); ?></div>
                                            <div class="top-scorer-team" title="<?php echo htmlspecialchars($scorer['team_name']); ?>">
                                                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($scorer['team_logo'] ?: 'default-team.png'); ?>" alt="Logo" class="top-scorer-team-logo" onerror="this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                                <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($scorer['team_name']); ?></span>
                                            </div>
                                            <div class="top-scorer-goals">
                                                <i class="fas fa-futbol"></i> <?php echo (int) $scorer['total_goals']; ?> goal
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="container section-container section-elevated">
                    <div class="section-header">
                        <h2 class="section-title">EVENT</h2>
                        <div class="section-tabs">
                            <span class="team-count-pill"><i class="fas fa-calendar-alt"></i> <?php echo count($events); ?> Event</span>
                        </div>
                    </div>

                    <div class="team-filter-card">
                        <label class="team-filter-label" for="eventSelector">Pilih Event</label>
                        <div class="team-selector">
                            <select id="eventSelector" class="event-selector-select" onchange="if(this.value) window.location.href='EVENTS.PHP?id=' + this.value">
                                <option value="">Pilih event dari dropdown</option>
                                <?php foreach ($events as $ev): ?>
                                    <option value="<?php echo (int) $ev['id']; ?>"><?php echo htmlspecialchars($ev['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="event-selector-search">
                                <input id="eventSelectorInput" placeholder="Cari event .....">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container section-container">
                    <?php if (empty($events)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h4>Tidak ada event</h4>
                            <p>Belum ada event yang tersedia.</p>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" id="eventSearchEmpty" style="display:none;">
                            <i class="fas fa-search"></i>
                            <h4>Event tidak ditemukan</h4>
                            <p>Coba kata kunci lain untuk mencari event.</p>
                        </div>
                        <div class="teams-grid team-directory-grid">
                            <?php foreach ($events as $ev): ?>
                                <a href="EVENTS.PHP?id=<?php echo (int) $ev['id']; ?>" class="team-card team-directory-card" data-team-id="<?php echo (int) $ev['id']; ?>" data-event-name="<?php echo htmlspecialchars(strtolower((string)($ev['name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php if (!empty($ev['image'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/images/events/<?php echo htmlspecialchars($ev['image']); ?>" alt="<?php echo htmlspecialchars($ev['name']); ?>" class="event-card-image" onerror="this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                                    <?php else: ?>
                                        <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="<?php echo htmlspecialchars($ev['name']); ?>" class="event-card-image">
                                    <?php endif; ?>
                                    <h3 class="team-name"><?php echo htmlspecialchars($ev['name']); ?></h3>
                                    <p class="team-label"><?php echo !empty($ev['category']) ? htmlspecialchars($ev['category']) : '-'; ?></p>
                                    <span class="event-chip <?php echo strtolower((string) ($ev['registration_status'] ?? 'closed')) === 'open' ? 'open' : 'closed'; ?>">
                                        <?php echo strtolower((string) ($ev['registration_status'] ?? 'closed')) === 'open' ? 'Open' : 'Closed'; ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <footer class="dashboard-footer">
            <p>&copy; 2026 ALVETRIX. Semua hak dilindungi.</p>
            <p>
                <a href="<?php echo SITE_URL; ?>">Beranda</a> |
                <a href="contact.php">Kontak</a> |
                <a href="bpjs.php">BPJSTK</a>
            </p>
        </footer>
    </div>
</div>

<script>
function toggleDropdown(element, dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;
    dropdown.classList.toggle('show');
    element.classList.toggle('open');
}

const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');

const setSidebarOpen = (open) => {
    if (!sidebar || !sidebarToggle || !sidebarOverlay) return;
    sidebar.classList.toggle('active', open);
    sidebarOverlay.classList.toggle('active', open);
    sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
    sidebarOverlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.classList.toggle('sidebar-open', open);
};

if (sidebarToggle && sidebar && sidebarOverlay) {
    sidebarToggle.addEventListener('click', () => {
        const isOpen = sidebar.classList.contains('active');
        setSidebarOpen(!isOpen);
    });
    sidebarOverlay.addEventListener('click', () => setSidebarOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setSidebarOpen(false);
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) setSidebarOpen(false);
    });
}

(function() {
    const input = document.getElementById('eventSelectorInput');
    const select = document.getElementById('eventSelector');
    const cards = Array.from(document.querySelectorAll('.team-directory-card[data-event-name]'));
    const emptyState = document.getElementById('eventSearchEmpty');
    if (!input || !select) return;

    const defaultOption = select.options[0] ? { value: '', text: select.options[0].text } : { value: '', text: 'Pilih event dari dropdown' };
    const allOptions = Array.from(select.options)
        .slice(1)
        .map(function(opt) {
            return { value: String(opt.value || ''), text: String(opt.text || '') };
        });

    function renderFilteredOptions(keyword) {
        const normalized = String(keyword || '').trim().toLowerCase();
        const filtered = normalized === ''
            ? allOptions
            : allOptions.filter(function(item) {
                return item.text.toLowerCase().includes(normalized);
            });

        select.innerHTML = '';
        const top = document.createElement('option');
        top.value = defaultOption.value;
        top.textContent = defaultOption.text;
        select.appendChild(top);

        filtered.forEach(function(item) {
            const opt = document.createElement('option');
            opt.value = item.value;
            opt.textContent = item.text;
            select.appendChild(opt);
        });

        if (!filtered.length) {
            const none = document.createElement('option');
            none.value = '';
            none.textContent = 'Tidak ada event yang cocok';
            none.disabled = true;
            select.appendChild(none);
        }
    }

    function renderFilteredCards(keyword) {
        if (!cards.length) return;
        const normalized = String(keyword || '').trim().toLowerCase();
        let visibleCount = 0;

        cards.forEach(function(card) {
            const eventName = String(card.getAttribute('data-event-name') || '').trim().toLowerCase();
            const isVisible = normalized === '' || eventName.includes(normalized);
            card.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount += 1;
        });

        if (emptyState) {
            emptyState.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    input.addEventListener('input', function() {
        renderFilteredOptions(input.value);
        renderFilteredCards(input.value);
    });
})();
</script>
<script>
(function() {
    const rows = Array.from(document.querySelectorAll('.evb-canvas .evb-row[data-team-id]'));
    if (!rows.length) return;

    function clearLinked() {
        rows.forEach(function(row) { row.classList.remove('linked-highlight'); });
    }

    function applyLinked(teamId) {
        if (!teamId || teamId === '0') return;
        rows.forEach(function(row) {
            if (row.getAttribute('data-team-id') === teamId) {
                row.classList.add('linked-highlight');
            }
        });
    }

    rows.forEach(function(row) {
        row.addEventListener('mouseenter', function() {
            clearLinked();
            applyLinked(row.getAttribute('data-team-id'));
        });
        row.addEventListener('mouseleave', function() {
            clearLinked();
        });
    });
})();
</script>
<script>
const SITE_URL = '<?php echo SITE_URL; ?>';
</script>
<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
