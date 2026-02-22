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


// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_active = isset($_GET['active']) ? trim((string) $_GET['active']) : '';
if (!in_array($filter_active, ['', '1', '0'], true)) {
    $filter_active = '';
}
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Query untuk mengambil data pelatih dengan JOIN ke tabel teams
$base_query = "SELECT au.*, t.name as team_name, t.alias as team_alias 
               FROM admin_users au 
               LEFT JOIN teams t ON au.team_id = t.id 
               WHERE 1=1";
               
$count_query = "SELECT COUNT(*) as total 
                FROM admin_users au 
                LEFT JOIN teams t ON au.team_id = t.id 
                WHERE 1=1";

// Handle search condition
if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (au.username LIKE ? OR au.email LIKE ? OR au.full_name LIKE ? OR au.role LIKE ? OR t.name LIKE ? OR t.alias LIKE ?)";
    $count_query .= " AND (au.username LIKE ? OR au.email LIKE ? OR au.full_name LIKE ? OR au.role LIKE ? OR t.name LIKE ? OR t.alias LIKE ?)";
}

if ($filter_active !== '') {
    $base_query .= " AND au.is_active = ?";
    $count_query .= " AND au.is_active = ?";
}

$base_query .= " ORDER BY au.created_at DESC";

// Get total data
$total_data = 0;
$total_pages = 1;
$pelatih = [];

