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

$menu_items = [
    'dashboard' => ['icon' => '🏠', 'name' => 'Dashboard', 'url' => 'dashboard.php', 'submenu' => false],
    'master' => [
        'icon' => '📊',
        'name' => 'Master Data',
        'submenu' => true,
        'items' => [
            'player' => 'player.php',
            'team' => 'team.php',
            'team_staff' => 'team_staff.php',
            'transfer' => 'transfer.php',
        ]
    ],
    'event' => ['icon' => '🏆', 'name' => 'Event', 'url' => 'event.php', 'submenu' => false],
    'Venue' => ['icon' => '📍', 'name' => 'Venue', 'url' => 'venue.php', 'submenu' => false],
    'Pelatih' => ['icon' => '👨‍🏫', 'name' => 'Pelatih', 'url' => 'pelatih.php', 'submenu' => false],
    'Berita' => ['icon' => '📰', 'name' => 'Berita', 'url' => 'berita.php', 'submenu' => false]
];

$current_page = basename($_SERVER['PHP_SELF']);
$admin_email = $_SESSION['admin_email'] ?? '';
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($event_id <= 0) {
    $_SESSION['error_message'] = 'ID event tidak valid.';
    header('Location: event.php');
    exit;
}

$event = null;
$error = '';
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Event</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #0f2744;
    --secondary: #f59e0b;
    --accent: #3b82f6;
    --danger: #ef4444;
    --success: #10b981;
    --dark: #1e293b;
    --gray: #64748b;
    --sidebar-bg: rgba(15, 39, 68, 0.95);
    --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%);
    color: var(--dark);
}
.wrapper { display: flex; min-height: 100vh; }
.sidebar { width: 280px; background: var(--sidebar-bg); color: #fff; position: fixed; height: 100vh; overflow-y: auto; }
.sidebar-header { padding: 26px 22px; text-align: center; border-bottom: 2px solid var(--secondary); }
.academy-name { color: var(--secondary); font-size: 20px; font-weight: 700; }
.academy-email { font-size: 13px; opacity: 0.9; }
.menu { padding: 20px 14px; }
.menu-item { margin-bottom: 8px; }
.menu-link { display: flex; align-items: center; gap: 10px; color: rgba(255,255,255,0.75); text-decoration: none; padding: 12px 14px; border-radius: 10px; }
.menu-link:hover { background: rgba(255,255,255,0.09); color: #fff; }
.menu-link.active { color: var(--secondary); background: rgba(245,158,11,0.12); border-right: 3px solid var(--secondary); }
.submenu { margin-top: 6px; margin-left: 14px; padding-left: 10px; border-left: 1px solid rgba(255,255,255,0.2); }
.submenu-link { display: block; color: rgba(255,255,255,0.7); padding: 8px 10px; border-radius: 8px; text-decoration: none; font-size: 14px; }
.submenu-link.active, .submenu-link:hover { color: var(--secondary); background: rgba(245,158,11,0.1); }
.main { margin-left: 280px; flex: 1; padding: 28px; }
.topbar, .page-header, .detail-container, .detail-description { background: #fff; border-radius: 18px; box-shadow: var(--card-shadow); }
.topbar { padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; }
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
.alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-danger { background: rgba(211, 47, 47, 0.1); border-left: 4px solid #ef4444; color: #b91c1c; }
@media (max-width: 900px) {
    .sidebar { transform: translateX(-100%); position: fixed; }
    .main { margin-left: 0; }
    .detail-container { grid-template-columns: 1fr; }
    .detail-grid { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="academy-name">Hi, Welcome...</div>
            <div class="academy-email"><?php echo htmlspecialchars($admin_email); ?></div>
        </div>
        <div class="menu">
            <?php foreach ($menu_items as $key => $item): ?>
            <?php
                $isActive = false;
                if ($item['submenu']) {
                    foreach ($item['items'] as $subUrl) {
                        if ($current_page === $subUrl) {
                            $isActive = true;
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
                <a href="<?php echo $item['submenu'] ? '#' : $item['url']; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                    <span><?php echo $item['icon']; ?></span>
                    <span><?php echo $item['name']; ?></span>
                </a>
                <?php if ($item['submenu']): ?>
                <div class="submenu">
                    <?php foreach ($item['items'] as $subKey => $subUrl): ?>
                    <a href="<?php echo $subUrl; ?>" class="submenu-link <?php echo $current_page === $subUrl ? 'active' : ''; ?>">
                        <?php echo ucwords(str_replace('_', ' ', $subKey)); ?>
                    </a>
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
                <h1>Detail Event</h1>
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
        <?php endif; ?>
    </div>
</div>
</body>
</html>
