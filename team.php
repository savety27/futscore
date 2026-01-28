<?php
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
    
    // Get events this team participated in
    $conn = $db->getConnection();
    $eventSql = "SELECT DISTINCT e.id, e.name 
                 FROM events e
                 INNER JOIN matches m ON e.id = m.event_id
                 WHERE (m.team1_id = ? OR m.team2_id = ?)
                 ORDER BY e.start_date DESC";
    $eventStmt = $conn->prepare($eventSql);
    $eventStmt->bind_param("ii", $teamId, $teamId);
    $eventStmt->execute();
    $eventsResult = $eventStmt->get_result();
    $events = [];
    while ($row = $eventsResult->fetch_assoc()) {
        $events[] = $row;
    }
    
    $pageTitle = $team['name'];
} else {
    // TEAM LISTING VIEW
    $allTeams = getAllTeams();
    $pageTitle = "Teams";
}
?>

<style>
/* Team Listing Styles */
.team-hero {
    background: linear-gradient(135deg, #1a1a1a 0%, #c00 100%);
    padding: 60px 0;
    text-align: center;
    color: #fff;
    margin-bottom: 40px;
}

.team-hero h1 {
    font-size: 48px;
    font-weight: 800;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 3px;
}

.team-selector {
    max-width: 500px;
    margin: 0 auto 40px;
}

.team-selector select {
    width: 100%;
    padding: 15px 20px;
    font-size: 16px;
    border: 2px solid #ddd;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    transition: all 0.3s;
}

.team-selector select:hover {
    border-color: #c00;
}

.teams-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.team-logo-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    text-decoration: none;
    color: inherit;
    display: block;
}

.team-logo-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(204, 0, 0, 0.3);
}

.team-logo-card img {
    width: 100px;
    height: 100px;
    object-fit: contain;
    margin-bottom: 10px;
}

.team-logo-card .team-name {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

/* Team Detail Styles */
.team-detail-container {
    padding: 40px 0;
}

.team-info-card {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    border-radius: 16px;
    padding: 40px;
    color: #fff;
    margin-bottom: 40px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.team-info-header {
    display: flex;
    align-items: center;
    gap: 30px;
    margin-bottom: 30px;
}

.team-logo-large {
    width: 150px;
    height: 150px;
    object-fit: contain;
    background: rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 15px;
}

.team-title-section h1 {
    font-size: 42px;
    font-weight: 800;
    margin: 0 0 10px 0;
    text-transform: uppercase;
}

.team-subtitle {
    font-size: 16px;
    opacity: 0.8;
    margin-bottom: 20px;
}

.team-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.detail-item {
    background: rgba(255,255,255,0.1);
    padding: 15px;
    border-radius: 8px;
}

.detail-label {
    font-size: 12px;
    text-transform: uppercase;
    opacity: 0.7;
    margin-bottom: 5px;
}

.detail-value {
    font-size: 18px;
    font-weight: 600;
}

.share-button {
    background: #c00;
    color: #fff;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    margin-top: 20px;
}

.share-button:hover {
    background: #a00;
    transform: scale(1.05);
}

/* Player Section */
.player-section {
    background: #fff;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.section-title {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 20px;
    color: #333;
}

.player-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    border-bottom: 2px solid #eee;
    flex-wrap: wrap;
}

.player-tab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    cursor: pointer;
    font-weight: 600;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
}

.player-tab.active {
    color: #c00;
    border-bottom-color: #c00;
}

.player-tab:hover {
    color: #c00;
}

.player-content {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 30px;
}

.player-list {
    max-height: 600px;
    overflow-y: auto;
}

.player-item {
    display: flex;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
    transition: all 0.3s;
}

.player-item:hover,
.player-item.active {
    background: #f8f8f8;
}

.position-badge {
    width: 30px;
    height: 30px;
    background: #c00;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 12px;
    margin-right: 15px;
    flex-shrink: 0;
}

.position-badge.keeper {
    background: #f39c12;
}

.player-item-info {
    flex: 1;
}

.player-number {
    font-weight: 700;
    color: #c00;
    margin-right: 10px;
}

.player-name {
    font-weight: 600;
    color: #333;
}

.player-detail-panel {
    background: #f8f8f8;
    border-radius: 12px;
    padding: 25px;
    position: sticky;
    top: 20px;
}

.player-photo-container {
    text-align: center;
    margin-bottom: 20px;
}

