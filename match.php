<?php
require_once 'includes/functions.php';

// ============================================
// SEMUA LOGIC DAN REDIRECT HARUS SEBELUM OUTPUT
// ============================================

$pageTitle = "Match Details";
$hideNavbars = true;

// Get match ID from URL
$matchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($matchId <= 0) {
    header("Location: index.php");
    exit();
}   

$requestedSource = strtolower(trim((string) ($_GET['source'] ?? '')));
$source = $requestedSource === 'match' ? 'match' : 'challenge';
$requestedEventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

$extraStyles = [
    '<link rel="stylesheet" href="' . SITE_URL . '/css/redesign_core.css?v=' . getAssetVersion('/css/redesign_core.css') . '">',
    '<link rel="stylesheet" href="' . SITE_URL . '/css/index_redesign.css?v=' . getAssetVersion('/css/index_redesign.css') . '">',
    '<link rel="stylesheet" href="' . SITE_URL . '/css/match_redesign.css?v=' . getAssetVersion('/css/match_redesign.css') . '">'
];

// Sekarang baru require header
require_once 'includes/header.php'; 

$conn = $db->getConnection();

$challengeHasEventId = false;
$eventIdColumnCheck = $conn->query("SHOW COLUMNS FROM challenges LIKE 'event_id'");
if ($eventIdColumnCheck && $eventIdColumnCheck->num_rows > 0) {
    $challengeHasEventId = true;
}
if ($eventIdColumnCheck instanceof mysqli_result) {
    $eventIdColumnCheck->free();
}

$challengeSql = "SELECT c.id,
                        c.challenge_code AS match_code,
                        c.challenge_date AS match_date,
                        c.challenger_score AS score1,
                        c.opponent_score AS score2,
                        c.challenger_uniform_choices AS team1_uniform_choices,
                        c.opponent_uniform_choices AS team2_uniform_choices,
                        c.match_official AS match_official,
                        COALESCE(NULLIF(c.match_status, ''), c.status) AS status,
                        v.name AS location,
                        t1.id AS team1_id,
                        t1.name AS team1_name,
                        t1.logo AS team1_logo,
                        t2.id AS team2_id,
                        t2.name AS team2_name,
                        t2.logo AS team2_logo,
                        " . ($challengeHasEventId ? "c.event_id AS event_id," : "0 AS event_id,") . "
                        " . ($challengeHasEventId ? "COALESCE(NULLIF(e.name, ''), c.sport_type) AS event_name," : "c.sport_type AS event_name,") . "
                        c.sport_type AS category_name,
                        '' AS event_description
                 FROM challenges c
                 LEFT JOIN teams t1 ON c.challenger_id = t1.id
                 LEFT JOIN teams t2 ON c.opponent_id = t2.id
                 LEFT JOIN venues v ON c.venue_id = v.id
                 " . ($challengeHasEventId ? "LEFT JOIN events e ON c.event_id = e.id" : "") . "
                 WHERE c.id = ?
                 LIMIT 1";

$legacySql = "SELECT m.id,
                      '' AS match_code,
                      m.match_date,
                      m.score1,
                      m.score2,
                      '' AS team1_uniform_choices,
                      '' AS team2_uniform_choices,
                      '' AS match_official,
                      m.status,
                      m.location,
                      t1.id AS team1_id,
                      t1.name AS team1_name,
                      t1.logo AS team1_logo,
                      t2.id AS team2_id,
                      t2.name AS team2_name,
                      t2.logo AS team2_logo,
                      m.event_id AS event_id,
                      e.name AS event_name,
                      '' AS category_name,
                      e.description AS event_description
               FROM matches m
               LEFT JOIN teams t1 ON m.team1_id = t1.id
               LEFT JOIN teams t2 ON m.team2_id = t2.id
               LEFT JOIN events e ON m.event_id = e.id
               WHERE m.id = ?
               LIMIT 1";

$match = null;
$resolvedSource = '';

$runLookup = function ($sql) use ($conn, $matchId) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $matchId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
};

if ($source === 'match') {
    $match = $runLookup($legacySql);
    if ($match) {
        $resolvedSource = 'match';
    }
    if (!$match) {
        $match = $runLookup($challengeSql);
        if ($match) {
            $resolvedSource = 'challenge';
        }
    }
} else {
    $match = $runLookup($challengeSql);
    if ($match) {
        $resolvedSource = 'challenge';
    }
    if (!$match) {
        $match = $runLookup($legacySql);
        if ($match) {
            $resolvedSource = 'match';
        }
    }
}

if ($match && $requestedEventId > 0) {
    $matchEventId = (int)($match['event_id'] ?? 0);
    if ($matchEventId !== $requestedEventId) {
        $match = null;
        $resolvedSource = '';
    }
}

$matchNotFound = false;
$lineups = [
    'team1' => ['half1' => [], 'half2' => []], 
    'team2' => ['half1' => [], 'half2' => []]
];
$goals = [];
$hasMatchStaffAssignmentsTable = false;
$hasMatchStaffHalfColumn = false;
$staffLineups = [
    'team1' => ['half1' => [], 'half2' => []],
    'team2' => ['half1' => [], 'half2' => []]
];
$hasPlayerEventCardsTable = false;
$matchSuspendRows = [];

