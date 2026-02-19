<?php
session_start();

// Load database config
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

// Mendapatkan nama file saat ini untuk penanda menu 'Active'
$current_page = basename($_SERVER['PHP_SELF']);

// --- DATA MENU DROPDOWN ---
$menu_items = [
    'dashboard' => [
        'icon' => '🏠',
        'name' => 'Dashboard',
        'url' => 'dashboard.php',
        'submenu' => false
    ],
    'master' => [
        'icon' => '📊',
        'name' => 'Master Data',
        'submenu' => true,
        'items' => [
            'player' => 'player.php',
            'team' => 'team.php',
            'team_staff' => 'team_staff.php',
            'transfer' => 'transfer.php'
        ]
    ],
    'Event' => [
        'icon' => '🏆',
        'name' => 'Event',
        'url' => 'challenge.php',
        'submenu' => false
    ],
    'Venue' => [
        'icon' => '📍',
        'name' => 'Venue',
        'url' => 'venue.php',
        'submenu' => false
    ],
    'Pelatih' => [
        'icon' => '👨‍🏫',
        'name' => 'Pelatih',
        'url' => 'pelatih.php',
        'submenu' => false
    ],
    'Berita' => [
        'icon' => '📰',
        'name' => 'Berita',
        'url' => 'berita.php',
        'submenu' => false
    ]
];

$academy_name = "Hi, Welcome...";
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';
$email = $admin_email;

$errors = [];

// Handle transfer submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $player_id = isset($_POST['player_id']) ? (int)$_POST['player_id'] : 0;
    $to_team_id = isset($_POST['to_team_id']) ? (int)$_POST['to_team_id'] : 0;
    $transfer_date = isset($_POST['transfer_date']) && $_POST['transfer_date'] !== ''
        ? $_POST['transfer_date']
        : date('Y-m-d');

    if ($player_id <= 0) {
        $errors[] = 'Pilih pemain terlebih dahulu.';
    }
    if ($to_team_id <= 0) {
        $errors[] = 'Pilih tim tujuan.';
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT team_id FROM players WHERE id = ?");
            $stmt->execute([$player_id]);
            $player_row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$player_row) {
                $errors[] = 'Pemain tidak ditemukan.';
            } else {
                $from_team_id = $player_row['team_id'] ?? null;
                if (!empty($from_team_id) && (int)$from_team_id === $to_team_id) {
                    $errors[] = 'Tim asal dan tim tujuan tidak boleh sama.';
                }
            }

            if (empty($errors)) {
                $conn->beginTransaction();

                $stmt = $conn->prepare("INSERT INTO transfers (player_id, from_team_id, to_team_id, transfer_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([$player_id, $from_team_id, $to_team_id, $transfer_date]);

                $stmt = $conn->prepare("UPDATE players SET team_id = ? WHERE id = ?");
                $stmt->execute([$to_team_id, $player_id]);

                $conn->commit();

                $_SESSION['success_message'] = 'Transfer berhasil disimpan.';
                header("Location: transfer.php");
                exit;
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $errors[] = 'Terjadi kesalahan saat menyimpan transfer.';
        }
    }
}

// Fetch players and teams for form
$players = [];
$teams = [];
$recent_transfers = [];

