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

// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';

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
            'player' => ['name' => 'Player', 'url' => 'player.php'],
            'team' => ['name' => 'Team', 'url' => 'team.php'],
            'team_staff' => ['name' => 'Team Staff', 'url' => 'team_staff.php'],
            'transfer' => ['name' => 'Transfer', 'url' => 'transfer.php'],
        ]
    ],
    'Event' => [
        'icon' => '🏆',
        'name' => 'Event',
        'url' => 'challenge.php',
        'submenu' => false
    ],
    'venue' => [
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
$email = $admin_email;

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Validasi sort column
$allowed_sort = ['name', 'location', 'capacity', 'created_at', 'updated_at'];
if (!in_array($sort, $allowed_sort)) {
    $sort = 'created_at';
}

// Validasi order
$allowed_order = ['asc', 'desc'];
if (!in_array($order, $allowed_order)) {
    $order = 'desc';
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Query untuk mengambil data venues
$base_query = "SELECT v.* FROM venues v WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM venues v WHERE 1=1";

// Handle search condition
if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (v.name LIKE ? OR v.location LIKE ? OR v.facilities LIKE ?)";
    $count_query .= " AND (v.name LIKE ? OR v.location LIKE ? OR v.facilities LIKE ?)";
}

// Tambahkan sorting - PASTIKAN sorting ini ADA sebelum LIMIT
$base_query .= " ORDER BY v.$sort $order";

// Get total data
$total_data = 0;
$total_pages = 1;
$venues = [];

try {
    // Count total records
    if (!empty($search)) {
        $stmt = $conn->prepare($count_query);
        $stmt->execute([$search_term, $search_term, $search_term]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
    } else {
        $stmt = $conn->prepare($count_query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
    }
    
    $total_pages = ceil($total_data / $limit);
    
    // Get data with pagination - PASTIKAN sorting MASUK di query
    $query = $base_query . " LIMIT ? OFFSET ?";
    
    if (!empty($search)) {
        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $search_term);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $search_term);
        $stmt->bindValue(4, $limit, PDO::PARAM_INT);
        $stmt->bindValue(5, $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}

// Fungsi untuk mendapatkan URL dengan parameter yang tepat
function getSortUrl($sort_field, $current_sort, $current_order) {
    $params = $_GET;
    $params['sort'] = $sort_field;
    
    if ($current_sort == $sort_field) {
        // Jika sudah sorting field yang sama, toggle order
        $params['order'] = $current_order == 'asc' ? 'desc' : 'asc';
    } else {
        // Jika field berbeda, default ke asc
        $params['order'] = 'asc';
    }
    
    return '?' . http_build_query($params);
}

// Fungsi untuk mendapatkan arrow icon
// Fungsi untuk mendapatkan arrow icon dengan FontAwesome
function getSortIcon($sort_field, $current_sort, $current_order) {
    if ($current_sort == $sort_field) {
        if ($current_order == 'asc') {
            return '<i class="fas fa-sort-up" style="color: #FFD700;"></i>';
        } else {
            return '<i class="fas fa-sort-down" style="color: #FFD700;"></i>';
        }
    }
    // Tampilkan kedua icon (up dan down) tapi lebih transparan
    return '<div class="sort-icons">
                <i class="fas fa-sort-up"></i>
                <i class="fas fa-sort-down"></i>
            </div>';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Venue Management</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
/* Semua CSS tetap sama seperti sebelumnya, hanya tambahkan style untuk sorting link */
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
    transition: none;
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
    transition: none;
    position: relative;
    border-radius: 12px;
    margin: 4px 0;
}

.menu-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    transform: none;
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
    transition: none;
}

.menu-arrow.rotate {
    transform: none;
    opacity: 1;
}

/* Submenu */
.submenu {
    max-height: 0;
    overflow: hidden;
    transition: none;
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
    transition: none;
    position: relative;
    font-size: 14px;
}

.submenu-link:hover {
    background: rgba(255, 215, 0, 0.1);
    color: var(--secondary);
    padding-left: 15px;
}

.submenu-link.active {
    background: rgba(255, 215, 0, 0.1);
    color: var(--secondary);
    font-weight: 600;
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
    flex-wrap: wrap;
    gap: 15px;
    border: 1px solid rgba(255, 255, 255, 0.6);
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
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(5px);
    border-radius: 24px;
    overflow: hidden;
    box-shadow: var(--premium-shadow);
    margin-bottom: 30px;
    overflow-x: auto;
    max-width: 100%;
    border: 1px solid rgba(255, 255, 255, 0.8);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
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
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.data-table tbody tr:hover {
    background: #eef5ff;
    transform: translateY(-3px);
    box-shadow: 0 12px 24px rgba(10, 36, 99, 0.2), 0 0 0 1px rgba(76, 138, 255, 0.35);
    z-index: 2;
}

/* Prevent first row hover from overlapping the yellow header border */
.data-table tbody tr:first-child:hover {
    transform: translateY(0);
}

.data-table td {
    padding: 8px;
    vertical-align: middle;
    font-size: 12px;
}

.name-cell {
    font-weight: 600;
    color: var(--dark);
}

.location-cell {
    color: var(--gray);
    font-size: 14px;
}

.capacity-cell {
    text-align: center;
    font-weight: 600;
    color: var(--primary);
    background: #f0f7ff;
    border-radius: 8px;
    padding: 8px 12px;
    min-width: 80px;
}

.facilities-cell {
    color: var(--gray);
    font-size: 14px;
    max-width: 200px;
    word-wrap: break-word;
}

.status-cell {
    text-align: center;
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
        transition: none;
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
        font-size: 20px;
    }

    .page-title i {
        font-size: 24px;
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



/* Sidebar interaction override: disable animations */
.sidebar,
.sidebar *,
.menu-link,
.menu-arrow,
.submenu,
.submenu-link,
.menu-overlay {
    transition: none !important;
    animation: none !important;
}

.menu-link:hover,
.submenu-link:hover,
.menu-arrow.rotate {
    transform: none !important;
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
</style>
</head>
<body>


<!-- Mobile Menu Components -->
<div class="menu-overlay"></div>
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Konfirmasi Hapus Venue</h3>
        </div>
        <div class="modal-body">
            <p>Apakah Anda yakin ingin menghapus venue <strong>"<span id="deleteVenueName"></span>"</strong>?</p>
            <p style="color: var(--danger); font-weight: 600; margin-top: 10px;">
                <i class="fas fa-exclamation-circle"></i> Data yang dihapus tidak dapat dikembalikan!
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
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
                    foreach($item['items'] as $subKey => $subItem) {
                        if($current_page === $subItem['url']) {
                            $isActive = true;
                            $isSubmenuOpen = true;
                            break;
                        }
                    }
                } else {
                    // Cek jika halaman saat ini adalah venue.php atau halaman venue lainnya
                    if ($current_page === $item['url'] || 
                        ($key === 'venue' && ($current_page === 'venue.php' || $current_page === 'venue_create.php' || $current_page === 'venue_edit.php' || $current_page === 'venue_view.php'))) {
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
                    <?php foreach ($item['items'] as $subKey => $subItem): ?>
                    <div class="submenu-item">
                        <a href="<?php echo $subItem['url']; ?>" 
                           class="submenu-link <?php echo ($current_page === $subItem['url']) ? 'active' : ''; ?>">
                           <?php echo $subItem['name']; ?>
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
                <h1>Venue Management 🏟️</h1>
                <p>Kelola data venue dengan mudah dan cepat</p>
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
                <i class="fas fa-map-marker-alt"></i>
                <span>Daftar Venue</span>
            </div>
            
            <form method="GET" action="" class="search-bar" id="searchForm">
                <input type="text" name="search" placeholder="Cari venue (nama, lokasi, fasilitas)..." 
                       value="<?php echo htmlspecialchars($search ?? ''); ?>">
                <button type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </form>
            
            <div class="action-buttons">
                <a href="venue_create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Tambah Venue
                </a>
                <button class="btn btn-success" onclick="exportVenues()">
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

       <!-- VENUE TABLE -->
<div class="table-container">
    <table class="data-table" id="venuesTable">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Venue</th>
                <th>Lokasi</th>
                <th>Kapasitas</th>
                <th>Fasilitas</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Action</th>
            </tr>
                </thead>
                <tbody>
                    <?php if (!empty($venues) && count($venues) > 0): ?>
                        <?php $no = $offset + 1; ?>
                        <?php foreach($venues as $venue): ?>
                        <tr>
                            <td class="capacity-cell"><?php echo $no++; ?></td>
                            <td class="name-cell">
                                <strong><?php echo htmlspecialchars($venue['name'] ?? ''); ?></strong>
                            </td>
                            <td class="location-cell">
                                <?php echo htmlspecialchars($venue['location'] ?? ''); ?>
                            </td>
                            <td class="capacity-cell">
                                <span class="badge badge-primary"><?php echo number_format($venue['capacity']); ?> orang</span>
                            </td>
                            <td class="facilities-cell">
                                <?php echo !empty($venue['facilities']) ? htmlspecialchars($venue['facilities']) : '-'; ?>
                            </td>
                            <td class="status-cell">
                                <?php if ($venue['is_active']): ?>
                                    <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Non-Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="date-cell">
                                <?php echo date('d M Y', strtotime($venue['created_at'])); ?>
                            </td>
                            <td class="date-cell">
                                <?php echo date('d M Y', strtotime($venue['updated_at'])); ?>
                            </td>
                            <td class="action-cell">
                                <div class="action-buttons">
                                    <a href="venue_view.php?id=<?php echo $venue['id']; ?>" 
                                       class="action-btn btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="venue_edit.php?id=<?php echo $venue['id']; ?>" 
                                       class="action-btn btn-edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="action-btn btn-delete" 
                                            data-venue-id="<?php echo (int) $venue['id']; ?>"
                                            data-venue-name="<?php echo htmlspecialchars($venue['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px;">
                                <div class="empty-state" style="box-shadow: none; padding: 0;">
                                    <div class="empty-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <h3>Belum Ada Data Venue</h3>
                                    <p>Mulai dengan menambahkan venue pertama Anda menggunakan tombol "Add Venue" di atas.</p>
                                    <a href="venue_create.php" class="btn btn-primary" style="margin-top: 20px;">
                                        <i class="fas fa-plus"></i>
                                        Tambah Venue Pertama
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
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page-1])); ?>" 
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
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                   <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page+1])); ?>" 
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
let currentVenueId = null;

document.addEventListener('DOMContentLoaded', function() {
    const deleteVenueName = document.getElementById('deleteVenueName');
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    document.querySelectorAll('.btn-delete[data-venue-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentVenueId = this.getAttribute('data-venue-id');
            if (deleteVenueName) {
                deleteVenueName.textContent = this.getAttribute('data-venue-name') || '-';
            }
            if (deleteModal) {
                deleteModal.style.display = 'flex';
            }
        });
    });

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

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (currentVenueId) {
                deleteVenue(currentVenueId);
            }
        });
    }
});

function closeDeleteModal() {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.style.display = 'none';
    }
    currentVenueId = null;
}

const deleteModalElement = document.getElementById('deleteModal');
if (deleteModalElement) {
    deleteModalElement.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
}

function deleteVenue(venueId) {
    fetch(`venue_delete.php?id=${venueId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            toastr.success('Venue berhasil dihapus!');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            toastr.error('Error: ' + data.message);
            closeDeleteModal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toastr.error('Terjadi kesalahan saat menghapus venue.');
        closeDeleteModal();
    });
}

function exportVenues() {
    window.location.href = 'venue_export.php' + (window.location.search ? window.location.search + '&export=excel' : '?export=excel');
}
</script>
</body>
</html>

