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


<div class="wrapper">
    <!-- SIDEBAR -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

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
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>
