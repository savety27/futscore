<?php
session_start();
// TAMBAHKIN INI - Deklarasi variabel sebelum digunakan
$photo_displayed = false;
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// Mendapatkan nama file saat ini untuk penanda menu 'Active'
$current_page = basename($_SERVER['PHP_SELF']);

// --- DATA MENU DROPDOWN ---
$menu_items = [
    'dashboard' => [
        'icon' => '🏠',
        'name' => 'Dashboard',
        'url' => '../dashboard.php',
        'submenu' => false
    ],
    'master' => [
        'icon' => '📊',
        'name' => 'Master Data',
        'submenu' => true,
        'items' => [
            'player' => '../player.php',
            'team' => '../team.php',
            'team_staff' => '../team_staff.php',
            'transfer' => '../transfer.php',
        ]
    ],
    'Event' => [
        'icon' => '🏆',
        'name' => 'Event',
        'url' => '../challenge.php',
        'submenu' => false
    ],
    'Venue' => [
        'icon' => '📍',
        'name' => 'Venue',
        'url' => '../venue.php',
        'submenu' => false
    ],
    'Pelatih' => [
        'icon' => '👨‍🏫',
        'name' => 'Pelatih',
        'url' => '../pelatih.php',
        'submenu' => false
    ],
    'Berita' => [
        'icon' => '📰',
        'name' => 'Berita',
        'url' => '../berita.php',
        'submenu' => false
    ]
];

$academy_name = "Hi, Welcome...";
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';
$email = $admin_email;

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: ../player.php");
    exit;
}

$player_id = (int)$_GET['id'];

try {
    // Get player data with team info
    $stmt = $conn->prepare("
        SELECT p.*, t.name as team_name, t.logo as team_logo, 
               t.coach, t.basecamp
        FROM players p 
        LEFT JOIN teams t ON p.team_id = t.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$player) {
        header("Location: ../player.php");
        exit;
    }
    
    // Calculate age
    if (!empty($player['birth_date']) && $player['birth_date'] != '0000-00-00') {
        $birth_date = new DateTime($player['birth_date']);
        $today = new DateTime();
        $age = $today->diff($birth_date);
        $player['age_years'] = $age->y;
        $player['age_months'] = $age->m;
    } else {
        $player['age_years'] = '-';
        $player['age_months'] = '-';
    }
    
    // Format gender untuk display
    function formatGenderView($gender) {
        if (empty($gender)) return '-';
        
        // Handle enum values 'L' and 'P'
        if ($gender == 'L') {
            return 'Laki-laki';
        } elseif ($gender == 'P') {
            return 'Perempuan';
        }
        
        $gender_lower = strtolower($gender);
        
        if (strpos($gender_lower, 'perempuan') !== false || $gender_lower == 'p' || $gender_lower == 'perempuan') {
            return 'Perempuan';
        } elseif (strpos($gender_lower, 'laki') !== false || $gender_lower == 'l' || $gender_lower == 'laki-laki') {
            return 'Laki-laki';
        } else {
            return ucfirst($gender ?? '');
        }
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Player - FutScore</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* CSS styles for sidebar and layout */
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
    --sidebar-bg: linear-gradient(180deg, #0a1628 0%, #0f2744 100%);
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
    padding-top: 20px;
    padding-right: 10px;
    padding-bottom: 10px;
    text-align: center;
    background: transparent;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    margin-bottom: 10px;
}

.logo-container {
    position: relative;
    display: inline-block;
}

.logo {
    max-width: 200px;
    background: transparent;
    margin: 0 auto 12px;
    border: none;
    border-radius: 0;
    box-shadow: none;
    position: relative;
    overflow: visible;
    transition: var(--transition);
}

.logo:hover {
    transform: none;
    box-shadow: none;
}

.logo img {
    width: 100%;
    height: auto;
    max-width: 200px;
    filter: brightness(1.1) drop-shadow(0 0 15px rgba(255, 255, 255, 0.1));
    transition: transform var(--transition), filter var(--transition);
}

.logo img:hover {
    transform: scale(1.05);
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

/* Container */
.container {
    max-width: 1200px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

/* Header */
.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 30px;
    background: linear-gradient(135deg, var(--primary), #1a365d);
    color: white;
}

.back-btn {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
    padding: 10px 20px;
    border-radius: 10px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
}

.back-btn:hover {
    background: var(--secondary);
    color: var(--primary);
    border-color: var(--secondary);
}

.page-title {
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title i {
    color: var(--secondary);
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 10px 20px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    font-size: 14px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #FF9800);
    color: white;
}

.btn-warning:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(249, 168, 38, 0.2);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #1B5E20);
    color: white;
}

.btn-success:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.2);
}

/* Player Profile */
.player-profile {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 30px;
    padding: 30px;
}

.player-photo-section {
    text-align: center;
}

.player-photo {
    width: 250px;
    height: 250px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid white;
    box-shadow: var(--card-shadow);
    margin-bottom: 20px;
}

.default-photo {
    width: 250px;
    height: 250px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--secondary), #FFEC8B);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    border: 5px solid white;
    box-shadow: var(--card-shadow);
}

.default-photo i {
    font-size: 80px;
    color: var(--primary);
}

.player-name {
    font-size: 28px;
    color: var(--dark);
    margin-bottom: 10px;
}

.player-team {
    color: var(--primary);
    font-weight: 600;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.player-number {
    font-size: 24px;
    color: var(--secondary);
    font-weight: 700;
    background: var(--primary);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 20px auto;
}

/* Player Details */
.player-details {
    padding-right: 20px;
}

.details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.detail-group {
    margin-bottom: 15px;
}

.detail-label {
    font-weight: 600;
    color: var(--gray);
    font-size: 13px;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-value {
    font-size: 15px;
    color: var(--dark);
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

/* Skills Section */
.skills-section {
    background: #f8f9fa;
    padding: 30px;
    margin: 30px;
    border-radius: 15px;
}

.section-title {
    font-size: 20px;
    color: var(--primary);
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.skills-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.skill-item {
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.skill-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
}

.skill-bar {
    height: 10px;
    background: #e0e0e0;
    border-radius: 5px;
    overflow: hidden;
    margin-bottom: 5px;
}

.skill-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--accent));
    border-radius: 5px;
    transition: width 1s ease-in-out;
}

.skill-value {
    text-align: right;
    font-weight: 700;
    color: var(--primary);
    font-size: 16px;
}

/* Documents Section */
.documents-section {
    padding: 30px;
}

.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.document-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    border: 2px solid transparent;
}

.document-card:hover {
    transform: translateY(-5px);
    border-color: var(--primary);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

.document-icon {
    font-size: 40px;
    color: var(--primary);
    margin-bottom: 15px;
    opacity: 0.8;
}

.document-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
}

.document-status {
    display: inline-block;
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 15px;
}

.status-available {
    background: rgba(46, 125, 50, 0.1);
    color: var(--success);
}

.status-unavailable {
    background: rgba(158, 158, 158, 0.1);
    color: #9E9E9E;
}

.document-preview {
    width: 100%;
    height: 150px;
    border-radius: 10px;
    overflow: hidden;
    margin-top: 15px;
    border: 2px solid #e0e0e0;
}

.document-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    cursor: pointer;
    transition: var(--transition);
}

