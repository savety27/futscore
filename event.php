<?php
require_once 'includes/header.php';

// Page Metadata
$pageTitle = "Event & Pertandingan";

// Logic for Search and Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_sport = isset($_GET['sport']) ? $_GET['sport'] : '';
$filter_match_status = isset($_GET['match_status']) ? $_GET['match_status'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 40;
$offset = ($page - 1) * $limit;

// Database connection
$conn = $db->getConnection();

// Query for Total Records (for pagination)
$count_query = "SELECT COUNT(*) as total FROM challenges c 
                LEFT JOIN teams t1 ON c.challenger_id = t1.id
                LEFT JOIN teams t2 ON c.opponent_id = t2.id
                LEFT JOIN venues v ON c.venue_id = v.id
                WHERE 1=1";

// Query for Event Data
$query = "SELECT 
    c.*,
    t1.name as challenger_name, 
    t1.logo as challenger_logo,
    t1.sport_type as challenger_sport,
    t2.name as opponent_name, 
    t2.logo as opponent_logo,
    v.name as venue_name
FROM challenges c
LEFT JOIN teams t1 ON c.challenger_id = t1.id
LEFT JOIN teams t2 ON c.opponent_id = t2.id
LEFT JOIN venues v ON c.venue_id = v.id
WHERE 1=1";

// Build where conditions for both queries
$where_conditions = "";
$search_param = "";
$params = [];
$types = "";

if (!empty($search)) {
    $search_param = "%$search%";
    $where_conditions .= " AND (c.challenge_code LIKE ? 
                      OR c.sport_type LIKE ? 
                      OR c.notes LIKE ?
                      OR t1.name LIKE ? 
                      OR t2.name LIKE ? 
                      OR v.name LIKE ?)";
    
    $params[] = $search_param; // challenge_code
    $params[] = $search_param; // sport_type
    $params[] = $search_param; // notes
    $params[] = $search_param; // challenger_name
    $params[] = $search_param; // opponent_name
    $params[] = $search_param; // venue_name
    $types .= 'ssssss';
}

