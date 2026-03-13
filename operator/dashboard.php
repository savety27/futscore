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
$next_match = null;

// Chart variables
$chart_labels = [];
$chart_values = [];
$chart_tooltips = [];

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

// Data untuk sidebar operator (mengikuti halaman challenge)
$operator_event_name = $event_name !== '' ? $event_name : 'Event Operator';
$operator_event_image = $event_image;

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

        // Get Next Match Spotlight for this event
        $stmt = $conn->prepare("
            SELECT c.*, 
                   t1.name as team1_name, t1.logo as team1_logo,
                   t2.name as team2_name, t2.logo as team2_logo,
                   v.name as venue_name
            FROM challenges c
            JOIN teams t1 ON c.challenger_id = t1.id
            JOIN teams t2 ON c.opponent_id = t2.id
            LEFT JOIN venues v ON c.venue_id = v.id
            WHERE c.event_id = ?
              AND c.status = 'accepted' 
              AND c.challenge_date >= NOW()
            ORDER BY c.challenge_date ASC
            LIMIT 1
        ");
        $stmt->execute([$event_id]);
        $next_match = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get last 30 days completed matches for event velocity chart
        $stmt = $conn->prepare("
            SELECT 
                c.id,
                c.challenge_date,
                t1.name AS challenger_name,
                t2.name AS opponent_name,
                c.challenger_score,
                c.opponent_score
            FROM challenges c
            JOIN teams t1 ON c.challenger_id = t1.id
            JOIN teams t2 ON c.opponent_id = t2.id
            WHERE c.status = 'completed'
              AND c.event_id = ?
              AND c.challenge_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
              AND c.challenge_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            ORDER BY c.challenge_date ASC
        ");
        $stmt->execute([$event_id]);
        $chart_matches_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cumulative = 0;
        foreach ($chart_matches_raw as $cm) {
            $match_dt = new DateTime($cm['challenge_date']);
            $label = $match_dt->format('d/m');
            $cumulative++; // Incremental count of completed matches
            
            $chart_labels[]   = $label;
            $chart_values[]   = $cumulative;
            $chart_tooltips[] = [
                'date'   => $match_dt->format('d M Y'),
                'opp'    => $cm['challenger_name'] . ' vs ' . $cm['opponent_name'],
                'result' => 'Selesai',
                'score'  => ($cm['challenger_score'] ?? '0') . ' - ' . ($cm['opponent_score'] ?? '0'),
            ];
        }

    } catch (PDOException $e) {
        // Keep dashboard rendering
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

require_once __DIR__ . '/includes/header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@200;800&family=Plus+Jakarta+Sans:wght@300;800&display=swap');

    :root {
        --heritage-bg: #f8f7f4;
        --heritage-card: #ffffff;
        --heritage-border: #e5e1da;
        --heritage-text: #1e1b4b;
        --heritage-text-muted: #6b7280;
        --heritage-accent: #0f2744;
        --heritage-gold: #b45309;
        --heritage-crimson: #991b1b;
        --font-display: 'Bricolage Grotesque', sans-serif;
        --font-body: 'Plus Jakarta Sans', sans-serif;
        --soft-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
    }

    .main {
        background: var(--heritage-bg) !important;
        background-image: radial-gradient(#e5e1da 0.5px, transparent 0.5px) !important;
        background-size: 24px 24px !important;
        color: var(--heritage-text);
        font-family: var(--font-body);
        padding: 40px !important;
    }

    .dashboard-container { max-width: 1400px; margin: 0 auto; }

    .dashboard-hero {
        margin-bottom: 48px;
        border-bottom: 2px solid var(--heritage-text);
        padding-bottom: 32px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 20px;
    }

    .hero-content { max-width: 800px; }
    .hero-label {
        color: var(--heritage-gold);
        font-family: var(--font-display);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        font-size: 0.9rem;
        margin-bottom: 12px;
        display: block;
    }
    .hero-title {
        font-family: var(--font-display);
        font-size: 3.5rem;
        font-weight: 800;
        color: var(--heritage-text);
        margin: 0 0 16px 0;
        line-height: 1;
        letter-spacing: -0.04em;
    }
    .hero-description { color: var(--heritage-text-muted); font-size: 1.15rem; line-height: 1.6; margin: 0; }

    .stats-grid-wrapper {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 32px;
        margin-bottom: 48px;
    }

    .event-identity-card {
        grid-row: span 2;
        background: var(--heritage-accent);
        color: white;
        border-radius: 32px;
        padding: 40px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .event-identity-card::after {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        background: linear-gradient(45deg, rgba(255,255,255,0.05) 0%, transparent 100%);
    }

    .event-poster-main {
        width: 150px;
        height: 150px;
        background: white;
        border-radius: 24px;
        padding: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 28px;
        box-shadow: 0 12px 24px rgba(0,0,0,0.2);
        position: relative;
        z-index: 1;
    }
    .event-poster-main img { width: 100%; height: 100%; object-fit: contain; }
    .event-name-display { font-family: var(--font-display); font-size: 1.8rem; font-weight: 700; line-height: 1.1; margin-bottom: 12px; position: relative; z-index: 1; }
    .event-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,255,255,0.1);
        padding: 6px 14px;
        border-radius: 100px;
        font-size: 0.85rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
        position: relative;
        z-index: 1;
    }

    .stats-main-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }

    .heritage-card {
        background: var(--heritage-card);
        border: 1px solid var(--heritage-border);
        border-radius: 28px;
        padding: 24px;
        box-shadow: var(--soft-shadow);
        transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }
    .heritage-card:hover {
        transform: translateY(-8px);
        border-color: var(--heritage-accent);
        box-shadow: 0 16px 32px rgba(0,0,0,0.08);
    }
    .heritage-card-link {
        display: block;
        text-decoration: none;
        color: inherit;
    }
    .heritage-card-link:focus-visible {
        outline: 3px solid rgba(15, 39, 68, 0.22);
        outline-offset: 4px;
        border-radius: 28px;
    }
    .card-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 12px; }
    .card-label { font-family: var(--font-display); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--heritage-text-muted); }
    .card-value { font-family: var(--font-display); font-size: 2.25rem; font-weight: 800; line-height: 1; color: var(--heritage-text); }
    .card-icon { font-size: 1.2rem; color: var(--heritage-accent); opacity: 0.4; }

    .section-header { display: flex; align-items: center; gap: 24px; margin-bottom: 32px; }
    .section-title { font-family: var(--font-display); font-size: 2rem; font-weight: 800; color: var(--heritage-text); margin: 0; }
    .section-line { height: 2px; background: var(--heritage-border); flex: 1; }

    @media (max-width: 768px) {
        .section-header { flex-direction: column; text-align: center; gap: 12px; }
        .section-line { width: 60px; height: 3px; background: var(--heritage-gold); flex: none; margin: 0 auto; }
        .section-title { font-size: 1.75rem; }
    }

    .match-hero-card { 
        background: white; 
        border: 1px solid var(--heritage-border); 
        border-radius: 40px; 
        overflow: hidden; 
        box-shadow: 0 30px 60px rgba(0,0,0,0.05); 
        margin-bottom: 48px; 
    }
    .match-hero-link {
        display: block;
        text-decoration: none;
        color: inherit;
        border-radius: 40px;
    }
    .match-hero-link:focus-visible {
        outline: 3px solid rgba(180, 83, 9, 0.35);
        outline-offset: 4px;
    }
    .match-hero-link .match-hero-card {
        cursor: pointer;
    }
    .match-hero-content { 
        padding: 64px 48px; 
        display: grid; 
        grid-template-columns: 1fr auto 1fr; 
        align-items: center; 
        text-align: center; 
        background: radial-gradient(circle at center, #ffffff 0%, #fdfcfb 100%); 
        position: relative; 
    }
    .team-focus { 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        gap: 24px; 
    }
    .team-logo-large { 
        width: 160px; 
        height: 160px; 
        background: white; 
        border-radius: 40px; 
        padding: 24px; 
        box-shadow: 0 20px 40px rgba(0,0,0,0.04); 
        border: 1px solid var(--heritage-border); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        transition: transform 0.5s ease;
    }
    .match-hero-card:hover .team-logo-large {
        transform: scale(1.05) rotate(2deg);
    }
    .team-name-large { 
        font-family: var(--font-display); 
        font-size: 1.75rem; 
        font-weight: 800; 
        margin: 0;
        color: var(--heritage-text); 
    }
    .vs-emblem { 
        padding: 20px; 
        position: relative; 
    }
    .vs-text { 
        font-family: var(--font-display); 
        font-size: 1.5rem; 
        font-weight: 900; 
        color: var(--heritage-gold); 
        background: var(--heritage-bg); 
        width: 70px; 
        height: 70px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        border-radius: 50%; 
        border: 2px solid var(--heritage-gold); 
        z-index: 2; 
        position: relative; 
    }
    .vs-line { 
        position: absolute; 
        top: 50%; 
        left: -100px; 
        right: -100px; 
        height: 2px; 
        background: linear-gradient(90deg, transparent, var(--heritage-border), transparent); 
        z-index: 1; 
    }
    .match-hero-footer { 
        background: var(--heritage-accent); 
        padding: 32px 48px; 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 32px; 
    }
    .match-info-box { color: white; }
    .match-info-label { 
        font-size: 0.75rem; 
        font-weight: 700; 
        text-transform: uppercase; 
        letter-spacing: 0.1em; 
        margin-bottom: 8px; 
        color: rgba(255,255,255,0.5); 
    }
    .match-info-value { 
        font-size: 1.15rem; 
        font-weight: 600; 
    }

    .today-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }
    .schedule-card { background: white; border: 1px solid var(--heritage-border); border-radius: 24px; padding: 28px; transition: all 0.3s ease; }
    .schedule-card:hover { border-color: var(--heritage-gold); transform: translateX(8px); }
    .schedule-time { font-family: var(--font-display); font-size: 1.25rem; font-weight: 800; color: var(--heritage-gold); margin-bottom: 12px; display: block; }
    .schedule-teams { font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; color: var(--heritage-text); }
    .schedule-meta { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; color: var(--heritage-text-muted); }

    .dossier-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 32px; }
    .dossier-item { background: white; border: 1px solid var(--heritage-border); border-radius: 20px; padding: 20px; }
    .dossier-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: var(--heritage-text-muted); margin-bottom: 8px; display: block; }
    .dossier-value { font-size: 0.95rem; font-weight: 700; color: var(--heritage-text); overflow-wrap: break-word; }

    .empty-state { text-align: center; padding: 60px 40px; background: white; border: 2px dashed var(--heritage-border); border-radius: 32px; }
    .empty-state i { font-size: 2.5rem; color: var(--heritage-border); margin-bottom: 20px; }

    @keyframes revealUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .reveal { animation: revealUp 0.8s cubic-bezier(0.23, 1, 0.32, 1) forwards; opacity: 0; }
    .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; } .d-3 { animation-delay: 0.3s; }

    @media (max-width: 1024px) {
        .stats-grid-wrapper { grid-template-columns: 1fr; }
        .match-hero-content { 
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); 
            gap: 32px; 
            padding: 48px 24px; 
        }
        .vs-line { display: none; }
        .match-hero-footer { grid-template-columns: 1fr; gap: 24px; padding: 32px 24px; }
        .dossier-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 768px) {
        .main { padding: 20px 16px !important; }
        .hero-title { font-size: 2.25rem; }
        .hero-description { font-size: 1rem; }
        .stats-main-grid { gap: 16px; }
        .heritage-card { border-radius: 20px; padding: 16px; }
        .card-value { font-size: 1.75rem; }
        .match-hero-content { 
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            gap: 16px;
            padding: 32px 16px; 
        }
        .team-focus { gap: 12px; }
        .team-logo-large { width: 100px; height: 100px; padding: 16px; border-radius: 24px; }
        .team-name-large { font-size: 1.1rem; }
        .vs-text { width: 45px; height: 40px; font-size: 1.1rem; margin: 0 auto; }
        .match-hero-footer { 
            padding: 20px 22px;
            gap: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .match-hero-footer .match-info-box { text-align: center !important; width: 100%; }
        .match-info-label { font-size: 0.65rem; letter-spacing: 0.08em; }
        .match-info-value { font-size: 0.95rem; }
    }

    @media (max-width: 600px) {
        .dossier-grid { grid-template-columns: 1fr; gap: 16px; }
        .dossier-item { padding: 20px; border-radius: 20px; text-align: center; }
        .dossier-label { font-size: 0.75rem; }
        .dossier-value { font-size: 1.1rem; }
    }

    @media (max-width: 480px) {
        .hero-title { font-size: 1.8rem; }
        .event-identity-card { padding: 32px 20px; }
        .event-poster-main { width: 100px; height: 100px; }
        .event-name-display { font-size: 1.45rem; }
        .stats-main-grid { grid-template-columns: 1fr; }
        .heritage-card[style*="grid-column: span 2"] { grid-column: 1 / -1 !important; }
        .match-hero-content { 
            padding: 28px 12px; 
            gap: 12px; 
            grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        }
        .team-logo-large { width: 72px; height: 72px; padding: 10px; border-radius: 18px; }
        .team-name-large { font-size: 0.9rem; }
        .vs-text { width: 34px; height: 34px; font-size: 0.85rem; }
        .match-hero-footer { 
            padding: 16px 18px;
            gap: 12px;
        }
        .match-info-label { font-size: 0.6rem; }
        .match-info-value { font-size: 0.85rem; }
    }
</style>

<div class="dashboard-container">
    <header class="dashboard-hero reveal">
        <div class="hero-content">
            <span class="hero-label">Operator Terminal</span>
            <h1 class="hero-title">Operational Hub</h1>
            <p class="hero-description">Pusat komando integrasi data challenge dan manajemen naratif untuk event yang sedang berlangsung.</p>
        </div>
    </header>

    <div class="stats-grid-wrapper">
        <div class="event-identity-card reveal d-1">
            <div class="event-poster-main">
                <?php if ($event_image_path !== ''): ?>
                    <img src="<?php echo htmlspecialchars($event_image_path); ?>" alt="Event">
                <?php else: ?>
                    <i class="fas fa-flag-checkered" style="font-size: 3rem; color: var(--heritage-accent);"></i>
                <?php endif; ?>
            </div>
            <h2 class="event-name-display"><?php echo htmlspecialchars($event_name ?: 'Event Belum Ditugaskan'); ?></h2>
            <div class="event-status-badge">
                <span style="width: 8px; height: 8px; background: <?php echo $event_is_active ? '#10b981' : '#ef4444'; ?>; border-radius: 50%;"></span>
                Status: <?php echo $event_is_active ? 'Event Aktif' : 'Nonaktif'; ?>
            </div>
        </div>

        <div class="stats-main-grid">
            <a class="heritage-card heritage-card-link reveal d-2" href="challenge.php#challengeTable" aria-label="Buka daftar challenge">
                <div class="card-meta">
                    <span class="card-label">Total Challenge</span>
                    <i class="fas fa-futbol card-icon"></i>
                </div>
                <div class="card-value" data-count="<?php echo $challenge_total; ?>"><?php echo $challenge_total; ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Data Agregat Challenge</div>
            </a>
            <a class="heritage-card heritage-card-link reveal d-3" href="challenge.php?view=upcoming#challengeTable" aria-label="Buka challenge mendatang">
                <div class="card-meta">
                    <span class="card-label">Mendatang</span>
                    <i class="fas fa-hourglass-start card-icon"></i>
                </div>
                <div class="card-value" data-count="<?php echo $challenge_upcoming; ?>"><?php echo $challenge_upcoming; ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Challenge Belum Selesai</div>
            </a>
            <a class="heritage-card heritage-card-link reveal d-2" href="berita.php" aria-label="Buka arsip berita">
                <div class="card-meta">
                    <span class="card-label">Arsip Berita</span>
                    <i class="fas fa-newspaper card-icon"></i>
                </div>
                <div class="card-value" data-count="<?php echo $berita_total; ?>"><?php echo $berita_total; ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Publikasi Terverifikasi</div>
            </a>
            <div class="heritage-card reveal d-3" style="border-bottom: 3px solid var(--heritage-gold);">
                <div class="card-meta">
                    <span class="card-label">Progres Event</span>
                    <i class="fas fa-tasks card-icon" style="color: var(--heritage-gold); opacity: 1;"></i>
                </div>
                <div class="card-value" style="color: var(--heritage-gold);"><?php echo $event_progress_percent; ?>%</div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;"><?php echo $event_runtime_label; ?></div>
            </div>
            
            <!-- Event Operational Velocity (Line Chart) -->
            <div class="heritage-card reveal d-4" style="grid-column: span 2; background: var(--heritage-accent); color: white; padding: 28px 28px 20px; position: relative;">
                <div class="card-meta" style="margin-bottom: 12px;">
                    <div style="display:flex; flex-direction:column; gap:2px;">
                        <span class="card-label" style="color: rgba(255,255,255,0.55);">Operational Velocity</span>
                        <span style="font-family: var(--font-display); font-size:1.1rem; font-weight:700; color:white; letter-spacing:-0.02em;">Cumulative Challenge Completion</span>
                    </div>
                    <div style="display:flex; gap:16px; align-items:center;">
                        <div style="text-align:right;">
                            <div style="font-size:0.7rem; color:rgba(255,255,255,0.45); text-transform:uppercase; letter-spacing:.06em;">Finished</div>
                            <div style="font-family:var(--font-display); font-size:1.4rem; font-weight:800; color:#f59e0b;"><?php echo (int)$challenge_completed; ?></div>
                        </div>
                        <i class="fas fa-chart-line" style="color: rgba(255,255,255,0.3); font-size:1.1rem;"></i>
                    </div>
                </div>
                <?php if (!empty($chart_values)): ?>
                <div style="position:relative; height:140px; margin-top:8px;">
                    <canvas id="velocityChart"></canvas>
                </div>
                <div id="velocityChartTooltip" style="
                    position:absolute; pointer-events:none; display:none;
                    background:rgba(255,255,255,0.97); color:#1e1b4b;
                    border-radius:12px; padding:10px 14px;
                    font-family:var(--font-body); font-size:0.8rem; font-weight:600;
                    box-shadow:0 8px 24px rgba(0,0,0,0.18);
                    white-space:nowrap; z-index:50; min-width:160px;
                    border-left: 3px solid #b45309;
                "></div>
                <?php else: ?>
                <div style="height:120px; display:flex; align-items:center; justify-content:center; opacity:0.4; font-size:0.9rem;">Belum ada data challenge yang selesai dalam 30 hari terakhir</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <section class="reveal d-2" style="margin-top: 64px;">
        <div class="section-header">
            <h2 class="section-title">Match Spotlight</h2>
            <div class="section-line"></div>
        </div>

        <?php if ($next_match): 
            $match_date = new DateTime($next_match['challenge_date']);
        ?>
            <a class="match-hero-link" href="challenge_view.php?id=<?php echo (int)($next_match['id'] ?? 0); ?>" aria-label="Buka detail challenge">
            <div class="match-hero-card">
                <div class="match-hero-content">
                    <div class="team-focus">
                        <div class="team-logo-large">
                            <?php if (!empty($next_match['team1_logo']) && file_exists(__DIR__ . '/../images/teams/' . $next_match['team1_logo'])): ?>
                                <img src="../images/teams/<?php echo htmlspecialchars($next_match['team1_logo']); ?>" alt="Team 1" style="width: 100%; height: 100%; object-fit: contain;">
                            <?php else: ?>
                                <i class="fas fa-shield-alt" style="font-size: 3.5rem; color: var(--heritage-border);"></i>
                            <?php endif; ?>
                        </div>
                        <h3 class="team-name-large"><?php echo htmlspecialchars($next_match['team1_name']); ?></h3>
                    </div>

                    <div class="vs-emblem">
                        <div class="vs-line"></div>
                        <div class="vs-text">VS</div>
                    </div>

                    <div class="team-focus">
                        <div class="team-logo-large">
                            <?php if (!empty($next_match['team2_logo']) && file_exists(__DIR__ . '/../images/teams/' . $next_match['team2_logo'])): ?>
                                <img src="../images/teams/<?php echo htmlspecialchars($next_match['team2_logo']); ?>" alt="Team 2" style="width: 100%; height: 100%; object-fit: contain;">
                            <?php else: ?>
                                <i class="fas fa-shield-alt" style="font-size: 3.5rem; color: var(--heritage-border);"></i>
                            <?php endif; ?>
                        </div>
                        <h3 class="team-name-large"><?php echo htmlspecialchars($next_match['team2_name']); ?></h3>
                    </div>
                </div>

                <div class="match-hero-footer">
                    <div class="match-info-box">
                        <div class="match-info-label"><i class="far fa-calendar-alt"></i> Tanggal</div>
                        <div class="match-info-value"><?php echo $match_date->format('l, d M Y'); ?></div>
                    </div>
                    <div class="match-info-box">
                        <div class="match-info-label"><i class="far fa-clock"></i> Kickoff</div>
                        <div class="match-info-value"><?php echo $match_date->format('H:i'); ?> WIB</div>
                    </div>
                    <div class="match-info-box">
                        <div class="match-info-label"><i class="fas fa-map-marker-alt"></i> Stadion</div>
                        <div class="match-info-value"><?php echo htmlspecialchars($next_match['venue_name'] ?: 'TBD'); ?></div>
                    </div>
                </div>
            </div>
            </a>
        <?php else: ?>
            <div class="empty-state">
                <i class="far fa-calendar-times"></i>
                <h3>Tidak Ada Challenge Mendatang</h3>
                <p style="color: var(--heritage-text-muted);">Seluruh challenge dalam event ini telah selesai atau belum dijadwalkan kembali.</p>
            </div>
        <?php endif; ?>
    </section>

    <div class="section-header reveal d-3" style="margin-top: 64px;">
        <h2 class="section-title">Jadwal Hari Ini</h2>
        <div class="section-line"></div>
    </div>

    <?php if (!empty($today_matches)): ?>
        <div class="today-grid reveal d-3">
            <?php foreach ($today_matches as $today_match): ?>
                <?php $t_date = new DateTime($today_match['challenge_date']); ?>
                <div class="schedule-card">
                    <span class="schedule-time"><?php echo $t_date->format('H:i'); ?> WIB</span>
                    <div class="schedule-teams">
                        <?php echo htmlspecialchars($today_match['team1_name']); ?> 
                        <span style="color: var(--heritage-gold); font-size: 0.75rem; margin: 0 4px;">VS</span> 
                        <?php echo htmlspecialchars($today_match['team2_name']); ?>
                    </div>
                    <div class="schedule-meta">
                        <i class="fas fa-map-marker-alt" style="color: var(--heritage-gold);"></i>
                        <span><?php echo htmlspecialchars($today_match['venue_name'] ?: 'Venue TBD'); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state reveal d-3" style="padding: 40px;">
            <p style="margin: 0; color: var(--heritage-text-muted); font-weight: 500;">Tidak ada pertandingan yang dijadwalkan untuk hari ini.</p>
        </div>
    <?php endif; ?>

    <section class="reveal d-3" style="margin-top: 64px; margin-bottom: 60px;">
        <div class="section-header">
            <h2 class="section-title">Dossier Event</h2>
            <div class="section-line"></div>
        </div>
        
        <div class="dossier-grid">
            <div class="dossier-item">
                <span class="dossier-label">Lokasi Penyelenggaraan</span>
                <span class="dossier-value"><?php echo htmlspecialchars($event_location_display); ?></span>
            </div>
            <div class="dossier-item">
                <span class="dossier-label">Kontak Resmi</span>
                <span class="dossier-value"><?php echo htmlspecialchars($event_contact_display); ?></span>
            </div>
            <div class="dossier-item">
                <span class="dossier-label">Unique Slug</span>
                <span class="dossier-value"><?php echo htmlspecialchars($event_slug_display); ?></span>
            </div>
            <div class="dossier-item">
                <span class="dossier-label">Periode Mulai</span>
                <span class="dossier-value"><?php echo htmlspecialchars($event_start_display); ?></span>
            </div>
            <div class="dossier-item">
                <span class="dossier-label">Periode Berakhir</span>
                <span class="dossier-value"><?php echo htmlspecialchars($event_end_display); ?></span>
            </div>
            <div class="dossier-item">
                <span class="dossier-label">Hitung Mundur</span>
                <span class="dossier-value"><?php echo htmlspecialchars($event_countdown_label); ?></span>
            </div>
        </div>
        
        <div class="heritage-card" style="margin-top: 24px; min-height: auto;">
            <span class="card-label">Deskripsi Operasional</span>
            <p style="margin: 12px 0 0; line-height: 1.6; font-size: 0.95rem; color: var(--heritage-text);">
                <?php echo nl2br(htmlspecialchars($event_description_display)); ?>
            </p>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Counter animation ──────────────────────────────────────────────────
    const counters = document.querySelectorAll('.card-value[data-count]');
    counters.forEach((el) => {
        const target = parseInt(el.getAttribute('data-count'), 10);
        if (Number.isNaN(target)) return;
        const duration = 1200;
        const start = performance.now();
        function tick(now) {
            const progress = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 4);
            el.textContent = Math.round(target * eased).toString();
            if (progress < 1) requestAnimationFrame(tick);
        }
        el.textContent = '0';
        requestAnimationFrame(tick);
    });

    // ── Operational Velocity Chart (Line Chart) ───────────────────────────
    <?php if (!empty($chart_values)): ?>
    const chartLabels   = <?php echo json_encode($chart_labels); ?>;
    const chartValues   = <?php echo json_encode($chart_values); ?>;
    const chartTooltips = <?php echo json_encode($chart_tooltips); ?>;

    const ctx = document.getElementById('velocityChart');
    if (!ctx) return;

    const canvasCtx = ctx.getContext('2d');
    const grad = canvasCtx.createLinearGradient(0, 0, 0, 140);
    grad.addColorStop(0, 'rgba(245,158,11,0.25)'); // Amber Glow
    grad.addColorStop(1, 'rgba(245,158,11,0.00)');

    const velocityChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Cumulative Progress',
                data: chartValues,
                borderColor: '#f59e0b',
                borderWidth: 2.5,
                pointBackgroundColor: '#b45309',
                pointBorderColor: '#0f2744',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHitRadius: 14,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#f59e0b',
                pointHoverBorderWidth: 2.5,
                fill: true,
                backgroundColor: grad,
                tension: 0.4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 1000, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { enabled: false },
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255,255,255,0.06)', drawBorder: false },
                    ticks: {
                        color: 'rgba(255,255,255,0.4)',
                        font: { size: 10, family: "'Plus Jakarta Sans', sans-serif" },
                    },
                    border: { display: false },
                },
                y: {
                    display: false,
                    beginAtZero: true,
                }
            },
        }
    });

    const tooltipEl = document.getElementById('velocityChartTooltip');
    ctx.addEventListener('mousemove', function(e) {
        const points = velocityChart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false);
        if (points.length === 0) {
            tooltipEl.style.display = 'none';
            return;
        }
        const idx = points[0].index;
        const t   = chartTooltips[idx];

        tooltipEl.innerHTML = `
            <div style="font-size:0.7rem; color:#6b7280; margin-bottom:4px;">${t.date}</div>
            <div style="font-size:0.85rem; font-weight:700; color:#1e1b4b; margin-bottom:6px;">${t.opp}</div>
            <div style="display:flex; align-items:center; gap:8px; justify-content:space-between;">
                <span style="color:#b45309; font-weight:700;">⚙️ ${t.result}</span>
                <span style="background:#f3f4f6; border-radius:6px; padding:2px 8px; font-weight:800; color:#1e1b4b; font-size:0.8rem;">${t.score}</span>
            </div>
        `;

        const cardRect = tooltipEl.parentElement.getBoundingClientRect();
        let left = (e.clientX - cardRect.left) + 12;
        let top  = (e.clientY - cardRect.top)  - 20;
        
        tooltipEl.style.display = 'block';
        const tw = tooltipEl.offsetWidth;
        if (left + tw > cardRect.width - 10) left = left - tw - 24;
        if (top < 0) top = 0;
        tooltipEl.style.left = left + 'px';
        tooltipEl.style.top  = top + 'px';
    });

    ctx.addEventListener('mouseleave', function() {
        tooltipEl.style.display = 'none';
    });
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
