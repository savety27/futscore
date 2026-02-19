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
        // Keep page running; errors will appear on write query if schema is still invalid.
    }
}

ensure_events_active_column($conn);

function make_slug($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    return $text !== '' ? $text : ('event-' . time());
}

function generate_unique_slug_except_id(PDO $conn, $name, $event_id) {
    $base = make_slug($name);
    $slug = $base;
    $i = 1;

    while (true) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM events WHERE slug = ? AND id <> ?");
        $stmt->execute([$slug, $event_id]);
        $exists = (int) $stmt->fetchColumn();
        if ($exists === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

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
    'challenge' => ['icon' => '⚔️', 'name' => 'Challenge', 'url' => 'challenge.php', 'submenu' => false],
    'Venue' => ['icon' => '📍', 'name' => 'Venue', 'url' => 'venue.php', 'submenu' => false],
    'Pelatih' => ['icon' => '👨‍🏫', 'name' => 'Pelatih', 'url' => 'pelatih.php', 'submenu' => false],
    'Berita' => ['icon' => '📰', 'name' => 'Berita', 'url' => 'berita.php', 'submenu' => false]
];

$current_page = basename($_SERVER['PHP_SELF']);
$admin_email = $_SESSION['admin_email'] ?? '';
$academy_name = "Hi, Welcome...";
$email = $admin_email;
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);

if ($event_id <= 0) {
    $_SESSION['error_message'] = 'ID event tidak valid.';
    header('Location: event.php');
    exit;
}

$errors = [];

try {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $event = false;
    $errors['database'] = 'Data event tidak dapat dimuat saat ini. Silakan coba lagi.';
}

if (!$event) {
    if (!isset($errors['database'])) {
        $_SESSION['error_message'] = 'Data event tidak ditemukan.';
        header('Location: event.php');
        exit;
    }

    $event = [
        'name' => '',
        'start_date' => '',
        'end_date' => '',
        'category' => '',
        'location' => '',
        'registration_status' => 'open',
        'is_active' => 1,
        'contact' => '',
        'description' => '',
        'image' => '',
        'slug' => ''
    ];
}

