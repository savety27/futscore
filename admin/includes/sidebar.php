<?php
/**
 * ALVETRIX Admin Sidebar Include
 * Include this file inside the <div class="wrapper"> of every admin page.
 * 
 * Prerequisites: $admin_name and $admin_email (or $email) must be set before including.
 */

// --- Menu items with Font Awesome icons ---
$menu_items = [
    'dashboard' => [
        'icon' => 'fas fa-th-large',
        'name' => 'Dashboard',
        'url' => 'dashboard.php',
        'submenu' => false
    ],
    'master' => [
        'icon' => 'fas fa-database',
        'name' => 'Master Data',
        'submenu' => true,
        'items' => [
            'player' => 'player.php',
            'team' => 'team.php',
            'team_staff' => 'team_staff.php',
            'transfer' => 'transfer.php',
        ]
    ],
    'event' => [
        'icon' => 'fas fa-trophy',
        'name' => 'Event',
        'url' => 'event.php',
        'submenu' => false
    ],
    'challenge' => [
        'icon' => 'fas fa-futbol',
        'name' => 'Challenge',
        'url' => 'challenge.php',
        'submenu' => false
    ],
    'Venue' => [
        'icon' => 'fas fa-map-marker-alt',
        'name' => 'Venue',
        'url' => 'venue.php',
        'submenu' => false
    ],
    'Pelatih' => [
        'icon' => 'fas fa-chalkboard-teacher',
        'name' => 'Pelatih',
        'url' => 'pelatih.php',
        'submenu' => false
    ],
    'Berita' => [
        'icon' => 'fas fa-newspaper',
        'name' => 'Berita',
        'url' => 'berita.php',
        'submenu' => false
    ]
];

// Detect current page
$current_page = basename($_SERVER['PHP_SELF']);

// Admin info
if (!isset($admin_name)) {
    $admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
}
if (!isset($admin_email)) {
    $admin_email = $_SESSION['admin_email'] ?? '';
}
$academy_name = "Hi, Welcome...";
$email = $admin_email;

// Determine base path for links (handles subdirectory pages like player/add.php)
$_sb_base = '';
$_sb_img_base = '../';
$_sb_script_dir = __DIR__;
$_sb_current_dir = dirname($_SERVER['PHP_SELF']);
// If we're in a subdirectory of admin (e.g., /admin/player/)
if (basename(dirname($_sb_current_dir)) === 'admin' && basename($_sb_current_dir) !== 'admin') {
    $_sb_base = '../';
    $_sb_img_base = '../../';
}

// Get initials for avatar
$_sb_initials = '';
$_sb_name_parts = explode(' ', $admin_name);
foreach (array_slice($_sb_name_parts, 0, 2) as $_p) {
    $_sb_initials .= strtoupper(mb_substr(trim($_p), 0, 1));
}
if (empty($_sb_initials)) $_sb_initials = 'A';
?>

<!-- Mobile Menu Components -->
<div class="menu-overlay"></div>
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-container">
            <div class="logo">
                <img src="<?php echo $_sb_img_base; ?>images/alvetrix.png" alt="Alvetrix">
            </div>
        </div>
        <div class="academy-info">
            <div class="academy-name"><?php echo htmlspecialchars($academy_name); ?></div>
            <div class="academy-email"><?php echo htmlspecialchars($email); ?></div>
        </div>
    </div>

    <div class="menu">
        <div class="menu-section-label">Navigation</div>

        <?php foreach ($menu_items as $key => $item): ?>
        <?php 
            $isActive = false;
            $isSubmenuOpen = false;
            
            if ($item['submenu']) {
                foreach($item['items'] as $subUrl) {
                    if($current_page === $subUrl || $current_page === basename($subUrl)) {
                        $isActive = true;
                        $isSubmenuOpen = true;
                        break;
                    }
                }
            } else {
                if ($current_page === $item['url'] || $current_page === basename($item['url'])) {
                    $isActive = true;
                }
            }
        ?>
        <div class="menu-item">
            <a href="<?php echo $item['submenu'] ? '#' : $_sb_base . $item['url']; ?>" 
               class="menu-link <?php echo $isActive ? 'active' : ''; ?>" 
               data-menu="<?php echo $key; ?>">
                <span class="menu-icon"><i class="<?php echo $item['icon']; ?>"></i></span>
                <span class="menu-text"><?php echo $item['name']; ?></span>
                <?php if ($item['submenu']): ?>
                <span class="menu-arrow <?php echo $isSubmenuOpen ? 'rotate' : ''; ?>">›</span>
                <?php endif; ?>
            </a>
            
            <?php if ($item['submenu']): ?>
            <div class="submenu <?php echo $isSubmenuOpen ? 'open' : ''; ?>" id="submenu-<?php echo $key; ?>">
                <?php foreach ($item['items'] as $subKey => $subUrl): ?>
                <div class="submenu-item">
                    <a href="<?php echo $_sb_base . $subUrl; ?>" 
                       class="submenu-link <?php echo ($current_page === $subUrl || $current_page === basename($subUrl)) ? 'active' : ''; ?>">
                       <?php echo ucwords(str_replace('_', ' ', $subKey)); ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-footer-content">
            <div class="sidebar-footer-avatar"><?php echo $_sb_initials; ?></div>
            <div class="sidebar-footer-info">
                <div class="sidebar-footer-name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="sidebar-footer-role">Administrator</div>
            </div>
        </div>
    </div>
</div>
