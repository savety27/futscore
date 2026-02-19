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

// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';

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
            'player' => ['name' => 'Player', 'url' => 'player.php'],
            'team' => ['name' => 'Team', 'url' => 'team.php'],
            'team_staff' => ['name' => 'Team Staff', 'url' => 'team_staff.php'],
            'transfer' => ['name' => 'Transfer', 'url' => 'transfer.php'],
            // HAPUS 'venue' DARI SINI
        ]
    ],
    'event' => [
        'icon' => '🏆',
        'name' => 'Event',
        'url' => 'event.php',
        'submenu' => false
    ],
    'challenge' => [
        'icon' => '⚔️',
        'name' => 'Challenge',
        'url' => 'challenge.php',
        'submenu' => false
    ],
    'venue' => [  // Menu Venue terpisah
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
$email = $admin_email;

// Initialize variables
$errors = [];
$form_data = [
    'name' => '',
    'location' => '',
    'capacity' => '',
    'facilities' => '',
    'is_active' => 1
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'capacity' => trim($_POST['capacity'] ?? ''),
        'facilities' => trim($_POST['facilities'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    // Validation
    if (empty($form_data['name'])) {
        $errors['name'] = "Nama venue harus diisi";
    }
    
    if (empty($form_data['location'])) {
        $errors['location'] = "Lokasi harus diisi";
    }
    
    if (empty($form_data['capacity'])) {
        $errors['capacity'] = "Kapasitas harus diisi";
    } elseif (!is_numeric($form_data['capacity'])) {
        $errors['capacity'] = "Kapasitas harus berupa angka";
    } elseif ($form_data['capacity'] < 1) {
        $errors['capacity'] = "Kapasitas harus lebih dari 0";
    }
    
    // If no errors, insert to database
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO venues (name, location, capacity, facilities, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $form_data['name'],
                $form_data['location'],
                $form_data['capacity'],
                $form_data['facilities'],
                $form_data['is_active']
            ]);
            
            $_SESSION['success_message'] = "Venue berhasil ditambahkan!";
            header("Location: venue.php");
            exit;
            
        } catch (PDOException $e) {
            $errors['database'] = "Gagal menyimpan data: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Venue</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
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
    --sidebar-bg: linear-gradient(180deg, #0a1628 0%, #0f2744 100%);
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
    animation: fadeIn 0.8s ease-out;
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
    font-weight: 600;
}

.submenu-link::before {
    content: "•";
    position: absolute;
    left: 0;
    color: var(--secondary);
    font-size: 18px;
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
    animation: slideDown 0.5s ease-out;
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

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
}

.page-title {
    font-size: 28px;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 15px;
}

.page-title i {
    color: var(--secondary);
    font-size: 32px;
}

.search-bar {
    position: relative;
    width: 400px;
}

.search-bar input {
    width: 100%;
    padding: 15px 50px 15px 20px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 16px;
    transition: var(--transition);
    background: #f8f9fa;
}

.search-bar input:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
}

.search-bar button {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--primary);
    font-size: 18px;
    cursor: pointer;
}

.action-buttons {
    display: flex;
    gap: 15px;
}

.btn {
    padding: 12px 25px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition);
    font-size: 15px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
    box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(10, 36, 99, 0.3);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #4CAF50);
    color: white;
    box-shadow: 0 5px 15px rgba(46, 125, 50, 0.2);
}

.btn-success:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(46, 125, 50, 0.3);
}

.btn-secondary {
    background: #6c757d;
    color: white;
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
}

.btn-secondary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
}

/* Form Styles */
.form-container {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: var(--card-shadow);
    margin-bottom: 30px;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 20px;
    color: var(--primary);
    margin-bottom: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark);
    font-size: 14px;
}

.required {
    color: var(--danger);
}

.form-input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 16px;
    transition: var(--transition);
    background: #f8f9fa;
    font-family: inherit;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
}

.form-textarea {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 16px;
    transition: var(--transition);
    background: #f8f9fa;
    font-family: inherit;
    resize: vertical;
    min-height: 100px;
}

.form-select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 16px;
    transition: var(--transition);
    background: #f8f9fa;
    cursor: pointer;
}

.form-select:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.1);
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.checkbox-group input {
    width: auto;
    margin-right: 10px;
}

/* Action Buttons */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #f0f0f0;
}

