<?php
$hideNavbars = true;
require_once 'includes/header.php';

// Get data from database
$latestChallenges = getLatestChallenges(5);
$scheduledMatches = getScheduledChallenges(5);
$completedMatches = getCompletedChallenges(5);
$newPlayers = getPlayers(5);
$recentTransfers = getPlayerTransfers(5);

$recentWinners = getRecentWinners(5);
$newTeams = getTeams(5); // tes

$pageTitle = "Home";
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/index_redesign.css?v=<?php echo time(); ?>">

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
            <a href="<?php echo SITE_URL; ?>" class="active"><i class="fas fa-home"></i> <span>BERANDA</span></a>
            <a href="EVENTS.PHP"><i class="fas fa-calendar-alt"></i> <span>EVENT</span></a>
            <a href="all.php"><i class="fas fa-trophy"></i> <span>CHALLENGE</span></a>
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
    <div class="main-content-dashboard">
        <header class="dashboard-header dashboard-header-home">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">ALVETRIX</div>
                    <h1>Beranda</h1>
                    <p class="header-subtitle">Ringkasan pertandingan, berita, pemain, dan team terbaru dalam satu tampilan.</p>
                </div>
                <div class="header-actions">
                    <a href="event.php" class="btn-primary"><i class="fas fa-calendar-alt"></i> Lihat Event</a>
                    <a href="team.php" class="btn-secondary"><i class="fas fa-users"></i> Lihat Team</a>
                    <a href="player.php" class="btn-primary"><i class="fas fa-running"></i> Lihat Player</a>
                </div>
            </div>
        </header>

        <div class="dashboard-body">

<!-- Match Summary Cards dengan Horizontal Scroll -->
<div class="container section-container section-elevated">
    <div class="section-header">
        <h2 class="section-title">PERTANDINGAN TERBARU</h2>
        <div class="scroll-controls">
            <button class="scroll-btn scroll-prev" aria-label="Gulir ke kiri">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="scroll-btn scroll-next" aria-label="Gulir ke kanan">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
    
    <div class="match-cards-container">
        <div class="match-cards-scroll">
            <?php 
            foreach ($latestChallenges as $match): 
            ?>
            <div class="match-card-wrapper">
                <div class="match-card" data-match-id="<?php echo $match['id']; ?>">
                    <div class="match-header">
                        <span class="match-date"><?php echo formatDate($match['challenge_date']); ?> | <?php echo $match['sport_type']; ?></span>
                        <span class="match-status"><?php echo strtoupper($match['match_status'] ?? 'VS'); ?></span>
                    </div>
                    
                    <div class="match-teams">
                        <!-- TEAM 1 -->
                        <div class="team">
                            <?php
                            $logo1 = $match['challenger_logo'];
                            $logo1Path = SITE_URL . '/images/teams/' . $logo1;
                            ?>
                            <div class="team-logo-container">
                                <img src="<?php echo $logo1Path; ?>" 
                                     alt="<?php echo htmlspecialchars($match['challenger_name'] ?? ''); ?>" 
                                     class="team-logo"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                            </div>
                            <span class="team-name"><?php echo htmlspecialchars($match['challenger_name'] ?? ''); ?></span>
                        </div>
                        
                        <div class="vs">VS</div>
                        
                        <!-- TEAM 2 -->
                        <div class="team">
                            <?php
                            $logo2 = $match['opponent_logo'];
                            $logo2Path = SITE_URL . '/images/teams/' . $logo2;
                            ?>
                            <div class="team-logo-container">
                                <img src="<?php echo $logo2Path; ?>" 
                                     alt="<?php echo htmlspecialchars($match['opponent_name'] ?? ''); ?>" 
                                     class="team-logo"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                            </div>
                            <span class="team-name"><?php echo htmlspecialchars($match['opponent_name'] ?? ''); ?></span>
                        </div>
                    </div>
                    
                    <div class="match-score">
                        <?php 
                        if ($match['match_status'] == 'completed') {
                            echo $match['challenger_score'] . " - " . $match['opponent_score'];
                        } else {
                            echo date('H:i', strtotime($match['challenge_date']));
                        }
                        ?>
                    </div>
                    
                    <div class="match-details">
                        <span class="match-venue"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($match['venue_name'] ?? ''); ?></span>
                        <button class="btn-details" data-match-id="<?php echo $match['id']; ?>">Lihat Detail</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- News Section -->
