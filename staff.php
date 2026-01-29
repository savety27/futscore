<?php
// DEBUG MODE
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/header.php';

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
    (SELECT COUNT(*) FROM staff_certificates sc WHERE sc.staff_id = ts.id) as certificate_count,
    (SELECT COUNT(*) FROM staff_events se WHERE se.staff_id = ts.id) as event_count,
    (SELECT COUNT(*) FROM staff_matches sm WHERE sm.staff_id = ts.id) as match_count
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
    
    return $position_labels[$position] ?? ucfirst(str_replace('_', ' ', $position));
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

<div class="container">
    <div class="staff-list-section">
        <!-- Page Header -->
       

        <!-- Search Bar -->
        <div class="search-container">
            <form action="" method="GET" class="search-wrapper">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Cari staff (nama, email, telepon, jabatan)...">
                
            </form>
        </div>

        <!-- Staff Table -->
        <div class="staff-table-container">
            <div class="table-responsive">
                <table class="staff-table">
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th class="col-photo">Foto</th>
                            <th>Nama Staff</th>
                            <th>Tim</th>
                            <th class="col-position">Jabatan</th>
                            <th class="col-age">Usia</th>
                            <th class="col-certificate">Lisensi</th>
                            <th class="col-events">Events</th>
                            <th class="col-matches">Matches</th>
                            <th>Dibuat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staffs)): ?>
                            <tr>
                                <td colspan="10" class="no-data">
                                    <i class="fas fa-user-slash"></i>
                                    <p>Tidak ada staff ditemukan</p>
                                    <?php if (!empty($search)): ?>
                                        <p style="margin-top: 10px; font-size: 14px;">
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
                                <td class="col-no"><?php echo $no++; ?></td>
                                
                                <!-- Kolom Foto Staff -->
                                <td class="col-photo">
                                    <div class="staff-photo-wrapper">
                                        <?php if ($staff_photo['found']): ?>
                                            <img src="<?php echo $staff_photo['url']; ?>" 
                                                 class="staff-img-sm" 
                                                 alt="<?php echo htmlspecialchars($s['name']); ?>"
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
                                                     alt="<?php echo htmlspecialchars($s['team_name']); ?>"
                                                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <?php endif; ?>
                                            
                                            <div class="team-badge-placeholder" style="<?php echo $team_logo['found'] ? 'display: none;' : ''; ?>">
                                                <i class="fas <?php echo $team_logo['icon']; ?>"></i>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Kolom Nama -->
                                <td class="col-name">
                                    <div class="staff-name"><?php echo htmlspecialchars($s['name']); ?></div>
                                    <div class="staff-contact">
                                        <?php if (!empty($s['email'])): ?>
                                            <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($s['email']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($s['phone'])): ?>
                                            <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($s['phone']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <!-- Kolom Team -->
                                <td>
                                    <div class="team-display">
                                        <?php if ($team_logo['found']): ?>
                                            <img src="<?php echo $team_logo['url']; ?>" 
                                                 class="team-logo" 
                                                 alt="<?php echo htmlspecialchars($s['team_name']); ?>"
                                                 onerror="this.style.display='none'">
                                        <?php endif; ?>
                                        <div class="team-info">
                                            <span class="team-name"><?php echo htmlspecialchars($s['team_name'] ?: '-'); ?></span>
                                            <?php if (!empty($s['team_alias'])): ?>
                                                <span class="team-alias"><?php echo htmlspecialchars($s['team_alias']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Kolom Jabatan -->
                                <td class="col-position">
                                    <span class="position-badge <?php echo $position_class; ?>">
                                        <?php echo formatPosition($s['position']); ?>
                                    </span>
                                </td>
                                
                                <!-- Kolom Usia -->
                                <td class="col-age"><?php echo calculateStaffAge($s['birth_date']); ?></td>
                                
                                <!-- Kolom Lisensi -->
                                <td class="col-certificate">
                                    <?php if ($s['certificate_count'] > 0): ?>
                                        <div class="cert-count" 
                                             onclick="loadCertificates(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'])); ?>')">
                                            <i class="fas fa-certificate"></i>
                                            <span><?php echo $s['certificate_count']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #a0aec0;">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Kolom Events -->
                                <td class="col-events">
                                    <?php if ($s['event_count'] > 0): ?>
                                        <div class="event-match-count">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span><?php echo $s['event_count']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #a0aec0;">0</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Kolom Matches -->
                                <td class="col-matches">
                                    <?php if ($s['match_count'] > 0): ?>
                                        <div class="event-match-count">
                                            <i class="fas fa-futbol"></i>
                                            <span><?php echo $s['match_count']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #a0aec0;">0</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Kolom Created At -->
                                <td class="col-created">
                                    <?php echo date('d M Y', strtotime($s['created_at'])); ?><br>
                                    <small><?php echo date('H:i', strtotime($s['created_at'])); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

          <!-- Pagination -->
        <div class="pagination-info" style="justify-content: flex-start;">
            <div class="info-text">
                Showing <?php echo min($offset + 1, $total_records); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo number_format($total_records); ?> entries
            </div>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination-controls" style="margin-top: 10px;">
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
</div>
<?php require_once 'includes/footer.php'; ?>

<script>
// Base URL
const SITE_URL = '<?php echo SITE_URL; ?>';

// Function to load certificates
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
    
    // Try multiple AJAX paths
    const ajaxPaths = [
        'includes/ajax_get_certificates.php',
        './includes/ajax_get_certificates.php',
        'ajax_get_certificates.php'
    ];
    
    let currentPathIndex = 0;
    
    function tryAjaxRequest() {
        if (currentPathIndex >= ajaxPaths.length) {
            content.innerHTML = `
                <div class="no-certificates">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Gagal Memuat Data</h3>
                    <p>Tidak dapat menghubungi server.</p>
                    <button onclick="loadCertificates(${staffId}, '${staffName}')" 
                            style="margin-top: 20px; padding: 10px 20px; background: #1a365d; color: white; border: none; border-radius: 6px; cursor: pointer;">
                        <i class="fas fa-redo"></i> Coba Lagi
                    </button>
                </div>
            `;
            return;
        }
        
        const ajaxPath = ajaxPaths[currentPathIndex];
        const url = `${ajaxPath}?staff_id=${staffId}&_=${Date.now()}`;
        
        console.log('Trying AJAX path:', ajaxPath);
        
        fetch(url)
            .then(response => {
                console.log('Response status:', response.status);
                
                // Check if response is HTML instead of JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    console.warn('Response is not JSON, trying next path');
                    currentPathIndex++;
                    tryAjaxRequest();
                    return Promise.reject('Not JSON response');
                }
                
                return response.json();
            })
            .then(data => {
                console.log('Received data:', data);
                
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
                currentPathIndex++;
                tryAjaxRequest();
            });
    }
    
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
            const isDoc = ['doc', 'docx', 'txt', 'pdf'].includes(fileExt);
            
            const fileUrl = cert.certificate_file ? 
                `${SITE_URL}/uploads/certificates/${fileName}` : '#';
            
            const formattedDate = cert.issue_date ? 
                new Date(cert.issue_date).toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }) : 'Tidak ada tanggal';
            
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
            
            if (cert.certificate_file) {
                if (isImage) {
                    html += `
                        <img src="${fileUrl}" 
                             alt="${cert.certificate_name}" 
                             class="certificate-image"
                             onclick="viewImage('${fileUrl}', '${cert.certificate_name.replace(/'/g, "\\'")}')">
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
    
    // Start trying
    tryAjaxRequest();
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

// Auto-submit search on Enter
document.querySelector('.search-wrapper input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});

// Image error handler for staff photos
document.addEventListener('DOMContentLoaded', function() {
    const staffPhotos = document.querySelectorAll('.staff-img-sm');
    staffPhotos.forEach(img => {
        img.addEventListener('error', function() {
            const parent = this.parentElement;
            const placeholder = parent.querySelector('.photo-placeholder');
            if (placeholder) {
                this.style.display = 'none';
                placeholder.style.display = 'flex';
            }
        });
    });
});
</script>

</body>
</html>