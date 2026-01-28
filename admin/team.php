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

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Query untuk mengambil data teams dengan count yang benar
$base_query = "SELECT t.*, 
              (SELECT COUNT(*) FROM players p WHERE p.team_id = t.id AND p.status = 'active') as player_count,
              (SELECT COUNT(*) FROM team_staff ts WHERE ts.team_id = t.id) as staff_count
              FROM teams t WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM teams t WHERE 1=1";

// Handle search condition
if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (t.name LIKE ? OR t.alias LIKE ? OR t.coach LIKE ? OR t.sport_type LIKE ?)";
    $count_query .= " AND (t.name LIKE ? OR t.alias LIKE ? OR t.coach LIKE ? OR t.sport_type LIKE ?)";
}

$base_query .= " ORDER BY t.created_at DESC";

// Get total data
$total_data = 0;
$total_pages = 1;
$teams = [];

try {
    // Count total records
    if (!empty($search)) {
        $stmt = $conn->prepare($count_query);
        $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
    } else {
        $stmt = $conn->prepare($count_query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
    }
    
    $total_pages = ceil($total_data / $limit);
    
    // Get data with pagination
    $query = $base_query . " LIMIT ? OFFSET ?";
    
    if (!empty($search)) {
        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $search_term);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $search_term);
        $stmt->bindValue(4, $search_term);
        $stmt->bindValue(5, $limit, PDO::PARAM_INT);
        $stmt->bindValue(6, $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Team Management - FutScore</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
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

/* Table Styles */
.table-container {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--card-shadow);
    margin-bottom: 30px;
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 100%;
    table-layout: auto;
}

.data-table thead {
    background: linear-gradient(135deg, var(--primary), #1a365d);
    color: white;
}

.data-table th {
    padding: 12px 8px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid var(--secondary);
    white-space: nowrap;
    font-size: 12px;
}

.data-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
    transition: var(--transition);
}

.data-table tbody tr:hover {
    background: #f8f9fa;
}

.data-table td {
    padding: 8px;
    vertical-align: middle;
    font-size: 12px;
}

.logo-cell {
    width: 80px;
}

.team-logo {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e0e0e0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.team-name-cell {
    font-weight: 600;
    color: var(--dark);
}

.alias-cell {
    color: var(--gray);
    font-size: 14px;
}

.coach-cell {
    font-weight: 500;
    color: var(--dark);
}

.established-cell {
    color: var(--gray);
    font-size: 14px;
}

.uniform-cell {
    text-align: center;
    font-weight: 500;
    color: var(--primary);
    background: #f0f7ff;
    border-radius: 8px;
    padding: 6px 12px;
    min-width: 120px;
}

.count-cell {
    text-align: center;
    font-weight: 600;
    color: var(--primary);
    background: #f0f7ff;
    border-radius: 8px;
    padding: 8px 12px;
    min-width: 60px;
}

.basecamp-cell {
    color: var(--gray);
    font-size: 14px;
}

.sport-cell {
    text-align: center;
}

.sport-badge {
    display: inline-block;
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    background: linear-gradient(135deg, var(--secondary), #FFEC8B);
    color: var(--primary);
}

.date-cell {
    color: var(--gray);
    font-size: 14px;
}

.action-cell {
    min-width: 150px;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.action-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    font-size: 16px;
    text-decoration: none;
    display: inline-flex;
}

.btn-edit {
    background: rgba(76, 175, 80, 0.1);
    color: var(--success);
}

.btn-edit:hover {
    background: var(--success);
    color: white;
}

.btn-delete {
    background: rgba(211, 47, 47, 0.1);
    color: var(--danger);
}

.btn-delete:hover {
    background: var(--danger);
    color: white;
}

.btn-view {
    background: rgba(10, 36, 99, 0.1);
    color: var(--primary);
}

.btn-view:hover {
    background: var(--primary);
    color: white;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 30px;
}

.page-link {
    padding: 12px 18px;
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    color: var(--dark);
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition);
}

.page-link:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-link.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Empty State */
.empty-state {
    background: white;
    border-radius: 20px;
    padding: 60px 40px;
    box-shadow: var(--card-shadow);
    margin-bottom: 40px;
    text-align: center;
}

.empty-icon {
    font-size: 80px;
    color: var(--primary);
    opacity: 0.2;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 24px;
    color: var(--dark);
    margin-bottom: 15px;
}

.empty-state p {
    color: var(--gray);
    max-width: 600px;
    margin: 0 auto 30px;
    line-height: 1.6;
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

.alert-warning {
    background: rgba(249, 168, 38, 0.1);
    border-left: 4px solid var(--warning);
    color: var(--warning);
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

/* DataTable Custom Style */
.dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 8px 12px;
    margin: 0 2px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: var(--primary) !important;
    color: white !important;
    border-color: var(--primary);
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
    
    .data-table {
        min-width: 1000px;
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
                           class="submenu-link <?php echo $subitem === 'team' ? 'active' : ''; ?>">
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
                <h1>Team Management ⚽</h1>
                <p>Kelola data team dengan mudah dan cepat</p>
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

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-users"></i>
                <span>Daftar Team</span>
            </div>
            
            <form method="GET" action="" class="search-bar" id="searchForm">
                <input type="text" name="search" placeholder="Cari team (nama, alias, coach, cabor)..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </form>
            
            <div class="action-buttons">
                <a href="team_create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Team
                </a>
                <button class="btn btn-success" onclick="exportTeams()">
                    <i class="fas fa-download"></i>
                    Export Excel
                </button>
            </div>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
        </div>
        <?php endif; ?>

        <!-- TEAM TABLE -->
        <div class="table-container">
            <table class="data-table" id="teamsTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Logo</th>
                        <th>Nama</th>
                        <th>Alias</th>
                        <th>Manager/Coach</th>
                        <th>Tgl Berdiri</th>
                        <th>Warna Kostum</th>
                        <th># Player</th>
                        <th># Staff</th>
                        <th>Basecamp</th>
                        <th>Cabor</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($teams) && count($teams) > 0): ?>
                        <?php $no = $offset + 1; ?>
                        <?php foreach($teams as $team): ?>
                        <tr>
                            <td class="count-cell"><?php echo $no++; ?></td>
                            <td class="logo-cell">
                                <?php if (!empty($team['logo'])): ?>
                                    <img src="../images/teams/<?php echo htmlspecialchars($team['logo']); ?>" 
                                         alt="<?php echo htmlspecialchars($team['name']); ?>" 
                                         class="team-logo">
                                <?php else: ?>
                                    <div class="team-logo" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-shield-alt" style="color: #999; font-size: 24px;"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="team-name-cell">
                                <strong><?php echo htmlspecialchars($team['name']); ?></strong>
                            </td>
                            <td class="alias-cell">
                                <?php echo !empty($team['alias']) ? htmlspecialchars($team['alias']) : '-'; ?>
                            </td>
                            <td class="coach-cell">
                                <?php echo htmlspecialchars($team['coach']); ?>
                            </td>
                            <td class="established-cell">
                                <?php echo !empty($team['established_year']) ? $team['established_year'] : '-'; ?>
                            </td>
                            <td class="uniform-cell">
                                <?php echo !empty($team['uniform_color']) ? htmlspecialchars($team['uniform_color']) : '-'; ?>
                            </td>
                            <td class="count-cell">
                                <span class="badge badge-primary"><?php echo $team['player_count']; ?> Players</span>
                            </td>
                            <td class="count-cell">
                                <span class="badge badge-secondary"><?php echo $team['staff_count']; ?> Staff</span>
                            </td>
                            <td class="basecamp-cell">
                                <?php echo !empty($team['basecamp']) ? htmlspecialchars($team['basecamp']) : '-'; ?>
                            </td>
                            <td class="sport-cell">
                                <?php if (!empty($team['sport_type'])): ?>
                                    <span class="sport-badge"><?php echo htmlspecialchars($team['sport_type']); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="status-cell">
                                <?php if ($team['is_active']): ?>
                                    <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Non-Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="date-cell">
                                <?php echo date('d M Y', strtotime($team['created_at'])); ?>
                            </td>
                            <td class="action-cell">
                                <div class="action-buttons">
                                    <a href="team_view.php?id=<?php echo $team['id']; ?>" 
                                       class="action-btn btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="team_edit.php?id=<?php echo $team['id']; ?>" 
                                       class="action-btn btn-edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="action-btn btn-delete" 
                                            onclick="deleteTeam(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars(addslashes($team['name'])); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="14" style="text-align: center; padding: 40px;">
                                <div class="empty-state" style="box-shadow: none; padding: 0;">
                                    <div class="empty-icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <h3>Belum Ada Data Team</h3>
                                    <p>Mulai dengan menambahkan team pertama Anda menggunakan tombol "Add Team" di atas.</p>
                                    <a href="team_create.php" class="btn btn-primary" style="margin-top: 20px;">
                                        <i class="fas fa-plus"></i>
                                        Tambah Team Pertama
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>" 
                   class="page-link">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php else: ?>
                <span class="page-link disabled">
                    <i class="fas fa-chevron-left"></i>
                </span>
            <?php endif; ?>
            
            <?php 
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++): 
            ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                   <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>" 
                   class="page-link">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <span class="page-link disabled">
                    <i class="fas fa-chevron-right"></i>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
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
            link.getAttribute('href') === 'team.php') {
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
    
    // Initialize DataTable
    $('#teamsTable').DataTable({
        searching: false,
        paging: false,
        info: false,
        ordering: true,
        language: {
            emptyTable: "Tidak ada data team",
            zeroRecords: "Tidak ada data yang cocok dengan pencarian"
        }
    });
});

function deleteTeam(teamId, teamName) {
    if (confirm(`Apakah Anda yakin ingin menghapus team "${teamName}"?`)) {
        fetch(`team_delete.php?id=${teamId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                toastr.success('Team berhasil dihapus!');
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                toastr.error('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            toastr.error('Terjadi kesalahan saat menghapus team.');
        });
    }
}

function exportTeams() {
    window.location.href = 'team_export.php' + (window.location.search ? window.location.search + '&export=excel' : '?export=excel');
}
</script>
</body>
</html>