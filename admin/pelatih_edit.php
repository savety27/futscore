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

// Get pelatih ID
$pelatih_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($pelatih_id <= 0) {
    header("Location: pelatih.php");
    exit;
}

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

// Ambil data tim dari database untuk dropdown
$teams = [];
try {
    $stmt = $conn->prepare("SELECT id, name FROM teams WHERE is_active = 1 ORDER BY name ASC");
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $teams_error = "Gagal mengambil data tim: " . $e->getMessage();
}

// Initialize variables
$errors = [];
$pelatih_data = null;

// Fetch pelatih data
try {
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->execute([$pelatih_id]);
    $pelatih_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pelatih_data) {
        header("Location: pelatih.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error fetching pelatih data: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $form_data = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'full_name' => trim($_POST['full_name'] ?? ''),
        'role' => $_POST['role'] ?? 'admin',
        'team_id' => !empty($_POST['team_id']) ? (int)$_POST['team_id'] : null,
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    // Validation
    if (empty($form_data['username'])) {
        $errors['username'] = "Username harus diisi";
    } elseif (strlen($form_data['username']) < 3) {
        $errors['username'] = "Username minimal 3 karakter";
    } elseif (strlen($form_data['username']) > 50) {
        $errors['username'] = "Username maksimal 50 karakter";
    }
    
    if (empty($form_data['email'])) {
        $errors['email'] = "Email harus diisi";
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Format email tidak valid";
    }
    
    if (!empty($form_data['password'])) {
        if (strlen($form_data['password']) < 6) {
            $errors['password'] = "Password minimal 6 karakter";
        }
        
        if ($form_data['password'] !== $form_data['confirm_password']) {
            $errors['confirm_password'] = "Konfirmasi password tidak cocok";
        }
    }
    
    if (empty($form_data['full_name'])) {
        $errors['full_name'] = "Nama lengkap harus diisi";
    }
    
    // Jika role bukan superadmin, validasi team_id
    if ($form_data['role'] !== 'superadmin' && empty($form_data['team_id'])) {
        $errors['team_id'] = "Tim harus dipilih untuk role admin";
    }
    
    // Check for existing username and email (excluding current user)
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM admin_users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$form_data['username'], $form_data['email'], $pelatih_id]);
            if ($stmt->rowCount() > 0) {
                $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($existing as $user) {
                    $stmt_check = $conn->prepare("SELECT username, email FROM admin_users WHERE id = ?");
                    $stmt_check->execute([$user['id']]);
                    $user_data = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user_data['username'] === $form_data['username']) {
                        $errors['username'] = "Username sudah terdaftar";
                    }
                    if ($user_data['email'] === $form_data['email']) {
                        $errors['email'] = "Email sudah terdaftar";
                    }
                }
            }
        } catch (PDOException $e) {
            $errors['database'] = "Gagal memeriksa data: " . $e->getMessage();
        }
    }
    
    // If no errors, update database
    if (empty($errors)) {
        try {
            if (!empty($form_data['password'])) {
                // Update with new password
                $password_hash = password_hash($form_data['password'], PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("
                    UPDATE admin_users SET 
                        username = ?, 
                        email = ?, 
                        password_hash = ?, 
                        full_name = ?, 
                        role = ?, 
                        team_id = ?,
                        is_active = ?, 
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $form_data['username'],
                    $form_data['email'],
                    $password_hash,
                    $form_data['full_name'],
                    $form_data['role'],
                    $form_data['team_id'],
                    $form_data['is_active'],
                    $pelatih_id
                ]);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("
                    UPDATE admin_users SET 
                        username = ?, 
                        email = ?, 
                        full_name = ?, 
                        role = ?, 
                        team_id = ?,
                        is_active = ?, 
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $form_data['username'],
                    $form_data['email'],
                    $form_data['full_name'],
                    $form_data['role'],
                    $form_data['team_id'],
                    $form_data['is_active'],
                    $pelatih_id
                ]);
            }
            
            $_SESSION['success_message'] = "Data pelatih berhasil diperbarui!";
            header("Location: pelatih.php");
            exit;
            
        } catch (PDOException $e) {
            $errors['database'] = "Gagal memperbarui data: " . $e->getMessage();
        }
    } else {
        // Update pelatih_data with new form data for display
        $pelatih_data = array_merge($pelatih_data, $form_data);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Pelatih - MGP</title>
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
    padding-top: 20px;
    padding-right: 10px;
    padding-bottom: 10px;
    text-align: center;
    background: transparent;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    margin-bottom: 10px;
}

