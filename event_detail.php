<?php
$pageTitle = "Event Detail";
$hideNavbars = true;
require_once 'includes/header.php';

// Get event ID
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($event_id <= 0) {
    header("Location: event.php");
    exit;
}

// Database connection
$conn = $db->getConnection();

// Query for Event Data
$query = "SELECT 
    c.*,
    t1.name as challenger_name, 
    t1.logo as challenger_logo,
    t1.sport_type as challenger_sport,
    t2.name as opponent_name, 
    t2.logo as opponent_logo,
    v.name as venue_name,
    v.location as venue_location,
    v.capacity as venue_capacity,
    w.name as winner_team_name
FROM challenges c
LEFT JOIN teams t1 ON c.challenger_id = t1.id
LEFT JOIN teams t2 ON c.opponent_id = t2.id
LEFT JOIN venues v ON c.venue_id = v.id
LEFT JOIN teams w ON c.winner_team_id = w.id
WHERE c.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();

if (!$event) {
    header("Location: event.php");
    exit;
}

// Helper Functions
function getStatusBadge($status) {
    $status = strtolower($status ?? '');
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
    $match_status = strtolower($match_status ?? '');
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

// Build timeline data
$timeline_events = [];

// Event Created
if ($event['created_at']) {
    $timeline_events[] = [
        'icon' => 'fa-plus-circle',
        'event' => 'Challenge Dibuat',
        'time' => $event['created_at'],
        'color' => '#3b82f6'
    ];
}

// Status changes based on current status
if ($event['status'] == 'accepted' && $event['updated_at'] != $event['created_at']) {
    $timeline_events[] = [
        'icon' => 'fa-check-circle',
        'event' => 'Challenge Diterima',
        'time' => $event['updated_at'],
        'color' => '#10b981'
    ];
}

// Match scheduled (challenge date)
if ($event['challenge_date']) {
    $timeline_events[] = [
        'icon' => 'fa-calendar-check',
        'event' => 'Pertandingan Dijadwalkan',
        'time' => $event['challenge_date'],
        'color' => '#6366f1'
    ];
}

// Result entered
if ($event['result_entered_at']) {
    $timeline_events[] = [
        'icon' => 'fa-futbol',
        'event' => 'Hasil Pertandingan Diinput',
        'time' => $event['result_entered_at'],
        'color' => '#f59e0b'
    ];
}

// Match completed
if ($event['status'] == 'completed') {
    $timeline_events[] = [
        'icon' => 'fa-flag-checkered',
        'event' => 'Pertandingan Selesai',
        'time' => $event['updated_at'],
        'color' => '#22c55e'
    ];
}

// Expiry date
if ($event['expiry_date']) {
    $timeline_events[] = [
        'icon' => 'fa-hourglass-end',
        'event' => 'Batas Waktu Penerimaan',
        'time' => $event['expiry_date'],
        'color' => '#ef4444'
    ];
}

// Sort timeline by time
usort($timeline_events, function($a, $b) {
    return strtotime($a['time']) - strtotime($b['time']);
});
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/event_detail_redesign.css?v=<?php echo time(); ?>">

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
        <header class="dashboard-header dashboard-header-detail">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">MGP</div>
                    <h1>Event Detail</h1>
                    <p class="header-subtitle">Rangkuman lengkap detail pertandingan, status, dan timeline event.</p>
                </div>
                <div class="header-actions">
                    <a href="event.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Kembali ke Event
                    </a>
                </div>
            </div>
            <div class="header-meta">
                <div class="event-code-chip">
                    <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($event['challenge_code'] ?? ''); ?>
                </div>
                <div class="status-badges">
                    <?php echo getStatusBadge($event['status']); ?>
                    <?php echo getMatchStatusBadge($event['match_status']); ?>
                </div>
            </div>
        </header>

        <div class="dashboard-body">
            <section class="event-detail-card">
                <!-- Teams Display -->
                <div class="teams-display">
                    <div class="team-card">
                        <div class="team-logo-container">
                            <?php if (!empty($event['challenger_logo'])): ?>
                                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($event['challenger_logo']); ?>" 
                                     class="team-logo-large"
                                     alt="<?php echo htmlspecialchars($event['challenger_name'] ?? ''); ?>"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                            <?php else: ?>
                                <div class="team-logo-placeholder">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="team-name"><?php echo htmlspecialchars($event['challenger_name'] ?? ''); ?></div>
                    </div>
                    
                    <div class="vs-center">
                        <div class="vs-badge">VS</div>
                        <?php if ($event['challenger_score'] !== null && $event['opponent_score'] !== null): ?>
                            <div class="score-display">
                                <?php echo $event['challenger_score']; ?><span class="score-separator">:</span><?php echo $event['opponent_score']; ?>
                            </div>
                        <?php else: ?>
                            <div class="score-pending">
                                Belum dimainkan
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="team-card">
                        <div class="team-logo-container">
                            <?php if (!empty($event['opponent_logo'])): ?>
                                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($event['opponent_logo']); ?>" 
                                     class="team-logo-large"
                                     alt="<?php echo htmlspecialchars($event['opponent_name'] ?? ''); ?>"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                            <?php else: ?>
                                <div class="team-logo-placeholder">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="team-name"><?php echo htmlspecialchars($event['opponent_name'] ?? 'TBD'); ?></div>
                    </div>
                </div>

                <!-- Winner/Draw Display -->
                <?php if ($event['winner_team_name']): ?>
                    <div class="winner-display">
                        <div class="winner-title">Pemenang Pertandingan</div>
                        <div class="winner-name">
                            <i class="fas fa-trophy"></i>
                            <?php echo htmlspecialchars($event['winner_team_name'] ?? ''); ?>
                            <i class="fas fa-trophy"></i>
                        </div>
                    </div>
                <?php elseif ($event['challenger_score'] !== null && $event['opponent_score'] !== null && $event['challenger_score'] == $event['opponent_score']): ?>
                    <div class="draw-display">
                        <div class="draw-title">Pertandingan Berakhir Seri</div>
                        <div class="draw-text">
                            <i class="fas fa-handshake"></i>
                            DRAW
                            <i class="fas fa-handshake"></i>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Information Grid -->
                <div class="info-grid">
                    <!-- Event Information -->
                    <div class="info-card">
                        <div class="info-title">
                            <i class="fas fa-info-circle"></i> Informasi Event
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-running"></i> Cabang Olahraga</span>
                            <div class="info-value"><?php echo htmlspecialchars($event['sport_type'] ?? ''); ?></div>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-calendar-alt"></i> Tanggal & Waktu</span>
                            <div class="info-value"><?php echo formatDateTime($event['challenge_date']); ?></div>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-hourglass-end"></i> Batas Penerimaan</span>
                            <div class="info-value info-danger"><?php echo formatDateTime($event['expiry_date']); ?></div>
                        </div>
                    </div>

                    <!-- Venue Information -->
                    <div class="info-card">
                        <div class="info-title">
                            <i class="fas fa-map-marker-alt"></i> Informasi Venue
                        </div>
                        <?php if ($event['venue_name']): ?>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-building"></i> Nama Venue</span>
                                <div class="info-value"><?php echo htmlspecialchars($event['venue_name'] ?? ''); ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-location-arrow"></i> Lokasi</span>
                                <div class="info-value"><?php echo htmlspecialchars($event['venue_location'] ?? ''); ?></div>
                            </div>
                            <?php if ($event['venue_capacity']): ?>
                            <div class="info-item">
                                <span class="info-label"><i class="fas fa-users"></i> Kapasitas</span>
                                <div class="info-value"><?php echo number_format($event['venue_capacity']); ?> orang</div>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="info-item">
                                <div class="info-value info-muted">
                                    <i class="fas fa-question-circle"></i> Venue belum ditentukan
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Status Information -->
                    <div class="info-card">
                        <div class="info-title">
                            <i class="fas fa-flag"></i> Status Pertandingan
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-tasks"></i> Challenge Status</span>
                            <div class="info-value"><?php echo getStatusBadge($event['status']); ?></div>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-play-circle"></i> Match Status</span>
                            <div class="info-value"><?php echo getMatchStatusBadge($event['match_status']); ?></div>
                        </div>
                        <?php if ($event['result_entered_at']): ?>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-check-double"></i> Hasil Diinput</span>
                            <div class="info-value info-success">
                                <?php echo formatDateTime($event['result_entered_at']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Match Details (only if scores exist) -->
                    <?php if ($event['challenger_score'] !== null && $event['opponent_score'] !== null): ?>
                    <div class="info-card">
                        <div class="info-title">
                            <i class="fas fa-futbol"></i> Detail Pertandingan
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-chart-line"></i> Skor Akhir</span>
                            <div class="info-value info-score">
                                <?php echo $event['challenger_score']; ?> : <?php echo $event['opponent_score']; ?>
                            </div>
                        </div>
                        <?php if ($event['match_duration']): ?>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-clock"></i> Durasi</span>
                            <div class="info-value"><?php echo htmlspecialchars($event['match_duration']); ?> menit</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($event['match_official']): ?>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-user-tie"></i> Wasit</span>
                            <div class="info-value"><?php echo htmlspecialchars($event['match_official']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Notes -->
                <?php if (!empty($event['notes'])): ?>
                <div class="notes-section">
                    <div class="notes-title">
                        <i class="fas fa-sticky-note"></i> Catatan Challenge
                    </div>
                    <div class="notes-content">
                        <?php echo nl2br(htmlspecialchars($event['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($event['match_notes'])): ?>
                <div class="notes-section">
                    <div class="notes-title">
                        <i class="fas fa-clipboard"></i> Catatan Pertandingan
                    </div>
                    <div class="notes-content">
                        <?php echo nl2br(htmlspecialchars($event['match_notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Timeline -->
                <div class="timeline-section">
                    <div class="timeline-header">
                        <i class="fas fa-history"></i> Timeline Event
                    </div>
                    
                    <div class="timeline">
                        <?php foreach ($timeline_events as $item): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon" style="background: <?php echo $item['color']; ?>;">
                                <i class="fas <?php echo $item['icon']; ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-event"><?php echo $item['event']; ?></div>
                                <div class="timeline-time">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo formatDateTime($item['time']); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
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
