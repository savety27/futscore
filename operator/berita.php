<?php
session_start();

// Load database config
$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'operator') {
    header("Location: ../login.php");
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

// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_username = trim((string)($_SESSION['admin_username'] ?? ''));
$admin_email = $_SESSION['admin_email'] ?? '';
$operator_id = (int)($_SESSION['admin_id'] ?? 0);
$current_page = 'berita';
$operator_event_name = 'Event Operator';
$operator_event_image = '';
$operator_event_is_active = true;

if ($operator_id > 0) {
    try {
        $stmtOperator = $conn->prepare("
            SELECT e.name AS event_name, e.image AS event_image,
                   COALESCE(e.is_active, 1) AS event_is_active
            FROM admin_users au
            LEFT JOIN events e ON e.id = au.event_id
            WHERE au.id = ?
            LIMIT 1
        ");
        $stmtOperator->execute([$operator_id]);
        $operator_row = $stmtOperator->fetch(PDO::FETCH_ASSOC);
        $operator_event_name = trim((string)($operator_row['event_name'] ?? '')) !== '' ? (string)$operator_row['event_name'] : 'Event Operator';
        $operator_event_image = trim((string)($operator_row['event_image'] ?? ''));
        $operator_event_is_active = ((int)($operator_row['event_is_active'] ?? 1) === 1);
    } catch (PDOException $e) {
        // keep defaults
    }
}

$operator_read_only = !$operator_event_is_active;


// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Operator hanya boleh melihat berita miliknya sendiri.
// Prioritas pakai created_by (kalau kolom tersedia), fallback ke kolom penulis.
$ownership_where_sql = '';
$ownership_params = [];
$berita_has_created_by = adminHasColumn($conn, 'berita', 'created_by');
if ($berita_has_created_by && $operator_id > 0) {
    $ownership_where_sql = " AND created_by = ?";
    $ownership_params[] = $operator_id;
} else {
    $ownership_where_sql = " AND (penulis = ? OR penulis = ?)";
    $ownership_params[] = $admin_name;
    $ownership_params[] = $admin_username;
}

// Query untuk mengambil data berita
$base_query = "SELECT * FROM berita WHERE 1=1" . $ownership_where_sql;
$count_query = "SELECT COUNT(*) as total FROM berita WHERE 1=1" . $ownership_where_sql;
$base_params = $ownership_params;
$count_params = $ownership_params;

// Handle search condition
if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (judul LIKE ? OR konten LIKE ? OR penulis LIKE ? OR tag LIKE ?)";
    $count_query .= " AND (judul LIKE ? OR konten LIKE ? OR penulis LIKE ? OR tag LIKE ?)";
    $base_params = array_merge($base_params, [$search_term, $search_term, $search_term, $search_term]);
    $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term]);
}

// Handle status filter
if (!empty($status_filter)) {
    $base_query .= " AND status = ?";
    $count_query .= " AND status = ?";
    $base_params[] = $status_filter;
    $count_params[] = $status_filter;
}

$base_query .= " ORDER BY created_at DESC";

// Get total data
$total_data = 0;
$total_pages = 1;
$berita = [];

try {
    // Count total records
    $stmt = $conn->prepare($count_query);
    $stmt->execute($count_params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_data = (int)($result['total'] ?? 0);
    
    $total_pages = max(1, (int)ceil($total_data / $limit));
    
    // Get data with pagination
    $query = $base_query . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $paramIndex = 1;
    foreach ($base_params as $param) {
        $stmt->bindValue($paramIndex++, $param);
    }
    $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $berita = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($berita as &$row) {
        $statusRaw = strtolower(trim((string)($row['status'] ?? '')));
        $viewsCount = (int)($row['views'] ?? 0);
        $isAllowedStatus = in_array($statusRaw, ['draft', 'archived'], true);
        $hasRelatedData = $viewsCount > 0;

        $row['can_delete'] = $isAllowedStatus && !$hasRelatedData;
        if (!$isAllowedStatus) {
            $row['delete_block_reason'] = 'Delete hanya untuk status draft/archived.';
        } elseif ($hasRelatedData) {
            $row['delete_block_reason'] = 'Tidak bisa dihapus karena sudah memiliki data turunan (views/interaksi).';
        } else {
            $row['delete_block_reason'] = 'Delete';
        }
    }
    unset($row);
    
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}

// Fungsi untuk membuat excerpt dari konten
function createExcerpt($text, $maxLength = 100) {
    $text = strip_tags($text);
    if (strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength);
        $text = substr($text, 0, strrpos($text, ' ')) . '...';
    }
    return $text;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Berita</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../pelatih/css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../pelatih/css/style.css'); ?>">
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

.page-header .action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
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
    background: #6b7280;
    color: white;
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
}

