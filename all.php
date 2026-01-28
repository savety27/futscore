<?php
// ============================================
// LOGIC SEBELUM OUTPUT
// ============================================
$pageTitle = "All Matches";

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'result';
$eventId = isset($_GET['event']) ? (int)$_GET['event'] : 0;
$teamId = isset($_GET['team']) ? (int)$_GET['team'] : 0;
$week = isset($_GET['week']) ? (int)$_GET['week'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 40;

// Redirect jika ID tidak valid
if ($status !== 'schedule' && $status !== 'result') {
    header("Location: index.php");
    exit();
}

// Sekarang baru require header
require_once 'includes/header.php';

$conn = $db->getConnection();

// Get events for filter dropdown
$events = [];
$eventsSql = "SELECT * FROM events ORDER BY name";
$eventsResult = $conn->query($eventsSql);
while ($event = $eventsResult->fetch_assoc()) {
    $events[] = $event;
}

// Get teams for filter dropdown
$teams = [];
$teamsSql = "SELECT * FROM teams ORDER BY name";
$teamsResult = $conn->query($teamsSql);
while ($team = $teamsResult->fetch_assoc()) {
    $teams[] = $team;
}

// Data hardcoded untuk contoh (gantikan dengan query database nanti)
$matches = [
    [
        'id' => 1,
        'team1_name' => 'PAFCA',
        'team1_logo' => 'PAFCA.png',
        'team2_name' => '014 BUFC',
        'team2_logo' => '014-bufc.png',
        'score1' => 5,
        'score2' => 1,
        'match_date' => '2026-01-25 16:40:00',
        'location' => 'LAP SEPINGGAN PRATAMA',
        'event_name' => 'PL AAFI 2026',
        'status' => 'completed'
    ],
    [
        'id' => 2,
        'team1_name' => 'GENERASI FAB',
        'team1_logo' => 'generasi-fab.png',
        'team2_name' => 'FAMILY FUTSAL BALIKPAPAN',
        'team2_logo' => 'famili-balikpapan.png',
        'score1' => 0,
        'score2' => 4,
        'match_date' => '2026-01-25 15:50:00',
        'location' => 'LAP SEPINGGAN PRATAMA',
        'event_name' => 'PL AAFI 2026',
        'status' => 'completed'
    ],
    [
        'id' => 3,
        'team1_name' => 'KUDA LAUT NUSANTARA',
        'team1_logo' => 'kuda-laut-nusantara.png',
        'team2_name' => 'ANTRI FUTSAL SCHOOL GNR',
        'team2_logo' => 'antri-futsal.png',
        'score1' => 3,
        'score2' => 2,
        'match_date' => '2026-01-25 15:50:00',
        'location' => 'LAP SEPINGGAN PRATAMA',
        'event_name' => 'JTFL',
        'status' => 'completed'
    ],
    [
        'id' => 101,
        'team1_name' => 'PAFCA',
        'team1_logo' => 'PAFCA.png',
        'team2_name' => '014 BUFC',
        'team2_logo' => '014-bufc.png',
        'match_date' => '2026-02-01 10:15:00',
        'location' => 'LAP SEPINGGAN PRATAMA - Lap 2',
        'event_name' => 'PL AAFI 2026',
        'status' => 'scheduled'
    ],
    [
        'id' => 102,
        'team1_name' => 'GENERASI FAB',
        'team1_logo' => 'generasi-fab.png',
        'team2_name' => 'FAMILY FUTSAL BALIKPAPAN',
        'team2_logo' => 'famili-balikpapan.png',
        'match_date' => '2026-02-01 10:45:00',
        'location' => 'LAP SEPINGGAN PRATAMA - Lap 2',
        'event_name' => 'PL AAFI 2026',
        'status' => 'scheduled'
    ],
    [
        'id' => 103,
        'team1_name' => 'KUDA LAUT NUSANTARA',
        'team1_logo' => 'kuda-laut-nusantara.png',
        'team2_name' => 'ANTRI FUTSAL SCHOOL GNR',
        'team2_logo' => 'antri-futsal.png',
        'match_date' => '2026-02-01 10:45:00',
        'location' => 'LAP SEPINGGAN PRATAMA - Lap 2',
        'event_name' => 'JTFL',
        'status' => 'scheduled'
    ],
    [
        'id' => 104,
        'team1_name' => 'APOLLO FUTSAL ACADEMY',
        'team1_logo' => 'apollo futsal.png',
        'team2_name' => 'TWO IN ONE FA',
        'team2_logo' => 'two in one.png',
        'match_date' => '2026-02-01 10:45:00',
        'location' => 'LAP SEPINGGAN PRATAMA - Lap 2',
        'event_name' => 'AAFI TANGGERANG 1',
        'status' => 'scheduled'
    ],
    [
        'id' => 105,
        'team1_name' => 'MESS FUTSAL',
        'team1_logo' => 'mess-futsal.png',
        'team2_name' => 'BAHATI FUTSAL',
        'team2_logo' => 'bahati-futsal.png',
        'match_date' => '2026-02-01 11:15:00',
        'location' => 'Golden Sport Center - Lap 2',
        'event_name' => 'JFTL',
        'status' => 'scheduled'
    ]
];

// Filter matches based on status
$filteredMatches = array_filter($matches, function($match) use ($status) {
    return $match['status'] === ($status === 'schedule' ? 'scheduled' : 'completed');
});

// Pagination
$totalMatches = count($filteredMatches);
$totalPages = ceil($totalMatches / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$paginatedMatches = array_slice($filteredMatches, $offset, $perPage);
?>

<div class="container">
    <div class="all-matches-header">
        <h1>All Matches</h1>
        <div class="matches-filter">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="statusFilter">Show:</label>
                    <select id="statusFilter" class="filter-select">
                        <option value="result" <?php echo $status === 'result' ? 'selected' : ''; ?>>Result</option>
                        <option value="schedule" <?php echo $status === 'schedule' ? 'selected' : ''; ?>>Schedule</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="eventFilter">Event:</label>
                    <select id="eventFilter" class="filter-select">
                        <option value="0">All Events</option>
                        <?php foreach ($events as $event): ?>
                        <option value="<?php echo $event['id']; ?>" <?php echo $eventId == $event['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($event['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="teamFilter">Team:</label>
                    <select id="teamFilter" class="filter-select">
                        <option value="0">All Teams</option>
                        <?php foreach ($teams as $team): ?>
                        <option value="<?php echo $team['id']; ?>" <?php echo $teamId == $team['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($team['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="weekFilter">Week:</label>
                    <select id="weekFilter" class="filter-select">
                        <option value="0">All Weeks</option>
                        <?php for ($i = 1; $i <= 52; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $week == $i ? 'selected' : ''; ?>>
                            Week <?php echo $i; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <button class="btn-apply-filter" id="applyFilter">Apply Filter</button>
                <button class="btn-reset-filter" id="resetFilter">Reset</button>
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
    <div class="all-matches-table">
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
                        $isScheduled = $match['status'] === 'scheduled';
                    ?>
                    <tr class="match-row" data-match-id="<?php echo $match['id']; ?>">
                        <td class="match-number"><?php echo $matchNumber; ?></td>
                        <td class="match-teams-cell">
                            <div class="match-teams-info">
                                <div class="team-info">
                                    <div class="team-logo-wrapper">
                                        <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>" 
                                             alt="<?php echo htmlspecialchars($match['team1_name']); ?>" 
                                             class="team-logo-sm"
                                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                    </div>
                                    <span class="team-name-sm"><?php echo htmlspecialchars($match['team1_name']); ?></span>
                                </div>
                                <div class="vs-sm">VS</div>
                                <div class="team-info">
                                    <div class="team-logo-wrapper">
                                        <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>" 
                                             alt="<?php echo htmlspecialchars($match['team2_name']); ?>" 
                                             class="team-logo-sm"
                                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                    </div>
                                    <span class="team-name-sm"><?php echo htmlspecialchars($match['team2_name']); ?></span>
                                </div>
                            </div>
                        </td>
                        <td class="match-score-cell">
                            <?php if ($isScheduled): ?>
                            <div class="match-status-badge scheduled">SCHEDULE</div>
                            <?php else: ?>
                            <div class="score-info">
                                <span class="score-team"><?php echo $match['score1']; ?></span>
                                <span class="score-separator">-</span>
                                <span class="score-team"><?php echo $match['score2']; ?></span>
                            </div>
                            <div class="match-status-badge completed">FT</div>
                            <?php endif; ?>
                        </td>
                        <td class="match-datetime-cell">
                            <div class="datetime-info">
                                <span class="date-info"><?php echo formatDateTime($match['match_date']); ?></span>
                            </div>
                        </td>
                        <td class="match-venue-cell">
                            <div class="venue-info">
                                <i class="fas fa-map-marker-alt"></i>
                                <span class="venue-text"><?php echo htmlspecialchars($match['location']); ?></span>
                            </div>
                        </td>
                        <td class="match-event-cell">
                            <span class="event-badge"><?php echo htmlspecialchars($match['event_name']); ?></span>
                        </td>
                        <td class="match-actions-cell">
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
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrapper">
        <div class="pagination-info">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalMatches); ?> of <?php echo $totalMatches; ?> entries
        </div>
        
        <nav class="pagination-nav">
            <?php if ($page > 1): ?>
            <a href="?page=1&status=<?php echo $status; ?>&event=<?php echo $eventId; ?>&team=<?php echo $teamId; ?>&week=<?php echo $week; ?>&per_page=<?php echo $perPage; ?>" class="pagination-link" title="First Page">
                <i class="fas fa-angle-double-left"></i>
            </a>
            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&event=<?php echo $eventId; ?>&team=<?php echo $teamId; ?>&week=<?php echo $week; ?>&per_page=<?php echo $perPage; ?>" class="pagination-link">
                <i class="fas fa-chevron-left"></i> Previous
            </a>
            <?php endif; ?>
            
            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $startPage + 4);
            
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
            <a href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&event=<?php echo $eventId; ?>&team=<?php echo $teamId; ?>&week=<?php echo $week; ?>&per_page=<?php echo $perPage; ?>" 
               class="pagination-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&event=<?php echo $eventId; ?>&team=<?php echo $teamId; ?>&week=<?php echo $week; ?>&per_page=<?php echo $perPage; ?>" class="pagination-link">
                Next <i class="fas fa-chevron-right"></i>
            </a>
            <a href="?page=<?php echo $totalPages; ?>&status=<?php echo $status; ?>&event=<?php echo $eventId; ?>&team=<?php echo $teamId; ?>&week=<?php echo $week; ?>&per_page=<?php echo $perPage; ?>" class="pagination-link" title="Last Page">
                <i class="fas fa-angle-double-right"></i>
            </a>
            <?php endif; ?>
        </nav>
        
        <div class="entries-per-page">
            <label>Show </label>
            <select id="entriesPerPage" class="entries-select">
                <option value="20" <?php echo $perPage == 20 ? 'selected' : ''; ?>>20</option>
                <option value="40" <?php echo $perPage == 40 ? 'selected' : ''; ?>>40</option>
                <option value="60" <?php echo $perPage == 60 ? 'selected' : ''; ?>>60</option>
                <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
            </select>
            <label> entries</label>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<style>
/* Additional CSS for all.php */
.all-matches-header {
    margin: 30px 0;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--primary-green);
}

.all-matches-header h1 {
    color: var(--primary-green);
    margin-bottom: 20px;
    font-size: 28px;
}

.matches-filter {
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 10px;
    border: 1px solid var(--gray);
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
}

.filter-group {
    flex: 1;
    min-width: 180px;
}

.filter-group label {
    display: block;
    color: var(--white);
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
}

.filter-select {
    width: 100%;
    padding: 10px 15px;
    background-color: var(--black);
    border: 1px solid var(--gray);
    border-radius: 6px;
    color: var(--white);
    font-family: 'Montserrat', sans-serif;
    font-size: 14px;
    transition: all 0.3s ease;
}

.filter-select:focus {
    border-color: var(--primary-green);
    outline: none;
    box-shadow: 0 0 0 2px rgba(0, 255, 136, 0.2);
}

.btn-apply-filter,
.btn-reset-filter {
    padding: 12px 24px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    font-size: 14px;
}

.btn-apply-filter {
    background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
    color: var(--black);
}

.btn-apply-filter:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 255, 136, 0.3);
}

