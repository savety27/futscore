<?php
session_start();

// Load database config
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    // Jika file config tidak ada, script akan berhenti (bisa dikomment jika hanya testing UI)
    // die("Database configuration file not found at: $config_path");
}

// Cek session login (Aktifkan kembali jika sudah siap live)
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../login.php");
    exit;
}

// Placeholder connection jika file config belum ada (untuk testing tampilan)
if (!isset($conn)) {
    // Mock connection logic (Hapus blok ini jika config/database.php sudah benar)
    $stats = [
        'total_players' => 0,
        'total_teams' => 0,
        'total_events' => 0,
        'active_teams' => 0
    ];
} else {
    // Get statistics real dari DB
    $stats = [
        'total_players' => 0,
        'total_teams' => 0,
        'total_events' => 0,
        'active_teams' => 0
    ];

    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM players");
        $stats['total_players'] = $stmt->fetchColumn();
        
        $stmt = $conn->query("SELECT COUNT(*) FROM teams");
        $stats['total_teams'] = $stmt->fetchColumn();
        
        $stmt = $conn->query("SELECT COUNT(*) FROM teams WHERE is_active = 1");
        $stats['active_teams'] = $stmt->fetchColumn();
        
        $stmt = $conn->query("SELECT COUNT(*) FROM events");
        $stats['total_events'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $stats['total_events'] = 0;
    }
}

// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';

$academy_name = "Hi, Welcome...";
$email = $admin_email;

// --- DATA MENU DROPDOWN ---
$menu_items = [
    'dashboard' => [
        'icon' => '🏠',
        'name' => 'Dashboard',
        'url' => 'dashboard.php',
        'submenu' => false
    ],
    'master' => [
        'icon' => '📊',
        'name' => 'Master Data',
        'submenu' => true,
        'items' => [
            'player' => 'player.php',
            'team' => 'team.php',
            'team_staff' => 'team_staff.php',
            'transfer' => 'transfer.php',
        ]
    ],
    'Event' => [
        'icon' => '🏆',
        'name' => 'Event',
        'url' => 'challenge.php', // URL sesuai permintaan
        'submenu' => false
    ],
    'Venue' => [
        'icon' => '📍',
        'name' => 'Venue',
        'url' => 'venue.php',
        'submenu' => false
    ],
    'Pelatih' => [
        'icon' => '👨‍🏫',
        'name' => 'Pelatih',
        'url' => 'pelatih.php',
        'submenu' => false
    ],
    'Berita' => [
        'icon' => '📰',
        'name' => 'Berita',
        'url' => 'berita.php',
        'submenu' => false
    ]
];

// Mendapatkan nama file saat ini untuk penanda menu 'Active'
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - MGP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #0A2463;
    --secondary: #FFD700;
    --accent: #4CC9F0;
    --danger: #D32F2F;
    --light: #F8F9FA;
    --dark: #1A1A2E;
    --gray: #6C757D;
    --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
    color: var(--dark);
    min-height: 100vh;
    overflow-x: hidden;
}

.wrapper {
    display: flex;
    min-height: 100vh;
}

/* ===== SIDEBAR ===== */
.sidebar {
    width: 280px;
    background: linear-gradient(180deg, var(--primary) 0%, #1a365d 100%);
    color: white;
    padding: 0;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 100;
    box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
    transition: var(--transition);
}

.sidebar-header {
    padding: 30px 25px;
    text-align: center;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 2px solid var(--secondary);
}

.logo {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--secondary) 0%, #FFEC8B 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    border: 4px solid white;
    box-shadow: 0 0 25px rgba(255, 215, 0, 0.3);
    transition: var(--transition);
}

.logo:hover {
    transform: rotate(15deg) scale(1.05);
    box-shadow: 0 0 35px rgba(255, 215, 0, 0.5);
}

.logo::before {
    content: "⚽";
    font-size: 48px;
    color: var(--primary);
}

.academy-name {
    font-size: 22px;
    font-weight: 700;
    color: var(--secondary);
    margin-bottom: 8px;
    letter-spacing: 0.5px;
}

.academy-email {
    font-size: 14px;
    opacity: 0.9;
    color: rgba(255, 255, 255, 0.8);
}

/* Menu */
.menu {
    padding: 25px 15px;
}

.menu-item {
    margin-bottom: 8px;
    border-radius: 12px;
    overflow: hidden;
}

.menu-link {
    display: flex;
    align-items: center;
    padding: 16px 20px;
    color: rgba(255, 255, 255, 0.85);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    border-left: 4px solid transparent;
}

.menu-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: var(--secondary);
    padding-left: 25px;
}

.menu-link.active {
    background: rgba(255, 215, 0, 0.15);
    color: var(--secondary);
    border-left-color: var(--secondary);
    font-weight: 600;
}

