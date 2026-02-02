<?php
require_once 'functions.php';
$latestNews = getLatestNews(3);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
</head>
<body>
    <!-- Top Navbar -->
    <div class="top-navbar">
        <div class="container">
            <div class="top-navbar-content">
                <div class="latest-news-label">
                    <i class="fas fa-bullhorn"></i> LATEST NEWS
                </div>
                <div class="news-ticker">
                    <div class="ticker-wrapper">
                        <?php foreach ($latestNews as $index => $news): ?>
                        <div class="ticker-item <?php echo $index === 0 ? 'active' : ''; ?>">
                           <a href="<?php echo SITE_URL; ?>/news.php?slug=<?php echo $news['slug']; ?>">
                                <?php echo $news['title']; ?>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="ticker-controls">
                        <button class="ticker-prev"><i class="fas fa-chevron-left"></i></button>
                        <button class="ticker-next"><i class="fas fa-chevron-right"></i></button>
                    </div>
                </div>
                <div class="social-icons">
                    <a href="https://www.youtube.com/@futscoreindonesia4634" target="_blank" class="social-icon youtube">
                        <i class="fab fa-youtube"></i>
                    </a>
                    <a href="https://www.instagram.com/futscore.id/" target="_blank" class="social-icon instagram">
                        <i class="fab fa-instagram"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navbar -->
    <nav class="main-navbar">
        <div class="container">
            <div class="navbar-content">
                <div class="logo">
                    <a href="<?php echo SITE_URL; ?>">
                        <img src="<?php echo SITE_URL; ?>/images/MGP FC.jpeg" alt="Futscore Logo">
                    </a>
                </div>
                <ul class="nav-menu">
                    <li><a href="<?php echo SITE_URL; ?>" class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">HOME</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/event.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'event.php' ? 'active' : ''; ?>">EVENT</a></li>
                    <li class="dropdown">
                        <a href="<?php echo SITE_URL; ?>/team.php" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['team.php']) ? 'active' : ''; ?>">
                            TEAM
                        </a>
                    </li>
                    <li class="dropdown">
                        <a href="#" class="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['player.php', 'staff.php', 'official.php']) ? 'active' : ''; ?>">
                            PLAYER <i class="fas fa-chevron-down"></i>
                        </a>
                        <div class="dropdown-menu">
                            <a href="<?php echo SITE_URL; ?>/player.php">Player</a>
                            <a href="<?php echo SITE_URL; ?>/staff.php">Team Staff</a>
                        </div>
                    </li>
                    <li><a href="<?php echo SITE_URL; ?>/news.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'news.php' ? 'active' : ''; ?>">NEWS</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/bpjs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'bpjs.php' ? 'active' : ''; ?>">BPJSTK</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/contact.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">CONTACT</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/login.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>">LOGIN</a></li>
                </ul>
                <div class="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    </nav>

    <!-- BPJS Banner -->
    <?php if (basename($_SERVER['PHP_SELF']) == 'index.php'): ?>
    <div class="bpjs-banner">
        <div class="container">
            <a href="<?php echo SITE_URL; ?>/bpjs.php?src=navbar">
                <img src="<?php echo SITE_URL; ?>/images/bpjs-banner-web.svg" alt="BPJS Banner">
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="main-content">