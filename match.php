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

// Get match statistics
$stats = null;
$sqlStats = "SELECT * FROM match_stats WHERE match_id = ?";
$stmtStats = $conn->prepare($sqlStats);
$stmtStats->bind_param('i', $matchId);
$stmtStats->execute();
$resultStats = $stmtStats->get_result();
$stats = $resultStats->fetch_assoc();
$stmtStats->close();

// Set default stats if not available
if (!$stats) {
    $stats = [
        'team1_possession' => 50, 'team2_possession' => 50,
        'team1_shots_on_target' => 0, 'team2_shots_on_target' => 0,
        'team1_fouls' => 0, 'team2_fouls' => 0
    ];
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

    :root {
        --bg-dark: #0f172a;
        --bg-card: #1e293b;
        --bg-card-hover: #334155;
        --accent: #00ff88;
        --accent-glow: rgba(0, 255, 136, 0.4);
        --text-primary: #f8fafc;
        --text-secondary: #94a3b8;
        --border-color: #334155;
        --success: #10b981;
        --danger: #ef4444;
        --font-main: 'Outfit', sans-serif;
    }

    body {
        background-color: var(--bg-dark) !important;
        font-family: var(--font-main) !important;
        color: var(--text-primary);
    }

    .match-detail-page {
        max-width: 1200px;
        margin: 40px auto;
        padding: 0 20px;
    }

    /* --- HERO SECTION --- */
    .match-hero {
        background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
        border: 1px solid var(--border-color);
        border-radius: 24px;
        padding: 60px 40px;
        text-align: center;
        position: relative;
        overflow: hidden;
        margin-bottom: 40px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.3);
    }

    .match-hero::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(0,255,136,0.05) 0%, transparent 70%);
        animation: pulse 10s infinite alternate;
        z-index: 0;
    }

    @keyframes pulse {
        0% { transform: scale(1); opacity: 0.5; }
        100% { transform: scale(1.2); opacity: 1; }
    }

    .hero-content {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 60px;
    }

    .team-display {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 20px;
    }

    .team-logo-wrapper {
        width: 120px;
        height: 120px;
        background: rgba(255,255,255,0.05);
        border-radius: 50%;
        padding: 20px;
        border: 2px solid var(--border-color);
        box-shadow: 0 0 30px rgba(0,0,0,0.2);
        transition: transform 0.3s ease;
    }

    .team-display:hover .team-logo-wrapper {
        transform: scale(1.05);
        border-color: var(--accent);
        box-shadow: 0 0 30px var(--accent-glow);
    }

    .team-logo-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .team-name {
        font-size: 1.5rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .match-center {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
    }

    .match-score {
        font-size: 4rem;
        font-weight: 800;
        color: var(--text-primary);
        text-shadow: 0 0 20px rgba(0,0,0,0.5);
        letter-spacing: -2px;
        line-height: 1;
    }

    .match-vs {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--text-secondary);
        background: var(--bg-card);
        padding: 5px 15px;
        border-radius: 20px;
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    .match-meta-pill {
        display: flex;
        gap: 20px;
        background: rgba(0,0,0,0.3);
        padding: 10px 25px;
        border-radius: 50px;
        border: 1px solid var(--border-color);
        margin-top: 20px;
        backdrop-filter: blur(10px);
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.9rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .meta-item i {
        color: var(--accent);
    }

    .event-badge {
        position: absolute;
        top: 20px;
        right: 20px;
        background: var(--accent);
        color: var(--bg-dark);
        padding: 5px 15px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 0 15px var(--accent-glow);
    }

    /* --- TABS --- */
    .tabs-container {
        display: flex;
        justify-content: center;
        margin-bottom: 40px;
        gap: 10px;
        background: var(--bg-card);
        padding: 5px;
        border-radius: 50px;
        width: fit-content;
        margin-left: auto;
        margin-right: auto;
        border: 1px solid var(--border-color);
    }

    .tab-btn {
        background: transparent;
        border: none;
        color: var(--text-secondary);
        padding: 10px 30px;
        border-radius: 40px;
        font-family: var(--font-main);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .tab-btn.active {
        background: var(--accent);
        color: var(--bg-dark);
        box-shadow: 0 0 15px var(--accent-glow);
    }

    .tab-btn:hover:not(.active) {
        color: var(--text-primary);
        background: rgba(255,255,255,0.05);
    }

    /* --- CONTENT SECTIONS --- */
    .tab-content {
        display: none;
        animation: slideUp 0.4s ease forwards;
    }

    .tab-content.active {
        display: block;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .section-title {
        text-align: center;
        font-size: 1.2rem;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: var(--text-secondary);
        margin-bottom: 30px;
        position: relative;
        display: inline-block;
        left: 50%;
        transform: translateX(-50%);
    }
    
    .section-title::after {
        content: '';
        display: block;
        width: 40px;
        height: 2px;
        background: var(--accent);
        margin: 10px auto 0;
    }

    /* --- LINEUPS GRID --- */
    .lineups-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
    }

    .team-column h3 {
        text-align: center;
        color: var(--accent);
        margin-bottom: 20px;
        font-size: 1.2rem;
    }

    .players-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }

    .player-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: all 0.3s ease;
    }

    .player-card:hover {
        transform: translateY(-3px);
        border-color: var(--accent);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }

    .player-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        background: var(--bg-dark);
        border: 2px solid var(--border-color);
    }

    .player-info h4 {
        font-size: 0.95rem;
        margin: 0;
        color: var(--text-primary);
        font-weight: 600;
    }

    .player-meta {
        font-size: 0.8rem;
        color: var(--text-secondary);
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .player-number {
        color: var(--accent);
        font-weight: 700;
    }

    .starter-badge {
        font-size: 0.65rem;
        background: rgba(0,255,136,0.1);
        color: var(--accent);
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px solid rgba(0,255,136,0.2);
    }

    /* --- STATISTICS --- */
    .stats-card {
        background: var(--bg-card);
        border-radius: 20px;
        padding: 40px;
        border: 1px solid var(--border-color);
        max-width: 800px;
        margin: 0 auto;
    }

    .stat-row {
        margin-bottom: 25px;
    }

    .stat-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 8px;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .stat-label-center {
        color: var(--text-primary);
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 1px;
    }

    .stat-bar-container {
        height: 8px;
        background: rgba(255,255,255,0.05);
        border-radius: 4px;
        display: flex;
        overflow: hidden;
        position: relative;
    }

    .bar-segment {
        height: 100%;
        transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .bar-left {
        background: linear-gradient(90deg, transparent, var(--accent));
        margin-right: 2px;
        border-right: 1px solid var(--bg-dark);
    }

    .bar-right {
        background: linear-gradient(90deg, #f59e0b, transparent); /* Orange for team 2 */
        margin-left: 2px;
        border-left: 1px solid var(--bg-dark);
        background: linear-gradient(-90deg, transparent, #38bdf8); /* Blue for team 2 actually looks better with Green */
    }

    /* Re-override bar colors for clarity */
    .bar-left { background: var(--accent); box-shadow: 0 0 10px var(--accent-glow); }
    .bar-right { background: #38bdf8; box-shadow: 0 0 10px rgba(56, 189, 248, 0.4); }

    /* --- GOALS --- */
    .timeline {
        position: relative;
        max-width: 600px;
        margin: 0 auto;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 50%;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--border-color);
        transform: translateX(-50%);
    }

    .timeline-event {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        position: relative;
    }

    .event-left, .event-right {
        width: 45%;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .event-left { justify-content: flex-end; text-align: right; }
    .event-right { justify-content: flex-start; text-align: left; }

    .event-minute {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        background: var(--bg-card);
        border: 1px solid var(--accent);
        color: var(--accent);
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 700;
        z-index: 2;
    }

    .empty-state {
        text-align: center;
        padding: 50px;
        color: var(--text-secondary);
        font-style: italic;
    }

    @media (max-width: 768px) {
        .hero-content {
            flex-direction: column;
            gap: 30px;
        }
        .match-score { font-size: 3rem; }
        .lineups-grid { grid-template-columns: 1fr; }
        
        .players-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="container match-detail-page">
    
    <!-- Hero Section -->
    <div class="match-hero">
        <?php if (!empty($match['event_name'])): ?>
            <div class="event-badge"><?php echo htmlspecialchars($match['event_name']); ?></div>
        <?php endif; ?>

        <div class="hero-content">
            <!-- Team 1 -->
            <div class="team-display">
                <div class="team-logo-wrapper">
                    <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>" 
                         alt="<?php echo htmlspecialchars($match['team1_name']); ?>" 
                         class="team-logo-img"
                         onerror="this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                </div>
                <div class="team-name"><?php echo htmlspecialchars($match['team1_name']); ?></div>
            </div>

            <!-- Score -->
            <div class="match-center">
                <?php if ($match['status'] === 'completed'): ?>
                    <div class="match-score">
                        <?php echo $match['score1']; ?> - <?php echo $match['score2']; ?>
                    </div>
                <?php else: ?>
                    <div class="match-vs">VS</div>
                <?php endif; ?>
                
                <div class="match-meta-pill">
                    <div class="meta-item"><i class="far fa-calendar"></i> <?php echo formatDateTime($match['match_date']); ?></div>
                    <div class="meta-item"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($match['location'] ?: 'TBA'); ?></div>
                    <div class="meta-item"><i class="fas fa-circle-info"></i> <?php echo ucfirst($match['status']); ?></div>
                </div>
            </div>

            <!-- Team 2 -->
            <div class="team-display">
                <div class="team-logo-wrapper">
                    <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>" 
                         alt="<?php echo htmlspecialchars($match['team2_name']); ?>" 
                         class="team-logo-img"
                         onerror="this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                </div>
                <div class="team-name"><?php echo htmlspecialchars($match['team2_name']); ?></div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
        <button class="tab-btn active" onclick="switchTab('lineups')">Squads</button>
        <button class="tab-btn" onclick="switchTab('stats')">Stats</button>
        <button class="tab-btn" onclick="switchTab('goals')">Timeline</button>
    </div>

    <!-- Lineups Content -->
    <div id="lineups" class="tab-content active">
        <div class="section-title">Team Lineups</div>
        <div class="lineups-grid">
            <!-- Team 1 Squad -->
            <div class="team-column">
                <h3><?php echo htmlspecialchars($match['team1_name']); ?></h3>
                <?php if(empty($lineups['team1'])): ?>
                    <div class="empty-state">No lineup submitted yet.</div>
                <?php else: ?>
                    <div class="players-grid">
                        <?php foreach($lineups['team1'] as $player): ?>
                        <div class="player-card">
                            <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player['photo']; ?>" 
                                 class="player-avatar"
                                 onerror="this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                            <div class="player-info">
                                <h4><?php echo htmlspecialchars($player['player_name']); ?></h4>
                                <div class="player-meta">
                                    <span class="player-number">#<?php echo $player['jersey_number']; ?></span>
                                    <span><?php echo $player['position']; ?></span>
                                    <?php if($player['is_starting']): ?>
                                        <span class="starter-badge">Starter</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Team 2 Squad -->
            <div class="team-column">
                <h3><?php echo htmlspecialchars($match['team2_name']); ?></h3>
                <?php if(empty($lineups['team2'])): ?>
                    <div class="empty-state">No lineup submitted yet.</div>
                <?php else: ?>
                    <div class="players-grid">
                        <?php foreach($lineups['team2'] as $player): ?>
                        <div class="player-card">
                            <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player['photo']; ?>" 
                                 class="player-avatar"
                                 onerror="this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                            <div class="player-info">
                                <h4><?php echo htmlspecialchars($player['player_name']); ?></h4>
                                <div class="player-meta">
                                    <span class="player-number">#<?php echo $player['jersey_number']; ?></span>
                                    <span><?php echo $player['position']; ?></span>
                                    <?php if($player['is_starting']): ?>
                                        <span class="starter-badge">Starter</span>
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

    <!-- Stats Content -->
    <div id="stats" class="tab-content">
        <div class="section-title">Match Statistics</div>
        <div class="stats-card">
            <!-- Possession -->
            <div class="stat-row">
                <div class="stat-header">
                    <span><?php echo $stats['team1_possession']; ?>%</span>
                    <span class="stat-label-center">Ball Possession</span>
                    <span><?php echo $stats['team2_possession']; ?>%</span>
                </div>
                <div class="stat-bar-container">
                    <div class="bar-segment bar-left" style="width: <?php echo $stats['team1_possession']; ?>%"></div>
                    <div class="bar-segment bar-right" style="width: <?php echo $stats['team2_possession']; ?>%"></div>
                </div>
            </div>

            <!-- Shots -->
            <div class="stat-row">
                <?php 
                    $t1_shots = $stats['team1_shots_on_target'];
                    $t2_shots = $stats['team2_shots_on_target'];
                    $total_shots = $t1_shots + $t2_shots;
                    $t1_width = $total_shots > 0 ? ($t1_shots / $total_shots * 100) : 50;
                    $t2_width = $total_shots > 0 ? ($t2_shots / $total_shots * 100) : 50;
                ?>
                <div class="stat-header">
                    <span><?php echo $t1_shots; ?></span>
                    <span class="stat-label-center">Shots on Target</span>
                    <span><?php echo $t2_shots; ?></span>
                </div>
                <div class="stat-bar-container">
                    <div class="bar-segment bar-left" style="width: <?php echo $t1_width; ?>%"></div>
                    <div class="bar-segment bar-right" style="width: <?php echo $t2_width; ?>%"></div>
                </div>
            </div>

             <!-- Fouls -->
             <div class="stat-row">
                <?php 
                    $t1_fouls = $stats['team1_fouls'];
                    $t2_fouls = $stats['team2_fouls'];
                    $total_fouls = $t1_fouls + $t2_fouls;
                    $t1_f_width = $total_fouls > 0 ? ($t1_fouls / $total_fouls * 100) : 50;
                    $t2_f_width = $total_fouls > 0 ? ($t2_fouls / $total_fouls * 100) : 50;
                ?>
                <div class="stat-header">
                    <span><?php echo $t1_fouls; ?></span>
                    <span class="stat-label-center">Fouls</span>
                    <span><?php echo $t2_fouls; ?></span>
                </div>
                <div class="stat-bar-container">
                    <div class="bar-segment bar-left" style="width: <?php echo $t1_f_width; ?>%"></div>
                    <div class="bar-segment bar-right" style="width: <?php echo $t2_f_width; ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Goals Content -->
    <div id="goals" class="tab-content">
        <div class="section-title">Match Timeline</div>
        <?php if(empty($goals)): ?>
            <div class="empty-state">No goals recorded available (0-0).</div>
        <?php else: ?>
            <div class="timeline">
                <?php foreach($goals as $goal): 
                    $isTeamm1 = ($goal['team_id'] == $match['team1_id']);
                ?>
                <div class="timeline-event">
                    <div class="event-left">
                        <?php if($isTeamm1): ?>
                            <div class="player-info">
                                <h4><?php echo htmlspecialchars($goal['player_name']); ?></h4>
                                <span class="text-muted">Goal</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="event-minute"><?php echo $goal['minute']; ?>'</div>
                    
                    <div class="event-right">
                        <?php if(!$isTeamm1): ?>
                            <div class="player-info">
                                <h4><?php echo htmlspecialchars($goal['player_name']); ?></h4>
                                <span class="text-muted">Goal</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
    function switchTab(tabId) {
        // Update Buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');

        // Update Content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        document.getElementById(tabId).classList.add('active');
    }

    // Auto-select tab logic if match is scheduled
    document.addEventListener('DOMContentLoaded', () => {
        const status = "<?php echo $match['status']; ?>";
        if (status === 'scheduled') {
           // Simply click the lineups tab to switch to it
           const lineupsBtn = document.querySelector('button[onclick="switchTab(\'lineups\')"]');
           if(lineupsBtn) lineupsBtn.click();
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>