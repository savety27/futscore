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
            <div class="event-badge"><?php echo htmlspecialchars($match['event_name'] ?? ''); ?></div>
        <?php endif; ?>

        <div class="hero-content">
            <!-- Team 1 -->
            <div class="team-display">
                <div class="team-logo-wrapper">
                    <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>" 
                         alt="<?php echo htmlspecialchars($match['team1_name'] ?? ''); ?>" 
                         class="team-logo-img"
                         onerror="this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                </div>
                <div class="team-name"><?php echo htmlspecialchars($match['team1_name'] ?? ''); ?></div>
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
                    <div class="meta-item"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($match['location'] ?? 'TBA'); ?></div>
                    <div class="meta-item"><i class="fas fa-circle-info"></i> <?php echo ucfirst($match['status']); ?></div>
                </div>
            </div>

            <!-- Team 2 -->
            <div class="team-display">
                <div class="team-logo-wrapper">
                    <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>" 
                         alt="<?php echo htmlspecialchars($match['team2_name'] ?? ''); ?>" 
                         class="team-logo-img"
                         onerror="this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                </div>
                <div class="team-name"><?php echo htmlspecialchars($match['team2_name'] ?? ''); ?></div>
            </div>
        </div>
    </div>

    <div class="section-title">Team Lineups</div>
    <div class="lineups-grid">
        <!-- Team 1 Squad -->
        <div class="team-column">
            <h3><?php echo htmlspecialchars($match['team1_name'] ?? ''); ?></h3>
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
                            <h4><?php echo htmlspecialchars($player['player_name'] ?? ''); ?></h4>
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
            <h3><?php echo htmlspecialchars($match['team2_name'] ?? ''); ?></h3>
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
                            <h4><?php echo htmlspecialchars($player['player_name'] ?? ''); ?></h4>
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

<?php require_once 'includes/footer.php'; ?>
