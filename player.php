<?php
$hideNavbars = true;
$pageTitle = "Player Directory";
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/player_redesign.css?v=<?php echo time(); ?>">
<?php

// Logic for Search and Pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 40;
$offset = ($page - 1) * $limit;
$player_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

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

// Optional Player Detail (by ID)
$player_detail = null;
if ($player_id > 0) {
    $detail_query = "SELECT p.*, t.name as team_name, t.logo as team_logo 
                     FROM players p 
                     LEFT JOIN teams t ON p.team_id = t.id 
                     WHERE p.id = ? LIMIT 1";
    $detail_stmt = $conn->prepare($detail_query);
    $detail_stmt->bind_param("i", $player_id);
    $detail_stmt->execute();
    $player_detail = $detail_stmt->get_result()->fetch_assoc();
    
    // Add team events if player has a team
    $player_events = [];
    if ($player_detail && !empty($player_detail['team_id'])) {
        $team_info = getTeamById($player_detail['team_id']);
        if ($team_info && !empty($team_info['events_array'])) {
            $player_events = $team_info['events_array'];
        }
    }
    
    // Merge player specific sport_type if not already in list
    if (!empty($player_detail['sport_type']) && !in_array($player_detail['sport_type'], $player_events)) {
        $player_events[] = $player_detail['sport_type'];
    }
    
    $player_detail['team_events_array'] = $player_events;
}

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

?>

