<?php
$page_title = 'Atur Lineup';
$current_page = 'schedule';
require_once 'config/database.php';
require_once 'includes/header.php';

$challenge_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pelatih_id = $_SESSION['pelatih_id'] ?? 0;
$match_event = '';
$event_fallback = false;

// Verify challenge exists and pelatih owns one of the teams
try {

// Get pelatih's team_id directly from session
$my_team_id = $_SESSION['team_id'] ?? 0;

// Fallback: If not in session, try to get from database using pelatih_id
if ($my_team_id == 0) {
    try {
        $stmtTeam = $conn->prepare("SELECT team_id FROM team_staff WHERE id = ?");
        $stmtTeam->execute([$pelatih_id]);
        $staff = $stmtTeam->fetch(PDO::FETCH_ASSOC);
        $my_team_id = $staff['team_id'] ?? 0;
        
        // Save to session for future use
        if ($my_team_id > 0) {
            $_SESSION['team_id'] = $my_team_id;
        }
    } catch (PDOException $e) {
        // Ignore error
    }
}

    if (!$my_team_id) {
        die("Error: Anda tidak terdaftar dalam tim manapun.");
    }

    // Get challenge details
    $stmt = $conn->prepare("
        SELECT c.*, 
        t1.name as challenger_name, t1.id as challenger_id,
        t2.name as opponent_name, t2.id as opponent_id
        FROM challenges c
        LEFT JOIN teams t1 ON c.challenger_id = t1.id
        LEFT JOIN teams t2 ON c.opponent_id = t2.id
        WHERE c.id = ? AND (c.challenger_id = ? OR c.opponent_id = ?)
    ");
    $stmt->execute([$challenge_id, $my_team_id, $my_team_id]);
    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$challenge) {
        echo "<div class='card'><div class='alert alert-danger'>Pertandingan tidak ditemukan atau Anda tidak memiliki akses.</div><a href='schedule.php' class='btn-secondary'>Kembali</a></div>";
        require_once 'includes/footer.php';
        exit;
    }

    $match_event = trim($challenge['sport_type'] ?? '');

    // Determine current lineup
    $current_lineup = [];
    $stmtLineup = $conn->prepare("SELECT player_id, is_starting FROM lineups WHERE match_id = ? AND team_id = ?");
    $stmtLineup->execute([$challenge_id, $my_team_id]);
    while ($row = $stmtLineup->fetch(PDO::FETCH_ASSOC)) {
        $current_lineup[$row['player_id']] = $row['is_starting'];
    }

    // Get all players
    if ($match_event !== '') {
        $stmtPlayers = $conn->prepare("SELECT * FROM players WHERE team_id = ? AND status = 'active' AND sport_type = ? ORDER BY jersey_number ASC");
        $stmtPlayers->execute([$my_team_id, $match_event]);
        $players = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);

        if (empty($players)) {
            $event_fallback = true;
            $stmtPlayers = $conn->prepare("SELECT * FROM players WHERE team_id = ? AND status = 'active' ORDER BY jersey_number ASC");
            $stmtPlayers->execute([$my_team_id]);
            $players = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $event_fallback = true;
        $stmtPlayers = $conn->prepare("SELECT * FROM players WHERE team_id = ? AND status = 'active' ORDER BY jersey_number ASC");
        $stmtPlayers->execute([$my_team_id]);
        $players = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // 1. Clear existing lineup for this match & team
        $stmtDelete = $conn->prepare("DELETE FROM lineups WHERE match_id = ? AND team_id = ?");
        $stmtDelete->execute([$challenge_id, $my_team_id]);

        // 2. Insert new lineup
        if (isset($_POST['players']) && is_array($_POST['players'])) {
            $stmtInsert = $conn->prepare("INSERT INTO lineups (match_id, player_id, team_id, is_starting, position) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($_POST['players'] as $player_id) {
                $is_starting = isset($_POST['starters'][$player_id]) ? 1 : 0;
                // Find player position from array (optimization: could map earlier, but loop is small)
                $pos = '';
                foreach($players as $p) { if($p['id'] == $player_id) { $pos = $p['position']; break; } }
                
                $stmtInsert->execute([$challenge_id, $player_id, $my_team_id, $is_starting, $pos]);
            }
        }

        $conn->commit();
        $_SESSION['success_message'] = "Lineup berhasil disimpan!";
        
        // Refresh check
        header("Location: match_lineup.php?id=$challenge_id");
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Gagal menyimpan lineup: " . $e->getMessage();
    }
}
?>

<div class="card">
    <div class="section-header">
        <div>
            <h2 class="section-title">Atur Lineup: <?php echo htmlspecialchars($challenge['challenger_name'] ?? ''); ?> vs <?php echo htmlspecialchars($challenge['opponent_name'] ?? ''); ?></h2>
            <p class="text-muted">Pilih pemain yang akan bertanding.</p>
        </div>
        <a href="schedule.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($event_fallback)): ?>
        <div class="alert alert-warning">
            Event pertandingan belum diatur atau tidak ada pemain yang cocok. Menampilkan semua pemain tim.
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Main</th>
                        <th style="width: 50px;">Starter</th>
                        <th>No</th>
                        <th>Nama</th>
                        <th>Posisi</th>
                        <th>Event</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $player): 
                        $is_selected = isset($current_lineup[$player['id']]);
                        $is_starting = $is_selected && $current_lineup[$player['id']] == 1;
                    ?>
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" name="players[]" value="<?php echo $player['id']; ?>" 
                                   class="form-check-input player-select" 
                                   data-id="<?php echo $player['id']; ?>"
                                   <?php echo $is_selected ? 'checked' : ''; ?>>
                        </td>
                        <td class="text-center">
                            <input type="checkbox" name="starters[<?php echo $player['id']; ?>]" value="1" 
                                   class="form-check-input starter-select" 
                                   id="starter-<?php echo $player['id']; ?>"
                                   <?php echo $is_starting ? 'checked' : ''; ?>
                                   <?php echo !$is_selected ? 'disabled' : ''; ?>>
                        </td>
                        <td><span class="badge badge-primary"><?php echo $player['jersey_number']; ?></span></td>
                        <td><?php echo htmlspecialchars($player['name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($player['position'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($player['sport_type'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-actions mt-4">
            <button type="submit" class="btn-primary">
                <i class="fas fa-save"></i> Simpan Lineup
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const playerChecks = document.querySelectorAll('.player-select');
    
    playerChecks.forEach(check => {
        check.addEventListener('change', function() {
            const id = this.dataset.id;
            const starterCheck = document.getElementById('starter-' + id);
            
            if (this.checked) {
                starterCheck.disabled = false;
            } else {
                starterCheck.disabled = true;
                starterCheck.checked = false;
            }
        });
    });
});
</script>

<style>
.form-check-input {
    width: 20px;
    height: 20px;
    cursor: pointer;
}
.badge-primary {
    background-color: var(--primary);
    color: white;
    padding: 4px 8px;
    border-radius: 50%;
    font-size: 12px;
}
.alert-warning {
    background: rgba(249, 168, 38, 0.1);
    border-left: 4px solid var(--warning);
    color: var(--warning);
}
.text-center { text-align: center; }
.mt-4 { margin-top: 1.5rem; }
</style>

<?php require_once 'includes/footer.php'; ?>
