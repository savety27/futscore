<?php
$page_title = 'Pemain Saya';
$current_page = 'players';
require_once 'config/database.php';
require_once 'includes/header.php';

$team_id = $_SESSION['team_id'] ?? 0;
$filter_category = trim((string)($_GET['category'] ?? ''));
$filter_search = trim((string)($_GET['q'] ?? ''));

// Pagination settings
$players_per_page = 10;
$current_page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page_num - 1) * $players_per_page;

// Get total players for pagination
$total_players = 0;
$players = [];
$total_pages = 1;
$category_options = [];

function tableExists(PDO $conn, string $table): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }

    $stmt = $conn->query("SHOW TABLES LIKE " . $conn->quote($table));
    return (bool)$stmt->fetchColumn();
}

function columnExists(PDO $conn, string $table, string $column): bool
{
    if (!tableExists($conn, $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }

    $stmt = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE " . $conn->quote($column));
    return (bool)$stmt->fetchColumn();
}

function hasPlayerRelatedData(PDO $conn, int $playerId, array $usageMap): bool
{
    foreach ($usageMap as $usage) {
        $table = (string)($usage['table'] ?? '');
        $column = (string)($usage['column'] ?? '');
        if (!columnExists($conn, $table, $column)) {
            continue;
        }

        $stmt = $conn->prepare("SELECT 1 FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
        $stmt->execute([$playerId]);
        if ((bool)$stmt->fetchColumn()) {
            return true;
        }
    }

    return false;
}

if ($team_id) {
    try {
        $stmtCategory = $conn->prepare("SELECT DISTINCT sport_type 
                                        FROM players 
                                        WHERE team_id = :team_id 
                                          AND sport_type IS NOT NULL 
                                          AND sport_type <> '' 
                                        ORDER BY sport_type ASC");
        $stmtCategory->execute([':team_id' => $team_id]);
        $category_options = $stmtCategory->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $where = ["team_id = :team_id"];
        $params = [':team_id' => $team_id];

        if ($filter_category !== '') {
            $where[] = "sport_type = :sport_type";
            $params[':sport_type'] = $filter_category;
        }

        if ($filter_search !== '') {
            $where[] = "(name LIKE :search_name OR jersey_number LIKE :search_jersey)";
            $search_like = '%' . $filter_search . '%';
            $params[':search_name'] = $search_like;
            $params[':search_jersey'] = $search_like;
        }

        $where_sql = implode(' AND ', $where);

        // Get total count
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM players WHERE $where_sql");
        $stmt->execute($params);
        $total_result = $stmt->fetch();
        $total_players = (int)($total_result['total'] ?? 0);
        $total_pages = max(1, (int)ceil($total_players / $players_per_page));

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
            setplay_position, passing, control, sport_type,
            TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) as age
            FROM players 
            WHERE $where_sql
            ORDER BY jersey_number ASC, name ASC
            LIMIT :limit OFFSET :offset");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $players_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $players = $stmt->fetchAll();

        $usage_map = [
            ['table' => 'lineups', 'column' => 'player_id'],
            ['table' => 'goals', 'column' => 'player_id'],
            ['table' => 'transfers', 'column' => 'player_id'],
            ['table' => 'player_event_cards', 'column' => 'player_id']
        ];

        foreach ($players as &$player) {
            $player['has_related_data'] = hasPlayerRelatedData($conn, (int)($player['id'] ?? 0), $usage_map);
        }
        unset($player);
    } catch (PDOException $e) {
        $players = [];
        error_log("Database error: " . $e->getMessage());
    }
}

$base_query_params = [];
if ($filter_category !== '') {
    $base_query_params['category'] = $filter_category;
}
if ($filter_search !== '') {
    $base_query_params['q'] = $filter_search;
}
$build_page_url = function(int $page) use ($base_query_params): string {
    $params = $base_query_params;
    $params['page'] = $page;
    return '?' . http_build_query($params);
};
$players_export_url = 'players_export.php' . (!empty($base_query_params) ? '?' . http_build_query($base_query_params) : '');
?>

<style>
/* Background only: baby-blue like dashboard */
.main {
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.6);
    border-radius: 20px;
    padding: 22px 24px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    gap: 12px;
    flex-wrap: wrap;
}

.page-title-wrap {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.page-title {
    margin: 0;
    font-size: 28px;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 12px;
    line-height: 1.15;
}

.page-title i {
    color: var(--secondary);
}

.page-subtitle {
    margin: 0;
    color: var(--gray);
    font-size: 14px;
}

.summary-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 999px;
    background: #eef5ff;
    color: var(--primary);
    border: 1px solid #dbeafe;
    font-size: 13px;
    font-weight: 700;
}

.section-header {
    margin-bottom: 16px;
}

.filter-container {
    margin-bottom: 20px;
}

.players-filter-card {
    padding: 16px;
    border: 1px solid #dbe5f3;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    box-shadow: 0 8px 20px rgba(10, 36, 99, 0.06);
}

.players-filter-form {
    display: grid;
    grid-template-columns: minmax(240px, 1fr) minmax(210px, 0.72fr) auto;
    gap: 12px;
    align-items: center;
}

.players-search-group {
    position: relative;
}

.players-search-group i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #7b8797;
    font-size: 13px;
}

