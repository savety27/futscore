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

// Mendapatkan nama file saat ini untuk penanda menu 'Active'
$current_page = basename($_SERVER['PHP_SELF']);

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
    'Event' => [
        'icon' => '🏆',
        'name' => 'Event',
        'url' => 'challenge.php',
        'submenu' => false
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

$academy_name = "Hi, Welcome...";
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';
$email = $admin_email;

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Handle status filter (only active/inactive)
$status_filter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
$status_options = [
    'active' => 'Aktif',
    'inactive' => 'Non-aktif'
];
if (!array_key_exists($status_filter, $status_options)) {
    $status_filter = '';
}

// Query untuk mengambil data players
$query = "SELECT p.*, t.name as team_name, t.logo as team_logo 
          FROM players p 
          LEFT JOIN teams t ON p.team_id = t.id 
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM players p WHERE 1=1";

// Handle search
if (!empty($search)) {
    $search_term = "%{$search}%";
    $query .= " AND (p.name LIKE ? OR p.nik LIKE ? OR p.nisn LIKE ? OR p.email LIKE ?)";
    $count_query .= " AND (p.name LIKE ? OR p.nik LIKE ? OR p.nisn LIKE ? OR p.email LIKE ?)";
}

// Handle status filter
if (!empty($status_filter)) {
    $query .= " AND p.status = ?";
    $count_query .= " AND p.status = ?";
}

// Handle team filter
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$filter_team_name = '';

if ($team_id > 0) {
    $query .= " AND p.team_id = ?";
    $count_query .= " AND p.team_id = ?";
    
    // Get team name for display
    $stmt_team = $conn->prepare("SELECT name FROM teams WHERE id = ?");
    $stmt_team->execute([$team_id]);
    $team_data = $stmt_team->fetch(PDO::FETCH_ASSOC);
    if ($team_data) {
        $filter_team_name = $team_data['name'];
    }
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
        $params = [$search_term, $search_term, $search_term, $search_term];
        if (!empty($status_filter)) {
            $params[] = $status_filter;
        }
        if ($team_id > 0) {
            $params[] = $team_id;
        }
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
        $total_pages = ceil($total_data / $limit);
    } else {
        $stmt = $conn->prepare($count_query);
        $params = [];
        if (!empty($status_filter)) {
            $params[] = $status_filter;
        }
        if ($team_id > 0) {
            $params[] = $team_id;
        }
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
        $total_pages = ceil($total_data / $limit);
    }
    
    // Ambil data players
    if (!empty($search)) {
        $stmt = $conn->prepare($query);
        $params = [$search_term, $search_term, $search_term, $search_term];
        if (!empty($status_filter)) {
            $params[] = $status_filter;
        }
        if ($team_id > 0) {
            $params[] = $team_id;
        }
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $conn->prepare($query);
        $params = [];
        if (!empty($status_filter)) {
            $params[] = $status_filter;
        }
        if ($team_id > 0) {
            $params[] = $team_id;
        }
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
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
        return ucfirst($gender ?? '');
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
/* CSS sama seperti yang Anda miliki */
.filter-badge {
    background: #e3f2fd;
    color: #1976d2;
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #bbdefb;
    margin-left: 0;
    max-width: 100%;
    flex-wrap: wrap;
}

.filter-badge a {
    color: #1976d2;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.filter-badge a:hover {
    color: #0d47a1;
}
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
    width: calc(100% - 280px);
    min-width: 0;
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
    white-space: nowrap;
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
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--premium-shadow);
    border: 1px solid rgba(255, 255, 255, 0.6);
}

.page-title {
    font-size: 28px;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 15px;
}

    

.page-title-content {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    min-width: 0;
}

.search-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    max-width: 520px;
    min-width: 260px;
}

.search-input-wrap {
    position: relative;
    flex: 1;
}

.search-input-wrap input {
    width: 100%;
    padding: 15px 50px 15px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 16px;
    transition: var(--transition);
    background: #f8f9fa;
}

.search-input-wrap input:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
}

.search-input-wrap button {
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

.status-filter-select {
    min-width: 140px;
    padding: 13px 14px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 14px;
    background: #f8f9fa;
    color: var(--dark);
    transition: var(--transition);
}

.status-filter-select:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    justify-content: flex-end;
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
    white-space: nowrap;
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
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(5px);
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--premium-shadow);
    margin-bottom: 30px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border: 1px solid rgba(255, 255, 255, 0.8);
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

