<?php
session_start();

$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'operator') {
    header('Location: ../login.php');
    exit;
}

$operator_id = (int)($_SESSION['admin_id'] ?? 0);
$event_id = (int)($_SESSION['event_id'] ?? 0);
$event_name = '';
$event_image = '';
$event_is_active = true;

if ($operator_id > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT au.event_id, e.name AS event_name, e.image AS event_image, COALESCE(e.is_active, 1) AS event_is_active
            FROM admin_users au
            LEFT JOIN events e ON e.id = au.event_id
            WHERE au.id = ?
            LIMIT 1
        ");
        $stmt->execute([$operator_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $event_id = (int)($row['event_id'] ?? $event_id);
        $event_name = trim((string)($row['event_name'] ?? ''));
        $event_image = trim((string)($row['event_image'] ?? ''));
        $event_is_active = ((int)($row['event_is_active'] ?? 1) === 1);
        $_SESSION['event_id'] = $event_id > 0 ? $event_id : null;
    } catch (PDOException $e) {
        $event_id = 0;
    }
}

$page_title = 'Event Management';
$current_page = 'event';
$operator_event_name = $event_name !== '' ? $event_name : 'Event Operator';
$operator_read_only = ($event_id > 0 && !$event_is_active);

$event_value_url = $event_id > 0 ? 'event_value.php?event_id=' . $event_id : '#';
$event_bracket_url = $event_id > 0 ? 'event_bracket.php?event_id=' . $event_id : '#';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../pelatih/css/style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../pelatih/css/style.css'); ?>">
    <link rel="stylesheet" href="css/event.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/event.css'); ?>">
</head>
<body>
<div class="menu-overlay"></div>
<button class="mobile-menu-toggle" aria-label="Toggle menu">
    <i class="fas fa-bars"></i>
</button>

<div class="wrapper">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar reveal">
            <div class="greeting">
                <h1>Pusat Event 🗓️</h1>
                <p>Kelola data event dengan konsep tampilan premium</p>
            </div>
            
            <div class="user-actions">
                <a href="../operator/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Keluar
                </a>
            </div>
        </div>

        <div class="event-hub">
            <!-- Alerts -->
            <?php if ($event_id <= 0): ?>
                <div class="hub-alert alert-warning reveal d-1">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Akun operator belum terhubung ke event. Hubungi admin untuk menetapkan event terlebih dahulu.</span>
                </div>
            <?php elseif ($operator_read_only): ?>
                <div class="hub-alert alert-danger reveal d-1">
                    <i class="fas fa-lock"></i>
                    <span>Event saat ini non-aktif. Anda masih bisa melihat data, tetapi perubahan data dapat dibatasi.</span>
                </div>
            <?php endif; ?>

            <!-- Editorial Header -->
            <header class="dashboard-hero reveal d-1">
                <div class="hero-content">
                    <span class="hero-label">Manajemen Konten</span>
                    <h1 class="hero-title">Event Hub</h1>
                    <p class="hero-description">Kelola nilai pertandingan, skor, dan bracket untuk event <strong><?php echo htmlspecialchars($operator_event_name); ?></strong> secara terpusat.</p>
                </div>
            </header>

            <div class="hub-grid reveal d-2">
                <a class="hub-card <?php echo $event_id <= 0 ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars($event_value_url); ?>">
                    <div class="hub-icon"><i class="fas fa-list-ol"></i></div>
                    <h3>Event Value</h3>
                    <p>Atur klasemen, poin, hasil pertandingan, dan kartu pemain per kategori event.</p>
                    <span class="hub-link">Buka Halaman <i class="fas fa-arrow-right"></i></span>
                </a>

                <a class="hub-card <?php echo $event_id <= 0 ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars($event_bracket_url); ?>">
                    <div class="hub-icon"><i class="fas fa-diagram-project"></i></div>
                    <h3>Event Bracket</h3>
                    <p>Susun semifinal, final, perebutan juara 3, sekaligus update skor bracket event.</p>
                    <span class="hub-link">Buka Halaman <i class="fas fa-arrow-right"></i></span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