.players-search-input,
.players-filter-select {
    width: 100%;
    height: 42px;
    border: 1px solid #d3dcea;
    border-radius: 10px;
    background: #ffffff;
    color: #1f2937;
    font-size: 14px;
    transition: all 0.2s ease;
}

.players-search-input {
    padding: 0 12px 0 36px;
}

.players-filter-select {
    padding: 0 12px;
}

.players-search-input:focus,
.players-filter-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.12);
}

.players-filter-actions {
    display: flex;
    gap: 8px;
}

.players-filter-actions .btn-filter,
.players-filter-actions .clear-filter-btn {
    height: 42px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid transparent;
    font-size: 13px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    white-space: nowrap;
}

.players-filter-actions .btn-filter {
    background: linear-gradient(135deg, var(--primary), #1a4f9e);
    color: #ffffff;
}

.players-filter-actions .btn-filter:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(10, 36, 99, 0.22);
}

.players-filter-actions .clear-filter-btn {
    background: #ffffff;
    border-color: #d3dcea;
    color: #3b4a5f;
}

.players-filter-actions .clear-filter-btn:hover {
    background: #f2f6fc;
}

@media (max-width: 768px) {
    .page-header {
        padding: 18px;
        border-radius: 16px;
    }

    .page-title {
        font-size: 23px;
    }

    .players-filter-form {
        grid-template-columns: 1fr;
    }

    .players-filter-actions {
        width: 100%;
    }

    .players-filter-actions .btn-filter,
    .players-filter-actions .clear-filter-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="page-header">
    <div class="page-title-wrap">
        <h1 class="page-title"><i class="fas fa-users"></i> Direktori Pemain</h1>
        <p class="page-subtitle">Kelola roster, filter kategori, dan pantau profil pemain aktif tim Anda.</p>
    </div>
    <div class="page-summary">
        <span class="summary-pill"><i class="fas fa-id-badge"></i> <?php echo (int)$total_players; ?> Pemain</span>
    </div>
</div>

<div class="card">
    <div class="section-header">
        <h2 class="section-title">Daftar Pemain</h2>
        <div class="section-actions">
            <a href="<?php echo htmlspecialchars($players_export_url); ?>" class="btn-export">
                <i class="fas fa-download"></i> Export Excel
            </a>
            <a href="player_form.php" class="btn-primary">
                <i class="fas fa-plus"></i> Tambah Pemain
            </a>
        </div>
    </div>

<?php if (isset($_GET['msg'])): ?>
        <div class="message-alert">
            <?php 
                if ($_GET['msg'] == 'added') echo "<i class='fas fa-check-circle'></i> Pemain berhasil ditambahkan!";
                if ($_GET['msg'] == 'updated') echo "<i class='fas fa-check-circle'></i> Pemain berhasil diperbarui!";
                if ($_GET['msg'] == 'deleted') echo "<i class='fas fa-check-circle'></i> Pemain berhasil dihapus!";
                if ($_GET['msg'] == 'no_changes_or_unauthorized') echo "<i class='fas fa-exclamation-triangle'></i> Tidak ada perubahan yang dilakukan atau tindakan tidak berwenang.";
                if ($_GET['msg'] == 'no_changes') echo "<i class='fas fa-exclamation-triangle'></i> Tidak ada perubahan yang dilakukan.";
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error']) && $_GET['error'] !== ''): ?>
        <div class="message-alert" style="background: #fff3cd; color: #856404; border-left: 4px solid #f0ad4e;">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="filter-container">
        <div class="players-filter-card">
            <form method="GET" class="players-filter-form">
                <div class="players-search-group">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" class="players-search-input" placeholder="Cari nama atau nomor pemain..." value="<?php echo htmlspecialchars($filter_search); ?>">
                </div>
                <div>
                    <select name="category" class="players-filter-select">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($category_options as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $filter_category === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="players-filter-actions">
                    <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Terapkan</button>
                    <a href="players.php" class="clear-filter-btn"><i class="fas fa-times"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($players)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <p>Tidak ada pemain ditemukan.</p>
            <a href="player_form.php" class="btn-primary">Tambah Pemain Pertama Anda</a>
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
                        <th>Kategori</th>
                        <th>Umur</th>
                        <th>Jenis Kelamin</th>
                        <th>Kontak</th>
                        <th style="width: 80px;">Skills</th>
                        <th>Status</th>
                        <th style="width: 200px;">Aksi</th>
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
                            $photo_path = 'images/players/' . $player['photo'];
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
                            <a href="player_view.php?id=<?php echo $player['id']; ?>" 
                               class="player-name-link"
                               title="Klik untuk lihat detail pemain"
                               aria-label="Lihat detail pemain <?php echo htmlspecialchars($player['name'] ?? ''); ?>">
                                <strong><?php echo htmlspecialchars($player['name'] ?? ''); ?></strong>
                                <span class="player-click-hint"><i class="fas fa-mouse-pointer"></i> Klik nama untuk lihat detail</span>
                            </a>
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
                        <td><?php echo htmlspecialchars($player['sport_type'] ?? '-'); ?></td>
                        <td class="age-cell">
                            <?php echo htmlspecialchars($player['age'] ?? 'N/A'); ?> thn
                        </td>
                        <td class="gender-cell" data-gender="<?php echo $player['gender']; ?>">
                            <?php echo $player['gender'] == 'L' ? '♂' : '♀'; ?>
                        </td>
                        <td class="contact-cell">
                            <div class="contact-info">
                                <?php if (!empty($player['phone'])): ?>
                                    <small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($player['phone'] ?? ''); ?></small><br>
                                <?php endif; ?>
                                <?php if (!empty($player['email'])): ?>
                                    <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($player['email'] ?? ''); ?></small>
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
                                <?php 
                                    $status_map = ['active' => 'Aktif', 'inactive' => 'Non-aktif', 'injured' => 'Cedera', 'suspended' => 'Skorsing'];
                                    echo $status_map[$player['status'] ?? ''] ?? ucfirst($player['status'] ?? ''); 
                                ?>
                            </span>
                        </td>
                        <td class="actions-cell">
                            <div class="action-buttons">
                                <?php $delete_disabled = !empty($player['has_related_data']); ?>
                                <!-- TAMBAHKAN TOMBOL VIEW DISINI -->
                                <a href="player_view.php?id=<?php echo $player['id']; ?>" 
                                   class="btn-primary btn-sm btn-view"
                                   title="Lihat Detail Pemain"
                                   aria-label="Lihat Detail Pemain">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="player_form.php?id=<?php echo $player['id']; ?>" 
                                   class="btn-primary btn-sm"
                                   title="Ubah Pemain"
                                   aria-label="Ubah Pemain">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="player_actions.php" method="POST" class="delete-form<?php echo $delete_disabled ? ' delete-form-disabled' : ''; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $player['id']; ?>">
                                    <button type="submit" 
                                             class="btn-primary btn<?php echo $delete_disabled ? ' btn-delete-disabled' : ''; ?>"
                                            title="<?php echo $delete_disabled ? 'Tidak bisa dihapus karena sudah ada data turunan (event/match/dll)' : 'Hapus Pemain'; ?>"
                                            aria-label="Hapus Pemain"
                                            data-name="<?php echo htmlspecialchars($player['name'] ?? ''); ?>"
                                            <?php echo $delete_disabled ? 'disabled aria-disabled="true"' : ''; ?>>
                                        <i class="fas fa-trash"></i>
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
                <span class="stat-label">Total Pemain:</span>
                <span class="stat-value"><?php echo $total_players; ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Aktif:</span>
                <span class="stat-value">
                    <?php 
                        $active_count = array_filter($players, fn($p) => $p['status'] == 'active');
                        echo count($active_count);
                    ?>
                </span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Rata-rata Umur:</span>
                <span class="stat-value">
                    <?php 
                        $ages = array_filter(array_column($players, 'age'), 'is_numeric');
                        echo !empty($ages) ? round(array_sum($ages) / count($ages), 1) : 'N/A';
                    ?> thn
                </span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Halaman:</span>
                <span class="stat-value">
                    <?php echo $current_page_num; ?> dari <?php echo $total_pages; ?>
                </span>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($current_page_num > 1): ?>
                <a href="<?php echo htmlspecialchars($build_page_url(1)); ?>" class="page-link" title="Halaman Pertama">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="<?php echo htmlspecialchars($build_page_url($current_page_num - 1)); ?>" class="page-link" title="Sebelumnya">
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
                <a href="<?php echo htmlspecialchars($build_page_url($i)); ?>" 
                   class="page-link <?php echo $i == $current_page_num ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): ?>
                <span class="page-dots">...</span>
            <?php endif; ?>
            
            <?php if ($current_page_num < $total_pages): ?>
                <a href="<?php echo htmlspecialchars($build_page_url($current_page_num + 1)); ?>" class="page-link" title="Berikutnya">
                    <i class="fas fa-angle-right"></i>
                </a>
                <a href="<?php echo htmlspecialchars($build_page_url($total_pages)); ?>" class="page-link" title="Halaman Terakhir">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<link rel="stylesheet" href="css/players.css?v=<?php echo (int)@filemtime(__DIR__ . '/css/players.css'); ?>">


<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentUrl = new URL(window.location.href);
    const errorMsg = currentUrl.searchParams.get('error');

    if (errorMsg && typeof Swal !== 'undefined') {
        let detail = '';
        const lowerError = errorMsg.toLowerCase();
        if (lowerError.includes('tidak dapat menghapus') || lowerError.includes('foreign key') || lowerError.includes('1451')) {
            detail = 'Pemain ini sudah tercatat di data pertandingan (mis. lineup, gol, atau event terkait), jadi tidak bisa dihapus.';
        }

        Swal.fire({
            icon: 'warning',
            title: 'Penghapusan Gagal',
            html: `<p style="margin:0 0 8px 0;">${errorMsg}</p>${detail ? `<p style="margin:0; color:#666; font-size:13px;">${detail}</p>` : ''}`,
            confirmButtonText: 'Mengerti',
            confirmButtonColor: '#f0ad4e'
        });
    }

    // Remove flash message query params after first load
    if (currentUrl.searchParams.has('msg') || currentUrl.searchParams.has('error')) {
        currentUrl.searchParams.delete('msg');
        currentUrl.searchParams.delete('error');
        const query = currentUrl.searchParams.toString();
        window.history.replaceState({}, document.title, currentUrl.pathname + (query ? '?' + query : ''));
    }

    // Initialize skill scores
    document.querySelectorAll('.skill-score').forEach(score => {
        const skillValue = parseFloat(score.textContent);
        
        // Data extraction - FIXED: Handled 0 correctly
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
                    PERINGKAT KESELURUHAN <span class="overall-value" style="color: ${overallColor}">${skillValue.toFixed(1)}</span>
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

    // --- DELETE CONFIRMATION HANDLER ---
    const deleteForms = document.querySelectorAll('.delete-form');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = form.querySelector('.btn');
            if (!btn || btn.disabled) {
                return;
            }
            const playerName = btn.getAttribute('data-name');
            
            confirmDelete(playerName).then(confirmed => {
                if (confirmed) {
                    form.submit();
                }
            });
        });
    });
});

// Delete confirmation with SweetAlert2 (Preserved)
function confirmDelete(playerName) {
    if (typeof Swal !== 'undefined') {
        return new Promise((resolve) => {
            Swal.fire({
                title: 'Hapus Pemain?',
                html: `<div style="text-align: left;">
                    <p>Apakah Anda yakin ingin menghapus <strong>"${playerName}"</strong>?</p>
                    <p style="color: #666; font-size: 14px; margin-top: 10px;">
                        <i class="fas fa-exclamation-triangle" style="color: #ff9800;"></i>
                        Tindakan ini tidak dapat dibatalkan. Semua data pemain termasuk:
                    </p>
                    <ul style="text-align: left; margin: 10px 0 10px 20px; color: #666; font-size: 13px;">
                        <li>Profil pemain</li>
                        <li>Statistik kemampuan</li>
                        <li>Foto dan dokumen</li>
                        <li>Riwayat pertandingan</li>
                    </ul>
                </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash"></i> Hapus',
                cancelButtonText: '<i class="fas fa-times"></i> Batal',
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
        return confirm(`Apakah Anda yakin ingin menghapus "${playerName}"?\nTindakan ini tidak dapat dibatalkan.`);
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