/* Alert */
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

.alert-warning {
    background: rgba(249, 168, 38, 0.1);
    border-left: 4px solid var(--warning);
    color: var(--warning);
}

/* Error styling */
.error {
    color: var(--danger);
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.is-invalid {
    border-color: var(--danger) !important;
}


/* =========================================
   MOBILE RESPONSIVE DESIGN
   ========================================= */

/* Default: Hide mobile-only elements on desktop */
.menu-toggle {
    display: none;
}

.menu-overlay {
    display: none;
}

/* ===== TABLET (max-width: 1024px) ===== */
@media screen and (max-width: 1024px) {
    .sidebar {
        width: 240px;
    }

    .main {
        margin-left: 240px;
        width: calc(100% - 240px);
        max-width: calc(100vw - 240px);
    }
}

/* ===== MOBILE LANDSCAPE (max-width: 768px) ===== */
@media screen and (max-width: 768px) {
    
    /* Show Mobile Menu Toggle Button */
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

    .menu-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 8px 25px rgba(255, 215, 0, 0.6);
    }

    .menu-toggle:active {
        transform: scale(0.95);
    }

    /* Sidebar: Hidden by default on mobile */
    .sidebar {
        transform: translateX(-100%);
        box-shadow: none;
        width: 280px;
    }

    /* Sidebar: Show when active */
    .sidebar.active {
        transform: translateX(0);
        box-shadow: 10px 0 40px rgba(0, 0, 0, 0.3);
    }

    /* Overlay: Show when menu is open */
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

    /* Main Content: Full width on mobile */
    .main {
        margin-left: 0;
        padding: 20px 15px;
        width: 100%;
        max-width: 100vw;
    }

    /* Topbar: Stack vertically */
    .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
        padding: 20px;
    }

    .greeting h1 {
        font-size: 24px;
    }

    .user-actions {
        width: 100%;
        display: flex;
        justify-content: flex-end;
    }

    /* Page Header: Stack vertically */
    .page-header {
        flex-direction: column;
        gap: 20px;
        align-items: flex-start;
    }

    .search-bar {
        width: 100%;
        max-width: 100%;
    }

    .action-buttons {
        width: 100%;
        flex-wrap: wrap;
    }

    .btn {
        flex: 1;
        justify-content: center;
    }
    
    /* Form Grid optimization */
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column-reverse;
        gap: 10px;
    }
}

/* ===== MOBILE PORTRAIT (max-width: 480px) ===== */
@media screen and (max-width: 480px) {
    
    /* Reduce font sizes */
    .greeting h1 {
        font-size: 20px;
    }
    
    .greeting p {
        font-size: 13px;
    }

    .page-title {
        font-size: 20px;
    }

    .page-title i {
        font-size: 24px;
    }

    /* Compact sidebar */
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

    .academy-name {
        font-size: 18px;
    }
    
    /* Compact menu */
    .menu {
        padding: 20px 10px;
    }

    .menu-link {
        padding: 14px 15px;
        font-size: 15px;
    }

    .menu-icon {
        font-size: 20px;
        width: 28px;
    }

    /* Smaller mobile toggle button */
    .menu-toggle {
        width: 55px;
        height: 55px;
        font-size: 22px;
        bottom: 20px;
        right: 20px;
    }

    /* Compact buttons */
    .btn {
        padding: 10px 18px;
        font-size: 14px;
    }

    .logout-btn {
        padding: 10px 20px;
        font-size: 14px;
    }
}
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
</head>
<body>


