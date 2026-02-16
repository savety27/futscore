<?php
$page_title = 'Pemain Tim';
$current_page = 'team'; // Keep 'team' as current page to highlight the sidebar correctly
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
    echo "<div class='card'><div class='alert alert-danger'>Tim tidak ditemukan.</div><a href='team.php' class='btn-secondary'>Kembali ke Daftar Tim</a></div>";
    require_once 'includes/footer.php';
    exit;
}

// Update page title with team name
$page_title = htmlspecialchars($team_info['name'] ?? '') . ' - Pemain';

// Pagination settings
$players_per_page = 10;
$current_page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page_num - 1) * $players_per_page;

// Get total players for pagination
$total_players = 0;
$players = [];
$total_pages = 1;

try {
    // Get total count (for specific team)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM players WHERE team_id = ?");
    $stmt->execute([$team_id]);
    $total_result = $stmt->fetch();
    $total_players = $total_result['total'];
    $total_pages = ceil($total_players / $players_per_page);

    // Validate current page
    if ($current_page_num < 1) $current_page_num = 1;
    if ($current_page_num > $total_pages && $total_pages > 0) $current_page_num = $total_pages;
    
    // Recalculate offset logic if page changed
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
    
    $stmt->bindValue(1, $team_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $players_per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $players = $stmt->fetchAll();
} catch (PDOException $e) {
    $players = [];
    error_log("Database error: " . $e->getMessage());
}
?>

<div class="card">
    <div class="section-header">
        <div style="display: flex; align-items: center; gap: 15px;">
            <?php if (!empty($team_info['logo'])): ?>
                <img src="../images/teams/<?php echo $team_info['logo']; ?>" alt="Logo" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;" onerror="this.onerror=null; this.src='../images/teams/default-team.png'">
            <?php endif; ?>
            <div>
                <h2 class="section-title"><?php echo htmlspecialchars($team_info['name'] ?? ''); ?> <span style="font-weight: normal; font-size: 0.8em; color: var(--gray);">Pemain</span></h2>
            </div>
        </div>
        <a href="team.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali ke Daftar Tim
        </a>
    </div>

    <?php if (empty($players)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>Tidak ada pemain ditemukan di tim ini.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">Foto</th>
                        <th>Nama</th>
                        <th style="width: 80px;">Nomor</th>
                        <th>Posisi</th>
                        <th>Umur</th>
                        <th>Jenis Kelamin</th>
                        <th style="width: 80px;">Skills</th>
                        <th>Status</th>
                        <!-- Removed Actions Column -->
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
                        
                        // Photo path
                        $photo_url = '';
                        if (!empty($player['photo'])) {
                            $photo_path_images = 'images/players/' . $player['photo'];
                            $photo_path_uploads = 'uploads/players/' . $player['photo'];
                            $possible_paths = [
                                $photo_path_images,
                                '../' . $photo_path_images,
                                '../../' . $photo_path_images,
                                '../../../' . $photo_path_images,
                                $photo_path_uploads,
                                '../' . $photo_path_uploads,
                                '../../' . $photo_path_uploads,
                                '../../../' . $photo_path_uploads
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
                                         alt="<?php echo htmlspecialchars($player['name'] ?? ''); ?>"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22100%22%20height%3D%22100%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%22100%22%20height%3D%22100%22%20fill%3D%22%23f0f0f0%22%2F%3E%3Ctext%20x%3D%2250%22%20y%3D%2255%22%20font-size%3D%2230%22%20text-anchor%3D%22middle%22%20fill%3D%22%23666%22%3E⚽%3C%2Ftext%3E%3C%2Fsvg%3E'">
                                <?php else: ?>
                                    <div class="default-photo">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="name-cell">
                            <strong><?php echo htmlspecialchars($player['name'] ?? ''); ?></strong>
                            <div class="player-info">
                                <small><?php echo htmlspecialchars($player['height'] ?? '0'); ?> cm • <?php echo htmlspecialchars($player['weight'] ?? '0'); ?> kg</small>
                            </div>
                        </td>
                        <td class="number-cell">
                            <span class="jersey-number">#<?php echo htmlspecialchars($player['jersey_number'] ?? ''); ?></span>
                        </td>
                        <td class="position-cell">
                            <span class="position-badge" data-position="<?php echo htmlspecialchars($player['position'] ?? ''); ?>">
                                <?php echo htmlspecialchars($player['position'] ?? ''); ?>
                            </span>
                        </td>
                        <td class="age-cell">
                            <?php echo htmlspecialchars($player['age'] ?? 'N/A'); ?> thn
                        </td>
                        <td class="gender-cell" data-gender="<?php echo $player['gender']; ?>">
                            <?php echo $player['gender'] == 'L' ? '♂' : '♀'; ?>
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
                                <?php 
                                    $status_map = ['active' => 'Aktif', 'inactive' => 'Non-aktif', 'injured' => 'Cedera', 'suspended' => 'Skorsing'];
                                    echo $status_map[$player['status'] ?? ''] ?? ucfirst($player['status'] ?? ''); 
                                ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Statistics Summary -->
        <div class="stats-summary">
            <div class="stat-item">
                <span class="stat-label">Total Pemain</span>
                <span class="stat-value"><?php echo $total_players; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Aktif</span>
                <span class="stat-value">
                    <?php 
                        $active_count = array_filter($players, fn($p) => $p['status'] == 'active');
                        echo count($active_count);
                    ?>
                </span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Rata-rata Umur</span>
                <span class="stat-value">
                    <?php 
                        $ages = array_filter(array_column($players, 'age'), 'is_numeric');
                        echo !empty($ages) ? round(array_sum($ages) / count($ages), 1) : 'N/A';
                    ?>
                </span>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($current_page_num > 1): ?>
                <a href="?team_id=<?php echo $team_id; ?>&page=1" class="page-link" title="Halaman Pertama">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $current_page_num - 1; ?>" class="page-link" title="Sebelumnya">
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
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $i; ?>" 
                   class="page-link <?php echo $i == $current_page_num ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
            
            <?php if ($current_page_num < $total_pages): ?>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $current_page_num + 1; ?>" class="page-link" title="Berikutnya">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $total_pages; ?>" class="page-link" title="Halaman Terakhir">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.main {
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
}

/* Reusing styles from players.php mainly */
.empty-state { text-align: center; padding: 50px 20px; color: var(--gray); }
.empty-state i { font-size: 48px; margin-bottom: 20px; color: #ddd; }
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
.btn-secondary { background: #e0e0e0; color: #333; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; transition: all 0.2s; }
.btn-secondary:hover { background: #d5d5d5; color: #000; }

/* Photo */
.player-photo { width: 50px; height: 50px; border-radius: 50%; overflow: hidden; border: 3px solid white; box-shadow: 0 4px 8px rgba(0,0,0,0.1); background: #f8f9fa; }
.player-photo img { width: 100%; height: 100%; object-fit: cover; }
.default-photo { width: 100%; height: 100%; background: linear-gradient(135deg, var(--secondary), #FFEC8B); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 20px; }

/* Jersey Number */
.jersey-number { display: inline-block; width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary), var(--accent)); color: white; border-radius: 50%; text-align: center; line-height: 40px; font-weight: bold; font-size: 14px; box-shadow: 0 4px 8px rgba(10, 36, 99, 0.2); }

/* Position Badge */
.position-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; text-align: center; min-width: 40px; background: #2196F3; }
.position-badge[data-position="GK"] { background: linear-gradient(135deg, #FF9800, #F57C00); }
.position-badge[data-position="DF"], .position-badge[data-position="CB"], .position-badge[data-position="LB"], .position-badge[data-position="RB"] { background: linear-gradient(135deg, #4CAF50, #2E7D32); }
.position-badge[data-position="MF"], .position-badge[data-position="CM"], .position-badge[data-position="DM"], .position-badge[data-position="AM"] { background: linear-gradient(135deg, #2196F3, #1976D2); }
.position-badge[data-position="FW"], .position-badge[data-position="ST"], .position-badge[data-position="LW"], .position-badge[data-position="RW"] { background: linear-gradient(135deg, #F44336, #C62828); }

/* Status Badge */
.status-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: white; }
.status-badge.active { background: #4CAF50; }
.status-badge.inactive { background: #9e9e9e; }
.status-badge.injured { background: #F44336; }

/* Stats Summary */
.stats-summary { display: flex; gap: 20px; padding: 20px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 12px; margin-top: 20px; margin-bottom: 20px; }
.stat-item { flex: 1; text-align: center; }
.stat-label { font-size: 12px; text-transform: uppercase; color: var(--gray); display: block; margin-bottom: 4px; }
.stat-value { font-size: 20px; font-weight: 700; color: var(--primary); }

/* Pagination */
.pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
.page-link { padding: 8px 14px; background: white; border: 2px solid #e0e0e0; border-radius: 8px; color: var(--dark); text-decoration: none; font-weight: 600; }
.page-link.active { background: var(--primary); color: white; border-color: var(--primary); }

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
</style>

<?php require_once 'includes/footer.php'; ?>
