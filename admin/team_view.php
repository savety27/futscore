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

// Menu items sesuai dengan file pertama

// Get team ID
$team_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($team_id <= 0) {
    header("Location: team.php");
    exit;
}

// Fetch team data with player and staff count
try {
    $stmt = $conn->prepare("
        SELECT t.*, 
        (SELECT COUNT(*) FROM players p WHERE p.team_id = t.id AND p.status = 'active') as player_count,
        (SELECT COUNT(*) FROM team_staff ts WHERE ts.team_id = t.id) as staff_count
        FROM teams t 
        WHERE t.id = ?
    ");
    $stmt->execute([$team_id]);
    $team_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$team_data) {
        header("Location: team.php");
        exit;
    }
    
    $events_stmt = $conn->prepare("SELECT event_name FROM team_events WHERE team_id = ? ORDER BY event_name ASC");
    $events_stmt->execute([$team_id]);
    $team_events = $events_stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($team_events) && !empty($team_data['sport_type'])) {
        $team_events = [$team_data['sport_type']];
    }
    $event_display = !empty($team_events) ? implode(', ', $team_events) : '-';

    // Fetch players in this team - FIXED QUERY
    $players_stmt = $conn->prepare("
        SELECT p.*, p.position as position_name
        FROM players p 
        WHERE p.team_id = ? AND p.status = 'active'
        ORDER BY p.name ASC
        LIMIT 10
    ");
    $players_stmt->execute([$team_id]);
    $players = $players_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch staff in this team - FIXED QUERY
    $staff_stmt = $conn->prepare("
        SELECT ts.*, ts.position as position_name
        FROM team_staff ts 
        WHERE ts.team_id = ?
        ORDER BY ts.name ASC
        LIMIT 10
    ");
    $staff_stmt->execute([$team_id]);
    $staff = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);

    $established_display = '-';
    if (!empty($team_data['established_year'])) {
        $timestamp = strtotime($team_data['established_year']);
        $established_display = $timestamp ? date('d M Y', $timestamp) : $team_data['established_year'];
    }
    
} catch (PDOException $e) {
    die("Error fetching team data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Team</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
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

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
}

.stat-icon {
    font-size: 40px;
    margin-bottom: 15px;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 14px;
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

/* Team Logo Large */
.team-logo-large {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid white;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

/* Player and Staff Cards */
.player-card, .staff-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 3px 15px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    gap: 15px;
    transition: var(--transition);
}

.player-card:hover, .staff-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.player-avatar, .staff-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #f0f0f0;
}

.player-info, .staff-info {
    flex: 1;
}

.player-name, .staff-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 5px;
}

.player-position, .staff-position {
    color: #666;
    font-size: 14px;
}

/* Badge Styles */
.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.badge-primary {
    background: rgba(10, 36, 99, 0.1);
    color: var(--primary);
}

.badge-secondary {
    background: rgba(108, 117, 125, 0.1);
    color: var(--gray);
}

.badge-success {
    background: rgba(46, 125, 50, 0.1);
    color: var(--success);
}

.badge-danger {
    background: rgba(211, 47, 47, 0.1);
    color: var(--danger);
}

.badge-warning {
    background: rgba(249, 168, 38, 0.1);
    color: var(--warning);
}

