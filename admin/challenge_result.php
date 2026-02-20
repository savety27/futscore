<?php
session_start();

// Load database config
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

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}


// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';


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
    $stmt = $conn->prepare("
        SELECT c.*, 
        t1.name as challenger_name, t1.logo as challenger_logo,
        t2.name as opponent_name, t2.logo as opponent_logo
        FROM challenges c
        LEFT JOIN teams t1 ON c.challenger_id = t1.id
        LEFT JOIN teams t2 ON c.opponent_id = t2.id
        WHERE c.id = ?
    ");
    $stmt->execute([$challenge_id]);
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
    $team1_players = [];
    $stmtP1 = $conn->prepare("SELECT id, name, jersey_number FROM players WHERE team_id = ? AND status = 'active' ORDER BY name ASC");
    $stmtP1->execute([$challenge_data['challenger_id']]);
    $team1_players = $stmtP1->fetchAll(PDO::FETCH_ASSOC);

    $team2_players = [];
    $stmtP2 = $conn->prepare("SELECT id, name, jersey_number FROM players WHERE team_id = ? AND status = 'active' ORDER BY name ASC");
    $stmtP2->execute([$challenge_data['opponent_id']]);
    $team2_players = $stmtP2->fetchAll(PDO::FETCH_ASSOC);

    // Get existing goals
    $existing_goals = [];
    $stmtG = $conn->prepare("SELECT * FROM goals WHERE match_id = ? ORDER BY minute ASC");
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
        'match_official' => trim($_POST['match_official'] ?? ''),
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
                    match_official = ?, 
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
                $form_data['match_official'],
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
                $stmtInsGoal = $conn->prepare("INSERT INTO goals (match_id, player_id, team_id, minute) VALUES (?, ?, ?, ?)");
                foreach ($_POST['goal_player_id'] as $index => $player_id) {
                    if (empty($player_id)) continue;
                    
                    $minute = intval($_POST['goal_minute'][$index] ?? 0);
                    $team_id = intval($_POST['goal_team_id'][$index] ?? 0);
                    
                    $stmtInsGoal->execute([$challenge_id, $player_id, $team_id, $minute]);
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
:root {
    --primary: #0f2744;
    --secondary: #f59e0b;
    --accent: #3b82f6;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --light: #F8F9FA;
    --dark: #1e293b;
    --gray: #64748b;
    --glass-white: rgba(255, 255, 255, 0.85);
    --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
    --premium-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --transition: cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%);
    color: var(--dark);
    min-height: 100vh;
    overflow-x: hidden;
}

.wrapper {
    display: flex;
    min-height: 100vh;
}

/* ===== MAIN CONTENT ===== */
.main {
    flex: 1;
    padding: 30px;
    margin-left: 280px;
    width: calc(100% - 280px);
    max-width: calc(100vw - 280px);
    overflow-x: hidden;
    transition: var(--transition);
}

/* Topbar */
.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    padding: 20px 25px;
    background: white;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    animation: slideDown 0.5s ease-out;
}

.greeting h1 {
    font-size: 28px;
    color: var(--primary);
    margin-bottom: 5px;
}

.greeting p {
    color: var(--gray);
    font-size: 14px;
}

.user-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}

.logout-btn {
    background: linear-gradient(135deg, var(--danger) 0%, #B71C1C 100%);
    color: white;
    padding: 12px 28px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    box-shadow: 0 5px 15px rgba(211, 47, 47, 0.2);
}

.logout-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(211, 47, 47, 0.3);
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    flex-wrap: wrap;
    gap: 15px;
}

.page-title {
    font-size: 28px;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title i {
    color: var(--secondary);
    font-size: 32px;
}

.search-bar {
    position: relative;
    width: 400px;
}

.search-bar input {
    width: 100%;
    padding: 15px 50px 15px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 16px;
    transition: var(--transition);
    background: #f8f9fa;
}

.search-bar input:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
}

.search-bar button {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--primary);
    font-size: 18px;
    cursor: pointer;
}

.action-buttons {
    display: flex;
    gap: 15px;
}

.btn {
    padding: 12px 25px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    font-size: 15px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
    box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(10, 36, 99, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #4CAF50);
    color: white;
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.2);
}

