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

if (!function_exists('adminHasColumn')) {
    function adminHasColumn(PDO $conn, $tableName, $columnName) {
        try {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tableName);
            if ($safeTable === '') {
                return false;
            }
            $quotedColumn = $conn->quote((string) $columnName);
            $stmt = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quotedColumn}");
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('adminHasTable')) {
    function adminHasTable(PDO $conn, $tableName) {
        try {
            $quotedTable = $conn->quote((string) $tableName);
            $stmt = $conn->query("SHOW TABLES LIKE {$quotedTable}");
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

$challenge_has_event_id = adminHasColumn($conn, 'challenges', 'event_id');
$events_table_exists = adminHasTable($conn, 'events');
$can_join_event_name = $challenge_has_event_id && $events_table_exists;


// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';


// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$event_filter_options = [];

if ($events_table_exists) {
    try {
        $events_stmt = $conn->prepare("SELECT id, name FROM events WHERE name IS NOT NULL AND name <> '' ORDER BY created_at DESC, name ASC");
        $events_stmt->execute();
        $event_filter_options = $events_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $event_filter_options = [];
    }
}

// Query untuk mengambil data challenges dengan join ke teams
$event_select = $can_join_event_name ? "e.name as event_name," : "NULL as event_name,";
$event_join = $can_join_event_name ? "LEFT JOIN events e ON c.event_id = e.id" : "";

$base_query = "SELECT c.*,
              {$event_select}
              t1.name as challenger_name, t1.logo as challenger_logo, t1.sport_type as challenger_sport,
              t2.name as opponent_name, t2.logo as opponent_logo,
              v.name as venue_name, v.location as venue_location
              FROM challenges c
              {$event_join}
              LEFT JOIN teams t1 ON c.challenger_id = t1.id
              LEFT JOIN teams t2 ON c.opponent_id = t2.id
              LEFT JOIN venues v ON c.venue_id = v.id
              WHERE 1=1";

$count_query = "SELECT COUNT(*) as total 
                FROM challenges c
                WHERE 1=1";
$base_params = [];
$count_params = [];

// Handle search condition
if (!empty($search)) {
    $search_term = "%{$search}%";
    if ($can_join_event_name) {
        $base_query .= " AND (c.challenge_code LIKE ? OR c.status LIKE ? OR c.sport_type LIKE ? OR e.name LIKE ? OR t1.name LIKE ? OR t2.name LIKE ?)";
        $count_query .= " AND (c.challenge_code LIKE ? OR c.status LIKE ? OR c.sport_type LIKE ? OR
                         EXISTS (SELECT 1 FROM events e2 WHERE e2.id = c.event_id AND e2.name LIKE ?) OR
                         EXISTS (SELECT 1 FROM teams t WHERE t.id = c.challenger_id AND t.name LIKE ?) OR
                         EXISTS (SELECT 1 FROM teams t WHERE t.id = c.opponent_id AND t.name LIKE ?))";
        $base_params = array_merge($base_params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
        $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
    } else {
        $base_query .= " AND (c.challenge_code LIKE ? OR c.status LIKE ? OR c.sport_type LIKE ? OR t1.name LIKE ? OR t2.name LIKE ?)";
        $count_query .= " AND (c.challenge_code LIKE ? OR c.status LIKE ? OR c.sport_type LIKE ? OR
                         EXISTS (SELECT 1 FROM teams t WHERE t.id = c.challenger_id AND t.name LIKE ?) OR
                         EXISTS (SELECT 1 FROM teams t WHERE t.id = c.opponent_id AND t.name LIKE ?))";
        $base_params = array_merge($base_params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
        $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    }
}

if ($can_join_event_name && $selected_event_id > 0) {
    $base_query .= " AND c.event_id = ?";
    $count_query .= " AND c.event_id = ?";
    $base_params[] = $selected_event_id;
    $count_params[] = $selected_event_id;
}

$base_query .= " ORDER BY c.challenge_date DESC, c.created_at DESC";

// Get total data
$total_data = 0;
$total_pages = 1;
$challenges = [];
$error = '';

try {
    // Count total records
    $stmt = $conn->prepare($count_query);
    $stmt->execute($count_params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_data = $result['total'];
    
    $total_pages = ceil($total_data / $limit);
    
    // Get data with pagination
    $query = $base_query . " LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);
    $params = array_merge($base_params, [$limit, $offset]);
    $stmt->execute($params);
    
    $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
    error_log("Challenge Query Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Challenge Management</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
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

.search-toolbar {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 620px;
    max-width: 100%;
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

.event-filter-select {
    width: 220px;
    padding: 15px 14px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 14px;
    background: #f8f9fa;
    color: var(--dark);
}

.event-filter-select:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
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
    border: 1px solid rgba(255, 255, 255, 0.8);
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

/* Status Badge */
.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
    text-align: center;
    min-width: 80px;
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

/* Team Logo */
.team-logo-small {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #e0e0e0;
    vertical-align: middle;
    margin-right: 8px;
}

.challenger-stack {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}

.challenger-stack .team-logo-small {
    margin-right: 0;
}

.challenger-name {
    font-weight: 600;
    line-height: 1.2;
    text-align: center;
    max-width: 90px;
    word-break: break-word;
}

.team-logo-fallback {
    background: #f0f0f0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.event-badge {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-width: 92px;
    min-height: 34px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    line-height: 1.15;
    text-align: center;
}

.event-badge-primary {
    background: #f0f7ff;
    color: var(--primary);
}

.event-badge-muted {
    background: #f0f0f0;
    color: #666;
}

/* Score Badge */
.score-badge {
    background: var(--primary);
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 14px;
    display: inline-block;
}

/* Action Buttons */
.action-cell {
    min-width: 180px;
}

.action-buttons-inline {
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

.btn-result {
    background: rgba(249, 168, 38, 0.1);
    color: var(--warning);
}

.btn-result:hover {
    background: var(--warning);
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

/* Menu Toggle Button */


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

    .search-toolbar {
        width: 100%;
        max-width: 100%;
        flex-direction: column;
        align-items: stretch;
    }

    .event-filter-select {
        width: 100%;
    }

    .action-buttons {
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
        min-width: 1200px;
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
                <h1>Challenge Management 🏆</h1>
                <p>Kelola tantangan antar team dengan mudah</p>
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
                <span>Daftar Challenge</span>
            </div>
            
            <form method="GET" action="" class="search-toolbar" id="searchForm">
                <div class="search-bar">
                    <input type="text" name="search" placeholder="Cari challenge (kode, status, nama team)..."
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <select name="event_id" id="eventFilter" class="event-filter-select">
                    <option value="">Semua Events</option>
                    <?php foreach ($event_filter_options as $event_option): ?>
                        <option value="<?php echo (int)($event_option['id'] ?? 0); ?>" <?php echo $selected_event_id === (int)($event_option['id'] ?? 0) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($event_option['name'] ?? ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <div class="action-buttons">
                <a href="challenge_create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Tambah Challenge
                </a>
                <button class="btn btn-success" onclick="exportChallenges()">
                    <i class="fas fa-download"></i>
                    Export Excel
                </button>
            </div>
        </div>

        <?php if (isset($error) && !empty($error)): ?>
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

        <div class="alert alert-danger" id="deleteErrorAlert" style="display: none;">
            <i class="fas fa-exclamation-circle"></i>
            <span id="deleteErrorText"></span>
        </div>

        <!-- CHALLENGE TABLE -->
        <div class="table-container">
            <table class="data-table" id="challengesTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Status</th>
                        <th>Challenger</th>
                        <th>vs</th>
                        <th>Opponent</th>
                        <th>Venue</th>
                        <th>Date & Time</th>
                        <th>Expired</th>
                        <th>Events</th>
                        <th>Kategori</th>
                        <th>Match Status</th>
                        <th>Score</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($challenges) && count($challenges) > 0): ?>
                        <?php $no = $offset + 1; ?>
                        <?php foreach($challenges as $challenge): ?>
                        <tr>
                            <td class="count-cell"><?php echo $no++; ?></td>
                            <td class="code-cell">
                                <strong><?php echo htmlspecialchars($challenge['challenge_code'] ?? ''); ?></strong>
                            </td>
                            <td class="status-cell">
                                <?php 
                                $status_class = 'status-' . strtolower($challenge['status']);
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($challenge['status'] ?? ''); ?>
                                </span>
                            </td>
                            <td class="team-cell challenger-cell">
                                <div class="challenger-stack">
                                    <?php if (!empty($challenge['challenger_logo'])): ?>
                                        <img src="../images/teams/<?php echo htmlspecialchars($challenge['challenger_logo'] ?? ''); ?>" 
                                             alt="<?php echo htmlspecialchars($challenge['challenger_name'] ?? ''); ?>" 
                                             class="team-logo-small">
                                    <?php else: ?>
                                        <div class="team-logo-small team-logo-fallback">
                                            <i class="fas fa-shield-alt" style="color: #999; font-size: 18px;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="challenger-name">
                                        <?php echo nl2br(htmlspecialchars(preg_replace('/\s+/', "\n", trim($challenge['challenger_name'] ?? '-')))); ?>
                                    </div>
                                </div>
                            </td>
                            <td class="vs-cell" style="text-align: center; font-weight: bold; color: var(--primary);">
                                VS
                            </td>
                            <td class="team-cell opponent-cell">
                                <div class="challenger-stack">
                                    <?php if (!empty($challenge['opponent_logo'])): ?>
                                        <img src="../images/teams/<?php echo htmlspecialchars($challenge['opponent_logo'] ?? ''); ?>" 
                                             alt="<?php echo htmlspecialchars($challenge['opponent_name'] ?? ''); ?>" 
                                             class="team-logo-small">
                                    <?php else: ?>
                                        <div class="team-logo-small team-logo-fallback">
                                            <i class="fas fa-shield-alt" style="color: #999; font-size: 18px;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="challenger-name">
                                        <?php echo nl2br(htmlspecialchars(preg_replace('/\s+/', "\n", trim($challenge['opponent_name'] ?? 'TBD')))); ?>
                                    </div>
                                </div>
                            </td>
                            <td class="venue-cell">
                                <?php echo !empty($challenge['venue_name']) ? htmlspecialchars($challenge['venue_name']) : '-'; ?>
                            </td>
                            <td class="datetime-cell">
                                <?php echo date('d M Y, H:i', strtotime($challenge['challenge_date'])); ?>
                            </td>
                            <td class="expired-cell">
                                <?php echo date('d M Y, H:i', strtotime($challenge['expiry_date'])); ?>
                            </td>
                            <td class="sport-cell">
                                <?php
                                $active_event_name = trim((string)($challenge['event_name'] ?? ''));
                                ?>
                                <span class="event-badge <?php echo $active_event_name !== '' ? 'event-badge-primary' : 'event-badge-muted'; ?>">
                                    <span><?php echo $active_event_name !== '' ? htmlspecialchars($active_event_name) : '-'; ?></span>
                                </span>
                            </td>
                            <td class="sport-cell">
                                <?php
                                $event_value = !empty($challenge['sport_type'])
                                    ? (string)($challenge['sport_type'] ?? '')
                                    : (string)($challenge['challenger_sport'] ?? '-');
                                $event_value = trim($event_value);
                                $event_words = preg_split('/\s+/', $event_value, -1, PREG_SPLIT_NO_EMPTY);
                                $event_line_1 = $event_words[0] ?? '-';
                                $event_line_2 = count($event_words) > 1 ? implode(' ', array_slice($event_words, 1)) : '&nbsp;';
                                $event_class = !empty($challenge['sport_type']) ? 'event-badge-primary' : 'event-badge-muted';
                                ?>
                                <span class="event-badge <?php echo $event_class; ?>">
                                    <span><?php echo htmlspecialchars($event_line_1); ?></span>
                                    <span><?php echo $event_line_2 === '&nbsp;' ? '&nbsp;' : htmlspecialchars($event_line_2); ?></span>
                                </span>
                            </td>
                            <td class="match-cell">
                                <?php if (!empty($challenge['match_status'])): ?>
                                    <span style="padding: 4px 8px; background: #fff3cd; color: #856404; border-radius: 12px; font-size: 11px;">
                                        <?php echo htmlspecialchars($challenge['match_status'] ?? ''); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="padding: 4px 8px; background: #f8f9fa; color: #6c757d; border-radius: 12px; font-size: 11px;">
                                        Belum Mulai
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="score-cell">
                                <?php if ($challenge['challenger_score'] !== null && $challenge['opponent_score'] !== null): ?>
                                    <span class="score-badge">
                                        <?php echo $challenge['challenger_score']; ?> - <?php echo $challenge['opponent_score']; ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-cell">
                                <div class="action-buttons-inline">
                                    <!-- TOMBOL VIEW SELALU TAMPIL -->
                                    <a href="challenge_view.php?id=<?php echo $challenge['id']; ?>" 
                                       class="action-btn btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <!-- TOMBOL EDIT SELALU TAMPIL -->
                                    <a href="challenge_edit.php?id=<?php echo $challenge['id']; ?>" 
                                       class="action-btn btn-edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <!-- TOMBOL RESULT/BOLA SELALU TAMPIL -->
                                    <a href="challenge_result.php?id=<?php echo $challenge['id']; ?>" 
                                       class="action-btn btn-result">
                                        <i class="fas fa-futbol"></i>
                                    </a>
                                    <!-- TOMBOL DELETE SELALU TAMPIL -->
                                    <button class="action-btn btn-delete" 
                                            data-challenge-id="<?php echo (int) $challenge['id']; ?>"
                                            data-challenge-code="<?php echo htmlspecialchars($challenge['challenge_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- colspan="14" untuk 14 kolom -->
                        <tr>
                            <td colspan="14" style="text-align: center; padding: 40px;">
                                <div class="empty-state" style="box-shadow: none; padding: 0;">
                                    <div class="empty-icon">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                    <h3>Belum Ada Challenge</h3>
                                    <p>Mulai dengan membuat challenge pertama menggunakan tombol "Buat Challenge" di atas.</p>
                                    <a href="challenge_create.php" class="btn btn-primary" style="margin-top: 20px;">
                                        <i class="fas fa-plus"></i>
                                        Buat Challenge Pertama
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
                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&event_id=<?php echo (int)$selected_event_id; ?>" 
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
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&event_id=<?php echo (int)$selected_event_id; ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                   <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&event_id=<?php echo (int)$selected_event_id; ?>" 
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
let currentChallengeId = null;

document.addEventListener('DOMContentLoaded', function() {
    const eventFilter = document.getElementById('eventFilter');
    const searchForm = document.getElementById('searchForm');
    if (eventFilter && searchForm) {
        eventFilter.addEventListener('change', function () {
            searchForm.submit();
        });
    }

    const deleteChallengeCode = document.getElementById('deleteChallengeCode');
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    document.querySelectorAll('.btn-delete[data-challenge-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentChallengeId = this.getAttribute('data-challenge-id');
            if (deleteChallengeCode) {
                deleteChallengeCode.textContent = this.getAttribute('data-challenge-code') || '-';
            }
            if (deleteModal) {
                deleteModal.style.display = 'flex';
            }
        });
    });

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (currentChallengeId) {
                deleteChallenge(currentChallengeId);
            }
        });
    }
});

function closeDeleteModal() {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.style.display = 'none';
    }
    currentChallengeId = null;
}

const deleteModalElement = document.getElementById('deleteModal');
if (deleteModalElement) {
    deleteModalElement.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
}

function deleteChallenge(challengeId) {
    const deleteErrorAlert = document.getElementById('deleteErrorAlert');
    const deleteErrorText = document.getElementById('deleteErrorText');

    const clearDeleteError = () => {
        if (deleteErrorAlert) {
            deleteErrorAlert.style.display = 'none';
        }
        if (deleteErrorText) {
            deleteErrorText.textContent = '';
        }
    };

    const showDeleteError = (message) => {
        if (!deleteErrorAlert || !deleteErrorText) {
            return;
        }
        deleteErrorText.textContent = message;
        deleteErrorAlert.style.display = 'flex';
        deleteErrorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    clearDeleteError();
    fetch(`challenge_delete.php?id=${challengeId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            toastr.success('Challenge berhasil dihapus!');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            closeDeleteModal();
            showDeleteError(data.message || 'Gagal menghapus challenge.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        closeDeleteModal();
        showDeleteError('Terjadi kesalahan saat menghapus challenge.');
    });
}

function exportChallenges() {
    window.location.href = 'challenge_export.php' + (window.location.search ? window.location.search + '&export=excel' : '?export=excel');
}
</script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>