<div class="dashboard-wrapper">
    <!-- Mobile Header -->
    <header class="mobile-dashboard-header">
        <div class="mobile-logo">
            <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Buka/Tutup Sidebar" aria-controls="sidebar" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar" aria-hidden="true">
        <div class="sidebar-logo">
            <a href="<?php echo SITE_URL; ?>">
                <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo SITE_URL; ?>"><i class="fas fa-home"></i> <span>BERANDA</span></a>
            <a href="event.php"><i class="fas fa-calendar-alt"></i> <span>EVENT</span></a>
            <a href="team.php"><i class="fas fa-users"></i> <span>TIM</span></a>
            <div class="nav-item-dropdown">
                <a href="#" class="nav-has-dropdown open active" onclick="toggleDropdown(this, 'playerDropdown'); return false;">
                    <div class="nav-link-content">
                        <i class="fas fa-users"></i> <span>PEMAIN</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <div id="playerDropdown" class="sidebar-dropdown show">
                    <a href="player.php" class="active">Pemain</a>
                    <a href="staff.php">Staf Tim</a>
                </div>
            </div>
            <a href="news.php"><i class="fas fa-newspaper"></i> <span>BERITA</span></a>
            <a href="bpjs.php"><i class="fas fa-shield-alt"></i> <span>BPJSTK</span></a>
            <a href="contact.php"><i class="fas fa-envelope"></i> <span>KONTAK</span></a>
            
            <div class="sidebar-divider" style="margin: 15px 0; border-top: 1px solid rgba(255,255,255,0.1);"></div>

            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
                <a href="<?php echo ($_SESSION['admin_role'] === 'pelatih' ? SITE_URL.'/pelatih/dashboard.php' : SITE_URL.'/admin/dashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i> <span>DASHBOARD</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/logout.php" style="color: #e74c3c;">
                    <i class="fas fa-sign-out-alt"></i> <span>KELUAR</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn-login-sidebar">
                    <i class="fas fa-sign-in-alt"></i> <span>MASUK</span>
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content-dashboard">
        <header class="dashboard-header player-header">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">ALVETRIX</div>
                    <h1>Direktori Pemain</h1>
                    <p class="header-subtitle">Daftar pemain aktif lengkap dengan informasi dasar dan statistik.</p>
                </div>
                <div class="header-stats">
                    <div class="header-pill">
                        <i class="fas fa-users"></i> <?php echo number_format($total_records); ?> Pemain
                    </div>
                    <div class="header-pill header-pill-light">
                        <i class="fas fa-check-circle"></i> Aktif
                    </div>
                </div>
            </div>
        </header>

        <div class="dashboard-body">
            <?php if ($player_id > 0): ?>
                <section class="player-detail-card" id="player-detail">
                    <?php if (!$player_detail): ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
                            <h3>Player tidak ditemukan</h3>
                            <p>Pemain dengan ID tersebut tidak tersedia.</p>
                        </div>

                    <?php else: ?>
                        <div class="player-detail-header">
                            <div class="player-detail-identity">
                                <div class="player-photo-lg">
                                    <?php if (!empty($player_detail['photo']) && file_exists('images/players/' . $player_detail['photo'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $player_detail['photo']; ?>" alt="">
                                    <?php else: ?>
                                        <div class="photo-placeholder"><i class="fas fa-user"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div class="player-detail-main">
                                    <h2><?php echo htmlspecialchars($player_detail['name'] ?? ''); ?></h2>
                                    <div class="player-detail-meta">
                                        <span class="meta-pill"><i class="fas fa-shirt"></i> #<?php echo htmlspecialchars($player_detail['jersey_number'] ?: '-'); ?></span>
                                        <span class="meta-pill meta-pill-outline"><i class="fas fa-venus-mars"></i> <?php echo htmlspecialchars($player_detail['gender'] ?: '-'); ?></span>
                                        <span class="meta-pill meta-pill-outline"><i class="fas fa-user-clock"></i> <?php echo calculateAgeV2($player_detail['birth_date']); ?></span>
                                    </div>
                                    <div class="player-team-row">
                                        <?php if (!empty($player_detail['team_logo']) && file_exists('images/teams/' . $player_detail['team_logo'])): ?>
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $player_detail['team_logo']; ?>" class="team-logo-lg" alt="">
                                        <?php else: ?>
                                            <div class="team-logo-lg team-logo-placeholder"></div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="team-label">Tim</div>
                                            <div class="team-name"><?php echo htmlspecialchars($player_detail['team_name'] ?: '-'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="player-detail-actions">
                                <a href="player.php?<?php echo http_build_query(['page' => $page ?: 1, 'search' => $search ?: null]); ?>" class="btn-filter-reset">
                                    <i class="fas fa-arrow-left"></i> Kembali ke daftar
                                </a>
                            </div>
                        </div>

                        <div class="player-detail-grid">
                            <div class="detail-item">
                                <span class="detail-label">NISN</span>
                                <span class="detail-value"><?php echo htmlspecialchars($player_detail['nisn'] ?: '-'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">NIK</span>
                                <span class="detail-value"><?php echo maskNIK($player_detail['nik']); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Tanggal Lahir</span>
                                <span class="detail-value"><?php echo !empty($player_detail['birth_date']) ? date('d M Y', strtotime($player_detail['birth_date'])) : '-'; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Posisi</span>
                                <span class="detail-value"><?php echo htmlspecialchars($player_detail['position'] ?: '-'); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Event</span>
                                <span class="detail-value">
                                    <?php if (!empty($player_detail['team_events_array'])): ?>
                                        <div class="event-badges-container" style="display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px;">
                                            <?php foreach ($player_detail['team_events_array'] as $event_name): ?>
                                                <span class="event-badge" style="font-size: 11px; background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd;"><?php echo htmlspecialchars($event_name); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($player_detail['sport_type'] ?: '-'); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Dibuat Pada</span>
                                <span class="detail-value"><?php echo date('d M Y, H:i', strtotime($player_detail['created_at'])); ?></span>
                            </div>
                        </div>

                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <!-- Filter / Search -->
            <div class="filter-card">
                <form action="" method="GET" class="filter-row">
                    <div class="filter-group">
                        <label for="search">Pencarian</label>
                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari nama, NIK, atau NISN...">
                    </div>
                    <div class="filter-actions-new">
                        <button type="submit" class="btn-filter-apply">
                            <i class="fas fa-search"></i> Cari
                        </button>
                        <a href="player.php" class="btn-filter-reset">
                            <i class="fas fa-redo"></i> Atur Ulang
                        </a>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="player-table-container">
                <table class="player-table-new">
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th class="col-photo">Foto</th>
                            <th>Nama</th>
                            <th>Tim</th>
                            <th class="col-center">No Punggung</th>
                            <th class="col-center">Tgl Lahir</th>
                            <th class="col-center">Usia</th>
                            <th class="col-center">JK</th>
                            <th>NISN</th>
                            <th>NIK</th>
                            <th>Event</th>
                            <th>Dibuat Pada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($players)): ?>
                            <tr>
                                <td colspan="12">
                                    <div class="empty-state">
                                        <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
                                        <h3>Pemain tidak ditemukan</h3>
                                        <p>Coba kata kunci lain atau reset filter pencarian.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $no = $offset + 1;
                            foreach ($players as $p): 
                            ?>
                            <tr>
                                <td class="col-no cell-no" data-label="No"><?php echo $no++; ?></td>
                                <td class="col-photo cell-photo" data-label="Foto">
                                    <?php if (!empty($p['photo']) && file_exists('images/players/' . $p['photo'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/images/players/<?php echo $p['photo']; ?>" class="player-photo-sm" alt="">
                                    <?php else: ?>
                                        <div class="placeholder-img"><i class="fas fa-user"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-name" data-label="Nama">
                                    <a href="player.php?id=<?php echo $p['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>#player-detail" class="player-link">
                                        <?php echo htmlspecialchars($p['name'] ?? ''); ?>
                                    </a>
                                </td>
                                <td class="cell-team" data-label="Tim">
                                    <div class="col-team">
                                        <?php if (!empty($p['team_logo']) && file_exists('images/teams/' . $p['team_logo'])): ?>
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $p['team_logo']; ?>" class="team-logo-small" alt="">
                                        <?php else: ?>
                                            <div class="team-logo-small team-logo-placeholder"></div>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($p['team_name'] ?: '-'); ?></span>
                                    </div>
                                </td>
                                <td class="col-center" data-label="No Punggung"><?php echo $p['jersey_number'] ?: '-'; ?></td>
                                <td class="col-center" data-label="Tgl Lahir"><?php echo !empty($p['birth_date']) ? date('d M Y', strtotime($p['birth_date'])) : '-'; ?></td>
                                <td class="col-center" data-label="Usia"><?php echo calculateAgeV2($p['birth_date']); ?></td>
                                <td class="col-center" data-label="JK"><?php echo $p['gender'] ?: '-'; ?></td>
                                <td data-label="NISN"><?php echo htmlspecialchars($p['nisn'] ?: '-'); ?></td>
                                <td data-label="NIK"><?php echo maskNIK($p['nik']); ?></td>
                                <td data-label="Event">

                                    <?php 
                                    $p_events = [];
                                    if (!empty($p['team_id'])) {
                                        $p_team = getTeamById($p['team_id']);
                                        if ($p_team && !empty($p_team['events_array'])) {
                                            $p_events = $p_team['events_array'];
                                        }
                                    }
                                    $eventLabel = !empty($p['sport_type']) ? $p['sport_type'] : (!empty($p_events[0]) ? $p_events[0] : '');
                                    ?>

                                    <?php if (!empty($eventLabel)): ?>
                                        <div class="team-events-badges" style="display: flex; flex-wrap: wrap; gap: 4px;">
                                            <span class="event-badge" style="font-size: 9px; padding: 1px 6px; background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd;"><?php echo htmlspecialchars($eventLabel); ?></span>
                                        </div>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                </td>
                                <td data-label="Dibuat Pada"><?php echo date('d M Y, H:i', strtotime($p['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-info">
                <div class="info-text">
                    Menampilkan <?php echo min($offset + 1, $total_records); ?> sampai <?php echo min($offset + $limit, $total_records); ?> dari <?php echo number_format($total_records); ?> data
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-controls">
                        <!-- Previous -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Sebelumnya</a>
                        <?php else: ?>
                            <span class="disabled">Sebelumnya</span>
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
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Berikutnya</a>
                        <?php else: ?>
                            <span class="disabled">Berikutnya</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <footer class="dashboard-footer">
            <p>&copy; 2026 ALVETRIX. Semua hak dilindungi.</p>
            <p>
                <a href="<?php echo SITE_URL; ?>">Beranda</a> |
                <a href="contact.php">Kontak</a> |
                <a href="bpjs.php">BPJSTK</a>
            </p>
        </footer>
    </main>
</div>

<script>
// Sidebar Dropdown Toggle
function toggleDropdown(element, dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;
    
    dropdown.classList.toggle('show');
    element.classList.toggle('open');
}

// Sidebar Toggle Strategy for Mobile
const sidebar = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarOverlay = document.getElementById('sidebarOverlay');

const setSidebarOpen = (open) => {
    if (!sidebar || !sidebarToggle || !sidebarOverlay) return;
    sidebar.classList.toggle('active', open);
    sidebarOverlay.classList.toggle('active', open);
    sidebarToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
    sidebarOverlay.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.classList.toggle('sidebar-open', open);
};

if (sidebarToggle && sidebar && sidebarOverlay) {
    sidebarToggle.addEventListener('click', () => {
        const isOpen = sidebar.classList.contains('active');
        setSidebarOpen(!isOpen);
    });

    sidebarOverlay.addEventListener('click', () => {
        setSidebarOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebarOpen(false);
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) {
            setSidebarOpen(false);
        }
    });
}
</script>

<script>
// Define SITE_URL for JavaScript
const SITE_URL = '<?php echo SITE_URL; ?>';
</script>
<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>

