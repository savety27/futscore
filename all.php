<?php
// ============================================
// LOGIC SEBELUM OUTPUT
// ============================================
$pageTitle = "All Matches";
$hideNavbars = true;

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'result';
$eventId = isset($_GET['event']) ? trim($_GET['event']) : '';
if ($eventId === '0') {
    $eventId = '';
}
$teamId = isset($_GET['team']) ? (int)$_GET['team'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 40;

// Redirect jika ID tidak valid
if ($status !== 'schedule' && $status !== 'result') {
    header("Location: index.php");
    exit();
}

// Sekarang baru require header
require_once 'includes/header.php';
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/all_redesign.css?v=<?php echo time(); ?>">

<?php
$conn = $db->getConnection();

// Get events for filter dropdown from challenges
$events = [];
$eventsSql = "SELECT DISTINCT sport_type FROM challenges WHERE sport_type IS NOT NULL AND sport_type != '' ORDER BY sport_type";
$eventsResult = $conn->query($eventsSql);
while ($event = $eventsResult->fetch_assoc()) {
    $events[] = $event['sport_type'];
}

// Get teams for filter dropdown
$teams = [];
$teamsSql = "SELECT * FROM teams ORDER BY name";
$teamsResult = $conn->query($teamsSql);
while ($team = $teamsResult->fetch_assoc()) {
    $teams[] = $team;
}

// Get matches from database
$result = getAllChallenges([
    'status' => $status,
    'event' => $eventId,
    'team_id' => $teamId,
    'page' => $page,
    'per_page' => $perPage,
    'order_by' => 'challenge_date',
    'order_dir' => $status === 'schedule' ? 'ASC' : 'DESC'
]);

$paginatedMatches = $result['matches'];
$totalMatches = $result['total'];
$totalPages = $result['total_pages'];
$offset = ($page - 1) * $perPage;
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
    <main class="main-content-dashboard">
        <header class="dashboard-header dashboard-header-all">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">MGP</div>
                    <h1>All Matches</h1>
                    <p class="header-subtitle">Daftar lengkap jadwal dan hasil pertandingan yang bisa kamu filter sesuai kebutuhan.</p>
                </div>
            </div>
        </header>

        <div class="dashboard-body">
            <div class="filter-card">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="statusFilter">Show</label>
                        <select id="statusFilter" class="filter-select">
                            <option value="result" <?php echo $status === 'result' ? 'selected' : ''; ?>>Result</option>
                            <option value="schedule" <?php echo $status === 'schedule' ? 'selected' : ''; ?>>Schedule</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="eventFilter">Event</label>
                        <select id="eventFilter" class="filter-select">
                            <option value="0">All Events</option>
                            <?php foreach ($events as $event): ?>
                            <option value="<?php echo htmlspecialchars($event ?? ''); ?>" <?php echo $eventId === $event ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($event ?? ''); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="teamFilter">Team</label>
                        <select id="teamFilter" class="filter-select">
                            <option value="0">All Teams</option>
                            <?php foreach ($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>" <?php echo $teamId == $team['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($team['name'] ?? ''); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-actions-new">
                        <button class="btn-filter-apply" id="applyFilter">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                        <button class="btn-filter-reset" id="resetFilter">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
            
            <?php if (empty($paginatedMatches)): ?>
            <div class="empty-state">
                <i class="fas fa-futbol"></i>
                <h3>No matches found</h3>
                <p>No matches available with the current filters</p>
            </div>
            <?php else: ?>
            <div class="table-container-new">
                <div class="table-responsive">
                    <table class="matches-table">
                        <thead>
                            <tr>
                                <th class="col-no">No</th>
                                <th class="col-match">Match</th>
                                <th class="col-score">Score</th>
                                <th class="col-datetime">Date & Time</th>
                                <th class="col-venue">Venue</th>
                                <th class="col-event">Event</th>
                                <th class="col-action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginatedMatches as $index => $match): 
                                $matchNumber = $offset + $index + 1;
                                $isScheduled = $status === 'schedule';
                            ?>
                            <tr class="match-row" data-match-id="<?php echo $match['id']; ?>">
                                <td class="match-number" data-label="No"><?php echo $matchNumber; ?></td>
                                <td class="match-teams-cell" data-label="Match">
                                    <div class="match-teams-info">
                                        <div class="team-info">
                                            <div class="team-logo-wrapper">
                                                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['challenger_logo']; ?>" 
                                                     alt="<?php echo htmlspecialchars($match['challenger_name'] ?? ''); ?>" 
                                                     class="team-logo-sm"
                                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                            </div>
                                            <span class="team-name-sm"><?php echo htmlspecialchars($match['challenger_name'] ?? ''); ?></span>
                                        </div>
                                        <div class="vs-sm">VS</div>
                                        <div class="team-info">
                                            <div class="team-logo-wrapper">
                                                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['opponent_logo']; ?>" 
                                                     alt="<?php echo htmlspecialchars($match['opponent_name'] ?? ''); ?>" 
                                                     class="team-logo-sm"
                                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                            </div>
                                            <span class="team-name-sm"><?php echo htmlspecialchars($match['opponent_name'] ?? ''); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="match-score-cell" data-label="Score">
                                    <?php if ($isScheduled): ?>
                                    <div class="match-status-badge scheduled">SCHEDULE</div>
                                    <?php else: ?>
                                    <div class="score-info">
                                        <span class="score-team"><?php echo $match['challenger_score']; ?></span>
                                        <span class="score-separator">-</span>
                                        <span class="score-team"><?php echo $match['opponent_score']; ?></span>
                                    </div>
                                    <div class="match-status-badge completed">FT</div>
                                    <?php endif; ?>
                                </td>
                                <td class="match-datetime-cell" data-label="Date & Time">
                                    <div class="datetime-info">
                                        <span class="date-info"><?php echo formatDateTime($match['challenge_date']); ?></span>
                                    </div>
                                </td>
                                <td class="match-venue-cell" data-label="Venue">
                                    <div class="venue-info">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span class="venue-text"><?php echo htmlspecialchars($match['venue_name'] ?? ''); ?></span>
                                    </div>
                                </td>
                                <td class="match-event-cell" data-label="Event">
                                    <span class="event-badge"><?php echo htmlspecialchars($match['sport_type'] ?? ''); ?></span>
                                </td>
                                <td class="match-actions-cell" data-label="Action">
                                    <?php if ($isScheduled): ?>
                                    <a href="match.php?id=<?php echo $match['id']; ?>" class="btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php else: ?>
                                    <a href="match.php?id=<?php echo $match['id']; ?>" class="btn-view">
                                        <i class="fas fa-chart-bar"></i> Report
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalMatches); ?> of <?php echo $totalMatches; ?> entries
                </div>
                
                <nav class="pagination-nav">
                    <?php if ($page > 1): ?>
                    <a href="?page=1&status=<?php echo $status; ?>&event=<?php echo urlencode($eventId); ?>&team=<?php echo $teamId; ?>&per_page=<?php echo $perPage; ?>" class="pagination-link" title="First Page">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&event=<?php echo urlencode($eventId); ?>&team=<?php echo $teamId; ?>&per_page=<?php echo $perPage; ?>" class="pagination-link">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php 
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $startPage + 4);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&event=<?php echo urlencode($eventId); ?>&team=<?php echo $teamId; ?>&per_page=<?php echo $perPage; ?>" 
                       class="pagination-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&event=<?php echo urlencode($eventId); ?>&team=<?php echo $teamId; ?>&per_page=<?php echo $perPage; ?>" class="pagination-link">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                    <a href="?page=<?php echo $totalPages; ?>&status=<?php echo $status; ?>&event=<?php echo urlencode($eventId); ?>&team=<?php echo $teamId; ?>&per_page=<?php echo $perPage; ?>" class="pagination-link" title="Last Page">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                    <?php endif; ?>
                </nav>
                
                <div class="entries-per-page">
                    <label>Show</label>
                    <select id="entriesPerPage" class="entries-select">
                        <option value="20" <?php echo $perPage == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="40" <?php echo $perPage == 40 ? 'selected' : ''; ?>>40</option>
                        <option value="60" <?php echo $perPage == 60 ? 'selected' : ''; ?>>60</option>
                        <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                    <label>entries</label>
                </div>
            </div>
            <?php endif; ?>
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
document.addEventListener('DOMContentLoaded', function() {
    // Filter functionality
    document.getElementById('applyFilter').addEventListener('click', function() {
        const status = document.getElementById('statusFilter').value;
        const eventId = document.getElementById('eventFilter').value;
        const teamId = document.getElementById('teamFilter').value;
        const perPageEl = document.getElementById('entriesPerPage');
        const perPage = perPageEl ? perPageEl.value : '';
        
        let url = '?status=' + status;
        if (eventId && eventId !== '0') url += '&event=' + encodeURIComponent(eventId);
        if (teamId > 0) url += '&team=' + teamId;
        if (perPage > 0) url += '&per_page=' + perPage;
        
        window.location.href = url;
    });
    
    document.getElementById('resetFilter').addEventListener('click', function() {
        window.location.href = '?status=result&per_page=40';
    });
    
    // Entries per page change
    const entriesPerPageSelect = document.getElementById('entriesPerPage');
    if (entriesPerPageSelect) {
        entriesPerPageSelect.addEventListener('change', function() {
            const perPage = this.value;
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('per_page', perPage);
            currentUrl.searchParams.set('page', 1);
            window.location.href = currentUrl.toString();
        });
    }
    
    // Row click functionality
    document.querySelectorAll('.match-row').forEach(row => {
        row.addEventListener('click', function(e) {
            // Jangan redirect jika klik pada tombol
            if (e.target.closest('.btn-view') || e.target.tagName === 'A' || e.target.tagName === 'BUTTON') {
                return;
            }
            
            const matchId = this.dataset.matchId;
            if (matchId) {
                window.location.href = 'match.php?id=' + matchId;
            }
        });
    });
});

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
</script>

<script>
const SITE_URL = '<?php echo SITE_URL; ?>';
</script>
<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
