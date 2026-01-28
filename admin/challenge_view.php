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

// Get challenge ID
$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($challenge_id <= 0) {
    header("Location: challenge.php");
    exit;
}

// Menu items
$menu_items = [
    'dashboard' => ['icon' => '🏠', 'name' => 'Dashboard', 'submenu' => false],
    'master' => ['icon' => '📊', 'name' => 'Master Data', 'submenu' => true, 'items' => ['player', 'team', 'team_staff']],
    'event' => ['icon' => '📅', 'name' => 'Event', 'submenu' => true, 'items' => ['event', 'player_liga', 'staff_liga']],
    'match' => ['icon' => '⚽', 'name' => 'Match', 'submenu' => false],
    'challenge' => ['icon' => '🏆', 'name' => 'Challenge', 'submenu' => false],
    'training' => ['icon' => '🎯', 'name' => 'Training', 'submenu' => false],
    'settings' => ['icon' => '⚙️', 'name' => 'Settings', 'submenu' => false]
];

$academy_name = "Marbella Academy";
$email = "marbellacommunitycenter@gmail.com";

// Fetch challenge data with all details
try {
    $stmt = $conn->prepare("
        SELECT c.*, 
        t1.name as challenger_name, t1.logo as challenger_logo, t1.sport_type as challenger_sport, t1.coach as challenger_coach,
        t2.name as opponent_name, t2.logo as opponent_logo, t2.coach as opponent_coach,
        l.name as venue_name, l.location as venue_location, l.capacity as venue_capacity
        FROM challenges c
        LEFT JOIN teams t1 ON c.challenger_id = t1.id
        LEFT JOIN teams t2 ON c.opponent_id = t2.id
        LEFT JOIN venues l ON c.venue_id = l.id
        WHERE c.id = ?
    ");
    $stmt->execute([$challenge_id]);
    $challenge_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$challenge_data) {
        header("Location: challenge.php");
        exit;
    }
    
} catch (PDOException $e) {
    die("Error fetching challenge data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Challenge - FutScore</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Using the same CSS structure as team_view.php */
:root {
    --primary: #0A2463;
    --secondary: #FFD700;
    --accent: #4CC9F0;
    --success: #2E7D32;
    --warning: #F9A826;
    --danger: #D32F2F;
    --light: #F8F9FA;
    --dark: #1A1A2E;
    --gray: #6C757D;
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
    color: var(--dark);
    min-height: 100vh;
    overflow-x: hidden;
}

.wrapper {
    display: flex;
    min-height: 100vh;
}

/* ===== SIDEBAR ===== */
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, var(--primary) 0%, #1a365d 100%);
    color: white;
    padding: 0;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
    box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
    transition: var(--transition);
}

.sidebar-header {
    padding: 30px 25px;
    text-align: center;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 2px solid var(--secondary);
}

.logo-container {
    position: relative;
    display: inline-block;
}

.logo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--secondary) 0%, #FFEC8B 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    border: 4px solid white;
    box-shadow: 0 0 25px rgba(255, 215, 0, 0.3);
    position: relative;
    overflow: hidden;
    transition: var(--transition);
}

.logo:hover {
    transform: rotate(15deg) scale(1.05);
    box-shadow: 0 0 35px rgba(255, 215, 0, 0.5);
}

.logo::before {
    content: "⚽";
    font-size: 48px;
    color: var(--primary);
}

.academy-info {
    text-align: center;
    animation: fadeIn 0.8s ease-out;
}

.academy-name {
    font-size: 22px;
    font-weight: 700;
    color: var(--secondary);
    margin-bottom: 8px;
    letter-spacing: 0.5px;
}

.academy-email {
    font-size: 14px;
    opacity: 0.9;
    color: rgba(255, 255, 255, 0.8);
}

/* Menu */
.menu {
    padding: 25px 15px;
}

.menu-item {
    margin-bottom: 8px;
    border-radius: 12px;
    overflow: hidden;
}

.menu-link {
    display: flex;
    align-items: center;
    padding: 16px 20px;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    border-left: 4px solid transparent;
}

.menu-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: var(--secondary);
    padding-left: 25px;
}

