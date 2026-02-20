<?php
session_start();

$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

function ensure_events_active_column(PDO $conn) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM events LIKE 'is_active'");
        $exists = $check && $check->fetch(PDO::FETCH_ASSOC);
        if (!$exists) {
            $conn->exec("ALTER TABLE events ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER registration_status");
            try {
                $conn->exec("CREATE INDEX idx_is_active ON events (is_active)");
            } catch (PDOException $e) {
                // Index may already exist.
            }
        }
    } catch (PDOException $e) {
        // Keep page running. Read query below will fail with message if table is inaccessible.
    }
}

ensure_events_active_column($conn);

function hasColumnEventView(PDO $conn, $tableName, $columnName) {
    try {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tableName);
        if ($safeTable === '') {
            return false;
        }
        $quotedColumn = $conn->quote((string) $columnName);
        $stmt = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quotedColumn}");
        return $stmt && $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

$admin_email = $_SESSION['admin_email'] ?? '';
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($event_id <= 0) {
    $_SESSION['error_message'] = 'ID event tidak valid.';
    header('Location: event.php');
    exit;
}

$event = null;
$error = '';
$eventParticipantGroups = [];
$eventParticipantTotal = 0;
$eventDataReady = false;
try {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Data event tidak dapat dimuat saat ini. Silakan coba lagi.';
}

if (!$event && $error === '') {
    $_SESSION['error_message'] = 'Data event tidak ditemukan.';
    header('Location: event.php');
    exit;
}

