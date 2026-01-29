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
    header("Location: ../login.php");
    exit;
}

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';

// Get statistics
$stats = [
    'total_players' => 0,
    'total_teams' => 0,
    'total_events' => 0,
    'active_teams' => 0
];

try {
    // Count players
    $stmt = $conn->query("SELECT COUNT(*) FROM players");
    $stats['total_players'] = $stmt->fetchColumn();
    
    // Count teams
    $stmt = $conn->query("SELECT COUNT(*) FROM teams");
    $stats['total_teams'] = $stmt->fetchColumn();
    
    // Count active teams
    $stmt = $conn->query("SELECT COUNT(*) FROM teams WHERE is_active = 1");
    $stats['active_teams'] = $stmt->fetchColumn();
    
    // Count events (assuming events table exists)
    $stmt = $conn->query("SELECT COUNT(*) FROM events");
    $stats['total_events'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Events table might not exist yet
    $stats['total_events'] = 0;
}

$academy_name = "Hi, Welcome...";
$email = "";

// Data menu dropdown (sama seperti dashboard)
$menu_items = [
    'dashboard' => [
        'icon' => '🏠',
        'name' => 'Dashboard',
        'submenu' => false
    ],
    'master' => [
        'icon' => '📊',
        'name' => 'Master Data',
        'submenu' => true,
        'items' => ['player', 'team', 'team_staff']
    ],
    'event' => [
        'icon' => '📅',
        'name' => 'Event',
        'submenu' => true,
        'items' => ['event', 'player_liga', 'staff_liga']
    ],
    'match' => [
        'icon' => '⚽',
        'name' => 'Match',
        'submenu' => false
    ],
    'challenge' => [
        'icon' => '🏆',
        'name' => 'Challenge',
        'submenu' => false,
         'url' => 'challenge.php'
    ],
    'training' => [
        'icon' => '🎯',
        'name' => 'Training',
        'submenu' => false
    ],
    'settings' => [
        'icon' => '⚙️',
        'name' => 'Settings',
        'submenu' => false
    ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - FutScore</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
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

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 6px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    box-shadow: 0 10px 20px rgba(10, 36, 99, 0.2);
}

.stat-content h3 {
    font-size: 24px;
    color: var(--dark);
    margin-bottom: 5px;
    font-weight: 700;
}

.stat-content p {
    color: var(--gray);
    font-size: 14px;
    font-weight: 500;
}

/* Charts Section */
.charts-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
    margin-bottom: 40px;
}

.chart-card {
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-title {
    font-size: 18px;
    color: var(--primary);
    font-weight: 600;
}

.chart-actions {
    display: flex;
    gap: 10px;
}

.chart-btn {
    padding: 8px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    background: white;
    color: var(--dark);
    cursor: pointer;
    transition: var(--transition);
    font-size: 12px;
    font-weight: 600;
}

.chart-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: #f8f9ff;
}

/* Recent Activity */
.activity-card {
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
}

.activity-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.activity-title {
    font-size: 18px;
    color: var(--primary);
    font-weight: 600;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid #f0f0f0;
    transition: var(--transition);
}

.activity-item:hover {
    background: #f8f9ff;
    border-radius: 8px;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--accent));
}

.activity-content {
    flex: 1;
}

.activity-content h4 {
    font-size: 16px;
    color: var(--dark);
    margin-bottom: 4px;
}

.activity-content p {
    font-size: 13px;
    color: var(--gray);
}

