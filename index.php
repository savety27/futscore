<?php
require_once 'includes/header.php';

// Get data from database
$scheduledMatches = getScheduledMatches(5);
$completedMatches = getCompletedMatches(5);
$newPlayers = getPlayers(5);
$recentTransfers = [];
$birthdayPlayers = [];
$recentWinners = [];
$newTeams = getTeams(5);

$pageTitle = "Home";
?>

<!-- Match Summary Cards dengan Horizontal Scroll -->
<div class="container section-container">
    <div class="section-header">
        <h2 class="section-title">LATEST MATCHES</h2>
        <div class="scroll-controls">
            <button class="scroll-btn scroll-prev" aria-label="Scroll left">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="scroll-btn scroll-next" aria-label="Scroll right">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
    
    <div class="match-cards-container">
        <div class="match-cards-scroll">
            <?php 
            $matches = [
                [
                    'id' => 1,
                    'date' => '25 Jan', 
                    'comp' => 'PL AAFI 2026', 
                    'team1' => 'PAFCA', 
                    'team1_logo' => 'PAFCA.png',
                    'score1' => 5, 
                    'team2' => '014 BUFC',
                    'team2_logo' => '014-bufc.png',
                    'score2' => 1,
                    'venue' => 'Golden Sport Center'
                ],
                [
                    'id' => 2,
                    'date' => '25 Jan', 
                    'comp' => 'PL AAFI 2026', 
                    'team1' => 'GENERASI FAB', 
                    'team1_logo' => 'generasi-fab.png',
                    'score1' => 0, 
                    'team2' => 'FAMILY FUTSAL BALIKPAPAN',
                    'team2_logo' => 'famili-balikpapan.png',
                    'score2' => 4,
                    'venue' => 'Golden Sport Center'
                ],
                [
                    'id' => 3,
                    'date' => '25 Jan', 
                    'comp' => 'JTFL', 
                    'team1' => 'KUDA LAUT NUSANTARA', 
                    'team1_logo' => 'kuda-laut-nusantara.png',
                    'score1' => 3, 
                    'team2' => 'ANTRI FUTSAL SCHOOL GNR',
                    'team2_logo' => 'antri-futsal.png',
                    'score2' => 2,
                    'venue' => 'Golden Sport Center'
                ],
                [
                    'id' => 4,
                    'date' => '25 Jan', 
                    'comp' => 'AAFI TANGGERANG 1', 
                    'team1' => 'APOLLO FUTSAL ACADEMY', 
                    'team1_logo' => 'apollo futsal.png',
                    'score1' => 1, 
                    'team2' => 'TWO IN ONE FA',
                    'team2_logo' => 'two in one.png',
                    'score2' => 5,
                    'venue' => 'Golden Sport Center'
                ],
                [
                    'id' => 5,
                    'date' => '26 Jan', 
                    'comp' => 'JFTL', 
                    'team1' => 'MESS FUTSAL', 
                    'team1_logo' => 'mess-futsal.png',
                    'score1' => 3, 
                    'team2' => 'BAHATI FUTSAL',
                    'team2_logo' => 'bahati-futsal.png',
                    'score2' => 1,
                    'venue' => 'Sport Center'
                ]
            ];
            
            foreach ($matches as $match): 
            ?>
            <div class="match-card-wrapper">
                <div class="match-card" data-match-id="<?php echo $match['id']; ?>">
                    <div class="match-header">
                        <span class="match-date"><?php echo $match['date']; ?> | <?php echo $match['comp']; ?></span>
                        <span class="match-status">FT</span>
                    </div>
                    
                    <div class="match-teams">
                        <!-- TEAM 1 -->
                        <div class="team">
                            <?php
                            $logo1 = $match['team1_logo'];
                            $logo1Path = SITE_URL . '/images/teams/' . $logo1;
                            ?>
                            <div class="team-logo-container">
                                <img src="<?php echo $logo1Path; ?>" 
                                     alt="<?php echo $match['team1']; ?>" 
                                     class="team-logo"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                            </div>
                            <span class="team-name"><?php echo $match['team1']; ?></span>
                        </div>
                        
                        <div class="vs">VS</div>
                        
                        <!-- TEAM 2 -->
                        <div class="team">
                            <?php
                            $logo2 = $match['team2_logo'];
                            $logo2Path = SITE_URL . '/images/teams/' . $logo2;
                            ?>
                            <div class="team-logo-container">
                                <img src="<?php echo $logo2Path; ?>" 
                                     alt="<?php echo $match['team2']; ?>" 
                                     class="team-logo"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                            </div>
                            <span class="team-name"><?php echo $match['team2']; ?></span>
                        </div>
                    </div>
                    
                    <div class="match-score">
                        <?php echo $match['score1']; ?> - <?php echo $match['score2']; ?>
                    </div>
                    
                    <div class="match-details">
                        <span class="match-venue"><i class="fas fa-map-marker-alt"></i> <?php echo $match['venue']; ?></span>
                        <button class="btn-details" data-match-id="<?php echo $match['id']; ?>">View Details</button>
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
        <h2 class="section-title">NEWS</h2>
        <div class="section-tabs">
            <button class="tab-button active" data-tab="new-news">New</button>
            <button class="tab-button" data-tab="popular-news">Popular</button>
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
                $image = !empty($news['image']) ? $news['image'] : 'news1.jpg';
                $imagePath = SITE_URL . '/images/news/' . $image;
                $defaultImage = SITE_URL . '/images/news/default-news.jpg';
                ?>
                <div class="news-item-large" data-news-id="<?php echo $news['id']; ?>">
                    <img src="<?php echo $imagePath; ?>" 
                         alt="<?php echo htmlspecialchars($news['title']); ?>" 
                         class="news-image"
                         onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                    <div class="news-content">
                        <div class="news-meta">
                            <span class="news-date"><?php echo formatDate($news['created_at']); ?></span>
                            <span class="news-views"><i class="fas fa-eye"></i> <span class="view-count" id="view-count-<?php echo $news['id']; ?>"><?php echo $news['views']; ?></span>x</span>
                        </div>
                        <h3 class="news-title">
                            <a href="<?php echo SITE_URL; ?>/news.php?slug=<?php echo $news['slug']; ?>" class="news-link" data-news-id="<?php echo $news['id']; ?>">
                                <?php echo htmlspecialchars($news['title']); ?>
                            </a>
                        </h3>
                        <p class="news-excerpt">
                            <?php 
                            $excerpt = strip_tags($news['content']);
                            echo htmlspecialchars(mb_substr($excerpt, 0, 100)) . '...'; 
                            ?>
                        </p>
                        <p class="news-author">by <?php echo htmlspecialchars($news['author']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="news-sidebar">
                <?php 
                for ($i = 1; $i < count($newNews); $i++): 
                    $news = $newNews[$i];
                    $image = !empty($news['image']) ? $news['image'] : 'news' . ($i + 1) . '.jpg';
                    $imagePath = SITE_URL . '/images/news/' . $image;
                    $defaultImage = SITE_URL . '/images/news/default-news.jpg';
                ?>
                <div class="news-item-small" data-news-id="<?php echo $news['id']; ?>">
                    <div class="news-thumbnail">
                        <img src="<?php echo $imagePath; ?>" 
                             alt="<?php echo htmlspecialchars($news['title']); ?>"
                             class="news-thumb"
                             onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                    </div>
                    <div class="news-content-small">
                        <div class="news-meta">
                            <span class="news-date"><?php echo formatDate($news['created_at']); ?></span>
                            <span class="news-views"><i class="fas fa-eye"></i> <span class="view-count" id="view-count-<?php echo $news['id']; ?>"><?php echo $news['views']; ?></span>x</span>
                        </div>
                        <h4 class="news-title">
                            <a href="<?php echo SITE_URL; ?>/news.php?slug=<?php echo $news['slug']; ?>" class="news-link" data-news-id="<?php echo $news['id']; ?>">
                                <?php echo htmlspecialchars($news['title']); ?>
                            </a>
                        </h4>
                        <p class="news-author">by <?php echo htmlspecialchars($news['author']); ?></p>
                    </div>
                </div>
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
                $image = !empty($news['image']) ? $news['image'] : 'news1.jpg';
                $imagePath = SITE_URL . '/images/news/' . $image;
                $defaultImage = SITE_URL . '/images/news/default-news.jpg';
                ?>
                <div class="news-item-large" data-news-id="<?php echo $news['id']; ?>">
                    <img src="<?php echo $imagePath; ?>" 
                         alt="<?php echo htmlspecialchars($news['title']); ?>" 
                         class="news-image"
                         onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                    <div class="news-content">
                        <div class="news-meta">
                            <span class="news-date"><?php echo formatDate($news['created_at']); ?></span>
                            <span class="news-views"><i class="fas fa-eye"></i> <span class="view-count" id="view-count-<?php echo $news['id']; ?>"><?php echo $news['views']; ?></span>x</span>
                            <span class="news-popular-badge"><i class="fas fa-fire"></i> Trending</span>
                        </div>
                        <h3 class="news-title">
                            <a href="<?php echo SITE_URL; ?>/news.php?slug=<?php echo $news['slug']; ?>" class="news-link" data-news-id="<?php echo $news['id']; ?>">
                                <?php echo htmlspecialchars($news['title']); ?>
                            </a>
                        </h3>
                        <p class="news-excerpt">
                            <?php 
                            $excerpt = strip_tags($news['content']);
                            echo htmlspecialchars(mb_substr($excerpt, 0, 100)) . '...'; 
                            ?>
                        </p>
                        <p class="news-author">by <?php echo htmlspecialchars($news['author']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="news-sidebar">
                <?php 
                for ($i = 1; $i < count($popularNews); $i++): 
                    $news = $popularNews[$i];
                    $image = !empty($news['image']) ? $news['image'] : 'news' . ($i + 1) . '.jpg';
                    $imagePath = SITE_URL . '/images/news/' . $image;
                    $defaultImage = SITE_URL . '/images/news/default-news.jpg';
                ?>
                <div class="news-item-small" data-news-id="<?php echo $news['id']; ?>">
                    <div class="news-thumbnail">
                        <img src="<?php echo $imagePath; ?>" 
                             alt="<?php echo htmlspecialchars($news['title']); ?>"
                             class="news-thumb"
                             onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                    </div>
                    <div class="news-content-small">
                        <div class="news-meta">
                            <span class="news-date"><?php echo formatDate($news['created_at']); ?></span>
                            <span class="news-views"><i class="fas fa-eye"></i> <span class="view-count" id="view-count-<?php echo $news['id']; ?>"><?php echo $news['views']; ?></span>x</span>
                        </div>
                        <h4 class="news-title">
                            <a href="<?php echo SITE_URL; ?>/news.php?slug=<?php echo $news['slug']; ?>" class="news-link" data-news-id="<?php echo $news['id']; ?>">
                                <?php echo htmlspecialchars($news['title']); ?>
                            </a>
                        </h4>
                        <p class="news-author">by <?php echo htmlspecialchars($news['author']); ?></p>
                    </div>
                </div>
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
        <h2 class="section-title">MATCHES</h2>
        <div class="section-tabs">
            <button class="tab-button active" data-tab="match-schedule">Schedule</button>
            <button class="tab-button" data-tab="match-result">Results</button>
        </div>
    </div>
    
    <!-- Schedule Tab -->
    <div class="tab-content active" id="match-schedule">
        <?php 
        // Data schedule yang sinkron dengan result
        $scheduleMatches = [
            [
                'id' => 101,
                'team1' => 'PAFCA',
                'team1_logo' => 'PAFCA.png',
                'team2' => '014 BUFC',
                'team2_logo' => '014-bufc.png',
                'date' => '01 Feb 2026',
                'time' => '10:15',
                'event' => 'PL AAFI 2026',
                'round' => 'Semi Final - Pekan ke-3',
                'venue' => 'LAP SEPINGGAN PRATAMA - Lap 2',
                'status' => 'scheduled'
            ],
            [
                'id' => 102,
                'team1' => 'GENERASI FAB',
                'team1_logo' => 'generasi-fab.png',
                'team2' => 'FAMILY FUTSAL BALIKPAPAN',
                'team2_logo' => 'famili-balikpapan.png',
                'date' => '01 Feb 2026',
                'time' => '10:45',
                'event' => 'PL AAFI 2026',
                'round' => 'Semi Final - Pekan ke-3',
                'venue' => 'LAP SEPINGGAN PRATAMA - Lap 2',
                'status' => 'scheduled'
            ],
            [
                'id' => 103,
                'team1' => 'KUDA LAUT NUSANTARA',
                'team1_logo' => 'kuda-laut-nusantara.png',
                'team2' => 'ANTRI FUTSAL SCHOOL GNR',
                'team2_logo' => 'antri-futsal.png',
                'date' => '01 Feb 2026',
                'time' => '10:45',
                'event' => 'JTFL',
                'round' => 'Semi Final - Pekan ke-3',
                'venue' => 'LAP SEPINGGAN PRATAMA - Lap 2',
                'status' => 'scheduled'
            ],
            [
                'id' => 104,
                'team1' => 'APOLLO FUTSAL ACADEMY',
                'team1_logo' => 'apollo futsal.png',
                'team2' => 'TWO IN ONE FA',
                'team2_logo' => 'two in one.png',
                'date' => '01 Feb 2026',
                'time' => '10:45',
                'event' => 'AAFI TANGGERANG 1',
                'round' => 'Semi Final - Pekan ke-3',
                'venue' => 'LAP SEPINGGAN PRATAMA - Lap 2',
                'status' => 'scheduled'
            ],
            [
                'id' => 105,
                'team1' => 'MESS FUTSAL',
                'team1_logo' => 'mess-futsal.png',
                'team2' => 'BAHATI FUTSAL',
                'team2_logo' => 'bahati-futsal.png',
                'date' => '01 Feb 2026',
                'time' => '11:15',
                'event' => 'JFTL',
                'round' => 'Semi Final - Pekan ke-3',
                'venue' => 'Golden Sport Center - Lap 2',
                'status' => 'scheduled'
            ]
        ];
        
        if (empty($scheduleMatches)): 
        ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h4>Tidak ada jadwal pertandingan</h4>
            <p>Belum ada pertandingan yang dijadwalkan</p>
        </div>
        <?php else: ?>
        <div class="match-table-container">
            <div class="table-responsive">
                <table class="match-table">
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th class="col-match">Match</th>
                            <th class="col-datetime">Date & Time</th>
                            <th class="col-venue">Venue</th>
                            <th class="col-event">Event</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduleMatches as $index => $match): ?>
                        <tr class="match-row schedule-row" data-match-id="<?php echo $match['id']; ?>">
                            <td class="match-number"><?php echo $index + 1; ?></td>
                            <td class="match-teams-cell">
                                <div class="match-teams-info">
                                    <div class="team-info">
                                        <div class="team-logo-wrapper">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($match['team1']); ?>" 
                                                 class="team-logo-sm"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                        </div>
                                        <span class="team-name-sm"><?php echo htmlspecialchars($match['team1']); ?></span>
                                    </div>
                                    <div class="vs-sm">VS</div>
                                    <div class="team-info">
                                        <div class="team-logo-wrapper">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($match['team2']); ?>" 
                                                 class="team-logo-sm"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                        </div>
                                        <span class="team-name-sm"><?php echo htmlspecialchars($match['team2']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="match-datetime-cell">
                                <div class="datetime-info">
                                    <span class="date-info"><?php echo htmlspecialchars($match['date']); ?></span>
                                    <span class="time-info"><?php echo htmlspecialchars($match['time']); ?></span>
                                </div>
                            </td>
                            <td class="match-venue-cell">
                                <div class="venue-info">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span class="venue-text"><?php echo htmlspecialchars($match['venue']); ?></span>
                                </div>
                            </td>
                            <td class="match-event-cell">
                                <span class="event-badge"><?php echo htmlspecialchars($match['event']); ?></span>
                                <div class="round-info"><?php echo htmlspecialchars($match['round']); ?></div>
                            </td>
                            <td class="match-actions-cell">
                                <button class="btn-view btn-view-schedule" data-match-id="<?php echo $match['id']; ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="see-more-section">
            <a href="all.php?status=schedule" class="btn-see-more">
                <i class="fas fa-arrow-right"></i> See All Schedule
            </a>
        </div>
    </div>

    <!-- Result Tab -->
    <div class="tab-content" id="match-result">
        <?php 
        // Data result yang sinkron dengan schedule
        $resultMatches = [
            [
                'id' => 1,
                'team1' => 'PAFCA',
                'team1_logo' => 'PAFCA.png',
                'score1' => 5,
                'team2' => '014 BUFC',
                'team2_logo' => '014-bufc.png',
                'score2' => 1,
                'date' => '1 Feb 2026',
                'time' => '16:40',
                'event' => 'PL AAFI 2026',
                'venue' => 'LAP SEPINGGAN PRATAMA',
                'status' => 'completed'
            ],
            [
                'id' => 2,
                'team1' => 'GENERASI FAB',
                'team1_logo' => 'generasi-fab.png',
                'score1' => 0,
                'team2' => 'FAMILY FUTSAL BALIKPAPAN',
                'team2_logo' => 'famili-balikpapan.png',
                'score2' => 4,
                'date' => '1 Feb 2026',
                'time' => '15:50',
                'event' => 'PL AAFI 2026',
                'venue' => 'LAP SEPINGGAN PRATAMA',
                'status' => 'completed'
            ],
            [
                'id' => 3,
                'team1' => 'KUDA LAUT NUSANTARA',
                'team1_logo' => 'kuda-laut-nusantara.png',
                'score1' => 3,
                'team2' => 'ANTRI FUTSAL SCHOOL GNR',
                'team2_logo' => 'antri-futsal.png',
                'score2' => 2,
                'date' => '1 Feb 2026',
                'time' => '15:50',
                'event' => 'JTFL',
                'venue' => 'LAP SEPINGGAN PRATAMA',
                'status' => 'completed'
            ],
            [
                'id' => 4,
                'team1' => 'APOLLO FUTSAL ACADEMY',
                'team1_logo' => 'apollo futsal.png',
                'score1' => 1,
                'team2' => 'TWO IN ONE FA',
                'team2_logo' => 'two in one.png',
                'score2' => 5,
                'date' => '1 Feb 2026',
                'time' => '15:40',
                'event' => 'AAFI TANGGERANG 1',
                'venue' => 'LAP SEPINGGAN PRATAMA',
                'status' => 'completed'
            ],
            [
                'id' => 5,
                'team1' => 'MESS FUTSAL',
                'team1_logo' => 'mess-futsal.png',
                'score1' => 3,
                'team2' => 'BAHATI FUTSAL',
                'team2_logo' => 'bahati-futsal.png',
                'score2' => 1,
                'date' => '1 Feb 2026',
                'time' => '10:15',
                'event' => 'JFTL',
                'venue' => 'Golden Sport Center',
                'status' => 'completed'
            ]
        ];
        
        if (empty($resultMatches)): 
        ?>
        <div class="empty-state">
            <i class="fas fa-trophy"></i>
            <h4>Tidak ada hasil pertandingan</h4>
            <p>Belum ada pertandingan yang selesai</p>
        </div>
        <?php else: ?>
        <div class="match-table-container">
            <div class="table-responsive">
                <table class="match-table">
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th class="col-match">Match</th>
                            <th class="col-score">Score</th>
                            <th class="col-datetime">Date & Time</th>
                            <th class="col-venue">Venue</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultMatches as $index => $match): ?>
                        <tr class="match-row result-row" data-match-id="<?php echo $match['id']; ?>">
                            <td class="match-number"><?php echo $index + 1; ?></td>
                            <td class="match-teams-cell">
                                <div class="match-teams-info">
                                    <div class="team-info">
                                        <div class="team-logo-wrapper">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($match['team1']); ?>" 
                                                 class="team-logo-sm"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                        </div>
                                        <span class="team-name-sm"><?php echo htmlspecialchars($match['team1']); ?></span>
                                    </div>
                                    <div class="vs-sm">VS</div>
                                    <div class="team-info">
                                        <div class="team-logo-wrapper">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($match['team2']); ?>" 
                                                 class="team-logo-sm"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                        </div>
                                        <span class="team-name-sm"><?php echo htmlspecialchars($match['team2']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="match-score-cell">
                                <div class="score-info">
                                    <span class="score-team"><?php echo $match['score1']; ?></span>
                                    <span class="score-separator">-</span>
                                    <span class="score-team"><?php echo $match['score2']; ?></span>
                                </div>
                                <div class="match-status-badge completed">FT</div>
                            </td>
                            <td class="match-datetime-cell">
                                <div class="datetime-info">
                                    <span class="date-info"><?php echo htmlspecialchars($match['date']); ?></span>
                                    <span class="time-info"><?php echo htmlspecialchars($match['time']); ?></span>
                                </div>
                            </td>
                            <td class="match-venue-cell">
                                <div class="venue-info">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span class="venue-text"><?php echo htmlspecialchars($match['venue']); ?></span>
                                </div>
                            </td>
                            <td class="match-actions-cell">
                                <button class="btn-view btn-view-result" data-match-id="<?php echo $match['id']; ?>">
                                    <i class="fas fa-chart-bar"></i> Report
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="see-more-section">
            <a href="all.php?status=result" class="btn-see-more">
                <i class="fas fa-arrow-right"></i> See All Results
            </a>
        </div>
    </div>
</div>

<!-- Player Section -->
<div class="container section-container">
    <div class="section-header">
        <h2 class="section-title">PLAYERS</h2>
        <div class="section-tabs">
            <button class="tab-button active" data-tab="new-added">New Added</button>
            <button class="tab-button" data-tab="transfer">Transfer</button>
            <button class="tab-button" data-tab="birthday">Birthday</button>
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
                    <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player['photo']; ?>" 
                         alt="<?php echo $player['name']; ?>" 
                         class="player-photo"
                         onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                </div>
                <h3 class="player-name"><?php echo $player['name']; ?></h3>
                <p class="player-team">
                    <?php echo $player['team_name']; ?> #<?php echo $player['jersey_number']; ?>
                </p>
                <p class="player-info">
                    <?php echo $player['position']; ?> | <?php echo $player['age']; ?> years
                </p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="tab-content" id="transfer">
        <div class="empty-state">
            <i class="fas fa-exchange-alt"></i>
            <h4>Tidak ada transfer</h4>
            <p>Belum ada data transfer pemain</p>
        </div>
    </div>
    
    <div class="tab-content" id="birthday">
        <div class="empty-state">
            <i class="fas fa-birthday-cake"></i>
            <h4>Tidak ada ulang tahun</h4>
            <p>Belum ada pemain yang berulang tahun</p>
        </div>
    </div>
</div>

<!-- Team Section -->
<div class="container section-container">
    <div class="section-header">
        <h2 class="section-title">TEAMS</h2>
        <div class="section-tabs">
            <button class="tab-button active" data-tab="recent-winner">Recent Winners</button>
            <button class="tab-button" data-tab="new-team">New Added</button>
        </div>
    </div>
    
    <div class="tab-content active" id="recent-winner">
        <?php if (empty($recentWinners)): ?>
        <div class="empty-state">
            <i class="fas fa-trophy"></i>
            <h4>Tidak ada pemenang terkini</h4>
            <p>Belum ada tim yang memenangkan kompetisi</p>
        </div>
        <?php else: ?>
        <div class="teams-grid">
            <?php foreach ($recentWinners as $team): ?>
            <div class="team-card" data-team-id="<?php echo $team['id']; ?>">
                <div class="team-logo-container">
                    <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $team['logo']; ?>" 
                         alt="<?php echo $team['name']; ?>" 
                         class="team-logo-lg"
                         onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                </div>
                <h3 class="team-name"><?php echo $team['name']; ?></h3>
                <p class="team-achievement"><?php echo $team['achievement']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="tab-content" id="new-team">
        <?php if (empty($newTeams)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h4>Tidak ada tim baru</h4>
            <p>Belum ada tim yang ditambahkan</p>
        </div>
        <?php else: ?>
        <div class="teams-grid">
            <?php foreach ($newTeams as $team): ?>
            <div class="team-card" data-team-id="<?php echo $team['id']; ?>">
                <div class="team-logo-container">
                    <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $team['logo']; ?>" 
                         alt="<?php echo $team['name']; ?>" 
                         class="team-logo-lg"
                         onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                </div>
                <h3 class="team-name"><?php echo $team['name']; ?></h3>
                <p class="team-label">New Team</p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Match Detail Modal -->
<div class="match-modal" id="matchModal">
    <div class="match-modal-content">
        <div class="match-modal-header">
            <h3 id="matchModalTitle">Match Details</h3>
            <button class="match-modal-close" id="closeMatchModal">&times;</button>
        </div>
        
        <div class="match-modal-body">
            <div class="match-tabs">
                <button class="match-tab active" data-tab="goals">Goals</button>
                <button class="match-tab" data-tab="timeline">Timeline</button>
                <button class="match-tab" data-tab="lineups">Lineups</button>
            </div>
            
            <div class="match-detail-header">
                <div class="match-teams-large">
                    <div class="team-large">
                        <img id="team1LogoLarge" src="" alt="" class="team-logo-large">
                        <h4 id="team1Name"></h4>
                    </div>
                    
                    <div class="vs-large">
                        <span class="vs-text-large">VS</span>
                        <div class="score-large" id="matchScoreLarge"></div>
                    </div>
                    
                    <div class="team-large">
                        <img id="team2LogoLarge" src="" alt="" class="team-logo-large">
                        <h4 id="team2Name"></h4>
                    </div>
                </div>
                
                <div class="match-info">
                    <p id="matchDateTime"></p>
                    <p id="matchLocation"></p>
                </div>
            </div>
            
            <div class="match-tab-content active" id="goalsContent">
                <h4>Goals</h4>
                <div class="goals-list" id="goalsList"></div>
            </div>
            
            <div class="match-tab-content" id="timelineContent">
                <h4>Timeline</h4>
                <div class="timeline-filter">
                    <select id="timelineFilter">
                        <option value="all">All Events</option>
                        <option value="goal">Goals</option>
                        <option value="foul">Fouls</option>
                        <option value="substitution">Substitutions</option>
                    </select>
                </div>
                <div class="timeline-list" id="timelineList"></div>
            </div>
            
            <div class="match-tab-content" id="lineupsContent">
                <h4>Lineups</h4>
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
                    <input type="text" id="playerSearch" placeholder="Search by Player ID">
                    <button id="searchPlayerBtn">Search</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div class="schedule-modal" id="scheduleModal">
    <div class="schedule-modal-content">
        <div class="schedule-modal-header">
            <h3 id="scheduleModalTitle">Schedule Details</h3>
            <button class="schedule-modal-close" id="closeScheduleModal">&times;</button>
        </div>
        
        <div class="schedule-modal-body" id="scheduleModalContent"></div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>