try {
    $stmt = $conn->query("SELECT p.id, p.name, p.team_id, t.name AS team_name
                          FROM players p
                          LEFT JOIN teams t ON p.team_id = t.id
                          WHERE p.status = 'active'
                          ORDER BY p.name ASC");
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->query("SELECT id, name, logo FROM teams ORDER BY name ASC");
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->query("SELECT tr.*, p.name AS player_name,
                                 t1.name AS from_team_name, t2.name AS to_team_name
                          FROM transfers tr
                          LEFT JOIN players p ON tr.player_id = p.id
                          LEFT JOIN teams t1 ON tr.from_team_id = t1.id
                          LEFT JOIN teams t2 ON tr.to_team_id = t2.id
                          ORDER BY tr.transfer_date DESC, tr.id DESC
                          LIMIT 20");
    $recent_transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errors[] = 'Gagal memuat data. Silakan coba lagi.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transfer Center - FutScore</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #0f2744;
    --secondary: #f59e0b;
    --accent: #3b82f6;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --light: #F8F9FA;
    --dark: #1e293b;
    --gray: #64748b;
    --sidebar-bg: rgba(15, 39, 68, 0.95);
    --glass-white: rgba(255, 255, 255, 0.85);
    --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.03);
    --premium-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    --transition: cubic-bezier(0.4, 0, 0.2, 1) 0.3s;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Plus Jakarta Sans', 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%);
    color: var(--dark);
    min-height: 100vh;
    overflow-x: hidden;
}

.wrapper {
    display: flex;
    min-height: 100vh;
}

/* ===== SIDEBAR ===== */
.sidebar {
    width: 280px;
    background: var(--sidebar-bg);
    backdrop-filter: blur(15px) saturate(160%);
    -webkit-backdrop-filter: blur(15px) saturate(160%);
    color: white;
    padding: 0;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
    box-shadow: 10px 0 30px rgba(0, 0, 0, 0.15);
    transition: var(--transition);
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

.logo-container {
    position: relative;
    display: inline-block;
}

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

.logo:hover {
    transform: none;
    box-shadow: none;
}

.logo img {
    width: 100%;
    height: auto;
    max-width: 200px;
    filter: brightness(1.1) drop-shadow(0 0 15px rgba(255, 255, 255, 0.1));
    transition: transform var(--transition), filter var(--transition);
}

.logo img:hover {
    transform: scale(1.05);
}

.academy-info {
    text-align: center;
}

.academy-name {
    font-size: 22px;
    font-weight: 700;
    color: var(--secondary);
    margin-bottom: 8px;
    letter-spacing: 0.5px;
}

.academy-email {
    font-size: 14px;
    opacity: 0.9;
    color: rgba(255, 255, 255, 0.8);
}

/* Menu */
.menu {
    padding: 25px 15px;
}

.menu-item {
    margin-bottom: 8px;
    border-radius: 12px;
    overflow: hidden;
}

.menu-link {
    display: flex;
    align-items: center;
    padding: 14px 20px;
    color: rgba(255, 255, 255, 0.75);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    border-radius: 12px;
    margin: 4px 0;
}

.menu-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    transform: translateX(5px);
}

.menu-link.active {
    background: linear-gradient(90deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.02) 100%);
    color: var(--secondary);
    font-weight: 700;
    border-right: 4px solid var(--secondary);
    border-radius: 12px 0 0 12px;
}

.menu-icon {
    font-size: 18px;
    margin-right: 15px;
    width: 24px;
    text-align: center;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
}

.menu-text {
    flex: 1;
    font-size: 15px;
    letter-spacing: 0.3px;
}

.menu-arrow {
    font-size: 12px;
    opacity: 0.6;
    transition: var(--transition);
}

.menu-arrow.rotate {
    transform: rotate(90deg);
    opacity: 1;
}

/* Submenu */
.submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease-in-out;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 0 0 12px 12px;
}

.submenu.open {
    max-height: 300px;
}

.submenu-item {
    padding: 5px 15px 5px 70px;
}

.submenu-link {
    display: block;
    padding: 12px 15px;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    border-radius: 8px;
    transition: var(--transition);
    position: relative;
    font-size: 14px;
}

.submenu-link:hover {
    background: rgba(255, 215, 0, 0.1);
    color: var(--secondary);
    padding-left: 20px;
}

.submenu-link.active {
    background: rgba(255, 215, 0, 0.1);
    color: var(--secondary);
    padding-left: 20px;
}

.submenu-link::before {
    content: "•";
    position: absolute;
    left: 0;
    color: var(--secondary);
    font-size: 16px;
}

