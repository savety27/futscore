<?php
session_start();

// Load database config
$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
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

if (!function_exists('countByColumnValue')) {
    function countByColumnValue(PDO $conn, string $table, string $column, int $value): int
    {
        if (!adminHasTable($conn, $table) || !adminHasColumn($conn, $table, $column)) {
            return 0;
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
        $stmt->execute([$value]);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('getChallengeDependencySources')) {
    function getChallengeDependencySources(PDO $conn, int $challengeId): array
    {
        $usageMap = [
            ['table' => 'lineups', 'column' => 'match_id', 'label' => 'lineup'],
            ['table' => 'goals', 'column' => 'match_id', 'label' => 'goal'],
            ['table' => 'match_stats', 'column' => 'match_id', 'label' => 'match_stats'],
            ['table' => 'match_staff_assignments', 'column' => 'match_id', 'label' => 'match_staff'],
            ['table' => 'predictions', 'column' => 'match_id', 'label' => 'prediction']
        ];

        $sources = [];
        foreach ($usageMap as $usage) {
            $count = countByColumnValue($conn, $usage['table'], $usage['column'], $challengeId);
            if ($count > 0) {
                $sources[] = $usage['label'];
            }
        }

        $bracketColumns = ['sf1_challenge_id', 'sf2_challenge_id', 'final_challenge_id', 'third_challenge_id'];
        foreach ($bracketColumns as $column) {
            $count = countByColumnValue($conn, 'event_brackets', $column, $challengeId);
            if ($count > 0) {
                $sources[] = 'event_bracket';
                break;
            }
        }

        return array_values(array_unique($sources));
    }
}

$challenge_has_event_id = adminHasColumn($conn, 'challenges', 'event_id');
$events_table_exists = adminHasTable($conn, 'events');
$can_join_event_name = $challenge_has_event_id && $events_table_exists;


// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Operator';
$admin_email = $_SESSION['admin_email'] ?? '';
$current_page = 'challenge';

$operator_id = (int)($_SESSION['admin_id'] ?? 0);
$operator_event_id = (int)($_SESSION['event_id'] ?? 0);
$operator_event_name = 'Event Operator';
$operator_event_image = '';
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
        $_SESSION['event_id'] = $operator_event_id > 0 ? $operator_event_id : null;
    } catch (PDOException $e) {
        // keep defaults
    }
}

$operator_read_only = ($operator_event_id > 0 && !$operator_event_is_active);


// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_event_id = $operator_event_id;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$event_filter_options = [];

if ($events_table_exists) {
    try {
        $events_stmt = $conn->prepare("SELECT id, name FROM events WHERE id = ? AND name IS NOT NULL AND name <> '' LIMIT 1");
        $events_stmt->execute([$operator_event_id]);
        $event_filter_options = $events_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $event_filter_options = [];
    }
}

// Query untuk mengambil data challenges dengan join ke teams
$event_select = $can_join_event_name ? "e.name as event_name," : "NULL as event_name,";
$event_join = $can_join_event_name ? "LEFT JOIN events e ON c.event_id = e.id" : "";

$base_query = "SELECT c.*,
              {$event_select}
              t1.name as challenger_name, t1.logo as challenger_logo, t1.sport_type as challenger_sport,
              t2.name as opponent_name, t2.logo as opponent_logo,
              v.name as venue_name, v.location as venue_location
              FROM challenges c
              {$event_join}
              LEFT JOIN teams t1 ON c.challenger_id = t1.id
              LEFT JOIN teams t2 ON c.opponent_id = t2.id
              LEFT JOIN venues v ON c.venue_id = v.id
              WHERE 1=1";

$count_query = "SELECT COUNT(*) as total 
                FROM challenges c
                WHERE 1=1";
$base_params = [];
$count_params = [];

