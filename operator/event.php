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

$page_title = 'Event';
$current_page = 'event';
$operator_event_name = $event_name !== '' ? $event_name : 'Event Operator';
$operator_event_image = $event_image;
$operator_read_only = ($event_id > 0 && !$event_is_active);
$topbar_title = 'Event Management 🗓️';
$topbar_subtitle = 'Kelola data event dengan konsep tampilan seragam';

$event_value_url = $event_id > 0 ? 'event_value.php?event_id=' . $event_id : '#';
$event_bracket_url = $event_id > 0 ? 'event_bracket.php?event_id=' . $event_id : '#';

require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* Align with dashboard tone and avoid yellow active menu on this page */
    .main {
        background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
    }
    .menu-link.active {
        background: linear-gradient(90deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.02) 100%) !important;
        color: #f59e0b !important;
        border-right: 4px solid #f59e0b !important;
    }

    .event-hub {
        max-width: 1100px;
        margin: 0 auto;
        display: grid;
        gap: 20px;
    }
    .hub-header {
        background: #fff;
        border-radius: 18px;
        padding: 24px;
        box-shadow: 0 8px 24px rgba(15, 39, 68, 0.1);
    }
    .hub-title {
        margin: 0;
        font-size: 30px;
        color: #0f2744;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .hub-subtitle {
        margin-top: 10px;
        color: #64748b;
    }
    .hub-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }
    .hub-card {
        background: #fff;
        border-radius: 18px;
        border: 1px solid #dbeafe;
        padding: 24px;
        box-shadow: 0 10px 24px rgba(15, 39, 68, 0.08);
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        text-decoration: none;
        color: inherit;
    }
    .hub-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 28px rgba(15, 39, 68, 0.15);
        border-color: #93c5fd;
    }
    .hub-card.disabled {
        opacity: .65;
        pointer-events: none;
        transform: none;
    }
    .hub-icon {
        width: 54px;
        height: 54px;
        border-radius: 14px;
        background: linear-gradient(135deg, #0f2744, #1e40af);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin-bottom: 16px;
    }
    .hub-card h3 {
        margin: 0 0 8px;
        color: #0f2744;
    }
    .hub-card p {
        margin: 0 0 16px;
        color: #64748b;
        line-height: 1.5;
    }
    .hub-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #1d4ed8;
        font-weight: 600;
    }
    .hub-alert {
        padding: 14px 16px;
        border-radius: 12px;
        background: #fef3c7;
        color: #92400e;
        border-left: 4px solid #f59e0b;
        margin-bottom: 4px;
    }
    @media (max-width: 768px) {
        .hub-title {
            font-size: 24px;
        }
    }
</style>

<div class="event-hub">
    <?php if ($event_id <= 0): ?>
        <div class="hub-alert">
            Akun operator belum terhubung ke event. Hubungi admin untuk menetapkan event terlebih dahulu.
        </div>
    <?php elseif ($operator_read_only): ?>
        <div class="hub-alert">
            Event saat ini non-aktif. Anda masih bisa melihat data, tetapi perubahan data dapat dibatasi.
        </div>
    <?php endif; ?>

    <div class="hub-header">
        <h2 class="hub-title"><i class="fas fa-trophy"></i> Manajemen Event</h2>
        <div class="hub-subtitle">
            Kelola nilai pertandingan, skor, dan bracket untuk event operator.
        </div>
    </div>

    <div class="hub-grid">
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
