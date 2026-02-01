<?php
$page_title = 'Dashboard';
$current_page = 'dashboard';
require_once 'config/database.php';
require_once 'includes/header.php';

$team_id = $_SESSION['team_id'] ?? 0;
$player_count = 0;
$team_name = 'Unknown Team';

if ($team_id) {
    try {
        // Get Team Name and Logo
        $stmt = $conn->prepare("SELECT name, logo FROM teams WHERE id = ?");
        $stmt->execute([$team_id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        $team_name = $team ? ($team['name'] ?: 'Unknown Team') : 'Unknown Team';
        $team_logo = $team ? $team['logo'] : null;

        // Get Player Count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM players WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $player_count = $stmt->fetchColumn();

        // Get Staff Count
        $stmt = $conn->prepare("SELECT COUNT(*) FROM team_staff WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $staff_count = $stmt->fetchColumn();

        // Get Wins
        $stmt = $conn->prepare("SELECT COUNT(*) FROM matches WHERE status = 'completed' AND ((team1_id = ? AND score1 > score2) OR (team2_id = ? AND score2 > score1))");
        $stmt->execute([$team_id, $team_id]);
        $wins = $stmt->fetchColumn();

        // Get Losses
        $stmt = $conn->prepare("SELECT COUNT(*) FROM matches WHERE status = 'completed' AND ((team1_id = ? AND score1 < score2) OR (team2_id = ? AND score2 < score1))");
        $stmt->execute([$team_id, $team_id]);
        $losses = $stmt->fetchColumn();

        // Get Draws
        $stmt = $conn->prepare("SELECT COUNT(*) FROM matches WHERE status = 'completed' AND (team1_id = ? OR team2_id = ?) AND score1 = score2");
        $stmt->execute([$team_id, $team_id]);
        $draws = $stmt->fetchColumn();

        // Get Next Match Spotlight
        $stmt = $conn->prepare("
            SELECT m.*, 
                   t1.name as team1_name, t1.logo as team1_logo,
                   t2.name as team2_name, t2.logo as team2_logo,
                   e.name as event_name
            FROM matches m
            JOIN teams t1 ON m.team1_id = t1.id
            JOIN teams t2 ON m.team2_id = t2.id
            LEFT JOIN events e ON m.event_id = e.id
            WHERE m.status = 'scheduled' 
              AND (m.team1_id = ? OR m.team2_id = ?)
              AND m.match_date >= NOW()
            ORDER BY m.match_date ASC
            LIMIT 1
        ");
        $stmt->execute([$team_id, $team_id]);
        $next_match = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $player_count = 0;
        $staff_count = 0;
        $wins = 0;
        $losses = 0;
        $draws = 0;
        $next_match = null;
        $team_name = 'Unknown Team';
        $team_logo = null;
    }
}
?>


<style>
    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

    :root {
        --premium-bg: #f8fafc;
        --premium-card: #ffffff;
        --premium-border: #e2e8f0;
        --premium-text: #1e293b;
        --premium-text-muted: #64748b;
        --premium-accent: #0A2463;
        --premium-gold: #FFD700;
        --font-outfit: 'Outfit', sans-serif;
        --soft-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        --hover-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
    }

    .main {
        background: var(--premium-bg) !important;
        color: var(--premium-text);
        font-family: var(--font-outfit);
        padding: 40px !important;
    }

    .dashboard-container {
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Refined Hero */
    .dashboard-hero {
        margin-bottom: 40px;
        position: relative;
    }

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
        letter-spacing: -0.5px;
    }

    .hero-description {
        color: var(--premium-text-muted);
        font-size: 1.1rem;
        max-width: 600px;
        line-height: 1.6;
    }

    /* Stats Grid */
    .premium-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 25px;
        margin-bottom: 60px;
    }

    .premium-card {
        background: var(--premium-card);
        border: 1px solid var(--premium-border);
        border-radius: 24px;
        padding: 30px;
        box-shadow: var(--soft-shadow);
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .premium-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--hover-shadow);
        border-color: var(--premium-accent);
    }

    .card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 25px;
    }

    .card-icon-box {
        width: 54px;
        height: 54px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        background: #f1f5f9;
        color: var(--premium-accent);
        transition: var(--transition);
    }

    .premium-card:hover .card-icon-box {
        background: var(--premium-accent);
        color: white;
    }

    .card-value {
        font-size: 2.2rem;
        font-weight: 800;
        color: var(--premium-text);
        margin-bottom: 4px;
    }

    .card-label {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--premium-text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* Match Spotlight */
    .match-spotlight {
        margin-top: 50px;
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 20px;
        margin-bottom: 30px;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--premium-accent);
        white-space: nowrap;
    }

    .section-line {
        height: 1px;
        background: var(--premium-border);
        flex: 1;
    }

    .editorial-match-card {
        background: white;
        border: 1px solid var(--premium-border);
        border-radius: 32px;
        overflow: hidden;
        box-shadow: var(--soft-shadow);
        display: flex;
        flex-direction: column;
    }

    .match-body {
        padding: 60px 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 80px;
        background: radial-gradient(circle at center, #ffffff 0%, #f8fafc 100%);
        position: relative;
    }

    .team-block {
        text-align: center;
        flex: 1;
        transition: var(--transition);
    }

    .team-logo-frame {
        width: 120px;
        height: 120px;
        margin: 0 auto 20px;
        background: white;
        border-radius: 50%;
        padding: 15px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        border: 1px solid var(--premium-border);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .team-logo-frame img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .team-name-small {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--premium-accent);
    }

    .vs-capsule {
        padding: 10px 25px;
        background: var(--premium-accent);
        color: white;
        border-radius: 100px;
        font-weight: 900;
        font-size: 1.2rem;
        letter-spacing: 2px;
        box-shadow: 0 10px 20px rgba(10, 36, 99, 0.2);
    }

    .match-footer {
        padding: 30px 40px;
        background: #f1f5f9;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        border-top: 1px solid var(--premium-border);
    }

    .info-group {
        text-align: center;
    }

    .info-label {
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--premium-text-muted);
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-bottom: 5px;
    }

    .info-data {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--premium-text);
    }

    .empty-state-light {
        padding: 100px 40px;
        text-align: center;
        background: white;
        border: 2px dashed var(--premium-border);
        border-radius: 32px;
    }

    .empty-state-light i {
        font-size: 3rem;
        color: var(--premium-border);
        margin-bottom: 20px;
    }

    /* Animations */
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .reveal {
        animation: slideUp 0.6s cubic-bezier(0.23, 1, 0.32, 1) forwards;
        opacity: 0;
    }

    .d-1 { animation-delay: 0.1s; }
    .d-2 { animation-delay: 0.2s; }
    .d-3 { animation-delay: 0.3s; }
    .d-4 { animation-delay: 0.4s; }
    .d-5 { animation-delay: 0.5s; }

    @media (max-width: 992px) {
        .match-body {
            flex-direction: column;
            gap: 40px;
            padding: 40px 20px;
        }
        .match-footer {
            grid-template-columns: 1fr;
            padding: 30px 20px;
        }
        .hero-title {
            font-size: 2rem;
        }
    }
