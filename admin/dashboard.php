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
        'active_teams' => 0
    ];
} else {
    // Get statistics real dari DB
    $stats = [
        'total_players' => 0,
        'total_teams' => 0,
        'active_teams' => 0
    ];

    try {
        $stmt = $conn->query("SELECT COUNT(*) FROM players");
        $stats['total_players'] = $stmt->fetchColumn();
        
        $stmt = $conn->query("SELECT COUNT(*) FROM teams");
        $stats['total_teams'] = $stmt->fetchColumn();
        
        $stmt = $conn->query("SELECT COUNT(*) FROM teams WHERE is_active = 1");
        $stats['active_teams'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
    }
}

// Get admin info
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_email = $_SESSION['admin_email'] ?? '';

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

:root {
    --surface: #ffffff;
    --surface-soft: #f6faff;
    --text-strong: #10243a;
    --text-muted: #5d6f83;
    --border: #d7e4f0;
    --border-strong: #c8d9ea;
    --accent: #1f5ea8;
    --accent-soft: #e8f2ff;
    --success: #157f4d;
    --success-soft: #eaf8f1;
    --danger: #b83638;
    --danger-dark: #932b2d;
    --shadow-sm: 0 8px 20px rgba(16, 36, 58, 0.06);
    --shadow-md: 0 16px 30px rgba(16, 36, 58, 0.1);
    --radius-xl: 22px;
    --radius-lg: 18px;
    --radius-md: 12px;
    --ease: 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
    color: var(--text-strong);
    min-height: 100vh;
    overflow-x: hidden;
    background:
        radial-gradient(circle at 10% 0%, #f9fcff 0%, rgba(249, 252, 255, 0) 40%),
        radial-gradient(circle at 92% 20%, #e9f2ff 0%, rgba(233, 242, 255, 0) 35%),
        linear-gradient(180deg, #edf4fb 0%, #f8fbff 100%);
}

.wrapper {
    display: flex;
    min-height: 100vh;
}

.main {
    flex: 1;
    margin-left: 280px;
    padding: 34px;
    transition: margin-left var(--ease), padding var(--ease);
}

.main > * {
    max-width: 1220px;
    margin-left: auto;
    margin-right: auto;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    margin-bottom: 26px;
    padding: 26px 28px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow-sm);
}

.greeting h1 {
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(1.6rem, 2.4vw, 1.95rem);
    font-weight: 700;
    color: var(--text-strong);
    letter-spacing: -0.02em;
    margin-bottom: 6px;
}

.greeting p {
    color: var(--text-muted);
    font-size: 0.92rem;
    line-height: 1.55;
}

.user-actions {
    display: flex;
    align-items: center;
}

.logout-btn {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 11px 18px;
    border-radius: var(--radius-md);
    border: 1px solid #e9b9ba;
    background: var(--danger);
    color: #ffffff;
    text-decoration: none;
    font-size: 0.9rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    transition: transform var(--ease), box-shadow var(--ease), background-color var(--ease);
    box-shadow: 0 8px 18px rgba(184, 54, 56, 0.28);
}

.logout-btn:hover {
    background: var(--danger-dark);
    transform: translateY(-2px);
    box-shadow: 0 12px 22px rgba(147, 43, 45, 0.32);
}

.logout-btn:focus-visible,
.action-link:focus-visible {
    outline: 2px solid #6fa5e6;
    outline-offset: 2px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 26px;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 16px;
    min-height: 128px;
    padding: 22px;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: var(--shadow-sm);
    transition: transform var(--ease), box-shadow var(--ease), border-color var(--ease);
}

.stat-card:hover {
    transform: translateY(-3px);
    border-color: var(--border-strong);
    box-shadow: var(--shadow-md);
}

.stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 1.25rem;
    color: var(--accent);
    background: var(--accent-soft);
}

.stat-card:nth-child(3) .stat-icon {
    color: var(--success);
    background: var(--success-soft);
}

.stat-content h3 {
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(1.6rem, 2.2vw, 2rem);
    font-weight: 700;
    letter-spacing: -0.02em;
    color: var(--text-strong);
    margin-bottom: 4px;
    font-variant-numeric: tabular-nums;
}

.stat-content p {
    color: var(--text-muted);
    font-size: 0.9rem;
    font-weight: 500;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.action-card {
    min-height: 240px;
    padding: 24px;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border);
    background: var(--surface);
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
    transition: transform var(--ease), box-shadow var(--ease), border-color var(--ease);
}

.action-card:hover,
.action-card:focus-within {
    transform: translateY(-3px);
    border-color: var(--border-strong);
    box-shadow: var(--shadow-md);
}

.action-icon {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    color: var(--accent);
    background: var(--accent-soft);
    font-size: 1.15rem;
}

.action-title {
    font-family: 'Outfit', 'Plus Jakarta Sans', sans-serif;
    font-size: 1.12rem;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--text-strong);
    margin-bottom: 10px;
}

.action-desc {
    color: var(--text-muted);
    font-size: 0.9rem;
    line-height: 1.6;
}

.action-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: auto;
    padding: 10px 16px;
    border-radius: 10px;
    background: var(--accent);
    color: #ffffff;
    text-decoration: none;
    font-size: 0.88rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    transition: transform var(--ease), background-color var(--ease), box-shadow var(--ease);
    box-shadow: 0 8px 16px rgba(31, 94, 168, 0.28);
}

