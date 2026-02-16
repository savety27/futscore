<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in or not pelatih
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_role'] !== 'pelatih') {
    header('Location: ../login.php');
    exit;
}

$page_title = $page_title ?? 'Dashboard';
$current_page = $current_page ?? 'dashboard';
$pelatih_name = $_SESSION['admin_fullname'] ?? 'Pelatih';
$team_id = $_SESSION['team_id'] ?? 0;
$team_name = 'FutScore';

if ($team_id && isset($conn)) {
    try {
        $stmt = $conn->prepare("SELECT name, logo FROM teams WHERE id = ?");
        $stmt->execute([$team_id]);
        $team = $stmt->fetch();
        if ($team) {
            $team_name = $team['name'];
            $team_logo = $team['logo'];
        }

        // Otomatis expired challenge yang sudah lewat tanggal
        $stmt_expire = $conn->prepare("UPDATE challenges SET status = 'expired' WHERE status = 'open' AND challenge_date < NOW()");
        $stmt_expire->execute();
        
    } catch (PDOException $e) {
        $team_name = 'FutScore';
    }
} elseif (isset($conn)) {
    // Fallback jika tidak ada team_id tapi koneksi ada (jarang terjadi di pelatih area tapi untuk jaga-jaga)
    try {
        $stmt_expire = $conn->prepare("UPDATE challenges SET status = 'expired' WHERE status = 'open' AND challenge_date < NOW()");
        $stmt_expire->execute();
    } catch (PDOException $e) {
        // Silent fail
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Area Pelatih</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/style.css'); ?>">
    <style>
        /* Additional page specific styles can go here */
    </style>
</head>
<body>


<!-- Mobile Menu Components (hidden by default via CSS) -->
<div class="menu-overlay"></div>
<button class="mobile-menu-toggle" aria-label="Toggle menu">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo <?php echo (!empty($team_logo) && file_exists('../images/teams/' . $team_logo)) ? 'has-team-logo' : ''; ?>">
                    <?php if (!empty($team_logo) && file_exists('../images/teams/' . $team_logo)): ?>
                        <img src="../images/teams/<?php echo htmlspecialchars($team_logo); ?>" alt="<?php echo htmlspecialchars($team_name); ?>" class="sidebar-team-logo">
                    <?php endif; ?>
                </div>
            </div>
            <div class="academy-info">
                <div class="academy-name"><?php echo htmlspecialchars($team_name); ?></div>
                <div style="font-size: 14px; opacity: 0.8; color: white;">Portal Pelatih</div>
            </div>
        </div>

        <div class="menu">
            <div class="menu-item">
                <a href="dashboard.php" class="menu-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                    <span class="menu-icon">🏠</span>
                    <span class="menu-text">Dashboard</span>
                </a>
            </div>
            
            <div class="menu-item">
                <a href="players.php" class="menu-link <?php echo $current_page === 'players' ? 'active' : ''; ?>">
                    <span class="menu-icon">👥</span>
                    <span class="menu-text">Pemain Saya</span>
                </a>
            </div>

            <div class="menu-item">
                <a href="team.php" class="menu-link <?php echo $current_page === 'team' ? 'active' : ''; ?>">
                    <span class="menu-icon">🏆</span>
                    <span class="menu-text">Team</span>
                </a>
            </div>

            <div class="menu-item">
                <a href="team_staff.php" class="menu-link <?php echo $current_page === 'team_staff' ? 'active' : ''; ?>">
                    <span class="menu-icon">👔</span>
                    <span class="menu-text">Staf Tim</span>
                </a>
            </div>

             <div class="menu-item">
                <a href="schedule.php" class="menu-link <?php echo $current_page === 'schedule' ? 'active' : ''; ?>">
                    <span class="menu-icon">📅</span>
                    <span class="menu-text">Jadwal</span>
                </a>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="greeting">
                <h1>Halo, <?php echo htmlspecialchars($pelatih_name); ?>! 👋</h1>
                <p><?php echo $page_title; ?></p>
            </div>
            
            <div class="user-actions">
                <a href="../admin/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Keluar
                </a>
            </div>
        </div>