.menu-link.active {
    background: rgba(255, 215, 0, 0.15);
    color: var(--secondary);
    border-left-color: var(--secondary);
    font-weight: 600;
}

.menu-icon {
    font-size: 22px;
    margin-right: 15px;
    width: 30px;
    text-align: center;
}

.menu-text {
    flex: 1;
    font-size: 16px;
}

.menu-arrow {
    font-size: 12px;
    transition: var(--transition);
}

.menu-arrow.rotate {
    transform: rotate(90deg);
}

/* Submenu */
.submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease-in-out;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 0 0 12px 12px;
}

.submenu.open {
    max-height: 300px;
}

.submenu-item {
    padding: 5px 15px 5px 70px;
}

.submenu-link {
    display: block;
    padding: 12px 15px;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    border-radius: 8px;
    transition: var(--transition);
    position: relative;
    font-size: 14px;
}

.submenu-link:hover {
    background: rgba(255, 215, 0, 0.1);
    color: var(--secondary);
    padding-left: 20px;
}

.submenu-link::before {
    content: "•";
    position: absolute;
    left: 0;
    color: var(--secondary);
    font-size: 18px;
}

/* ===== MAIN CONTENT ===== */
.main {
    flex: 1;
    padding: 30px;
    margin-left: 280px;
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

.notification {
    position: relative;
    cursor: pointer;
    font-size: 22px;
    color: var(--primary);
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--danger);
    color: white;
    font-size: 12px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
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

/* Challenge Header */
.challenge-header {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: var(--card-shadow);
    text-align: center;
}

.challenge-code {
    font-size: 24px;
    color: var(--primary);
    font-weight: bold;
    margin-bottom: 10px;
    letter-spacing: 1px;
}

.challenge-status {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 20px;
}

.status-open {
    background: rgba(76, 201, 240, 0.1);
    color: #4CC9F0;
    border: 1px solid #4CC9F0;
}

.status-accepted {
    background: rgba(46, 125, 50, 0.1);
    color: var(--success);
    border: 1px solid var(--success);
}

.status-rejected {
    background: rgba(211, 47, 47, 0.1);
    color: var(--danger);
    border: 1px solid var(--danger);
}

.status-expired {
    background: rgba(108, 117, 125, 0.1);
    color: var(--gray);
    border: 1px solid var(--gray);
}

.status-completed {
    background: rgba(249, 168, 38, 0.1);
    color: var(--warning);
    border: 1px solid var(--warning);
}

/* Teams VS Display */
.teams-vs {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 50px;
    margin: 30px 0;
    flex-wrap: wrap;
}

.team-card {
    text-align: center;
    min-width: 200px;
}

.team-logo-large {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid white;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    margin-bottom: 15px;
}

.team-name {
    font-size: 22px;
    color: var(--dark);
    margin-bottom: 5px;
    font-weight: 600;
}

.team-coach {
    color: #666;
    font-size: 14px;
    margin-bottom: 5px;
}

.team-sport {
    background: var(--primary);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    display: inline-block;
}

.vs-center {
    text-align: center;
}

.vs-text {
    font-size: 32px;
    font-weight: bold;
    color: var(--secondary);
    background: var(--primary);
    width: 70px;
    height: 70px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    margin: 0 auto;
}

.score-display {
    margin-top: 20px;
}

.score-text {
    font-size: 48px;
    font-weight: bold;
    color: var(--primary);
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
}

/* Info Card */
.info-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: var(--card-shadow);
}

.info-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.info-title {
    font-size: 22px;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    margin-bottom: 15px;
}

.info-label {
    font-weight: 600;
    color: #666;
    font-size: 14px;
    margin-bottom: 5px;
    display: block;
}

.info-value {
    font-size: 16px;
    color: #333;
}

.info-value.badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 14px;
}