.action-link:hover {
    background: #174f91;
    transform: translateY(-1px);
    box-shadow: 0 12px 20px rgba(23, 79, 145, 0.3);
}

.reveal {
    opacity: 0;
    animation: fadeUp 0.45s ease forwards;
}

.d-1 { animation-delay: 0.04s; }
.d-2 { animation-delay: 0.1s; }
.d-3 { animation-delay: 0.16s; }
.d-4 { animation-delay: 0.22s; }
.d-5 { animation-delay: 0.28s; }
.d-6 { animation-delay: 0.34s; }
.d-7 { animation-delay: 0.4s; }

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media screen and (max-width: 1200px) {
    .stats-grid,
    .quick-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media screen and (max-width: 1024px) {
    .main {
        margin-left: 240px;
        padding: 28px 24px;
    }

    .topbar {
        padding: 22px 24px;
    }
}

@media screen and (max-width: 768px) {
    .main {
        margin-left: 0;
        width: 100%;
        padding: 20px 14px 28px;
    }

    .topbar {
        flex-direction: column;
        align-items: flex-start;
        padding: 20px;
    }

    .user-actions {
        width: 100%;
    }

    .stats-grid,
    .quick-actions {
        grid-template-columns: 1fr;
    }

    .action-card {
        min-height: 220px;
    }
}

@media screen and (max-width: 480px) {
    .greeting h1 {
        font-size: 1.35rem;
    }

    .greeting p {
        font-size: 0.84rem;
    }

    .topbar {
        padding: 18px;
    }

    .stat-card,
    .action-card {
        padding: 20px;
    }

    .logout-btn {
        width: 100%;
        justify-content: center;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation: none !important;
        transition: none !important;
    }

    .reveal {
        opacity: 1 !important;
    }
}
</style>
</head>
<body>

<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main">
        <div class="topbar reveal d-1">
            <div class="greeting">
                <h1>Selamat datang, <?php echo htmlspecialchars($admin_name ?? ''); ?></h1>
                <p>Dashboard admin untuk manajemen pertandingan futsal.</p>
            </div>
            
            <div class="user-actions">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card reveal d-1">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3 data-count="<?php echo (int) $stats['total_players']; ?>"><?php echo number_format((int) $stats['total_players']); ?></h3>
                    <p>Total Pemain</p>
                </div>
            </div>

            <div class="stat-card reveal d-2">
                <div class="stat-icon">
                    <i class="fas fa-futbol"></i>
                </div>
                <div class="stat-content">
                    <h3 data-count="<?php echo (int) $stats['total_teams']; ?>"><?php echo number_format((int) $stats['total_teams']); ?></h3>
                    <p>Total Team</p>
                </div>
            </div>

            <div class="stat-card reveal d-3">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 data-count="<?php echo (int) $stats['active_teams']; ?>"><?php echo number_format((int) $stats['active_teams']); ?></h3>
                    <p>Team Aktif</p>
                </div>
            </div>
        </div>

        <div class="quick-actions">
            <div class="action-card reveal d-4">
                <div class="action-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="action-title">Tambah Pemain</div>
                <div class="action-desc">Tambahkan pemain baru ke sistem dengan data lengkap dan dokumen</div>
                <a href="player/add.php" class="action-link">
                    <i class="fas fa-plus"></i> Tambah Pemain
                </a>
            </div>

            <div class="action-card reveal d-5">
                <div class="action-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="action-title">Tambah Team</div>
                <div class="action-desc">Buat team baru dengan informasi lengkap dan logo team</div>
                <a href="team_create.php" class="action-link">
                    <i class="fas fa-plus"></i> Tambah Team
                </a>
            </div>

            <div class="action-card reveal d-6">
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
    // Counter animation (same style as pelatih dashboard)
    const counters = document.querySelectorAll('.stat-content h3[data-count]');
    counters.forEach((el) => {
        const target = parseInt(el.getAttribute('data-count'), 10);
        if (Number.isNaN(target)) return;

        const duration = 700;
        const start = performance.now();

        function tick(now) {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(target * eased).toLocaleString('id-ID');
            if (progress < 1) {
                requestAnimationFrame(tick);
            }
        }

        el.textContent = '0';
        requestAnimationFrame(tick);
    });
});
</script>
<?php include __DIR__ . '/includes/sidebar_js.php'; ?>
</body>
</html>