<!-- Mobile Menu Components -->
<div class="menu-overlay"></div>
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <!-- SIDEBAR -->
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
                // Cek apakah menu ini aktif berdasarkan URL saat ini
                $isActive = false;
                $isSubmenuOpen = false;
                
                if ($item['submenu']) {
                    // Cek jika salah satu sub-item ada yang aktif
                    foreach($item['items'] as $subKey => $subItem) {
                        if($current_page === $subItem['url']) {
                            $isActive = true;
                            $isSubmenuOpen = true;
                            break;
                        }
                    }
                } else {
                    // Cek jika halaman saat ini adalah venue.php atau venue_create.php
                    if ($current_page === $item['url'] || 
                        ($key === 'venue' && ($current_page === 'venue.php' || $current_page === 'venue_create.php' || $current_page === 'venue_edit.php' || $current_page === 'venue_view.php'))) {
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
                    <?php foreach ($item['items'] as $subKey => $subItem): ?>
                    <div class="submenu-item">
                        <a href="<?php echo $subItem['url']; ?>" 
                           class="submenu-link <?php echo ($current_page === $subItem['url']) ? 'active' : ''; ?>">
                           <?php echo $subItem['name']; ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="greeting">
                <h1>Tambah Venue Baru 🏟️</h1>
                <p>Tambahkan data venue baru ke sistem</p>
            </div>
            
            <div class="user-actions">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-title">
                <i class="fas fa-plus-circle"></i>
                <span>Tambah Venue Baru</span>
            </div>
            <a href="venue.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Daftar Venue
            </a>
        </div>

        <!-- ERROR MESSAGES -->
        <?php if (isset($errors['database'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $errors['database']; ?>
        </div>
        <?php endif; ?>

        <!-- ADD VENUE FORM -->
        <div class="form-container">
            <form method="POST" action="" id="venueForm">
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Informasi Dasar Venue
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="name">
                                Nama Venue <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   class="form-input <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>"
                                   placeholder="Contoh: GOR Buluh Indah, Stadion Utama"
                                   required>
                            <?php if (isset($errors['name'])): ?>
                                <span class="error"><?php echo $errors['name']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="location">
                                Lokasi <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="location" 
                                   name="location" 
                                   class="form-input <?php echo isset($errors['location']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($form_data['location'] ?? ''); ?>"
                                   placeholder="Contoh: Jl. Buluh Indah No. 123, Jakarta"
                                   required>
                            <?php if (isset($errors['location'])): ?>
                                <span class="error"><?php echo $errors['location']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="capacity">
                                Kapasitas <span class="required">*</span>
                            </label>
                            <input type="number" 
                                   id="capacity" 
                                   name="capacity" 
                                   class="form-input <?php echo isset($errors['capacity']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($form_data['capacity'] ?? ''); ?>"
                                   placeholder="Contoh: 500"
                                   min="1" 
                                   max="100000"
                                   required>
                            <?php if (isset($errors['capacity'])): ?>
                                <span class="error"><?php echo $errors['capacity']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-wrench"></i>
                        Fasilitas Venue
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="facilities">
                            Daftar Fasilitas
                        </label>
                        <textarea id="facilities" 
                                  name="facilities" 
                                  class="form-textarea"
                                  placeholder="Deskripsikan fasilitas yang tersedia di venue ini. Pisahkan dengan koma atau buat dalam poin-poin.
Contoh:
- Lapangan futsal standar internasional
- Ruang ganti pemain
- Toilet
- Musholla
- Kantin
- Parkir luas untuk 100 mobil"><?php echo htmlspecialchars($form_data['facilities'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-toggle-on"></i>
                        Status Venue
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo $form_data['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active" style="font-weight: normal;">Venue Aktif</label>
                        </div>
                        <small style="color: #666;">Venue aktif akan tampil dalam sistem dan dapat dipilih untuk event</small>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>
                        Reset
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Simpan Venue
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>

document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Toggle Functionality
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.menu-overlay');

    if (menuToggle && sidebar && overlay) {
        // Toggle menu when clicking hamburger button
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            document.body.classList.toggle('menu-open');
        });

        // Close menu when clicking overlay
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            document.body.classList.remove('menu-open');
        });

        // Close menu when clicking a menu link (better UX on mobile)
        const menuLinks = document.querySelectorAll('.menu-link');
        menuLinks.forEach(function(link) {
            // Only close if it's not a submenu toggle
            if (!link.querySelector('.menu-arrow')) {
                link.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('menu-open');
                });
            }
        });
    }
    
    // Menu toggle functionality (untuk Submenu)
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

    // Form Validation
    const form = document.getElementById('venueForm');
    form.addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        const location = document.getElementById('location').value.trim();
        const capacity = document.getElementById('capacity').value.trim();

        if (!name || !location || !capacity) {
            e.preventDefault();
            toastr.error('Harap isi semua field yang wajib diisi (*)');
            return;
        }

        const capacityNum = parseInt(capacity);
        if (capacityNum < 1 || capacityNum > 100000) {
            e.preventDefault();
            toastr.error('Kapasitas harus antara 1 dan 100.000');
            return;
        }
    });
});
</script>
</body>
</html>
