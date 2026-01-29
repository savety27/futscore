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
$pelatih_name = $_SESSION['admin_fullname'] ?? 'Coach';
$team_id = $_SESSION['team_id'] ?? 0;
$team_name = 'FutScore';

if ($team_id && isset($conn)) {
    try {
        $stmt = $conn->prepare("SELECT name FROM teams WHERE id = ?");
        $stmt->execute([$team_id]);
        $team = $stmt->fetch();
        if ($team) {
            $team_name = $team['name'];
        }
    } catch (PDOException $e) {
        $team_name = 'FutScore';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Pelatih Area</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Additional page specific styles can go here */
    </style>
</head>
<body>

<div class="wrapper">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo"></div>
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
                    <span class="menu-text">My Players</span>
                </a>
            </div>

             <div class="menu-item">
                <a href="#" class="menu-link <?php echo $current_page === 'schedule' ? 'active' : ''; ?>">
                    <span class="menu-icon">📅</span>
                    <span class="menu-text">Schedule</span>
                </a>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="greeting">
                <h1>Hello, <?php echo htmlspecialchars($pelatih_name); ?>! 👋</h1>
                <p><?php echo $page_title; ?></p>
            </div>
            
            <div class="user-actions">
                <a href="../admin/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
