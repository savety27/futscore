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
        // Keep page running; action query will report if schema still invalid.
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
$academy_name = "Hi, Welcome...";
$email = $_SESSION['admin_email'] ?? '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_registration = isset($_GET['registration']) ? trim($_GET['registration']) : '';
$filter_active = isset($_GET['active']) ? trim($_GET['active']) : '';

if (!in_array($filter_registration, ['', 'open', 'closed'], true)) {
    $filter_registration = '';
}
if (!in_array($filter_active, ['', '1', '0'], true)) {
    $filter_active = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $event_id = (int) $_POST['id'];
    $action = trim($_POST['action']);

    try {
        if ($action === 'toggle_registration') {
            $stmt = $conn->prepare("UPDATE events SET registration_status = CASE WHEN registration_status='open' THEN 'closed' ELSE 'open' END WHERE id = ?");
            $stmt->execute([$event_id]);
            $_SESSION['success_message'] = 'Status pendaftaran event berhasil diubah.';
        }
    } catch (PDOException $e) {
        $sql_state = (string) $e->getCode();
        $mysql_code = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;

        $_SESSION['error_message'] = ($sql_state === '23000' || $mysql_code === 1451 || $mysql_code === 1452)
            ? 'Aksi tidak dapat diproses karena data terkait masih digunakan.'
            : 'Aksi tidak dapat diproses. Silakan coba lagi.';
    }

    $redirect_query = [];
    if ($search !== '') {
        $redirect_query['search'] = $search;
    }
    if ($filter_registration !== '') {
        $redirect_query['registration'] = $filter_registration;
    }
    if ($filter_active !== '') {
        $redirect_query['active'] = $filter_active;
    }
    if (!empty($_GET['page'])) {
        $redirect_query['page'] = (int) $_GET['page'];
    }
    $qs = $redirect_query ? ('?' . http_build_query($redirect_query)) : '';
    header("Location: event.php" . $qs);
    exit;
}
$page = isset($_GET['page']) ? max((int) $_GET['page'], 1) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$base_query = "SELECT * FROM events WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM events WHERE 1=1";
$params = [];

if ($search !== '') {
    $base_query .= " AND (name LIKE ? OR category LIKE ? OR location LIKE ? OR contact LIKE ?)";
    $count_query .= " AND (name LIKE ? OR category LIKE ? OR location LIKE ? OR contact LIKE ?)";
    $search_term = "%{$search}%";
    $params = [$search_term, $search_term, $search_term, $search_term];
}
if ($filter_registration !== '') {
    $base_query .= " AND registration_status = ?";
    $count_query .= " AND registration_status = ?";
    $params[] = $filter_registration;
}
if ($filter_active !== '') {
    $base_query .= " AND is_active = ?";
    $count_query .= " AND is_active = ?";
    $params[] = (int) $filter_active;
}

$base_query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

$events = [];
$total_data = 0;
$total_pages = 1;
$error = '';

