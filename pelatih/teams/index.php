<?php
$page_title = 'Daftar Team';
$current_page = 'team';
require_once '../config/database.php';
require_once '../includes/header.php';
?>
<link rel="stylesheet" href="css/teams.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/teams.css'); ?>">
<?php
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Base query (ReadOnly - lists all teams)
$base_query = "SELECT t.*, 
              (SELECT COUNT(*) FROM players p WHERE p.team_id = t.id AND p.status = 'active') as player_count,
              (SELECT COUNT(*) FROM team_staff ts WHERE ts.team_id = t.id) as staff_count,
              (SELECT COUNT(*) FROM challenges c WHERE (c.challenger_id = t.id OR c.opponent_id = t.id) AND (c.status = 'accepted' OR c.status = 'completed')) as match_count
              FROM teams t WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM teams t WHERE 1=1";

if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (t.name LIKE ? OR t.alias LIKE ? OR t.coach LIKE ? OR t.sport_type LIKE ?)";
    $count_query .= " AND (t.name LIKE ? OR t.alias LIKE ? OR t.coach LIKE ? OR t.sport_type LIKE ?)";
}

$base_query .= " ORDER BY t.created_at DESC";

$total_data = 0;
$total_pages = 1;
$teams = [];

try {
    if (!empty($search)) {
        $stmt = $conn->prepare($count_query);
        $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
    } else {
        $stmt = $conn->prepare($count_query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
    }
    
    $total_pages = ceil($total_data / $limit);
    
    $query = $base_query . " LIMIT ? OFFSET ?";
    if (!empty($search)) {
        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $search_term);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $search_term);
        $stmt->bindValue(4, $search_term);
        $stmt->bindValue(5, $limit, PDO::PARAM_INT);
        $stmt->bindValue(6, $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
    }
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$team_export_url = 'export.php' . ($search !== '' ? '?' . http_build_query(['search' => $search]) : '');
?>

<div class="teams-container">
    <!-- Editorial Header -->
    <header class="dashboard-hero reveal">
        <div class="hero-content">
            <span class="hero-label">Direktori</span>
            <h1 class="hero-title">Direktori Team</h1>
            <p class="hero-description">Lihat profil team, jumlah pemain, staf, dan riwayat pertandingan dalam satu halaman.</p>
        </div>
        <div class="hero-actions">
            <span class="summary-pill"><i class="fas fa-users"></i> <?php echo (int)$total_data; ?> Team Terdaftar</span>
        </div>
    </header>

    <div class="filter-container reveal d-1">
        <div class="teams-filter-card">
            <form action="" method="GET" class="teams-filter-form">
                <div class="filter-group">
                    <label>Pencarian</label>
                    <div class="teams-search-group">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="teams-search-input" placeholder="Cari nama atau alias team..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="teams-filter-actions" style="margin-top: auto;">
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Cari</button>
                    <a href="./" class="clear-filter-btn"><i class="fas fa-times"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="reveal d-2">
        <div class="section-header">
            <div class="section-title-wrap">
                <h2 class="section-title">Daftar Team</h2>
                <div class="section-line"></div>
            </div>
            <div class="section-actions">
                <a href="<?php echo htmlspecialchars($team_export_url); ?>" class="btn-premium btn-export">
                    <i class="fas fa-download"></i> Export Excel
                </a>
            </div>
        </div>

    <?php if (empty($teams)): ?>
        <div class="empty-state">
            <i class="fas fa-shield-alt"></i>
            <p>Team tidak ditemukan.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="logo-cell">Logo</th>
                        <th>Nama Team</th>
                        <th>Alias</th>
                        <th>Manager</th>
                        <th>Pemain</th>
                        <th>Staf</th>
                        <th>Pertandingan</th>
                        <th>Tanggal Berdiri</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($teams as $team): ?>
                    <tr>
                        <td class="logo-cell">
                            <?php if (!empty($team['logo'])): ?>
                                <img src="../../images/teams/<?php echo htmlspecialchars($team['logo'] ?? ''); ?>" 
                                alt="<?php echo htmlspecialchars($team['name'] ?? ''); ?>"  
                                class="team-logo"
                                onerror="this.onerror=null; this.src='../../images/teams/default-team.png'">
                            <?php else: ?>
                                <div class="team-logo" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-shield-alt" style="color: #999; font-size: 24px;"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="name-cell">
                            <strong><?php echo htmlspecialchars($team['name'] ?? ''); ?></strong>
                        </td>
                        <td class="alias-cell"><?php echo htmlspecialchars($team['alias'] ?? ''); ?></td>
                        <td class="coach-cell"><?php echo htmlspecialchars($team['coach'] ?? ''); ?></td>
                        <td>
                            <a href="players.php?team_id=<?php echo $team['id']; ?>" class="count-link" title="Lihat <?php echo $team['player_count']; ?> pemain">
                                <span class="count-cell players"><?php echo $team['player_count']; ?></span>
                            </a>
                        </td>
                        <td>
                            <a href="staff_view.php?team_id=<?php echo $team['id']; ?>" class="count-link" title="Lihat <?php echo $team['staff_count']; ?> staf">
                                <span class="count-cell staff"><?php echo $team['staff_count']; ?></span>
                            </a>
                        </td>
                        <td>
                            <a href="matches.php?team_id=<?php echo $team['id']; ?>" class="count-link" title="Lihat <?php echo $team['match_count']; ?> pertandingan">
                                <span class="count-cell matches"><?php echo $team['match_count']; ?></span>
                            </a>
                        </td>
                        <td class="established-cell">
                            <?php
                                $established_display = '-';
                                if (!empty($team['established_year'])) {
                                    $timestamp = strtotime($team['established_year']);
                                    $established_display = $timestamp ? date('d M Y', $timestamp) : $team['established_year'];
                                }
                                echo htmlspecialchars($established_display);
                            ?>
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
                <a href="?page=1&search=<?php echo urlencode($search); ?>" class="page-link" title="Halaman Pertama">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="Sebelumnya">
                    <i class="fas fa-angle-left"></i>
                </a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="Berikutnya">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="Halaman Terakhir">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
    </div>
</div>


<?php require_once '../includes/footer.php'; ?>
