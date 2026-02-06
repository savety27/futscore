<?php
$hideNavbars = true;
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/event_redesign.css?v=<?php echo time(); ?>">
<?php

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
        'open' => '<span class="badge-new" style="background-color: #3498db;">Open</span>',
        'accepted' => '<span class="badge-new badge-completed">Accepted</span>',
        'completed' => '<span class="badge-new badge-completed">Completed</span>',
        'rejected' => '<span class="badge-new" style="background-color: #e74c3c;">Rejected</span>',
        'expired' => '<span class="badge-new badge-warning">Expired</span>',
        'cancelled' => '<span class="badge-new badge-warning">Cancelled</span>'
    ];
    return $badges[$status] ?? '<span class="badge-new" style="background-color: #bdc3c7;">' . ucfirst($status) . '</span>';
}

function getMatchStatusBadge($match_status) {
    $match_status = strtolower($match_status);
    $badges = [
        'scheduled' => '<span class="badge-new badge-warning">Scheduled</span>',
        'ongoing' => '<span class="badge-new badge-pending">Ongoing</span>',
        'completed' => '<span class="badge-new badge-completed">Completed</span>',
        'postponed' => '<span class="badge-new badge-warning">Postponed</span>',
        'cancelled' => '<span class="badge-new" style="background-color: #e74c3c;">Cancelled</span>',
        'abandoned' => '<span class="badge-new" style="background-color: #95a5a6;">Abandoned</span>'
    ];
    return $badges[$match_status] ?? '<span class="badge-new badge-pending">' . ucfirst($match_status ?? 'Not Set') . '</span>';
}

function formatScore($challenger_score, $opponent_score) {
    if ($challenger_score === null || $opponent_score === null) {
        return '<span class="score-pending">—</span>';
    }
    return '<span class="score-display" style="font-weight: 800; color: #002d62;">' . $challenger_score . ' : ' . $opponent_score . '</span>';
}

