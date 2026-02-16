<?php
$hideNavbars = true;
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/event_redesign.css?v=<?php echo time(); ?>">
<?php

// Page Metadata
$pageTitle = "Event";

// Logic for search/filter/pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$filter_sport = isset($_GET['sport']) ? trim($_GET['sport']) : 'all';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Database connection
$conn = $db->getConnection();

// Build WHERE conditions
$where_conditions = ["c.sport_type IS NOT NULL", "c.sport_type != ''"];
$params = [];
$types = '';

if ($search !== '') {
    $where_conditions[] = "c.sport_type LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}

if ($filter_sport !== '' && $filter_sport !== 'all') {
    $where_conditions[] = "c.sport_type = ?";
    $params[] = $filter_sport;
    $types .= 's';
}

$where_sql = ' WHERE ' . implode(' AND ', $where_conditions);

// Build HAVING for derived event status
$having_sql = '';
if ($filter_status === 'completed') {
    $having_sql = " HAVING SUM(CASE WHEN LOWER(COALESCE(c.status, '')) <> 'completed' THEN 1 ELSE 0 END) = 0";
} elseif ($filter_status === 'active') {
    $having_sql = " HAVING SUM(CASE WHEN LOWER(COALESCE(c.status, '')) <> 'completed' THEN 1 ELSE 0 END) > 0";
}

// Count grouped records for pagination
$count_query = "SELECT COUNT(*) AS total FROM (
    SELECT c.sport_type
    FROM challenges c
    $where_sql
    GROUP BY c.sport_type
    $having_sql
) grouped_events";

$stmt_count = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result()->fetch_assoc();
$total_records = (int) ($count_result['total'] ?? 0);
$total_pages = max(1, (int) ceil($total_records / $limit));

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// Fetch category-level event rows
$query = "SELECT
    c.sport_type,
    COUNT(*) AS total_matches,
    SUM(CASE WHEN LOWER(COALESCE(c.status, '')) = 'completed' THEN 1 ELSE 0 END) AS completed_matches,
    SUM(CASE WHEN LOWER(COALESCE(c.status, '')) <> 'completed' THEN 1 ELSE 0 END) AS pending_matches,
    MIN(c.challenge_date) AS first_match_date,
    MAX(c.challenge_date) AS last_match_date
FROM challenges c
$where_sql
GROUP BY c.sport_type
$having_sql
ORDER BY last_match_date DESC
LIMIT ? OFFSET ?";

$query_params = $params;
$query_params[] = $limit;
$query_params[] = $offset;
$query_types = $types . 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($query_types, ...$query_params);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Distinct categories for dropdown
$sport_query = "SELECT DISTINCT sport_type FROM challenges WHERE sport_type IS NOT NULL AND sport_type != '' ORDER BY sport_type";
$sport_result = $conn->query($sport_query);
$sports = [];
while ($row = $sport_result->fetch_assoc()) {
    $sports[] = $row['sport_type'];
}

function getEventStatusBadge($pending_matches) {
    if ((int) $pending_matches === 0) {
        return '<span class="badge-new badge-completed">Completed</span>';
    }
    return '<span class="badge-new badge-pending">Active</span>';
}

