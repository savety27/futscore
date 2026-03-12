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
$matches_needing_lineup = 0;
$upcoming_matches_h7 = 0;
$chart_labels   = [];
$chart_values   = [];
$chart_tooltips = [];

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
        $stmt->execute([$team_id, $team_id]);
        $today_matches = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Get Matches Needing Lineup
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM challenges c
            WHERE c.status = 'accepted'
              AND (c.challenger_id = ? OR c.opponent_id = ?)
              AND c.challenge_date >= CURDATE()
              AND NOT EXISTS (
                  SELECT 1 FROM lineups l WHERE l.match_id = c.id AND l.team_id = ?
              )
        ");
        $stmt->execute([$team_id, $team_id, $team_id]);
        $matches_needing_lineup = $stmt->fetchColumn();

        // Get Upcoming Matches H-7
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM challenges 
            WHERE status = 'accepted' 
              AND (challenger_id = ? OR opponent_id = ?) 
              AND challenge_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$team_id, $team_id]);
        $upcoming_matches_h7 = $stmt->fetchColumn();

        // Get last 30 days completed matches for performance chart
        $chart_start = date('Y-m-d', strtotime('today'));
        $chart_end   = date('Y-m-d', strtotime('+29 days'));
        $stmt = $conn->prepare("
            SELECT 
                c.id,
                c.challenge_date,
                c.winner_team_id,
                c.challenger_id,
                c.opponent_id,
                c.challenger_score,
                c.opponent_score,
                t1.name AS challenger_name,
                t2.name AS opponent_name
            FROM challenges c
            JOIN teams t1 ON c.challenger_id = t1.id
            JOIN teams t2 ON c.opponent_id = t2.id
            WHERE c.status = 'completed'
              AND (c.challenger_id = ? OR c.opponent_id = ?)
              AND c.challenge_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
              AND c.challenge_date <= DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            ORDER BY c.challenge_date ASC
        ");
        $stmt->execute([$team_id, $team_id]);
        $chart_matches_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build cumulative performance series (win-only increments)
        $chart_labels = [];
        $chart_values = [];
        $chart_tooltips = [];
        $cumulative = 0;
        foreach ($chart_matches_raw as $cm) {
            $match_dt = new DateTime($cm['challenge_date']);
            $label = $match_dt->format('d/m');

            // Determine result
            if ($cm['winner_team_id'] == $team_id) {
                $result = 'Menang';
                $cumulative += 3; // Win = +3
            } elseif ($cm['winner_team_id'] === null || $cm['winner_team_id'] == 0) {
                $result = 'Seri';
            } else {
                $result = 'Kalah';
                // Loss = no change
            }

            $opp = ($cm['challenger_id'] == $team_id)
                ? $cm['opponent_name']
                : $cm['challenger_name'];

            $score_c = $cm['challenger_score'] ?? '-';
            $score_o = $cm['opponent_score'] ?? '-';
            $score_str = ($cm['challenger_id'] == $team_id)
                ? "$score_c - $score_o"
                : "$score_o - $score_c";

            $chart_labels[]   = $label;
            $chart_values[]   = $cumulative;
            $chart_tooltips[] = [
                'date'   => $match_dt->format('d M Y'),
                'opp'    => $opp,
                'result' => $result,
                'score'  => $score_str,
            ];
        }

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
        $chart_matches_raw = [];
        $chart_labels = [];
        $chart_values = [];
        $chart_tooltips = [];
    }
}
?>

