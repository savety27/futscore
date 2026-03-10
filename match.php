<?php
$extraStyles = [
    '<link rel="stylesheet" href="' . SITE_URL . '/css/redesign_core.css?v=' . time() . '">',
    '<link rel="stylesheet" href="' . SITE_URL . '/css/index_redesign.css?v=' . time() . '">',
    '<link rel="stylesheet" href="' . SITE_URL . '/css/match_redesign.css?v=' . time() . '">'
];
require_once 'includes/header.php'; ?>

<style>
/* Custom Tabs for Match Lineup */
.lineup-tabs {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-bottom: 25px;
}

.lineup-tab-btn {
    padding: 10px 30px;
    border-radius: 30px;
    background: #f1f5f9;
    color: #64748b;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.lineup-tab-btn.active {
    background: var(--primary-color, #0f172a);
    color: white;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
}

.lineup-content-half {
    display: none;
    animation: fadeIn 0.4s ease;
}

.lineup-content-half.active {
    display: block;
}

.staff-content-half {
    display: none;
    animation: fadeIn 0.4s ease;
}

.staff-content-half.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

.goals-half-title {
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #0f172a;
    margin: 12px 0 8px;
    padding-left: 10px;
}

.goals-half-title:first-of-type {
    margin-top: 0;
}

.card-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    padding: 2px 8px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 12px;
}

