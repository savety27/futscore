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
        // Get Player Count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM players WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $player_count = $stmt->fetchColumn();

    } catch (PDOException $e) {
        $player_count = 0;
    }
}
?>

<div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; margin-bottom: 40px;">
    <!-- Team Card -->
    <div class="stat-card" style="background: white; padding: 25px; border-radius: 20px; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 20px;">
        <div class="stat-icon" style="width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; color: white; background: linear-gradient(135deg, var(--primary), var(--accent));">
            🛡️
        </div>
        <div class="stat-content">
            <h3 style="font-size: 24px; color: var(--dark); margin-bottom: 5px; font-weight: 700;"><?php echo htmlspecialchars($team_name); ?></h3>
            <p style="color: var(--gray); font-size: 14px;">Your Team</p>
        </div>
    </div>

    <!-- Players Card -->
    <div class="stat-card" style="background: white; padding: 25px; border-radius: 20px; box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 20px;">
        <div class="stat-icon" style="width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 28px; color: white; background: linear-gradient(135deg, var(--secondary), #FFEC8B);">
            👥
        </div>
        <div class="stat-content">
            <h3 style="font-size: 24px; color: var(--dark); margin-bottom: 5px; font-weight: 700;"><?php echo $player_count; ?></h3>
            <p style="color: var(--gray); font-size: 14px;">Total Players</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="section-header">
        <h2 class="section-title">Team Overview</h2>
    </div>
    <p>Welcome to the Coach Dashboard. Use the sidebar to manage your players and view schedules.</p>
</div>

<?php require_once 'includes/footer.php'; ?>
