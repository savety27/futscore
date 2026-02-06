<?php
require_once 'includes/header.php';

// Get event ID
$event_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($event_id <= 0) {
    header("Location: event.php");
    exit;
}

// Database connection
$conn = $db->getConnection();

// Query for Event Data
$query = "SELECT 
    c.*,
    t1.name as challenger_name, 
    t1.logo as challenger_logo,
    t1.sport_type as challenger_sport,
    t2.name as opponent_name, 
    t2.logo as opponent_logo,
    v.name as venue_name,
    v.location as venue_location,
    v.capacity as venue_capacity,
    w.name as winner_team_name
FROM challenges c
LEFT JOIN teams t1 ON c.challenger_id = t1.id
LEFT JOIN teams t2 ON c.opponent_id = t2.id
LEFT JOIN venues v ON c.venue_id = v.id
LEFT JOIN teams w ON c.winner_team_id = w.id
WHERE c.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();

if (!$event) {
    header("Location: event.php");
    exit;
}

// Helper Functions
function getStatusBadge($status) {
    $status = strtolower($status ?? '');
    $badges = [
        'open' => '<span class="status-badge status-open">Open</span>',
        'accepted' => '<span class="status-badge status-accepted">Accepted</span>',
        'completed' => '<span class="status-badge status-completed">Completed</span>',
        'rejected' => '<span class="status-badge status-rejected">Rejected</span>',
        'expired' => '<span class="status-badge status-expired">Expired</span>',
        'cancelled' => '<span class="status-badge status-cancelled">Cancelled</span>'
    ];
    return $badges[$status] ?? '<span class="status-badge status-default">' . ucfirst($status) . '</span>';
}

function getMatchStatusBadge($match_status) {
    $match_status = strtolower($match_status ?? '');
    $badges = [
        'scheduled' => '<span class="status-badge match-scheduled">Scheduled</span>',
        'ongoing' => '<span class="status-badge match-ongoing">Ongoing</span>',
        'completed' => '<span class="status-badge match-completed">Completed</span>',
        'postponed' => '<span class="status-badge match-postponed">Postponed</span>',
        'cancelled' => '<span class="status-badge match-cancelled">Cancelled</span>',
        'abandoned' => '<span class="status-badge match-abandoned">Abandoned</span>'
    ];
    return $badges[$match_status] ?? '<span class="status-badge match-default">' . ucfirst($match_status ?? 'Not Set') . '</span>';
}

// Build timeline data
$timeline_events = [];

// Event Created
if ($event['created_at']) {
    $timeline_events[] = [
        'icon' => 'fa-plus-circle',
        'event' => 'Challenge Dibuat',
        'time' => $event['created_at'],
        'color' => '#3498db'
    ];
}

// Status changes based on current status
if ($event['status'] == 'accepted' && $event['updated_at'] != $event['created_at']) {
    $timeline_events[] = [
        'icon' => 'fa-check-circle',
        'event' => 'Challenge Diterima',
        'time' => $event['updated_at'],
        'color' => '#2ecc71'
    ];
}

// Match scheduled (challenge date)
if ($event['challenge_date']) {
    $timeline_events[] = [
        'icon' => 'fa-calendar-check',
        'event' => 'Pertandingan Dijadwalkan',
        'time' => $event['challenge_date'],
        'color' => '#9b59b6'
    ];
}

// Result entered
if ($event['result_entered_at']) {
    $timeline_events[] = [
        'icon' => 'fa-futbol',
        'event' => 'Hasil Pertandingan Diinput',
        'time' => $event['result_entered_at'],
        'color' => '#f39c12'
    ];
}

// Match completed
if ($event['status'] == 'completed') {
    $timeline_events[] = [
        'icon' => 'fa-flag-checkered',
        'event' => 'Pertandingan Selesai',
        'time' => $event['updated_at'],
        'color' => '#27ae60'
    ];
}