.menu-icon {
    font-size: 22px;
    margin-right: 15px;
    width: 30px;
    text-align: center;
}

.menu-text {
    flex: 1;
    font-size: 16px;
}

.menu-arrow {
    font-size: 12px;
    transition: var(--transition);
}

.menu-arrow.rotate {
    transform: rotate(90deg);
}

/* Submenu */
.submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease-in-out;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 0 0 12px 12px;
}

.submenu.open {
    max-height: 300px;
}

.submenu-item {
    padding: 5px 15px 5px 70px;
}

.submenu-link {
    display: block;
    padding: 12px 15px;
    color: rgba(255, 255, 255, 0.7);
    text-decoration: none;
    border-radius: 8px;
    transition: var(--transition);
    position: relative;
    font-size: 14px;
}

.submenu-link:hover {
    background: rgba(255, 215, 0, 0.1);
    color: var(--secondary);
    padding-left: 20px;
}

.submenu-link::before {
    content: "•";
    position: absolute;
    left: 0;
    color: var(--secondary);
    font-size: 18px;
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

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 6px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    box-shadow: 0 10px 20px rgba(10, 36, 99, 0.2);
}

.stat-content h3 {
    font-size: 24px;
    color: var(--dark);
    margin-bottom: 5px;
    font-weight: 700;
}

.stat-content p {
    color: var(--gray);
    font-size: 14px;
    font-weight: 500;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.action-card {
    background: white;
    padding: 25px;
    border-radius: 20px;
    box-shadow: var(--card-shadow);
    text-align: center;
    transition: var(--transition);
    border: 2px solid transparent;
}

.action-card:hover {
    transform: translateY(-5px);
    border-color: var(--secondary);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
}

.action-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    margin: 0 auto 15px;
    box-shadow: 0 10px 20px rgba(10, 36, 99, 0.2);
}

.action-title {
    font-size: 18px;
    color: var(--dark);
    margin-bottom: 10px;
    font-weight: 600;
}

.action-desc {
    font-size: 14px;
    color: var(--gray);
    line-height: 1.5;
}

.action-link {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 20px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 600;
    transition: var(--transition);
}

.action-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(10, 36, 99, 0.3);
}


/* =========================================
   MOBILE RESPONSIVE DESIGN
   ========================================= */

/* Default: Hide mobile-only elements on desktop */
.menu-toggle {
    display: none;
}

.menu-overlay {
    display: none;
}

