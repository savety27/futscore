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
        die("Error: Anda tidak terdaftar dalam team manapun.");
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

<div class="lineup-page">
    <div class="card lineup-shell">
        <div class="lineup-hero">
            <div class="lineup-hero__copy">
                <span class="lineup-hero__eyebrow">Pengaturan Susunan Pemain</span>
                <h2 class="lineup-hero__title">
                    <?php echo htmlspecialchars($challenge['challenger_name'] ?? ''); ?>
                    <span class="lineup-hero__vs">vs</span>
                    <?php echo htmlspecialchars($challenge['opponent_name'] ?? ''); ?>
                </h2>
                <div class="lineup-hero__meta">
                    <span class="lineup-pill">
                        <i class="fas fa-trophy"></i>
                        <?php echo htmlspecialchars($challenge['sport_type'] ?? '-'); ?>
                    </span>
                    <span class="lineup-pill">
                        <i class="fas fa-hashtag"></i>
                        Match <?php echo (int)$challenge_id; ?>
                    </span>
                </div>
            </div>
            <a href="schedule.php" class="btn-secondary lineup-back-btn">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger lineup-alert lineup-alert--danger">
                <i class="fas fa-circle-exclamation"></i>
                <span><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success lineup-alert lineup-alert--success">
                <i class="fas fa-circle-check"></i>
                <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!$has_lineups_half_column): ?>
            <div class="alert alert-warning lineup-alert lineup-alert--warning">
                <i class="fas fa-triangle-exclamation"></i>
                <span>Simpan lineup dinonaktifkan sampai migrasi database dijalankan: <code>migrations/migration_add_half_column_to_lineups.sql</code></span>
            </div>
        <?php endif; ?>

        <div class="lineup-filter-panel">
            <form method="GET" class="lineup-filter-form">
                <input type="hidden" name="id" value="<?php echo (int)$challenge_id; ?>">
                <div class="lineup-filter-grid">
                    <div class="lineup-filter-group">
                        <label>Event Pertandingan</label>
                        <select name="event" class="form-control" disabled>
                            <option value="<?php echo htmlspecialchars($challenge['sport_type'] ?? ''); ?>" selected>
                                <?php echo htmlspecialchars($challenge['sport_type'] ?? '-'); ?>
                            </option>
                        </select>
                        <input type="hidden" name="event" value="<?php echo htmlspecialchars($challenge['sport_type'] ?? ''); ?>">
                    </div>
                    <div class="lineup-filter-group">
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
                    <div class="lineup-filter-group">
                        <label>Nama / Nomor Punggung</label>
                        <input type="text" name="q" class="form-control" placeholder="Cari nama atau nomor..." value="<?php echo htmlspecialchars($filter_search); ?>">
                    </div>
                    <div class="lineup-filter-actions">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="match_lineup.php?id=<?php echo (int)$challenge_id; ?>" class="btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <form method="POST" action="" class="lineup-form">
            <div class="lineup-tabs" role="tablist" aria-label="Pilihan babak lineup">
                <button type="button" class="lineup-tab-btn active" onclick="openTab(event, 'half1')">Babak 1</button>
                <button type="button" class="lineup-tab-btn" onclick="openTab(event, 'half2')">Babak 2</button>
            </div>

            <div id="half1" class="lineup-tab-content active">
                <div class="lineup-tab-header">
                    <h4>Lineup Babak 1</h4>
                    <p>Pilih pemain yang tampil, lalu tandai siapa yang menjadi starter.</p>
                </div>
                <div class="lineup-table-wrap">
                    <table class="data-table lineup-table">
                        <thead>
                            <tr>
                                <th style="width: 72px;">Main</th>
                                <th style="width: 72px;">Starter</th>
                                <th style="width: 88px;">No</th>
                                <th>Nama</th>
                                <th style="width: 140px;">Posisi</th>
                                <th style="width: 180px;">Event</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $player):
                                $is_selected = isset($current_lineup_h1[$player['id']]);
                                $is_starting = $is_selected && $current_lineup_h1[$player['id']] == 1;
                                $is_valid = ($player['sport_type'] == $challenge['sport_type']);
                            ?>
                                <tr class="<?php echo !$is_valid ? 'lineup-row-invalid' : ''; ?>">
                                    <td class="lineup-cell-center">
                                        <input
                                            type="checkbox"
                                            name="players_h1[]"
                                            value="<?php echo (int)$player['id']; ?>"
                                            class="form-check-input lineup-checkbox player-select-h1"
                                            data-id="<?php echo (int)$player['id']; ?>"
                                            <?php echo $is_selected ? 'checked' : ''; ?>
                                            <?php echo !$is_valid ? 'disabled' : ''; ?>
                                        >
                                    </td>
                                    <td class="lineup-cell-center">
                                        <input
                                            type="checkbox"
                                            name="starters_h1[<?php echo (int)$player['id']; ?>]"
                                            value="1"
                                            class="form-check-input lineup-checkbox starter-select-h1"
                                            id="starter-h1-<?php echo (int)$player['id']; ?>"
                                            <?php echo $is_starting ? 'checked' : ''; ?>
                                            <?php echo !$is_selected ? 'disabled' : ''; ?>
                                            <?php echo !$is_valid ? 'disabled' : ''; ?>
                                        >
                                    </td>
                                    <td>
                                        <span class="badge badge-primary lineup-jersey"><?php echo htmlspecialchars($player['jersey_number'] ?? '-'); ?></span>
                                    </td>
                                    <td>
                                        <span class="lineup-player-name"><?php echo htmlspecialchars($player['name'] ?? ''); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($player['position'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($is_valid): ?>
                                            <span class="badge badge-success lineup-event-badge lineup-event-badge--ok">Sesuai</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger lineup-event-badge lineup-event-badge--invalid">Beda Kategori</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="half2" class="lineup-tab-content">
                <div class="lineup-tab-header">
                    <h4>Lineup Babak 2</h4>
                    <p>Pilih susunan pemain untuk babak kedua pertandingan.</p>
                </div>
                <div class="lineup-table-wrap">
                    <table class="data-table lineup-table">
                        <thead>
                            <tr>
                                <th style="width: 72px;">Main</th>
                                <th style="width: 72px;">Starter</th>
                                <th style="width: 88px;">No</th>
                                <th>Nama</th>
                                <th style="width: 140px;">Posisi</th>
                                <th style="width: 180px;">Event</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $player):
                                $is_selected = isset($current_lineup_h2[$player['id']]);
                                $is_starting = $is_selected && $current_lineup_h2[$player['id']] == 1;
                                $is_valid = ($player['sport_type'] == $challenge['sport_type']);
                            ?>
                                <tr class="<?php echo !$is_valid ? 'lineup-row-invalid' : ''; ?>">
                                    <td class="lineup-cell-center">
                                        <input
                                            type="checkbox"
                                            name="players_h2[]"
                                            value="<?php echo (int)$player['id']; ?>"
                                            class="form-check-input lineup-checkbox player-select-h2"
                                            data-id="<?php echo (int)$player['id']; ?>"
                                            <?php echo $is_selected ? 'checked' : ''; ?>
                                            <?php echo !$is_valid ? 'disabled' : ''; ?>
                                        >
                                    </td>
                                    <td class="lineup-cell-center">
                                        <input
                                            type="checkbox"
                                            name="starters_h2[<?php echo (int)$player['id']; ?>]"
                                            value="1"
                                            class="form-check-input lineup-checkbox starter-select-h2"
                                            id="starter-h2-<?php echo (int)$player['id']; ?>"
                                            <?php echo $is_starting ? 'checked' : ''; ?>
                                            <?php echo !$is_selected ? 'disabled' : ''; ?>
                                            <?php echo !$is_valid ? 'disabled' : ''; ?>
                                        >
                                    </td>
                                    <td>
                                        <span class="badge badge-primary lineup-jersey"><?php echo htmlspecialchars($player['jersey_number'] ?? '-'); ?></span>
                                    </td>
                                    <td>
                                        <span class="lineup-player-name"><?php echo htmlspecialchars($player['name'] ?? ''); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($player['position'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($is_valid): ?>
                                            <span class="badge badge-success lineup-event-badge lineup-event-badge--ok">Sesuai</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger lineup-event-badge lineup-event-badge--invalid">Beda Kategori</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-actions lineup-actions">
                <button type="submit" class="btn-primary lineup-save-btn" <?php echo !$has_lineups_half_column ? 'disabled title="Migrasi database belum dijalankan"' : ''; ?>>
                    <i class="fas fa-save"></i> Simpan Lineup
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openTab(evt, tabName) {
    var tabContent = document.getElementsByClassName('lineup-tab-content');
    var tabButtons = document.getElementsByClassName('lineup-tab-btn');
    var i;

    for (i = 0; i < tabContent.length; i++) {
        tabContent[i].classList.remove('active');
    }

    for (i = 0; i < tabButtons.length; i++) {
        tabButtons[i].classList.remove('active');
    }

    document.getElementById(tabName).classList.add('active');
    evt.currentTarget.classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {
    ['h1', 'h2'].forEach(function(half) {
        var playerChecks = document.querySelectorAll('.player-select-' + half);

        playerChecks.forEach(function(check) {
            check.addEventListener('change', function() {
                var id = this.dataset.id;
                var starterCheck = document.getElementById('starter-' + half + '-' + id);

                if (!starterCheck) {
                    return;
                }

                if (this.checked) {
                    starterCheck.disabled = false;
                } else {
                    starterCheck.disabled = true;
                    starterCheck.checked = false;
                }
            });
        });
    });
});
</script>

<style>
.main {
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
}

.lineup-page {
    --lineup-panel-border: #dbe4f2;
    --lineup-surface-soft: #f5f9ff;
    --lineup-shadow-soft: 0 18px 36px rgba(12, 39, 89, 0.08);
}

.lineup-page .lineup-shell {
    position: relative;
    border: 1px solid #e5ebf5;
    box-shadow: var(--lineup-shadow-soft);
    overflow: hidden;
    background:
        radial-gradient(1200px 480px at 0% -20%, rgba(76, 201, 240, 0.13), rgba(76, 201, 240, 0)),
        radial-gradient(880px 420px at 100% -10%, rgba(255, 215, 0, 0.16), rgba(255, 215, 0, 0)),
        #ffffff;
}

.lineup-hero {
    position: relative;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
    padding: 22px;
    border-radius: 18px;
    background: linear-gradient(128deg, #0a2463 0%, #113577 48%, #1f5ea4 100%);
    color: #ffffff;
    overflow: hidden;
}

.lineup-hero::after {
    content: '';
    position: absolute;
    right: -72px;
    top: -68px;
    width: 210px;
    height: 210px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.28), rgba(255, 255, 255, 0));
    pointer-events: none;
}

.lineup-hero__copy {
    position: relative;
    z-index: 1;
}

.lineup-hero__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    font-size: 12px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.88);
}