function getWinner($challenger_name, $opponent_name, $challenger_score, $opponent_score, $winner_team_id, $challenger_id, $opponent_id) {
    if ($winner_team_id == $challenger_id) {
        return '<span class="btn-winner-new">' . htmlspecialchars($challenger_name) . ' <i class="fas fa-trophy"></i></span>';
    } elseif ($winner_team_id == $opponent_id) {
        return '<span class="btn-winner-new">' . htmlspecialchars($opponent_name) . ' <i class="fas fa-trophy"></i></span>';
    } elseif ($challenger_score !== null && $opponent_score !== null) {
        if ($challenger_score > $opponent_score) {
            return '<span class="btn-winner-new">' . htmlspecialchars($challenger_name) . ' <i class="fas fa-trophy"></i></span>';
        } elseif ($challenger_score < $opponent_score) {
            return '<span class="btn-winner-new">' . htmlspecialchars($opponent_name) . ' <i class="fas fa-trophy"></i></span>';
        } else {
            return '<span class="badge-new badge-pending">DRAW</span>';
        }
    }
    return '<span class="no-winner">—</span>';
}
?>


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
            <a href="event.php" class="active"><i class="fas fa-calendar-alt"></i> <span>EVENT</span></a>
            <a href="team.php"><i class="fas fa-users"></i> <span>TEAM</span></a>
            <div class="nav-item-dropdown">
                <a href="#" class="nav-has-dropdown" onclick="toggleDropdown(this, 'playerDropdown'); return false;">
                    <div class="nav-link-content">
                        <i class="fas fa-users"></i> <span>PLAYER</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <div id="playerDropdown" class="sidebar-dropdown">
                    <a href="player.php">Player</a>
                    <a href="staff.php">Team Staff</a>
                </div>
            </div>
            <a href="news.php"><i class="fas fa-newspaper"></i> <span>NEWS</span></a>
            <a href="bpjs.php"><i class="fas fa-shield-alt"></i> <span>BPJSTK</span></a>
            <a href="contact.php"><i class="fas fa-envelope"></i> <span>CONTACT</span></a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content-dashboard">
        <header class="dashboard-header">
            <h1>EVENTS & MATCHES</h1>
        </header>

        <div class="dashboard-body">
            <!-- Filter Section -->
            <div class="filter-card">
                <form method="GET" action="" class="filter-row">
                    <!-- Search -->
                    <div class="filter-group">
                        <label for="search">Pencarian</label>
                        <input type="text" name="search" id="search" placeholder="Cari event..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="filter-group">
                        <label for="status">Status Challenge</label>
                        <select name="status" id="status">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status ?? ''); ?>" <?php echo $filter_status == $status ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($status ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Sport Filter -->
                    <div class="filter-group">
                        <label for="sport">Cabang Olahraga</label>
                        <select name="sport" id="sport">
                            <option value="all" <?php echo $filter_sport === 'all' ? 'selected' : ''; ?>>Semua Cabor</option>
                            <?php foreach ($sports as $sport): ?>
                                <option value="<?php echo htmlspecialchars($sport ?? ''); ?>" <?php echo $filter_sport == $sport ? 'selected' : ''; ?>><?php echo htmlspecialchars($sport ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Match Status Filter -->
                    <div class="filter-group">
                        <label for="match_status">Status Pertandingan</label>
                        <select name="match_status" id="match_status">
                            <option value="all" <?php echo $filter_match_status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                            <?php foreach ($match_statuses as $ms): ?>
                                <option value="<?php echo htmlspecialchars($ms ?? ''); ?>" <?php echo $filter_match_status == $ms ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($ms ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="filter-actions-new">
                        <button type="submit" class="btn-filter-apply">
                            <i class="fas fa-filter"></i> Terapkan Filter
                        </button>
                        <a href="event.php" class="btn-filter-reset">
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
            <div class="table-container-new">
                <table class="event-table-new">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">No</th>
                            <th style="width: 150px;">Kode</th>
                            <th style="width: 300px;">Pertandingan</th>
                            <th style="width: 100px; text-align: center;">Skor</th>
                            <th style="width: 180px; text-align: center;">Pemenang</th>
                            <th style="width: 120px; text-align: center;">Status</th>
                            <th style="width: 140px; text-align: center;">Match Status</th>
                            <th style="width: 110px; text-align: center;">Cabor</th>
                            <th style="width: 100px; text-align: center;">Action</th>
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
                        <tr class="match-row-new">
                            <td data-label="No" style="text-align: center; font-weight: 700; color: #666;"><?php echo $no++; ?></td>
                            <td data-label="Kode" style="font-weight: 700; color: #002d62;">
                                <?php echo htmlspecialchars($e['challenge_code']); ?>
                            </td>
                            <td data-label="Pertandingan">
                                <div class="match-cell-new">
                                    <div class="team-info-new">
                                        <span style="font-weight: 600;"><?php echo htmlspecialchars($e['challenger_name']); ?></span>
                                        <?php if (!empty($e['challenger_logo'])): ?>
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($e['challenger_logo']); ?>" 
                                                 class="team-logo-tiny" 
                                                 alt="<?php echo htmlspecialchars($e['challenger_name']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-shield-alt" style="font-size: 14px; color: #ccc;"></i>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="font-weight: 800; color: #e74c3c; font-size: 12px; padding: 0 5px;">VS</div>
                                    
                                    <div class="team-info-new">
                                        <?php if (!empty($e['opponent_logo'])): ?>
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($e['opponent_logo']); ?>" 
                                                 class="team-logo-tiny" 
                                                 alt="<?php echo htmlspecialchars($e['opponent_name']); ?>">
                                        <?php else: ?>
                                            <i class="fas fa-shield-alt" style="font-size: 14px; color: #ccc;"></i>
                                        <?php endif; ?>
                                        <span style="font-weight: 600;"><?php echo htmlspecialchars($e['opponent_name'] ?? 'TBD'); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Skor" style="text-align: center;">
                                <?php echo formatScore($e['challenger_score'], $e['opponent_score']); ?>
                            </td>
                            <td data-label="Pemenang" style="text-align: center;">
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
                            <td data-label="Status" style="text-align: center;">
                                <?php echo getStatusBadge($e['status']); ?>
                            </td>
                            <td data-label="Match Status" style="text-align: center;">
                                <?php echo getMatchStatusBadge($e['match_status']); ?>
                            </td>
                            <td data-label="Cabor" style="text-align: center;">
                                <span class="badge-new badge-cabor">
                                    <?php echo htmlspecialchars($e['sport_type']); ?>
                                </span>
                            </td>
                            <td data-label="Action" style="text-align: center;">
                                <a href="event_detail.php?id=<?php echo $e['id']; ?>" class="btn-filter-reset" style="padding: 5px 12px; border-color: #002d62; color: #002d62;">
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
        <footer class="dashboard-footer">
            <p>&copy; 2026 MGP Indonesia. All rights reserved.</p>
            <p>
                <a href="<?php echo SITE_URL; ?>">Home</a> | 
                <a href="contact.php">Contact</a> | 
                <a href="privacy.php">Privacy Policy</a>
            </p>
        </footer>
    </main>
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

// Sidebar Dropdown Toggle
function toggleDropdown(element, dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;
    
    // Toggle dropdown visibility
    dropdown.classList.toggle('show');
    
    // Rotate icon
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
</script>

<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
