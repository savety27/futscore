<?php
session_start();

// Load database config
$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

$event_helper_path = __DIR__ . '/../admin/includes/event_helpers.php';
if (file_exists($event_helper_path)) {
    require_once $event_helper_path;
}

if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'operator') {
    header("Location: ../login.php");
    exit;
}

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

if (!function_exists('adminHasColumn')) {
    function adminHasColumn(PDO $conn, $tableName, $columnName) {
        try {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tableName);
            if ($safeTable === '') {
                return false;
            }
            $quotedColumn = $conn->quote((string) $columnName);
            $stmt = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quotedColumn}");
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('adminHasTable')) {
    function adminHasTable(PDO $conn, $tableName) {
        try {
            $quotedTable = $conn->quote((string) $tableName);
            $stmt = $conn->query("SHOW TABLES LIKE {$quotedTable}");
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}


// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Operator';
$admin_email = $_SESSION['admin_email'] ?? '';
$current_page = 'challenge';

$operator_id = (int)($_SESSION['admin_id'] ?? 0);
$operator_event_id = (int)($_SESSION['event_id'] ?? 0);
$operator_event_name = 'Event Operator';
$operator_event_image = '';
$operator_event_locked = false;
$operator_event_is_active = true;

if ($operator_id > 0) {
    try {
        $stmtOperator = $conn->prepare("
            SELECT au.event_id, e.name AS event_name, e.image AS event_image,
                   COALESCE(e.is_active, 1) AS event_is_active
            FROM admin_users au
            LEFT JOIN events e ON e.id = au.event_id
            WHERE au.id = ?
            LIMIT 1
        ");
        $stmtOperator->execute([$operator_id]);
        $operator_row = $stmtOperator->fetch(PDO::FETCH_ASSOC);
        $operator_event_id = (int)($operator_row['event_id'] ?? $operator_event_id);
        $operator_event_name = trim((string)($operator_row['event_name'] ?? '')) !== '' ? (string)$operator_row['event_name'] : 'Event Operator';
        $operator_event_image = trim((string)($operator_row['event_image'] ?? ''));
        $operator_event_is_active = ((int)($operator_row['event_is_active'] ?? 1) === 1);
        $operator_event_locked = ($operator_event_id > 0);
        $_SESSION['event_id'] = $operator_event_id > 0 ? $operator_event_id : null;
    } catch (PDOException $e) {
        // keep defaults
    }
}

if ($operator_event_id > 0 && !$operator_event_is_active) {
    $_SESSION['error_message'] = 'Event operator sedang non-aktif. Mode hanya lihat data.';
    header("Location: challenge.php");
    exit;
}

$event_types = function_exists('getDynamicEventOptions') ? getDynamicEventOptions($conn) : [];
$active_events = [];
$active_event_ids = [];
$challenge_has_event_id = adminHasColumn($conn, 'challenges', 'event_id');
$challenge_has_match_official = adminHasColumn($conn, 'challenges', 'match_official');
$perangkat_official_names = [];
$perangkat_official_lookup = [];

// Get challenge ID
$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($challenge_id <= 0) {
    header("Location: challenge.php");
    exit;
}

// Initialize variables
$errors = [];
$challenge_data = null;

// Fetch challenge data
try {
    $operator_scope_where = '';
    $query_params = [$challenge_id];
    if ($challenge_has_event_id) {
        if ($operator_event_id > 0) {
            $operator_scope_where = " AND c.event_id = ?";
            $query_params[] = $operator_event_id;
        } else {
            $operator_scope_where = " AND 1=0";
        }
    }

    $stmt = $conn->prepare("
        SELECT c.*, 
        t1.name as challenger_name, t1.sport_type as challenger_sport,
        t2.name as opponent_name,
        l.name as venue_name, l.location as venue_location
        FROM challenges c
        LEFT JOIN teams t1 ON c.challenger_id = t1.id
        LEFT JOIN teams t2 ON c.opponent_id = t2.id
        LEFT JOIN venues l ON c.venue_id = l.id
        WHERE c.id = ?
        {$operator_scope_where}
    ");
    $stmt->execute($query_params);
    $challenge_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$challenge_data) {
        header("Location: challenge.php");
        exit;
    }
    
    // PERUBAHAN: Challenge bisa diedit walaupun sudah ada skor
    // Tidak ada validasi untuk cek apakah sudah ada skor
    
    // Split date and time
    $challenge_datetime = new DateTime($challenge_data['challenge_date']);
    $challenge_data['challenge_date_only'] = $challenge_datetime->format('Y-m-d');
    $challenge_data['challenge_time_only'] = $challenge_datetime->format('H:i');

    if (!isset($challenge_data['event_id'])) {
        $challenge_data['event_id'] = '';
    } elseif ((int)$challenge_data['event_id'] > 0) {
        $challenge_data['event_id'] = (string)((int)$challenge_data['event_id']);
    } else {
        $challenge_data['event_id'] = '';
    }
    if (!isset($challenge_data['match_official'])) {
        $challenge_data['match_official'] = '';
    }
    if ($operator_event_locked) {
        $challenge_data['event_id'] = (string)$operator_event_id;
    }

    if (adminHasTable($conn, 'events')) {
        $event_sql = "SELECT id, name FROM events WHERE 1=1 AND name IS NOT NULL AND name <> ''";
        if (adminHasColumn($conn, 'events', 'is_active')) {
            $event_sql .= " AND is_active = 1";
        }
        if (adminHasColumn($conn, 'events', 'registration_status')) {
            $event_sql .= " AND registration_status = 'open'";
        }
        if ($operator_event_id > 0) {
            $event_sql .= " AND id = " . (int)$operator_event_id;
        } else {
            $event_sql .= " AND 1=0";
        }
        $event_sql .= " ORDER BY created_at DESC, name ASC";

        $active_events_stmt = $conn->prepare($event_sql);
        $active_events_stmt->execute();
        $active_events = $active_events_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($active_events as $event_row) {
            $active_event_id = (int)($event_row['id'] ?? 0);
            if ($active_event_id > 0) {
                $active_event_ids[$active_event_id] = true;
            }
        }
    }
    
    // Fetch teams for dropdown
    $teams_stmt = $conn->prepare("SELECT id, name, sport_type FROM teams WHERE is_active = 1 ORDER BY name ASC");
    $teams_stmt->execute();
    $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

    $team_events_stmt = $conn->prepare("SELECT team_id, event_name FROM team_events ORDER BY team_id ASC");
    $team_events_stmt->execute();
    $team_events_rows = $team_events_stmt->fetchAll(PDO::FETCH_ASSOC);
    $team_events_map = [];
    foreach ($team_events_rows as $row) {
        $team_id = (int)($row['team_id'] ?? 0);
        $event_name = trim((string)($row['event_name'] ?? ''));
        if ($team_id <= 0 || $event_name === '') {
            continue;
        }
        if (!isset($team_events_map[$team_id])) {
            $team_events_map[$team_id] = [];
        }
        $team_events_map[$team_id][] = $event_name;
    }

    if (function_exists('mergeTeamPrimarySportsIntoEventsMap')) {
        mergeTeamPrimarySportsIntoEventsMap($teams, $team_events_map);
    }
    
    // Fetch venues for dropdown
    $venues_stmt = $conn->prepare("SELECT id, name, location FROM venues WHERE is_active = 1 ORDER BY name ASC");
    $venues_stmt->execute();
    $venues = $venues_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (adminHasTable($conn, 'perangkat')) {
        $official_sql = "SELECT p.name FROM perangkat p WHERE p.name IS NOT NULL AND p.name <> ''";
        if (adminHasColumn($conn, 'perangkat', 'is_active')) {
            $official_sql .= " AND p.is_active = 1";
        }
        $official_sql .= " ORDER BY p.name ASC";
        $official_stmt = $conn->query($official_sql);
        if ($official_stmt) {
            $seen_names = [];
            foreach ($official_stmt->fetchAll(PDO::FETCH_ASSOC) as $official_row) {
                $official_name = trim((string)($official_row['name'] ?? ''));
                if ($official_name === '') {
                    continue;
                }
                $name_key = function_exists('mb_strtolower')
                    ? mb_strtolower($official_name, 'UTF-8')
                    : strtolower($official_name);
                if (isset($seen_names[$name_key])) {
                    continue;
                }
                $seen_names[$name_key] = true;
                $perangkat_official_names[] = $official_name;
                $perangkat_official_lookup[$name_key] = true;
            }
        }
    }
    
} catch (PDOException $e) {
    die("Error fetching challenge data: " . $e->getMessage());
}

$default_expiry_seconds = 24 * 60 * 60;
$preserved_expiry_seconds = $default_expiry_seconds;

if (!empty($challenge_data['challenge_date']) && !empty($challenge_data['expiry_date'])) {
    try {
        $existing_challenge_datetime = new DateTime($challenge_data['challenge_date']);
        $existing_expiry_datetime = new DateTime($challenge_data['expiry_date']);
        $existing_offset_seconds = $existing_challenge_datetime->getTimestamp() - $existing_expiry_datetime->getTimestamp();

        if ($existing_offset_seconds > 0) {
            $preserved_expiry_seconds = $existing_offset_seconds;
        }
    } catch (Exception $e) {
        $preserved_expiry_seconds = $default_expiry_seconds;
    }
}

$preserved_hours = floor($preserved_expiry_seconds / 3600);
$preserved_minutes = floor(($preserved_expiry_seconds % 3600) / 60);

if ($preserved_minutes > 0) {
    $preserved_expiry_label = $preserved_hours . ' jam ' . $preserved_minutes . ' menit';
} else {
    $preserved_expiry_label = $preserved_hours . ' jam';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $posted_event_id = $operator_event_id > 0 ? $operator_event_id : (int) trim($_POST['event_id'] ?? '0');

    // Get and sanitize form data
    $form_data = [
        'event_id' => $posted_event_id > 0 ? (string)$posted_event_id : '',
        'challenger_id' => trim($_POST['challenger_id'] ?? ''),
        'opponent_id' => trim($_POST['opponent_id'] ?? ''),
        'venue_id' => trim($_POST['venue_id'] ?? ''),
        'challenge_date' => trim($_POST['challenge_date'] ?? ''),
        'challenge_time' => trim($_POST['challenge_time'] ?? '18:00'),
        'sport_type' => in_array(trim($_POST['sport_type'] ?? ''), $event_types, true) ? trim($_POST['sport_type'] ?? '') : '',
        'match_official' => trim($_POST['match_official'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'status' => trim($_POST['status'] ?? 'open')
    ];
    
    // Validation
    if (empty($form_data['challenger_id'])) {
        $errors['challenger_id'] = "Challenger harus dipilih";
    }
    
    if (empty($form_data['opponent_id'])) {
        $errors['opponent_id'] = "Opponent harus dipilih";
    }

    if ($operator_event_id <= 0 || empty($form_data['event_id'])) {
        $errors['event_id'] = "Akun operator belum terhubung ke event aktif";
    } elseif (!isset($active_event_ids[(int)$form_data['event_id']])) {
        $errors['event_id'] = "Event yang dipilih tidak valid atau tidak aktif";
    }
    
    if ($form_data['challenger_id'] == $form_data['opponent_id']) {
        $errors['opponent_id'] = "Challenger dan Opponent tidak boleh sama";
    }
    
    if (empty($form_data['venue_id'])) {
        $errors['venue_id'] = "Venue harus dipilih";
    }
    
    if (empty($form_data['challenge_date'])) {
        $errors['challenge_date'] = "Tanggal challenge harus diisi";
    }
    
    if (empty($form_data['challenge_time'])) {
        $errors['challenge_time'] = "Waktu challenge harus diisi";
    }
    
    if (empty($form_data['sport_type'])) {
        $errors['sport_type'] = "Kategori harus dipilih";
    }

    if ($form_data['match_official'] !== '') {
        $official_name_key = function_exists('mb_strtolower')
            ? mb_strtolower($form_data['match_official'], 'UTF-8')
            : strtolower($form_data['match_official']);
        if (!isset($perangkat_official_lookup[$official_name_key])) {
            $errors['match_official'] = "Wasit/Pengawas harus dipilih dari daftar perangkat aktif";
        }
    }

    if (empty($errors['sport_type']) && !empty($form_data['challenger_id'])) {
        $challenger_events = $team_events_map[(int)$form_data['challenger_id']] ?? [];
        if (!in_array($form_data['sport_type'], $challenger_events, true)) {
            $errors['challenger_id'] = "Challenger tidak terdaftar pada kategori yang dipilih";
        }
    }

    if (empty($errors['sport_type']) && !empty($form_data['opponent_id'])) {
        $opponent_events = $team_events_map[(int)$form_data['opponent_id']] ?? [];
        if (!in_array($form_data['sport_type'], $opponent_events, true)) {
            $errors['opponent_id'] = "Opponent tidak terdaftar pada kategori yang dipilih";
        }
    }
    
    // Calculate expiry date using preserved offset
    $challenge_datetime = $form_data['challenge_date'] . ' ' . $form_data['challenge_time'] . ':00';
    $challenge_timestamp = strtotime($challenge_datetime);
    $expiry_datetime = null;

    if ($challenge_timestamp === false) {
        if (!isset($errors['challenge_date']) && !isset($errors['challenge_time'])) {
            $errors['challenge_date'] = "Tanggal atau waktu challenge tidak valid";
        }
    } else {
        $expiry_datetime = date('Y-m-d H:i:s', $challenge_timestamp - $preserved_expiry_seconds);
    }
    
    // If no errors, update database
    if (empty($errors)) {
        try {
            if ($challenge_has_event_id && $challenge_has_match_official) {
                $stmt = $conn->prepare("
                    UPDATE challenges SET
                        challenger_id = ?,
                        opponent_id = ?,
                        venue_id = ?,
                        challenge_date = ?,
                        expiry_date = ?,
                        event_id = ?,
                        sport_type = ?,
                        match_official = ?,
                        notes = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $form_data['challenger_id'],
                    $form_data['opponent_id'],
                    $form_data['venue_id'],
                    $challenge_datetime,
                    $expiry_datetime,
                    (int)$form_data['event_id'],
                    $form_data['sport_type'],
                    $form_data['match_official'],
                    $form_data['notes'],
                    $form_data['status'],
                    $challenge_id
                ]);
            } elseif ($challenge_has_event_id) {
                $stmt = $conn->prepare("
                    UPDATE challenges SET
                        challenger_id = ?,
                        opponent_id = ?,
                        venue_id = ?,
                        challenge_date = ?,
                        expiry_date = ?,
                        event_id = ?,
                        sport_type = ?,
                        notes = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $form_data['challenger_id'],
                    $form_data['opponent_id'],
                    $form_data['venue_id'],
                    $challenge_datetime,
                    $expiry_datetime,
                    (int)$form_data['event_id'],
                    $form_data['sport_type'],
                    $form_data['notes'],
                    $form_data['status'],
                    $challenge_id
                ]);
            } elseif ($challenge_has_match_official) {
                $stmt = $conn->prepare("
                    UPDATE challenges SET
                        challenger_id = ?,
                        opponent_id = ?,
                        venue_id = ?,
                        challenge_date = ?,
                        expiry_date = ?,
                        sport_type = ?,
                        match_official = ?,
                        notes = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $form_data['challenger_id'],
                    $form_data['opponent_id'],
                    $form_data['venue_id'],
                    $challenge_datetime,
                    $expiry_datetime,
                    $form_data['sport_type'],
                    $form_data['match_official'],
                    $form_data['notes'],
                    $form_data['status'],
                    $challenge_id
                ]);
            } else {
                $stmt = $conn->prepare("
                    UPDATE challenges SET
                        challenger_id = ?,
                        opponent_id = ?,
                        venue_id = ?,
                        challenge_date = ?,
                        expiry_date = ?,
                        sport_type = ?,
                        notes = ?,
                        status = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                $stmt->execute([
                    $form_data['challenger_id'],
                    $form_data['opponent_id'],
                    $form_data['venue_id'],
                    $challenge_datetime,
                    $expiry_datetime,
                    $form_data['sport_type'],
                    $form_data['notes'],
                    $form_data['status'],
                    $challenge_id
                ]);
            }
            
            $_SESSION['success_message'] = "Challenge berhasil diperbarui!";
            header("Location: challenge.php");
            exit;
            
        } catch (PDOException $e) {
            $errors['database'] = "Gagal memperbarui data: " . $e->getMessage();
        }
    } else {
        // Update challenge_data with new form data for display
        $challenge_data = array_merge($challenge_data, $form_data);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Challenge - Futscore</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../pelatih/css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../pelatih/css/style.css'); ?>">
<link rel="stylesheet" href="css/challenge.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/challenge.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<link rel="stylesheet" href="../css/challenge_official_combobox.css?v=<?php echo (int) @filemtime(__DIR__ . '/../css/challenge_official_combobox.css'); ?>">
<style>
/* Heritage Form Customizations */
.form-container {
    background: var(--heritage-card);
    border: 1px solid var(--heritage-border);
    border-radius: 28px;
    padding: 40px;
    margin-bottom: 40px;
    box-shadow: var(--soft-shadow);
}

.form-section {
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 1px solid var(--heritage-border);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-family: var(--font-display);
    font-size: 1.25rem;
    font-weight: 800;
    color: var(--heritage-text);
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-title i {
    color: var(--heritage-gold);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
}

.form-group {
    margin-bottom: 24px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--heritage-text-muted);
}

.required {
    color: var(--heritage-crimson);
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 14px 20px;
    border: 1px solid var(--heritage-border);
    border-radius: 16px;
    font-family: var(--font-body);
    font-size: 1rem;
    color: var(--heritage-text);
    background: #fdfcfb;
    transition: all 0.3s ease;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--heritage-gold);
    background: white;
    box-shadow: 0 0 0 4px rgba(180, 83, 9, 0.05);
}

.form-textarea {
    resize: vertical;
    min-height: 120px;
}

/* VS Display Redesign */
.vs-display {
    text-align: center;
    padding: 40px;
    background: var(--heritage-bg);
    border: 1px solid var(--heritage-border);
    border-radius: 28px;
    margin: 30px 0;
    position: relative;
    overflow: hidden;
}

.vs-display::before {
    content: "MATCHUP";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-family: var(--font-display);
    font-size: 6rem;
    font-weight: 900;
    color: rgba(0,0,0,0.02);
    z-index: 0;
    white-space: nowrap;
}

.vs-title {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 0.8rem;
    color: var(--heritage-gold);
    letter-spacing: 0.2em;
    margin-bottom: 25px;
    position: relative;
    z-index: 1;
}

.team-vs-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 40px;
    position: relative;
    z-index: 1;
}

.team-box {
    flex: 1;
    text-align: center;
}

.team-box h4 {
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--heritage-text);
    margin: 0;
}

.vs-symbol {
    font-family: var(--font-display);
    font-size: 2.5rem;
    font-weight: 900;
    color: var(--heritage-gold);
    font-style: italic;
    background: var(--heritage-text);
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 20px rgba(30, 27, 75, 0.2);
}

/* Status Badge Large */
.status-badge-large {
    padding: 8px 16px;
    border-radius: 100px;
    font-family: var(--font-display);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 16px;
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid var(--heritage-border);
}

.error {
    color: var(--heritage-crimson);
    font-size: 0.8rem;
    font-weight: 600;
    margin-top: 6px;
    display: block;
}

.is-invalid {
    border-color: var(--heritage-crimson) !important;
}

@media screen and (max-width: 768px) {
    .form-grid { grid-template-columns: 1fr; }
    .team-vs-container { flex-direction: column; gap: 20px; }
    .vs-symbol { width: 50px; height: 50px; font-size: 1.75rem; }
    .vs-display::before { font-size: 4rem; }
}
</style>
</head>
<body>

<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar reveal">
            <div class="greeting">
                <h1>Edit Challenge 🏆</h1>
                <p>Perbarui challenge: <?php echo htmlspecialchars($challenge_data['challenge_code'] ?? ''); ?></p>
            </div>
            
            <div class="user-actions">
                <a href="../operator/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Keluar
                </a>
            </div>
        </div>

        <!-- EDITORIAL HEADER -->
        <header class="dashboard-hero reveal d-1">
            <div class="hero-content">
                <span class="hero-label">Manajemen Pertandingan</span>
                <h1 class="hero-title">Perbarui Challenge</h1>
                <p class="hero-description">Perbarui detail tantangan, lokasi, waktu, dan perangkat pertandingan secara real-time.</p>
            </div>
            <div class="hero-actions">
                <a href="challenge.php" class="btn-premium btn-export" style="margin-right: 12px;">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <span class="status-badge-large status-<?php echo strtolower($challenge_data['status']); ?>">
                    <i class="fas fa-circle" style="font-size: 8px;"></i>
                    <?php echo htmlspecialchars($challenge_data['status'] ?? ''); ?>
                </span>
            </div>
        </header>

        <!-- ERROR MESSAGES -->
        <?php if (isset($errors['database'])): ?>
        <div class="alert alert-danger reveal d-2">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $errors['database']; ?></span>
        </div>
        <?php endif; ?>

        <!-- EDIT CHALLENGE FORM -->
        <div class="form-container reveal d-2">
            <form method="POST" action="" id="challengeForm">
                <input type="hidden" name="id" value="<?php echo $challenge_id; ?>">
                
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-users"></i>
                        Pilih Team
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="challenger_id">
                                Challenger Team <span class="required">*</span>
                            </label>
                            <select id="challenger_id" name="challenger_id" 
                                    class="form-select <?php echo isset($errors['challenger_id']) ? 'is-invalid' : ''; ?>" 
                                    required>
                                <option value="">Pilih Challenger Team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>" 
                                            data-sport="<?php echo htmlspecialchars($team['sport_type'] ?? ''); ?>"
                                            <?php echo $challenge_data['challenger_id'] == $team['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team['name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['challenger_id'])): ?>
                                <span class="error"><?php echo $errors['challenger_id']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="opponent_id">
                                Opponent Team <span class="required">*</span>
                            </label>
                            <select id="opponent_id" name="opponent_id" 
                                    class="form-select <?php echo isset($errors['opponent_id']) ? 'is-invalid' : ''; ?>" 
                                    required>
                                <option value="">Pilih Opponent Team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>"
                                            <?php echo $challenge_data['opponent_id'] == $team['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team['name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['opponent_id'])): ?>
                                <span class="error"><?php echo $errors['opponent_id']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- VS Display -->
                    <div class="vs-display">
                        <div class="vs-title">MATCH PREVIEW</div>
                        <div class="team-vs-container">
                            <div class="team-box" id="challengerBox">
                                <h4><?php echo htmlspecialchars($challenge_data['challenger_name'] ?? ''); ?></h4>
                            </div>
                            <div class="vs-symbol">VS</div>
                            <div class="team-box" id="opponentBox">
                                <h4><?php echo htmlspecialchars($challenge_data['opponent_name'] ?? 'TBD'); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Detail Pertandingan
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="status">
                                Status Challenge
                            </label>
                            <select id="status" name="status" class="form-select">
                                <option value="open" <?php echo $challenge_data['status'] == 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="accepted" <?php echo $challenge_data['status'] == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                <option value="rejected" <?php echo $challenge_data['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="expired" <?php echo $challenge_data['status'] == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <?php if ($challenge_data['challenger_score'] !== null && $challenge_data['opponent_score'] !== null): ?>
                                <option value="completed" <?php echo $challenge_data['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="event_id">
                                Event Aktif <span class="required">*</span>
                            </label>
                            <select id="event_id_display" class="form-select" disabled>
                                <?php if ($operator_event_locked): ?>
                                    <option value="<?php echo (int)$operator_event_id; ?>" selected>
                                        <?php echo htmlspecialchars($operator_event_name); ?>
                                    </option>
                                <?php else: ?>
                                    <option value="">Akun operator belum terhubung ke event</option>
                                <?php endif; ?>
                            </select>
                            <input type="hidden" name="event_id" id="event_id" value="<?php echo htmlspecialchars($challenge_data['event_id'] ?? ''); ?>">
                            <small style="color:var(--heritage-text-muted); display:block; margin-top:5px; font-size: 0.75rem;">
                                Event dikunci otomatis sesuai akun operator.
                            </small>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="venue_id">
                                Venue/Lokasi <span class="required">*</span>
                            </label>
                            <select id="venue_id" name="venue_id" 
                                    class="form-select <?php echo isset($errors['venue_id']) ? 'is-invalid' : ''; ?>" 
                                    required>
                                <option value="">Pilih Venue</option>
                                <?php foreach ($venues as $venue): ?>
                                    <option value="<?php echo $venue['id']; ?>"
                                            <?php echo $challenge_data['venue_id'] == $venue['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($venue['name'] ?? ''); ?> (<?php echo htmlspecialchars($venue['location'] ?? ''); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['venue_id'])): ?>
                                <span class="error"><?php echo $errors['venue_id']; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="sport_type">
                                Kategori <span class="required">*</span>
                            </label>
                            <select id="sport_type" name="sport_type" 
                                    class="form-select <?php echo isset($errors['sport_type']) ? 'is-invalid' : ''; ?>" 
                                    required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($event_types as $event_option): ?>
                                    <option value="<?php echo htmlspecialchars($event_option); ?>" <?php echo $challenge_data['sport_type'] == $event_option ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['sport_type'])): ?>
                                <span class="error"><?php echo $errors['sport_type']; ?></span>
                            <?php endif; ?>
                            <span class="error" id="teamCategoryWarning" style="display: none;"></span>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="challenge_date">
                                Tanggal Challenge <span class="required">*</span>
                            </label>
                            <input type="date" 
                                   id="challenge_date" 
                                   name="challenge_date" 
                                   class="form-input <?php echo isset($errors['challenge_date']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($challenge_data['challenge_date_only']); ?>"
                                   min="<?php echo date('Y-m-d'); ?>"
                                   required>
                            <?php if (isset($errors['challenge_date'])): ?>
                                <span class="error"><?php echo $errors['challenge_date']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="challenge_time">
                                Waktu Challenge <span class="required">*</span>
                            </label>
                            <input type="time" 
                                   id="challenge_time" 
                                   name="challenge_time" 
                                   class="form-input <?php echo isset($errors['challenge_time']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($challenge_data['challenge_time_only']); ?>"
                                   required>
                            <?php if (isset($errors['challenge_time'])): ?>
                                <span class="error"><?php echo $errors['challenge_time']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-grid">
                         <div class="form-group">
                            <label class="form-label" for="match_official_search">
                                Wasit/Pengawas
                            </label>
                            <div class="official-combobox" data-official-combobox>
                                <input type="text"
                                       id="match_official_search"
                                       class="form-input official-combobox-input"
                                       placeholder="Cari dan pilih wasit/pengawas...">
                                <select id="match_official"
                                        name="match_official"
                                        class="form-select official-combobox-native <?php echo isset($errors['match_official']) ? 'is-invalid' : ''; ?>">
                                    <option value="">Pilih Wasit/Pengawas</option>
                                    <?php foreach ($perangkat_official_names as $official_name): ?>
                                        <option value="<?php echo htmlspecialchars($official_name); ?>" <?php echo ($challenge_data['match_official'] ?? '') === $official_name ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($official_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if (isset($errors['match_official'])): ?>
                                <span class="error"><?php echo $errors['match_official']; ?></span>
                            <?php endif; ?>
                            <small style="color:var(--heritage-text-muted); font-size: 0.75rem;">Daftar diambil dari data Perangkat aktif.</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="expiry_info">
                                Challenge Expiry
                            </label>
                            <input type="text" id="expiry_info" class="form-input" value="Diatur otomatis oleh sistem" readonly style="background: #f3f4f6;">
                            <small style="color:var(--heritage-text-muted); font-size: 0.75rem;">Offset saat ini: <?php echo htmlspecialchars($preserved_expiry_label); ?> sebelum pertandingan.</small>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" for="notes">
                            Catatan Tambahan
                        </label>
                        <textarea id="notes" name="notes" class="form-textarea" 
                                  placeholder="Masukkan catatan atau informasi tambahan..."><?php echo htmlspecialchars($challenge_data['notes'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="reset" class="btn-premium btn-export">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button type="submit" class="btn-premium btn-add">
                        <i class="fas fa-save"></i> Update Challenge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="../js/challenge_official_search.js?v=<?php echo (int) @filemtime(__DIR__ . '/../js/challenge_official_search.js'); ?>"></script>
<script>
const teamsData = <?php echo json_encode($teams); ?>;
const teamEventsMap = <?php echo json_encode($team_events_map ?? []); ?>;

document.addEventListener('DOMContentLoaded', function() {
    function updateVSDisplay() {
        const challengerId = document.getElementById('challenger_id').value;
        const opponentId = document.getElementById('opponent_id').value;
        const selectedCategory = document.getElementById('sport_type').value;
        const challengerBox = document.getElementById('challengerBox');
        const opponentBox = document.getElementById('opponentBox');
        const teamCategoryWarning = document.getElementById('teamCategoryWarning');

        const challenger = teamsData.find(team => team.id == challengerId);
        const opponent = teamsData.find(team => team.id == opponentId);

        if (challengerBox && challenger) {
            challengerBox.innerHTML = `<h4>${challenger.name}</h4>`;
        }
        if (opponentBox && opponent) {
            opponentBox.innerHTML = `<h4>${opponent.name}</h4>`;
        }

        if (teamCategoryWarning) {
            const warningMessages = [];
            if (selectedCategory && challengerId) {
                const challengerEvents = teamEventsMap[challengerId] || [];
                if (!challengerEvents.includes(selectedCategory)) {
                    warningMessages.push('Challenger tidak terdaftar di kategori yang dipilih.');
                }
            }
            if (selectedCategory && opponentId) {
                const opponentEvents = teamEventsMap[opponentId] || [];
                if (!opponentEvents.includes(selectedCategory)) {
                    warningMessages.push('Opponent tidak terdaftar di kategori yang dipilih.');
                }
            }

            if (warningMessages.length > 0) {
                teamCategoryWarning.style.display = 'block';
                teamCategoryWarning.textContent = warningMessages.join(' ');
            } else {
                teamCategoryWarning.style.display = 'none';
                teamCategoryWarning.textContent = '';
            }
        }
    }

    document.getElementById('challenger_id').addEventListener('change', updateVSDisplay);
    document.getElementById('opponent_id').addEventListener('change', updateVSDisplay);
    document.getElementById('sport_type').addEventListener('change', updateVSDisplay);
    updateVSDisplay();
    
    if (typeof initOfficialSearch === 'function') {
        initOfficialSearch('match_official', 'match_official_search');
    } else {
        console.warn('initOfficialSearch is not available');
    }
    
    // Form Validation
    const form = document.getElementById('challengeForm');
    form.addEventListener('submit', function(e) {
        const challengerId = document.getElementById('challenger_id').value;
        const opponentId = document.getElementById('opponent_id').value;
        const eventId = document.getElementById('event_id').value;
        const venueId = document.getElementById('venue_id').value;
        const challengeDate = document.getElementById('challenge_date').value;
        const challengeTime = document.getElementById('challenge_time').value;
        const sportType = document.getElementById('sport_type').value;
        
        if (!challengerId || !opponentId || !eventId || !venueId || !challengeDate || !challengeTime || !sportType) {
            e.preventDefault();
            toastr.error('Harap isi semua field yang wajib diisi (*)');
            return;
        }
        
        if (challengerId === opponentId) {
            e.preventDefault();
            toastr.error('Challenger dan Opponent tidak boleh sama');
            return;
        }

        const challengerEvents = teamEventsMap[challengerId] || [];
        if (!challengerEvents.includes(sportType)) {
            e.preventDefault();
            toastr.error('Challenger tidak terdaftar pada kategori yang dipilih');
            return;
        }

        const opponentEvents = teamEventsMap[opponentId] || [];
        if (!opponentEvents.includes(sportType)) {
            e.preventDefault();
            toastr.error('Opponent tidak terdaftar pada kategori yang dipilih');
            return;
        }
        
        // Check if date is not in the past
        const selectedDate = new Date(challengeDate + 'T' + challengeTime);
        const now = new Date();
        
        if (selectedDate < now) {
            e.preventDefault();
            toastr.error('Tanggal dan waktu challenge tidak boleh di masa lalu');
            return;
        }
    });
    
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('challenge_date').setAttribute('min', today);
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>