<?php
$page_title = 'Dashboard';
$current_page = 'dashboard';
require_once 'config/database.php';
require_once 'includes/header.php';

$team_id = $_SESSION['team_id'] ?? 0;
$player_count = 0;
$team_name = 'Unknown Team';

if ($team_id) {
    try {
        // Get Team Name and Logo
        $stmt = $conn->prepare("SELECT name, logo FROM teams WHERE id = ?");
        $stmt->execute([$team_id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        $team_name = $team ? ($team['name'] ?: 'Unknown Team') : 'Unknown Team';
        $team_logo = $team ? $team['logo'] : null;

        // Get Player Count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM players WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $player_count = $stmt->fetchColumn();

        // Get Staff Count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM team_staff WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $staff_count = $stmt->fetchColumn();

        // Get Wins
        $stmt = $conn->prepare("SELECT COUNT(*) FROM matches WHERE status = 'completed' AND ((team1_id = ? AND score1 > score2) OR (team2_id = ? AND score2 > score1))");
        $stmt->execute([$team_id, $team_id]);
        $wins = $stmt->fetchColumn();

        // Get Losses
        $stmt = $conn->prepare("SELECT COUNT(*) FROM matches WHERE status = 'completed' AND ((team1_id = ? AND score1 < score2) OR (team2_id = ? AND score2 < score1))");
        $stmt->execute([$team_id, $team_id]);
        $losses = $stmt->fetchColumn();

        // Get Draws
        $stmt = $conn->prepare("SELECT COUNT(*) FROM matches WHERE status = 'completed' AND (team1_id = ? OR team2_id = ?) AND score1 = score2");
        $stmt->execute([$team_id, $team_id]);
        $draws = $stmt->fetchColumn();

        // Get Next Match Spotlight
        $stmt = $conn->prepare("
            SELECT m.*, 
                   t1.name as team1_name, t1.logo as team1_logo,
                   t2.name as team2_name, t2.logo as team2_logo,
                   e.name as event_name
            FROM matches m
            JOIN teams t1 ON m.team1_id = t1.id
            JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN events e ON m.event_id = e.id
            WHERE m.status = 'scheduled' 
              AND (m.team1_id = ? OR m.team2_id = ?)
              AND m.match_date >= NOW()
            ORDER BY m.match_date ASC
            LIMIT 1
        ");
        $stmt->execute([$team_id, $team_id]);
        $next_match = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $player_count = 0;
        $staff_count = 0;
        $wins = 0;
        $losses = 0;
        $draws = 0;
        $next_match = null;
        $team_name = 'Unknown Team';
        $team_logo = null;
    }
}
?>


<div class="card" style="margin-bottom: 30px;">
    <div class="section-header">
        <h2 class="section-title">Team Overview</h2>
    </div>
    <p>Welcome to the Coach Dashboard. Use the sidebar to manage your players and view schedules.</p>
</div>

<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <!-- Team Card -->
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 15px;">
        <div class="stat-icon" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; background: linear-gradient(135deg, var(--primary), var(--accent)); overflow: hidden;">
            <?php if (!empty($team_logo) && file_exists('../images/teams/' . $team_logo)): ?>
                <img src="../images/teams/<?php echo htmlspecialchars($team_logo); ?>" alt="Team Logo" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
                🛡️
            <?php endif; ?>
        </div>
        <div class="stat-content">
            <h3 style="font-size: 18px; color: var(--dark); margin-bottom: 2px; font-weight: 700;"><?php echo htmlspecialchars($team_name); ?></h3>
            <p style="color: var(--gray); font-size: 13px;">Your Team</p>
        </div>
    </div>

    <!-- Players Card -->
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 15px;">
        <div class="stat-icon" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; background: linear-gradient(135deg, var(--secondary), #FFEC8B);">
            👥
        </div>
        <div class="stat-content">
            <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 2px; font-weight: 700;"><?php echo $player_count; ?></h3>
            <p style="color: var(--gray); font-size: 13px;">Total Players</p>
        </div>
    </div>

    <!-- Staff Card -->
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 15px;">
        <div class="stat-icon" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; background: linear-gradient(135deg, #4facfe, #00f2fe);">
            📋
        </div>
        <div class="stat-content">
            <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 2px; font-weight: 700;"><?php echo $staff_count; ?></h3>
            <p style="color: var(--gray); font-size: 13px;">Coach & Staff</p>
        </div>
    </div>

    <!-- Wins Card -->
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 15px;">
        <div class="stat-icon" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; background: linear-gradient(135deg, #43e97b, #38f9d7);">
            🏆
        </div>
        <div class="stat-content">
            <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 2px; font-weight: 700;"><?php echo $wins; ?></h3>
            <p style="color: var(--gray); font-size: 13px;">Wins</p>
        </div>
    </div>

    <!-- Losses Card -->
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 15px;">
        <div class="stat-icon" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; background: linear-gradient(135deg, #fa709a, #fee140);">
            ❌
        </div>
        <div class="stat-content">
            <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 2px; font-weight: 700;"><?php echo $losses; ?></h3>
            <p style="color: var(--gray); font-size: 13px;">Losses</p>
        </div>
    </div>

    <!-- Draws Card -->
    <div class="stat-card" style="background: white; padding: 20px; border-radius: 16px; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 15px;">
        <div class="stat-icon" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; background: linear-gradient(135deg, #667eea, #764ba2);">
            🤝
        </div>
        <div class="stat-content">
            <h3 style="font-size: 20px; color: var(--dark); margin-bottom: 2px; font-weight: 700;"><?php echo $draws; ?></h3>
            <p style="color: var(--gray); font-size: 13px;">Draws</p>
        </div>
    </div>
</div>

<div class="next-match-spotlight" style="margin-top: 30px;">
    <div class="section-header" style="margin-bottom: 20px;">
        <h2 class="section-title">Next Match Spotlight</h2>
    </div>
    
    <?php if ($next_match): 
        $is_team1 = ($next_match['team1_id'] == $team_id);
        $opponent_name = $is_team1 ? $next_match['team2_name'] : $next_match['team1_name'];
        $opponent_logo = $is_team1 ? $next_match['team2_logo'] : $next_match['team1_logo'];
        $match_date = new DateTime($next_match['match_date']);
    ?>
        <div class="spotlight-card" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); padding: 30px; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05);">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 30px;">
                <!-- Team 1 -->
                <div style="flex: 1; text-align: center; min-width: 150px;">
                    <div style="width: 80px; height: 80px; margin: 0 auto 15px; background: white; border-radius: 20px; padding: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); display: flex; align-items: center; justify-content: center;">
                        <?php if ($next_match['team1_logo'] && file_exists('../images/teams/' . $next_match['team1_logo'])): ?>
                            <img src="../images/teams/<?php echo htmlspecialchars($next_match['team1_logo']); ?>" alt="Team 1" style="width: 100%; height: 100%; object-fit: contain;">
                        <?php else: ?>
                            <span style="font-size: 32px;">🛡️</span>
                        <?php endif; ?>
                    </div>
                    <h4 style="font-size: 18px; font-weight: 700; color: var(--dark);"><?php echo htmlspecialchars($next_match['team1_name']); ?></h4>
                </div>

                <!-- VS -->
                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: 900; color: #e0e0e0; margin-bottom: 15px; letter-spacing: 5px;">VS</div>
                    <div style="background: var(--primary); color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block;">
                        <?php echo htmlspecialchars($next_match['event_name'] ?: 'Friendly Match'); ?>
                    </div>
                </div>

                <!-- Team 2 -->
                <div style="flex: 1; text-align: center; min-width: 150px;">
                    <div style="width: 80px; height: 80px; margin: 0 auto 15px; background: white; border-radius: 20px; padding: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); display: flex; align-items: center; justify-content: center;">
                        <?php if ($next_match['team2_logo'] && file_exists('../images/teams/' . $next_match['team2_logo'])): ?>
                            <img src="../images/teams/<?php echo htmlspecialchars($next_match['team2_logo']); ?>" alt="Team 2" style="width: 100%; height: 100%; object-fit: contain;">
                        <?php else: ?>
                            <span style="font-size: 32px;">🛡️</span>
                        <?php endif; ?>
                    </div>
                    <h4 style="font-size: 18px; font-weight: 700; color: var(--dark);"><?php echo htmlspecialchars($next_match['team2_name']); ?></h4>
                </div>

                <!-- Match Info -->
                <div style="flex-basis: 100%; border-top: 1px dashed #e0e0e0; padding-top: 25px; margin-top: 5px; display: flex; justify-content: space-around; flex-wrap: wrap; gap: 20px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">📅</span>
                        <div>
                            <p style="font-size: 12px; color: var(--gray); margin: 0;">Date</p>
                            <p style="font-size: 14px; font-weight: 600; color: var(--dark); margin: 0;"><?php echo $match_date->format('l, d M Y'); ?></p>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">⏰</span>
                        <div>
                            <p style="font-size: 12px; color: var(--gray); margin: 0;">Time</p>
                            <p style="font-size: 14px; font-weight: 600; color: var(--dark); margin: 0;"><?php echo $match_date->format('H:i'); ?> WIB</p>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 20px;">📍</span>
                        <div>
                            <p style="font-size: 12px; color: var(--gray); margin: 0;">Venue</p>
                            <p style="font-size: 14px; font-weight: 600; color: var(--dark); margin: 0;"><?php echo htmlspecialchars($next_match['location'] ?: 'To be announced'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 40px; background: #fafafa; border: 2px dashed #eee; box-shadow: none;">
            <div style="font-size: 40px; margin-bottom: 15px;">🏐</div>
            <h3 style="color: var(--gray); font-weight: 600;">No matches scheduled</h3>
            <p style="color: var(--gray); opacity: 0.7;">Check back later or contact the admin for the latest schedule.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
