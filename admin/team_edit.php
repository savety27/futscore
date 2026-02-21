<?php
session_start();

// Load database config
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

$event_helper_path = __DIR__ . '/includes/event_helpers.php';
if (file_exists($event_helper_path)) {
    require_once $event_helper_path;
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// Menu items sesuai dengan file pertama

$event_types = function_exists('getDynamicEventOptions') ? getDynamicEventOptions($conn) : [];

// Get team ID
$team_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($team_id <= 0) {
    header("Location: team.php");
    exit;
}

// Initialize variables
$errors = [];
$team_data = null;

// Fetch team data
try {
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$team_data) {
        header("Location: team.php");
        exit;
    }

    $events_stmt = $conn->prepare("SELECT event_name FROM team_events WHERE team_id = ? ORDER BY event_name ASC");
    $events_stmt->execute([$team_id]);
    $team_events = $events_stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($team_events) && !empty($team_data['sport_type'])) {
        $team_events = [$team_data['sport_type']];
    }
} catch (PDOException $e) {
    die("Error fetching team data: " . $e->getMessage());
}

if (!empty($team_data['established_year']) && preg_match('/^\d{4}$/', $team_data['established_year'])) {
    $team_data['established_year'] .= '-01-01';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $selected_events = $_POST['sport_types'] ?? [];
    if (!is_array($selected_events)) {
        $selected_events = [];
    }
    $selected_events = array_values(array_filter($selected_events, function($event) use ($event_types) {
        return in_array($event, $event_types, true);
    }));

    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'alias' => trim($_POST['alias'] ?? ''),
        'coach' => trim($_POST['coach'] ?? ''),
        'established_year' => trim($_POST['established_year'] ?? ''),
        'uniform_color' => trim($_POST['uniform_color'] ?? ''),
        'basecamp' => trim($_POST['basecamp'] ?? ''),
        'sport_type' => $selected_events[0] ?? '',
        'sport_types' => $selected_events,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'delete_logo' => isset($_POST['delete_logo']) ? 1 : 0
    ];

    if (!empty($form_data['established_year']) && preg_match('/^\d{4}$/', $form_data['established_year'])) {
        $form_data['established_year'] .= '-01-01';
    }
    
    // Validation
    if (empty($form_data['name'])) {
        $errors['name'] = "Nama team harus diisi";
    }
    
    if (empty($form_data['coach'])) {
        $errors['coach'] = "Manager harus diisi";
    }
    
    if (empty($form_data['established_year'])) {
        $errors['established_year'] = "Tanggal berdiri harus diisi";
    } else {
        $established_date = DateTime::createFromFormat('Y-m-d', $form_data['established_year']);
        $date_errors = DateTime::getLastErrors();
        $has_date_errors = is_array($date_errors) && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0);

        if (!$established_date || $has_date_errors) {
            $errors['established_year'] = "Tanggal berdiri tidak valid";
        } else {
            $min_date = new DateTime('1900-01-01');
            $max_date = new DateTime('today');
            if ($established_date < $min_date || $established_date > $max_date) {
                $errors['established_year'] = "Tanggal berdiri harus antara 1900-01-01 dan " . $max_date->format('Y-m-d');
            } else {
                $form_data['established_year'] = $established_date->format('Y-m-d');
            }
        }
    }
    
    if (empty($form_data['sport_types'])) {
        $errors['sport_type'] = "Event harus diisi";
    }
    
    // Handle file upload
    $logo_path = $team_data['logo'];
    
    // If delete logo is checked
    if ($form_data['delete_logo'] && $logo_path) {
        if (file_exists('../images/teams/' . $logo_path)) {
            @unlink('../images/teams/' . $logo_path);
        }
        $logo_path = null;
    }
    
    // If new logo is uploaded
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Check file type
        if (!in_array($file['type'], $allowed_types)) {
            $errors['logo'] = "Format file harus JPG, PNG, atau GIF";
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors['logo'] = "Ukuran file maksimal 5MB";
        }
        
        if (!isset($errors['logo'])) {
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'team_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_dir = '../images/teams/';
            
            // Create directory if not exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Delete old logo if exists
                if ($logo_path && file_exists('../images/teams/' . $logo_path)) {
                    @unlink('../images/teams/' . $logo_path);
                }
                $logo_path = $filename;
            } else {
                $errors['logo'] = "Gagal mengupload logo";
            }
        }
    }
    
    // If no errors, update database
    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("
                UPDATE teams SET 
                    name = ?, 
                    alias = ?, 
                    coach = ?, 
                    established_year = ?, 
                    uniform_color = ?, 
                    basecamp = ?, 
                    sport_type = ?, 
                    is_active = ?, 
                    logo = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $form_data['name'],
                $form_data['alias'],
                $form_data['coach'],
                $form_data['established_year'],
                $form_data['uniform_color'],
                $form_data['basecamp'],
                $form_data['sport_type'],
                $form_data['is_active'],
                $logo_path,
                $team_id
            ]);

            $delete_stmt = $conn->prepare("DELETE FROM team_events WHERE team_id = ?");
            $delete_stmt->execute([$team_id]);

            if (!empty($form_data['sport_types'])) {
                $event_stmt = $conn->prepare("INSERT INTO team_events (team_id, event_name) VALUES (?, ?)");
                foreach ($form_data['sport_types'] as $event_name) {
                    $event_stmt->execute([$team_id, $event_name]);
                }
            }

            $conn->commit();
            
            $_SESSION['success_message'] = "Team berhasil diperbarui!";
            header("Location: team.php");
            exit;
            
        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $errors['database'] = "Gagal memperbarui data: " . $e->getMessage();
        }
    } else {
        // Update team_data with new form data for display
        $team_data = array_merge($team_data, $form_data);
    }
}

