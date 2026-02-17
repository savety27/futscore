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

// --- DATA MENU DROPDOWN ---
$menu_items = [
    'dashboard' => [
        'icon' => '🏠',
        'name' => 'Dashboard',
        'url' => 'dashboard.php',
        'submenu' => false
    ],
    'master' => [
        'icon' => '📊',
        'name' => 'Master Data',
        'submenu' => true,
        'items' => [
            'player' => 'player.php',
            'team' => 'team.php',
            'team_staff' => 'team_staff.php',
            'transfer' => 'transfer.php',
        ]
    ],
    'event' => [
        'icon' => '🏆',
        'name' => 'Event',
        'url' => 'challenge.php',  // Langsung ke challenge.php
        'submenu' => false         // Tidak ada submenu
    ],
    'Venue' => [
        'icon' => '📍',
        'name' => 'Venue',
        'url' => 'venue.php',
        'submenu' => false
    ],
    'Pelatih' => [
        'icon' => '👨‍🏫',
        'name' => 'Pelatih',
        'url' => 'pelatih.php',
        'submenu' => false
    ],
    'Berita' => [
        'icon' => '📰',
        'name' => 'Berita',
        'url' => 'berita.php',
        'submenu' => false
    ]
];

// Mendapatkan nama file saat ini untuk penanda menu 'Active'
$current_page = basename($_SERVER['PHP_SELF']);

// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';

$academy_name = "Hi, Welcome...";
$email = $admin_email;

// Get challenge ID
$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($challenge_id <= 0) {
    header("Location: challenge.php");
    exit;
}

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
<title>View Challenge - MGP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    --sidebar-bg: rgba(15, 39, 68, 0.95);
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

/* ===== SIDEBAR ===== */
.sidebar {
    width: 280px;
    background: var(--sidebar-bg);
    backdrop-filter: blur(15px) saturate(160%);
    -webkit-backdrop-filter: blur(15px) saturate(160%);
    color: white;
    padding: 0;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
    box-shadow: 10px 0 30px rgba(0, 0, 0, 0.15);
    transition: var(--transition);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar-header {
    padding: 30px 25px;
    text-align: center;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 2px solid var(--secondary);
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
    transition: var(--transition);
}

.logo:hover {
    transform: rotate(15deg) scale(1.05);
    box-shadow: 0 0 35px rgba(255, 215, 0, 0.5);
}

.logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
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
    padding: 14px 20px;
    color: rgba(255, 255, 255, 0.75);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    border-radius: 12px;
    margin: 4px 0;
}

.menu-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    transform: translateX(5px);
}

.menu-link.active {
    background: linear-gradient(90deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.02) 100%);
    color: var(--secondary);
    font-weight: 700;
    border-right: 4px solid var(--secondary);
    border-radius: 12px 0 0 12px;
}

.menu-icon {
    font-size: 18px;
    margin-right: 15px;
    width: 24px;
    text-align: center;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
}

.menu-text {
    flex: 1;
    font-size: 15px;
    letter-spacing: 0.3px;
}

.menu-arrow {
    font-size: 12px;
    opacity: 0.6;
    transition: var(--transition);
}

