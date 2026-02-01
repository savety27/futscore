<?php
// ============================================
// SEMUA LOGIC DAN REDIRECT HARUS SEBELUM OUTPUT
// ============================================

$pageTitle = "Match Details";

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
                   c.match_status as status, c.notes as location,
                   t1.id as team1_id, t1.name as team1_name, t1.logo as team1_logo, 
                   t2.id as team2_id, t2.name as team2_name, t2.logo as team2_logo,
                   c.sport_type as event_name
            FROM challenges c
            LEFT JOIN teams t1 ON c.challenger_id = t1.id
            LEFT JOIN teams t2 ON c.opponent_id = t2.id
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

if (!$match) {
    echo '<div class="container"><div class="alert alert-danger">Match not found</div></div>';
    require_once 'includes/footer.php';
    exit();
}

// Get goals for this match
$goals = [];
$sqlGoals = "SELECT g.*, p.name as player_name, p.jersey_number, t.name as team_name
             FROM goals g
             LEFT JOIN players p ON g.player_id = p.id
             LEFT JOIN teams t ON g.team_id = t.id
             WHERE g.match_id = ?
             ORDER BY g.minute ASC";
$stmtGoals = $conn->prepare($sqlGoals);
$stmtGoals->bind_param('i', $matchId);
$stmtGoals->execute();
$resultGoals = $stmtGoals->get_result();
while ($goal = $resultGoals->fetch_assoc()) {
    $goals[] = $goal;
}
$stmtGoals->close();

// Get lineups for this match
$lineups = ['team1' => [], 'team2' => []];
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
?>

