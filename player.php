<?php
require_once 'includes/header.php';

// Logic for Search and Pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 40;
$offset = ($page - 1) * $limit;

// Database connection
$conn = $db->getConnection();

// Query for Total Records (for pagination)
$count_query = "SELECT COUNT(*) as total FROM players p WHERE p.status = 'active'";
if (!empty($search)) {
    $count_query .= " AND (p.name LIKE ? OR p.nik LIKE ? OR p.nisn LIKE ?)";
}

$stmt_count = $conn->prepare($count_query);
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt_count->bind_param("sss", $search_param, $search_param, $search_param);
}
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Query for Player Data
$query = "SELECT p.*, t.name as team_name, t.logo as team_logo 
          FROM players p 
          LEFT JOIN teams t ON p.team_id = t.id 
          WHERE p.status = 'active'";
if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.nik LIKE ? OR p.nisn LIKE ?)";
}
$query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!empty($search)) {
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper Functions
function calculateAgeV2($birth_date) {
    if (empty($birth_date) || $birth_date == '0000-00-00') return '-';
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $diff = $today->diff($birth);
    return $diff->y . 'y ' . $diff->m . 'm';
}

function maskNIK($nik) {
    if (empty($nik)) return '-';
    if (strlen($nik) < 8) return $nik;
    return substr($nik, 0, 3) . str_repeat('*', 9) . substr($nik, -4);
}

// Page Metadata
$pageTitle = "Player List";
?>

<style>
/* Hero Banner Styles */
.player-hero {
    background: linear-gradient(135deg, #1a1a1a 0%, #c00 100%);
    padding: 60px 0;
    text-align: center;
    color: #fff;
    margin-bottom: 40px;
}

.player-hero h1 {
    font-size: 48px;
    font-weight: 800;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 3px;
}

/* CSS Reset and Base for the section */
.player-list-section {
    padding: 40px 0;
    color: #fff;
}

.search-container {
    margin-bottom: 20px;
    max-width: 400px;
}

.search-wrapper {
    position: relative;
    display: flex;
}

.search-wrapper input {
    width: 100%;
    padding: 12px 15px;
    background: #fff;
    border: none;
    border-radius: 4px;
    color: #333;
    font-size: 14px;
}

.player-table-container {
    background: #fff;
    border-radius: 8px;
    overflow-x: auto;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    border: 1px solid #333;
}

.player-table {
    width: 100%;
    border-collapse: collapse;
    color: #333;
    font-size: 13px;
    min-width: 1200px;
}

.player-table thead tr {
    background: linear-gradient(to right, #000, #c00); /* Dark to Red gradient */
    color: #fff;
}

.player-table th {
    padding: 12px 10px;
    text-align: left;
    font-weight: 700;
    text-transform: capitalize;
    border-right: 1px solid rgba(255,255,255,0.1);
}

.player-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.player-table tbody tr:hover {
    background-color: #f9f9f9;
}

/* Specific alignments and styles from reference */
.col-no { width: 40px; text-align: center; }
.col-photo { width: 60px; text-align: center; }
.col-name { color: #0066cc; font-weight: 500; }
.col-team { display: flex; align-items: center; gap: 8px; }
.team-logo-small { width: 24px; height: 24px; border-radius: 50%; object-fit: contain; background: #eee; }
.col-center { text-align: center; }

.player-img-sm {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    object-fit: cover;
}

.placeholder-img {
    width: 40px;
    height: 40px;
    background: #ddd;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
}

/* Pagination Styles */
.pagination-info {
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #ccc; /* Improved contrast for dark background */
    font-size: 14px;
}

.pagination-controls {
    display: flex;
    gap: 0;
    border-radius: 4px;
    overflow: hidden;
    border: 1px solid #ddd;
}

.pagination-controls a, 
.pagination-controls span {
    padding: 8px 16px;
    background: #fff;
    color: #333;
    text-decoration: none;
    border-right: 1px solid #ddd;
}

.pagination-controls a:last-child { border-right: none; }

.pagination-controls a:hover {
    background: #eee;
}

.pagination-controls .active {
    background: #0066cc;
    color: #fff;
    border-color: #0066cc;
}

.pagination-controls .disabled {
    color: #ccc;
    cursor: default;
}

/* Header sort icons (decorative) */
.sort-icon::after {
    content: " \21D5";
    font-size: 10px;
    opacity: 0.5;
}

/* Horizontal scrollbar styling for the table container */
.player-table-container::-webkit-scrollbar {
    height: 10px;
}
.player-table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.player-table-container::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 5px;
}
.player-table-container::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<!-- Banner Hero Section DI LUAR container -->
<div class="player-hero">
    <div class="container">
        <h1>PLAYERS</h1>
    </div>
</div>

<div class="container">
    <div class="player-list-section">
        <!-- Search Bar -->
        <div class="search-container">
            <form action="" method="GET" class="search-wrapper">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search...">
            </form>
        </div>

        <!-- Table -->
        <div class="player-table-container">
            <table class="player-table">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-photo">Photo</th>
                        <th class="sort-icon">Nama</th>
                        <th class="sort-icon">Team</th>
                        <th class="col-center sort-icon">No Punggung</th>
                        <th class="col-center sort-icon">Tgl Lahir</th>
                        <th class="col-center sort-icon">Usia</th>
                        <th class="col-center sort-icon">JK</th>
                        <th class="sort-icon">NISN</th>
                        <th class="sort-icon">NIK</th>
                        <th class="sort-icon">Cabor</th>
                        <th class="col-center sort-icon"># Events</th>
                        <th class="col-center sort-icon"># Matches</th>
                        <th class="sort-icon">Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($players)): ?>
                        <tr>
                            <td colspan="14" class="col-center" style="padding: 40px;">No players found.</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($players as $p): 
                        ?>
                        <tr>
                            <td class="col-no"><?php echo $no++; ?></td>
                            <td class="col-photo">
                                <?php if (!empty($p['photo']) && file_exists('images/players/' . $p['photo'])): ?>
                                    <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $p['photo']; ?>" class="player-img-sm" alt="">
                                <?php else: ?>
                                    <div class="placeholder-img"><i class="fas fa-user"></i></div>
                                <?php endif; ?>
                            </td>
                            <td class="col-name">
                                <a href="<?php echo SITE_URL; ?>/player_view.php?id=<?php echo $p['id']; ?>" style="color: inherit; text-decoration: none;">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </a>
                            </td>
                            <td>
                                <div class="col-team">
                                    <?php if (!empty($p['team_logo']) && file_exists('images/teams/' . $p['team_logo'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $p['team_logo']; ?>" class="team-logo-small" alt="">
                                    <?php else: ?>
                                        <div class="team-logo-small" style="background: #ddd;"></div>
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($p['team_name'] ?: '-'); ?></span>
                                </div>
                            </td>
                            <td class="col-center"><?php echo $p['jersey_number'] ?: '-'; ?></td>
                            <td class="col-center"><?php echo !empty($p['birth_date']) ? date('d M Y', strtotime($p['birth_date'])) : '-'; ?></td>
                            <td class="col-center"><?php echo calculateAgeV2($p['birth_date']); ?></td>
                            <td class="col-center"><?php echo $p['gender'] ?: '-'; ?></td>
                            <td><?php echo htmlspecialchars($p['nisn'] ?: '-'); ?></td>
                            <td><?php echo maskNIK($p['nik']); ?></td>
                            <td><?php echo htmlspecialchars($p['sport_type'] ?: '-'); ?></td>
                            <td class="col-center">0</td>
                            <td class="col-center">0</td>
                            <td><?php echo date('d M Y, H:i', strtotime($p['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-info">
            <div class="info-text">
                Showing <?php echo min($offset + 1, $total_records); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo number_format($total_records); ?> entries
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination-controls">
                    <!-- Previous -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                    <?php else: ?>
                        <span class="disabled">Previous</span>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo '<a href="?page=1&search='.urlencode($search).'">1</a>';
                        if ($start_page > 2) echo '<span>...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span>...</span>';
                        echo '<a href="?page='.$total_pages.'&search='.urlencode($search).'">'.$total_pages.'</a>';
                    }
                    ?>

                    <!-- Next -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                    <?php else: ?>
                        <span class="disabled">Next</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>