function formatEventDate($datetime) {
    if (empty($datetime)) {
        return '-';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '-';
    }

    return date('d M Y H:i', $timestamp);
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
            <a href="event.php" class="active"><i class="fas fa-calendar-alt"></i> <span>EVENT</span></a>
            <a href="team.php"><i class="fas fa-users"></i> <span>TIM</span></a>
            <div class="nav-item-dropdown">
                <a href="#" class="nav-has-dropdown" onclick="toggleDropdown(this, 'playerDropdown'); return false;">
                    <div class="nav-link-content">
                        <i class="fas fa-users"></i> <span>PEMAIN</span>
                    </div>
                    <i class="fas fa-chevron-down dropdown-icon"></i>
                </a>
                <div id="playerDropdown" class="sidebar-dropdown">
                    <a href="player.php">Pemain</a>
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
        <header class="dashboard-header">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">ALVETRIX</div>
                    <h1>EVENT BERDASARKAN CABOR</h1>
                    <p class="header-subtitle">Pantau kategori event berdasarkan cabor dan progres pertandingan tiap kategori.</p>
                </div>
            </div>
        </header>

        <div class="dashboard-body">
            <!-- Filter Section -->
            <div class="filter-card">
                <form method="GET" action="" class="filter-row">
                    <!-- Search -->
                    <div class="filter-group">
                        <label for="search">Pencarian</label>
                        <input type="text" name="search" id="search" placeholder="Cari..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                    
                    <!-- Status Filter -->
                    <div class="filter-group">
                        <label for="status">Status Event</label>
                        <select name="status" id="status">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    
                    <!-- Sport Filter -->
                    <div class="filter-group">
                        <label for="sport">Kategori Event (Cabor)</label>
                        <select name="sport" id="sport">
                            <option value="all" <?php echo $filter_sport === 'all' ? 'selected' : ''; ?>>Semua Kategori</option>
                            <?php foreach ($sports as $sport): ?>
                                <option value="<?php echo htmlspecialchars($sport ?? ''); ?>" <?php echo $filter_sport == $sport ? 'selected' : ''; ?>><?php echo htmlspecialchars($sport ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="filter-actions-new">
                        <button type="submit" class="btn-filter-apply">
                            <i class="fas fa-filter"></i> Terapkan Filter
                        </button>
                        <a href="event.php" class="btn-filter-reset">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                    
                    <!-- Hidden inputs for pagination -->
                    <?php if ($page > 1): ?>
                        <input type="hidden" name="page" value="<?php echo $page; ?>">
                    <?php endif; ?>
                </form>
            </div>

            <!-- Table -->
            <div class="table-container-new">
                <table class="event-table-new">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">No</th>
                            <th>Kategori Event (Cabor)</th>
                            <th style="width: 120px; text-align: center;">Total Match</th>
                            <th style="width: 120px; text-align: center;">Completed</th>
                            <th style="width: 140px; text-align: center;">Belum Selesai</th>
                            <th style="width: 170px; text-align: center;">Jadwal Terakhir</th>
                            <th style="width: 120px; text-align: center;">Status Event</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <div class="empty-state" style="box-shadow: none; padding: 20px;">
                                    <div class="empty-icon">
                                        <i class="fas fa-calendar-times"></i>
                                    </div>
                                    <h3 style="font-size: 16px; margin-bottom: 10px;">Tidak Ada Event Ditemukan</h3>
                                    <p style="font-size: 14px; margin-bottom: 15px;">
                                        Tidak ada kategori cabor yang sesuai dengan filter yang Anda pilih.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = $offset + 1;
                        foreach ($events as $e): 
                        ?>
                        <tr class="match-row-new">
                            <td data-label="No" style="text-align: center; font-weight: 700; color: #666;"><?php echo $no++; ?></td>
                            <td data-label="Kategori Event" style="font-weight: 700; color: #002d62;">
                                <span class="badge-new badge-cabor"><?php echo htmlspecialchars($e['sport_type'] ?? '-'); ?></span>
                            </td>
                            <td data-label="Total Match" style="text-align: center; font-weight: 700; color: #002d62;">
                                <?php echo number_format((int) ($e['total_matches'] ?? 0)); ?>
                            </td>
                            <td data-label="Completed" style="text-align: center; color: #059669; font-weight: 700;">
                                <?php echo number_format((int) ($e['completed_matches'] ?? 0)); ?>
                            </td>
                            <td data-label="Belum Selesai" style="text-align: center; color: #ea580c; font-weight: 700;">
                                <?php echo number_format((int) ($e['pending_matches'] ?? 0)); ?>
                            </td>
                            <td data-label="Jadwal Terakhir" style="text-align: center; font-weight: 600; color: #334155;">
                                <?php echo htmlspecialchars(formatEventDate($e['last_match_date'] ?? null)); ?>
                            </td>
                            <td data-label="Status Event" style="text-align: center;">
                                <?php echo getEventStatusBadge($e['pending_matches'] ?? 0); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-info">
            <div class="info-text">
                Menampilkan <?php echo $total_records > 0 ? min($offset + 1, $total_records) : 0; ?> sampai <?php echo min($offset + $limit, $total_records); ?> dari <?php echo number_format($total_records); ?> kategori event
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="pagination-controls">
                    <!-- Previous -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&sport=<?php echo urlencode($filter_sport); ?>">Sebelumnya</a>
                    <?php else: ?>
                        <span class="disabled">Sebelumnya</span>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo '<a href="?page=1&search='.urlencode($search).'&status='.urlencode($filter_status).'&sport='.urlencode($filter_sport).'">1</a>';
                        if ($start_page > 2) echo '<span>...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&sport=<?php echo urlencode($filter_sport); ?>" 
                           class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php 
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span>...</span>';
                        echo '<a href="?page='.$total_pages.'&search='.urlencode($search).'&status='.urlencode($filter_status).'&sport='.urlencode($filter_sport).'">'.$total_pages.'</a>';
                    }
                    ?>

                    <!-- Next -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($filter_status); ?>&sport=<?php echo urlencode($filter_sport); ?>">Berikutnya</a>
                    <?php else: ?>
                        <span class="disabled">Berikutnya</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
// Auto-submit filter on change
document.getElementById('status')?.addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('sport')?.addEventListener('change', function() {
    this.form.submit();
});

// Auto-submit search on enter
document.getElementById('search')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        this.form.submit();
    }
});

// Sidebar Dropdown Toggle
function toggleDropdown(element, dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;
    
    // Toggle dropdown visibility
    dropdown.classList.toggle('show');
    
    // Rotate icon
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

<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>


