<?php
$page_title = 'Jadwal Pertandingan';
$current_page = 'schedule';
require_once 'config/database.php';
require_once 'includes/header.php';

// Get pelatih's team_id directly from session (like in dashboard.php)
$my_team_id = $_SESSION['team_id'] ?? 0;

// Fallback: If not in session, try to get from database using pelatih_id
if ($my_team_id == 0) {
    $pelatih_id = $_SESSION['pelatih_id'] ?? 0;
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

// Handle search dan filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sport_filter = isset($_GET['sport']) ? trim($_GET['sport']) : '';
$result_filter = isset($_GET['result']) ? trim($_GET['result']) : ''; // menang | seri | kalah
$day_filter = isset($_GET['day']) ? trim($_GET['day']) : ''; // today
$lineup_filter = isset($_GET['lineup']) ? trim($_GET['lineup']) : ''; // needed
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$next_match = null;
$has_challenge_event_id = false;
$has_events_table = false;
$can_join_event_name = false;

if ($my_team_id) {
    try {
        $stmt = $conn->prepare("
            SELECT c.*, 
                   t1.name as team1_name, t1.logo as team1_logo,
                   t2.name as team2_name, t2.logo as team2_logo,
                   v.name as venue_name
            FROM challenges c
            JOIN teams t1 ON c.challenger_id = t1.id
            JOIN teams t2 ON c.opponent_id = t2.id
            LEFT JOIN venues v ON c.venue_id = v.id
            WHERE c.status = 'accepted' 
              AND (c.challenger_id = ? OR c.opponent_id = ?)
              AND c.challenge_date >= NOW()
            ORDER BY c.challenge_date ASC
            LIMIT 1
        ");
        $stmt->execute([$my_team_id, $my_team_id]);
        $next_match = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $next_match = null;
    }
}

try {
    $check_event_col = $conn->query("SHOW COLUMNS FROM challenges LIKE 'event_id'");
    $has_challenge_event_id = $check_event_col && $check_event_col->rowCount() > 0;
} catch (PDOException $e) {
    $has_challenge_event_id = false;
}

try {
    $check_events_tbl = $conn->query("SHOW TABLES LIKE 'events'");
    $has_events_table = $check_events_tbl && $check_events_tbl->rowCount() > 0;
} catch (PDOException $e) {
    $has_events_table = false;
}

$can_join_event_name = $has_challenge_event_id && $has_events_table;

// Ambil semua tipe olahraga yang tersedia untuk filter
$sport_types = [];
try {
    $sport_query = "SELECT DISTINCT sport_type 
                    FROM challenges 
                    WHERE sport_type IS NOT NULL 
                      AND sport_type != '' 
                      AND (challenger_id = ? OR opponent_id = ?)
                    ORDER BY sport_type";
    $sport_stmt = $conn->prepare($sport_query);
    $sport_stmt->execute([$my_team_id, $my_team_id]);
    $sport_types = $sport_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Jika error, sport_types akan tetap array kosong
}

// Base query untuk challenges dengan join ke teams untuk nama team
$base_query = "SELECT 
    c.*,
    " . ($can_join_event_name
        ? "TRIM(COALESCE(NULLIF(e.name, ''), NULLIF(c.sport_type, ''))) AS event_name,"
        : "TRIM(c.sport_type) AS event_name,") . "
    t1.name as challenger_name,
    t1.logo as challenger_logo,
    t2.name as opponent_name,
    t2.logo as opponent_logo,
    v.name as venue_name
    FROM challenges c
    " . ($can_join_event_name ? "LEFT JOIN events e ON c.event_id = e.id" : "") . "
    LEFT JOIN teams t1 ON c.challenger_id = t1.id
    LEFT JOIN teams t2 ON c.opponent_id = t2.id
    LEFT JOIN venues v ON c.venue_id = v.id
    WHERE (c.challenger_id = ? OR c.opponent_id = ?)";

$count_query = "SELECT COUNT(*) as total 
                FROM challenges c 
                WHERE (c.challenger_id = ? OR c.opponent_id = ?)";

// Tambahkan kondisi untuk search
if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (c.challenge_code LIKE ? 
                OR t1.name LIKE ? 
                OR t2.name LIKE ? 
                OR c.sport_type LIKE ? " . ($can_join_event_name ? "OR e.name LIKE ? " : "") . "
                OR c.status LIKE ?
                OR c.match_status LIKE ?)";
    $count_query .= " AND (c.challenge_code LIKE ? 
                OR EXISTS (SELECT 1 FROM teams t WHERE t.id = c.challenger_id AND t.name LIKE ?)
                OR EXISTS (SELECT 1 FROM teams t WHERE t.id = c.opponent_id AND t.name LIKE ?)
                OR c.sport_type LIKE ?
                " . ($can_join_event_name ? "OR EXISTS (SELECT 1 FROM events e2 WHERE e2.id = c.event_id AND e2.name LIKE ?) " : "") . "
                OR c.status LIKE ?
                OR c.match_status LIKE ?)";
}

