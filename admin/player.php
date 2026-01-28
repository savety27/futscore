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
    header("Location: index.php");
    exit;
}

// Data menu dropdown
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
        'submenu' => false
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

$academy_name = "Marbella Academy";
$email = "marbellacommunitycenter@gmail.com";

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Query untuk mengambil data players
$query = "SELECT p.*, t.name as team_name, t.logo as team_logo 
          FROM players p 
          LEFT JOIN teams t ON p.team_id = t.id 
          WHERE p.status = 'active'";

$count_query = "SELECT COUNT(*) as total FROM players p WHERE p.status = 'active'";

// Handle search
if (!empty($search)) {
    $search_term = "%{$search}%";
    $query .= " AND (p.name LIKE ? OR p.nik LIKE ? OR p.nisn LIKE ? OR p.email LIKE ?)";
    $count_query .= " AND (p.name LIKE ? OR p.nik LIKE ? OR p.nisn LIKE ? OR p.email LIKE ?)";
}

$query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";

// Initialize variables
$error = null;
$players = [];
$total_data = 0;
$total_pages = 1;

try {
    // Hitung total data
    if (!empty($search)) {
        $stmt = $conn->prepare($count_query);
        $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
        $total_pages = ceil($total_data / $limit);
    } else {
        $stmt = $conn->prepare($count_query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
        $total_pages = ceil($total_data / $limit);
    }
    
    // Ambil data players
    if (!empty($search)) {
        $stmt = $conn->prepare($query);
        $stmt->execute([$search_term, $search_term, $search_term, $search_term, $limit, $offset]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->execute([$limit, $offset]);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}

// Hitung usia dari tanggal lahir
function calculateAge($birth_date) {
    if (empty($birth_date) || $birth_date == '0000-00-00') return '-';
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $age = $today->diff($birth);
    
    $years = $age->y;
    $months = $age->m;
    
    if ($years == 0 && $months == 0) {
        return 'Baru lahir';
    } elseif ($years == 0) {
        return $months . ' bulan';
    } else {
        return $years . ' tahun';
    }
}

// Format gender untuk display
function formatGender($gender) {
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
        return ucfirst($gender);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Player Management - FutScore</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* CSS sama seperti yang Anda miliki */
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
    min-width: 1200px;
}

.data-table thead {
    background: linear-gradient(135deg, var(--primary), #1a365d);
    color: white;
}

.data-table th {
    padding: 15px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid var(--secondary);
    font-size: 14px;
}

.data-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
    transition: var(--transition);
}

.data-table tbody tr:hover {
    background: #f8f9fa;
}

.data-table td {
    padding: 12px 15px;
    vertical-align: middle;
    font-size: 14px;
}

.photo-cell {
    width: 70px;
}

.player-photo {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e0e0e0;
}

.default-photo {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--secondary), #FFEC8B);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #e0e0e0;
}

.default-photo i {
    font-size: 24px;
    color: var(--primary);
}

.team-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.team-logo {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e0e0e0;
}

.team-name {
    font-weight: 500;
    color: var(--dark);
}

.number-cell {
    text-align: center;
    font-weight: 700;
    color: var(--primary);
    background: #f0f7ff;
    border-radius: 8px;
    padding: 8px 0;
    min-width: 50px;
}

.age-cell {
    text-align: center;
    color: var(--gray);
    font-size: 14px;
}

.gender-cell {
    text-align: center;
}

.gender-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    min-width: 80px;
}

.gender-male {
    background: rgba(10, 36, 99, 0.1);
    color: var(--primary);
    border: 1px solid rgba(10, 36, 99, 0.2);
}

.gender-female {
    background: rgba(233, 30, 99, 0.1);
    color: #E91E63;
    border: 1px solid rgba(233, 30, 99, 0.2);
}

.gender-other {
    background: rgba(158, 158, 158, 0.1);
    color: #757575;
    border: 1px solid rgba(158, 158, 158, 0.2);
}

.sport-cell {
    text-align: center;
}

.sport-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    background: linear-gradient(135deg, var(--secondary), #FFEC8B);
    color: var(--primary);
    min-width: 80px;
}

.date-cell {
    color: var(--gray);
    font-size: 14px;
}

.action-cell {
    min-width: 150px;
}

.action-buttons-small {
    display: flex;
    gap: 5px;
}

.action-btn {
    width: 35px;
    height: 35px;
    border-radius: 8px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    font-size: 14px;
    text-decoration: none;
}

.btn-view {
    background: rgba(10, 36, 99, 0.1);
    color: var(--primary);
}

.btn-view:hover {
    background: var(--primary);
    color: white;
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

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 30px;
}

.page-link {
    padding: 10px 15px;
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    color: var(--dark);
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition);
    font-size: 14px;
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

/* Delete Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 20px;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    color: var(--danger);
}

.modal-header i {
    font-size: 24px;
}

.modal-body {
    margin-bottom: 25px;
    color: var(--dark);
    line-height: 1.6;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger), #B71C1C);
    color: white;
}

.btn-danger:hover {
    background: linear-gradient(135deg, #B71C1C, var(--danger));
}

/* Image Load Error Handling */
.player-photo.error {
    display: none;
}

.player-photo.error + .default-photo {
    display: flex !important;
}
</style>
</head>
<body>

<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Konfirmasi Hapus Player</h3>
        </div>
        <div class="modal-body">
            <p id="deleteMessage">Apakah Anda yakin ingin menghapus player ini?</p>
            <p style="color: var(--danger); font-weight: 600; margin-top: 10px;">
                <i class="fas fa-exclamation-circle"></i> Data yang dihapus tidak dapat dikembalikan!
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Batal</button>
            <button class="btn btn-danger" id="confirmDeleteBtn">Hapus</button>
        </div>
    </div>
</div>

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
                <?php 
                $menu_link = '#';
                if (!$item['submenu']) {
                    $menu_link = $key === 'dashboard' ? 'dashboard.php' : ($key . '.php');
                }
                ?>
                <a href="<?php echo $menu_link; ?>" 
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
                        $subitem_url = '';
                        if ($subitem === 'player') {
                            $subitem_url = 'player.php';
                        } elseif ($subitem === 'team_staff') {
                            $subitem_url = 'team_staff.php';
                        } elseif ($subitem === 'player_liga') {
                            $subitem_url = 'player_liga.php';
                        } elseif ($subitem === 'staff_liga') {
                            $subitem_url = 'staff_liga.php';
                        } else {
                            $subitem_url = $subitem . '.php';
                        }
                        ?>
                        <a href="<?php echo $subitem_url; ?>" 
                           class="submenu-link <?php echo $subitem === 'player' ? 'active' : ''; ?>">
                           <?php echo ucwords(str_replace('_', ' ', $subitem)); ?>
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
                <h1>Player Management ⚽</h1>
                <p>Kelola data pemain dengan mudah dan cepat</p>
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
                <span>Daftar Player</span>
            </div>
            
            <form method="GET" action="" class="search-bar">
                <input type="text" name="search" placeholder="Cari player (nama, NIK, NISN)..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </form>
            
            <div class="action-buttons">
                <a href="player/add.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Player
                </a>
                <button class="btn btn-success" onclick="exportPlayers()">
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

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></span>
        </div>
        <?php endif; ?>

        <!-- PLAYER TABLE -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Photo</th>
                        <th>Nama</th>
                        <th>Team</th>
                        <th>No</th>
                        <th>Usia</th>
                        <th>JK</th>
                        <th>NISN</th>
                        <th>NIK</th>
                        <th>Cabor</th>
                        <th>Tgl Daftar</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($players) && count($players) > 0): ?>
                        <?php $no = $offset + 1; ?>
                        <?php foreach($players as $player): ?>
                        <tr>
                            <td class="number-cell"><?php echo $no++; ?></td>
                            <td class="photo-cell">
                                <?php 
                                $photo_displayed = false;
                                if (!empty($player['photo'])): 
                                    // Cek berbagai kemungkinan path
                                    $possible_paths = [
                                        '../../images/players/' . $player['photo'],
                                        '../../' . $player['photo'],
                                        'images/players/' . $player['photo'],
                                        'uploads/players/' . $player['photo'],
                                        $player['photo']
                                    ];
                                    
                                    foreach ($possible_paths as $photo_path):
                                        if (file_exists($photo_path) && is_file($photo_path)): 
                                            $photo_displayed = true;
                                            break;
                                        endif;
                                    endforeach;
                                    
                                    if ($photo_displayed): 
                                ?>
                                    <img src="<?php echo $photo_path; ?>" 
                                         alt="<?php echo htmlspecialchars($player['name']); ?>" 
                                         class="player-photo"
                                         onerror="this.style.display='none'; showDefaultPhoto(this);">
                                <?php 
                                    endif;
                                endif; 
                                ?>
                                
                                <?php if (!$photo_displayed): ?>
                                    <div class="default-photo">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($player['name']); ?></strong><br>
                                <small style="color: var(--gray);">
                                    <?php echo !empty($player['position']) ? htmlspecialchars($player['position']) : '-'; ?>
                                </small>
                            </td>
                            <td class="team-cell">
                                <?php if (!empty($player['team_logo']) && file_exists('../../' . $player['team_logo'])): ?>
                                    <img src="../../<?php echo htmlspecialchars($player['team_logo']); ?>" 
                                         alt="<?php echo htmlspecialchars($player['team_name']); ?>" 
                                         class="team-logo">
                                <?php endif; ?>
                                <span class="team-name">
                                    <?php echo !empty($player['team_name']) ? htmlspecialchars($player['team_name']) : '-'; ?>
                                </span>
                            </td>
                            <td class="number-cell">
                                <?php echo !empty($player['jersey_number']) ? $player['jersey_number'] : '-'; ?>
                            </td>
                            <td class="age-cell">
                                <?php echo calculateAge($player['birth_date']); ?>
                            </td>
                            <td class="gender-cell">
                                <?php 
                                $gender_display = formatGender($player['gender']);
                                $gender_class = '';
                                
                                if (strtolower($gender_display) === 'perempuan') {
                                    $gender_class = 'gender-female';
                                } elseif (strtolower($gender_display) === 'laki-laki') {
                                    $gender_class = 'gender-male';
                                } else {
                                    $gender_class = 'gender-other';
                                }
                                ?>
                                <span class="gender-badge <?php echo $gender_class; ?>">
                                    <?php echo $gender_display; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo !empty($player['nisn']) ? htmlspecialchars($player['nisn']) : '-'; ?>
                            </td>
                            <td>
                                <?php if (!empty($player['nik'])): ?>
                                    <?php 
                                    $nik = $player['nik'];
                                    $masked_nik = substr($nik, 0, 3) . '*********' . substr($nik, -4);
                                    echo $masked_nik;
                                    ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="sport-cell">
                                <?php if (!empty($player['sport_type'])): ?>
                                    <span class="sport-badge"><?php echo htmlspecialchars($player['sport_type']); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="date-cell">
                                <?php echo date('d/m/Y', strtotime($player['created_at'])); ?>
                            </td>
                            <td class="action-cell">
                                <div class="action-buttons-small">
                                    <a href="player/view.php?id=<?php echo $player['id']; ?>" 
                                       class="action-btn btn-view"
                                       title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="player/edit.php?id=<?php echo $player['id']; ?>" 
                                       class="action-btn btn-edit"
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="action-btn btn-delete" 
                                            onclick="showDeleteModal(<?php echo $player['id']; ?>, '<?php echo htmlspecialchars(addslashes($player['name'])); ?>')"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" style="text-align: center; padding: 40px;">
                                <div class="empty-state" style="box-shadow: none; padding: 0;">
                                    <div class="empty-icon">
                                        <i class="fas fa-user-slash"></i>
                                    </div>
                                    <h3>Belum Ada Data Player</h3>
                                    <p>Mulai dengan menambahkan player pertama Anda menggunakan tombol "Add New Player" di atas.</p>
                                    <a href="player/add.php" class="btn btn-primary" style="margin-top: 20px;">
                                        <i class="fas fa-plus"></i>
                                        Tambah Player Pertama
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