.lineup-hero__title {
    margin: 0;
    font-size: clamp(1.35rem, 2.2vw, 1.85rem);
    line-height: 1.28;
    font-weight: 800;
    max-width: 760px;
}

.lineup-hero__vs {
    margin: 0 8px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.72);
}

.lineup-hero__meta {
    margin-top: 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.lineup-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.28);
    background: rgba(255, 255, 255, 0.12);
    font-size: 12px;
    font-weight: 600;
}

.lineup-back-btn {
    align-self: flex-start;
    white-space: nowrap;
    border: 1px solid rgba(255, 255, 255, 0.38);
    background: rgba(255, 255, 255, 0.14);
    color: #ffffff;
    padding: 10px 14px;
    border-radius: 12px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    transition: all 0.2s ease;
}

.lineup-back-btn:hover {
    background: rgba(255, 255, 255, 0.22);
    color: #ffffff;
    transform: translateY(-1px);
}

.lineup-alert {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 12px;
    padding: 13px 14px;
    border-radius: 13px;
    border: 1px solid transparent;
    font-size: 13px;
    line-height: 1.45;
}

.lineup-alert i {
    margin-top: 2px;
}

.lineup-alert--danger {
    color: #8d1f1f;
    background: #fff1f1;
    border-color: #ffd3d3;
}

