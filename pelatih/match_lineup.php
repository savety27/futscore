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
$has_uniform_choice_column = false;
$uniform_choice_column = '';
$uniform_options = [];
$selected_uniform_choices = [];
$my_team_uniform_raw = '';
$has_match_staff_assignments_table = false;
$has_match_staff_half_column = false;
$has_challenge_event_id_column = false;
$has_events_table = false;
$can_join_event_name = false;
$team_staffs = [];
$current_staff_ids = [];
$current_staff_ids_h1 = [];
$current_staff_ids_h2 = [];

$position_options = [
    'GK' => 'Goalkeeper (GK)',
    'DF' => 'Defender (DF)',
    'MF' => 'Midfielder (MF)',
    'FW' => 'Forward (FW)'
];

function normalizeTextKey($value): string
{
    $text = trim((string)$value);
    $text = preg_replace('/\s+/u', ' ', $text);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($text, 'UTF-8');
    }
    return strtolower($text);
}

function parseUniformChoices($raw): array
{
    $value = trim((string)$raw);
    if ($value === '') {
        return [];
    }

    $parts = preg_split('/\s*[,\/;|\n\r]+\s*|\s*-\s*/u', $value);
    if (!is_array($parts)) {
        return [$value];
    }

    $choices = [];
    foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part !== '') {
            $choices[] = $part;
        }
    }

    if (empty($choices)) {
        $choices[] = $value;
    }

    return array_values(array_unique($choices));
}

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

    $stmtEventIdCol = $conn->query("SHOW COLUMNS FROM challenges LIKE 'event_id'");
    $has_challenge_event_id_column = $stmtEventIdCol && $stmtEventIdCol->fetch(PDO::FETCH_ASSOC) !== false;
    $stmtEventsTable = $conn->query("SHOW TABLES LIKE 'events'");
    $has_events_table = $stmtEventsTable && $stmtEventsTable->fetch(PDO::FETCH_NUM) !== false;
    $can_join_event_name = $has_challenge_event_id_column && $has_events_table;

    $stmtStaffTable = $conn->query("SHOW TABLES LIKE 'match_staff_assignments'");
    $has_match_staff_assignments_table = $stmtStaffTable && $stmtStaffTable->fetch(PDO::FETCH_NUM) !== false;
    if ($has_match_staff_assignments_table) {
        $stmtStaffHalfColumn = $conn->query("SHOW COLUMNS FROM match_staff_assignments LIKE 'half'");
        $has_match_staff_half_column = $stmtStaffHalfColumn && $stmtStaffHalfColumn->fetch(PDO::FETCH_ASSOC) !== false;
    }

    // Get challenge details
    $challengeSelect = "
        SELECT c.*,
               " . ($can_join_event_name
                    ? "TRIM(COALESCE(NULLIF(e.name, ''), NULLIF(c.sport_type, ''))) AS event_name,"
                    : "TRIM(c.sport_type) AS event_name,") . "
               t1.name as challenger_name, t1.id as challenger_id,
               t2.name as opponent_name, t2.id as opponent_id
        FROM challenges c
        " . ($can_join_event_name ? "LEFT JOIN events e ON c.event_id = e.id" : "") . "
        LEFT JOIN teams t1 ON c.challenger_id = t1.id
        LEFT JOIN teams t2 ON c.opponent_id = t2.id
        WHERE c.id = ? AND (c.challenger_id = ? OR c.opponent_id = ?)
    ";
    $stmt = $conn->prepare($challengeSelect);
    $stmt->execute([$challenge_id, $my_team_id, $my_team_id]);
    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$challenge) {
        echo "<div class='card'><div class='alert alert-danger'>Pertandingan tidak ditemukan atau Anda tidak memiliki akses.</div><a href='schedule.php' class='btn-secondary'>Kembali</a></div>";
        require_once 'includes/footer.php';
        exit;
    }

    $stmtTeamStaff = $conn->prepare("
        SELECT id, name, position
        FROM team_staff
        WHERE team_id = ?
          AND is_active = 1
        ORDER BY
            CASE
                WHEN position = 'manager' THEN 1
                WHEN position = 'headcoach' THEN 2
                WHEN position = 'coach' THEN 3
                WHEN position = 'assistant_coach' THEN 4
                WHEN position = 'goalkeeper_coach' THEN 5
                WHEN position = 'fitness_coach' THEN 6
                WHEN position = 'analyst' THEN 7
                WHEN position = 'medic' THEN 8
                WHEN position = 'official' THEN 9
                ELSE 99
            END,
            name ASC
    ");
    $stmtTeamStaff->execute([$my_team_id]);
    $team_staffs = $stmtTeamStaff->fetchAll(PDO::FETCH_ASSOC);

    if ($has_match_staff_assignments_table) {
        if ($has_match_staff_half_column) {
            $stmtAssignedStaff = $conn->prepare("
                SELECT staff_id, half
                FROM match_staff_assignments
                WHERE match_id = ? AND team_id = ?
            ");
            $stmtAssignedStaff->execute([$challenge_id, $my_team_id]);
            foreach ($stmtAssignedStaff->fetchAll(PDO::FETCH_ASSOC) as $assignedRow) {
                $sid = (int)($assignedRow['staff_id'] ?? 0);
                $half = (int)($assignedRow['half'] ?? 1);
                if ($sid <= 0) {
                    continue;
                }
                if ($half === 2) {
                    $current_staff_ids_h2[$sid] = true;
                } else {
                    $current_staff_ids_h1[$sid] = true;
                }
            }
        } else {
            $stmtAssignedStaff = $conn->prepare("
                SELECT staff_id
                FROM match_staff_assignments
                WHERE match_id = ? AND team_id = ?
            ");
            $stmtAssignedStaff->execute([$challenge_id, $my_team_id]);
            foreach ($stmtAssignedStaff->fetchAll(PDO::FETCH_ASSOC) as $assignedRow) {
                $sid = (int)($assignedRow['staff_id'] ?? 0);
                if ($sid > 0) {
                    $current_staff_ids[$sid] = true;
                }
            }
        }
    }

    // Initial Filter: If user hasn't selected an event filter, force it to match the challenge event
    if ($filter_event === '' && !empty($challenge['sport_type'])) {
        $filter_event = $challenge['sport_type'];
    }

    $stmtHalfColumn = $conn->query("SHOW COLUMNS FROM lineups LIKE 'half'");
    $has_lineups_half_column = $stmtHalfColumn && $stmtHalfColumn->fetch(PDO::FETCH_ASSOC) !== false;

    $is_my_team_challenger = (int)($challenge['challenger_id'] ?? 0) === (int)$my_team_id;
    $uniform_choice_column = $is_my_team_challenger ? 'challenger_uniform_choices' : 'opponent_uniform_choices';

    $stmtUniformColumn = $conn->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'challenges'
          AND COLUMN_NAME = ?
    ");
    $stmtUniformColumn->execute([$uniform_choice_column]);
    $has_uniform_choice_column = ((int)$stmtUniformColumn->fetchColumn() > 0);

    $stmtTeamUniform = $conn->prepare("SELECT uniform_color FROM teams WHERE id = ?");
    $stmtTeamUniform->execute([$my_team_id]);
    $my_team_uniform_raw = trim((string)$stmtTeamUniform->fetchColumn());
    $uniform_options = parseUniformChoices($my_team_uniform_raw);

    if ($has_uniform_choice_column) {
        $stmtSavedUniform = $conn->prepare("SELECT {$uniform_choice_column} AS uniform_choices FROM challenges WHERE id = ?");
        $stmtSavedUniform->execute([$challenge_id]);
        $saved_uniform_raw = trim((string)$stmtSavedUniform->fetchColumn());
        $selected_uniform_choices = parseUniformChoices($saved_uniform_raw);
    }

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

    $suspended_players_map = [];
    $suspend_event_id = (int)($challenge['event_id'] ?? 0);
    $suspend_sport_type = trim((string)($challenge['sport_type'] ?? ''));
    $suspend_sport_type_norm = normalizeTextKey($suspend_sport_type);
    $stmtSuspended = $conn->prepare("SELECT event_id, player_id, sport_type, suspension_until
                                     FROM player_event_cards
                                     WHERE team_id = ?
                                       AND suspension_until IS NOT NULL
                                       AND suspension_until >= CURDATE()");
    $stmtSuspended->execute([$my_team_id]);
    $suspendedRows = $stmtSuspended->fetchAll(PDO::FETCH_ASSOC);

    foreach ($suspendedRows as $srow) {
        $pid = (int)($srow['player_id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $suspend_until = (string)($srow['suspension_until'] ?? '');
        if (!isset($suspended_players_map[$pid]) || $suspended_players_map[$pid] < $suspend_until) {
            $suspended_players_map[$pid] = $suspend_until;
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_uniform_choices = [];
    if (isset($_POST['uniform_choices']) && is_array($_POST['uniform_choices'])) {
        $valid_choice_map = [];
        foreach ($uniform_options as $choice) {
            $valid_choice_map[normalizeTextKey($choice)] = $choice;
        }

        foreach ($_POST['uniform_choices'] as $choice) {
            $normalized = normalizeTextKey($choice);
            if ($normalized !== '' && isset($valid_choice_map[$normalized])) {
                $selected_uniform_choices[] = $valid_choice_map[$normalized];
            }
        }

        $selected_uniform_choices = array_values(array_unique($selected_uniform_choices));
    }

    $submitted_staff_ids = [];
    $submitted_staff_ids_h1 = [];
    $submitted_staff_ids_h2 = [];
    if ($has_match_staff_half_column) {
        if (isset($_POST['assigned_staff_h1']) && is_array($_POST['assigned_staff_h1'])) {
            foreach ($_POST['assigned_staff_h1'] as $sid) {
                $sid = (int)$sid;
                if ($sid > 0) {
                    $submitted_staff_ids_h1[$sid] = true;
                }
            }
        }
        if (isset($_POST['assigned_staff_h2']) && is_array($_POST['assigned_staff_h2'])) {
            foreach ($_POST['assigned_staff_h2'] as $sid) {
                $sid = (int)$sid;
                if ($sid > 0) {
                    $submitted_staff_ids_h2[$sid] = true;
                }
            }
        }
    } else {
        if (isset($_POST['assigned_staff']) && is_array($_POST['assigned_staff'])) {
            foreach ($_POST['assigned_staff'] as $sid) {
                $sid = (int)$sid;
                if ($sid > 0) {
                    $submitted_staff_ids[$sid] = true;
                }
            }
        }
    }

    if (!$has_lineups_half_column) {
        $error_message = "Pembaruan database belum diterapkan. Jalankan migrations/migration_add_half_column_to_lineups.sql terlebih dahulu.";
    } else {
        try {
            $conn->beginTransaction();
            $challenge_sport_type_norm = normalizeTextKey($challenge['sport_type'] ?? '');
            $eligible_player_ids = [];
            foreach ($players as $p) {
                $pid = (int)($p['id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                $is_valid = normalizeTextKey($p['sport_type'] ?? '') === $challenge_sport_type_norm;
                $is_suspended = isset($suspended_players_map[$pid]);
                if ($is_valid && !$is_suspended) {
                    $eligible_player_ids[$pid] = true;
                }
            }

            // 1. Clear existing lineup for this match & team
            $stmtDelete = $conn->prepare("DELETE FROM lineups WHERE match_id = ? AND team_id = ?");
            $stmtDelete->execute([$challenge_id, $my_team_id]);

            $stmtInsert = $conn->prepare("INSERT INTO lineups (match_id, player_id, team_id, is_starting, position, half) VALUES (?, ?, ?, ?, ?, ?)");

            // 2. Insert new lineup - Babak 1
            if (isset($_POST['players_h1']) && is_array($_POST['players_h1'])) {
                foreach ($_POST['players_h1'] as $player_id) {
                    $player_id = (int)$player_id;
                    if (!isset($eligible_player_ids[$player_id])) {
                        continue;
                    }
                    $is_starting = isset($_POST['starters_h1'][$player_id]) ? 1 : 0;
                    $pos = '';
                    foreach($players as $p) { if($p['id'] == $player_id) { $pos = $p['position']; break; } }

                    $stmtInsert->execute([$challenge_id, $player_id, $my_team_id, $is_starting, $pos, 1]);
                }
            }

            // 3. Insert new lineup - Babak 2
            if (isset($_POST['players_h2']) && is_array($_POST['players_h2'])) {
                foreach ($_POST['players_h2'] as $player_id) {
                     $player_id = (int)$player_id;
                     if (!isset($eligible_player_ids[$player_id])) {
                        continue;
                     }
                     $is_starting = isset($_POST['starters_h2'][$player_id]) ? 1 : 0;
                     $pos = '';
                     foreach($players as $p) { if($p['id'] == $player_id) { $pos = $p['position']; break; } }

                     $stmtInsert->execute([$challenge_id, $player_id, $my_team_id, $is_starting, $pos, 2]);
                }
            }

            if ($has_uniform_choice_column) {
                $uniform_choice_value = !empty($selected_uniform_choices) ? implode(', ', $selected_uniform_choices) : null;
                $stmtUpdateUniform = $conn->prepare("UPDATE challenges SET {$uniform_choice_column} = ? WHERE id = ?");
                $stmtUpdateUniform->execute([$uniform_choice_value, $challenge_id]);
            }

            if ($has_match_staff_assignments_table) {
                $valid_staff_ids = [];
                foreach ($team_staffs as $staff_row) {
                    $sid = (int)($staff_row['id'] ?? 0);
                    if ($sid > 0) {
                        $valid_staff_ids[$sid] = [
                            'position' => trim((string)($staff_row['position'] ?? ''))
                        ];
                    }
                }

                $stmtDeleteStaff = $conn->prepare("DELETE FROM match_staff_assignments WHERE match_id = ? AND team_id = ?");
                $stmtDeleteStaff->execute([$challenge_id, $my_team_id]);

                if ($has_match_staff_half_column) {
                    $stmtInsertStaff = $conn->prepare("
                        INSERT INTO match_staff_assignments (match_id, staff_id, team_id, half, role, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    foreach (array_keys($submitted_staff_ids_h1) as $sid) {
                        if (!isset($valid_staff_ids[$sid])) {
                            continue;
                        }
                        $role = $valid_staff_ids[$sid]['position'] ?: null;
                        $created_by = $pelatih_id > 0 ? $pelatih_id : null;
                        $stmtInsertStaff->execute([$challenge_id, $sid, $my_team_id, 1, $role, $created_by]);
                    }
                    foreach (array_keys($submitted_staff_ids_h2) as $sid) {
                        if (!isset($valid_staff_ids[$sid])) {
                            continue;
                        }
                        $role = $valid_staff_ids[$sid]['position'] ?: null;
                        $created_by = $pelatih_id > 0 ? $pelatih_id : null;
                        $stmtInsertStaff->execute([$challenge_id, $sid, $my_team_id, 2, $role, $created_by]);
                    }
                } else {
                    $stmtInsertStaff = $conn->prepare("
                        INSERT INTO match_staff_assignments (match_id, staff_id, team_id, role, created_by)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    foreach (array_keys($submitted_staff_ids) as $sid) {
                        if (!isset($valid_staff_ids[$sid])) {
                            continue;
                        }
                        $role = $valid_staff_ids[$sid]['position'] ?: null;
                        $created_by = $pelatih_id > 0 ? $pelatih_id : null;
                        $stmtInsertStaff->execute([$challenge_id, $sid, $my_team_id, $role, $created_by]);
                    }
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

<style>
    :root {
        --heritage-bg: #f8f7f4;
        --heritage-card: #ffffff;
        --heritage-border: #e5e1da;
        --heritage-text: #1e1b4b;
        --heritage-text-muted: #6b7280;
        --heritage-accent: #064e3b;
        --heritage-gold: #b45309;
        --heritage-crimson: #991b1b;
        --font-display: 'Bricolage Grotesque', sans-serif;
        --font-body: 'Plus Jakarta Sans', sans-serif;
        --soft-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
        --glow-shadow: 0 0 40px rgba(6, 78, 59, 0.08);
    }

    .main {
        background: var(--heritage-bg) !important;
        background-image: radial-gradient(#e5e1da 0.5px, transparent 0.5px) !important;
        background-size: 24px 24px !important;
        color: var(--heritage-text);
        font-family: var(--font-body);
        padding: 40px !important;
    }

    .dashboard-container {
        max-width: 1200px;
        margin: 0 auto;
    }

    /* Editorial Hero */
    .dashboard-hero {
        margin-bottom: 32px;
        border-bottom: 2px solid var(--heritage-text);
        padding-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 20px;
    }

    .hero-label {
        color: var(--heritage-gold);
        font-family: var(--font-display);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-size: 0.9rem;
        margin-bottom: 8px;
        display: block;
    }

    .hero-title {
        font-family: var(--font-display);
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--heritage-text);
        margin: 0;
        line-height: 1;
        letter-spacing: -0.04em;
    }

    .hero-description {
        color: var(--heritage-text-muted);
        font-size: 1rem;
        line-height: 1.5;
        margin: 8px 0 0 0;
        max-width: 600px;
    }

    /* Match Context Card */
    .match-context-card {
        background: white;
        border: 1px solid var(--heritage-border);
        border-radius: 24px;
        padding: 24px;
        margin-bottom: 32px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: var(--soft-shadow);
        flex-wrap: wrap;
        gap: 20px;
    }

    .match-teams {
        display: flex;
        align-items: center;
        gap: 24px;
        flex-wrap: wrap;
    }

    .match-team-name {
        font-family: var(--font-display);
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--heritage-text);
    }

    .match-vs {
        font-family: var(--font-display);
        font-weight: 900;
        color: var(--heritage-gold);
        font-size: 1.2rem;
        background: var(--heritage-bg);
        padding: 8px 12px;
        border-radius: 12px;
    }

    .match-meta-badges {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .meta-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 99px;
        background: #f3f4f6;
        color: var(--heritage-text-muted);
        font-size: 0.85rem;
        font-weight: 600;
    }

    .meta-badge i {
        color: var(--heritage-text);
    }

    /* Cards */
    .heritage-card {
        background: var(--heritage-card);
        border: 1px solid var(--heritage-border);
        border-radius: 20px;
        padding: 24px;
        box-shadow: var(--soft-shadow);
        margin-bottom: 24px;
    }

    .card-header {
        margin-bottom: 20px;
        border-bottom: 1px solid var(--heritage-border);
        padding-bottom: 16px;
    }

    .card-title {
        font-family: var(--font-display);
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--heritage-text);
        margin: 0;
    }

    .card-subtitle {
        font-size: 0.9rem;
        color: var(--heritage-text-muted);
        margin: 4px 0 0 0;
    }

    /* Form Elements */
    .form-control {
        width: 100%;
        padding: 10px 14px;
        border-radius: 12px;
        border: 1px solid var(--heritage-border);
        font-family: var(--font-body);
        font-size: 0.95rem;
        transition: all 0.2s;
    }

    .form-control:focus {
        border-color: var(--heritage-gold);
        outline: none;
        box-shadow: 0 0 0 3px rgba(180, 83, 9, 0.08);
    }

    /* Custom Select (match schedule style) */
    .lineup-select-wrap {
        position: relative;
        width: 100%;
    }

    .lineup-custom-select {
        position: relative;
        z-index: 50;
    }

    .lineup-custom-select-trigger {
        width: 100%;
        height: 42px;
        background: #fdfcfb;
        border: 1px solid var(--heritage-border);
        border-radius: 12px;
        padding: 0 14px;
        font-family: var(--font-body);
        font-size: 0.95rem;
        color: var(--heritage-text);
        transition: all 0.2s ease;
        box-sizing: border-box;
        text-align: left;
        cursor: pointer;
        display: flex;
        align-items: center;
        position: relative;
    }

    .lineup-custom-select-trigger:focus,
    .lineup-custom-select.open .lineup-custom-select-trigger {
        outline: none;
        border-color: var(--heritage-gold);
        background: white;
        box-shadow: 0 0 0 3px rgba(180, 83, 9, 0.08);
    }

    .lineup-custom-select-label {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        min-width: 0;
    }

    .lineup-custom-select-label i {
        color: var(--heritage-text-muted);
        font-size: 0.95rem;
        flex: 0 0 auto;
    }

    .lineup-custom-select-text {
        display: block;
        width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .lineup-custom-select-trigger .select-icon-right {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--heritage-text-muted);
        transition: transform 0.2s ease;
    }

    .lineup-custom-select.open .lineup-custom-select-trigger .select-icon-right {
        transform: translateY(-50%) rotate(180deg);
    }

    .lineup-custom-select-menu {
        display: none;
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 8px);
        background: #ffffff;
        border: 1px solid var(--heritage-border);
        border-radius: 14px;
        box-shadow: 0 12px 30px rgba(0,0,0,0.1);
        padding: 8px;
        max-height: 240px;
        overflow-y: auto;
        z-index: 100;
    }

    .lineup-custom-select.open .lineup-custom-select-menu {
        display: block;
    }

    .lineup-custom-option {
        width: 100%;
        text-align: left;
        border: none;
        background: transparent;
        color: var(--heritage-text);
        font-size: 0.95rem;
        font-family: var(--font-body);
        font-weight: 500;
        border-radius: 10px;
        padding: 10px 12px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .lineup-custom-option:hover {
        background: var(--heritage-bg);
    }

    .lineup-custom-option.active {
        background: #fef3c7;
        color: var(--heritage-gold);
        font-weight: 700;
    }

    .btn-primary {
        background: var(--heritage-text);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary:hover {
        background: var(--heritage-gold);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(180, 83, 9, 0.28);
    }

    .btn-primary.btn-white {
        background: #ffffff;
        color: var(--heritage-text);
        border: 1px solid var(--heritage-border);
    }

    .btn-primary.btn-white:hover {
        background: var(--heritage-gold);
        color: #ffffff;
        border-color: var(--heritage-gold);
    }
    
    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-secondary {
        background: transparent;
        color: var(--heritage-text);
        border: 1px solid var(--heritage-border);
        padding: 10px 20px;
        border-radius: 12px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }

    .btn-secondary:hover {
        background: var(--heritage-gold);
        color: #ffffff;
        border-color: var(--heritage-gold);
    }

    /* Custom Checkbox Group (Uniforms & Staff) */
    .checkbox-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .checkbox-option {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        background: white;
        border: 1px solid var(--heritage-border);
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
        user-select: none;
    }

    .checkbox-option:hover {
        border-color: var(--heritage-text);
        background: #fafafa;
    }
    
    .checkbox-option:has(input:checked) {
        border-color: var(--heritage-accent);
        background: #f0fdf4;
    }

    .checkbox-option input:checked + span {
        font-weight: 700;
        color: var(--heritage-accent);
    }

    /* Tabs */
    .tabs-nav {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        background: #f3f4f6;
        padding: 6px;
        border-radius: 16px;
        width: fit-content;
    }

    .tab-btn {
        padding: 10px 24px;
        border-radius: 12px;
        border: none;
        background: transparent;
        color: var(--heritage-text-muted);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .tab-btn.active {
        background: white;
        color: var(--heritage-text);
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .tab-content.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Table */
    .table-container {
        overflow-x: auto;
    }

    .heritage-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .heritage-table th {
        text-align: left;
        padding: 16px;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--heritage-text-muted);
        border-bottom: 2px solid var(--heritage-border);
        font-weight: 700;
    }

    .heritage-table td {
        padding: 16px;
        border-bottom: 1px solid var(--heritage-border);
        vertical-align: middle;
        background: white;
    }

    .heritage-table tr:last-child td {
        border-bottom: none;
    }

    .heritage-table tr:hover td {
        background: #fafafa;
    }

    .player-jersey {
        display: inline-block;
        width: 28px;
        height: 28px;
        line-height: 28px;
        text-align: center;
        background: var(--heritage-text);
        color: white;
        border-radius: 50%;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .player-name {
        font-weight: 600;
        color: var(--heritage-text);
    }

    .player-pos {
        color: var(--heritage-text-muted);
        font-size: 0.9rem;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .status-ok { background: #d1fae5; color: #064e3b; }
    .status-bad { background: #fee2e2; color: #991b1b; }
    .status-suspend { background: #fef3c7; color: #92400e; }

    .alert {
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-success { background: #d1fae5; color: #064e3b; border: 1px solid #a7f3d0; }
    .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    
    /* Code Snippet Styles in Alert */
    code {
        background: rgba(0,0,0,0.06);
        padding: 2px 6px;
        border-radius: 4px;
        font-family: monospace;
        font-size: 0.9em;
    }

    /* Filter Grid */
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        align-items: end;
    }

    /* Static Save Bar */
    .save-bar {
        background: var(--heritage-text);
        color: white;
        padding: 24px 32px;
        border-radius: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: var(--soft-shadow);
        margin-top: 40px;
        border: 1px solid rgba(255,255,255,0.1);
    }

    @media (max-width: 768px) {
        .dashboard-hero {
            flex-direction: column;
            align-items: flex-start;
        }
        .match-context-card {
            flex-direction: column;
            text-align: center;
            gap: 16px;
        }
        .match-teams {
            flex-direction: column;
            gap: 12px;
        }
        .save-bar {
            flex-direction: column;
            gap: 12px;
            text-align: center;
        }
        .save-bar button {
            width: 100%;
            justify-content: center;
        }
        .tabs-nav {
            width: 100%;
            overflow-x: auto;
        }
    }
</style>

<div class="dashboard-container">
    <header class="dashboard-hero">
        <div class="hero-content">
            <span class="hero-label">Matchday Management</span>
            <h1 class="hero-title">Atur Lineup</h1>
            <p class="hero-description">Tentukan susunan pemain, staff, dan seragam untuk pertandingan ini.</p>
        </div>
        <div class="hero-actions">
            <a href="schedule.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Jadwal
            </a>
        </div>
    </header>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-circle-exclamation"></i>
            <span><?php echo $error_message; ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-circle-check"></i>
            <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
        </div>
    <?php endif; ?>

    <!-- Match Context -->
    <div class="match-context-card">
        <div class="match-teams">
            <span class="match-team-name"><?php echo htmlspecialchars($challenge['challenger_name'] ?? ''); ?></span>
            <span class="match-vs">VS</span>
            <span class="match-team-name"><?php echo htmlspecialchars($challenge['opponent_name'] ?? ''); ?></span>
        </div>
        <div class="match-meta-badges">
            <div class="meta-badge">
                <i class="fas fa-trophy"></i>
                <?php echo htmlspecialchars($challenge['event_name'] ?? ($challenge['sport_type'] ?? '-')); ?>
            </div>
            <div class="meta-badge">
                <i class="fas fa-hashtag"></i>
                Match #<?php echo (int)$challenge_id; ?>
            </div>
        </div>
    </div>

    <?php if (!$has_lineups_half_column): ?>
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation"></i>
            <span>Simpan lineup dinonaktifkan sampai migrasi database dijalankan: <code>migrations/migration_add_half_column_to_lineups.sql</code></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($suspended_players_map)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-user-slash"></i>
            <span><?php echo count($suspended_players_map); ?> pemain sedang suspend dan tidak bisa dipilih.</span>
        </div>
    <?php endif; ?>
    
    <?php if (!$has_match_staff_assignments_table): ?>
        <div class="alert alert-warning">
            <i class="fas fa-user-clock"></i>
            <span>Fitur assignment staff belum aktif. Jalankan migrasi: <code>migrations/migration_create_match_staff_assignments.sql</code></span>
        </div>
    <?php elseif (!$has_match_staff_half_column): ?>
        <div class="alert alert-warning">
            <i class="fas fa-user-clock"></i>
            <span>Assignment staff masih mode lama (tanpa babak). Jalankan migrasi: <code>migrations/migration_add_half_to_match_staff_assignments.sql</code></span>
        </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="heritage-card">
        <div class="card-header">
            <h3 class="card-title">Filter Pemain</h3>
            <p class="card-subtitle">Cari pemain berdasarkan posisi atau nama.</p>
        </div>
        <form method="GET" class="filter-grid">
            <input type="hidden" name="id" value="<?php echo (int)$challenge_id; ?>">
            
            <div>
                <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 6px;">Event</label>
                <select name="event" class="form-control" disabled style="background: #f3f4f6;">
                    <option selected><?php echo htmlspecialchars($challenge['sport_type'] ?? '-'); ?></option>
                </select>
                <input type="hidden" name="event" value="<?php echo htmlspecialchars($challenge['sport_type'] ?? ''); ?>">
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 6px;">Posisi</label>
                <div class="lineup-select-wrap">
                    <div class="lineup-custom-select" id="lineupPositionSelect">
                        <input type="hidden" name="position" id="lineupPositionValue" value="<?php echo htmlspecialchars($filter_position); ?>">
                        <button type="button" class="lineup-custom-select-trigger" id="lineupPositionTrigger" aria-expanded="false">
                            <span class="lineup-custom-select-label">
                                <i class="fas fa-clipboard-list"></i>
                                <span id="lineupPositionLabel" class="lineup-custom-select-text">
                                    <?php
                                    if ($filter_position !== '') {
                                        echo htmlspecialchars($position_options[$filter_position] ?? $filter_position);
                                    } else {
                                        echo 'Semua Posisi';
                                    }
                                    ?>
                                </span>
                            </span>
                            <i class="fas fa-chevron-down select-icon-right"></i>
                        </button>
                        <div class="lineup-custom-select-menu" id="lineupPositionMenu">
                            <button type="button" class="lineup-custom-option <?php echo $filter_position === '' ? 'active' : ''; ?>" data-value="">Semua Posisi</button>
                            <?php foreach ($position_options as $pos_value => $pos_label): ?>
                                <button type="button" class="lineup-custom-option <?php echo $filter_position === $pos_value ? 'active' : ''; ?>" data-value="<?php echo htmlspecialchars($pos_value); ?>">
                                    <?php echo htmlspecialchars($pos_label); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 6px;">Cari</label>
                <input type="text" name="q" class="form-control" placeholder="Nama atau No Punggung..." value="<?php echo htmlspecialchars($filter_search); ?>">
            </div>

            <div>
                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 42px;">
                    <i class="fas fa-filter"></i> Terapkan
                </button>
            </div>
        </form>
    </div>

    <form method="POST" action="">
        
        <!-- Uniforms & Staff Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; margin-bottom: 24px;">
            <!-- Uniforms -->
            <div class="heritage-card" style="margin-bottom: 0;">
                <div class="card-header">
                    <h3 class="card-title">Kostum Tim</h3>
                    <p class="card-subtitle">Pilih warna jersey yang digunakan.</p>
                </div>
                <?php if (!empty($uniform_options)): ?>
                    <div class="checkbox-grid">
                        <?php foreach ($uniform_options as $uniform_choice): ?>
                            <?php $is_checked = in_array($uniform_choice, $selected_uniform_choices, true); ?>
                            <label class="checkbox-option">
                                <input type="checkbox" name="uniform_choices[]" value="<?php echo htmlspecialchars($uniform_choice); ?>" <?php echo $is_checked ? 'checked' : ''; ?>>
                                <span><?php echo htmlspecialchars($uniform_choice); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Warna kostum belum diatur di data tim.</p>
                <?php endif; ?>
                <?php if (!$has_uniform_choice_column): ?>
                    <div style="margin-top: 12px; font-size: 0.8rem; color: var(--heritage-text-muted);">
                        Pilihan kostum tampil, tapi belum tersimpan permanen. Jalankan migrasi: <code>migrations/migration_add_uniform_choice_columns_to_challenges.sql</code>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Staff -->
            <div class="heritage-card" style="margin-bottom: 0;">
                <div class="card-header">
                    <h3 class="card-title">Official Team</h3>
                    <p class="card-subtitle">Staff yang bertugas di bench.</p>
                </div>
                
                <?php if ($has_match_staff_half_column): ?>
                    <div class="tabs-nav">
                        <button type="button" class="tab-btn active" onclick="openStaffTab(event, 'staff-half1')">Babak 1</button>
                        <button type="button" class="tab-btn" onclick="openStaffTab(event, 'staff-half2')">Babak 2</button>
                    </div>

                    <div id="staff-half1" class="tab-content active lineup-staff-tab-content">
                        <div class="checkbox-grid">
                            <?php foreach ($team_staffs as $staff_row): 
                                $sid = (int)$staff_row['id'];
                                $is_assigned = isset($current_staff_ids_h1[$sid]);
                            ?>
                                <label class="checkbox-option">
                                    <input type="checkbox" name="assigned_staff_h1[]" value="<?php echo $sid; ?>" <?php echo $is_assigned ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($staff_row['name']); ?> <small style="color: var(--heritage-text-muted);">(<?php echo htmlspecialchars($staff_row['position'] ?: '-'); ?>)</small></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="staff-half2" class="tab-content lineup-staff-tab-content">
                        <div class="checkbox-grid">
                            <?php foreach ($team_staffs as $staff_row): 
                                $sid = (int)$staff_row['id'];
                                $is_assigned = isset($current_staff_ids_h2[$sid]);
                            ?>
                                <label class="checkbox-option">
                                    <input type="checkbox" name="assigned_staff_h2[]" value="<?php echo $sid; ?>" <?php echo $is_assigned ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($staff_row['name']); ?> <small style="color: var(--heritage-text-muted);">(<?php echo htmlspecialchars($staff_row['position'] ?: '-'); ?>)</small></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="checkbox-grid">
                        <?php foreach ($team_staffs as $staff_row): 
                             $sid = (int)$staff_row['id'];
                             $is_assigned = isset($current_staff_ids[$sid]);
                        ?>
                            <label class="checkbox-option">
                                <input type="checkbox" name="assigned_staff[]" value="<?php echo $sid; ?>" <?php echo $is_assigned ? 'checked' : ''; ?>>
                                <span><?php echo htmlspecialchars($staff_row['name']); ?> <small style="color: var(--heritage-text-muted);">(<?php echo htmlspecialchars($staff_row['position'] ?: '-'); ?>)</small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lineup Table -->
        <div class="heritage-card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h3 class="card-title">Susunan Pemain</h3>
                    <p class="card-subtitle">Tentukan starter dan pemain cadangan.</p>
                </div>
                <div class="tabs-nav" style="margin: 0;">
                    <button type="button" class="tab-btn active" onclick="openTab(event, 'half1')">Babak 1</button>
                    <button type="button" class="tab-btn" onclick="openTab(event, 'half2')">Babak 2</button>
                </div>
            </div>

            <?php foreach (['half1' => 1, 'half2' => 2] as $tab_id => $half_num): 
                $current_lineup = ($half_num === 1) ? $current_lineup_h1 : $current_lineup_h2;
                $input_players = ($half_num === 1) ? 'players_h1[]' : 'players_h2[]';
                $input_starters = ($half_num === 1) ? 'starters_h1' : 'starters_h2';
                $check_class = ($half_num === 1) ? 'player-select-h1' : 'player-select-h2';
                $starter_class = ($half_num === 1) ? 'starter-select-h1' : 'starter-select-h2';
            ?>
                <div id="<?php echo $tab_id; ?>" class="tab-content lineup-tab-content <?php echo ($half_num === 1) ? 'active' : ''; ?>">
                    <div class="table-container">
                        <table class="heritage-table">
                            <thead>
                                <tr>
                                    <th width="50" style="text-align: center;">Main</th>
                                    <th width="50" style="text-align: center;">Starter</th>
                                    <th width="60">No</th>
                                    <th>Nama Pemain</th>
                                    <th>Posisi</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($players as $player): 
                                    $pid = (int)$player['id'];
                                    $suspend_until = $suspended_players_map[$pid] ?? '';
                                    $is_suspended = $suspend_until !== '';
                                    $is_selected = !$is_suspended && isset($current_lineup[$pid]);
                                    $is_starting = $is_selected && $current_lineup[$pid] == 1;
                                    $is_valid = (normalizeTextKey($player['sport_type'] ?? '') === normalizeTextKey($challenge['sport_type'] ?? ''));
                                    
                                    if (!$is_valid) $status_class = 'status-bad';
                                    elseif ($is_suspended) $status_class = 'status-suspend';
                                    else $status_class = 'status-ok';
                                ?>
                                    <tr style="<?php echo $is_suspended || !$is_valid ? 'background: #f9fafb; opacity: 0.7;' : ''; ?>">
                                        <td style="text-align: center;">
                                            <input type="checkbox" 
                                                   name="<?php echo $input_players; ?>" 
                                                   value="<?php echo $pid; ?>" 
                                                   class="<?php echo $check_class; ?>" 
                                                   data-id="<?php echo $pid; ?>"
                                                   <?php echo $is_selected ? 'checked' : ''; ?>
                                                   <?php echo !$is_valid || $is_suspended ? 'disabled' : ''; ?>
                                                   style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--heritage-accent);">
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="checkbox" 
                                                   name="<?php echo $input_starters; ?>[<?php echo $pid; ?>]" 
                                                   value="1" 
                                                   class="<?php echo $starter_class; ?>" 
                                                   id="starter-h<?php echo $half_num; ?>-<?php echo $pid; ?>"
                                                   <?php echo $is_starting ? 'checked' : ''; ?>
                                                   <?php echo !$is_selected ? 'disabled' : ''; ?>
                                                   style="width: 18px; height: 18px; cursor: pointer; accent-color: var(--heritage-gold);">
                                        </td>
                                        <td>
                                            <span class="player-jersey"><?php echo htmlspecialchars($player['jersey_number'] ?? '-'); ?></span>
                                        </td>
                                        <td>
                                            <div class="player-name"><?php echo htmlspecialchars($player['name']); ?></div>
                                        </td>
                                        <td>
                                            <div class="player-pos"><?php echo htmlspecialchars($player['position']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($is_suspended): ?>
                                                <span class="status-badge status-suspend">Suspend <?php echo date('d/m', strtotime($suspend_until)); ?></span>
                                            <?php elseif (!$is_valid): ?>
                                                <span class="status-badge status-bad">Beda Event</span>
                                            <?php else: ?>
                                                <span class="status-badge status-ok">Ready</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="save-bar">
            <div>
                <strong style="display: block; font-size: 1.1rem;">Siap Bertanding?</strong>
                <span style="font-size: 0.9rem; opacity: 0.8;">Pastikan formasi sudah sesuai sebelum kick-off.</span>
            </div>
            <button type="submit" class="btn-primary btn-white" style="padding: 12px 32px;" <?php echo !$has_lineups_half_column ? 'disabled' : ''; ?>>
                <i class="fas fa-save"></i> Simpan Lineup
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectRoot = document.getElementById('lineupPositionSelect');
    if (!selectRoot) return;

    const trigger = document.getElementById('lineupPositionTrigger');
    const menu = document.getElementById('lineupPositionMenu');
    const hiddenInput = document.getElementById('lineupPositionValue');
    const label = document.getElementById('lineupPositionLabel');
    const options = menu.querySelectorAll('.lineup-custom-option');

    function closeMenu() {
        selectRoot.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    }

    function openMenu() {
        selectRoot.classList.add('open');
        trigger.setAttribute('aria-expanded', 'true');
    }

    trigger.addEventListener('click', function() {
        if (selectRoot.classList.contains('open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    options.forEach(function(opt) {
        opt.addEventListener('click', function() {
            const value = opt.getAttribute('data-value') || '';
            hiddenInput.value = value;
            label.textContent = opt.textContent.trim();
            options.forEach(function(o) { o.classList.remove('active'); });
            opt.classList.add('active');
            closeMenu();
        });
    });

    document.addEventListener('click', function(e) {
        if (!selectRoot.contains(e.target)) {
            closeMenu();
        }
    });
});

function openTab(evt, tabName) {
    var tabContent = document.getElementsByClassName('lineup-tab-content');
    var container = evt.currentTarget.parentNode;
    var buttons = container.getElementsByClassName('tab-btn');

    for (var i = 0; i < tabContent.length; i++) {
        tabContent[i].classList.remove('active');
    }
    
    // Deactivate all buttons in this specific container
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove('active');
    }

    document.getElementById(tabName).classList.add('active');
    evt.currentTarget.classList.add('active');
}

function openStaffTab(evt, tabName) {
    var tabContent = document.getElementsByClassName('lineup-staff-tab-content');
    var container = evt.currentTarget.parentNode;
    var buttons = container.getElementsByClassName('tab-btn');
    
    for (var i = 0; i < tabContent.length; i++) {
        tabContent[i].classList.remove('active');
    }
    
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove('active');
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
<?php require_once 'includes/footer.php'; ?>