<div class="container section-container">
    <div class="section-header">
        <h2 class="section-title">BERITA</h2>
        <div class="section-tabs">
            <button class="tab-button active" data-tab="new-news">Baru</button>
            <button class="tab-button" data-tab="popular-news">Populer</button>
        </div>
    </div>
    
    <!-- Tab New News -->
    <div class="tab-content active" id="new-news">
        <?php 
        $newNews = getLatestNews(3);
        
        if (empty($newNews)): 
        ?>
        <div class="empty-state">
            <i class="fas fa-newspaper"></i>
            <h4>Tidak ada berita terbaru</h4>
            <p>Belum ada berita yang diterbitkan</p>
        </div>
        <?php else: ?>
        <div class="news-grid">
            <div class="news-main">
                <?php 
                $news = $newNews[0];
                $image = !empty($news['gambar']) ? $news['gambar'] : 'news1.jpg';
                $imagePath = SITE_URL . '/images/berita/' . $image;
                $defaultImage = SITE_URL . '/images/berita/default-news.jpg';
                ?>
                <a href="<?php echo SITE_URL; ?>/news.php?slug=<?php echo $news['slug']; ?>" class="news-item-large news-link" data-news-id="<?php echo $news['id']; ?>">
                    <img src="<?php echo $imagePath; ?>" 
                         alt="<?php echo htmlspecialchars($news['judul'] ?? ''); ?>" 
                         class="news-image"
                         onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                    <div class="news-content">
                        <div class="news-meta">
                            <span class="news-date"><?php echo formatDate($news['created_at']); ?></span>
                            <span class="news-views"><i class="fas fa-eye"></i> <span class="view-count" id="view-count-<?php echo $news['id']; ?>"><?php echo $news['views']; ?></span>x</span>
                        </div>
                        <h3 class="news-title">
                            <?php echo htmlspecialchars($news['judul'] ?? ''); ?>
                        </h3>
                        <p class="news-excerpt">
                            <?php 
                            $content = $news['konten'] ?? ($news['content'] ?? '');
                            $excerpt = strip_tags($content);
                            echo htmlspecialchars(mb_substr($excerpt ?? '', 0, 100)) . '...'; 
                            ?>
                        </p>
                        <p class="news-author">by <?php echo htmlspecialchars($news['penulis'] ?? ''); ?></p>
                    </div>
                </a>
            </div>
            
            <div class="news-sidebar">
                <?php 
                for ($i = 1; $i < count($newNews); $i++): 
                    $news = $newNews[$i];
                    $image = !empty($news['gambar']) ? $news['gambar'] : 'news' . ($i + 1) . '.jpg';
                    $imagePath = SITE_URL . '/images/berita/' . $image;
                    $defaultImage = SITE_URL . '/images/berita/default-news.jpg';
                ?>
                <a href="<?php echo SITE_URL; ?>/news.php?slug=<?php echo $news['slug']; ?>" class="news-item-small news-link" data-news-id="<?php echo $news['id']; ?>">
                    <div class="news-thumbnail">
                        <img src="<?php echo $imagePath; ?>" 
                             alt="<?php echo htmlspecialchars($news['judul'] ?? ''); ?>"
                             class="news-thumb"
                             onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                    </div>
                    <div class="news-content-small">
                        <div class="news-meta">
                            <span class="news-date"><?php echo formatDate($news['created_at']); ?></span>
                            <span class="news-views"><i class="fas fa-eye"></i> <span class="view-count" id="view-count-<?php echo $news['id']; ?>"><?php echo $news['views']; ?></span>x</span>
                        </div>
                        <h4 class="news-title">
                            <?php echo htmlspecialchars($news['judul'] ?? ''); ?>
                        </h4>
                        <p class="news-author">by <?php echo htmlspecialchars($news['penulis'] ?? ''); ?></p>
                    </div>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="news-info">
            <i class="fas fa-info-circle"></i> Menampilkan <?php echo count($newNews); ?> berita terbaru (diurutkan dari yang terbaru)
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Tab Popular News -->
    <div class="tab-content" id="popular-news">
        <?php 
        $popularNews = getPopularNews(3);
        
        if (empty($popularNews)): 
        ?>
        <div class="empty-state">
            <i class="fas fa-fire"></i>
            <h4>Tidak ada berita populer</h4>
            <p>Belum ada berita yang cukup populer</p>
        </div>
        <?php else: ?>
        <div class="news-grid">
            <div class="news-main">
                <?php 
                $news = $popularNews[0];
                $image = !empty($news['gambar']) ? $news['gambar'] : 'news1.jpg';
                $imagePath = SITE_URL . '/images/berita/' . $image;
                $defaultImage = SITE_URL . '/images/berita/default-news.jpg';
                ?>
                <a href="<?php echo SITE_URL; ?>/news.php?slug=<?php echo $news['slug']; ?>" class="news-item-large news-link" data-news-id="<?php echo $news['id']; ?>">
                    <img src="<?php echo $imagePath; ?>" 
                         alt="<?php echo htmlspecialchars($news['judul'] ?? ''); ?>" 
                         class="news-image"
                         onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                    <div class="news-content">
                        <div class="news-meta">
                            <span class="news-date"><?php echo formatDate($news['created_at']); ?></span>
                            <span class="news-views"><i class="fas fa-eye"></i> <span class="view-count" id="view-count-<?php echo $news['id']; ?>"><?php echo $news['views']; ?></span>x</span>
                            <span class="news-popular-badge"><i class="fas fa-fire"></i> Trending</span>
                        </div>
                        <h3 class="news-title">
                            <?php echo htmlspecialchars($news['judul'] ?? ''); ?>
                        </h3>
                        <p class="news-excerpt">
                            <?php 
                            $excerpt = strip_tags($news['konten']);
                            echo htmlspecialchars(mb_substr($excerpt ?? '', 0, 100)) . '...'; 
                            ?>
                        </p>
                        <p class="news-author">by <?php echo htmlspecialchars($news['penulis'] ?? ''); ?></p>
                    </div>
                </a>
            </div>
            
            <div class="news-sidebar">
                <?php 
                for ($i = 1; $i < count($popularNews); $i++): 
                    $news = $popularNews[$i];
                    $image = !empty($news['gambar']) ? $news['gambar'] : 'news' . ($i + 1) . '.jpg';
                    $imagePath = SITE_URL . '/images/berita/' . $image;
                    $defaultImage = SITE_URL . '/images/berita/default-news.jpg';
                ?>
                <a href="<?php echo SITE_URL; ?>/news.php?slug=<?php echo $news['slug']; ?>" class="news-item-small news-link" data-news-id="<?php echo $news['id']; ?>">
                    <div class="news-thumbnail">
                        <img src="<?php echo $imagePath; ?>" 
                             alt="<?php echo htmlspecialchars($news['judul'] ?? ''); ?>"
                             class="news-thumb"
                             onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                    </div>
                    <div class="news-content-small">
                        <div class="news-meta">
                            <span class="news-date"><?php echo formatDate($news['created_at']); ?></span>
                            <span class="news-views"><i class="fas fa-eye"></i> <span class="view-count" id="view-count-<?php echo $news['id']; ?>"><?php echo $news['views']; ?></span>x</span>
                        </div>
                        <h4 class="news-title">
                            <?php echo htmlspecialchars($news['judul'] ?? ''); ?>
                        </h4>
                        <p class="news-author">by <?php echo htmlspecialchars($news['penulis'] ?? ''); ?></p>
                    </div>
                </a>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="sorting-info">
            <i class="fas fa-sort-amount-down"></i> Diurutkan berdasarkan jumlah views tertinggi (<?php echo count($popularNews); ?> berita terpopuler)
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Match Section - IMPROVED -->
<div class="container section-container match-section">
    <div class="section-header">
        <h2 class="section-title">PERTANDINGAN</h2>
        <div class="section-tabs">
            <button class="tab-button active" data-tab="match-schedule">Jadwal</button>
            <button class="tab-button" data-tab="match-result">Hasil</button>
        </div>
    </div>
    
    <!-- Schedule Tab -->
    <div class="tab-content active" id="match-schedule">
        <?php 
        if (empty($scheduledMatches)): 
        ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h4>Tidak ada jadwal pertandingan</h4>
            <p>Belum ada pertandingan yang dijadwalkan</p>
        </div>
        <?php else: ?>
        <div class="match-grid-redesign">
            <?php foreach ($scheduledMatches as $index => $match): ?>
            <div class="match-item-card schedule-card" data-match-id="<?php echo $match['id']; ?>">
                <div class="match-card-top">
                    <span class="m-sport-badge"><?php echo htmlspecialchars($match['sport_type'] ?? 'Futsal'); ?></span>
                    <span class="m-match-code"><?php echo htmlspecialchars($match['challenge_code'] ?? ''); ?></span>
                </div>
                
                <div class="match-card-main">
                    <div class="m-team challenger">
                        <div class="m-team-logo">
                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['challenger_logo']; ?>" 
                                 alt="<?php echo htmlspecialchars($match['challenger_name'] ?? ''); ?>" 
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                        </div>
                        <span class="m-team-name"><?php echo htmlspecialchars($match['challenger_name'] ?? ''); ?></span>
                    </div>
                    
                    <div class="m-vs-divider">
                        <span class="vs-label">VS</span>
                    </div>
                    
                    <div class="m-team opponent">
                        <div class="m-team-logo">
                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['opponent_logo']; ?>" 
                                 alt="<?php echo htmlspecialchars($match['opponent_name'] ?? ''); ?>" 
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                        </div>
                        <span class="m-team-name"><?php echo htmlspecialchars($match['opponent_name'] ?? ''); ?></span>
                    </div>
                </div>
                
                <div class="match-card-bottom">
                    <div class="m-info-item">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo formatDate($match['challenge_date']); ?></span>
                    </div>
                    <div class="m-info-item">
                        <i class="far fa-clock"></i>
                        <span><?php echo date('H:i', strtotime($match['challenge_date'])); ?></span>
                    </div>
                </div>
                
                <div class="match-card-venue">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars($match['venue_name'] ?? 'TBA'); ?></span>
                </div>
                
                <div class="match-card-action">
                    <button class="btn-view-premium btn-view-schedule" data-match-id="<?php echo $match['id']; ?>">
                        <i class="fas fa-eye"></i> Lihat Detail
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="see-more-section">
            <a href="all.php?status=schedule" class="btn-see-more-premium">
                <span>Lihat Semua Jadwal</span>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- Result Tab -->
    <div class="tab-content" id="match-result">
        <?php 
        if (empty($completedMatches)): 
        ?>
        <div class="empty-state">
            <i class="fas fa-trophy"></i>
            <h4>Tidak ada hasil pertandingan</h4>
            <p>Belum ada pertandingan yang selesai</p>
        </div>
        <?php else: ?>
        <div class="match-grid-redesign">
            <?php foreach ($completedMatches as $index => $match): ?>
            <div class="match-item-card result-card" data-match-id="<?php echo $match['id']; ?>">
                <div class="match-card-top">
                    <span class="m-sport-badge"><?php echo htmlspecialchars($match['sport_type'] ?? 'Futsal'); ?></span>
                    <span class="m-match-status">FT</span>
                </div>
                
                <div class="match-card-main">
                    <div class="m-team challenger">
                        <div class="m-team-logo">
                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['challenger_logo']; ?>" 
                                 alt="<?php echo htmlspecialchars($match['challenger_name'] ?? ''); ?>" 
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                        </div>
                        <span class="m-team-name"><?php echo htmlspecialchars($match['challenger_name'] ?? ''); ?></span>
                    </div>
                    
                    <div class="m-score-container">
                        <span class="m-score"><?php echo $match['challenger_score']; ?> - <?php echo $match['opponent_score']; ?></span>
                    </div>
                    
                    <div class="m-team opponent">
                        <div class="m-team-logo">
                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['opponent_logo']; ?>" 
                                 alt="<?php echo htmlspecialchars($match['opponent_name'] ?? ''); ?>" 
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                        </div>
                        <span class="m-team-name"><?php echo htmlspecialchars($match['opponent_name'] ?? ''); ?></span>
                    </div>
                </div>
                
                <div class="match-card-bottom">
                    <div class="m-info-item">
                        <i class="far fa-calendar-alt"></i>
                        <span><?php echo formatDate($match['challenge_date']); ?></span>
                    </div>
                    <div class="m-info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($match['venue_name'] ?? 'TBA'); ?></span>
                    </div>
                </div>
                
                <div class="match-card-action">
                    <button class="btn-view-premium btn-view-result" data-match-id="<?php echo $match['id']; ?>">
                        <i class="fas fa-chart-bar"></i> Laporan Pertandingan
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="see-more-section">
            <a href="all.php?status=result" class="btn-see-more-premium">
                <span>Lihat Semua Hasil</span>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</div>