.lineup-alert--success {
    color: #1f6a30;
    background: #effcf1;
    border-color: #ccefd2;
}

.lineup-alert--warning {
    color: #865a09;
    background: #fff8e8;
    border-color: #ffe3a6;
}

.lineup-filter-panel {
    margin-bottom: 18px;
    padding: 16px;
    border-radius: 16px;
    border: 1px solid var(--lineup-panel-border);
    background: linear-gradient(180deg, #ffffff 0%, var(--lineup-surface-soft) 100%);
}

.lineup-filter-grid {
    display: grid;
    grid-template-columns: minmax(240px, 1.25fr) minmax(170px, 0.7fr) minmax(230px, 1fr) auto;
    gap: 12px;
    align-items: end;
}

.lineup-filter-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #2f3a4a;
}

.lineup-filter-form .form-control {
    width: 100%;
    height: 44px;
    border-radius: 12px;
    border: 1px solid #d2ddeb;
    background: #ffffff;
    padding: 10px 12px;
    font-size: 14px;
    color: #1f2937;
    transition: all 0.2s ease;
}

.lineup-filter-form .form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.12);
}

.lineup-filter-form .form-control:disabled {
    background: #eff4fb;
    color: #41556f;
    cursor: not-allowed;
}

.lineup-filter-actions {
    display: flex;
    gap: 8px;
}

.lineup-filter-actions .btn-primary,
.lineup-filter-actions .btn-secondary {
    height: 44px;
    padding: 0 15px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    text-decoration: none;
    white-space: nowrap;
}

.lineup-filter-actions .btn-secondary {
    border: 1px solid #d0dced;
    color: #233448;
    background: #ffffff;
}

