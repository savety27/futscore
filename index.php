<?php
require_once 'includes/header.php';

// Get data from database
$latestChallenges = getLatestChallenges(5);
$scheduledMatches = getScheduledChallenges(5);
$completedMatches = getCompletedChallenges(5);
$newPlayers = getPlayers(5);
$recentTransfers = getPlayerTransfers(5);

$recentWinners = getRecentWinners(5);
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
                                     alt="<?php echo $match['challenger_name']; ?>" 
                                     class="team-logo"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/MGP FC.jpeg'">
                            </div>
                            <span class="team-name"><?php echo $match['challenger_name']; ?></span>
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
                                     alt="<?php echo $match['opponent_name']; ?>" 
                                     class="team-logo"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/MGP FC.jpeg'">
                            </div>
                            <span class="team-name"><?php echo $match['opponent_name']; ?></span>
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
                        <span class="match-venue"><i class="fas fa-map-marker-alt"></i> <?php echo $match['venue_name']; ?></span>
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
                $image = !empty($news['gambar']) ? $news['gambar'] : 'news1.jpg';
                $imagePath = SITE_URL . '/images/berita/' . $image;
                $defaultImage = SITE_URL . '/images/berita/default-news.jpg';
                ?>
                <div class="news-item-large" data-news-id="<?php echo $news['id']; ?>">
                    <img src="<?php echo $imagePath; ?>" 
                         alt="<?php echo htmlspecialchars($news['judul']); ?>" 
                         class="news-image"
                         onerror="this.onerror=null; this.src='<?php echo $defaultImage; ?>'">
                    <div class="news-content">
                        <div class="news-meta">
                            <span class="news-date"><?php echo formatDate($news['created_at']); ?></span>
                            <span class="news-views"><i class="fas fa-eye"></i> <span class="view-count" id="view-count-<?php echo $news['id']; ?>"><?php echo $news['views']; ?></span>x</span>
                        </div>
                        <h3 class="news-title">
                            <a href="<?php echo SITE_URL; ?>/news.php?slug=<?php echo $news['slug']; ?>" class="news-link" data-news-id="<?php echo $news['id']; ?>">
                                <?php echo htmlspecialchars($news['judul']); ?>
                            </a>
                        </h3>
                        <p class="news-excerpt">
                            <?php 
                            $content = $news['konten'] ?? ($news['content'] ?? '');
                            $excerpt = strip_tags($content);
                            echo htmlspecialchars(mb_substr($excerpt, 0, 100)) . '...'; 
                            ?>
                        </p>
                        <p class="news-author">by <?php echo htmlspecialchars($news['penulis']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="news-sidebar">
                <?php 
                for ($i = 1; $i < count($newNews); $i++): 
                    $news = $newNews[$i];
                    $image = !empty($news['gambar']) ? $news['gambar'] : 'news' . ($i + 1) . '.jpg';
                    $imagePath = SITE_URL . '/images/berita/' . $image;
                    $defaultImage = SITE_URL . '/images/berita/default-news.jpg';
                ?>
                <div class="news-item-small" data-news-id="<?php echo $news['id']; ?>">
                    <div class="news-thumbnail">
                        <img src="<?php echo $imagePath; ?>" 
                             alt="<?php echo htmlspecialchars($news['judul']); ?>"
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
                                <?php echo htmlspecialchars($news['judul']); ?>
                            </a>
                        </h4>
                        <p class="news-author">by <?php echo htmlspecialchars($news['penulis']); ?></p>
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
                $image = !empty($news['gambar']) ? $news['gambar'] : 'news1.jpg';
                $imagePath = SITE_URL . '/images/berita/' . $image;
                $defaultImage = SITE_URL . '/images/berita/default-news.jpg';
                ?>
                <div class="news-item-large" data-news-id="<?php echo $news['id']; ?>">
                    <img src="<?php echo $imagePath; ?>" 
                         alt="<?php echo htmlspecialchars($news['judul']); ?>" 
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
                                <?php echo htmlspecialchars($news['judul']); ?>
                            </a>
                        </h3>
                        <p class="news-excerpt">
                            <?php 
                            $excerpt = strip_tags($news['konten']);
                            echo htmlspecialchars(mb_substr($excerpt, 0, 100)) . '...'; 
                            ?>
                        </p>
                        <p class="news-author">by <?php echo htmlspecialchars($news['penulis']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="news-sidebar">
                <?php 
                for ($i = 1; $i < count($popularNews); $i++): 
                    $news = $popularNews[$i];
                    $image = !empty($news['gambar']) ? $news['gambar'] : 'news' . ($i + 1) . '.jpg';
                    $imagePath = SITE_URL . '/images/berita/' . $image;
                    $defaultImage = SITE_URL . '/images/berita/default-news.jpg';
                ?>
                <div class="news-item-small" data-news-id="<?php echo $news['id']; ?>">
                    <div class="news-thumbnail">
                        <img src="<?php echo $imagePath; ?>" 
                             alt="<?php echo htmlspecialchars($news['judul']); ?>"
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
                                <?php echo htmlspecialchars($news['judul']); ?>
                            </a>
                        </h4>
                        <p class="news-author">by <?php echo htmlspecialchars($news['penulis']); ?></p>
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
        if (empty($scheduledMatches)): 
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
                        <?php foreach ($scheduledMatches as $index => $match): ?>
                        <tr class="match-row schedule-row" data-match-id="<?php echo $match['id']; ?>">
                            <td class="match-number"><?php echo $index + 1; ?></td>
                            <td class="match-teams-cell">
                                <div class="match-teams-info">
                                    <div class="team-info">
                                        <div class="team-logo-wrapper">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['challenger_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($match['challenger_name']); ?>" 
                                                 class="team-logo-sm"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/MGP FC.jpeg'">
                                        </div>
                                        <span class="team-name-sm"><?php echo htmlspecialchars($match['challenger_name']); ?></span>
                                    </div>
                                    <div class="vs-sm">VS</div>
                                    <div class="team-info">
                                        <div class="team-logo-wrapper">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['opponent_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($match['opponent_name']); ?>" 
                                                 class="team-logo-sm"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/MGP FC.jpeg'">
                                        </div>
                                        <span class="team-name-sm"><?php echo htmlspecialchars($match['opponent_name']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="match-datetime-cell">
                                <div class="datetime-info">
                                    <span class="date-info"><?php echo formatDate($match['challenge_date']); ?></span>
                                    <span class="time-info"><?php echo date('H:i', strtotime($match['challenge_date'])); ?></span>
                                </div>
                            </td>
                            <td class="match-venue-cell">
                                <div class="venue-info">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span class="venue-text"><?php echo htmlspecialchars($match['venue_name']); ?></span>
                                </div>
                            </td>
                            <td class="match-event-cell">
                                <span class="event-badge"><?php echo htmlspecialchars($match['sport_type']); ?></span>
                                <div class="round-info"><?php echo htmlspecialchars($match['challenge_code']); ?></div>
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
        if (empty($completedMatches)): 
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
                        <?php foreach ($completedMatches as $index => $match): ?>
                        <tr class="match-row result-row" data-match-id="<?php echo $match['id']; ?>">
                            <td class="match-number"><?php echo $index + 1; ?></td>
                            <td class="match-teams-cell">
                                <div class="match-teams-info">
                                    <div class="team-info">
                                        <div class="team-logo-wrapper">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['challenger_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($match['challenger_name']); ?>" 
                                                 class="team-logo-sm"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/MGP FC.jpeg'">
                                        </div>
                                        <span class="team-name-sm"><?php echo htmlspecialchars($match['challenger_name']); ?></span>
                                    </div>
                                    <div class="vs-sm">VS</div>
                                    <div class="team-info">
                                        <div class="team-logo-wrapper">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['opponent_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($match['opponent_name']); ?>" 
                                                 class="team-logo-sm"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/MGP FC.jpeg'">
                                        </div>
                                        <span class="team-name-sm"><?php echo htmlspecialchars($match['opponent_name']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="match-score-cell">
                                <div class="score-info">
                                    <span class="score-team"><?php echo $match['challenger_score']; ?></span>
                                    <span class="score-separator">-</span>
                                    <span class="score-team"><?php echo $match['opponent_score']; ?></span>
                                </div>
                                <div class="match-status-badge completed">FT</div>
                            </td>
                            <td class="match-datetime-cell">
                                <div class="datetime-info">
                                    <span class="date-info"><?php echo formatDate($match['challenge_date']); ?></span>
                                    <span class="time-info"><?php echo date('H:i', strtotime($match['challenge_date'])); ?></span>
                                </div>
                            </td>
                            <td class="match-venue-cell">
                                <div class="venue-info">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span class="venue-text"><?php echo htmlspecialchars($match['venue_name']); ?></span>
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
        <style>
            .transfers-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
                gap: 25px;
                margin: 30px 0;
            }
            .transfer-card {
                background: linear-gradient(145deg, #131313, #0a0a0a);
                border: 1px solid rgba(0, 255, 136, 0.2);
                border-radius: 20px;
                padding: 24px;
                display: flex;
                flex-direction: column;
                gap: 20px;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            }
            .transfer-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 4px;
                background: linear-gradient(90deg, transparent, var(--primary-green), transparent);
                opacity: 0.5;
            }
            .transfer-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 20px 40px rgba(0, 255, 136, 0.15);
                border-color: var(--primary-green);
            }
            .transfer-header {
                display: flex;
                gap: 18px;
                align-items: center;
            }
            .transfer-player-photo {
                width: 70px;
                height: 70px;
                border-radius: 18px;
                overflow: hidden;
                background: #1a1a1a;
                border: 2px solid rgba(255, 255, 255, 0.1);
                flex: 0 0 70px;
                position: relative;
            }
            .transfer-player-img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
            .transfer-player-details {
                flex: 1;
                min-width: 0;
            }
            .transfer-player-name {
                font-size: 18px;
                font-weight: 800;
                margin: 0 0 4px;
                color: #fff;
                letter-spacing: 0.5px;
                text-transform: uppercase;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .transfer-date-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 10px;
                background: rgba(0, 255, 136, 0.1);
                border-radius: 6px;
                color: #00ff88;
                font-size: 11px;
                font-weight: 700;
            }
            .transfer-flow {
                display: flex;
                align-items: center;
                background: rgba(255, 255, 255, 0.03);
                border-radius: 15px;
                padding: 15px;
                gap: 12px;
                border: 1px solid rgba(255, 255, 255, 0.05);
            }
            .transfer-team {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 8px;
                text-align: center;
                min-width: 0;
            }
            .transfer-team-label {
                font-size: 9px;
                text-transform: uppercase;
                color: #888;
                letter-spacing: 1px;
                margin-bottom: -4px;
            }
            .transfer-team-logo {
                width: 44px;
                height: 44px;
                border-radius: 50%;
                object-fit: contain;
                background: #000;
                border: 2px solid rgba(255, 255, 255, 0.05);
                padding: 4px;
            }
            .transfer-team-name {
                font-size: 12px;
                color: #fff;
                font-weight: 700;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                width: 100%;
            }
            .transfer-arrow-container {
                display: flex;
                align-items: center;
                color: #00ff88;
                font-size: 18px;
            }
            .transfer-footer {
                display: flex;
                justify-content: center;
            }
            .transfer-status {
                font-size: 10px;
                font-weight: 800;
                text-transform: uppercase;
                color: #000;
                background: #00ff88;
                padding: 4px 12px;
                border-radius: 99px;
                letter-spacing: 1px;
            }
        </style>
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
                $fromTeam = $transfer['from_team_name'] ?? 'Free Agent';
                $toTeam = $transfer['to_team_name'] ?? 'Free Agent';
                $transferDate = $transfer['transfer_date'] ?? ($transfer['created_at'] ?? null);
                
                $playerPhotoPath = SITE_URL . '/images/players/' . $playerPhoto;
                $fromLogoPath = $fromLogo ? SITE_URL . '/images/teams/' . $fromLogo : SITE_URL . '/images/MGP FC.jpeg';
                $toLogoPath = $toLogo ? SITE_URL . '/images/teams/' . $toLogo : SITE_URL . '/images/MGP FC.jpeg';
                ?>
                <div class="transfer-card">
                    <div class="transfer-header">
                        <div class="transfer-player-photo">
                            <img src="<?php echo $playerPhotoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($transfer['player_name'] ?? 'Player'); ?>"
                                 class="transfer-player-img"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                        </div>
                        <div class="transfer-player-details">
                            <h3 class="transfer-player-name"><?php echo htmlspecialchars($transfer['player_name'] ?? 'Unknown Player'); ?></h3>
                            <?php if (!empty($transferDate)): ?>
                                <div class="transfer-date-badge">
                                    <i class="far fa-calendar-alt"></i> <?php echo formatDate($transferDate); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="transfer-flow">
                        <div class="transfer-team">
                            <span class="transfer-team-label">From</span>
                            <img src="<?php echo $fromLogoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($fromTeam); ?>" 
                                 class="transfer-team-logo"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/MGP FC.jpeg'">
                            <span class="transfer-team-name"><?php echo htmlspecialchars($fromTeam); ?></span>
                        </div>
                        
                        <div class="transfer-arrow-container">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                        
                        <div class="transfer-team">
                            <span class="transfer-team-label">To</span>
                            <img src="<?php echo $toLogoPath; ?>" 
                                 alt="<?php echo htmlspecialchars($toTeam); ?>" 
                                 class="transfer-team-logo"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/MGP FC.jpeg'">
                            <span class="transfer-team-name"><?php echo htmlspecialchars($toTeam); ?></span>
                        </div>
                    </div>
                    
                    <div class="transfer-footer">
                        <span class="transfer-status">Confirmed</span>
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
        <div class="winners-grid">
            <?php foreach ($recentWinners as $team): ?>
            <a href="team.php?id=<?php echo $team['id']; ?>" class="winner-premium-card">
                <div class="winner-bg-glow"></div>
                <div class="winner-trophy-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="team-logo-container">
                    <?php 
                    $teamLogo = !empty($team['logo']) ? SITE_URL . '/images/teams/' . $team['logo'] : SITE_URL . '/images/MGP FC.jpeg';
                    ?>
                    <img src="<?php echo $teamLogo; ?>" 
                         alt="<?php echo htmlspecialchars($team['name']); ?>" 
                         class="team-logo-premium"
                         onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/MGP FC.jpeg'">
                </div>
                <div class="winner-info">
                    <h3 class="team-name"><?php echo htmlspecialchars($team['name']); ?></h3>
                    <div class="achievement-badge">
                        <i class="fas fa-trophy"></i>
                        <span><?php echo htmlspecialchars($team['achievement']); ?></span>
                    </div>
                </div>
                <div class="winner-footer">
                    <span class="view-profile">View Profile <i class="fas fa-arrow-right"></i></span>
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
            <h4>Tidak ada tim baru</h4>
            <p>Belum ada tim yang ditambahkan</p>
        </div>
        <?php else: ?>
        <div class="teams-grid">
            <?php foreach ($newTeams as $team): ?>
            <a href="team.php?id=<?php echo $team['id']; ?>" class="team-card" data-team-id="<?php echo $team['id']; ?>">
                <div class="team-logo-container">
                    <?php 
                    $tLogo = !empty($team['logo']) ? SITE_URL . '/images/teams/' . $team['logo'] : SITE_URL . '/images/MGP FC.jpeg';
                    ?>
                    <img src="<?php echo $tLogo; ?>" 
                         alt="<?php echo htmlspecialchars($team['name']); ?>" 
                         class="team-logo-lg"
                         onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/MGP FC.jpeg'">
                </div>
                <h3 class="team-name"><?php echo htmlspecialchars($team['name']); ?></h3>
                <p class="team-label">New Team</p>
            </a>
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
