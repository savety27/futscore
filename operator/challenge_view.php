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

if ($operator_id > 0) {
    try {
        $stmtOperator = $conn->prepare("
            SELECT au.event_id, e.name AS event_name, e.image AS event_image
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
        $_SESSION['event_id'] = $operator_event_id > 0 ? $operator_event_id : null;
    } catch (PDOException $e) {
        // keep defaults
    }
}


// Get challenge ID
$challenge_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($challenge_id <= 0) {
    header("Location: challenge.php");
    exit;
}

// Fetch challenge data with all details
try {
    $event_select = $can_join_event_name ? "e.name as event_name," : "NULL as event_name,";
    $event_join = $can_join_event_name ? "LEFT JOIN events e ON c.event_id = e.id" : "";
    $operator_scope_where = '';
    $query_params = [$challenge_id];

    if ($can_join_event_name) {
        if ($operator_event_id > 0) {
            $operator_scope_where = " AND c.event_id = ?";
            $query_params[] = $operator_event_id;
        } else {
            $operator_scope_where = " AND 1=0";
        }
    }

    $stmt = $conn->prepare("
        SELECT c.*,
        {$event_select}
        t1.name as challenger_name, t1.logo as challenger_logo, t1.sport_type as challenger_sport, t1.coach as challenger_coach,
        t2.name as opponent_name, t2.logo as opponent_logo, t2.sport_type as opponent_sport, t2.coach as opponent_coach,
        l.name as venue_name, l.location as venue_location, l.capacity as venue_capacity
        FROM challenges c
        {$event_join}
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
    
} catch (PDOException $e) {
    die("Error fetching challenge data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Challenge - Futscore</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="../pelatih/css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../pelatih/css/style.css'); ?>">
<link rel="stylesheet" href="css/challenge.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/challenge.css'); ?>">
<style>
/* Keeping only page-specific unique styles for View Challenge */
:root {
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Specific UI for View Details */
.info-card {
    background: var(--heritage-card);
    border: 1px solid var(--heritage-border);
    border-radius: 28px;
    padding: 40px;
    margin-bottom: 40px;
    box-shadow: var(--soft-shadow);
}

.vs-card {
    display: flex;
    justify-content: space-around;
    align-items: center;
    padding: 50px 20px;
    text-align: center;
}

.team-brand { flex: 1; max-width: 300px; }

.team-logo-wrap {
    width: 140px;
    height: 140px;
    margin: 0 auto 20px;
    position: relative;
}

.team-logo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 30px;
    border: 1px solid var(--heritage-border);
    box-shadow: var(--card-shadow);
    background: white;
}

.team-logo-placeholder {
    width: 100%;
    height: 100%;
    background: var(--heritage-bg);
    border-radius: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: var(--heritage-text-muted);
    border: 1px solid var(--heritage-border);
}

.team-name {
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--heritage-text);
    margin-bottom: 8px;
}

.team-meta {
    font-size: 0.9rem;
    color: var(--heritage-text-muted);
    font-weight: 600;
}

.vs-divider {
    padding: 0 40px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
}

.vs-circle {
    width: 70px;
    height: 70px;
    background: var(--heritage-text);
    color: var(--heritage-gold);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 800;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.score-text {
    font-family: var(--font-display);
    font-size: 4rem;
    font-weight: 800;
    color: var(--heritage-text);
    letter-spacing: -0.05em;
    line-height: 1;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 32px;
}

.info-item {
    padding-bottom: 20px;
    border-bottom: 1px solid var(--heritage-border);
}

.info-label {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--heritage-text-muted);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-label i { color: var(--heritage-gold); font-size: 0.9rem; }

.info-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--heritage-text);
    line-height: 1.4;
}

.badge-premium {
    display: inline-flex;
    padding: 4px 16px;
    border-radius: 100px;
    font-size: 0.85rem;
    font-weight: 700;
    font-family: var(--font-display);
}

.badge-open { background: #eff6ff; color: #1d4ed8; }
.badge-accepted { background: #ecfdf5; color: #047857; }
.badge-rejected { background: #fef2f2; color: #b91c1c; }
.badge-expired { background: #f9fafb; color: #374151; }
.badge-completed { background: #fffbeb; color: #b45309; }

/* Timeline */
.timeline-container { display: flex; flex-direction: column; gap: 24px; }

.timeline-item {
    display: flex;
    gap: 20px;
    align-items: flex-start;
}

.timeline-icon {
    width: 44px;
    height: 44px;
    background: var(--heritage-bg);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--heritage-text);
    font-size: 1.1rem;
    flex-shrink: 0;
    border: 1px solid var(--heritage-border);
}

.timeline-content h4 {
    font-family: var(--font-display);
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 4px;
}

.timeline-content p {
    font-size: 0.9rem;
    color: var(--heritage-text-muted);
}

/* Animations */
@keyframes revealUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.reveal { animation: revealUp 0.8s cubic-bezier(0.23, 1, 0.32, 1) forwards; opacity: 0; }
.d-1 { animation-delay: 0.1s; }
.d-2 { animation-delay: 0.2s; }
.d-3 { animation-delay: 0.3s; }

/* Mobile View Adjustments for VS Card */
@media (max-width: 768px) {
    .main { padding: 20px !important; }
    
    .dashboard-hero { 
        flex-direction: column; 
        align-items: flex-start; 
        text-align: left;
        padding-bottom: 24px;
        margin-bottom: 32px;
    }
    
    .hero-title { font-size: 2.2rem; }
    
    .hero-actions { 
        width: 100%; 
        display: flex; 
        flex-direction: column; 
        gap: 12px; 
    }
    
    .hero-actions .btn-premium { 
        width: 100%; 
        justify-content: center; 
    }

    .info-card { padding: 24px; margin-bottom: 24px; border-radius: 20px; }
    
    .vs-card { 
        padding: 32px 16px; 
        flex-direction: column; 
        gap: 24px; 
    }
    
    .vs-divider { padding: 0; gap: 10px; }
    
    .team-logo-wrap { width: 100px; height: 100px; margin-bottom: 12px; }
    
    .team-name { font-size: 1.25rem; }
    
    .vs-circle { width: 50px; height: 50px; font-size: 1.1rem; }
    
    .score-text { font-size: 2.5rem; }
    
    .info-grid { gap: 20px; grid-template-columns: 1fr; }
    
    .info-item { padding-bottom: 15px; }
    
    .info-value { font-size: 1rem; }
    
    .section-title { font-size: 1.5rem; }
    
    .timeline-item { gap: 15px; }
    
    .timeline-icon { width: 36px; height: 36px; font-size: 0.9rem; }
}

@media (max-width: 480px) {
    .hero-title { font-size: 1.8rem; }
    .score-text { font-size: 2rem; }
    .team-logo-wrap { width: 80px; height: 80px; }
    .info-card { padding: 20px; }
}
</style>
</head>
<body>
<div class="menu-overlay"></div>
<button class="mobile-menu-toggle" aria-label="Toggle menu">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar reveal">
            <div class="greeting">
                <h1>Detail Challenge 🏆</h1>
                <p>Informasi lengkap mengenai tantangan pertandingan</p>
            </div>
            
            <div class="user-actions">
                <a href="../operator/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Keluar
                </a>
            </div>
        </div>

        <div class="challenge-container">
            
            <!-- HEADER HERO -->
            <header class="dashboard-hero reveal">
                <div class="hero-content">
                    <span class="hero-label">Challenge Overview #<?php echo htmlspecialchars($challenge_data['id']); ?></span>
                    <h1 class="hero-title"><?php echo htmlspecialchars($challenge_data['challenge_code']); ?></h1>
                    <p class="hero-description">
                        Manajemen dan detail informasi pertandingan antara <strong><?php echo htmlspecialchars($challenge_data['challenger_name']); ?></strong> 
                        melawan <strong><?php echo htmlspecialchars($challenge_data['opponent_name']); ?></strong>.
                    </p>
                </div>
                <div class="hero-actions">
                    <a href="challenge.php" class="btn-premium btn-outline">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <?php if ($challenge_data['status'] == 'open'): ?>
                    <a href="challenge_edit.php?id=<?php echo $challenge_id; ?>" class="btn-premium btn-primary">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <?php endif; ?>
                    <?php if ($challenge_data['status'] == 'accepted' && empty($challenge_data['challenger_score'])): ?>
                    <a href="challenge_result.php?id=<?php echo $challenge_id; ?>" class="btn-premium btn-success">
                        <i class="fas fa-check-circle"></i> Input Hasil
                    </a>
                    <?php endif; ?>
                </div>
            </header>

            <!-- MATCH OVERVIEW SECTION -->
            <div class="reveal d-1">
                <div class="section-header">
                    <div class="section-title-wrap">
                        <h2 class="section-title">Overview Pertandingan</h2>
                        <div class="section-line"></div>
                    </div>
                </div>

                <div class="info-card vs-card">
                    <!-- Challenger -->
                    <div class="team-brand">
                        <div class="team-logo-wrap">
                            <?php if (!empty($challenge_data['challenger_logo'])): ?>
                                <img src="../images/teams/<?php echo htmlspecialchars($challenge_data['challenger_logo']); ?>" class="team-logo">
                            <?php else: ?>
                                <div class="team-logo-placeholder"><i class="fas fa-shield-alt"></i></div>
                            <?php endif; ?>
                        </div>
                        <h3 class="team-name"><?php echo htmlspecialchars($challenge_data['challenger_name']); ?></h3>
                        <div class="team-meta">Coach: <?php echo htmlspecialchars($challenge_data['challenger_coach']); ?></div>
                    </div>

                    <!-- VS / Score -->
                    <div class="vs-divider">
                        <div class="vs-circle">VS</div>
                        <?php if (!empty($challenge_data['challenger_score']) || !empty($challenge_data['opponent_score'])): ?>
                        <div class="score-text">
                            <?php echo (int)$challenge_data['challenger_score']; ?> : <?php echo (int)$challenge_data['opponent_score']; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Opponent -->
                    <div class="team-brand">
                        <div class="team-logo-wrap">
                            <?php if (!empty($challenge_data['opponent_logo'])): ?>
                                <img src="../images/teams/<?php echo htmlspecialchars($challenge_data['opponent_logo']); ?>" class="team-logo">
                            <?php else: ?>
                                <div class="team-logo-placeholder"><i class="fas fa-shield-alt"></i></div>
                            <?php endif; ?>
                        </div>
                        <h3 class="team-name"><?php echo htmlspecialchars($challenge_data['opponent_name']); ?></h3>
                        <div class="team-meta">Coach: <?php echo htmlspecialchars($challenge_data['opponent_coach']); ?></div>
                    </div>
                </div>
            </div>

            <!-- CHALLENGE DETAILS -->
            <div class="reveal d-2">
                <div class="section-header">
                    <div class="section-title-wrap">
                        <h2 class="section-title">Informasi Detail</h2>
                        <div class="section-line"></div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-info-circle"></i> Status Challenge</div>
                            <div class="info-value">
                                <span class="badge-premium badge-<?php echo strtolower($challenge_data['status']); ?>">
                                    <?php echo strtoupper($challenge_data['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-map-marker-alt"></i> Venue / Lokasi</div>
                            <div class="info-value">
                                <?php echo htmlspecialchars($challenge_data['venue_name']); ?><br>
                                <small style="color: var(--heritage-text-muted);"><?php echo htmlspecialchars($challenge_data['venue_location']); ?></small>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-calendar-alt"></i> Jadwal Kick-off</div>
                            <div class="info-value"><?php echo date('d F Y', strtotime($challenge_data['challenge_date'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-clock"></i> Waktu Pertandingan</div>
                            <div class="info-value"><?php echo date('H:i', strtotime($challenge_data['challenge_date'])); ?> WIB</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-running"></i> Kategori Olahraga</div>
                            <div class="info-value"><?php echo htmlspecialchars($challenge_data['sport_type'] ?? '-'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-id-badge"></i> Pengawas Pertandingan</div>
                            <div class="info-value"><?php echo htmlspecialchars($challenge_data['match_official'] ?: '-'); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-hourglass-end"></i> Masa Berlaku</div>
                            <div class="info-value"><?php echo date('d F Y, H:i', strtotime($challenge_data['expiry_date'])); ?></div>
                        </div>
                        <?php if ($can_join_event_name && !empty($challenge_data['event_name'])): ?>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-trophy"></i> Turnamen / Event</div>
                            <div class="info-value"><?php echo htmlspecialchars($challenge_data['event_name']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($challenge_data['notes'])): ?>
                    <div style="margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--heritage-border);">
                        <div class="info-label"><i class="fas fa-sticky-note"></i> Catatan Challenge</div>
                        <div class="info-value" style="font-style: italic; color: var(--heritage-text-muted);">
                            "<?php echo nl2br(htmlspecialchars($challenge_data['notes'])); ?>"
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RESULTS & TIMELINE -->
            <div class="reveal d-3">
                <div class="section-header">
                    <div class="section-title-wrap">
                        <h2 class="section-title">Hasil & Riwayat</h2>
                        <div class="section-line"></div>
                    </div>
                </div>

                <div class="info-grid">
                    <!-- Results Card -->
                    <?php if (!empty($challenge_data['challenger_score']) || !empty($challenge_data['opponent_score'])): ?>
                    <div class="info-card" style="margin-bottom: 0;">
                        <h4 style="font-family: var(--font-display); margin-bottom: 24px;">Ringkasan Hasil</h4>
                        <div class="info-item" style="border: none;">
                            <div class="info-label">Pemenang Pertandingan</div>
                            <div class="info-value">
                                <?php 
                                if ($challenge_data['winner_team_id'] == $challenge_data['challenger_id']) {
                                    echo '<i class="fas fa-crown" style="color:var(--heritage-gold)"></i> ' . htmlspecialchars($challenge_data['challenger_name']);
                                } elseif ($challenge_data['winner_team_id'] == $challenge_data['opponent_id']) {
                                    echo '<i class="fas fa-crown" style="color:var(--heritage-gold)"></i> ' . htmlspecialchars($challenge_data['opponent_name']);
                                } else {
                                    echo 'Pertandingan Seri (Draw)';
                                }
                                ?>
                            </div>
                        </div>
                        <?php if (!empty($challenge_data['match_notes'])): ?>
                        <div style="margin-top: 16px; font-size: 0.9rem; color: var(--heritage-text-muted);">
                            <strong>Match Report:</strong> <?php echo htmlspecialchars($challenge_data['match_notes']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Timeline Card -->
                    <div class="info-card" style="margin-bottom: 0;">
                        <h4 style="font-family: var(--font-display); margin-bottom: 24px;">Timeline Activity</h4>
                        <div class="timeline-container">
                            <div class="timeline-item">
                                <div class="timeline-icon"><i class="fas fa-plus"></i></div>
                                <div class="timeline-content">
                                    <h4>Challenge Dibuat</h4>
                                    <p><?php echo date('d M Y, H:i', strtotime($challenge_data['created_at'])); ?></p>
                                </div>
                            </div>
                            <?php if ($challenge_data['updated_at'] != $challenge_data['created_at']): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon"><i class="fas fa-history"></i></div>
                                <div class="timeline-content">
                                    <h4>Update Terakhir</h4>
                                    <p><?php echo date('d M Y, H:i', strtotime($challenge_data['updated_at'])); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($challenge_data['result_entered_at'])): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon" style="background: var(--heritage-accent); color: white;"><i class="fas fa-flag-checkered"></i></div>
                                <div class="timeline-content">
                                    <h4>Hasil Selesai</h4>
                                    <p><?php echo date('d M Y, H:i', strtotime($challenge_data['result_entered_at'])); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>