.btn-success:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(46, 125, 50, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
}

.btn-secondary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
}

/* Form Styles */
.form-container {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: var(--card-shadow);
    margin-bottom: 30px;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 20px;
    color: var(--primary);
    margin-bottom: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.required {
    color: var(--danger);
}

.form-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 16px;
    transition: var(--transition);
    background: #f8f9fa;
    font-family: inherit;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
}

.form-textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 16px;
    transition: var(--transition);
    background: #f8f9fa;
    font-family: inherit;
    resize: vertical;
    min-height: 100px;
}

.form-select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 16px;
    transition: var(--transition);
    background: #f8f9fa;
    cursor: pointer;
}

.form-select:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
}

/* Action Buttons */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #f0f0f0;
}

/* Alert */
.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-danger {
    background: rgba(211, 47, 47, 0.1);
    border-left: 4px solid var(--danger);
    color: var(--danger);
}

.alert-success {
    background: rgba(46, 125, 50, 0.1);
    border-left: 4px solid var(--success);
    color: var(--success);
}

/* Error styling */
.error {
    color: var(--danger);
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.is-invalid {
    border-color: var(--danger) !important;
}

/* Score Input Section */
.score-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    padding: 30px;
    margin: 20px 0;
}

.score-input-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 40px;
    flex-wrap: wrap;
}

.team-score-box {
    text-align: center;
    min-width: 200px;
}

.team-logo-medium {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    margin-bottom: 15px;
}

.team-name {
    font-size: 18px;
    color: var(--dark);
    margin-bottom: 10px;
    font-weight: 600;
}

.score-input {
    width: 100px;
    height: 100px;
    font-size: 48px;
    font-weight: bold;
    text-align: center;
    border: 3px solid #e0e0e0;
    border-radius: 12px;
    background: white;
    transition: var(--transition);
}

.score-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
}

.vs-symbol {
    font-size: 32px;
    font-weight: bold;
    color: var(--secondary);
    background: var(--primary);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.result-preview {
    text-align: center;
    margin-top: 20px;
    font-size: 24px;
    font-weight: bold;
    color: var(--primary);
    min-height: 40px;
}


/* =========================================
   MOBILE RESPONSIVE DESIGN
   ========================================= */

/* Default: Hide mobile-only elements on desktop */


/* ===== TABLET (max-width: 1024px) ===== */
@media screen and (max-width: 1024px) {

    .main {
        margin-left: 240px;
        width: calc(100% - 240px);
        max-width: calc(100vw - 240px);
    }
}

/* ===== MOBILE LANDSCAPE (max-width: 768px) ===== */
@media screen and (max-width: 768px) {
    


    /* Main Content: Full width on mobile */
    .main {
        margin-left: 0;
        padding: 20px 15px;
        width: 100%;
        max-width: 100vw;
    }

    /* Topbar: Stack vertically */
    .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
        padding: 20px;
    }

    .greeting h1 {
        font-size: 24px;
    }

    .user-actions {
        width: 100%;
        display: flex;
        justify-content: flex-end;
    }

    /* Page Header: Stack vertically */
    .page-header {
        flex-direction: column;
        gap: 20px;
        align-items: flex-start;
    }

    .search-bar {
        width: 100%;
        max-width: 100%;
    }

    .action-buttons {
        width: 100%;
        flex-wrap: wrap;
    }

    .btn {
        flex: 1;
        justify-content: center;
    }
    
    /* Form Layout adaptations */
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column-reverse;
        gap: 10px;
    }
    
    .score-input-container {
        flex-direction: column;
        gap: 20px;
    }
    
    .team-score-box {
        min-width: 100%;
    }
    
    .score-input {
        width: 80px;
        height: 80px;
        font-size: 36px;
    }
}

