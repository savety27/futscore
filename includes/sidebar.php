<!-- Mobile Header -->
<header class="mobile-dashboard-header">
    <div class="mobile-logo">
        <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
    </div>
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Buka/Tutup Sidebar" aria-controls="sidebar" aria-expanded="false">
        <i class="fas fa-bars"></i>
    </button>
</header>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar" aria-hidden="true">
    <div class="sidebar-logo">
        <a href="<?php echo SITE_URL; ?>">
            <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
        </a>
    </div>
    <nav class="sidebar-nav">
        <a href="<?php echo SITE_URL; ?>" class="<?php echo ($currentPage === 'home') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> <span>BERANDA</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/events.php" class="<?php echo ($currentPage === 'events') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> <span>EVENT</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/all.php" class="<?php echo ($currentPage === 'challenge') ? 'active' : ''; ?>">
            <i class="fas fa-trophy"></i> <span>CHALLENGE</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/team.php" class="<?php echo ($currentPage === 'team') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> <span>TEAM</span>
        </a>
        <div class="nav-item-dropdown">
            <a href="#" class="nav-has-dropdown <?php echo (in_array($currentPage, ['player', 'staff', 'perangkat'])) ? 'open' : ''; ?>" onclick="toggleDropdown(this, 'playerDropdown'); return false;">
                <div class="nav-link-content">
                    <i class="fas fa-users"></i> <span>PEMAIN</span>
                </div>
                <i class="fas fa-chevron-down dropdown-icon"></i>
            </a>
            <div id="playerDropdown" class="sidebar-dropdown <?php echo (in_array($currentPage, ['player', 'staff', 'perangkat'])) ? 'show' : ''; ?>">
                <a href="<?php echo SITE_URL; ?>/player.php" class="<?php echo ($currentPage === 'player') ? 'active' : ''; ?>">Pemain</a>
                <a href="<?php echo SITE_URL; ?>/staff.php" class="<?php echo ($currentPage === 'staff') ? 'active' : ''; ?>">Staf Team</a>
                <a href="<?php echo SITE_URL; ?>/perangkat.php" class="<?php echo ($currentPage === 'perangkat') ? 'active' : ''; ?>">Perangkat Pertandingan</a>
            </div>
        </div>
        <a href="<?php echo SITE_URL; ?>/news.php" class="<?php echo ($currentPage === 'news') ? 'active' : ''; ?>">
            <i class="fas fa-newspaper"></i> <span>BERITA</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/bpjs.php" class="<?php echo ($currentPage === 'bpjs') ? 'active' : ''; ?>">
            <i class="fas fa-shield-alt"></i> <span>BPJSTK</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/contact.php" class="<?php echo ($currentPage === 'contact') ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i> <span>KONTAK</span>
        </a>
        
        <div class="sidebar-divider" style="margin: 15px 0; border-top: 1px solid rgba(255,255,255,0.1);"></div>

        <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
            <a href="<?php echo ($_SESSION['admin_role'] === 'pelatih' ? SITE_URL.'/pelatih/dashboard.php' : SITE_URL.'/admin/dashboard.php'); ?>">
                <i class="fas fa-tachometer-alt"></i> <span>DASHBOARD</span>
            </a>
            <a href="<?php echo SITE_URL; ?>/admin/logout.php" style="color: #e74c3c;">
                <i class="fas fa-sign-out-alt"></i> <span>KELUAR</span>
            </a>
        <?php else: ?>
            <a href="<?php echo SITE_URL; ?>/login.php" class="btn-login-sidebar">
                <i class="fas fa-sign-in-alt"></i> <span>MASUK</span>
            </a>
        <?php endif; ?>
    </nav>
</aside>
