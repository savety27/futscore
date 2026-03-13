<?php
session_start();

// Load database config
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

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

if (!function_exists('adminHasColumn')) {
    function adminHasColumn(PDO $conn, $tableName, $columnName) {
        try {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tableName);
            if ($safeTable === '') {
                return false;
            }
            $quotedColumn = $conn->quote((string) $columnName);
            $stmt = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quotedColumn}");
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('adminHasTable')) {
    function adminHasTable(PDO $conn, $tableName) {
        try {
            $quotedTable = $conn->quote((string) $tableName);
            $stmt = $conn->query("SHOW TABLES LIKE {$quotedTable}");
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

$challenge_has_event_id = adminHasColumn($conn, 'challenges', 'event_id');
$events_table_exists = adminHasTable($conn, 'events');
$can_join_event_name = $challenge_has_event_id && $events_table_exists;
$goals_has_half_column = adminHasColumn($conn, 'goals', 'half');


// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Operator';
$admin_email = $_SESSION['admin_email'] ?? '';
$current_page = 'challenge';

$operator_id = (int)($_SESSION['admin_id'] ?? 0);
$operator_event_id = (int)($_SESSION['event_id'] ?? 0);
$operator_event_name = 'Event Operator';
$operator_event_image = '';
$operator_event_is_active = true;

if ($operator_id > 0) {
    try {
        $stmtOperator = $conn->prepare("
            SELECT au.event_id, e.name AS event_name, e.image AS event_image,
                   COALESCE(e.is_active, 1) AS event_is_active
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
        // keep defaults
    }
}

if ($operator_event_id > 0 && !$operator_event_is_active) {
    $_SESSION['error_message'] = 'Event operator sedang non-aktif. Mode hanya lihat data.';
    header("Location: challenge.php");
    exit;
}


// Get challenge ID
$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($challenge_id <= 0) {
    header("Location: challenge.php");
    exit;
}

// Initialize variables
$errors = [];
$challenge_data = null;

// Fetch challenge data
try {
    $event_select = $can_join_event_name ? "e.name as event_name," : "NULL as event_name,";
    $event_join = $can_join_event_name ? "LEFT JOIN events e ON c.event_id = e.id" : "";
    $operator_scope_where = '';
    $query_params = [$challenge_id];
    if ($challenge_has_event_id) {
        if ($operator_event_id > 0) {
            $operator_scope_where = " AND c.event_id = ?";
            $query_params[] = $operator_event_id;
        } else {
            $operator_scope_where = " AND 1=0";
        }
    }

    $stmt = $conn->prepare("
        SELECT c.*, 
        {$event_select}
        t1.name as challenger_name, t1.logo as challenger_logo,
        t2.name as opponent_name, t2.logo as opponent_logo
        FROM challenges c
        {$event_join}
        LEFT JOIN teams t1 ON c.challenger_id = t1.id
        LEFT JOIN teams t2 ON c.opponent_id = t2.id
        WHERE c.id = ?
        {$operator_scope_where}
    ");
    $stmt->execute($query_params);
    $challenge_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$challenge_data) {
        header("Location: challenge.php");
        exit;
    }
    
    // PERUBAHAN: Hapus validasi status, semua challenge bisa input hasil
    // Tidak perlu cek apakah status = 'accepted' atau sudah ada skor
    
    // Fetch match statistics
    $match_stats = null;
    $stmtStats = $conn->prepare("SELECT * FROM match_stats WHERE match_id = ?");
    $stmtStats->execute([$challenge_id]);
    $match_stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // Get players for both teams
    $challenge_category = trim((string)($challenge_data['sport_type'] ?? ''));

    $team1_players = [];
    $team1PlayerSql = "SELECT id, name, jersey_number FROM players WHERE team_id = ? AND status = 'active'";
    $team1PlayerParams = [(int)$challenge_data['challenger_id']];
    if ($challenge_category !== '') {
        $team1PlayerSql .= " AND sport_type = ?";
        $team1PlayerParams[] = $challenge_category;
    }
    $team1PlayerSql .= " ORDER BY name ASC";
    $stmtP1 = $conn->prepare($team1PlayerSql);
    $stmtP1->execute($team1PlayerParams);
    $team1_players = $stmtP1->fetchAll(PDO::FETCH_ASSOC);

    $team2_players = [];
    $team2PlayerSql = "SELECT id, name, jersey_number FROM players WHERE team_id = ? AND status = 'active'";
    $team2PlayerParams = [(int)$challenge_data['opponent_id']];
    if ($challenge_category !== '') {
        $team2PlayerSql .= " AND sport_type = ?";
        $team2PlayerParams[] = $challenge_category;
    }
    $team2PlayerSql .= " ORDER BY name ASC";
    $stmtP2 = $conn->prepare($team2PlayerSql);
    $stmtP2->execute($team2PlayerParams);
    $team2_players = $stmtP2->fetchAll(PDO::FETCH_ASSOC);

    $team1PlayerLookup = [];
    foreach ($team1_players as $player) {
        $team1PlayerLookup[(int)($player['id'] ?? 0)] = true;
    }
    $team2PlayerLookup = [];
    foreach ($team2_players as $player) {
        $team2PlayerLookup[(int)($player['id'] ?? 0)] = true;
    }

    // Get existing goals
    $existing_goals = [];
    $goalHalfExpr = $goals_has_half_column
        ? "COALESCE(NULLIF(half, 0), CASE WHEN minute > 45 THEN 2 ELSE 1 END)"
        : "CASE WHEN minute > 45 THEN 2 ELSE 1 END";
    $stmtG = $conn->prepare("SELECT *, {$goalHalfExpr} AS goal_half FROM goals WHERE match_id = ? ORDER BY goal_half ASC, minute ASC");
    $stmtG->execute([$challenge_id]);
    $existing_goals = $stmtG->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching challenge data: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data = [
        'challenger_score' => intval($_POST['challenger_score'] ?? 0),
        'opponent_score' => intval($_POST['opponent_score'] ?? 0),
        'match_status' => trim($_POST['match_status'] ?? 'completed'),
        'match_duration' => trim($_POST['match_duration'] ?? '90'),
        'match_notes' => trim($_POST['match_notes'] ?? ''),
        
        // Statistics
        'team1_possession' => intval($_POST['team1_possession'] ?? 0),
        'team2_possession' => intval($_POST['team2_possession'] ?? 0),
        'team1_shots' => intval($_POST['team1_shots'] ?? 0),
        'team2_shots' => intval($_POST['team2_shots'] ?? 0),
        'team1_fouls' => intval($_POST['team1_fouls'] ?? 0),
        'team2_fouls' => intval($_POST['team2_fouls'] ?? 0)
    ];
    
    // Validation
    if ($form_data['challenger_score'] < 0) $errors['challenger_score'] = "Skor tidak boleh negatif";
    if ($form_data['opponent_score'] < 0) $errors['opponent_score'] = "Skor tidak boleh negatif";
    if (empty($form_data['match_status'])) $errors['match_status'] = "Status match harus diisi";
    if ($form_data['match_duration'] < 0) $errors['match_duration'] = "Durasi tidak boleh negatif";
    
    // Validate possession sum (optional, but good UX)
    if (($form_data['team1_possession'] + $form_data['team2_possession']) != 100 && 
        ($form_data['team1_possession'] > 0 || $form_data['team2_possession'] > 0)) {
        // Just warn or auto-adjust? Let's just accept it but maybe the UI should help.
        // For now, allow it.
    }

    // Validate goal scorers must belong to selected challenge category and proper team.
    if (isset($_POST['goal_player_id']) && is_array($_POST['goal_player_id'])) {
        foreach ($_POST['goal_player_id'] as $index => $rawPlayerId) {
            $playerId = (int)$rawPlayerId;
            if ($playerId <= 0) {
                continue;
            }

            $teamId = (int)($_POST['goal_team_id'][$index] ?? 0);
            $minute = (int)($_POST['goal_minute'][$index] ?? 0);
            $goalHalf = (int)($_POST['goal_half'][$index] ?? 0);

            if ($minute <= 0 || $teamId <= 0) {
                $errors['goal_players'] = "Data pencetak gol belum lengkap.";
                break;
            }
            if ($goalHalf !== 1 && $goalHalf !== 2) {
                $errors['goal_players'] = "Babak gol hanya boleh Babak 1 atau Babak 2.";
                break;
            }

            $isTeam1Valid = $teamId === (int)$challenge_data['challenger_id'] && isset($team1PlayerLookup[$playerId]);
            $isTeam2Valid = $teamId === (int)$challenge_data['opponent_id'] && isset($team2PlayerLookup[$playerId]);

            if (!$isTeam1Valid && !$isTeam2Valid) {
                $errors['goal_players'] = "Pencetak gol harus pemain aktif sesuai kategori challenge.";
                break;
            }
        }
    }
    
    // Determine winner
    $winner_team_id = null;
    if ($form_data['challenger_score'] > $form_data['opponent_score']) {
        $winner_team_id = $challenge_data['challenger_id'];
    } elseif ($form_data['opponent_score'] > $form_data['challenger_score']) {
        $winner_team_id = $challenge_data['opponent_id'];
    }
    
    // If no errors, update database
    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // 1. Update Challenge
            // Keep current challenge status unless match is explicitly completed
            $new_status = $challenge_data['status'];
            if ($form_data['match_status'] === 'completed') {
                $new_status = 'completed';
            }
            
            $stmt = $conn->prepare("
                UPDATE challenges SET 
                    challenger_score = ?, 
                    opponent_score = ?, 
                    winner_team_id = ?, 
                    status = ?,
                    match_status = ?, 
                    match_duration = ?, 
                    match_notes = ?,
                    result_entered_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $form_data['challenger_score'],
                $form_data['opponent_score'],
                $winner_team_id,
                $new_status,
                $form_data['match_status'],
                $form_data['match_duration'],
                $form_data['match_notes'],
                $challenge_id
            ]);

            // 2. Update/Insert Match Stats
            $stmtCheck = $conn->prepare("SELECT id FROM match_stats WHERE match_id = ?");
            $stmtCheck->execute([$challenge_id]);
            $exists = $stmtCheck->fetchColumn();

            if ($exists) {
                $stmtStats = $conn->prepare("
                    UPDATE match_stats SET 
                        team1_possession = ?, team2_possession = ?,
                        team1_shots_on_target = ?, team2_shots_on_target = ?,
                        team1_fouls = ?, team2_fouls = ?
                    WHERE match_id = ?
                ");
                $stmtStats->execute([
                    $form_data['team1_possession'], $form_data['team2_possession'],
                    $form_data['team1_shots'], $form_data['team2_shots'],
                    $form_data['team1_fouls'], $form_data['team2_fouls'],
                    $challenge_id
                ]);
            } else {
                $stmtStats = $conn->prepare("
                    INSERT INTO match_stats (
                        match_id, 
                        team1_possession, team2_possession,
                        team1_shots_on_target, team2_shots_on_target,
                        team1_fouls, team2_fouls
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtStats->execute([
                    $challenge_id,
                    $form_data['team1_possession'], $form_data['team2_possession'],
                    $form_data['team1_shots'], $form_data['team2_shots'],
                    $form_data['team1_fouls'], $form_data['team2_fouls']
                ]);
            }

            // 3. Update Goals (Delete then Insert)
            $stmtDelGoals = $conn->prepare("DELETE FROM goals WHERE match_id = ?");
            $stmtDelGoals->execute([$challenge_id]);

            if (isset($_POST['goal_player_id']) && is_array($_POST['goal_player_id'])) {
                if ($goals_has_half_column) {
                    $stmtInsGoal = $conn->prepare("INSERT INTO goals (match_id, player_id, team_id, minute, half) VALUES (?, ?, ?, ?, ?)");
                } else {
                    $stmtInsGoal = $conn->prepare("INSERT INTO goals (match_id, player_id, team_id, minute) VALUES (?, ?, ?, ?)");
                }
                foreach ($_POST['goal_player_id'] as $index => $player_id) {
                    if (empty($player_id)) continue;
                    
                    $minute = intval($_POST['goal_minute'][$index] ?? 0);
                    $team_id = intval($_POST['goal_team_id'][$index] ?? 0);
                    $goal_half = intval($_POST['goal_half'][$index] ?? 0);
                    if ($goal_half !== 2) {
                        $goal_half = 1;
                    }
                    
                    if ($goals_has_half_column) {
                        $stmtInsGoal->execute([$challenge_id, $player_id, $team_id, $minute, $goal_half]);
                    } else {
                        $stmtInsGoal->execute([$challenge_id, $player_id, $team_id, $minute]);
                    }
                }
            }

            $conn->commit();
            
            $_SESSION['success_message'] = "Hasil pertandingan dan statistik berhasil diinput!";
            header("Location: challenge.php");
            exit;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $errors['database'] = "Gagal menyimpan hasil: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Input Hasil Challenge</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../pelatih/css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../pelatih/css/style.css'); ?>">
<link rel="stylesheet" href="css/challenge.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/challenge.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
/* Heritage Result Custom Styling */
.challenge-container {
    padding-bottom: 60px;
}

.form-container {
    background: var(--heritage-card);
    border: 1px solid var(--heritage-border);
    border-radius: 28px;
    padding: 40px;
    box-shadow: var(--soft-shadow);
    margin-bottom: 40px;
}

.form-section {
    margin-bottom: 40px;
}

/* Score Input Section Heritage */
.score-section {
    background: #fdfcfb;
    border: 1px solid var(--heritage-border);
    border-radius: 24px;
    padding: 40px;
    margin: 20px 0;
}

.score-input-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 60px;
    flex-wrap: wrap;
}

.team-score-box {
    text-align: center;
    min-width: 220px;
}

.team-logo-medium {
    width: 100px;
    height: 100px;
    border-radius: 20px;
    object-fit: cover;
    border: 2px solid var(--heritage-border);
    background: white;
    padding: 8px;
    margin-bottom: 20px;
    box-shadow: 0 10px 20px rgba(0,0,0,0.05);
}

.team-name {
    font-family: var(--font-display);
    font-size: 1.25rem;
    color: var(--heritage-text);
    margin-bottom: 15px;
    font-weight: 800;
}

.score-input {
    width: 110px;
    height: 110px;
    font-family: var(--font-display);
    font-size: 3.5rem;
    font-weight: 800;
    text-align: center;
    border: 2px solid var(--heritage-border);
    border-radius: 24px;
    background: white;
    color: var(--heritage-text);
    transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
}

.score-input:focus {
    outline: none;
    border-color: var(--heritage-gold);
    box-shadow: 0 0 0 6px rgba(180, 83, 9, 0.05);
    transform: translateY(-4px);
}

.vs-symbol {
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 900;
    color: var(--heritage-gold);
    background: var(--heritage-bg);
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--heritage-border);
    font-style: italic;
}

.result-preview {
    text-align: center;
    margin-top: 30px;
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--heritage-gold);
    min-height: 40px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Goal Entry Styles Heritage */
.goal-entry-row {
    display: grid;
    grid-template-columns: 1.5fr 2fr 1fr 1fr 60px;
    gap: 16px;
    align-items: center;
    background: #fdfcfb;
    padding: 20px;
    border-radius: 20px;
    margin-bottom: 12px;
    border: 1px solid var(--heritage-border);
    transition: all 0.3s ease;
}

.goal-entry-row:hover {
    background: white;
    border-color: var(--heritage-gold);
    transform: translateX(4px);
}

.btn-add-goal {
    margin-top: 20px;
    background: #fdfcfb;
    color: var(--heritage-text);
    border: 2px dashed var(--heritage-border);
    width: 100%;
    padding: 18px;
    border-radius: 20px;
    cursor: pointer;
    font-family: var(--font-display);
    font-weight: 700;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.btn-add-goal:hover {
    background: white;
    border-color: var(--heritage-gold);
    color: var(--heritage-gold);
}

.btn-remove-goal {
    color: var(--heritage-crimson);
    background: #fef2f2;
    border: 1px solid #fee2e2;
    width: 44px;
    height: 44px;
    border-radius: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-remove-goal:hover {
    background: var(--heritage-crimson);
    color: white;
    border-color: var(--heritage-crimson);
}

.form-label {
    font-family: var(--font-display);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    color: var(--heritage-text-muted);
    margin-bottom: 8px;
    display: block;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    background: #fdfcfb;
    border: 1px solid var(--heritage-border);
    border-radius: 16px;
    padding: 12px 20px;
    font-family: var(--font-body);
    font-size: 1rem;
    color: var(--heritage-text);
    transition: all 0.3s ease;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--heritage-gold);
    background: white;
    box-shadow: 0 0 0 4px rgba(180, 83, 9, 0.05);
}

@media (max-width: 1024px) {
    .goal-entry-row {
        grid-template-columns: 1fr 1fr;
    }
    .btn-remove-goal {
        grid-column: span 2;
        width: 100%;
    }
}

@media (max-width: 768px) {
    .form-container { padding: 25px; }
    .score-input-container { gap: 30px; }
    .score-input { width: 90px; height: 90px; font-size: 2.5rem; }
}
</style>
</head>
<body>


<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar reveal">
            <div class="greeting">
                <h1>Input Hasil Pertandingan ⚽</h1>
                <p>Update statistik dan skor akhir pertandingan</p>
            </div>
            
            <div class="user-actions">
                <a href="../operator/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Keluar
                </a>
            </div>
        </div>

        <div class="challenge-container">
            <!-- Editorial Header -->
            <header class="dashboard-hero reveal d-1">
                <div class="hero-content">
                    <span class="hero-label">Manajemen Pertandingan</span>
                    <h1 class="hero-title">Input Hasil</h1>
                    <p class="hero-description">Challenge Code: <strong><?php echo htmlspecialchars($challenge_data['challenge_code'] ?? ''); ?></strong>. Masukkan skor dan detail pencetak gol di bawah ini.</p>
                </div>
                <div class="hero-actions">
                    <a href="challenge.php" class="btn-premium btn-export">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </header>

            <!-- ERROR MESSAGES -->
            <?php if (isset($errors['database'])): ?>
            <div class="alert alert-danger reveal d-2">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $errors['database']; ?></span>
            </div>
            <?php endif; ?>

            <!-- INPUT RESULT FORM -->
            <div class="form-container reveal d-2">
                <form method="POST" action="" id="resultForm">
                <input type="hidden" name="id" value="<?php echo $challenge_id; ?>">
                
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-title-wrap">
                            <h2 class="section-title">Skor Pertandingan</h2>
                            <div class="section-line"></div>
                        </div>
                    </div>
                    
                    <!-- Score Input Section -->
                    <div class="score-section">
                        <div class="score-input-container">
                            <div class="team-score-box">
                                <?php if (!empty($challenge_data['challenger_logo'])): ?>
                                    <img src="../images/teams/<?php echo htmlspecialchars($challenge_data['challenger_logo'] ?? ''); ?>" 
                                         alt="<?php echo htmlspecialchars($challenge_data['challenger_name'] ?? ''); ?>" 
                                         class="team-logo-medium">
                                <?php else: ?>
                                    <div class="team-logo-medium" style="background: linear-gradient(135deg, #f0f0f0, #e0e0e0); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-shield-alt" style="color: #999; font-size: 36px;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="team-name"><?php echo htmlspecialchars($challenge_data['challenger_name'] ?? ''); ?></div>
                                <input type="number" 
                                       id="challenger_score" 
                                       name="challenger_score" 
                                       class="score-input <?php echo isset($errors['challenger_score']) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo isset($form_data['challenger_score']) ? $form_data['challenger_score'] : ($challenge_data['challenger_score'] !== null ? $challenge_data['challenger_score'] : '0'); ?>"
                                       min="0" 
                                       max="50" 
                                       required>
                                <?php if (isset($errors['challenger_score'])): ?>
                                    <span class="error"><?php echo $errors['challenger_score']; ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="vs-symbol">VS</div>
                            
                            <div class="team-score-box">
                                <?php if (!empty($challenge_data['opponent_logo'])): ?>
                                    <img src="../images/teams/<?php echo htmlspecialchars($challenge_data['opponent_logo'] ?? ''); ?>" 
                                         alt="<?php echo htmlspecialchars($challenge_data['opponent_name'] ?? ''); ?>" 
                                         class="team-logo-medium">
                                <?php else: ?>
                                    <div class="team-logo-medium" style="background: linear-gradient(135deg, #f0f0f0, #e0e0e0); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-shield-alt" style="color: #999; font-size: 36px;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="team-name"><?php echo htmlspecialchars($challenge_data['opponent_name'] ?? ''); ?></div>
                                <input type="number" 
                                       id="opponent_score" 
                                       name="opponent_score" 
                                       class="score-input <?php echo isset($errors['opponent_score']) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo isset($form_data['opponent_score']) ? $form_data['opponent_score'] : ($challenge_data['opponent_score'] !== null ? $challenge_data['opponent_score'] : '0'); ?>"
                                       min="0" 
                                       max="50" 
                                       required>
                                <?php if (isset($errors['opponent_score'])): ?>
                                    <span class="error"><?php echo $errors['opponent_score']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Result Preview -->
                        <div class="result-preview" id="resultPreview">
                            <!-- Filled by JavaScript -->
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-header">
                        <div class="section-title-wrap">
                            <h2 class="section-title">Pencetak Gol</h2>
                            <div class="section-line"></div>
                        </div>
                    </div>
                    <?php if (isset($errors['goal_players'])): ?>
                        <div class="alert alert-danger" style="margin-bottom: 12px;">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $errors['goal_players']; ?>
                        </div>
                    <?php endif; ?>
                    <div class="note" style="margin-bottom: 20px; color: var(--heritage-text-muted); font-size: 0.9rem;">
                        <i class="fas fa-info-circle" style="color: var(--heritage-gold);"></i> Daftar pemain difilter untuk: <strong><?php echo htmlspecialchars($challenge_data['sport_type'] ?? '-'); ?></strong>
                    </div>
                    
                    <div id="goal-entries">
                        <!-- Goal rows will be added here -->
                    </div>
                    
                    <div class="btn-add-goal" id="btnAddGoal">
                        <i class="fas fa-plus-circle"></i> Tambah Pencetak Gol
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-header">
                        <div class="section-title-wrap">
                            <h2 class="section-title">Detail Pertandingan</h2>
                            <div class="section-line"></div>
                        </div>
                    </div>
                    
                    <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                        <div class="form-group">
                            <label class="form-label" for="match_status">
                                Status Match <span class="required">*</span>
                            </label>
                            <select id="match_status" name="match_status" 
                                    class="form-select <?php echo isset($errors['match_status']) ? 'is-invalid' : ''; ?>" 
                                    required>
                                <option value="completed" <?php echo (isset($form_data['match_status']) && $form_data['match_status'] == 'completed') || $challenge_data['match_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="scheduled" <?php echo (isset($form_data['match_status']) && $form_data['match_status'] == 'scheduled') || $challenge_data['match_status'] == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="ongoing" <?php echo (isset($form_data['match_status']) && $form_data['match_status'] == 'ongoing') || $challenge_data['match_status'] == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="coming_soon" <?php echo (isset($form_data['match_status']) && $form_data['match_status'] == 'coming_soon') || $challenge_data['match_status'] == 'coming_soon' ? 'selected' : ''; ?>>Coming Soon</option>
                                <option value="postponed" <?php echo (isset($form_data['match_status']) && $form_data['match_status'] == 'postponed') || $challenge_data['match_status'] == 'postponed' ? 'selected' : ''; ?>>Postponed</option>
                                <option value="cancelled" <?php echo (isset($form_data['match_status']) && $form_data['match_status'] == 'cancelled') || $challenge_data['match_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="abandoned" <?php echo (isset($form_data['match_status']) && $form_data['match_status'] == 'abandoned') || $challenge_data['match_status'] == 'abandoned' ? 'selected' : ''; ?>>Abandoned</option>
                            </select>
                            <?php if (isset($errors['match_status'])): ?>
                                <span class="error"><?php echo $errors['match_status']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="match_duration">
                                Durasi Match (menit) <span class="required">*</span>
                            </label>
                            <input type="number" 
                                   id="match_duration" 
                                   name="match_duration" 
                                   class="form-input <?php echo isset($errors['match_duration']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo isset($form_data['match_duration']) ? $form_data['match_duration'] : ($challenge_data['match_duration'] ? $challenge_data['match_duration'] : '90'); ?>"
                                   min="0" 
                                   max="180" 
                                   required>
                            <?php if (isset($errors['match_duration'])): ?>
                                <span class="error"><?php echo $errors['match_duration']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    
                    <div class="form-group" style="margin-top: 24px;">
                        <label class="form-label" for="match_notes">
                            Catatan Pertandingan
                        </label>
                        <textarea id="match_notes" name="match_notes" class="form-textarea" style="min-height: 120px;"
                                  placeholder="Masukkan catatan pertandingan (kondisi lapangan, kejadian penting, dll)..."><?php echo isset($form_data['match_notes']) ? htmlspecialchars($form_data['match_notes'] ?? '') : ($challenge_data['match_notes'] ? htmlspecialchars($challenge_data['match_notes'] ?? '') : ''); ?></textarea>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 15px; margin-top: 40px; border-top: 1px solid var(--heritage-border); padding-top: 30px;">
                    <button type="reset" class="btn-premium btn-export">
                        <i class="fas fa-redo"></i>
                        Reset
                    </button>
                    <button type="submit" class="btn-premium btn-add">
                        <i class="fas fa-save"></i>
                        Simpan Hasil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Basic score calculation preview
    const cScore = document.getElementById('challenger_score');
    const oScore = document.getElementById('opponent_score');
    const preview = document.getElementById('resultPreview');
    const cName = <?php echo json_encode($challenge_data['challenger_name'] ?? ''); ?>;
    const oName = <?php echo json_encode($challenge_data['opponent_name'] ?? ''); ?>;

    function updatePreview() {
        if (!cScore || !oScore || !preview) return;
        const cs = parseInt(cScore.value) || 0;
        const os = parseInt(oScore.value) || 0;
        
        let txt = '';
        if (cs > os) {
            txt = `${cName} Menang`;
        } else if (os > cs) {
            txt = `${oName} Menang`;
        } else {
            txt = 'Seri (Draw)';
        }
        preview.textContent = txt;
    }
    
    if (cScore) cScore.addEventListener('input', updatePreview);
    if (oScore) oScore.addEventListener('input', updatePreview);
    updatePreview();

    // Goal Scorers Logic
    const team1Id = <?php echo json_encode($challenge_data['challenger_id'] ?? ''); ?>;
    const team1Name = <?php echo json_encode($challenge_data['challenger_name'] ?? ''); ?>;
    const team2Id = <?php echo json_encode($challenge_data['opponent_id'] ?? ''); ?>;
    const team2Name = <?php echo json_encode($challenge_data['opponent_name'] ?? ''); ?>;
    
    const team1Players = <?php echo json_encode($team1_players ?: []); ?>;
    const team2Players = <?php echo json_encode($team2_players ?: []); ?>;
    const existingGoals = <?php echo json_encode($existing_goals ?: []); ?>;

    const btnAddGoal = document.getElementById('btnAddGoal');
    const goalContainer = document.getElementById('goal-entries');

    function createGoalRow(goalData = null) {
        if (!goalContainer) return;

        const row = document.createElement('div');
        row.className = 'goal-entry-row';
        
        // Half Select (pilih babak dulu)
        const halfCol = document.createElement('div');
        const halfSelect = document.createElement('select');
        halfSelect.name = 'goal_half[]';
        halfSelect.className = 'form-select';
        halfSelect.required = true;
        halfSelect.innerHTML = `
            <option value="">-- Pilih Babak --</option>
            <option value="1">Babak 1</option>
            <option value="2">Babak 2</option>
        `;
        if (goalData && (goalData.goal_half || goalData.half)) {
            halfSelect.value = String(goalData.goal_half || goalData.half);
        } else {
            halfSelect.value = '';
        }
        halfCol.appendChild(halfSelect);

        // Team Select
        const teamCol = document.createElement('div');
        const teamSelect = document.createElement('select');
        teamSelect.name = 'goal_team_id[]';
        teamSelect.className = 'form-select';
        teamSelect.required = true;
        
        let tOpts = '<option value="">-- Pilih Tim --</option>';
        tOpts += `<option value="${team1Id}">${team1Name}</option>`;
        tOpts += `<option value="${team2Id}">${team2Name}</option>`;
        teamSelect.innerHTML = tOpts;

        if (goalData && goalData.team_id) {
            teamSelect.value = goalData.team_id;
        }

        teamCol.appendChild(teamSelect);

        // Player Select
        const playerCol = document.createElement('div');
        const playerSelect = document.createElement('select');
        playerSelect.name = 'goal_player_id[]';
        playerSelect.className = 'form-select';
        playerSelect.required = true;
        playerSelect.innerHTML = '<option value="">-- Pilih Pemain --</option>';

        if (goalData && goalData.player_id) {
            const tempVal = goalData.player_id;
            // Postpone setting value until options are populated below
            setTimeout(() => { playerSelect.value = tempVal; }, 10);
        }
        playerCol.appendChild(playerSelect);

        // Populate players based on team
        function updatePlayerOpts(teamId) {
            let pOpts = '<option value="">-- Pilih Pemain --</option>';
            let players = [];
            if (teamId == team1Id) players = team1Players;
            else if (teamId == team2Id) players = team2Players;

            players.forEach(p => {
                const jn = p.jersey_number ? ` (#${p.jersey_number})` : '';
                pOpts += `<option value="${p.id}">${p.name}${jn}</option>`;
            });
            if (!players.length) {
                pOpts += '<option value="" disabled>(Tidak ada pemain aktif di kategori ini)</option>';
            }
            playerSelect.innerHTML = pOpts;
        }

        teamSelect.addEventListener('change', function() {
            updatePlayerOpts(this.value);
        });

        // Initial setup for existing goal
        if (goalData && goalData.team_id) {
            updatePlayerOpts(goalData.team_id);
        }

        // Minute Input
        const minCol = document.createElement('div');
        const minInput = document.createElement('input');
        minInput.type = 'number';
        minInput.name = 'goal_minute[]';
        minInput.className = 'form-input';
        minInput.placeholder = 'Menit (e.g. 45)';
        minInput.min = '1';
        minInput.required = true;
        if (goalData && goalData.minute) {
            minInput.value = goalData.minute;
        }
        minCol.appendChild(minInput);

        // Remove Button
        const delCol = document.createElement('div');
        const delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'btn-remove-goal';
        delBtn.innerHTML = '<i class="fas fa-trash"></i>';
        delBtn.onclick = function() {
            row.remove();
        };
        delCol.appendChild(delBtn);

        // Assemble row
        row.appendChild(teamCol);
        row.appendChild(playerCol);
        row.appendChild(minCol);
        row.appendChild(halfCol);
        row.appendChild(delCol);

        goalContainer.appendChild(row);
    }

    if (btnAddGoal) {
        btnAddGoal.addEventListener('click', function() {
            createGoalRow();
        });
    }

    // Load existing goals
    if (existingGoals && existingGoals.length > 0) {
        existingGoals.forEach(g => {
            createGoalRow(g);
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
