<?php
session_start();

$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
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
}

function loadEventCategories(PDO $conn, int $eventId): array
{
    if ($eventId <= 0) return [];
    $stmt = $conn->prepare("SELECT DISTINCT sport_type
                            FROM challenges
                            WHERE event_id = ?
                              AND status = 'accepted'
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
                                SELECT challenger_id AS team_id FROM challenges WHERE event_id = ? AND sport_type = ? AND status = 'accepted'
                                UNION
                                SELECT opponent_id AS team_id FROM challenges WHERE event_id = ? AND sport_type = ? AND status = 'accepted'
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

function renderTeamLabelById(array $teamMap, array $teamLogoMap, int $teamId): string
{
    if ($teamId <= 0) return '<span class="d-team"><span class="d-team-logo d-team-logo-placeholder"></span><span class="d-team-name">-</span></span>';
    $name = (string)($teamMap[$teamId] ?? ('Team #' . $teamId));
    $logoFile = trim((string)($teamLogoMap[$teamId] ?? ''));
    $logoPath = '../images/teams/default-team.png';
    if ($logoFile !== '') {
        $logoPath = '../images/teams/' . ltrim($logoFile, '/');
    }

    return '<span class="d-team"><img class="d-team-logo" src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"><span class="d-team-name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span></span>';
}

ensureBracketSchema($conn);

$events = $conn->query("SELECT id, name FROM events ORDER BY start_date DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$eventMap = [];
foreach ($events as $event) {
    $eventMap[(int)$event['id']] = (string)($event['name'] ?? '');
}

$errors = [];
$success = '';

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$selectedCategory = trim((string)($_GET['sport_type'] ?? ''));
if ($eventId <= 0) {
    $selectedCategory = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        $errors[] = 'Token invalid.';
    }

    $action = trim((string)($_POST['action'] ?? ''));
    $eventId = (int)($_POST['event_id'] ?? 0);
    $selectedCategory = trim((string)($_POST['sport_type'] ?? ''));
    if ($eventId <= 0) {
        $selectedCategory = '';
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

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO event_brackets (
                                        event_id, sport_type,
                                        sf1_team1_id, sf1_team2_id, sf1_score1, sf1_score2,
                                        sf2_team1_id, sf2_team2_id, sf2_score1, sf2_score2,
                                        final_score1, final_score2, third_score1, third_score2
                                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE
                                        sf1_team1_id = VALUES(sf1_team1_id),
                                        sf1_team2_id = VALUES(sf1_team2_id),
                                        sf1_score1 = VALUES(sf1_score1),
                                        sf1_score2 = VALUES(sf1_score2),
                                        sf2_team1_id = VALUES(sf2_team1_id),
                                        sf2_team2_id = VALUES(sf2_team2_id),
                                        sf2_score1 = VALUES(sf2_score1),
                                        sf2_score2 = VALUES(sf2_score2),
                                        final_score1 = VALUES(final_score1),
                                        final_score2 = VALUES(final_score2),
                                        third_score1 = VALUES(third_score1),
                                        third_score2 = VALUES(third_score2),
                                        updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([
                $eventId, $effectiveCategory,
                $sf1Team1, $sf1Team2, $sf1Score1, $sf1Score2,
                $sf2Team1, $sf2Team2, $sf2Score1, $sf2Score2,
                $finalScore1, $finalScore2, $thirdScore1, $thirdScore2
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
<title>Event Bracket</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<style>
:root { --primary:#0f2744; --accent:#3b82f6; --gray:#64748b; --ok:#16a34a; --warn:#dc2626; --card:#ffffff; }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Plus Jakarta Sans','Segoe UI',sans-serif; background:linear-gradient(180deg,#eaf6ff 0%,#f4fbff 100%); color:#1e293b; }
.main { margin-left:280px; width:calc(100% - 280px); padding:28px; }
.topbar,.panel { background:#fff; border-radius:16px; box-shadow:0 10px 24px rgba(15,39,68,.08); }
.topbar { padding:18px 22px; margin-bottom:18px; display:flex; justify-content:space-between; align-items:center; }
.topbar h1 { font-size:26px; color:var(--primary); }
.topbar p { color:var(--gray); font-size:13px; margin-top:4px; }
.btn { border:none; border-radius:10px; padding:10px 14px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:8px; }
.btn-primary { background:linear-gradient(135deg,var(--primary),var(--accent)); color:#fff; }
.btn-secondary { background:#475569; color:#fff; }
.btn-danger { background:#b91c1c; color:#fff; }
.panel { padding:18px; margin-bottom:16px; }
.panel-title { font-size:18px; color:var(--primary); margin-bottom:12px; font-weight:800; }
.grid { display:grid; gap:12px; }
.grid-2 { grid-template-columns:repeat(2,minmax(0,1fr)); }
.grid-4 { grid-template-columns:repeat(4,minmax(0,1fr)); }
.form-group label { display:block; font-size:13px; margin-bottom:6px; font-weight:700; }
.form-control { width:100%; height:40px; border:1px solid #dbe5f3; border-radius:10px; padding:0 10px; }
.section-divider { border-top:1px solid #e2e8f0; margin:14px 0; padding-top:14px; }
.alert { padding:11px 13px; border-radius:10px; font-size:13px; margin-bottom:10px; }
.alert-success { background:#dcfce7; color:#166534; }
.alert-danger { background:#fee2e2; color:#991b1b; }
.muted { color:var(--gray); font-size:12px; }
.bracket-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
.match-box { border:1px solid #dbe5f3; border-radius:12px; padding:12px; background:#f8fbff; }
.match-title { font-size:14px; font-weight:800; color:var(--primary); margin-bottom:8px; }
.match-row { display:flex; justify-content:space-between; gap:8px; padding:6px 0; border-bottom:1px dashed #dbe5f3; }
.match-row:last-child { border-bottom:none; }
.score-pill { min-width:24px; text-align:center; font-weight:800; }
.rank-list { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin-top:10px; }
.rank-item { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:10px; font-size:13px; }
.rank-item strong { display:block; color:var(--primary); margin-bottom:4px; }
.diagram-wrap {
  background:radial-gradient(circle at 20% 0%, #f8fbff 0%, #eef4fb 48%, #e9f1f9 100%);
  border:1px solid #dbe5f3;
  border-radius:16px;
  padding:22px;
}
.diagram-title {
  font-size:24px;
  font-weight:800;
  color:#334155;
  margin-bottom:6px;
}
.diagram-note {
  color:#64748b;
  font-size:12px;
  margin-bottom:14px;
}
.diagram-canvas {
  position:relative;
  min-height:540px;
  transition:transform .25s ease;
  perspective:1200px;
  transform-style:preserve-3d;
}
.d-match {
  position:absolute;
  width:260px;
  background:#fff;
  border:1px solid #d1d5db;
  border-radius:10px;
  overflow:visible;
  box-shadow:0 10px 20px rgba(15,39,68,.08);
  opacity:0;
  transform:translateY(10px) scale(.98);
  animation:bracketNodeIn .45s ease-out forwards, nodeFloat 4.5s ease-in-out infinite;
  transition:transform .24s ease, box-shadow .24s ease, border-color .24s ease, filter .24s ease;
  transform-origin:center center;
  will-change:transform, box-shadow, filter;
  z-index:1;
  backface-visibility:hidden;
}
.d-match::after {
  content:'';
  position:absolute;
  inset:0;
  border-radius:10px;
  pointer-events:none;
  background:linear-gradient(110deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.18) 40%, rgba(255,255,255,0) 70%);
  transform:translateX(-140%);
  opacity:0;
  mix-blend-mode:normal;
}
.d-match:hover::after {
  opacity:.55;
  animation:nodeShine .65s ease-out forwards;
}
.d-row {
  display:grid;
  grid-template-columns: 1fr 40px;
  border-bottom:1px solid #e5e7eb;
  cursor:pointer;
  transition:background .2s ease, box-shadow .2s ease, color .2s ease;
}
.d-row:last-child { border-bottom:none; }
.d-row.win {
  background:linear-gradient(90deg,#0ea5e9 0%, #2563eb 100%);
  color:#ffffff;
}
.d-row.lose { background:#e5e7eb; }
.d-name {
  padding:8px 10px;
  font-size:15px;
}
.d-team {
  display:inline-flex;
  align-items:center;
  gap:8px;
}
.d-team-logo {
  width:20px;
  height:20px;
  border-radius:50%;
  object-fit:cover;
  background:#ffffff;
  border:1px solid rgba(148,163,184,.7);
  flex-shrink:0;
}
.d-team-logo-placeholder {
  display:inline-block;
  background:#cbd5e1;
}
.d-team-name {
  line-height:1.2;
}
.d-score {
  text-align:center;
  padding:8px 0;
  font-weight:800;
  font-size:22px;
}
.d-score.green { color:#16a34a; }
.d-score.red { color:#b91c1c; }
.d-row.win .d-score.green { color:#ffffff; }
.d-rank {
  position:absolute;
  right:-62px;
  top:8px;
  width:52px;
  text-align:center;
  color:#fff;
  font-size:12px;
  font-weight:800;
  border-radius:6px;
  padding:4px 0;
  box-shadow:0 8px 14px rgba(15,39,68,.18);
  z-index:3;
  opacity:0;
  animation:bracketNodeIn .45s ease-out forwards;
  transition:transform .22s ease, box-shadow .22s ease, filter .22s ease;
}
.d-rank.second { top:44px; background:#6b7280; }
.d-rank.first { background:#2563eb; }
.d-rank.second { background:#334155; }
.d-rank.third { background:#0f766e; }
.d-rank.fourth { background:#7c2d12; top:44px; }
.d-line {
  position:absolute;
  border-color:#cbd5e1;
  border-style:solid;
  border-width:0;
  opacity:0;
  transform-origin:left center;
  animation:lineDraw .55s ease-out forwards, bracketLinePulse 2.4s ease-in-out infinite;
  transition:filter .25s ease, border-color .25s ease, opacity .25s ease;
}
.d-line.gold { border-color:#2563eb; }
@keyframes bracketNodeIn {
  from { opacity:0; transform:translateY(10px) scale(.98); }
  to { opacity:1; transform:translateY(0) scale(1); }
}
@keyframes bracketLineIn {
  from { opacity:0; }
  to { opacity:1; }
}
@keyframes lineDraw {
  0% { opacity:0; transform:scaleX(0); }
  100% { opacity:1; transform:scaleX(1); }
}
@keyframes bracketLinePulse {
  0% { filter: drop-shadow(0 0 0 rgba(37,99,235,.0)); }
  50% { filter: drop-shadow(0 0 5px rgba(37,99,235,.35)); }
  100% { filter: drop-shadow(0 0 0 rgba(37,99,235,.0)); }
}
@keyframes winnerGlow {
  0% { box-shadow: inset 0 0 0 rgba(255,255,255,0); }
  50% { box-shadow: inset 0 0 18px rgba(255,255,255,.14); }
  100% { box-shadow: inset 0 0 0 rgba(255,255,255,0); }
}
.d-rank {
  animation:bracketNodeIn .45s ease-out forwards, rankPulse 2.8s ease-in-out infinite;
}
@keyframes nodeFloat {
  0% { transform:translateY(0) scale(1); }
  50% { transform:translateY(-2px) scale(1); }
  100% { transform:translateY(0) scale(1); }
}
@keyframes nodeShine {
  from { transform:translateX(-140%); }
  to { transform:translateX(140%); }
}
@keyframes rankPulse {
  0% { filter:brightness(1); }
  50% { filter:brightness(1.08); }
  100% { filter:brightness(1); }
}
.d-row.win {
  animation:winnerGlow 2.8s ease-in-out infinite;
}
.d-row.linked-highlight {
  background:linear-gradient(90deg,#166534 0%, #15803d 45%, #16a34a 100%) !important;
  color:#ffffff !important;
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.22), 0 0 0 2px rgba(22,163,74,.36);
}
.d-row.linked-highlight .d-score {
  color:#ffffff !important;
}
.d-match:hover {
  transform:translateY(-8px) translateZ(60px) scale(1.08);
  transition:transform .22s ease;
  z-index:12;
}
.diagram-canvas:hover .d-line {
  border-color:#60a5fa;
  filter:drop-shadow(0 0 8px rgba(37,99,235,.35));
}
.diagram-canvas:hover .d-line.gold {
  border-color:#1d4ed8;
  filter:drop-shadow(0 0 10px rgba(29,78,216,.45));
}
.diagram-canvas:hover .d-match {
  filter:saturate(1.05);
}
.diagram-canvas .d-match:hover {
  border-color:#60a5fa;
  border-width:5px;
  box-shadow:0 20px 34px rgba(15,39,68,.2);
  filter:saturate(1.08);
}
.diagram-canvas .d-match:hover .d-name {
  letter-spacing:.2px;
}
.diagram-canvas .d-match:hover .d-score {
  transform:scale(1.08);
  transition:transform .2s ease;
}
.diagram-canvas .d-match:hover .d-rank {
  transform:translateX(3px) scale(1.06);
  box-shadow:0 12px 20px rgba(15,39,68,.24);
  filter:brightness(1.08);
}
.diagram-canvas .d-match:hover .d-row.lose {
  background:#cbd5e1;
}
@media (max-width: 1100px) {
  .diagram-canvas { min-height: unset; display:grid; gap:12px; }
  .d-match { position:static; width:100%; }
  .d-line, .d-rank { display:none; }
}
@media (max-width: 900px) {
  .main { margin-left:0; width:100%; padding:14px; }
  .grid-2,.grid-4,.bracket-grid,.rank-list { grid-template-columns:1fr; }
}
</style>
</head>
<body>
<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <div class="main">
        <div class="topbar">
            <div>
                <h1>Event Bracket 🗓️</h1>
                <p>Mode 4 tim: Semifinal, Final, dan perebutan juara 3.</p>
            </div>
            <a href="event.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
        </div>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>

        <div class="panel">
            <div class="panel-title">Pilih Event & Kategori</div>
            <form method="get" class="grid grid-2">
                <div class="form-group">
                    <label>Event</label>
                    <select class="form-control" name="event_id" onchange="this.form.submit()">
                        <option value="">Pilih Event</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo (int)$event['id']; ?>" <?php echo $eventId === (int)$event['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)($event['name'] ?? '-')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kategori</label>
                    <select class="form-control" name="sport_type" onchange="this.form.submit()">
                        <option value="">Pilih Kategori</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $selectedCategory === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($eventId <= 0): ?>
            <div class="panel"><p class="muted">Pilih event dan kategori dulu.</p></div>
        <?php elseif ($effectiveCategory === ''): ?>
            <div class="panel">
                <div class="panel-title">Preview Semua Kategori</div>
                <?php if (empty($specificCategories)): ?>
                    <p class="muted">Belum ada kategori spesifik untuk event ini.</p>
                <?php else: ?>
                    <p class="muted" style="margin-bottom:12px;">Mode Liga menampilkan semua kategori. Klik kategori untuk edit bracket.</p>
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
                        ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin:8px 0 10px;">
                            <div class="match-title" style="margin:0;"><?php echo htmlspecialchars($vCategory); ?></div>
                            <a class="btn btn-secondary" style="padding:8px 12px;" href="event_bracket.php?event_id=<?php echo (int)$eventId; ?>&sport_type=<?php echo urlencode($vCategory); ?>">
                                Kelola
                            </a>
                        </div>
                        <div class="diagram-wrap" style="margin-bottom:16px;">
                            <div class="diagram-title" style="font-size:20px;"><?php echo htmlspecialchars($vCategory); ?> - Bracket</div>
                            <div class="diagram-note">Preview bracket kategori ini.</div>
                            <div class="diagram-canvas">
                                <div class="d-match" style="left:10px; top:48px; animation-delay:.05s;">
                                    <div class="d-row <?php echo $vSf1Done && (int)$vSf1Outcome['winner'] === $vSf1Team1 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$vSf1Team1; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vSf1Team1); ?></div>
                                        <div class="d-score <?php echo $vSf1Done && (int)$vSf1Outcome['winner'] === $vSf1Team1 ? 'green' : 'red'; ?>"><?php echo $vSf1Score1 !== null ? (int)$vSf1Score1 : '-'; ?></div>
                                    </div>
                                    <div class="d-row <?php echo $vSf1Done && (int)$vSf1Outcome['winner'] === $vSf1Team2 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$vSf1Team2; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vSf1Team2); ?></div>
                                        <div class="d-score <?php echo $vSf1Done && (int)$vSf1Outcome['winner'] === $vSf1Team2 ? 'green' : 'red'; ?>"><?php echo $vSf1Score2 !== null ? (int)$vSf1Score2 : '-'; ?></div>
                                    </div>
                                </div>
                                <div class="d-match" style="left:10px; top:240px; animation-delay:.15s;">
                                    <div class="d-row <?php echo $vSf2Done && (int)$vSf2Outcome['winner'] === $vSf2Team1 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$vSf2Team1; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vSf2Team1); ?></div>
                                        <div class="d-score <?php echo $vSf2Done && (int)$vSf2Outcome['winner'] === $vSf2Team1 ? 'green' : 'red'; ?>"><?php echo $vSf2Score1 !== null ? (int)$vSf2Score1 : '-'; ?></div>
                                    </div>
                                    <div class="d-row <?php echo $vSf2Done && (int)$vSf2Outcome['winner'] === $vSf2Team2 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$vSf2Team2; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vSf2Team2); ?></div>
                                        <div class="d-score <?php echo $vSf2Done && (int)$vSf2Outcome['winner'] === $vSf2Team2 ? 'green' : 'red'; ?>"><?php echo $vSf2Score2 !== null ? (int)$vSf2Score2 : '-'; ?></div>
                                    </div>
                                </div>
                                <div class="d-match" style="left:460px; top:145px; animation-delay:.25s;">
                                    <div class="d-row <?php echo $vFinalDone && (int)$vFinalOutcome['winner'] === $vFinalTeam1 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$vFinalTeam1; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vFinalTeam1); ?></div>
                                        <div class="d-score <?php echo $vFinalDone && (int)$vFinalOutcome['winner'] === $vFinalTeam1 ? 'green' : 'red'; ?>"><?php echo $vFinalScore1 !== null ? (int)$vFinalScore1 : '-'; ?></div>
                                    </div>
                                    <div class="d-row <?php echo $vFinalDone && (int)$vFinalOutcome['winner'] === $vFinalTeam2 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$vFinalTeam2; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vFinalTeam2); ?></div>
                                        <div class="d-score <?php echo $vFinalDone && (int)$vFinalOutcome['winner'] === $vFinalTeam2 ? 'green' : 'red'; ?>"><?php echo $vFinalScore2 !== null ? (int)$vFinalScore2 : '-'; ?></div>
                                    </div>
                                    <span class="d-rank first" style="animation-delay:.35s;">1st</span>
                                    <span class="d-rank second" style="animation-delay:.4s;">2nd</span>
                                </div>
                                <div class="d-match" style="left:460px; top:380px; animation-delay:.3s;">
                                    <div class="d-row <?php echo $vThirdDone && (int)$vThirdOutcome['winner'] === $vThirdTeam1 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$vThirdTeam1; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vThirdTeam1); ?></div>
                                        <div class="d-score <?php echo $vThirdDone && (int)$vThirdOutcome['winner'] === $vThirdTeam1 ? 'green' : 'red'; ?>"><?php echo $vThirdScore1 !== null ? (int)$vThirdScore1 : '-'; ?></div>
                                    </div>
                                    <div class="d-row <?php echo $vThirdDone && (int)$vThirdOutcome['winner'] === $vThirdTeam2 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$vThirdTeam2; ?>">
                                        <div class="d-name"><?php echo renderTeamLabelById($vTeamMap, $vTeamLogoMap, $vThirdTeam2); ?></div>
                                        <div class="d-score <?php echo $vThirdDone && (int)$vThirdOutcome['winner'] === $vThirdTeam2 ? 'green' : 'red'; ?>"><?php echo $vThirdScore2 !== null ? (int)$vThirdScore2 : '-'; ?></div>
                                    </div>
                                    <span class="d-rank third" style="animation-delay:.45s;">3rd</span>
                                    <span class="d-rank fourth" style="animation-delay:.5s;">4th</span>
                                </div>
                                <div class="d-line gold" style="left:270px; top:84px; width:90px; border-top-width:3px; animation-delay:.28s;"></div>
                                <div class="d-line gold" style="left:360px; top:84px; height:92px; border-right-width:3px; animation-delay:.32s;"></div>
                                <div class="d-line gold" style="left:360px; top:176px; width:100px; border-top-width:3px; animation-delay:.36s;"></div>
                                <div class="d-line" style="left:270px; top:276px; width:90px; border-top-width:3px; animation-delay:.3s;"></div>
                                <div class="d-line" style="left:360px; top:176px; height:100px; border-right-width:3px; animation-delay:.34s;"></div>
                                <div class="d-line" style="left:360px; top:176px; width:100px; border-top-width:3px; animation-delay:.38s;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="panel">
                <div class="panel-title">Setup Bracket (4 Tim)</div>
                <?php if (count($teams) < 4): ?>
                    <p class="muted">Tim kategori ini kurang dari 4. Minimal 4 tim untuk mode bracket ini.</p>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="save_bracket">
                        <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                        <input type="hidden" name="sport_type" value="<?php echo htmlspecialchars($effectiveCategory); ?>">

                        <div class="grid grid-4">
                            <div class="form-group">
                                <label>SF1 Team A</label>
                                <select class="form-control" name="sf1_team1_id" required>
                                    <option value="">Pilih Tim</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?php echo (int)$team['id']; ?>" <?php echo $sf1Team1 === (int)$team['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string)($team['name'] ?? '-')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>SF1 Team B</label>
                                <select class="form-control" name="sf1_team2_id" required>
                                    <option value="">Pilih Tim</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?php echo (int)$team['id']; ?>" <?php echo $sf1Team2 === (int)$team['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string)($team['name'] ?? '-')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>SF2 Team A</label>
                                <select class="form-control" name="sf2_team1_id" required>
                                    <option value="">Pilih Tim</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?php echo (int)$team['id']; ?>" <?php echo $sf2Team1 === (int)$team['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string)($team['name'] ?? '-')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>SF2 Team B</label>
                                <select class="form-control" name="sf2_team2_id" required>
                                    <option value="">Pilih Tim</option>
                                    <?php foreach ($teams as $team): ?>
                                        <option value="<?php echo (int)$team['id']; ?>" <?php echo $sf2Team2 === (int)$team['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string)($team['name'] ?? '-')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="section-divider"></div>
                        <div class="grid grid-4">
                            <div class="form-group">
                                <label>Skor SF1 Team A</label>
                                <input class="form-control" type="number" min="0" name="sf1_score1" value="<?php echo $sf1Score1 !== null ? (int)$sf1Score1 : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Skor SF1 Team B</label>
                                <input class="form-control" type="number" min="0" name="sf1_score2" value="<?php echo $sf1Score2 !== null ? (int)$sf1Score2 : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Skor SF2 Team A</label>
                                <input class="form-control" type="number" min="0" name="sf2_score1" value="<?php echo $sf2Score1 !== null ? (int)$sf2Score1 : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Skor SF2 Team B</label>
                                <input class="form-control" type="number" min="0" name="sf2_score2" value="<?php echo $sf2Score2 !== null ? (int)$sf2Score2 : ''; ?>">
                            </div>
                        </div>

                        <div class="section-divider"></div>
                        <div class="grid grid-4">
                            <div class="form-group">
                                <label>Skor Final Team A</label>
                                <input class="form-control" type="number" min="0" name="final_score1" value="<?php echo $finalScore1 !== null ? (int)$finalScore1 : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Skor Final Team B</label>
                                <input class="form-control" type="number" min="0" name="final_score2" value="<?php echo $finalScore2 !== null ? (int)$finalScore2 : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Skor 3rd Team A</label>
                                <input class="form-control" type="number" min="0" name="third_score1" value="<?php echo $thirdScore1 !== null ? (int)$thirdScore1 : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Skor 3rd Team B</label>
                                <input class="form-control" type="number" min="0" name="third_score2" value="<?php echo $thirdScore2 !== null ? (int)$thirdScore2 : ''; ?>">
                            </div>
                        </div>

                        <div class="section-divider"></div>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Simpan Bracket</button>
                        </div>
                    </form>
                    <form method="post" style="margin-top:8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                        <input type="hidden" name="action" value="clear_bracket">
                        <input type="hidden" name="event_id" value="<?php echo (int)$eventId; ?>">
                        <input type="hidden" name="sport_type" value="<?php echo htmlspecialchars($effectiveCategory); ?>">
                        <button class="btn btn-danger" type="submit"><i class="fas fa-eraser"></i> Reset Bracket</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="panel">
                <div class="panel-title">Preview Bracket</div>
                <div class="diagram-wrap">
                    <div class="diagram-title">Bracket</div>
                    <div class="diagram-note">Klik match form di atas untuk update skor pertandingan.</div>
                    <div class="diagram-canvas">
                        <?php
                        $sf1Done = ((string)$sf1Outcome['status'] === 'done');
                        $sf2Done = ((string)$sf2Outcome['status'] === 'done');
                        $finalDone = ((string)$finalOutcome['status'] === 'done');
                        $thirdDone = ((string)$thirdOutcome['status'] === 'done');
                        ?>
                        <div class="d-match" style="left:10px; top:48px; animation-delay:.05s;">
                            <div class="d-row <?php echo $sf1Done && (int)$sf1Outcome['winner'] === $sf1Team1 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$sf1Team1; ?>">
                                <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $sf1Team1); ?></div>
                                <div class="d-score <?php echo $sf1Done && (int)$sf1Outcome['winner'] === $sf1Team1 ? 'green' : 'red'; ?>"><?php echo $sf1Score1 !== null ? (int)$sf1Score1 : '-'; ?></div>
                            </div>
                            <div class="d-row <?php echo $sf1Done && (int)$sf1Outcome['winner'] === $sf1Team2 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$sf1Team2; ?>">
                                <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $sf1Team2); ?></div>
                                <div class="d-score <?php echo $sf1Done && (int)$sf1Outcome['winner'] === $sf1Team2 ? 'green' : 'red'; ?>"><?php echo $sf1Score2 !== null ? (int)$sf1Score2 : '-'; ?></div>
                            </div>
                        </div>

                        <div class="d-match" style="left:10px; top:240px; animation-delay:.15s;">
                            <div class="d-row <?php echo $sf2Done && (int)$sf2Outcome['winner'] === $sf2Team1 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$sf2Team1; ?>">
                                <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $sf2Team1); ?></div>
                                <div class="d-score <?php echo $sf2Done && (int)$sf2Outcome['winner'] === $sf2Team1 ? 'green' : 'red'; ?>"><?php echo $sf2Score1 !== null ? (int)$sf2Score1 : '-'; ?></div>
                            </div>
                            <div class="d-row <?php echo $sf2Done && (int)$sf2Outcome['winner'] === $sf2Team2 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$sf2Team2; ?>">
                                <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $sf2Team2); ?></div>
                                <div class="d-score <?php echo $sf2Done && (int)$sf2Outcome['winner'] === $sf2Team2 ? 'green' : 'red'; ?>"><?php echo $sf2Score2 !== null ? (int)$sf2Score2 : '-'; ?></div>
                            </div>
                        </div>

                        <div class="d-match" style="left:460px; top:145px; animation-delay:.25s;">
                            <div class="d-row <?php echo $finalDone && (int)$finalOutcome['winner'] === $finalTeam1 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$finalTeam1; ?>">
                                <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $finalTeam1); ?></div>
                                <div class="d-score <?php echo $finalDone && (int)$finalOutcome['winner'] === $finalTeam1 ? 'green' : 'red'; ?>"><?php echo $finalScore1 !== null ? (int)$finalScore1 : '-'; ?></div>
                            </div>
                            <div class="d-row <?php echo $finalDone && (int)$finalOutcome['winner'] === $finalTeam2 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$finalTeam2; ?>">
                                <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $finalTeam2); ?></div>
                                <div class="d-score <?php echo $finalDone && (int)$finalOutcome['winner'] === $finalTeam2 ? 'green' : 'red'; ?>"><?php echo $finalScore2 !== null ? (int)$finalScore2 : '-'; ?></div>
                            </div>
                            <span class="d-rank first" style="animation-delay:.35s;">1st</span>
                            <span class="d-rank second" style="animation-delay:.4s;">2nd</span>
                        </div>

                        <div class="d-match" style="left:460px; top:380px; animation-delay:.3s;">
                            <div class="d-row <?php echo $thirdDone && (int)$thirdOutcome['winner'] === $thirdTeam1 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$thirdTeam1; ?>">
                                <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $thirdTeam1); ?></div>
                                <div class="d-score <?php echo $thirdDone && (int)$thirdOutcome['winner'] === $thirdTeam1 ? 'green' : 'red'; ?>"><?php echo $thirdScore1 !== null ? (int)$thirdScore1 : '-'; ?></div>
                            </div>
                            <div class="d-row <?php echo $thirdDone && (int)$thirdOutcome['winner'] === $thirdTeam2 ? 'win' : 'lose'; ?>" data-team-id="<?php echo (int)$thirdTeam2; ?>">
                                <div class="d-name"><?php echo renderTeamLabelById($teamMap, $teamLogoMap, $thirdTeam2); ?></div>
                                <div class="d-score <?php echo $thirdDone && (int)$thirdOutcome['winner'] === $thirdTeam2 ? 'green' : 'red'; ?>"><?php echo $thirdScore2 !== null ? (int)$thirdScore2 : '-'; ?></div>
                            </div>
                            <span class="d-rank third" style="animation-delay:.45s;">3rd</span>
                            <span class="d-rank fourth" style="animation-delay:.5s;">4th</span>
                        </div>

                        <div class="d-line gold" style="left:270px; top:84px; width:90px; border-top-width:3px; animation-delay:.28s;"></div>
                        <div class="d-line gold" style="left:360px; top:84px; height:92px; border-right-width:3px; animation-delay:.32s;"></div>
                        <div class="d-line gold" style="left:360px; top:176px; width:100px; border-top-width:3px; animation-delay:.36s;"></div>

                        <div class="d-line" style="left:270px; top:276px; width:90px; border-top-width:3px; animation-delay:.3s;"></div>
                        <div class="d-line" style="left:360px; top:176px; height:100px; border-right-width:3px; animation-delay:.34s;"></div>
                        <div class="d-line" style="left:360px; top:176px; width:100px; border-top-width:3px; animation-delay:.38s;"></div>
                    </div>
                </div>
                <p class="muted" style="margin-top:10px;">Catatan: jika skor belum diisi atau seri, posisi pemenang belum ditentukan.</p>
            </div>
        <?php endif; ?>
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
</script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>