.menu-arrow.rotate {
    transform: rotate(90deg);
    opacity: 1;
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

.submenu-link.active {
    background: rgba(255, 215, 0, 0.1);
    color: var(--secondary);
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


/* =========================================
   MOBILE RESPONSIVE DESIGN
   ========================================= */

/* Default: Hide mobile-only elements on desktop */
.menu-toggle {
    display: none;
}

.menu-overlay {
    display: none;
}

/* ===== TABLET (max-width: 1024px) ===== */
@media screen and (max-width: 1024px) {
    .sidebar {
        width: 240px;
    }

    .main {
        margin-left: 240px;
        width: calc(100% - 240px);
        max-width: calc(100vw - 240px);
    }
}

/* ===== MOBILE LANDSCAPE (max-width: 768px) ===== */
@media screen and (max-width: 768px) {
    
    /* Show Mobile Menu Toggle Button */
    .menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        position: fixed;
        bottom: 25px;
        right: 25px;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--secondary), #FFEC8B);
        color: var(--primary);
        border: none;
        border-radius: 50%;
        box-shadow: 0 5px 20px rgba(255, 215, 0, 0.4);
        z-index: 1001;
        font-size: 24px;
        cursor: pointer;
        transition: var(--transition);
    }

    .menu-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 8px 25px rgba(255, 215, 0, 0.6);
    }

    .menu-toggle:active {
        transform: scale(0.95);
    }

    /* Sidebar: Hidden by default on mobile */
    .sidebar {
        transform: translateX(-100%);
        box-shadow: none;
        width: 280px;
    }

    /* Sidebar: Show when active */
    .sidebar.active {
        transform: translateX(0);
        box-shadow: 10px 0 40px rgba(0, 0, 0, 0.3);
    }

    /* Overlay: Show when menu is open */
    .menu-overlay {
        display: block;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 99;
        opacity: 0;
        visibility: hidden;
        transition: var(--transition);
        backdrop-filter: blur(2px);
    }

    body.menu-open .menu-overlay {
        opacity: 1;
        visibility: visible;
    }

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
    
    /* Layout adaptations */
    .teams-vs {
        flex-direction: column;
        gap: 30px;
    }
    
    .info-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .time-info {
        flex-direction: column;
        gap: 15px;
    }
    
    .time-item {
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

    /* Compact sidebar */
    .sidebar {
        width: 260px;
    }

    .sidebar-header {
        padding: 20px 15px;
    }

    .logo {
        width: 80px;
        height: 80px;
    }

    .logo::before {
        font-size: 36px;
    }

    .academy-name {
        font-size: 18px;
    }
    
    /* Compact menu */
    .menu {
        padding: 20px 10px;
    }

    .menu-link {
        padding: 14px 15px;
        font-size: 15px;
    }

    .menu-icon {
        font-size: 20px;
        width: 28px;
    }

    /* Smaller mobile toggle button */
    .menu-toggle {
        width: 55px;
        height: 55px;
        font-size: 22px;
        bottom: 20px;
        right: 20px;
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

    .team-logo-large {
        width: 100px;
        height: 100px;
    }
    
    .team-name {
        font-size: 18px;
    }
    
    .score-text {
        font-size: 36px;
    }
}

@media (max-width: 1400px) {
    .page-header {
        justify-content: center;
        text-align: center;
    }
    
    .page-title {
        width: 100%;
        justify-content: center;
        margin-bottom: 10px;
    }
}
</style>
</head>
<body>


<!-- Mobile Menu Components (hidden by default via CSS) -->
<div class="menu-overlay"></div>
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo">
                    <img src="../images/alvetrix.png" alt="Logo">
                </div>
            </div>
            <div class="academy-info">
                <div class="academy-name"><?php echo htmlspecialchars($academy_name ?? ''); ?></div>
                <div class="academy-email"><?php echo htmlspecialchars($email ?? ''); ?></div>
            </div>
        </div>

        <div class="menu">
            <?php foreach ($menu_items as $key => $item): ?>
            <?php 
                // Cek apakah menu ini aktif berdasarkan URL saat ini
                $isActive = false;
                $isSubmenuOpen = false;
                
                if ($item['submenu']) {
                    // Cek jika salah satu sub-item ada yang aktif
                    foreach($item['items'] as $subKey => $subUrl) {
                        if($current_page === $subUrl) {
                            $isActive = true;
                            $isSubmenuOpen = true;
                            break;
                        }
                    }
                } else {
                    // Untuk menu Event, aktif jika di challenge_view.php, challenge.php, atau challenge_create.php
                    if ($key === 'event') {
                        $isActive = in_array($current_page, ['challenge_view.php', 'challenge.php', 'challenge_create.php', 'challenge_edit.php', 'challenge_result.php']);
                    } else {
                        $isActive = ($current_page === $item['url']);
                    }
                }
            ?>
            <div class="menu-item">
                <a href="<?php echo $item['submenu'] ? '#' : $item['url']; ?>" 
                   class="menu-link <?php echo $isActive ? 'active' : ''; ?>" 
                   data-menu="<?php echo $key; ?>">
                    <span class="menu-icon"><?php echo $item['icon']; ?></span>
                    <span class="menu-text"><?php echo $item['name']; ?></span>
                    <?php if ($item['submenu']): ?>
                    <span class="menu-arrow <?php echo $isSubmenuOpen ? 'rotate' : ''; ?>">›</span>
                    <?php endif; ?>
                </a>
                
                <?php if ($item['submenu']): ?>
                <div class="submenu <?php echo $isSubmenuOpen ? 'open' : ''; ?>" id="submenu-<?php echo $key; ?>">
                    <?php foreach ($item['items'] as $subKey => $subUrl): ?>
                    <div class="submenu-item">
                        <a href="<?php echo $subUrl; ?>" 
                           class="submenu-link <?php echo ($current_page === $subUrl) ? 'active' : ''; ?>">
                           <?php echo ucwords(str_replace('_', ' ', $subKey)); ?>
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
                <a href="logout.php" class="logout-btn">
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
                <?php echo htmlspecialchars($challenge_data['status'] ?? ''); ?>
            </div>
            
            <!-- TEAMS VS DISPLAY -->
            <div class="teams-vs">
                <div class="team-card">
                    <?php if (!empty($challenge_data['challenger_logo'])): ?>
                        <img src="../images/teams/<?php echo htmlspecialchars($challenge_data['challenger_logo'] ?? ''); ?>" 
                             alt="<?php echo htmlspecialchars($challenge_data['challenger_name'] ?? ''); ?>" 
                             class="team-logo-large">
                    <?php else: ?>
                        <div class="team-logo-large" style="background: linear-gradient(135deg, #f0f0f0, #e0e0e0); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-shield-alt" style="color: #999; font-size: 48px;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="team-name"><?php echo htmlspecialchars($challenge_data['challenger_name'] ?? ''); ?></div>
                    <div class="team-coach">Coach: <?php echo htmlspecialchars($challenge_data['challenger_coach'] ?? ''); ?></div>
                    <div class="team-sport"><?php echo htmlspecialchars($challenge_data['challenger_sport'] ?? ''); ?></div>
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
                        <img src="../images/teams/<?php echo htmlspecialchars($challenge_data['opponent_logo'] ?? ''); ?>" 
                             alt="<?php echo htmlspecialchars($challenge_data['opponent_name'] ?? ''); ?>" 
                             class="team-logo-large">
                    <?php else: ?>
                        <div class="team-logo-large" style="background: linear-gradient(135deg, #f0f0f0, #e0e0e0); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-shield-alt" style="color: #999; font-size: 48px;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="team-name"><?php echo htmlspecialchars($challenge_data['opponent_name'] ?? ''); ?></div>
                    <div class="team-coach">Coach: <?php echo htmlspecialchars($challenge_data['opponent_coach'] ?? ''); ?></div>
                    <div class="team-sport"><?php echo htmlspecialchars($challenge_data['sport_type'] ?? ''); ?></div>
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
                    <div class="info-value"><?php echo htmlspecialchars($challenge_data['challenge_code'] ?? ''); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <div class="info-value">
                        <span class="badge badge-<?php 
                            echo $challenge_data['status'] == 'approved' ? 'success' : 
                                ($challenge_data['status'] == 'pending' ? 'warning' : 'danger'); 
                        ?>">
                            <?php echo htmlspecialchars($challenge_data['status'] ?? ''); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Venue/Lokasi</span>
                    <div class="info-value">
                        <strong><?php echo htmlspecialchars($challenge_data['venue_name'] ?? ''); ?></strong><br>
                        <small><?php echo htmlspecialchars($challenge_data['venue_location'] ?? ''); ?></small>
                        <?php if (!empty($challenge_data['venue_capacity'])): ?>
                            <br><small>Kapasitas: <?php echo $challenge_data['venue_capacity']; ?> orang</small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Event</span>
                    <div class="info-value">
                        <span style="padding: 4px 12px; background: var(--primary); color: white; border-radius: 12px; font-size: 14px;">
                            <?php echo htmlspecialchars($challenge_data['sport_type'] ?? ''); ?>
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
                            <span style="color: var(--warning);"><?php echo htmlspecialchars($challenge_data['match_status'] ?? ''); ?></span>
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
                    <?php echo nl2br(htmlspecialchars($challenge_data['notes'] ?? '')); ?>
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
                            echo htmlspecialchars($winner_name ?? '');
                            ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($challenge_data['match_duration'])): ?>
                <div class="info-item">
                    <span class="info-label">Durasi Pertandingan</span>
                    <div class="info-value"><?php echo htmlspecialchars($challenge_data['match_duration'] ?? ''); ?> menit</div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($challenge_data['match_official'])): ?>
                <div class="info-item">
                    <span class="info-label">Wasit</span>
                    <div class="info-value"><?php echo htmlspecialchars($challenge_data['match_official'] ?? ''); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($challenge_data['match_notes'])): ?>
                <div class="info-item" style="grid-column: span 2;">
                    <span class="info-label">Catatan Pertandingan</span>
                    <div class="info-value" style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <?php echo nl2br(htmlspecialchars($challenge_data['match_notes'] ?? '')); ?>
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
    // Mobile Menu Toggle Functionality
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.menu-overlay');

    if (menuToggle && sidebar && overlay) {
        // Toggle menu when clicking hamburger button
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            document.body.classList.toggle('menu-open');
        });

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
    
    // Menu toggle functionality (untuk Submenu)
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
});
</script>
</body>
</html>