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

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

// --- DATA MENU DROPDOWN ---
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
            'team_staff' => 'team_staff.php'
        ]
    ],
    'event' => [
        'icon' => '🏆',
        'name' => 'Event',
        'url' => 'challenge.php',  // Langsung ke challenge.php
        'submenu' => false         // Tidak ada submenu
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

// Mendapatkan nama file saat ini untuk penanda menu 'Active'
$current_page = basename($_SERVER['PHP_SELF']);

// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';

$academy_name = "Hi, Welcome...";
$email = $admin_email;

// Initialize variables
$errors = [];
$form_data = [
    'challenger_id' => '',
    'opponent_id' => '',
    'venue_id' => '',
    'challenge_date' => '',
    'challenge_time' => '18:00',
    'sport_type' => '',
    'notes' => '',
    'expiry_hours' => 24
];

// Fetch teams for dropdown
try {
    $teams_stmt = $conn->prepare("SELECT id, name, sport_type FROM teams WHERE is_active = 1 ORDER BY name ASC");
    $teams_stmt->execute();
    $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch venues for dropdown
    $venues_stmt = $conn->prepare("SELECT id, name, location FROM venues WHERE is_active = 1 ORDER BY name ASC");
    $venues_stmt->execute();
    $venues = $venues_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data = [
        'challenger_id' => trim($_POST['challenger_id'] ?? ''),
        'opponent_id' => trim($_POST['opponent_id'] ?? ''),
        'venue_id' => trim($_POST['venue_id'] ?? ''),
        'challenge_date' => trim($_POST['challenge_date'] ?? ''),
        'challenge_time' => trim($_POST['challenge_time'] ?? '18:00'),
        'sport_type' => trim($_POST['sport_type'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
        'expiry_hours' => intval($_POST['expiry_hours'] ?? 24)
    ];
    
    // Validation
    if (empty($form_data['challenger_id'])) {
        $errors['challenger_id'] = "Challenger harus dipilih";
    }
    
    if (empty($form_data['opponent_id'])) {
        $errors['opponent_id'] = "Opponent harus dipilih";
    }
    
    if ($form_data['challenger_id'] == $form_data['opponent_id']) {
        $errors['opponent_id'] = "Challenger dan Opponent tidak boleh sama";
    }
    
    if (empty($form_data['venue_id'])) {
        $errors['venue_id'] = "Venue harus dipilih";
    }
    
    if (empty($form_data['challenge_date'])) {
        $errors['challenge_date'] = "Tanggal challenge harus diisi";
    }
    
    if (empty($form_data['challenge_time'])) {
        $errors['challenge_time'] = "Waktu challenge harus diisi";
    }
    
    if (empty($form_data['sport_type'])) {
        $errors['sport_type'] = "Cabor harus dipilih";
    }
    
    // Generate challenge code
    $challenge_code = 'CH' . date('Ymd') . strtoupper(substr(uniqid(), -6));
    
    // Calculate expiry date
    $challenge_datetime = $form_data['challenge_date'] . ' ' . $form_data['challenge_time'] . ':00';
    $expiry_datetime = date('Y-m-d H:i:s', strtotime($challenge_datetime . ' -' . $form_data['expiry_hours'] . ' hours'));
    
    // If no errors, insert to database
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO challenges (
                    challenge_code, challenger_id, opponent_id, venue_id, 
                    challenge_date, expiry_date, sport_type, notes, 
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', NOW())
            ");
            
            $stmt->execute([
                $challenge_code,
                $form_data['challenger_id'],
                $form_data['opponent_id'],
                $form_data['venue_id'],
                $challenge_datetime,
                $expiry_datetime,
                $form_data['sport_type'],
                $form_data['notes']
            ]);
            
            $_SESSION['success_message'] = "Challenge berhasil dibuat dengan kode: " . $challenge_code;
            header("Location: challenge.php");
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
<title>Buat Challenge - MGP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
:root {
    --primary: #0A2463;
    --secondary: #FFD700;
    --accent: #4CC9F0;
    --success: #2E7D32;
    --warning: #F9A826;
    --danger: #D32F2F;
    --light: #F8F9FA;
    --dark: #1A1A2E;
    --gray: #6C757D;
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
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
    background: linear-gradient(180deg, var(--primary) 0%, #1a365d 100%);
    color: white;
    padding: 0;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
    box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
    transition: var(--transition);
}

.sidebar-header {
    padding: 30px 25px;
    text-align: center;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 2px solid var(--secondary);
}

.logo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--secondary) 0%, #FFEC8B 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    border: 4px solid white;
    box-shadow: 0 0 25px rgba(255, 215, 0, 0.3);
    transition: var(--transition);
}

.logo:hover {
    transform: rotate(15deg) scale(1.05);
    box-shadow: 0 0 35px rgba(255, 215, 0, 0.5);
}

.logo::before {
    content: "⚽";
    font-size: 48px;
    color: var(--primary);
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
    padding: 16px 20px;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    border-left: 4px solid transparent;
}

.menu-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: var(--secondary);
    padding-left: 25px;
}

.menu-link.active {
    background: rgba(255, 215, 0, 0.15);
    color: var(--secondary);
    border-left-color: var(--secondary);
    font-weight: 600;
}

.menu-icon {
    font-size: 22px;
    margin-right: 15px;
    width: 30px;
    text-align: center;
}

.menu-text {
    flex: 1;
    font-size: 16px;
}

.menu-arrow {
    font-size: 12px;
    transition: var(--transition);
}

.menu-arrow.rotate {
    transform: rotate(90deg);
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
    width: calc(100% - 280px);
    max-width: calc(100vw - 280px);
    overflow-x: hidden;
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
    flex-wrap: wrap;
    gap: 15px;
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

/* Team vs Team Display */
.vs-display {
    text-align: center;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    margin: 20px 0;
}

.vs-title {
    font-size: 18px;
    color: var(--primary);
    margin-bottom: 15px;
    font-weight: 600;
}

.team-vs-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 30px;
    flex-wrap: wrap;
}

.team-box {
    text-align: center;
    min-width: 200px;
}

.team-box h4 {
    font-size: 16px;
    color: var(--dark);
    margin-bottom: 5px;
}

.team-box p {
    font-size: 14px;
    color: var(--gray);
}

.vs-symbol {
    font-size: 32px;
    font-weight: bold;
    color: var(--secondary);
    background: var(--primary);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

/* Responsive */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .team-vs-container {
        flex-direction: column;
        gap: 20px;
    }
}

/* Menu Toggle Button */
.menu-toggle {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 101;
    background: var(--primary);
    color: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 22px;
    cursor: pointer;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    transition: var(--transition);
}

.menu-toggle:hover {
    background: var(--secondary);
    color: var(--primary);
    transform: rotate(90deg);
}

/* Responsive */
@media (max-width: 1200px) {
    .menu-toggle {
        display: block;
    }
    
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.active {
        transform: translateX(0);
    }
    
    .main {
        margin-left: 0;
        width: 100%;
        padding: 20px;
    }
}

@media (max-width: 1400px) {
    .page-header {
        justify-content: center;
        text-align: center;
    }
    
    .search-bar {
        width: 100%;
        max-width: 500px;
        order: 3;
    }
    
    .page-title {
        width: 100%;
        justify-content: center;
        margin-bottom: 10px;
    }
}
</style>
</head>
<body>

<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <!-- SIDEBAR -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo"></div>
            </div>
            <div class="academy-info">
                <div class="academy-name"><?php echo htmlspecialchars($academy_name); ?></div>
                <div class="academy-email"><?php echo htmlspecialchars($email); ?></div>
            </div>
        </div>

        <div class="menu">
           <?php foreach ($menu_items as $key => $item): ?>
<?php 
    // Cek apakah menu ini aktif berdasarkan URL saat ini
    $isActive = false;
    
    if ($item['submenu']) {
        // Cek jika salah satu sub-item ada yang aktif
        foreach($item['items'] as $subKey => $subUrl) {
            if($current_page === $subUrl) {
                $isActive = true;
                $isSubmenuOpen = true;
                break;
            }
        }
    } else {
        // Untuk menu Event, aktif jika di challenge_create.php, challenge.php, atau event.php
        if ($key === 'event') {
            $isActive = in_array($current_page, ['challenge_create.php', 'challenge.php', 'event.php']);
        } else {
            $isActive = ($current_page === $item['url']);
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

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="greeting">
                <h1>Buat Challenge Baru 🏆</h1>
                <p>Buat tantangan antar team</p>
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
                <span>Buat Challenge Baru</span>
            </div>
            <a href="challenge.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Daftar Challenge
            </a>
        </div>

        <!-- ERROR MESSAGES -->
        <?php if (isset($errors['database'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $errors['database']; ?>
        </div>
        <?php endif; ?>

        <!-- CREATE CHALLENGE FORM -->
        <div class="form-container">
            <form method="POST" action="" id="challengeForm">
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-users"></i>
                        Pilih Team
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="challenger_id">
                                Challenger Team <span class="required">*</span>
                            </label>
                            <select id="challenger_id" name="challenger_id" 
                                    class="form-select <?php echo isset($errors['challenger_id']) ? 'is-invalid' : ''; ?>" 
                                    required>
                                <option value="">Pilih Challenger Team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>" 
                                            data-sport="<?php echo htmlspecialchars($team['sport_type']); ?>"
                                            <?php echo $form_data['challenger_id'] == $team['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team['name']); ?> (<?php echo htmlspecialchars($team['sport_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['challenger_id'])): ?>
                                <span class="error"><?php echo $errors['challenger_id']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="opponent_id">
                                Opponent Team <span class="required">*</span>
                            </label>
                            <select id="opponent_id" name="opponent_id" 
                                    class="form-select <?php echo isset($errors['opponent_id']) ? 'is-invalid' : ''; ?>" 
                                    required>
                                <option value="">Pilih Opponent Team</option>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>"
                                            <?php echo $form_data['opponent_id'] == $team['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($team['name']); ?> (<?php echo htmlspecialchars($team['sport_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['opponent_id'])): ?>
                                <span class="error"><?php echo $errors['opponent_id']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- VS Display -->
                    <div class="vs-display" id="vsDisplay" style="display: none;">
                        <div class="vs-title">PERTANDINGAN</div>
                        <div class="team-vs-container">
                            <div class="team-box" id="challengerBox">
                                <!-- Filled by JavaScript -->
                            </div>
                            <div class="vs-symbol">VS</div>
                            <div class="team-box" id="opponentBox">
                                <!-- Filled by JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-calendar-alt"></i>
                        Detail Challenge
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="venue_id">
                                Venue/Lokasi <span class="required">*</span>
                            </label>
                            <select id="venue_id" name="venue_id" 
                                    class="form-select <?php echo isset($errors['venue_id']) ? 'is-invalid' : ''; ?>" 
                                    required>
                                <option value="">Pilih Venue</option>
                                <?php foreach ($venues as $venue): ?>
                                    <option value="<?php echo $venue['id']; ?>"
                                            <?php echo $form_data['venue_id'] == $venue['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($venue['name']); ?> (<?php echo htmlspecialchars($venue['location']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['venue_id'])): ?>
                                <span class="error"><?php echo $errors['venue_id']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="sport_type">
                                Cabor <span class="required">*</span>
                            </label>
                            <select id="sport_type" name="sport_type" 
                                    class="form-select <?php echo isset($errors['sport_type']) ? 'is-invalid' : ''; ?>" 
                                    required>
                                <option value="">Pilih Cabor</option>
                                <option value="Futsal" <?php echo $form_data['sport_type'] == 'Futsal' ? 'selected' : ''; ?>>Futsal</option>
                                <option value="Sepak Bola" <?php echo $form_data['sport_type'] == 'Sepak Bola' ? 'selected' : ''; ?>>Sepak Bola</option>
                                <option value="Basket" <?php echo $form_data['sport_type'] == 'Basket' ? 'selected' : ''; ?>>Basket</option>
                                <option value="Voli" <?php echo $form_data['sport_type'] == 'Voli' ? 'selected' : ''; ?>>Voli</option>
                                <option value="Badminton" <?php echo $form_data['sport_type'] == 'Badminton' ? 'selected' : ''; ?>>Badminton</option>
                                <option value="Tenis Meja" <?php echo $form_data['sport_type'] == 'Tenis Meja' ? 'selected' : ''; ?>>Tenis Meja</option>
                                <option value="Renang" <?php echo $form_data['sport_type'] == 'Renang' ? 'selected' : ''; ?>>Renang</option>
                                <option value="Atletik" <?php echo $form_data['sport_type'] == 'Atletik' ? 'selected' : ''; ?>>Atletik</option>
                                <option value="Bulu Tangkis" <?php echo $form_data['sport_type'] == 'Bulu Tangkis' ? 'selected' : ''; ?>>Bulu Tangkis</option>
                                <option value="Judo" <?php echo $form_data['sport_type'] == 'Judo' ? 'selected' : ''; ?>>Judo</option>
                                <option value="Taekwondo" <?php echo $form_data['sport_type'] == 'Taekwondo' ? 'selected' : ''; ?>>Taekwondo</option>
                                <option value="Silat" <?php echo $form_data['sport_type'] == 'Silat' ? 'selected' : ''; ?>>Silat</option>
                                <option value="Panahan" <?php echo $form_data['sport_type'] == 'Panahan' ? 'selected' : ''; ?>>Panahan</option>
                                <option value="Angkat Besi" <?php echo $form_data['sport_type'] == 'Angkat Besi' ? 'selected' : ''; ?>>Angkat Besi</option>
                                <option value="Lainnya" <?php echo $form_data['sport_type'] == 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                            </select>
                            <?php if (isset($errors['sport_type'])): ?>
                                <span class="error"><?php echo $errors['sport_type']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="challenge_date">
                                Tanggal Challenge <span class="required">*</span>
                            </label>
                            <input type="date" 
                                   id="challenge_date" 
                                   name="challenge_date" 
                                   class="form-input <?php echo isset($errors['challenge_date']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($form_data['challenge_date']); ?>"
                                   min="<?php echo date('Y-m-d'); ?>"
                                   required>
                            <?php if (isset($errors['challenge_date'])): ?>
                                <span class="error"><?php echo $errors['challenge_date']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="challenge_time">
                                Waktu Challenge <span class="required">*</span>
                            </label>
                            <input type="time" 
                                   id="challenge_time" 
                                   name="challenge_time" 
                                   class="form-input <?php echo isset($errors['challenge_time']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($form_data['challenge_time']); ?>"
                                   required>
                            <?php if (isset($errors['challenge_time'])): ?>
                                <span class="error"><?php echo $errors['challenge_time']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="expiry_hours">
                                Challenge Expiry (jam sebelum pertandingan)
                            </label>
                            <select id="expiry_hours" name="expiry_hours" class="form-select">
                                <option value="1" <?php echo $form_data['expiry_hours'] == 1 ? 'selected' : ''; ?>>1 Jam</option>
                                <option value="6" <?php echo $form_data['expiry_hours'] == 6 ? 'selected' : ''; ?>>6 Jam</option>
                                <option value="12" <?php echo $form_data['expiry_hours'] == 12 ? 'selected' : ''; ?>>12 Jam</option>
                                <option value="24" <?php echo $form_data['expiry_hours'] == 24 ? 'selected' : ''; ?>>24 Jam (Default)</option>
                                <option value="48" <?php echo $form_data['expiry_hours'] == 48 ? 'selected' : ''; ?>>48 Jam</option>
                                <option value="72" <?php echo $form_data['expiry_hours'] == 72 ? 'selected' : ''; ?>>72 Jam</option>
                            </select>
                            <small style="color: #666;">Challenge akan expired setelah waktu ini sebelum pertandingan</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="notes">
                                Catatan Tambahan
                            </label>
                            <textarea id="notes" name="notes" class="form-textarea" 
                                      placeholder="Masukkan catatan atau informasi tambahan..."><?php echo htmlspecialchars($form_data['notes']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>
                        Reset
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i>
                        Kirim Challenge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script>
// Simpan data teams untuk JavaScript
const teamsData = <?php echo json_encode($teams); ?>;

document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar untuk mobile
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    menuToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        this.innerHTML = sidebar.classList.contains('active') 
            ? '<i class="fas fa-times"></i>' 
            : '<i class="fas fa-bars"></i>';
    });
    
    // Auto close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1200) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        }
    });
    
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

    // Responsive adjustments
    function adjustLayout() {
        const isMobile = window.innerWidth <= 768;
        if (isMobile) {
            document.querySelector('.main').style.marginLeft = '0';
        } else if (window.innerWidth > 1200) {
            document.querySelector('.main').style.marginLeft = '280px';
        }
    }
    
    adjustLayout();
    window.addEventListener('resize', adjustLayout);
    
    // Update VS Display when teams are selected
    function updateVSDisplay() {
        const challengerId = document.getElementById('challenger_id').value;
        const opponentId = document.getElementById('opponent_id').value;
        const vsDisplay = document.getElementById('vsDisplay');
        const challengerBox = document.getElementById('challengerBox');
        const opponentBox = document.getElementById('opponentBox');
        
        // Find selected teams
        const challenger = teamsData.find(team => team.id == challengerId);
        const opponent = teamsData.find(team => team.id == opponentId);
        
        if (challenger && opponent) {
            vsDisplay.style.display = 'block';
            
            // Update challenger box
            challengerBox.innerHTML = `
                <h4>${challenger.name}</h4>
                <p>${challenger.sport_type}</p>
            `;
            
            // Update opponent box
            opponentBox.innerHTML = `
                <h4>${opponent.name}</h4>
                <p>${opponent.sport_type}</p>
            `;
            
            // Auto-set sport type if not set
            if (!document.getElementById('sport_type').value && challenger.sport_type === opponent.sport_type) {
                document.getElementById('sport_type').value = challenger.sport_type;
            }
        } else {
            vsDisplay.style.display = 'none';
        }
    }
    
    // Attach event listeners
    document.getElementById('challenger_id').addEventListener('change', updateVSDisplay);
    document.getElementById('opponent_id').addEventListener('change', updateVSDisplay);
    
    // Initial update
    updateVSDisplay();
    
    // Form Validation
    const form = document.getElementById('challengeForm');
    form.addEventListener('submit', function(e) {
        const challengerId = document.getElementById('challenger_id').value;
        const opponentId = document.getElementById('opponent_id').value;
        const venueId = document.getElementById('venue_id').value;
        const challengeDate = document.getElementById('challenge_date').value;
        const challengeTime = document.getElementById('challenge_time').value;
        const sportType = document.getElementById('sport_type').value;
        
        if (!challengerId || !opponentId || !venueId || !challengeDate || !challengeTime || !sportType) {
            e.preventDefault();
            toastr.error('Harap isi semua field yang wajib diisi (*)');
            return;
        }
        
        if (challengerId === opponentId) {
            e.preventDefault();
            toastr.error('Challenger dan Opponent tidak boleh sama');
            return;
        }
        
        // Check if date is not in the past
        const selectedDate = new Date(challengeDate + 'T' + challengeTime);
        const now = new Date();
        
        if (selectedDate < now) {
            e.preventDefault();
            toastr.error('Tanggal dan waktu challenge tidak boleh di masa lalu');
            return;
        }
    });
    
    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('challenge_date').setAttribute('min', today);
});
</script>
</body>
</html>