// Filter by status
if (!empty($filter_status) && $filter_status !== 'all') {
    $where_conditions .= " AND c.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

// Filter by sport
if (!empty($filter_sport) && $filter_sport !== 'all') {
    $where_conditions .= " AND c.sport_type = ?";
    $params[] = $filter_sport;
    $types .= 's';
}

// Filter by match status
if (!empty($filter_match_status) && $filter_match_status !== 'all') {
    $where_conditions .= " AND c.match_status = ?";
    $params[] = $filter_match_status;
    $types .= 's';
}

// Get total records with filters
$stmt_count = $conn->prepare($count_query . $where_conditions);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Get paginated data
$query .= $where_conditions . " ORDER BY c.challenge_date DESC LIMIT ? OFFSET ?";

// Add pagination params
$params_count = $params;
$params_count[] = $limit;
$params_count[] = $offset;
$types_count = $types . 'ii';

$stmt = $conn->prepare($query);
if (!empty($params_count)) {
    $stmt->bind_param($types_count, ...$params_count);
}
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unique sports for filter dropdown
$sport_query = "SELECT DISTINCT sport_type FROM challenges WHERE sport_type IS NOT NULL AND sport_type != '' ORDER BY sport_type";
$sport_result = $conn->query($sport_query);
$sports = [];
while ($row = $sport_result->fetch_assoc()) {
    $sports[] = $row['sport_type'];
}

// Get unique statuses for filter dropdown
$status_query = "SELECT DISTINCT status FROM challenges WHERE status IS NOT NULL ORDER BY FIELD(status, 'open', 'accepted', 'completed', 'rejected', 'expired')";
$status_result = $conn->query($status_query);
$statuses = [];
while ($row = $status_result->fetch_assoc()) {
    $statuses[] = $row['status'];
}

// Get unique match statuses for filter dropdown
$match_status_query = "SELECT DISTINCT match_status FROM challenges WHERE match_status IS NOT NULL AND match_status != '' ORDER BY match_status";
$match_status_result = $conn->query($match_status_query);
$match_statuses = [];
while ($row = $match_status_result->fetch_assoc()) {
    $match_statuses[] = $row['match_status'];
}

// Helper Functions with better badges
function getStatusBadge($status) {
    $status = strtolower($status);
    $badges = [
        'open' => '<span class="status-badge status-open">Open</span>',
        'accepted' => '<span class="status-badge status-accepted">Accepted</span>',
        'completed' => '<span class="status-badge status-completed">Completed</span>',
        'rejected' => '<span class="status-badge status-rejected">Rejected</span>',
        'expired' => '<span class="status-badge status-expired">Expired</span>',
        'cancelled' => '<span class="status-badge status-cancelled">Cancelled</span>'
    ];
    return $badges[$status] ?? '<span class="status-badge status-default">' . ucfirst($status) . '</span>';
}

function getMatchStatusBadge($match_status) {
    $match_status = strtolower($match_status);
    $badges = [
        'scheduled' => '<span class="status-badge match-scheduled">Scheduled</span>',
        'ongoing' => '<span class="status-badge match-ongoing">Ongoing</span>',
        'completed' => '<span class="status-badge match-completed">Completed</span>',
        'postponed' => '<span class="status-badge match-postponed">Postponed</span>',
        'cancelled' => '<span class="status-badge match-cancelled">Cancelled</span>',
        'abandoned' => '<span class="status-badge match-abandoned">Abandoned</span>'
    ];
    return $badges[$match_status] ?? '<span class="status-badge match-default">' . ucfirst($match_status ?? 'Not Set') . '</span>';
}

function formatScore($challenger_score, $opponent_score) {
    if ($challenger_score === null || $opponent_score === null) {
        return '<span class="score-pending">—</span>';
    }
    return '<span class="score-display">' . $challenger_score . ' : ' . $opponent_score . '</span>';
}

function getWinner($challenger_name, $opponent_name, $challenger_score, $opponent_score, $winner_team_id, $challenger_id, $opponent_id) {
    if ($winner_team_id == $challenger_id) {
        return '<span class="winner-badge">' . htmlspecialchars($challenger_name) . ' <i class="fas fa-trophy"></i></span>';
    } elseif ($winner_team_id == $opponent_id) {
        return '<span class="winner-badge">' . htmlspecialchars($opponent_name) . ' <i class="fas fa-trophy"></i></span>';
    } elseif ($challenger_score !== null && $opponent_score !== null) {
        if ($challenger_score > $opponent_score) {
            return '<span class="winner-badge">' . htmlspecialchars($challenger_name) . ' <i class="fas fa-trophy"></i></span>';
        } elseif ($challenger_score < $opponent_score) {
            return '<span class="winner-badge">' . htmlspecialchars($opponent_name) . ' <i class="fas fa-trophy"></i></span>';
        } else {
            return '<span class="draw-badge"><i class="fas fa-equals"></i> DRAW</span>';
        }
    }
    return '<span class="no-winner">—</span>';
}
?>

<style>
/* CSS Reset and Base for the section */
.event-list-section {
    padding: 40px 0;
    color: #fff;
}

/* Filter Section */
.filter-section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    margin-bottom: 8px;
    font-weight: 600;
    color: #fff;
    font-size: 14px;
}

.filter-group select,
.filter-group input {
    padding: 10px 15px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    color: #333;
    font-size: 14px;
    transition: all 0.3s ease;
}

.filter-group select:focus,
.filter-group input:focus {
    outline: none;
    border-color: #0066cc;
    box-shadow: 0 0 0 2px rgba(0, 102, 204, 0.2);
}

