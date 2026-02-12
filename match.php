<?php
// ============================================
// SEMUA LOGIC DAN REDIRECT HARUS SEBELUM OUTPUT
// ============================================

$pageTitle = "Match Details";
$hideNavbars = true;

// Get match ID from URL
$matchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($matchId <= 0) {
    header("Location: index.php");
    exit();
}

$source = isset($_GET['source']) ? $_GET['source'] : 'match';

// Sekarang baru require header
require_once 'includes/header.php'; 

$conn = $db->getConnection();

if ($source === 'challenge') {
    // Get match details from challenges table
    $sql = "SELECT c.id, c.challenge_code as match_code, c.challenge_date as match_date, 
                   c.challenger_score as score1, c.opponent_score as score2,
                   c.match_status as status, v.name as location,
                   t1.id as team1_id, t1.name as team1_name, t1.logo as team1_logo, 
                   t2.id as team2_id, t2.name as team2_name, t2.logo as team2_logo,
                   c.sport_type as event_name
            FROM challenges c
            LEFT JOIN teams t1 ON c.challenger_id = t1.id
            LEFT JOIN teams t2 ON c.opponent_id = t2.id
            LEFT JOIN venues v ON c.venue_id = v.id
            WHERE c.id = ?";
} else {
    // Get match details from matches table
    $sql = "SELECT m.*, 
                   t1.name as team1_name, t1.logo as team1_logo, 
                   t2.name as team2_name, t2.logo as team2_logo,
                   e.name as event_name
            FROM matches m
            LEFT JOIN teams t1 ON m.team1_id = t1.id
            LEFT JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN events e ON m.event_id = e.id
            WHERE m.id = ?";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $matchId);
$stmt->execute();
$result = $stmt->get_result();
$match = $result->fetch_assoc();
$stmt->close();

// Fallback search in challenges if not found in matches and source not specified
if (!$match && $source !== 'challenge') {
    $sql = "SELECT c.id, c.challenge_code as match_code, c.challenge_date as match_date, 
                   c.challenger_score as score1, c.opponent_score as score2,
                   c.match_status as status, v.name as location,
                   t1.id as team1_id, t1.name as team1_name, t1.logo as team1_logo, 
                   t2.id as team2_id, t2.name as team2_name, t2.logo as team2_logo,
                   c.sport_type as event_name
            FROM challenges c
            LEFT JOIN teams t1 ON c.challenger_id = t1.id
            LEFT JOIN teams t2 ON c.opponent_id = t2.id
            LEFT JOIN venues v ON c.venue_id = v.id
            WHERE c.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $result = $stmt->get_result();
    $match = $result->fetch_assoc();
    $stmt->close();
}

$matchNotFound = false;
$lineups = ['team1' => [], 'team2' => []];
$goals = [];

if (!$match) {
    $matchNotFound = true;
} else {
    // Get lineups for this match
    $sqlLineups = "SELECT l.*, p.name as player_name, p.photo, p.jersey_number, t.id as team_id, t.name as team_name
                   FROM lineups l
                   LEFT JOIN players p ON l.player_id = p.id
                   LEFT JOIN teams t ON l.team_id = t.id
                   WHERE l.match_id = ?
                   ORDER BY l.is_starting DESC, p.jersey_number ASC";
    $stmtLineups = $conn->prepare($sqlLineups);
    $stmtLineups->bind_param('i', $matchId);
    $stmtLineups->execute();
    $resultLineups = $stmtLineups->get_result();
    while ($lineup = $resultLineups->fetch_assoc()) {
        if ($lineup['team_id'] == $match['team1_id']) {
            $lineups['team1'][] = $lineup;
        } else if ($lineup['team_id'] == $match['team2_id']) {
            $lineups['team2'][] = $lineup;
        }
    }
    $stmtLineups->close();

    // Get goals for this match
    $goals = getMatchGoals($matchId);
}

$statusValue = '';
$statusLabel = 'TBA';
$statusClass = 'status-tba';
$matchTime = 'TBD';
$locationLabel = 'TBA';
$eventName = '';
$matchCode = '';
$scoreAvailable = false;

if (!$matchNotFound) {
    $statusValue = strtolower($match['status'] ?? '');
    $statusLabel = $statusValue ? ucfirst($statusValue) : 'TBA';
    $statusClass = $statusValue ? 'status-' . preg_replace('/[^a-z0-9]+/', '-', $statusValue) : 'status-tba';
    $matchTime = !empty($match['match_date']) ? date('H:i', strtotime($match['match_date'])) : 'TBD';
    $locationLabel = $match['location'] ?? ($match['venue_name'] ?? 'TBA');
    $eventName = $match['event_name'] ?? '';
    $matchCode = $match['match_code'] ?? '';
    $scoreAvailable = $match['score1'] !== null && $match['score2'] !== null;
}

?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/index_redesign.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/match_redesign.css?v=<?php echo time(); ?>">

<div class="dashboard-wrapper">
    <!-- Mobile Header -->
    <header class="mobile-dashboard-header">
        <div class="mobile-logo">
            <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Buka/Tutup Sidebar" aria-controls="sidebar" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar" aria-hidden="true">
        <div class="sidebar-logo">
            <a href="<?php echo SITE_URL; ?>">
                <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo SITE_URL; ?>"><i class="fas fa-home"></i> <span>BERANDA</span></a>
            <a href="event.php"><i class="fas fa-calendar-alt"></i> <span>EVENT</span></a>
            <a href="team.php"><i class="fas fa-users"></i> <span>TIM</span></a>
            <div class="nav-item-dropdown">
                <a href="#" class="nav-has-dropdown" onclick="toggleDropdown(this, 'playerDropdown'); return false;">
                    <div class="nav-link-content">
                        <i class="fas fa-users"></i> <span>PEMAIN</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <div id="playerDropdown" class="sidebar-dropdown">
                    <a href="player.php">Pemain</a>
                    <a href="staff.php">Staf Tim</a>
                </div>
            </div>
            <a href="news.php"><i class="fas fa-newspaper"></i> <span>BERITA</span></a>
            <a href="bpjs.php"><i class="fas fa-shield-alt"></i> <span>BPJSTK</span></a>
            <a href="contact.php"><i class="fas fa-envelope"></i> <span>KONTAK</span></a>
            
            <div class="sidebar-divider" style="margin: 15px 0; border-top: 1px solid rgba(255,255,255,0.1);"></div>

            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
                <a href="<?php echo ($_SESSION['admin_role'] === 'pelatih' ? SITE_URL.'/pelatih/dashboard.php' : SITE_URL.'/admin/dashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i> <span>DASBOR</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/logout.php" style="color: #e74c3c;">
                    <i class="fas fa-sign-out-alt"></i> <span>KELUAR</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn-login-sidebar">
                    <i class="fas fa-sign-in-alt"></i> <span>MASUK</span>
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content-dashboard">
        <header class="dashboard-header dashboard-header-match">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">FUTSCORE</div>
                    <h1><?php echo $matchNotFound ? 'Pertandingan Tidak Ditemukan' : 'Detail Pertandingan'; ?></h1>
                    <p class="header-subtitle">Detail pertandingan, status, dan susunan pemain dalam tampilan yang rapi dan modern.</p>
                </div>
                <div class="header-actions">
                    <a href="all.php?status=result" class="btn-secondary"><i class="fas fa-list"></i> Semua Pertandingan</a>
                    <a href="event.php" class="btn-primary"><i class="fas fa-calendar-alt"></i> Jelajahi Event</a>
                </div>
            </div>
            <?php if (!$matchNotFound): ?>
            <div class="header-meta">
                <?php if (!empty($eventName)): ?>
                    <span class="meta-chip"><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($eventName ?? ''); ?></span>
                <?php endif; ?>
                <?php if (!empty($matchCode)): ?>
                    <span class="meta-chip"><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($matchCode ?? ''); ?></span>
                <?php endif; ?>
                <span class="meta-chip <?php echo htmlspecialchars($statusClass ?? ''); ?>">
                    <i class="fas fa-circle-info"></i> <?php echo htmlspecialchars($statusLabel ?? ''); ?>
                </span>
            </div>
            <?php endif; ?>
        </header>

        <div class="dashboard-body">
            <?php if ($matchNotFound): ?>
                <section class="section-container">
                    <div class="empty-state">
                        <i class="fas fa-futbol"></i>
                        <h4>Pertandingan tidak ditemukan</h4>
                        <p>Match ID yang kamu cari tidak tersedia.</p>
                    </div>
                </section>
            <?php else: ?>
                <section class="section-container section-elevated match-summary-card">
                    <div class="match-tags">
                        <?php if (!empty($eventName)): ?>
                            <span class="match-tag event-tag"><i class="fas fa-bolt"></i> <?php echo htmlspecialchars($eventName ?? ''); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($matchCode)): ?>
                            <span class="match-tag code-tag"><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($matchCode ?? ''); ?></span>
                        <?php endif; ?>
                        <span class="match-tag status-tag <?php echo htmlspecialchars($statusClass ?? ''); ?>">
                            <i class="fas fa-flag-checkered"></i> <?php echo htmlspecialchars($statusLabel ?? ''); ?>
                        </span>
                    </div>

                    <div class="match-teams-grid">
                        <div class="match-team">
                            <div class="match-team-logo">
                                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>" 
                                     alt="<?php echo htmlspecialchars($match['team1_name'] ?? ''); ?>"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                            </div>
                            <div class="match-team-name"><?php echo htmlspecialchars($match['team1_name'] ?? ''); ?></div>
                        </div>

                        <div class="match-score-block">
                            <?php if ($scoreAvailable): ?>
                                <div class="score-pill"><?php echo $match['score1']; ?> - <?php echo $match['score2']; ?></div>
                                <div class="score-subtext">Skor Akhir</div>
                            <?php else: ?>
                                <div class="vs-pill">VS</div>
                                <div class="score-subtext"><?php echo htmlspecialchars($matchTime ?? ''); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="match-team">
                            <div class="match-team-logo">
                                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>" 
                                     alt="<?php echo htmlspecialchars($match['team2_name'] ?? ''); ?>"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                            </div>
                            <div class="match-team-name"><?php echo htmlspecialchars($match['team2_name'] ?? ''); ?></div>
                        </div>
                    </div>

                    <div class="match-meta-grid">
                        <div class="match-meta-card">
                            <div class="match-meta-icon"><i class="far fa-calendar-alt"></i></div>
                            <div>
                                <div class="match-meta-label">Tanggal & Waktu</div>
                                <div class="match-meta-value"><?php echo formatDateTime($match['match_date']); ?></div>
                            </div>
                        </div>
                        <div class="match-meta-card">
                            <div class="match-meta-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div>
                                <div class="match-meta-label">Lokasi</div>
                                <div class="match-meta-value"><?php echo htmlspecialchars($locationLabel ?? 'TBA'); ?></div>
                            </div>
                        </div>
                        <div class="match-meta-card">
                            <div class="match-meta-icon"><i class="fas fa-circle-info"></i></div>
                            <div>
                                <div class="match-meta-label">Status</div>
                                <div class="match-meta-value">
                                    <span class="status-chip <?php echo htmlspecialchars($statusClass ?? ''); ?>">
                                        <?php echo htmlspecialchars($statusLabel ?? ''); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="section-container lineup-section">
                    <div class="section-header">
                        <h2 class="section-title">GOL</h2>
                    </div>

                    <div class="goals-list pro-goals-container">
                        <?php if (empty($goals)): ?>
                            <div class="no-data">Belum ada gol tercipta</div>
                        <?php else: ?>
                            <?php foreach ($goals as $goal): ?>
                                <?php $isTeam1 = ($goal['team_id'] == $match['team1_id']); ?>
                                <div class="goal-row">
                                    <div class="goal-side team-1-side <?php echo $isTeam1 ? 'active' : ''; ?>">
                                        <?php if ($isTeam1): ?>
                                            <div class="goal-details">
                                                <span class="goal-player-name"><?php echo htmlspecialchars($goal['player_name'] ?? ''); ?></span>
                                                <span class="goal-icon">⚽</span>
                                                <?php if (!empty($goal['jersey_number'])): ?>
                                                    <span class="goal-player-number">(<?php echo htmlspecialchars($goal['jersey_number']); ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="goal-time-pill"><?php echo htmlspecialchars(($goal['minute'] ?? '-') . '"'); ?></div>
                                    <div class="goal-side team-2-side <?php echo !$isTeam1 ? 'active' : ''; ?>">
                                        <?php if (!$isTeam1): ?>
                                            <div class="goal-details">
                                                <span class="goal-player-name"><?php echo htmlspecialchars($goal['player_name'] ?? ''); ?></span>
                                                <span class="goal-icon">⚽</span>
                                                <?php if (!empty($goal['jersey_number'])): ?>
                                                    <span class="goal-player-number">(<?php echo htmlspecialchars($goal['jersey_number']); ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="section-container lineup-section">
                    <div class="section-header">
                        <h2 class="section-title">SUSUNAN PEMAIN TIM</h2>
                        <div class="lineup-legend">
                            <span class="legend-pill"><i class="fas fa-star"></i> Pemain Utama</span>
                        </div>
                    </div>

                    <div class="lineups-grid">
                        <div class="lineup-card">
                            <div class="lineup-card-header">
                                <div class="lineup-team">
                                    <div class="lineup-team-logo">
                                        <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>" 
                                             alt="<?php echo htmlspecialchars($match['team1_name'] ?? ''); ?>"
                                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                    </div>
                                    <div class="lineup-team-info">
                                        <h3 class="lineup-team-name"><?php echo htmlspecialchars($match['team1_name'] ?? ''); ?></h3>
                                        <span class="lineup-count"><?php echo count($lineups['team1']); ?> pemain</span>
                                    </div>
                                </div>
                                <span class="team-side-badge">Kandang</span>
                            </div>

                            <?php if (empty($lineups['team1'])): ?>
                                <div class="empty-state">Belum ada susunan pemain.</div>
                            <?php else: ?>
                                <div class="lineup-list">
                                    <?php foreach ($lineups['team1'] as $player): ?>
                                        <div class="lineup-player">
                                            <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player['photo']; ?>" 
                                                 class="lineup-avatar"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                                            <div class="lineup-info">
                                                <div class="lineup-name"><?php echo htmlspecialchars($player['player_name'] ?? ''); ?></div>
                                                <div class="lineup-meta">
                                                    <span class="lineup-number"><?php echo '#' . $player['jersey_number']; ?></span>
                                                    <span class="lineup-position"><?php echo htmlspecialchars($player['position'] ?? ''); ?></span>
                                                    <?php if ($player['is_starting']): ?>
                                                        <span class="lineup-badge">Pemain Utama</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="lineup-card">
                            <div class="lineup-card-header">
                                <div class="lineup-team">
                                    <div class="lineup-team-logo">
                                        <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>" 
                                             alt="<?php echo htmlspecialchars($match['team2_name'] ?? ''); ?>"
                                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                    </div>
                                    <div class="lineup-team-info">
                                        <h3 class="lineup-team-name"><?php echo htmlspecialchars($match['team2_name'] ?? ''); ?></h3>
                                        <span class="lineup-count"><?php echo count($lineups['team2']); ?> pemain</span>
                                    </div>
                                </div>
                                <span class="team-side-badge away">Tandang</span>
                            </div>

                            <?php if (empty($lineups['team2'])): ?>
                                <div class="empty-state">Belum ada susunan pemain.</div>
                            <?php else: ?>
                                <div class="lineup-list">
                                    <?php foreach ($lineups['team2'] as $player): ?>
                                        <div class="lineup-player">
                                            <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player['photo']; ?>" 
                                                 class="lineup-avatar"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                                            <div class="lineup-info">
                                                <div class="lineup-name"><?php echo htmlspecialchars($player['player_name'] ?? ''); ?></div>
                                                <div class="lineup-meta">
                                                    <span class="lineup-number"><?php echo '#' . $player['jersey_number']; ?></span>
                                                    <span class="lineup-position"><?php echo htmlspecialchars($player['position'] ?? ''); ?></span>
                                                    <?php if ($player['is_starting']): ?>
                                                        <span class="lineup-badge">Pemain Utama</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </div>

         <footer class="dashboard-footer">
            <p>&copy; 2026 ALVETRIX. Semua hak dilindungi.</p>
            <p>
                <a href="<?php echo SITE_URL; ?>">Beranda</a> |
                <a href="contact.php">Kontak</a> |
                <a href="bpjs.php">BPJSTK</a>
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