.btn-reset-filter {
    background-color: transparent;
    color: var(--white);
    border: 1px solid var(--gray);
}

.btn-reset-filter:hover {
    background-color: rgba(255, 255, 255, 0.1);
    border-color: var(--primary-green);
}

.all-matches-table {
    margin: 30px 0;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid var(--dark-green);
    background-color: var(--gray-dark);
}

.matches-table {
    width: 100%;
    border-collapse: collapse;
}

.matches-table th {
    background-color: var(--black);
    color: var(--primary-green);
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    padding: 16px 12px;
    text-align: left;
    border-bottom: 2px solid var(--primary-green);
    white-space: nowrap;
}

.matches-table td {
    padding: 16px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    color: var(--white);
    font-size: 14px;
    vertical-align: middle;
    transition: background-color 0.2s ease;
}

.matches-table tr:last-child td {
    border-bottom: none;
}

.matches-table tr:hover {
    background-color: rgba(0, 255, 136, 0.05);
}

.matches-table tr:hover td {
    background-color: rgba(0, 255, 136, 0.03);
}

/* Column widths */
.col-no { width: 60px; text-align: center; }
.col-match { min-width: 250px; }
.col-score { width: 120px; }
.col-datetime { width: 180px; }
.col-venue { width: 200px; }
.col-event { width: 150px; }
.col-action { width: 100px; text-align: center; }