.player-photo-large {
    width: 200px;
    height: 200px;
    object-fit: cover;
    border-radius: 12px;
    margin-bottom: 15px;
}

.player-detail-info {
    margin-bottom: 20px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #ddd;
}

.info-label {
    font-weight: 600;
    color: #666;
}

.info-value {
    color: #333;
}

.detail-button {
    width: 100%;
    padding: 12px;
    background: #0066cc;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.detail-button:hover {
    background: #0052a3;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 20px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .player-content {
        grid-template-columns: 1fr;
    }
    
    .player-detail-panel {
        position: static;
    }
    
    .teams-grid {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    }
    
    .team-info-header {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<?php if ($teamId > 0): ?>
    <!-- TEAM DETAIL VIEW -->
    <div class="container team-detail-container">
        <div class="team-info-card">
            <div class="team-info-header">
                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $team['logo']; ?>" 
                     alt="<?php echo htmlspecialchars($team['name']); ?>" 
                     class="team-logo-large"
                     onerror="this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                <div class="team-title-section">
                    <h1><?php echo htmlspecialchars($team['name']); ?></h1>
                    <?php if (!empty($team['established_year'])): ?>
                        <div class="team-subtitle">Didirikan sejak <?php echo date('d M Y', strtotime($team['established_year'] . '-01-01')); ?></div>
                    <?php endif; ?>
                    
                    <div class="team-details-grid">
                        <?php if (!empty($team['manager']) || !empty($team['coach'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">Manager / Coach</div>
                            <div class="detail-value">
                                <?php 
                                $managerCoach = [];
                                if (!empty($team['manager'])) $managerCoach[] = htmlspecialchars($team['manager']);
                                if (!empty($team['coach'])) $managerCoach[] = htmlspecialchars($team['coach']);
                                echo implode(' / ', $managerCoach);
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($team['basecamp'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">Basecamp</div>
                            <div class="detail-value"><?php echo htmlspecialchars($team['basecamp']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($team['contact'])): ?>
                        <div class="detail-item">
                            <div class="detail-label">Kontak</div>
                            <div class="detail-value"><?php echo htmlspecialchars($team['contact']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <button class="share-button" onclick="shareTeam()">
                        <i class="fas fa-share-alt"></i> Share
                    </button>
                </div>
            </div>
        </div>
        
        <div class="player-section">
            <h2 class="section-title">Player/Lineup</h2>
            
            <div class="player-tabs">
                <?php foreach ($events as $event): ?>
                    <button class="player-tab" data-event="<?php echo $event['id']; ?>">
                        <?php echo htmlspecialchars($event['name']); ?>
                    </button>
                <?php endforeach; ?>
                <button class="player-tab active" data-tab="all">Non Category</button>
                <button class="player-tab" data-tab="staff">Pelatih / Staff</button>
            </div>
            
            <div class="player-content">
                <div class="player-list" id="playerList">
                    <?php if (empty($players)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h4>Tidak ada pemain</h4>
                            <p>Belum ada pemain yang terdaftar di tim ini</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($players as $index => $player): ?>
                            <div class="player-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                                 data-player-id="<?php echo $player['id']; ?>"
                                 onclick="showPlayerDetail(<?php echo htmlspecialchars(json_encode($player)); ?>)">
                                <div class="position-badge <?php echo strtolower($player['position']) === 'gk' ? 'keeper' : ''; ?>">
                                    <?php echo strtoupper(substr($player['position'] ?: 'P', 0, 1)); ?>
                                </div>
                                <div class="player-item-info">
                                    <span class="player-number"><?php echo $player['jersey_number'] ?: '-'; ?>.</span>
                                    <span class="player-name"><?php echo htmlspecialchars($player['name']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="player-detail-panel" id="playerDetailPanel">
                    <?php if (!empty($players)): 
                        $firstPlayer = $players[0];
                    ?>
                        <div class="player-photo-container">
                            <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $firstPlayer['photo']; ?>" 
                                 alt="<?php echo htmlspecialchars($firstPlayer['name']); ?>" 
                                 class="player-photo-large"
                                 id="playerPhoto"
                                 onerror="this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                        </div>
                        
                        <div class="player-detail-info" id="playerDetailInfo">
                            <div class="info-row">
                                <span class="info-label">KEBANGSAAN:</span>
                                <span class="info-value"><?php echo htmlspecialchars($firstPlayer['nationality'] ?: 'Indonesia'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">NISN:</span>
                                <span class="info-value"><?php echo htmlspecialchars($firstPlayer['nisn'] ?: '-'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">TMP/TGL LAHIR:</span>
                                <span class="info-value">
                                    <?php 
                                    $birthInfo = [];
                                    if (!empty($firstPlayer['birth_place'])) $birthInfo[] = $firstPlayer['birth_place'];
                                    if (!empty($firstPlayer['birth_date']) && $firstPlayer['birth_date'] != '0000-00-00') {
                                        $birthInfo[] = date('d M Y', strtotime($firstPlayer['birth_date']));
                                    }
                                    echo htmlspecialchars(implode(', ', $birthInfo) ?: '-');
                                    ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">USIA:</span>
                                <span class="info-value"><?php echo $firstPlayer['age']; ?> Tahun <?php 
                                    if (!empty($firstPlayer['birth_date']) && $firstPlayer['birth_date'] != '0000-00-00') {
                                        $birth = new DateTime($firstPlayer['birth_date']);
                                        $today = new DateTime();
                                        echo $today->diff($birth)->m;
                                    } else {
                                        echo '0';
                                    }
                                ?> Bulan</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">POSISI:</span>
                                <span class="info-value"><?php echo htmlspecialchars($firstPlayer['position'] ?: '-'); ?></span>
                            </div>
                        </div>
                        
                        <button class="detail-button" onclick="window.location.href='<?php echo SITE_URL; ?>/player_view.php?id=<?php echo $firstPlayer['id']; ?>'">
                            <i class="fas fa-info-circle"></i> Detail
                        </button>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>Pilih pemain untuk melihat detail</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function showPlayerDetail(player) {
        // Update active state
        document.querySelectorAll('.player-item').forEach(item => {
            item.classList.remove('active');
        });
        event.currentTarget.classList.add('active');
        
        // Update player photo
        const photo = document.getElementById('playerPhoto');
        photo.src = '<?php echo SITE_URL; ?>/images/players/' + (player.photo || 'default-player.jpg');
        
        // Calculate months
        let months = 0;
        if (player.birth_date && player.birth_date !== '0000-00-00') {
            const birth = new Date(player.birth_date);
            const today = new Date();
            months = today.getMonth() - birth.getMonth();
            if (months < 0) months += 12;
        }
        
        // Update player info
        const birthInfo = [];
        if (player.birth_place) birthInfo.push(player.birth_place);
        if (player.birth_date && player.birth_date !== '0000-00-00') {
            const date = new Date(player.birth_date);
            birthInfo.push(date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }));
        }
        
        document.getElementById('playerDetailInfo').innerHTML = `
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
                <span class="info-value">${player.age} Tahun ${months} Bulan</span>
            </div>
            <div class="info-row">
                <span class="info-label">POSISI:</span>
                <span class="info-value">${player.position || '-'}</span>
            </div>
        `;
        
        // Update detail button
        const detailBtn = document.querySelector('.detail-button');
        detailBtn.onclick = () => window.location.href = '<?php echo SITE_URL; ?>/player_view.php?id=' + player.id;
    }
    
    function shareTeam() {
        if (navigator.share) {
            navigator.share({
                title: '<?php echo htmlspecialchars($team['name']); ?>',
                text: 'Check out <?php echo htmlspecialchars($team['name']); ?> on Futscore',
                url: window.location.href
            });
        } else {
            // Fallback: copy to clipboard
            navigator.clipboard.writeText(window.location.href);
            alert('Link copied to clipboard!');
        }
    }
    </script>

<?php else: ?>
    <!-- TEAM LISTING VIEW -->
    <div class="team-hero">
        <h1>TEAM</h1>
    </div>
    
    <div class="container">
        <div class="team-selector">
            <select onchange="if(this.value) window.location.href='team.php?id=' + this.value">
                <option value="">Pilih team dari dropdown atau daftar di bawah</option>
                <?php foreach ($allTeams as $team): ?>
                    <option value="<?php echo $team['id']; ?>">
                        <?php echo htmlspecialchars($team['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="teams-grid">
            <?php foreach ($allTeams as $team): ?>
                <a href="team.php?id=<?php echo $team['id']; ?>" class="team-logo-card">
                    <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $team['logo']; ?>" 
                         alt="<?php echo htmlspecialchars($team['name']); ?>"
                         onerror="this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                    <div class="team-name"><?php echo htmlspecialchars($team['name']); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