if (!empty($event)) {
    $hasChallengeEventId = hasColumnEventView($conn, 'challenges', 'event_id');
    if ($hasChallengeEventId) {
        try {
            $categoryStmt = $conn->prepare("SELECT sport_type AS category_name, COUNT(*) AS total_matches
                                            FROM challenges
                                            WHERE event_id = ?
                                              AND sport_type IS NOT NULL
                                              AND sport_type <> ''
                                            GROUP BY sport_type
                                            ORDER BY sport_type ASC");
            $categoryStmt->execute([$event_id]);
            $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
            $participantTotalRows = 0;

            foreach ($categories as $categoryRow) {
                $categoryName = trim((string)($categoryRow['category_name'] ?? ''));
                if ($categoryName === '') {
                    continue;
                }

                $teamStmt = $conn->prepare("SELECT t.id, t.name, t.logo
                                            FROM teams t
                                            INNER JOIN (
                                                SELECT challenger_id AS team_id
                                                FROM challenges
                                                WHERE event_id = ? AND sport_type = ?
                                                UNION
                                                SELECT opponent_id AS team_id
                                                FROM challenges
                                                WHERE event_id = ? AND sport_type = ?
                                            ) participant ON participant.team_id = t.id
                                            ORDER BY t.name ASC");
                $teamStmt->execute([$event_id, $categoryName, $event_id, $categoryName]);
                $teams = $teamStmt->fetchAll(PDO::FETCH_ASSOC);

                $participantTotalRows += count($teams);

                $eventParticipantGroups[] = [
                    'category_name' => $categoryName,
                    'total_matches' => (int)($categoryRow['total_matches'] ?? 0),
                    'teams' => $teams
                ];
            }

            $eventParticipantTotal = $participantTotalRows;
            $eventDataReady = true;
        } catch (PDOException $e) {
            $eventDataReady = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Event</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<style>
:root {
    --primary: #0f2744;
    --secondary: #f59e0b;
    --accent: #3b82f6;
    --danger: #ef4444;
    --success: #10b981;
    --dark: #1e293b;
    --gray: #64748b;
    --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
    --premium-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --transition: cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%);
    color: var(--dark);
}
.wrapper { display: flex; min-height: 100vh; }
.logo-container { position: relative; display: inline-block; }
.logo:hover { transform: none; box-shadow: none; }
.logo img {
    width: 100%;
    height: auto;
    max-width: 200px;
    filter: brightness(1.1) drop-shadow(0 0 15px rgba(255, 255, 255, 0.1));
    transition: transform var(--transition), filter var(--transition);
}
.logo img:hover { transform: scale(1.05); }
.academy-info { text-align: center; }
.academy-email { font-size: 14px; opacity: 0.9; color: rgba(255,255,255,0.8); }
.menu { padding: 25px 15px; }
.menu-item { margin-bottom: 8px; border-radius: 12px; overflow: hidden; }
.menu-link {
    display: flex;
    align-items: center;
    color: rgba(255,255,255,0.75);
    text-decoration: none;
    padding: 14px 20px;
    border-radius: 12px;
    transition: var(--transition);
    margin: 4px 0;
}
.menu-link:hover { background: rgba(255,255,255,0.1); color: #fff; transform: translateX(5px); }
.menu-link.active {
    color: var(--secondary);
    background: linear-gradient(90deg, rgba(245,158,11,0.15) 0%, rgba(245,158,11,0.02) 100%);
    border-right: 4px solid var(--secondary);
    border-radius: 12px 0 0 12px;
    font-weight: 700;
}
.menu-icon { font-size: 18px; margin-right: 15px; width: 24px; text-align: center; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); }
.menu-text { flex: 1; font-size: 15px; letter-spacing: 0.3px; }
.menu-arrow { font-size: 12px; opacity: 0.6; transition: var(--transition); }
.menu-arrow.rotate { transform: rotate(90deg); opacity: 1; }
.submenu { max-height: 0; overflow: hidden; transition: max-height 0.4s ease-in-out; background: rgba(0,0,0,0.2); border-radius: 0 0 12px 12px; }
.submenu.open { max-height: 300px; }
.submenu-item { padding: 5px 15px 5px 70px; }
.submenu-link { display: block; color: rgba(255,255,255,0.7); padding: 12px 15px; border-radius: 8px; text-decoration: none; font-size: 14px; transition: var(--transition); position: relative; }
.submenu-link.active, .submenu-link:hover { color: var(--secondary); background: rgba(245,158,11,0.1); padding-left: 20px; }
.submenu-link::before { content: "•"; position: absolute; left: 0; color: var(--secondary); font-size: 18px; }
.main { margin-left: 280px; flex: 1; padding: 28px; }
.topbar, .page-header, .detail-container, .detail-description { background: #fff; border-radius: 18px; box-shadow: var(--card-shadow); }
.topbar { padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; animation: slideDown 0.5s ease-out; }
.greeting h1 { color: var(--primary); font-size: 26px; }
.greeting p { color: var(--gray); font-size: 14px; }
.logout-btn { display: inline-flex; gap: 8px; align-items: center; background: linear-gradient(135deg, var(--danger), #b91c1c); color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 10px; font-weight: 600; }
.page-header { margin-bottom: 22px; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; gap: 14px; }
.page-title { display: flex; align-items: center; gap: 10px; color: var(--primary); font-size: 25px; }
.btn { border: none; border-radius: 10px; padding: 11px 18px; font-weight: 600; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
.btn-primary { background: linear-gradient(135deg, var(--primary), var(--accent)); color: #fff; }
.btn-secondary { background: #6b7280; color: #fff; }
.detail-container { padding: 24px; margin-bottom: 18px; display: grid; grid-template-columns: 280px 1fr; gap: 24px; }
.event-image {
    width: 100%;
    max-width: 280px;
    aspect-ratio: 1/1;
    border-radius: 14px;
    object-fit: contain;
    object-position: center;
    border: 2px solid #e5e7eb;
    background: #f8fafc;
    padding: 6px;
}
.event-placeholder { width: 100%; max-width: 280px; aspect-ratio: 1/1; border-radius: 14px; border: 2px solid #e5e7eb; display:flex;align-items:center;justify-content:center;color:#64748b;background:#f1f5f9; font-size: 34px; }
.detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.detail-item { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px; }
.detail-label { font-size: 12px; color: #64748b; margin-bottom: 5px; }
.detail-value { font-size: 15px; color: #0f172a; font-weight: 600; }
.badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-block; }
.badge-open { background: rgba(16, 185, 129, 0.15); color: #047857; }
.badge-close { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }
.badge-active { background: rgba(16, 185, 129, 0.15); color: #047857; }
.badge-inactive { background: rgba(100, 116, 139, 0.15); color: #334155; }
.detail-description { padding: 22px; }
.desc-title { color: var(--primary); font-size: 20px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
.desc-body { color: #1f2937; line-height: 1.6; white-space: pre-wrap; }
.participant-section { margin-top: 18px; background: #fff; border-radius: 18px; box-shadow: var(--card-shadow); padding: 22px; }
.participant-title { color: var(--primary); font-size: 20px; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
.participant-note { color: #64748b; font-size: 13px; margin-bottom: 14px; }
.participant-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
.participant-card { border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background: #fff; }
.participant-head { background: #0f172a; color: #fff; padding: 10px 12px; font-weight: 700; font-size: 14px; }
.participant-table { width: 100%; border-collapse: collapse; }
.participant-table th, .participant-table td { padding: 8px 10px; border-bottom: 1px solid #edf2f7; font-size: 13px; }
.participant-table th { text-align: left; background: #f8fafc; }
.team-row-mini { display: flex; align-items: center; gap: 8px; }
.team-logo-mini { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid #dbe6f3; }
.team-info-link { color: #2563eb; text-decoration: none; }
.alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-danger { background: rgba(211, 47, 47, 0.1); border-left: 4px solid #ef4444; color: #b91c1c; }
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@media (max-width: 900px) {
    .main { margin-left: 0; }
    .detail-container { grid-template-columns: 1fr; }
    .detail-grid { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>
<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo">
                    <img src="../images/alvetrix.png" alt="Logo">
                </div>
            </div>
            <div class="academy-info">
                <div class="academy-name"><?php echo htmlspecialchars($academy_name); ?></div>
                <div class="academy-email"><?php echo htmlspecialchars($email); ?></div>
            </div>
        </div>
        <div class="menu">
            <?php foreach ($menu_items as $key => $item): ?>
            <?php
                $isActive = false;
                $isSubmenuOpen = false;
                if ($item['submenu']) {
                    foreach ($item['items'] as $subUrl) {
                        if ($current_page === $subUrl) {
                            $isActive = true;
                            $isSubmenuOpen = true;
                            break;
                        }
                    }
                } else {
                    if ($key === 'event') {
                        $isActive = in_array($current_page, ['event.php', 'event_create.php', 'event_edit.php', 'event_view.php'], true);
                    } else {
                        $isActive = ($current_page === $item['url']);
                    }
                }
            ?>
            <div class="menu-item">
                <a href="<?php echo $item['submenu'] ? '#' : $item['url']; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>" data-menu="<?php echo $key; ?>">
                    <span class="menu-icon"><?php echo $item['icon']; ?></span>
                    <span class="menu-text"><?php echo $item['name']; ?></span>
                    <?php if ($item['submenu']): ?>
                    <span class="menu-arrow <?php echo $isSubmenuOpen ? 'rotate' : ''; ?>">›</span>
                    <?php endif; ?>
                </a>
                <?php if ($item['submenu']): ?>
                <div class="submenu <?php echo $isSubmenuOpen ? 'open' : ''; ?>" id="submenu-<?php echo $key; ?>">
                    <?php foreach ($item['items'] as $subKey => $subUrl): ?>
                    <div class="submenu-item">
                        <a href="<?php echo $subUrl; ?>" class="submenu-link <?php echo $current_page === $subUrl ? 'active' : ''; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $subKey)); ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="main">
        <div class="topbar">
            <div class="greeting">
                <h1>Event Details 🗓️</h1>
                <p>Informasi lengkap event</p>
            </div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="page-header">
            <div class="page-title"><i class="fas fa-calendar-check"></i> <span>Lihat Data Event</span></div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="event.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                <?php if (!empty($event['id'])): ?>
                <a href="event_edit.php?id=<?php echo (int) $event['id']; ?>" class="btn btn-primary"><i class="fas fa-pen"></i> Edit Event</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php elseif (!empty($event)): ?>
        <div class="detail-container">
            <div>
                <?php if (!empty($event['image'])): ?>
                    <img src="../images/events/<?php echo htmlspecialchars($event['image']); ?>" class="event-image" alt="Event Image">
                <?php else: ?>
                    <div class="event-placeholder"><i class="fas fa-image"></i></div>
                <?php endif; ?>
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Nama Event</div>
                    <div class="detail-value"><?php echo htmlspecialchars($event['name'] ?? '-'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Slug</div>
                    <div class="detail-value"><?php echo htmlspecialchars($event['slug'] ?? '-'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Kategori</div>
                    <div class="detail-value"><?php echo htmlspecialchars($event['category'] ?? '-'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Lokasi</div>
                    <div class="detail-value"><?php echo htmlspecialchars($event['location'] ?? '-'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Tanggal Mulai</div>
                    <div class="detail-value"><?php echo !empty($event['start_date']) ? htmlspecialchars(date('d M Y', strtotime($event['start_date']))) : '-'; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Tanggal Selesai</div>
                    <div class="detail-value"><?php echo !empty($event['end_date']) ? htmlspecialchars(date('d M Y', strtotime($event['end_date']))) : '-'; ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Status Pendaftaran</div>
                    <div class="detail-value">
                        <?php if (($event['registration_status'] ?? '') === 'open'): ?>
                            <span class="badge badge-open">Open</span>
                        <?php else: ?>
                            <span class="badge badge-close">Closed</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Status Tampil</div>
                    <div class="detail-value">
                        <?php if ((int) ($event['is_active'] ?? 1) === 1): ?>
                            <span class="badge badge-active">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Nonaktif</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Kontak</div>
                    <div class="detail-value"><?php echo htmlspecialchars($event['contact'] ?? '-'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Dibuat</div>
                    <div class="detail-value"><?php echo !empty($event['created_at']) ? htmlspecialchars(date('d M Y H:i', strtotime($event['created_at']))) : '-'; ?></div>
                </div>
            </div>
        </div>

        <div class="detail-description">
            <div class="desc-title"><i class="fas fa-align-left"></i> Deskripsi</div>
            <div class="desc-body"><?php echo !empty(trim((string) ($event['description'] ?? ''))) ? nl2br(htmlspecialchars($event['description'])) : 'Belum ada deskripsi.'; ?></div>
        </div>

        <div class="participant-section">
            <div class="participant-title"><i class="fas fa-users"></i> Peserta (<?php echo (int) $eventParticipantTotal; ?>)</div>
            <div class="participant-note">Daftar tim yang terdaftar berdasarkan challenge pada event ini, dikelompokkan per kategori.</div>

            <?php if (!$eventDataReady): ?>
                <div style="color:#64748b;">Data peserta belum tersedia (kolom relasi event belum aktif).</div>
            <?php elseif (empty($eventParticipantGroups)): ?>
                <div style="color:#64748b;">Belum ada challenge yang terhubung ke event ini.</div>
            <?php else: ?>
                <div class="participant-grid">
                    <?php foreach ($eventParticipantGroups as $group): ?>
                        <article class="participant-card">
                            <div class="participant-head"><?php echo htmlspecialchars($group['category_name'] ?? '-'); ?></div>
                            <table class="participant-table">
                                <thead>
                                    <tr>
                                        <th style="width:44px;">No</th>
                                        <th>Team</th>
                                        <th style="width:44px;">Info</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($group['teams'])): ?>
                                        <tr><td colspan="3" style="color:#64748b;">Belum ada tim</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($group['teams'] as $idx => $team): ?>
                                            <?php
                                            $teamId = (int)($team['id'] ?? 0);
                                            $teamLink = '../all.php?status=result&event=' . urlencode((string)($group['category_name'] ?? '')) . '&team=' . urlencode((string)$teamId);
                                            ?>
                                            <tr>
                                                <td><?php echo $idx + 1; ?></td>
                                                <td>
                                                    <div class="team-row-mini">
                                                        <img src="../images/teams/<?php echo htmlspecialchars($team['logo'] ?? 'default-team.png'); ?>"
                                                             alt="<?php echo htmlspecialchars($team['name'] ?? ''); ?>"
                                                             class="team-logo-mini"
                                                             onerror="this.onerror=null; this.src='../images/teams/default-team.png'">
                                                        <span><?php echo htmlspecialchars($team['name'] ?? '-'); ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a class="team-info-link" href="<?php echo htmlspecialchars($teamLink); ?>" title="Lihat pertandingan tim">
                                                        <i class="fas fa-circle-info"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>

    }
});
</script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>