/* ===== MAIN CONTENT ===== */
.main {
    flex: 1;
    padding: 30px;
    margin-left: 280px;
    transition: var(--transition);
}

/* Topbar */
.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 40px;
    padding: 20px 25px;
    background: white;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
}

.greeting h1 {
    font-size: 28px;
    color: var(--primary);
    margin-bottom: 5px;
}

.greeting p {
    color: var(--gray);
    font-size: 14px;
}

.user-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}

.logout-btn {
    background: linear-gradient(135deg, var(--danger) 0%, #B71C1C 100%);
    color: white;
    padding: 12px 28px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    box-shadow: 0 5px 15px rgba(211, 47, 47, 0.2);
}

.logout-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(211, 47, 47, 0.3);
}

/* ===== TRANSFER CENTER ===== */
.transfer-hero {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 24px;
    padding: 30px;
    box-shadow: var(--premium-shadow);
    position: relative;
    overflow: hidden;
    margin-bottom: 30px;
}

.transfer-hero::after {
    content: '';
    position: absolute;
    right: -80px;
    top: -80px;
    width: 220px;
    height: 220px;
    background: conic-gradient(from 120deg, rgba(255, 215, 0, 0.3), rgba(10, 36, 99, 0.15), rgba(76, 201, 240, 0.25));
    border-radius: 50%;
    filter: blur(2px);
}

.transfer-hero-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    position: relative;
    z-index: 1;
}

.transfer-hero-title {
    font-size: 28px;
    color: var(--primary);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.transfer-hero-title i {
    color: var(--secondary);
}

.transfer-hero-subtitle {
    color: var(--gray);
    font-size: 14px;
}

.transfer-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.transfer-badge {
    padding: 8px 14px;
    border-radius: 999px;
    background: rgba(10, 36, 99, 0.08);
    color: var(--primary);
    font-weight: 600;
    font-size: 12px;
    border: 1px solid rgba(10, 36, 99, 0.15);
}

.transfer-layout {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 25px;
}

.transfer-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 24px;
    box-shadow: var(--premium-shadow);
    padding: 30px;
    position: relative;
    overflow: hidden;
}

.transfer-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 6px;
    width: 100%;
    background: linear-gradient(135deg, var(--primary), var(--accent));
}

.transfer-form {
    display: grid;
    gap: 16px;
    margin-top: 10px;
}

.form-row {
    display: grid;
    gap: 10px;
}

.form-row label {
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.form-row select,
.form-row input {
    padding: 12px 14px;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    background: #f8f9fa;
    font-size: 14px;
    transition: var(--transition);
}

.form-row select:focus,
.form-row input:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.08);
}

.player-search {
    position: relative;
}

.search-results {
    position: absolute;
    top: calc(100% + 6px);
    left: 0;
    right: 0;
    background: white;
    border-radius: 12px;
    border: 1px solid #e8edf3;
    box-shadow: 0 10px 25px rgba(10, 36, 99, 0.12);
    max-height: 260px;
    overflow-y: auto;
    z-index: 10;
    display: none;
}

.search-results.active {
    display: block;
}

.search-item {
    padding: 12px 14px;
    cursor: pointer;
    font-size: 14px;
    border-bottom: 1px solid #f1f4f8;
}

.search-item:last-child {
    border-bottom: none;
}

.search-item:hover,
.search-item.active {
    background: rgba(10, 36, 99, 0.08);
}

.search-item-title {
    font-weight: 600;
    color: var(--primary);
}

.search-item-sub {
    font-size: 12px;
    color: var(--gray);
    margin-top: 4px;
}

.from-team-chip {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 12px;
    background: rgba(10, 36, 99, 0.08);
    color: var(--primary);
    font-weight: 600;
    font-size: 13px;
}

.btn-submit {
    padding: 14px 18px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 10px;
    justify-content: center;
}

.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px rgba(10, 36, 99, 0.2);
}