function formatStaffRoleLabel($role): string
{
    $key = strtolower(trim((string)$role));
    $labels = [
        'manager' => 'Manager',
        'headcoach' => 'Head Coach',
        'coach' => 'Coach',
        'assistant_coach' => 'Asst. Coach',
        'goalkeeper_coach' => 'GK Coach',
        'fitness_coach' => 'Fitness Coach',
        'analyst' => 'Analyst',
        'medic' => 'Medic',
        'official' => 'Official',
        'scout' => 'Scout'
    ];
    if (isset($labels[$key])) {
        return $labels[$key];
    }
    return $key !== '' ? ucwords(str_replace('_', ' ', $key)) : '-';
}

function resolveMatchStaffPhoto($filename): array
{
    if (empty($filename)) {
        return ['url' => null, 'found' => false];
    }

    $basename = basename((string)$filename);
    $candidates = [
        'uploads/staff/' . $basename,
        'images/staff/' . $basename,
        'assets/staff/' . $basename,
        ltrim((string)$filename, '/\\')
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return ['url' => SITE_URL . '/' . str_replace('\\', '/', $path), 'found' => true];
        }
    }

    return ['url' => null, 'found' => false];
}

if (!$match) {
    $matchNotFound = true;
} else {
    if ($resolvedSource === 'challenge') {
        // Get lineups for challenge-based match IDs only.
        $sqlLineups = "SELECT l.*, p.name as player_name, p.photo, p.jersey_number, t.id as team_id, t.name as team_name
                       FROM lineups l
                       LEFT JOIN players p ON l.player_id = p.id
                       LEFT JOIN teams t ON l.team_id = t.id
                       WHERE l.match_id = ?
                       ORDER BY l.is_starting DESC, p.jersey_number ASC";

        $stmtLineups = $conn->prepare($sqlLineups);
        $stmtLineups->bind_param('i', $matchId);
        $stmtLineups->execute();
        $resultLineups = $stmtLineups->get_result();
        while ($lineup = $resultLineups->fetch_assoc()) {
            $half = isset($lineup['half']) && $lineup['half'] == 2 ? 'half2' : 'half1';

            if ($lineup['team_id'] == $match['team1_id']) {
                $lineups['team1'][$half][] = $lineup;
            } elseif ($lineup['team_id'] == $match['team2_id']) {
                $lineups['team2'][$half][] = $lineup;
            }
        }
        $stmtLineups->close();

        // Get goals for this challenge match.
        $goals = getMatchGoals($matchId);

        $staffTableCheck = $conn->query("SHOW TABLES LIKE 'match_staff_assignments'");
        $hasMatchStaffAssignmentsTable = $staffTableCheck && $staffTableCheck->num_rows > 0;
        if ($staffTableCheck instanceof mysqli_result) {
            $staffTableCheck->free();
        }

        if ($hasMatchStaffAssignmentsTable) {
            $staffHalfCheck = $conn->query("SHOW COLUMNS FROM match_staff_assignments LIKE 'half'");
            $hasMatchStaffHalfColumn = $staffHalfCheck && $staffHalfCheck->num_rows > 0;
            if ($staffHalfCheck instanceof mysqli_result) {
                $staffHalfCheck->free();
            }

            $halfSelectSql = $hasMatchStaffHalfColumn ? "COALESCE(msa.half, 1) AS half," : "1 AS half,";
            $sqlStaffLineups = "SELECT msa.team_id,
                                       {$halfSelectSql}
                                       msa.role AS assignment_role,
                                       ts.position AS staff_position,
                                       ts.photo AS staff_photo,
                                       ts.name AS staff_name
                                FROM match_staff_assignments msa
                                LEFT JOIN team_staff ts ON msa.staff_id = ts.id
                                WHERE msa.match_id = ?
                                ORDER BY
                                    COALESCE(msa.half, 1) ASC,
                                    CASE
                                        WHEN (
                                            (msa.role IS NOT NULL AND msa.role <> '' AND msa.role = 'manager')
                                            OR ((msa.role IS NULL OR msa.role = '') AND ts.position = 'manager')
                                        ) THEN 1
                                        WHEN (
                                            (msa.role IS NOT NULL AND msa.role <> '' AND msa.role = 'headcoach')
                                            OR ((msa.role IS NULL OR msa.role = '') AND ts.position = 'headcoach')
                                        ) THEN 2
                                        WHEN (
                                            (msa.role IS NOT NULL AND msa.role <> '' AND msa.role = 'coach')
                                            OR ((msa.role IS NULL OR msa.role = '') AND ts.position = 'coach')
                                        ) THEN 3
                                        WHEN (
                                            (msa.role IS NOT NULL AND msa.role <> '' AND msa.role = 'assistant_coach')
                                            OR ((msa.role IS NULL OR msa.role = '') AND ts.position = 'assistant_coach')
                                        ) THEN 4
                                        WHEN (
                                            (msa.role IS NOT NULL AND msa.role <> '' AND msa.role = 'goalkeeper_coach')
                                            OR ((msa.role IS NULL OR msa.role = '') AND ts.position = 'goalkeeper_coach')
                                        ) THEN 5
                                        WHEN (
                                            (msa.role IS NOT NULL AND msa.role <> '' AND msa.role = 'fitness_coach')
                                            OR ((msa.role IS NULL OR msa.role = '') AND ts.position = 'fitness_coach')
                                        ) THEN 6
                                        WHEN (
                                            (msa.role IS NOT NULL AND msa.role <> '' AND msa.role = 'analyst')
                                            OR ((msa.role IS NULL OR msa.role = '') AND ts.position = 'analyst')
                                        ) THEN 7
                                        WHEN (
                                            (msa.role IS NOT NULL AND msa.role <> '' AND msa.role = 'medic')
                                            OR ((msa.role IS NULL OR msa.role = '') AND ts.position = 'medic')
                                        ) THEN 8
                                        WHEN (
                                            (msa.role IS NOT NULL AND msa.role <> '' AND msa.role = 'official')
                                            OR ((msa.role IS NULL OR msa.role = '') AND ts.position = 'official')
                                        ) THEN 9
                                        ELSE 99
                                    END,
                                    ts.name ASC";
            $stmtStaffLineups = $conn->prepare($sqlStaffLineups);
            if ($stmtStaffLineups) {
                $stmtStaffLineups->bind_param('i', $matchId);
                $stmtStaffLineups->execute();
                $resultStaffLineups = $stmtStaffLineups->get_result();
                while ($staffRow = $resultStaffLineups->fetch_assoc()) {
                    $teamKey = null;
                    if ((int)$staffRow['team_id'] === (int)$match['team1_id']) {
                        $teamKey = 'team1';
                    } elseif ((int)$staffRow['team_id'] === (int)$match['team2_id']) {
                        $teamKey = 'team2';
                    }

                    if ($teamKey === null) {
                        continue;
                    }
                    $halfKey = ((int)($staffRow['half'] ?? 1) === 2) ? 'half2' : 'half1';

                    $effectiveRole = trim((string)($staffRow['assignment_role'] ?? ''));
                    if ($effectiveRole === '') {
                        $effectiveRole = trim((string)($staffRow['staff_position'] ?? ''));
                    }

                    $staffLineups[$teamKey][$halfKey][] = [
                        'name' => trim((string)($staffRow['staff_name'] ?? '')),
                        'role' => $effectiveRole,
                        'photo' => trim((string)($staffRow['staff_photo'] ?? ''))
                    ];
                }
                $stmtStaffLineups->close();
            }
        }

        $cardsTableCheck = $conn->query("SHOW TABLES LIKE 'player_event_cards'");
        $hasPlayerEventCardsTable = $cardsTableCheck && $cardsTableCheck->num_rows > 0;
        if ($cardsTableCheck instanceof mysqli_result) {
            $cardsTableCheck->free();
        }

        if ($hasPlayerEventCardsTable) {
            $team1Id = (int)($match['team1_id'] ?? 0);
            $team2Id = (int)($match['team2_id'] ?? 0);
            $matchEventId = (int)($match['event_id'] ?? 0);
            $matchCategory = trim((string)($match['category_name'] ?? ''));

            if ($team1Id > 0 && $team2Id > 0) {
                $cardSql = "SELECT pec.*, p.name AS player_name, t.name AS team_name
                            FROM player_event_cards pec
                            INNER JOIN players p ON p.id = pec.player_id
                            INNER JOIN teams t ON t.id = pec.team_id
                            WHERE pec.team_id IN (?, ?)
                              AND (pec.yellow_cards > 0 OR pec.red_cards > 0 OR pec.green_cards > 0)";

                $types = "ii";
                $params = [$team1Id, $team2Id];

                if ($matchEventId > 0) {
                    $cardSql .= " AND pec.event_id = ?";
                    $types .= "i";
                    $params[] = $matchEventId;
                }

                if ($matchCategory !== '') {
                    $cardSql .= " AND (pec.sport_type = ? OR pec.sport_type = '' OR pec.sport_type IS NULL)";
                    $types .= "s";
                    $params[] = $matchCategory;
                }

                $cardSql .= " ORDER BY t.name ASC, p.name ASC";

                $stmtMatchCards = $conn->prepare($cardSql);
                if ($stmtMatchCards) {
                    $stmtMatchCards->bind_param($types, ...$params);
                    $stmtMatchCards->execute();
                    $resultMatchCards = $stmtMatchCards->get_result();
                    while ($row = $resultMatchCards->fetch_assoc()) {
                        $matchSuspendRows[] = $row;
                    }
                    $stmtMatchCards->close();
                }
            }
        }
    }
}

