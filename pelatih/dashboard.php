<?php
$page_title = 'Dashboard';
$current_page = 'dashboard';
require_once 'config/database.php';
require_once 'includes/header.php';

$team_id = $_SESSION['team_id'] ?? 0;
$player_count = 0;
$team_name = 'Unknown Team';
$staff_count = 0;
$wins = 0;
$losses = 0;
$draws = 0;
$ongoing = 0;
$next_match = null;

if ($team_id) {
    try {
        // Get Team Name and Logo
        $stmt = $conn->prepare("SELECT name, logo, is_active FROM teams WHERE id = ?");
        $stmt->execute([$team_id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        $team_name = $team ? ($team['name'] ?: 'Unknown Team') : 'Unknown Team';
        $team_logo = $team ? $team['logo'] : null;
        $is_active = $team ? (int)$team['is_active'] : 0;

        // Get Player Count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM players WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $player_count = $stmt->fetchColumn();

        // Get Staff Count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM team_staff WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $staff_count = $stmt->fetchColumn();

        // Get Wins
        $stmt = $conn->prepare("SELECT COUNT(*) FROM challenges WHERE status = 'completed' AND winner_team_id = ?");
        $stmt->execute([$team_id]);
        $wins = $stmt->fetchColumn();

        // Get Losses
        $stmt = $conn->prepare("SELECT COUNT(*) FROM challenges WHERE status = 'completed' AND (challenger_id = ? OR opponent_id = ?) AND winner_team_id IS NOT NULL AND winner_team_id != ?");
        $stmt->execute([$team_id, $team_id, $team_id]);
        $losses = $stmt->fetchColumn();

        // Get Draws
        $stmt = $conn->prepare("SELECT COUNT(*) FROM challenges WHERE status = 'completed' AND (challenger_id = ? OR opponent_id = ?) AND winner_team_id IS NULL");
        $stmt->execute([$team_id, $team_id]);
        $draws = $stmt->fetchColumn();

        // Get Ongoing Matches
        // Definition: status 'accepted' and match time is today and near current time (let's say match lasts 2 hours)
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM challenges 
            WHERE status = 'accepted' 
            AND (challenger_id = ? OR opponent_id = ?) 
            AND challenge_date <= NOW() 
            AND challenge_date >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ");
        $stmt->execute([$team_id, $team_id]);
        $ongoing = $stmt->fetchColumn();

        // Get Next Match Spotlight
        $stmt = $conn->prepare("
            SELECT c.*, 
                   t1.name as team1_name, t1.logo as team1_logo,
                   t2.name as team2_name, t2.logo as team2_logo,
                   v.name as venue_name
            FROM challenges c
            JOIN teams t1 ON c.challenger_id = t1.id
            JOIN teams t2 ON c.opponent_id = t2.id
            LEFT JOIN venues v ON c.venue_id = v.id
            WHERE c.status = 'accepted' 
              AND (c.challenger_id = ? OR c.opponent_id = ?)
              AND c.challenge_date >= NOW()
            ORDER BY c.challenge_date ASC
            LIMIT 1
        ");
        $stmt->execute([$team_id, $team_id]);
        $next_match = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get All Matches Today
        $stmt = $conn->prepare("
            SELECT c.*, 
                   t1.name as team1_name, t1.logo as team1_logo,
                   t2.name as team2_name, t2.logo as team2_logo,
                   v.name as venue_name
            FROM challenges c
            JOIN teams t1 ON c.challenger_id = t1.id
            JOIN teams t2 ON c.opponent_id = t2.id
            LEFT JOIN venues v ON c.venue_id = v.id
            WHERE c.status = 'accepted'
              AND (c.challenger_id = ? OR c.opponent_id = ?)
              AND DATE(c.challenge_date) = CURDATE()
            ORDER BY c.challenge_date ASC
        ");
        $stmt->execute([$team_id, $team_id]);
        $today_matches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } catch (PDOException $e) {
        $player_count = 0;
        $staff_count = 0;
        $wins = 0;
        $losses = 0;
        $draws = 0;
        $next_match = null;
        $today_matches = [];
        $team_name = 'Unknown Team';
        $team_logo = null;
    }
}
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,200..800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

    :root {
        --heritage-bg: #f8f7f4;
        --heritage-card: #ffffff;
        --heritage-border: #e5e1da;
        --heritage-text: #1e1b4b;
        --heritage-text-muted: #6b7280;
        --heritage-accent: #064e3b;
        --heritage-gold: #b45309;
        --heritage-crimson: #991b1b;
        --font-display: 'Bricolage Grotesque', sans-serif;
        --font-body: 'Plus Jakarta Sans', sans-serif;
        --soft-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
        --glow-shadow: 0 0 40px rgba(6, 78, 59, 0.08);
    }

    .main {
        background: var(--heritage-bg) !important;
        background-image: radial-gradient(#e5e1da 0.5px, transparent 0.5px) !important;
        background-size: 24px 24px !important;
        color: var(--heritage-text);
        font-family: var(--font-body);
        padding: 40px !important;
    }

    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Editorial Hero */
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

    .hero-content {
        max-width: 800px;
    }

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

    .hero-description {
        color: var(--heritage-text-muted);
        font-size: 1.15rem;
        line-height: 1.6;
        margin: 0;
    }

    /* Stats Grid - Modern Editorial Layout */
    .stats-grid-wrapper {
        display: grid;
        grid-template-columns: 1fr 2fr;
        gap: 32px;
        margin-bottom: 48px;
    }

    .team-identity-card {
        background: var(--heritage-text);
        color: white;
        border-radius: 32px;
        padding: 40px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .team-identity-card::after {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        background: linear-gradient(45deg, rgba(255,255,255,0.05) 0%, transparent 100%);
    }

    .team-logo-main {
        width: 80px;
        height: 80px;
        background: white;
        border-radius: 20px;
        padding: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 24px;
        box-shadow: 0 12px 24px rgba(0,0,0,0.2);
    }

    .team-logo-main img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .team-name-display {
        font-family: var(--font-display);
        font-size: 2.25rem;
        font-weight: 700;
        line-height: 1.1;
        margin-bottom: 8px;
    }

    .team-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,255,255,0.1);
        padding: 6px 14px;
        border-radius: 100px;
        font-size: 0.85rem;
        font-weight: 600;
        backdrop-filter: blur(4px);
    }

        .stats-main-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
    }

    .heritage-card {
        background: var(--heritage-card);
        border: 1px solid var(--heritage-border);
        border-radius: 28px;
        padding: 24px;
        transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        box-shadow: var(--soft-shadow);
    }

    .heritage-card:hover {
        transform: translateY(-8px);
        border-color: var(--heritage-text);
        box-shadow: 0 20px 40px rgba(0,0,0,0.06);
    }

    .card-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .card-icon {
        font-size: 1.1rem;
        color: var(--heritage-text);
        opacity: 0.6;
    }

    .card-label {
        font-family: var(--font-display);
        font-weight: 700;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--heritage-text-muted);
    }

    .card-value {
        font-family: var(--font-display);
        font-size: 2.25rem;
        font-weight: 800;
        line-height: 1;
        color: var(--heritage-text);
    }

    /* Detailed Record Bar */
    .record-row {
        background: var(--heritage-card);
        border: 1px solid var(--heritage-border);
        border-radius: 24px;
        padding: 24px 40px;
        margin-bottom: 48px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: var(--soft-shadow);
    }

    .record-item {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .record-number {
        font-family: var(--font-display);
        font-size: 2.5rem;
        font-weight: 800;
    }

    .record-label {
        font-size: 0.9rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--heritage-text-muted);
        letter-spacing: 1px;
    }

    .record-divider {
        width: 1px;
        height: 40px;
        background: var(--heritage-border);
    }

    /* Premium Match Spotlight */
    .section-header {
        display: flex;
        align-items: center;
        gap: 24px;
        margin-bottom: 32px;
    }

    .section-title {
        font-family: var(--font-display);
        font-size: 2rem;
        font-weight: 800;
        color: var(--heritage-text);
        margin: 0;
    }

    .section-line {
        height: 2px;
        background: var(--heritage-border);
        flex: 1;
    }

    .match-hero-card {
        background: white;
        border: 1px solid var(--heritage-border);
        border-radius: 40px;
        overflow: hidden;
        box-shadow: 0 30px 60px rgba(0,0,0,0.05);
        margin-bottom: 48px;
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
        background: var(--heritage-text);
        padding: 32px 48px;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 32px;
    }

    .match-info-box {
        color: white;
    }

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

    /* Today's Schedule Mini-Cards */
    .today-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 24px;
    }

    .schedule-card {
        background: white;
        border: 1px solid var(--heritage-border);
        border-radius: 24px;
        padding: 28px;
        transition: all 0.3s ease;
    }

    .schedule-card:hover {
        border-color: var(--heritage-gold);
        transform: translateX(8px);
    }

    .schedule-time {
        font-family: var(--font-display);
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--heritage-gold);
        margin-bottom: 12px;
        display: block;
    }

    .schedule-teams {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 16px;
        line-height: 1.4;
    }

    .schedule-meta {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 0.85rem;
        color: var(--heritage-text-muted);
    }

    .schedule-meta i {
        color: var(--heritage-gold);
    }

    /* Empty States */
    .empty-state {
        text-align: center;
        padding: 80px 40px;
        background: white;
        border: 2px dashed var(--heritage-border);
        border-radius: 32px;
    }

    .empty-state i {
        font-size: 3rem;
        color: var(--heritage-border);
        margin-bottom: 24px;
    }

    .empty-state h3 {
        font-family: var(--font-display);
        font-size: 1.5rem;
        margin-bottom: 8px;
    }

    /* Animations */
    @keyframes revealUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .reveal {
        animation: revealUp 0.8s cubic-bezier(0.23, 1, 0.32, 1) forwards;
        opacity: 0;
    }

    .d-1 { animation-delay: 0.1s; }
    .d-2 { animation-delay: 0.2s; }
    .d-3 { animation-delay: 0.3s; }
    .d-4 { animation-delay: 0.4s; }
    .d-5 { animation-delay: 0.5s; }

    /* Mobile Responsive Optimizations */
    @media (max-width: 1200px) {
        .hero-title { font-size: 3rem; }
        .stats-main-grid { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 1024px) {
        .stats-grid-wrapper { grid-template-columns: 1fr; }
        .match-hero-content { 
            grid-template-columns: 1fr;
            gap: 40px;
            padding: 48px 24px;
        }
        .vs-line { display: none; }
        .match-hero-footer { grid-template-columns: 1fr; gap: 24px; padding: 32px 24px; }
        .record-row { 
            flex-direction: column; 
            gap: 32px;
            text-align: center;
        }
        .record-divider { display: none; }
    }

    @media (max-width: 768px) {
        .main { padding: 16px !important; }
        .hero-title { font-size: 2.25rem; }
        .hero-description { font-size: 1rem; }
        .dashboard-hero { 
            margin-bottom: 32px; 
            padding-bottom: 24px;
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        
        .team-identity-card { padding: 32px 24px; border-radius: 24px; }
        .team-name-display { font-size: 1.75rem; }
        
        .stats-main-grid { grid-template-columns: 1fr; gap: 16px; }
        .heritage-card { border-radius: 20px; padding: 20px; }
        .heritage-card[style*="grid-column: span 2"] { grid-column: span 1 !important; }
        
        .section-title { font-size: 1.5rem; }
        .team-logo-large { width: 100px; height: 100px; padding: 16px; border-radius: 24px; }
        .team-name-large { font-size: 1.25rem; }
        
        .today-grid { grid-template-columns: 1fr; }
        .schedule-card { padding: 20px; }
        
        .match-hero-content { padding: 40px 16px; }
        .vs-text { width: 50px; height: 50px; font-size: 1.1rem; }
    }

    @media (max-width: 480px) {
        .hero-title { font-size: 1.85rem; }
        .team-logo-main { width: 60px; height: 60px; }
        .card-value { font-size: 1.85rem; }
        .match-hero-content { padding: 40px 16px; }
        .vs-text { width: 60px; height: 60px; font-size: 1.25rem; }
    }
</style>

<div class="dashboard-container">
    <!-- Editorial Header -->
    <header class="dashboard-hero reveal">
        <div class="hero-content">
            <span class="hero-label">Musim 2025/2026</span>
            <h1 class="hero-title">Command Center</h1>
            <p class="hero-description">Arsitektur strategi dan pemantauan performa atletik secara real-time untuk keunggulan kompetitif tim.</p>
        </div>
        <div class="hero-actions">
            <!-- Add context-relevant button if needed -->
        </div>
    </header>

    <!-- Core Stats Section -->
    <div class="stats-grid-wrapper">
        <!-- Team Identity Focus -->
        <div class="team-identity-card reveal d-1">
            <div class="top">
                <div class="team-logo-main">
                    <?php if (!empty($team_logo) && file_exists('../images/teams/' . $team_logo)): ?>
                        <img src="../images/teams/<?php echo htmlspecialchars($team_logo); ?>" alt="Team">
                    <?php else: ?>
                        <i class="fas fa-shield-alt" style="font-size: 2rem; color: #1e1b4b;"></i>
                    <?php endif; ?>
                </div>
                <h2 class="team-name-display"><?php echo htmlspecialchars($team_name); ?></h2>
                <div class="team-status-badge">
                    <span style="width: 8px; height: 8px; background: <?php echo $is_active ? '#10b981' : '#ef4444'; ?>; border-radius: 50%;"></span>
                    Status: <?php echo $is_active ? 'Aktif Kompetisi' : 'Nonaktif / Ditangguhkan'; ?>
                </div>
            </div>
            <div class="bottom" style="margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 24px;">
                <div style="font-size: 0.85rem; opacity: 0.6; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;">Terakhir Diperbarui</div>
                <div style="font-weight: 600;"><?php echo date('d F, Y • H:i'); ?></div>
            </div>
        </div>

                <!-- Secondary Stats Grid -->
        <div class="stats-main-grid">
            <!-- Row 1 -->
            <div class="heritage-card reveal d-2">
                <div class="card-meta">
                    <span class="card-label">Skuad</span>
                    <i class="fas fa-users card-icon"></i>
                </div>
                <div class="card-value" data-count="<?php echo (int)$player_count; ?>"><?php echo (int)$player_count; ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Pemain Terdaftar</div>
            </div>
            <div class="heritage-card reveal d-3">
                <div class="card-meta">
                    <span class="card-label">Manajemen</span>
                    <i class="fas fa-briefcase card-icon"></i>
                </div>
                <div class="card-value" data-count="<?php echo (int)$staff_count; ?>"><?php echo (int)$staff_count; ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Ofisial Terverifikasi</div>
            </div>
            <div class="heritage-card reveal d-4">
                <div class="card-meta">
                    <span class="card-label">Matchday</span>
                    <i class="fas fa-calendar-check card-icon"></i>
                </div>
                <div class="card-value" data-count="<?php echo count($today_matches); ?>"><?php echo count($today_matches); ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Pertandingan Hari Ini</div>
            </div>

            <!-- Row 2 -->
            <div class="heritage-card reveal d-5" style="border-bottom: 3px solid var(--heritage-accent);">
                <div class="card-meta">
                    <span class="card-label">Menang</span>
                    <i class="fas fa-trophy card-icon" style="color: var(--heritage-accent);"></i>
                </div>
                <div class="card-value" style="color: var(--heritage-accent);" data-count="<?php echo (int)$wins; ?>"><?php echo (int)$wins; ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Total Kemenangan</div>
            </div>
            <div class="heritage-card reveal d-2">
                <div class="card-meta">
                    <span class="card-label">Seri</span>
                    <i class="fas fa-handshake card-icon"></i>
                </div>
                <div class="card-value" data-count="<?php echo (int)$draws; ?>"><?php echo (int)$draws; ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Hasil Imbang</div>
            </div>
            <div class="heritage-card reveal d-3" style="border-bottom: 3px solid var(--heritage-crimson);">
                <div class="card-meta">
                    <span class="card-label">Kalah</span>
                    <i class="fas fa-times-circle card-icon" style="color: var(--heritage-crimson);"></i>
                </div>
                <div class="card-value" style="color: var(--heritage-crimson);" data-count="<?php echo (int)$losses; ?>"><?php echo (int)$losses; ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Total Kekalahan</div>
            </div>

            <!-- Row 3 -->
            <div class="heritage-card reveal d-4" style="border-bottom: 3px solid var(--heritage-gold);">
                <div class="card-meta">
                    <span class="card-label">Ongoing</span>
                    <i class="fas fa-clock card-icon" style="color: var(--heritage-gold);"></i>
                </div>
                <div class="card-value" style="color: var(--heritage-gold);" data-count="<?php echo (int)$ongoing; ?>"><?php echo (int)$ongoing; ?></div>
                <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Sedang Berlangsung</div>
            </div>
            <div class="heritage-card reveal d-5" style="grid-column: span 2; background: var(--heritage-text); color: white;">
                <div class="card-meta">
                    <span class="card-label" style="color: rgba(255,255,255,0.6);">Win Rate</span>
                    <i class="fas fa-chart-line card-icon" style="color: white; opacity: 1;"></i>
                </div>
                <?php 
                    $total_games = $wins + $losses + $draws;
                    $win_rate = $total_games > 0 ? round(($wins / $total_games) * 100) : 0;
                ?>
                <div class="card-value" style="color: white;"><?php echo $win_rate; ?>%</div>
                <div style="font-size: 0.8rem; color: rgba(255,255,255,0.6); margin-top: 4px;">Efektivitas Strategi Tim</div>
            </div>
        </div>
    </div>
    <!-- Match Spotlight -->
    <section class="reveal" style="animation-delay: 0.7s;">
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
                            <?php if ($next_match['team1_logo'] && file_exists('../images/teams/' . $next_match['team1_logo'])): ?>
                                <img src="../images/teams/<?php echo htmlspecialchars($next_match['team1_logo']); ?>" alt="Team 1" style="width: 100%; height: 100%; object-fit: contain;">
                            <?php else: ?>
                                <i class="fas fa-shield-alt" style="font-size: 4rem; color: var(--heritage-border);"></i>
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
                            <?php if ($next_match['team2_logo'] && file_exists('../images/teams/' . $next_match['team2_logo'])): ?>
                                <img src="../images/teams/<?php echo htmlspecialchars($next_match['team2_logo']); ?>" alt="Team 2" style="width: 100%; height: 100%; object-fit: contain;">
                            <?php else: ?>
                                <i class="fas fa-shield-alt" style="font-size: 4rem; color: var(--heritage-border);"></i>
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
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calendar-day"></i>
                <h3>Belum Ada Jadwal Pertandingan</h3>
                <p style="color: var(--heritage-text-muted);">Tetap fokus pada pelatihan rutin sambil menunggu konfirmasi jadwal kompetisi berikutnya.</p>
            </div>
        <?php endif; ?>
    </section>

    <!-- Schedule Mini -->
    <section class="reveal" style="animation-delay: 0.8s; margin-top: 64px;">
        <div class="section-header">
            <h2 class="section-title">Jadwal Hari Ini</h2>
            <div class="section-line"></div>
        </div>

        <?php if (!empty($today_matches)): ?>
            <div class="today-grid">
                <?php foreach ($today_matches as $today_match): ?>
                    <?php $today_match_date = new DateTime($today_match['challenge_date']); ?>
                    <div class="schedule-card">
                        <span class="schedule-time"><?php echo $today_match_date->format('H:i'); ?> WIB</span>
                        <div class="schedule-teams">
                            <?php echo htmlspecialchars($today_match['team1_name']); ?> 
                            <span style="color: var(--heritage-gold); font-size: 0.8rem; margin: 0 4px;">VS</span> 
                            <?php echo htmlspecialchars($today_match['team2_name']); ?>
                        </div>
                        <div class="schedule-meta">
                            <i class="fas fa-location-dot"></i>
                            <span><?php echo htmlspecialchars($today_match['venue_name'] ?: 'Venue TBD'); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding: 40px;">
                <p style="margin: 0; color: var(--heritage-text-muted); font-weight: 500;">Tidak ada pertandingan yang dijadwalkan untuk hari ini.</p>
            </div>
        <?php endif; ?>
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
            const eased = 1 - Math.pow(1 - progress, 4); // Quartic ease out
            el.textContent = Math.round(target * eased).toString();
            if (progress < 1) requestAnimationFrame(tick);
        }

        el.textContent = '0';
        requestAnimationFrame(tick);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>