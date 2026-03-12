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
    .card-meta { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .card-label { font-family: var(--font-display); font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--heritage-text-muted); }
    .card-value { font-family: var(--font-display); font-size: 2.25rem; font-weight: 800; line-height: 1; color: var(--heritage-text); }
    .card-icon { font-size: 1.2rem; color: var(--heritage-accent); opacity: 0.4; }

    .section-header { display: flex; align-items: center; gap: 24px; margin-bottom: 32px; }
    .section-title { font-family: var(--font-display); font-size: 2rem; font-weight: 800; color: var(--heritage-text); margin: 0; }
    .section-line { height: 2px; background: var(--heritage-border); flex: 1; }

    .match-hero-card { background: white; border: 1px solid var(--heritage-border); border-radius: 40px; overflow: hidden; box-shadow: 0 30px 60px rgba(0,0,0,0.05); margin-bottom: 48px; }
    .match-hero-content { padding: 64px 48px; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; text-align: center; background: radial-gradient(circle at center, #ffffff 0%, #fdfcfb 100%); position: relative; }
    .team-focus { display: flex; flex-direction: column; align-items: center; gap: 24px; }
    .team-logo-large { width: 140px; height: 140px; background: white; border-radius: 40px; padding: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.04); border: 1px solid var(--heritage-border); display: flex; align-items: center; justify-content: center; }
    .team-name-large { font-family: var(--font-display); font-size: 1.5rem; font-weight: 800; color: var(--heritage-text); }
    .vs-emblem { padding: 20px; position: relative; }
    .vs-text { font-family: var(--font-display); font-size: 1.5rem; font-weight: 900; color: var(--heritage-gold); background: var(--heritage-bg); width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: 2px solid var(--heritage-gold); z-index: 2; position: relative; }
    .vs-line { position: absolute; top: 50%; left: -80px; right: -80px; height: 2px; background: linear-gradient(90deg, transparent, var(--heritage-border), transparent); z-index: 1; }
    .match-hero-footer { background: var(--heritage-accent); padding: 32px 48px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; }
    .match-info-box { color: white; }
    .match-info-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; color: rgba(255,255,255,0.5); }
    .match-info-value { font-size: 1.1rem; font-weight: 600; }

    .today-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }
    .schedule-card { background: white; border: 1px solid var(--heritage-border); border-radius: 24px; padding: 28px; transition: all 0.3s ease; }
    .schedule-card:hover { border-color: var(--heritage-gold); transform: translateX(8px); }
    .schedule-time { font-family: var(--font-display); font-size: 1.25rem; font-weight: 800; color: var(--heritage-gold); margin-bottom: 12px; display: block; }
    .schedule-teams { font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; color: var(--heritage-text); }
    .schedule-meta { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; color: var(--heritage-text-muted); }

    .dossier-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 32px; }
    .dossier-item { background: white; border: 1px solid var(--heritage-border); border-radius: 20px; padding: 20px; }
    .dossier-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; color: var(--heritage-text-muted); margin-bottom: 8px; display: block; }
    .dossier-value { font-size: 0.95rem; font-weight: 700; color: var(--heritage-text); }

    .empty-state { text-align: center; padding: 60px 40px; background: white; border: 2px dashed var(--heritage-border); border-radius: 32px; }
    .empty-state i { font-size: 2.5rem; color: var(--heritage-border); margin-bottom: 20px; }

    @keyframes revealUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .reveal { animation: revealUp 0.8s cubic-bezier(0.23, 1, 0.32, 1) forwards; opacity: 0; }
    .d-1 { animation-delay: 0.1s; } .d-2 { animation-delay: 0.2s; } .d-3 { animation-delay: 0.3s; }

    @media (max-width: 1024px) {
        .stats-grid-wrapper { grid-template-columns: 1fr; }
        .match-hero-content { grid-template-columns: 1fr; gap: 32px; padding: 48px 24px; }
        .vs-line { display: none; }
        .match-hero-footer { grid-template-columns: 1fr; }
        .dossier-grid { grid-template-columns: repeat(2, 1fr); }
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
            <div class="heritage-card reveal d-2">
                <div class="card-meta">
                    <span class="card-label">Total Challenge</span>
                    <i class="fas fa-futbol card-icon"></i>
                </div>
                <div class="card-value" data-count="<?php echo $challenge_total; ?>"><?php echo $challenge_total; ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Data Agregat Challenge</div>
            </div>
            <div class="heritage-card reveal d-3">
                <div class="card-meta">
                    <span class="card-label">Mendatang</span>
                    <i class="fas fa-hourglass-start card-icon"></i>
                </div>
                <div class="card-value" data-count="<?php echo $challenge_upcoming; ?>"><?php echo $challenge_upcoming; ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Challenge Belum Selesai</div>
            </div>
            <div class="heritage-card reveal d-2">
                <div class="card-meta">
                    <span class="card-label">Arsip Berita</span>
                    <i class="fas fa-newspaper card-icon"></i>
                </div>
                <div class="card-value" data-count="<?php echo $berita_total; ?>"><?php echo $berita_total; ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Publikasi Terverifikasi</div>
            </div>
            <div class="heritage-card reveal d-3" style="border-bottom: 3px solid var(--heritage-gold);">
                <div class="card-meta">
                    <span class="card-label">Progres Event</span>
                    <i class="fas fa-tasks card-icon" style="color: var(--heritage-gold); opacity: 1;"></i>
                </div>
                <div class="card-value" style="color: var(--heritage-gold);"><?php echo $event_progress_percent; ?>%</div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;"><?php echo $event_runtime_label; ?></div>
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
                        <div class="match-info-label">Kickoff Scheduled</div>
                        <div class="match-info-value"><?php echo $match_date->format('l, d M Y | H:i'); ?> WIB</div>
                    </div>
                    <div class="match-info-box">
                        <div class="match-info-label">Operational Venue</div>
                        <div class="match-info-value"><?php echo htmlspecialchars($next_match['venue_name'] ?: 'TBD'); ?></div>
                    </div>
                    <div class="match-info-box" style="text-align: right;">
                        <div class="match-info-label">Status</div>
                        <div class="match-info-value" style="color: var(--heritage-gold); text-transform: uppercase; letter-spacing: 1px;">
                            <?php echo htmlspecialchars($next_match['status']); ?>
                        </div>
                    </div>
                </div>
            </div>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
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
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
