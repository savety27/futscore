<?php
$page_title = 'Atur Lineup';
$current_page = 'schedule';
require_once 'config/database.php';
require_once 'includes/header.php';

$challenge_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pelatih_id = $_SESSION['pelatih_id'] ?? 0;
$filter_event = trim($_GET['event'] ?? '');
$filter_position = trim($_GET['position'] ?? '');
$filter_search = trim($_GET['q'] ?? '');

$event_types = [
    'LIGA AAFI BATAM U-13 PUTRA 2026',
    'LIGA AAFI BATAM U-16 PUTRA 2026',
    'LIGA AAFI BATAM U-16 PUTRI 2026'
];

$position_options = [
    'GK' => 'Goalkeeper (GK)',
    'DF' => 'Defender (DF)',
    'MF' => 'Midfielder (MF)',
    'FW' => 'Forward (FW)'
];

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

    // Determine current lineup
    $current_lineup = [];
    $stmtLineup = $conn->prepare("SELECT player_id, is_starting FROM lineups WHERE match_id = ? AND team_id = ?");
    $stmtLineup->execute([$challenge_id, $my_team_id]);
    while ($row = $stmtLineup->fetch(PDO::FETCH_ASSOC)) {
        $current_lineup[$row['player_id']] = $row['is_starting'];
    }

    // Get all players (with optional filters)
    $player_query = "SELECT * FROM players WHERE team_id = ? AND status = 'active'";
    $player_params = [$my_team_id];

    if ($filter_event !== '') {
        $player_query .= " AND sport_type = ?";
        $player_params[] = $filter_event;
    }

    if ($filter_position !== '') {
        $player_query .= " AND position = ?";
        $player_params[] = $filter_position;
    }

    if ($filter_search !== '') {
        $player_query .= " AND (name LIKE ? OR jersey_number LIKE ?)";
        $search_term = '%' . $filter_search . '%';
        $player_params[] = $search_term;
        $player_params[] = $search_term;
    }

    $player_query .= " ORDER BY jersey_number ASC";
    $stmtPlayers = $conn->prepare($player_query);
    $stmtPlayers->execute($player_params);
    $players = $stmtPlayers->fetchAll(PDO::FETCH_ASSOC);

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

    <div class="filter-container">
        <form method="GET" class="filter-form">
            <input type="hidden" name="id" value="<?php echo $challenge_id; ?>">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Event</label>
                    <select name="event" class="form-control">
                        <option value="">Semua Event</option>
                        <?php foreach ($event_types as $event_option): ?>
                            <option value="<?php echo htmlspecialchars($event_option); ?>" <?php echo $filter_event === $event_option ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($event_option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Posisi</label>
                    <select name="position" class="form-control">
                        <option value="">Semua Posisi</option>
                        <?php foreach ($position_options as $pos_value => $pos_label): ?>
                            <option value="<?php echo htmlspecialchars($pos_value); ?>" <?php echo $filter_position === $pos_value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pos_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Nama / No</label>
                    <input type="text" name="q" class="form-control" placeholder="Cari nama atau no..." value="<?php echo htmlspecialchars($filter_search); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="match_lineup.php?id=<?php echo $challenge_id; ?>" class="btn-secondary">
                        Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

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
.text-center { text-align: center; }
.mt-4 { margin-top: 1.5rem; }

.filter-container {
    margin: 20px 0 14px;
    padding: 18px 20px;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    border: 1px solid #edf1f7;
}

.filter-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr 1fr auto;
    gap: 14px;
    align-items: end;
}

.filter-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
    color: #333;
    font-size: 13px;
}

.filter-form .form-control {
    height: 44px;
    border-radius: 12px;
    border: 1px solid #dfe6f1;
    background: #f8fafc;
    padding: 10px 12px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.filter-form .form-control:focus {
    outline: none;
    background: #ffffff;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.12);
}

.filter-actions {
    display: flex;
    gap: 8px;
}

.filter-actions .btn-primary,
.filter-actions .btn-secondary {
    height: 44px;
    padding: 0 16px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.filter-actions .btn-secondary {
    background: #f1f5f9;
    color: #0f172a;
    border: 1px solid #e2e8f0;
}

.filter-actions .btn-secondary:hover {
    background: #e2e8f0;
}

.btn-secondary {
    background: #ffffff;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    padding: 10px 16px;
    border-radius: 12px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background: #f8fafc;
    border-color: #cbd5f5;
    transform: translateY(-1px);
}

@media (max-width: 900px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    .filter-actions {
        justify-content: flex-start;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
