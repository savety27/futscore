<?php
$hideNavbars = true;
require_once 'includes/header.php';

// Check if viewing a specific team
$teamId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($teamId > 0) {
    // TEAM DETAIL VIEW
    $team = getTeamById($teamId);
    
    if (!$team) {
        header('Location: ' . SITE_URL . '/team.php');
        exit;
    }
    
    // Get players and staff
    $players = getPlayersByTeamId($teamId);
    $staff = getTeamStaffByTeamId($teamId);
    
    // SMART MATCH: Get event participation (Official & Team History)
    $conn = $db->getConnection();
    
    // Optimized Query: Combines all sources and matches with official events in one go
    // This structure preserves multiple IDs for the same name while deduplicating identical events
    $participationSql = "
        SELECT 
            COALESCE(e.name, matched.event_name) as event_name,
            e.id as event_id,
            e.category,
            e.start_date,
            e.end_date,
            e.location,
            e.image,
            CASE WHEN e.id IS NOT NULL THEN 1 ELSE 0 END as is_official
        FROM (
            SELECT 
                MAX(s.source_name) as event_name,
                s.best_id
            FROM (
                SELECT 
                    s.source_name,
                    COALESCE(s.source_id, (SELECT id FROM events WHERE s.source_name LIKE CONCAT('%', name, '%') OR name LIKE CONCAT('%', s.source_name, '%') ORDER BY start_date DESC, id DESC LIMIT 1)) as best_id
                FROM (
                    SELECT e.name as source_name, e.id as source_id 
                    FROM events e 
                    JOIN event_team_values etv ON e.id = etv.event_id 
                    WHERE etv.team_id = ?
                    
                    UNION
                    
                    SELECT sport_type as source_name, event_id as source_id 
                    FROM challenges 
                    WHERE (challenger_id = ? OR opponent_id = ?) 
                      AND sport_type IS NOT NULL AND sport_type <> ''
                      
                    UNION
                    
                    SELECT event_name as source_name, NULL as source_id 
                    FROM team_events 
                    WHERE team_id = ?
                ) s
            ) s
            GROUP BY s.best_id, (CASE WHEN s.best_id IS NULL THEN s.source_name ELSE '' END)
        ) matched
        LEFT JOIN events e ON matched.best_id = e.id
        ORDER BY is_official DESC, e.start_date DESC
    ";
    
    $pStmt = $conn->prepare($participationSql);
    $pStmt->bind_param("iiii", $teamId, $teamId, $teamId, $teamId);
    $pStmt->execute();
    $pResult = $pStmt->get_result();
    
    $participations = [];
    $events = []; // For roster tabs
    
    while ($row = $pResult->fetch_assoc()) {
        $isOfficial = (bool)$row['is_official'];
        
        $participations[] = [
            'event_id' => $row['event_id'],
            'event_name' => $row['event_name'],
            'category' => $row['category'] ?? ($isOfficial ? 'Official Event' : 'Tournament / Cabor'),
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'location' => $row['location'] ?? ($isOfficial ? 'Tournament Venue' : 'Team Record'),
            'image' => $row['image'],
            'is_official' => $isOfficial
        ];
        
        // Populate events list for tabs (replaces the second query)
        $events[] = [
            'event_key' => $row['event_name'],
            'event_name' => $row['event_name']
        ];
    }
    
    $pageTitle = $team['name'];
} else {
    // TEAM LISTING VIEW
    $allTeams = getAllTeams();
    $pageTitle = "Teams";
}
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/index_redesign.css?v=<?php echo time(); ?>">
<style>
.event-badge {
    color: #0f172a !important;
    background: #e2e8f0 !important;
    border: 1px solid #cbd5e1 !important;
    white-space: normal !important;
    word-break: break-word !important;
    overflow-wrap: anywhere !important;
    max-width: 100%;
    line-height: 1.3;
}

.event-meta-label {
    color: #0f172a !important;
}

/* Participation Table Styles */
.participation-section {
    margin-top: 30px;
    margin-bottom: 30px;
}

.participation-table-container {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
}

.participation-row {
    display: flex;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s ease;
    text-decoration: none;
    color: inherit;
}

.participation-row:last-child {
    border-bottom: none;
}

