<?php
session_start();

// Debug path
// echo "Current dir: " . __DIR__ . "<br>";
// echo "Config path: " . __DIR__ . '/config/database.php' . "<br>";
// echo "File exists: " . (file_exists(__DIR__ . '/config/database.php') ? 'YES' : 'NO') . "<br>";

// Load database config
$config_path = __DIR__ . '/../config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $name = trim($_POST['name'] ?? '');
        $alias = trim($_POST['alias'] ?? '');
        $coach = trim($_POST['coach'] ?? '');
        $established_year = trim($_POST['established_year'] ?? '');
        $uniform_color = trim($_POST['uniform_color'] ?? '');
        $basecamp = trim($_POST['basecamp'] ?? '');
        $sport_type = trim($_POST['sport_type'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Nama team harus diisi";
        }
        
        if (empty($coach)) {
            $errors[] = "Manager/Coach harus diisi";
        }
        
        if (empty($established_year)) {
            $errors[] = "Tahun berdiri harus diisi";
        } elseif (!is_numeric($established_year) || $established_year < 1900 || $established_year > date('Y')) {
            $errors[] = "Tahun berdiri harus berupa angka antara 1900 dan " . date('Y');
        }
        
        if (empty($sport_type)) {
            $errors[] = "Cabor harus diisi";
        }
        
        // Handle logo upload
        $logo_path = '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['logo']['type'];
            $file_size = $_FILES['logo']['size'];
            $file_name = $_FILES['logo']['name'];
            
            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "Format file logo harus berupa gambar (JPEG, PNG, atau GIF)";
            }
            
            if ($file_size > 5 * 1024 * 1024) { // 5MB
                $errors[] = "Ukuran file logo maksimal 5MB";
            }
            
            if (empty($errors)) {
                $upload_dir = '../../images/teams/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                $new_filename = 'team_' . time() . '_' . uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_path)) {
                    $logo_path = $new_filename;
                } else {
                    $errors[] = "Gagal mengupload logo";
                }
            }
        }
        
        if (empty($errors)) {
            // Insert team data
            $stmt = $conn->prepare("INSERT INTO teams (name, alias, coach, established_year, uniform_color, basecamp, sport_type, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$name, $alias, $coach, $established_year, $uniform_color, $basecamp, $sport_type, $is_active]);
            
            $team_id = $conn->lastInsertId();
            
            // Update logo path if uploaded
            if ($logo_path && $team_id) {
                $update_stmt = $conn->prepare("UPDATE teams SET logo = ? WHERE id = ?");
                $update_stmt->execute([$logo_path, $team_id]);
            }
            
            $_SESSION['success_message'] = "Team berhasil ditambahkan!";
            header("Location: team.php");
            exit;
        } else {
            $form_errors = $errors;
        }
    } catch (Exception $e) {
        $form_error = "Error: " . $e->getMessage();
        error_log("Team Add Error: " . $e->getMessage());
    }
}

$academy_name = "Marbella Academy";
$email = "marbellacommunitycenter@gmail.com";

