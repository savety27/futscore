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

// Menu items sesuai dengan file pertama
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
            'transfer' => 'transfer.php',
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

// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';

$academy_name = "Hi, Welcome...";
$email = $admin_email;

// Mendapatkan nama file saat ini untuk penanda menu 'Active'
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize variables
$errors = [];
$form_data = [
    'team_id' => '',
    'name' => '',
    'position' => '',
    'email' => '',
    'phone' => '',
    'birth_place' => '',
    'birth_date' => '',
    'address' => '',
    'city' => '',
    'province' => '',
    'postal_code' => '',
    'country' => 'Indonesia',
    'is_active' => 1
];

// Fetch teams for dropdown
$teams = [];
try {
    $stmt = $conn->prepare("SELECT id, name, alias FROM teams WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors['database'] = "Error fetching teams: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data = [
        'team_id' => trim($_POST['team_id'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'position' => trim($_POST['position'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'birth_place' => trim($_POST['birth_place'] ?? ''),
        'birth_date' => trim($_POST['birth_date'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'province' => trim($_POST['province'] ?? ''),
        'postal_code' => trim($_POST['postal_code'] ?? ''),
        'country' => trim($_POST['country'] ?? 'Indonesia'),
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    // Validation
    if (empty($form_data['team_id'])) {
        $errors['team_id'] = "Team harus dipilih";
    }
    
    if (empty($form_data['name'])) {
        $errors['name'] = "Nama staff harus diisi";
    }
    
    if (empty($form_data['position'])) {
        $errors['position'] = "Jabatan harus dipilih";
    }
    
    if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Format email tidak valid";
    }
    
    // Handle photo upload
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Check file type
        if (!in_array($file['type'], $allowed_types)) {
            $errors['photo'] = "Format file harus JPG, PNG, atau GIF";
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors['photo'] = "Ukuran file maksimal 5MB";
        }
        
        if (!isset($errors['photo'])) {
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'staff_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_dir = '../uploads/staff/';
            
            // Create directory if not exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $photo_path = 'uploads/staff/' . $filename;
            } else {
                $errors['photo'] = "Gagal mengupload foto";
            }
        }
    }
    
    // Handle certificates upload
    $certificates = [];
    if (isset($_FILES['certificates']) && is_array($_FILES['certificates']['name'])) {
        $certificate_names = $_POST['certificate_name'] ?? [];
        $issuing_authorities = $_POST['certificate_authority'] ?? [];
        $issue_dates = $_POST['certificate_date'] ?? [];
        
        for ($i = 0; $i < count($_FILES['certificates']['name']); $i++) {
            if ($_FILES['certificates']['error'][$i] == UPLOAD_ERR_OK) {
                $cert_file = [
                    'name' => $_FILES['certificates']['name'][$i],
                    'type' => $_FILES['certificates']['type'][$i],
                    'tmp_name' => $_FILES['certificates']['tmp_name'][$i],
                    'size' => $_FILES['certificates']['size'][$i]
                ];
                
                $max_size = 10 * 1024 * 1024; // 10MB
                
                // Check file type
                $file_ext = strtolower(pathinfo($cert_file['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
                
                if (!in_array($file_ext, $allowed_ext)) {
                    $errors['certificates'] = "Format file sertifikat harus JPG, PNG, GIF, PDF, atau DOC";
                    break;
                }
                
                // Check file size
                if ($cert_file['size'] > $max_size) {
                    $errors['certificates'] = "Ukuran file sertifikat maksimal 10MB per file";
                    break;
                }
                
                // Generate unique filename
                $filename = 'cert_' . time() . '_' . uniqid() . '.' . $file_ext;
                $upload_dir = '../uploads/certificates/';
                
                // Create directory if not exists
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($cert_file['tmp_name'], $target_path)) {
                    $certificates[] = [
                        'name' => $certificate_names[$i] ?? $cert_file['name'],
                        'file' => $filename,
                        'authority' => $issuing_authorities[$i] ?? '',
                        'date' => $issue_dates[$i] ?? null
                    ];
                } else {
                    $errors['certificates'] = "Gagal mengupload file sertifikat";
                }
            }
        }
    }
    
    // If no errors, insert to database
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            // Insert staff data
            $stmt = $conn->prepare("
                INSERT INTO team_staff (
                    team_id, name, position, email, phone, photo, 
                    birth_place, birth_date, address, city, province, 
                    postal_code, country, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $form_data['team_id'],
                $form_data['name'],
                $form_data['position'],
                $form_data['email'],
                $form_data['phone'],
                $photo_path,
                $form_data['birth_place'],
                $form_data['birth_date'] ?: null,
                $form_data['address'],
                $form_data['city'],
                $form_data['province'],
                $form_data['postal_code'],
                $form_data['country'],
                $form_data['is_active']
            ]);
            
            $staff_id = $conn->lastInsertId();
            
            // Insert certificates if any
            if (!empty($certificates)) {
                $stmt = $conn->prepare("
                    INSERT INTO staff_certificates (staff_id, certificate_name, certificate_file, issuing_authority, issue_date, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                
                foreach ($certificates as $cert) {
                    $stmt->execute([
                        $staff_id,
                        $cert['name'],
                        $cert['file'],
                        $cert['authority'],
                        $cert['date'] ?: null
                    ]);
                }
            }
            
            $conn->commit();
            
            $_SESSION['success_message'] = "Staff berhasil ditambahkan!";
            header("Location: team_staff.php");
            exit;
            
        } catch (PDOException $e) {
            $conn->rollBack();
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
<title>Add Team Staff - FutScore</title>
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

.logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
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

.btn-sm {
    padding: 8px 15px;
    font-size: 14px;
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

/* File Upload State */
.file-upload-container.has-file {
    border-color: var(--success);
    background: rgba(46, 125, 50, 0.1);
}

.file-upload-container.has-file .file-upload-icon {
    color: var(--success);
}

.file-upload-container.has-file .file-upload-text {
    color: var(--success);
    font-weight: 600;
}

.file-selected-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--success);
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    animation: pulse 2s infinite;
    z-index: 10;
}

.file-upload-input {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    opacity: 0;
    cursor: pointer;
    z-index: 5;
}

.file-upload-icon {
    font-size: 48px;
    color: var(--primary);
    margin-bottom: 15px;
    display: block;
    transition: var(--transition);
}

.file-upload-text {
    font-size: 16px;
    color: var(--gray);
    margin-bottom: 10px;
    transition: var(--transition);
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

/* Certificate Upload Section */
.certificate-upload-section {
    border: 2px dashed #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    background: #f8f9fa;
    position: relative;
}

.certificate-upload-section.has-file {
    border-color: var(--success);
    background: rgba(46, 125, 50, 0.05);
}

.remove-certificate {
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--danger);
    color: white;
    border: none;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    z-index: 10;
}

.remove-certificate:hover {
    background: #b71c1c;
    transform: scale(1.1);
}

/* Certificate File Info */
.certificate-file-info {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 10px;
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.certificate-file-info i {
    color: var(--primary);
    font-size: 20px;
}

.certificate-file-name {
    flex: 1;
    font-size: 14px;
    color: var(--dark);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
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

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
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

    /* Page Header */
    .page-header {
        flex-direction: column;
        gap: 15px;
        align-items: center;
        text-align: center;
        padding: 20px;
    }
    
    .page-title {
        order: -1; /* Title first */
        font-size: 22px;
    }

    /* Form Layout */
    .form-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 15px;
    }

    .btn {
        width: 100%;
        justify-content: center;
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

    /* Compact sidebar */
    .sidebar {
        width: 260px;
    }

    .sidebar-header {
        padding: 20px 15px;
    }

    .logo {
        width: 80px;
        height: 80px;
    }

    .logo::before {
        font-size: 36px;
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
}
</style>
</head>
<body>


<!-- Mobile Menu Components (hidden by default via CSS) -->
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
                    foreach($item['items'] as $subKey => $subUrl) {
                        // Untuk halaman team_staff_create.php, kita juga ingin submenu team_staff aktif
                        if($current_page === $subUrl || 
                           ($subKey === 'team_staff' && $current_page === 'team_staff_create.php')) {
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
                           class="submenu-link <?php echo ($current_page === $subUrl || ($subKey === 'team_staff' && $current_page === 'team_staff_create.php')) ? 'active' : ''; ?>">
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
                <h1>Add New Team Staff 👔</h1>
                <p>Tambahkan data staff baru ke sistem</p>
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
                <span>Tambah Staff Baru</span>
            </div>
            <a href="team_staff.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Daftar Staff
            </a>
        </div>

        <!-- ERROR MESSAGES -->
        <?php if (isset($errors['database'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $errors['database']; ?>
        </div>
        <?php endif; ?>

        <!-- ADD STAFF FORM -->
        <div class="form-container">
            <form method="POST" action="" enctype="multipart/form-data" id="staffForm">
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Informasi Dasar Staff
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="team_id">
                                Team <span class="required">*</span>
                            </label>
                            <select id="team_id" name="team_id" class="form-select <?php echo isset($errors['team_id']) ? 'is-invalid' : ''; ?>" required>
                                <option value="">Pilih Team</option>
                                <?php foreach ($teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>" <?php echo $form_data['team_id'] == $team['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($team['name'] ?? ''); ?> (<?php echo htmlspecialchars($team['alias'] ?? ''); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['team_id'])): ?>
                                <span class="error"><?php echo $errors['team_id']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="name">
                                Nama Lengkap <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   class="form-input <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>"
                                   required>
                            <?php if (isset($errors['name'])): ?>
                                <span class="error"><?php echo $errors['name']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="position">
                                Jabatan <span class="required">*</span>
                            </label>
                            <select id="position" name="position" class="form-select <?php echo isset($errors['position']) ? 'is-invalid' : ''; ?>" required>
                                <option value="">Pilih Jabatan</option>
                                <option value="manager" <?php echo $form_data['position'] == 'manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="headcoach" <?php echo $form_data['position'] == 'headcoach' ? 'selected' : ''; ?>>Head Coach</option>
                                <option value="coach" <?php echo $form_data['position'] == 'coach' ? 'selected' : ''; ?>>Coach</option>
                                <option value="goalkeeper_coach" <?php echo $form_data['position'] == 'goalkeeper_coach' ? 'selected' : ''; ?>>Goalkeeper Coach</option>
                                <option value="medic" <?php echo $form_data['position'] == 'medic' ? 'selected' : ''; ?>>Medic</option>
                                <option value="official" <?php echo $form_data['position'] == 'official' ? 'selected' : ''; ?>>Official</option>
                            </select>
                            <?php if (isset($errors['position'])): ?>
                                <span class="error"><?php echo $errors['position']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="photo">
                                Foto Profil
                            </label>
                            <div class="file-upload-container" id="photoUpload">
                                <input type="file" 
                                       id="photo" 
                                       name="photo" 
                                       class="file-upload-input"
                                       accept="image/jpeg,image/png,image/gif">
                                <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                <div class="file-upload-text">Klik atau drag & drop foto di sini</div>
                                <div class="file-upload-subtext">Format: JPEG, PNG, GIF | Maks: 5MB</div>
                                <div class="file-preview" id="photoPreview" style="display: none;">
                                    <img id="photoPreviewImg" src="" alt="Preview" style="max-width: 200px; max-height: 200px;">
                                    <div class="file-info" id="photoFileInfo"></div>
                                </div>
                            </div>
                            <?php if (isset($errors['photo'])): ?>
                                <span class="error"><?php echo $errors['photo']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-address-card"></i>
                        Kontak & Identitas
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="email">
                                Email
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-input <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                                   placeholder="email@example.com">
                            <?php if (isset($errors['email'])): ?>
                                <span class="error"><?php echo $errors['email']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="phone">
                                No. Telepon
                            </label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>"
                                   placeholder="+62 812-3456-7890">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="birth_place">
                                Tempat Lahir
                            </label>
                            <input type="text" 
                                   id="birth_place" 
                                   name="birth_place" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($form_data['birth_place'] ?? ''); ?>"
                                   placeholder="Kota tempat lahir">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="birth_date">
                                Tanggal Lahir
                            </label>
                            <input type="date" 
                                   id="birth_date" 
                                   name="birth_date" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($form_data['birth_date'] ?? ''); ?>"
                                   max="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Alamat Lengkap
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="address">
                                Alamat
                            </label>
                            <textarea id="address" 
                                      name="address" 
                                      class="form-textarea" 
                                      rows="3"
                                      placeholder="Alamat lengkap"><?php echo htmlspecialchars($form_data['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="city">
                                Kota
                            </label>
                            <input type="text" 
                                   id="city" 
                                   name="city" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($form_data['city'] ?? ''); ?>"
                                   placeholder="Nama kota">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="province">
                                Provinsi
                            </label>
                            <input type="text" 
                                   id="province" 
                                   name="province" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($form_data['province'] ?? ''); ?>"
                                   placeholder="Nama provinsi">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="postal_code">
                                Kode Pos
                            </label>
                            <input type="text" 
                                   id="postal_code" 
                                   name="postal_code" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($form_data['postal_code'] ?? ''); ?>"
                                   placeholder="12345">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="country">
                                Negara
                            </label>
                            <input type="text" 
                                   id="country" 
                                   name="country" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($form_data['country'] ?? ''); ?>"
                                   placeholder="Indonesia">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-certificate"></i>
                        Sertifikat & Lisensi
                    </div>
                    
                    <div id="certificatesContainer">
                        <!-- Certificate template akan ditambahkan oleh JavaScript -->
                    </div>
                    
                    <?php if (isset($errors['certificates'])): ?>
                        <span class="error"><?php echo $errors['certificates']; ?></span>
                    <?php endif; ?>
                    
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-toggle-on"></i>
                        Status Staff
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo $form_data['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active" style="font-weight: normal;">Staff Aktif</label>
                        </div>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            Staff aktif akan tampil dalam sistem
                        </small>
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
                        Simpan Staff
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

    // File Upload Logic untuk foto
    const photoUpload = document.getElementById('photoUpload');
    const photoInput = document.getElementById('photo');
    const photoPreview = document.getElementById('photoPreview');
    const photoPreviewImg = document.getElementById('photoPreviewImg');
    const photoFileInfo = document.getElementById('photoFileInfo');
    let photoFileIndicator = null;

    // Drag and Drop
    photoUpload.addEventListener('dragover', function(e) {
        e.preventDefault();
        photoUpload.classList.add('drag-over');
    });

    photoUpload.addEventListener('dragleave', function() {
        photoUpload.classList.remove('drag-over');
    });

    photoUpload.addEventListener('drop', function(e) {
        e.preventDefault();
        photoUpload.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length) {
            photoInput.files = files;
            handlePhotoFileSelect(files[0]);
        }
    });

    // Click to Upload
    photoUpload.addEventListener('click', function(e) {
        if (!e.target.classList.contains('file-upload-input')) {
            photoInput.click();
        }
    });

    photoInput.addEventListener('change', function() {
        if (this.files.length) {
            handlePhotoFileSelect(this.files[0]);
        }
    });

    function handlePhotoFileSelect(file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!allowedTypes.includes(file.type)) {
            toastr.error('Format file harus berupa gambar (JPEG, PNG, atau GIF)');
            return;
        }

        if (file.size > maxSize) {
            toastr.error('Ukuran file maksimal 5MB');
            return;
        }

        // Show file selected indicator
        showFileSelectedIndicator(photoUpload, file.name);
        
        const reader = new FileReader();
        reader.onload = function(e) {
            photoPreviewImg.src = e.target.result;
            photoFileInfo.textContent = `${file.name} (${formatFileSize(file.size)})`;
            photoPreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    function showFileSelectedIndicator(container, fileName) {
        // Remove existing indicator
        if (photoFileIndicator) {
            photoFileIndicator.remove();
        }
        
        // Add new indicator
        container.classList.add('has-file');
        photoFileIndicator = document.createElement('div');
        photoFileIndicator.className = 'file-selected-indicator';
        photoFileIndicator.innerHTML = '<i class="fas fa-check"></i>';
        photoFileIndicator.title = `File dipilih: ${fileName}`;
        container.appendChild(photoFileIndicator);
        
        // Update text
        const uploadText = container.querySelector('.file-upload-text');
        if (uploadText) {
            uploadText.textContent = `File dipilih: ${fileName}`;
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Certificate Management
    let certificateCounter = 0;
    
    function createCertificateTemplate() {
        const template = document.createElement('div');
        template.className = 'certificate-upload-section';
        template.id = 'certificate_' + certificateCounter;
        
        template.innerHTML = `
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nama Sertifikat/Lisensi</label>
                    <input type="text" name="certificate_name[]" class="form-input" placeholder="Contoh: Lisensi Kepelatihan C AFC">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Penerbit/Lembaga</label>
                    <input type="text" name="certificate_authority[]" class="form-input" placeholder="Contoh: AFC (Asian Football Confederation)">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tanggal Terbit</label>
                    <input type="date" name="certificate_date[]" class="form-input certificate-date">
                </div>
                
                <div class="form-group">
                    <label class="form-label">File Sertifikat <span style="color: var(--danger);">*</span></label>
                    <div class="file-upload-container certificate-upload" style="padding: 15px; position: relative;">
                        <input type="file" name="certificates[]" class="file-upload-input certificate-file" 
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx" data-index="${certificateCounter}">
                        <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                        <div class="file-upload-text">Klik untuk upload file sertifikat</div>
                        <div class="file-upload-subtext">Format: JPG, PNG, GIF, PDF, DOC | Maks: 10MB</div>
                        <div class="certificate-file-info" style="display: none;"></div>
                    </div>
                </div>
            </div>
            <button type="button" class="remove-certificate" onclick="removeCertificate(this)">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Add event listener for file selection
        const fileInput = template.querySelector('.certificate-file');
        const fileContainer = template.querySelector('.certificate-upload');
        const fileInfoContainer = template.querySelector('.certificate-file-info');
        
        fileInput.addEventListener('change', function() {
            if (this.files.length) {
                handleCertificateFileSelect(this.files[0], fileContainer, fileInfoContainer);
            }
        });
        
        // Allow click on container to trigger file input
        fileContainer.addEventListener('click', function(e) {
            if (!e.target.classList.contains('file-upload-input')) {
                fileInput.click();
            }
        });
        
        // Drag and drop for certificate files
        fileContainer.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileContainer.classList.add('drag-over');
        });

        fileContainer.addEventListener('dragleave', function() {
            fileContainer.classList.remove('drag-over');
        });

        fileContainer.addEventListener('drop', function(e) {
            e.preventDefault();
            fileContainer.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            if (files.length) {
                fileInput.files = files;
                handleCertificateFileSelect(files[0], fileContainer, fileInfoContainer);
            }
        });
        
        certificateCounter++;
        return template;
    }
    
    function handleCertificateFileSelect(file, container, infoContainer) {
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        // Check file type
        const fileExt = file.name.split('.').pop().toLowerCase();
        const allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
        
        if (!allowedExt.includes(fileExt)) {
            toastr.error('Format file harus JPG, PNG, GIF, PDF, DOC, atau DOCX');
            return;
        }
        
        if (file.size > maxSize) {
            toastr.error('Ukuran file maksimal 10MB');
            return;
        }
        
        // Update container appearance
        container.classList.add('has-file');
        container.closest('.certificate-upload-section').classList.add('has-file');
        
        // Show file info
        infoContainer.innerHTML = `
            <i class="fas ${getFileIcon(fileExt)}"></i>
            <div class="certificate-file-name">${file.name} (${formatFileSize(file.size)})</div>
            <button type="button" class="btn btn-sm" style="background: var(--success); color: white; padding: 2px 8px; font-size: 12px;" onclick="viewCertificateFile('${file.name}', this)">
                <i class="fas fa-eye"></i>
            </button>
        `;
        infoContainer.style.display = 'flex';
        
        // Update text
        const uploadText = container.querySelector('.file-upload-text');
        if (uploadText) {
            uploadText.textContent = 'File dipilih';
            uploadText.style.color = 'var(--success)';
            uploadText.style.fontWeight = '600';
        }
        
        // Add success indicator
        let indicator = container.querySelector('.file-selected-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'file-selected-indicator';
            indicator.innerHTML = '<i class="fas fa-check"></i>';
            indicator.title = `File dipilih: ${file.name}`;
            container.appendChild(indicator);
        }
    }
    
    function getFileIcon(ext) {
        const icons = {
            'jpg': 'fa-file-image',
            'jpeg': 'fa-file-image',
            'png': 'fa-file-image',
            'gif': 'fa-file-image',
            'pdf': 'fa-file-pdf',
            'doc': 'fa-file-word',
            'docx': 'fa-file-word'
        };
        return icons[ext] || 'fa-file';
    }
    
    function viewCertificateFile(fileName, button) {
        // In a real implementation, this would show a preview modal
        toastr.info(`Melihat file: ${fileName}`);
    }
    
    function addCertificate() {
        const container = document.getElementById('certificatesContainer');
        const newCertificate = createCertificateTemplate();
        container.appendChild(newCertificate);
        
        // Set default date to today
        const dateInput = newCertificate.querySelector('.certificate-date');
        if (dateInput) {
            dateInput.valueAsDate = new Date();
        }
    }
    
    function removeCertificate(button) {
        const certificateDiv = button.closest('.certificate-upload-section');
        if (certificateDiv && certificateDiv.id !== 'certificateUploadTemplate') {
            certificateDiv.remove();
        }
    }

    // Form Validation
    const form = document.getElementById('staffForm');
    form.addEventListener('submit', function(e) {
        const teamId = document.getElementById('team_id').value;
        const name = document.getElementById('name').value.trim();
        const position = document.getElementById('position').value.trim();
        const email = document.getElementById('email').value.trim();
        
        let valid = true;
        let errorMessage = '';

        if (!teamId) {
            errorMessage += 'Team harus dipilih\n';
            valid = false;
        }
        
        if (!name) {
            errorMessage += 'Nama staff harus diisi\n';
            valid = false;
        }
        
        if (!position) {
            errorMessage += 'Jabatan harus dipilih\n';
            valid = false;
        }
        
        if (email && !isValidEmail(email)) {
            errorMessage += 'Format email tidak valid\n';
            valid = false;
        }
        
        // Check certificate files
        const certificateFiles = document.querySelectorAll('.certificate-file');
        certificateFiles.forEach((fileInput, index) => {
            if (!fileInput.files.length) {
                const certificateSection = fileInput.closest('.certificate-upload-section');
                const nameInput = certificateSection.querySelector('input[name="certificate_name[]"]');
                if (nameInput && nameInput.value.trim()) {
                    errorMessage += `Sertifikat "${nameInput.value}" harus memiliki file\n`;
                    valid = false;
                }
            }
        });
        
        if (!valid) {
            e.preventDefault();
            toastr.error(errorMessage);
        }
    });

    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // Initialize with one certificate field
    addCertificate();
});
</script>
</body>
</html>