/* Time Info */
.time-info {
    display: flex;
    gap: 30px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.time-item {
    text-align: center;
    padding: 15px;
    border-radius: 12px;
    background: #f8f9fa;
    flex: 1;
    min-width: 200px;
}

.time-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.time-value {
    font-size: 18px;
    color: var(--primary);
    font-weight: 600;
}

.time-remaining {
    color: var(--danger);
    font-weight: bold;
}

/* Notes Section */
.notes-section {
    background: #fff9e6;
    border-left: 4px solid var(--warning);
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.notes-title {
    font-weight: 600;
    color: #856404;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Responsive */
@media (max-width: 1200px) {
    .main {
        margin-left: 0;
        padding: 20px;
    }
    
    .sidebar {
        transform: translateX(-100%);
        width: 300px;
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .menu-toggle {
        display: block;
    }
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .search-bar {
        width: 100%;
    }
    
    .action-buttons {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .teams-vs {
        flex-direction: column;
        gap: 30px;
    }
    
    .info-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
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

/* Menu Toggle Button */
.menu-toggle {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 101;
    background: var(--primary);
    color: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 22px;
    cursor: pointer;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    transition: var(--transition);
}

.menu-toggle:hover {
    background: var(--secondary);
    color: var(--primary);
    transform: rotate(90deg);
}

/* Mobile Styles */
@media (max-width: 1200px) {
    .menu-toggle {
        display: block;
    }
    
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .main {
        margin-left: 0;
    }
}
</style>
</head>
<body>

<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo"></div>
            </div>
            <div class="academy-info">
                <div class="academy-name"><?php echo $academy_name; ?></div>
                <div class="academy-email"><?php echo $email; ?></div>
            </div>
        </div>

           <div class="menu">
    <?php foreach ($menu_items as $key => $item): ?>
    <div class="menu-item">
        <a href="<?php 
            if ($key === 'dashboard') {
                echo 'dashboard.php';
            } elseif ($key === 'challenge') {
                echo 'challenge.php';
            } elseif ($key === 'match') {
                echo '../match.php';
            } elseif ($key === 'training') {
                echo '../training.php';
            } elseif ($key === 'settings') {
                echo '../settings.php';
            } else {
                echo '#';
            }
        ?>" 
           class="menu-link <?php echo $key === 'challenge' ? 'active' : ''; ?>" 
           data-menu="<?php echo $key; ?>">
                    <span class="menu-icon"><?php echo $item['icon']; ?></span>
                    <span class="menu-text"><?php echo $item['name']; ?></span>
                    <?php if ($item['submenu']): ?>
                    <span class="menu-arrow">›</span>
                    <?php endif; ?>
                </a>
                
                <?php if ($item['submenu']): ?>
                <div class="submenu <?php echo $key === 'master' ? 'open' : ''; ?>" id="submenu-<?php echo $key; ?>">
                    <?php foreach ($item['items'] as $subitem): ?>
                    <div class="submenu-item">
                        <?php 
                        $subitem_url = $subitem . '.php';
                        ?>
                        <a href="<?php echo $subitem_url; ?>" 
                           class="submenu-link <?php echo $subitem === 'challenge' ? 'active' : ''; ?>">
                           <?php echo ucfirst(str_replace('_', ' ', $subitem)); ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="greeting">
                <h1>Challenge Details 🏆</h1>
                <p>Detail informasi challenge</p>
            </div>
            
            <div class="user-actions">
                <div class="notification">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">0</span>
                </div>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-trophy"></i>
                <span>Detail Challenge</span>
            </div>
            <div class="action-buttons">
                <?php if ($challenge_data['status'] == 'open'): ?>
                <a href="challenge_edit.php?id=<?php echo $challenge_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Challenge
                </a>
                <?php endif; ?>
                <?php if ($challenge_data['status'] == 'accepted' && empty($challenge_data['challenger_score'])): ?>
                <a href="challenge_result.php?id=<?php echo $challenge_id; ?>" class="btn btn-success">
                    <i class="fas fa-futbol"></i>
                    Input Hasil
                </a>
                <?php endif; ?>
                <a href="challenge.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
                </a>
            </div>
        </div>

        <!-- CHALLENGE HEADER -->
        <div class="challenge-header">
            <div class="challenge-code"><?php echo htmlspecialchars($challenge_data['challenge_code']); ?></div>
            <div class="challenge-status status-<?php echo strtolower($challenge_data['status']); ?>">
                <?php echo htmlspecialchars($challenge_data['status']); ?>
            </div>
            
            <!-- TEAMS VS DISPLAY -->
            <div class="teams-vs">
                <div class="team-card">
                    <?php if (!empty($challenge_data['challenger_logo'])): ?>
                        <img src="../images/teams/<?php echo htmlspecialchars($challenge_data['challenger_logo']); ?>" 
                             alt="<?php echo htmlspecialchars($challenge_data['challenger_name']); ?>" 
                             class="team-logo-large">
                    <?php else: ?>
                        <div class="team-logo-large" style="background: linear-gradient(135deg, #f0f0f0, #e0e0e0); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-shield-alt" style="color: #999; font-size: 48px;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="team-name"><?php echo htmlspecialchars($challenge_data['challenger_name']); ?></div>
                    <div class="team-coach">Coach: <?php echo htmlspecialchars($challenge_data['challenger_coach']); ?></div>
                    <div class="team-sport"><?php echo htmlspecialchars($challenge_data['challenger_sport']); ?></div>
                </div>
                
                <div class="vs-center">
                    <div class="vs-text">VS</div>
                    <?php if (!empty($challenge_data['challenger_score']) && !empty($challenge_data['opponent_score'])): ?>
                        <div class="score-display">
                            <div class="score-text">
                                <?php echo $challenge_data['challenger_score']; ?> - <?php echo $challenge_data['opponent_score']; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="team-card">
                    <?php if (!empty($challenge_data['opponent_logo'])): ?>
                        <img src="../images/teams/<?php echo htmlspecialchars($challenge_data['opponent_logo']); ?>" 
                             alt="<?php echo htmlspecialchars($challenge_data['opponent_name']); ?>" 
                             class="team-logo-large">
                    <?php else: ?>
                        <div class="team-logo-large" style="background: linear-gradient(135deg, #f0f0f0, #e0e0e0); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-shield-alt" style="color: #999; font-size: 48px;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="team-name"><?php echo htmlspecialchars($challenge_data['opponent_name']); ?></div>
                    <div class="team-coach">Coach: <?php echo htmlspecialchars($challenge_data['opponent_coach']); ?></div>
                    <div class="team-sport"><?php echo htmlspecialchars($challenge_data['sport_type']); ?></div>
                </div>
            </div>
        </div>

        <!-- CHALLENGE INFORMATION -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Challenge
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Kode Challenge</span>
                    <div class="info-value"><?php echo htmlspecialchars($challenge_data['challenge_code']); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <div class="info-value">
                        <span class="challenge-status status-<?php echo strtolower($challenge_data['status']); ?>" style="padding: 4px 12px;">
                            <?php echo htmlspecialchars($challenge_data['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Venue/Lokasi</span>
                    <div class="info-value">
                        <strong><?php echo htmlspecialchars($challenge_data['venue_name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($challenge_data['venue_location']); ?></small>
                        <?php if (!empty($challenge_data['venue_capacity'])): ?>
                            <br><small>Kapasitas: <?php echo $challenge_data['venue_capacity']; ?> orang</small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Cabor</span>
                    <div class="info-value">
                        <span style="padding: 4px 12px; background: var(--primary); color: white; border-radius: 12px; font-size: 14px;">
                            <?php echo htmlspecialchars($challenge_data['sport_type']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Time Information -->
            <div class="time-info">
                <div class="time-item">
                    <div class="time-label">Tanggal & Waktu Challenge</div>
                    <div class="time-value"><?php echo date('d F Y, H:i', strtotime($challenge_data['challenge_date'])); ?></div>
                </div>
                
                <div class="time-item">
                    <div class="time-label">Challenge Expired</div>
                    <div class="time-value"><?php echo date('d F Y, H:i', strtotime($challenge_data['expiry_date'])); ?></div>
                </div>
                
                <div class="time-item">
                    <div class="time-label">Status Match</div>
                    <div class="time-value">
                        <?php if (!empty($challenge_data['match_status'])): ?>
                            <span style="color: var(--warning);"><?php echo htmlspecialchars($challenge_data['match_status']); ?></span>
                        <?php else: ?>
                            <span style="color: #999;">Belum Mulai</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($challenge_data['notes'])): ?>
            <div class="notes-section">
                <div class="notes-title">
                    <i class="fas fa-sticky-note"></i>
                    Catatan
                </div>
                <div style="color: #666; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($challenge_data['notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- MATCH DETAILS -->
        <?php if (!empty($challenge_data['challenger_score']) && !empty($challenge_data['opponent_score'])): ?>
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-futbol"></i>
                    Hasil Pertandingan
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Skor Akhir</span>
                    <div class="info-value">
                        <div style="font-size: 32px; font-weight: bold; color: var(--primary); text-align: center;">
                            <?php echo $challenge_data['challenger_score']; ?> - <?php echo $challenge_data['opponent_score']; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($challenge_data['winner_team_id'])): ?>
                <div class="info-item">
                    <span class="info-label">Pemenang</span>
                    <div class="info-value">
                        <span style="padding: 3px 16px; background: var(--secondary); color: var(--primary); border-radius: 12px; font-weight: bold;">
                            <?php 
                            $winner_name = ($challenge_data['winner_team_id'] == $challenge_data['challenger_id']) 
                                ? $challenge_data['challenger_name'] 
                                : $challenge_data['opponent_name'];
                            echo htmlspecialchars($winner_name);
                            ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($challenge_data['match_duration'])): ?>
                <div class="info-item">
                    <span class="info-label">Durasi Pertandingan</span>
                    <div class="info-value"><?php echo htmlspecialchars($challenge_data['match_duration']); ?> menit</div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($challenge_data['match_official'])): ?>
                <div class="info-item">
                    <span class="info-label">Wasit</span>
                    <div class="info-value"><?php echo htmlspecialchars($challenge_data['match_official']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($challenge_data['match_notes'])): ?>
                <div class="info-item" style="grid-column: span 2;">
                    <span class="info-label">Catatan Pertandingan</span>
                    <div class="info-value" style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <?php echo nl2br(htmlspecialchars($challenge_data['match_notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($challenge_data['result_entered_at'])): ?>
            <div style="text-align: right; margin-top: 20px; font-size: 14px; color: #666;">
                Hasil diinput pada: <?php echo date('d F Y, H:i', strtotime($challenge_data['result_entered_at'])); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- TIMELINE -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-history"></i>
                    Timeline
                </div>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 30px; height: 30px; border-radius: 50%; background: var(--success); display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600;">Challenge Dibuat</div>
                        <div style="color: #666; font-size: 14px;">
                            <?php echo date('d F Y, H:i', strtotime($challenge_data['created_at'])); ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($challenge_data['updated_at']) && $challenge_data['updated_at'] != $challenge_data['created_at']): ?>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 30px; height: 30px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600;">Challenge Diupdate</div>
                        <div style="color: #666; font-size: 14px;">
                            <?php echo date('d F Y, H:i', strtotime($challenge_data['updated_at'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($challenge_data['result_entered_at'])): ?>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 30px; height: 30px; border-radius: 50%; background: var(--warning); display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-futbol"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600;">Hasil Diinput</div>
                        <div style="color: #666; font-size: 14px;">
                            <?php echo date('d F Y, H:i', strtotime($challenge_data['result_entered_at'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar untuk mobile
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    menuToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        this.innerHTML = sidebar.classList.contains('active') 
            ? '<i class="fas fa-times"></i>' 
            : '<i class="fas fa-bars"></i>';
    });
    
    // Auto close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1200) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        }
    });
    
    // Highlight active menu
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.menu-link, .submenu-link').forEach(link => {
        if (link.getAttribute('href') === currentPage || 
            link.getAttribute('href') === 'challenge.php') {
            link.classList.add('active');
            
            // Open parent submenu if exists
            const parentMenu = link.closest('.submenu');
            if (parentMenu) {
                parentMenu.classList.add('open');
                const arrow = parentMenu.previousElementSibling.querySelector('.menu-arrow');
                if (arrow) arrow.classList.add('rotate');
            }
        }
    });
});
</script>
</body>
</html>