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

/* Custom Tooltip */
.custom-tooltip {
    position: absolute;
    background: rgba(0, 0, 0, 0.95);
    color: white;
    padding: 15px 20px;
    border-radius: 10px;
    font-size: 13px;
    line-height: 1.5;
    z-index: 10000;
    pointer-events: none;
    white-space: pre-line;
    max-width: 320px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.4);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.15);
    animation: tooltipFadeIn 0.2s ease-out;
}

.custom-tooltip::before {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border-width: 10px;
    border-style: solid;
    border-color: rgba(0, 0, 0, 0.95) transparent transparent transparent;
}

.skill-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-top: 10px;
}

.skill-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 3px 0;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.skill-name {
    font-weight: 500;
    color: #e0e0e0;
}

.skill-value {
    font-weight: 600;
    color: #fff;
}

.skill-bar {
    width: 80px;
    height: 6px;
    background: rgba(255,255,255,0.1);
    border-radius: 3px;
    overflow: hidden;
    margin-left: 10px;
}

.skill-progress {
    height: 100%;
    border-radius: 3px;
}

@keyframes tooltipFadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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
        max-width: 280px;
        font-size: 12px;
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
    // Initialize skill scores with data attributes
    document.querySelectorAll('.skill-score').forEach(score => {
        const skillValue = parseFloat(score.textContent);
        const roundedValue = Math.round(skillValue);
        score.setAttribute('data-score', roundedValue);
        
        // Get ALL skill data from data attributes
        const dribbling = parseInt(score.getAttribute('data-dribbling')) || 5;
        const technique = parseInt(score.getAttribute('data-technique')) || 5;
        const speed = parseInt(score.getAttribute('data-speed')) || 5;
        const juggling = parseInt(score.getAttribute('data-juggling')) || 5;
        const shooting = parseInt(score.getAttribute('data-shooting')) || 5;
        const setplay = parseInt(score.getAttribute('data-setplay')) || 5;
        const passing = parseInt(score.getAttribute('data-passing')) || 5;
        const control = parseInt(score.getAttribute('data-control')) || 5;
        
        const row = score.closest('tr');
        const name = row.querySelector('.name-cell strong').textContent;
        const position = row.querySelector('.position-badge').textContent;
        
        // Get skill color based on value
        function getSkillColor(value) {
            if (value >= 9) return '#4CAF50';
            if (value >= 7) return '#8BC34A';
            if (value >= 6) return '#CDDC39';
            if (value >= 5) return '#FFC107';
            if (value >= 4) return '#FF9800';
            if (value >= 3) return '#FF5722';
            return '#f44336';
        }
        
        // Build tooltip content with ALL skills
        let tooltipContent = `
            <div style="text-align: center; margin-bottom: 10px;">
                <strong style="font-size: 14px; color: #fff;">${name}</strong><br>
                <span style="color: #bbb; font-size: 12px;">${position}</span>
            </div>
            <div class="skill-details">
        `;
        
        // Add all skills to tooltip
        const skills = [
            { name: 'Dribbling', value: dribbling, color: getSkillColor(dribbling) },
            { name: 'Technique', value: technique, color: getSkillColor(technique) },
            { name: 'Speed', value: speed, color: getSkillColor(speed) },
            { name: 'Juggling', value: juggling, color: getSkillColor(juggling) },
            { name: 'Shooting', value: shooting, color: getSkillColor(shooting) },
            { name: 'Set Play', value: setplay, color: getSkillColor(setplay) },
            { name: 'Passing', value: passing, color: getSkillColor(passing) },
            { name: 'Control', value: control, color: getSkillColor(control) }
        ];
        
        skills.forEach(skill => {
            tooltipContent += `
                <div class="skill-item">
                    <span class="skill-name">${skill.name}</span>
                    <div style="display: flex; align-items: center;">
                        <span class="skill-value">${skill.value}/10</span>
                        <div class="skill-bar">
                            <div class="skill-progress" style="width: ${skill.value * 10}%; background: ${skill.color};"></div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        tooltipContent += `
            </div>
            <div style="margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.2); text-align: center;">
                <strong style="color: #4CAF50; font-size: 14px;">Overall Rating: ${skillValue.toFixed(1)}/10</strong>
            </div>
        `;
        
        // Store tooltip content
        score.setAttribute('data-full-tooltip', tooltipContent);
        
        // Add event listeners for tooltip
        score.addEventListener('mouseenter', showSkillTooltip);
        score.addEventListener('mouseleave', hideSkillTooltip);
        score.addEventListener('click', function(e) {
            e.stopPropagation();
            showSkillTooltip(e);
        });
        
        // Add touch support for mobile
        score.addEventListener('touchstart', function(e) {
            e.preventDefault();
            showSkillTooltip(e);
        });
    });
    
    // Add gender data attributes for styling
    document.querySelectorAll('.gender-cell').forEach(cell => {
        const gender = cell.getAttribute('data-gender');
        if (gender) {
            cell.setAttribute('data-gender', gender);
        }
    });
    
    // Position badge styling
    document.querySelectorAll('.position-badge').forEach(badge => {
        const position = badge.getAttribute('data-position');
        if (position) {
            badge.setAttribute('data-position', position);
        }
    });
    
    // Tooltip functions
    let tooltip = null;
    let tooltipTimeout = null;
    
    function showSkillTooltip(e) {
        // Clear any existing timeout
        if (tooltipTimeout) {
            clearTimeout(tooltipTimeout);
            tooltipTimeout = null;
        }
        
        // Remove existing tooltip
        if (tooltip) {
            tooltip.remove();
            tooltip = null;
        }
        
        // Create new tooltip
        tooltip = document.createElement('div');
        tooltip.className = 'custom-tooltip';
        tooltip.innerHTML = e.target.getAttribute('data-full-tooltip');
        
        // Position tooltip
        const rect = e.target.getBoundingClientRect();
        tooltip.style.position = 'fixed';
        tooltip.style.left = (rect.left + rect.width / 2) + 'px';
        tooltip.style.top = (rect.top - 10) + 'px';
        tooltip.style.transform = 'translate(-50%, -100%)';
        
        // Add to document
        document.body.appendChild(tooltip);
        
        // Adjust position if tooltip goes off screen
        setTimeout(() => {
            if (tooltip) {
                const tooltipRect = tooltip.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                
                // Check if tooltip goes off left side
                if (tooltipRect.left < 10) {
                    tooltip.style.left = (rect.left + rect.width) + 'px';
                    tooltip.style.transform = 'translate(0, -100%)';
                }
                
                // Check if tooltip goes off right side
                if (tooltipRect.right > viewportWidth - 10) {
                    tooltip.style.left = rect.left + 'px';
                    tooltip.style.transform = 'translate(-100%, -100%)';
                }
                
                // Check if tooltip goes off top
                if (tooltipRect.top < 10) {
                    tooltip.style.top = (rect.bottom + 10) + 'px';
                    tooltip.style.transform = 'translate(-50%, 0)';
                    
                    // Also adjust arrow
                    tooltip.style.setProperty('--tooltip-arrow', 'none');
                }
                
                // Check if tooltip goes off bottom
                if (tooltipRect.bottom > viewportHeight - 10 && tooltipRect.top > 10) {
                    tooltip.style.top = (rect.top - tooltipRect.height - 10) + 'px';
                    tooltip.style.transform = 'translate(-50%, 0)';
                }
            }
        }, 0);
    }
    
    function hideSkillTooltip() {
        // Delay hiding to allow moving cursor to tooltip
        tooltipTimeout = setTimeout(() => {
            if (tooltip) {
                tooltip.remove();
                tooltip = null;
            }
            tooltipTimeout = null;
        }, 300);
    }
    
    // Prevent tooltip from hiding when hovering over it
    document.addEventListener('mouseover', function(e) {
        if (tooltip && tooltip.contains(e.target)) {
            if (tooltipTimeout) {
                clearTimeout(tooltipTimeout);
                tooltipTimeout = null;
            }
        }
    });
    
    // Close tooltip when clicking elsewhere
    document.addEventListener('click', function(e) {
        if (!e.target.classList.contains('skill-score') && 
            !e.target.closest('.custom-tooltip') && 
            tooltip) {
            if (tooltip) {
                tooltip.remove();
                tooltip = null;
            }
        }
    });
    
    // Close tooltip on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && tooltip) {
            tooltip.remove();
            tooltip = null;
        }
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (tooltip) {
            tooltip.remove();
            tooltip = null;
        }
    });
});

// Delete confirmation with SweetAlert2
function confirmDelete(playerName) {
    // Check if SweetAlert2 is available
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
        // Fallback to native confirm
        return confirm(`Are you sure you want to delete "${playerName}"?\nThis action cannot be undone.`);
    }
}

// Add SweetAlert2 styles
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

/* Debug photo paths */
.photo-debug {
    position: fixed;
    bottom: 10px;
    right: 10px;
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 10px;
    border-radius: 5px;
    font-size: 12px;
    z-index: 9999;
}
`;
document.head.appendChild(style);

// Debug function to check photo paths
function debugPhotoPaths() {
    const debugDiv = document.createElement('div');
    debugDiv.className = 'photo-debug';
    debugDiv.innerHTML = '<strong>Photo Debug:</strong><br>';
    
    document.querySelectorAll('.player-photo img').forEach((img, index) => {
        debugDiv.innerHTML += `Player ${index + 1}: ${img.src}<br>`;
    });
    
    document.body.appendChild(debugDiv);
    
    // Remove after 10 seconds
    setTimeout(() => {
        debugDiv.remove();
    }, 10000);
}

// Uncomment to debug photo paths
// debugPhotoPaths();
</script>

<!-- Add SweetAlert2 for better delete confirmation -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php require_once 'includes/footer.php'; ?>