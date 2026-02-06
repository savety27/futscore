<?php
// DEBUG MODE
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';

// Cek koneksi database
if (!$db || !$db->getConnection()) {
    die("Database connection failed!");
}

// Cek SITE_URL
if (!defined('SITE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $site_url = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    define('SITE_URL', $site_url);
}

// Logic for Search and Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 40;
$offset = ($page - 1) * $limit;

// Database connection
$conn = $db->getConnection();

// Query for Total Records (for pagination)
$count_query = "SELECT COUNT(*) as total FROM team_staff ts WHERE ts.is_active = 1";
if (!empty($search)) {
    $count_query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ?)";
}

$stmt_count = $conn->prepare($count_query);
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt_count->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
if ($count_result) {
    $total_records = $count_result->fetch_assoc()['total'];
} else {
    $total_records = 0;
}
$total_pages = ceil($total_records / $limit);

// Query for Staff Data with Team Info and Counts
$query = "SELECT 
    ts.*, 
    t.name as team_name, 
    t.logo as team_logo,
    t.alias as team_alias,
    (SELECT COUNT(*) FROM staff_certificates sc WHERE sc.staff_id = ts.id) as certificate_count
    FROM team_staff ts 
    LEFT JOIN teams t ON ts.team_id = t.id 
    WHERE ts.is_active = 1";
    