.transfer-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.transfer-search {
    display: flex;
    gap: 10px;
    margin-top: 12px;
    margin-bottom: 6px;
}

.transfer-search input {
    flex: 1;
    padding: 12px 14px;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    background: #f8f9fa;
    font-size: 14px;
    transition: var(--transition);
}

.transfer-search input:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.08);
}

.transfer-item {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 10px;
    padding: 16px;
    border-radius: 16px;
    background: #f8f9fa;
    border: 1px solid #eef1f5;
}

.transfer-item-title {
    font-weight: 700;
    color: var(--primary);
}

.transfer-item-sub {
    color: var(--gray);
    font-size: 13px;
    margin-top: 4px;
}

.transfer-item-date {
    font-size: 12px;
    color: var(--gray);
    font-weight: 600;
    text-align: right;
}

/* Mobile Menu */
.menu-toggle {
    display: none;
}

.menu-overlay {
    display: none;
}

/* Alerts */
.alert {
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-danger {
    background: rgba(211, 47, 47, 0.1);
    border-left: 4px solid var(--danger);
    color: var(--danger);
}

.alert-success {
    background: rgba(46, 125, 50, 0.1);
    border-left: 4px solid var(--success);
    color: var(--success);
}

/* Responsive */
@media screen and (max-width: 1024px) {
    .sidebar {
        width: 240px;
    }

    .main {
        margin-left: 240px;
    }

    .transfer-layout {
        grid-template-columns: 1fr;
    }
}

@media screen and (max-width: 768px) {
    .menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        position: fixed;
        bottom: 25px;
        right: 25px;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--secondary), #FFEC8B);
        color: var(--primary);
        border: none;
        border-radius: 50%;
        box-shadow: 0 5px 20px rgba(255, 215, 0, 0.4);
        z-index: 1001;
        font-size: 24px;
        cursor: pointer;
        transition: var(--transition);
    }

    .menu-overlay {
        display: block;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 99;
        opacity: 0;
        visibility: hidden;
        transition: var(--transition);
        backdrop-filter: blur(2px);
    }

    body.menu-open .menu-overlay {
        opacity: 1;
        visibility: visible;
    }

    .sidebar {
        transform: translateX(-100%);
        box-shadow: none;
        width: 280px;
    }

    .sidebar.active {
        transform: translateX(0);
        box-shadow: 10px 0 40px rgba(0, 0, 0, 0.3);
    }

    .main {
        margin-left: 0;
        padding: 20px 15px;
        width: 100%;
    }

    .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
        padding: 20px;
    }

    .transfer-hero-content {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media screen and (max-width: 480px) {
    .sidebar {
        width: 260px;
    }

    .sidebar-header {
        padding: 20px 18px 26px;
    }

    .logo,
    .logo img {
        max-width: 120px;
    }
}
</style>
</head>
<body>

<div class="menu-overlay"></div>
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo">
                    <img src="../images/alvetrix.png" alt="Logo">
                </div>
            </div>
            <div class="academy-info">
                <div class="academy-name"><?php echo htmlspecialchars($academy_name ?? ''); ?></div>
                <div class="academy-email"><?php echo htmlspecialchars($email ?? ''); ?></div>
            </div>
        </div>

        <div class="menu">
            <?php foreach ($menu_items as $key => $item): ?>
            <?php 
                $isActive = false;
                $isSubmenuOpen = false;
                
                if ($item['submenu']) {
                    foreach($item['items'] as $subUrl) {
                        if($current_page === $subUrl) {
                            $isActive = true;
                            $isSubmenuOpen = true;
                            break;
                        }
                    }
                } else {
                    if ($current_page === $item['url']) {
                        $isActive = true;
                    }
                }
            ?>
            <div class="menu-item">
                <a href="<?php echo $item['submenu'] ? '#' : $item['url']; ?>" 
                   class="menu-link <?php echo $isActive ? 'active' : ''; ?>" 
                   data-menu="<?php echo $key; ?>">
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
                        <a href="<?php echo $subUrl; ?>" 
                           class="submenu-link <?php echo ($current_page === $subUrl) ? 'active' : ''; ?>">
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
                <h1>Selamat Datang, <?php echo htmlspecialchars($admin_name ?? ''); ?>!</h1>
                <p>Kelola transfer pemain antar team</p>
            </div>
            
            <div class="user-actions">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <div class="transfer-hero">
            <div class="transfer-hero-content">
                <div>
                    <div class="transfer-hero-title">
                        <i class="fas fa-exchange-alt"></i>
                        Transfer Center
                    </div>
                    <div class="transfer-hero-subtitle">Catat perpindahan pemain dengan rapi, cepat, dan terverifikasi.</div>
                </div>
                <div class="transfer-badges">
                    <span class="transfer-badge">Otomatis update team</span>
                    <span class="transfer-badge">Riwayat tersimpan</span>
                    <span class="transfer-badge">Validasi tim asal</span>
                </div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo implode(' ', array_map(fn($e) => htmlspecialchars($e ?? ''), $errors)); ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></span>
        </div>
        <?php endif; ?>

        <div class="transfer-layout">
            <div class="transfer-card">
                <h2 style="color: var(--primary); display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-user-switch"></i> Form Transfer
                </h2>
                <form method="POST" class="transfer-form" id="transferForm">
                    <div class="form-row">
                        <label for="player_id">Pilih Pemain</label>
                        <div class="player-search">
                            <input type="text" id="player_search" placeholder="Cari nama pemain atau tim..." autocomplete="off">
                            <input type="hidden" name="player_id" id="player_id">
                            <div class="search-results" id="playerResults"></div>
                        </div>
                    </div>

                    <div class="form-row">
                        <label>Tim Asal</label>
                        <div class="from-team-chip" id="fromTeamLabel">Pilih pemain untuk melihat tim asal</div>
                    </div>

                    <div class="form-row">
                        <label for="to_team_id">Tim Tujuan</label>
                        <select name="to_team_id" id="to_team_id" required>
                            <option value="">-- Pilih Tim Tujuan --</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo (int)$team['id']; ?>">
                                    <?php echo htmlspecialchars($team['name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="transfer_date">Tanggal Transfer</label>
                        <input type="date" name="transfer_date" id="transfer_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i>
                        Simpan Transfer
                    </button>
                </form>
            </div>

            <div class="transfer-card">
                <h2 style="color: var(--primary); display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-history"></i> Riwayat Transfer
                </h2>
                <div class="transfer-search">
                    <input type="text" id="transfer_search" placeholder="Cari pemain atau tim..." autocomplete="off">
                </div>
                <div class="transfer-list" style="margin-top: 10px;">
                    <?php if (!empty($recent_transfers)): ?>
                        <?php foreach ($recent_transfers as $tr): ?>
                        <div class="transfer-item">
                            <div>
                                <div class="transfer-item-title">
                                    <?php echo htmlspecialchars($tr['player_name'] ?? 'Unknown'); ?>
                                </div>
                                <div class="transfer-item-sub">
                                    <?php echo htmlspecialchars($tr['from_team_name'] ?? 'Free Agent'); ?> -> <?php echo htmlspecialchars($tr['to_team_name'] ?? 'Free Agent'); ?>
                                </div>
                            </div>
                            <div class="transfer-item-date">
                                <?php echo !empty($tr['transfer_date']) ? date('d M Y', strtotime($tr['transfer_date'])) : '-'; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="transfer-item">
                            <div>
                                <div class="transfer-item-title">Belum ada transfer</div>
                                <div class="transfer-item-sub">Mulai dengan membuat transfer pertama.</div>
                            </div>
                            <div class="transfer-item-date">-</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const playerSearch = document.getElementById('player_search');
    const playerIdInput = document.getElementById('player_id');
    const playerResults = document.getElementById('playerResults');
    const fromTeamLabel = document.getElementById('fromTeamLabel');
    const transferSearch = document.getElementById('transfer_search');
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.menu-overlay');

    const players = <?php echo json_encode(array_map(function($player) {
        return [
            'id' => (int)($player['id'] ?? 0),
            'name' => $player['name'] ?? '',
            'team' => $player['team_name'] ?? 'Free Agent'
        ];
    }, $players), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

    const buildLabel = (player) => {
        return player.team ? `${player.name} - ${player.team}` : player.name;
    };

    const renderResults = (items) => {
        if (!playerResults) return;
        if (!items.length) {
            playerResults.classList.remove('active');
            playerResults.innerHTML = '';
            return;
        }

        playerResults.innerHTML = items.map((player) => {
            const safeName = player.name.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            const safeTeam = player.team.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return `
                <div class="search-item" data-id="${player.id}" data-name="${safeName}" data-team="${safeTeam}">
                    <div class="search-item-title">${safeName}</div>
                    <div class="search-item-sub">${safeTeam}</div>
                </div>
            `;
        }).join('');
        playerResults.classList.add('active');
    };

    const clearSelection = () => {
        if (playerIdInput) {
            playerIdInput.value = '';
        }
        if (fromTeamLabel) {
            fromTeamLabel.textContent = 'Pilih pemain untuk melihat tim asal';
        }
    };

    const setSelection = (player) => {
        if (playerIdInput) {
            playerIdInput.value = player.id;
        }
        if (playerSearch) {
            playerSearch.value = buildLabel(player);
        }
        if (fromTeamLabel) {
            fromTeamLabel.textContent = player.team || 'Free Agent';
        }
        if (playerResults) {
            playerResults.classList.remove('active');
        }
    };

    if (playerSearch) {
        playerSearch.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            if (!query) {
                renderResults([]);
                clearSelection();
                return;
            }

            const matches = players.filter((player) => {
                const name = (player.name || '').toLowerCase();
                const team = (player.team || '').toLowerCase();
                return name.includes(query) || team.includes(query);
            }).slice(0, 15);

            renderResults(matches);

            const exact = matches.find((player) => buildLabel(player).toLowerCase() === query);
            if (exact) {
                setSelection(exact);
            } else {
                if (playerIdInput) {
                    playerIdInput.value = '';
                }
            }
        });

        playerSearch.addEventListener('focus', function() {
            const query = this.value.trim().toLowerCase();
            if (query) {
                const matches = players.filter((player) => {
                    const name = (player.name || '').toLowerCase();
                    const team = (player.team || '').toLowerCase();
                    return name.includes(query) || team.includes(query);
                }).slice(0, 15);
                renderResults(matches);
            }
        });
    }

    if (playerResults) {
        playerResults.addEventListener('click', function(event) {
            const item = event.target.closest('.search-item');
            if (!item) return;

            const playerId = parseInt(item.getAttribute('data-id'), 10);
            const team = item.getAttribute('data-team') || 'Free Agent';
            const name = item.getAttribute('data-name') || '';
            const player = { id: playerId, name: name, team: team };
            setSelection(player);
        });
    }

    document.addEventListener('click', function(event) {
        if (!playerResults || !playerSearch) return;
        if (!playerResults.contains(event.target) && event.target !== playerSearch) {
            playerResults.classList.remove('active');
        }
    });

    if (transferSearch) {
        transferSearch.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            document.querySelectorAll('.transfer-item').forEach((item) => {
                const text = item.textContent.toLowerCase();
                if (!query || text.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    if (menuToggle && sidebar && overlay) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            document.body.classList.toggle('menu-open');
        });

        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            document.body.classList.remove('menu-open');
        });
    }

    // Submenu toggle functionality
    document.querySelectorAll('.menu-link').forEach(link => {
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
});
</script>
</body>
</html>