.lineup-filter-actions .btn-secondary:hover {
    background: #eef3fb;
}

.lineup-tabs {
    display: inline-grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 6px;
    margin: 6px 0 16px;
    padding: 6px;
    border-radius: 14px;
    background: #edf2fb;
    border: 1px solid #d7e0ef;
    width: min(420px, 100%);
}

.lineup-tab-btn {
    border: none;
    cursor: pointer;
    border-radius: 10px;
    padding: 10px 14px;
    font-size: 14px;
    font-weight: 700;
    color: #45556d;
    background: transparent;
    transition: all 0.2s ease;
}

.lineup-tab-btn:hover {
    color: #132d60;
}

.lineup-tab-btn.active {
    color: #0a2463;
    background: #ffffff;
    box-shadow: 0 4px 10px rgba(14, 44, 98, 0.12);
}

.lineup-tab-content {
    display: none;
}

.lineup-tab-content.active {
    display: block;
    animation: lineupFadeIn 0.28s ease;
}

.lineup-tab-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.lineup-tab-header h4 {
    margin: 0;
    color: #102e67;
    font-size: 18px;
    font-weight: 800;
}

.lineup-tab-header p {
    margin: 0;
    color: #5b6b83;
    font-size: 13px;
}

.lineup-table-wrap {
    border: 1px solid #d6e1f1;
    border-radius: 16px;
    overflow-x: auto;
    background: #ffffff;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
}

.lineup-table {
    min-width: 760px;
}

.lineup-table thead {
    background: linear-gradient(135deg, #0a2463, #133f85);
}

.lineup-table th {
    border-bottom: 2px solid rgba(255, 255, 255, 0.22);
    font-size: 12px;
    letter-spacing: 0.02em;
    text-transform: uppercase;
}

.lineup-table td {
    padding: 13px 15px;
}

.lineup-table tbody tr {
    border-bottom: 1px solid #edf2fa;
}

.lineup-table tbody tr:hover {
    background: #f7faff;
}

.lineup-row-invalid {
    opacity: 0.6;
    background: repeating-linear-gradient(
        -45deg,
        rgba(234, 241, 252, 0.55),
        rgba(234, 241, 252, 0.55) 8px,
        rgba(248, 251, 255, 0.55) 8px,
        rgba(248, 251, 255, 0.55) 16px
    );
}

.lineup-cell-center {
    text-align: center;
}

.lineup-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary);
}

.lineup-jersey {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 34px;
    min-height: 34px;
    border-radius: 999px;
    background: linear-gradient(145deg, #0a2463, #174d99);
    color: #ffffff;
    font-size: 12px;
    font-weight: 700;
    box-shadow: 0 4px 10px rgba(9, 34, 87, 0.24);
}

.lineup-player-name {
    font-weight: 700;
    color: #0f203f;
}

.lineup-event-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.01em;
}

.lineup-event-badge--ok {
    color: #1f6a30;
    background: #e7f7eb;
}

.lineup-event-badge--invalid {
    color: #8f2626;
    background: #ffe9e9;
}

.lineup-actions {
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px dashed #d0dcef;
    display: flex;
    justify-content: flex-end;
}

.lineup-save-btn {
    min-width: 200px;
    padding: 11px 20px;
    border-radius: 12px;
}

.lineup-save-btn[disabled] {
    opacity: 0.55;
    cursor: not-allowed;
    transform: none;
}

@keyframes lineupFadeIn {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 1080px) {
    .lineup-filter-grid {
        grid-template-columns: 1fr 1fr;
    }

    .lineup-filter-actions {
        grid-column: span 2;
        justify-content: flex-start;
    }
}

@media (max-width: 860px) {
    .lineup-hero {
        flex-direction: column;
        align-items: flex-start;
    }

    .lineup-back-btn {
        align-self: stretch;
        justify-content: center;
    }

    .lineup-tab-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 640px) {
    .lineup-page .lineup-shell {
        padding: 18px 14px;
    }

    .lineup-hero {
        padding: 18px 14px;
        border-radius: 14px;
    }

    .lineup-filter-grid {
        grid-template-columns: 1fr;
    }

    .lineup-filter-actions {
        grid-column: auto;
    }

    .lineup-filter-actions .btn-primary,
    .lineup-filter-actions .btn-secondary,
    .lineup-save-btn {
        width: 100%;
        justify-content: center;
    }

    .lineup-actions {
        justify-content: stretch;
    }
}

@media (prefers-reduced-motion: reduce) {
    .lineup-tab-content.active {
        animation: none;
    }

    .lineup-back-btn,
    .lineup-tab-btn,
    .lineup-filter-form .form-control {
        transition: none;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