<!-- Player Section -->
<div class="container section-container">
    <div class="section-header">
        <h2 class="section-title">PEMAIN</h2>
        <div class="section-tabs">
            <button class="tab-button active" data-tab="new-added">Baru Ditambahkan</button>
            <button class="tab-button" data-tab="transfer">Transfer</button>

        </div>
    </div>
    
    <div class="tab-content active" id="new-added">
        <?php if (empty($newPlayers)): ?>
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            <h4>Tidak ada pemain baru</h4>
            <p>Belum ada pemain yang ditambahkan</p>
        </div>
        <?php else: ?>
        <div class="players-grid">
            <?php foreach ($newPlayers as $player): ?>
            <div class="player-card" data-player-id="<?php echo $player['id']; ?>">
                <div class="player-photo-container">
                    <?php if (!empty($player['photo'])): ?>
                        <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player['photo']; ?>" 
                             alt="<?php echo htmlspecialchars($player['name']); ?>" 
                             class="player-photo"
                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="default-photo" style="display: none;">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php else: ?>
                        <div class="default-photo">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <h3 class="player-name"><?php echo htmlspecialchars($player['name']); ?></h3>
                <p class="player-team">
                    <?php echo htmlspecialchars($player['team_name']); ?> #<?php echo htmlspecialchars($player['jersey_number']); ?>
                </p>
                <p class="player-info">
                    <?php echo htmlspecialchars($player['position']); ?> | <?php echo htmlspecialchars($player['age']); ?> Tahun
                </p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="tab-content" id="transfer">
        <?php if (empty($recentTransfers)): ?>
        <div class="empty-state">
            <i class="fas fa-exchange-alt"></i>
            <h4>Tidak ada transfer</h4>
            <p>Belum ada data transfer pemain baru-baru ini</p>
        </div>
        <?php else: ?>
        <div class="transfers-grid">
            <?php foreach ($recentTransfers as $transfer): ?>
                <?php
                $playerPhoto = !empty($transfer['player_photo']) ? $transfer['player_photo'] : 'default-player.jpg';
                $fromLogo = !empty($transfer['from_team_logo']) ? $transfer['from_team_logo'] : null;
                $toLogo = !empty($transfer['to_team_logo']) ? $transfer['to_team_logo'] : null;
                $fromTeam = $transfer['from_team_name'] ?? 'Tanpa Klub';
                $toTeam = $transfer['to_team_name'] ?? 'Tanpa Klub';
                $transferDate = $transfer['transfer_date'] ?? ($transfer['created_at'] ?? null);
                
                $playerPhotoPath = SITE_URL . '/images/players/' . $playerPhoto;
                $fromLogoPath = $fromLogo ? SITE_URL . '/images/teams/' . $fromLogo : SITE_URL . '/images/alvetrix.png';
                $toLogoPath = $toLogo ? SITE_URL . '/images/teams/' . $toLogo : SITE_URL . '/images/alvetrix.png';
                ?>
                <div class="transfer-card">
                    <div class="transfer-header">
                        <div class="transfer-player-photo">
                            <?php if (!empty($transfer['player_photo'])): ?>
                                <img src="<?php echo $playerPhotoPath; ?>" 
                                     alt="<?php echo htmlspecialchars($transfer['player_name'] ?? 'Pemain'); ?>"
                                     class="transfer-player-img"
                                     onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="default-photo" style="display: none;">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php else: ?>
                                <div class="default-photo">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="transfer-player-details">
                            <h3 class="transfer-player-name"><?php echo htmlspecialchars($transfer['player_name'] ?? 'Pemain Tidak Diketahui'); ?></h3>
                            <?php if (!empty($transferDate)): ?>
                                <div class="transfer-date-badge">
                                    <i class="far fa-calendar-alt"></i> <?php echo formatDate($transferDate); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="transfer-flow">
                        <div class="transfer-team">
                            <span class="transfer-team-label">Dari</span>
                            <img src="<?php echo $fromLogoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($fromTeam ?? ''); ?>" 
                                 class="transfer-team-logo"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                            <span class="transfer-team-name"><?php echo htmlspecialchars($fromTeam ?? ''); ?></span>
                        </div>
                        
                        <div class="transfer-arrow-container">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                        
                        <div class="transfer-team">
                            <span class="transfer-team-label">Ke</span>
                            <img src="<?php echo $toLogoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($toTeam ?? ''); ?>" 
                                 class="transfer-team-logo"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                            <span class="transfer-team-name"><?php echo htmlspecialchars($toTeam ?? ''); ?></span>
                        </div>
                    </div>
                    
                    <div class="transfer-footer">
                        <span class="transfer-status">Dikonfirmasi</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    

