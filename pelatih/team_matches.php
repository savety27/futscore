<?php
$page_title = 'Riwayat Pertandingan Team';
$current_page = 'team';
require_once 'config/database.php';
require_once 'includes/header.php';

$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$team_info = null;

if ($team_id) {
    // Basic team info
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$team_info) {
    echo "<div class='card'><div class='alert alert-danger'>Team tidak ditemukan.</div><a href='team.php' class='btn-secondary'>Kembali ke Daftar Team</a></div>";
    require_once 'includes/footer.php';
    exit;
}

// Update page title
$page_title = htmlspecialchars($team_info['name'] ?? '') . ' - Pertandingan';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Query Matches (from challenges table)
$base_query = "SELECT c.*, 
    t1.name as team1_name, t1.logo as team1_logo, 
    t2.name as team2_name, t2.logo as team2_logo,
    v.name as venue_name
    FROM challenges c
    LEFT JOIN teams t1 ON c.challenger_id = t1.id
    LEFT JOIN teams t2 ON c.opponent_id = t2.id
    LEFT JOIN venues v ON c.venue_id = v.id
    WHERE (c.challenger_id = ? OR c.opponent_id = ?)
    AND (c.status = 'accepted' OR c.status = 'completed')";

$count_query = "SELECT COUNT(*) as total FROM challenges WHERE (challenger_id = ? OR opponent_id = ?) AND (status = 'accepted' OR status = 'completed')";

// Add ordering
$base_query .= " ORDER BY c.challenge_date DESC";

$total_data = 0;
$total_pages = 1;
$matches = [];