// Handle search condition
if (!empty($search)) {
    $search_term = "%{$search}%";
    if ($can_join_event_name) {
        $base_query .= " AND (c.challenge_code LIKE ? OR c.status LIKE ? OR c.sport_type LIKE ? OR e.name LIKE ? OR t1.name LIKE ? OR t2.name LIKE ?)";
        $count_query .= " AND (c.challenge_code LIKE ? OR c.status LIKE ? OR c.sport_type LIKE ? OR
                         EXISTS (SELECT 1 FROM events e2 WHERE e2.id = c.event_id AND e2.name LIKE ?) OR
                         EXISTS (SELECT 1 FROM teams t WHERE t.id = c.challenger_id AND t.name LIKE ?) OR
                         EXISTS (SELECT 1 FROM teams t WHERE t.id = c.opponent_id AND t.name LIKE ?))";
        $base_params = array_merge($base_params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
        $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
    } else {
        $base_query .= " AND (c.challenge_code LIKE ? OR c.status LIKE ? OR c.sport_type LIKE ? OR t1.name LIKE ? OR t2.name LIKE ?)";
        $count_query .= " AND (c.challenge_code LIKE ? OR c.status LIKE ? OR c.sport_type LIKE ? OR
                         EXISTS (SELECT 1 FROM teams t WHERE t.id = c.challenger_id AND t.name LIKE ?) OR
                         EXISTS (SELECT 1 FROM teams t WHERE t.id = c.opponent_id AND t.name LIKE ?))";
        $base_params = array_merge($base_params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
        $count_params = array_merge($count_params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    }
}

if ($can_join_event_name) {
    if ($selected_event_id > 0) {
        $base_query .= " AND c.event_id = ?";
        $count_query .= " AND c.event_id = ?";
        $base_params[] = $selected_event_id;
        $count_params[] = $selected_event_id;
    } else {
        // Operator tanpa event assignment tidak boleh melihat data challenge lintas event.
        $base_query .= " AND 1=0";
        $count_query .= " AND 1=0";
    }
}

$base_query .= " ORDER BY c.challenge_date DESC, c.created_at DESC";

// Get total data
$total_data = 0;
$total_pages = 1;
$challenges = [];
$error = '';

try {
    // Count total records
    $stmt = $conn->prepare($count_query);
    $stmt->execute($count_params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_data = $result['total'];
    
    $total_pages = ceil($total_data / $limit);
    
    // Get data with pagination
    $query = $base_query . " LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);
    $params = array_merge($base_params, [$limit, $offset]);
    $stmt->execute($params);
    
    $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get stats for summary
    $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('Accepted', 'Open') THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
                    FROM challenges c
                    WHERE 1=1";
    if ($selected_event_id > 0) {
        $stats_query .= " AND c.event_id = " . (int)$selected_event_id;
    } else {
        $stats_query .= " AND 1=0";
    }
    $stats_stmt = $conn->query($stats_query);
    $summary_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    foreach ($challenges as &$challenge) {
        $dependencySources = getChallengeDependencySources($conn, (int)($challenge['id'] ?? 0));
        $hasDependency = !empty($dependencySources);

        $challenge['can_delete'] = !$hasDependency;
        if ($hasDependency) {
            $challenge['delete_block_reason'] = 'Tidak bisa dihapus karena sudah ada data turunan: ' . implode(', ', $dependencySources) . '.';
        } else {
            $challenge['delete_block_reason'] = 'Delete';
        }
    }
    unset($challenge);
    
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
    error_log("Challenge Query Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Challenge Management</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../pelatih/css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../pelatih/css/style.css'); ?>">
<link rel="stylesheet" href="css/challenge.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/challenge.css'); ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
<div class="menu-overlay"></div>
<button class="mobile-menu-toggle" aria-label="Toggle menu">
    <i class="fas fa-bars"></i>
</button>

<!-- No longer using old modal since we use SweetAlert2 -->


<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar reveal">
            <div class="greeting">
                <h1>Challenge Management 🏆</h1>
                <p>Kelola tantangan antar team dengan mudah</p>
            </div>
            
            <div class="user-actions">
                <a href="../operator/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Keluar
                </a>
            </div>
        </div>

        <div class="challenge-container">
            <!-- Editorial Header -->
            <header class="dashboard-hero reveal d-1">
                <div class="hero-content">
                    <span class="hero-label">Manajemen Pertandingan</span>
                    <h1 class="hero-title">Direktori Challenge</h1>
                    <p class="hero-description">Kelola tantangan, pantau status pertandingan, dan update hasil skor secara real-time.</p>
                </div>
                <div class="hero-actions">
                    <span class="summary-pill"><i class="fas fa-trophy"></i> <?php echo (int)$total_data; ?> Total Challenge</span>
                </div>
            </header>

            <!-- Filters -->
            <div class="filter-container reveal d-2">
                <div class="challenge-filter-card">
                    <form method="GET" class="challenge-filter-form" id="searchForm">
                        <div class="filter-group">
                            <label>Pencarian</label>
                            <div class="challenge-search-group">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" class="challenge-search-input" 
                                       placeholder="Cari challenge (kode, status, team)..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="filter-group">
                            <label>Event</label>
                            <select name="event_id" id="eventFilter" class="challenge-filter-select" disabled>
                                <option value=""><?php echo $selected_event_id > 0 ? 'Event Operator' : 'Event belum diatur'; ?></option>
                                <?php foreach ($event_filter_options as $event_option): ?>
                                    <option value="<?php echo (int)($event_option['id'] ?? 0); ?>" <?php echo $selected_event_id === (int)($event_option['id'] ?? 0) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event_option['name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="event_id" value="<?php echo (int)$selected_event_id; ?>">
                        </div>
                        <div class="challenge-filter-actions">
                            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
                            <a href="challenge.php" class="clear-filter-btn"><i class="fas fa-times"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="reveal d-3">
                <div class="section-header">
                    <div class="section-title-wrap">
                        <h2 class="section-title">Daftar Challenge</h2>
                        <div class="section-line"></div>
                    </div>
                    <div class="section-actions">
                        <?php if (!$operator_read_only): ?>
                            <a href="challenge_create.php" class="btn-premium btn-add">
                                <i class="fas fa-plus"></i> Tambah Challenge
                            </a>
                            <button type="button" class="btn-premium btn-export" onclick="exportChallenges()">
                                <i class="fas fa-download"></i> Export Excel
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($operator_read_only): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-lock"></i>
                        <span>Event Anda sedang non-aktif. Mode operator hanya lihat data.</span>
                    </div>
                <?php endif; ?>

        <?php if (isset($error) && !empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></span>
        </div>
        <?php endif; ?>

        <div class="alert alert-danger" id="deleteErrorAlert" style="display: none;">
            <i class="fas fa-exclamation-circle"></i>
            <span id="deleteErrorText"></span>
        </div>

        <!-- CHALLENGE TABLE -->
        <div class="table-responsive">
            <table class="data-table" id="challengesTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Status</th>
                        <th>Challenger</th>
                        <th>vs</th>
                        <th>Opponent</th>
                        <th>Venue</th>
                        <th>Date & Time</th>
                        <th>Expired</th>
                        <th>Events</th>
                        <th>Kategori</th>
                        <th>Match Status</th>
                        <th>Score</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($challenges) && count($challenges) > 0): ?>
                        <?php $no = $offset + 1; ?>
                        <?php foreach($challenges as $challenge): ?>
                        <tr>
                            <td><strong><?php echo $no++; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($challenge['challenge_code'] ?? ''); ?></strong>
                            </td>
                            <td>
                                <?php 
                                $status_class = 'status-' . strtolower($challenge['status']);
                                ?>
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($challenge['status'] ?? ''); ?>
                                </span>
                            </td>
                            <td>
                                <div class="challenger-stack">
                                    <?php if (!empty($challenge['challenger_logo'])): ?>
                                        <img src="../images/teams/<?php echo htmlspecialchars($challenge['challenger_logo'] ?? ''); ?>" 
                                             alt="<?php echo htmlspecialchars($challenge['challenger_name'] ?? ''); ?>" 
                                             class="team-logo-small">
                                    <?php else: ?>
                                        <div class="team-logo-small" style="display: flex; align-items: center; justify-content: center; background: #f0f0f0;">
                                            <i class="fas fa-shield-alt" style="color: #999; font-size: 18px;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="challenger-name">
                                        <?php echo htmlspecialchars($challenge['challenger_name'] ?? '-'); ?>
                                    </div>
                                </div>
                            </td>
                            <td class="vs-cell">VS</td>
                            <td>
                                <div class="challenger-stack">
                                    <?php if (!empty($challenge['opponent_logo'])): ?>
                                        <img src="../images/teams/<?php echo htmlspecialchars($challenge['opponent_logo'] ?? ''); ?>" 
                                             alt="<?php echo htmlspecialchars($challenge['opponent_name'] ?? ''); ?>" 
                                             class="team-logo-small">
                                    <?php else: ?>
                                        <div class="team-logo-small" style="display: flex; align-items: center; justify-content: center; background: #f0f0f0;">
                                            <i class="fas fa-shield-alt" style="color: #999; font-size: 18px;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="challenger-name">
                                        <?php echo htmlspecialchars($challenge['opponent_name'] ?? 'TBD'); ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo !empty($challenge['venue_name']) ? htmlspecialchars($challenge['venue_name']) : '-'; ?>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem; font-weight: 600;">
                                    <?php echo date('d M Y', strtotime($challenge['challenge_date'])); ?>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--heritage-text-muted);">
                                    <?php echo date('H:i', strtotime($challenge['challenge_date'])); ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size: 0.8rem;">
                                    <?php echo date('d M Y', strtotime($challenge['expiry_date'])); ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $active_event_name = trim((string)($challenge['event_name'] ?? ''));
                                ?>
                                <span class="event-badge <?php echo $active_event_name !== '' ? 'event-badge-primary' : 'event-badge-muted'; ?>">
                                    <?php echo $active_event_name !== '' ? htmlspecialchars($active_event_name) : '-'; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $sport_value = !empty($challenge['sport_type']) ? $challenge['sport_type'] : ($challenge['challenger_sport'] ?? '-');
                                ?>
                                <span class="event-badge event-badge-primary">
                                    <?php echo htmlspecialchars($sport_value); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($challenge['match_status'])): ?>
                                    <span class="match-status-pill">
                                        <?php echo htmlspecialchars($challenge['match_status'] ?? ''); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="match-status-pill">Belum Mulai</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($challenge['challenger_score'] !== null && $challenge['opponent_score'] !== null): ?>
                                    <span class="score-badge">
                                        <?php echo $challenge['challenger_score']; ?> - <?php echo $challenge['opponent_score']; ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons-inline">
                                    <?php
                                    $deleteDisabled = $operator_read_only || empty($challenge['can_delete']);
                                    $deleteTitle = $operator_read_only
                                        ? 'Event non-aktif. Mode hanya lihat data.'
                                        : (string)($challenge['delete_block_reason'] ?? 'Delete');
                                    ?>
                                    <a href="challenge_view.php?id=<?php echo $challenge['id']; ?>" 
                                       class="action-btn btn-view" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (!$operator_read_only): ?>
                                        <a href="challenge_edit.php?id=<?php echo $challenge['id']; ?>" 
                                           class="action-btn btn-edit" title="Ubah Challenge">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="challenge_result.php?id=<?php echo $challenge['id']; ?>" 
                                           class="action-btn btn-result" title="Update Hasil">
                                            <i class="fas fa-futbol"></i>
                                        </a>
                                        <button class="action-btn btn-delete<?php echo $deleteDisabled ? ' btn-delete-disabled' : ''; ?>"
                                                <?php if (!$deleteDisabled): ?>
                                                data-challenge-id="<?php echo (int) $challenge['id']; ?>"
                                                data-challenge-code="<?php echo htmlspecialchars($challenge['challenge_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php else: ?>
                                                disabled aria-disabled="true"
                                                <?php endif; ?>
                                                title="<?php echo htmlspecialchars($deleteTitle, ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="14" style="text-align: center; padding: 60px;">
                                <div style="text-align: center; color: var(--heritage-text-muted);">
                                    <i class="fas fa-trophy" style="font-size: 48px; opacity: 0.2; margin-bottom: 20px; display: block;"></i>
                                    <h3>Belum Ada Challenge</h3>
                                    <p>Mulai dengan membuat challenge pertama menggunakan tombol di atas.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Statistics Summary -->
        <div class="stats-summary reveal d-3">
            <div class="stat-item">
                <span class="stat-label">Total Challenge</span>
                <span class="stat-value"><?php echo (int)($summary_stats['total'] ?? 0); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Challenge Aktif</span>
                <span class="stat-value"><?php echo (int)($summary_stats['active'] ?? 0); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Challenge Selesai</span>
                <span class="stat-value"><?php echo (int)($summary_stats['completed'] ?? 0); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Halaman</span>
                <span class="stat-value"><?php echo $page; ?> <small style="font-size: 1rem; color: var(--heritage-text-muted);">dari <?php echo $total_pages; ?></small></span>
            </div>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination reveal">
            <?php if ($page > 1): ?>
                <a href="?page=1&search=<?php echo urlencode($search); ?>&event_id=<?php echo (int)$selected_event_id; ?>" class="page-link" title="Halaman Pertama">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&event_id=<?php echo (int)$selected_event_id; ?>" class="page-link" title="Sebelumnya">
                    <i class="fas fa-angle-left"></i>
                </a>
            <?php endif; ?>
            
            <?php 
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++): 
            ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&event_id=<?php echo (int)$selected_event_id; ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                   <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&event_id=<?php echo (int)$selected_event_id; ?>" class="page-link" title="Berikutnya">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&event_id=<?php echo (int)$selected_event_id; ?>" class="page-link" title="Halaman Terakhir">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const eventFilter = document.getElementById('eventFilter');
    const searchForm = document.getElementById('searchForm');
    if (eventFilter && searchForm) {
        eventFilter.addEventListener('change', function () {
            searchForm.submit();
        });
    }

    // --- DELETE HANDLER WITH SWEETALERT2 ---
    document.querySelectorAll('.btn-delete[data-challenge-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const challengeId = this.getAttribute('data-challenge-id');
            const challengeCode = this.getAttribute('data-challenge-code') || '-';
            
            confirmDelete(challengeCode).then(confirmed => {
                if (confirmed) {
                    deleteChallenge(challengeId);
                }
            });
        });
    });
});