.btn-secondary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
}

/* Filter Controls */
.filter-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(8px);
    padding: 20px;
    border-radius: 20px;
    box-shadow: var(--premium-shadow);
    margin-bottom: 20px;
    flex-wrap: wrap;
    border: 1px solid rgba(255, 255, 255, 0.5);
}

.filter-actions {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-label {
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.filter-select {
    padding: 10px 15px;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    background: #f8f9fa;
    color: var(--dark);
    font-size: 14px;
    cursor: pointer;
    transition: var(--transition);
    min-width: 150px;
}

.filter-select:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
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
    min-width: 1320px;
    table-layout: auto;
}

.data-table thead {
    background: linear-gradient(135deg, var(--primary), #1a365d);
    color: white;
}

.data-table th {
    padding: 14px 10px;
    text-align: center;
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
    transform: none;
    box-shadow: inset 0 0 0 1px rgba(76, 138, 255, 0.18);
    z-index: 1;
}

/* Prevent first row hover from overlapping the yellow header border */
.data-table tbody tr:first-child:hover {
    transform: translateY(0);
}

.data-table td {
    padding: 12px 10px;
    vertical-align: middle;
    font-size: 12px;
    text-align: center;
}

/* Table Cell Styles */
.no-cell {
    text-align: center;
    font-weight: 600;
    color: var(--primary);
    width: 50px;
}

.image-cell {
    width: 130px;
}

.news-image {
    width: 100px;
    height: 80px;
    border-radius: 8px;
    object-fit: cover;
    border: 2px solid #e0e0e0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: var(--transition);
}

.news-image:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.news-image-placeholder {
    width: 100px;
    height: 80px;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    background: #f0f4f8;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.judul-cell {
    font-weight: 600;
    color: var(--dark);
    min-width: 240px;
    width: 240px;
}

.excerpt-cell {
    color: var(--gray);
    font-size: 14px;
    line-height: 1.4;
    min-width: 250px;
    max-width: 250px;
    width: 250px;
}

.penulis-cell {
    color: var(--dark);
    font-weight: 500;
    min-width: 130px;
    width: 130px;
}

.tag-cell {
    min-width: 140px;
    width: 140px;
}

.status-cell {
    text-align: center;
    width: 120px;
}

.date-cell {
    color: var(--gray);
    font-size: 14px;
    width: 150px;
    text-align: center;
}

.views-cell {
    text-align: center;
    font-weight: 600;
    color: var(--primary);
    width: 95px;
}

.action-cell {
    width: 132px;
    min-width: 132px;
    text-align: center;
}

.action-cell .action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
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

.btn-delete-disabled,
.btn-delete-disabled:hover {
    background: rgba(148, 163, 184, 0.2);
    color: #94a3b8;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
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

/* Status Badge */
.status-published {
    background: rgba(46, 125, 50, 0.15);
    color: var(--success);
    border: 1px solid rgba(46, 125, 50, 0.3);
}

.status-draft {
    background: rgba(108, 117, 125, 0.15);
    color: var(--gray);
    border: 1px solid rgba(108, 117, 125, 0.3);
}

.status-archived {
    background: rgba(211, 47, 47, 0.15);
    color: var(--danger);
    border: 1px solid rgba(211, 47, 47, 0.3);
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

/* Tag Styles */
.tag-item {
    display: inline-block;
    background: #e9ecef;
    color: #495057;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    margin: 2px;
}

.tag-item:hover {
    background: #dee2e6;
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

    .search-bar {
        width: 100%;
        max-width: 100%;
        order: -1;
    }
    
    .page-header .action-buttons {
        width: 100%;
        flex-direction: column;
        gap: 10px;
        order: 3;
    }

    .page-header .action-buttons .btn {
        width: 100%;
        justify-content: center;
    }
    
    /* Filter Controls vertically stacked */
    .filter-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }

    .filter-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .filter-group {
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .filter-select {
        width: 100%;
    }

    .filter-actions .btn {
        width: 100%;
        justify-content: center;
    }
    
    /* Table Responsive */
    .table-container {
        border-radius: 12px;
    }

    .action-cell {
        min-width: 126px;
    }

    .action-cell .action-buttons {
        gap: 6px;
        justify-content: center;
    }

    .action-btn {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        font-size: 16px;
    }
    
    /* Statistics Responsive */
    .statistics-container {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px !important;
    }

    .statistics-container > div {
        min-width: 0 !important;
        padding: 12px !important;
    }

    .statistics-container > div > div:first-child {
        font-size: 24px !important;
    }

    .statistics-container > div > div:last-child {
        font-size: 12px !important;
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
<div class="menu-overlay"></div>
<button class="mobile-menu-toggle" aria-label="Toggle menu">
    <i class="fas fa-bars"></i>
</button>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Konfirmasi Hapus Berita</h3>
        </div>
        <div class="modal-body">
            <p>Apakah Anda yakin ingin menghapus berita <strong>"<span id="deleteBeritaTitle"></span>"</strong>?</p>
            <p style="color: var(--danger); font-weight: 600; margin-top: 10px;">
                <i class="fas fa-exclamation-circle"></i> Data yang dihapus tidak dapat dikembalikan!
            </p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" type="button" onclick="closeDeleteModal()">Batal</button>
            <button class="btn btn-danger" type="button" id="confirmDeleteBtn">Hapus</button>
        </div>
    </div>
</div>

<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="greeting">
                <h1> Berita Management 📰</h1>
                <p>Kelola berita dan artikel dengan mudah</p>
            </div>
            
            <div class="user-actions">
                <a href="../admin/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Keluar
                </a>
            </div>
        </div>

        <!-- STATISTIK -->
        <div style="background: white; padding: 20px; border-radius: 15px; box-shadow: var(--card-shadow); margin-bottom: 30px;">
            <h3 style="color: var(--primary); margin-bottom: 15px;">Statistik Berita</h3>
            <div class="statistics-container" style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px; text-align: center; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                    <div style="font-size: 32px; color: var(--primary); font-weight: 700;"><?php echo $total_data; ?></div>
                    <div style="color: var(--gray); font-size: 14px;">Total Berita</div>
                </div>
                
                <?php 
                try {
                    // Count published
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM berita WHERE status = 'published'" . $ownership_where_sql);
                    $stmt->execute($ownership_params);
                    $published = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    // Count draft
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM berita WHERE status = 'draft'" . $ownership_where_sql);
                    $stmt->execute($ownership_params);
                    $draft = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    // Count archived
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM berita WHERE status = 'archived'" . $ownership_where_sql);
                    $stmt->execute($ownership_params);
                    $archived = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    // Total views
                    $stmt = $conn->prepare("SELECT COALESCE(SUM(views), 0) as total_views FROM berita WHERE 1=1" . $ownership_where_sql);
                    $stmt->execute($ownership_params);
                    $total_views = $stmt->fetch(PDO::FETCH_ASSOC)['total_views'] ?? 0;
                } catch (PDOException $e) {
                    $published = $draft = $archived = $total_views = 0;
                }
                ?>
                
                <div style="flex: 1; min-width: 200px; text-align: center; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                    <div style="font-size: 32px; color: var(--success); font-weight: 700;"><?php echo $published; ?></div>
                    <div style="color: var(--gray); font-size: 14px;">Published</div>
                </div>
                
                <div style="flex: 1; min-width: 200px; text-align: center; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                    <div style="font-size: 32px; color: var(--gray); font-weight: 700;"><?php echo $draft; ?></div>
                    <div style="color: var(--gray); font-size: 14px;">Draft</div>
                </div>
                
                <div style="flex: 1; min-width: 200px; text-align: center; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                    <div style="font-size: 32px; color: var(--accent); font-weight: 700;"><?php echo $total_views; ?></div>
                    <div style="color: var(--gray); font-size: 14px;">Total Views</div>
                </div>
            </div>
        </div>

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-newspaper"></i>
                <span>Daftar Berita</span>
            </div>

            <div class="action-buttons">
                <?php if (!$operator_read_only): ?>
                    <a href="berita_create.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Tambah Berita
                    </a>
                <?php endif; ?>
                <?php if (!$operator_read_only): ?>
                    <button class="btn btn-success" onclick="exportBerita()">
                        <i class="fas fa-download"></i>
                        Export Excel
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($operator_read_only): ?>
            <div class="alert alert-danger">
                <i class="fas fa-lock"></i>
                <span>Event Anda sedang non-aktif. Mode operator hanya lihat data.</span>
            </div>
        <?php endif; ?>

        <!-- FILTER CONTROLS -->
        <div class="filter-controls">
            <form method="GET" action="" class="search-bar" id="searchForm" style="margin-bottom: 0;">
                <input type="text" name="search" placeholder="Cari berita (judul, konten, penulis)..." 
                       value="<?php echo htmlspecialchars($search ?? ''); ?>">
                <button type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </form>

            <div class="filter-actions">
                <div class="filter-group">
                    <span class="filter-label">Filter Status:</span>
                    <select class="filter-select" onchange="window.location.href='?status=' + this.value + '&search=<?php echo urlencode($search); ?>'">
                        <option value="">Semua Status</option>
                        <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <a href="berita.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>
                        Reset Filter
                    </a>
                </div>
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

        <!-- BERITA TABLE -->
        <div class="table-container">
            <table class="data-table" id="beritaTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Gambar</th>
                        <th>Judul Berita</th>
                        <th>Ringkasan</th>
                        <th>Penulis</th>
                        <th>Tag</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($berita) && count($berita) > 0): ?>
                        <?php $no = $offset + 1; ?>
                        <?php foreach($berita as $b): ?>
                        <tr>
                            <td class="no-cell"><?php echo $no++; ?></td>
                            <td class="image-cell">
                                <?php if (!empty($b['gambar'])): ?>
                                    <img src="../images/berita/<?php echo htmlspecialchars($b['gambar'] ?? ''); ?>" 
                                         alt="Gambar berita" 
                                         onerror="this.onerror=null; this.style.display='none'; this.insertAdjacentHTML('afterend','<div class=&quot;news-image-placeholder&quot;><i class=&quot;fas fa-newspaper&quot;></i></div>');"
                                         class="news-image">
                                <?php else: ?>
                                    <div class="news-image-placeholder">
                                        <i class="fas fa-newspaper"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="judul-cell">
                                <strong><?php echo htmlspecialchars($b['judul'] ?? ''); ?></strong><br>
                                <small style="color: #666; font-size: 11px;">Slug: <?php echo htmlspecialchars($b['slug'] ?? ''); ?></small>
                            </td>
                            <td class="excerpt-cell">
                                <?php echo createExcerpt($b['konten'], 120); ?>
                            </td>
                            <td class="penulis-cell">
                                <?php echo !empty($b['penulis']) ? htmlspecialchars($b['penulis'] ?? '') : '-'; ?>
                            </td>
                            <td class="tag-cell">
                                <?php if (!empty($b['tag'])): ?>
                                    <?php 
                                    $tags = explode(',', $b['tag']);
                                    foreach ($tags as $tag):
                                        $tag = trim($tag);
                                        if (!empty($tag)):
                                    ?>
                                    <span class="tag-item"><?php echo htmlspecialchars($tag ?? ''); ?></span>
                                    <?php endif; endforeach; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="status-cell">
                                <?php if ($b['status'] === 'published'): ?>
                                    <span class="badge status-published">Published</span>
                                <?php elseif ($b['status'] === 'draft'): ?>
                                    <span class="badge status-draft">Draft</span>
                                <?php else: ?>
                                    <span class="badge status-archived">Archived</span>
                                <?php endif; ?>
                            </td>
                            <td class="views-cell">
                                <span class="badge badge-primary"><?php echo $b['views']; ?> views</span>
                            </td>
                            <td class="date-cell">
                                <?php echo date('d M Y', strtotime($b['created_at'])); ?>
                            </td>
                            <td class="action-cell">
                                <div class="action-buttons">
                                    <?php
                                    $deleteDisabled = $operator_read_only || empty($b['can_delete']);
                                    $deleteTitle = $operator_read_only
                                        ? 'Event non-aktif. Mode hanya lihat data.'
                                        : (string)($b['delete_block_reason'] ?? 'Delete');
                                    ?>
                                    <a href="berita_view.php?id=<?php echo $b['id']; ?>" 
                                       class="action-btn btn-view" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (!$operator_read_only): ?>
                                        <a href="berita_edit.php?id=<?php echo $b['id']; ?>" 
                                           class="action-btn btn-edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="action-btn btn-delete<?php echo $deleteDisabled ? ' btn-delete-disabled' : ''; ?>"
                                                <?php if (!$deleteDisabled): ?>
                                                data-berita-id="<?php echo (int) $b['id']; ?>"
                                                data-berita-title="<?php echo htmlspecialchars($b['judul'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php else: ?>
                                                disabled aria-disabled="true"
                                                <?php endif; ?>
                                                title="<?php echo htmlspecialchars($deleteTitle, ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px;">
                                <div class="empty-state" style="box-shadow: none; padding: 0;">
                                    <div class="empty-icon">
                                        <i class="fas fa-newspaper"></i>
                                    </div>
                                    <h3>Belum Ada Data Berita</h3>
                                    <p>Mulai dengan membuat berita pertama Anda menggunakan tombol "Buat Berita Baru" di atas.</p>
                                    <?php if (!$operator_read_only): ?>
                                        <a href="berita_create.php" class="btn btn-primary" style="margin-top: 20px;">
                                            <i class="fas fa-plus"></i>
                                            Buat Berita Pertama
                                        </a>
                                    <?php endif; ?>
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
                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
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
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                   <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>" 
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

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
let currentBeritaId = null;

document.addEventListener('DOMContentLoaded', function() {
    const deleteBeritaTitle = document.getElementById('deleteBeritaTitle');
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    document.querySelectorAll('.btn-delete[data-berita-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            currentBeritaId = this.getAttribute('data-berita-id');
            const beritaTitle = this.getAttribute('data-berita-title') || '-';
            if (deleteBeritaTitle) {
                deleteBeritaTitle.textContent = beritaTitle;
            }
            if (deleteModal) {
                deleteModal.style.display = 'flex';
            } else {
                const confirmed = confirm(`Hapus berita "${beritaTitle}"?`);
                if (confirmed && currentBeritaId) {
                    deleteBerita(currentBeritaId);
                } else {
                    currentBeritaId = null;
                }
            }
        });
    });

    // Responsive adjustments
    function adjustLayout() {
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            document.querySelector('.main').style.marginLeft = '0';
        } else if (window.innerWidth > 1200) {
            document.querySelector('.main').style.marginLeft = '280px';
        }
    }
    
    adjustLayout();
    window.addEventListener('resize', adjustLayout);

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function() {
            if (currentBeritaId) {
                deleteBerita(currentBeritaId);
            }
        });
    }
});

function closeDeleteModal() {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.style.display = 'none';
    }
    currentBeritaId = null;
}

const deleteModalElement = document.getElementById('deleteModal');
if (deleteModalElement) {
    deleteModalElement.addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });
}

function deleteBerita(beritaId) {
    fetch(`berita_delete.php?id=${beritaId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            toastr.success('Berita berhasil dihapus!');
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
        toastr.error('Terjadi kesalahan saat menghapus berita.');
        closeDeleteModal();
    });
}

function exportBerita() {
    window.location.href = 'berita_export.php' + (window.location.search ? window.location.search + '&export=excel' : '?export=excel');
}
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