/* Match cells */
.match-teams-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.team-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
    min-width: 0;
}

.team-logo-wrapper {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.team-logo-sm {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: contain;
    border: 2px solid var(--dark-green);
    padding: 2px;
    background-color: var(--black);
    transition: transform 0.3s ease;
}

.match-row:hover .team-logo-sm {
    transform: scale(1.1);
    border-color: var(--primary-green);
}

.team-name-sm {
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 120px;
    color: var(--white);
}

.vs-sm {
    color: var(--primary-green);
    font-size: 12px;
    font-weight: 700;
    padding: 0 8px;
    flex-shrink: 0;
}

/* Score cell */
.match-score-cell {
    text-align: center;
}

.score-info {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    font-size: 22px;
    font-weight: 800;
    margin-bottom: 6px;
    color: var(--white);
}

.score-team {
    min-width: 30px;
    text-align: center;
}

.score-separator {
    color: var(--primary-green);
    font-weight: bold;
}

.match-status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.match-status-badge.completed {
    background-color: var(--dark-green);
    color: var(--white);
}

.match-status-badge.scheduled {
    background-color: #f39c12;
    color: var(--black);
}

/* Date time cell */
.datetime-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.date-info {
    font-weight: 600;
    font-size: 14px;
    color: var(--white);
}

/* Venue cell */
.venue-info {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 13px;
    line-height: 1.4;
}

.venue-info i {
    color: var(--primary-green);
    font-size: 14px;
    flex-shrink: 0;
    margin-top: 2px;
}

.venue-text {
    color: var(--gray-light);
    word-wrap: break-word;
    max-width: 180px;
}

/* Event cell */
.event-badge {
    display: inline-block;
    background-color: rgba(0, 255, 136, 0.1);
    color: var(--primary-green);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
}

/* Actions cell */
.btn-view {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
    color: var(--black);
    text-decoration: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-view:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 255, 136, 0.3);
}

