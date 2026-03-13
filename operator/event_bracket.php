<?php
session_start();

$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'operator') {
    header("Location: ../login.php");
    exit;
}

if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

$operator_id = (int)($_SESSION['admin_id'] ?? 0);
$operator_event_id = (int)($_SESSION['event_id'] ?? 0);
$operator_event_name = 'Event Operator';
$operator_event_image = '';
$operator_event_is_active = true;
$current_page = 'event';

if ($operator_id > 0) {
    try {
        $stmtOperator = $conn->prepare("
            SELECT au.event_id, e.name AS event_name, e.image AS event_image, COALESCE(e.is_active, 1) AS event_is_active
            FROM admin_users au
            LEFT JOIN events e ON e.id = au.event_id
            WHERE au.id = ?
            LIMIT 1
        ");
        $stmtOperator->execute([$operator_id]);
        $operator_row = $stmtOperator->fetch(PDO::FETCH_ASSOC);
        $operator_event_id = (int)($operator_row['event_id'] ?? $operator_event_id);
        $operator_event_name = trim((string)($operator_row['event_name'] ?? '')) !== '' ? (string)$operator_row['event_name'] : 'Event Operator';
        $operator_event_image = trim((string)($operator_row['event_image'] ?? ''));
        $operator_event_is_active = ((int)($operator_row['event_is_active'] ?? 1) === 1);
        $_SESSION['event_id'] = $operator_event_id > 0 ? $operator_event_id : null;
    } catch (PDOException $e) {
        $operator_event_id = 0;
    }
}

$operator_read_only = ($operator_event_id > 0 && !$operator_event_is_active);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function ensureBracketSchema(PDO $conn): void
{
    $conn->exec("CREATE TABLE IF NOT EXISTS event_brackets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_id INT NOT NULL,
        sport_type VARCHAR(120) NOT NULL DEFAULT '',
        sf1_team1_id INT NULL,
        sf1_team2_id INT NULL,
        sf1_score1 INT NULL,
        sf1_score2 INT NULL,
        sf2_team1_id INT NULL,
        sf2_team2_id INT NULL,
        sf2_score1 INT NULL,
        sf2_score2 INT NULL,
        final_score1 INT NULL,
        final_score2 INT NULL,
        third_score1 INT NULL,
        third_score2 INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_event_sport (event_id, sport_type),
        INDEX idx_event_sport (event_id, sport_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $columnsToEnsure = [
        'sf1_challenge_id' => "ALTER TABLE event_brackets ADD COLUMN sf1_challenge_id INT NULL AFTER sf1_team2_id",
        'sf2_challenge_id' => "ALTER TABLE event_brackets ADD COLUMN sf2_challenge_id INT NULL AFTER sf2_team2_id",
        'final_challenge_id' => "ALTER TABLE event_brackets ADD COLUMN final_challenge_id INT NULL AFTER sf2_score2",
        'third_challenge_id' => "ALTER TABLE event_brackets ADD COLUMN third_challenge_id INT NULL AFTER final_challenge_id",
    ];

    foreach ($columnsToEnsure as $columnName => $alterSql) {
        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'event_brackets'
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$columnName]);
        $exists = (int)$stmt->fetchColumn() > 0;
        if (!$exists) {
            $conn->exec($alterSql);
        }
    }
}

function hasColumnPDO(PDO $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function findBracketChallengeIdAuto(PDO $conn, int $eventId, string $category, int $teamA, int $teamB, ?int $scoreA = null, ?int $scoreB = null): int
{
    if ($teamA <= 0 || $teamB <= 0 || trim($category) === '') {
        return 0;
    }

    $hasEventIdColumn = hasColumnPDO($conn, 'challenges', 'event_id');

    if ($scoreA !== null && $scoreB !== null) {
        if ($hasEventIdColumn && $eventId > 0) {
            $sqlScore = "SELECT id
                         FROM challenges
                         WHERE event_id = ?
                           AND sport_type = ?
                           AND (
                             (challenger_id = ? AND opponent_id = ? AND challenger_score = ? AND opponent_score = ?)
                             OR
                             (challenger_id = ? AND opponent_id = ? AND challenger_score = ? AND opponent_score = ?)
                           )
                         ORDER BY challenge_date DESC, id DESC
                         LIMIT 1";
            $stmtScore = $conn->prepare($sqlScore);
            $stmtScore->execute([$eventId, $category, $teamA, $teamB, $scoreA, $scoreB, $teamB, $teamA, $scoreB, $scoreA]);
        } else {
            $sqlScore = "SELECT id
                         FROM challenges
                         WHERE sport_type = ?
                           AND (
                             (challenger_id = ? AND opponent_id = ? AND challenger_score = ? AND opponent_score = ?)
                             OR
                             (challenger_id = ? AND opponent_id = ? AND challenger_score = ? AND opponent_score = ?)
                           )
                         ORDER BY challenge_date DESC, id DESC
                         LIMIT 1";
            $stmtScore = $conn->prepare($sqlScore);
            $stmtScore->execute([$category, $teamA, $teamB, $scoreA, $scoreB, $teamB, $teamA, $scoreB, $scoreA]);
        }
        $rowScore = $stmtScore->fetch(PDO::FETCH_ASSOC);
        if (!empty($rowScore['id'])) {
            return (int)$rowScore['id'];
        }
    }

    if ($hasEventIdColumn && $eventId > 0) {
        $sql = "SELECT id
                FROM challenges
                WHERE event_id = ?
                  AND sport_type = ?
                  AND ((challenger_id = ? AND opponent_id = ?) OR (challenger_id = ? AND opponent_id = ?))
                ORDER BY challenge_date DESC, id DESC
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$eventId, $category, $teamA, $teamB, $teamB, $teamA]);
    } else {
        $sql = "SELECT id
                FROM challenges
                WHERE sport_type = ?
                  AND ((challenger_id = ? AND opponent_id = ?) OR (challenger_id = ? AND opponent_id = ?))
                ORDER BY challenge_date DESC, id DESC
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$category, $teamA, $teamB, $teamB, $teamA]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['id'] ?? 0);
}

