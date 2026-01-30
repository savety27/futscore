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

$academy_name = "Hi, Welcome...";
$email = $admin_email;

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

// Mendapatkan nama file saat ini untuk penanda menu 'Active'
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize variables
$errors = [];
$form_data = [
    'judul' => '',
    'slug' => '',
    'konten' => '',
    'penulis' => '',
    'status' => 'draft',
    'tag' => ''
];

// Fungsi untuk generate slug
function generateSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data = [
        'judul' => trim($_POST['judul'] ?? ''),
        'slug' => trim($_POST['slug'] ?? ''),
        'konten' => trim($_POST['konten'] ?? ''),
        'penulis' => trim($_POST['penulis'] ?? ''),
        'status' => $_POST['status'] ?? 'draft',
        'tag' => trim($_POST['tag'] ?? '')
    ];
    
    // Validation
    if (empty($form_data['judul'])) {
        $errors['judul'] = "Judul berita harus diisi";
    } elseif (strlen($form_data['judul']) < 5) {
        $errors['judul'] = "Judul minimal 5 karakter";
    } elseif (strlen($form_data['judul']) > 200) {
        $errors['judul'] = "Judul maksimal 200 karakter";
    }
    
    if (empty($form_data['slug'])) {
        // Generate slug from judul
        $form_data['slug'] = generateSlug($form_data['judul']);
    } else {
        $form_data['slug'] = generateSlug($form_data['slug']);
    }
    
    // Check if slug already exists
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM berita WHERE slug = ?");
            $stmt->execute([$form_data['slug']]);
            if ($stmt->rowCount() > 0) {
                // Add timestamp to make it unique
                $form_data['slug'] .= '-' . time();
            }
        } catch (PDOException $e) {
            $errors['database'] = "Gagal memeriksa slug: " . $e->getMessage();
        }
    }
    
    if (empty($form_data['konten'])) {
        $errors['konten'] = "Konten berita harus diisi";
    }
    
    if (empty($form_data['penulis'])) {
        $errors['penulis'] = "Penulis harus diisi";
    }
    
    // Handle file upload
    $gambar_path = null;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['gambar'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Check file type
        if (!in_array($file['type'], $allowed_types)) {
            $errors['gambar'] = "Format file harus JPG, PNG, GIF, atau WebP";
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors['gambar'] = "Ukuran file maksimal 5MB";
        }
        
        if (!isset($errors['gambar'])) {
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'berita_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_dir = '../images/berita/';
            
            // Create directory if not exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $gambar_path = $filename;
            } else {
                $errors['gambar'] = "Gagal mengupload gambar";
            }
        }
    }
    
    // If no errors, insert to database
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO berita (judul, slug, konten, gambar, penulis, status, tag, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $form_data['judul'],
                $form_data['slug'],
                $form_data['konten'],
                $gambar_path,
                $form_data['penulis'],
                $form_data['status'],
                $form_data['tag']
            ]);
            
            $_SESSION['success_message'] = "Berita berhasil ditambahkan!";
            header("Location: berita.php");
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
<title>Buat Berita Baru - MGP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<!-- Summernote CSS -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
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
    min-height: 150px;
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
    max-width: 300px;
    max-height: 200px;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
}

.file-preview .file-info {
    margin-top: 10px;
    font-size: 14px;
    color: var(--gray);
}

/* Tag Input */
.tag-input-container {
    position: relative;
}

.tag-input {
    padding-right: 40px;
}

.tag-help {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray);
    cursor: help;
}

