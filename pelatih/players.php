<?php
$page_title = 'My Players';
$current_page = 'players';
require_once 'config/database.php';
require_once 'includes/header.php';

$team_id = $_SESSION['team_id'] ?? 0;
$players = [];

if ($team_id) {
    try {
        $stmt = $conn->prepare("SELECT * FROM players WHERE team_id = ? ORDER BY name ASC");
        $stmt->execute([$team_id]);
        $players = $stmt->fetchAll();
    } catch (PDOException $e) {
        $players = [];
    }
}
?>

<div class="card">
    <div class="section-header">
        <h2 class="section-title">Player List</h2>
        <a href="player_form.php" class="btn-primary">+ Add Player</a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div style="padding: 10px; margin-bottom: 20px; background: #e0f7fa; color: #006064; border-radius: 8px;">
            <?php 
                if ($_GET['msg'] == 'added') echo "Player added successfully!";
                if ($_GET['msg'] == 'updated') echo "Player updated successfully!";
                if ($_GET['msg'] == 'deleted') echo "Player deleted successfully!";
                if ($_GET['msg'] == 'no_changes_or_unauthorized') echo "No changes made or unauthorized action.";
            ?>
        </div>
    <?php endif; ?>

    <?php if (empty($players)): ?>
        <p style="text-align: center; color: var(--gray); padding: 20px;">No players found in your team.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #eee;">
                        <th style="padding: 15px; text-align: left; color: var(--primary);">Name</th>
                        <th style="padding: 15px; text-align: left; color: var(--primary);">Position</th>
                        <th style="padding: 15px; text-align: center; color: var(--primary);">Number</th>
                        <th style="padding: 15px; text-align: right; color: var(--primary);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $player): ?>
                    <tr style="border-bottom: 1px solid #f0f0f0;">
                        <td style="padding: 15px; font-weight: 500;">
                            <?php echo htmlspecialchars($player['name']); ?>
                        </td>
                        <td style="padding: 15px; color: var(--gray);">
                            <?php echo htmlspecialchars($player['position']); ?>
                        </td>
                        <td style="padding: 15px; text-align: center; font-weight: bold;">
                            <?php echo htmlspecialchars($player['jersey_number']); ?>
                        </td>
                        <td style="padding: 15px; text-align: right;">
                            <a href="player_form.php?id=<?php echo $player['id']; ?>" style="color: var(--warning); margin-right: 10px; text-decoration: none;">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <form action="player_actions.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this player?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $player['id']; ?>">
                                <button type="submit" style="background: none; border: none; color: var(--danger); cursor: pointer;">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