try {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->execute($params);
    $total_data = (int) ($count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $total_pages = max((int) ceil($total_data / $limit), 1);

    $stmt = $conn->prepare($base_query);
    $idx = 1;
    foreach ($params as $param) {
        $stmt->bindValue($idx++, $param);
    }
    $stmt->bindValue($idx++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($idx, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Event Management - MGP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
:root {
    --primary: #0f2744;
    --secondary: #f59e0b;
    --accent: #3b82f6;
    --success: #10b981;
    --danger: #ef4444;
    --dark: #1e293b;
    --gray: #64748b;
    --sidebar-bg: rgba(15, 39, 68, 0.95);
    --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
    --premium-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%);
    color: var(--dark);
}
.wrapper { display: flex; min-height: 100vh; }
.sidebar {
    width: 280px;
    background: var(--sidebar-bg);
    color: white;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
}
.sidebar-header {
    padding: 30px 25px;
    text-align: center;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 2px solid var(--secondary);
}
.academy-name { font-size: 22px; font-weight: 700; color: var(--secondary); margin-bottom: 8px; }
.academy-email { font-size: 14px; color: rgba(255, 255, 255, 0.8); }
.menu { padding: 25px 15px; }
.menu-item { margin-bottom: 8px; }
.menu-link { display: flex; align-items: center; gap: 10px; padding: 14px 20px; color: rgba(255, 255, 255, 0.75); text-decoration: none; border-radius: 12px; }
.menu-link:hover { background: rgba(255, 255, 255, 0.1); color: white; }
.menu-link.active { background: linear-gradient(90deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.02) 100%); color: var(--secondary); font-weight: 700; border-right: 4px solid var(--secondary); }
.submenu { margin-left: 20px; margin-top: 6px; }
.submenu-link { display: block; color: rgba(255, 255, 255, 0.7); text-decoration: none; padding: 8px 12px; border-radius: 8px; }
.submenu-link.active, .submenu-link:hover { color: var(--secondary); background: rgba(255, 215, 0, 0.1); }
.main { flex: 1; padding: 30px; margin-left: 280px; width: calc(100% - 280px); }
.topbar, .page-header { background: white; border-radius: 20px; box-shadow: var(--card-shadow); }
.topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding: 20px 25px; }
.greeting h1 { font-size: 28px; color: var(--primary); margin-bottom: 5px; }
.greeting p { color: var(--gray); font-size: 14px; }
.logout-btn { background: linear-gradient(135deg, var(--danger) 0%, #B71C1C 100%); color: white; padding: 12px 28px; border-radius: 12px; text-decoration: none; font-weight: 600; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding: 25px; gap: 15px; flex-wrap: wrap; }
.page-title { font-size: 28px; color: var(--primary); display: flex; align-items: center; gap: 15px; }
.page-title i { color: var(--secondary); }
.filter-container { margin-bottom: 24px; }
.event-filter-card {
    padding: 16px;
    border: 1px solid #dbe5f3;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    box-shadow: 0 8px 20px rgba(10, 36, 99, 0.06);
}
.event-filter-form {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) minmax(180px, 0.55fr) minmax(180px, 0.55fr) auto;
    gap: 12px;
    align-items: center;
}
.event-search-group { position: relative; }
.event-search-group i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #7b8797;
    font-size: 13px;
}
.event-search-input,
.event-filter-select {
    width: 100%;
    height: 42px;
    border: 1px solid #d3dcea;
    border-radius: 10px;
    background: #ffffff;
    color: #1f2937;
    font-size: 14px;
    transition: all 0.2s ease;
}
.event-search-input { padding: 0 12px 0 36px; }
.event-filter-select { padding: 0 12px; }
.event-search-input:focus,
.event-filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.12);
}
.event-filter-actions { display: flex; gap: 8px; }
.btn-filter,
.clear-filter-btn {
    height: 42px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
}
.btn-filter {
    background: linear-gradient(135deg, var(--primary), #1a4f9e);
    color: #ffffff;
}
.btn-filter:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(10, 36, 99, 0.22);
}
.clear-filter-btn {
    background: #ffffff;
    border-color: #d3dcea;
    color: #3b4a5f;
}
.clear-filter-btn:hover { background: #f2f6fc; }
.action-buttons { display: flex; gap: 10px; }
.btn { padding: 12px 20px; border-radius: 12px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
.btn-primary { background: linear-gradient(135deg, var(--primary), var(--accent)); color: white; }
.table-container { background: rgba(255, 255, 255, 0.9); border-radius: 24px; overflow: hidden; box-shadow: var(--premium-shadow); overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; min-width: 1020px; }
.data-table thead { background: linear-gradient(135deg, var(--primary), #1a365d); color: white; }
.data-table th, .data-table td { padding: 11px 10px; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
.data-table tbody tr:hover { background: #f8f9fa; }
.event-image { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; border: 2px solid #e5e7eb; }
.badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-block; }
.badge-open { background: rgba(16, 185, 129, 0.15); color: #047857; }
.badge-close { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }
.badge-active { background: rgba(16, 185, 129, 0.15); color: #047857; }
.badge-inactive { background: rgba(100, 116, 139, 0.15); color: #334155; }
.action-cell form { display: inline-block; margin-right: 6px; }
.action-btn { border: none; width: 36px; height: 36px; border-radius: 10px; cursor: pointer; font-size: 14px; }
.btn-view { background: rgba(99, 102, 241, 0.15); color: #4338ca; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
.btn-edit { background: rgba(59, 130, 246, 0.15); color: #1d4ed8; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
.btn-registration { background: rgba(245, 158, 11, 0.15); color: #b45309; }
.btn-delete { background: rgba(239, 68, 68, 0.15); color: #b91c1c; }
.alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 14px; }
.alert-success { background: rgba(46, 125, 50, 0.1); border-left: 4px solid #10b981; color: #047857; }
.alert-danger { background: rgba(211, 47, 47, 0.1); border-left: 4px solid #ef4444; color: #b91c1c; }
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal-content {
    background: white;
    padding: 30px;
    border-radius: 20px;
    max-width: 500px;
    width: 100%;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}
.modal-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
    color: var(--danger);
}
.modal-header i {
    font-size: 24px;
}
.modal-body {
    margin-bottom: 25px;
    color: var(--dark);
    line-height: 1.6;
}
.modal-footer { display: flex; justify-content: flex-end; gap: 15px; }
.btn-danger { background: linear-gradient(135deg, #ef4444, #b91c1c); color: #fff; }
.btn-danger:hover { background: linear-gradient(135deg, #b91c1c, #ef4444); }
.empty-state { text-align: center; padding: 50px 20px; color: #64748b; }
.pagination { display: flex; justify-content: center; gap: 8px; margin-top: 18px; }
.page-link { padding: 10px 14px; border: 2px solid #e5e7eb; border-radius: 10px; text-decoration: none; color: #334155; background: white; }
.page-link.active { background: var(--primary); color: white; border-color: var(--primary); }
.page-link.disabled { opacity: 0.4; pointer-events: none; }
@media (max-width: 900px) {
    .sidebar { display: none; }
    .main { margin-left: 0; width: 100%; padding: 16px; }
    .topbar, .page-header { padding: 16px; }
    .greeting h1 { font-size: 22px; }
    .page-title { font-size: 22px; }
    .event-filter-form { grid-template-columns: 1fr; }
    .event-filter-actions .btn-filter,
    .event-filter-actions .clear-filter-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>
</head>
<body>
<div class="wrapper">
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="academy-name"><?php echo htmlspecialchars($academy_name); ?></div>
            <div class="academy-email"><?php echo htmlspecialchars($email); ?></div>
        </div>
        <div class="menu">
            <?php foreach ($menu_items as $key => $item): ?>
            <?php
                $isActive = false;
                if (!empty($item['submenu'])) {
                    foreach ($item['items'] as $subUrl) {
                        if ($current_page === $subUrl) {
                            $isActive = true;
                            break;
                        }
                    }
                } else {
                    $isActive = ($current_page === basename($item['url']));
                }
            ?>
            <div class="menu-item">
                <a href="<?php echo $item['submenu'] ? '#' : $item['url']; ?>" class="menu-link <?php echo $isActive ? 'active' : ''; ?>">
                    <span><?php echo $item['icon']; ?></span>
                    <span><?php echo $item['name']; ?></span>
                </a>
                <?php if (!empty($item['submenu'])): ?>
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
                <h1>Event Management</h1>
                <p>Kelola data event dengan konsep tampilan seragam</p>
            </div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-calendar-alt"></i>
                <span>Daftar Event</span>
            </div>
            <div class="action-buttons">
                <a href="event_create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Event</a>
            </div>
        </div>

        <div class="filter-container">
            <div class="event-filter-card">
                <form method="GET" action="" class="event-filter-form">
                    <div class="event-search-group">
                        <i class="fas fa-search"></i>
                        <input
                            type="text"
                            name="search"
                            class="event-search-input"
                            placeholder="Cari event (nama, tipe, lokasi, kontak)..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>

                    <select name="registration" class="event-filter-select">
                        <option value="">Semua Pendaftaran</option>
                        <option value="open" <?php echo $filter_registration === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="closed" <?php echo $filter_registration === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>

                    <select name="active" class="event-filter-select">
                        <option value="">Semua Status</option>
                        <option value="1" <?php echo $filter_active === '1' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="0" <?php echo $filter_active === '0' ? 'selected' : ''; ?>>Nonaktif</option>
                    </select>

                    <div class="event-filter-actions">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-filter"></i> Terapkan
                        </button>
                        <?php if ($search !== '' || $filter_registration !== '' || $filter_active !== ''): ?>
                        <a href="event.php" class="clear-filter-btn">
                            <i class="fas fa-times"></i> Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($error); ?></span></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></span></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i><span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span></div>
        <?php endif; ?>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Image</th>
                        <th>Nama Event</th>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Lokasi</th>
                        <th>Pendaftaran</th>
                        <th>Status</th>
                        <th>Kontak</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($events)): ?>
                        <?php $no = $offset + 1; ?>
                        <?php foreach ($events as $event): ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td>
                                <?php if (!empty($event['image'])): ?>
                                    <img src="../images/events/<?php echo htmlspecialchars($event['image']); ?>" class="event-image" alt="image">
                                <?php else: ?>
                                    <div class="event-image" style="display:flex;align-items:center;justify-content:center;background:#f1f5f9;color:#64748b;">
                                        <i class="fas fa-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($event['name'] ?? '-'); ?></strong></td>
                            <td>
                                <?php
                                $start = !empty($event['start_date']) ? date('d M Y', strtotime($event['start_date'])) : '-';
                                $end = !empty($event['end_date']) ? date('d M Y', strtotime($event['end_date'])) : '-';
                                echo htmlspecialchars($start . ' - ' . $end);
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($event['category'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($event['location'] ?? '-'); ?></td>
                            <td>
                                <?php if (($event['registration_status'] ?? '') === 'open'): ?>
                                    <span class="badge badge-open">Open</span>
                                <?php else: ?>
                                    <span class="badge badge-close">Closed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) ($event['is_active'] ?? 1) === 1): ?>
                                    <span class="badge badge-active">Aktif</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($event['contact'] ?? '-'); ?></td>
                            <td><?php echo !empty($event['created_at']) ? date('d M Y', strtotime($event['created_at'])) : '-'; ?></td>
                            <td class="action-cell">
                                <a href="event_view.php?id=<?php echo (int) $event['id']; ?>" class="action-btn btn-view" title="Lihat Detail"><i class="fas fa-eye"></i></a>
                                <a href="event_edit.php?id=<?php echo (int) $event['id']; ?>" class="action-btn btn-edit" title="Edit Event"><i class="fas fa-pen"></i></a>
                                <form method="POST" action="?<?php echo http_build_query(['search' => $search, 'registration' => $filter_registration, 'active' => $filter_active, 'page' => $page]); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int) $event['id']; ?>">
                                    <input type="hidden" name="action" value="toggle_registration">
                                    <button type="submit" class="action-btn btn-registration" title="Toggle Open/Closed"><i class="fas fa-toggle-on"></i></button>
                                </form>
                                <button
                                    type="button"
                                    class="action-btn btn-delete"
                                    title="Hapus"
                                    onclick="showDeleteModal(<?php echo (int) $event['id']; ?>, '<?php echo htmlspecialchars(addslashes($event['name'] ?? '-')); ?>')"
                                ><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11">
                                <div class="empty-state">
                                    <h3>Belum Ada Data Event</h3>
                                    <p>Mulai dengan menambahkan event pertama menggunakan tombol Add Event.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php $prev = max($page - 1, 1); $next = min($page + 1, $total_pages); ?>
            <a class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?<?php echo http_build_query(['page' => $prev, 'search' => $search, 'registration' => $filter_registration, 'active' => $filter_active]); ?>"><i class="fas fa-chevron-left"></i></a>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a class="page-link <?php echo $i === $page ? 'active' : ''; ?>" href="?<?php echo http_build_query(['page' => $i, 'search' => $search, 'registration' => $filter_registration, 'active' => $filter_active]); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="?<?php echo http_build_query(['page' => $next, 'search' => $search, 'registration' => $filter_registration, 'active' => $filter_active]); ?>"><i class="fas fa-chevron-right"></i></a>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Konfirmasi Hapus Event</h3>
        </div>
        <div class="modal-body">
            <p id="deleteMessage">Apakah Anda yakin ingin menghapus event ini?</p>
            <p style="color: var(--danger); font-weight: 600; margin-top: 10px;">
                <i class="fas fa-exclamation-circle"></i> Data yang dihapus tidak dapat dikembalikan!
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancelDeleteBtn">Batal</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Hapus</button>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
let currentEventId = null;

(function () {
    const modal = document.getElementById('deleteModal');
    const deleteMessage = document.getElementById('deleteMessage');
    const cancelBtn = document.getElementById('cancelDeleteBtn');
    const confirmBtn = document.getElementById('confirmDeleteBtn');

    window.showDeleteModal = function (eventId, eventName) {
        currentEventId = eventId || null;
        deleteMessage.innerHTML = 'Apakah Anda yakin ingin menghapus event <strong>"' + eventName + '"</strong>?';
        modal.style.display = 'flex';
    };

    window.closeModal = function () {
        modal.style.display = 'none';
        currentEventId = null;
    };

    cancelBtn.addEventListener('click', closeModal);

    confirmBtn.addEventListener('click', function () {
        if (!currentEventId) return;
        deleteEvent(currentEventId);
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal();
        }
    });
})();

function deleteEvent(eventId) {
    fetch('event_delete.php?id=' + encodeURIComponent(eventId), {
        method: 'GET',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
        if (data.success) {
            closeModal();
            toastr.success('Event berhasil dihapus!');
            setTimeout(function () { window.location.reload(); }, 900);
        } else {
            toastr.error(data.message ? data.message : 'Gagal menghapus event.');
            closeModal();
        }
    })
    .catch(function () {
        toastr.error('Terjadi kesalahan saat menghapus event.');
        closeModal();
    });
}
</script>
</body>
</html>