.tag-help:hover::after {
    content: "Pisahkan tag dengan koma. Contoh: olahraga,futsal,prestasi";
    position: absolute;
    right: 0;
    top: -10px;
    background: var(--dark);
    color: white;
    padding: 10px;
    border-radius: 8px;
    font-size: 12px;
    width: 200px;
    z-index: 1000;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

/* Summernote Custom */
.note-editor {
    border-radius: 12px;
    overflow: hidden;
    border: 2px solid #e0e0e0 !important;
}

.note-editor:focus-within {
    border-color: var(--primary) !important;
}

.note-toolbar {
    background: #f8f9fa !important;
    border-bottom: 1px solid #e0e0e0 !important;
}

.note-editable {
    background: white !important;
    min-height: 300px !important;
    font-family: inherit !important;
}

/* Status Options */
.status-options {
    display: flex;
    gap: 15px;
    margin-top: 10px;
}

.status-option {
    flex: 1;
}

.status-option input[type="radio"] {
    display: none;
}

.status-option label {
    display: block;
    padding: 12px;
    text-align: center;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    cursor: pointer;
    transition: var(--transition);
    font-weight: 500;
}

.status-option input[type="radio"]:checked + label {
    border-color: var(--primary);
    background: rgba(10, 36, 99, 0.1);
    color: var(--primary);
    font-weight: 600;
}

.status-option label:hover {
    background: #f8f9fa;
}

/* Character Count */
.char-count {
    text-align: right;
    font-size: 12px;
    color: var(--gray);
    margin-top: 5px;
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

/* Preview Box */
.preview-box {
    background: #f8f9fa;
    border: 2px dashed #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    margin-top: 20px;
}

.preview-title {
    font-size: 18px;
    color: var(--primary);
    margin-bottom: 10px;
    font-weight: 600;
}

.preview-content {
    color: #666;
    font-size: 14px;
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
    }
}

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
    
    .status-options {
        flex-direction: column;
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
                $isSubmenuOpen = false;
                
                if ($item['submenu']) {
                    // Cek jika salah satu sub-item ada yang aktif
                    foreach($item['items'] as $subUrl) {
                        if($current_page === $subUrl) {
                            $isActive = true;
                            $isSubmenuOpen = true;
                            break;
                        }
                    }
                } else {
                    // Untuk menu Berita, cek juga berita_create.php
                    if ($current_page === $item['url'] || 
                        ($item['url'] === 'berita.php' && $current_page === 'berita_create.php')) {
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

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="greeting">
                <h1>Buat Berita Baru 📰</h1>
                <p>Buat berita atau artikel baru untuk dipublikasikan</p>
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
                <span>Buat Berita Baru</span>
            </div>
            <a href="berita.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Daftar Berita
            </a>
        </div>

        <!-- ERROR MESSAGES -->
        <?php if (isset($errors['database'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $errors['database']; ?>
        </div>
        <?php endif; ?>

        <!-- CREATE BERITA FORM -->
        <div class="form-container">
            <form method="POST" action="" enctype="multipart/form-data" id="beritaForm">
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-heading"></i>
                        Informasi Dasar Berita
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="judul">
                                Judul Berita <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="judul" 
                                   name="judul" 
                                   class="form-input <?php echo isset($errors['judul']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($form_data['judul']); ?>"
                                   placeholder="Masukkan judul berita yang menarik"
                                   maxlength="200"
                                   required>
                            <div class="char-count">
                                <span id="judulCount">0</span>/200 karakter
                            </div>
                            <?php if (isset($errors['judul'])): ?>
                                <span class="error"><?php echo $errors['judul']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="slug">
                                URL Slug
                            </label>
                            <input type="text" 
                                   id="slug" 
                                   name="slug" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($form_data['slug']); ?>"
                                   placeholder="judul-berita-seo-friendly">
                            <small style="color: #666; display: block; margin-top: 5px;">
                                Akan digenerate otomatis dari judul jika kosong
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="penulis">
                                Penulis <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="penulis" 
                                   name="penulis" 
                                   class="form-input <?php echo isset($errors['penulis']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($form_data['penulis']); ?>"
                                   placeholder="Nama penulis berita"
                                   required>
                            <?php if (isset($errors['penulis'])): ?>
                                <span class="error"><?php echo $errors['penulis']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="tag">
                                Tags
                            </label>
                            <div class="tag-input-container">
                                <input type="text" 
                                       id="tag" 
                                       name="tag" 
                                       class="form-input tag-input" 
                                       value="<?php echo htmlspecialchars($form_data['tag']); ?>"
                                       placeholder="olahraga, futsal, prestasi">
                                <span class="tag-help">
                                    <i class="fas fa-question-circle"></i>
                                </span>
                            </div>
                            <small style="color: #666; display: block; margin-top: 5px;">
                                Pisahkan dengan koma untuk menambah multiple tags
                            </small>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-image"></i>
                        Gambar Berita
                    </div>
                    
                    <div class="form-group">
                        <div class="file-upload-container" id="gambarUpload">
                            <input type="file" 
                                   id="gambar" 
                                   name="gambar" 
                                   class="file-upload-input"
                                   accept="image/jpeg,image/png,image/gif,image/webp">
                            <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                            <div class="file-upload-text">Klik atau drag & drop gambar berita di sini</div>
                            <div class="file-upload-subtext">Format: JPEG, PNG, GIF, WebP | Maks: 5MB</div>
                            <div class="file-preview" id="gambarPreview" style="display: none;">
                                <img id="gambarPreviewImg" src="" alt="Preview" style="max-width: 300px; max-height: 200px;">
                                <div class="file-info" id="gambarFileInfo"></div>
                            </div>
                        </div>
                        <?php if (isset($errors['gambar'])): ?>
                            <span class="error"><?php echo $errors['gambar']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-file-alt"></i>
                        Konten Berita
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="konten">
                            Konten <span class="required">*</span>
                        </label>
                        <textarea id="konten" 
                                  name="konten" 
                                  class="form-textarea <?php echo isset($errors['konten']) ? 'is-invalid' : ''; ?>"
                                  placeholder="Tulis konten berita di sini..."
                                  rows="10"
                                  required><?php echo htmlspecialchars($form_data['konten']); ?></textarea>
                        <div class="char-count">
                            <span id="kontenCount">0</span> karakter
                        </div>
                        <?php if (isset($errors['konten'])): ?>
                            <span class="error"><?php echo $errors['konten']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-toggle-on"></i>
                        Status Publikasi
                    </div>
                    
                    <div class="form-group">
                        <div class="status-options">
                            <div class="status-option">
                                <input type="radio" id="status_draft" name="status" value="draft" <?php echo $form_data['status'] == 'draft' ? 'checked' : ''; ?>>
                                <label for="status_draft">
                                    <i class="fas fa-save"></i><br>
                                    Draft
                                </label>
                            </div>
                            
                            <div class="status-option">
                                <input type="radio" id="status_published" name="status" value="published" <?php echo $form_data['status'] == 'published' ? 'checked' : ''; ?>>
                                <label for="status_published">
                                    <i class="fas fa-globe"></i><br>
                                    Published
                                </label>
                            </div>
                            
                            <div class="status-option">
                                <input type="radio" id="status_archived" name="status" value="archived" <?php echo $form_data['status'] == 'archived' ? 'checked' : ''; ?>>
                                <label for="status_archived">
                                    <i class="fas fa-archive"></i><br>
                                    Archived
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Section -->
                <div class="preview-box">
                    <div class="preview-title">
                        <i class="fas fa-eye"></i> Preview
                    </div>
                    <div class="preview-content">
                        <strong>Judul:</strong> <span id="previewJudul"><?php echo htmlspecialchars($form_data['judul']); ?></span><br>
                        <strong>Slug:</strong> <span id="previewSlug"><?php echo htmlspecialchars($form_data['slug']); ?></span><br>
                        <strong>Status:</strong> <span id="previewStatus"><?php echo ucfirst($form_data['status']); ?></span>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="customResetBtn">
                        <i class="fas fa-redo"></i>
                        Reset Form
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Simpan Berita
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<!-- Summernote JS -->
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/lang/summernote-id-ID.min.js"></script>
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

    // Initialize Summernote
    $('#konten').summernote({
        height: 300,
        lang: 'id-ID',
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear']],
            ['fontname', ['fontname']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        callbacks: {
            onChange: function(contents) {
                updateCharCount('kontenCount', contents);
            },
            onPaste: function(e) {
                // Clean HTML on paste
                var bufferText = ((e.originalEvent || e).clipboardData || window.clipboardData).getData('Text');
                e.preventDefault();
                document.execCommand('insertText', false, bufferText);
            }
        }
    });

    // File Upload Logic - FIXED VERSION (klik sekali langsung bekerja)
    const gambarUpload = document.getElementById('gambarUpload');
    const gambarInput = document.getElementById('gambar');
    const gambarPreview = document.getElementById('gambarPreview');
    const gambarPreviewImg = document.getElementById('gambarPreviewImg');
    const gambarFileInfo = document.getElementById('gambarFileInfo');

    // Fungsi untuk membuka file dialog
    function openFileDialog() {
        gambarInput.click();
    }

    // Event listener yang lebih spesifik untuk mencegah multiple triggers
    const fileUploadIcon = document.querySelector('.file-upload-icon');
    const fileUploadText = document.querySelector('.file-upload-text');
    const fileUploadSubtext = document.querySelector('.file-upload-subtext');

    // Tambahkan event listener ke elemen individual dengan mencegah event bubbling
    [fileUploadIcon, fileUploadText, fileUploadSubtext].forEach(element => {
        element.style.cursor = 'pointer';
        element.addEventListener('click', function(e) {
            e.stopPropagation(); // Mencegah event bubbling ke parent
            e.preventDefault();  // Mencegah default behavior
            openFileDialog();
        });
    });

    // Untuk container, gunakan event delegation yang lebih spesifik
    gambarUpload.addEventListener('click', function(e) {
        // Hanya trigger jika yang diklik adalah container itu sendiri (area kosong)
        // atau jika target adalah container (bukan child elements yang sudah ada handler-nya)
        if (e.target === this || 
            (e.target.classList && e.target.classList.contains('file-upload-container')) ||
            (!e.target.closest('.file-preview') && 
             e.target !== fileUploadIcon && 
             e.target !== fileUploadText && 
             e.target !== fileUploadSubtext)) {
            openFileDialog();
        }
    });

    // Mencegah input file sendiri dari triggering event berulang
    gambarInput.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Drag and Drop
    gambarUpload.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.add('drag-over');
    });

    gambarUpload.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('drag-over');
    });

    gambarUpload.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('drag-over');
        
        if (e.dataTransfer.files.length) {
            gambarInput.files = e.dataTransfer.files;
            handleFileSelect(e.dataTransfer.files[0]);
        }
    });

    gambarInput.addEventListener('change', function() {
        if (this.files.length) {
            handleFileSelect(this.files[0]);
        }
    });

    // Custom reset function untuk handle semua elemen form
    function resetForm() {
        // Reset judul
        document.getElementById('judul').value = '';
        document.getElementById('judul').classList.remove('is-invalid');
        
        // Reset slug
        document.getElementById('slug').value = '';
        
        // Reset penulis
        document.getElementById('penulis').value = '';
        document.getElementById('penulis').classList.remove('is-invalid');
        
        // Reset tag
        document.getElementById('tag').value = '';
        
        // Reset Summernote content
        $('#konten').summernote('code', '');
        document.getElementById('konten').classList.remove('is-invalid');
        
        // Reset status radio buttons
        document.getElementById('status_draft').checked = true;
        
        // Reset file upload
        document.getElementById('gambar').value = '';
        document.getElementById('gambarPreview').style.display = 'none';
        document.getElementById('gambarPreviewImg').src = '';
        document.getElementById('gambarFileInfo').textContent = '';
        
        // Reset karakter count
        updateCharCount('judulCount', '');
        updateCharCount('kontenCount', '');
        
        // Reset preview
        updatePreview('judul', '');
        updatePreview('slug', '');
        updatePreview('status', 'Draft');
        
        // Remove error messages
        document.querySelectorAll('.error').forEach(error => error.remove());
        
        // Reset drag-over class
        gambarUpload.classList.remove('drag-over');
        
        
    }

    // Event listener untuk tombol reset
    document.getElementById('customResetBtn').addEventListener('click', resetForm);

    function handleFileSelect(file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!allowedTypes.includes(file.type)) {
            toastr.error('Format file harus berupa gambar (JPEG, PNG, GIF, atau WebP)');
            return;
        }

        if (file.size > maxSize) {
            toastr.error('Ukuran file maksimal 5MB');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            gambarPreviewImg.src = e.target.result;
            gambarFileInfo.textContent = `${file.name} (${formatFileSize(file.size)})`;
            gambarPreview.style.display = 'block';
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

    // Character Count
    const judulInput = document.getElementById('judul');
    const judulCount = document.getElementById('judulCount');
    const kontenCount = document.getElementById('kontenCount');

    judulInput.addEventListener('input', function() {
        updateCharCount('judulCount', this.value);
        generateSlug();
        updatePreview('judul', this.value);
    });

    document.getElementById('slug').addEventListener('input', function() {
        updatePreview('slug', this.value);
    });

    document.querySelectorAll('input[name="status"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const statusLabels = {
                'draft': 'Draft',
                'published': 'Published',
                'archived': 'Archived'
            };
            updatePreview('status', statusLabels[this.value]);
        });
    });

    function updateCharCount(elementId, text) {
        const count = text.length;
        document.getElementById(elementId).textContent = count;
    }

    function generateSlug() {
        const judul = judulInput.value.trim();
        if (judul && !document.getElementById('slug').value) {
            const slug = judul
                .toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
            document.getElementById('slug').value = slug;
            updatePreview('slug', slug);
        }
    }

    function updatePreview(element, value) {
        document.getElementById(`preview${element.charAt(0).toUpperCase() + element.slice(1)}`).textContent = value;
    }

    // Initial character count
    updateCharCount('judulCount', judulInput.value);
    updateCharCount('kontenCount', $('#konten').summernote('code'));

    // Form Validation
    const form = document.getElementById('beritaForm');
    form.addEventListener('submit', function(e) {
        const judul = document.getElementById('judul').value.trim();
        const konten = $('#konten').summernote('code').trim();
        const penulis = document.getElementById('penulis').value.trim();

        // Clear previous error highlights
        document.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });

        let hasError = false;

        if (!judul) {
            markError('judul', 'Judul berita harus diisi');
            hasError = true;
        } else if (judul.length < 5) {
            markError('judul', 'Judul minimal 5 karakter');
            hasError = true;
        } else if (judul.length > 200) {
            markError('judul', 'Judul maksimal 200 karakter');
            hasError = true;
        }

        if (!konten) {
            markError('konten', 'Konten berita harus diisi');
            hasError = true;
        }

        if (!penulis) {
            markError('penulis', 'Penulis harus diisi');
            hasError = true;
        }

        if (hasError) {
            e.preventDefault();
            toastr.error('Harap perbaiki kesalahan di form');
        }
    });

    function markError(fieldId, message) {
        const field = document.getElementById(fieldId);
        const errorSpan = field.nextElementSibling?.classList.contains('error') 
            ? field.nextElementSibling 
            : document.createElement('span');
        
        field.classList.add('is-invalid');
        errorSpan.className = 'error';
        errorSpan.textContent = message;
        
        if (!field.nextElementSibling?.classList.contains('error')) {
            field.parentNode.appendChild(errorSpan);
        }
    }

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

});
</script>
</body>
</html>