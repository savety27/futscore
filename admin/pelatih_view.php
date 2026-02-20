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


// Fetch pelatih data dengan JOIN ke tabel teams
try {
    $stmt = $conn->prepare("
        SELECT au.*, t.name as team_name, t.logo as team_logo, t.alias as team_alias, 
               t.sport_type, t.uniform_color, t.coach as team_coach, t.basecamp as team_basecamp
        FROM admin_users au 
        LEFT JOIN teams t ON au.team_id = t.id 
        WHERE au.id = ?
    ");
    $stmt->execute([$pelatih_id]);
    $pelatih_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pelatih_data) {
        header("Location: pelatih.php");
        exit;
    }
    
} catch (PDOException $e) {
    die("Error fetching pelatih data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Pelatih</title>
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

/* Profile Section */
.profile-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 25px;
    box-shadow: var(--card-shadow);
    display: flex;
    gap: 40px;
    align-items: center;
    flex-wrap: wrap;
}

.profile-avatar {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 60px;
    border: 5px solid white;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.profile-info {
    flex: 1;
    min-width: 300px;
}

.profile-name {
    font-size: 32px;
    color: var(--dark);
    margin-bottom: 10px;
}

.profile-username {
    font-size: 20px;
    color: var(--gray);
    margin-bottom: 15px;
}

.profile-role {
    display: inline-block;
    padding: 8px 20px;
    border-radius: 20px;
    font-weight: 600;
    margin-bottom: 20px;
}

.role-superadmin {
    background: linear-gradient(135deg, #FF6B6B, #FF8E8E);
    color: white;
}

.role-admin {
    background: linear-gradient(135deg, var(--primary), #4CC9F0);
    color: white;
}

.role-editor, .role-pelatih {
    background: linear-gradient(135deg, var(--warning), #FFD166);
    color: var(--dark);
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
    color: var(--primary);
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

/* Team Info Styles */
.team-info-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-left: 5px solid var(--primary);
}

.team-display {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
    padding: 15px;
    background: white;
    border-radius: 10px;
    border: 1px solid #e0e0e0;
}

.team-logo-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 30px;
    border: 3px solid white;
    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
}

.team-details {
    flex: 1;
}

.team-name {
    font-size: 24px;
    color: var(--dark);
    margin-bottom: 5px;
}

.team-alias {
    font-size: 18px;
    color: var(--gray);
    margin-bottom: 10px;
}

.team-sport {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 15px;
    background: rgba(76, 201, 240, 0.2);
    color: var(--accent);
    font-size: 14px;
    font-weight: 600;
}

/* Color Display */
.color-display {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    margin-right: 10px;
    vertical-align: middle;
    border: 2px solid #e0e0e0;
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

    .btn {
        flex: 1;
        justify-content: center;
    }

    /* Profile Card: Stack vertically */
    .profile-card {
        flex-direction: column;
        text-align: center;
    }

    .profile-info {
        min-width: auto;
    }

    /* Stats Grid: 2 columns */
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    /* Info Grid: Single column */
    .info-grid {
        grid-template-columns: 1fr;
    }

    /* Team Display: Stack vertically */
    .team-display {
        flex-direction: column;
        text-align: center;
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


    /* Compact buttons */
    .btn {
        padding: 10px 18px;
        font-size: 14px;
    }

    .logout-btn {
        padding: 10px 20px;
        font-size: 14px;
    }

    /* Profile adjustments */
    .profile-avatar {
        width: 120px;
        height: 120px;
        font-size: 48px;
    }

    .profile-name {
        font-size: 24px;
    }

    .profile-username {
        font-size: 16px;
    }

    /* Stats Grid: Single column on very small screens */
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-number {
        font-size: 16px !important;
    }

    /* Team display adjustments */
    .team-logo-placeholder {
        width: 60px;
        height: 60px;
        font-size: 24px;
    }

    .team-name {
        font-size: 20px;
    }

    .team-alias {
        font-size: 16px;
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
                <h1>Pelatih Profile 👤</h1>
                <p>Detail informasi pelatih: <?php echo htmlspecialchars($pelatih_data['full_name'] ?? ''); ?></p>
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
                <i class="fas fa-user-circle"></i>
                <span>Detail Pelatih</span>
            </div>
            <div class="action-buttons">
                <a href="pelatih_edit.php?id=<?php echo $pelatih_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Pelatih
                </a>
                <a href="pelatih.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Kembali
                </a>
            </div>
        </div>

        <!-- PROFILE SECTION -->
        <div class="profile-card">
            <div class="profile-avatar">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="profile-info">
                <h1 class="profile-name"><?php echo htmlspecialchars($pelatih_data['full_name'] ?? ''); ?></h1>
                <div class="profile-username">@<?php echo htmlspecialchars($pelatih_data['username'] ?? ''); ?></div>
                <div class="profile-role <?php echo 'role-' . $pelatih_data['role']; ?>">
                    <?php 
                    if ($pelatih_data['role'] === 'superadmin') {
                        echo 'Super Admin';
                    } else {
                        echo 'Pelatih';
                    }
                    ?>
                </div>
                <?php if ($pelatih_data['is_active']): ?>
                    <span class="badge badge-success" style="padding: 8px 16px; font-size: 14px;">AKTIF</span>
                <?php else: ?>
                    <span class="badge badge-danger" style="padding: 8px 16px; font-size: 14px;">NON-AKTIF</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- STATS GRID -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-label">Email</div>
                <div class="stat-number" style="font-size: 18px;"><?php echo htmlspecialchars($pelatih_data['email'] ?? ''); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-label">Tim</div>
                <div class="stat-number" style="font-size: 18px;">
                    <?php echo !empty($pelatih_data['team_name']) ? htmlspecialchars($pelatih_data['team_name']) : '-'; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-label">Bergabung Sejak</div>
                <div class="stat-number" style="font-size: 18px;">
                    <?php echo date('d F Y', strtotime($pelatih_data['created_at'])); ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-label">Login Terakhir</div>
                <div class="stat-number" style="font-size: 18px;">
                    <?php echo !empty($pelatih_data['last_login']) ? date('d F Y H:i', strtotime($pelatih_data['last_login'])) : '-'; ?>
                </div>
            </div>
        </div>

        <!-- TEAM INFORMATION SECTION -->
        <?php if (!empty($pelatih_data['team_name'])): ?>
        <div class="info-card team-info-section">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-users"></i>
                    Informasi Tim
                </div>
            </div>
            
            <div class="team-display">
                <div class="team-logo-placeholder">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="team-details">
                    <h3 class="team-name"><?php echo htmlspecialchars($pelatih_data['team_name'] ?? ''); ?></h3>
                    <div class="team-alias">(<?php echo htmlspecialchars($pelatih_data['team_alias'] ?? ''); ?>)</div>
                    <?php if (!empty($pelatih_data['sport_type'])): ?>
                        <span class="team-sport"><?php echo htmlspecialchars($pelatih_data['sport_type'] ?? ''); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nama Tim</span>
                    <div class="info-value"><?php echo htmlspecialchars($pelatih_data['team_name'] ?? ''); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Alias Tim</span>
                    <div class="info-value"><?php echo htmlspecialchars($pelatih_data['team_alias'] ?? ''); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Event</span>
                    <div class="info-value">
                        <?php echo !empty($pelatih_data['sport_type']) ? htmlspecialchars($pelatih_data['sport_type']) : '-'; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Warna Seragam</span>
                    <div class="info-value">
                        <?php if (!empty($pelatih_data['uniform_color'])): ?>
                            <span class="color-display" style="background-color: <?php echo htmlspecialchars($pelatih_data['uniform_color'] ?? ''); ?>;"></span>
                            <?php echo htmlspecialchars($pelatih_data['uniform_color'] ?? ''); ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Pelatih Tim</span>
                    <div class="info-value">
                        <?php echo !empty($pelatih_data['team_coach']) ? htmlspecialchars($pelatih_data['team_coach']) : '-'; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Basecamp</span>
                    <div class="info-value">
                        <?php echo !empty($pelatih_data['team_basecamp']) ? htmlspecialchars($pelatih_data['team_basecamp']) : '-'; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- DETAILED INFORMATION -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Detail Akun
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Username</span>
                    <div class="info-value"><?php echo htmlspecialchars($pelatih_data['username'] ?? ''); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <div class="info-value"><?php echo htmlspecialchars($pelatih_data['email'] ?? ''); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Nama Lengkap</span>
                    <div class="info-value"><?php echo htmlspecialchars($pelatih_data['full_name'] ?? ''); ?></div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Role</span>
                    <div class="info-value">
                        <span class="badge <?php echo 'role-' . $pelatih_data['role']; ?>" style="padding: 6px 12px;">
                            <?php 
                            if ($pelatih_data['role'] === 'superadmin') {
                                echo 'Super Admin';
                            } else {
                                echo 'Pelatih';
                            }
                            ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($pelatih_data['team_name'])): ?>
                <div class="info-item">
                    <span class="info-label">Tim</span>
                    <div class="info-value">
                        <span class="badge badge-primary">
                            <i class="fas fa-users"></i>
                            <?php echo htmlspecialchars($pelatih_data['team_name'] ?? ''); ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="info-item">
                    <span class="info-label">Status Akun</span>
                    <div class="info-value">
                        <?php if ($pelatih_data['is_active']): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Non-Aktif</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Tanggal Dibuat</span>
                    <div class="info-value">
                        <?php echo date('d F Y, H:i', strtotime($pelatih_data['created_at'])); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Terakhir Diupdate</span>
                    <div class="info-value">
                        <?php echo date('d F Y, H:i', strtotime($pelatih_data['updated_at'])); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Login Terakhir</span>
                    <div class="info-value">
                        <?php echo !empty($pelatih_data['last_login']) ? date('d F Y, H:i', strtotime($pelatih_data['last_login'])) : '-'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACCOUNT SECURITY INFO -->
        <div class="info-card">
            <div class="info-header">
                <div class="info-title">
                    <i class="fas fa-shield-alt"></i>
                    Keamanan Akun
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Password Terakhir Diubah</span>
                    <div class="info-value">
                        <?php echo date('d F Y, H:i', strtotime($pelatih_data['updated_at'])); ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Status Password</span>
                    <div class="info-value">
                        <span class="badge badge-success">
                            <i class="fas fa-check-circle"></i>
                            Aman
                        </span>
                    </div>
                </div>
                
                <div class="info-item">
                    <span class="info-label">Rekomendasi</span>
                    <div class="info-value">
                        <?php 
                        $created_date = strtotime($pelatih_data['created_at']);
                        $current_date = time();
                        $days_diff = floor(($current_date - $created_date) / (60 * 60 * 24));
                        
                        if ($days_diff > 90): ?>
                            <span class="badge badge-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Ganti password (lebih dari 90 hari)
                            </span>
                        <?php else: ?>
                            <span class="badge badge-success">
                                <i class="fas fa-check-circle"></i>
                                Password masih baru
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                <h4 style="color: var(--primary); margin-bottom: 10px;">Tips Keamanan:</h4>
                <ul style="color: #666; line-height: 1.6;">
                    <li>Ganti password secara berkala (setiap 90 hari)</li>
                    <li>Jangan bagikan password dengan siapapun</li>
                    <li>Pastikan email yang terdaftar masih aktif</li>
                    <li>Gunakan password yang kuat dengan kombinasi huruf, angka, dan simbol</li>
                    <?php if (!empty($pelatih_data['team_name'])): ?>
                    <li>Akun ini terhubung dengan tim: <?php echo htmlspecialchars($pelatih_data['team_name'] ?? ''); ?></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
});
</script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>