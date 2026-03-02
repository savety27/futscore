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

$page_title = 'Dashboard';
$current_page = 'dashboard';

$operator_id = (int) ($_SESSION['admin_id'] ?? 0);
$event_id = (int) ($_SESSION['event_id'] ?? 0);
$event_name = '';
$event_image = '';
$event_category = '-';
$event_slug = '';
$event_location = '';
$event_contact = '';
$event_description = '';
$event_start_date = '';
$event_end_date = '';
$event_registration_status = 'closed';
$event_is_active = 1;
$challenge_total = 0;
$challenge_upcoming = 0;
$challenge_completed = 0;
$berita_total = 0;
$today_matches = [];

if ($operator_id > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT au.event_id,
                   e.name AS event_name,
                   e.category AS event_category,
                   e.image AS event_image,
                   e.slug AS event_slug,
                   e.location AS event_location,
                   e.contact AS event_contact,
                   e.description AS event_description,
                   e.start_date AS event_start_date,
                   e.end_date AS event_end_date,
                   e.registration_status AS event_registration_status,
                   COALESCE(e.is_active, 1) AS event_is_active
            FROM admin_users au
            LEFT JOIN events e ON e.id = au.event_id
            WHERE au.id = ?
            LIMIT 1
        ");
        $stmt->execute([$operator_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $event_id = (int) ($row['event_id'] ?? 0);
        $event_name = trim((string) ($row['event_name'] ?? ''));
        $event_image = trim((string) ($row['event_image'] ?? ''));
        $event_category = trim((string) ($row['event_category'] ?? '-'));
        $event_slug = trim((string) ($row['event_slug'] ?? ''));
        $event_location = trim((string) ($row['event_location'] ?? ''));
        $event_contact = trim((string) ($row['event_contact'] ?? ''));
        $event_description = trim((string) ($row['event_description'] ?? ''));
        $event_start_date = trim((string) ($row['event_start_date'] ?? ''));
        $event_end_date = trim((string) ($row['event_end_date'] ?? ''));
        $event_registration_status = trim((string) ($row['event_registration_status'] ?? 'closed'));
        $event_is_active = (int) ($row['event_is_active'] ?? 1);
        $_SESSION['event_id'] = $event_id > 0 ? $event_id : null;
    } catch (PDOException $e) {
        $event_id = 0;
    }
}

if ($event_id > 0) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM challenges WHERE event_id = ?");
        $stmt->execute([$event_id]);
        $challenge_total = (int) $stmt->fetchColumn();

        $stmt = $conn->prepare("
            SELECT COUNT(*)
            FROM challenges
            WHERE event_id = ?
              AND status IN ('open','accepted')
              AND challenge_date >= NOW()
        ");
        $stmt->execute([$event_id]);
        $challenge_upcoming = (int) $stmt->fetchColumn();

        $stmt = $conn->prepare("SELECT COUNT(*) FROM challenges WHERE event_id = ? AND status = 'completed'");
        $stmt->execute([$event_id]);
        $challenge_completed = (int) $stmt->fetchColumn();

        $stmt = $conn->query("SELECT COUNT(*) FROM berita");
        $berita_total = (int) $stmt->fetchColumn();

        $stmt = $conn->prepare("
            SELECT c.challenge_date, c.sport_type, c.status,
                   t1.name AS team1_name, t1.logo AS team1_logo,
                   t2.name AS team2_name, t2.logo AS team2_logo,
                   v.name AS venue_name
            FROM challenges c
            JOIN teams t1 ON c.challenger_id = t1.id
            JOIN teams t2 ON c.opponent_id = t2.id
            LEFT JOIN venues v ON c.venue_id = v.id
            WHERE c.event_id = ?
              AND DATE(c.challenge_date) = CURDATE()
            ORDER BY c.challenge_date ASC
        ");
        $stmt->execute([$event_id]);
        $today_matches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        // Keep dashboard rendering even if any query fails.
    }
}

$event_period_label = '-';
$event_duration_label = '-';
$event_start_display = '-';
$event_end_display = '-';
$event_progress_percent = 0;
$event_runtime_label = 'Jadwal Belum Lengkap';
$event_runtime_class = 'neutral';
$event_countdown_label = '-';

$event_registration_is_open = (strtolower($event_registration_status) === 'open');
$event_registration_label = $event_registration_is_open ? 'Pendaftaran Dibuka' : 'Pendaftaran Ditutup';
$event_registration_class = $event_registration_is_open ? 'registration-open' : 'registration-closed';

$event_visibility_label = $event_is_active ? 'Event Aktif' : 'Event Disembunyikan';
$event_visibility_class = $event_is_active ? 'visibility-active' : 'visibility-inactive';

$startDateObj = DateTime::createFromFormat('Y-m-d', $event_start_date);
$endDateObj = DateTime::createFromFormat('Y-m-d', $event_end_date);

if ($startDateObj instanceof DateTime) {
    $event_start_display = $startDateObj->format('d M Y');
}
if ($endDateObj instanceof DateTime) {
    $event_end_display = $endDateObj->format('d M Y');
}
if ($startDateObj instanceof DateTime && $endDateObj instanceof DateTime && $endDateObj >= $startDateObj) {
    $event_period_label = $startDateObj->format('d M Y') . ' - ' . $endDateObj->format('d M Y');
    $durationDays = ((int)$startDateObj->diff($endDateObj)->format('%a')) + 1;
    $event_duration_label = $durationDays . ' hari';

    $today = new DateTime('today');
    if ($today < $startDateObj) {
        $daysToStart = (int)$today->diff($startDateObj)->format('%a');
        $event_runtime_label = 'Akan Dimulai';
        $event_runtime_class = 'upcoming';
        $event_countdown_label = 'Mulai ' . $daysToStart . ' hari lagi';
        $event_progress_percent = 0;
    } elseif ($today > $endDateObj) {
        $daysAfterEnd = (int)$endDateObj->diff($today)->format('%a');
        $event_runtime_label = 'Selesai';
        $event_runtime_class = 'ended';
        $event_countdown_label = 'Berakhir ' . $daysAfterEnd . ' hari lalu';
        $event_progress_percent = 100;
    } else {
        $currentDay = ((int)$startDateObj->diff($today)->format('%a')) + 1;
        $event_runtime_label = 'Sedang Berjalan';
        $event_runtime_class = 'ongoing';
        $event_countdown_label = 'Hari ke-' . $currentDay . ' dari ' . $durationDays;
        if ($durationDays <= 1) {
            $event_progress_percent = 100;
        } else {
            $event_progress_percent = (int)round((($currentDay - 1) / ($durationDays - 1)) * 100);
        }
    }
}

$event_progress_percent = max(0, min(100, $event_progress_percent));
$event_location_display = $event_location !== '' ? $event_location : '-';
$event_contact_display = $event_contact !== '' ? $event_contact : '-';
$event_slug_display = $event_slug !== '' ? $event_slug : '-';
$event_description_display = $event_description !== '' ? $event_description : 'Belum ada deskripsi event.';
$event_image_path = '';
if ($event_image !== '' && file_exists(__DIR__ . '/../images/events/' . $event_image)) {
    $event_image_path = '../images/events/' . $event_image;
}

$operator_event_name = $event_name !== '' ? $event_name : 'Event Operator';
$operator_event_image = $event_image;
require_once __DIR__ . '/includes/header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

    :root {
        --premium-bg: #eaf6ff;
        --premium-card: #ffffff;
        --premium-border: #cfe5ff;
        --premium-text: #0f2744;
        --premium-text-muted: #5f728a;
        --premium-accent: #0f2744;
        --premium-gold: #f59e0b;
        --font-outfit: 'Outfit', sans-serif;
        --soft-shadow: 0 6px 20px rgba(30, 64, 175, 0.08);
        --hover-shadow: 0 14px 34px rgba(30, 64, 175, 0.16);
    }

    .main {
        background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
        color: var(--premium-text);
        font-family: var(--font-outfit);
        padding: 30px !important;
    }

    .menu-link.active {
        background: linear-gradient(90deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.02) 100%) !important;
        color: #f59e0b !important;
        border-right: 4px solid #f59e0b !important;
    }

    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .dashboard-hero { margin-bottom: 40px; }
    .hero-label {
        color: var(--premium-accent);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 2px;
        font-size: 0.8rem;
        margin-bottom: 8px;
        display: block;
    }
    .hero-title {
        font-size: 2.5rem;
        font-weight: 800;
        color: var(--premium-accent);
        margin-bottom: 15px;
    }
    .hero-description {
        color: var(--premium-text-muted);
        font-size: 1.05rem;
        max-width: 720px;
        line-height: 1.6;
    }

    .premium-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 25px;
        margin-bottom: 45px;
    }
    .premium-card {
        background: var(--premium-card);
        border: 1px solid var(--premium-border);
        border-radius: 24px;
        padding: 30px;
        box-shadow: var(--soft-shadow);
        transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.32s ease, border-color 0.32s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
        will-change: transform;
    }
    .premium-card:hover {
        transform: translateY(-11px) scale(1.017);
        box-shadow: 0 26px 52px rgba(30, 64, 175, 0.26), 0 0 0 3px rgba(76, 138, 255, 0.24);
        border-color: #8ebeff;
    }
    .premium-card:active {
        transform: translateY(-4px) scale(1.008);
    }
    .premium-card:focus-within {
        transform: translateY(-11px) scale(1.017);
        box-shadow: 0 26px 52px rgba(30, 64, 175, 0.26), 0 0 0 3px rgba(76, 138, 255, 0.24);
        border-color: #8ebeff;
    }
    .premium-card.d-2:hover,
    .premium-card.d-2:focus-within {
        transform: perspective(900px) translateY(-13px) translateZ(18px) scale(1.032);
        box-shadow: 0 30px 60px rgba(30, 64, 175, 0.3), 0 0 0 4px rgba(76, 138, 255, 0.28);
        border-color: #79abf5;
    }
    .premium-card::before {
        content: '';
        position: absolute;
        top: -120%;
        left: -35%;
        width: 35%;
        height: 300%;
        background: linear-gradient(120deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.45), rgba(255, 255, 255, 0));
        transform: rotate(14deg);
        transition: transform 0.8s ease, left 0.8s ease;
        pointer-events: none;
    }
    .premium-card:hover::before {
        left: 120%;
    }
    .card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 22px;
    }
    .card-icon-box {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        background: #eaf2ff;
        color: var(--premium-accent);
        transition: transform 0.28s ease, background-color 0.28s ease, color 0.28s ease;
    }
    .premium-card:hover .card-icon-box {
        background: var(--premium-accent);
        color: white;
        transform: translateY(-3px) scale(1.06);
    }
    .premium-card:focus-within .card-icon-box {
        background: var(--premium-accent);
        color: white;
        transform: translateY(-3px) scale(1.06);
    }
    .card-value {
        font-size: 2.1rem;
        font-weight: 800;
        margin-bottom: 4px;
    }
    .card-label {
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--premium-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .section-header { display: flex; align-items: center; gap: 20px; margin-bottom: 24px; }
    .section-title { font-size: 1.45rem; font-weight: 800; color: var(--premium-accent); white-space: nowrap; }
    .section-line { height: 1px; background: var(--premium-border); flex: 1; }

    .editorial-match-card {
        background: white;
        border: 1px solid var(--premium-border);
        border-radius: 28px;
        overflow: hidden;
        box-shadow: var(--soft-shadow);
        display: flex;
        flex-direction: column;
    }
    .match-spotlight {
        margin-top: 50px;
    }
    .today-match {
        margin-bottom: 18px;
    }
    .today-match:last-child {
        margin-bottom: 0;
    }
    .status-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.45);
        margin-left: 8px;
    }
    .match-body {
        padding: 48px 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 70px;
        background: radial-gradient(circle at center, #ffffff 0%, #eef6ff 100%);
    }
    .team-block { text-align: center; flex: 1; }
    .team-logo-frame {
        width: 110px;
        height: 110px;
        margin: 0 auto 16px;
        background: white;
        border-radius: 50%;
        padding: 12px;
        border: 1px solid var(--premium-border);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .team-logo-frame img { width: 100%; height: 100%; object-fit: contain; }
    .team-name-small { font-size: 1.25rem; font-weight: 700; color: var(--premium-accent); }
    .vs-capsule {
        padding: 10px 22px;
        background: var(--premium-accent);
        color: white;
        border-radius: 100px;
        font-weight: 800;
        letter-spacing: 1.6px;
    }
    .match-footer {
        padding: 24px 26px;
        background: #eaf2ff;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
        border-top: 1px solid var(--premium-border);
    }
    .info-group { text-align: center; }
    .info-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--premium-text-muted);
        text-transform: uppercase;
        letter-spacing: 1.2px;
        margin-bottom: 4px;
    }
    .info-data { font-size: 1.02rem; font-weight: 700; color: var(--premium-text); }

    .empty-state-light {
        padding: 68px 24px;
        text-align: center;
        background: white;
        border: 2px dashed #bfdbfe;
        border-radius: 26px;
    }
    .empty-state-light i { font-size: 2.4rem; color: #9eb8dd; margin-bottom: 14px; }

    .event-intel-wrap {
        margin-bottom: 44px;
        display: grid;
        grid-template-columns: minmax(300px, 420px) 1fr;
        gap: 22px;
    }
    .event-poster-card,
    .event-details-card {
        background: var(--premium-card);
        border: 1px solid var(--premium-border);
        border-radius: 26px;
        box-shadow: var(--soft-shadow);
        overflow: hidden;
    }
    .event-poster {
        position: relative;
        min-height: 240px;
        background: linear-gradient(145deg, #dbeafe 0%, #bfdbfe 42%, #e0f2fe 100%);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .event-poster::after {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.55), rgba(255,255,255,0));
        pointer-events: none;
    }
    .event-poster img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .event-poster-fallback {
        color: #475569;
        text-align: center;
        padding: 26px;
    }
    .event-poster-fallback i {
        font-size: 56px;
        margin-bottom: 12px;
        color: #3b82f6;
    }
    .event-poster-content {
        padding: 22px;
    }
    .event-readonly {
        font-size: 0.74rem;
        font-weight: 700;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: #1d4ed8;
        margin-bottom: 8px;
        display: inline-flex;
        gap: 6px;
        align-items: center;
    }
    .event-name-heading {
        margin: 0;
        font-size: 1.5rem;
        line-height: 1.28;
        color: var(--premium-accent);
        font-weight: 800;
    }
    .event-sub-meta {
        margin-top: 9px;
        color: var(--premium-text-muted);
        font-weight: 600;
    }
    .event-chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 15px;
    }
    .event-chip {
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 0.76rem;
        font-weight: 700;
        border: 1px solid transparent;
    }
    .event-chip.registration-open {
        color: #166534;
        background: #dcfce7;
        border-color: #bbf7d0;
    }
    .event-chip.registration-closed {
        color: #991b1b;
        background: #fee2e2;
        border-color: #fecaca;
    }
    .event-chip.visibility-active {
        color: #0f2744;
        background: #dbeafe;
        border-color: #bfdbfe;
    }
    .event-chip.visibility-inactive {
        color: #78350f;
        background: #fef3c7;
        border-color: #fde68a;
    }
    .event-chip.upcoming {
        color: #3730a3;
        background: #e0e7ff;
        border-color: #c7d2fe;
    }
    .event-chip.ongoing {
        color: #0f766e;
        background: #ccfbf1;
        border-color: #99f6e4;
    }
    .event-chip.ended {
        color: #9f1239;
        background: #ffe4e6;
        border-color: #fecdd3;
    }
    .event-chip.neutral {
        color: #334155;
        background: #e2e8f0;
        border-color: #cbd5e1;
    }
    .event-progress-wrap {
        margin-top: 16px;
    }
    .event-progress-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
        color: #475569;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .event-progress-track {
        height: 10px;
        border-radius: 999px;
        background: #dbeafe;
        overflow: hidden;
    }
    .event-progress-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #1d4ed8, #2563eb, #0ea5e9);
        transition: width .5s ease;
    }
    .event-details-card {
        padding: 22px;
    }
    .event-detail-title {
        margin: 0 0 14px;
        color: var(--premium-accent);
        font-size: 1.2rem;
        font-weight: 800;
    }
    .event-info-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }
    .event-info-item {
        border: 1px solid #dbeafe;
        border-radius: 14px;
        padding: 12px;
        background: #f8fbff;
    }
    .event-info-label {
        display: block;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #64748b;
        margin-bottom: 7px;
        font-weight: 700;
    }
    .event-info-value {
        color: #0f2744;
        font-size: 0.96rem;
        font-weight: 700;
        line-height: 1.4;
        word-break: break-word;
    }
    .event-description-box {
        margin-top: 12px;
        border: 1px solid #dbeafe;
        border-radius: 14px;
        background: #f8fbff;
        padding: 13px;
    }
    .event-description-text {
        margin: 0;
        color: #334155;
        line-height: 1.65;
        font-size: 0.95rem;
    }

    @media (max-width: 992px) {
        .main { padding: 20px 15px !important; }
        .premium-card:hover,
        .premium-card:focus-within {
            transform: translateY(-4px) scale(1.006);
            box-shadow: 0 14px 28px rgba(30, 64, 175, 0.16), 0 0 0 2px rgba(76, 138, 255, 0.18);
        }
        .premium-card.d-2:hover,
        .premium-card.d-2:focus-within {
            transform: translateY(-5px) scale(1.01);
            box-shadow: 0 16px 30px rgba(30, 64, 175, 0.18), 0 0 0 2px rgba(76, 138, 255, 0.2);
        }
        .hero-title { font-size: 2rem; }
        .match-body { flex-direction: column; gap: 34px; padding: 36px 20px; }
        .match-footer { grid-template-columns: 1fr; }
        .event-intel-wrap { grid-template-columns: 1fr; }
        .event-info-grid { grid-template-columns: 1fr; }
        .event-poster { min-height: 200px; }
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes cardPop {
        0% { opacity: 0; transform: translateY(22px) scale(0.96); }
        65% { opacity: 1; transform: translateY(-4px) scale(1.02); }
        100% { opacity: 1; transform: translateY(0) scale(1); }
    }

    .reveal {
        animation: slideUp 0.6s cubic-bezier(0.23, 1, 0.32, 1) forwards;
        opacity: 0;
    }

    .premium-card.reveal {
        animation: cardPop 0.68s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        opacity: 0;
    }

    .d-1 { animation-delay: 0.1s; }
    .d-2 { animation-delay: 0.2s; }
    .d-3 { animation-delay: 0.3s; }
    .d-4 { animation-delay: 0.4s; }
    .d-5 { animation-delay: 0.5s; }
    .d-6 { animation-delay: 0.6s; }
    .d-7 { animation-delay: 0.7s; }
    .d-8 { animation-delay: 0.8s; }
</style>

<div class="dashboard-container">
    <div class="dashboard-hero reveal">
        <span class="hero-label">Operator Panel</span>
        <h1 class="hero-title">Pusat Operasional Event</h1>
        <p class="hero-description">
            Kelola data challenge dan berita untuk event yang ditugaskan. Semua data di area operator difokuskan ke event ini.
        </p>
    </div>

    <div class="premium-stats-grid">
        <div class="premium-card reveal d-1">
            <div class="card-top">
                <div class="card-icon-box"><i class="fas fa-calendar-check"></i></div>
            </div>
            <div>
                <div class="card-value" style="font-size:1.35rem;">
                    <?php echo htmlspecialchars($event_name !== '' ? $event_name : 'Event belum ditetapkan'); ?>
                </div>
                <div class="card-label">Event Aktif Operator</div>
            </div>
        </div>

        <div class="premium-card reveal d-2">
            <div class="card-top">
                <div class="card-icon-box"><i class="fas fa-futbol"></i></div>
            </div>
            <div>
                <div class="card-value" data-count="<?php echo (int) $challenge_total; ?>"><?php echo (int) $challenge_total; ?></div>
                <div class="card-label">Total Challenge Event</div>
            </div>
        </div>

        <div class="premium-card reveal d-3">
            <div class="card-top">
                <div class="card-icon-box" style="background:#ecfdf5;color:#059669;"><i class="fas fa-hourglass-half"></i></div>
            </div>
            <div>
                <div class="card-value" data-count="<?php echo (int) $challenge_upcoming; ?>"><?php echo (int) $challenge_upcoming; ?></div>
                <div class="card-label">Challenge Mendatang</div>
            </div>
        </div>

        <div class="premium-card reveal d-4">
            <div class="card-top">
                <div class="card-icon-box" style="background:#fff7ed;color:#c2410c;"><i class="fas fa-newspaper"></i></div>
            </div>
            <div>
                <div class="card-value" data-count="<?php echo (int) $berita_total; ?>"><?php echo (int) $berita_total; ?></div>
                <div class="card-label">Total Berita</div>
            </div>
        </div>
    </div>

    <div class="section-header reveal d-5">
        <h2 class="section-title">Profil Event</h2>
        <div class="section-line"></div>
    </div>

    <?php if ($event_id <= 0): ?>
        <div class="empty-state-light" style="margin-bottom: 44px;">
            <i class="fas fa-unlink"></i>
            <h3 style="font-weight:800;color:#0f2744;margin-bottom:6px;">Belum ada detail event</h3>
            <p style="color:#5f728a;">Akun operator belum memiliki event aktif untuk ditampilkan detailnya.</p>
        </div>
    <?php else: ?>
        <div class="event-intel-wrap">
            <article class="event-poster-card reveal d-6">
                <div class="event-poster">
                    <?php if ($event_image_path !== ''): ?>
                        <img src="<?php echo htmlspecialchars($event_image_path); ?>" alt="<?php echo htmlspecialchars($event_name !== '' ? $event_name : 'Event'); ?>">
                    <?php else: ?>
                        <div class="event-poster-fallback">
                            <i class="fas fa-flag-checkered"></i>
                            <div>Poster event belum ditambahkan</div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="event-poster-content">
                    <span class="event-readonly"><i class="fas fa-lock"></i> Informasi Hanya-Baca</span>
                    <h3 class="event-name-heading"><?php echo htmlspecialchars($event_name !== '' ? $event_name : 'Event Operator'); ?></h3>
                    <div class="event-sub-meta">Kategori: <?php echo htmlspecialchars($event_category !== '' ? $event_category : '-'); ?></div>

                    <div class="event-chip-list">
                        <span class="event-chip <?php echo htmlspecialchars($event_registration_class); ?>"><?php echo htmlspecialchars($event_registration_label); ?></span>
                        <span class="event-chip <?php echo htmlspecialchars($event_visibility_class); ?>"><?php echo htmlspecialchars($event_visibility_label); ?></span>
                        <span class="event-chip <?php echo htmlspecialchars($event_runtime_class); ?>"><?php echo htmlspecialchars($event_runtime_label); ?></span>
                    </div>

                    <div class="event-progress-wrap">
                        <div class="event-progress-head">
                            <span>Progres Periode</span>
                            <span><?php echo (int)$event_progress_percent; ?>%</span>
                        </div>
                        <div class="event-progress-track">
                            <div class="event-progress-fill" style="width: <?php echo (int)$event_progress_percent; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </article>

            <article class="event-details-card reveal d-7">
                <h3 class="event-detail-title">Dossier Event Operator</h3>
                <div class="event-info-grid">
                    <div class="event-info-item">
                        <span class="event-info-label">Periode</span>
                        <span class="event-info-value"><?php echo htmlspecialchars($event_period_label); ?></span>
                    </div>
                    <div class="event-info-item">
                        <span class="event-info-label">Durasi</span>
                        <span class="event-info-value"><?php echo htmlspecialchars($event_duration_label); ?></span>
                    </div>
                    <div class="event-info-item">
                        <span class="event-info-label">Countdown</span>
                        <span class="event-info-value"><?php echo htmlspecialchars($event_countdown_label); ?></span>
                    </div>
                    <div class="event-info-item">
                        <span class="event-info-label">Slug</span>
                        <span class="event-info-value"><?php echo htmlspecialchars($event_slug_display); ?></span>
                    </div>
                    <div class="event-info-item">
                        <span class="event-info-label">Lokasi</span>
                        <span class="event-info-value"><?php echo htmlspecialchars($event_location_display); ?></span>
                    </div>
                    <div class="event-info-item">
                        <span class="event-info-label">Kontak</span>
                        <span class="event-info-value"><?php echo htmlspecialchars($event_contact_display); ?></span>
                    </div>
                    <div class="event-info-item">
                        <span class="event-info-label">Tanggal Mulai</span>
                        <span class="event-info-value"><?php echo htmlspecialchars($event_start_display); ?></span>
                    </div>
                    <div class="event-info-item">
                        <span class="event-info-label">Tanggal Selesai</span>
                        <span class="event-info-value"><?php echo htmlspecialchars($event_end_display); ?></span>
                    </div>
                </div>

                <div class="event-description-box">
                    <span class="event-info-label">Deskripsi Event</span>
                    <p class="event-description-text"><?php echo nl2br(htmlspecialchars($event_description_display)); ?></p>
                </div>
            </article>
        </div>
    <?php endif; ?>

    <div class="section-header match-spotlight reveal d-8">
        <h2 class="section-title">Highlight Pertandingan Hari Ini</h2>
        <div class="section-line"></div>
    </div>

    <?php if ($event_id <= 0): ?>
        <div class="empty-state-light">
            <i class="fas fa-unlink"></i>
            <h3 style="font-weight:800;color:#0f2744;margin-bottom:6px;">Akun belum terhubung ke event</h3>
            <p style="color:#5f728a;">Pilih event di manajemen akun operator terlebih dahulu.</p>
        </div>
    <?php elseif (!empty($today_matches)): ?>
        <?php foreach ($today_matches as $idx => $today_match): ?>
            <?php $match_date = new DateTime($today_match['challenge_date']); ?>
            <div class="editorial-match-card today-match reveal" style="animation-delay: <?php echo number_format(0.55 + ($idx * 0.08), 2, '.', ''); ?>s;">
                <div class="match-body">
                    <div class="team-block">
                        <div class="team-logo-frame">
                            <?php if (!empty($today_match['team1_logo']) && file_exists(__DIR__ . '/../images/teams/' . $today_match['team1_logo'])): ?>
                                <img src="../images/teams/<?php echo htmlspecialchars($today_match['team1_logo']); ?>" alt="Team 1">
                            <?php else: ?>
                                <i class="fas fa-shield-alt" style="font-size:2rem;color:#cbd5e1;"></i>
                            <?php endif; ?>
                        </div>
                        <h4 class="team-name-small"><?php echo htmlspecialchars($today_match['team1_name'] ?? '-'); ?></h4>
                    </div>

                    <div class="vs-capsule">VS</div>

                    <div class="team-block">
                        <div class="team-logo-frame">
                            <?php if (!empty($today_match['team2_logo']) && file_exists(__DIR__ . '/../images/teams/' . $today_match['team2_logo'])): ?>
                                <img src="../images/teams/<?php echo htmlspecialchars($today_match['team2_logo']); ?>" alt="Team 2">
                            <?php else: ?>
                                <i class="fas fa-shield-alt" style="font-size:2rem;color:#cbd5e1;"></i>
                            <?php endif; ?>
                        </div>
                        <h4 class="team-name-small"><?php echo htmlspecialchars($today_match['team2_name'] ?? '-'); ?></h4>
                    </div>
                </div>

                <div class="match-footer">
                    <div class="info-group">
                        <div class="info-label">Tanggal Kickoff</div>
                        <div class="info-data"><?php echo $match_date->format('d M Y'); ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Waktu</div>
                        <div class="info-data"><?php echo $match_date->format('H:i'); ?> WIB</div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Venue</div>
                        <div class="info-data"><?php echo htmlspecialchars($today_match['venue_name'] ?? 'Akan diumumkan'); ?></div>
                    </div>
                </div>

                <div style="padding:12px;text-align:center;background:#0f2744;color:white;font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;">
                    <?php echo htmlspecialchars($today_match['sport_type'] ?: ($event_category !== '' ? $event_category : 'Challenge')); ?>
                    <span class="status-pill"><?php echo htmlspecialchars((string)($today_match['status'] ?? '-')); ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state-light">
            <i class="far fa-calendar-times"></i>
            <h3 style="font-weight:800;color:#0f2744;margin-bottom:6px;">Belum ada challenge hari ini</h3>
            <p style="color:#5f728a;">Highlight menampilkan semua challenge event ini yang jadwalnya tepat hari ini.</p>
        </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const counters = document.querySelectorAll('.card-value[data-count]');
    counters.forEach((el) => {
        const target = parseInt(el.getAttribute('data-count'), 10);
        if (Number.isNaN(target)) return;

        const duration = 850;
        const start = performance.now();

        function tick(now) {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(target * eased).toString();
            if (progress < 1) requestAnimationFrame(tick);
        }

        el.textContent = '0';
        requestAnimationFrame(tick);
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