</style>

<div class="dashboard-container">
    <!-- Header Section -->
    <div class="dashboard-hero reveal">
        <span class="hero-label">Dasbor Taktis</span>
        <h1 class="hero-title">Pusat Manajemen Tim</h1>
        <p class="hero-description">Pantau performa skuad, jadwal pertandingan mendatang, dan staf manajemen. Pembaruan waktu nyata untuk musim aktif saat ini.</p>
    </div>

    <!-- Quick Stats -->
    <div class="premium-stats-grid">
        <!-- Team Identity -->
        <div class="premium-card reveal d-1">
            <div class="card-top">
                <div class="card-icon-box">
                    <?php if (!empty($team_logo) && file_exists('../images/teams/' . $team_logo)): ?>
                        <img src="../images/teams/<?php echo htmlspecialchars($team_logo); ?>" alt="Tim" style="width: 32px; height: 32px; object-fit: contain;">
                    <?php else: ?>
                        <i class="fas fa-shield"></i>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="card-value" style="font-size: 1.5rem;"><?php echo htmlspecialchars($team_name); ?></div>
                <div class="card-label">Tim Utama</div>
            </div>
        </div>

        <!-- Roster Count -->
        <div class="premium-card reveal d-2">
            <div class="card-top">
                <div class="card-icon-box"><i class="fas fa-users"></i></div>
            </div>
            <div>
                <div class="card-value"><?php echo $player_count; ?></div>
                <div class="card-label">Total Pemain</div>
            </div>
        </div>

        <!-- Staff Count -->
        <div class="premium-card reveal d-3">
            <div class="card-top">
                <div class="card-icon-box"><i class="fas fa-user-tie"></i></div>
            </div>
            <div>
                <div class="card-value"><?php echo $staff_count; ?></div>
                <div class="card-label">Ofisial Tim</div>
            </div>
        </div>

        <!-- Victory Stats -->
        <div class="premium-card reveal d-4">
            <div class="card-top">
                <div class="card-icon-box" style="background: #ecfdf5; color: #059669;"><i class="fas fa-trophy"></i></div>
            </div>
            <div>
                <div class="card-value"><?php echo $wins; ?></div>
                <div class="card-label">Total Kemenangan</div>
            </div>
        </div>
    </div>

    <!-- Additional Stats in row -->
    <div class="premium-stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
         <!-- Losses -->
         <div class="premium-card reveal d-5">
            <div class="card-top">
                <div class="card-icon-box" style="background: #fef2f2; color: #dc2626;"><i class="fas fa-times-circle"></i></div>
            </div>
            <div>
                <div class="card-value"><?php echo $losses; ?></div>
                <div class="card-label">Total Kekalahan</div>
            </div>
        </div>

        <!-- Draws -->
        <div class="premium-card reveal d-5" style="animation-delay: 0.5s;">
            <div class="card-top">
                <div class="card-icon-box" style="background: #f8fafc; color: #64748b;"><i class="fas fa-handshake"></i></div>
            </div>
            <div>
                <div class="card-value"><?php echo $draws; ?></div>
                <div class="card-label">Hasil Seri</div>
            </div>
        </div>
    </div>

    <!-- Match Spotlight -->
    <div class="match-spotlight reveal" style="animation-delay: 0.6s;">
        <div class="section-header">
            <h2 class="section-title">Pertandingan Mendatang</h2>
            <div class="section-line"></div>
        </div>

        <?php if ($next_match): 
            $match_date = new DateTime($next_match['match_date']);
        ?>
            <div class="editorial-match-card">
                <div class="match-body">
                    <div class="team-block">
                        <div class="team-logo-frame">
                            <?php if ($next_match['team1_logo'] && file_exists('../images/teams/' . $next_match['team1_logo'])): ?>
                                <img src="../images/teams/<?php echo htmlspecialchars($next_match['team1_logo']); ?>" alt="Team 1">
                            <?php else: ?>
                                <i class="fas fa-shield" style="font-size: 2rem; color: #cbd5e1;"></i>
                            <?php endif; ?>
                        </div>
                        <h4 class="team-name-small"><?php echo htmlspecialchars($next_match['team1_name']); ?></h4>
                    </div>

                    <div class="vs-capsule">VS</div>

                    <div class="team-block">
                        <div class="team-logo-frame">
                            <?php if ($next_match['team2_logo'] && file_exists('../images/teams/' . $next_match['team2_logo'])): ?>
                                <img src="../images/teams/<?php echo htmlspecialchars($next_match['team2_logo']); ?>" alt="Team 2">
                            <?php else: ?>
                                <i class="fas fa-shield" style="font-size: 2rem; color: #cbd5e1;"></i>
                            <?php endif; ?>
                        </div>
                        <h4 class="team-name-small"><?php echo htmlspecialchars($next_match['team2_name']); ?></h4>
                    </div>
                </div>

                <div class="match-footer">
                    <div class="info-group">
                        <div class="info-label">Tanggal Kickoff</div>
                        <div class="info-data"><?php echo $match_date->format('l, d M Y'); ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Waktu Pertandingan</div>
                        <div class="info-data"><?php echo $match_date->format('H:i'); ?> WIB</div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Lokasi Stadion</div>
                        <div class="info-data"><?php echo htmlspecialchars($next_match['location'] ?: 'Akan diumumkan'); ?></div>
                    </div>
                </div>
                
                <div style="padding: 15px; text-align: center; background: var(--premium-accent); color: white; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">
                    <?php echo htmlspecialchars($next_match['event_name'] ?: 'Pertandingan Liga Resmi'); ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state-light">
                <i class="far fa-calendar-times"></i>
                <h3 style="font-weight: 800; color: var(--premium-accent); margin-bottom: 5px;">Tidak Ada Pertandingan Terjadwal</h3>
                <p style="color: var(--premium-text-muted);">Tim saat ini sedang dalam masa istirahat di antara jadwal pertandingan.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