// Expiry date
if ($event['expiry_date']) {
    $timeline_events[] = [
        'icon' => 'fa-hourglass-end',
        'event' => 'Batas Waktu Penerimaan',
        'time' => $event['expiry_date'],
        'color' => '#e74c3c'
    ];
}

// Sort timeline by time
usort($timeline_events, function($a, $b) {
    return strtotime($a['time']) - strtotime($b['time']);
});
?>

<style>
/* CSS Reset and Base for the section */
.event-detail-section {
    padding: 40px 0;
    color: #fff;
}

/* Header Styles */
.detail-header {
    margin-bottom: 30px;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #34495e, #2c3e50);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 20px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.back-btn:hover {
    background: linear-gradient(135deg, #2c3e50, #1a252f);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
}

.detail-title {
    color: #fff;
    margin-bottom: 15px;
}

.detail-title h1 {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 32px;
    margin: 0;
}

.event-code {
    font-size: 18px;
    color: #FFD700;
    font-weight: 700;
    font-family: 'Courier New', monospace;
    margin-bottom: 15px;
    padding: 8px 15px;
    background: rgba(255, 215, 0, 0.1);
    border-left: 4px solid #FFD700;
    display: inline-block;
    border-radius: 4px;
}

.badge-container {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 15px;
}

/* Main Event Card */
.event-main-card {
    background: #fff;
    border-radius: 12px;
    padding: 35px;
    margin-bottom: 30px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    border: 1px solid #e0e0e0;
}

/* Teams Display */
.teams-display {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 40px 0;
    padding: 30px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 12px;
    border: 2px solid #dee2e6;
}

.team-card {
    text-align: center;
    flex: 1;
}

.team-logo-container {
    width: 140px;
    height: 140px;
    margin: 0 auto 20px;
    position: relative;
}

.team-logo-large {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: contain;
    border: 4px solid #fff;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    padding: 10px;
}

.team-logo-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #e9ecef, #dee2e6);
    border: 4px solid #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.team-name {
    font-size: 22px;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 8px;
}

.team-sport {
    font-size: 14px;
    color: #7f8c8d;
    font-weight: 500;
    padding: 4px 12px;
    background: rgba(127, 140, 141, 0.1);
    border-radius: 12px;
    display: inline-block;
}

.vs-center {
    text-align: center;
    padding: 0 40px;
}

.vs-text {
    font-size: 36px;
    font-weight: 900;
    color: #c0392b;
    background: #fff;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 4px solid #c0392b;
    margin: 0 auto;
    box-shadow: 0 4px 12px rgba(192, 57, 43, 0.3);
}

.score-display {
    margin-top: 25px;
    font-size: 56px;
    font-weight: 900;
    color: #2c3e50;
    font-family: 'Arial', sans-serif;
    letter-spacing: 4px;
}

.score-separator {
    color: #c0392b;
    margin: 0 8px;
}

/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 8px 18px;
    color: white;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Challenge Status Colors */