// Tambahkan kondisi untuk filter olahraga
if (!empty($sport_filter)) {
    $base_query .= " AND c.sport_type = ?";
    $count_query .= " AND c.sport_type = ?";
}

// Tambahkan kondisi untuk filter hari (matchday hari ini)
if ($day_filter === 'today') {
    $base_query .= " AND DATE(c.challenge_date) = CURDATE()";
    $count_query .= " AND DATE(c.challenge_date) = CURDATE()";
}

// Tambahkan kondisi untuk filter lineup (butuh lineup)
if ($lineup_filter === 'needed') {
    $base_query .= " AND c.status = 'accepted' AND NOT EXISTS (
        SELECT 1 FROM lineups l WHERE l.match_id = c.id AND l.team_id = ?
    ) AND c.challenge_date >= CURDATE()";
    $count_query .= " AND c.status = 'accepted' AND NOT EXISTS (
        SELECT 1 FROM lineups l WHERE l.match_id = c.id AND l.team_id = ?
    ) AND c.challenge_date >= CURDATE()";
}

// Tambahkan kondisi untuk filter hasil (menang/seri/kalah)
if ($result_filter === 'menang') {
    $base_query  .= " AND c.status = 'completed' AND c.winner_team_id = ?";
    $count_query .= " AND c.status = 'completed' AND c.winner_team_id = ?";
} elseif ($result_filter === 'kalah') {
    $base_query  .= " AND c.status = 'completed' AND c.winner_team_id IS NOT NULL AND c.winner_team_id != ?";
    $count_query .= " AND c.status = 'completed' AND c.winner_team_id IS NOT NULL AND c.winner_team_id != ?";
} elseif ($result_filter === 'seri') {
    $base_query  .= " AND c.status = 'completed' AND c.winner_team_id IS NULL";
    $count_query .= " AND c.status = 'completed' AND c.winner_team_id IS NULL";
}

$base_query .= " ORDER BY c.challenge_date DESC";

$total_data = 0;
$total_pages = 1;
$challenges = [];
$needs_lineup_ids = [];

