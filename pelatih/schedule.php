<?php
$page_title = 'Match Schedule';
$current_page = 'schedule';
require_once 'config/database.php';
require_once 'includes/header.php';

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Base query untuk challenges dengan join ke teams untuk nama tim
$base_query = "SELECT 
    c.*,
    t1.name as challenger_name,
    t1.logo as challenger_logo,
    t2.name as opponent_name,
    t2.logo as opponent_logo,
    v.name as venue_name
    FROM challenges c
    LEFT JOIN teams t1 ON c.challenger_id = t1.id
    LEFT JOIN teams t2 ON c.opponent_id = t2.id
    LEFT JOIN venues v ON c.venue_id = v.id
    WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM challenges c WHERE 1=1";

if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (c.challenge_code LIKE ? 
                OR t1.name LIKE ? 
                OR t2.name LIKE ? 
                OR c.sport_type LIKE ?
                OR c.status LIKE ?
                OR c.match_status LIKE ?)";
    $count_query .= " AND (c.challenge_code LIKE ? 
                OR EXISTS (SELECT 1 FROM teams t WHERE t.id = c.challenger_id AND t.name LIKE ?)
                OR EXISTS (SELECT 1 FROM teams t WHERE t.id = c.opponent_id AND t.name LIKE ?)
                OR c.sport_type LIKE ?
                OR c.status LIKE ?
                OR c.match_status LIKE ?)";
}

$base_query .= " ORDER BY c.challenge_date DESC";

$total_data = 0;
$total_pages = 1;
$challenges = [];