.filter-actions {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.filter-btn {
    padding: 10px 25px;
    background: linear-gradient(135deg, #0066cc, #004d99);
    color: white;
    border: none;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
}

.filter-btn:hover {
    background: linear-gradient(135deg, #0052a3, #003366);
    transform: translateY(-2px);
}

.reset-btn {
    padding: 10px 25px;
    background: linear-gradient(135deg, #6c757d, #545b62);
    color: white;
    border: none;
    border-radius: 4px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.reset-btn:hover {
    background: linear-gradient(135deg, #545b62, #3d4348);
    transform: translateY(-2px);
}

/* Event Table */
.event-table-container {
    background: #fff;
    border-radius: 8px;
    overflow-x: auto;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    border: 1px solid #ddd;
    margin-bottom: 30px;
}

.event-table {
    width: 100%;
    border-collapse: collapse;
    color: #333;
    font-size: 13px;
    min-width: 1600px;
}

.event-table thead tr {
    background: linear-gradient(to right, #000, #c00) !important;
    color: #fff;
}

.event-table th {
    padding: 14px 12px;
    text-align: left;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.5px;
    border-right: 1px solid rgba(255,255,255,0.15);
    white-space: nowrap;
    color: #fff !important;
    background: transparent !important;
}

.event-table th:last-child {
    border-right: none;
}

.event-table td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    border-right: 1px solid #f5f5f5;
    vertical-align: middle;
}

.event-table td:last-child {
    border-right: none;
}

.event-table tbody tr {
    transition: all 0.2s ease;
}

.event-table tbody tr:hover {
    background-color: #f8f9fa;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.event-table tbody tr:last-child td {
    border-bottom: none;
}

/* Column Specific Styles */
.col-no { 
    width: 50px; 
    text-align: center;
    font-weight: 600;
    color: #666;
    background: #f9f9f9;
}

.col-code { 
    width: 140px;
    font-weight: 600;
    color: #333;
}

.col-teams { 
    width: 320px;
    min-width: 320px;
}

.col-score { 
    width: 110px; 
    text-align: center;
    font-weight: 600;
}

.col-winner { 
    width: 180px;
    text-align: center;
}

.col-status { 
    width: 120px;
    text-align: center;
    padding: 8px !important;
}

.col-sport { 
    width: 110px;
    text-align: center;
}

.col-venue { 
    width: 160px;
}

.col-date { 
    width: 140px;
    font-size: 12px;
    color: #555;
}

.col-action { 
    width: 90px; 
    text-align: center;
}

/* Team Display */
.team-display {
    display: flex;
    align-items: center;
    gap: 12px;
}

.team-section {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 6px;
}

.team-section.left {
    justify-content: flex-end;
}

.team-section.right {
    justify-content: flex-start;
}

.team-logo-small {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: contain;
    background: #f5f5f5;
    border: 2px solid #e0e0e0;
    padding: 2px;
}

.team-name {
    font-size: 12px;
    font-weight: 500;
    color: #333;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.vs-divider {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
}

.vs-text {
    font-weight: 700;
    color: #c00;
    font-size: 13px;
    padding: 4px 8px;
    background: #fff3f3;
    border-radius: 4px;
    border: 1px solid #ffe0e0;
}

/* Score Display */
.score-display {
    font-weight: 700;
    font-size: 18px;
    color: #333;
    font-family: 'Arial', sans-serif;
    letter-spacing: 2px;
}

.score-pending {
    color: #aaa;
    font-style: italic;
    font-size: 20px;
}

/* Winner Badge */
.winner-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: linear-gradient(135deg, #2ecc71, #27ae60);
    color: white;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
    box-shadow: 0 2px 4px rgba(46, 204, 113, 0.3);
    max-width: 160px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.winner-badge i {
    font-size: 12px;
    color: #ffd700;
}

.draw-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: linear-gradient(135deg, #f39c12, #e67e22);
    color: white;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
}

.no-winner {
    color: #aaa;
    font-style: italic;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 6px 12px;
    color: white;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
    min-width: 85px;
    white-space: nowrap;
}

/* Challenge Status Colors */
.status-open { background: linear-gradient(135deg, #3498db, #2980b9); }
.status-accepted { background: linear-gradient(135deg, #2ecc71, #27ae60); }
.status-completed { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
.status-rejected { background: linear-gradient(135deg, #e74c3c, #c0392b); }
.status-expired { background: linear-gradient(135deg, #95a5a6, #7f8c8d); }
.status-cancelled { background: linear-gradient(135deg, #34495e, #2c3e50); }
.status-default { background: linear-gradient(135deg, #bdc3c7, #95a5a6); }

/* Match Status Colors */
.match-scheduled { background: linear-gradient(135deg, #3498db, #2980b9); }
.match-ongoing { background: linear-gradient(135deg, #f39c12, #e67e22); }
.match-completed { background: linear-gradient(135deg, #2ecc71, #27ae60); }
.match-postponed { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
.match-cancelled { background: linear-gradient(135deg, #e74c3c, #c0392b); }
.match-abandoned { background: linear-gradient(135deg, #7f8c8d, #95a5a6); }
.match-default { background: linear-gradient(135deg, #ecf0f1, #bdc3c7); color: #666; }

/* Sport Badge */
.sport-badge {
    font-size: 11px;
    padding: 5px 10px;
    background: linear-gradient(135deg, #f0f0f0, #e0e0e0);
    color: #333;
    border-radius: 12px;
    font-weight: 600;
    display: inline-block;
    border: 1px solid #d0d0d0;
}

/* View button */
.view-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    background: linear-gradient(135deg, #0066cc, #004d99);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 102, 204, 0.2);
}

.view-btn:hover {
    background: linear-gradient(135deg, #0052a3, #003366);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 102, 204, 0.3);
}

/* Pagination */
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
    transition: all 0.3s ease;
}

.pagination-controls a:last-child { 
    border-right: none; 
}

.pagination-controls a:hover {
    background: #eee;
}

.pagination-controls .active {
    background: linear-gradient(135deg, #0066cc, #004d99);
    color: #fff;
    border-color: #0066cc;
}

.pagination-controls .disabled {
    color: #ccc;
    cursor: default;
    background: #f8f9fa;
}

/* Empty State */
.empty-state {
    background: white;
    border-radius: 8px;
    padding: 60px 40px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    text-align: center;
    grid-column: 1 / -1;
}

.empty-icon {
    font-size: 60px;
    color: #ccc;
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 20px;
    color: #333;
    margin-bottom: 10px;
}

.empty-state p {
    color: #666;
    max-width: 500px;
    margin: 0 auto 20px;
    line-height: 1.6;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .filter-btn,
    .reset-btn {
        width: 100%;
        justify-content: center;
    }
    
    .pagination-info {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .pagination-controls {
        justify-content: center;
        flex-wrap: wrap;
    }
}
</style>

<div class="container">
    <div class="event-list-section">
        <!-- Page Header -->
        <div style="margin-bottom: 30px;">
            <h1 style="color: #fff; margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-trophy"></i>
                Event & Pertandingan
            </h1>
            <p style="color: #ccc;">Daftar semua pertandingan yang telah dan akan berlangsung</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-grid">
                <!-- Search -->
                <div class="filter-group">
                    <label for="search">Pencarian</label>
                    <input type="text" name="search" id="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Cari kode, tim, venue, cabor...">
                </div>
                
                <!-- Status Filter -->
                <div class="filter-group">
                    <label for="status">Status Challenge</label>
                    <select name="status" id="status">
                        <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo htmlspecialchars($status); ?>" 
                                <?php echo $filter_status === $status ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Sport Filter -->
                <div class="filter-group">
                    <label for="sport">Cabang Olahraga</label>
                    <select name="sport" id="sport">
                        <option value="all" <?php echo $filter_sport === 'all' ? 'selected' : ''; ?>>Semua Cabor</option>
                        <?php foreach ($sports as $sport): ?>
                            <option value="<?php echo htmlspecialchars($sport); ?>" 
                                <?php echo $filter_sport === $sport ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sport); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Match Status Filter -->
                <div class="filter-group">
                    <label for="match_status">Status Pertandingan</label>
                    <select name="match_status" id="match_status">
                        <option value="all" <?php echo $filter_match_status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                        <?php foreach ($match_statuses as $ms): ?>
                            <option value="<?php echo htmlspecialchars($ms); ?>" 
                                <?php echo $filter_match_status === $ms ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($ms)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Action Buttons -->
                <div class="filter-group filter-actions">
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Terapkan Filter
                    </button>
                    <a href="event.php" class="reset-btn">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
                
                <!-- Hidden inputs for pagination -->
                <?php if ($page > 1): ?>
                    <input type="hidden" name="page" value="<?php echo $page; ?>">
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="event-table-container">
            <table class="event-table">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-code">Kode</th>
                        <th class="col-teams">Pertandingan</th>
                        <th class="col-score">Skor</th>
                        <th class="col-winner">Pemenang</th>
                        <th class="col-status">Status</th>
                        <th class="col-status">Match Status</th>
                        <th class="col-sport">Cabor</th>
                        <th class="col-venue">Venue</th>
                        <th class="col-date">Tanggal</th>
                        <th class="col-action">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 40px;">
                                <div class="empty-state" style="box-shadow: none; padding: 20px;">
                                    <div class="empty-icon">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                    <h3 style="font-size: 16px; margin-bottom: 10px;">Tidak Ada Event Ditemukan</h3>
                                    <p style="font-size: 14px; margin-bottom: 15px;">
                                        Tidak ada pertandingan yang sesuai dengan filter yang Anda pilih.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($events as $e): 
                        ?>
                        <tr>
                            <td class="col-no"><?php echo $no++; ?></td>
                            <td class="col-code">
                                <?php echo htmlspecialchars($e['challenge_code']); ?>
                            </td>
                            <td class="col-teams">
                                <div class="team-display">
                                    <!-- Challenger -->
                                    <div class="team-section left">
                                        <span class="team-name"><?php echo htmlspecialchars($e['challenger_name']); ?></span>
                                        <?php if (!empty($e['challenger_logo'])): ?>
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($e['challenger_logo']); ?>" 
                                                 class="team-logo-small" 
                                                 alt="<?php echo htmlspecialchars($e['challenger_name']); ?>">
                                        <?php else: ?>
                                            <div class="team-logo-small" style="display: flex; align-items: center; justify-content: center; background: #eee;">
                                                <i class="fas fa-shield-alt" style="font-size: 12px; color: #999;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- VS -->
                                    <div class="vs-divider">
                                        <span class="vs-text">VS</span>
                                    </div>
                                    
                                    <!-- Opponent -->
                                    <div class="team-section right">
                                        <?php if (!empty($e['opponent_logo'])): ?>
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($e['opponent_logo']); ?>" 
                                                 class="team-logo-small" 
                                                 alt="<?php echo htmlspecialchars($e['opponent_name']); ?>">
                                        <?php else: ?>
                                            <div class="team-logo-small" style="display: flex; align-items: center; justify-content: center; background: #eee;">
                                                <i class="fas fa-shield-alt" style="font-size: 12px; color: #999;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span class="team-name"><?php echo htmlspecialchars($e['opponent_name'] ?? 'TBD'); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="col-score">
                                <?php echo formatScore($e['challenger_score'], $e['opponent_score']); ?>
                            </td>
                            <td class="col-winner">
                                <?php echo getWinner(
                                    $e['challenger_name'], 
                                    $e['opponent_name'], 
                                    $e['challenger_score'], 
                                    $e['opponent_score'],
                                    $e['winner_team_id'],
                                    $e['challenger_id'],
                                    $e['opponent_id']
                                ); ?>
                            </td>
                            <td class="col-status">
                                <?php echo getStatusBadge($e['status']); ?>
                            </td>
                            <td class="col-status">
                                <?php echo getMatchStatusBadge($e['match_status']); ?>
                            </td>
                            <td class="col-sport">
                                <span class="sport-badge">
                                    <?php echo htmlspecialchars($e['sport_type']); ?>
                                </span>
                            </td>
                            <td class="col-venue">
                                <i class="fas fa-map-marker-alt" style="color: #c00; margin-right: 4px;"></i>
                                <?php echo htmlspecialchars($e['venue_name'] ?? '-'); ?>
                            </td>
                            <td class="col-date">
                                <i class="fas fa-calendar-alt" style="color: #666; margin-right: 4px;"></i>
                                <?php echo formatDateTime($e['challenge_date']); ?>
                            </td>
                            <td class="col-action">
                                <a href="event_detail.php?id=<?php echo $e['id']; ?>" class="view-btn">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-info">
            <div class="info-text">
                Menampilkan <?php echo min($offset + 1, $total_records); ?> sampai <?php echo min($offset + $limit, $total_records); ?> dari <?php echo number_format($total_records); ?> event
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination-controls">
                    <!-- Previous -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $filter_status; ?>&sport=<?php echo $filter_sport; ?>&match_status=<?php echo $filter_match_status; ?>">Previous</a>
                    <?php else: ?>
                        <span class="disabled">Previous</span>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo '<a href="?page=1&search='.urlencode($search).'&status='.$filter_status.'&sport='.$filter_sport.'&match_status='.$filter_match_status.'">1</a>';
                        if ($start_page > 2) echo '<span>...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $filter_status; ?>&sport=<?php echo $filter_sport; ?>&match_status=<?php echo $filter_match_status; ?>" 
                           class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span>...</span>';
                        echo '<a href="?page='.$total_pages.'&search='.urlencode($search).'&status='.$filter_status.'&sport='.$filter_sport.'&match_status='.$filter_match_status.'">'.$total_pages.'</a>';
                    }
                    ?>

                    <!-- Next -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $filter_status; ?>&sport=<?php echo $filter_sport; ?>&match_status=<?php echo $filter_match_status; ?>">Next</a>
                    <?php else: ?>
                        <span class="disabled">Next</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-submit filter on change
document.getElementById('status')?.addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('sport')?.addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('match_status')?.addEventListener('change', function() {
    this.form.submit();
});

// Auto-submit search on enter
document.getElementById('search')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>