/* ===== TABLET (max-width: 1024px) ===== */
@media screen and (max-width: 1024px) {
    .sidebar {
        width: 240px;
    }

    .main {
        margin-left: 240px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .quick-actions {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* ===== MOBILE LANDSCAPE (max-width: 768px) ===== */
@media screen and (max-width: 768px) {
    
    /* Show Mobile Menu Toggle Button */
    .menu-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        position: fixed;
        bottom: 25px;
        right: 25px;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--secondary), #FFEC8B);
        color: var(--primary);
        border: none;
        border-radius: 50%;
        box-shadow: 0 5px 20px rgba(255, 215, 0, 0.4);
        z-index: 1001;
        font-size: 24px;
        cursor: pointer;
        transition: var(--transition);
    }

    .menu-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 8px 25px rgba(255, 215, 0, 0.6);
    }

    .menu-toggle:active {
        transform: scale(0.95);
    }

    /* Sidebar: Hidden by default on mobile */
    .sidebar {
        transform: translateX(-100%);
        box-shadow: none;
        width: 280px;
    }

    /* Sidebar: Show when active */
    .sidebar.active {
        transform: translateX(0);
        box-shadow: 10px 0 40px rgba(0, 0, 0, 0.3);
    }

    /* Overlay: Show when menu is open */
    .menu-overlay {
        display: block;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 99;
        opacity: 0;
        visibility: hidden;
        transition: var(--transition);
        backdrop-filter: blur(2px);
    }

    body.menu-open .menu-overlay {
        opacity: 1;
        visibility: visible;
    }

    /* Main Content: Full width on mobile */
    .main {
        margin-left: 0;
        padding: 20px 15px;
        width: 100%;
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

    /* Stats Grid: 1 column */
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .stat-card {
        padding: 20px;
    }

    /* Quick Actions: 1 column */
    .quick-actions {
        grid-template-columns: 1fr;
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
    
    /* Smaller stat icons */
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 22px;
    }
    
    .stat-content h3 {
        font-size: 20px;
    }
    
    .stat-content p {
        font-size: 13px;
    }

    /* Compact sidebar */
    .sidebar {
        width: 260px;
    }

    .sidebar-header {
        padding: 20px 15px;
    }

    .logo {
        width: 80px;
        height: 80px;
    }

    .logo::before {
        font-size: 36px;
    }

    .academy-name {
        font-size: 18px;
    }
    
    /* Compact menu */
    .menu {
        padding: 20px 10px;
    }

    .menu-link {
        padding: 14px 15px;
        font-size: 15px;
    }

    .menu-icon {
        font-size: 20px;
        width: 28px;
    }

    /* Smaller mobile toggle button */
    .menu-toggle {
        width: 55px;
        height: 55px;
        font-size: 22px;
        bottom: 20px;
        right: 20px;
    }

    /* Compact action cards */
    .action-card {
        padding: 20px 15px;
    }

    .action-title {
        font-size: 16px;
    }

    .action-desc {
        font-size: 13px;
    }

    .logout-btn {
        padding: 10px 20px;
        font-size: 14px;
    }
}
</style>
</head>
<body>

<!-- Mobile Menu Components (hidden by default via CSS) -->
<div class="menu-overlay"></div>
<button class="menu-toggle" id="menuToggle">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo"></div>
            </div>
            <div class="academy-info">
                <div class="academy-name"><?php echo $academy_name; ?></div>
                <div class="academy-email"><?php echo $email; ?></div>
            </div>
        </div>

        <div class="menu">
            <?php foreach ($menu_items as $key => $item): ?>
            <?php 
                // Cek apakah menu ini aktif berdasarkan URL saat ini
                $isActive = false;
                $isSubmenuOpen = false;
                
                if ($item['submenu']) {
                    // Cek jika salah satu sub-item ada yang aktif
                    foreach($item['items'] as $subUrl) {
                        if($current_page === $subUrl) {
                            $isActive = true;
                            $isSubmenuOpen = true;
                            break;
                        }
                    }
                } else {
                    if ($current_page === $item['url']) {
                        $isActive = true;
                    }
                }
            ?>
            <div class="menu-item">
                <a href="<?php echo $item['submenu'] ? '#' : $item['url']; ?>" 
                   class="menu-link <?php echo $isActive ? 'active' : ''; ?>" 
                   data-menu="<?php echo $key; ?>">
                        <span class="menu-icon"><?php echo $item['icon']; ?></span>
                        <span class="menu-text"><?php echo $item['name']; ?></span>
                        <?php if ($item['submenu']): ?>
                        <span class="menu-arrow <?php echo $isSubmenuOpen ? 'rotate' : ''; ?>">›</span>
                        <?php endif; ?>
                </a>
                
                <?php if ($item['submenu']): ?>
                <div class="submenu <?php echo $isSubmenuOpen ? 'open' : ''; ?>" id="submenu-<?php echo $key; ?>">
                    <?php foreach ($item['items'] as $subKey => $subUrl): ?>
                    <div class="submenu-item">
                        <a href="<?php echo $subUrl; ?>" 
                           class="submenu-link <?php echo ($current_page === $subUrl) ? 'active' : ''; ?>">
                           <?php echo ucwords(str_replace('_', ' ', $subKey)); ?>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="main">
        <div class="topbar">
            <div class="greeting">
                <h1>Selamat Datang, <?php echo htmlspecialchars($admin_name ?? ''); ?> ! 👋</h1>
                <p>Dashboard admin MGP - Sistem manajemen pertandingan futsal</p>
            </div>
            
            <div class="user-actions">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_players']); ?></h3>
                    <p>Total Pemain</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-futbol"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_teams']); ?></h3>
                    <p>Total Team</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_events']); ?></h3>
                    <p>Total Event</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['active_teams']); ?></h3>
                    <p>Team Aktif</p>
                </div>
            </div>
        </div>

        <div class="quick-actions">
            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="action-title">Tambah Pemain</div>
                <div class="action-desc">Tambahkan pemain baru ke sistem dengan data lengkap dan dokumen</div>
                <a href="player/add.php" class="action-link">
                    <i class="fas fa-plus"></i> Tambah Pemain
                </a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="action-title">Tambah Team</div>
                <div class="action-desc">Buat team baru dengan informasi lengkap dan logo team</div>
                <a href="team_create.php" class="action-link">
                    <i class="fas fa-plus"></i> Tambah Team
                </a>
            </div>

            <div class="action-card">
                <div class="action-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="action-title">Kelola Event</div>
                <div class="action-desc">Atur jadwal pertandingan dan turnamen futsal</div>
                <a href="challenge.php" class="action-link">
                    <i class="fas fa-calendar"></i> Kelola Event
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Toggle Functionality
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
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
    
    // Submenu toggle functionality
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

    // Action card hover effects
    document.querySelectorAll('.action-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 15px 35px rgba(0, 0, 0, 0.12)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'var(--card-shadow)';
        });
    });
});
</script>
</body>
</html>