if (!empty($search)) {
    $query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ?)";
}
$query .= " ORDER BY ts.created_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!empty($search)) {
    $stmt->bind_param("ssssii", $search_param, $search_param, $search_param, $search_param, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$staffs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Helper Functions
function calculateStaffAge($birth_date) {
    if (empty($birth_date) || $birth_date == '0000-00-00') return '-';
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $diff = $today->diff($birth);
    
    if ($diff->y == 0 && $diff->m == 0) {
        return 'Baru lahir';
    } elseif ($diff->y == 0) {
        return $diff->m . ' bulan';
    } else {
        return $diff->y . ' tahun';
    }
}

function formatPosition($position) {
    $position_labels = [
        'manager' => 'Manager',
        'headcoach' => 'Head Coach',
        'coach' => 'Coach',
        'goalkeeper_coach' => 'GK Coach',
        'medic' => 'Medic',
        'official' => 'Official',
        'assistant_coach' => 'Asst. Coach',
        'fitness_coach' => 'Fitness Coach',
        'analyst' => 'Analyst',
        'scout' => 'Scout'
    ];
    
    return $position_labels[$position] ?? ucfirst(str_replace('_', ' ', $position ?? ''));
}

// Helper function to check file exists and return correct path
function getFileUrl($filename, $directory, $defaultIcon = 'fa-user') {
    if (empty($filename)) {
        return [
            'url' => null,
            'found' => false,
            'icon' => $defaultIcon
        ];
    }
    
    // Extract just the filename (remove path if exists)
    $basename = basename($filename);
    
    // Check various possible locations
    $locations = [
        $directory . '/' . $basename,
        'uploads/' . $directory . '/' . $basename,
        'images/' . $directory . '/' . $basename,
        '../uploads/' . $directory . '/' . $basename,
        '../images/' . $directory . '/' . $basename,
        'assets/' . $directory . '/' . $basename,
        $filename
    ];
    
    foreach ($locations as $location) {
        // Clean path
        $clean_path = str_replace(['../', './', '//'], '', $location);
        
        if (file_exists($clean_path) && is_file($clean_path)) {
            return [
                'url' => SITE_URL . '/' . $clean_path,
                'found' => true,
                'icon' => $defaultIcon
            ];
        }
    }
    
    // If not found, return placeholder
    return [
        'url' => null,
        'found' => false,
        'icon' => $defaultIcon
    ];
}


// Page Metadata
$pageTitle = "Staff List";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
   <style>
    /* Hero Banner Styles */
.staff-hero {
    background: linear-gradient(135deg, #1a1a1a 0%, #c00 100%);
    padding: 60px 0;
    text-align: center;
    color: #fff;
    margin-bottom: 40px;
}

.staff-hero h1 {
    font-size: 48px;
    font-weight: 800;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 3px;
}

/* CSS Reset and Base for the section */
.staff-list-section {
    padding: 40px 0;
    color: #fff;
}

.search-container {
    margin-bottom: 20px;
    max-width: 400px;
}

.search-wrapper {
    position: relative;
    display: flex;
}

.search-wrapper input {
    width: 100%;
    padding: 12px 15px;
    background: #fff;
    border: none;
    border-radius: 4px;
    color: #333;
    font-size: 14px;
}

.staff-table-container {
    background: #fff;
    border-radius: 8px;
    overflow-x: auto;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    border: 1px solid #333;
}

.staff-table {
    width: 100%;
    border-collapse: collapse;
    color: #333;
    font-size: 13px;
    min-width: 1200px;
}

.staff-table thead tr {
    background: linear-gradient(to right, #000, #c00); /* Dark to Red gradient */
    color: #fff;
}

.staff-table th {
    padding: 12px 10px;
    text-align: left;
    font-weight: 700;
    text-transform: capitalize;
    border-right: 1px solid rgba(255,255,255,0.1);
}

.staff-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.staff-table tbody tr:hover {
    background-color: #f9f9f9;
}

/* Specific alignments and styles from reference */
.col-no { width: 40px; text-align: center; }
.col-photo { width: 80px; text-align: center; }
.col-name { color: #0066cc; font-weight: 500; }
.col-team { display: flex; align-items: center; gap: 8px; }
.team-logo-small { width: 24px; height: 24px; border-radius: 50%; object-fit: contain; background: #eee; }
.col-center { text-align: center; }

/* Staff Photo Styles */
.staff-photo-wrapper {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 50px;
}

.staff-img-sm {
    width: 100%;
    height: 100%;
    border-radius: 4px; /* Changed from 50% to 4px */
    object-fit: cover;
    border: 1px solid #ddd;
}

.photo-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 4px; /* Changed from 50% to 4px */
    background: #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    font-size: 18px;
    border: 1px solid #ddd;
}

/* Team Badge Styles */
.team-badge {
    position: absolute;
    bottom: -5px;
    right: -5px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: white;
    border: 2px solid white;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.team-badge-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.team-badge-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, #48bb78, #38a169);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 9px;
}

/* Staff Info Styles */
.staff-name {
    font-weight: 600;
    color: #0066cc;
    font-size: 14px;
    margin-bottom: 3px;
}

.staff-contact {
    font-size: 11px;
    color: #718096;
}

.staff-contact i {
    margin-right: 5px;
    width: 12px;
}

/* Team Display */
.team-display {
    display: flex;
    align-items: center;
    gap: 8px;
}

.team-logo {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    background: #e2e8f0;
}

.team-info {
    display: flex;
    flex-direction: column;
}

.team-name {
    font-weight: 600;
    color: #2d3748;
    font-size: 13px;
}

.team-alias {
    font-size: 10px;
    color: #718096;
}

/* Position Badge Styles */
.col-position {
    text-align: center;
    width: 120px;
}

.position-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.manager-badge { background: #1e40af; color: white; }
.headcoach-badge { background: #059669; color: white; }
.coach-badge { background: #7c3aed; color: white; }
.goalkeeper_coach-badge { background: #d97706; color: white; }
.medic-badge { background: #dc2626; color: white; }
.official-badge { background: #475569; color: white; }
.assistant_coach-badge { background: #0891b2; color: white; }
.fitness_coach-badge { background: #ea580c; color: white; }
.analyst-badge { background: #9333ea; color: white; }
.scout-badge { background: #65a30d; color: white; }

/* Certificate Count */
.col-certificate {
    text-align: center;
    width: 80px;
}

.cert-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 10px;
    background: #10b981;
    color: white;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.cert-count:hover {
    background: #059669;
    transform: scale(1.05);
}

.cert-count i {
    margin-right: 4px;
    font-size: 10px;
}

/* Events & Matches Count */
.col-events, .col-matches {
    text-align: center;
    width: 70px;
}

.event-match-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 10px;
    background: #3b82f6;
    color: white;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
}

.event-match-count i {
    margin-right: 4px;
    font-size: 10px;
}

/* Created At */
.col-created {
    color: #718096;
    font-size: 12px;
    white-space: nowrap;
}

/* No Data Row */
.no-data {
    text-align: center;
    padding: 40px !important;
    color: #718096;
    font-size: 14px;
}

.no-data i {
    font-size: 36px;
    margin-bottom: 10px;
    color: #cbd5e0;
}

/* Pagination Styles */
.pagination-info {
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #ccc;
    font-size: 14px;
}

.pagination-controls {
    display: flex;
    gap: 0;
    border-radius: 4px;
    overflow: hidden;
    border: 1px solid #ddd;
}

.pagination-controls a, 
.pagination-controls span {
    padding: 8px 16px;
    background: #fff;
    color: #333;
    text-decoration: none;
    border-right: 1px solid #ddd;
}

.pagination-controls a:last-child { border-right: none; }

.pagination-controls a:hover {
    background: #eee;
}

.pagination-controls .active {
    background: #0066cc;
    color: #fff;
    border-color: #0066cc;
}

.pagination-controls .disabled {
    color: #ccc;
    cursor: default;
}

/* Header sort icons */
.sort-icon::after {
    content: " \21D5";
    font-size: 10px;
    opacity: 0.5;
}

/* Horizontal scrollbar styling */
.staff-table-container::-webkit-scrollbar {
    height: 10px;
}
.staff-table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.staff-table-container::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 5px;
}
.staff-table-container::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Modal Styles (minimal for certificates) */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f7fafc;
}

.modal-title {
    color: #1a365d;
    font-size: 18px;
    font-weight: 700;
    margin: 0;
}

.modal-title i {
    margin-right: 8px;
    color: #10b981;
}

.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    color: #718096;
    cursor: pointer;
    padding: 5px;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.close-modal:hover {
    background: #fed7d7;
    color: #dc2626;
}

.modal-body {
    padding: 20px;
}

/* Certificates Grid */
.certificates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.certificate-card {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
    background: white;
}

.certificate-header {
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #e2e8f0;
}

.certificate-title {
    font-size: 16px;
    font-weight: 600;
    color: #1a365d;
    margin-bottom: 8px;
}

.certificate-meta {
    font-size: 12px;
    color: #718096;
}

.certificate-meta i {
    width: 14px;
    margin-right: 6px;
    color: #4a5568;
}

.certificate-preview {
    padding: 15px;
    text-align: center;
    background: #f8f9fa;
}

.certificate-image {
    max-width: 100%;
    max-height: 150px;
    border-radius: 4px;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.file-preview {
    padding: 20px;
    text-align: center;
    background: #f8f9fa;
}

.file-icon {
    font-size: 40px;
    color: #4a5568;
    margin-bottom: 10px;
}

.file-name {
    font-size: 12px;
    color: #718096;
    word-break: break-all;
    margin-bottom: 15px;
}

.file-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.btn-view, .btn-download {
    padding: 6px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-view {
    background: #3b82f6;
    color: white;
}

.btn-download {
    background: #10b981;
    color: white;
}

.no-certificates {
    text-align: center;
    padding: 40px 20px;
    color: #718096;
}

.no-certificates i {
    font-size: 40px;
    margin-bottom: 15px;
    color: #cbd5e0;
}

.no-certificates h3 {
    color: #4a5568;
    margin-bottom: 10px;
}

/* Image Viewer */
.image-viewer {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    z-index: 10001;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 20px;
}

.image-viewer img {
    max-width: 90%;
    max-height: 80%;
    object-fit: contain;
    border-radius: 4px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.5);
}

.image-viewer .close-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.image-viewer .image-title {
    color: white;
    margin-top: 15px;
    font-size: 16px;
    text-align: center;
    max-width: 80%;
}

/* Loading Spinner */
.loading-container {
    text-align: center;
    padding: 40px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #e2e8f0;
    border-top-color: #0066cc;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .staff-table th,
    .staff-table td {
        padding: 10px 8px;
        font-size: 12px;
    }
    
    .staff-img-sm {
        width: 45px;
        height: 45px;
    }
    
    .photo-placeholder {
        width: 45px;
        height: 45px;
    }
}

@media (max-width: 768px) {
    .search-container {
        max-width: 100%;
    }
    
    .pagination-info {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .certificates-grid {
        grid-template-columns: 1fr;
    }
}
</style>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/staff_redesign.css?v=<?php echo time(); ?>">
</head>
<body>

<!-- Certificate Modal -->
<div class="modal-overlay" id="certificateModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-certificate"></i> <span id="modalStaffName">Lisensi Staff</span></h2>
            <button class="close-modal" onclick="closeCertificateModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="certificateContent">
                <!-- Certificate content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Image Viewer -->
<div class="image-viewer" id="imageViewer">
    <button class="close-btn" onclick="closeImageViewer()">&times;</button>
    <img id="fullSizeImage" src="" alt="">
    <div class="image-title" id="imageTitle"></div>
</div>

<div class="dashboard-wrapper">
    <!-- Mobile Header -->
    <header class="mobile-dashboard-header">
        <div class="mobile-logo">
            <img src="<?php echo SITE_URL; ?>/images/mgp-no-bg.png" alt="Logo">
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar" aria-controls="sidebar" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar" aria-hidden="true">
        <div class="sidebar-logo">
            <a href="<?php echo SITE_URL; ?>">
                <img src="<?php echo SITE_URL; ?>/images/mgp-no-bg.png" alt="Logo">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo SITE_URL; ?>"><i class="fas fa-home"></i> <span>HOME</span></a>
            <a href="event.php"><i class="fas fa-calendar-alt"></i> <span>EVENT</span></a>
            <a href="team.php"><i class="fas fa-users"></i> <span>TEAM</span></a>
            <div class="nav-item-dropdown">
                <a href="#" class="nav-has-dropdown active" onclick="toggleDropdown(this, 'playerDropdown'); return false;">
                    <div class="nav-link-content">
                        <i class="fas fa-users"></i> <span>PLAYER</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <div id="playerDropdown" class="sidebar-dropdown show">
                    <a href="player.php">Player</a>
                    <a href="staff.php" class="active">Team Staff</a>
                </div>
            </div>
            <a href="news.php"><i class="fas fa-newspaper"></i> <span>NEWS</span></a>
            <a href="bpjs.php"><i class="fas fa-shield-alt"></i> <span>BPJSTK</span></a>
            <a href="contact.php"><i class="fas fa-envelope"></i> <span>CONTACT</span></a>
            
            <div class="sidebar-divider" style="margin: 15px 0; border-top: 1px solid rgba(255,255,255,0.1);"></div>

            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
                <a href="<?php echo ($_SESSION['admin_role'] === 'pelatih' ? SITE_URL.'/pelatih/dashboard.php' : SITE_URL.'/admin/dashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i> <span>DASHBOARD</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/logout.php" style="color: #e74c3c;">
                    <i class="fas fa-sign-out-alt"></i> <span>LOGOUT</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn-login-sidebar">
                    <i class="fas fa-sign-in-alt"></i> <span>LOGIN</span>
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content-dashboard">
        <header class="dashboard-header dashboard-header-staff">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">FUTSCORE</div>
                    <h1>TEAM STAFF</h1>
                    <p class="header-subtitle">Direktori staff, lisensi, dan afiliasi tim untuk memantau peran kunci di setiap skuad.</p>
                </div>
                <div class="header-actions">
                    <div class="header-stat">
                        <span class="stat-label">Total Staff Aktif</span>
                        <span class="stat-value"><?php echo number_format($total_records); ?></span>
                    </div>
                    <a href="team.php" class="btn-secondary"><i class="fas fa-users"></i> Lihat Tim</a>
                </div>
            </div>
        </header>

        <div class="dashboard-body">
            <div class="filter-card staff-filter-card">
                <form action="" method="GET" class="filter-row">
                    <div class="filter-group">
                        <label for="search">Pencarian Staff</label>
                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Cari staff (nama, email, telepon, jabatan)...">
                    </div>
                    <div class="filter-actions-new">
                        <button type="submit" class="btn-filter-apply">
                            <i class="fas fa-search"></i> Cari
                        </button>
                        <a href="staff.php" class="btn-filter-reset">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                    <?php if ($page > 1): ?>
                        <input type="hidden" name="page" value="<?php echo $page; ?>">
                    <?php endif; ?>
                </form>
                <div class="filter-summary">
                    <div class="summary-item">
                        <span class="summary-label">Menampilkan</span>
                        <span class="summary-value"><?php echo min($offset + 1, $total_records); ?> - <?php echo min($offset + $limit, $total_records); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Staff</span>
                        <span class="summary-value"><?php echo number_format($total_records); ?></span>
                    </div>
                </div>
            </div>

            <div class="table-container-new">
                <table class="staff-table-new">
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th class="col-photo">Foto</th>
                            <th>Nama Staff</th>
                            <th>Tim</th>
                            <th class="col-position">Jabatan</th>
                            <th class="col-age">Usia</th>
                            <th class="col-certificate">Lisensi</th>
                            <th>Dibuat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staffs)): ?>
                            <tr>
                                <td colspan="8" class="no-data">
                                    <i class="fas fa-user-slash"></i>
                                    <p>Tidak ada staff ditemukan</p>
                                    <?php if (!empty($search)): ?>
                                        <p class="no-data-keyword">
                                            Kata kunci: "<?php echo htmlspecialchars($search); ?>"
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $no = $offset + 1;
                            foreach ($staffs as $s): 
                                $position_class = $s['position'] . '-badge';
                                
                                // Get staff photo info
                                $staff_photo = getFileUrl($s['photo'], 'staff', 'fa-user-tie');
                                
                                // Get team logo info
                                $team_logo = getFileUrl($s['team_logo'], 'teams', 'fa-users');
                            ?>
                            <tr>
                                <!-- Kolom No -->
                                <td class="col-no" data-label="No"><?php echo $no++; ?></td>
                                
                                <!-- Kolom Foto Staff -->
                                <td class="col-photo" data-label="Foto">
                                    <div class="staff-photo-wrapper">
                                        <?php if ($staff_photo['found']): ?>
                                            <img src="<?php echo $staff_photo['url']; ?>" 
                                                 class="staff-img-sm" 
                                                 alt="<?php echo htmlspecialchars($s['name'] ?? ''); ?>"
                                                 onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <?php endif; ?>
                                        
                                        <div class="photo-placeholder" style="<?php echo $staff_photo['found'] ? 'display: none;' : ''; ?>">
                                            <i class="fas <?php echo $staff_photo['icon']; ?>"></i>
                                        </div>
                                        
                                        <!-- Team Badge/Lambang di bawah foto -->
                                        <div class="team-badge">
                                            <?php if ($team_logo['found']): ?>
                                                <img src="<?php echo $team_logo['url']; ?>" 
                                                     class="team-badge-img" 
                                                     alt="<?php echo htmlspecialchars($s['team_name'] ?? ''); ?>"
                                                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <?php endif; ?>
                                            
                                            <div class="team-badge-placeholder" style="<?php echo $team_logo['found'] ? 'display: none;' : ''; ?>">
                                                <i class="fas <?php echo $team_logo['icon']; ?>"></i>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Kolom Nama -->
                                <td class="col-name" data-label="Nama">
                                    <div class="staff-name"><?php echo htmlspecialchars($s['name'] ?? ''); ?></div>
                                    <div class="staff-contact">
                                        <?php if (!empty($s['email'])): ?>
                                            <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($s['email'] ?? ''); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($s['phone'])): ?>
                                            <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($s['phone'] ?? ''); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <!-- Kolom Team -->
                                <td class="col-team" data-label="Tim">
                                    <div class="team-display">
                                        <?php if ($team_logo['found']): ?>
                                            <img src="<?php echo $team_logo['url']; ?>" 
                                                 class="team-logo" 
                                                 alt="<?php echo htmlspecialchars($s['team_name'] ?? ''); ?>"
                                                 onerror="this.style.display='none'">
                                        <?php endif; ?>
                                        <div class="team-info">
                                            <span class="team-name"><?php echo htmlspecialchars($s['team_name'] ?: '-'); ?></span>
                                            <?php if (!empty($s['team_alias'])): ?>
                                                <span class="team-alias"><?php echo htmlspecialchars($s['team_alias'] ?? ''); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Kolom Jabatan -->
                                <td class="col-position" data-label="Jabatan">
                                    <span class="position-badge <?php echo $position_class; ?>">
                                        <?php echo formatPosition($s['position']); ?>
                                    </span>
                                </td>
                                
                                <!-- Kolom Usia -->
                                <td class="col-age" data-label="Usia"><?php echo calculateStaffAge($s['birth_date']); ?></td>
                                
                                <!-- Kolom Lisensi -->
                                <td class="col-certificate" data-label="Lisensi">
                                    <?php if ($s['certificate_count'] > 0): ?>
                                        <div class="cert-count" 
                                             onclick="loadCertificates(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'] ?? '')); ?>')">
                                            <i class="fas fa-certificate"></i>
                                            <span><?php echo $s['certificate_count']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Kolom Created At -->
                                <td class="col-created" data-label="Dibuat">
                                    <?php echo date('d M Y', strtotime($s['created_at'])); ?><br>
                                    <small><?php echo date('H:i', strtotime($s['created_at'])); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-bar">
                <div class="pagination-info">
                    <div class="info-text">
                        Showing <?php echo min($offset + 1, $total_records); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo number_format($total_records); ?> entries
                    </div>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="pagination-controls">
                    <!-- Previous -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                    <?php else: ?>
                        <span class="disabled">Previous</span>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo '<a href="?page=1&search='.urlencode($search).'">1</a>';
                        if ($start_page > 2) echo '<span>...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span>...</span>';
                        echo '<a href="?page='.$total_pages.'&search='.urlencode($search).'">'.$total_pages.'</a>';
                    }
                    ?>

                    <!-- Next -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                    <?php else: ?>
                        <span class="disabled">Next</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <footer class="dashboard-footer">
            <p>&copy; 2026 MGP Indonesia. All rights reserved.</p>
            <p>
                <a href="<?php echo SITE_URL; ?>">Home</a> | 
                <a href="contact.php">Contact</a> | 
                <a href="privacy.php">Privacy Policy</a>
            </p>
        </footer>
    </div>
</div>

<script>
const SITE_URL = '<?php echo SITE_URL; ?>';

// Sidebar Dropdown Toggle
function toggleDropdown(element, dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;
    
    dropdown.classList.toggle('show');
    element.classList.toggle('open');
}

// Sidebar Toggle Strategy for Mobile
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');

const setSidebarOpen = (open) => {
    if (!sidebar || !sidebarToggle || !sidebarOverlay) return;
    sidebar.classList.toggle('active', open);
    sidebarOverlay.classList.toggle('active', open);
    sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
    sidebarOverlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.classList.toggle('sidebar-open', open);
};

if (sidebarToggle && sidebar && sidebarOverlay) {
    sidebarToggle.addEventListener('click', () => {
        const isOpen = sidebar.classList.contains('active');
        setSidebarOpen(!isOpen);
    });

    sidebarOverlay.addEventListener('click', () => {
        setSidebarOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebarOpen(false);
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            setSidebarOpen(false);
        }
    });
}

// Function to load certificates - FIXED VERSION
function loadCertificates(staffId, staffName) {
    console.log('Loading certificates for:', staffName, 'ID:', staffId);
    
    const modal = document.getElementById('certificateModal');
    const modalTitle = document.getElementById('modalStaffName');
    const content = document.getElementById('certificateContent');
    
    modalTitle.textContent = `Lisensi: ${staffName}`;
    content.innerHTML = `
        <div class="loading-container">
            <div class="spinner"></div>
            <p>Memuat data lisensi...</p>
        </div>
    `;
    
    modal.style.display = 'flex';
    
    // URL yang benar untuk AJAX request
    const ajaxPath = 'includes/ajax_get_certificates.php';
    const url = `${ajaxPath}?staff_id=${staffId}`;
    
    console.log('Fetching certificates from:', url);
    
    fetch(url)
        .then(response => {
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Certificates data:', data);
            
            if (data.success && data.certificates && data.certificates.length > 0) {
                displayCertificates(data.certificates);
            } else {
                content.innerHTML = `
                    <div class="no-certificates">
                        <i class="fas fa-file-alt"></i>
                        <h3>Tidak Ada Lisensi</h3>
                        <p>Staff ini belum memiliki lisensi.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading certificates:', error);
            
            // Debug: test with direct URL
            const testUrl = ajaxPath + '?staff_id=' + staffId;
            content.innerHTML = `
                <div class="no-certificates">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Gagal Memuat Data</h3>
                    <p>${error.message}</p>
                    <div style="margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                        <small>Debug info:</small><br>
                        <small>URL: ${testUrl}</small><br>
                        <small>Staff ID: ${staffId}</small>
                    </div>
                    <p style="font-size: 12px; color: #666;">
                        Pastikan file <code>${ajaxPath}</code> ada di server.
                    </p>
                    <button onclick="loadCertificates(${staffId}, '${staffName}')" 
                            style="margin-top: 15px; padding: 8px 16px; background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-redo"></i> Coba Lagi
                    </button>
                </div>
            `;
        });
    
    function displayCertificates(certificates) {
        let html = `
            <div style="margin-bottom: 20px;">
                <p style="color: #4a5568; font-size: 14px;">
                    <i class="fas fa-info-circle"></i> Ditemukan ${certificates.length} lisensi
                </p>
            </div>
            <div class="certificates-grid">
        `;
        
        certificates.forEach((cert, index) => {
            const fileName = cert.certificate_file ? 
                cert.certificate_file.split('/').pop() : 'Tidak ada file';
            const fileExt = fileName.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileExt);
            const isPDF = fileExt === 'pdf';
            
            // Build file URL - gunakan SITE_URL yang sudah didefinisikan
            const fileUrl = cert.certificate_file ? 
                `${SITE_URL}/uploads/certificates/${fileName}` : '#';
            
            const formattedDate = cert.issue_date ? 
                new Date(cert.issue_date).toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }) : 'Tidak ada tanggal';
            
            // Escape quotes untuk onclick
            const safeCertName = (cert.certificate_name || 'Lisensi ' + (index + 1)).replace(/'/g, "\\'");
            const safeFileUrl = fileUrl.replace(/'/g, "\\'");
            
            html += `
            <div class="certificate-card">
                <div class="certificate-header">
                    <h3 class="certificate-title">${cert.certificate_name || 'Lisensi ' + (index + 1)}</h3>
                    <div class="certificate-meta">
                        ${cert.issuing_authority ? `
                            <div><i class="fas fa-building"></i> ${cert.issuing_authority}</div>
                        ` : ''}
                        <div><i class="fas fa-calendar"></i> ${formattedDate}</div>
                        <div><i class="fas fa-file"></i> ${fileName}</div>
                    </div>
                </div>
                
                <div class="certificate-preview">
            `;
            
            if (cert.certificate_file && fileName !== 'Tidak ada file') {
                if (isImage) {
                    html += `
                        <img src="${fileUrl}" 
                             alt="${safeCertName}" 
                             class="certificate-image"
                             onclick="viewImage('${safeFileUrl}', '${safeCertName}')">
                        <div class="file-actions" style="margin-top: 15px;">
                            <a href="${fileUrl}" target="_blank" class="btn-view">
                                <i class="fas fa-external-link-alt"></i> Lihat
                            </a>
                            <a href="${fileUrl}" download class="btn-download">
                                <i class="fas fa-download"></i> Unduh
                            </a>
                        </div>
                    `;
                } else if (isPDF) {
                    html += `
                        <div class="file-preview">
                            <div class="file-icon">
                                <i class="fas fa-file-pdf" style="color: #dc2626;"></i>
                            </div>
                            <div class="file-name">${fileName}</div>
                            <div class="file-actions">
                                <a href="${fileUrl}" target="_blank" class="btn-view">
                                    <i class="fas fa-external-link-alt"></i> Buka
                                </a>
                                <a href="${fileUrl}" download class="btn-download">
                                    <i class="fas fa-download"></i> Unduh
                                </a>
                            </div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="file-preview">
                            <div class="file-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="file-name">${fileName}</div>
                            <div class="file-actions">
                                <a href="${fileUrl}" target="_blank" class="btn-view">
                                    <i class="fas fa-external-link-alt"></i> Lihat
                                </a>
                                <a href="${fileUrl}" download class="btn-download">
                                    <i class="fas fa-download"></i> Unduh
                                </a>
                            </div>
                        </div>
                    `;
                }
            } else {
                html += `
                    <div class="file-preview">
                        <div class="file-icon">
                            <i class="fas fa-times-circle" style="color: #a0aec0;"></i>
                        </div>
                        <div class="file-name">Tidak ada file terlampir</div>
                    </div>
                `;
            }
            
            html += `
                </div>
            </div>
            `;
        });
        
        html += `</div>`;
        content.innerHTML = html;
    }
}

// Function to view image in full screen
function viewImage(imageUrl, title) {
    const viewer = document.getElementById('imageViewer');
    const image = document.getElementById('fullSizeImage');
    const imageTitle = document.getElementById('imageTitle');
    
    image.src = imageUrl;
    imageTitle.textContent = title;
    viewer.style.display = 'flex';
    
    // Prevent body scrolling
    document.body.style.overflow = 'hidden';
}

// Function to close image viewer
function closeImageViewer() {
    const viewer = document.getElementById('imageViewer');
    viewer.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Function to close certificate modal
function closeCertificateModal() {
    document.getElementById('certificateModal').style.display = 'none';
}

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCertificateModal();
        closeImageViewer();
    }
});

// Close modal when clicking outside
document.getElementById('certificateModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCertificateModal();
    }
});

document.getElementById('imageViewer').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageViewer();
    }
});

// Image error handler for certificate images
document.addEventListener('DOMContentLoaded', function() {
    // Handler untuk gambar sertifikat (jika ada)
    const certImages = document.querySelectorAll('.certificate-image');
    certImages.forEach(img => {
        img.addEventListener('error', function() {
            console.error('Certificate image failed to load:', this.src);
            this.style.display = 'none';
            const parent = this.parentElement;
            if (parent) {
                const fileUrl = this.src;
                const fileName = fileUrl.split('/').pop();
                parent.innerHTML = `
                    <div class="file-preview">
                        <div class="file-icon">
                            <i class="fas fa-file-image" style="color: #a0aec0;"></i>
                        </div>
                        <div class="file-name">${fileName}</div>
                        <div class="file-actions">
                            <a href="${fileUrl}" target="_blank" class="btn-view">
                                <i class="fas fa-external-link-alt"></i> Buka Link
                            </a>
                        </div>
                    </div>
                `;
            }
        });
    });
});
</script>

<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>

</body>
</html>