$statusValue = '';
$statusLabel = 'TBA';
$statusClass = 'status-tba';
$matchTime = 'TBD';
$locationLabel = 'TBA';
$eventName = '';
$categoryName = '';
$matchCode = '';
$scoreAvailable = false;
$team1UniformLabel = 'Belum dipilih';
$team2UniformLabel = 'Belum dipilih';
$matchOfficialLabel = '-';

if (!$matchNotFound) {
    $statusValue = strtolower($match['status'] ?? '');
    $statusLabel = $statusValue ? ucfirst($statusValue) : 'TBA';
    $statusClass = $statusValue ? 'status-' . preg_replace('/[^a-z0-9]+/', '-', $statusValue) : 'status-tba';
    $matchTime = !empty($match['match_date']) ? date('H:i', strtotime($match['match_date'])) : 'TBD';
    $locationLabel = $match['location'] ?? ($match['venue_name'] ?? 'TBA');
    $eventName = $match['event_name'] ?? '';
    $categoryName = $match['category_name'] ?? '';
    $matchCode = $match['match_code'] ?? '';
    $scoreAvailable = $match['score1'] !== null && $match['score2'] !== null;
    $team1UniformLabel = trim((string)($match['team1_uniform_choices'] ?? '')) ?: 'Belum dipilih';
    $team2UniformLabel = trim((string)($match['team2_uniform_choices'] ?? '')) ?: 'Belum dipilih';
    $matchOfficialLabel = trim((string)($match['match_official'] ?? '')) ?: '-';
}