try {
    $query_params = [];
    if (!empty($search)) {
        $query_params = [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term];
    }
    if ($filter_active !== '') {
        $query_params[] = (int) $filter_active;
    }

    // Count total records
    $stmt = $conn->prepare($count_query);
    $stmt->execute($query_params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_data = $result['total'];
    
    $total_pages = ceil($total_data / $limit);
    
    // Get data with pagination
    $query = $base_query . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $bind_index = 1;
    foreach ($query_params as $param) {
        $stmt->bindValue($bind_index++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue($bind_index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($bind_index, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $pelatih = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Pelatih</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
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

.filter-container {
    margin-bottom: 24px;
}

.pelatih-filter-card {
    padding: 16px;
    border: 1px solid #dbe5f3;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    box-shadow: 0 8px 20px rgba(10, 36, 99, 0.06);
}

.pelatih-filter-form {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) minmax(180px, 0.55fr) auto;
    gap: 12px;
    align-items: center;
}

.pelatih-search-group {
    position: relative;
}

.pelatih-search-group i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #7b8797;
    font-size: 13px;
}

.pelatih-search-input,
.pelatih-filter-select {
    width: 100%;
    height: 42px;
    border: 1px solid #d3dcea;
    border-radius: 10px;
    background: #ffffff;
    color: #1f2937;
    font-size: 14px;
    transition: all 0.2s ease;
}

.pelatih-search-input {
    padding: 0 12px 0 36px;
}

.pelatih-filter-select {
    padding: 0 12px;
}

.pelatih-search-input:focus,
.pelatih-filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.12);
}

.pelatih-filter-actions {
    display: flex;
    gap: 8px;
}

.btn-filter,
.clear-filter-btn {
    height: 42px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
}

.btn-filter {
    background: linear-gradient(135deg, var(--primary), #1a4f9e);
    color: #ffffff;
}

.btn-filter:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(10, 36, 99, 0.22);
}

.clear-filter-btn {
    background: #ffffff;
    border-color: #d3dcea;
    color: #3b4a5f;
}

.clear-filter-btn:hover {
    background: #f2f6fc;
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
    min-width: 1400px;
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

.username-cell {
    font-weight: 600;
    color: var(--dark);
}

.email-cell {
    color: var(--gray);
    font-size: 14px;
}

.name-cell {
    font-weight: 500;
    color: var(--dark);
}

.role-cell {
    text-align: center;
}

.role-badge {
    display: inline-block;
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.role-superadmin {
    background: linear-gradient(135deg, #FF6B6B, #FF8E8E);
    color: white;
}

.role-admin {
    background: linear-gradient(135deg, var(--primary), #4CC9F0);
    color: white;
}

.role-editor, .role-pelatih {
    background: linear-gradient(135deg, var(--warning), #FFD166);
    color: var(--dark);
}

.team-cell {
    text-align: center;
    font-weight: 500;
}

.team-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(76, 201, 240, 0.1);
    color: var(--accent);
    border: 1px solid rgba(76, 201, 240, 0.3);
}

.no-team {
    color: var(--gray);
    font-style: italic;
}

.status-cell {
    text-align: center;
}

.lastlogin-cell {
    color: var(--gray);
    font-size: 14px;
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

    .pelatih-filter-form {
        grid-template-columns: 1fr;
    }

    .pelatih-filter-actions .btn-filter,
    .pelatih-filter-actions .clear-filter-btn {
        width: 100%;
        justify-content: center;
    }

    .page-header .action-buttons {
        width: 100%;
        flex-wrap: wrap;
    }

    .page-header .btn {
        flex: 1;
        justify-content: center;
    }

    /* Keep table action buttons horizontal */
    .action-cell .action-buttons {
        width: auto;
        flex-wrap: nowrap;
    }

    .action-cell .action-btn {
        flex: 0 0 auto;
    }

    /* Table Optimization */
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


<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="greeting">
                <h1> Pelatih Management 👤</h1>
                <p>Kelola data pelatih dan administrator dengan mudah</p>
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
                <i class="fas fa-users-cog"></i>
                <span>Daftar Pelatih</span>
            </div>

            <div class="action-buttons">
                <a href="pelatih_create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Tambah Pelatih
                </a>
                <button class="btn btn-success" onclick="exportPelatih()">
                    <i class="fas fa-download"></i>
                    Export Excel
                </button>
            </div>
        </div>

        <div class="filter-container">
            <div class="pelatih-filter-card">
                <form method="GET" action="" class="pelatih-filter-form" id="searchForm">
                    <input type="hidden" name="page" value="1">
                    <div class="pelatih-search-group">
                        <i class="fas fa-search"></i>
                        <input
                            type="text"
                            name="search"
                            class="pelatih-search-input"
                            placeholder="Cari pelatih (username, email, nama, role, tim)..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>
                    <select name="active" class="pelatih-filter-select">
                        <option value="">Semua Status</option>
                        <option value="1" <?php echo $filter_active === '1' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="0" <?php echo $filter_active === '0' ? 'selected' : ''; ?>>Nonaktif</option>
                    </select>
                    <div class="pelatih-filter-actions">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-filter"></i> Terapkan
                        </button>
                        <?php if ($search !== '' || $filter_active !== ''): ?>
                            <a href="pelatih.php" class="clear-filter-btn">
                                <i class="fas fa-times"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
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

        <!-- PELATIH TABLE -->
        <div class="table-container">
            <table class="data-table" id="pelatihTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Nama Lengkap</th>
                        <th>Role</th>
                        <th>Tim</th>
                        <th>Status</th>
                        <th>Login Terakhir</th>
                        <th>Tanggal Dibuat</th>
                        <th>Terakhir Update</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pelatih) && count($pelatih) > 0): ?>
                        <?php $no = $offset + 1; ?>
                        <?php foreach($pelatih as $p): ?>
                        <tr>
                            <td class="count-cell"><?php echo $no++; ?></td>
                            <td class="username-cell">
                                <strong><?php echo htmlspecialchars($p['username'] ?? ''); ?></strong>
                            </td>
                            <td class="email-cell">
                                <?php echo htmlspecialchars($p['email'] ?? ''); ?>
                            </td>
                            <td class="name-cell">
                                <?php echo !empty($p['full_name']) ? htmlspecialchars($p['full_name']) : '-'; ?>
                            </td>
                            <td class="role-cell">
                                <?php if ($p['role'] === 'superadmin'): ?>
                                    <span class="role-badge role-superadmin">Super Admin</span>
                                <?php else: ?>
                                    <span class="role-badge role-pelatih">Pelatih</span>
                                <?php endif; ?>
                            </td>
                            <td class="team-cell">
                                <?php if (!empty($p['team_name'])): ?>
                                    <span class="team-badge" title="<?php echo htmlspecialchars($p['team_alias'] ?? ''); ?>">
                                        <i class="fas fa-users"></i>
                                        <?php echo htmlspecialchars($p['team_name'] ?? ''); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="no-team">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="status-cell">
                                <?php if ($p['is_active']): ?>
                                    <span class="badge badge-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Non-Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="lastlogin-cell">
                                <?php echo !empty($p['last_login']) ? date('d M Y H:i', strtotime($p['last_login'])) : '-'; ?>
                            </td>
                            <td class="date-cell">
                                <?php echo date('d M Y', strtotime($p['created_at'])); ?>
                            </td>
                            <td class="date-cell">
                                <?php echo date('d M Y', strtotime($p['updated_at'])); ?>
                            </td>
                            <td class="action-cell">
                                <div class="action-buttons">
                                    <a href="pelatih_view.php?id=<?php echo $p['id']; ?>" 
                                       class="action-btn btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="pelatih_edit.php?id=<?php echo $p['id']; ?>" 
                                       class="action-btn btn-edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="action-btn btn-delete" 
                                            data-pelatih-id="<?php echo (int) $p['id']; ?>"
                                            data-pelatih-name="<?php echo htmlspecialchars($p['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 40px;">
                                <div class="empty-state" style="box-shadow: none; padding: 0;">
                                    <div class="empty-icon">
                                        <i class="fas fa-user-slash"></i>
                                    </div>
                                    <h3>Belum Ada Data Pelatih</h3>
                                    <p>Mulai dengan menambahkan pelatih pertama Anda menggunakan tombol "Tambah Pelatih" di atas.</p>
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
                <a href="?<?php echo http_build_query(['page' => $page - 1, 'search' => $search, 'active' => $filter_active]); ?>" 
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
                <a href="?<?php echo http_build_query(['page' => $i, 'search' => $search, 'active' => $filter_active]); ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                   <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(['page' => $page + 1, 'search' => $search, 'active' => $filter_active]); ?>" 
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
let currentPelatihId = null;

document.addEventListener('DOMContentLoaded', function() {
    const deletePelatihName = document.getElementById('deletePelatihName');
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    document.querySelectorAll('.btn-delete[data-pelatih-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentPelatihId = this.getAttribute('data-pelatih-id');
            if (deletePelatihName) {
                deletePelatihName.textContent = this.getAttribute('data-pelatih-name') || '-';
            }
            if (deleteModal) {
                deleteModal.style.display = 'flex';
            }
        });
    });

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (currentPelatihId) {
                deletePelatih(currentPelatihId);
            }
        });
    }
});

function closeDeleteModal() {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.style.display = 'none';
    }
    currentPelatihId = null;
}

const deleteModalElement = document.getElementById('deleteModal');
if (deleteModalElement) {
    deleteModalElement.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
}

function deletePelatih(pelatihId) {
    fetch(`pelatih_delete.php?id=${pelatihId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            toastr.success('Pelatih berhasil dihapus!');
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
        toastr.error('Terjadi kesalahan saat menghapus pelatih.');
        closeDeleteModal();
    });
}

function exportPelatih() {
    window.location.href = 'pelatih_export.php' + (window.location.search ? window.location.search + '&export=excel' : '?export=excel');
}
</script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>
