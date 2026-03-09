<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'operator') {
    header('Location: ../login.php');
    exit;
}

$page_title = $page_title ?? 'Dashboard';
$current_page = $current_page ?? '';
$operator_name = $_SESSION['admin_fullname'] ?? 'Operator';
$operator_event_name = $operator_event_name ?? 'Event Operator';
$operator_event_image = $operator_event_image ?? '';
$topbar_title = $topbar_title ?? ('Halo, ' . $operator_name . '!');
$topbar_subtitle = $topbar_subtitle ?? $page_title;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Area Operator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../pelatih/css/style.css?v=<?php echo (int) @filemtime(__DIR__ . '/../../pelatih/css/style.css'); ?>">
</head>
<body>
<div class="menu-overlay"></div>
<button class="mobile-menu-toggle" aria-label="Toggle menu">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <div class="main">
        <div class="topbar">
            <div class="greeting">
                <h1><?php echo htmlspecialchars($topbar_title); ?></h1>
                <p><?php echo htmlspecialchars($topbar_subtitle); ?></p>
            </div>

            <div class="user-actions">
                <a href="../admin/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Keluar
                </a>
            </div>
        </div>