.logo {
    max-width: 200px;
    background: transparent;
    margin: 0 auto 12px;
    border: none;
    border-radius: 0;
    box-shadow: none;
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
    
    /* Show Mobile Menu Toggle Button - Golden & Bottom-Right */
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
        transform: scale(1.1) rotate(90deg);
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

    .action-buttons {
        width: 100%;
        flex-wrap: wrap;
    }
    
    .page-header .btn {
        width: 100%;
        justify-content: center;
    }

    /* Form Styles */
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
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
}

/* Password Toggle */
.password-toggle {
    position: relative;
}

.password-toggle input {
    padding-right: 40px;
}

.toggle-password {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--gray);
    cursor: pointer;
    font-size: 16px;
}

.toggle-password:hover {
    color: var(--primary);
}

/* Password Notice */
.password-notice {
    background: #f0f7ff;
    border: 1px solid #cce5ff;
    border-radius: 8px;
    padding: 15px;
    margin-top: 10px;
    font-size: 14px;
    color: #004085;
}

.password-notice i {
    color: var(--primary);
    margin-right: 8px;
}

/* Team Select */
.team-select-group {
    position: relative;
}

.team-select-wrapper {
    position: relative;
}

.team-select-wrapper::after {
    content: "";
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    pointer-events: none;
}
</style>
</head>
<body>

