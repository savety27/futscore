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
// Get venue ID
$venue_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($venue_id <= 0) {
    header("Location: venue.php");
    exit;
}

// Fetch venue data
try {
    $stmt = $conn->prepare("SELECT * FROM venues WHERE id = ?");
    $stmt->execute([$venue_id]);
    $venue_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venue_data) {
        header("Location: venue.php");
        exit;
    }
    
} catch (PDOException $e) {
    die("Error fetching venue data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Venue</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
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

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
}

.stat-icon {
    font-size: 40px;
    margin-bottom: 15px;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 14px;
}

/* Info Card */
.info-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: var(--card-shadow);
}

.info-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.info-title {
    font-size: 22px;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    margin-bottom: 15px;
}

.info-label {
    font-weight: 600;
    color: #666;
    font-size: 14px;
    margin-bottom: 5px;
    display: block;
}

.info-value {
    font-size: 16px;
    color: #333;
}

.info-value.badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 14px;
}

/* Venue Icon Large */
.venue-icon-large {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--primary));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 60px;
    margin: 0 auto 30px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

/* Badge Styles */
.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.badge-primary {
    background: rgba(10, 36, 99, 0.1);
    color: var(--primary);
}

.badge-secondary {
    background: rgba(108, 117, 125, 0.1);
    color: var(--gray);
}

.badge-success {
    background: rgba(46, 125, 50, 0.1);
    color: var(--success);
}

.badge-danger {
    background: rgba(211, 47, 47, 0.1);
    color: var(--danger);
}

.badge-warning {
    background: rgba(249, 168, 38, 0.1);
    color: var(--warning);
}

/* Facilities Display */
.facilities-display {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    margin-top: 15px;
    white-space: pre-wrap;
    line-height: 1.6;
    font-size: 14px;
    color: #333;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px;
    color: #999;
}

.empty-state i {
    font-size: 60px;
    margin-bottom: 20px;
    opacity: 0.3;
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
    
    /* Extra grid optimizations */
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .venue-icon-large {
        width: 100px;
        height: 100px;
        font-size: 40px;
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
                <h1>Venue Details 🏟️</h1>
                <p>Detail informasi venue: <?php echo htmlspecialchars($venue_data['name'] ?? ''); ?></p>
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
                <i class="fas fa-map-marker-alt"></i>
                <span>Detail Venue</span>
            </div>
            <div class="action-buttons">
                <a href="venue_edit.php?id=<?php echo $venue_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Venue
                </a>
                <a href="venue.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
                </a>
            </div>
        </div>

        <!-- VENUE STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color: #0A2463;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo number_format($venue_data['capacity']); ?></div>
                <div class="stat-label">Kapasitas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #FFD700;">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="stat-number"><?php echo substr($venue_data['location'], 0, 15) . '...'; ?></div>
                <div class="stat-label">Lokasi</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #4CC9F0;">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-number"><?php echo date('d M Y', strtotime($venue_data['created_at'])); ?></div>
                <div class="stat-label">Dibuat Pada</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #2E7D32;">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-number"><?php echo date('d M Y', strtotime($venue_data['updated_at'])); ?></div>
                <div class="stat-label">Terakhir Update</div>
            </div>
        </div>

        <!-- VENUE INFORMATION -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Venue
                </div>
                <div>
                    <?php if ($venue_data['is_active']): ?>
                        <span class="badge badge-success" style="padding: 8px 16px;">AKTIF</span>
                    <?php else: ?>
                        <span class="badge badge-danger" style="padding: 8px 16px;">NON-AKTIF</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="text-align: center; margin-bottom: 30px;">
                <div class="venue-icon-large">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                
                <h2 style="font-size: 28px; color: #333; margin-bottom: 10px;">
                    <?php echo htmlspecialchars($venue_data['name'] ?? ''); ?>
                </h2>
                <p style="color: #666; margin-bottom: 15px;">
                    <i class="fas fa-map-pin"></i>
                    Lokasi: <?php echo htmlspecialchars($venue_data['location'] ?? ''); ?>
                </p>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nama Venue</span>
                    <div class="info-value"><?php echo htmlspecialchars($venue_data['name'] ?? ''); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Lokasi</span>
                    <div class="info-value"><?php echo htmlspecialchars($venue_data['location'] ?? ''); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Kapasitas</span>
                    <div class="info-value">
                        <span class="badge badge-primary"><?php echo number_format($venue_data['capacity']); ?> orang</span>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Status</span>
                    <div class="info-value">
                        <?php if ($venue_data['is_active']): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Non-Aktif</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Dibuat Pada</span>
                    <div class="info-value">
                        <?php echo date('d F Y, H:i', strtotime($venue_data['created_at'])); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Terakhir Diupdate</span>
                    <div class="info-value">
                        <?php echo date('d F Y, H:i', strtotime($venue_data['updated_at'])); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- FACILITIES SECTION -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-wrench"></i>
                    Fasilitas Venue
                </div>
            </div>
            
            <?php if (!empty($venue_data['facilities'])): ?>
                <div class="facilities-display">
                    <?php echo nl2br(htmlspecialchars($venue_data['facilities'] ?? '')); ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-wrench"></i>
                    <h4>Tidak ada informasi fasilitas</h4>
                    <p>Belum ada informasi fasilitas yang ditambahkan untuk venue ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>

document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Toggle Functionality
});
</script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>