<div class="container match-detail-page">
    <div class="match-detail-header">
        <h1><?php echo htmlspecialchars($match['team1_name']); ?> vs <?php echo htmlspecialchars($match['team2_name']); ?></h1>
        
        <?php if (!empty($match['event_name'])): ?>
        <div class="match-event-badge">
            <?php echo htmlspecialchars($match['event_name']); ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="match-info-summary">
        <div class="match-teams-large">
            <div class="team-large">
                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>" 
                     alt="<?php echo htmlspecialchars($match['team1_name']); ?>" 
                     class="team-logo-large"
                     onerror="this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                <h3><?php echo htmlspecialchars($match['team1_name']); ?></h3>
            </div>
            
            <div class="vs-large">
                <span class="vs-text-large">VS</span>
                <?php if ($match['status'] === 'completed'): ?>
                <div class="score-large">
                    <?php echo $match['score1']; ?> - <?php echo $match['score2']; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="team-large">
                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>" 
                     alt="<?php echo htmlspecialchars($match['team2_name']); ?>" 
                     class="team-logo-large"
                     onerror="this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                <h3><?php echo htmlspecialchars($match['team2_name']); ?></h3>
            </div>
        </div>
        
        <div class="match-details-info">
            <div class="detail-item">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo formatDateTime($match['match_date']); ?></span>
            </div>
            
            <?php if (!empty($match['location'])): ?>
            <div class="detail-item">
                <i class="fas fa-map-marker-alt"></i>
                <span><?php echo htmlspecialchars($match['location']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="detail-item">
                <i class="fas fa-flag"></i>
                <span><?php echo ucfirst($match['status']); ?></span>
            </div>
        </div>
    </div>
    
    <div class="match-tabs-section">
        <div class="match-tabs">
            <button class="match-tab active" data-tab="goals">Goals</button>
            <button class="match-tab" data-tab="lineups">Lineups</button>
            <button class="match-tab" data-tab="stats">Statistics</button>
        </div>
        
        <div class="match-tab-content active" id="goals">
            <h3>Goals</h3>
            <?php if (empty($goals)): ?>
            <div class="empty-state">
                <i class="fas fa-futbol"></i>
                <p>No goals recorded for this match</p>
            </div>
            <?php else: ?>
            <div class="goals-list">
                <?php foreach ($goals as $goal): ?>
                <div class="goal-item">
                    <div class="goal-player">
                        <span class="goal-player-number">#<?php echo $goal['jersey_number']; ?></span>
                        <span class="goal-player-name"><?php echo htmlspecialchars($goal['player_name']); ?></span>
                        <span class="goal-team">(<?php echo $goal['team_name']; ?>)</span>
                    </div>
                    <span class="goal-minute"><?php echo $goal['minute']; ?>'</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="match-tab-content" id="lineups">
            <h3>Lineups</h3>
            <div class="lineups-container">
                <div class="team-lineup">
                    <h4><?php echo htmlspecialchars($match['team1_name']); ?></h4>
                    <?php if (empty($lineups['team1'])): ?>
                    <p class="no-data">No lineup data available</p>
                    <?php else: ?>
                    <div class="players-list">
                        <?php foreach ($lineups['team1'] as $player): ?>
                        <div class="player-lineup-item">
                            <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player['photo']; ?>" 
                                 alt="<?php echo htmlspecialchars($player['player_name']); ?>" 
                                 class="player-photo-small"
                                 onerror="this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                            <div class="player-info-lineup">
                                <div class="player-name-lineup"><?php echo htmlspecialchars($player['player_name']); ?></div>
                                <div class="player-number-lineup">#<?php echo $player['jersey_number']; ?></div>
                                <?php if ($player['is_starting']): ?>
                                <span class="starting-badge">Starting</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="team-lineup">
                    <h4><?php echo htmlspecialchars($match['team2_name']); ?></h4>
                    <?php if (empty($lineups['team2'])): ?>
                    <p class="no-data">No lineup data available</p>
                    <?php else: ?>
                    <div class="players-list">
                        <?php foreach ($lineups['team2'] as $player): ?>
                        <div class="player-lineup-item">
                            <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player['photo']; ?>" 
                                 alt="<?php echo htmlspecialchars($player['player_name']); ?>" 
                                 class="player-photo-small"
                                 onerror="this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                            <div class="player-info-lineup">
                                <div class="player-name-lineup"><?php echo htmlspecialchars($player['player_name']); ?></div>
                                <div class="player-number-lineup">#<?php echo $player['jersey_number']; ?></div>
                                <?php if ($player['is_starting']): ?>
                                <span class="starting-badge">Starting</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="match-tab-content" id="stats">
            <h3>Statistics</h3>
            <div class="stats-container">
                <div class="stat-item">
                    <div class="stat-label">Ball Possession</div>
                    <div class="stat-bar">
                        <div class="stat-team1" style="width: <?php echo $match['status'] === 'completed' ? '60' : '50'; ?>%">
                            <span><?php echo $match['status'] === 'completed' ? '60%' : '50%'; ?></span>
                        </div>
                        <div class="stat-team2" style="width: <?php echo $match['status'] === 'completed' ? '40' : '50'; ?>%">
                            <span><?php echo $match['status'] === 'completed' ? '40%' : '50%'; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-label">Shots on Target</div>
                    <div class="stat-bar">
                        <div class="stat-team1" style="width: <?php echo $match['status'] === 'completed' ? '70' : '50'; ?>%">
                            <span><?php echo $match['status'] === 'completed' ? '7' : 'N/A'; ?></span>
                        </div>
                        <div class="stat-team2" style="width: <?php echo $match['status'] === 'completed' ? '30' : '50'; ?>%">
                            <span><?php echo $match['status'] === 'completed' ? '3' : 'N/A'; ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-label">Fouls</div>
                    <div class="stat-bar">
                        <div class="stat-team1" style="width: <?php echo $match['status'] === 'completed' ? '40' : '50'; ?>%">
                            <span><?php echo $match['status'] === 'completed' ? '4' : 'N/A'; ?></span>
                        </div>
                        <div class="stat-team2" style="width: <?php echo $match['status'] === 'completed' ? '60' : '50'; ?>%">
                            <span><?php echo $match['status'] === 'completed' ? '6' : 'N/A'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional CSS for match.php */
.match-detail-page {
    margin-top: 30px;
    margin-bottom: 50px;
}

.match-detail-header {
    text-align: center;
    margin-bottom: 30px;
}

.match-detail-header h1 {
    color: var(--primary-green);
    font-size: 32px;
    margin-bottom: 15px;
}

.match-event-badge {
    display: inline-block;
    background-color: rgba(0, 255, 136, 0.2);
    color: var(--primary-green);
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.match-info-summary {
    background: linear-gradient(135deg, rgba(0, 0, 0, 0.3), rgba(26, 26, 26, 0.3));
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    border: 1px solid var(--dark-green);
}

.match-teams-large {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.team-large {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.team-logo-large {
    width: 120px;
    height: 120px;
    object-fit: contain;
    border-radius: 50%;
    border: 4px solid var(--primary-green);
    padding: 8px;
    background-color: var(--black);
    margin-bottom: 15px;
}

.team-large h3 {
    color: var(--white);
    font-size: 22px;
    font-weight: 700;
    max-width: 200px;
    word-wrap: break-word;
}

.vs-large {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 30px;
}

.vs-text-large {
    color: var(--primary-green);
    font-size: 28px;
    font-weight: 800;
    margin-bottom: 15px;
}

.score-large {
    font-size: 48px;
    font-weight: 900;
    color: var(--white);
    background: linear-gradient(135deg, var(--dark-green), var(--primary-green));
    padding: 15px 30px;
    border-radius: 12px;
    border: 3px solid var(--white);
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
}

.match-details-info {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
    padding-top: 20px;
    border-top: 1px solid var(--gray);
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--white);
    font-size: 16px;
}

.detail-item i {
    color: var(--primary-green);
    font-size: 18px;
}

.match-tabs-section {
    background-color: var(--gray-dark);
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--dark-green);
}

.match-tabs {
    display: flex;
    background-color: var(--black);
    border-bottom: 2px solid var(--primary-green);
}

.match-tab {
    flex: 1;
    padding: 15px 20px;
    background: none;
    border: none;
    color: var(--gray-light);
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.match-tab:hover {
    color: var(--primary-green);
    background-color: rgba(0, 255, 136, 0.1);
}

.match-tab.active {
    color: var(--primary-green);
    background-color: rgba(0, 255, 136, 0.2);
    border-bottom: 3px solid var(--primary-green);
}

.match-tab-content {
    padding: 30px;
    display: none;
}

.match-tab-content.active {
    display: block;
}

.match-tab-content h3 {
    color: var(--primary-green);
    font-size: 20px;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray);
}

.goals-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.goal-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    border-left: 4px solid var(--primary-green);
}

.goal-player {
    display: flex;
    align-items: center;
    gap: 12px;
}

.goal-player-number {
    background-color: var(--primary-green);
    color: var(--black);
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
}

.goal-player-name {
    color: var(--white);
    font-weight: 600;
    font-size: 16px;
}

.goal-team {
    color: var(--gray-light);
    font-size: 14px;
    margin-left: 10px;
}

.goal-minute {
    background-color: var(--dark-green);
    color: var(--white);
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
}

.lineups-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.team-lineup h4 {
    color: var(--primary-green);
    font-size: 18px;
    margin-bottom: 20px;
    text-align: center;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray);
}