function loadEventCategories(PDO $conn, int $eventId): array
{
    if ($eventId <= 0) return [];
    $stmt = $conn->prepare("SELECT DISTINCT sport_type
                            FROM challenges
                            WHERE event_id = ?
                              AND status IN ('accepted', 'completed')
                              AND sport_type IS NOT NULL
                              AND sport_type <> ''
                            ORDER BY sport_type ASC");
    $stmt->execute([$eventId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $name = trim((string)($row['sport_type'] ?? ''));
        if ($name !== '') $result[] = $name;
    }
    return $result;
}

function loadTeamsByEventCategory(PDO $conn, int $eventId, string $category): array
{
    if ($eventId <= 0 || $category === '') return [];
    $stmt = $conn->prepare("SELECT DISTINCT t.id, t.name, t.logo
                            FROM teams t
                            INNER JOIN (
                                SELECT challenger_id AS team_id FROM challenges WHERE event_id = ? AND sport_type = ? AND status IN ('accepted', 'completed')
                                UNION
                                SELECT opponent_id AS team_id FROM challenges WHERE event_id = ? AND sport_type = ? AND status IN ('accepted', 'completed')
                            ) src ON src.team_id = t.id
                            ORDER BY t.name ASC");
    $stmt->execute([$eventId, $category, $eventId, $category]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function parseNullableScore($value): ?int
{
    $str = trim((string)$value);
    if ($str === '') return null;
    $num = (int)$str;
    return max(0, $num);
}

function isAllCategorySelection(string $category): bool
{
    $normalized = strtolower(trim($category));
    return in_array($normalized, ['liga', 'all', 'semua'], true);
}

function filterSpecificCategories(array $categories): array
{
    $result = [];
    foreach ($categories as $category) {
        $name = trim((string)$category);
        if ($name === '' || isAllCategorySelection($name)) {
            continue;
        }
        $result[] = $name;
    }
    return array_values(array_unique($result));
}

function resolveMatchOutcome(int $team1, int $team2, ?int $score1, ?int $score2): array
{
    if ($team1 <= 0 || $team2 <= 0) {
        return ['winner' => 0, 'loser' => 0, 'status' => 'pending'];
    }
    if ($score1 === null || $score2 === null) {
        return ['winner' => 0, 'loser' => 0, 'status' => 'pending'];
    }
    if ($score1 === $score2) {
        return ['winner' => 0, 'loser' => 0, 'status' => 'tie'];
    }
    if ($score1 > $score2) {
        return ['winner' => $team1, 'loser' => $team2, 'status' => 'done'];
    }
    return ['winner' => $team2, 'loser' => $team1, 'status' => 'done'];
}

function teamNameById(array $teamMap, int $teamId): string
{
    if ($teamId <= 0) return '-';
    return (string)($teamMap[$teamId] ?? ('Team #' . $teamId));
}

function resolveTeamLogoUrlAdmin($logoFile): string
{
    $logoFile = trim((string)$logoFile);
    $logoFile = str_replace('\\', '/', $logoFile);
    if ($logoFile === '') {
        return '../images/teams/default-team.png';
    }
    if (preg_match('#^https?://#i', $logoFile)) {
        return $logoFile;
    }
    if (strpos($logoFile, 'uploads/') === 0) {
        return '../' . ltrim($logoFile, '/');
    }
    if (strpos($logoFile, '/uploads/') === 0) {
        return '..' . $logoFile;
    }
    if (strpos($logoFile, 'images/teams/') === 0) {
        return '../' . ltrim($logoFile, '/');
    }
    if (strpos($logoFile, '/images/teams/') === 0) {
        return '..' . $logoFile;
    }
    if (strpos($logoFile, '/') === 0) {
        return '..' . $logoFile;
    }
    $logoName = ltrim($logoFile, '/');
    if (preg_match('/^[A-Za-z]:\//', $logoName) || strpos($logoName, '/') !== false) {
        $logoName = basename($logoName);
    }
    return '../images/teams/' . $logoName;
}

function renderTeamLabelById(array $teamMap, array $teamLogoMap, int $teamId): string
{
    if ($teamId <= 0) return '<span class="d-team"><span class="d-team-logo d-team-logo-placeholder"></span><span class="d-team-name">-</span></span>';
    $name = (string)($teamMap[$teamId] ?? ('Team #' . $teamId));
    $logoPath = resolveTeamLogoUrlAdmin($teamLogoMap[$teamId] ?? '');

    return '<span class="d-team"><img class="d-team-logo" src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" onerror="this.onerror=null;this.src=\'../images/teams/default-team.png\'"><span class="d-team-name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span></span>';
}

ensureBracketSchema($conn);

$events = [];
if ($operator_event_id > 0) {
    try {
        $eventStmt = $conn->prepare("SELECT id, name FROM events WHERE id = ? LIMIT 1");
        $eventStmt->execute([$operator_event_id]);
        $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $events = [];
    }
}
$eventMap = [];
foreach ($events as $event) {
    $eventMap[(int)$event['id']] = (string)($event['name'] ?? '');
}

$errors = [];
$success = '';

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : $operator_event_id;
$eventId = $operator_event_id > 0 ? $operator_event_id : 0;
$selectedCategory = trim((string)($_GET['sport_type'] ?? ''));
if ($eventId <= 0) {
    $selectedCategory = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $errors[] = 'Token invalid.';
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $postedEventId = (int)($_POST['event_id'] ?? 0);
    $eventId = $operator_event_id > 0 ? $operator_event_id : $postedEventId;
    $selectedCategory = trim((string)($_POST['sport_type'] ?? ''));
    if ($eventId <= 0) {
        $selectedCategory = '';
    }
    if ($eventId <= 0) {
        $errors[] = 'Event operator belum ditetapkan.';
    }
    if ($operator_read_only) {
        $errors[] = 'Event sedang non-aktif. Mode operator hanya lihat data.';
    }
    $effectiveCategory = isAllCategorySelection($selectedCategory) ? '' : $selectedCategory;

    $categories = loadEventCategories($conn, $eventId);
    if ($eventId <= 0 || !isset($eventMap[$eventId])) {
        $errors[] = 'Event wajib dipilih.';
    }
    if ($selectedCategory === '' || (!isAllCategorySelection($selectedCategory) && !in_array($selectedCategory, $categories, true))) {
        $errors[] = 'Kategori wajib dipilih.';
    }
    if (in_array($action, ['save_bracket', 'clear_bracket'], true) && $effectiveCategory === '') {
        $errors[] = 'Pilih kategori spesifik (U-13/U-16/dll), bukan Liga.';
    }

    $teams = loadTeamsByEventCategory($conn, $eventId, $effectiveCategory);
    $teamMap = [];
    $teamLogoMap = [];
    foreach ($teams as $team) {
        $teamMap[(int)$team['id']] = (string)($team['name'] ?? '-');
        $teamLogoMap[(int)$team['id']] = (string)($team['logo'] ?? '');
    }

    if ($action === 'clear_bracket' && empty($errors)) {
        $stmt = $conn->prepare("DELETE FROM event_brackets WHERE event_id = ? AND sport_type = ?");
        $stmt->execute([$eventId, $effectiveCategory]);
        $success = 'Bracket berhasil direset.';
    }

    if ($action === 'save_bracket' && empty($errors)) {
        $sf1Team1 = (int)($_POST['sf1_team1_id'] ?? 0);
        $sf1Team2 = (int)($_POST['sf1_team2_id'] ?? 0);
        $sf2Team1 = (int)($_POST['sf2_team1_id'] ?? 0);
        $sf2Team2 = (int)($_POST['sf2_team2_id'] ?? 0);

        $sf1Score1 = parseNullableScore($_POST['sf1_score1'] ?? null);
        $sf1Score2 = parseNullableScore($_POST['sf1_score2'] ?? null);
        $sf2Score1 = parseNullableScore($_POST['sf2_score1'] ?? null);
        $sf2Score2 = parseNullableScore($_POST['sf2_score2'] ?? null);
        $finalScore1 = parseNullableScore($_POST['final_score1'] ?? null);
        $finalScore2 = parseNullableScore($_POST['final_score2'] ?? null);
        $thirdScore1 = parseNullableScore($_POST['third_score1'] ?? null);
        $thirdScore2 = parseNullableScore($_POST['third_score2'] ?? null);

        $allSelected = [$sf1Team1, $sf1Team2, $sf2Team1, $sf2Team2];
        foreach ($allSelected as $teamId) {
            if ($teamId <= 0 || !isset($teamMap[$teamId])) {
                $errors[] = 'Semua slot semifinal harus diisi tim valid.';
                break;
            }
        }
        if ($sf1Team1 === $sf1Team2 || $sf2Team1 === $sf2Team2) {
            $errors[] = 'Tim di match semifinal yang sama tidak boleh sama.';
        }
        if (count(array_unique($allSelected)) !== 4) {
            $errors[] = 'Empat slot semifinal harus diisi empat tim berbeda.';
        }

        if ($sf1Score1 !== null && $sf1Score2 !== null && $sf1Score1 === $sf1Score2) {
            $errors[] = 'Skor Semifinal 1 tidak boleh seri.';
        }
        if ($sf2Score1 !== null && $sf2Score2 !== null && $sf2Score1 === $sf2Score2) {
            $errors[] = 'Skor Semifinal 2 tidak boleh seri.';
        }
        if ($finalScore1 !== null && $finalScore2 !== null && $finalScore1 === $finalScore2) {
            $errors[] = 'Skor Final tidak boleh seri.';
        }
        if ($thirdScore1 !== null && $thirdScore2 !== null && $thirdScore1 === $thirdScore2) {
            $errors[] = 'Skor 3rd Place tidak boleh seri.';
        }

        $sf1Outcome = resolveMatchOutcome($sf1Team1, $sf1Team2, $sf1Score1, $sf1Score2);
        $sf2Outcome = resolveMatchOutcome($sf2Team1, $sf2Team2, $sf2Score1, $sf2Score2);
        $finalTeam1 = (int)($sf1Outcome['winner'] ?? 0);
        $finalTeam2 = (int)($sf2Outcome['winner'] ?? 0);
        $thirdTeam1 = (int)($sf1Outcome['loser'] ?? 0);
        $thirdTeam2 = (int)($sf2Outcome['loser'] ?? 0);

        $sf1ChallengeId = findBracketChallengeIdAuto($conn, $eventId, $effectiveCategory, $sf1Team1, $sf1Team2, $sf1Score1, $sf1Score2);
        $sf2ChallengeId = findBracketChallengeIdAuto($conn, $eventId, $effectiveCategory, $sf2Team1, $sf2Team2, $sf2Score1, $sf2Score2);
        $finalChallengeId = findBracketChallengeIdAuto($conn, $eventId, $effectiveCategory, $finalTeam1, $finalTeam2, $finalScore1, $finalScore2);
        $thirdChallengeId = findBracketChallengeIdAuto($conn, $eventId, $effectiveCategory, $thirdTeam1, $thirdTeam2, $thirdScore1, $thirdScore2);

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO event_brackets (
                                        event_id, sport_type,
                                        sf1_team1_id, sf1_team2_id, sf1_challenge_id, sf1_score1, sf1_score2,
                                        sf2_team1_id, sf2_team2_id, sf2_challenge_id, sf2_score1, sf2_score2,
                                        final_challenge_id, final_score1, final_score2,
                                        third_challenge_id, third_score1, third_score2
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE
                                        sf1_team1_id = VALUES(sf1_team1_id),
                                        sf1_team2_id = VALUES(sf1_team2_id),
                                        sf1_challenge_id = VALUES(sf1_challenge_id),
                                        sf1_score1 = VALUES(sf1_score1),
                                        sf1_score2 = VALUES(sf1_score2),
                                        sf2_team1_id = VALUES(sf2_team1_id),
                                        sf2_team2_id = VALUES(sf2_team2_id),
                                        sf2_challenge_id = VALUES(sf2_challenge_id),
                                        sf2_score1 = VALUES(sf2_score1),
                                        sf2_score2 = VALUES(sf2_score2),
                                        final_challenge_id = VALUES(final_challenge_id),
                                        final_score1 = VALUES(final_score1),
                                        final_score2 = VALUES(final_score2),
                                        third_challenge_id = VALUES(third_challenge_id),
                                        third_score1 = VALUES(third_score1),
                                        third_score2 = VALUES(third_score2),
                                        updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([
                $eventId, $effectiveCategory,
                $sf1Team1, $sf1Team2, ($sf1ChallengeId > 0 ? $sf1ChallengeId : null), $sf1Score1, $sf1Score2,
                $sf2Team1, $sf2Team2, ($sf2ChallengeId > 0 ? $sf2ChallengeId : null), $sf2Score1, $sf2Score2,
                ($finalChallengeId > 0 ? $finalChallengeId : null), $finalScore1, $finalScore2,
                ($thirdChallengeId > 0 ? $thirdChallengeId : null), $thirdScore1, $thirdScore2
            ]);
            $success = 'Bracket berhasil disimpan.';
        }
    }
}

$categories = loadEventCategories($conn, $eventId);
if ($selectedCategory !== '' && !isAllCategorySelection($selectedCategory) && !in_array($selectedCategory, $categories, true)) {
    $selectedCategory = '';
}
$effectiveCategory = isAllCategorySelection($selectedCategory) ? '' : $selectedCategory;
$specificCategories = filterSpecificCategories($categories);

$teams = loadTeamsByEventCategory($conn, $eventId, $effectiveCategory);
$teamMap = [];
$teamLogoMap = [];
foreach ($teams as $team) {
    $teamMap[(int)$team['id']] = (string)($team['name'] ?? '-');
    $teamLogoMap[(int)$team['id']] = (string)($team['logo'] ?? '');
}

$bracket = null;
if ($eventId > 0 && $effectiveCategory !== '') {
    $stmt = $conn->prepare("SELECT * FROM event_brackets WHERE event_id = ? AND sport_type = ? LIMIT 1");
    $stmt->execute([$eventId, $effectiveCategory]);
    $bracket = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$allBracketViews = [];
if ($eventId > 0 && $effectiveCategory === '' && !empty($specificCategories)) {
    $allBracketStmt = $conn->prepare("SELECT * FROM event_brackets WHERE event_id = ? AND sport_type = ? LIMIT 1");
    foreach ($specificCategories as $categoryName) {
        $categoryTeams = loadTeamsByEventCategory($conn, $eventId, (string)$categoryName);
        $categoryTeamMap = [];
        $categoryTeamLogoMap = [];
        foreach ($categoryTeams as $team) {
            $categoryTeamMap[(int)$team['id']] = (string)($team['name'] ?? '-');
            $categoryTeamLogoMap[(int)$team['id']] = (string)($team['logo'] ?? '');
        }

        $allBracketStmt->execute([$eventId, (string)$categoryName]);
        $categoryBracket = $allBracketStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $cSf1Team1 = (int)($categoryBracket['sf1_team1_id'] ?? 0);
        $cSf1Team2 = (int)($categoryBracket['sf1_team2_id'] ?? 0);
        $cSf2Team1 = (int)($categoryBracket['sf2_team1_id'] ?? 0);
        $cSf2Team2 = (int)($categoryBracket['sf2_team2_id'] ?? 0);
        $cSf1Score1 = $categoryBracket['sf1_score1'] ?? null;
        $cSf1Score2 = $categoryBracket['sf1_score2'] ?? null;
        $cSf2Score1 = $categoryBracket['sf2_score1'] ?? null;
        $cSf2Score2 = $categoryBracket['sf2_score2'] ?? null;
        $cFinalScore1 = $categoryBracket['final_score1'] ?? null;
        $cFinalScore2 = $categoryBracket['final_score2'] ?? null;
        $cThirdScore1 = $categoryBracket['third_score1'] ?? null;
        $cThirdScore2 = $categoryBracket['third_score2'] ?? null;

        $cSf1Outcome = resolveMatchOutcome($cSf1Team1, $cSf1Team2, $cSf1Score1 !== null ? (int)$cSf1Score1 : null, $cSf1Score2 !== null ? (int)$cSf1Score2 : null);
        $cSf2Outcome = resolveMatchOutcome($cSf2Team1, $cSf2Team2, $cSf2Score1 !== null ? (int)$cSf2Score1 : null, $cSf2Score2 !== null ? (int)$cSf2Score2 : null);

        $cFinalTeam1 = (int)$cSf1Outcome['winner'];
        $cFinalTeam2 = (int)$cSf2Outcome['winner'];
        $cThirdTeam1 = (int)$cSf1Outcome['loser'];
        $cThirdTeam2 = (int)$cSf2Outcome['loser'];

        $cFinalOutcome = resolveMatchOutcome($cFinalTeam1, $cFinalTeam2, $cFinalScore1 !== null ? (int)$cFinalScore1 : null, $cFinalScore2 !== null ? (int)$cFinalScore2 : null);
        $cThirdOutcome = resolveMatchOutcome($cThirdTeam1, $cThirdTeam2, $cThirdScore1 !== null ? (int)$cThirdScore1 : null, $cThirdScore2 !== null ? (int)$cThirdScore2 : null);

        $allBracketViews[] = [
            'category' => (string)$categoryName,
            'teamMap' => $categoryTeamMap,
            'teamLogoMap' => $categoryTeamLogoMap,
            'sf1Team1' => $cSf1Team1,
            'sf1Team2' => $cSf1Team2,
            'sf2Team1' => $cSf2Team1,
            'sf2Team2' => $cSf2Team2,
            'sf1Score1' => $cSf1Score1,
            'sf1Score2' => $cSf1Score2,
            'sf2Score1' => $cSf2Score1,
            'sf2Score2' => $cSf2Score2,
            'finalScore1' => $cFinalScore1,
            'finalScore2' => $cFinalScore2,
            'thirdScore1' => $cThirdScore1,
            'thirdScore2' => $cThirdScore2,
            'sf1Outcome' => $cSf1Outcome,
            'sf2Outcome' => $cSf2Outcome,
            'finalTeam1' => $cFinalTeam1,
            'finalTeam2' => $cFinalTeam2,
            'thirdTeam1' => $cThirdTeam1,
            'thirdTeam2' => $cThirdTeam2,
            'finalOutcome' => $cFinalOutcome,
            'thirdOutcome' => $cThirdOutcome
        ];
    }
}

$sf1Team1 = (int)($bracket['sf1_team1_id'] ?? 0);
$sf1Team2 = (int)($bracket['sf1_team2_id'] ?? 0);
$sf2Team1 = (int)($bracket['sf2_team1_id'] ?? 0);
$sf2Team2 = (int)($bracket['sf2_team2_id'] ?? 0);
$sf1ChallengeId = (int)($bracket['sf1_challenge_id'] ?? 0);
$sf2ChallengeId = (int)($bracket['sf2_challenge_id'] ?? 0);
$finalChallengeId = (int)($bracket['final_challenge_id'] ?? 0);
$thirdChallengeId = (int)($bracket['third_challenge_id'] ?? 0);
$sf1Score1 = $bracket['sf1_score1'] ?? null;
$sf1Score2 = $bracket['sf1_score2'] ?? null;
$sf2Score1 = $bracket['sf2_score1'] ?? null;
$sf2Score2 = $bracket['sf2_score2'] ?? null;
$finalScore1 = $bracket['final_score1'] ?? null;
$finalScore2 = $bracket['final_score2'] ?? null;
$thirdScore1 = $bracket['third_score1'] ?? null;
$thirdScore2 = $bracket['third_score2'] ?? null;

$sf1Outcome = resolveMatchOutcome($sf1Team1, $sf1Team2, $sf1Score1 !== null ? (int)$sf1Score1 : null, $sf1Score2 !== null ? (int)$sf1Score2 : null);
$sf2Outcome = resolveMatchOutcome($sf2Team1, $sf2Team2, $sf2Score1 !== null ? (int)$sf2Score1 : null, $sf2Score2 !== null ? (int)$sf2Score2 : null);

$finalTeam1 = (int)$sf1Outcome['winner'];
$finalTeam2 = (int)$sf2Outcome['winner'];
$thirdTeam1 = (int)$sf1Outcome['loser'];
$thirdTeam2 = (int)$sf2Outcome['loser'];

$finalOutcome = resolveMatchOutcome($finalTeam1, $finalTeam2, $finalScore1 !== null ? (int)$finalScore1 : null, $finalScore2 !== null ? (int)$finalScore2 : null);
$thirdOutcome = resolveMatchOutcome($thirdTeam1, $thirdTeam2, $thirdScore1 !== null ? (int)$thirdScore1 : null, $thirdScore2 !== null ? (int)$thirdScore2 : null);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Bracket - Area Operator</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../pelatih/css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../pelatih/css/style.css'); ?>">
    <link rel="stylesheet" href="css/event_bracket.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/event_bracket.css'); ?>">
</head>
<body>
    <div class="menu-overlay"></div>
    <button class="mobile-menu-toggle" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </button>

    <div class="wrapper">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
        
        <div class="main">
            <!-- TOPBAR -->
            <div class="topbar reveal">
                <div class="greeting">
                    <h1>Event Bracket 🗓️</h1>
                    <p>Mode 4 tim: Semifinal, Final, dan perebutan juara 3.</p>
                </div>
                <div class="user-actions">
                    <a href="../operator/logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Keluar
                    </a>
                </div>
            </div>

            <div class="event-hub">
                <!-- Editorial Header -->
                <header class="dashboard-hero reveal d-1">
                    <div class="hero-content">
                        <span class="hero-label">Manajemen Kompetisi</span>
                        <h1 class="hero-title">Visualisasi Bracket</h1>
                        <p class="hero-description">Kelola alur turnamen, dari babak semifinal hingga partai puncak untuk event <strong><?php echo htmlspecialchars($operator_event_name); ?></strong>.</p>
                    </div>
                    <div class="hero-actions">
                        <a href="event.php" class="btn-premium btn-cancel">
                            <i class="fas fa-arrow-left"></i> Kembali ke Hub
                        </a>
                    </div>
                </header>

                <?php if ($success !== ''): ?>
                    <div class="alert alert-success reveal d-1">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger reveal d-1">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endforeach; ?>

                <?php if ($operator_read_only): ?>
                    <div class="alert alert-danger reveal d-1">
                        <i class="fas fa-lock"></i>
                        <span>Event Anda sedang non-aktif. Mode operator hanya lihat data.</span>
                    </div>
                <?php endif; ?>

                <!-- SELECTION CARD -->
                <div class="heritage-card reveal d-2">
                    <div class="section-title" style="margin-bottom: 24px; color: var(--heritage-text); font-family: var(--font-display); font-weight: 800; font-size: 1.5rem;">
                        <i class="fas fa-filter" style="color: var(--heritage-gold);"></i> Filter Data
                    </div>
                    <form method="get">
                        <div class="form-grid form-grid-2">
                            <div class="form-group">
                                <label>Pilih Event</label>
                                <select class="form-select" name="event_id" onchange="this.form.submit()" disabled>
                                    <option value=""><?php echo $eventId > 0 ? 'Event Operator' : 'Event belum ditetapkan'; ?></option>
                                </select>
                                <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                            </div>
                            <div class="form-group">
                                <label>Pilih Kategori</label>
                                <select class="form-select" name="sport_type" onchange="this.form.submit()">
                                    <option value="">Pilih Kategori</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $selectedCategory === $category ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if ($eventId <= 0): ?>
                    <div class="heritage-card reveal d-2">
                        <p class="muted">Pilih event dan kategori dulu untuk mengelola bracket.</p>
                    </div>
                <?php elseif ($effectiveCategory === ''): ?>
                    <div class="heritage-card reveal d-2">
                        <div class="section-header" style="margin-bottom: 24px;">
                            <div class="section-title-wrap">
                                <h2 class="section-title">Preview Semua Kategori</h2>
                                <div class="section-line"></div>
                            </div>
                        </div>
                        
                        <?php if (empty($specificCategories)): ?>
                            <p class="muted">Belum ada kategori spesifik untuk event ini.</p>
                        <?php else: ?>
                            <p class="muted" style="margin-bottom: 32px;">Mode Liga menampilkan semua kategori. Klik tombol "Kelola" pada kategori terkait untuk edit bracket.</p>
                            
                            <?php foreach ($allBracketViews as $view): ?>
                                <?php
                                $vCategory = (string)$view['category'];
                                $vTeamMap = (array)$view['teamMap'];
                                $vTeamLogoMap = (array)$view['teamLogoMap'];
                                $vSf1Team1 = (int)$view['sf1Team1'];
                                $vSf1Team2 = (int)$view['sf1Team2'];
                                $vSf2Team1 = (int)$view['sf2Team1'];
                                $vSf2Team2 = (int)$view['sf2Team2'];
                                $vSf1Score1 = $view['sf1Score1'];
                                $vSf1Score2 = $view['sf1Score2'];
                                $vSf2Score1 = $view['sf2Score1'];
                                $vSf2Score2 = $view['sf2Score2'];
                                $vFinalScore1 = $view['finalScore1'];
                                $vFinalScore2 = $view['finalScore2'];
                                $vThirdScore1 = $view['thirdScore1'];
                                $vThirdScore2 = $view['thirdScore2'];
                                $vSf1Outcome = (array)$view['sf1Outcome'];
                                $vSf2Outcome = (array)$view['sf2Outcome'];
                                $vFinalTeam1 = (int)$view['finalTeam1'];
                                $vFinalTeam2 = (int)$view['finalTeam2'];
                                $vThirdTeam1 = (int)$view['thirdTeam1'];
                                $vThirdTeam2 = (int)$view['thirdTeam2'];
                                $vFinalOutcome = (array)$view['finalOutcome'];
                                $vThirdOutcome = (array)$view['thirdOutcome'];
                                $vSf1Done = ((string)($vSf1Outcome['status'] ?? '') === 'done');
                                $vSf2Done = ((string)($vSf2Outcome['status'] ?? '') === 'done');
                                $vFinalDone = ((string)($vFinalOutcome['status'] ?? '') === 'done');
                                $vThirdDone = ((string)($vThirdOutcome['status'] ?? '') === 'done');

                                $vFinalTopRankText = '1st';
                                $vFinalBottomRankText = '2nd';
                                if ($vFinalDone && (int)($vFinalOutcome['winner'] ?? 0) === $vFinalTeam2) {
                                    $vFinalTopRankText = '2nd';
                                    $vFinalBottomRankText = '1st';
                                }
                                $vThirdTopRankText = '3rd';
                                $vThirdBottomRankText = '4th';
                                if ($vThirdDone && (int)($vThirdOutcome['winner'] ?? 0) === $vThirdTeam2) {
                                    $vThirdTopRankText = '4th';
                                    $vThirdBottomRankText = '3rd';
                                }
                                ?>
                                <div class="diagram-wrap" style="margin-bottom: 64px;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
                                        <div>
                                            <div class="diagram-title"><?php echo htmlspecialchars($vCategory); ?></div>
                                            <div class="diagram-note">Visualisasi jalur turnamen babak gugur.</div>
                                        </div>
                                        <a class="btn-premium btn-save" href="event_bracket.php?event_id=<?php echo (int)$eventId; ?>&sport_type=<?php echo urlencode($vCategory); ?>" style="height: 44px; padding: 0 20px; font-size: 0.9rem;">
                                            <i class="fas fa-edit"></i> Kelola
                                        </a>
                                    </div>

                                    <div class="diagram-canvas">
                                        <!-- Semifinal 1 -->
                                        <div class="d-match" style="left:10px; top:48px;">
                                            <div class="d-row <?php echo $vSf1Done && (int)$vSf1Outcome['winner'] === $vSf1Team1 ? 'win' : ($vSf1Done ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$vSf1Team1; ?>">
                                                <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vSf1Team1); ?></div>
                                                <div class="d-score"><?php echo $vSf1Score1 !== null ? (int)$vSf1Score1 : '-'; ?></div>
                                            </div>
                                            <div class="d-row <?php echo $vSf1Done && (int)$vSf1Outcome['winner'] === $vSf1Team2 ? 'win' : ($vSf1Done ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$vSf1Team2; ?>">
                                                <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vSf1Team2); ?></div>
                                                <div class="d-score"><?php echo $vSf1Score2 !== null ? (int)$vSf1Score2 : '-'; ?></div>
                                            </div>
                                        </div>

                                        <!-- Semifinal 2 -->
                                        <div class="d-match" style="left:10px; top:240px;">
                                            <div class="d-row <?php echo $vSf2Done && (int)$vSf2Outcome['winner'] === $vSf2Team1 ? 'win' : ($vSf2Done ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$vSf2Team1; ?>">
                                                <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vSf2Team1); ?></div>
                                                <div class="d-score"><?php echo $vSf2Score1 !== null ? (int)$vSf2Score1 : '-'; ?></div>
                                            </div>
                                            <div class="d-row <?php echo $vSf2Done && (int)$vSf2Outcome['winner'] === $vSf2Team2 ? 'win' : ($vSf2Done ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$vSf2Team2; ?>">
                                                <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vSf2Team2); ?></div>
                                                <div class="d-score"><?php echo $vSf2Score2 !== null ? (int)$vSf2Score2 : '-'; ?></div>
                                            </div>
                                        </div>

                                        <!-- Final -->
                                        <div class="d-match" style="left:460px; top:145px; border-color: var(--heritage-gold); border-width: 2px;">
                                            <div class="d-row <?php echo $vFinalDone && (int)$vFinalOutcome['winner'] === $vFinalTeam1 ? 'win' : ($vFinalDone ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$vFinalTeam1; ?>">
                                                <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vFinalTeam1); ?></div>
                                                <div class="d-score"><?php echo $vFinalScore1 !== null ? (int)$vFinalScore1 : '-'; ?></div>
                                            </div>
                                            <div class="d-row <?php echo $vFinalDone && (int)$vFinalOutcome['winner'] === $vFinalTeam2 ? 'win' : ($vFinalDone ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$vFinalTeam2; ?>">
                                                <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vFinalTeam2); ?></div>
                                                <div class="d-score"><?php echo $vFinalScore2 !== null ? (int)$vFinalScore2 : '-'; ?></div>
                                            </div>
                                            <span class="d-rank first"><?php echo $vFinalTopRankText; ?></span>
                                            <span class="d-rank second"><?php echo $vFinalBottomRankText; ?></span>
                                        </div>

                                        <!-- Juara 3 -->
                                        <div class="d-match" style="left:460px; top:380px;">
                                            <div class="d-row <?php echo $vThirdDone && (int)$vThirdOutcome['winner'] === $vThirdTeam1 ? 'win' : ($vThirdDone ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$vThirdTeam1; ?>">
                                                <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vThirdTeam1); ?></div>
                                                <div class="d-score"><?php echo $vThirdScore1 !== null ? (int)$vThirdScore1 : '-'; ?></div>
                                            </div>
                                            <div class="d-row <?php echo $vThirdDone && (int)$vThirdOutcome['winner'] === $vThirdTeam2 ? 'win' : ($vThirdDone ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$vThirdTeam2; ?>">
                                                <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vThirdTeam2); ?></div>
                                                <div class="d-score"><?php echo $vThirdScore2 !== null ? (int)$vThirdScore2 : '-'; ?></div>
                                            </div>
                                            <span class="d-rank third"><?php echo $vThirdTopRankText; ?></span>
                                            <span class="d-rank fourth"><?php echo $vThirdBottomRankText; ?></span>
                                        </div>

                                        <!-- Lines -->
                                        <div class="d-line gold" style="left:250px; top:74px; width:110px; border-top-width:2px;"></div>
                                        <div class="d-line gold" style="left:360px; top:74px; height:98px; border-right-width:2px;"></div>
                                        <div class="d-line gold" style="left:360px; top:172px; width:100px; border-top-width:2px;"></div>

                                        <div class="d-line" style="left:250px; top:266px; width:110px; border-top-width:2px;"></div>
                                        <div class="d-line" style="left:360px; top:172px; height:94px; border-right-width:2px;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- SETUP FORM -->
                    <div class="heritage-card reveal d-2">
                        <div class="section-title" style="margin-bottom: 24px; color: var(--heritage-text); font-family: var(--font-display); font-weight: 800; font-size: 1.5rem;">
                            <i class="fas fa-cog" style="color: var(--heritage-gold);"></i> Setup Bracket (4 Tim)
                        </div>
                        
                        <?php if (count($teams) < 4): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle"></i>
                                <span>Tim kategori ini kurang dari 4. Minimal 4 tim untuk mengaktifkan mode bracket.</span>
                            </div>
                        <?php else: ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="save_bracket">
                                <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                                <input type="hidden" name="sport_type" value="<?php echo htmlspecialchars($effectiveCategory); ?>">

                                <div class="setup-grid">
                                    <div class="match-setup-card">
                                        <div class="match-setup-title"><i class="fas fa-play"></i> Semifinal 1</div>
                                        <div class="form-grid form-grid-2">
                                            <div class="form-group">
                                                <label>Team A</label>
                                                <select class="form-select" id="sf1_team1_id" name="sf1_team1_id" required>
                                                    <option value="">Pilih Tim</option>
                                                    <?php foreach ($teams as $team): ?>
                                                        <option value="<?php echo (int)$team['id']; ?>" <?php echo $sf1Team1 === (int)$team['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars((string)($team['name'] ?? '-')); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Skor A</label>
                                                <input class="form-input" id="sf1_score1" type="number" min="0" name="sf1_score1" value="<?php echo $sf1Score1 !== null ? (int)$sf1Score1 : ''; ?>">
                                            </div>
                                            <div class="form-group">
                                                <label>Team B</label>
                                                <select class="form-select" id="sf1_team2_id" name="sf1_team2_id" required>
                                                    <option value="">Pilih Tim</option>
                                                    <?php foreach ($teams as $team): ?>
                                                        <option value="<?php echo (int)$team['id']; ?>" <?php echo $sf1Team2 === (int)$team['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars((string)($team['name'] ?? '-')); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Skor B</label>
                                                <input class="form-input" id="sf1_score2" type="number" min="0" name="sf1_score2" value="<?php echo $sf1Score2 !== null ? (int)$sf1Score2 : ''; ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="match-setup-card">
                                        <div class="match-setup-title"><i class="fas fa-play"></i> Semifinal 2</div>
                                        <div class="form-grid form-grid-2">
                                            <div class="form-group">
                                                <label>Team A</label>
                                                <select class="form-select" id="sf2_team1_id" name="sf2_team1_id" required>
                                                    <option value="">Pilih Tim</option>
                                                    <?php foreach ($teams as $team): ?>
                                                        <option value="<?php echo (int)$team['id']; ?>" <?php echo $sf2Team1 === (int)$team['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars((string)($team['name'] ?? '-')); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Skor A</label>
                                                <input class="form-input" id="sf2_score1" type="number" min="0" name="sf2_score1" value="<?php echo $sf2Score1 !== null ? (int)$sf2Score1 : ''; ?>">
                                            </div>
                                            <div class="form-group">
                                                <label>Team B</label>
                                                <select class="form-select" id="sf2_team2_id" name="sf2_team2_id" required>
                                                    <option value="">Pilih Tim</option>
                                                    <?php foreach ($teams as $team): ?>
                                                        <option value="<?php echo (int)$team['id']; ?>" <?php echo $sf2Team2 === (int)$team['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars((string)($team['name'] ?? '-')); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Skor B</label>
                                                <input class="form-input" id="sf2_score2" type="number" min="0" name="sf2_score2" value="<?php echo $sf2Score2 !== null ? (int)$sf2Score2 : ''; ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="match-setup-card">
                                        <div class="match-setup-title"><i class="fas fa-trophy"></i> Grand Final</div>
                                        <div class="form-grid form-grid-2">
                                            <div class="form-group">
                                                <label id="final_team1_label">Pemenang SF1</label>
                                                <input class="form-input" type="number" min="0" name="final_score1" value="<?php echo $finalScore1 !== null ? (int)$finalScore1 : ''; ?>" placeholder="Skor">
                                            </div>
                                            <div class="form-group">
                                                <label id="final_team2_label">Pemenang SF2</label>
                                                <input class="form-input" type="number" min="0" name="final_score2" value="<?php echo $finalScore2 !== null ? (int)$finalScore2 : ''; ?>" placeholder="Skor">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="match-setup-card">
                                        <div class="match-setup-title"><i class="fas fa-award"></i> Perebutan Juara 3</div>
                                        <div class="form-grid form-grid-2">
                                            <div class="form-group">
                                                <label id="third_team1_label">Kalah SF1</label>
                                                <input class="form-input" type="number" min="0" name="third_score1" value="<?php echo $thirdScore1 !== null ? (int)$thirdScore1 : ''; ?>" placeholder="Skor">
                                            </div>
                                            <div class="form-group">
                                                <label id="third_team2_label">Kalah SF2</label>
                                                <input class="form-input" type="number" min="0" name="third_score2" value="<?php echo $thirdScore2 !== null ? (int)$thirdScore2 : ''; ?>" placeholder="Skor">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button class="btn-premium btn-save" type="submit"><i class="fas fa-save"></i> Simpan Bracket</button>
                                </div>
                            </form>
                            
                            <form method="post" style="margin-top: 12px; display: flex; justify-content: flex-end;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="action" value="clear_bracket">
                                <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                                <input type="hidden" name="sport_type" value="<?php echo htmlspecialchars($effectiveCategory); ?>">
                                <button class="btn-premium btn-cancel" type="submit" style="color: var(--heritage-crimson);"><i class="fas fa-eraser"></i> Reset Bracket</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- PREVIEW DIAGRAM -->
                    <div class="heritage-card reveal d-3">
                        <div class="diagram-wrap">
                            <div class="diagram-title">Visualisasi Turnamen</div>
                            <div class="diagram-note">Peta persaingan menuju tangga juara.</div>
                            
                            <div class="diagram-canvas">
                                <?php
                                $sf1Done = ((string)$sf1Outcome['status'] === 'done');
                                $sf2Done = ((string)$sf2Outcome['status'] === 'done');
                                $finalDone = ((string)$finalOutcome['status'] === 'done');
                                $thirdDone = ((string)$thirdOutcome['status'] === 'done');

                                $finalTopRankText = '1st';
                                $finalBottomRankText = '2nd';
                                if ($finalDone && (int)$finalOutcome['winner'] === $finalTeam2) {
                                    $finalTopRankText = '2nd';
                                    $finalBottomRankText = '1st';
                                }
                                $thirdTopRankText = '3rd';
                                $thirdBottomRankText = '4th';
                                if ($thirdDone && (int)$thirdOutcome['winner'] === $thirdTeam2) {
                                    $thirdTopRankText = '4th';
                                    $thirdBottomRankText = '3rd';
                                }
                                ?>
                                <!-- Semifinal 1 -->
                                <div class="d-match" style="left:10px; top:48px;">
                                    <div class="d-row <?php echo $sf1Done && (int)$sf1Outcome['winner'] === $sf1Team1 ? 'win' : ($sf1Done ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$sf1Team1; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $sf1Team1); ?></div>
                                        <div class="d-score"><?php echo $sf1Score1 !== null ? (int)$sf1Score1 : '-'; ?></div>
                                    </div>
                                    <div class="d-row <?php echo $sf1Done && (int)$sf1Outcome['winner'] === $sf1Team2 ? 'win' : ($sf1Done ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$sf1Team2; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $sf1Team2); ?></div>
                                        <div class="d-score"><?php echo $sf1Score2 !== null ? (int)$sf1Score2 : '-'; ?></div>
                                    </div>
                                </div>

                                <!-- Semifinal 2 -->
                                <div class="d-match" style="left:10px; top:240px;">
                                    <div class="d-row <?php echo $sf2Done && (int)$sf2Outcome['winner'] === $sf2Team1 ? 'win' : ($sf2Done ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$sf2Team1; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $sf2Team1); ?></div>
                                        <div class="d-score"><?php echo $sf2Score1 !== null ? (int)$sf2Score1 : '-'; ?></div>
                                    </div>
                                    <div class="d-row <?php echo $sf2Done && (int)$sf2Outcome['winner'] === $sf2Team2 ? 'win' : ($sf2Done ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$sf2Team2; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $sf2Team2); ?></div>
                                        <div class="d-score"><?php echo $sf2Score2 !== null ? (int)$sf2Score2 : '-'; ?></div>
                                    </div>
                                </div>

                                <!-- Final -->
                                <div class="d-match" style="left:460px; top:145px; border-color: var(--heritage-gold); border-width: 2px;">
                                    <div class="d-row <?php echo $finalDone && (int)$finalOutcome['winner'] === $finalTeam1 ? 'win' : ($vFinalDone ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$finalTeam1; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $finalTeam1); ?></div>
                                        <div class="d-score"><?php echo $finalScore1 !== null ? (int)$finalScore1 : '-'; ?></div>
                                    </div>
                                    <div class="d-row <?php echo $finalDone && (int)$finalOutcome['winner'] === $finalTeam2 ? 'win' : ($vFinalDone ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$finalTeam2; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $finalTeam2); ?></div>
                                        <div class="d-score"><?php echo $finalScore2 !== null ? (int)$finalScore2 : '-'; ?></div>
                                    </div>
                                    <span class="d-rank first"><?php echo $finalTopRankText; ?></span>
                                    <span class="d-rank second"><?php echo $finalBottomRankText; ?></span>
                                </div>

                                <!-- Juara 3 -->
                                <div class="d-match" style="left:460px; top:380px;">
                                    <div class="d-row <?php echo $thirdDone && (int)$thirdOutcome['winner'] === $thirdTeam1 ? 'win' : ($vThirdDone ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$thirdTeam1; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $thirdTeam1); ?></div>
                                        <div class="d-score"><?php echo $thirdScore1 !== null ? (int)$thirdScore1 : '-'; ?></div>
                                    </div>
                                    <div class="d-row <?php echo $thirdDone && (int)$thirdOutcome['winner'] === $thirdTeam2 ? 'win' : ($vThirdDone ? 'lose' : ''); ?>" data-team-id="<?php echo (int)$thirdTeam2; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $thirdTeam2); ?></div>
                                        <div class="d-score"><?php echo $thirdScore2 !== null ? (int)$thirdScore2 : '-'; ?></div>
                                    </div>
                                    <span class="d-rank third"><?php echo $thirdTopRankText; ?></span>
                                    <span class="d-rank fourth"><?php echo $thirdBottomRankText; ?></span>
                                </div>

                                <!-- Lines -->
                                <div class="d-line gold" style="left:250px; top:74px; width:110px; border-top-width:2px;"></div>
                                <div class="d-line gold" style="left:360px; top:74px; height:98px; border-right-width:2px;"></div>
                                <div class="d-line gold" style="left:360px; top:172px; width:100px; border-top-width:2px;"></div>

                                <div class="d-line" style="left:250px; top:266px; width:110px; border-top-width:2px;"></div>
                                <div class="d-line" style="left:360px; top:172px; height:94px; border-right-width:2px;"></div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        const rows = Array.from(document.querySelectorAll('.diagram-canvas .d-row[data-team-id]'));
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

    (function() {
        const sf1Team1 = document.getElementById('sf1_team1_id');
        const sf1Team2 = document.getElementById('sf1_team2_id');
        const sf2Team1 = document.getElementById('sf2_team1_id');
        const sf2Team2 = document.getElementById('sf2_team2_id');
        const sf1Score1 = document.getElementById('sf1_score1');
        const sf1Score2 = document.getElementById('sf1_score2');
        const sf2Score1 = document.getElementById('sf2_score1');
        const sf2Score2 = document.getElementById('sf2_score2');

        const finalTeam1Label = document.getElementById('final_team1_label');
        const finalTeam2Label = document.getElementById('final_team2_label');
        const thirdTeam1Label = document.getElementById('third_team1_label');
        const thirdTeam2Label = document.getElementById('third_team2_label');

        if (!sf1Team1 || !sf1Team2 || !sf2Team1 || !sf2Team2 || !sf1Score1 || !sf1Score2 || !sf2Score1 || !sf2Score2) return;
        if (!finalTeam1Label || !finalTeam2Label || !thirdTeam1Label || !thirdTeam2Label) return;

        function parseScore(input) {
            const raw = String(input.value || '').trim();
            if (raw === '') return null;
            const value = parseInt(raw, 10);
            return isNaN(value) ? null : Math.max(0, value);
        }

        function selectedTeam(select) {
            const id = parseInt(select.value || '0', 10) || 0;
            if (id <= 0) return { id: 0, name: '-' };
            const option = select.options[select.selectedIndex];
            return { id, name: option ? option.text.trim() : '-' };
        }

        function resolveOutcome(teamA, teamB, scoreA, scoreB) {
            if (!teamA.id || !teamB.id) return { winner: '-', loser: '-' };
            if (scoreA === null || scoreB === null) return { winner: 'TBD', loser: 'TBD' };
            if (scoreA > scoreB) return { winner: teamA.name, loser: teamB.name };
            if (scoreB > scoreA) return { winner: teamB.name, loser: teamA.name };
            return { winner: 'Draw', loser: 'Draw' };
        }

        function updateDerivedTeamLabels() {
            const m1 = resolveOutcome(selectedTeam(sf1Team1), selectedTeam(sf1Team2), parseScore(sf1Score1), parseScore(sf1Score2));
            const m2 = resolveOutcome(selectedTeam(sf2Team1), selectedTeam(sf2Team2), parseScore(sf2Score1), parseScore(sf2Score2));

            finalTeam1Label.textContent = 'Pemenang SF1: ' + m1.winner;
            finalTeam2Label.textContent = 'Pemenang SF2: ' + m2.winner;
            thirdTeam1Label.textContent = 'Kalah SF1: ' + m1.loser;
            thirdTeam2Label.textContent = 'Kalah SF2: ' + m2.loser;
        }

        [sf1Team1, sf1Team2, sf2Team1, sf2Team2, sf1Score1, sf1Score2, sf2Score1, sf2Score2].forEach(el => {
            el.addEventListener('change', updateDerivedTeamLabels);
            el.addEventListener('input', updateDerivedTeamLabels);
        });

        updateDerivedTeamLabels();
    })();
    </script>
    <?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>
