<?php
$page_title = 'Pemain Team';
$current_page = 'team'; // Keep 'team' as current page to highlight the sidebar correctly
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
    echo "<div class='card'><div class='alert alert-danger'>Team tidak ditemukan.</div><a href='index.php' class='btn-premium btn-export'>Kembali ke Daftar Team</a></div>";
    require_once '../includes/footer.php';
    exit;
}

// Update page title with team name
$page_title = htmlspecialchars($team_info['name'] ?? '') . ' - Pemain';

// Search function
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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
    $count_query = "SELECT COUNT(*) as total FROM players WHERE team_id = ?";
    $count_params = [$team_id];
    if (!empty($search)) {
        $count_query .= " AND (name LIKE ? OR position LIKE ? OR email LIKE ? OR phone LIKE ?)";
        $search_term = "%{$search}%";
        array_push($count_params, $search_term, $search_term, $search_term, $search_term);
    }
    $stmt = $conn->prepare($count_query);
    $stmt->execute($count_params);
    $total_result = $stmt->fetch();
    $total_players = $total_result['total'];
    $total_pages = ceil($total_players / $players_per_page);

    // Validate current page
    if ($current_page_num < 1) $current_page_num = 1;
    if ($current_page_num > $total_pages && $total_pages > 0) $current_page_num = $total_pages;
    
    // Recalculate offset logic if page changed
    $offset = ($current_page_num - 1) * $players_per_page;

    // Get players with pagination
    $base_query = "SELECT 
        id, name, position, jersey_number, birth_date, gender, 
        height, weight, phone, email, status, photo,
        dribbling, technique, speed, juggling, shooting, 
        setplay_position, passing, control,
        TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) as age
        FROM players 
        WHERE team_id = ? ";
    $params = [$team_id];
    if (!empty($search)) {
        $base_query .= " AND (name LIKE ? OR position LIKE ? OR email LIKE ? OR phone LIKE ?) ";
        $search_term = "%{$search}%";
        array_push($params, $search_term, $search_term, $search_term, $search_term);
    }
    $base_query .= " ORDER BY jersey_number ASC, name ASC LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($base_query);
    foreach ($params as $i => $val) {
        $stmt->bindValue($i + 1, $val);
    }
    $stmt->bindValue(count($params) + 1, $players_per_page, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $players = $stmt->fetchAll();
} catch (PDOException $e) {
    $players = [];
    error_log("Database error: " . $e->getMessage());
}
?>

<div class="teams-container">
    <header class="dashboard-hero reveal">
        <div class="hero-content">
            <span class="hero-label">Pemain Team</span>
            <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 16px;">
                <?php if (!empty($team_info['logo'])): ?>
                    <img src="../../images/teams/<?php echo $team_info['logo']; ?>" alt="Logo" style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 2px solid var(--heritage-border);" onerror="this.onerror=null; this.src='../../images/teams/default-team.png'">
                <?php endif; ?>
                <h1 class="hero-title" style="margin: 0;"><?php echo htmlspecialchars($team_info['name'] ?? ''); ?></h1>
            </div>
            <p class="hero-description">Kelola roster dan pantau profil pemain tim ini secara komprehensif.</p>
        </div>
        <div class="hero-actions" style="display: flex; flex-direction: column; gap: 12px; align-items: flex-end;">
            <a href="index.php" class="btn-premium btn-export">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Team
            </a>
        </div>
    </header>

    <div class="reveal d-2">
        <div class="filter-container">
            <div class="teams-filter-card">
                <form action="" method="GET" class="teams-inline-filter">
                    <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
                    <div class="filter-input-wrapper">
                        <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--heritage-text); opacity: 0.5;"></i>
                        <input type="text" name="search" class="teams-search-input" placeholder="Cari pemain berdasarkan nama, posisi..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn-premium">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <?php if(!empty($search)): ?>
                        <a href="?team_id=<?php echo $team_id; ?>" class="btn-premium btn-export">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    <?php if (empty($players)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>Tidak ada pemain ditemukan di team ini.</p>
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
                <a href="?team_id=<?php echo $team_id; ?>&page=1&search=<?php echo urlencode($search); ?>" class="page-link" title="Halaman Pertama">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $current_page_num - 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="Sebelumnya">
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
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                   class="page-link <?php echo $i == $current_page_num ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
            
            <?php if ($current_page_num < $total_pages): ?>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $current_page_num + 1; ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="Berikutnya">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="?team_id=<?php echo $team_id; ?>&page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>" class="page-link" title="Halaman Terakhir">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize skill scores
    document.querySelectorAll('.skill-score').forEach(score => {
        const skillValue = parseFloat(score.textContent);
        
        const getSkillBase = (attr) => {
            const val = score.getAttribute(attr);
            return (val !== null && val !== "") ? parseInt(val) : 5;
        };
        
        const data = {
            dribbling: getSkillBase('data-dribbling'),
            technique: getSkillBase('data-technique'),
            speed: getSkillBase('data-speed'),
            juggling: getSkillBase('data-juggling'),
            shooting: getSkillBase('data-shooting'),
            setplay: getSkillBase('data-setplay'),
            passing: getSkillBase('data-passing'),
            control: getSkillBase('data-control')
        };
        
        const row = score.closest('tr');
        const name = row.querySelector('.name-cell strong').textContent;
        const position = row.querySelector('.position-badge').textContent;
        
        const colors = {
            9: ['#10b981', '#059669'],
            8: ['#84cc16', '#65a30d'],
            7: ['#facc15', '#eab308'],
            6: ['#fbbf24', '#d97706'],
            5: ['#f97316', '#ea580c'],
            4: ['#ef4444', '#dc2626'],
            3: ['#ec4899', '#db2777'],
            1: ['#a855f7', '#9333ea'],
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
                    PERINGKAT KESELURUHAN <span class="overall-value" style="color: ${overallColor}">${skillValue.toFixed(1)}</span>
                </div>
            </div>
        `;
        
        score.setAttribute('data-full-tooltip', tooltipHTML);
        
        score.addEventListener('mouseenter', showTooltip);
        score.addEventListener('mouseleave', hideTooltip);
        score.addEventListener('touchstart', (e) => {
             if(tooltip && tooltip._source === score) {
                 hideTooltip();
             } else {
                 showTooltip(e);
             }
        }, {passive: true});
    });
    
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
        tooltip._source = target;
        
        document.body.appendChild(tooltip);
        
        const rect = target.getBoundingClientRect();
        const spacing = 12;
        let top = rect.top - tooltip.offsetHeight - spacing;
        let left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2);
        
        if (top < 10) {
            top = rect.bottom + spacing;
            tooltip.style.transformOrigin = 'top center';
        } else {
             tooltip.style.transformOrigin = 'bottom center';
        }
        
        if (left < 10) left = 10;
        if (left + tooltip.offsetWidth > window.innerWidth - 10) {
            left = window.innerWidth - tooltip.offsetWidth - 10;
        }

        tooltip.style.top = `${top}px`;
        tooltip.style.left = `${left}px`;
        tooltip.style.position = 'fixed';
        
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
            }, 200);
        }
    }
});
</script>


<?php require_once '../includes/footer.php'; ?>