.card-badge.yellow { background: #fef3c7; color: #92400e; }
.card-badge.red { background: #fee2e2; color: #991b1b; }
.card-badge.green { background: #dcfce7; color: #166534; }

.suspend-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 700;
}

.suspend-pill.active { background: #fee2e2; color: #991b1b; }
.suspend-pill.done { background: #dcfce7; color: #166534; }

.match-suspend-wrap {
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
}

.match-suspend-table {
    width: 100%;
    min-width: 760px;
    border-collapse: collapse;
}

.match-suspend-table th,
.match-suspend-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 13px;
    color: #0f172a;
}

.match-suspend-table th {
    background: #0f2744;
    font-weight: 700;
    text-align: left;
    color: #ffffff;
}

.match-suspend-table tbody tr:hover {
    background: #f8fbff;
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
            <a href="all.php" class="active"><i class="fas fa-trophy"></i> <span>CHALLENGE</span></a>
            <a href="team.php"><i class="fas fa-users"></i> <span>TEAM</span></a>
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
    <main class="main-content-dashboard">
        <header class="dashboard-header dashboard-header-match">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">ALVETRIX</div>
                    <h1><?php echo $matchNotFound ? 'Pertandingan Tidak Ditemukan' : 'Detail Pertandingan'; ?></h1>
                    <p class="header-subtitle">Detail pertandingan, status, dan susunan pemain dalam tampilan yang rapi dan modern.</p>
                </div>
                <div class="header-actions">
                    <a href="all.php?status=result" class="btn-secondary"><i class="fas fa-list"></i> Semua Pertandingan</a>
                    <a href="events.php" class="btn-primary"><i class="fas fa-calendar-alt"></i> Jelajahi Event</a>
                </div>
            </div>
            <?php if (!$matchNotFound): ?>
            <div class="header-meta">
                <?php if (!empty($eventName)): ?>
                    <span class="meta-chip"><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($eventName ?? ''); ?></span>
                <?php endif; ?>
                <?php if (!empty($categoryName)): ?>
                    <span class="meta-chip"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($categoryName ?? ''); ?></span>
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
                        <?php if (!empty($categoryName)): ?>
                            <span class="match-tag code-tag"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($categoryName ?? ''); ?></span>
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
                            <div class="match-team-uniform">Baju: <?php echo htmlspecialchars($team1UniformLabel); ?></div>
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
                            <div class="match-team-uniform">Baju: <?php echo htmlspecialchars($team2UniformLabel); ?></div>
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
                        <div class="match-meta-card">
                            <div class="match-meta-icon"><i class="fas fa-user-tie"></i></div>
                            <div>
                                <div class="match-meta-label">Wasit/Pengawas</div>
                                <div class="match-meta-value"><?php echo htmlspecialchars($matchOfficialLabel); ?></div>
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
                            <?php $currentGoalHalf = null; ?>
                            <?php foreach ($goals as $goal): ?>
                                <?php
                                    $goalHalf = (int)($goal['goal_half'] ?? ($goal['half'] ?? 0));
                                    if ($goalHalf !== 1 && $goalHalf !== 2) {
                                        $goalHalf = ((int)($goal['minute'] ?? 0) > 45) ? 2 : 1;
                                    }
                                    $isTeam1 = ($goal['team_id'] == $match['team1_id']);
                                ?>
                                <?php if ($goalHalf !== $currentGoalHalf): ?>
                                    <div class="goals-half-title"><?php echo $goalHalf === 2 ? 'Babak 2' : 'Babak 1'; ?></div>
                                    <?php $currentGoalHalf = $goalHalf; ?>
                                <?php endif; ?>
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
                        <h2 class="section-title">SUSUNAN PEMAIN TEAM</h2>
                    </div>

                    <!-- TABS FOR HALF SELECTION -->
                    <div class="lineup-tabs">
                        <button class="lineup-tab-btn player-tab-btn active" onclick="switchLineupTab(1)">BABAK 1</button>
                        <button class="lineup-tab-btn player-tab-btn" onclick="switchLineupTab(2)">BABAK 2</button>
                    </div>

                    <!-- CONTENT WRAPPER FOR HALVES -->
                    <div id="lineup-half-1" class="lineup-content-half player-content-half active">
                        <div class="lineups-grid">
                            <!-- Team 1 Half 1 -->
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
                                            <span class="lineup-count"><?php echo count($lineups['team1']['half1']); ?> pemain</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge">Home</span>
                                </div>

                                <?php if (empty($lineups['team1']['half1'])): ?>
                                    <div class="empty-state">Belum ada susunan pemain babak 1.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($lineups['team1']['half1'] as $player): ?>
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

                            <!-- Team 2 Half 1 -->
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
                                            <span class="lineup-count"><?php echo count($lineups['team2']['half1']); ?> pemain</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge away">Away</span>
                                </div>

                                <?php if (empty($lineups['team2']['half1'])): ?>
                                    <div class="empty-state">Belum ada susunan pemain babak 1.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($lineups['team2']['half1'] as $player): ?>
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
                    </div>

                    <div id="lineup-half-2" class="lineup-content-half player-content-half">
                         <div class="lineups-grid">
                            <!-- Team 1 Half 2 -->
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
                                            <span class="lineup-count"><?php echo count($lineups['team1']['half2']); ?> pemain</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge">Home</span>
                                </div>

                                <?php if (empty($lineups['team1']['half2'])): ?>
                                    <div class="empty-state">Belum ada susunan pemain babak 2.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($lineups['team1']['half2'] as $player): ?>
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

                            <!-- Team 2 Half 2 -->
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
                                            <span class="lineup-count"><?php echo count($lineups['team2']['half2']); ?> pemain</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge away">Away</span>
                                </div>

                                <?php if (empty($lineups['team2']['half2'])): ?>
                                    <div class="empty-state">Belum ada susunan pemain babak 2.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($lineups['team2']['half2'] as $player): ?>
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
                    </div>
                </section>

                <?php if ($resolvedSource === 'challenge' && $hasMatchStaffAssignmentsTable): ?>
                <section class="section-container lineup-section">
                    <div class="section-header">
                        <h2 class="section-title">LINEUP STAFF TEAM</h2>
                    </div>
                    <div class="lineup-tabs">
                        <button class="lineup-tab-btn staff-tab-btn active" onclick="switchStaffTab(1)">BABAK 1</button>
                        <button class="lineup-tab-btn staff-tab-btn" onclick="switchStaffTab(2)">BABAK 2</button>
                    </div>

                    <div id="staff-half-1" class="staff-content-half active">
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
                                            <span class="lineup-count"><?php echo count($staffLineups['team1']['half1']); ?> staff</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge">Home</span>
                                </div>
                                <?php if (empty($staffLineups['team1']['half1'])): ?>
                                    <div class="empty-state">Belum ada staff ditetapkan untuk babak 1.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($staffLineups['team1']['half1'] as $staff): ?>
                                            <?php $staffPhoto = resolveMatchStaffPhoto($staff['photo'] ?? ''); ?>
                                            <div class="lineup-player">
                                                <?php if ($staffPhoto['found']): ?>
                                                    <img src="<?php echo htmlspecialchars($staffPhoto['url']); ?>"
                                                         class="lineup-avatar"
                                                         alt="<?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : 'Staff'); ?>"
                                                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="lineup-avatar" style="display:none;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="lineup-avatar" style="display:flex;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="lineup-info">
                                                    <div class="lineup-name"><?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : '-'); ?></div>
                                                    <div class="lineup-meta">
                                                        <span class="lineup-position"><?php echo htmlspecialchars(formatStaffRoleLabel($staff['role'])); ?></span>
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
                                            <span class="lineup-count"><?php echo count($staffLineups['team2']['half1']); ?> staff</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge away">Away</span>
                                </div>
                                <?php if (empty($staffLineups['team2']['half1'])): ?>
                                    <div class="empty-state">Belum ada staff ditetapkan untuk babak 1.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($staffLineups['team2']['half1'] as $staff): ?>
                                            <?php $staffPhoto = resolveMatchStaffPhoto($staff['photo'] ?? ''); ?>
                                            <div class="lineup-player">
                                                <?php if ($staffPhoto['found']): ?>
                                                    <img src="<?php echo htmlspecialchars($staffPhoto['url']); ?>"
                                                         class="lineup-avatar"
                                                         alt="<?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : 'Staff'); ?>"
                                                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="lineup-avatar" style="display:none;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="lineup-avatar" style="display:flex;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="lineup-info">
                                                    <div class="lineup-name"><?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : '-'); ?></div>
                                                    <div class="lineup-meta">
                                                        <span class="lineup-position"><?php echo htmlspecialchars(formatStaffRoleLabel($staff['role'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div id="staff-half-2" class="staff-content-half">
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
                                            <span class="lineup-count"><?php echo count($staffLineups['team1']['half2']); ?> staff</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge">Home</span>
                                </div>
                                <?php if (empty($staffLineups['team1']['half2'])): ?>
                                    <div class="empty-state">Belum ada staff ditetapkan untuk babak 2.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($staffLineups['team1']['half2'] as $staff): ?>
                                            <?php $staffPhoto = resolveMatchStaffPhoto($staff['photo'] ?? ''); ?>
                                            <div class="lineup-player">
                                                <?php if ($staffPhoto['found']): ?>
                                                    <img src="<?php echo htmlspecialchars($staffPhoto['url']); ?>"
                                                         class="lineup-avatar"
                                                         alt="<?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : 'Staff'); ?>"
                                                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="lineup-avatar" style="display:none;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="lineup-avatar" style="display:flex;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="lineup-info">
                                                    <div class="lineup-name"><?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : '-'); ?></div>
                                                    <div class="lineup-meta">
                                                        <span class="lineup-position"><?php echo htmlspecialchars(formatStaffRoleLabel($staff['role'])); ?></span>
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
                                            <span class="lineup-count"><?php echo count($staffLineups['team2']['half2']); ?> staff</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge away">Away</span>
                                </div>
                                <?php if (empty($staffLineups['team2']['half2'])): ?>
                                    <div class="empty-state">Belum ada staff ditetapkan untuk babak 2.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($staffLineups['team2']['half2'] as $staff): ?>
                                            <?php $staffPhoto = resolveMatchStaffPhoto($staff['photo'] ?? ''); ?>
                                            <div class="lineup-player">
                                                <?php if ($staffPhoto['found']): ?>
                                                    <img src="<?php echo htmlspecialchars($staffPhoto['url']); ?>"
                                                         class="lineup-avatar"
                                                         alt="<?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : 'Staff'); ?>"
                                                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="lineup-avatar" style="display:none;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="lineup-avatar" style="display:flex;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="lineup-info">
                                                    <div class="lineup-name"><?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : '-'); ?></div>
                                                    <div class="lineup-meta">
                                                        <span class="lineup-position"><?php echo htmlspecialchars(formatStaffRoleLabel($staff['role'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($resolvedSource === 'challenge'): ?>
                <section class="section-container lineup-section">
                    <div class="section-header">
                        <h2 class="section-title">STATUS SUSPEND PEMAIN MATCH INI</h2>
                    </div>
                    <?php if (!$hasPlayerEventCardsTable): ?>
                        <div class="empty-state">Tabel data kartu pemain belum tersedia.</div>
                    <?php elseif (empty($matchSuspendRows)): ?>
                        <div class="empty-state">Tidak ada data kartu pemain untuk pertandingan ini.</div>
                    <?php else: ?>
                        <div class="match-suspend-wrap">
                            <table class="match-suspend-table">
                                <thead>
                                    <tr>
                                        <th>Pemain</th>
                                        <th>Team</th>
                                        <th>Kuning</th>
                                        <th>Merah</th>
                                        <th>Hijau</th>
                                        <th>Suspend Sampai</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($matchSuspendRows as $card): ?>
                                        <?php
                                        $suspendUntil = !empty($card['suspension_until']) ? $card['suspension_until'] : null;
                                        $isSuspended = $suspendUntil && $suspendUntil >= date('Y-m-d');
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($card['player_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($card['team_name'] ?? '-'); ?></td>
                                            <td><span class="card-badge yellow"><?php echo (int)($card['yellow_cards'] ?? 0); ?></span></td>
                                            <td><span class="card-badge red"><?php echo (int)($card['red_cards'] ?? 0); ?></span></td>
                                            <td><span class="card-badge green"><?php echo (int)($card['green_cards'] ?? 0); ?></span></td>
                                            <td><?php echo $suspendUntil ? htmlspecialchars(date('d M Y', strtotime($suspendUntil))) : '-'; ?></td>
                                            <td>
                                                <span class="suspend-pill <?php echo $isSuspended ? 'active' : 'done'; ?>">
                                                    <?php echo $isSuspended ? 'Tidak Boleh Main' : 'Boleh Main'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
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

// Lineup Tab Switching
function switchLineupTab(half) {
    // Hide all contents
    document.querySelectorAll('.player-content-half').forEach(el => el.classList.remove('active'));
    // Show selected
    document.getElementById('lineup-half-' + half).classList.add('active');
    
    // Update buttons
    const btns = document.querySelectorAll('.player-tab-btn');
    btns.forEach(btn => btn.classList.remove('active'));
    btns[half-1].classList.add('active');
}

function switchStaffTab(half) {
    document.querySelectorAll('.staff-content-half').forEach(el => el.classList.remove('active'));
    document.getElementById('staff-half-' + half).classList.add('active');

    const btns = document.querySelectorAll('.staff-tab-btn');
    btns.forEach(btn => btn.classList.remove('active'));
    btns[half-1].classList.add('active');
}
</script>

<script>
const SITE_URL = '<?php echo SITE_URL; ?>';
</script>
<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>