function confirmDelete(challengeCode) {
    return new Promise((resolve) => {
        Swal.fire({
            title: 'Hapus Challenge?',
            html: `<div style="text-align: left;">
                <p>Apakah Anda yakin ingin menghapus challenge <strong>"${challengeCode}"</strong>?</p>
                <p style="color: #666; font-size: 14px; margin-top: 10px;">
                    <i class="fas fa-exclamation-triangle" style="color: #ff9800;"></i>
                    Tindakan ini tidak dapat dibatalkan. Data yang dihapus akan hilang permanen.
                </p>
            </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-trash"></i> Hapus',
            cancelButtonText: '<i class="fas fa-times"></i> Batal',
            confirmButtonColor: '#991b1b', // var(--heritage-crimson)
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            customClass: {
                confirmButton: 'swal-delete-btn',
                cancelButton: 'swal-cancel-btn'
            }
        }).then((result) => {
            resolve(result.isConfirmed);
        });
    });
}

function deleteChallenge(challengeId) {
    fetch(`challenge_delete.php?id=${challengeId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            toastr.success('Challenge berhasil dihapus!');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: data.message || 'Gagal menghapus challenge.'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        toastr.error('Terjadi kesalahan saat menghapus challenge.');
    });
}

function exportChallenges() {
    window.location.href = 'challenge_export.php' + (window.location.search ? window.location.search + '&export=excel' : '?export=excel');
}

// Add SweetAlert2 styles
const style = document.createElement('style');
style.textContent = `
.swal-delete-btn {
    padding: 12px 24px !important;
    border-radius: 12px !important;
    font-weight: 700 !important;
    font-family: 'Bricolage Grotesque', sans-serif !important;
}
.swal-cancel-btn {
    padding: 12px 24px !important;
    border-radius: 12px !important;
    font-weight: 700 !important;
    font-family: 'Bricolage Grotesque', sans-serif !important;
}
.swal2-popup {
    border-radius: 24px !important;
}
`;
document.head.appendChild(style);
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