try {
    // Count
    $stmt = $conn->prepare($count_query);
    $stmt->execute([$team_id, $team_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_data = $result['total'];
    $total_pages = ceil($total_data / $limit);
    
    // Validate Page
    if ($page < 1) $page = 1;
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
    $offset = ($page - 1) * $limit;
    
    // Fetch Data
    $query = $base_query . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(1, $team_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $team_id, PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->bindValue(4, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="card">
    <div class="section-header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <?php if (!empty($team_info['logo'])): ?>
                <img src="../images/teams/<?php echo $team_info['logo']; ?>" alt="Logo" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.onerror=null; this.src='../images/teams/default-team.png'">
            <?php endif; ?>
            <div>
                <h2 class="section-title"><?php echo htmlspecialchars($team_info['name'] ?? ''); ?> <span style="font-weight: normal; font-size: 0.8em; color: var(--gray);">Riwayat Pertandingan</span></h2>
            </div>
        </div>
        <a href="team.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar Team
        </a>
    </div>

    <?php if (empty($matches)): ?>
        <div class="empty-state">
            <i class="fas fa-futbol"></i>
            <p>Tidak ada pertandingan ditemukan untuk team ini.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Event</th>
                        <th>Lawan</th>
                        <th style="text-align: center;">Hasil</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($matches as $match): 
                        $is_team1 = ($match['challenger_id'] == $team_id);
                        $my_name = $is_team1 ? $match['team1_name'] : $match['team2_name'];
                        $opponent_name = $is_team1 ? $match['team2_name'] : $match['team1_name'];
                        $opponent_logo = $is_team1 ? $match['team2_logo'] : $match['team1_logo'];
                        
                        $score_display = '-';
                        $result_class = 'neutral';
                        
                        if ($match['match_status'] == 'completed') {
                            $my_score = $is_team1 ? $match['challenger_score'] : $match['opponent_score'];
                            $opp_score = $is_team1 ? $match['opponent_score'] : $match['challenger_score'];
                            $score_display = $my_score . ' - ' . $opp_score;
                            
                            if ($my_score > $opp_score) $result_class = 'win';
                            elseif ($my_score < $opp_score) $result_class = 'loss';
                            else $result_class = 'draw';
                        }
                    ?>
                    <tr>
                        <td class="date-cell">
                             <div><?php echo date('d M Y', strtotime($match['challenge_date'])); ?></div>
                             <small style="color: var(--gray);"><?php echo date('H:i', strtotime($match['challenge_date'])); ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($match['sport_type'] ?? '-'); ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="../images/teams/<?php echo $opponent_logo; ?>" alt="Opponent" style="width: 30px; height: 30px; border-radius: 50%; object-fit: contain; background: #eee;" onerror="this.onerror=null; this.src='../images/teams/default-team.png'">
                                <span><?php echo htmlspecialchars($opponent_name ?? ''); ?></span>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <span class="score-badge <?php echo $result_class; ?>">
                                <?php echo $score_display; ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <span class="status-match <?php echo $match['match_status'] ?: strtolower($match['status']); ?>">
                                <?php 
                                    $m_status = $match['match_status'] ?: $match['status'];
                                    $m_status_map = ['completed' => 'Selesai', 'scheduled' => 'Terjadwal', 'live' => 'Langsung', 'accepted' => 'Diterima'];
                                    echo $m_status_map[$m_status ?? ''] ?? ucfirst($m_status ?? ''); 
                                ?>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <a href="../match.php?id=<?php echo $match['id']; ?>&source=challenge" class="btn-view" target="_blank" title="Lihat Detail Pertandingan & Lineup">
                                <i class="fas fa-eye"></i> Detail
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $page - 1; ?>" class="page-link">&laquo; Seb</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $i; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $page + 1; ?>" class="page-link">Sel &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<style>
.main {
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
}

/* Reused & New Styles */
.empty-state { text-align: center; padding: 50px 20px; color: var(--gray); }
.empty-state i { font-size: 48px; margin-bottom: 20px; color: #ddd; }
.btn-secondary { background: #e0e0e0; color: #333; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; transition: all 0.2s; }
.btn-secondary:hover { background: #d5d5d5; color: #000; }

.data-table { width: 100%; border-collapse: separate; border-spacing: 0; background: white; border-radius: 12px; overflow: hidden; }
.data-table thead { background: linear-gradient(135deg, var(--primary), #1a365d); }
.data-table th { padding: 15px 12px; text-align: left; font-weight: 600; color: white; border: none; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
.data-table td { padding: 15px 12px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; }
.data-table tbody tr {
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    position: relative;
    will-change: transform;
}
.data-table tbody tr:hover,
.data-table tbody tr:focus-within {
    background: #eef5ff;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(10, 36, 99, 0.18), 0 0 0 1px rgba(76, 138, 255, 0.35);
    z-index: 2;
}

.score-badge { display: inline-block; padding: 6px 12px; border-radius: 8px; font-weight: bold; font-size: 14px; min-width: 60px; text-align: center; }
.score-badge.win { background: #4CAF50; color: white; }
.score-badge.loss { background: #F44336; color: white; }
.score-badge.draw { background: #FF9800; color: white; }
.score-badge.neutral { background: #e0e0e0; color: #555; }

.status-match { font-size: 11px; text-transform: uppercase; font-weight: 600; padding: 4px 8px; border-radius: 4px; }
.status-match.completed { color: #4CAF50; background: #e8f5e9; }
.status-match.scheduled { color: #2196F3; background: #e3f2fd; }
.status-match.live { color: #F44336; background: #ffebee; animation: pulse 2s infinite; }

.btn-view { display: inline-block; padding: 6px 12px; background: var(--primary); color: white; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; transition: transform 0.2s; }
.btn-view:hover { transform: translateY(-2px); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }

@media (max-width: 768px) {
    .data-table tbody tr:hover,
    .data-table tbody tr:focus-within {
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(10, 36, 99, 0.14), 0 0 0 1px rgba(76, 138, 255, 0.28);
    }
}

@media (hover: none) {
    .data-table tbody tr:hover,
    .data-table tbody tr:focus-within {
        transform: none;
        box-shadow: none;
        background: #f8f9fa;
    }
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.6; }
    100% { opacity: 1; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