<style>

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
        grid-row: span 2;
        background: var(--heritage-text);
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

    .team-identity-card::after {
        content: '';
        position: absolute;
        top: 0; right: 0; bottom: 0; left: 0;
        background: linear-gradient(45deg, rgba(255,255,255,0.05) 0%, transparent 100%);
    }

    .team-logo-main {
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
        margin: 0 auto 28px auto;
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

    .team-side-column {
        display: grid;
        grid-template-rows: repeat(3, 1fr);

        gap: 24px;
    }

    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 24px;
    }

    .quick-action-card {
        text-decoration: none;
        color: inherit;
    }

    .quick-action-card:hover {
        color: inherit;
    }

    .quick-action-card:hover,
    .quick-action-card:focus-within {
        transform: translateY(-9px) scale(1.02) !important;
        border-color: var(--heritage-text);
        border-width: 2px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 34px 70px rgba(17, 24, 39, 0.2), 0 0 0 6px rgba(100, 116, 139, 0.32) !important;
    }


        .stats-main-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        grid-template-rows: repeat(3, 1fr);
        gap: 24px;
    }

    .heritage-card {
        background: var(--heritage-card);
        border: 1px solid var(--heritage-border);
        border-radius: 28px;
        padding: 24px;
        transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.32s ease, border-color 0.32s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
        will-change: transform;
        box-sizing: border-box;
        box-shadow: var(--soft-shadow);
    }

    .heritage-card:hover,
    .heritage-card:focus-within {
        transform: translateY(-10px) scale(1.015);
        border-color: var(--heritage-text);
        border-width: 2px;
        box-shadow: 0 26px 52px rgba(17, 24, 39, 0.14), 0 0 0 4px rgba(100, 116, 139, 0.22);
    }

    .stats-main-grid .heritage-card {
        cursor: pointer;
        transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.32s ease, border-color 0.32s ease;
    }

    .stats-main-grid .heritage-card:not(.perf-chart-card):hover,
    .stats-main-grid .heritage-card:not(.perf-chart-card):focus-within {
        transform: translateY(-9px) scale(1.02) !important;
        border-color: var(--heritage-text);
        border-width: 2px;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 34px 70px rgba(17, 24, 39, 0.2), 0 0 0 6px rgba(100, 116, 139, 0.32) !important;
    }

    .heritage-card:active {
        transform: translateY(-4px) scale(1.008);
    }

    .heritage-card::before {
        content: '';
        position: absolute;
        top: -120%;
        left: -35%;
        width: 35%;
        height: 300%;
        background: linear-gradient(120deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.35), rgba(255, 255, 255, 0));
        transform: rotate(14deg);
        transition: transform 0.8s ease, left 0.8s ease;
        pointer-events: none;
    }

    .heritage-card:hover::before,
    .heritage-card:focus-within::before {
        left: 120%;
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
        transition: transform 0.28s ease, opacity 0.28s ease;
    }

    .heritage-card:hover .card-icon,
    .heritage-card:focus-within .card-icon {
        opacity: 1;
        transform: translateY(-3px) scale(1.06);
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
        from { opacity: 0; }
        to { opacity: 1; }
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
        .quick-actions-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .match-hero-content { 
            grid-template-columns: 1fr;
            gap: 24px;
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
        .dashboard-container { overflow-x: hidden; }
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
        
        .team-side-column { 
            display: flex; 
            flex-direction: column; 
            min-width: 0; 
        }

        .team-identity-card { padding: 32px 16px; border-radius: 20px; }
        .team-identity-card .top {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .team-logo-main { width: 96px; height: 96px; margin: 0 auto 16px auto; }
        .team-name-display { font-size: 1.25rem; }
        .team-status-badge { padding: 4px 8px; font-size: 0.7rem; line-height: 1.2; text-align: center; align-self: center; }
        .team-status-badge span { width: 6px !important; height: 6px !important; }

        .stats-grid-wrapper,
        .team-side-column,
        .stats-main-grid,
        .quick-actions-grid { min-width: 0; }

        .quick-actions-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .quick-action-card { min-height: 64px; }
        
        .stats-main-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .heritage-card { border-radius: 14px; padding: 10px; min-width: 0; max-width: 100%; }
        .stats-main-grid .card-label { font-size: 0.58rem; }
        .stats-main-grid .card-value { font-size: 1.35rem; }
        .heritage-card[style*="grid-column: span 2"] { grid-column: span 2 !important; }
        .stats-main-grid .card-label { font-size: 0.6rem; }
        .stats-main-grid .card-value { font-size: 1.45rem; }

        .quick-action-card:hover,
        .quick-action-card:focus-within,
        .stats-main-grid .heritage-card:not(.perf-chart-card):hover,
        .stats-main-grid .heritage-card:not(.perf-chart-card):focus-within {
            transform: translateY(-2px) scale(1) !important;
            box-shadow: 0 10px 20px rgba(17, 24, 39, 0.14) !important;
        }
        
        .section-title { font-size: 1.5rem; }
        .match-hero-content { 
            grid-template-columns: 1fr;
            gap: 16px;
            padding: 32px 16px;
        }
        .team-focus { gap: 12px; }
        .team-logo-large { width: 76px; height: 76px; padding: 10px; border-radius: 18px; }
        .team-name-large { font-size: 0.95rem; }
        .vs-text { width: 40px; height: 40px; font-size: 0.95rem; margin: 0 auto; }
        
        .today-section .section-header { margin-bottom: 14px; }
        .today-grid { grid-template-columns: 1fr; gap: 12px; }
        .schedule-card { padding: 16px; min-width: 0; }
        .schedule-time { font-size: 1rem; }
        .schedule-teams { font-size: 0.95rem; }
        .schedule-meta { font-size: 0.75rem; }

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

    @media (max-width: 480px) {
        .main { padding: 12px !important; }
        .hero-title { font-size: 1.85rem; }
        .team-identity-card { padding: 24px 12px; }
        .team-logo-main { width: 72px; height: 72px; }
        .team-name-display { font-size: 1.1rem; }
        .team-status-badge { font-size: 0.65rem; padding: 4px 6px; margin-top: 8px; }
        .card-value { font-size: 1.5rem; }
        
        .quick-actions-grid { grid-template-columns: 1fr; }
        .quick-action-card { min-height: 64px; }

        .stats-main-grid { grid-template-columns: 1fr; }
        .heritage-card[style*="grid-column: span 2"] { grid-column: 1 / -1 !important; }
        .heritage-card { border-radius: 12px; padding: 10px; max-width: 100%; }

        .quick-action-card:hover,
        .quick-action-card:focus-within,
        .stats-main-grid .heritage-card:not(.perf-chart-card):hover,
        .stats-main-grid .heritage-card:not(.perf-chart-card):focus-within {
            transform: translateY(-1px) scale(1) !important;
            box-shadow: 0 8px 16px rgba(17, 24, 39, 0.12) !important;
        }

        .match-hero-content { padding: 28px 12px; gap: 12px; }
        .team-logo-large { width: 64px; height: 64px; padding: 8px; border-radius: 16px; }
        .team-name-large { font-size: 0.85rem; }
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
        <div class="team-side-column">
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
            </div>

            <div class="quick-actions-grid reveal d-2">
                <a class="heritage-card quick-action-card" href="schedule.php" aria-label="Butuh Match Lineup" style="border-bottom: 3px solid var(--heritage-text);">
                    <div class="card-meta">
                        <span class="card-label">Match Lineup</span>
                        <i class="fas fa-users-cog card-icon" style="color: var(--heritage-text);"></i>
                    </div>
                    <div class="card-value" style="color: var(--heritage-text);" data-count="<?php echo (int)$matches_needing_lineup; ?>">
                        <?php echo (int)$matches_needing_lineup; ?>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Butuh Lineup (Upcoming)</div>
                </a>
                <a class="heritage-card quick-action-card" href="schedule.php#daftar-jadwal-pertandingan" aria-label="Pertandingan Terdekat" style="border-bottom: 3px solid var(--heritage-text);">
                    <div class="card-meta">
                        <span class="card-label">Pertandingan</span>
                        <i class="fas fa-calendar-day card-icon" style="color: var(--heritage-text);"></i>
                    </div>
                    <div class="card-value" style="color: var(--heritage-text);" data-count="<?php echo (int)$upcoming_matches_h7; ?>">
                        <?php echo (int)$upcoming_matches_h7; ?>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--heritage-text-muted); margin-top: 4px;">Terdekat (H-7)</div>
                </a>
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
            <!-- Performance Chart Card (replaces Win Rate) -->
            <div class="heritage-card reveal d-5 perf-chart-card" style="grid-column: span 2; background: var(--heritage-text); color: white; padding: 28px 28px 20px; position: relative;">
                <div class="card-meta" style="margin-bottom: 12px;">
                    <div style="display:flex; flex-direction:column; gap:2px;">
                        <span class="card-label" style="color: rgba(255,255,255,0.55);">Performa Tim</span>
                        <span style="font-family: var(--font-display); font-size:1.1rem; font-weight:700; color:white; letter-spacing:-0.02em;">30 Hari Terakhir</span>
                    </div>
                    <div style="display:flex; gap:16px; align-items:center;">
                        <?php
                            $total_games = $wins + $losses + $draws;
                            $win_rate = $total_games > 0 ? round(($wins / $total_games) * 100) : 0;
                        ?>
                        <div style="text-align:right;">
                            <div style="font-size:0.7rem; color:rgba(255,255,255,0.45); text-transform:uppercase; letter-spacing:.06em;">Win Rate</div>
                            <div style="font-family:var(--font-display); font-size:1.4rem; font-weight:800; color:#6ee7b7;"><?php echo $win_rate; ?>%</div>
                        </div>
                        <i class="fas fa-chart-line" style="color: rgba(255,255,255,0.3); font-size:1.1rem;"></i>
                    </div>
                </div>
                <?php if (!empty($chart_values)): ?>
                <div style="position:relative; height:140px; margin-top:8px;">
                    <canvas id="perfChart"></canvas>
                </div>
                <div id="perfChartTooltip" style="
                    position:absolute; pointer-events:none; display:none;
                    background:rgba(255,255,255,0.97); color:#1e1b4b;
                    border-radius:12px; padding:10px 14px;
                    font-family:var(--font-body); font-size:0.8rem; font-weight:600;
                    box-shadow:0 8px 24px rgba(0,0,0,0.18);
                    white-space:nowrap; z-index:50; min-width:160px;
                    border-left: 3px solid #064e3b;
                "></div>
                <?php else: ?>
                <div style="height:120px; display:flex; align-items:center; justify-content:center; opacity:0.4; font-size:0.9rem;">Belum ada data pertandingan dalam 30 hari terakhir</div>
                <?php endif; ?>
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
    <section class="reveal today-section" style="animation-delay: 0.8s; margin-top: 64px;">
        <div class="section-header">
            <h2 class="section-title" >Jadwal Hari Ini</h2>
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

    // ── Performance Chart ──────────────────────────────────────────────────
    <?php if (!empty($chart_values)): ?>
    const chartLabels   = <?php echo json_encode($chart_labels); ?>;
    const chartValues   = <?php echo json_encode($chart_values); ?>;
    const chartTooltips = <?php echo json_encode($chart_tooltips); ?>;

    const ctx = document.getElementById('perfChart');
    if (!ctx) return;

    // Build gradient
    const canvasCtx = ctx.getContext('2d');
    const grad = canvasCtx.createLinearGradient(0, 0, 0, 140);
    grad.addColorStop(0, 'rgba(110,231,183,0.30)');
    grad.addColorStop(1, 'rgba(110,231,183,0.00)');

    const perfChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Poin Kumulatif',
                data: chartValues,
                borderColor: '#6ee7b7',
                borderWidth: 2.5,
                pointBackgroundColor: chartValues.map((v, i) => {
                    const t = chartTooltips[i];
                    if (t.result === 'Menang') return '#6ee7b7';
                    if (t.result === 'Seri')   return '#fbbf24';
                    return '#f87171';
                }),
                pointBorderColor: '#1e1b4b',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHitRadius: 14,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#6ee7b7',
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

    // Custom tooltip
    const tooltipEl = document.getElementById('perfChartTooltip');
    ctx.addEventListener('mousemove', function(e) {
        const points = perfChart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false);
        if (points.length === 0) {
            tooltipEl.style.display = 'none';
            return;
        }
        const idx = points[0].index;
        const t   = chartTooltips[idx];
        const resultColor = t.result === 'Menang' ? '#059669' : (t.result === 'Seri' ? '#d97706' : '#dc2626');
        const resultIcon  = t.result === 'Menang' ? '🏆' : (t.result === 'Seri' ? '🤝' : '💔');

        tooltipEl.innerHTML = `
            <div style="font-size:0.7rem; color:#6b7280; margin-bottom:4px;">${t.date}</div>
            <div style="font-size:0.85rem; font-weight:700; color:#1e1b4b; margin-bottom:6px;">vs ${t.opp}</div>
            <div style="display:flex; align-items:center; gap:8px; justify-content:space-between;">
                <span style="color:${resultColor}; font-weight:700;">${resultIcon} ${t.result}</span>
                <span style="background:#f3f4f6; border-radius:6px; padding:2px 8px; font-weight:800; color:#1e1b4b; font-size:0.8rem;">${t.score}</span>
            </div>
        `;

        // Position near cursor but inside chart card
        const chartRect = ctx.getBoundingClientRect();
        const cardRect  = tooltipEl.parentElement.getBoundingClientRect();
        let left = (e.clientX - cardRect.left) + 12;
        let top  = (e.clientY - cardRect.top)  - 20;
        // Clamp right
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

<?php require_once 'includes/footer.php'; ?>