$selected_events_for_form = $team_events ?? [];
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selected_events_for_form = $form_data['sport_types'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Team </title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
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

.event-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.event-option {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.92rem;
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


/* ===== TABLET (max-width: 1024px) ===== */
@media screen and (max-width: 1024px) {

    .main {
        margin-left: 240px;
        width: calc(100% - 240px);
        max-width: calc(100vw - 240px);
    }
}

/* ===== MOBILE LANDSCAPE (max-width: 768px) ===== */
@media screen and (max-width: 768px) {
    


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
    }

    .page-title {
        width: 100%;
        justify-content: center;
    }
    
    .page-header .btn {
        width: 100%;
        justify-content: center;
    }

    /* Form Layout on Mobile */
    .form-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .form-container {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
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

    .page-title {
        font-size: 22px;
    }

    .page-title i {
        font-size: 26px;
    }


    .logo,
    .logo img {
        max-width: 120px;
    }

    

    .menu-link {
        padding: 14px 15px;
        font-size: 15px;
    }

    .menu-icon {
        font-size: 20px;
        width: 28px;
    }

    
    .section-title {
        font-size: 18px;
    }
    
    .file-upload-container {
        padding: 20px;
    }
    
    .file-upload-icon {
        font-size: 36px;
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


<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="greeting">
                <h1>Edit Team ⚽</h1>
                <p>Perbarui data team: <?php echo htmlspecialchars($team_data['name'] ?? ''); ?></p>
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
                <i class="fas fa-edit"></i>
                <span>Edit Team</span>
            </div>
            <a href="team.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Daftar Team
            </a>
        </div>

        <!-- ERROR MESSAGES -->
        <?php if (isset($errors['database'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $errors['database']; ?>
        </div>
        <?php endif; ?>

        <!-- EDIT TEAM FORM -->
        <div class="form-container">
            <form method="POST" action="" enctype="multipart/form-data" id="teamForm">
                <input type="hidden" name="id" value="<?php echo $team_id; ?>">
                
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
                                   class="form-input <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($team_data['name'] ?? ''); ?>"
                                   required>
                            <?php if (isset($errors['name'])): ?>
                                <span class="error"><?php echo $errors['name']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="alias">
                                Nama Alias/Pendek
                            </label>
                            <input type="text" 
                                   id="alias" 
                                   name="alias" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($team_data['alias'] ?? ''); ?>"
                                   placeholder="Contoh: BUFC, SSB ABC">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="coach">
                                Manager <span class="required">*</span>
                            </label>
                            <input type="text" 
                                   id="coach" 
                                   name="coach" 
                                   class="form-input <?php echo isset($errors['coach']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($team_data['coach'] ?? ''); ?>"
                                   placeholder="Masukkan nama manager"
                                   required>
                            <?php if (isset($errors['coach'])): ?>
                                <span class="error"><?php echo $errors['coach']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="established_year">
                                Tanggal Berdiri <span class="required">*</span>
                            </label>
                            <input type="date" 
                                   id="established_year" 
                                   name="established_year" 
                                   class="form-input <?php echo isset($errors['established_year']) ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($team_data['established_year'] ?? ''); ?>"
                                   placeholder="Contoh: 2020-01-31"
                                   min="1900-01-01" 
                                   max="<?php echo date('Y-m-d'); ?>"
                                   required>
                            <?php if (isset($errors['established_year'])): ?>
                                <span class="error"><?php echo $errors['established_year']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

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
                            <?php if (!empty($team_data['logo'])): ?>
                                <div style="margin-bottom: 15px;">
                                    <img src="../images/teams/<?php echo htmlspecialchars($team_data['logo']); ?>" 
                                         alt="Current Logo" 
                                         style="max-width: 200px; max-height: 200px; border-radius: 10px; border: 2px solid #ddd;">
                                    <div style="margin-top: 5px;">
                                        <label style="display: flex; align-items: center; gap: 10px;">
                                            <input type="checkbox" name="delete_logo" value="1">
                                            Hapus logo saat ini
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="file-upload-container" id="logoUpload">
                                <input type="file" 
                                       id="logo" 
                                       name="logo" 
                                       class="file-upload-input"
                                       accept="image/jpeg,image/png,image/gif">
                                <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                <div class="file-upload-text">Klik atau drag & drop logo team baru di sini</div>
                                <div class="file-upload-subtext">Format: JPEG, PNG, GIF | Maks: 5MB</div>
                                <div class="file-preview" id="logoPreview" style="display: none;">
                                    <img id="logoPreviewImg" src="" alt="Preview" style="max-width: 200px; max-height: 200px;">
                                    <div class="file-info" id="logoFileInfo"></div>
                                </div>
                            </div>
                            <?php if (isset($errors['logo'])): ?>
                                <span class="error"><?php echo $errors['logo']; ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="uniform_color">
                                Warna Kostum
                            </label>
                            <input type="text" 
                                   id="uniform_color" 
                                   name="uniform_color" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($team_data['uniform_color'] ?? ''); ?>"
                                   placeholder="Contoh: Biru-Kuning, Merah-Putih">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Lokasi & Event
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
                                   value="<?php echo htmlspecialchars($team_data['basecamp'] ?? ''); ?>"
                                   placeholder="Masukkan lokasi basecamp team">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="sport_type">
                                Event <span class="required">*</span>
                            </label>
                            <div class="event-list">
                                <?php foreach ($event_types as $event_option): ?>
                                    <label class="event-option">
                                        <input type="checkbox" name="sport_types[]" value="<?php echo htmlspecialchars($event_option); ?>"
                                            <?php echo in_array($event_option, $selected_events_for_form, true) ? 'checked' : ''; ?>>
                                        <span><?php echo htmlspecialchars($event_option); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (isset($errors['sport_type'])): ?>
                                <span class="error"><?php echo $errors['sport_type']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-title">
                        <i class="fas fa-toggle-on"></i>
                        Status Team
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_active" name="is_active" value="1" 
                                   <?php echo $team_data['is_active'] ? 'checked' : ''; ?>>
                            <label for="is_active" style="font-weight: normal;">Team Aktif</label>
                        </div>
                        <small style="color: #666;">Team aktif akan tampil dalam sistem</small>
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
                        Update Team
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>
