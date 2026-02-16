<?php
$page_title = 'Daftar Tim';
$current_page = 'team';
require_once 'config/database.php';
require_once 'includes/header.php';

// Handle search
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
?>

<div class="card">
    <div class="section-header">
        <h2 class="section-title">Tim</h2>
        <!-- Read Only: No Add Button -->
    </div>

    <div class="search-bar" style="margin-bottom: 20px;">
        <form action="" method="GET">
            <input type="text" name="search" placeholder="Cari tim..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <?php if (empty($teams)): ?>
        <p style="text-align: center; color: var(--gray); padding: 20px;">Tim tidak ditemukan.</p>
    <?php else: ?>
        <div class="team-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="logo-cell">Logo</th>
                        <th>Nama Tim</th>
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
                                <img src="../images/teams/<?php echo htmlspecialchars($team['logo'] ?? ''); ?>" 
                                alt="<?php echo htmlspecialchars($team['name'] ?? ''); ?>"  
                                class="team-logo"
                                onerror="this.onerror=null; this.src='../images/teams/default-team.png'">
                            <?php else: ?>
                                <div class="team-logo" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-shield-alt" style="color: #999; font-size: 24px;"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="team-name-cell"><?php echo htmlspecialchars($team['name'] ?? ''); ?></td>
                        <td class="alias-cell"><?php echo htmlspecialchars($team['alias'] ?? ''); ?></td>
                        <td class="coach-cell"><?php echo htmlspecialchars($team['coach'] ?? ''); ?></td>
                        <td>
                            <a href="team_players.php?team_id=<?php echo $team['id']; ?>" class="count-link" title="Lihat <?php echo $team['player_count']; ?> pemain">
                                <span class="count-cell players"><?php echo $team['player_count']; ?></span>
                            </a>
                        </td>
                        <td>
                            <a href="team_staff_view.php?team_id=<?php echo $team['id']; ?>" class="count-link" title="Lihat <?php echo $team['staff_count']; ?> staf">
                                <span class="count-cell staff"><?php echo $team['staff_count']; ?></span>
                            </a>
                        </td>
                        <td>
                            <a href="team_matches.php?team_id=<?php echo $team['id']; ?>" class="count-link" title="Lihat <?php echo $team['match_count']; ?> pertandingan">
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
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">&laquo; Seb</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">Sel &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<style>
.main {
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
}

.team-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 14px;
}

/* Clickable count styling */
.count-link {
    text-decoration: none;
    color: inherit;
    display: inline-block;
    transition: all 0.3s ease;
}

.count-cell {
    display: inline-block;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    min-width: 40px;
    text-align: center;
}

.count-cell.players {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    color: #1565c0;
    border: 2px solid transparent;
}

.count-cell.staff {
    background: linear-gradient(135deg, #f3e5f5, #e1bee7);
    color: #6a1b9a;
}

.count-cell.matches {
    background: linear-gradient(135deg, #e0f2f1, #b2dfdb);
    color: #00897b;
}

.count-link:hover .count-cell.players {
    background: linear-gradient(135deg, #2196F3, #1976D2);
    color: white;
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(33, 150, 243, 0.4);
    border-color: #1976D2;
}

.count-link:hover .count-cell.staff {
    background: linear-gradient(135deg, #9C27B0, #7B1FA2);
    color: white;
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(156, 39, 176, 0.4);
}

.count-link:hover .count-cell.matches {
    background: linear-gradient(135deg, #009688, #00796b);
    color: white;
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0, 150, 136, 0.4);
} 

.count-link:active .count-cell.players,
.count-link:active .count-cell.staff,
.count-link:active .count-cell.matches {
    transform: scale(0.95);
}

/* Make it clear it's clickable with cursor */
.count-link {
    cursor: pointer;
}

/* Stronger row hover effect for team table */
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

@media (max-width: 768px) {
    .data-table tbody tr:hover,
    .data-table tbody tr:focus-within {
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(10, 36, 99, 0.14), 0 0 0 1px rgba(76, 138, 255, 0.28);
    }
}

/* Page-specific responsiveness */
@media (max-width: 992px) {
    .data-table {
        min-width: 900px;
    }

    .data-table th,
    .data-table td {
        padding: 10px 12px;
        font-size: 13px;
    }

    .team-logo {
        width: 44px;
        height: 44px;
    }

    .count-cell {
        padding: 6px 12px;
        min-width: 36px;
        font-size: 13px;
    }
}

@media (max-width: 768px) {
    .card {
        padding: 16px 12px;
    }

    .search-bar {
        margin-bottom: 14px !important;
    }

    .pagination {
        gap: 6px;
    }

    .page-link {
        padding: 8px 12px;
        font-size: 13px;
        min-width: 40px;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .data-table {
        min-width: 820px;
    }

    .data-table th,
    .data-table td {
        padding: 9px 10px;
        font-size: 12px;
    }

    .team-logo {
        width: 38px;
        height: 38px;
    }

    .count-cell {
        padding: 5px 10px;
        min-width: 32px;
        font-size: 12px;
    }
}

/* Avoid jumpy hover effects on touch devices */
@media (hover: none) {
    .data-table tbody tr:hover,
    .data-table tbody tr:focus-within {
        transform: none;
        box-shadow: none;
        background: #f8f9fa;
    }
}
</style>

<?php require_once 'includes/footer.php'; ?>
