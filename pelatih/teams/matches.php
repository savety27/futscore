<?php
$page_title = 'Riwayat Pertandingan Team';
$current_page = 'team';
require_once '../config/database.php';
require_once '../includes/header.php';
?>
<link rel="stylesheet" href="css/teams.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/teams.css'); ?>">
<?php
$team_id = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$team_info = null;

if ($team_id) {
    // Basic team info
    $stmt = $conn->prepare("SELECT * FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$team_info) {
    echo "<div class='card'><div class='alert alert-danger'>Team tidak ditemukan.</div><a href='index.php' class='btn-secondary'>Kembali ke Daftar Team</a></div>";
    require_once '../includes/footer.php';
    exit;
}

// Update page title
$page_title = htmlspecialchars($team_info['name'] ?? '') . ' - Pertandingan';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Soft-compat check: some deployments may not have challenges.event_id yet.
$can_join_event_name = false;
try {
    $has_event_id = $conn->query("SHOW COLUMNS FROM `challenges` LIKE 'event_id'")->fetch(PDO::FETCH_ASSOC);
    $has_events_table = $conn->query("SHOW TABLES LIKE 'events'")->fetch(PDO::FETCH_ASSOC);
    $can_join_event_name = !empty($has_event_id) && !empty($has_events_table);
} catch (Throwable $e) {
    $can_join_event_name = false;
}

// Query Matches (from challenges table)
$event_select = $can_join_event_name ? "e.name as event_name," : "NULL as event_name,";
$event_join = $can_join_event_name ? "LEFT JOIN events e ON c.event_id = e.id" : "";
$base_query = "SELECT c.*, 
    {$event_select}
    t1.name as team1_name, t1.logo as team1_logo, 
    t2.name as team2_name, t2.logo as team2_logo,
    v.name as venue_name
    FROM challenges c
    {$event_join}
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

<div class="teams-container">
    <header class="dashboard-hero reveal">
        <div class="hero-content">
            <span class="hero-label">Pertandingan Team</span>
            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 16px;">
                <?php if (!empty($team_info['logo'])): ?>
                    <img src="../../images/teams/<?php echo $team_info['logo']; ?>" alt="Logo" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 2px solid var(--heritage-border);" onerror="this.onerror=null; this.src='../../images/teams/default-team.png'">
                <?php endif; ?>
                <h1 class="hero-title" style="margin: 0;"><?php echo htmlspecialchars($team_info['name'] ?? ''); ?></h1>
            </div>
            <p class="hero-description">Riwayat lengkap pertandingan yang diikuti oleh tim ini.</p>
        </div>
        <div class="hero-actions" style="display: flex; flex-direction: column; gap: 12px; align-items: flex-end;">
            <a href="index.php" class="btn-premium btn-export" style="background: white;">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Team
            </a>
        </div>
    </header>

    <div class="reveal d-2">

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
                        <th>Kategori</th>
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
                            <?php echo htmlspecialchars($match['event_name'] ?? '-'); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($match['sport_type'] ?? '-'); ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="../../images/teams/<?php echo $opponent_logo; ?>" alt="Opponent" style="width: 30px; height: 30px; border-radius: 50%; object-fit: contain; background: #eee;" onerror="this.onerror=null; this.src='../../images/teams/default-team.png'">
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
                            <a href="../../match.php?id=<?php echo $match['id']; ?>&source=challenge<?php echo ((int)($match['event_id'] ?? 0) > 0) ? '&event_id=' . (int)$match['event_id'] : ''; ?>" class="btn-view" target="_blank" title="Lihat Detail Pertandingan & Lineup">
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
                <a href="?team_id=<?php echo $team_id; ?>&page=1"><i class="fas fa-angle-double-left"></i></a>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $page - 1; ?>"><i class="fas fa-angle-left"></i></a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1) {
                echo '<span class="pagination-dots">...</span>';
            }
            
            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; 
            
            if ($end_page < $total_pages) {
                echo '<span class="pagination-dots">...</span>';
            }
            ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $page + 1; ?>"><i class="fas fa-angle-right"></i></a>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $total_pages; ?>"><i class="fas fa-angle-double-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
    </div>
</div>

<style>
/* Specific matches styles overriding the teams stylesheet if needed */
.score-badge { display: inline-flex; align-items: center; justify-content: center; padding: 6px 14px; border-radius: 8px; font-weight: 700; font-size: 14px; min-width: 65px; letter-spacing: 0.5px; box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
.score-badge.win { background: linear-gradient(135deg, #10b981, #059669); color: white; border: 1px solid rgba(16, 185, 129, 0.4); }
.score-badge.loss { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; border: 1px solid rgba(239, 68, 68, 0.4); }
.score-badge.draw { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border: 1px solid rgba(245, 158, 11, 0.4); }
.score-badge.neutral { background: var(--heritage-bg); color: var(--heritage-text); border: 1px solid var(--heritage-border); }

.status-match { font-size: 11px; text-transform: uppercase; font-weight: 700; padding: 5px 10px; border-radius: 4px; letter-spacing: 0.5px; }
.status-match.completed { color: #059669; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); }
.status-match.scheduled { color: #2563eb; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); }
.status-match.live { color: #dc2626; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); animation: pulse 2s infinite; }

.btn-view { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: rgba(15, 23, 42, 0.05); color: #0f172a; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 600; transition: all 0.2s ease; border: 1px solid rgba(15, 23, 42, 0.1); }
.btn-view:hover { background: rgba(15, 23, 42, 0.1); transform: translateY(-1px); color: #0f172a; border-color: rgba(15, 23, 42, 0.2); }
.btn-view i { opacity: 0.7; }

@keyframes pulse {
    0% { opacity: 1; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
    70% { opacity: 0.8; box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
    100% { opacity: 1; box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}
</style>

<?php require_once '../includes/footer.php'; ?>