/* ===== MOBILE PORTRAIT (max-width: 480px) ===== */
@media screen and (max-width: 480px) {
    
    /* Reduce font sizes */
    .greeting h1 {
        font-size: 20px;
    }
    
    .greeting p {
        font-size: 13px;
    }

    .page-title {
        font-size: 20px;
    }

    .page-title i {
        font-size: 24px;
    }


    .logo,
    .logo img {
        max-width: 120px;
    }

    

    .menu-link {
        padding: 14px 15px;
        font-size: 15px;
    }

    .menu-icon {
        font-size: 20px;
        width: 28px;
    }


    /* Compact buttons */
    .btn {
        padding: 10px 18px;
        font-size: 14px;
    }

    .logout-btn {
        padding: 10px 20px;
        font-size: 14px;
    }
    
    .team-logo-medium {
        width: 60px;
        height: 60px;
    }
    
    .team-name {
        font-size: 16px;
    }
}

/* Goal Entry Styles */
.goal-entry-row {
    display: grid;
    grid-template-columns: 2fr 1fr 2fr 50px;
    gap: 15px;
    align-items: center;
    background: #f8f9fa;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 10px;
    border: 1px solid #e0e0e0;
    transition: var(--transition);
}

.goal-entry-row:hover {
    background: white;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.btn-add-goal {
    margin-top: 10px;
    background: rgba(10, 36, 99, 0.1);
    color: var(--primary);
    border: 2px dashed var(--primary);
    width: 100%;
    padding: 15px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.btn-add-goal:hover {
    background: var(--primary);
    color: white;
}

.btn-remove-goal {
    color: var(--danger);
    background: rgba(211, 47, 47, 0.1);
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
}

.btn-remove-goal:hover {
    background: var(--danger);
    color: white;
}
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
</head>
<body>


<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="greeting">
                <h1>Input Hasil Pertandingan ⚽</h1>
                <p>Challenge: <?php echo htmlspecialchars($challenge_data['challenge_code'] ?? ''); ?></p>
            </div>
            
            <div class="user-actions">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-futbol"></i>
                <span>Input Hasil Challenge</span>
            </div>
            <a href="challenge_view.php?id=<?php echo $challenge_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Detail
            </a>
        </div>

        <!-- ERROR MESSAGES -->
        <?php if (isset($errors['database'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $errors['database']; ?>
        </div>
        <?php endif; ?>

        <!-- INPUT RESULT FORM -->
        <div class="form-container">
            <form method="POST" action="" id="resultForm">
                <input type="hidden" name="id" value="<?php echo $challenge_id; ?>">
                
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-trophy"></i>
                        Skor Pertandingan
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
                    <div class="section-title">
                        <i class="fas fa-futbol"></i>
                        Pencetak Gol
                    </div>
                    
                    <div id="goal-entries">
                        <!-- Goal rows will be added here -->
                    </div>
                    
                    <div class="btn-add-goal" id="btnAddGoal">
                        <i class="fas fa-plus-circle"></i> Tambah Pencetak Gol
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Detail Pertandingan
                    </div>
                    
                    <div class="form-grid">
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
                    
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="match_official">
                                Wasit/Pengawas Pertandingan
                            </label>
                            <input type="text" 
                                   id="match_official" 
                                   name="match_official" 
                                   class="form-input" 
                                   value="<?php echo isset($form_data['match_official']) ? htmlspecialchars($form_data['match_official'] ?? '') : ($challenge_data['match_official'] ? htmlspecialchars($challenge_data['match_official'] ?? '') : ''); ?>"
                                   placeholder="Masukkan nama wasit...">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="match_notes">
                                Catatan Pertandingan
                            </label>
                            <textarea id="match_notes" name="match_notes" class="form-textarea" 
                                      placeholder="Masukkan catatan pertandingan (kondisi lapangan, kejadian penting, dll)..."><?php echo isset($form_data['match_notes']) ? htmlspecialchars($form_data['match_notes'] ?? '') : ($challenge_data['match_notes'] ? htmlspecialchars($challenge_data['match_notes'] ?? '') : ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>
                        Reset
                    </button>
                    <button type="submit" class="btn btn-primary">
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
        // Close menu when clicking overlay
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            document.body.classList.remove('menu-open');
        });

        // Close menu when clicking a menu link (better UX on mobile)
        const menuLinks = document.querySelectorAll('.menu-link');
        menuLinks.forEach(function(link) {
            // Only close if it's not a submenu toggle
            if (!link.querySelector('.menu-arrow')) {
                link.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('menu-open');
                });
            }
        });
    }
    
    
</script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>