<script>
let currentPlayerId = null;

// Fungsi untuk menampilkan default photo jika image error
function showDefaultPhoto(imgElement) {
    imgElement.style.display = 'none';
    let defaultPhoto = imgElement.nextElementSibling;
    if (defaultPhoto && defaultPhoto.classList.contains('default-photo')) {
        defaultPhoto.style.display = 'flex';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar untuk mobile
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            this.innerHTML = sidebar.classList.contains('active') 
                ? '<i class="fas fa-times"></i>' 
                : '<i class="fas fa-bars"></i>';
        });
    }
    
    // Auto close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1200) {
            if (sidebar && !sidebar.contains(e.target) && menuToggle && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                if (menuToggle) {
                    menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                }
            }
        }
    });
    
    // Menu dropdown functionality
    document.querySelectorAll('.menu-link[data-menu]').forEach(link => {
        link.addEventListener('click', function(e) {
            const menuKey = this.getAttribute('data-menu');
            const submenu = document.getElementById('submenu-' + menuKey);
            
            if (submenu) {
                e.preventDefault();
                const isOpen = submenu.classList.contains('open');
                
                // Close all other submenus
                document.querySelectorAll('.submenu').forEach(sm => {
                    sm.classList.remove('open');
                });
                document.querySelectorAll('.menu-arrow').forEach(arrow => {
                    arrow.classList.remove('rotate');
                });
                
                // Toggle current submenu
                if (!isOpen) {
                    submenu.classList.add('open');
                    this.querySelector('.menu-arrow').classList.add('rotate');
                }
            }
        });
    });
    
    // Highlight active menu based on current page
    const currentPage = window.location.pathname.split('/').pop() || 'player.php';
    document.querySelectorAll('.menu-link, .submenu-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || 
            (currentPage === 'player.php' && href.includes('player.php'))) {
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
    
    // Handle image loading errors
    document.querySelectorAll('.player-photo').forEach(img => {
        img.addEventListener('error', function() {
            showDefaultPhoto(this);
        });
    });
    
    // Delete button functionality
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (currentPlayerId) {
                deletePlayer(currentPlayerId);
            }
        });
    }
});

function showDeleteModal(playerId, playerName) {
    currentPlayerId = playerId;
    const deleteMessage = document.getElementById('deleteMessage');
    if (deleteMessage) {
        deleteMessage.innerHTML = 
            `Apakah Anda yakin ingin menghapus player <strong>"${playerName}"</strong>?`;
    }
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeModal() {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.style.display = 'none';
    }
    currentPlayerId = null;
}

// Close modal when clicking outside
const modal = document.getElementById('deleteModal');
if (modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
}

function deletePlayer(playerId) {
    fetch(`player/delete.php?id=${playerId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
            closeModal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat menghapus player.');
        closeModal();
    });
}

function exportPlayers() {
    // Create export URL with current search parameters
    const search = new URLSearchParams(window.location.search).get('search') || '';
    window.location.href = `player/export.php?search=${encodeURIComponent(search)}`;
}
</script>
</body>
</html>