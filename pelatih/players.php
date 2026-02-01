<?php
$page_title = 'My Players';
$current_page = 'players';
require_once 'config/database.php';
require_once 'includes/header.php';

$team_id = $_SESSION['team_id'] ?? 0;

// Pagination settings
$players_per_page = 10;
$current_page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page_num - 1) * $players_per_page;

// Get total players for pagination
$total_players = 0;
$players = [];
$total_pages = 1;

if ($team_id) {
    try {
        // Get total count
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM players WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $total_result = $stmt->fetch();
        $total_players = $total_result['total'];
        $total_pages = ceil($total_players / $players_per_page);

        // Validate current page
        if ($current_page_num < 1) $current_page_num = 1;
        if ($current_page_num > $total_pages) $current_page_num = $total_pages;
        
        // Recalculate offset
        $offset = ($current_page_num - 1) * $players_per_page;

        // Get players with pagination
        $stmt = $conn->prepare("SELECT 
            id, name, position, jersey_number, birth_date, gender, 
            height, weight, phone, email, status, photo,
            dribbling, technique, speed, juggling, shooting, 
            setplay_position, passing, control,
            TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) as age
            FROM players 
            WHERE team_id = ? 
            ORDER BY jersey_number ASC, name ASC
            LIMIT ? OFFSET ?");
        
        $stmt->bindParam(1, $team_id, PDO::PARAM_INT);
        $stmt->bindParam(2, $players_per_page, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $players = $stmt->fetchAll();
    } catch (PDOException $e) {
        $players = [];
        error_log("Database error: " . $e->getMessage());
    }
}
?>

<div class="card">
    <div class="section-header">
        <h2 class="section-title">Player List</h2>
        <a href="player_form.php" class="btn-primary">
            <i class="fas fa-plus"></i> Add Player
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="message-alert">
            <?php 
                if ($_GET['msg'] == 'added') echo "✅ Player added successfully!";
                if ($_GET['msg'] == 'updated') echo "✅ Player updated successfully!";
                if ($_GET['msg'] == 'deleted') echo "✅ Player deleted successfully!";
                if ($_GET['msg'] == 'no_changes_or_unauthorized') echo "⚠️ No changes made or unauthorized action.";
            ?>
        </div>
    <?php endif; ?>

    <?php if (empty($players)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>No players found in your team.</p>
            <a href="player_form.php" class="btn-primary">Add Your First Player</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">Photo</th>
                        <th>Name</th>
                        <th style="width: 80px;">Number</th>
                        <th>Position</th>
                        <th>Age</th>
                        <th>Gender</th>
                        <th>Contact</th>
                        <th style="width: 80px;">Skills</th>
                        <th>Status</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($players as $player): 
                        // Ensure numeric values
                        $dribbling = isset($player['dribbling']) ? (int)$player['dribbling'] : 5;
                        $technique = isset($player['technique']) ? (int)$player['technique'] : 5;
                        $speed = isset($player['speed']) ? (int)$player['speed'] : 5;
                        $juggling = isset($player['juggling']) ? (int)$player['juggling'] : 5;
                        $shooting = isset($player['shooting']) ? (int)$player['shooting'] : 5;
                        $setplay_position = isset($player['setplay_position']) ? (int)$player['setplay_position'] : 5;
                        $passing = isset($player['passing']) ? (int)$player['passing'] : 5;
                        $control = isset($player['control']) ? (int)$player['control'] : 5;
                        
                        // Calculate skill score (average of ALL skills)
                        if ($player['position'] == 'GK') {
                            // Untuk GK, berikan bobot lebih pada skill tertentu
                            $skill_score = round((
                                ($juggling * 1.5) + 
                                ($shooting * 1.2) + 
                                ($setplay_position * 1.3) +
                                ($control * 1.2) +
                                ($passing * 0.8) +
                                ($dribbling * 0.5) +
                                ($technique * 0.5) +
                                ($speed * 0.5)
                            ) / 8, 1);
                        } else {
                            // Untuk field players, semua skill penting
                            $skill_score = round((
                                $dribbling + 
                                $technique + 
                                $speed + 
                                $juggling + 
                                $shooting + 
                                $setplay_position + 
                                $passing + 
                                $control
                            ) / 8, 1);
                        }
                        
                        // Photo path - FIXED
                        $photo_url = '';
                        if (!empty($player['photo'])) {
                            $photo_path = 'uploads/players/' . $player['photo'];
                            // Check if file exists in multiple possible locations
                            $possible_paths = [
                                $photo_path,
                                '../' . $photo_path,
                                '../../' . $photo_path,
                                '../../../' . $photo_path
                            ];
                            
                            foreach ($possible_paths as $path) {
                                if (file_exists($path)) {
                                    $photo_url = $path;
                                    break;
                                }
                            }
                        }
                    ?>
                    <tr>
                        <td class="photo-cell">
                            <div class="player-photo">
                                <?php if (!empty($photo_url)): ?>
                                    <img src="<?php echo $photo_url; ?>" 
                                         alt="<?php echo htmlspecialchars($player['name']); ?>"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22100%22%20height%3D%22100%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%22100%22%20height%3D%22100%22%20fill%3D%22%23f0f0f0%22%2F%3E%3Ctext%20x%3D%2250%22%20y%3D%2255%22%20font-size%3D%2230%22%20text-anchor%3D%22middle%22%20fill%3D%22%23666%22%3E⚽%3C%2Ftext%3E%3C%2Fsvg%3E'">
                                <?php else: ?>
                                    <div class="default-photo">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="name-cell">
                            <strong><?php echo htmlspecialchars($player['name']); ?></strong>
                            <div class="player-info">
                                <small><?php echo htmlspecialchars($player['height'] ?? '0'); ?> cm • <?php echo htmlspecialchars($player['weight'] ?? '0'); ?> kg</small>
                            </div>
                        </td>
                        <td class="number-cell">
                            <span class="jersey-number">#<?php echo htmlspecialchars($player['jersey_number']); ?></span>
                        </td>
                        <td class="position-cell">
                            <span class="position-badge" data-position="<?php echo htmlspecialchars($player['position']); ?>">
                                <?php echo htmlspecialchars($player['position']); ?>
                            </span>
                        </td>
                        <td class="age-cell">
                            <?php echo htmlspecialchars($player['age'] ?? 'N/A'); ?> yrs
                        </td>
                        <td class="gender-cell" data-gender="<?php echo $player['gender']; ?>">
                            <?php echo $player['gender'] == 'L' ? '♂' : '♀'; ?>
                        </td>
                        <td class="contact-cell">
                            <div class="contact-info">
                                <?php if (!empty($player['phone'])): ?>
                                    <small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($player['phone']); ?></small><br>
                                <?php endif; ?>
                                <?php if (!empty($player['email'])): ?>
                                    <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($player['email']); ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="skills-cell">
                            <span class="skill-score" 
                                  data-dribbling="<?php echo $dribbling; ?>"
                                  data-technique="<?php echo $technique; ?>"
                                  data-speed="<?php echo $speed; ?>"
                                  data-juggling="<?php echo $juggling; ?>"
                                  data-shooting="<?php echo $shooting; ?>"
                                  data-setplay="<?php echo $setplay_position; ?>"
                                  data-passing="<?php echo $passing; ?>"
                                  data-control="<?php echo $control; ?>">
                                <?php echo $skill_score; ?>
                            </span>
                        </td>
                        <td class="status-cell">
                            <span class="status-badge <?php echo $player['status']; ?>">
                                <?php echo ucfirst($player['status']); ?>
                            </span>
                        </td>
                        <td class="actions-cell">
                            <div class="action-buttons">
                                <a href="player_form.php?id=<?php echo $player['id']; ?>" 
                                   class="btn-edit" 
                                   title="Edit Player">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form action="player_actions.php" method="POST" class="delete-form">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $player['id']; ?>">
                                    <button type="submit" 
                                            class="btn-delete" 
                                            title="Delete Player"
                                            onclick="return confirmDelete('<?php echo addslashes($player['name']); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Statistics Summary -->
        <div class="stats-summary">
            <div class="stat-item">
                <span class="stat-label">Total Players:</span>
                <span class="stat-value"><?php echo $total_players; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Active:</span>
                <span class="stat-value">
                    <?php 
                        $active_count = array_filter($players, fn($p) => $p['status'] == 'active');
                        echo count($active_count);
                    ?>
                </span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Avg Age:</span>
                <span class="stat-value">
                    <?php 
                        $ages = array_filter(array_column($players, 'age'), 'is_numeric');
                        echo !empty($ages) ? round(array_sum($ages) / count($ages), 1) : 'N/A';
                    ?> yrs
                </span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Page:</span>
                <span class="stat-value">
                    <?php echo $current_page_num; ?> of <?php echo $total_pages; ?>
                </span>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($current_page_num > 1): ?>
                <a href="?page=1" class="page-link" title="First Page">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?page=<?php echo $current_page_num - 1; ?>" class="page-link" title="Previous">
                    <i class="fas fa-angle-left"></i>
                </a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $current_page_num - 2);
            $end_page = min($total_pages, $current_page_num + 2);
            
            if ($start_page > 1): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?page=<?php echo $i; ?>" 
                   class="page-link <?php echo $i == $current_page_num ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
            
            <?php if ($current_page_num < $total_pages): ?>
                <a href="?page=<?php echo $current_page_num + 1; ?>" class="page-link" title="Next">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?page=<?php echo $total_pages; ?>" class="page-link" title="Last Page">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
/* Additional styles for players page */
.message-alert {
    padding: 12px 20px;
    margin-bottom: 25px;
    background: #e0f7fa;
    color: #006064;
    border-radius: 12px;
    border-left: 4px solid #00bcd4;
    animation: slideIn 0.3s ease-out;
}

.empty-state {
    text-align: center;
    padding: 50px 20px;
    color: var(--gray);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 20px;
    color: #ddd;
}

.empty-state p {
    margin-bottom: 25px;
    font-size: 16px;
}

.table-responsive {
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: white;
    border-radius: 12px;
    overflow: hidden;
}

.data-table thead {
    background: linear-gradient(135deg, var(--primary), #1a365d);
}

.data-table th {
    padding: 15px 12px;
    text-align: left;
    font-weight: 600;
    color: white;
    border: none;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.data-table tbody tr:last-child {
    border-bottom: none;
}

.data-table tbody tr:hover {
    background: #f8f9ff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(10, 36, 99, 0.1);
}

.data-table td {
    padding: 15px 12px;
    vertical-align: middle;
    font-size: 14px;
    border: none;
}

/* Player Photo */
.player-photo {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    overflow: hidden;
    margin: 0 auto;
    border: 3px solid white;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    background: #f8f9fa;
}

.player-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.player-photo:hover img {
    transform: scale(1.1);
}

.default-photo {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, var(--secondary), #FFEC8B);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 20px;
}

/* Player Info */
.name-cell {
    min-width: 180px;
}

.player-info {
    margin-top: 5px;
}

.player-info small {
    font-size: 12px;
    color: var(--gray);
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Jersey Number */
.jersey-number {
    display: inline-block;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
    border-radius: 50%;
    text-align: center;
    line-height: 40px;
    font-weight: bold;
    font-size: 14px;
    box-shadow: 0 4px 8px rgba(10, 36, 99, 0.2);
    transition: transform 0.3s ease;
}

.data-table tbody tr:hover .jersey-number {
    transform: scale(1.1) rotate(5deg);
}

/* Position Badge */
.position-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    background: linear-gradient(135deg, var(--accent), #2196F3);
    color: white;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    min-width: 40px;
    text-align: center;
}

/* Position-specific colors */
.position-badge[data-position="GK"] {
    background: linear-gradient(135deg, #FF9800, #F57C00);
}

.position-badge[data-position="DF"] {
    background: linear-gradient(135deg, #4CAF50, #2E7D32);
}

.position-badge[data-position="MF"] {
    background: linear-gradient(135deg, #2196F3, #0D47A1);
}

.position-badge[data-position="FW"] {
    background: linear-gradient(135deg, #F44336, #C62828);
}

.position-badge[data-position="CB"] {
    background: linear-gradient(135deg, #2E7D32, #1B5E20);
}

.position-badge[data-position="LB"] {
    background: linear-gradient(135deg, #388E3C, #2E7D32);
}

.position-badge[data-position="RB"] {
    background: linear-gradient(135deg, #43A047, #388E3C);
}

.position-badge[data-position="DM"] {
    background: linear-gradient(135deg, #1976D2, #1565C0);
}

.position-badge[data-position="CM"] {
    background: linear-gradient(135deg, #2196F3, #1E88E5);
}

.position-badge[data-position="AM"] {
    background: linear-gradient(135deg, #03A9F4, #0288D1);
}

.position-badge[data-position="LW"] {
    background: linear-gradient(135deg, #F44336, #E53935);
}

.position-badge[data-position="RW"] {
    background: linear-gradient(135deg, #EF5350, #E53935);
}

.position-badge[data-position="ST"] {
    background: linear-gradient(135deg, #D32F2F, #C2185B);
}

/* Age Cell */
.age-cell {
    font-weight: 600;
    color: var(--dark);
    text-align: center;
}

/* Gender Cell */
.gender-cell {
    text-align: center;
    font-size: 18px;
}

.gender-cell[data-gender="L"] {
    color: #2196F3;
}

.gender-cell[data-gender="P"] {
    color: #E91E63;
}

/* Contact Info */
.contact-info {
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.contact-info small {
    display: block;
    margin-bottom: 3px;
    color: var(--gray);
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.contact-info i {
    width: 14px;
    margin-right: 5px;
    opacity: 0.7;
}

/* Skill Score */
.skill-score {
    display: inline-block;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    text-align: center;
    line-height: 40px;
    font-weight: bold;
    font-size: 14px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
    cursor: help;
    position: relative;
}

/* Skill score color based on value */
.skill-score[data-score="10"] { background: linear-gradient(135deg, #4CAF50, #2E7D32); color: white; }
.skill-score[data-score="9"] { background: linear-gradient(135deg, #8BC34A, #689F38); color: white; }
.skill-score[data-score="8"] { background: linear-gradient(135deg, #CDDC39, #AFB42B); color: #333; }
.skill-score[data-score="7"] { background: linear-gradient(135deg, #FFEB3B, #FBC02D); color: #333; }
.skill-score[data-score="6"] { background: linear-gradient(135deg, #FFC107, #FF9800); color: #333; }
.skill-score[data-score="5"] { background: linear-gradient(135deg, #FF9800, #F57C00); color: white; }
.skill-score[data-score="4"] { background: linear-gradient(135deg, #FF5722, #E64A19); color: white; }
.skill-score[data-score="3"] { background: linear-gradient(135deg, #f44336, #D32F2F); color: white; }
.skill-score[data-score="2"] { background: linear-gradient(135deg, #E91E63, #C2185B); color: white; }
.skill-score[data-score="1"] { background: linear-gradient(135deg, #9C27B0, #7B1FA2); color: white; }

.skill-score:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 12px rgba(0,0,0,0.3);
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    min-width: 70px;
    text-align: center;
}

.status-badge.active {
    background: linear-gradient(135deg, #4CAF50, #2E7D32);
    color: white;
    box-shadow: 0 3px 8px rgba(46, 125, 50, 0.2);
}

.status-badge.inactive {
    background: linear-gradient(135deg, #9e9e9e, #616161);
    color: white;
    box-shadow: 0 3px 8px rgba(158, 158, 158, 0.2);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 10px;
}

.btn-edit, .btn-delete {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    border: none;
    min-width: 70px;
    justify-content: center;
}

.btn-edit {
    background: linear-gradient(135deg, var(--warning), #FF9800);
    color: white;
    box-shadow: 0 3px 10px rgba(249, 168, 38, 0.2);
}

.btn-edit:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(249, 168, 38, 0.3);
    background: linear-gradient(135deg, #FF9800, #F57C00);
}

.btn-delete {
    background: linear-gradient(135deg, var(--danger), #C62828);
    color: white;
    box-shadow: 0 3px 10px rgba(211, 47, 47, 0.2);
}

.btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(211, 47, 47, 0.3);
    background: linear-gradient(135deg, #C62828, #B71C1C);
}

.delete-form {
    margin: 0;
}

/* Statistics Summary */
.stats-summary {
    display: flex;
    gap: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 12px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 150px;
}

.stat-label {
    font-size: 12px;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.page-link {
    padding: 10px 16px;
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    color: var(--dark);
    text-decoration: none;
    font-weight: 600;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 45px;
    min-height: 45px;
}

.page-link:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
}

.page-link.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: scale(1.1);
    box-shadow: 0 5px 15px rgba(10, 36, 99, 0.2);
}

.page-link i {
    font-size: 14px;
}

.page-dots {
    padding: 10px;
    color: var(--gray);
    font-weight: bold;
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.data-table tbody tr {
    animation: fadeIn 0.5s ease-out;
    animation-fill-mode: both;
}

.data-table tbody tr:nth-child(1) { animation-delay: 0.1s; }
.data-table tbody tr:nth-child(2) { animation-delay: 0.2s; }
.data-table tbody tr:nth-child(3) { animation-delay: 0.3s; }
.data-table tbody tr:nth-child(4) { animation-delay: 0.4s; }
.data-table tbody tr:nth-child(5) { animation-delay: 0.5s; }
.data-table tbody tr:nth-child(6) { animation-delay: 0.6s; }
.data-table tbody tr:nth-child(7) { animation-delay: 0.7s; }
.data-table tbody tr:nth-child(8) { animation-delay: 0.8s; }
.data-table tbody tr:nth-child(9) { animation-delay: 0.9s; }
.data-table tbody tr:nth-child(10) { animation-delay: 1.0s; }

/* Custom Tooltip - Premium Design */
.custom-tooltip {
    position: absolute;
    background: rgba(20, 25, 40, 0.95);
    color: white;
    padding: 0;
    border-radius: 16px;
    font-size: 13px;
    z-index: 10000;
    pointer-events: none;
    width: 300px;
    box-shadow: 
        0 4px 6px -1px rgba(0, 0, 0, 0.1), 
        0 10px 30px -5px rgba(0, 0, 0, 0.5),
        0 0 0 1px rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(12px);
    opacity: 0;
    transform: translateY(10px) scale(0.95);
    transition: opacity 0.2s ease, transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

.custom-tooltip.visible {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.tooltip-header {
    padding: 16px 20px;
    background: linear-gradient(135deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02));
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tooltip-player-info h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #fff;
    letter-spacing: -0.01em;
}

.tooltip-player-info span {
    font-size: 12px;
    color: #94a3b8;
    font-weight: 500;
}

.tooltip-rating-badge {
    background: rgba(255, 255, 255, 0.1);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    color: #4ade80; /* Default green, updated via JS */
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.tooltip-body {
    padding: 16px 20px;
}

.skill-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.skill-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.skill-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.skill-header {
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    font-weight: 600;
    color: #cbd5e1;
}

.skill-track {
    height: 6px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 3px;
    overflow: hidden;
    position: relative;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.2);
}

.skill-progress {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

/* Add shimmer effect to high stats */
.skill-progress.high-stat::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 100%;
    background: linear-gradient(
        90deg, 
        transparent, 
        rgba(255,255,255,0.4), 
        transparent
    );
    transform: translateX(-100%);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    100% { transform: translateX(100%); }
}

.tooltip-footer {
    padding: 12px 20px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    text-align: center;
}

.overall-text {
    font-size: 12px;
    color: #94a3b8;
    display: flex;
    justify-content: center;
    gap: 6px;
}

.overall-value {
    color: #fff;
    font-weight: 700;
}

/* Responsive */
@media (max-width: 768px) {
    .table-responsive {
        margin: 0 -15px;
        border-radius: 0;
    }
    
    .data-table {
        min-width: 1000px;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 5px;
        min-width: 120px;
    }
    
    .btn-edit, .btn-delete {
        font-size: 12px;
        padding: 6px 12px;
        width: 100%;
    }
    
    .stats-summary {
        gap: 15px;
    }
    
    .stat-item {
        flex: 0 0 calc(50% - 15px);
    }
    
    .pagination {
        gap: 5px;
    }
    
    .page-link {
        padding: 8px 12px;
        min-width: 40px;
        min-height: 40px;
        font-size: 14px;
    }
    
    .custom-tooltip {
        width: 280px;
    }
}

@media (max-width: 480px) {
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .section-title {
        font-size: 18px;
    }
    
    .btn-primary {
        width: 100%;
        justify-content: center;
    }
    
    .stats-summary {
        flex-direction: column;
        gap: 15px;
    }
    
    .stat-item {
        flex: 0 0 100%;
    }
    
    .pagination {
        flex-wrap: wrap;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize skill scores
    document.querySelectorAll('.skill-score').forEach(score => {
        const skillValue = parseFloat(score.textContent);
        
        // Data extraction
        const data = {
            dribbling: parseInt(score.getAttribute('data-dribbling')) || 5,
            technique: parseInt(score.getAttribute('data-technique')) || 5,
            speed: parseInt(score.getAttribute('data-speed')) || 5,
            juggling: parseInt(score.getAttribute('data-juggling')) || 5,
            shooting: parseInt(score.getAttribute('data-shooting')) || 5,
            setplay: parseInt(score.getAttribute('data-setplay')) || 5,
            passing: parseInt(score.getAttribute('data-passing')) || 5,
            control: parseInt(score.getAttribute('data-control')) || 5
        };
        
        const row = score.closest('tr');
        const name = row.querySelector('.name-cell strong').textContent;
        const position = row.querySelector('.position-badge').textContent;
        const positionClass = row.querySelector('.position-badge').className;
        
        // Advanced Colors
        const colors = {
            9: ['#10b981', '#059669'], // Emerald
            8: ['#84cc16', '#65a30d'], // Lime
            7: ['#facc15', '#eab308'], // Yellow
            6: ['#fbbf24', '#d97706'], // Amber
            5: ['#f97316', '#ea580c'], // Orange
            4: ['#ef4444', '#dc2626'], // Red
            3: ['#ec4899', '#db2777'], // Pink
            1: ['#a855f7', '#9333ea'], // Purple
        };

        function getGradient(value) {
            const level = Math.floor(value);
            const palette = colors[level] || (value >= 9 ? colors[9] : colors[1]);
            return `linear-gradient(90deg, ${palette[0]}, ${palette[1]})`;
        }
        
        function getColor(value) {
             if (value >= 9) return '#10b981';
             if (value >= 7) return '#84cc16';
             if (value >= 6) return '#facc15';
             if (value >= 5) return '#f97316';
             return '#ef4444';
        }

        const overallColor = getColor(skillValue);

        // Build premium tooltip
        let tooltipHTML = `
            <div class="tooltip-header">
                <div class="tooltip-player-info">
                    <h4>${name}</h4>
                    <span>${position}</span>
                </div>
                <div class="tooltip-rating-badge" style="color: ${overallColor}; border-color: ${overallColor}40; background: ${overallColor}10;">
                    ${skillValue.toFixed(1)}
                </div>
            </div>
            <div class="tooltip-body">
                <div class="skill-details">
        `;
        
        // Define skill pairs for grid layout
        const skillPairs = [
            [{n:'Dribbling', v:data.dribbling}, {n:'Control', v:data.control}],
            [{n:'Passing', v:data.passing}, {n:'Shooting', v:data.shooting}],
            [{n:'Speed', v:data.speed}, {n:'Technique', v:data.technique}],
            [{n:'Set Play', v:data.setplay}, {n:'Juggling', v:data.juggling}]
        ];
        
        skillPairs.forEach(pair => {
            tooltipHTML += `<div class="skill-row">`;
            pair.forEach(skill => {
                const gradient = getGradient(skill.v);
                const isHigh = skill.v >= 8 ? 'high-stat' : '';
                tooltipHTML += `
                    <div class="skill-item">
                        <div class="skill-header">
                            <span>${skill.n}</span>
                            <span>${skill.v}</span>
                        </div>
                        <div class="skill-track">
                            <div class="skill-progress ${isHigh}" style="width: ${skill.v * 10}%; background: ${gradient};"></div>
                        </div>
                    </div>
                `;
            });
            tooltipHTML += `</div>`;
        });
        
        tooltipHTML += `
                </div>
            </div>
            <div class="tooltip-footer">
                <div class="overall-text">
                    OVERALL RATING <span class="overall-value" style="color: ${overallColor}">${skillValue.toFixed(1)}</span>
                </div>
            </div>
        `;
        
        score.setAttribute('data-full-tooltip', tooltipHTML);
        
        // Listeners
        score.addEventListener('mouseenter', showTooltip);
        score.addEventListener('mouseleave', hideTooltip);
        // Mobile touch
        score.addEventListener('touchstart', (e) => {
             // toggle check for mobile
             if(tooltip && tooltip._source === score) {
                 hideTooltip();
             } else {
                 showTooltip(e);
             }
        }, {passive: true});
    });
    
    // Global Tooltip Management
    let tooltip = null;
    let tooltipTimeout = null;

    function showTooltip(e) {
        if (tooltipTimeout) clearTimeout(tooltipTimeout);
        if (tooltip) tooltip.remove();

        const target = e.currentTarget;
        const html = target.getAttribute('data-full-tooltip');
        
        tooltip = document.createElement('div');
        tooltip.className = 'custom-tooltip';
        tooltip.innerHTML = html;
        tooltip._source = target; // Track source for toggle logic
        
        document.body.appendChild(tooltip);
        
        // Position Logic
        const rect = target.getBoundingClientRect();
        const tipRect = tooltip.getBoundingClientRect(); // will be 0 height initially if hidden? No, opacity 0 still has dims
        
        // Force a reflow or simply position it
        // We set it fixed
        
        /* 
           Position: Try Top Center. If clip, try Bottom.
           Left/Right clipping logic included.
        */
        
        const spacing = 12;
        let top = rect.top - tooltip.offsetHeight - spacing;
        let left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2);
        
        // Vertical Bounds
        if (top < 10) {
            top = rect.bottom + spacing; // Flip to bottom
            tooltip.style.transformOrigin = 'top center';
        } else {
             tooltip.style.transformOrigin = 'bottom center';
        }
        
        // Horizontal Bounds
        if (left < 10) left = 10;
        if (left + tooltip.offsetWidth > window.innerWidth - 10) {
            left = window.innerWidth - tooltip.offsetWidth - 10;
        }

        tooltip.style.top = `${top}px`;
        tooltip.style.left = `${left}px`;
        tooltip.style.position = 'fixed';
        
        // Trigger Animation
        requestAnimationFrame(() => {
            tooltip.classList.add('visible');
        });
    }

    function hideTooltip() {
        if (tooltip) {
            tooltip.classList.remove('visible');
            tooltipTimeout = setTimeout(() => {
                if(tooltip && !tooltip.classList.contains('visible')) {
                    tooltip.remove();
                    tooltip = null;
                }
            }, 200); // Matches CSS transition duration
        }
    }
    
    // existing gender/position badge standardizing
    document.querySelectorAll('.gender-cell, .position-badge').forEach(el => {
        const type = el.classList.contains('gender-cell') ? 'gender' : 'position';
        const val = el.getAttribute(`data-${type}`);
        if(val) el.setAttribute(`data-${type}`, val);
    });
});

// Delete confirmation with SweetAlert2 (Preserved)
function confirmDelete(playerName) {
    if (typeof Swal !== 'undefined') {
        return new Promise((resolve) => {
            Swal.fire({
                title: 'Delete Player?',
                html: `<div style="text-align: left;">
                    <p>Are you sure you want to delete <strong>"${playerName}"</strong>?</p>
                    <p style="color: #666; font-size: 14px; margin-top: 10px;">
                        <i class="fas fa-exclamation-triangle" style="color: #ff9800;"></i>
                        This action cannot be undone. All player data including:
                    </p>
                    <ul style="text-align: left; margin: 10px 0 10px 20px; color: #666; font-size: 13px;">
                        <li>Player profile</li>
                        <li>Skills statistics</li>
                        <li>Photos and documents</li>
                        <li>Match history</li>
                    </ul>
                </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Delete',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                confirmButtonColor: '#d32f2f',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'swal-delete-btn',
                    cancelButton: 'swal-cancel-btn'
                },
                width: 500,
                padding: '2em',
                backdrop: 'rgba(0,0,0,0.4)'
            }).then((result) => {
                resolve(result.isConfirmed);
            });
        });
    } else {
        return confirm(`Are you sure you want to delete "${playerName}"?\nThis action cannot be undone.`);
    }
}

// Add SweetAlert2 styles (Preserved)
const style = document.createElement('style');
style.textContent = `
.swal-delete-btn {
    background: linear-gradient(135deg, #d32f2f, #b71c1c) !important;
    border: none !important;
    padding: 12px 24px !important;
    border-radius: 8px !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 12px rgba(211, 47, 47, 0.3) !important;
    transition: all 0.3s ease !important;
}

.swal-delete-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 16px rgba(211, 47, 47, 0.4) !important;
}

.swal-cancel-btn {
    background: linear-gradient(135deg, #6c757d, #5a6268) !important;
    border: none !important;
    padding: 12px 24px !important;
    border-radius: 8px !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3) !important;
    transition: all 0.3s ease !important;
}

.swal-cancel-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 16px rgba(108, 117, 125, 0.4) !important;
}

.swal2-popup {
    border-radius: 16px !important;
    overflow: hidden !important;
}

.swal2-title {
    color: var(--dark) !important;
    font-size: 24px !important;
    margin-bottom: 20px !important;
}
`;
document.head.appendChild(style);
</script>

<!-- Add SweetAlert2 for better delete confirmation -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php require_once 'includes/footer.php'; ?>