.document-preview img:hover {
    transform: scale(1.05);
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
    }
    
    .player-profile {
        grid-template-columns: 250px 1fr;
        gap: 20px;
        padding: 20px;
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

    /* Header */
    .header {
        flex-direction: column;
        gap: 15px;
        align-items: center;
        text-align: center;
        padding: 20px;
    }
    
    .page-title {
        order: -1; /* Title first */
    }
    
    .action-buttons {
        flex-wrap: wrap;
        justify-content: center;
        width: 100%;
    }
    
    .btn {
        flex: 1;
        justify-content: center;
        min-width: 140px;
    }

    /* Player Profile Layout */
    .player-profile {
        grid-template-columns: 1fr;
        padding: 20px;
        gap: 30px;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
    }
    
    .skills-grid {
        grid-template-columns: 1fr;
    }
    
    .documents-grid {
        grid-template-columns: 1fr;
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

    /* Compact sidebar */
    .sidebar {
        width: 260px;
    }

    .sidebar-header {
        padding: 20px 18px 26px;
    }

    .logo,
    .logo img {
        max-width: 120px;
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
    
    .section-title {
        font-size: 18px;
    }
    
    .player-photo, .default-photo {
        width: 180px;
        height: 180px;
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
                    <img src="../../images/alvetrix.png" alt="Logo">
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
                    foreach($item['items'] as $subUrl) {
                        // Karena kita di dalam folder 'player', perlu relative path
                        if($current_page === 'view.php' && $subUrl === '../player.php') {
                            $isActive = true;
                            $isSubmenuOpen = true;
                            break;
                        }
                    }
                } else {
                    // Untuk menu tanpa submenu
                    if ($current_page === 'view.php' && $item['url'] === '../player.php') {
                        $isActive = true;
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
                           class="submenu-link <?php echo ($current_page === 'view.php' && $subUrl === '../player.php') ? 'active' : ''; ?>">
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
                <h1>Selamat Datang, <?php echo htmlspecialchars($admin_name ?? ''); ?> ! 👋</h1>
                <p>Player Profile - Sistem manajemen pemain futsal</p>
            </div>
            
            <div class="user-actions">
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <div class="container">
            <!-- Header -->
            <div class="header">
                <a href="../player.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    Kembali ke Players
                </a>
                <div class="page-title">
                    <i class="fas fa-user"></i>
                    <span>Player Profile</span>
                </div>
                <div class="action-buttons">
                    <a href="edit.php?id=<?php echo $player['id']; ?>" class="btn btn-warning">
                        <i class="fas fa-edit"></i>
                        Edit Player
                    </a>
                    <button type="button" class="btn btn-success" id="printPlayerBtn">
                        <i class="fas fa-camera"></i>
                        Print Player
                    </button>
                    <a href="../player.php" class="btn btn-primary">
                        <i class="fas fa-list"></i>
                        All Players
                    </a>
                </div>
            </div>

            <!-- Player Profile -->
            <div class="player-profile">
                <div class="player-photo-section">
                    <?php if (!empty($player['photo'])): 
                        $photo_path = '../../images/players/' . $player['photo'];
                        if (file_exists($photo_path)):
                            $photo_displayed = true;
                    ?>
                        <img src="<?php echo $photo_path; ?>" 
                             alt="<?php echo htmlspecialchars($player['name'] ?? ''); ?>" 
                             class="player-photo">
                    <?php 
                        endif;
                    endif; 
                    ?>
                    
                    <?php if (!$photo_displayed): ?>
                        <div class="default-photo">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    
                    <h2 class="player-name"><?php echo htmlspecialchars($player['name'] ?? ''); ?></h2>
                    
                    <?php if (!empty($player['team_name'])): ?>
                        <div class="player-team">
                            <i class="fas fa-users"></i>
                            <?php echo htmlspecialchars($player['team_name'] ?? ''); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($player['jersey_number'])): ?>
                        <div class="player-number">#<?php echo $player['jersey_number']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="player-details">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Personal Information
                    </h3>
                    
                    <div class="details-grid">
                        <div class="detail-group">
                            <div class="detail-label">
                                <i class="fas fa-id-card"></i>
                                NIK
                            </div>
                            <div class="detail-value">
                                <?php 
                                $nik = $player['nik'];
                                if (!empty($nik)) {
                                    $masked_nik = substr($nik, 0, 3) . '*********' . substr($nik, -4);
                                    echo $masked_nik;
                                } else {
                                    echo '-';
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">
                                <i class="fas fa-id-card"></i>
                                NISN
                            </div>
                            <div class="detail-value">
                                <?php echo !empty($player['nisn']) ? htmlspecialchars($player['nisn']) : '-'; ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">
                                <i class="fas fa-birthday-cake"></i>
                                Tempat/Tanggal Lahir
                            </div>
                            <div class="detail-value">
                                <?php 
                                echo !empty($player['birth_place']) ? htmlspecialchars($player['birth_place']) : '-';
                                echo ' / ';
                                echo !empty($player['birth_date']) && $player['birth_date'] != '0000-00-00' ? 
                                     date('d M Y', strtotime($player['birth_date'])) : '-';
                                ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">
                                <i class="fas fa-user-clock"></i>
                                Usia
                            </div>
                            <div class="detail-value">
                                <?php echo $player['age_years']; ?> tahun <?php echo $player['age_months']; ?> bulan
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">
                                <i class="fas fa-venus-mars"></i>
                                Jenis Kelamin
                            </div>
                            <div class="detail-value">
                                <?php echo formatGenderView($player['gender']); ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">
                                <i class="fas fa-running"></i>
                                Event
                            </div>
                            <div class="detail-value">
                                <?php echo !empty($player['sport_type']) ? htmlspecialchars($player['sport_type']) : '-'; ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">
                                <i class="fas fa-ruler-vertical"></i>
                                Tinggi/Berat
                            </div>
                            <div class="detail-value">
                                <?php 
                                $height = !empty($player['height']) ? $player['height'] . ' cm' : '-';
                                $weight = !empty($player['weight']) ? $player['weight'] . ' kg' : '-';
                                echo $height . ' / ' . $weight;
                                ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">
                                <i class="fas fa-envelope"></i>
                                Email
                            </div>
                            <div class="detail-value">
                                <?php echo !empty($player['email']) ? htmlspecialchars($player['email']) : '-'; ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">
                                <i class="fas fa-phone"></i>
                                Telpon
                            </div>
                            <div class="detail-value">
                                <?php echo !empty($player['phone']) ? htmlspecialchars($player['phone']) : '-'; ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">
                                <i class="fas fa-flag"></i>
                                Kewarganegaraan
                            </div>
                            <div class="detail-value">
                                <?php echo !empty($player['nationality']) ? htmlspecialchars($player['nationality']) : '-'; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">
                            <i class="fas fa-map-marker-alt"></i>
                            Alamat
                        </div>
                        <div class="detail-value">
                            <?php 
                            $address_parts = [];
                            if (!empty($player['street'])) $address_parts[] = $player['street'];
                            if (!empty($player['city'])) $address_parts[] = $player['city'];
                            if (!empty($player['province'])) $address_parts[] = $player['province'];
                            if (!empty($player['postal_code'])) $address_parts[] = $player['postal_code'];
                            if (!empty($player['country'])) $address_parts[] = $player['country'];
                            
                            echo !empty($address_parts) ? htmlspecialchars(implode(', ', $address_parts)) : '-';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Football Info -->
            <div class="skills-section">
                <h3 class="section-title">
                    <i class="fas fa-futbol"></i>
                    Football Information & Skills
                </h3>
                
                <div class="details-grid" style="margin-bottom: 30px;">
                    <div class="detail-group">
                        <div class="detail-label">
                            <i class="fas fa-football-ball"></i>
                            Kaki Dominan
                        </div>
                        <div class="detail-value">
                            <?php echo !empty($player['dominant_foot']) ? htmlspecialchars($player['dominant_foot']) : '-'; ?>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">
                            <i class="fas fa-crosshairs"></i>
                            Posisi
                        </div>
                        <div class="detail-value">
                            <?php echo !empty($player['position']) ? htmlspecialchars($player['position']) : '-'; ?>
                        </div>
                    </div>

                    <div class="detail-group">
                        <div class="detail-label">
                            <i class="fas fa-check-circle"></i>
                            Status
                        </div>
                        <div class="detail-value">
                            <?php
                            $status_map = [
                                'active' => 'Aktif',
                                'inactive' => 'Non-aktif',
                                'injured' => 'Cedera',
                                'suspended' => 'Skorsing'
                            ];
                            $status_key = strtolower($player['status'] ?? '');
                            echo htmlspecialchars($status_map[$status_key] ?? ucfirst($status_key ?: '-'));
                            ?>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">
                            <i class="fas fa-calendar-plus"></i>
                            Created At
                        </div>
                        <div class="detail-value">
                            <?php echo date('d M Y, H:i', strtotime($player['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div class="detail-group">
                        <div class="detail-label">
                            <i class="fas fa-calendar-check"></i>
                            Last Updated
                        </div>
                        <div class="detail-value">
                            <?php echo date('d M Y, H:i', strtotime($player['updated_at'])); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Skills Display -->
                <h4 style="font-size: 18px; color: var(--primary); margin-bottom: 20px;">
                    Player Skills
                </h4>
                
                <div class="skills-grid">
                    <?php 
                    $skills = [
                        'dribbling' => 'Dribbling',
                        'technique' => 'Technique',
                        'speed' => 'Speed',
                        'juggling' => 'Juggling',
                        'shooting' => 'Shooting',
                        'setplay_position' => 'Setplay Position',
                        'passing' => 'Passing',
                        'control' => 'Control'
                    ];
                    
                    foreach ($skills as $key => $label): 
                        $value = $player[$key] ?? 5;
                        $percentage = ($value / 10) * 100;
                    ?>
                    <div class="skill-item">
                        <div class="skill-name"><?php echo $label; ?></div>
                        <div class="skill-bar">
                            <div class="skill-fill" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <div class="skill-value"><?php echo $value; ?>/10</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Documents -->
            <div class="documents-section">
                <h3 class="section-title">
                    <i class="fas fa-file-alt"></i>
                    Documents
                </h3>
                
                <div class="documents-grid">
                    <!-- KTP -->
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="document-name">KTP / Kartu Identitas</div>
                        <div class="document-status <?php echo !empty($player['ktp_image']) ? 'status-available' : 'status-unavailable'; ?>">
                            <?php echo !empty($player['ktp_image']) ? 'Tersedia' : 'Tidak Tersedia'; ?>
                        </div>
                        <?php if (!empty($player['ktp_image'])): 
                            $ktp_path = '../../images/players/' . $player['ktp_image'];
                            if (file_exists($ktp_path)): ?>
                            <div class="document-preview">
                                <img src="<?php echo $ktp_path; ?>" 
                                     alt="KTP" onclick="viewDocument('<?php echo $ktp_path; ?>')">
                            </div>
                        <?php endif; endif; ?>
                    </div>
                    
                    <!-- KK -->
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="document-name">Kartu Keluarga</div>
                        <div class="document-status <?php echo !empty($player['kk_image']) ? 'status-available' : 'status-unavailable'; ?>">
                            <?php echo !empty($player['kk_image']) ? 'Tersedia' : 'Tidak Tersedia'; ?>
                        </div>
                        <?php if (!empty($player['kk_image'])): 
                            $kk_path = '../../images/players/' . $player['kk_image'];
                            if (file_exists($kk_path)): ?>
                            <div class="document-preview">
                                <img src="<?php echo $kk_path; ?>" 
                                     alt="KK" onclick="viewDocument('<?php echo $kk_path; ?>')">
                            </div>
                        <?php endif; endif; ?>
                    </div>
                    
                    <!-- Akta Lahir -->
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-baby"></i>
                        </div>
                        <div class="document-name">Akta Lahir</div>
                        <div class="document-status <?php echo !empty($player['birth_cert_image']) ? 'status-available' : 'status-unavailable'; ?>">
                            <?php echo !empty($player['birth_cert_image']) ? 'Tersedia' : 'Tidak Tersedia'; ?>
                        </div>
                        <?php if (!empty($player['birth_cert_image'])): 
                            $akte_path = '../../images/players/' . $player['birth_cert_image'];
                            if (file_exists($akte_path)): ?>
                            <div class="document-preview">
                                <img src="<?php echo $akte_path; ?>" 
                                     alt="Akta Lahir" onclick="viewDocument('<?php echo $akte_path; ?>')">
                            </div>
                        <?php endif; endif; ?>
                    </div>
                    
                    <!-- Ijazah -->
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="document-name">Ijazah / Raport</div>
                        <div class="document-status <?php echo !empty($player['diploma_image']) ? 'status-available' : 'status-unavailable'; ?>">
                            <?php echo !empty($player['diploma_image']) ? 'Tersedia' : 'Tidak Tersedia'; ?>
                        </div>
                        <?php if (!empty($player['diploma_image'])): 
                            $ijazah_path = '../../images/players/' . $player['diploma_image'];
                            if (file_exists($ijazah_path)): ?>
                            <div class="document-preview">
                                <img src="<?php echo $ijazah_path; ?>" 
                                     alt="Ijazah" onclick="viewDocument('<?php echo $ijazah_path; ?>')">
                            </div>
                        <?php endif; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>

// Mobile Menu Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
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

    // Animate skill bars on load (Merged from below)
    const skillBars = document.querySelectorAll('.skill-fill');
    skillBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0';
        setTimeout(() => {
            bar.style.width = width;
        }, 100);
    });
    
    // Handle image errors (Merged from below)
    document.querySelectorAll('.player-photo, .document-preview img').forEach(img => {
        img.addEventListener('error', function() {
            if (this.classList.contains('player-photo')) {
                showDefaultPhoto(this);
            }
        });
    });

    const printBtn = document.getElementById('printPlayerBtn');
    if (printBtn) {
        printBtn.addEventListener('click', async function() {
            await downloadPlayerScreenshot();
        });
    }
});

function viewDocument(imagePath) {
    window.open(imagePath, '_blank');
}

function showDefaultPhoto(imgElement) {
    imgElement.style.display = 'none';
    let defaultPhoto = imgElement.nextElementSibling;
    if (defaultPhoto && defaultPhoto.classList.contains('default-photo')) {
        defaultPhoto.style.display = 'flex';
    }
}

async function downloadPlayerScreenshot() {
    if (typeof html2canvas === 'undefined') {
        alert('Screenshot library not loaded. Please refresh the page.');
        return;
    }

    const target = document.querySelector('.main');
    if (!target) return;

    const printBtn = document.getElementById('printPlayerBtn');
    const originalText = printBtn ? printBtn.innerHTML : '';
    if (printBtn) {
        printBtn.disabled = true;
        printBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
    }

    try {
        const canvas = await html2canvas(target, {
            backgroundColor: '#ffffff',
            scale: 2,
            useCORS: true,
            windowWidth: target.scrollWidth,
            windowHeight: target.scrollHeight
        });

        const link = document.createElement('a');
        link.download = 'player-<?php echo (int)$player['id']; ?>-profile.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch (error) {
        alert('Failed to generate screenshot.');
    } finally {
        if (printBtn) {
            printBtn.disabled = false;
            printBtn.innerHTML = originalText;
        }
    }
}

// Animate skill bars on load
</script>
</body>
</html>