?>


<style>
/* Custom Tabs for Match Lineup */
.lineup-tabs {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-bottom: 25px;
}

.lineup-tab-btn {
    padding: 10px 30px;
    border-radius: 30px;
    background: #f1f5f9;
    color: #64748b;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
}

.lineup-tab-btn.active {
    background: var(--primary-color, #0f172a);
    color: white;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
}

.lineup-content-half {
    display: none;
    animation: fadeIn 0.4s ease;
}

.lineup-content-half.active {
    display: block;
}

.staff-content-half {
    display: none;
    animation: fadeIn 0.4s ease;
}

.staff-content-half.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}

.goals-half-title {
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: #0f172a;
    margin: 12px 0 8px;
    padding-left: 10px;
}

.goals-half-title:first-of-type {
    margin-top: 0;
}

.card-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    padding: 2px 8px;
    border-radius: 999px;
    font-weight: 700;
    font-size: 12px;
}

.card-badge.yellow { background: #fef3c7; color: #92400e; }
.card-badge.red { background: #fee2e2; color: #991b1b; }
.card-badge.green { background: #dcfce7; color: #166534; }

.suspend-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 700;
}

.suspend-pill.active { background: #fee2e2; color: #991b1b; }
.suspend-pill.done { background: #dcfce7; color: #166534; }

.match-suspend-wrap {
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
}

.match-suspend-table {
    width: 100%;
    min-width: 760px;
    border-collapse: collapse;
}

.match-suspend-table th,
.match-suspend-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 13px;
    color: #0f172a;
}

.match-suspend-table th {
    background: #0f2744;
    font-weight: 700;
    text-align: left;
    color: #ffffff;
}

.match-suspend-table tbody tr:hover {
    background: #f8fbff;
}

</style>

<div class="dashboard-wrapper">
<?php 
$currentPage = 'challenge';
include 'includes/sidebar.php'; 
?>

    <!-- Main Content -->
    <main class="main-content-dashboard">
        <header class="dashboard-header dashboard-header-match">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">ALVETRIX</div>
                    <h1><?php echo $matchNotFound ? 'Pertandingan Tidak Ditemukan' : 'Detail Pertandingan'; ?></h1>
                    <p class="header-subtitle">Detail pertandingan, status, dan susunan pemain dalam tampilan yang rapi dan modern.</p>
                </div>
                <div class="header-actions">
                    <a href="all.php?status=result" class="btn-secondary"><i class="fas fa-list"></i> Semua Pertandingan</a>
                    <a href="events.php" class="btn-primary"><i class="fas fa-calendar-alt"></i> Jelajahi Event</a>
                </div>
            </div>
            <?php if (!$matchNotFound): ?>
            <div class="header-meta">
                <?php if (!empty($eventName)): ?>
                    <span class="meta-chip"><i class="fas fa-trophy"></i> <?php echo htmlspecialchars($eventName ?? ''); ?></span>
                <?php endif; ?>
                <?php if (!empty($categoryName)): ?>
                    <span class="meta-chip"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($categoryName ?? ''); ?></span>
                <?php endif; ?>
                <?php if (!empty($matchCode)): ?>
                    <span class="meta-chip"><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($matchCode ?? ''); ?></span>
                <?php endif; ?>
                <span class="meta-chip <?php echo htmlspecialchars($statusClass ?? ''); ?>">
                    <i class="fas fa-circle-info"></i> <?php echo htmlspecialchars($statusLabel ?? ''); ?>
                </span>
            </div>
            <?php endif; ?>
        </header>

        <div class="dashboard-body">
            <?php if ($matchNotFound): ?>
                <section class="section-container">
                    <div class="empty-state">
                        <i class="fas fa-futbol"></i>
                        <h4>Pertandingan tidak ditemukan</h4>
                        <p>Match ID yang kamu cari tidak tersedia.</p>
                    </div>
                </section>
            <?php else: ?>
                <section class="section-container section-elevated match-summary-card">
                    <div class="match-tags">
                        <?php if (!empty($eventName)): ?>
                            <span class="match-tag event-tag"><i class="fas fa-bolt"></i> <?php echo htmlspecialchars($eventName ?? ''); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($categoryName)): ?>
                            <span class="match-tag code-tag"><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($categoryName ?? ''); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($matchCode)): ?>
                            <span class="match-tag code-tag"><i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($matchCode ?? ''); ?></span>
                        <?php endif; ?>
                        <span class="match-tag status-tag <?php echo htmlspecialchars($statusClass ?? ''); ?>">
                            <i class="fas fa-flag-checkered"></i> <?php echo htmlspecialchars($statusLabel ?? ''); ?>
                        </span>
                    </div>

                    <div class="match-teams-grid">
                        <div class="match-team">
                            <div class="match-team-logo">
                                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>" 
                                     alt="<?php echo htmlspecialchars($match['team1_name'] ?? ''); ?>"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                            </div>
                            <div class="match-team-name"><?php echo htmlspecialchars($match['team1_name'] ?? ''); ?></div>
                            <div class="match-team-uniform">Baju: <?php echo htmlspecialchars($team1UniformLabel); ?></div>
                        </div>

                        <div class="match-score-block">
                            <?php if ($scoreAvailable): ?>
                                <div class="score-pill"><?php echo $match['score1']; ?> - <?php echo $match['score2']; ?></div>
                                <div class="score-subtext">Skor Akhir</div>
                            <?php else: ?>
                                <div class="vs-pill">VS</div>
                                <div class="score-subtext"><?php echo htmlspecialchars($matchTime ?? ''); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="match-team">
                            <div class="match-team-logo">
                                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>" 
                                     alt="<?php echo htmlspecialchars($match['team2_name'] ?? ''); ?>"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                            </div>
                            <div class="match-team-name"><?php echo htmlspecialchars($match['team2_name'] ?? ''); ?></div>
                            <div class="match-team-uniform">Baju: <?php echo htmlspecialchars($team2UniformLabel); ?></div>
                        </div>
                    </div>

                    <div class="match-meta-grid">
                        <div class="match-meta-card">
                            <div class="match-meta-icon"><i class="far fa-calendar-alt"></i></div>
                            <div>
                                <div class="match-meta-label">Tanggal & Waktu</div>
                                <div class="match-meta-value"><?php echo formatDateTime($match['match_date']); ?></div>
                            </div>
                        </div>
                        <div class="match-meta-card">
                            <div class="match-meta-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div>
                                <div class="match-meta-label">Lokasi</div>
                                <div class="match-meta-value"><?php echo htmlspecialchars($locationLabel ?? 'TBA'); ?></div>
                            </div>
                        </div>
                        <div class="match-meta-card">
                            <div class="match-meta-icon"><i class="fas fa-circle-info"></i></div>
                            <div>
                                <div class="match-meta-label">Status</div>
                                <div class="match-meta-value">
                                    <span class="status-chip <?php echo htmlspecialchars($statusClass ?? ''); ?>">
                                        <?php echo htmlspecialchars($statusLabel ?? ''); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="match-meta-card">
                            <div class="match-meta-icon"><i class="fas fa-user-tie"></i></div>
                            <div>
                                <div class="match-meta-label">Wasit/Pengawas</div>
                                <div class="match-meta-value"><?php echo htmlspecialchars($matchOfficialLabel); ?></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="section-container lineup-section">
                    <div class="section-header">
                        <h2 class="section-title">GOL</h2>
                    </div>

                    <div class="goals-list pro-goals-container">
                        <?php if (empty($goals)): ?>
                            <div class="no-data">Belum ada gol tercipta</div>
                        <?php else: ?>
                            <?php $currentGoalHalf = null; ?>
                            <?php foreach ($goals as $goal): ?>
                                <?php
                                    $goalHalf = (int)($goal['goal_half'] ?? ($goal['half'] ?? 0));
                                    if ($goalHalf !== 1 && $goalHalf !== 2) {
                                        $goalHalf = ((int)($goal['minute'] ?? 0) > 45) ? 2 : 1;
                                    }
                                    $isTeam1 = ($goal['team_id'] == $match['team1_id']);
                                ?>
                                <?php if ($goalHalf !== $currentGoalHalf): ?>
                                    <div class="goals-half-title"><?php echo $goalHalf === 2 ? 'Babak 2' : 'Babak 1'; ?></div>
                                    <?php $currentGoalHalf = $goalHalf; ?>
                                <?php endif; ?>
                                <div class="goal-row">
                                    <div class="goal-side team-1-side <?php echo $isTeam1 ? 'active' : ''; ?>">
                                        <?php if ($isTeam1): ?>
                                            <div class="goal-details">
                                                <span class="goal-player-name"><?php echo htmlspecialchars($goal['player_name'] ?? ''); ?></span>
                                                <span class="goal-icon">⚽</span>
                                                <?php if (!empty($goal['jersey_number'])): ?>
                                                    <span class="goal-player-number">(<?php echo htmlspecialchars($goal['jersey_number']); ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="goal-time-pill"><?php echo htmlspecialchars(($goal['minute'] ?? '-') . '"'); ?></div>
                                    <div class="goal-side team-2-side <?php echo !$isTeam1 ? 'active' : ''; ?>">
                                        <?php if (!$isTeam1): ?>
                                            <div class="goal-details">
                                                <span class="goal-player-name"><?php echo htmlspecialchars($goal['player_name'] ?? ''); ?></span>
                                                <span class="goal-icon">⚽</span>
                                                <?php if (!empty($goal['jersey_number'])): ?>
                                                    <span class="goal-player-number">(<?php echo htmlspecialchars($goal['jersey_number']); ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="section-container lineup-section">
                    <div class="section-header">
                        <h2 class="section-title">SUSUNAN PEMAIN TEAM</h2>
                    </div>

                    <!-- TABS FOR HALF SELECTION -->
                    <div class="lineup-tabs">
                        <button class="lineup-tab-btn player-tab-btn active" onclick="switchLineupTab(1)">BABAK 1</button>
                        <button class="lineup-tab-btn player-tab-btn" onclick="switchLineupTab(2)">BABAK 2</button>
                    </div>

                    <!-- CONTENT WRAPPER FOR HALVES -->
                    <div id="lineup-half-1" class="lineup-content-half player-content-half active">
                        <div class="lineups-grid">
                            <!-- Team 1 Half 1 -->
                            <div class="lineup-card">
                                <div class="lineup-card-header">
                                    <div class="lineup-team">
                                        <div class="lineup-team-logo">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($match['team1_name'] ?? ''); ?>"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                        </div>
                                        <div class="lineup-team-info">
                                            <h3 class="lineup-team-name"><?php echo htmlspecialchars($match['team1_name'] ?? ''); ?></h3>
                                            <span class="lineup-count"><?php echo count($lineups['team1']['half1']); ?> pemain</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge">Home</span>
                                </div>

                                <?php if (empty($lineups['team1']['half1'])): ?>
                                    <div class="empty-state">Belum ada susunan pemain babak 1.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($lineups['team1']['half1'] as $player): ?>
                                            <div class="lineup-player">
                                                <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player['photo']; ?>" 
                                                     class="lineup-avatar"
                                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                                                <div class="lineup-info">
                                                    <div class="lineup-name"><?php echo htmlspecialchars($player['player_name'] ?? ''); ?></div>
                                                    <div class="lineup-meta">
                                                        <span class="lineup-number"><?php echo '#' . $player['jersey_number']; ?></span>
                                                        <span class="lineup-position"><?php echo htmlspecialchars($player['position'] ?? ''); ?></span>
                                                        <?php if ($player['is_starting']): ?>
                                                            <span class="lineup-badge">Pemain Utama</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Team 2 Half 1 -->
                            <div class="lineup-card">
                                <div class="lineup-card-header">
                                    <div class="lineup-team">
                                        <div class="lineup-team-logo">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($match['team2_name'] ?? ''); ?>"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                        </div>
                                        <div class="lineup-team-info">
                                            <h3 class="lineup-team-name"><?php echo htmlspecialchars($match['team2_name'] ?? ''); ?></h3>
                                            <span class="lineup-count"><?php echo count($lineups['team2']['half1']); ?> pemain</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge away">Away</span>
                                </div>

                                <?php if (empty($lineups['team2']['half1'])): ?>
                                    <div class="empty-state">Belum ada susunan pemain babak 1.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($lineups['team2']['half1'] as $player): ?>
                                            <div class="lineup-player">
                                                <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player['photo']; ?>" 
                                                     class="lineup-avatar"
                                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                                                <div class="lineup-info">
                                                    <div class="lineup-name"><?php echo htmlspecialchars($player['player_name'] ?? ''); ?></div>
                                                    <div class="lineup-meta">
                                                        <span class="lineup-number"><?php echo '#' . $player['jersey_number']; ?></span>
                                                        <span class="lineup-position"><?php echo htmlspecialchars($player['position'] ?? ''); ?></span>
                                                        <?php if ($player['is_starting']): ?>
                                                            <span class="lineup-badge">Pemain Utama</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div id="lineup-half-2" class="lineup-content-half player-content-half">
                         <div class="lineups-grid">
                            <!-- Team 1 Half 2 -->
                            <div class="lineup-card">
                                <div class="lineup-card-header">
                                    <div class="lineup-team">
                                        <div class="lineup-team-logo">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($match['team1_name'] ?? ''); ?>"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                        </div>
                                        <div class="lineup-team-info">
                                            <h3 class="lineup-team-name"><?php echo htmlspecialchars($match['team1_name'] ?? ''); ?></h3>
                                            <span class="lineup-count"><?php echo count($lineups['team1']['half2']); ?> pemain</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge">Home</span>
                                </div>

                                <?php if (empty($lineups['team1']['half2'])): ?>
                                    <div class="empty-state">Belum ada susunan pemain babak 2.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($lineups['team1']['half2'] as $player): ?>
                                            <div class="lineup-player">
                                                <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player['photo']; ?>" 
                                                     class="lineup-avatar"
                                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                                                <div class="lineup-info">
                                                    <div class="lineup-name"><?php echo htmlspecialchars($player['player_name'] ?? ''); ?></div>
                                                    <div class="lineup-meta">
                                                        <span class="lineup-number"><?php echo '#' . $player['jersey_number']; ?></span>
                                                        <span class="lineup-position"><?php echo htmlspecialchars($player['position'] ?? ''); ?></span>
                                                        <?php if ($player['is_starting']): ?>
                                                            <span class="lineup-badge">Pemain Utama</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Team 2 Half 2 -->
                            <div class="lineup-card">
                                <div class="lineup-card-header">
                                    <div class="lineup-team">
                                        <div class="lineup-team-logo">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>" 
                                                 alt="<?php echo htmlspecialchars($match['team2_name'] ?? ''); ?>"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                        </div>
                                        <div class="lineup-team-info">
                                            <h3 class="lineup-team-name"><?php echo htmlspecialchars($match['team2_name'] ?? ''); ?></h3>
                                            <span class="lineup-count"><?php echo count($lineups['team2']['half2']); ?> pemain</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge away">Away</span>
                                </div>

                                <?php if (empty($lineups['team2']['half2'])): ?>
                                    <div class="empty-state">Belum ada susunan pemain babak 2.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($lineups['team2']['half2'] as $player): ?>
                                            <div class="lineup-player">
                                                <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player['photo']; ?>" 
                                                     class="lineup-avatar"
                                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/players/default-player.jpg'">
                                                <div class="lineup-info">
                                                    <div class="lineup-name"><?php echo htmlspecialchars($player['player_name'] ?? ''); ?></div>
                                                    <div class="lineup-meta">
                                                        <span class="lineup-number"><?php echo '#' . $player['jersey_number']; ?></span>
                                                        <span class="lineup-position"><?php echo htmlspecialchars($player['position'] ?? ''); ?></span>
                                                        <?php if ($player['is_starting']): ?>
                                                            <span class="lineup-badge">Pemain Utama</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <?php if ($resolvedSource === 'challenge' && $hasMatchStaffAssignmentsTable): ?>
                <section class="section-container lineup-section">
                    <div class="section-header">
                        <h2 class="section-title">LINEUP STAFF TEAM</h2>
                    </div>
                    <div class="lineup-tabs">
                        <button class="lineup-tab-btn staff-tab-btn active" onclick="switchStaffTab(1)">BABAK 1</button>
                        <button class="lineup-tab-btn staff-tab-btn" onclick="switchStaffTab(2)">BABAK 2</button>
                    </div>

                    <div id="staff-half-1" class="staff-content-half active">
                        <div class="lineups-grid">
                            <div class="lineup-card">
                                <div class="lineup-card-header">
                                    <div class="lineup-team">
                                        <div class="lineup-team-logo">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>"
                                                 alt="<?php echo htmlspecialchars($match['team1_name'] ?? ''); ?>"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                        </div>
                                        <div class="lineup-team-info">
                                            <h3 class="lineup-team-name"><?php echo htmlspecialchars($match['team1_name'] ?? ''); ?></h3>
                                            <span class="lineup-count"><?php echo count($staffLineups['team1']['half1']); ?> staff</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge">Home</span>
                                </div>
                                <?php if (empty($staffLineups['team1']['half1'])): ?>
                                    <div class="empty-state">Belum ada staff ditetapkan untuk babak 1.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($staffLineups['team1']['half1'] as $staff): ?>
                                            <?php $staffPhoto = resolveMatchStaffPhoto($staff['photo'] ?? ''); ?>
                                            <div class="lineup-player">
                                                <?php if ($staffPhoto['found']): ?>
                                                    <img src="<?php echo htmlspecialchars($staffPhoto['url']); ?>"
                                                         class="lineup-avatar"
                                                         alt="<?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : 'Staff'); ?>"
                                                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="lineup-avatar" style="display:none;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="lineup-avatar" style="display:flex;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="lineup-info">
                                                    <div class="lineup-name"><?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : '-'); ?></div>
                                                    <div class="lineup-meta">
                                                        <span class="lineup-position"><?php echo htmlspecialchars(formatStaffRoleLabel($staff['role'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="lineup-card">
                                <div class="lineup-card-header">
                                    <div class="lineup-team">
                                        <div class="lineup-team-logo">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>"
                                                 alt="<?php echo htmlspecialchars($match['team2_name'] ?? ''); ?>"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                        </div>
                                        <div class="lineup-team-info">
                                            <h3 class="lineup-team-name"><?php echo htmlspecialchars($match['team2_name'] ?? ''); ?></h3>
                                            <span class="lineup-count"><?php echo count($staffLineups['team2']['half1']); ?> staff</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge away">Away</span>
                                </div>
                                <?php if (empty($staffLineups['team2']['half1'])): ?>
                                    <div class="empty-state">Belum ada staff ditetapkan untuk babak 1.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($staffLineups['team2']['half1'] as $staff): ?>
                                            <?php $staffPhoto = resolveMatchStaffPhoto($staff['photo'] ?? ''); ?>
                                            <div class="lineup-player">
                                                <?php if ($staffPhoto['found']): ?>
                                                    <img src="<?php echo htmlspecialchars($staffPhoto['url']); ?>"
                                                         class="lineup-avatar"
                                                         alt="<?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : 'Staff'); ?>"
                                                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="lineup-avatar" style="display:none;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="lineup-avatar" style="display:flex;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="lineup-info">
                                                    <div class="lineup-name"><?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : '-'); ?></div>
                                                    <div class="lineup-meta">
                                                        <span class="lineup-position"><?php echo htmlspecialchars(formatStaffRoleLabel($staff['role'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div id="staff-half-2" class="staff-content-half">
                        <div class="lineups-grid">
                            <div class="lineup-card">
                                <div class="lineup-card-header">
                                    <div class="lineup-team">
                                        <div class="lineup-team-logo">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team1_logo']; ?>"
                                                 alt="<?php echo htmlspecialchars($match['team1_name'] ?? ''); ?>"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                        </div>
                                        <div class="lineup-team-info">
                                            <h3 class="lineup-team-name"><?php echo htmlspecialchars($match['team1_name'] ?? ''); ?></h3>
                                            <span class="lineup-count"><?php echo count($staffLineups['team1']['half2']); ?> staff</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge">Home</span>
                                </div>
                                <?php if (empty($staffLineups['team1']['half2'])): ?>
                                    <div class="empty-state">Belum ada staff ditetapkan untuk babak 2.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($staffLineups['team1']['half2'] as $staff): ?>
                                            <?php $staffPhoto = resolveMatchStaffPhoto($staff['photo'] ?? ''); ?>
                                            <div class="lineup-player">
                                                <?php if ($staffPhoto['found']): ?>
                                                    <img src="<?php echo htmlspecialchars($staffPhoto['url']); ?>"
                                                         class="lineup-avatar"
                                                         alt="<?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : 'Staff'); ?>"
                                                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="lineup-avatar" style="display:none;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="lineup-avatar" style="display:flex;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="lineup-info">
                                                    <div class="lineup-name"><?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : '-'); ?></div>
                                                    <div class="lineup-meta">
                                                        <span class="lineup-position"><?php echo htmlspecialchars(formatStaffRoleLabel($staff['role'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="lineup-card">
                                <div class="lineup-card-header">
                                    <div class="lineup-team">
                                        <div class="lineup-team-logo">
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $match['team2_logo']; ?>"
                                                 alt="<?php echo htmlspecialchars($match['team2_name'] ?? ''); ?>"
                                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                        </div>
                                        <div class="lineup-team-info">
                                            <h3 class="lineup-team-name"><?php echo htmlspecialchars($match['team2_name'] ?? ''); ?></h3>
                                            <span class="lineup-count"><?php echo count($staffLineups['team2']['half2']); ?> staff</span>
                                        </div>
                                    </div>
                                    <span class="team-side-badge away">Away</span>
                                </div>
                                <?php if (empty($staffLineups['team2']['half2'])): ?>
                                    <div class="empty-state">Belum ada staff ditetapkan untuk babak 2.</div>
                                <?php else: ?>
                                    <div class="lineup-list">
                                        <?php foreach ($staffLineups['team2']['half2'] as $staff): ?>
                                            <?php $staffPhoto = resolveMatchStaffPhoto($staff['photo'] ?? ''); ?>
                                            <div class="lineup-player">
                                                <?php if ($staffPhoto['found']): ?>
                                                    <img src="<?php echo htmlspecialchars($staffPhoto['url']); ?>"
                                                         class="lineup-avatar"
                                                         alt="<?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : 'Staff'); ?>"
                                                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="lineup-avatar" style="display:none;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="lineup-avatar" style="display:flex;align-items:center;justify-content:center;background:#e2e8f0;color:#475569;">
                                                        <i class="fas fa-user-tie"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="lineup-info">
                                                    <div class="lineup-name"><?php echo htmlspecialchars($staff['name'] !== '' ? $staff['name'] : '-'); ?></div>
                                                    <div class="lineup-meta">
                                                        <span class="lineup-position"><?php echo htmlspecialchars(formatStaffRoleLabel($staff['role'])); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($resolvedSource === 'challenge'): ?>
                <section class="section-container lineup-section">
                    <div class="section-header">
                        <h2 class="section-title">STATUS SUSPEND PEMAIN MATCH INI</h2>
                    </div>
                    <?php if (!$hasPlayerEventCardsTable): ?>
                        <div class="empty-state">Tabel data kartu pemain belum tersedia.</div>
                    <?php elseif (empty($matchSuspendRows)): ?>
                        <div class="empty-state">Tidak ada data kartu pemain untuk pertandingan ini.</div>
                    <?php else: ?>
                        <div class="match-suspend-wrap">
                            <table class="match-suspend-table">
                                <thead>
                                    <tr>
                                        <th>Pemain</th>
                                        <th>Team</th>
                                        <th>Kuning</th>
                                        <th>Merah</th>
                                        <th>Hijau</th>
                                        <th>Suspend Sampai</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($matchSuspendRows as $card): ?>
                                        <?php
                                        $suspendUntil = !empty($card['suspension_until']) ? $card['suspension_until'] : null;
                                        $isSuspended = $suspendUntil && $suspendUntil >= date('Y-m-d');
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($card['player_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($card['team_name'] ?? '-'); ?></td>
                                            <td><span class="card-badge yellow"><?php echo (int)($card['yellow_cards'] ?? 0); ?></span></td>
                                            <td><span class="card-badge red"><?php echo (int)($card['red_cards'] ?? 0); ?></span></td>
                                            <td><span class="card-badge green"><?php echo (int)($card['green_cards'] ?? 0); ?></span></td>
                                            <td><?php echo $suspendUntil ? htmlspecialchars(date('d M Y', strtotime($suspendUntil))) : '-'; ?></td>
                                            <td>
                                                <span class="suspend-pill <?php echo $isSuspended ? 'active' : 'done'; ?>">
                                                    <?php echo $isSuspended ? 'Tidak Boleh Main' : 'Boleh Main'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>

<?php include 'includes/footer.php'; ?>