.activity-time {
    font-size: 12px;
    color: var(--gray);
    font-weight: 500;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.action-card {
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    text-align: center;
    transition: var(--transition);
    border: 2px solid transparent;
}

.action-card:hover {
    transform: translateY(-5px);
    border-color: var(--secondary);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.action-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    margin: 0 auto 15px;
    box-shadow: 0 10px 20px rgba(10, 36, 99, 0.2);
}

.action-title {
    font-size: 18px;
    color: var(--dark);
    margin-bottom: 10px;
    font-weight: 600;
}

.action-desc {
    font-size: 14px;
    color: var(--gray);
    line-height: 1.5;
}

.action-link {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 20px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 600;
    transition: var(--transition);
}

.action-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(10, 36, 99, 0.3);
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .charts-section {
        grid-template-columns: 1fr;
    }
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .quick-actions {
        grid-template-columns: 1fr;
    }
    .main {
        margin-left: 0;
    }
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.active {
        transform: translateX(0);
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

@media (max-width: 1200px) {
    .menu-toggle {
        display: block;
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
           class="menu-link <?php echo $key === 'dashboard' ? 'active' : ''; ?>" 
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
                        $subitem_url = '';
                        if ($subitem === 'player') {
                            $subitem_url = 'player.php';
                        } elseif ($subitem === 'team') {
                            $subitem_url = 'team.php';
                        } else {
                            $subitem_url = $subitem . '.php';
                        }
                        ?>
                        <a href="<?php echo $subitem_url; ?>" class="submenu-link">
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
                <h1>Selamat Datang, <?php echo htmlspecialchars($admin_name); ?>! 👋</h1>
                <p>Dashboard admin FutScore - Sistem manajemen pertandingan futsal</p>
            </div>
            
            <div class="user-actions">
                <div class="notification">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">0</span>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_players']); ?></h3>
                    <p>Total Pemain</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-futbol"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_teams']); ?></h3>
                    <p>Total Team</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_events']); ?></h3>
                    <p>Total Event</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['active_teams']); ?></h3>
                    <p>Team Aktif</p>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="quick-actions">
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="action-title">Tambah Pemain</div>
                <div class="action-desc">Tambahkan pemain baru ke sistem dengan data lengkap dan dokumen</div>
                <a href="player/add.php" class="action-link">
                    <i class="fas fa-plus"></i> Tambah Pemain
                </a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="action-title">Tambah Team</div>
                <div class="action-desc">Buat team baru dengan informasi lengkap dan logo team</div>
                <a href="team/add.php" class="action-link">
                    <i class="fas fa-plus"></i> Tambah Team
                </a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="action-title">Kelola Event</div>
                <div class="action-desc">Atur jadwal pertandingan dan turnamen futsal</div>
                <a href="#" class="action-link">
                    <i class="fas fa-calendar"></i> Kelola Event
                </a>
            </div>
        </div>

        <!-- CHARTS SECTION -->
        <div class="charts-section">
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Statistik Pemain</div>
                    <div class="chart-actions">
                        <button class="chart-btn">Bulanan</button>
                        <button class="chart-btn">Tahunan</button>
                    </div>
                </div>
                <div style="height: 300px; display: flex; align-items: center; justify-content: center; color: var(--gray);">
                    <i class="fas fa-chart-bar" style="font-size: 48px; opacity: 0.3;"></i>
                    <div style="margin-left: 20px;">
                        <div style="font-size: 18px; font-weight: 600;">Grafik Statistik</div>
                        <div style="font-size: 14px;">Data visualisasi akan ditampilkan di sini</div>
                    </div>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">Performa Team</div>
                    <div class="chart-actions">
                        <button class="chart-btn">Lihat Detail</button>
                    </div>
                </div>
                <div style="height: 300px; display: flex; align-items: center; justify-content: center; color: var(--gray);">
                    <i class="fas fa-chart-pie" style="font-size: 48px; opacity: 0.3;"></i>
                    <div style="margin-left: 20px;">
                        <div style="font-size: 18px; font-weight: 600;">Grafik Pie</div>
                        <div style="font-size: 14px;">Distribusi data team</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RECENT ACTIVITY -->
        <div class="activity-card">
            <div class="activity-header">
                <div class="activity-title">Aktivitas Terbaru</div>
                <a href="#" style="color: var(--primary); text-decoration: none; font-size: 14px; font-weight: 600;">Lihat Semua</a>
            </div>
            
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="activity-content">
                    <h4>Player Registration</h4>
                    <p>5 pemain baru telah mendaftar hari ini</p>
                </div>
                <div class="activity-time">2 menit lalu</div>
            </div>

            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-futbol"></i>
                </div>
                <div class="activity-content">
                    <h4>Team Created</h4>
                    <p>Team BUFC telah ditambahkan ke sistem</p>
                </div>
                <div class="activity-time">15 menit lalu</div>
            </div>

            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="activity-content">
                    <h4>Event Scheduled</h4>
                    <p>Jadwal pertandingan pekan depan telah diatur</p>
                </div>
                <div class="activity-time">1 jam lalu</div>
            </div>

            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="activity-content">
                    <h4>Document Updated</h4>
                    <p>Dokumen pemain telah diperbarui</p>
                </div>
                <div class="activity-time">3 jam lalu</div>
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
        if (link.getAttribute('href') === currentPage) {
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

    // Menu toggle functionality
    document.querySelectorAll('.menu-link').forEach(link => {
        if (link.querySelector('.menu-arrow')) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const submenu = this.nextElementSibling;
                const arrow = this.querySelector('.menu-arrow');
                
                if (submenu) {
                    submenu.classList.toggle('open');
                    arrow.classList.toggle('rotate');
                }
            });
        }
    });

    // Chart buttons interaction
    document.querySelectorAll('.chart-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.chart-btn').forEach(b => b.style.borderColor = '#e0e0e0');
            this.style.borderColor = getComputedStyle(document.documentElement).getPropertyValue('--primary');
            this.style.color = getComputedStyle(document.documentElement).getPropertyValue('--primary');
            this.style.background = '#f8f9ff';
        });
    });

    // Action card hover effects
    document.querySelectorAll('.action-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 15px 35px rgba(0, 0, 0, 0.12)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'var(--card-shadow)';
        });
    });

    // Responsive adjustments
    function adjustLayout() {
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            document.querySelector('.main').style.marginLeft = '0';
        }
    }
    
    adjustLayout();
    window.addEventListener('resize', adjustLayout);
});
</script>
</body>
</html>