</div>

<!-- Team Section -->
<div class="container section-container">
    <div class="section-header">
        <h2 class="section-title">TEAM</h2>
        <div class="section-tabs">
            <button class="tab-button active" data-tab="recent-winner">Menang Terbaru</button>
            <button class="tab-button" data-tab="new-team">Team Baru</button>
        </div>
    </div>
    
    <div class="tab-content active" id="recent-winner">
        <?php if (empty($recentWinners)): ?>
        <div class="empty-state">
            <i class="fas fa-trophy"></i>
            <h4>Tidak ada pemenang terkini</h4>
            <p>Belum ada team yang memenangkan kompetisi</p>
        </div>
        <?php else: ?>
        <div class="winners-grid">
            <?php foreach ($recentWinners as $team): ?>
            <a href="team.php?id=<?php echo $team['id']; ?>" class="winner-premium-card">
                <div class="winner-bg-glow"></div>
                <div class="winner-trophy-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="team-logo-container">
                    <?php 
                    $teamLogo = !empty($team['logo']) ? SITE_URL . '/images/teams/' . $team['logo'] : SITE_URL . '/images/alvetrix.png';
                    ?>
                    <img src="<?php echo $teamLogo; ?>" 
                         alt="<?php echo htmlspecialchars($team['name'] ?? ''); ?>" 
                         class="team-logo-premium"
                         onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                </div>
                <div class="winner-info">
                    <h3 class="team-name"><?php echo htmlspecialchars($team['name'] ?? ''); ?></h3>
                    <div class="achievement-badge">
                        <i class="fas fa-trophy"></i>
                        <span><?php echo htmlspecialchars($team['achievement'] ?? ''); ?></span>
                    </div>
                </div>
                <div class="winner-footer">
                    <span class="view-profile">Lihat Profil <i class="fas fa-arrow-right"></i></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="tab-content" id="new-team">
        <?php if (empty($newTeams)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h4>Tidak ada team baru</h4>
            <p>Belum ada team yang ditambahkan</p>
        </div>
        <?php else: ?>
        <div class="teams-grid">
            <?php foreach ($newTeams as $team): ?>
            <a href="team.php?id=<?php echo $team['id']; ?>" class="team-card" data-team-id="<?php echo $team['id']; ?>">
                <div class="team-logo-container">
                    <?php 
                    $tLogo = !empty($team['logo']) ? SITE_URL . '/images/teams/' . $team['logo'] : SITE_URL . '/images/alvetrix.png';
                    ?>
                    <img src="<?php echo $tLogo; ?>" 
                         alt="<?php echo htmlspecialchars($team['name'] ?? ''); ?>" 
                         class="team-logo-lg"
                         onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                </div>
                <h3 class="team-name"><?php echo htmlspecialchars($team['name']); ?></h3>
                <p class="team-label">Team Baru</p>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Match Detail Modal - REDESIGNED -->
<div class="match-modal" id="matchModal">
    <div class="match-modal-content premium-modal">
        <div class="match-modal-header premium-header">
            <div class="header-content">
                <i class="fas fa-trophy header-icon"></i>
                <h3 id="matchModalTitle">Detail Pertandingan</h3>
            </div>
            <button class="match-modal-close" id="closeMatchModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="match-modal-body">
            <div class="match-tabs-premium">
                <button class="match-tab active" data-tab="goals">
                    <i class="fas fa-futbol"></i> Gol
                </button>
                <button class="match-tab" data-tab="lineups">
                    <i class="fas fa-users"></i> Susunan Pemain
                </button>
            </div>
            
            <div class="match-detail-header-premium">
                <div class="match-teams-comparison">
                    <div class="team-side team-left">
                        <div class="team-logo-glow">
                            <img id="team1LogoLarge" src="" alt="" class="team-logo-large-p">
                        </div>
                        <h4 id="team1Name" class="p-team-name"></h4>
                    </div>
                    
                    <div class="vs-score-center">
                        <span class="vs-badge">VS</span>
                        <div class="score-display-premium" id="matchScoreLarge"></div>
                    </div>
                    
                    <div class="team-side team-right">
                        <div class="team-logo-glow">
                            <img id="team2LogoLarge" src="" alt="" class="team-logo-large-p">
                        </div>
                        <h4 id="team2Name" class="p-team-name"></h4>
                    </div>
                </div>
                
                <div class="match-meta-info">
                    <div class="meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span id="matchDateTime"></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span id="matchLocation"></span>
                    </div>
                </div>
            </div>
            
            <div class="match-tab-content active" id="goalsContent">
                <h4>Gol</h4>
                <div class="goals-list" id="goalsList"></div>
            </div>
            
            
            <div class="match-tab-content" id="lineupsContent">
                <h4>Susunan Pemain</h4>
                
                <style>
                    .lineup-half-tabs {
                        display: flex;
                        justify-content: center;
                        gap: 0;
                        margin-bottom: 20px;
                        background: linear-gradient(135deg, #58688fff 0%, #4a6a9cff 100%);
                        border-radius: 16px;
                        padding: 6px;
                        max-width: 320px;
                        margin-left: auto;
                        margin-right: auto;
                        box-shadow: 0 4px 15px rgba(15, 23, 42, 0.25);
                    }
                    .lineup-half-btn {
                        flex: 1;
                        padding: 10px 20px;
                        border-radius: 12px;
                        border: none;
                        background: transparent;
                        color: rgba(255,255,255,0.5);
                        font-weight: 700;
                        font-size: 13px;
                        letter-spacing: 0.5px;
                        cursor: pointer;
                        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 8px;
                        position: relative;
                        overflow: hidden;
                        text-transform: uppercase;
                    }
                    .lineup-half-btn i {
                        font-size: 14px;
                        transition: transform 0.3s ease;
                    }
                    .lineup-half-btn:hover:not(.active) {
                        color: rgba(255,255,255,0.75);
                        background: rgba(255,255,255,0.08);
                    }
                    .lineup-half-btn.active {
                        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1d4ed8 100%);
                        color: #fff;
                        box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4), inset 0 1px 0 rgba(255,255,255,0.15);
                        text-shadow: 0 1px 2px rgba(0,0,0,0.2);
                    }
                    .lineup-half-btn.active i {
                        transform: scale(1.15);
                        animation: pulseIcon 2s ease-in-out infinite;
                    }
                    @keyframes pulseIcon {
                        0%, 100% { transform: scale(1.15); }
                        50% { transform: scale(1.3); }
                    }
                </style>
                <div class="lineup-half-tabs">
                    <button class="lineup-half-btn active" data-half="1">
                        <i class="fas fa-futbol"></i> Babak 1
                    </button>
                    <button class="lineup-half-btn" data-half="2">
                        <i class="fas fa-futbol"></i> Babak 2
                    </button>
                </div>

                <div class="lineups-container">
                    <div class="team-lineup" id="team1Lineup">
                        <h5 id="team1NameLineup"></h5>
                        <div class="players-list" id="team1Players"></div>
                    </div>
                    
                    <div class="team-lineup" id="team2Lineup">
                        <h5 id="team2NameLineup"></h5>
                        <div class="players-list" id="team2Players"></div>
                    </div>
                </div>
                
                <div class="player-search">
                    <input type="text" id="playerSearch" placeholder="Cari berdasarkan ID Pemain">
                    <button id="searchPlayerBtn">Cari</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="schedule-modal" id="scheduleModal">
    <div class="schedule-modal-content">
        <div class="schedule-modal-header">
            <h3 id="scheduleModalTitle">Detail Jadwal</h3>
            <button class="schedule-modal-close" id="closeScheduleModal">&times;</button>
        </div>
        
        <div class="schedule-modal-body" id="scheduleModalContent"></div>
    </div>
</div>
        </div>

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
