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

// Get pelatih ID
$pelatih_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pelatih_id <= 0) {
    header("Location: pelatih.php");
    exit;
}

// Menu items
$menu_items = [
    'dashboard' => ['icon' => '🏠', 'name' => 'Dashboard', 'submenu' => false],
    'master' => ['icon' => '📊', 'name' => 'Master Data', 'submenu' => true, 'items' => ['player', 'team', 'team_staff', 'pelatih']],
    'event' => ['icon' => '📅', 'name' => 'Event', 'submenu' => true, 'items' => ['event', 'player_liga', 'staff_liga']],
    'match' => ['icon' => '⚽', 'name' => 'Match', 'submenu' => false],
    'challenge' => ['icon' => '🏆', 'name' => 'Challenge', 'submenu' => false],
    'training' => ['icon' => '🎯', 'name' => 'Training', 'submenu' => false],
    'settings' => ['icon' => '⚙️', 'name' => 'Settings', 'submenu' => false]
];

$academy_name = "Hi, Welcome...";
$email = "";

// Fetch pelatih data
try {
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$pelatih_id]);
    $pelatih_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pelatih_data) {
        header("Location: pelatih.php");
        exit;
    }
    
} catch (PDOException $e) {
    die("Error fetching pelatih data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Pelatih - FutScore</title>
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
    content: "👤";
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

/* Profile Section */
.profile-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: var(--card-shadow);
    display: flex;
    gap: 40px;
    align-items: center;
    flex-wrap: wrap;
}

.profile-avatar {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 60px;
    border: 5px solid white;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.profile-info {
    flex: 1;
    min-width: 300px;
}

.profile-name {
    font-size: 32px;
    color: var(--dark);
    margin-bottom: 10px;
}

.profile-username {
    font-size: 20px;
    color: var(--gray);
    margin-bottom: 15px;
}

.profile-role {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 600;
    margin-bottom: 20px;
}

.role-superadmin {
    background: linear-gradient(135deg, #FF6B6B, #FF8E8E);
    color: white;
}

.role-admin {
    background: linear-gradient(135deg, var(--primary), #4CC9F0);
    color: white;
}

.role-editor {
    background: linear-gradient(135deg, var(--warning), #FFD166);
    color: var(--dark);
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
    color: var(--primary);
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
    
    .action-buttons {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .profile-card {
        flex-direction: column;
        text-align: center;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
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
                           class="submenu-link <?php echo $subitem === 'pelatih' ? 'active' : ''; ?>">
                           <?php 
                           if ($subitem === 'pelatih') {
                               echo 'Pelatih';
                           } else {
                               echo ucfirst(str_replace('_', ' ', $subitem));
                           }
                           ?>
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
                <h1>Detail Pelatih 👤</h1>
                <p>Detail informasi pelatih: <?php echo htmlspecialchars($pelatih_data['full_name']); ?></p>
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
                <i class="fas fa-user-circle"></i>
                <span>Detail Pelatih</span>
            </div>
            <div class="action-buttons">
                <a href="pelatih_edit.php?id=<?php echo $pelatih_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Pelatih
                </a>
                <a href="pelatih.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
                </a>
            </div>
        </div>

        <!-- PROFILE SECTION -->
        <div class="profile-card">
            <div class="profile-avatar">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="profile-info">
                <h1 class="profile-name"><?php echo htmlspecialchars($pelatih_data['full_name']); ?></h1>
                <div class="profile-username">@<?php echo htmlspecialchars($pelatih_data['username']); ?></div>
                <div class="profile-role <?php echo 'role-' . $pelatih_data['role']; ?>">
                    <?php 
                    if ($pelatih_data['role'] === 'superadmin') {
                        echo 'Super Admin';
                    } elseif ($pelatih_data['role'] === 'admin') {
                        echo 'Admin';
                    } else {
                        echo 'Editor';
                    }
                    ?>
                </div>
                <?php if ($pelatih_data['is_active']): ?>
                    <span class="badge badge-success" style="padding: 8px 16px; font-size: 14px;">AKTIF</span>
                <?php else: ?>
                    <span class="badge badge-danger" style="padding: 8px 16px; font-size: 14px;">NON-AKTIF</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-label">Email</div>
                <div class="stat-number" style="font-size: 18px;"><?php echo htmlspecialchars($pelatih_data['email']); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-label">Bergabung Sejak</div>
                <div class="stat-number" style="font-size: 18px;">
                    <?php echo date('d F Y', strtotime($pelatih_data['created_at'])); ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-label">Login Terakhir</div>
                <div class="stat-number" style="font-size: 18px;">
                    <?php echo !empty($pelatih_data['last_login']) ? date('d F Y H:i', strtotime($pelatih_data['last_login'])) : '-'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-sync-alt"></i>
                </div>
                <div class="stat-label">Terakhir Update</div>
                <div class="stat-number" style="font-size: 18px;">
                    <?php echo date('d F Y H:i', strtotime($pelatih_data['updated_at'])); ?>
                </div>
            </div>
        </div>

        <!-- DETAILED INFORMATION -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Detail Akun
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Username</span>
                    <div class="info-value"><?php echo htmlspecialchars($pelatih_data['username']); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <div class="info-value"><?php echo htmlspecialchars($pelatih_data['email']); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Nama Lengkap</span>
                    <div class="info-value"><?php echo htmlspecialchars($pelatih_data['full_name']); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Role</span>
                    <div class="info-value">
                        <span class="badge <?php echo 'role-' . $pelatih_data['role']; ?>" style="padding: 6px 12px;">
                            <?php 
                            if ($pelatih_data['role'] === 'superadmin') {
                                echo 'Super Admin';
                            } elseif ($pelatih_data['role'] === 'admin') {
                                echo 'Admin';
                            } else {
                                echo 'Editor';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Status Akun</span>
                    <div class="info-value">
                        <?php if ($pelatih_data['is_active']): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Non-Aktif</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Tanggal Dibuat</span>
                    <div class="info-value">
                        <?php echo date('d F Y, H:i', strtotime($pelatih_data['created_at'])); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Terakhir Diupdate</span>
                    <div class="info-value">
                        <?php echo date('d F Y, H:i', strtotime($pelatih_data['updated_at'])); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Login Terakhir</span>
                    <div class="info-value">
                        <?php echo !empty($pelatih_data['last_login']) ? date('d F Y, H:i', strtotime($pelatih_data['last_login'])) : '-'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACCOUNT SECURITY INFO -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-shield-alt"></i>
                    Keamanan Akun
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Password Terakhir Diubah</span>
                    <div class="info-value">
                        <?php echo date('d F Y, H:i', strtotime($pelatih_data['updated_at'])); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Status Password</span>
                    <div class="info-value">
                        <span class="badge badge-success">
                            <i class="fas fa-check-circle"></i>
                            Aman
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Rekomendasi</span>
                    <div class="info-value">
                        <?php 
                        $created_date = strtotime($pelatih_data['created_at']);
                        $current_date = time();
                        $days_diff = floor(($current_date - $created_date) / (60 * 60 * 24));
                        
                        if ($days_diff > 90): ?>
                            <span class="badge badge-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Ganti password (lebih dari 90 hari)
                            </span>
                        <?php else: ?>
                            <span class="badge badge-success">
                                <i class="fas fa-check-circle"></i>
                                Password masih baru
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                <h4 style="color: var(--primary); margin-bottom: 10px;">Tips Keamanan:</h4>
                <ul style="color: #666; line-height: 1.6;">
                    <li>Ganti password secara berkala (setiap 90 hari)</li>
                    <li>Jangan bagikan password dengan siapapun</li>
                    <li>Pastikan email yang terdaftar masih aktif</li>
                    <li>Gunakan password yang kuat dengan kombinasi huruf, angka, dan simbol</li>
                </ul>
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
            link.getAttribute('href') === 'pelatih.php') {
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