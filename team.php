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

.position-badge.staff {
    background: #28a745;
}

.position-badge i {
    font-size: 14px;
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

.staff-position {
    font-size: 12px;
    color: #666;
    margin-top: 2px;
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
                <button class="player-tab active" data-tab="players">Players</button>
                <button class="player-tab" data-tab="staff">Pelatih / Staff</button>
            </div>
            
            <div class="player-content">
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
                const eventId = this.getAttribute('data-event');
                
                if (eventId) {
                    // Event-specific players (you can implement this later)
                    loadEventPlayers(eventId);
                } else if (tabType === 'staff') {
                    currentTab = 'staff';
                    loadStaff();
                } else {
                    currentTab = 'players';
                    loadPlayers();
                }
            });
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
                    <p>Belum ada pemain yang terdaftar di tim ini</p>
                </div>
            `;
            showEmptyDetailPanel();
            return;
        }
        
        let html = '';
        playersData.forEach((player, index) => {
            const position = player.position || 'P';
            const isGoalkeeper = player.position === 'GK' || player.position === 'Goalkeeper';
            
            html += `
                <div class="player-item ${index === 0 ? 'active' : ''}" 
                     onclick="showPlayerDetail(${JSON.stringify(player).replace(/"/g, '&quot;')}, this)">
                    <div class="position-badge ${isGoalkeeper ? 'keeper' : ''}">
                        ${position.charAt(0).toUpperCase()}
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
                    <p>Belum ada staff yang terdaftar di tim ini</p>
                </div>
            `;
            showEmptyDetailPanel();
            return;
        }
        
        let html = '';
        staffData.forEach((staff, index) => {
            const position = staff.position || 'staff';33
            const icon = positionIcons[position] || positionIcons.default;
            const positionText = positionTranslations[position] || position;
            
            html += `
                <div class="player-item ${index === 0 ? 'active' : ''}" 
                     onclick="showStaffDetail(${JSON.stringify(staff).replace(/"/g, '&quot;')}, this)">
                    <div class="position-badge staff">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="player-item-info">
                        <span class="player-name">${escapeHtml(staff.name)}</span>
                        <div class="staff-position">${positionText}</div>
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
                <h3 style="margin-bottom: 5px;">${escapeHtml(staff.name)}</h3>
                <p style="color: #666; margin-bottom: 20px;">${positionText}</p>
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
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // For event players (placeholder function)
    function loadEventPlayers(eventId) {
        // Implement this if you want to show players filtered by event
        console.log('Loading players for event:', eventId);
        loadPlayers(); // Fallback to all players for now
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