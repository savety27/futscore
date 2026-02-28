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
$challenge_total = 0;
$challenge_upcoming = 0;
$challenge_completed = 0;
$berita_total = 0;
$next_match = null;

if ($operator_id > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT au.event_id, e.name AS event_name, e.category AS event_category, e.image AS event_image
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
            SELECT c.challenge_date, c.sport_type,
                   t1.name AS team1_name, t1.logo AS team1_logo,
                   t2.name AS team2_name, t2.logo AS team2_logo,
                   v.name AS venue_name
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
        $next_match = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        // Keep dashboard rendering even if any query fails.
    }
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
    }

    .main {
        background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
        color: var(--premium-text);
        font-family: var(--font-outfit);
        padding: 30px !important;
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

    @media (max-width: 992px) {
        .main { padding: 20px 15px !important; }
        .hero-title { font-size: 2rem; }
        .match-body { flex-direction: column; gap: 34px; padding: 36px 20px; }
        .match-footer { grid-template-columns: 1fr; }
    }
</style>

<div class="dashboard-container">
    <div class="dashboard-hero">
        <span class="hero-label">Operator Panel</span>
        <h1 class="hero-title">Pusat Operasional Event</h1>
        <p class="hero-description">
            Kelola data challenge dan berita untuk event yang ditugaskan. Semua data di area operator difokuskan ke event ini.
        </p>
    </div>

    <div class="premium-stats-grid">
        <div class="premium-card">
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

        <div class="premium-card">
            <div class="card-top">
                <div class="card-icon-box"><i class="fas fa-futbol"></i></div>
            </div>
            <div>
                <div class="card-value" data-count="<?php echo (int) $challenge_total; ?>"><?php echo (int) $challenge_total; ?></div>
                <div class="card-label">Total Challenge Event</div>
            </div>
        </div>

        <div class="premium-card">
            <div class="card-top">
                <div class="card-icon-box" style="background:#ecfdf5;color:#059669;"><i class="fas fa-hourglass-half"></i></div>
            </div>
            <div>
                <div class="card-value" data-count="<?php echo (int) $challenge_upcoming; ?>"><?php echo (int) $challenge_upcoming; ?></div>
                <div class="card-label">Challenge Mendatang</div>
            </div>
        </div>

        <div class="premium-card">
            <div class="card-top">
                <div class="card-icon-box" style="background:#fff7ed;color:#c2410c;"><i class="fas fa-newspaper"></i></div>
            </div>
            <div>
                <div class="card-value" data-count="<?php echo (int) $berita_total; ?>"><?php echo (int) $berita_total; ?></div>
                <div class="card-label">Total Berita</div>
            </div>
        </div>
    </div>

    <div class="section-header">
        <h2 class="section-title">Highlight Pertandingan Event</h2>
        <div class="section-line"></div>
    </div>

    <?php if ($event_id <= 0): ?>
        <div class="empty-state-light">
            <i class="fas fa-unlink"></i>
            <h3 style="font-weight:800;color:#0f2744;margin-bottom:6px;">Akun belum terhubung ke event</h3>
            <p style="color:#5f728a;">Pilih event di manajemen akun operator terlebih dahulu.</p>
        </div>
    <?php elseif ($next_match): ?>
        <?php $match_date = new DateTime($next_match['challenge_date']); ?>
        <div class="editorial-match-card">
            <div class="match-body">
                <div class="team-block">
                    <div class="team-logo-frame">
                        <?php if (!empty($next_match['team1_logo']) && file_exists(__DIR__ . '/../images/teams/' . $next_match['team1_logo'])): ?>
                            <img src="../images/teams/<?php echo htmlspecialchars($next_match['team1_logo']); ?>" alt="Team 1">
                        <?php else: ?>
                            <i class="fas fa-shield-alt" style="font-size:2rem;color:#cbd5e1;"></i>
                        <?php endif; ?>
                    </div>
                    <h4 class="team-name-small"><?php echo htmlspecialchars($next_match['team1_name'] ?? '-'); ?></h4>
                </div>

                <div class="vs-capsule">VS</div>

                <div class="team-block">
                    <div class="team-logo-frame">
                        <?php if (!empty($next_match['team2_logo']) && file_exists(__DIR__ . '/../images/teams/' . $next_match['team2_logo'])): ?>
                            <img src="../images/teams/<?php echo htmlspecialchars($next_match['team2_logo']); ?>" alt="Team 2">
                        <?php else: ?>
                            <i class="fas fa-shield-alt" style="font-size:2rem;color:#cbd5e1;"></i>
                        <?php endif; ?>
                    </div>
                    <h4 class="team-name-small"><?php echo htmlspecialchars($next_match['team2_name'] ?? '-'); ?></h4>
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
                    <div class="info-data"><?php echo htmlspecialchars($next_match['venue_name'] ?? 'Akan diumumkan'); ?></div>
                </div>
            </div>

            <div style="padding:12px;text-align:center;background:#0f2744;color:white;font-size:0.8rem;font-weight:700;text-transform:uppercase;letter-spacing:2px;">
                <?php echo htmlspecialchars($next_match['sport_type'] ?: ($event_category !== '' ? $event_category : 'Challenge')); ?>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state-light">
            <i class="far fa-calendar-times"></i>
            <h3 style="font-weight:800;color:#0f2744;margin-bottom:6px;">Belum ada challenge mendatang</h3>
            <p style="color:#5f728a;">Event ini belum memiliki pertandingan dengan status accepted yang akan datang.</p>
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
