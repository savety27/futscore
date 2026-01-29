<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$action = 'add';
$player_id = 0;
$player = [
    'name' => '',
    'jersey_number' => '',
    'position' => 'Forward',
    'birth_date' => '',
    'gender' => 'L',
    'height' => '',
    'weight' => ''
];

// Check if editing
if (isset($_GET['id'])) {
    $action = 'edit';
    $player_id = (int)$_GET['id'];
    $team_id = $_SESSION['team_id'];
    
    // IMPORTANT: Ownership Check
    try {
        $stmt = $conn->prepare("SELECT * FROM players WHERE id = ? AND team_id = ?");
        $stmt->execute([$player_id, $team_id]);
        $fetched_player = $stmt->fetch();
        
        if ($fetched_player) {
            $player = $fetched_player;
        } else {
            echo "<div class='alert alert-danger'>Player not found or you don't have permission to edit.</div>";
            require_once 'includes/footer.php';
            exit;
        }
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>

<div class="card">
    <div class="section-header">
        <h2 class="section-title"><?php echo ucfirst($action); ?> Player</h2>
        <a href="players.php" class="btn-primary" style="background: var(--gray);">Back</a>
    </div>

    <form action="player_actions.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?php echo $action; ?>">
        <input type="hidden" name="id" value="<?php echo $player_id; ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Full Name</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($player['name']); ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
            </div>
            
             <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Jersey Number</label>
                <input type="number" name="jersey_number" value="<?php echo htmlspecialchars($player['jersey_number']); ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
            </div>

            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Position</label>
                <select name="position" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    <?php 
                    $positions = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward', 'Anchor', 'Flank', 'Pivot'];
                    foreach ($positions as $pos) {
                        $selected = ($player['position'] == $pos) ? 'selected' : '';
                        echo "<option value='$pos' $selected>$pos</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Birth Date</label>
                <input type="date" name="birth_date" value="<?php echo htmlspecialchars($player['birth_date']); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
            </div>

            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Gender</label>
                <select name="gender" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    <option value="L" <?php echo ($player['gender'] == 'L') ? 'selected' : ''; ?>>Male (L)</option>
                    <option value="P" <?php echo ($player['gender'] == 'P') ? 'selected' : ''; ?>>Female (P)</option>
                </select>
            </div>
            
             <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Photo</label>
                <input type="file" name="photo" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                <?php if (!empty($player['photo'])): ?>
                    <small>Current: <?php echo htmlspecialchars($player['photo']); ?></small>
                <?php endif; ?>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Height (cm)</label>
                <input type="number" name="height" value="<?php echo htmlspecialchars($player['height']); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
            </div>
            
            <div>
                 <label style="display: block; margin-bottom: 5px; font-weight: 600;">Weight (kg)</label>
                <input type="number" name="weight" value="<?php echo htmlspecialchars($player['weight']); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
            </div>
        </div>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn-primary" style="width: 100%;">Save Player</button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