.btn-view i {
    font-size: 12px;
}

/* Pagination */
.pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 30px;
    padding: 20px;
    background-color: rgba(255, 255, 255, 0.03);
    border-radius: 8px;
    border: 1px solid var(--gray);
    flex-wrap: wrap;
    gap: 15px;
}

.pagination-info {
    color: var(--gray-light);
    font-size: 14px;
}

.pagination-nav {
    display: flex;
    gap: 5px;
    align-items: center;
}

.pagination-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 14px;
    background-color: var(--black);
    color: var(--white);
    text-decoration: none;
    border-radius: 6px;
    border: 1px solid var(--gray);
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.pagination-link:hover {
    background-color: var(--primary-green);
    color: var(--black);
    border-color: var(--primary-green);
}

.pagination-link.active {
    background-color: var(--primary-green);
    color: var(--black);
    border-color: var(--primary-green);
}

.pagination-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-link.disabled:hover {
    background-color: var(--black);
    color: var(--white);
    border-color: var(--gray);
}

.entries-per-page {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--white);
    font-size: 14px;
}

.entries-select {
    padding: 8px 12px;
    background-color: var(--black);
    border: 1px solid var(--gray);
    border-radius: 4px;
    color: var(--white);
    font-family: 'Montserrat', sans-serif;
}

.table-responsive {
    overflow-x: auto;
}

@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        min-width: 100%;
    }
    
    .pagination-wrapper {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
    }
    
    .pagination-nav {
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .entries-per-page {
        justify-content: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Filter functionality
    document.getElementById('applyFilter').addEventListener('click', function() {
        const status = document.getElementById('statusFilter').value;
        const eventId = document.getElementById('eventFilter').value;
        const teamId = document.getElementById('teamFilter').value;
        const week = document.getElementById('weekFilter').value;
        const perPage = document.getElementById('entriesPerPage').value;
        
        let url = '?status=' + status;
        if (eventId > 0) url += '&event=' + eventId;
        if (teamId > 0) url += '&team=' + teamId;
        if (week > 0) url += '&week=' + week;
        if (perPage > 0) url += '&per_page=' + perPage;
        
        window.location.href = url;
    });
    
    document.getElementById('resetFilter').addEventListener('click', function() {
        window.location.href = '?status=result&per_page=40';
    });
    
    // Entries per page change
    document.getElementById('entriesPerPage').addEventListener('change', function() {
        const perPage = this.value;
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('per_page', perPage);
        currentUrl.searchParams.set('page', 1);
        window.location.href = currentUrl.toString();
    });
    
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
</script>

<?php require_once 'includes/footer.php'; ?>