try {
    if (!empty($search)) {
        $stmt = $conn->prepare($count_query);
        $stmt->execute([
            $search_term, $search_term, $search_term, 
            $search_term, $search_term, $search_term
        ]);
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
    $stmt = $conn->prepare($query);
    
    if (!empty($search)) {
        $stmt->bindValue(1, $search_term);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $search_term);
        $stmt->bindValue(4, $search_term);
        $stmt->bindValue(5, $search_term);
        $stmt->bindValue(6, $search_term);
        $stmt->bindValue(7, $limit, PDO::PARAM_INT);
        $stmt->bindValue(8, $offset, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format tanggal dan waktu
    foreach ($challenges as &$challenge) {
        // Format challenge_date
        if (!empty($challenge['challenge_date'])) {
            $date = new DateTime($challenge['challenge_date']);
            $challenge['formatted_date'] = $date->format('d M Y');
            $challenge['formatted_time'] = $date->format('H:i');
        } else {
            $challenge['formatted_date'] = '-';
            $challenge['formatted_time'] = '-';
        }
        
        // Format match status badge color
        $challenge['match_status_badge'] = 'gray';
        if (!empty($challenge['match_status'])) {
            switch(strtolower($challenge['match_status'])) {
                case 'completed':
                    $challenge['match_status_badge'] = 'success';
                    break;
                case 'scheduled':
                    $challenge['match_status_badge'] = 'primary';
                    break;
                case 'cancelled':
                case 'abandoned':
                    $challenge['match_status_badge'] = 'danger';
                    break;
                case 'postponed':
                    $challenge['match_status_badge'] = 'warning';
                    break;
                default:
                    $challenge['match_status_badge'] = 'gray';
            }
        }
        
        // Format status badge color
        $challenge['status_badge'] = 'gray';
        if (!empty($challenge['status'])) {
            switch(strtolower($challenge['status'])) {
                case 'accepted':
                    $challenge['status_badge'] = 'success';
                    break;
                case 'open':
                    $challenge['status_badge'] = 'primary';
                    break;
                case 'rejected':
                    $challenge['status_badge'] = 'danger';
                    break;
                case 'expired':
                    $challenge['status_badge'] = 'warning';
                    break;
                default:
                    $challenge['status_badge'] = 'gray';
            }
        }
        
        // Set default logos jika kosong
        $challenge['challenger_logo'] = $challenge['challenger_logo'] ?: 'default-team.png';
        $challenge['opponent_logo'] = $challenge['opponent_logo'] ?: 'default-team.png';
    }
    unset($challenge);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="card">
    <div class="section-header">
        <h2 class="section-title">Match Schedule</h2>
        <!-- Read Only: No Add Button -->
    </div>

    <div class="search-bar" style="margin-bottom: 20px;">
        <form action="" method="GET">
            <input type="text" name="search" placeholder="Search matches..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <?php if (empty($challenges)): ?>
        <p style="text-align: center; color: var(--gray); padding: 20px;">No matches found.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Match Code</th>
                        <th>Match Date</th>
                        <th>Teams</th>
                        <th>Sport</th>
                        <th>Venue</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th>Match Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($challenges as $challenge): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($challenge['challenge_code']); ?></strong>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: var(--dark);"><?php echo $challenge['formatted_date']; ?></div>
                            <div style="font-size: 13px; color: var(--gray);"><?php echo $challenge['formatted_time']; ?></div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="text-align: center;">
                                    <img src="../images/teams/<?php echo htmlspecialchars($challenge['challenger_logo']); ?>" 
                                         alt="<?php echo htmlspecialchars($challenge['challenger_name']); ?>" 
                                         class="team-logo"
                                         onerror="this.onerror=null; this.src='../images/teams/default-team.png'">
                                    <div style="font-size: 12px; margin-top: 5px; font-weight: 600;">
                                        <?php echo htmlspecialchars($challenge['challenger_name']); ?>
                                    </div>
                                </div>
                                <div style="color: var(--gray);">vs</div>
                                <div style="text-align: center;">
                                    <img src="../images/teams/<?php echo htmlspecialchars($challenge['opponent_logo']); ?>" 
                                         alt="<?php echo htmlspecialchars($challenge['opponent_name']); ?>" 
                                         class="team-logo"
                                         onerror="this.onerror=null; this.src='../images/teams/default-team.png'">
                                    <div style="font-size: 12px; margin-top: 5px; font-weight: 600;">
                                        <?php echo htmlspecialchars($challenge['opponent_name']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="padding: 6px 12px; background: #f0f7ff; color: var(--primary); border-radius: 20px; font-size: 12px; font-weight: 600;">
                                <?php echo htmlspecialchars($challenge['sport_type']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($challenge['venue_name'])): ?>
                                <span><?php echo htmlspecialchars($challenge['venue_name']); ?></span>
                            <?php else: ?>
                                <span style="color: var(--gray); font-style: italic;">TBD</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($challenge['challenger_score']) || !empty($challenge['opponent_score'])): ?>
                                <div style="font-weight: 700; font-size: 18px; text-align: center; color: var(--primary);">
                                    <?php echo htmlspecialchars($challenge['challenger_score'] ?? 0); ?> - <?php echo htmlspecialchars($challenge['opponent_score'] ?? 0); ?>
                                </div>
                                <?php if (!empty($challenge['winner_team_id'])): ?>
                                    <div style="font-size: 11px; color: var(--success); text-align: center; font-weight: 600;">
                                        <?php 
                                        $winner_name = ($challenge['winner_team_id'] == $challenge['challenger_id']) 
                                            ? $challenge['challenger_name'] 
                                            : $challenge['opponent_name'];
                                        echo htmlspecialchars($winner_name);
                                        ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--gray); font-style: italic;">Not played</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($challenge['status'])): ?>
                                <?php 
                                $badge_class = '';
                                switch($challenge['status_badge']) {
                                    case 'success': $badge_class = 'background: #e8f5e9; color: var(--success);'; break;
                                    case 'primary': $badge_class = 'background: #f0f7ff; color: var(--primary);'; break;
                                    case 'danger': $badge_class = 'background: #ffebee; color: var(--danger);'; break;
                                    case 'warning': $badge_class = 'background: #fff8e1; color: var(--warning);'; break;
                                    default: $badge_class = 'background: #f5f5f5; color: var(--gray);';
                                }
                                ?>
                                <span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; <?php echo $badge_class; ?>">
                                    <?php echo htmlspecialchars(ucfirst($challenge['status'])); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($challenge['match_status'])): ?>
                                <?php 
                                $match_badge_class = '';
                                switch($challenge['match_status_badge']) {
                                    case 'success': $match_badge_class = 'background: #e8f5e9; color: var(--success);'; break;
                                    case 'primary': $match_badge_class = 'background: #f0f7ff; color: var(--primary);'; break;
                                    case 'danger': $match_badge_class = 'background: #ffebee; color: var(--danger);'; break;
                                    case 'warning': $match_badge_class = 'background: #fff8e1; color: var(--warning);'; break;
                                    default: $match_badge_class = 'background: #f5f5f5; color: var(--gray);';
                                }
                                ?>
                                <span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; <?php echo $match_badge_class; ?>">
                                    <?php echo htmlspecialchars(ucfirst($challenge['match_status'])); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--gray); font-style: italic;">N/A</span>
                            <?php endif; ?>
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
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">&laquo; Prev</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>