.players-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.player-lineup-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 12px;
    background-color: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.player-lineup-item:hover {
    background-color: rgba(0, 255, 136, 0.1);
    border-left-color: var(--primary-green);
    transform: translateX(5px);
}

.player-photo-small {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--dark-green);
    background-color: var(--black);
}

.player-info-lineup {
    flex: 1;
}

.player-name-lineup {
    color: var(--white);
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 4px;
}

.player-number-lineup {
    color: var(--primary-green);
    font-size: 14px;
    font-weight: 600;
}

.starting-badge {
    display: inline-block;
    background-color: var(--dark-green);
    color: var(--white);
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 8px;
    font-weight: 600;
}

.stats-container {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.stat-item {
    margin-bottom: 15px;
}

.stat-label {
    color: var(--white);
    font-weight: 600;
    margin-bottom: 10px;
    font-size: 16px;
}

.stat-bar {
    display: flex;
    height: 40px;
    border-radius: 8px;
    overflow: hidden;
    background-color: rgba(255, 255, 255, 0.1);
    border: 1px solid var(--gray);
}

.stat-team1 {
    background: linear-gradient(90deg, var(--dark-green), var(--primary-green));
    color: var(--black);
    display: flex;
    align-items: center;
    justify-content: flex-start;
    padding-left: 15px;
    font-weight: 700;
    font-size: 14px;
    transition: width 0.5s ease;
}

.stat-team2 {
    background: linear-gradient(90deg, #666666, #888888);
    color: var(--white);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding-right: 15px;
    font-weight: 700;
    font-size: 14px;
    transition: width 0.5s ease;
}

.no-data {
    text-align: center;
    color: var(--gray-light);
    font-style: italic;
    padding: 40px 20px;
    background-color: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    border: 2px dashed var(--gray);
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    background-color: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    border: 2px dashed var(--gray);
}

.empty-state i {
    font-size: 48px;
    color: var(--gray-light);
    margin-bottom: 15px;
}

.empty-state p {
    color: var(--gray-light);
    font-size: 16px;
}

@media (max-width: 768px) {
    .match-teams-large {
        flex-direction: column;
        gap: 20px;
    }
    
    .vs-large {
        padding: 20px 0;
        flex-direction: row;
        gap: 20px;
    }
    
    .score-large {
        font-size: 36px;
        padding: 10px 20px;
    }
    
    .lineups-container {
        grid-template-columns: 1fr;
    }
    
    .match-details-info {
        flex-direction: column;
        gap: 15px;
        align-items: center;
    }
    
    .match-tabs {
        flex-wrap: wrap;
    }
    
    .match-tab {
        min-width: 120px;
        text-align: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality for match detail page
    const tabs = document.querySelectorAll('.match-tab');
    const tabContents = document.querySelectorAll('.match-tab-content');
    
    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(tc => tc.classList.remove('active'));
            
            // Add active class to current tab
            this.classList.add('active');
            document.getElementById(tabName).classList.add('active');
        });
    });
    
    // If match is scheduled, show different content
    const matchStatus = "<?php echo $match['status']; ?>";
    if (matchStatus === 'scheduled') {
        // Hide goals tab if match is scheduled
        document.querySelector('.match-tab[data-tab="goals"]').style.display = 'none';
        document.querySelector('.match-tab[data-tab="stats"]').style.display = 'none';
        
        // Show schedule info
        const lineupsTab = document.getElementById('lineups');
        lineupsTab.innerHTML = `
            <h3>Match Schedule</h3>
            <div class="schedule-info">
                <p><strong>Date & Time:</strong> <?php echo formatDateTime($match['match_date']); ?></p>
                <p><strong>Venue:</strong> <?php echo htmlspecialchars($match['location']); ?></p>
                <p><strong>Status:</strong> Scheduled</p>
                <?php if (!empty($match['event_name'])): ?>
                <p><strong>Event:</strong> <?php echo htmlspecialchars($match['event_name']); ?></p>
                <?php endif; ?>
            </div>
            <div class="jersey-info">
                <h4>Jersey Information</h4>
                <p><strong><?php echo htmlspecialchars($match['team1_name']); ?>:</strong> Home Jersey</p>
                <p><strong><?php echo htmlspecialchars($match['team2_name']); ?>:</strong> Away Jersey</p>
            </div>
        `;
    }
});
</script>

<?php 
require_once 'includes/footer.php'; 
?>