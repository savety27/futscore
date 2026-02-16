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
$has_lineups_half_column = false;

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

    // Initial Filter: If user hasn't selected an event filter, force it to match the challenge event
    if ($filter_event === '' && !empty($challenge['sport_type'])) {
        $filter_event = $challenge['sport_type'];
    }

    $stmtHalfColumn = $conn->query("SHOW COLUMNS FROM lineups LIKE 'half'");
    $has_lineups_half_column = $stmtHalfColumn && $stmtHalfColumn->fetch(PDO::FETCH_ASSOC) !== false;

    // Determine current lineup (Babak 1 & 2)
    $current_lineup_h1 = [];
    $current_lineup_h2 = [];

    if ($has_lineups_half_column) {
        $stmtLineup = $conn->prepare("SELECT player_id, is_starting, half FROM lineups WHERE match_id = ? AND team_id = ?");
        $stmtLineup->execute([$challenge_id, $my_team_id]);
        while ($row = $stmtLineup->fetch(PDO::FETCH_ASSOC)) {
            if ((int)$row['half'] === 2) {
                $current_lineup_h2[$row['player_id']] = $row['is_starting'];
            } else {
                $current_lineup_h1[$row['player_id']] = $row['is_starting'];
            }
        }
    } else {
        $stmtLineup = $conn->prepare("SELECT player_id, is_starting FROM lineups WHERE match_id = ? AND team_id = ?");
        $stmtLineup->execute([$challenge_id, $my_team_id]);
        while ($row = $stmtLineup->fetch(PDO::FETCH_ASSOC)) {
            $current_lineup_h1[$row['player_id']] = $row['is_starting'];
        }
    }

    // Get all players (with optional filters)
    $player_query = "SELECT * FROM players WHERE team_id = ? AND status = 'active'";
    $player_params = [$my_team_id];

    if ($filter_event !== '') {
        $player_query .= " AND sport_type = ?";
        $player_params[] = $filter_event;
    } else {
        // Strict restriction: If no filter selected, still ONLY show players matching challenge event
        // Unless user explicitly clears it (but we forced it above).
        if (!empty($challenge['sport_type'])) {
             $player_query .= " AND sport_type = ?";
             $player_params[] = $challenge['sport_type'];
        }
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
    if (!$has_lineups_half_column) {
        $error_message = "Pembaruan database belum diterapkan. Jalankan migrations/migration_add_half_column_to_lineups.sql terlebih dahulu.";
    } else {
        try {
            $conn->beginTransaction();

            // 1. Clear existing lineup for this match & team
            $stmtDelete = $conn->prepare("DELETE FROM lineups WHERE match_id = ? AND team_id = ?");
            $stmtDelete->execute([$challenge_id, $my_team_id]);

            $stmtInsert = $conn->prepare("INSERT INTO lineups (match_id, player_id, team_id, is_starting, position, half) VALUES (?, ?, ?, ?, ?, ?)");

            // 2. Insert new lineup - Babak 1
            if (isset($_POST['players_h1']) && is_array($_POST['players_h1'])) {
                foreach ($_POST['players_h1'] as $player_id) {
                    $is_starting = isset($_POST['starters_h1'][$player_id]) ? 1 : 0;
                    $pos = '';
                    foreach($players as $p) { if($p['id'] == $player_id) { $pos = $p['position']; break; } }

                    $stmtInsert->execute([$challenge_id, $player_id, $my_team_id, $is_starting, $pos, 1]);
                }
            }

            // 3. Insert new lineup - Babak 2
            if (isset($_POST['players_h2']) && is_array($_POST['players_h2'])) {
                foreach ($_POST['players_h2'] as $player_id) {
                     $is_starting = isset($_POST['starters_h2'][$player_id]) ? 1 : 0;
                     $pos = '';
                     foreach($players as $p) { if($p['id'] == $player_id) { $pos = $p['position']; break; } }

                     $stmtInsert->execute([$challenge_id, $player_id, $my_team_id, $is_starting, $pos, 2]);
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
}
?>

<div class="card">
    <div class="section-header">
        <div>
            <h2 class="section-title">Atur Lineup: <?php echo htmlspecialchars($challenge['challenger_name'] ?? ''); ?> vs <?php echo htmlspecialchars($challenge['opponent_name'] ?? ''); ?></h2>
            <p class="text-muted">Event: <strong><?php echo htmlspecialchars($challenge['sport_type']); ?></strong></p>
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

    <?php if (!$has_lineups_half_column): ?>
        <div class="alert alert-warning">
            Simpan lineup dinonaktifkan sampai migrasi database dijalankan: <code>migrations/migration_add_half_column_to_lineups.sql</code>
        </div>
    <?php endif; ?>

    <div class="filter-container">
        <form method="GET" class="filter-form">
            <input type="hidden" name="id" value="<?php echo $challenge_id; ?>">
            <div class="filter-grid">
                <div class="filter-group">
                    <label>Event (Otomatis)</label>
                    <select name="event" class="form-control" disabled>
                        <option value="<?php echo htmlspecialchars($challenge['sport_type']); ?>" selected>
                            <?php echo htmlspecialchars($challenge['sport_type']); ?>
                        </option>
                    </select>
                     <!-- Sent hidden for logic consistency if needed, though we force it in PHP -->
                    <input type="hidden" name="event" value="<?php echo htmlspecialchars($challenge['sport_type']); ?>">
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
        <!-- TABS for Halves -->
        <div class="tabs">
            <button type="button" class="tab-btn active" onclick="openTab(event, 'half1')">Babak 1</button>
            <button type="button" class="tab-btn" onclick="openTab(event, 'half2')">Babak 2</button>
        </div>

        <!-- Half 1 Content -->
        <div id="half1" class="tab-content active">
            <h4 class="mb-3">Lineup Babak 1</h4>
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
                            $is_selected = isset($current_lineup_h1[$player['id']]);
                            $is_starting = $is_selected && $current_lineup_h1[$player['id']] == 1;
                            
                            // Check restriction (visual mainly, as query already filters)
                            $is_valid = ($player['sport_type'] == $challenge['sport_type']);
                        ?>
                        <tr class="<?php echo !$is_valid ? 'opacity-50' : ''; ?>">
                            <td class="text-center">
                                <input type="checkbox" name="players_h1[]" value="<?php echo $player['id']; ?>" 
                                       class="form-check-input player-select-h1" 
                                       data-id="<?php echo $player['id']; ?>"
                                       <?php echo $is_selected ? 'checked' : ''; ?>
                                       <?php echo !$is_valid ? 'disabled' : ''; ?>>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" name="starters_h1[<?php echo $player['id']; ?>]" value="1" 
                                       class="form-check-input starter-select-h1" 
                                       id="starter-h1-<?php echo $player['id']; ?>"
                                       <?php echo $is_starting ? 'checked' : ''; ?>
                                       <?php echo !$is_selected ? 'disabled' : ''; ?>
                                       <?php echo !$is_valid ? 'disabled' : ''; ?>>
                            </td>
                            <td><span class="badge badge-primary"><?php echo $player['jersey_number']; ?></span></td>
                            <td><?php echo htmlspecialchars($player['name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($player['position'] ?? ''); ?></td>
                            <td>
                                <?php if($is_valid): ?>
                                    <span class="badge badge-success" style="font-size: 10px;">Sesuai</span>
                                <?php else: ?>
                                    <span class="badge badge-danger" style="font-size: 10px;">Beda Kategori</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Half 2 Content -->
        <div id="half2" class="tab-content">
            <h4 class="mb-3">Lineup Babak 2</h4>
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
                            $is_selected = isset($current_lineup_h2[$player['id']]);
                            $is_starting = $is_selected && $current_lineup_h2[$player['id']] == 1;
                            
                             $is_valid = ($player['sport_type'] == $challenge['sport_type']);
                        ?>
                        <tr class="<?php echo !$is_valid ? 'opacity-50' : ''; ?>">
                            <td class="text-center">
                                <input type="checkbox" name="players_h2[]" value="<?php echo $player['id']; ?>" 
                                       class="form-check-input player-select-h2" 
                                       data-id="<?php echo $player['id']; ?>"
                                       <?php echo $is_selected ? 'checked' : ''; ?>
                                       <?php echo !$is_valid ? 'disabled' : ''; ?>>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" name="starters_h2[<?php echo $player['id']; ?>]" value="1" 
                                       class="form-check-input starter-select-h2" 
                                       id="starter-h2-<?php echo $player['id']; ?>"
                                       <?php echo $is_starting ? 'checked' : ''; ?>
                                       <?php echo !$is_selected ? 'disabled' : ''; ?>
                                       <?php echo !$is_valid ? 'disabled' : ''; ?>>
                            </td>
                            <td><span class="badge badge-primary"><?php echo $player['jersey_number']; ?></span></td>
                            <td><?php echo htmlspecialchars($player['name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($player['position'] ?? ''); ?></td>
                            <td>
                                <?php if($is_valid): ?>
                                    <span class="badge badge-success" style="font-size: 10px;">Sesuai</span>
                                <?php else: ?>
                                    <span class="badge badge-danger" style="font-size: 10px;">Beda Kategori</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-actions mt-4">
            <button type="submit" class="btn-primary" <?php echo !$has_lineups_half_column ? 'disabled title="Migrasi database belum dijalankan"' : ''; ?>>
                <i class="fas fa-save"></i> Simpan Lineup
            </button>
        </div>
    </form>
</div>

<script>
function openTab(evt, tabName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
        tabcontent[i].classList.remove("active");
    }
    tablinks = document.getElementsByClassName("tab-btn");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(tabName).style.display = "block";
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.className += " active";
}

document.addEventListener('DOMContentLoaded', function() {
    // Logic for Half 1
    const playerChecksH1 = document.querySelectorAll('.player-select-h1');
    playerChecksH1.forEach(check => {
        check.addEventListener('change', function() {
            const id = this.dataset.id;
            const starterCheck = document.getElementById('starter-h1-' + id);
            
            if (this.checked) {
                starterCheck.disabled = false;
            } else {
                starterCheck.disabled = true;
                starterCheck.checked = false;
            }
        });
    });

    // Logic for Half 2
    const playerChecksH2 = document.querySelectorAll('.player-select-h2');
    playerChecksH2.forEach(check => {
        check.addEventListener('change', function() {
            const id = this.dataset.id;
            const starterCheck = document.getElementById('starter-h2-' + id);
            
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
/* Tabs Styling */
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
}

.tab-btn {
    padding: 10px 20px;
    border: none;
    background: none;
    font-size: 16px;
    font-weight: 600;
    color: var(--gray);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
}

.tab-btn:hover {
    color: var(--primary);
    background: #f8f9fa;
}

.tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.tab-content {
    display: none;
    animation: fadeIn 0.5s;
}

.tab-content.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

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
.badge-success { background-color: var(--success); color: white; padding: 2px 6px; border-radius: 4px; }
.badge-danger { background-color: var(--danger); color: white; padding: 2px 6px; border-radius: 4px; }

.text-center { text-align: center; }
.mt-4 { margin-top: 1.5rem; }
.mb-3 { margin-bottom: 1rem; }
.opacity-50 { opacity: 0.5; }

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