.status-open { background: linear-gradient(135deg, #3498db, #2980b9); }
.status-accepted { background: linear-gradient(135deg, #2ecc71, #27ae60); }
.status-completed { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
.status-rejected { background: linear-gradient(135deg, #e74c3c, #c0392b); }
.status-expired { background: linear-gradient(135deg, #95a5a6, #7f8c8d); }
.status-cancelled { background: linear-gradient(135deg, #34495e, #2c3e50); }
.status-default { background: linear-gradient(135deg, #bdc3c7, #95a5a6); }

/* Match Status Colors */
.match-scheduled { background: linear-gradient(135deg, #3498db, #2980b9); }
.match-ongoing { background: linear-gradient(135deg, #f39c12, #e67e22); }
.match-completed { background: linear-gradient(135deg, #2ecc71, #27ae60); }
.match-postponed { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
.match-cancelled { background: linear-gradient(135deg, #e74c3c, #c0392b); }
.match-abandoned { background: linear-gradient(135deg, #7f8c8d, #95a5a6); }
.match-default { background: linear-gradient(135deg, #ecf0f1, #bdc3c7); color: #666; }

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 24px;
    margin: 35px 0;
}

.info-card {
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    border-radius: 10px;
    padding: 25px;
    border: 2px solid #e9ecef;
    box-shadow: 0 3px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.info-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.12);
    border-color: #dee2e6;
}

.info-title {
    font-size: 17px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 3px solid #c0392b;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-title i {
    color: #c0392b;
    font-size: 18px;
}

.info-item {
    margin-bottom: 18px;
}

.info-item:last-child {
    margin-bottom: 0;
}

.info-label {
    font-size: 13px;
    color: #7f8c8d;
    font-weight: 600;
    margin-bottom: 6px;
    display: block;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-size: 15px;
    color: #2c3e50;
    font-weight: 600;
    line-height: 1.5;
}

/* Winner Display */
.winner-display {
    background: linear-gradient(135deg, #fff9e6, #fffaf0);
    border: 3px solid #f39c12;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    margin: 35px 0;
    box-shadow: 0 6px 16px rgba(243, 156, 18, 0.2);
}

.winner-title {
    font-size: 18px;
    color: #856404;
    font-weight: 700;
    margin-bottom: 18px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.winner-name {
    font-size: 36px;
    font-weight: 900;
    color: #d35400;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 18px;
}

.winner-name i {
    color: #f39c12;
    font-size: 32px;
    animation: trophy-bounce 0.5s ease-in-out infinite alternate;
}

@keyframes trophy-bounce {
    from { transform: translateY(0); }
    to { transform: translateY(-5px); }
}

/* Draw Display */
.draw-display {
    background: linear-gradient(135deg, #e8f4fc, #d9edf7);
    border: 3px solid #3498db;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    margin: 35px 0;
    box-shadow: 0 6px 16px rgba(52, 152, 219, 0.2);
}

.draw-title {
    font-size: 18px;
    color: #0c5460;
    font-weight: 700;
    margin-bottom: 18px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.draw-text {
    font-size: 36px;
    font-weight: 900;
    color: #2980b9;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.draw-text i {
    font-size: 32px;
}

/* Notes Section */
.notes-section {
    background: linear-gradient(135deg, #fff5f5, #ffffff);
    border-left: 5px solid #c0392b;
    padding: 25px;
    border-radius: 8px;
    margin: 30px 0;
    box-shadow: 0 3px 8px rgba(0,0,0,0.08);
}

.notes-title {
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
}

.notes-title i {
    color: #c0392b;
}

.notes-content {
    color: #555;
    line-height: 1.8;
    white-space: pre-wrap;
    font-size: 14px;
}

/* Timeline */
.timeline-section {
    margin: 40px 0;
    padding: 30px;
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    border-radius: 12px;
    border: 2px solid #e9ecef;
}

.timeline-header {
    font-size: 20px;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 30px;
    padding-bottom: 15px;
    border-bottom: 3px solid #c0392b;
    display: flex;
    align-items: center;
    gap: 12px;
}

.timeline-header i {
    color: #c0392b;
}

.timeline {
    position: relative;
    padding-left: 40px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 18px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(to bottom, #c0392b, #e74c3c);
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #e9ecef;
}

.timeline-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.timeline-icon {
    position: absolute;
    left: -40px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
    flex-shrink: 0;
    box-shadow: 0 3px 8px rgba(0,0,0,0.2);
    border: 3px solid #fff;
}

.timeline-content {
    padding-left: 15px;
}

.timeline-event {
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 6px;
    font-size: 15px;
}

.timeline-time {
    font-size: 13px;
    color: #ffffff;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 6px;
}

.timeline-time i {
    font-size: 12px;
}

/* Responsive */
@media (max-width: 768px) {
    .teams-display {
        flex-direction: column;
        gap: 30px;
        padding: 20px;
    }
    
    .vs-center {
        padding: 20px 0;
        order: 2;
    }
    
    .team-card {
        width: 100%;
    }
    
    .team-logo-container {
        width: 120px;
        height: 120px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .score-display {
        font-size: 42px;
    }
    
    .winner-name {
        font-size: 26px;
    }
    
    .draw-text {
        font-size: 26px;
    }
    
    .event-main-card {
        padding: 20px;
    }
    
    .timeline {
        padding-left: 35px;
    }
    
    .timeline::before {
        left: 16px;
    }
    
    .timeline-icon {
        left: -35px;
        width: 36px;
        height: 36px;
        font-size: 14px;
    }
}
</style>

<div class="container">
    <div class="event-detail-section">
        <!-- Header -->
        <div class="detail-header">
            <a href="event.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Event
            </a>
            
            <div class="detail-title">
                <h1>
                    <i class="fas fa-trophy"></i> Detail Event Pertandingan
                </h1>
            </div>
            
            <div class="event-code">
                <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($event['challenge_code'] ?? ''); ?>
            </div>
            
            <div class="badge-container">
                <?php echo getStatusBadge($event['status']); ?>
                <?php echo getMatchStatusBadge($event['match_status']); ?>
            </div>
        </div>

        <!-- Main Event Card -->
        <div class="event-main-card">
            <!-- Teams Display -->
            <div class="teams-display">
                <div class="team-card">
                    <div class="team-logo-container">
                        <?php if (!empty($event['challenger_logo'])): ?>
                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($event['challenger_logo']); ?>" 
                                 class="team-logo-large"
                                 alt="<?php echo htmlspecialchars($event['challenger_name'] ?? ''); ?>">
                        <?php else: ?>
                            <div class="team-logo-placeholder">
                                <i class="fas fa-shield-alt" style="font-size: 56px; color: #95a5a6;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="team-name"><?php echo htmlspecialchars($event['challenger_name'] ?? ''); ?></div>
                </div>
                
                <div class="vs-center">
                    <div class="vs-text">VS</div>
                    <?php if ($event['challenger_score'] !== null && $event['opponent_score'] !== null): ?>
                        <div class="score-display">
                            <?php echo $event['challenger_score']; ?><span class="score-separator">:</span><?php echo $event['opponent_score']; ?>
                        </div>
                    <?php else: ?>
                        <div class="score-display" style="font-size: 20px; color: #95a5a6; font-style: italic;">
                            Belum dimainkan
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="team-card">
                    <div class="team-logo-container">
                        <?php if (!empty($event['opponent_logo'])): ?>
                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($event['opponent_logo']); ?>" 
                                 class="team-logo-large"
                                 alt="<?php echo htmlspecialchars($event['opponent_name'] ?? ''); ?>">
                        <?php else: ?>
                            <div class="team-logo-placeholder">
                                <i class="fas fa-shield-alt" style="font-size: 56px; color: #95a5a6;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="team-name"><?php echo htmlspecialchars($event['opponent_name'] ?? 'TBD'); ?></div>
                </div>
            </div>

            <!-- Winner/Draw Display -->
            <?php if ($event['winner_team_name']): ?>
                <div class="winner-display">
                    <div class="winner-title">🏆 Pemenang Pertandingan 🏆</div>
                    <div class="winner-name">
                        <i class="fas fa-trophy"></i>
                        <?php echo htmlspecialchars($event['winner_team_name'] ?? ''); ?>
                        <i class="fas fa-trophy"></i>
                    </div>
                </div>
            <?php elseif ($event['challenger_score'] !== null && $event['opponent_score'] !== null && $event['challenger_score'] == $event['opponent_score']): ?>
                <div class="draw-display">
                    <div class="draw-title">Pertandingan Berakhir Seri</div>
                    <div class="draw-text">
                        <i class="fas fa-handshake"></i>
                        DRAW
                        <i class="fas fa-handshake"></i>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Information Grid -->
            <div class="info-grid">
                <!-- Event Information -->
                <div class="info-card">
                    <div class="info-title">
                        <i class="fas fa-info-circle"></i> Informasi Event
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-running"></i> Cabang Olahraga</span>
                        <div class="info-value"><?php echo htmlspecialchars($event['sport_type'] ?? ''); ?></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-calendar-alt"></i> Tanggal & Waktu</span>
                        <div class="info-value"><?php echo formatDateTime($event['challenge_date']); ?></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-hourglass-end"></i> Batas Penerimaan</span>
                        <div class="info-value" style="color: #e74c3c;"><?php echo formatDateTime($event['expiry_date']); ?></div>
                    </div>
                </div>

                <!-- Venue Information -->
                <div class="info-card">
                    <div class="info-title">
                        <i class="fas fa-map-marker-alt"></i> Informasi Venue
                    </div>
                    <?php if ($event['venue_name']): ?>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-building"></i> Nama Venue</span>
                            <div class="info-value"><?php echo htmlspecialchars($event['venue_name'] ?? ''); ?></div>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-location-arrow"></i> Lokasi</span>
                            <div class="info-value"><?php echo htmlspecialchars($event['venue_location'] ?? ''); ?></div>
                        </div>
                        <?php if ($event['venue_capacity']): ?>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-users"></i> Kapasitas</span>
                            <div class="info-value"><?php echo number_format($event['venue_capacity']); ?> orang</div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="info-item">
                            <div class="info-value" style="color: #95a5a6; font-style: italic;">
                                <i class="fas fa-question-circle"></i> Venue belum ditentukan
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Status Information -->
                <div class="info-card">
                    <div class="info-title">
                        <i class="fas fa-flag"></i> Status Pertandingan
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-tasks"></i> Challenge Status</span>
                        <div class="info-value"><?php echo getStatusBadge($event['status']); ?></div>
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-play-circle"></i> Match Status</span>
                        <div class="info-value"><?php echo getMatchStatusBadge($event['match_status']); ?></div>
                    </div>
                    <?php if ($event['result_entered_at']): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-check-double"></i> Hasil Diinput</span>
                        <div class="info-value" style="color: #27ae60;">
                            <?php echo formatDateTime($event['result_entered_at']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Match Details (only if scores exist) -->
                <?php if ($event['challenger_score'] !== null && $event['opponent_score'] !== null): ?>
                <div class="info-card">
                    <div class="info-title">
                        <i class="fas fa-futbol"></i> Detail Pertandingan
                    </div>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-chart-line"></i> Skor Akhir</span>
                        <div class="info-value" style="font-size: 24px; font-weight: 900; color: #c0392b;">
                            <?php echo $event['challenger_score']; ?> : <?php echo $event['opponent_score']; ?>
                        </div>
                    </div>
                    <?php if ($event['match_duration']): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-clock"></i> Durasi</span>
                        <div class="info-value"><?php echo htmlspecialchars($event['match_duration']); ?> menit</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($event['match_official']): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-user-tie"></i> Wasit</span>
                        <div class="info-value"><?php echo htmlspecialchars($event['match_official']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Notes -->
            <?php if (!empty($event['notes'])): ?>
            <div class="notes-section">
                <div class="notes-title">
                    <i class="fas fa-sticky-note"></i> Catatan Challenge
                </div>
                <div class="notes-content">
                    <?php echo nl2br(htmlspecialchars($event['notes'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($event['match_notes'])): ?>
            <div class="notes-section">
                <div class="notes-title">
                    <i class="fas fa-clipboard"></i> Catatan Pertandingan
                </div>
                <div class="notes-content">
                    <?php echo nl2br(htmlspecialchars($event['match_notes'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Timeline -->
            <div class="timeline-section">
                <div class="timeline-header">
                    <i class="fas fa-history"></i> Timeline Event
                </div>
                
                <div class="timeline">
                    <?php foreach ($timeline_events as $item): ?>
                    <div class="timeline-item">
                        <div class="timeline-icon" style="background: <?php echo $item['color']; ?>;">
                            <i class="fas <?php echo $item['icon']; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <div class="timeline-event"><?php echo $item['event']; ?></div>
                            <div class="timeline-time">
                                <i class="fas fa-calendar"></i>
                                <?php echo formatDateTime($item['time']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>