.event-list-view {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.event-item {
    font-size: 0.85rem;
    color: #444;
    line-height: 1.2;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px;
    color: #999;
}

.empty-state i {
    font-size: 60px;
    margin-bottom: 20px;
    opacity: 0.3;
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

    /* Page Header */
    .page-header {
        flex-direction: column;
        gap: 15px;
        align-items: center;
        text-align: center;
    }

    .page-title {
        width: 100%;
        justify-content: center;
    }
    
    .action-buttons {
        flex-wrap: wrap;
        justify-content: center;
        width: 100%;
    }
    
    .action-buttons .btn {
        width: 100%;
        justify-content: center;
    }

    /* Stats Grid & Info Cards */
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .info-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .info-title {
        justify-content: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    /* Team Header Section (Logo + Name) */
    .info-card > div[style*="display: flex"] {
        flex-direction: column;
        text-align: center;
        gap: 20px !important;
    }
    
    .team-logo-large {
        margin: 0 auto;
    }
    
    .info-card > div > div[style*="min-width: 300px"] {
        min-width: unset !important;
        width: 100%;
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
        font-size: 22px;
    }

    .page-title i {
        font-size: 26px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
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
                <h1>Team Profile ⚽</h1>
                <p>Detail informasi team: <?php echo htmlspecialchars($team_data['name'] ?? ''); ?></p>
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
                <i class="fas fa-users"></i>
                <span>Detail Team</span>
            </div>
            <div class="action-buttons">
                <a href="team_edit.php?id=<?php echo $team_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Team
                </a>
                <a href="team.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
                </a>
            </div>
        </div>

        <!-- TEAM STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color: #0A2463;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $team_data['player_count']; ?></div>
                <div class="stat-label">Total Players</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #FFD700;">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-number"><?php echo $team_data['staff_count']; ?></div>
                <div class="stat-label">Total Staff</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #4CC9F0;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-number"><?php echo htmlspecialchars($established_display); ?></div>
                <div class="stat-label">Tanggal Berdiri</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #2E7D32;">
                    <i class="fas fa-running"></i>
                </div>
                <div class="stat-value">
                    <div class="stat-number">
                        <?php if (!empty($team_events)): ?>
                            <div class="event-list-view">
                                <?php foreach ($team_events as $event_name): ?>
                                    <div class="event-item"><?php echo htmlspecialchars($event_name); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">Event</div>
                </div>
            </div>
        </div>

        <!-- TEAM INFORMATION -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Team
                </div>
                <div>
                    <?php if ($team_data['is_active']): ?>
                        <span class="badge badge-success" style="padding: 8px 16px;">AKTIF</span>
                    <?php else: ?>
                        <span class="badge badge-danger" style="padding: 8px 16px;">NON-AKTIF</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="display: flex; gap: 40px; align-items: center; margin-bottom: 30px; flex-wrap: wrap;">
                <?php if (!empty($team_data['logo'])): ?>
                    <img src="../images/teams/<?php echo htmlspecialchars($team_data['logo'] ?? ''); ?>" 
                         alt="<?php echo htmlspecialchars($team_data['name'] ?? ''); ?>"  
                         class="team-logo-large">
                <?php else: ?>
                    <div class="team-logo-large" style="background: linear-gradient(135deg, #f0f0f0, #e0e0e0); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-shield-alt" style="color: #999; font-size: 48px;"></i>
                    </div>
                <?php endif; ?>
                
                <div style="flex: 1; min-width: 300px;">
                    <h2 style="font-size: 28px; color: #333; margin-bottom: 10px;">
                        <?php echo htmlspecialchars($team_data['name'] ?? ''); ?>
                        <?php if (!empty($team_data['alias'])): ?>
                            <span style="color: #666; font-size: 20px;">(<?php echo htmlspecialchars($team_data['alias'] ?? ''); ?>)</span>
                        <?php endif; ?>
                    </h2>
                    <p style="color: #666; margin-bottom: 15px;">
                        <i class="fas fa-user-tie"></i>
                        Manager: <strong><?php echo htmlspecialchars($team_data['coach'] ?? ''); ?></strong>
                    </p>
                    <?php if (!empty($team_data['basecamp'])): ?>
                        <p style="color: #666;">
                            <i class="fas fa-map-marker-alt"></i>
                            Basecamp: <?php echo htmlspecialchars($team_data['basecamp'] ?? ''); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nama Lengkap</span>
                    <div class="info-value"><?php echo htmlspecialchars($team_data['name'] ?? ''); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Alias</span>
                    <div class="info-value">
                        <?php echo !empty($team_data['alias']) ? htmlspecialchars($team_data['alias']) : '-'; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Manager</span>
                    <div class="info-value"><?php echo htmlspecialchars($team_data['coach'] ?? ''); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Tanggal Berdiri</span>
                    <div class="info-value"><?php echo htmlspecialchars($established_display); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Warna Kostum</span>
                    <div class="info-value">
                        <?php echo !empty($team_data['uniform_color']) ? htmlspecialchars($team_data['uniform_color']) : '-'; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Basecamp</span>
                    <div class="info-value">
                        <?php echo !empty($team_data['basecamp']) ? htmlspecialchars($team_data['basecamp']) : '-'; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Event</span>
                    <div class="info-value">
                        <span class="badge" style="background: #FFD700; color: #333; padding: 5px 12px;">
                            <?php if (!empty($team_events)): ?>
                                <div class="event-list-view">
                                    <?php foreach ($team_events as $event_name): ?>
                                        <div class="event-item"><?php echo htmlspecialchars($event_name); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <div class="info-value">
                        <?php if ($team_data['is_active']): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Non-Aktif</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Dibuat Pada</span>
                    <div class="info-value">
                        <?php echo date('d F Y, H:i', strtotime($team_data['created_at'])); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Terakhir Diupdate</span>
                    <div class="info-value">
                        <?php echo date('d F Y, H:i', strtotime($team_data['updated_at'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PLAYERS SECTION -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-users"></i>
                    Players in Team (<?php echo count($players); ?>)
                </div>
                <?php if (!empty($players)): ?>
                <a href="player.php?team=<?php echo $team_id; ?>" class="btn btn-primary btn-sm" style="padding: 8px 16px; font-size: 14px;">
                    <i class="fas fa-external-link-alt"></i>
                    Lihat Semua Players
                </a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($players)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                    <?php foreach ($players as $player): ?>
                        <div class="player-card">
                            <?php if (!empty($player['photo'])): ?>
                                <img src="../images/players/<?php echo htmlspecialchars($player['photo'] ?? ''); ?>" 
                                     alt="<?php echo htmlspecialchars($player['name'] ?? ''); ?>"  
                                     class="player-avatar">
                            <?php else: ?>
                                <div class="player-avatar" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user" style="color: #999; font-size: 24px;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="player-info">
                                <div class="player-name"><?php echo htmlspecialchars($player['name'] ?? ''); ?></div>
                                <div class="player-position">
                                    <?php echo !empty($player['position_name']) ? htmlspecialchars($player['position_name']) : 'No Position'; ?>
                                </div>
                                <div style="font-size: 12px; color: #999;">
                                    Back Number: <?php echo !empty($player['jersey_number']) ? $player['jersey_number'] : '-'; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h4>Tidak ada players dalam team ini</h4>
                    <p>Tambahkan players ke team ini melalui menu Players</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- STAFF SECTION -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-user-tie"></i>
                    Staff in Team (<?php echo count($staff); ?>)
                </div>
                <?php if (!empty($staff)): ?>
                <a href="team_staff.php?team=<?php echo $team_id; ?>" class="btn btn-primary btn-sm" style="padding: 8px 16px; font-size: 14px;">
                    <i class="fas fa-external-link-alt"></i>
                    Lihat Semua Staff
                </a>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($staff)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                    <?php foreach ($staff as $staff_member): ?>
                        <div class="staff-card">
                            <?php if (!empty($staff_member['photo'])): ?>
                                <img src="../<?php echo htmlspecialchars($staff_member['photo'] ?? ''); ?>" 
                                     alt="<?php echo htmlspecialchars($staff_member['name'] ?? ''); ?>"  
                                     class="staff-avatar">
                            <?php else: ?>
                                <div class="staff-avatar" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user-tie" style="color: #999; font-size: 24px;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="staff-info">
                                <div class="staff-name"><?php echo htmlspecialchars($staff_member['name'] ?? ''); ?></div>
                                <div class="staff-position">
                                    <?php echo !empty($staff_member['position_name']) ? htmlspecialchars($staff_member['position_name']) : 'Staff'; ?>
                                </div>
                                <div style="font-size: 12px; color: #999;">
                                    <?php echo !empty($staff_member['phone']) ? $staff_member['phone'] : 'No Phone'; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-tie"></i>
                    <h4>Tidak ada staff dalam team ini</h4>
                    <p>Tambahkan staff ke team ini melalui menu Team Staff</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>