// Data menu dropdown (sama seperti dashboard)
$menu_items = [
    'dashboard' => [
        'icon' => '🏠',
        'name' => 'Dashboard',
        'submenu' => false
    ],
    'master' => [
        'icon' => '📊',
        'name' => 'Master Data',
        'submenu' => true,
        'items' => ['player', 'team', 'team_staff']
    ],
    'event' => [
        'icon' => '📅',
        'name' => 'Event',
        'submenu' => true,
        'items' => ['event', 'player_liga', 'staff_liga']
    ],
    'match' => [
        'icon' => '⚽',
        'name' => 'Match',
        'submenu' => false
    ],
    'challenge' => [
        'icon' => '🏆',
        'name' => 'Challenge',
        'submenu' => false
    ],
    'training' => [
        'icon' => '🎯',
        'name' => 'Training',
        'submenu' => false
    ],
    'settings' => [
        'icon' => '⚙️',
        'name' => 'Settings',
        'submenu' => false
    ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Team - FutScore</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

.logo-container {
    position: relative;
    display: inline-block;
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
    position: relative;
    overflow: hidden;
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

.notification {
    position: relative;
    cursor: pointer;
    font-size: 22px;
    color: var(--primary);
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--danger);
    color: white;
    font-size: 12px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
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

/* File Upload */
.file-upload-container {
    border: 2px dashed #e0e0e0;
    border-radius: 16px;
    padding: 30px;
    text-align: center;
    transition: var(--transition);
    background: #f8f9fa;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.file-upload-container:hover {
    border-color: var(--primary);
    background: #f0f7ff;
}

.file-upload-container.drag-over {
    border-color: var(--primary);
    background: #e6f0ff;
    transform: translateY(-2px);
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
    font-size: 48px;
    color: var(--primary);
    margin-bottom: 15px;
    display: block;
}

.file-upload-text {
    font-size: 16px;
    color: var(--gray);
    margin-bottom: 10px;
}

.file-upload-subtext {
    font-size: 14px;
    color: var(--gray);
    opacity: 0.8;
}

.file-preview {
    display: none;
    margin-top: 20px;
    text-align: center;
}

.file-preview img {
    max-width: 200px;
    max-height: 200px;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
}

.file-preview .file-info {
    margin-top: 10px;
    font-size: 14px;
    color: var(--gray);
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

.btn {
    padding: 12px 25px;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
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

.btn-secondary {
    background: #6c757d;
    color: white;
    box-shadow: 0 5px 15px rgba(108, 117, 125, 0.2);
}

.btn-secondary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
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
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
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

/* Mobile Styles */
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
                <div class="academy-name"><?php echo $academy_name; ?></div>
                <div class="academy-email"><?php echo $email; ?></div>
            </div>
        </div>

        <div class="menu">
            <?php foreach ($menu_items as $key => $item): ?>
            <div class="menu-item">
                <a href="<?php echo $key === 'dashboard' ? 'dashboard.php' : '#'; ?>" 
                   class="menu-link <?php echo $key === 'master' ? 'active' : ''; ?>" 
                   data-menu="<?php echo $key; ?>">
                    <span class="menu-icon"><?php echo $item['icon']; ?></span>
                    <span class="menu-text"><?php echo $item['name']; ?></span>
                    <?php if ($item['submenu']): ?>
                    <span class="menu-arrow">›</span>
                    <?php endif; ?>
                </a>
                
                <?php if ($item['submenu']): ?>
                <div class="submenu <?php echo $key === 'master' ? 'open' : ''; ?>" id="submenu-<?php echo $key; ?>">
                    <?php foreach ($item['items'] as $subitem): ?>
                    <div class="submenu-item">
                        <?php 
                        $subitem_url = '';
                        if ($subitem === 'player') {
                            $subitem_url = 'player.php';
                        } elseif ($subitem === 'team') {
                            $subitem_url = 'team.php';
                        } else {
                            $subitem_url = $subitem . '.php';
                        }
                        ?>
                        <a href="<?php echo $subitem_url; ?>" 
                           class="submenu-link <?php echo $subitem === 'team' ? 'active' : ''; ?>">
                           <?php echo ucfirst(str_replace('_', ' ', $subitem)); ?>
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
                <h1>Add New Team ⚽</h1>
                <p>Tambahkan data team baru ke sistem</p>
            </div>
            
            <div class="user-actions">
                <div class="notification">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">0</span>
                </div>
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
                <span>Tambah Team Baru</span>
            </div>
        </div>

        <?php if (isset($form_error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $form_error; ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($form_errors) && !empty($form_errors)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Periksa kembali form Anda:</strong>
                <ul style="margin-top: 5px; padding-left: 20px;">
                    <?php foreach ($form_errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- ADD TEAM FORM -->
        <div class="form-container">
            <form method="POST" action="team.php" enctype="multipart/form-data" id="teamForm">
                <!-- Basic Information Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Informasi Dasar Team
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="name">
                                Nama Team <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   class="form-input" 
                                   placeholder="Masukkan nama team"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="alias">
                                Nama Alias/Pendek
                            </label>
                            <input type="text" 
                                   id="alias" 
                                   name="alias" 
                                   class="form-input" 
                                   placeholder="Contoh: BUFC, SSB ABC"
                                   value="<?php echo htmlspecialchars($_POST['alias'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="coach">
                                Manager/Coach <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="coach" 
                                   name="coach" 
                                   class="form-input" 
                                   placeholder="Masukkan nama manager/coach"
                                   value="<?php echo htmlspecialchars($_POST['coach'] ?? ''); ?>"
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="established_year">
                                Tanggal Berdiri <span class="required">*</span>
                            </label>
                            <input type="number" 
                                   id="established_year" 
                                   name="established_year" 
                                   class="form-input" 
                                   placeholder="Contoh: 2020"
                                   min="1900" 
                                   max="<?php echo date('Y'); ?>"
                                   value="<?php echo htmlspecialchars($_POST['established_year'] ?? ''); ?>"
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Appearance Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-palette"></i>
                        Penampilan & Identitas
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="logo">
                                Logo Team
                            </label>
                            <div class="file-upload-container" id="logoUpload">
                                <input type="file" 
                                       id="logo" 
                                       name="logo" 
                                       class="file-upload-input"
                                       accept="image/jpeg,image/png,image/gif">
                                <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                <div class="file-upload-text">Klik atau drag & drop logo team di sini</div>
                                <div class="file-upload-subtext">Format: JPEG, PNG, GIF | Maks: 5MB</div>
                                <div class="file-preview" id="logoPreview">
                                    <img id="logoPreviewImg" src="" alt="Preview">
                                    <div class="file-info" id="logoFileInfo"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="uniform_color">
                                Warna Kostum
                            </label>
                            <input type="text" 
                                   id="uniform_color" 
                                   name="uniform_color" 
                                   class="form-input" 
                                   placeholder="Contoh: Biru-Kuning, Merah-Putih"
                                   value="<?php echo htmlspecialchars($_POST['uniform_color'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Location & Sport Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Lokasi & Cabor
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="basecamp">
                                Basecamp
                            </label>
                            <input type="text" 
                                   id="basecamp" 
                                   name="basecamp" 
                                   class="form-input" 
                                   placeholder="Masukkan lokasi basecamp team"
                                   value="<?php echo htmlspecialchars($_POST['basecamp'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="sport_type">
                                Cabor <span class="required">*</span>
                            </label>
                            <select id="sport_type" name="sport_type" class="form-select" required>
                                <option value="">Pilih Cabor</option>
                                <option value="Futsal" <?php echo (($_POST['sport_type'] ?? '') === 'Futsal') ? 'selected' : ''; ?>>Futsal</option>
                                <option value="Sepak Bola" <?php echo (($_POST['sport_type'] ?? '') === 'Sepak Bola') ? 'selected' : ''; ?>>Sepak Bola</option>
                                <option value="Basket" <?php echo (($_POST['sport_type'] ?? '') === 'Basket') ? 'selected' : ''; ?>>Basket</option>
                                <option value="Voli" <?php echo (($_POST['sport_type'] ?? '') === 'Voli') ? 'selected' : ''; ?>>Voli</option>
                                <option value="Badminton" <?php echo (($_POST['sport_type'] ?? '') === 'Badminton') ? 'selected' : ''; ?>>Badminton</option>
                                <option value="Tenis Meja" <?php echo (($_POST['sport_type'] ?? '') === 'Tenis Meja') ? 'selected' : ''; ?>>Tenis Meja</option>
                                <option value="Renang" <?php echo (($_POST['sport_type'] ?? '') === 'Renang') ? 'selected' : ''; ?>>Renang</option>
                                <option value="Atletik" <?php echo (($_POST['sport_type'] ?? '') === 'Atletik') ? 'selected' : ''; ?>>Atletik</option>
                                <option value="Bulu Tangkis" <?php echo (($_POST['sport_type'] ?? '') === 'Bulu Tangkis') ? 'selected' : ''; ?>>Bulu Tangkis</option>
                                <option value="Judo" <?php echo (($_POST['sport_type'] ?? '') === 'Judo') ? 'selected' : ''; ?>>Judo</option>
                                <option value="Taekwondo" <?php echo (($_POST['sport_type'] ?? '') === 'Taekwondo') ? 'selected' : ''; ?>>Taekwondo</option>
                                <option value="Silat" <?php echo (($_POST['sport_type'] ?? '') === 'Silat') ? 'selected' : ''; ?>>Silat</option>
                                <option value="Panahan" <?php echo (($_POST['sport_type'] ?? '') === 'Panahan') ? 'selected' : ''; ?>>Panahan</option>
                                <option value="Angkat Besi" <?php echo (($_POST['sport_type'] ?? '') === 'Angkat Besi') ? 'selected' : ''; ?>>Angkat Besi</option>
                                <option value="Berenang" <?php echo (($_POST['sport_type'] ?? '') === 'Berenang') ? 'selected' : ''; ?>>Berenang</option>
                                <option value="Lainnya" <?php echo (($_POST['sport_type'] ?? '') === 'Lainnya') ? 'selected' : ''; ?>>Lainnya</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Status Section -->
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-toggle-on"></i>
                        Status Team
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" checked>
                            <label for="is_active" style="font-weight: normal;">Team Aktif</label>
                        </div>
                        <div style="font-size: 12px; color: var(--gray); margin-top: 5px;">
                            Centang jika team ini aktif dan dapat digunakan dalam sistem
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="team.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Kembali
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Simpan Team
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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
    
    // Highlight active menu
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.menu-link, .submenu-link').forEach(link => {
        if (link.getAttribute('href') === currentPage || 
            link.getAttribute('href') === 'team.php') {
            link.classList.add('active');
            
            // Open parent submenu if exists
            const parentMenu = link.closest('.submenu');
            if (parentMenu) {
                parentMenu.classList.add('open');
                const arrow = parentMenu.previousElementSibling.querySelector('.menu-arrow');
                if (arrow) arrow.classList.add('rotate');
            }
        }
    });

    // File Upload Logic
    const logoUpload = document.getElementById('logoUpload');
    const logoInput = document.getElementById('logo');
    const logoPreview = document.getElementById('logoPreview');
    const logoPreviewImg = document.getElementById('logoPreviewImg');
    const logoFileInfo = document.getElementById('logoFileInfo');

    // Drag and Drop
    logoUpload.addEventListener('dragover', function(e) {
        e.preventDefault();
        logoUpload.classList.add('drag-over');
    });

    logoUpload.addEventListener('dragleave', function() {
        logoUpload.classList.remove('drag-over');
    });

    logoUpload.addEventListener('drop', function(e) {
        e.preventDefault();
        logoUpload.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length) {
            logoInput.files = files;
            handleFileSelect(files[0]);
        }
    });

    // Click to Upload
    logoUpload.addEventListener('click', function() {
        logoInput.click();
    });

    logoInput.addEventListener('change', function() {
        if (this.files.length) {
            handleFileSelect(this.files[0]);
        }
    });

    function handleFileSelect(file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!allowedTypes.includes(file.type)) {
            alert('Format file harus berupa gambar (JPEG, PNG, atau GIF)');
            return;
        }

        if (file.size > maxSize) {
            alert('Ukuran file maksimal 5MB');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            logoPreviewImg.src = e.target.result;
            logoFileInfo.textContent = `File: ${file.name} (${formatFileSize(file.size)})`;
            logoPreview.style.display = 'block';
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

    // Form Validation
    const form = document.getElementById('teamForm');
    form.addEventListener('submit', function(e) {
        const name = document.getElementById('name').value.trim();
        const coach = document.getElementById('coach').value.trim();
        const establishedYear = document.getElementById('established_year').value.trim();
        const sportType = document.getElementById('sport_type').value.trim();

        if (!name || !coach || !establishedYear || !sportType) {
            e.preventDefault();
            alert('Harap isi semua field yang wajib diisi (*)');
            return;
        }

        const currentYear = new Date().getFullYear();
        if (establishedYear < 1900 || establishedYear > currentYear) {
            e.preventDefault();
            alert(`Tahun berdiri harus antara 1900 dan ${currentYear}`);
            return;
        }
    });
});
</script>
</body>
</html>