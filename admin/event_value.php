<?php
session_start();

$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

$event_helper_path = __DIR__ . '/includes/event_helpers.php';
if (file_exists($event_helper_path)) {
    require_once $event_helper_path;
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function ensureSchema(PDO $conn): void
{
    $conn->exec("CREATE TABLE IF NOT EXISTS event_team_values (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        team_id INT NOT NULL,
        sport_type VARCHAR(120) NOT NULL DEFAULT '',
        mn INT NOT NULL DEFAULT 0,
        m INT NOT NULL DEFAULT 0,
        mp INT NOT NULL DEFAULT 0,
        s INT NOT NULL DEFAULT 0,
        kp INT NOT NULL DEFAULT 0,
        k INT NOT NULL DEFAULT 0,
        gm INT NOT NULL DEFAULT 0,
        gk INT NOT NULL DEFAULT 0,
        sg INT NOT NULL DEFAULT 0,
        points INT NOT NULL DEFAULT 0,
        kls INT NOT NULL DEFAULT 0,
        red_cards INT NOT NULL DEFAULT 0,
        yellow_cards INT NOT NULL DEFAULT 0,
        green_cards INT NOT NULL DEFAULT 0,
        match_history TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_event_team_category (event_id, team_id, sport_type),
        CONSTRAINT fk_etv_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_etv_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->exec("CREATE TABLE IF NOT EXISTS player_event_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        player_id INT NOT NULL,
        team_id INT NOT NULL,
        sport_type VARCHAR(120) NOT NULL DEFAULT '',
        yellow_cards INT NOT NULL DEFAULT 0,
        red_cards INT NOT NULL DEFAULT 0,
        green_cards INT NOT NULL DEFAULT 0,
        suspension_until DATE DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_event_player_category (event_id, player_id, sport_type),
        CONSTRAINT fk_pec_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pec_player FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pec_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $hasKls = $conn->query("SHOW COLUMNS FROM event_team_values LIKE 'kls'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasKls) {
        $conn->exec("ALTER TABLE event_team_values ADD COLUMN kls INT NOT NULL DEFAULT 0 AFTER points");
    }
    $hasSportTypeTeam = $conn->query("SHOW COLUMNS FROM event_team_values LIKE 'sport_type'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasSportTypeTeam) {
        $conn->exec("ALTER TABLE event_team_values ADD COLUMN sport_type VARCHAR(120) NOT NULL DEFAULT '' AFTER team_id");
    }
    $hasSportTypePlayer = $conn->query("SHOW COLUMNS FROM player_event_cards LIKE 'sport_type'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasSportTypePlayer) {
        $conn->exec("ALTER TABLE player_event_cards ADD COLUMN sport_type VARCHAR(120) NOT NULL DEFAULT '' AFTER team_id");
    }
    $hasMatchHistory = $conn->query("SHOW COLUMNS FROM event_team_values LIKE 'match_history'")->fetch(PDO::FETCH_ASSOC);
    if (!$hasMatchHistory) {
        $conn->exec("ALTER TABLE event_team_values ADD COLUMN match_history TEXT NULL AFTER green_cards");
    }

    try { $conn->exec("ALTER TABLE event_team_values DROP INDEX uq_event_team"); } catch (PDOException $e) {}
    try { $conn->exec("ALTER TABLE event_team_values ADD UNIQUE KEY uq_event_team_category (event_id, team_id, sport_type)"); } catch (PDOException $e) {}
    try { $conn->exec("ALTER TABLE player_event_cards DROP INDEX uq_event_player"); } catch (PDOException $e) {}
    try { $conn->exec("ALTER TABLE player_event_cards ADD UNIQUE KEY uq_event_player_category (event_id, player_id, sport_type)"); } catch (PDOException $e) {}

    try {
        $conn->exec("CREATE INDEX idx_event_kls ON event_team_values (event_id, kls)");
    } catch (PDOException $e) {
        // Index may already exist.
    }
}

function loadEventTeams(PDO $conn, int $eventId, string $categoryName = ''): array
{
    if ($eventId <= 0) return [];

    if ($categoryName !== '') {
        $sql = "SELECT DISTINCT t.id, t.name
                FROM teams t
                INNER JOIN (
                    SELECT challenger_id AS team_id FROM challenges WHERE event_id = ? AND sport_type = ? AND status = 'accepted'
                    UNION
                    SELECT opponent_id AS team_id FROM challenges WHERE event_id = ? AND sport_type = ? AND status = 'accepted'
                    UNION
                    SELECT te.team_id
                    FROM team_events te
                    INNER JOIN events e ON e.name = te.event_name
                    WHERE e.id = ? AND te.event_name = ?
                ) src ON src.team_id = t.id
                ORDER BY t.name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$eventId, $categoryName, $eventId, $categoryName, $eventId, $categoryName]);
    } else {
        $sql = "SELECT DISTINCT t.id, t.name 
                FROM teams t 
                INNER JOIN (
                    SELECT challenger_id AS team_id FROM challenges WHERE event_id = ? AND status = 'accepted'
                    UNION 
                    SELECT opponent_id AS team_id FROM challenges WHERE event_id = ? AND status = 'accepted'
                    UNION 
                    SELECT te.team_id FROM team_events te INNER JOIN events e ON e.name = te.event_name WHERE e.id = ?
                ) src ON src.team_id = t.id 
                ORDER BY t.name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$eventId, $eventId, $eventId]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function loadEventCategoryTeamMap(PDO $conn, int $eventId): array
{
    if ($eventId <= 0) return [];

    try {
        $stmt = $conn->prepare("SELECT category_name, team_id
                                FROM (
                                    SELECT c.sport_type AS category_name, c.challenger_id AS team_id
                                    FROM challenges c
                                    WHERE c.event_id = ?
                                      AND c.status = 'accepted'
                                      AND c.sport_type IS NOT NULL
                                      AND c.sport_type <> ''
                                    UNION ALL
                                    SELECT c.sport_type AS category_name, c.opponent_id AS team_id
                                    FROM challenges c
                                    WHERE c.event_id = ?
                                      AND c.status = 'accepted'
                                      AND c.sport_type IS NOT NULL
                                      AND c.sport_type <> ''
                                ) src
                                ORDER BY category_name ASC, team_id ASC");
        $stmt->execute([$eventId, $eventId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }

    $result = [];
    foreach ($rows as $row) {
        $category = trim((string)($row['category_name'] ?? ''));
        $teamId = (int)($row['team_id'] ?? 0);
        if ($category === '' || $teamId <= 0) continue;
        if (!isset($result[$category])) $result[$category] = [];
        $result[$category][$teamId] = true;
    }

    foreach ($result as $category => $teamMap) {
        $result[$category] = array_map('intval', array_keys($teamMap));
    }

    return $result;
}

function calculateTeamPoints(int $m, int $mp, int $kp): int
{
    return ($m * 3) + ($mp * 2) + $kp;
}

function normalizeMatchTokens(string $raw): array
{
    $normalized = strtoupper(trim($raw));
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

function buildMatchTokensFromStats(int $m, int $mp, int $s, int $kp, int $k): array
{
    $tokens = [];
    for ($i = 0; $i < $m; $i++) $tokens[] = 'W';
    for ($i = 0; $i < $mp; $i++) $tokens[] = 'WP';
    for ($i = 0; $i < $s; $i++) $tokens[] = 'D';
    for ($i = 0; $i < $kp; $i++) $tokens[] = 'LP';
    for ($i = 0; $i < $k; $i++) $tokens[] = 'L';
    return $tokens;
}

function renderMatchHistoryBadges(string $history): string
{
    $tokens = normalizeMatchTokens($history);
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

function recomputeEventKls(PDO $conn, int $eventId, string $categoryName = ''): void
{
    if ($eventId <= 0) return;

    if ($categoryName !== '') {
        $rankStmt = $conn->prepare("SELECT id
                                    FROM event_team_values
                                    WHERE event_id = ? AND sport_type = ?
                                    ORDER BY points DESC, sg DESC, gm DESC, id ASC");
        $rankStmt->execute([$eventId, $categoryName]);
    } else {
        $rankStmt = $conn->prepare("SELECT id
                                    FROM event_team_values
                                    WHERE event_id = ?
                                    ORDER BY points DESC, sg DESC, gm DESC, id ASC");
        $rankStmt->execute([$eventId]);
    }
    $rows = $rankStmt->fetchAll(PDO::FETCH_ASSOC);

    $updateStmt = $conn->prepare("UPDATE event_team_values SET kls = ? WHERE id = ?");
    foreach ($rows as $idx => $row) {
        $updateStmt->execute([$idx + 1, (int)$row['id']]);
    }
}

ensureSchema($conn);

$menu_items = [
    'dashboard' => ['icon' => '🏠', 'name' => 'Dashboard', 'url' => 'dashboard.php', 'submenu' => false],
    'master' => ['icon' => '📊', 'name' => 'Master Data', 'submenu' => true, 'items' => [
        'player' => 'player.php',
        'team' => 'team.php',
        'team_staff' => 'team_staff.php',
        'transfer' => 'transfer.php'
    ]],
    'event' => ['icon' => '🏆', 'name' => 'Event', 'url' => 'event.php', 'submenu' => false],
    'challenge' => ['icon' => '⚔️', 'name' => 'Challenge', 'url' => 'challenge.php', 'submenu' => false],
    'Venue' => ['icon' => '📍', 'name' => 'Venue', 'url' => 'venue.php', 'submenu' => false],
    'Pelatih' => ['icon' => '👨‍🏫', 'name' => 'Pelatih', 'url' => 'pelatih.php', 'submenu' => false],
    'Berita' => ['icon' => '📰', 'name' => 'Berita', 'url' => 'berita.php', 'submenu' => false]
];

$current_page = basename($_SERVER['PHP_SELF']);
$academy_name = "Hi, Welcome...";
$email = $_SESSION['admin_email'] ?? '';
$event_types = function_exists('getDynamicEventOptions') ? getDynamicEventOptions($conn) : [];

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$selectedCategory = trim((string)($_GET['sport_type'] ?? ''));
$events = $conn->query("SELECT id, name FROM events ORDER BY start_date DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

if ($selectedCategory !== '' && !in_array($selectedCategory, $event_types, true)) {
    $selectedCategory = '';
}

$teams = loadEventTeams($conn, $eventId, $selectedCategory);
$teamMap = [];
foreach ($teams as $team) {
    $teamMap[(int)$team['id']] = true;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Token invalid.';
    }

    $action = trim($_POST['action'] ?? '');
    $eventId = (int)($_POST['event_id'] ?? $eventId);
    $selectedCategory = trim((string)($_POST['sport_type'] ?? $selectedCategory));
    if (!empty($event_types) && ($selectedCategory === '' || !in_array($selectedCategory, $event_types, true))) {
        $errors[] = 'Kategori wajib dipilih.';
    }
    $teams = loadEventTeams($conn, $eventId, $selectedCategory);
    $teamMap = [];
    foreach ($teams as $team) {
        $teamMap[(int)$team['id']] = true;
    }

    if ($action === 'save_team' && empty($errors)) {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $matchHistoryInput = trim((string)($_POST['match_history'] ?? ''));
        $m = max(0, (int)($_POST['m'] ?? 0));
        $mp = max(0, (int)($_POST['mp'] ?? 0));
        $s = max(0, (int)($_POST['s'] ?? 0));
        $kp = max(0, (int)($_POST['kp'] ?? 0));
        $k = max(0, (int)($_POST['k'] ?? 0));
        $gm = max(0, (int)($_POST['gm'] ?? 0));
        $gk = max(0, (int)($_POST['gk'] ?? 0));
        $red = max(0, (int)($_POST['red_cards'] ?? 0));
        $yellow = max(0, (int)($_POST['yellow_cards'] ?? 0));
        $green = max(0, (int)($_POST['green_cards'] ?? 0));

        if ($eventId <= 0 || $teamId <= 0) {
            $errors[] = 'Event dan tim wajib dipilih.';
        } elseif (!isset($teamMap[$teamId])) {
            $errors[] = 'Tim tidak terdaftar di event ini.';
        }

        if (empty($errors)) {
            $conn->beginTransaction();
            try {
                $existingStmt = $conn->prepare("SELECT mn, m, mp, s, kp, k, gm, gk, red_cards, yellow_cards, green_cards, match_history
                                                FROM event_team_values
                                                WHERE event_id = ? AND team_id = ? AND sport_type = ? LIMIT 1
                                                FOR UPDATE");
                $existingStmt->execute([$eventId, $teamId, $selectedCategory]);
                $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

                $totalM = (int)($existing['m'] ?? 0) + $m;
                $totalMp = (int)($existing['mp'] ?? 0) + $mp;
                $totalS = (int)($existing['s'] ?? 0) + $s;
                $totalKp = (int)($existing['kp'] ?? 0) + $kp;
                $totalK = (int)($existing['k'] ?? 0) + $k;
                $totalGm = (int)($existing['gm'] ?? 0) + $gm;
                $totalGk = (int)($existing['gk'] ?? 0) + $gk;
                $totalRed = (int)($existing['red_cards'] ?? 0) + $red;
                $totalYellow = (int)($existing['yellow_cards'] ?? 0) + $yellow;
                $totalGreen = (int)($existing['green_cards'] ?? 0) + $green;

                $mn = $totalM + $totalMp + $totalS + $totalKp + $totalK;
                $sg = $totalGm - $totalGk;
                $points = calculateTeamPoints($totalM, $totalMp, $totalKp);
                $existingTokens = normalizeMatchTokens((string)($existing['match_history'] ?? ''));
                $newTokens = normalizeMatchTokens($matchHistoryInput);
                if (empty($newTokens)) {
                    $newTokens = buildMatchTokensFromStats($m, $mp, $s, $kp, $k);
                }
                $matchHistory = implode(',', array_merge($existingTokens, $newTokens));

                $stmt = $conn->prepare("INSERT INTO event_team_values (event_id, team_id, sport_type, mn, m, mp, s, kp, k, gm, gk, sg, points, kls, red_cards, yellow_cards, green_cards, match_history) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                                        ON DUPLICATE KEY UPDATE 
                                        mn = VALUES(mn), m = VALUES(m), mp = VALUES(mp), s = VALUES(s), kp = VALUES(kp), k = VALUES(k), 
                                        gm = VALUES(gm), gk = VALUES(gk), sg = VALUES(sg), points = VALUES(points), kls = VALUES(kls), sport_type = VALUES(sport_type),
                                        red_cards = VALUES(red_cards), yellow_cards = VALUES(yellow_cards), green_cards = VALUES(green_cards), match_history = VALUES(match_history),
                                        updated_at = CURRENT_TIMESTAMP");

                $stmt->execute([$eventId, $teamId, $selectedCategory, $mn, $totalM, $totalMp, $totalS, $totalKp, $totalK, $totalGm, $totalGk, $sg, $points, 0, $totalRed, $totalYellow, $totalGreen, $matchHistory]);
                recomputeEventKls($conn, $eventId, $selectedCategory);
                $conn->commit();
            } catch (Throwable $e) {
                if ($conn->inTransaction()) $conn->rollBack();
                $errors[] = 'Gagal menyimpan nilai tim.';
            }

            if (empty($errors)) {
            $success = 'Nilai tim tersimpan.';
            }
        }
    }

    if ($action === 'save_player' && empty($errors)) {
        $teamId = (int)($_POST['player_team_id'] ?? 0);
        $playerId = (int)($_POST['player_id'] ?? 0);
        $yellow = max(0, (int)($_POST['player_yellow_cards'] ?? 0));
        $red = max(0, (int)($_POST['player_red_cards'] ?? 0));
        $green = max(0, (int)($_POST['player_green_cards'] ?? 0));

        if ($eventId <= 0 || $teamId <= 0 || $playerId <= 0) {
            $errors[] = 'Event, tim, dan pemain wajib.';
        } elseif (!isset($teamMap[$teamId])) {
            $errors[] = 'Tim pemain tidak valid untuk event ini.';
        }

        if (empty($errors)) {
            $check = $conn->prepare("SELECT id FROM players WHERE id = ? AND team_id = ?");
            $check->execute([$playerId, $teamId]);
            if (!$check->fetch(PDO::FETCH_ASSOC)) $errors[] = 'Pemain tidak ditemukan.';
        }

        if (empty($errors)) {
            $existingStmt = $conn->prepare("SELECT yellow_cards, red_cards, green_cards, suspension_until
                                            FROM player_event_cards
                                            WHERE event_id = ? AND player_id = ? AND sport_type = ? LIMIT 1");
            $existingStmt->execute([$eventId, $playerId, $selectedCategory]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

            $prevYellow = (int)($existing['yellow_cards'] ?? 0);
            $prevRed = (int)($existing['red_cards'] ?? 0);
            $prevGreen = (int)($existing['green_cards'] ?? 0);

            $totalYellow = $prevYellow + $yellow;
            $totalRed = $prevRed + $red;
            $totalGreen = $prevGreen + $green;

            $today = date('Y-m-d');
            $suspend = null;
            if (!empty($existing['suspension_until']) && $existing['suspension_until'] >= $today) {
                $suspend = $existing['suspension_until'];
            }

            $prevPair = intdiv($prevYellow, 2);
            $newPair = intdiv($totalYellow, 2);
            $newSuspensionBatch = $newPair - $prevPair;
            if ($newSuspensionBatch > 0) {
                $baseDate = $suspend ?: $today;
                $suspend = date('Y-m-d', strtotime($baseDate . ' +' . (7 * $newSuspensionBatch) . ' days'));
            }

            $stmt = $conn->prepare("INSERT INTO player_event_cards (event_id, player_id, team_id, sport_type, yellow_cards, red_cards, green_cards, suspension_until) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE 
                                    team_id = VALUES(team_id), sport_type = VALUES(sport_type), yellow_cards = VALUES(yellow_cards), red_cards = VALUES(red_cards), 
                                    green_cards = VALUES(green_cards), suspension_until = VALUES(suspension_until), updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$eventId, $playerId, $teamId, $selectedCategory, $totalYellow, $totalRed, $totalGreen, $suspend]);
            $success = 'Kartu pemain tersimpan.';
        }
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $csrf = $_SESSION['csrf_token'];
}

$teams = loadEventTeams($conn, $eventId, $selectedCategory);
$teamIds = array_map(static fn($row) => (int)$row['id'], $teams);

$players = [];
if (!empty($teamIds)) {
    $in = implode(',', $teamIds);
    if ($selectedCategory !== '') {
        $stmt_players = $conn->prepare("SELECT id, name, team_id FROM players WHERE team_id IN ($in) AND sport_type = ? ORDER BY name ASC");
        $stmt_players->execute([$selectedCategory]);
        $players = $stmt_players->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $players = $conn->query("SELECT id, name, team_id FROM players WHERE team_id IN ($in) ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
}

$standingsByCategory = [];
if ($eventId > 0) {
    $eventName = '';
    foreach ($events as $ev) {
        if ((int)($ev['id'] ?? 0) === $eventId) {
            $eventName = (string)($ev['name'] ?? '');
            break;
        }
    }

    $categoryTeamMap = loadEventCategoryTeamMap($conn, $eventId);
    if ($selectedCategory !== '') {
        if (isset($categoryTeamMap[$selectedCategory])) {
            $categoryTeamMap = [$selectedCategory => $categoryTeamMap[$selectedCategory]];
        } else {
            $categoryTeamMap = [];
        }
    }

    $allStandingsStmt = $conn->prepare("SELECT etv.*, t.name AS team_name
                                        FROM event_team_values etv
                                        INNER JOIN teams t ON t.id = etv.team_id
                                        WHERE etv.event_id = ?
                                        ORDER BY etv.sport_type ASC, etv.kls ASC, etv.points DESC, etv.sg DESC, etv.gm DESC, t.name ASC");
    $allStandingsStmt->execute([$eventId]);
    $standingsRowsAll = $allStandingsStmt->fetchAll(PDO::FETCH_ASSOC);

    $standingsBySport = [];
    foreach ($standingsRowsAll as $row) {
        $sport = trim((string)($row['sport_type'] ?? ''));
        if ($sport === '') continue;
        if (!isset($standingsBySport[$sport])) $standingsBySport[$sport] = [];
        $standingsBySport[$sport][] = $row;
    }
    if ($selectedCategory !== '') {
        if (isset($standingsBySport[$selectedCategory])) {
            $standingsBySport = [$selectedCategory => $standingsBySport[$selectedCategory]];
        } else {
            $standingsBySport = [];
        }
    }

    if (!empty($categoryTeamMap)) {
        foreach ($categoryTeamMap as $categoryName => $teamIdsInCategory) {
            recomputeEventKls($conn, $eventId, (string)$categoryName);

            $rows = $standingsBySport[$categoryName] ?? [];
            $rowsByTeamId = [];
            foreach ($rows as $row) {
                $rowsByTeamId[(int)$row['team_id']] = $row;
            }

            $teamsInCategory = loadEventTeams($conn, $eventId, (string)$categoryName);
            foreach ($teamsInCategory as $team) {
                $teamId = (int)($team['id'] ?? 0);
                if ($teamId <= 0 || isset($rowsByTeamId[$teamId])) continue;
                $rowsByTeamId[$teamId] = [
                    'team_id' => $teamId,
                    'team_name' => (string)($team['name'] ?? '-'),
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
                    'match_history' => '',
                ];
            }

            $rows = array_values($rowsByTeamId);
            usort($rows, static function (array $a, array $b): int {
                if ((int)$a['points'] !== (int)$b['points']) return (int)$b['points'] <=> (int)$a['points'];
                if ((int)$a['sg'] !== (int)$b['sg']) return (int)$b['sg'] <=> (int)$a['sg'];
                if ((int)$a['gm'] !== (int)$b['gm']) return (int)$b['gm'] <=> (int)$a['gm'];
                return strcmp((string)$a['team_name'], (string)$b['team_name']);
            });
            foreach ($rows as $idx => &$row) {
                $row['display_kls'] = $idx + 1;
            }
            unset($row);

            $standingsByCategory[] = [
                'title' => trim('Klasemen Event ' . $eventName . ' ' . $categoryName),
                'rows' => $rows
            ];
        }
    } else {
        foreach ($standingsBySport as $categoryName => $rows) {
            usort($rows, static function (array $a, array $b): int {
                if ((int)$a['points'] !== (int)$b['points']) return (int)$b['points'] <=> (int)$a['points'];
                if ((int)$a['sg'] !== (int)$b['sg']) return (int)$b['sg'] <=> (int)$a['sg'];
                if ((int)$a['gm'] !== (int)$b['gm']) return (int)$b['gm'] <=> (int)$a['gm'];
                return strcmp((string)$a['team_name'], (string)$b['team_name']);
            });
            foreach ($rows as $idx => &$row) {
                $row['display_kls'] = $idx + 1;
            }
            unset($row);

            $standingsByCategory[] = [
                'title' => trim('Klasemen Event ' . $eventName . ' ' . $categoryName),
                'rows' => $rows
            ];
        }
    }

    usort($standingsByCategory, static function (array $a, array $b): int {
        return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
    });
}

$playerCardsByCategory = [];
if ($eventId > 0) {
    $categoryTeamMap = loadEventCategoryTeamMap($conn, $eventId);
    if ($selectedCategory !== '') {
        if (isset($categoryTeamMap[$selectedCategory])) {
            $categoryTeamMap = [$selectedCategory => $categoryTeamMap[$selectedCategory]];
        } else {
            $categoryTeamMap = [];
        }
    }
    if (!empty($categoryTeamMap)) {
        foreach (array_keys($categoryTeamMap) as $categoryName) {
            if (!isset($playerCardsByCategory[$categoryName])) {
                $playerCardsByCategory[$categoryName] = [];
            }
        }
    }

    $stmt = $conn->prepare("SELECT c.*, p.name AS player_name, t.name AS team_name 
                            FROM player_event_cards c 
                            INNER JOIN players p ON p.id = c.player_id 
                            INNER JOIN teams t ON t.id = c.team_id 
                            WHERE c.event_id = ?
                            ORDER BY c.sport_type ASC, c.updated_at DESC");
    $stmt->execute([$eventId]);
    $playerCards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($playerCards as $card) {
        $sport = trim((string)($card['sport_type'] ?? ''));
        if ($sport === '') $sport = 'Lainnya';
        if ($selectedCategory !== '' && $sport !== $selectedCategory) {
            continue;
        }
        if (!isset($playerCardsByCategory[$sport])) $playerCardsByCategory[$sport] = [];
        $playerCardsByCategory[$sport][] = $card;
    }
    if (!empty($categoryTeamMap)) {
        $orderedPlayerCardsByCategory = [];
        foreach (array_keys($categoryTeamMap) as $categoryName) {
            $orderedPlayerCardsByCategory[$categoryName] = $playerCardsByCategory[$categoryName] ?? [];
        }
        foreach ($playerCardsByCategory as $categoryName => $rows) {
            if (!isset($orderedPlayerCardsByCategory[$categoryName])) {
                $orderedPlayerCardsByCategory[$categoryName] = $rows;
            }
        }
        $playerCardsByCategory = $orderedPlayerCardsByCategory;
    } else {
        ksort($playerCardsByCategory);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Value</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>
        :root {
            --primary: #0f2744;
            --secondary: #f59e0b;
            --accent: #3b82f6;
            --danger: #ef4444;
            --dark: #1e293b;
            --gray: #64748b;
            --sidebar-bg: linear-gradient(180deg, #0a1628 0%, #0f2744 100%);
            --card-shadow: 0 10px 15px -3px rgba(0,0,0,.05), 0 4px 6px -2px rgba(0,0,0,.03);
            --premium-shadow: 0 20px 25px -5px rgba(0,0,0,.08), 0 10px 10px -5px rgba(0,0,0,.04);
            --transition: cubic-bezier(.4,0,.2,1) .3s
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }
        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%);
            color: var(--dark)
        }
        .wrapper {
            display: flex;
            min-height: 100vh
        }

        .main {
            margin-left: 280px;
            flex: 1;
            padding: 28px
        }
        .topbar,
        .page-header,
        .form-container {
            background: #fff;
            border-radius: 18px;
            box-shadow: var(--card-shadow)
        }
        .topbar {
            padding: 18px 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px
        }
        .greeting h1 {
            color: var(--primary);
            font-size: 26px
        }
        .greeting p {
            color: var(--gray);
            font-size: 14px
        }
        .logout-btn {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            background: linear-gradient(135deg, var(--danger), #b91c1c);
            color: #fff;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600
        }
        .page-header {
            margin-bottom: 22px;
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px
        }
        .page-title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
            font-size: 25px
        }
        .btn {
            border: none;
            border-radius: 10px;
            padding: 11px 18px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: #fff
        }
        .btn-secondary {
            background: #6b7280;
            color: #fff
        }
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px
        }
        .alert-danger {
            background: rgba(211,47,47,.1);
            border-left: 4px solid #ef4444;
            color: #b91c1c
        }
        .alert-success {
            background: rgba(16,185,129,.12);
            border-left: 4px solid #10b981;
            color: #047857
        }
        .form-container {
            padding: 26px;
            margin-bottom: 22px
        }
        .form-section {
            margin-bottom: 26px;
            padding-bottom: 18px;
            border-bottom: 1px solid #e5e7eb
        }
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0
        }
        .section-title {
            color: var(--primary);
            font-size: 19px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px
        }
        .form-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(4, minmax(0, 1fr))
        }
        .form-grid-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }
        .form-group {
            margin-bottom: 10px
        }
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 7px
        }
        .form-input,
        .form-select {
            width: 100%;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 15px;
            background: #f8fafc
        }
        .form-input:focus,
        .form-select:focus {
            border-color: var(--accent);
            outline: none
        }
        .form-actions {
            margin-top: 10px;
            display: flex;
            justify-content: flex-end;
            gap: 10px
        }
        .form-note {
            color: var(--gray);
            font-size: 13px;
            margin-top: 10px
        }
        .table-container {
            background: rgba(255,255,255,.95);
            border-radius: 18px;
            overflow-x: auto;
            box-shadow: var(--premium-shadow);
            margin-top: 14px
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px
        }
        .data-table thead {
            background: linear-gradient(135deg, var(--primary), #1a365d);
            color: #fff
        }
        .data-table th,
        .data-table td {
            padding: 10px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
            text-align: center
        }
        .data-table th:nth-child(2),
        .data-table td:nth-child(2),
        .data-table th:first-child,
        .data-table td:first-child {
            text-align: left
        }
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
            border: 1px solid transparent
        }
        .card-badge.red { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
        .card-badge.yellow { background: #fef9c3; color: #a16207; border-color: #fde68a; }
        .card-badge.green { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .match-seq {
            display: flex;
            flex-wrap: wrap;
            gap: 4px
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
            color: #fff
        }
        .match-pill.win { background: #16a34a; }
        .match-pill.lose { background: #dc2626; }
        .match-pill.draw { background: #d97706; }
        .match-builder {
            border: 1px solid #dbe5f3;
            border-radius: 10px;
            background: #ffffff;
            padding: 10px
        }
        .match-builder-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 8px
        }
        .match-token-btn {
            border: 1px solid transparent;
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            color: #fff
        }
        .match-token-btn.win { background: #16a34a; }
        .match-token-btn.lose { background: #dc2626; }
        .match-token-btn.draw { background: #d97706; }
        .match-token-btn.neutral {
            background: #475569;
            border-color: #334155
        }
        .match-builder-preview {
            min-height: 26px;
            align-items: center
        }
        .match-placeholder {
            color: #64748b;
            font-size: 12px
        }
        .badge-pill {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700
        }
        .badge-ok {
            background: rgba(16,185,129,.15);
            color: #047857
        }
        .badge-ban {
            background: rgba(239,68,68,.15);
            color: #b91c1c
        }
        @media(max-width: 900px) {
            .sidebar {
                display: none
            }
            .main {
                margin-left: 0;
                width: 100%;
                padding: 16px
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start
            }
            .form-grid,
            .form-grid-2 {
                grid-template-columns: 1fr
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div class="main">
            <div class="topbar">
                <div class="greeting">
                    <h1>Event Value 🗓️</h1>
                    <p>Kelola klasemen, poin, kartu tim dan disiplin pemain.</p>
                </div>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>

            <div class="page-header">
                <div class="page-title">
                    <i class="fas fa-list-ol"></i> 
                    <span>Manajemen Nilai Event</span>
                </div>
                <a href="event.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endforeach; ?>

            <div class="form-container">
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-calendar-alt"></i> Pilih Event
                    </div>
                    <form method="get">
                        <div class="form-grid form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Event</label>
                                <select class="form-select" name="event_id" onchange="this.form.submit()">
                                    <option value="">Pilih Event</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo (int)$event['id']; ?>" <?php echo $eventId === (int)$event['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($event['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kategori</label>
                                <select class="form-select" name="sport_type" onchange="this.form.submit()">
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($event_types as $eventType): ?>
                                        <option value="<?php echo htmlspecialchars($eventType); ?>" <?php echo $selectedCategory === $eventType ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($eventType); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-trophy"></i> Input Nilai Tim
                    </div>
                    <?php if ($eventId <= 0 || $selectedCategory === ''): ?>
                        <div class="form-note">Pilih Event dan Kategori dulu untuk mengisi nilai tim.</div>
                    <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="save_team">
                        <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                        <input type="hidden" name="sport_type" value="<?php echo htmlspecialchars($selectedCategory); ?>">

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Tim</label>
                                <select class="form-select" name="team_id" required>
                                    <option value="">Pilih tim</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?php echo (int)$team['id']; ?>">
                                            <?php echo htmlspecialchars($team['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">TM (Otomatis)</label>
                                <input class="form-input" type="number" id="team_mn" name="mn" min="0" value="0" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">M</label>
                                <input class="form-input" type="number" id="team_m" name="m" min="0" value="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">MP</label>
                                <input class="form-input" type="number" id="team_mp" name="mp" min="0" value="0" required>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">S</label>
                                <input class="form-input" type="number" id="team_s" name="s" min="0" value="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">KP</label>
                                <input class="form-input" type="number" id="team_kp" name="kp" min="0" value="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">K</label>
                                <input class="form-input" type="number" id="team_k" name="k" min="0" value="0" required>
                            </div>
                        </div>

                        <div class="form-grid form-grid-2">
                            <div class="form-group">
                                <label class="form-label">GM</label>
                                <input class="form-input" type="number" name="gm" min="0" value="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">GK</label>
                                <input class="form-input" type="number" name="gk" min="0" value="0" required>
                            </div>
                        </div>

                        <div class="form-grid form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Poin (Otomatis)</label>
                                <input class="form-input" type="number" id="team_points_preview" value="0" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Input Match (Klik Kotak)</label>
                                <input type="hidden" name="match_history" id="match_history_input" value="<?php echo htmlspecialchars((string)($_POST['match_history'] ?? '')); ?>">
                                <div class="match-builder">
                                    <div class="match-builder-actions">
                                        <button type="button" class="match-token-btn win" data-token="W">+ W</button>
                                        <button type="button" class="match-token-btn win" data-token="WP">+ WP</button>
                                        <button type="button" class="match-token-btn draw" data-token="D">+ D</button>
                                        <button type="button" class="match-token-btn lose" data-token="LP">+ LP</button>
                                        <button type="button" class="match-token-btn lose" data-token="L">+ L</button>
                                        <button type="button" class="match-token-btn neutral" id="match_undo_btn">Undo</button>
                                        <button type="button" class="match-token-btn neutral" id="match_reset_btn">Reset</button>
                                    </div>
                                    <div class="match-seq match-builder-preview" id="match_builder_preview"></div>
                                </div>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Kartu Merah</label>
                                <input class="form-input" type="number" name="red_cards" min="0" value="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kartu Kuning</label>
                                <input class="form-input" type="number" name="yellow_cards" min="0" value="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kartu Hijau</label>
                                <input class="form-input" type="number" name="green_cards" min="0" value="0" required>
                            </div>
                        </div>

                        <div class="form-note">
                            Rumus poin: <strong>P = (M × 3) + (MP × 2) + (KP × 1)</strong>.
                        </div>
                        <div class="form-note">
                            TM otomatis: <strong>M + MP + S + KP + K</strong>. Kode match: W (win), WP (win penalty), D (draw), LP (lose penalty), L (lose).
                        </div>
                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Simpan Nilai Tim</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-id-card"></i> Input Kartu Pemain
                    </div>
                    <?php if ($eventId <= 0 || $selectedCategory === ''): ?>
                        <div class="form-note">Pilih Event dan Kategori dulu untuk input kartu pemain.</div>
                    <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="save_player">
                        <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                        <input type="hidden" name="sport_type" value="<?php echo htmlspecialchars($selectedCategory); ?>">

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Tim</label>
                                <select class="form-select" id="player_team_id" name="player_team_id" required>
                                    <option value="">Pilih tim</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?php echo (int)$team['id']; ?>">
                                            <?php echo htmlspecialchars($team['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Pemain</label>
                                <select class="form-select" id="player_id" name="player_id" required>
                                    <option value="">Pilih pemain</option>
                                    <?php foreach ($players as $player): ?>
                                        <option value="<?php echo (int)$player['id']; ?>" data-team="<?php echo (int)$player['team_id']; ?>">
                                            <?php echo htmlspecialchars($player['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kartu Kuning (Tambahan)</label>
                                <input class="form-input" type="number" name="player_yellow_cards" min="0" value="0" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Kartu Merah (Tambahan)</label>
                                <input class="form-input" type="number" name="player_red_cards" min="0" value="0" required>
                            </div>
                        </div>

                        <div class="form-grid form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Kartu Hijau (Tambahan)</label>
                                <input class="form-input" type="number" name="player_green_cards" min="0" value="0" required>
                            </div>
                        </div>

                        <div class="form-note">
                            Aturan: setiap akumulasi 2 kartu kuning, pemain suspend 1 minggu. Setelah lewat, status kembali boleh main.
                        </div>
                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Simpan Kartu Pemain</button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-table"></i> Klasemen Event
                    </div>
                    <?php if ($eventId <= 0): ?>
                        <div class="form-note">Pilih Event untuk menampilkan klasemen per kategori.</div>
                    <?php elseif (empty($standingsByCategory)): ?>
                        <div class="table-container">
                            <table class="data-table">
                                <tbody>
                                    <tr>
                                        <td>Belum ada data klasemen untuk event ini.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <?php foreach ($standingsByCategory as $group): ?>
                            <div class="form-note" style="margin: 0 0 10px 0; font-size: 15px; font-weight: 700; color: var(--primary);">
                                <?php echo htmlspecialchars($group['title'] ?? 'Klasemen Event'); ?>
                            </div>
                            <div class="table-container" style="margin-bottom: 14px;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Tim</th>
                                            <th>TM</th>
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
                                        <?php foreach (($group['rows'] ?? []) as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['team_name']); ?></td>
                                                <td><?php echo (int)$row['mn']; ?></td>
                                                <td><?php echo (int)$row['m']; ?></td>
                                                <td><?php echo (int)$row['mp']; ?></td>
                                                <td><?php echo (int)$row['s']; ?></td>
                                                <td><?php echo (int)$row['kp']; ?></td>
                                                <td><?php echo (int)$row['k']; ?></td>
                                                <td><?php echo (int)$row['gm']; ?></td>
                                                <td><?php echo (int)$row['gk']; ?></td>
                                                <td><?php echo (int)$row['sg']; ?></td>
                                                <td><strong><?php echo (int)$row['points']; ?></strong></td>
                                                <td><?php echo (int)($row['display_kls'] ?? $row['kls'] ?? 0); ?></td>
                                                <td><?php echo renderMatchHistoryBadges((string)($row['match_history'] ?? '')); ?></td>
                                                <td><span class="card-badge red"><?php echo (int)$row['red_cards']; ?></span></td>
                                                <td><span class="card-badge yellow"><?php echo (int)$row['yellow_cards']; ?></span></td>
                                                <td><span class="card-badge green"><?php echo (int)$row['green_cards']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-user-shield"></i> Status Suspend Pemain
                    </div>
                    <?php if ($eventId <= 0): ?>
                        <div class="form-note">Pilih Event untuk melihat status suspend pemain per kategori.</div>
                    <?php elseif (empty($playerCardsByCategory)): ?>
                        <div class="form-note">Belum ada data suspend pemain untuk event ini.</div>
                    <?php else: ?>
                        <?php foreach ($playerCardsByCategory as $categoryName => $rows): ?>
                            <div class="form-note" style="margin: 0 0 10px 0; font-size: 15px; font-weight: 700; color: var(--primary);">
                                Suspend <?php echo htmlspecialchars($categoryName); ?>
                            </div>
                            <div class="table-container" style="margin-bottom: 14px;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Pemain</th>
                                            <th>Tim</th>
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
                                                <td colspan="7">Belum ada data.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($rows as $row): ?>
                                                <?php $isSuspended = !empty($row['suspension_until']) && $row['suspension_until'] >= date('Y-m-d'); ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['player_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['team_name']); ?></td>
                                                    <td><span class="card-badge yellow"><?php echo (int)$row['yellow_cards']; ?></span></td>
                                                    <td><span class="card-badge red"><?php echo (int)$row['red_cards']; ?></span></td>
                                                    <td><span class="card-badge green"><?php echo (int)$row['green_cards']; ?></span></td>
                                                    <td><?php echo !empty($row['suspension_until']) ? htmlspecialchars(date('d M Y', strtotime($row['suspension_until']))) : '-'; ?></td>
                                                    <td>
                                                        <?php if ($isSuspended): ?>
                                                            <span class="badge-pill badge-ban">Tidak Boleh Main</span>
                                                        <?php else: ?>
                                                            <span class="badge-pill badge-ok">Boleh Main</span>
                                                        <?php endif; ?>
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
            </div>
        </div>
    </div>

    <script>
        const teamSelect = document.getElementById('player_team_id');
        const playerSelect = document.getElementById('player_id');
        if (teamSelect && playerSelect) {
            const allOptions = Array.from(playerSelect.options).map(function(o) {
                return o.cloneNode(true);
            });
            teamSelect.addEventListener('change', function() {
                const selectedTeam = teamSelect.value;
                playerSelect.innerHTML = '';
                allOptions.forEach(function(o, idx) {
                    if (idx === 0 || !selectedTeam || o.dataset.team === selectedTeam) {
                        playerSelect.appendChild(o.cloneNode(true));
                    }
                });
            });
        }

        const teamM = document.getElementById('team_m');
        const teamMp = document.getElementById('team_mp');
        const teamS = document.getElementById('team_s');
        const teamKp = document.getElementById('team_kp');
        const teamK = document.getElementById('team_k');
        const teamMn = document.getElementById('team_mn');
        const teamPointsPreview = document.getElementById('team_points_preview');
        const matchHistoryInput = document.getElementById('match_history_input');
        const matchBuilderPreview = document.getElementById('match_builder_preview');
        const matchTokenButtons = document.querySelectorAll('.match-token-btn[data-token]');
        const matchUndoBtn = document.getElementById('match_undo_btn');
        const matchResetBtn = document.getElementById('match_reset_btn');
        let matchTokens = [];

        function toNum(el) {
            if (!el) return 0;
            const v = parseInt(el.value, 10);
            return Number.isNaN(v) ? 0 : Math.max(0, v);
        }

        function updateTeamCalculatedFields() {
            const m = toNum(teamM);
            const mp = toNum(teamMp);
            const s = toNum(teamS);
            const kp = toNum(teamKp);
            const k = toNum(teamK);
            const mn = m + mp + s + kp + k;
            const points = (m * 3) + (mp * 2) + kp;

            if (teamMn) teamMn.value = mn;
            if (teamPointsPreview) teamPointsPreview.value = points;
        }

        [teamM, teamMp, teamS, teamKp, teamK].forEach(function(el) {
            if (el) el.addEventListener('input', updateTeamCalculatedFields);
        });
        updateTeamCalculatedFields();

        function tokenClass(token) {
            if (token === 'W' || token === 'WP') return 'win';
            if (token === 'L' || token === 'LP') return 'lose';
            return 'draw';
        }

        function renderMatchBuilder() {
            if (!matchBuilderPreview || !matchHistoryInput) return;
            matchHistoryInput.value = matchTokens.join(',');
            if (matchTokens.length === 0) {
                matchBuilderPreview.innerHTML = '<span class="match-placeholder">Belum ada match. Klik tombol +W / +L / dst.</span>';
                return;
            }
            matchBuilderPreview.innerHTML = '';
            matchTokens.forEach(function(token) {
                const pill = document.createElement('span');
                pill.className = 'match-pill ' + tokenClass(token);
                pill.textContent = token;
                matchBuilderPreview.appendChild(pill);
            });
        }

        if (matchHistoryInput) {
            matchTokens = (matchHistoryInput.value || '')
                .split(',')
                .map(function(v) { return v.trim().toUpperCase(); })
                .filter(function(v) { return ['W', 'WP', 'D', 'LP', 'L'].indexOf(v) !== -1; });
            renderMatchBuilder();
        }

        matchTokenButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const token = (btn.getAttribute('data-token') || '').toUpperCase();
                if (!token) return;
                matchTokens.push(token);
                renderMatchBuilder();
            });
        });

        if (matchUndoBtn) {
            matchUndoBtn.addEventListener('click', function() {
                if (matchTokens.length === 0) return;
                matchTokens.pop();
                renderMatchBuilder();
            });
        }

        if (matchResetBtn) {
            matchResetBtn.addEventListener('click', function() {
                matchTokens = [];
                renderMatchBuilder();
            });
        }
    </script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>