<!-- Mobile Menu Components -->
<div class="menu-overlay" id="menuOverlay"></div>
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <!-- SIDEBAR dengan struktur menu yang sama -->
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
                    foreach($item['items'] as $subUrl) {
                        if($current_page === $subUrl) {
                            $isActive = true;
                            $isSubmenuOpen = true;
                            break;
                        }
                    }
                } else {
                    if ($current_page === $item['url'] || $key === 'Pelatih') {
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
                <h1>Edit Pelatih 👤</h1>
                <p>Perbarui data pelatih: <?php echo htmlspecialchars($pelatih_data['full_name'] ?? ''); ?></p>
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
                <i class="fas fa-user-edit"></i>
                <span>Edit Pelatih</span>
            </div>
            <a href="pelatih.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Daftar Pelatih
            </a>
        </div>

        <!-- ERROR MESSAGES -->
        <?php if (isset($errors['database'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $errors['database']; ?>
        </div>
        <?php endif; ?>

        <!-- EDIT PELATIH FORM -->
        <div class="form-container">
            <form method="POST" action="" id="pelatihForm">
                <input type="hidden" name="id" value="<?php echo $pelatih_id; ?>">
                
                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-user-circle"></i>
                        Informasi Akun
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="username">
                                Username <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="form-input <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($pelatih_data['username'] ?? ''); ?>"
                                   placeholder="Masukkan username (min. 3 karakter)"
                                   required>
                            <?php if (isset($errors['username'])): ?>
                                <span class="error"><?php echo $errors['username']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="email">
                                Email <span class="required">*</span>
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   class="form-input <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($pelatih_data['email'] ?? ''); ?>"
                                   placeholder="contoh@email.com"
                                   required>
                            <?php if (isset($errors['email'])): ?>
                                <span class="error"><?php echo $errors['email']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-key"></i>
                        Ganti Password (Opsional)
                    </div>
                    
                    <div class="password-notice">
                        <i class="fas fa-info-circle"></i>
                        Kosongkan jika tidak ingin mengganti password
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="password">
                                Password Baru
                            </label>
                            <div class="password-toggle">
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       class="form-input <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                                       placeholder="Minimal 6 karakter">
                                <button type="button" class="toggle-password" data-target="password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['password'])): ?>
                                <span class="error"><?php echo $errors['password']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">
                                Konfirmasi Password
                            </label>
                            <div class="password-toggle">
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       class="form-input <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                                       placeholder="Ketik ulang password">
                                <button type="button" class="toggle-password" data-target="confirm_password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if (isset($errors['confirm_password'])): ?>
                                <span class="error"><?php echo $errors['confirm_password']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-id-card"></i>
                        Informasi Pribadi
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="full_name">
                                Nama Lengkap <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="full_name" 
                                   name="full_name" 
                                   class="form-input <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($pelatih_data['full_name'] ?? ''); ?>"
                                   placeholder="Masukkan nama lengkap"
                                   required>
                            <?php if (isset($errors['full_name'])): ?>
                                <span class="error"><?php echo $errors['full_name']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="role">
                                Role <span class="required">*</span>
                            </label>
                             <select id="role" name="role" class="form-select" required>
                                 <option value="pelatih" <?php echo ($pelatih_data['role'] == 'pelatih' || $pelatih_data['role'] == 'editor') ? 'selected' : ''; ?>>Pelatih</option>
                                 <option value="superadmin" <?php echo $pelatih_data['role'] == 'superadmin' ? 'selected' : ''; ?>>Super Admin</option>
                             </select>
                             <small style="color: #666; display: block; margin-top: 5px;">
                                 Super Admin: akses penuh, Pelatih: akses terbatas pada tim
                             </small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="team_id">
                                Tim <span class="required">*</span>
                            </label>
                            <div class="team-select-wrapper">
                                <select id="team_id" name="team_id" class="form-select <?php echo isset($errors['team_id']) ? 'is-invalid' : ''; ?>">
                                    <option value="">-- Pilih Tim --</option>
                                    <?php if (!empty($teams_error)): ?>
                                        <option value="" disabled>Error: <?php echo $teams_error; ?></option>
                                    <?php elseif (empty($teams)): ?>
                                        <option value="" disabled>Belum ada tim tersedia</option>
                                    <?php else: ?>
                                        <?php foreach ($teams as $team): ?>
                                            <option value="<?php echo $team['id']; ?>" 
                                                <?php echo $pelatih_data['team_id'] == $team['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($team['name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <?php if (isset($errors['team_id'])): ?>
                                <span class="error"><?php echo $errors['team_id']; ?></span>
                            <?php endif; ?>
                            <small style="color: #666; display: block; margin-top: 5px;">
                                Pilih tim untuk pelatih. Super Admin tidak perlu memilih tim.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-toggle-on"></i>
                        Status Akun
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" value="1" 
                                   <?php echo $pelatih_data['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active" style="font-weight: normal;">Aktifkan akun</label>
                        </div>
                        <small style="color: #666;">Akun aktif dapat login ke sistem</small>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="pelatih.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Batal
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Data Pelatih
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
    // Toggle sidebar untuk mobile
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const menuOverlay = document.getElementById('menuOverlay');
    
    menuToggle.addEventListener('click', function(e) {
        e.stopPropagation(); // Prevent click from bubbling to document
        sidebar.classList.toggle('active');
        document.body.classList.toggle('menu-open');
        this.innerHTML = sidebar.classList.contains('active') 
            ? '<i class="fas fa-times"></i>' 
            : '<i class="fas fa-bars"></i>';
    });
    
    // Close menu when clicking overlay
    menuOverlay.addEventListener('click', function() {
        sidebar.classList.remove('active');
        document.body.classList.remove('menu-open');
        menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
    });
    
    // Auto close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target) && !menuOverlay.contains(e.target)) {
                if (sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    document.body.classList.remove('menu-open');
                    menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                }
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

    // Password toggle functionality
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Tampilkan/sembunyikan field tim berdasarkan role
    const roleSelect = document.getElementById('role');
    const teamSelect = document.getElementById('team_id');
    
    function toggleTeamField() {
        const teamGroup = teamSelect.closest('.form-group');
        if (roleSelect.value === 'superadmin') {
            teamGroup.style.opacity = '0.6';
            teamGroup.style.pointerEvents = 'none';
            teamSelect.required = false;
            // teamSelect.value = '';
        } else {
            teamGroup.style.opacity = '1';
            teamGroup.style.pointerEvents = 'auto';
            teamSelect.required = true;
        }
    }
    
    // Jalankan saat halaman dimuat
    toggleTeamField();
    
    // Jalankan saat role berubah
    roleSelect.addEventListener('change', toggleTeamField);

    // Form Validation
    const form = document.getElementById('pelatihForm');
    form.addEventListener('submit', function(e) {
        const username = document.getElementById('username').value.trim();
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const fullName = document.getElementById('full_name').value.trim();
        const role = document.getElementById('role').value;
        const teamId = document.getElementById('team_id').value;

        // Clear previous error highlights
        document.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });

        let hasError = false;

        if (!username) {
            markError('username', 'Username harus diisi');
            hasError = true;
        } else if (username.length < 3) {
            markError('username', 'Username minimal 3 karakter');
            hasError = true;
        } else if (username.length > 50) {
            markError('username', 'Username maksimal 50 karakter');
            hasError = true;
        }

        if (!email) {
            markError('email', 'Email harus diisi');
            hasError = true;
        } else if (!isValidEmail(email)) {
            markError('email', 'Format email tidak valid');
            hasError = true;
        }

        if (password) {
            if (password.length < 6) {
                markError('password', 'Password minimal 6 karakter');
                hasError = true;
            }
            
            if (password !== confirmPassword) {
                markError('confirm_password', 'Password tidak cocok');
                hasError = true;
            }
        }

        if (!fullName) {
            markError('full_name', 'Nama lengkap harus diisi');
            hasError = true;
        }

        if (role !== 'superadmin' && !teamId) {
            markError('team_id', 'Tim harus dipilih untuk role pelatih');
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

    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // Auto-focus on first field
    document.getElementById('username').focus();
});
</script>
</body>
</html>