.participation-row.is-link:hover {
    background-color: #f8fafc;
    transform: translateX(4px);
}

.participation-event-info {
    display: flex;
    align-items: center;
    flex: 1;
    gap: 16px;
}

.participation-event-logo {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    object-fit: contain;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 4px;
}

.participation-event-details {
    display: flex;
    flex-direction: column;
}

.participation-event-name {
    font-weight: 700;
    color: #1e293b;
    font-size: 15px;
    margin-bottom: 2px;
}

.participation-event-category {
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.status-pill-small {
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}
.status-official { background: #dcfce7; color: #166534; }
.status-history { background: #f1f5f9; color: #64748b; }

.participation-meta {
    display: flex;
    gap: 32px;
    align-items: center;
}

.participation-meta-item {
    display: flex;
    flex-direction: column;
    min-width: 120px;
}

.participation-meta-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #94a3b8;
    margin-bottom: 4px;
    font-weight: 700;
}

.participation-meta-value {
    font-size: 13px;
    color: #334155;
    font-weight: 600;
}

.participation-arrow {
    color: #cbd5e1;
    margin-left: 16px;
    transition: transform 0.2s ease;
}

.participation-row.is-link:hover .participation-arrow {
    color: #2563eb;
    transform: translateX(4px);
}

@media (max-width: 768px) {
    .participation-meta {
        display: none;
    }
    .participation-row {
        padding: 12px 16px;
    }
}
</style>

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
            <a href="events.php"><i class="fas fa-calendar-alt"></i> <span>EVENT</span></a>
            <a href="all.php"><i class="fas fa-trophy"></i> <span>CHALLENGE</span></a>
            <a href="team.php" class="active"><i class="fas fa-users"></i> <span>TEAM</span></a>
            <div class="nav-item-dropdown">
                <a href="#" class="nav-has-dropdown" onclick="toggleDropdown(this, 'playerDropdown'); return false;">
                    <div class="nav-link-content">
                        <i class="fas fa-users"></i> <span>PEMAIN</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <div id="playerDropdown" class="sidebar-dropdown">
                    <a href="player.php">Pemain</a>
                    <a href="staff.php">Staf Team</a>
                    <a href="perangkat.php">Perangkat Pertandingan</a>
                </div>
            </div>
            <a href="news.php"><i class="fas fa-newspaper"></i> <span>BERITA</span></a>
            <a href="bpjs.php"><i class="fas fa-shield-alt"></i> <span>BPJSTK</span></a>
            <a href="contact.php"><i class="fas fa-envelope"></i> <span>KONTAK</span></a>
            
            <div class="sidebar-divider" style="margin: 15px 0; border-top: 1px solid rgba(255,255,255,0.1);"></div>

            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
                <a href="<?php echo ($_SESSION['admin_role'] === 'pelatih' ? SITE_URL.'/pelatih/dashboard.php' : SITE_URL.'/admin/dashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i> <span>DASHBOARD</span>
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
    <div class="main-content-dashboard">
        <header class="dashboard-header dashboard-header-home dashboard-header-team">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">ALVETRIX</div>
                    <h1>
                        <?php if ($teamId > 0): ?>
                            <?php echo htmlspecialchars($team['name'] ?? ''); ?>
                        <?php else: ?>
                            Direktori Team
                        <?php endif; ?>
                    </h1>
                    <p class="header-subtitle">
                        <?php if ($teamId > 0): ?>
                            Profil team, roster pemain, dan staff resmi dalam satu tampilan premium.
                        <?php else: ?>
                            Jelajahi daftar team futsal terbaru dan temukan profil lengkapnya.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="header-actions">
                    <?php if ($teamId > 0): ?>
                        <a href="team.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Semua Tim</a>
                        <button class="btn-primary" type="button" onclick="shareTeam()"><i class="fas fa-share-alt"></i> Bagikan Tim</button>
                    <?php else: ?>
                        <a href="event.php" class="btn-secondary"><i class="fas fa-calendar-alt"></i> Lihat Event</a>
                        <a href="player.php" class="btn-primary"><i class="fas fa-users"></i> Lihat Player</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="dashboard-body">
            <?php if ($teamId > 0): ?>
                <!-- TEAM DETAIL VIEW -->
                <div class="container section-container section-elevated team-profile-section">
                    <div class="section-header">
                        <h2 class="section-title">PROFIL TEAM</h2>
                        <div class="section-tabs">
                            <span class="team-status-pill"><i class="fas fa-shield-alt"></i> Terverifikasi</span>
                            <span class="team-status-pill"><i class="fas fa-users"></i> <?php echo count($players); ?> Pemain</span>
                            <span class="team-status-pill"><i class="fas fa-user-tie"></i> <?php echo count($staff); ?> Staf</span>
                            <?php if (!empty($events)): ?>
                                <span class="team-status-pill"><i class="fas fa-calendar-alt"></i> <?php echo count($events); ?> Event</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="team-profile-card">
                        <div class="team-profile-identity">
                            <div class="team-logo-shell">
                                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($team['logo'] ?? ''); ?>" 
                                     alt="<?php echo htmlspecialchars($team['name'] ?? ''); ?>" 
                                     class="team-logo-profile"
                                     onerror="this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                            </div>
                            <div class="team-profile-text">
                                <h2 class="team-name-display"><?php echo htmlspecialchars($team['name'] ?? ''); ?></h2>
                                <?php if (!empty($team['established_year'])): ?>
                                    <?php
                                        $timestamp = strtotime($team['established_year']);
                                        $established_display = $timestamp ? date('d M Y', $timestamp) : $team['established_year'];
                                    ?>
                                    <p class="team-subtitle">Didirikan sejak <?php echo htmlspecialchars($established_display); ?></p>
                                <?php endif; ?>
                                <p class="team-tagline">Fokus pada pembinaan, prestasi, dan pengembangan pemain terbaik.</p>
                            </div>
                        </div>

                        <div class="team-meta-grid">
                            <?php if (!empty($team['manager']) || !empty($team['coach'])): ?>
                            <div class="team-meta-item">
                                <div class="team-meta-label">Manager</div>
                                <div class="team-meta-value">
                                    <?php 
                                    $managerCoach = [];
                                    if (!empty($team['manager'])) $managerCoach[] = htmlspecialchars($team['manager'] ?? '');
                                    if (!empty($team['coach'])) $managerCoach[] = htmlspecialchars($team['coach'] ?? '');
                                    echo implode(' / ', $managerCoach);
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($team['basecamp'])): ?>
                            <div class="team-meta-item">
                                <div class="team-meta-label">Basecamp</div>
                                <div class="team-meta-value"><?php echo htmlspecialchars($team['basecamp'] ?? ''); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($team['contact'])): ?>
                            <div class="team-meta-item">
                                <div class="team-meta-label">Kontak</div>
                                <div class="team-meta-value"><?php echo htmlspecialchars($team['contact'] ?? ''); ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($team['events_array'])): ?>
                            <div class="team-meta-item">
                                <div class="team-meta-label event-meta-label">Cabor / Event</div>
                                <div class="team-meta-value">
                                    <div class="event-badges-container" style="display: flex; flex-wrap: wrap; gap: 5px;">
                                        <?php foreach ($team['events_array'] as $event_name): ?>
                                            <span class="event-badge"><?php echo htmlspecialchars($event_name); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (empty($team['manager']) && empty($team['coach']) && empty($team['basecamp']) && empty($team['contact']) && empty($team['events_array'])): ?>
                            <div class="team-meta-item team-meta-empty">
                                <div class="team-meta-label">Informasi Team</div>
                                <div class="team-meta-value">Belum ada data tambahan</div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($participations)): ?>
                <!-- EVENT PARTICIPATION SECTION -->
                <div class="container section-container participation-section">
                    <div class="section-header">
                        <h2 class="section-title">RIWAYAT PARTISIPASI</h2>
                    </div>
                    
                    <div class="participation-table-container">
                        <?php foreach ($participations as $p): ?>
                            <?php 
                                $isLink = !empty($p['event_id']);
                                $rowTag = $isLink ? 'a' : 'div';
                                $rowAttr = $isLink ? 'href="events.php?id=' . (int)$p['event_id'] . '"' : '';
                            ?>
                            <<?php echo $rowTag; ?> <?php echo $rowAttr; ?> class="participation-row <?php echo $isLink ? 'is-link' : ''; ?>">
                                <div class="participation-event-info">
                                    <img src="<?php echo SITE_URL; ?>/images/events/<?php echo htmlspecialchars(!empty($p['image']) ? $p['image'] : 'default-event.png'); ?>" 
                                         alt="<?php echo htmlspecialchars($p['event_name']); ?>" 
                                         class="participation-event-logo"
                                         onerror="this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                                    <div class="participation-event-details">
                                        <span class="participation-event-name"><?php echo htmlspecialchars($p['event_name']); ?></span>
                                        <div class="participation-event-category">
                                            <span><?php echo htmlspecialchars($p['category']); ?></span>
                                            <?php if($p['is_official']): ?>
                                                <span class="status-pill-small status-official">Official</span>
                                            <?php else: ?>
                                                <span class="status-pill-small status-history">History</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="participation-meta">
                                    <div class="participation-meta-item">
                                        <span class="participation-meta-label">PERIODE</span>
                                        <span class="participation-meta-value">
                                            <?php 
                                            if (!empty($p['start_date'])) {
                                                $start = strtotime($p['start_date']);
                                                $end = !empty($p['end_date']) ? strtotime($p['end_date']) : null;
                                                echo date('d M', $start) . ($end ? ' - ' . date('d M Y', $end) : date(' Y', $start));
                                            } else {
                                                echo 'Records Found';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="participation-meta-item">
                                        <span class="participation-meta-label">LOKASI</span>
                                        <span class="participation-meta-value"><?php echo htmlspecialchars($p['location'] ?? '-'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="participation-arrow">
                                    <?php if($isLink): ?>
                                        <i class="fas fa-chevron-right"></i>
                                    <?php else: ?>
                                        <i class="fas fa-info-circle" style="opacity: 0.3;"></i>
                                    <?php endif; ?>
                                </div>
                            </<?php echo $rowTag; ?>>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="container section-container team-roster-section">
                    <div class="section-header">
                        <h2 class="section-title">DAFTAR</h2>
                        <div class="section-tabs team-roster-tabs">
                            <button class="tab-button player-tab active" data-tab="players">Semua Pemain</button>
                            <button class="tab-button player-tab" data-tab="staff">Pelatih / Staf</button>
                        </div>
                    </div>
                    
                    <div class="player-content team-roster-grid">
                        <!-- Player List Container -->
                        <div class="player-list" id="playerList">
                            <!-- Content will be loaded dynamically -->
                        </div>
                        
                        <!-- Player Detail Panel -->
                        <div class="player-detail-panel" id="playerDetailPanel">
                            <div class="empty-state">
                                <i class="fas fa-user"></i>
                                <p>Pilih pemain atau staff untuk melihat detail</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <script>
                // Data from PHP
                const playersData = <?php echo json_encode($players); ?>;
                const staffData = <?php echo json_encode($staff); ?>;
                
                // Current active tab
                let currentTab = 'players';
                let currentType = 'player'; // 'player' or 'staff'
                
                // Tab translations for staff positions
                const positionTranslations = {
                    'manager': 'Manager',
                    'headcoach': 'Pelatih Kepala',
                    'coach': 'Pelatih',
                    'goalkeeper_coach': 'Pelatih Kiper',
                    'medic': 'Medis',
                    'official': 'Official'
                };
                
                // Position icons
                const positionIcons = {
                    'manager': 'fa-user-tie',
                    'headcoach': 'fa-clipboard-list',
                    'coach': 'fa-whistle',
                    'goalkeeper_coach': 'fa-hands',
                    'medic': 'fa-stethoscope',
                    'official': 'fa-id-card',
                    'default': 'fa-user'
                };
                
                // Function to get correct photo URL for staff
                function getStaffPhotoUrl(photo) {
                    if (!photo) {
                        return '<?php echo SITE_URL; ?>/images/staff/default-staff.jpg';
                    }
                    
                    // Check if photo is already a full path or contains uploads/
                    if (photo.includes('http://') || photo.includes('https://')) {
                        return photo;
                    } else if (photo.startsWith('uploads/')) {
                        return '<?php echo SITE_URL; ?>/' + photo;
                    } else if (photo.startsWith('/')) {
                        return '<?php echo SITE_URL; ?>' + photo;
                    } else {
                        return '<?php echo SITE_URL; ?>/images/staff/' + photo;
                    }
                }
                
                // Function to get correct photo URL for players
                function getPlayerPhotoUrl(photo) {
                    if (!photo) {
                        return '<?php echo SITE_URL; ?>/images/players/default-player.jpg';
                    }
                    
                    // Check if photo is already a full path or contains uploads/
                    if (photo.includes('http://') || photo.includes('https://')) {
                        return photo;
                    } else if (photo.startsWith('uploads/')) {
                        return '<?php echo SITE_URL; ?>/' + photo;
                    } else if (photo.startsWith('/')) {
                        return '<?php echo SITE_URL; ?>' + photo;
                    } else {
                        return '<?php echo SITE_URL; ?>/images/players/' + photo;
                    }
                }
                
                const teamShareName = <?php echo json_encode($team['name'] ?? ''); ?>;

                // Initialize on page load
                document.addEventListener('DOMContentLoaded', function() {
                    // Load players by default
                    loadPlayers();
                    
                    // Set up tab click handlers
                    document.querySelectorAll('.player-tab').forEach(tab => {
                        tab.addEventListener('click', function() {
                            // Remove active class from all tabs
                            document.querySelectorAll('.player-tab').forEach(t => {
                                t.classList.remove('active');
                            });
                            
                            // Add active class to clicked tab
                            this.classList.add('active');
                            
                            // Get tab type
                            const tabType = this.getAttribute('data-tab');

                            if (tabType === 'staff') {
                                currentTab = 'staff';
                                loadStaff();
                            } else {
                                currentTab = 'players';
                                loadPlayers();
                            }
                        });
                    });

                    // Share modal handlers
                    const teamShareModal = document.getElementById('teamShareModal');
                    const teamShareClose = document.getElementById('closeTeamShareModal');
                    const teamShareCopy = document.getElementById('teamShareCopy');

                    if (teamShareClose) {
                        teamShareClose.addEventListener('click', closeTeamShareModal);
                    }

                    if (teamShareModal) {
                        teamShareModal.addEventListener('click', function(event) {
                            if (event.target === teamShareModal) {
                                closeTeamShareModal();
                            }
                        });
                    }

                    if (teamShareCopy) {
                        teamShareCopy.addEventListener('click', function() {
                            copyTeamShareLink(teamShareCopy);
                        });
                    }

                    document.addEventListener('keydown', function(event) {
                        if (event.key === 'Escape') {
                            if (teamShareModal && teamShareModal.style.display === 'block') {
                                closeTeamShareModal();
                            }
                        }
                    });
                });
                
                function loadPlayers() {
                    currentType = 'player';
                    const playerList = document.getElementById('playerList');
                    const detailPanel = document.getElementById('playerDetailPanel');
                    
                    if (playersData.length === 0) {
                        playerList.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h4>Tidak ada pemain</h4>
                                <p>Belum ada pemain yang terdaftar di team ini</p>
                            </div>
                        `;
                        showEmptyDetailPanel();
                        return;
                    }
                    
                    let html = '';
                    playersData.forEach((player, index) => {
                        const nameInitial = getNameInitial(player.name);
                        
                        html += `
                            <div class="player-item ${index === 0 ? 'active' : ''}" 
                                 onclick="showPlayerDetail(${JSON.stringify(player).replace(/\"/g, '&quot;')}, this)">
                                <div class="position-badge">
                                    ${nameInitial}
                                </div>
                                <div class="player-item-info">
                                    <span class="player-number">${player.jersey_number || '-'}.</span>
                                    <span class="player-name">${escapeHtml(player.name)}</span>
                                </div>
                            </div>
                        `;
                    });
                    
                    playerList.innerHTML = html;
                    
                    // Show first player's detail
                    if (playersData.length > 0) {
                        showPlayerDetail(playersData[0]);
                    }
                }
                
                function loadStaff() {
                    currentType = 'staff';
                    const playerList = document.getElementById('playerList');
                    const detailPanel = document.getElementById('playerDetailPanel');
                    
                    if (staffData.length === 0) {
                        playerList.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-users-cog"></i>
                                <h4>Tidak ada staff</h4>
                                <p>Belum ada staff yang terdaftar di team ini</p>
                            </div>
                        `;
                        showEmptyDetailPanel();
                        return;
                    }
                    
                    let html = '';
                    staffData.forEach((staff, index) => {
                        const nameInitial = getNameInitial(staff.name);
                        
                        html += `
                            <div class="player-item ${index === 0 ? 'active' : ''}" 
                                 onclick="showStaffDetail(${JSON.stringify(staff).replace(/\"/g, '&quot;')}, this)">
                                <div class="position-badge staff">
                                    ${nameInitial}
                                </div>
                                <div class="player-item-info">
                                    <span class="player-name">${escapeHtml(staff.name)}</span>
                                </div>
                            </div>
                        `;
                    });
                    
                    playerList.innerHTML = html;
                    
                    // Show first staff's detail
                    if (staffData.length > 0) {
                        showStaffDetail(staffData[0]);
                    }
                }
                
                function showPlayerDetail(player, element = null) {
                    // Update active state
                    document.querySelectorAll('.player-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    if (element) {
                        element.classList.add('active');
                    }
                    
                    // Calculate months for age
                    let months = 0;
                    if (player.birth_date && player.birth_date !== '0000-00-00') {
                        const birth = new Date(player.birth_date);
                        const today = new Date();
                        months = today.getMonth() - birth.getMonth();
                        if (months < 0) months += 12;
                    }
                    
                    // Format birth info
                    const birthInfo = [];
                    if (player.birth_place) birthInfo.push(escapeHtml(player.birth_place));
                    if (player.birth_date && player.birth_date !== '0000-00-00') {
                        const date = new Date(player.birth_date);
                        birthInfo.push(date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }));
                    }
                    
                    // Get correct photo URL
                    const photoUrl = getPlayerPhotoUrl(player.photo);
                    
                    const detailPanel = document.getElementById('playerDetailPanel');
                    detailPanel.innerHTML = `
                        <div class="player-photo-container">
                            <img src="${photoUrl}" 
                                 alt="${escapeHtml(player.name)}" 
                                 class="player-photo-large"
                                 onerror="this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                        </div>
                        
                        <div class="player-detail-info">
                            <div class="info-row">
                                <span class="info-label">KEBANGSAAN:</span>
                                <span class="info-value">${player.nationality || 'Indonesia'}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">NISN:</span>
                                <span class="info-value">${player.nisn || '-'}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">TMP/TGL LAHIR:</span>
                                <span class="info-value">${birthInfo.join(', ') || '-'}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">USIA:</span>
                                <span class="info-value">${player.age || '0'} Tahun ${months} Bulan</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">POSISI:</span>
                                <span class="info-value">${player.position || '-'}</span>
                            </div>
                        </div>
                    `;
                }
                
                function showStaffDetail(staff, element = null) {
                    // Update active state
                    document.querySelectorAll('.player-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    if (element) {
                        element.classList.add('active');
                    }
                    
                    // Format staff info
                    const position = staff.position || 'staff';
                    const icon = positionIcons[position] || positionIcons.default;
                    const positionText = positionTranslations[position] || position;
                    
                    // Format birth info
                    let birthDateFormatted = '-';
                    let age = '-';
                    if (staff.birth_date && staff.birth_date !== '0000-00-00') {
                        const date = new Date(staff.birth_date);
                        birthDateFormatted = date.toLocaleDateString('id-ID', { 
                            day: '2-digit', 
                            month: 'long', 
                            year: 'numeric' 
                        });
                        
                        // Calculate age
                        const today = new Date();
                        const birth = new Date(staff.birth_date);
                        let ageYears = today.getFullYear() - birth.getFullYear();
                        const monthDiff = today.getMonth() - birth.getMonth();
                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                            ageYears--;
                        }
                        age = ageYears + ' Tahun';
                    }
                    
                    // Get correct photo URL
                    const photoUrl = getStaffPhotoUrl(staff.photo);
                    
                    const detailPanel = document.getElementById('playerDetailPanel');
                    detailPanel.innerHTML = `
                        <div class="player-photo-container">
                            <img src="${photoUrl}" 
                                 alt="${escapeHtml(staff.name)}" 
                                 class="player-photo-large"
                                 onerror="this.src='<?php echo SITE_URL; ?>/images/staff/default-staff.jpg'">
                        </div>
                        
                        <div class="player-detail-info">
                            <div class="info-row">
                                <span class="info-label">POSISI:</span>
                                <span class="info-value">${positionText}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">EMAIL:</span>
                                <span class="info-value">${staff.email || '-'}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">TELEPON:</span>
                                <span class="info-value">${staff.phone || '-'}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">TEMPAT LAHIR:</span>
                                <span class="info-value">${staff.birth_place || '-'}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">TANGGAL LAHIR:</span>
                                <span class="info-value">${birthDateFormatted}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">USIA:</span>
                                <span class="info-value">${age}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">KOTA:</span>
                                <span class="info-value">${staff.city || '-'}</span>
                            </div>
                        </div>
                    `;
                }
                
                function showEmptyDetailPanel() {
                    const detailPanel = document.getElementById('playerDetailPanel');
                    detailPanel.innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-user"></i>
                            <p>Pilih pemain atau staff untuk melihat detail</p>
                        </div>
                    `;
                }
                
                function buildTeamShareText() {
                    const name = teamShareName || 'team ini';
                    return `Lihat ${name} di ALVETRIX`;
                }

                function updateTeamShareLinks() {
                    const shareUrl = window.location.href;
                    const encodedUrl = encodeURIComponent(shareUrl);
                    const encodedText = encodeURIComponent(buildTeamShareText());
                    const encodedWhatsapp = encodeURIComponent(`${buildTeamShareText()}\n${shareUrl}`);

                    const whatsappLink = document.getElementById('teamShareWhatsapp');
                    const facebookLink = document.getElementById('teamShareFacebook');
                    const telegramLink = document.getElementById('teamShareTelegram');
                    const xLink = document.getElementById('teamShareX');

                    if (whatsappLink) {
                        whatsappLink.href = `https://wa.me/?text=${encodedWhatsapp}`;
                    }
                    if (facebookLink) {
                        facebookLink.href = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`;
                    }
                    if (telegramLink) {
                        telegramLink.href = `https://t.me/share/url?url=${encodedUrl}&text=${encodedText}`;
                    }
                    if (xLink) {
                        xLink.href = `https://twitter.com/intent/tweet?text=${encodedText}&url=${encodedUrl}`;
                    }
                }

                function openTeamShareModal() {
                    const modal = document.getElementById('teamShareModal');
                    if (!modal) return;
                    updateTeamShareLinks();
                    modal.style.display = 'block';
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                }

                function closeTeamShareModal() {
                    const modal = document.getElementById('teamShareModal');
                    if (!modal) return;
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = 'auto';
                }

                function showCopyFeedback(button) {
                    if (!button) return;
                    if (!button.dataset.defaultHtml) {
                        button.dataset.defaultHtml = button.innerHTML;
                    }
                    button.innerHTML = '<i class="fas fa-check"></i> Copied';
                    button.classList.add('copied');
                    setTimeout(() => {
                        if (button.dataset.defaultHtml) {
                            button.innerHTML = button.dataset.defaultHtml;
                        }
                        button.classList.remove('copied');
                    }, 1800);
                }

                function fallbackCopyText(text) {
                    const tempInput = document.createElement('textarea');
                    tempInput.value = text;
                    tempInput.setAttribute('readonly', '');
                    tempInput.style.position = 'absolute';
                    tempInput.style.left = '-9999px';
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    let success = false;
                    try {
                        success = document.execCommand('copy');
                    } catch (err) {
                        success = false;
                    }
                    document.body.removeChild(tempInput);
                    return success;
                }

                function copyTeamShareLink(button) {
                    const shareUrl = window.location.href;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(shareUrl).then(() => {
                            showCopyFeedback(button);
                        }).catch(() => {
                            if (fallbackCopyText(shareUrl)) {
                                showCopyFeedback(button);
                            } else {
                                alert('Unable to copy link.');
                            }
                        });
                    } else {
                        if (fallbackCopyText(shareUrl)) {
                            showCopyFeedback(button);
                        } else {
                            alert('Unable to copy link.');
                        }
                    }
                }

                function shareTeam() {
                    openTeamShareModal();
                }
                
                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                function getNameInitial(name) {
                    if (!name) return '?';
                    const trimmed = String(name).trim();
                    if (!trimmed) return '?';
                    return trimmed.charAt(0).toUpperCase();
                }
                </script>

            <?php else: ?>
                <!-- TEAM LISTING VIEW -->
                <div class="container section-container section-elevated">
                    <div class="section-header">
                        <h2 class="section-title">TEAM</h2>
                        <div class="section-tabs">
                            <span class="team-count-pill"><i class="fas fa-users"></i> <?php echo count($allTeams); ?> Team</span>
                        </div>
                    </div>

                    <div class="team-filter-card">
                        <label class="team-filter-label" for="teamSelector">Pilih Team</label>
                        <div class="team-selector">
                            <select id="teamSelector" onchange="if(this.value) window.location.href='team.php?id=' + this.value">
                                <option value="">Pilih team dari dropdown atau daftar di bawah</option>
                                <?php foreach ($allTeams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="container section-container">
                    <?php if (empty($allTeams)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h4>Tidak ada team</h4>
                            <p>Belum ada team yang terdaftar</p>
                        </div>
                    <?php else: ?>
                        <div class="teams-grid team-directory-grid">
                            <?php foreach ($allTeams as $team): ?>
                                <a href="team.php?id=<?php echo $team['id']; ?>" class="team-card team-directory-card" data-team-id="<?php echo $team['id']; ?>">
                                    <div class="team-logo-frame">
                                        <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($team['logo'] ?? ''); ?>" 
                                             alt="<?php echo htmlspecialchars($team['name']); ?>"
                                             class="team-logo-lg"
                                             onerror="this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                    </div>
                                    <h3 class="team-name"><?php echo htmlspecialchars($team['name']); ?></h3>
                                    <?php if (!empty($team['events_array'])): ?>
                                        <div class="team-events-badges" style="display: flex; flex-wrap: wrap; gap: 4px; justify-content: center; margin-bottom: 10px;">
                                            <?php foreach (array_slice($team['events_array'], 0, 2) as $event_name): ?>
                                                <span class="event-badge" style="font-size: 10px; padding: 2px 8px;"><?php echo htmlspecialchars($event_name); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($team['events_array']) > 2): ?>
                                                <span class="event-badge" style="font-size: 10px; padding: 2px 8px;">+<?php echo count($team['events_array']) - 2; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <p class="team-label">Lihat Profil</p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($teamId > 0): ?>
        <!-- Team Share Modal -->
        <div class="schedule-modal team-share-modal" id="teamShareModal" aria-hidden="true" role="dialog" aria-modal="true">
            <div class="schedule-modal-content">
                <div class="schedule-modal-header">
                    <h3>Bagikan Team</h3>
                    <button class="schedule-modal-close" id="closeTeamShareModal">&times;</button>
                </div>
                <div class="schedule-modal-body">
                    <div class="schedule-detail-content">
                        <h4 class="schedule-event-title"><?php echo htmlspecialchars($team['name'] ?? ''); ?></h4>
                        <p class="schedule-round">Pilih platform untuk berbagi profil team ini.</p>
                        <div class="share-buttons-grid">
                            <a href="#" target="_blank" class="share-btn-modal whatsapp" id="teamShareWhatsapp">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                            <a href="#" target="_blank" class="share-btn-modal facebook" id="teamShareFacebook">
                                <i class="fab fa-facebook"></i> Facebook
                            </a>
                            <a href="#" target="_blank" class="share-btn-modal telegram" id="teamShareTelegram">
                                <i class="fab fa-telegram"></i> Telegram
                            </a>
                            <a href="#" target="_blank" class="share-btn-modal twitter" id="teamShareX">
                                <svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false"><title>X</title><path d="M14.234 10.162 22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z"/></svg> X
                            </a>
                            <button type="button" class="share-btn-modal copy" id="teamShareCopy">
                                <i class="far fa-copy"></i> Salin Tautan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

         <footer class="dashboard-footer">
            <p>&copy; 2026 ALVETRIX. Semua hak dilindungi.</p>
            <p>
                <a href="<?php echo SITE_URL; ?>">Beranda</a> |
                <a href="contact.php">Kontak</a> |
                <a href="bpjs.php">BPJSTK</a>
            </p>
        </footer>
    </div>
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
// Define SITE_URL for JavaScript
const SITE_URL = '<?php echo SITE_URL; ?>';
</script>
<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