$form_data = [
    'name' => $event['name'] ?? '',
    'start_date' => $event['start_date'] ?? '',
    'end_date' => $event['end_date'] ?? '',
    'category' => $event['category'] ?? '',
    'location' => $event['location'] ?? '',
    'registration_status' => $event['registration_status'] ?? 'open',
    'is_active' => (int) ($event['is_active'] ?? 1),
    'contact' => $event['contact'] ?? '',
    'description' => $event['description'] ?? ''
];
$current_image = $event['image'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors['database'])) {
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'start_date' => trim($_POST['start_date'] ?? ''),
        'end_date' => trim($_POST['end_date'] ?? ''),
        'category' => trim($_POST['category'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'registration_status' => trim($_POST['registration_status'] ?? 'open'),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'contact' => trim($_POST['contact'] ?? ''),
        'description' => trim($_POST['description'] ?? '')
    ];

    if ($form_data['name'] === '') {
        $errors['name'] = 'Nama event harus diisi';
    }
    if ($form_data['category'] === '') {
        $errors['category'] = 'Tipe event harus diisi';
    }
    if ($form_data['location'] === '') {
        $errors['location'] = 'Lokasi harus diisi';
    }
    if (!in_array($form_data['registration_status'], ['open', 'closed'], true)) {
        $errors['registration_status'] = 'Status pendaftaran tidak valid';
    }
    if ($form_data['contact'] === '') {
        $errors['contact'] = 'Kontak harus diisi';
    }

    $start_date = DateTime::createFromFormat('Y-m-d', $form_data['start_date']);
    $end_date = DateTime::createFromFormat('Y-m-d', $form_data['end_date']);
    if (!$start_date) {
        $errors['start_date'] = 'Tanggal mulai tidak valid';
    }
    if (!$end_date) {
        $errors['end_date'] = 'Tanggal selesai tidak valid';
    }
    if ($start_date && $end_date && $end_date < $start_date) {
        $errors['end_date'] = 'Tanggal selesai harus lebih besar atau sama dengan tanggal mulai';
    }

    $image_path = $current_image;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024;

        if (!in_array($file['type'], $allowed_types, true)) {
            $errors['image'] = 'Format gambar harus JPG, PNG, GIF, atau WEBP';
        }
        if ($file['size'] > $max_size) {
            $errors['image'] = 'Ukuran gambar maksimal 5MB';
        }

        if (!isset($errors['image'])) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'event_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_dir = '../images/events/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                $errors['image'] = 'Gagal upload gambar event';
            } else {
                $image_path = $filename;
            }
        }
    }

    if (empty($errors)) {
        try {
            $current_slug = $event['slug'] ?? '';
            $new_slug_base = make_slug($form_data['name']);
            $slug = $current_slug;

            if ($current_slug === '' || strpos($current_slug, $new_slug_base) !== 0) {
                $slug = generate_unique_slug_except_id($conn, $form_data['name'], $event_id);
            }

            $stmt = $conn->prepare(
                "UPDATE events SET
                    name = ?,
                    slug = ?,
                    description = ?,
                    image = ?,
                    start_date = ?,
                    end_date = ?,
                    location = ?,
                    registration_status = ?,
                    is_active = ?,
                    contact = ?,
                    category = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $form_data['name'],
                $slug,
                $form_data['description'] !== '' ? $form_data['description'] : null,
                $image_path,
                $start_date->format('Y-m-d'),
                $end_date->format('Y-m-d'),
                $form_data['location'],
                $form_data['registration_status'],
                $form_data['is_active'],
                $form_data['contact'],
                $form_data['category'],
                $event_id
            ]);

            $_SESSION['success_message'] = 'Event berhasil diperbarui';
            header('Location: event.php');
            exit;
        } catch (PDOException $e) {
            $errors['database'] = 'Perubahan event gagal disimpan. Periksa kembali input dan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Event</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
:root {
    --primary: #0f2744;
    --secondary: #f59e0b;
    --accent: #3b82f6;
    --danger: #ef4444;
    --dark: #1e293b;
    --gray: #64748b;
    --sidebar-bg: linear-gradient(180deg, #0a1628 0%, #0f2744 100%);
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
.sidebar {
    width: 280px;
    background: var(--sidebar-bg);
    color: white;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
    box-shadow: 10px 0 30px rgba(0, 0, 0, 0.15);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
}
.sidebar-header {
    padding-top: 20px;
    padding-right: 10px;
    padding-bottom: 10px;
    text-align: center;
    background: transparent;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    margin-bottom: 10px;
}
.logo-container { position: relative; display: inline-block; }
.logo {
    max-width: 200px;
    background: transparent;
    margin: 0 auto 12px;
    border: none;
    border-radius: 0;
    box-shadow: none;
    position: relative;
    overflow: visible;
    transition: var(--transition);
}
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
.academy-name { color: var(--secondary); font-size: 22px; font-weight: 700; margin-bottom: 8px; }
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
.topbar, .page-header, .form-container { background: #fff; border-radius: 18px; box-shadow: var(--card-shadow); }
.topbar { padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; animation: slideDown 0.5s ease-out; }
.greeting h1 { color: var(--primary); font-size: 26px; }
.greeting p { color: var(--gray); font-size: 14px; }
.logout-btn { display: inline-flex; gap: 8px; align-items: center; background: linear-gradient(135deg, var(--danger), #b91c1c); color: #fff; text-decoration: none; padding: 10px 18px; border-radius: 10px; font-weight: 600; }
.page-header { margin-bottom: 22px; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; gap: 14px; }
.page-title { display: flex; align-items: center; gap: 10px; color: var(--primary); font-size: 25px; }
.btn { border: none; border-radius: 10px; padding: 11px 18px; font-weight: 600; text-decoration: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
.btn-primary { background: linear-gradient(135deg, var(--primary), var(--accent)); color: #fff; }
.btn-secondary { background: #6b7280; color: #fff; }
.form-container { padding: 26px; }
.form-section { margin-bottom: 26px; padding-bottom: 18px; border-bottom: 1px solid #e5e7eb; }
.form-section:last-child { border-bottom: none; margin-bottom: 0; }
.section-title { color: var(--primary); font-size: 19px; font-weight: 700; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.form-grid { display: grid; gap: 16px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.form-group { margin-bottom: 10px; }
.form-label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 7px; }
.required { color: var(--danger); }
.form-input, .form-textarea { width: 100%; border: 2px solid #e5e7eb; border-radius: 10px; padding: 11px 14px; font-size: 15px; background: #f8fafc; }
.form-textarea { min-height: 110px; resize: vertical; }
.status-wrap { display: flex; gap: 8px; }
.status-chip { flex: 1; border: 2px solid #d1d5db; border-radius: 10px; padding: 8px; text-align: center; cursor: pointer; font-weight: 700; color: #374151; }
.status-chip input { display: none; }
.status-chip.active-open { border-color: #22c55e; color: #16a34a; background: #f0fdf4; }
.status-chip.active-close { border-color: #ef4444; color: #dc2626; background: #fef2f2; }
.checkbox-group { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.checkbox-group input { width: auto; margin-right: 4px; }
.file-upload-container {
    border: 2px dashed #d1d5db;
    border-radius: 14px;
    padding: 22px;
    text-align: center;
    background: #f8fafc;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all 0.2s ease;
}
.file-upload-container:hover {
    border-color: var(--primary);
    background: #f0f7ff;
}
.file-upload-container.drag-over {
    border-color: var(--primary);
    background: #e6f0ff;
    transform: translateY(-1px);
}
.file-upload-input {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    opacity: 0;
    cursor: pointer;
}
.file-upload-icon {
    font-size: 40px;
    color: var(--primary);
    margin-bottom: 10px;
    display: block;
}
.file-upload-text {
    font-size: 15px;
    color: #334155;
    margin-bottom: 4px;
    font-weight: 600;
}
.file-upload-subtext {
    font-size: 12px;
    color: var(--gray);
}
.file-preview {
    margin-top: 14px;
}
.file-preview img {
    max-width: 200px;
    max-height: 200px;
    border-radius: 10px;
    border: 2px solid #e5e7eb;
}
.file-info {
    margin-top: 6px;
    font-size: 12px;
    color: var(--gray);
}
.error { color: var(--danger); font-size: 12px; margin-top: 6px; display: block; }
.alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.alert-danger { background: rgba(211, 47, 47, 0.1); border-left: 4px solid #ef4444; color: #b91c1c; }
.form-actions { margin-top: 10px; display: flex; justify-content: flex-end; gap: 10px; }
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
    .sidebar { display: none; }
    .main { margin-left: 0; width: 100%; padding: 16px; }
    .form-grid { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>
<div class="wrapper">
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
                        $isActive = in_array($current_page, ['event.php', 'event_create.php', 'event_edit.php'], true);
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
                <h1>Edit Event</h1>
                <p>Perbarui data event dengan konsep form seragam</p>
            </div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>

        <div class="page-header">
            <div class="page-title"><i class="fas fa-pen-to-square"></i> <span>Perbarui Data Event</span></div>
            <div>
                <a href="event.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>
        </div>

        <?php if (isset($errors['database'])): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i><span><?php echo htmlspecialchars($errors['database']); ?></span></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="" enctype="multipart/form-data" id="eventForm">
                <input type="hidden" name="id" value="<?php echo (int) $event_id; ?>">

                <div class="form-section">
                    <div class="section-title"><i class="fas fa-info-circle"></i> Informasi Event</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="name">Nama Event <span class="required">*</span></label>
                            <input class="form-input" type="text" id="name" name="name" required value="<?php echo htmlspecialchars($form_data['name']); ?>" placeholder="Contoh: PRA LIGA AAFI SAMPIT 2026">
                            <?php if (isset($errors['name'])): ?><span class="error"><?php echo $errors['name']; ?></span><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="category">Tipe <span class="required">*</span></label>
                            <input class="form-input" type="text" id="category" name="category" required value="<?php echo htmlspecialchars($form_data['category']); ?>" placeholder="Contoh: League / Turnamen / Friendly">
                            <?php if (isset($errors['category'])): ?><span class="error"><?php echo $errors['category']; ?></span><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="start_date">Tanggal Mulai <span class="required">*</span></label>
                            <input class="form-input" type="date" id="start_date" name="start_date" required value="<?php echo htmlspecialchars($form_data['start_date']); ?>">
                            <?php if (isset($errors['start_date'])): ?><span class="error"><?php echo $errors['start_date']; ?></span><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="end_date">Tanggal Selesai <span class="required">*</span></label>
                            <input class="form-input" type="date" id="end_date" name="end_date" required value="<?php echo htmlspecialchars($form_data['end_date']); ?>">
                            <?php if (isset($errors['end_date'])): ?><span class="error"><?php echo $errors['end_date']; ?></span><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="location">Lokasi <span class="required">*</span></label>
                            <input class="form-input" type="text" id="location" name="location" required value="<?php echo htmlspecialchars($form_data['location']); ?>" placeholder="Contoh: GOR FUTSAL DISPORA KOTIM">
                            <?php if (isset($errors['location'])): ?><span class="error"><?php echo $errors['location']; ?></span><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="contact">Kontak <span class="required">*</span></label>
                            <input class="form-input" type="text" id="contact" name="contact" required value="<?php echo htmlspecialchars($form_data['contact']); ?>" placeholder="Contoh: 08981434528">
                            <?php if (isset($errors['contact'])): ?><span class="error"><?php echo $errors['contact']; ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title"><i class="fas fa-toggle-on"></i> Status Pendaftaran</div>
                    <div class="status-wrap">
                        <label class="status-chip <?php echo $form_data['registration_status'] === 'open' ? 'active-open' : ''; ?>">
                            <input type="radio" name="registration_status" value="open" <?php echo $form_data['registration_status'] === 'open' ? 'checked' : ''; ?>>
                            OPEN
                        </label>
                        <label class="status-chip <?php echo $form_data['registration_status'] === 'closed' ? 'active-close' : ''; ?>">
                            <input type="radio" name="registration_status" value="closed" <?php echo $form_data['registration_status'] === 'closed' ? 'checked' : ''; ?>>
                            CLOSED
                        </label>
                    </div>
                    <?php if (isset($errors['registration_status'])): ?><span class="error"><?php echo $errors['registration_status']; ?></span><?php endif; ?>
                </div>

                <div class="form-section">
                    <div class="section-title"><i class="fas fa-eye"></i> Status Tampil Event</div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo !empty($form_data['is_active']) ? 'checked' : ''; ?>>
                            <label for="is_active" style="font-weight: normal;">Event Aktif (Tampil ke user)</label>
                        </div>
                        <small style="color: #666;">Jika tidak dicentang, event disembunyikan dari user dan hanya admin yang mengatur.</small>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title"><i class="fas fa-image"></i> Gambar & Deskripsi</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="image">Gambar Event</label>
                            <div class="file-upload-container" id="imageUpload">
                                <input class="file-upload-input" type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                                <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                <div class="file-upload-text">Klik atau drag & drop gambar event</div>
                                <div class="file-upload-subtext">Format: JPG, PNG, GIF, WEBP | Maks: 5MB</div>
                                <div class="file-preview" id="imagePreview" style="<?php echo !empty($current_image) ? '' : 'display:none;'; ?>">
                                    <img id="imagePreviewImg" src="<?php echo !empty($current_image) ? ('../images/events/' . htmlspecialchars($current_image)) : ''; ?>" alt="Preview Event">
                                    <div class="file-info" id="imageFileInfo"><?php echo !empty($current_image) ? htmlspecialchars($current_image) : ''; ?></div>
                                </div>
                            </div>
                            <?php if (isset($errors['image'])): ?><span class="error"><?php echo $errors['image']; ?></span><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="description">Deskripsi</label>
                            <textarea class="form-textarea" id="description" name="description" placeholder="Deskripsi singkat event..."><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="event.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
document.querySelectorAll('input[name="registration_status"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.querySelectorAll('.status-chip').forEach(function (chip) {
            chip.classList.remove('active-open', 'active-close');
            if (chip.querySelector('input').checked) {
                chip.classList.add(chip.querySelector('input').value === 'open' ? 'active-open' : 'active-close');
            }
        });
    });
});

const imageUpload = document.getElementById('imageUpload');
const imageInput = document.getElementById('image');
const imagePreview = document.getElementById('imagePreview');
const imagePreviewImg = document.getElementById('imagePreviewImg');
const imageFileInfo = document.getElementById('imageFileInfo');

if (typeof toastr !== 'undefined') {
    toastr.options = {
        closeButton: true,
        progressBar: true,
        positionClass: 'toast-top-right',
        timeOut: '2800'
    };
}

imageUpload.addEventListener('dragover', function (e) {
    e.preventDefault();
    imageUpload.classList.add('drag-over');
});

imageUpload.addEventListener('dragleave', function () {
    imageUpload.classList.remove('drag-over');
});

imageUpload.addEventListener('drop', function (e) {
    e.preventDefault();
    imageUpload.classList.remove('drag-over');
    const files = e.dataTransfer.files;
    if (files.length) {
        imageInput.files = files;
        handleImageSelect(files[0]);
    }
});

imageInput.addEventListener('change', function () {
    if (this.files.length) {
        handleImageSelect(this.files[0]);
    }
});

function handleImageSelect(file) {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const maxSize = 5 * 1024 * 1024;

    if (!allowedTypes.includes(file.type)) {
        if (typeof toastr !== 'undefined') {
            toastr.error('Format gambar harus JPG, PNG, GIF, atau WEBP.');
        }
        return;
    }

    if (file.size > maxSize) {
        if (typeof toastr !== 'undefined') {
            toastr.error('Ukuran gambar maksimal 5MB.');
        }
        return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
        imagePreviewImg.src = e.target.result;
        imageFileInfo.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
        imagePreview.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

document.getElementById('eventForm').addEventListener('submit', function (e) {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    if (startDate && endDate && endDate < startDate) {
        e.preventDefault();
        if (typeof toastr !== 'undefined') {
            toastr.error('Tanggal selesai harus lebih besar atau sama dengan tanggal mulai.');
        }
    }
});

document.querySelectorAll('.menu-link').forEach(function(link) {
    if (link.querySelector('.menu-arrow')) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const submenu = this.nextElementSibling;
            const arrow = this.querySelector('.menu-arrow');
            if (submenu) {
                submenu.classList.toggle('open');
                arrow.classList.toggle('rotate');
            }
        });
    }
});
</script>
</body>
</html>