try {
    $filter_params = [$my_team_id, $my_team_id];
    if (!empty($search)) {
        $search_params = [$search_term, $search_term, $search_term, $search_term];
        if ($can_join_event_name) {
            $search_params[] = $search_term;
        }
        $search_params[] = $search_term;
        $search_params[] = $search_term;
        $filter_params = array_merge($filter_params, $search_params);
    }
    if (!empty($sport_filter)) {
        $filter_params[] = $sport_filter;
    }
    if ($lineup_filter === 'needed') {
        $filter_params[] = $my_team_id;
    }
    // result filter: menang & kalah need $my_team_id as extra param; seri needs none
    if ($result_filter === 'menang' || $result_filter === 'kalah') {
        $filter_params[] = $my_team_id;
    }

    // Hitung total data dengan filter
    $stmt = $conn->prepare($count_query);
    $stmt->execute($filter_params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_data = $result['total'];
    
    $total_pages = ceil($total_data / $limit);
    
    // Query data dengan pagination
    $query = $base_query . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);

    $bind_index = 1;
    foreach ($filter_params as $param) {
        $stmt->bindValue($bind_index++, $param);
    }
    $stmt->bindValue($bind_index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($bind_index, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil daftar match yang butuh lineup (sekali query, lalu tandai di array)
    if (!empty($challenges)) {
        $challenge_ids = array_map(function ($row) {
            return (int)$row['id'];
        }, $challenges);
        $placeholders = implode(',', array_fill(0, count($challenge_ids), '?'));
        $need_stmt = $conn->prepare("
            SELECT c.id
            FROM challenges c
            WHERE c.id IN ($placeholders)
              AND c.status = 'accepted'
              AND c.challenge_date >= CURDATE()
              AND NOT EXISTS (
                  SELECT 1 FROM lineups l WHERE l.match_id = c.id AND l.team_id = ?
              )
        ");
        $bind_index = 1;
        foreach ($challenge_ids as $cid) {
            $need_stmt->bindValue($bind_index++, $cid, PDO::PARAM_INT);
        }
        $need_stmt->bindValue($bind_index, $my_team_id, PDO::PARAM_INT);
        $need_stmt->execute();
        $needs_lineup_ids = $need_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $needs_lineup_ids = array_map('intval', $needs_lineup_ids ?: []);
    }

    // Format tanggal dan waktu
    foreach ($challenges as &$challenge) {
        // Format challenge_date
        if (!empty($challenge['challenge_date'])) {
            $date = new DateTime($challenge['challenge_date']);
            $challenge['formatted_date'] = $date->format('d M Y');
            $challenge['formatted_time'] = $date->format('H:i');
        } else {
            $challenge['formatted_date'] = '-';
            $challenge['formatted_time'] = '-';
        }
        
        // Set default logos jika kosong
        $challenge['challenger_logo'] = $challenge['challenger_logo'] ?: 'default-team.png';
        $challenge['opponent_logo'] = $challenge['opponent_logo'] ?: 'default-team.png';

        // Tandai apakah butuh lineup
        $challenge['needs_lineup'] = in_array((int)$challenge['id'], $needs_lineup_ids, true);
    }
    unset($challenge);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$schedule_export_params = [];
if ($search !== '') {
    $schedule_export_params['search'] = $search;
}
if ($sport_filter !== '') {
    $schedule_export_params['sport'] = $sport_filter;
}
if ($day_filter !== '') {
    $schedule_export_params['day'] = $day_filter;
}
if ($lineup_filter !== '') {
    $schedule_export_params['lineup'] = $lineup_filter;
}
$schedule_export_url = 'schedule_export.php' . (!empty($schedule_export_params) ? '?' . http_build_query($schedule_export_params) : '');

// Label for active result filter
$result_filter_labels = ['menang' => '🏆 Menang', 'seri' => '🤝 Seri', 'kalah' => '❌ Kalah'];
$result_filter_label = $result_filter_labels[$result_filter] ?? '';

// Build reset URL preserving other filters but removing result
$reset_result_params = array_filter(['search' => $search, 'sport' => $sport_filter, 'day' => $day_filter, 'lineup' => $lineup_filter]);
$reset_result_url = 'schedule.php' . (!empty($reset_result_params) ? '?' . http_build_query($reset_result_params) : '');

// Label for active day filter
$day_filter_label = $day_filter === 'today' ? 'Hari Ini' : '';

// Build reset URL preserving other filters but removing day
$reset_day_params = array_filter(['search' => $search, 'sport' => $sport_filter, 'result' => $result_filter, 'lineup' => $lineup_filter]);
$reset_day_url = 'schedule.php' . (!empty($reset_day_params) ? '?' . http_build_query($reset_day_params) : '');

// Label for active lineup filter
$lineup_filter_label = $lineup_filter === 'needed' ? 'Butuh Lineup' : '';

// Build reset URL preserving other filters but removing lineup
$reset_lineup_params = array_filter(['search' => $search, 'sport' => $sport_filter, 'result' => $result_filter, 'day' => $day_filter]);
$reset_lineup_url = 'schedule.php' . (!empty($reset_lineup_params) ? '?' . http_build_query($reset_lineup_params) : '');
?>

<link rel="stylesheet" href="css/schedule.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/schedule.css'); ?>">

<div class="teams-container">
    <!-- Editorial Header -->
    <header class="dashboard-hero reveal d-1">
        <div class="hero-content">
            <span class="hero-label">Jadwal</span>
            <h1 class="hero-title">Jadwal Pertandingan</h1>
            <p class="hero-description">Pantau jadwal, hasil, status challenge, dan atur lineup untuk pertandingan aktif tim Anda.</p>
        </div>
        <div class="hero-actions">
            <span class="summary-pill"><i class="fas fa-futbol"></i> <?php echo (int)$total_data; ?> Pertandingan</span>
        </div>
    </header>

<div class="card next-match-card">
    <div class="section-header">
        <h2 class="section-title">Pertandingan Terdekat</h2>
    </div>

    <?php if ($next_match): ?>
        <?php $match_date = new DateTime($next_match['challenge_date']); ?>
        <div class="next-match-body">
            <div class="next-match-teams">
                <div class="next-team">
                    <div class="next-team-logo">
                        <?php if (!empty($next_match['team1_logo']) && file_exists('../images/teams/' . $next_match['team1_logo'])): ?>
                            <img src="../images/teams/<?php echo htmlspecialchars($next_match['team1_logo']); ?>" alt="Team 1">
                        <?php else: ?>
                            <i class="fas fa-shield-alt" style="font-size: 24px; color: #9ca3af;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="next-team-name"><?php echo htmlspecialchars($next_match['team1_name'] ?? '-'); ?></div>
                </div>
                <div class="next-vs">VS</div>
                <div class="next-team">
                    <div class="next-team-logo">
                        <?php if (!empty($next_match['team2_logo']) && file_exists('../images/teams/' . $next_match['team2_logo'])): ?>
                            <img src="../images/teams/<?php echo htmlspecialchars($next_match['team2_logo']); ?>" alt="Team 2">
                        <?php else: ?>
                            <i class="fas fa-shield-alt" style="font-size: 24px; color: #9ca3af;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="next-team-name"><?php echo htmlspecialchars($next_match['team2_name'] ?? '-'); ?></div>
                </div>
            </div>

            <div class="next-match-meta">
                <div class="next-meta-item">
                    <div class="next-meta-label">Tanggal</div>
                    <div class="next-meta-value"><?php echo $match_date->format('l, d M Y'); ?></div>
                </div>
                <div class="next-meta-item">
                    <div class="next-meta-label">Kickoff</div>
                    <div class="next-meta-value"><?php echo $match_date->format('H:i'); ?> WIB</div>
                </div>
                <div class="next-meta-item">
                    <div class="next-meta-label">Venue</div>
                    <div class="next-meta-value"><?php echo htmlspecialchars($next_match['venue_name'] ?: 'TBD'); ?></div>
                </div>
            </div>

            <div class="next-match-actions">
                <a href="match_lineup.php?id=<?php echo $next_match['id']; ?>" class="btn-sm btn-primary" style="text-decoration: none; padding: 8px 14px; border-radius: 8px; font-size: 13px; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-users-cog"></i> Lineup
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="next-empty">Belum ada pertandingan terjadwal yang akan datang.</div>
    <?php endif; ?>
</div>

<div class="card" id="daftar-jadwal-pertandingan">
    <div class="section-header">
        <h2 class="section-title">Daftar Jadwal Pertandingan</h2>
        <div class="section-actions">
            <a href="<?php echo htmlspecialchars($schedule_export_url); ?>" class="btn-premium btn-export">
                <i class="fas fa-download"></i> Export Excel
            </a>
        </div>
    </div>

    <?php if (!empty($day_filter) && !empty($day_filter_label)): ?>
    <div style="margin: 0 0 16px 0; padding: 12px 20px; background: #eff6ff; border: 1.5px solid #60a5fa; border-radius: 12px; display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 0.95rem; color: #1e40af;">
        <i class="fas fa-calendar-day"></i>
        Menampilkan pertandingan: <strong><?php echo htmlspecialchars($day_filter_label); ?></strong>
        <a href="<?php echo htmlspecialchars($reset_day_url); ?>" style="margin-left: auto; font-size: 0.82rem; background: #dbeafe; color: #1e40af; padding: 4px 12px; border-radius: 20px; text-decoration: none; border: 1px solid #93c5fd;">
            <i class="fas fa-times"></i> Hapus Filter Ini
        </a>
    </div>
    <?php endif; ?>

    <?php if (!empty($lineup_filter) && !empty($lineup_filter_label)): ?>
    <div style="margin: 0 0 16px 0; padding: 12px 20px; background: #fff7ed; border: 1.5px solid #fb923c; border-radius: 12px; display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 0.95rem; color: #9a3412;">
        <i class="fas fa-users-cog"></i>
        Menampilkan pertandingan: <strong><?php echo htmlspecialchars($lineup_filter_label); ?></strong>
        <a href="<?php echo htmlspecialchars($reset_lineup_url); ?>" style="margin-left: auto; font-size: 0.82rem; background: #ffedd5; color: #9a3412; padding: 4px 12px; border-radius: 20px; text-decoration: none; border: 1px solid #fdba74;">
            <i class="fas fa-times"></i> Hapus Filter Ini
        </a>
    </div>
    <?php endif; ?>

    <?php if (!empty($result_filter) && !empty($result_filter_label)): ?>
    <div style="margin: 0 0 16px 0; padding: 12px 20px; background: #f0faf5; border: 1.5px solid #10b981; border-radius: 12px; display: flex; align-items: center; gap: 12px; font-weight: 600; font-size: 0.95rem; color: #065f46;">
        <i class="fas fa-filter"></i>
        Menampilkan pertandingan: <strong><?php echo htmlspecialchars($result_filter_label); ?></strong>
        <a href="<?php echo htmlspecialchars($reset_result_url); ?>" style="margin-left: auto; font-size: 0.82rem; background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 20px; text-decoration: none; border: 1px solid #6ee7b7;">
            <i class="fas fa-times"></i> Hapus Filter Ini
        </a>
    </div>
    <?php endif; ?>

    <div class="filter-container reveal d-2">
        <div class="teams-filter-card">
            <form action="" method="GET" class="teams-filter-form">
                <?php if (!empty($result_filter)): ?>
                <input type="hidden" name="result" value="<?php echo htmlspecialchars($result_filter); ?>">
                <?php endif; ?>
                <?php if (!empty($day_filter)): ?>
                <input type="hidden" name="day" value="<?php echo htmlspecialchars($day_filter); ?>">
                <?php endif; ?>
                <?php if (!empty($lineup_filter)): ?>
                <input type="hidden" name="lineup" value="<?php echo htmlspecialchars($lineup_filter); ?>">
                <?php endif; ?>
                <div class="filter-group">
                    <label>Pencarian</label>
                    <div class="teams-search-group">
                        <i class="fas fa-search"></i>
                        <input
                            type="text"
                            name="search"
                            placeholder="Cari kode, team, event, atau status pertandingan..."
                            value="<?php echo htmlspecialchars($search); ?>"
                            class="teams-search-input"
                        >
                    </div>
                </div>

                <div class="filter-group">
                    <label>Kategori Event</label>
                    <div class="schedule-select-wrap">
                        <div class="schedule-custom-select" id="scheduleSportSelect">
                            <input type="hidden" name="sport" id="scheduleSportValue" value="<?php echo htmlspecialchars($sport_filter); ?>">
                            <button type="button" class="schedule-custom-select-trigger" id="scheduleSportTrigger" aria-expanded="false">
                                <span class="schedule-custom-select-label">
                                    <i class="fas fa-trophy"></i>
                                    <span id="scheduleSportLabel" class="schedule-custom-select-text"><?php echo $sport_filter !== '' ? htmlspecialchars($sport_filter) : 'Semua Kategori'; ?></span>
                                </span>
                                <i class="fas fa-chevron-down select-icon-right"></i>
                            </button>
                            <div class="schedule-custom-select-menu" id="scheduleSportMenu">
                                <button type="button" class="schedule-custom-option <?php echo $sport_filter === '' ? 'active' : ''; ?>" data-value="">Semua Kategori</button>
                                <?php foreach ($sport_types as $sport): ?>
                                    <button type="button" class="schedule-custom-option <?php echo $sport_filter === $sport ? 'active' : ''; ?>" data-value="<?php echo htmlspecialchars($sport); ?>">
                                        <?php echo htmlspecialchars($sport); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="teams-filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> Terapkan
                    </button>
                    <?php if (!empty($search) || !empty($sport_filter) || !empty($result_filter) || !empty($day_filter) || !empty($lineup_filter)): ?>
                    <a href="schedule.php" class="clear-filter-btn">
                        <i class="fas fa-times"></i> Reset
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($challenges)): ?>
        <p style="text-align: center; color: var(--gray); padding: 20px;">Pertandingan tidak ditemukan.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kode Pertandingan</th>
                        <th>Tanggal</th>
                        <th>Team</th>
                        <th>Event</th>
                        <th>Lokasi</th>
                        <th style="text-align:center;">Skor</th>
                        <th>Status</th>
                        <th>Status Pertandingan</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($challenges as $challenge): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($challenge['challenge_code'] ?? ''); ?></strong>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: var(--dark);"><?php echo $challenge['formatted_date']; ?></div>
                            <div style="font-size: 13px; color: var(--gray);"><?php echo $challenge['formatted_time']; ?></div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="text-align: center;">
                                    <img src="../images/teams/<?php echo htmlspecialchars($challenge['challenger_logo']); ?>" 
                                         alt="<?php echo htmlspecialchars($challenge['challenger_name'] ?? ''); ?>" 
                                         class="team-logo"
                                         onerror="this.onerror=null; this.src='../images/teams/default-team.png'">
                                    <div style="font-size: 12px; margin-top: 5px; font-weight: 600;">
                                        <?php echo htmlspecialchars($challenge['challenger_name'] ?? ''); ?>
                                    </div>
                                </div>
                                <div style="color: var(--gray);">vs</div>
                                <div style="text-align: center;">
                                    <img src="../images/teams/<?php echo htmlspecialchars($challenge['opponent_logo']); ?>" 
                                         alt="<?php echo htmlspecialchars($challenge['opponent_name'] ?? ''); ?>" 
                                         class="team-logo"
                                         onerror="this.onerror=null; this.src='../images/teams/default-team.png'">
                                    <div style="font-size: 12px; margin-top: 5px; font-weight: 600;">
                                        <?php echo htmlspecialchars($challenge['opponent_name'] ?? ''); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:4px; align-items:center; text-align:center;">
                                <span class="event-badge" title="<?php echo htmlspecialchars($challenge['event_name'] ?? ''); ?>" style="padding: 6px 12px; background: #f0f7ff; color: var(--primary); border-radius: 20px; font-size: 12px; font-weight: 700;">
                                    <?php echo htmlspecialchars($challenge['event_name'] ?? ($challenge['sport_type'] ?? '-')); ?>
                                </span>
                                <span style="font-size:11px; color: var(--gray);">
                                    Kategori: <?php echo htmlspecialchars($challenge['sport_type'] ?? '-'); ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($challenge['venue_name'])): ?>
                                <span><?php echo htmlspecialchars($challenge['venue_name'] ?? ''); ?></span>
                            <?php else: ?>
                                <span style="color: var(--gray); font-style: italic;">Akan ditentukan</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $has_score =
                                ($challenge['challenger_score'] !== null && $challenge['challenger_score'] !== '') ||
                                ($challenge['opponent_score'] !== null && $challenge['opponent_score'] !== '');
                            ?>
                            <?php if ($has_score): ?>
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 4px; line-height: 1.1;">
                                    <div style="font-weight: 700; font-size: 14px; text-align: center; color: var(--primary);">
                                        <?php echo htmlspecialchars($challenge['challenger_score'] ?? 0); ?> - <?php echo htmlspecialchars($challenge['opponent_score'] ?? 0); ?>
                                    </div>
                                    <?php if (!empty($challenge['winner_team_id'])): ?>
                                        <div style="font-size: 11px; color: var(--success); text-align: center; font-weight: 600;">
                                            <?php 
                                            $winner_name = ($challenge['winner_team_id'] == $challenge['challenger_id']) 
                                                ? $challenge['challenger_name'] 
                                                : $challenge['opponent_name'];
                                            echo htmlspecialchars($winner_name ?? '');
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--gray); font-style: italic;">Belum dimainkan</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($challenge['status'])): ?>
                                <?php $status_class = 'status-' . strtolower($challenge['status']); ?>
                                <span class="status-badge <?php echo htmlspecialchars($status_class); ?>">
                                    <?php echo htmlspecialchars($challenge['status'] ?? ''); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($challenge['match_status'])): ?>
                                <?php $match_class = 'match-' . strtolower($challenge['match_status']); ?>
                                <span class="match-badge <?php echo htmlspecialchars($match_class); ?>">
                                    <?php echo htmlspecialchars($challenge['match_status'] ?? ''); ?>
                                </span>
                            <?php else: ?>
                                <span class="match-badge match-empty">Belum Mulai</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if (!empty($challenge['needs_lineup']) && ($my_team_id == $challenge['challenger_id'] || $my_team_id == $challenge['opponent_id'])): ?>
                            <a href="match_lineup.php?id=<?php echo $challenge['id']; ?>" class="btn-sm btn-primary" style="text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                <i class="fas fa-users-cog"></i> Lineup
                            </a>
                            <?php else: ?>
                            <span style="color: var(--gray);">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1&search=<?php echo urlencode($search); ?>&sport=<?php echo urlencode($sport_filter); ?>&day=<?php echo urlencode($day_filter); ?>&lineup=<?php echo urlencode($lineup_filter); ?>&result=<?php echo urlencode($result_filter); ?>" class="page-link" title="Halaman Pertama">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&sport=<?php echo urlencode($sport_filter); ?>&day=<?php echo urlencode($day_filter); ?>&lineup=<?php echo urlencode($lineup_filter); ?>&result=<?php echo urlencode($result_filter); ?>" class="page-link" title="Sebelumnya">
                    <i class="fas fa-angle-left"></i>
                </a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&sport=<?php echo urlencode($sport_filter); ?>&day=<?php echo urlencode($day_filter); ?>&lineup=<?php echo urlencode($lineup_filter); ?>&result=<?php echo urlencode($result_filter); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&sport=<?php echo urlencode($sport_filter); ?>&day=<?php echo urlencode($day_filter); ?>&lineup=<?php echo urlencode($lineup_filter); ?>&result=<?php echo urlencode($result_filter); ?>" class="page-link" title="Berikutnya">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&sport=<?php echo urlencode($sport_filter); ?>&day=<?php echo urlencode($day_filter); ?>&lineup=<?php echo urlencode($lineup_filter); ?>&result=<?php echo urlencode($result_filter); ?>" class="page-link" title="Halaman Terakhir">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectRoot = document.getElementById('scheduleSportSelect');
    if (!selectRoot) return;

    const trigger = document.getElementById('scheduleSportTrigger');
    const menu = document.getElementById('scheduleSportMenu');
    const hiddenInput = document.getElementById('scheduleSportValue');
    const label = document.getElementById('scheduleSportLabel');
    const options = menu.querySelectorAll('.schedule-custom-option');

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
</script>

<?php require_once 'includes/footer.php'; ?>
