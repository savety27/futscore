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

// Get staff ID
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($staff_id <= 0) {
    header("Location: team_staff.php");
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

// Fetch staff data with certificates and counts
try {
    $stmt = $conn->prepare("
        SELECT ts.*, 
               t.name as team_name,
               t.alias as team_alias,
               (SELECT COUNT(*) FROM staff_certificates sc WHERE sc.staff_id = ts.id) as certificate_count,
               (SELECT COUNT(*) FROM staff_events se WHERE se.staff_id = ts.id) as event_count,
               (SELECT COUNT(*) FROM staff_matches sm WHERE sm.staff_id = ts.id) as match_count
        FROM team_staff ts
        LEFT JOIN teams t ON ts.team_id = t.id
        WHERE ts.id = ?
    ");
    $stmt->execute([$staff_id]);
    $staff_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff_data) {
        header("Location: team_staff.php");
        exit;
    }
    
    // Calculate age
    if (!empty($staff_data['birth_date'])) {
        $birthDate = new DateTime($staff_data['birth_date']);
        $today = new DateTime();
        $staff_data['age'] = $today->diff($birthDate)->y;
    } else {
        $staff_data['age'] = '-';
    }
    
    // Fetch certificates
    $stmt = $conn->prepare("SELECT * FROM staff_certificates WHERE staff_id = ? ORDER BY created_at DESC");
    $stmt->execute([$staff_id]);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch events (akan diisi nanti)
    $stmt = $conn->prepare("SELECT * FROM staff_events WHERE staff_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$staff_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch matches (akan diisi nanti)
    $stmt = $conn->prepare("SELECT * FROM staff_matches WHERE staff_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$staff_id]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error fetching staff data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Team Staff - FutScore</title>
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
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    text-align: center;
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    font-size: 40px;
    margin-bottom: 15px;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.stat-label {
    color: var(--gray);
    font-size: 14px;
    font-weight: 500;
}

/* Info Card */
.info-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: var(--card-shadow);
}

.info-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.info-title {
    font-size: 22px;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}

.info-title i {
    color: var(--secondary);
}

/* Staff Photo Large */
.staff-photo-large {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid white;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
    margin-top: 30px;
}

.info-item {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 15px;
    transition: var(--transition);
}

.info-item:hover {
    background: #f0f7ff;
    transform: translateY(-2px);
}

.info-label {
    display: block;
    font-size: 14px;
    color: var(--gray);
    margin-bottom: 8px;
    font-weight: 500;
}

.info-value {
    font-size: 16px;
    color: var(--dark);
    font-weight: 600;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-success {
    background: rgba(46, 125, 50, 0.1);
    color: var(--success);
    border: 1px solid rgba(46, 125, 50, 0.2);
}

.badge-danger {
    background: rgba(211, 47, 47, 0.1);
    color: var(--danger);
    border: 1px solid rgba(211, 47, 47, 0.2);
}

/* Certificate Cards */
.certificate-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.certificate-card {
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    background: white;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
    transition: var(--transition);
}

.certificate-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}

/* Tables */
.table-responsive {
    overflow-x: auto;
    border-radius: 10px;
    border: 1px solid #e0e0e0;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

thead {
    background: #f8f9fa;
}

th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    border-bottom: 2px solid #e0e0e0;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

td {
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
    color: var(--gray);
}

tbody tr:hover {
    background: #f8f9fa;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 60px;
    color: #ddd;
    margin-bottom: 20px;
}

.empty-state h4 {
    color: #999;
    margin-bottom: 10px;
    font-size: 18px;
}

.empty-state p {
    color: #999;
    font-size: 14px;
}

/* Modal for certificate image */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: var(--transition);
}

.modal.active {
    opacity: 1;
    visibility: visible;
}

.modal-content {
    position: relative;
    max-width: 90vw;
    max-height: 90vh;
    text-align: center;
}

.modal-close {
    position: absolute;
    top: -40px;
    right: 0;
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    transition: var(--transition);
}

.modal-close:hover {
    color: var(--secondary);
    transform: rotate(90deg);
}

.modal-image {
    max-width: 90vw;
    max-height: 90vh;
    border-radius: 5px;
    object-fit: contain;
}

.modal-caption {
    text-align: center;
    color: white;
    margin-top: 15px;
    font-size: 16px;
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

/* Responsive Styles */
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
        padding: 20px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .topbar {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .user-actions {
        justify-content: center;
    }
    
    .page-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .action-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .main {
        padding: 15px;
    }
    
    .info-card {
        padding: 20px;
    }
    
    .info-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .certificate-grid {
        grid-template-columns: 1fr;
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
                <a href="<?php echo $key === 'dashboard' ? '../dashboard.php' : '#'; ?>" 
                   class="menu-link <?php echo $key === 'master' ? 'active' : ''; ?>" 
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
                           class="submenu-link <?php echo $subitem === 'team_staff' ? 'active' : ''; ?>">
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
                <h1>Team Staff Details 👔</h1>
                <p>Detail informasi staff: <?php echo htmlspecialchars($staff_data['name']); ?></p>
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
                <i class="fas fa-user-tie"></i>
                <span>Detail Staff</span>
            </div>
            <div class="action-buttons">
                <a href="team_staff_edit.php?id=<?php echo $staff_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Staff
                </a>
                <a href="team_staff.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
                </a>
            </div>
        </div>

        <!-- STAFF STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color: #0A2463;">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="stat-number"><?php echo $staff_data['certificate_count']; ?></div>
                <div class="stat-label">Total Sertifikat</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #FFD700;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-number"><?php echo $staff_data['event_count']; ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #4CC9F0;">
                    <i class="fas fa-futbol"></i>
                </div>
                <div class="stat-number"><?php echo $staff_data['match_count']; ?></div>
                <div class="stat-label">Total Matches</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #2E7D32;">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-number"><?php echo $staff_data['age']; ?></div>
                <div class="stat-label">Usia</div>
            </div>
        </div>

        <!-- STAFF INFORMATION -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Staff
                </div>
                <div>
                    <?php if ($staff_data['is_active']): ?>
                        <span class="badge badge-success" style="padding: 8px 16px;">AKTIF</span>
                    <?php else: ?>
                        <span class="badge badge-danger" style="padding: 8px 16px;">NON-AKTIF</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="display: flex; gap: 40px; align-items: center; margin-bottom: 30px; flex-wrap: wrap;">
                <?php if (!empty($staff_data['photo'])): ?>
                    <img src="../<?php echo htmlspecialchars($staff_data['photo']); ?>" 
                         alt="<?php echo htmlspecialchars($staff_data['name']); ?>" 
                         class="staff-photo-large" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 5px solid white; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                <?php else: ?>
                    <div class="staff-photo-large" style="width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(135deg, #f0f0f0, #e0e0e0); display: flex; align-items: center; justify-content: center; border: 5px solid white; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                        <i class="fas fa-user-tie" style="color: #999; font-size: 48px;"></i>
                    </div>
                <?php endif; ?>
                
                <div style="flex: 1; min-width: 300px;">
                    <h2 style="font-size: 28px; color: #333; margin-bottom: 10px;">
                        <?php echo htmlspecialchars($staff_data['name']); ?>
                    </h2>
                    <p style="color: #666; margin-bottom: 15px;">
                        <?php 
                        $position_labels = [
                            'manager' => 'Manager',
                            'headcoach' => 'Head Coach',
                            'coach' => 'Coach',
                            'goalkeeper_coach' => 'Goalkeeper Coach',
                            'medic' => 'Medic',
                            'official' => 'Official'
                        ];
                        ?>
                        <i class="fas fa-briefcase"></i>
                        Jabatan: <strong><?php echo $position_labels[$staff_data['position']] ?? ucfirst($staff_data['position']); ?></strong>
                    </p>
                    <p style="color: #666; margin-bottom: 15px;">
                        <i class="fas fa-users"></i>
                        Team: <strong><?php echo htmlspecialchars($staff_data['team_name']); ?></strong>
                        <?php if (!empty($staff_data['team_alias'])): ?>
                            (<?php echo htmlspecialchars($staff_data['team_alias']); ?>)
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($staff_data['email'])): ?>
                        <p style="color: #666;">
                            <i class="fas fa-envelope"></i>
                            Email: <?php echo htmlspecialchars($staff_data['email']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nama Lengkap</span>
                    <div class="info-value"><?php echo htmlspecialchars($staff_data['name']); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Jabatan</span>
                    <div class="info-value">
                        <span class="badge" style="background: #FFD700; color: #333; padding: 5px 12px;">
                            <?php echo $position_labels[$staff_data['position']] ?? ucfirst($staff_data['position']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Team</span>
                    <div class="info-value">
                        <strong><?php echo htmlspecialchars($staff_data['team_name']); ?></strong>
                        <?php if (!empty($staff_data['team_alias'])): ?>
                            <br><small>(<?php echo htmlspecialchars($staff_data['team_alias']); ?>)</small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <div class="info-value">
                        <?php echo !empty($staff_data['email']) ? htmlspecialchars($staff_data['email']) : '-'; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Telepon</span>
                    <div class="info-value">
                        <?php echo !empty($staff_data['phone']) ? htmlspecialchars($staff_data['phone']) : '-'; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Tempat/Tanggal Lahir</span>
                    <div class="info-value">
                        <?php 
                        $birth_info = '';
                        if (!empty($staff_data['birth_place'])) {
                            $birth_info .= htmlspecialchars($staff_data['birth_place']);
                        }
                        if (!empty($staff_data['birth_date'])) {
                            $birth_info .= $birth_info ? ', ' : '';
                            $birth_info .= date('d/m/Y', strtotime($staff_data['birth_date']));
                        }
                        echo $birth_info ?: '-';
                        ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Usia</span>
                    <div class="info-value"><?php echo $staff_data['age']; ?> tahun</div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Alamat</span>
                    <div class="info-value">
                        <?php 
                        $address_parts = [];
                        if (!empty($staff_data['address'])) $address_parts[] = htmlspecialchars($staff_data['address']);
                        if (!empty($staff_data['city'])) $address_parts[] = htmlspecialchars($staff_data['city']);
                        if (!empty($staff_data['province'])) $address_parts[] = htmlspecialchars($staff_data['province']);
                        if (!empty($staff_data['postal_code'])) $address_parts[] = htmlspecialchars($staff_data['postal_code']);
                        if (!empty($staff_data['country'])) $address_parts[] = htmlspecialchars($staff_data['country']);
                        
                        echo $address_parts ? implode(', ', $address_parts) : '-';
                        ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Username Login</span>
                    <div class="info-value">
                        <?php echo !empty($staff_data['username']) ? htmlspecialchars($staff_data['username']) : '-'; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <div class="info-value">
                        <?php if ($staff_data['is_active']): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Non-Aktif</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Dibuat Pada</span>
                    <div class="info-value">
                        <?php echo date('d F Y, H:i', strtotime($staff_data['created_at'])); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Terakhir Diupdate</span>
                    <div class="info-value">
                        <?php echo date('d F Y, H:i', strtotime($staff_data['updated_at'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- CERTIFICATES SECTION -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-certificate"></i>
                    Sertifikat & Lisensi (<?php echo count($certificates); ?>)
                </div>
            </div>
            
            <?php if (!empty($certificates)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($certificates as $cert): ?>
                        <div style="border: 1px solid #e0e0e0; border-radius: 10px; padding: 20px; background: white; box-shadow: 0 3px 10px rgba(0,0,0,0.05);">
                            <h4 style="margin-bottom: 10px; color: var(--primary);"><?php echo htmlspecialchars($cert['certificate_name']); ?></h4>
                            <?php if ($cert['issuing_authority']): ?>
                                <p style="margin-bottom: 5px; color: #666;"><strong>Penerbit:</strong> <?php echo htmlspecialchars($cert['issuing_authority']); ?></p>
                            <?php endif; ?>
                            <?php if ($cert['issue_date']): ?>
                                <p style="margin-bottom: 15px; color: #666;"><strong>Tanggal Terbit:</strong> <?php echo date('d/m/Y', strtotime($cert['issue_date'])); ?></p>
                            <?php endif; ?>
                            
                            <div style="margin-top: 15px; text-align: center;">
                                <?php 
                                $file_ext = pathinfo($cert['certificate_file'], PATHINFO_EXTENSION);
                                if (in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif'])): 
                                ?>
                                    <img src="../uploads/certificates/<?php echo htmlspecialchars($cert['certificate_file']); ?>" 
                                         alt="<?php echo htmlspecialchars($cert['certificate_name']); ?>" 
                                         style="width: 100%; max-height: 200px; object-fit: contain; border-radius: 5px; cursor: pointer;"
                                         onclick="viewCertificateImage('<?php echo htmlspecialchars($cert['certificate_file']); ?>', '<?php echo htmlspecialchars($cert['certificate_name']); ?>')">
                                    <p style="margin-top: 10px; font-size: 12px; color: #666;">
                                        <a href="../uploads/certificates/<?php echo htmlspecialchars($cert['certificate_file']); ?>" 
                                           target="_blank" 
                                           style="color: var(--primary); text-decoration: none;">
                                            <i class="fas fa-external-link-alt"></i> Lihat Full Size
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <div style="background: #f0f0f0; padding: 30px; border-radius: 5px;">
                                        <i class="fas fa-file-alt" style="font-size: 64px; color: #666; margin-bottom: 15px;"></i>
                                        <p style="color: #666; margin-bottom: 15px;"><?php echo htmlspecialchars($cert['certificate_file']); ?></p>
                                        <a href="../uploads/certificates/<?php echo htmlspecialchars($cert['certificate_file']); ?>" 
                                           target="_blank" 
                                           class="btn btn-primary" 
                                           style="padding: 8px 16px; font-size: 14px;">
                                            <i class="fas fa-download"></i> Download File
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="background: none; box-shadow: none; padding: 40px 0;">
                    <i class="fas fa-certificate" style="font-size: 60px; color: #ddd; margin-bottom: 20px;"></i>
                    <h4 style="color: #999;">Belum ada sertifikat</h4>
                    <p style="color: #999;">Staff ini belum memiliki sertifikat atau lisensi</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- EVENTS SECTION -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-calendar-alt"></i>
                    Events (<?php echo count($events); ?>)
                </div>
            </div>
            
            <?php if (!empty($events)): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e0e0e0;">Event</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e0e0e0;">Role</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e0e0e0;">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td style="padding: 12px;">Event #<?php echo $event['event_id']; ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($event['role'] ?? '-'); ?></td>
                                <td style="padding: 12px;"><?php echo date('d/m/Y', strtotime($event['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="background: none; box-shadow: none; padding: 40px 0;">
                    <i class="fas fa-calendar-times" style="font-size: 60px; color: #ddd; margin-bottom: 20px;"></i>
                    <h4 style="color: #999;">Belum ada events</h4>
                    <p style="color: #999;">Staff ini belum mengikuti events</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- MATCHES SECTION -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-futbol"></i>
                    Matches (<?php echo count($matches); ?>)
                </div>
            </div>
            
            <?php if (!empty($matches)): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e0e0e0;">Match</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e0e0e0;">Role</th>
                                <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e0e0e0;">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $match): ?>
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td style="padding: 12px;">Match #<?php echo $match['match_id']; ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($match['role'] ?? '-'); ?></td>
                                <td style="padding: 12px;"><?php echo date('d/m/Y', strtotime($match['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="background: none; box-shadow: none; padding: 40px 0;">
                    <i class="fas fa-futbol" style="font-size: 60px; color: #ddd; margin-bottom: 20px;"></i>
                    <h4 style="color: #999;">Belum ada matches</h4>
                    <p style="color: #999;">Staff ini belum mengikuti matches</p>
                </div>
            <?php endif; ?>
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
            link.getAttribute('href') === 'team_staff.php') {
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

function viewCertificateImage(filename, title) {
    // Tampilkan gambar di modal baru
    const modal = document.createElement('div');
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 2000; display: flex; align-items: center; justify-content: center;';
    modal.innerHTML = `
        <div style="position: relative;">
            <button onclick="this.parentElement.parentElement.remove()" 
                    style="position: absolute; top: -40px; right: 0; background: none; border: none; color: white; font-size: 24px; cursor: pointer;">×</button>
            <img src="../uploads/certificates/${filename}" 
                 alt="${title}" 
                 style="max-width: 90vw; max-height: 90vh; border-radius: 5px;">
            <p style="text-align: center; color: white; margin-top: 10px;">${title}</p>
        </div>
    `;
    document.body.appendChild(modal);
}
</script>
</body>
</html>