.status-cell {
    text-align: center;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    min-width: 90px;
    text-transform: uppercase;
}

.status-badge.active {
    background: rgba(46, 125, 50, 0.12);
    color: #2E7D32;
    border: 1px solid rgba(46, 125, 50, 0.25);
}

.status-badge.inactive {
    background: rgba(108, 117, 125, 0.12);
    color: #6C757D;
    border: 1px solid rgba(108, 117, 125, 0.25);
}

.status-badge.injured {
    background: rgba(211, 47, 47, 0.12);
    color: #D32F2F;
    border: 1px solid rgba(211, 47, 47, 0.25);
}

.status-badge.suspended {
    background: rgba(249, 168, 38, 0.15);
    color: #A76400;
    border: 1px solid rgba(249, 168, 38, 0.25);
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

/* ===== SMALL DESKTOP / LAPTOP (max-width: 1366px) ===== */
@media screen and (max-width: 1366px) {
    .main {
        padding: 24px;
    }

    .topbar,
    .page-header {
        padding: 18px 20px;
        gap: 16px;
        flex-wrap: wrap;
    }

    .greeting {
        min-width: 0;
        flex: 1 1 360px;
    }

    .greeting h1 {
        font-size: 24px;
    }

    .user-actions {
        margin-left: auto;
    }

    .page-title {
        font-size: 24px;
        flex: 1 1 280px;
        min-width: 0;
    }

    .search-bar {
        flex: 1 1 420px;
        max-width: none;
    }

    .action-buttons {
        flex: 1 1 auto;
    }
}

/* ===== TABLET (max-width: 1024px) ===== */
@media screen and (max-width: 1024px) {
    .sidebar {
        width: 240px;
    }

    .main {
        margin-left: 240px;
        width: calc(100% - 240px);
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

    .greeting {
        flex: none;
        width: 100%;
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
        justify-content: flex-start;
    }

    .page-title {
        flex: none;
        width: 100%;
    }

    .search-bar {
        flex: none;
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        min-width: 0;
        max-width: 100%;
    }

    .status-filter-select {
        width: 100%;
    }

    .search-input-wrap {
        flex: none;
        width: 100%;
        min-width: 0;
    }

    .page-title-content {
        width: 100%;
    }

    .action-buttons {
        flex: none;
        width: 100%;
        flex-wrap: wrap;
    }

    .btn {
        flex: 1;
        justify-content: center;
    }

    /* Table: Horizontal scroll */
    .table-container {
        overflow-x: auto;
    }

    .data-table {
        min-width: 1000px;
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

    .page-title-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .filter-badge {
        width: 100%;
        justify-content: space-between;
        font-size: 12px;
        border-radius: 12px;
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

    /* Compact buttons */
    .btn {
        padding: 10px 18px;
        font-size: 14px;
    }

    .logout-btn {
        padding: 10px 20px;
        font-size: 14px;
    }

    /* Stack action buttons vertically */
    .action-buttons {
        flex-direction: column;
    }

    .btn {
        width: 100%;
    }
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


<!-- Mobile Menu Components (hidden by default via CSS) -->
<div class="menu-overlay"></div>
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
            <p>Apakah Anda yakin ingin menghapus player <strong>"<span id="deletePlayerName"></span>"</strong>?</p>
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
                <div class="logo">
                    <img src="../images/alvetrix.png" alt="Logo">
                </div>
            </div>
            <div class="academy-info">
                <div class="academy-name"><?php echo $academy_name; ?></div>
                <div class="academy-email"><?php echo $email; ?></div>
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
                        if($current_page === $subUrl) {
                            $isActive = true;
                            $isSubmenuOpen = true;
                            break;
                        }
                    }
                } else {
                    if ($current_page === $item['url']) {
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
                <h1>Selamat Datang, <?php echo htmlspecialchars($admin_name ?? ''); ?> ! 👋</h1>
                <p>Player Management - Sistem manajemen pemain futsal</p>
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
        <div class="page-title-content">
            Daftar Player
            <?php if ($team_id > 0 && !empty($filter_team_name)): ?>
                <div class="filter-badge">
                    <span><i class="fas fa-filter"></i> Team: <?php echo htmlspecialchars($filter_team_name ?? ''); ?></span>
                    <a href="player.php" title="Clear Filter"><i class="fas fa-times-circle"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <form method="GET" action="" class="search-bar">
        <?php if ($team_id > 0): ?>
            <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
        <?php endif; ?>
        <select name="status" class="status-filter-select" onchange="this.form.submit()">
            <option value="">Semua Status</option>
            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Aktif</option>
            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Non-aktif</option>
        </select>
        <div class="search-input-wrap">
            <input type="text" name="search" placeholder="Cari player (nama, NIK, NISN)..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </div>
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
                        <th>Event</th>
                        <th>Status</th>
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
                                    $photo_path = '../images/players/' . $player['photo'];
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
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($player['name'] ?? ''); ?></strong><br>
                                <small style="color: var(--gray);">
                                    <?php echo !empty($player['position']) ? htmlspecialchars($player['position']) : '-'; ?>
                                </small>
                            </td>
                            <td class="team-cell">
                                <?php if (!empty($player['team_logo'])): ?>
                                    <img src="../images/teams/<?php echo htmlspecialchars($player['team_logo'] ?? ''); ?>" 
                                         alt="<?php echo htmlspecialchars($player['team_name'] ?? ''); ?>" 
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
                                    <span class="sport-badge"><?php echo htmlspecialchars($player['sport_type'] ?? ''); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="status-cell">
                                <?php
                                $status = strtolower($player['status'] ?? 'inactive');
                                $status_map = [
                                    'active' => 'Aktif',
                                    'inactive' => 'Non-aktif',
                                    'injured' => 'Cedera',
                                    'suspended' => 'Skorsing'
                                ];
                                ?>
                                <span class="status-badge <?php echo htmlspecialchars($status); ?>">
                                    <?php echo htmlspecialchars($status_map[$status] ?? ucfirst($status)); ?>
                                </span>
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
                                            data-player-id="<?php echo (int) $player['id']; ?>"
                                            data-player-name="<?php echo htmlspecialchars($player['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13" style="text-align: center; padding: 40px;">
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
        <?php
            $pagination_params = ['search' => $search];
            if ($team_id > 0) {
                $pagination_params['team_id'] = $team_id;
            }
            if (!empty($status_filter)) {
                $pagination_params['status'] = $status_filter;
            }
        ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($pagination_params, ['page' => $page - 1])); ?>" 
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
                <a href="?<?php echo http_build_query(array_merge($pagination_params, ['page' => $i])); ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                   <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($pagination_params, ['page' => $page + 1])); ?>" 
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
let currentPlayerId = null;

function showDefaultPhoto(imgElement) {
    imgElement.style.display = 'none';
    let defaultPhoto = imgElement.nextElementSibling;
    if (defaultPhoto && defaultPhoto.classList.contains('default-photo')) {
        defaultPhoto.style.display = 'flex';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const deletePlayerName = document.getElementById('deletePlayerName');
    const modal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    document.querySelectorAll('.btn-delete[data-player-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentPlayerId = this.getAttribute('data-player-id');
            if (deletePlayerName) {
                deletePlayerName.textContent = this.getAttribute('data-player-name') || '-';
            }
            if (modal) {
                modal.style.display = 'flex';
            }
        });
    });

    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.menu-overlay');

    if (menuToggle && sidebar && overlay) {
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

    // Handle image loading errors
    document.querySelectorAll('.player-photo').forEach(img => {
        img.addEventListener('error', function() {
            showDefaultPhoto(this);
        });
    });
    
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (currentPlayerId) {
                deletePlayer(currentPlayerId);
            }
        });
    }
});

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
            toastr.success('Player berhasil dihapus!');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            toastr.error('Error: ' + data.message);
            closeModal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toastr.error('Terjadi kesalahan saat menghapus player.');
        closeModal();
    });
}

function exportPlayers() {
    // Create export URL with current filter parameters
    const params = new URLSearchParams(window.location.search);
    const search = params.get('search') || '';
    const status = params.get('status') || '';
    const teamId = params.get('team_id') || '';
    window.location.href = `player/export.php?search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&team_id=${encodeURIComponent(teamId)}`;
}
</script>
</body>
</html>
