<?php
$hideNavbars = true;
require_once 'includes/header.php';

$eventId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$conn = $db->getConnection();

function hasColumnMySQLi($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
    return $result && $result->num_rows > 0;
}

$hasIsActive = hasColumnMySQLi($conn, 'events', 'is_active');
$events = [];
$event = null;

if ($eventId > 0) {
    $sql = "SELECT * FROM events WHERE id = ?";
    if ($hasIsActive) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();

    if (!$event) {
        header('Location: ' . SITE_URL . '/EVENTS.PHP');
        exit;
    }
    $pageTitle = $event['name'];
} else {
    $sql = "SELECT * FROM events";
    if ($hasIsActive) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY start_date DESC, created_at DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
    $pageTitle = "Events";
}
?>

<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/index_redesign.css?v=<?php echo time(); ?>">
<style>
.event-poster-profile {
    width: 160px;
    height: 160px;
    border-radius: 22px;
    object-fit: contain;
    background: #f8fafc;
    border: 4px solid #fff;
    box-shadow: 0 10px 25px rgba(15, 39, 68, 0.14);
    padding: 6px;
}
.event-card-image {
    width: 100%;
    height: 170px;
    object-fit: contain;
    background: #f8fafc;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    padding: 6px;
}
.event-chip {
    display: inline-block;
    margin-top: 8px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}
.event-chip.open { background: rgba(16, 185, 129, 0.12); color: #047857; }
.event-chip.closed { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }
.event-desc {
    margin-top: 18px;
    padding: 18px;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    line-height: 1.65;
}
</style>

<div class="dashboard-wrapper">
    <header class="mobile-dashboard-header">
        <div class="mobile-logo">
            <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Buka/Tutup Sidebar" aria-controls="sidebar" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>
    </header>

    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <aside class="sidebar" id="sidebar" aria-hidden="true">
        <div class="sidebar-logo">
            <a href="<?php echo SITE_URL; ?>">
                <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="Logo">
            </a>
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo SITE_URL; ?>"><i class="fas fa-home"></i> <span>BERANDA</span></a>
            <a href="EVENTS.PHP" class="active"><i class="fas fa-calendar-alt"></i> <span>EVENT</span></a>
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

    <div class="main-content-dashboard">
        <header class="dashboard-header dashboard-header-home dashboard-header-team">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">ALVETRIX</div>
                    <h1>EVENT</h1>
                    <p class="header-subtitle">
                        <?php echo $eventId > 0
                            ? 'Detail lengkap event yang didaftarkan melalui panel admin.'
                            : 'Jelajahi event terbaru dari data yang telah diinput admin.'; ?>
                    </p>
                </div>
                <div class="header-actions">
                    <?php if ($eventId > 0): ?>
                        <a href="EVENTS.PHP" class="btn-secondary"><i class="fas fa-arrow-left"></i> Semua Event</a>
                        <a href="team.php" class="btn-primary"><i class="fas fa-users"></i> Lihat Tim</a>
                    <?php else: ?>
                        <a href="team.php" class="btn-secondary"><i class="fas fa-users"></i> Lihat Tim</a>
                        <a href="player.php" class="btn-primary"><i class="fas fa-running"></i> Lihat Player</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="dashboard-body">
            <?php if ($eventId > 0): ?>
                <div class="container section-container section-elevated team-profile-section">
                    <div class="section-header">
                        <h2 class="section-title">PROFIL EVENT</h2>
                        <div class="section-tabs">
                            <span class="team-status-pill"><i class="fas fa-calendar-day"></i> <?php echo htmlspecialchars(formatDate($event['start_date'] ?? '')); ?></span>
                            <span class="team-status-pill"><i class="fas fa-calendar-check"></i> <?php echo htmlspecialchars(formatDate($event['end_date'] ?? '')); ?></span>
                            <span class="team-status-pill">
                                <i class="fas fa-toggle-on"></i>
                                <?php echo strtolower((string) ($event['registration_status'] ?? 'closed')) === 'open' ? 'Pendaftaran Open' : 'Pendaftaran Closed'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="team-profile-card">
                        <div class="team-profile-identity">
                            <div class="team-logo-shell" style="border-radius:26px;">
                                <?php if (!empty($event['image'])): ?>
                                    <img src="<?php echo SITE_URL; ?>/images/events/<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['name']); ?>" class="event-poster-profile" onerror="this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                                <?php else: ?>
                                    <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="<?php echo htmlspecialchars($event['name']); ?>" class="event-poster-profile">
                                <?php endif; ?>
                            </div>
                            <div class="team-profile-text">
                                <h2 class="team-name-display"><?php echo htmlspecialchars($event['name']); ?></h2>
                                <p class="team-subtitle"><?php echo !empty($event['category']) ? htmlspecialchars($event['category']) : 'Kategori belum diisi'; ?></p>
                                <p class="team-tagline">Lokasi: <?php echo !empty($event['location']) ? htmlspecialchars($event['location']) : '-'; ?></p>
                            </div>
                        </div>

                        <div class="team-meta-grid">
                            <div class="team-meta-item">
                                <div class="team-meta-label">Tanggal Mulai</div>
                                <div class="team-meta-value"><?php echo htmlspecialchars(formatDate($event['start_date'] ?? '')); ?></div>
                            </div>
                            <div class="team-meta-item">
                                <div class="team-meta-label">Tanggal Selesai</div>
                                <div class="team-meta-value"><?php echo htmlspecialchars(formatDate($event['end_date'] ?? '')); ?></div>
                            </div>
                            <div class="team-meta-item">
                                <div class="team-meta-label">Kontak</div>
                                <div class="team-meta-value"><?php echo !empty($event['contact']) ? htmlspecialchars($event['contact']) : '-'; ?></div>
                            </div>
                            <div class="team-meta-item">
                                <div class="team-meta-label">Status Pendaftaran</div>
                                <div class="team-meta-value">
                                    <span class="event-chip <?php echo strtolower((string) ($event['registration_status'] ?? 'closed')) === 'open' ? 'open' : 'closed'; ?>">
                                        <?php echo strtoupper((string) ($event['registration_status'] ?? 'closed')); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="event-desc">
                            <?php
                            $desc = trim((string) ($event['description'] ?? ''));
                            echo $desc !== '' ? nl2br(htmlspecialchars($desc)) : 'Belum ada deskripsi event.';
                            ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="container section-container section-elevated">
                    <div class="section-header">
                        <h2 class="section-title">EVENT</h2>
                        <div class="section-tabs">
                            <span class="team-count-pill"><i class="fas fa-calendar-alt"></i> <?php echo count($events); ?> Event</span>
                        </div>
                    </div>

                    <div class="team-filter-card">
                        <label class="team-filter-label" for="eventSelector">Pilih Event</label>
                        <div class="team-selector">
                            <select id="eventSelector" onchange="if(this.value) window.location.href='EVENTS.PHP?id=' + this.value">
                                <option value="">Pilih event dari dropdown atau daftar di bawah</option>
                                <?php foreach ($events as $ev): ?>
                                    <option value="<?php echo (int) $ev['id']; ?>"><?php echo htmlspecialchars($ev['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="container section-container">
                    <?php if (empty($events)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h4>Tidak ada event</h4>
                            <p>Belum ada event yang tersedia.</p>
                        </div>
                    <?php else: ?>
                        <div class="teams-grid team-directory-grid">
                            <?php foreach ($events as $ev): ?>
                                <a href="EVENTS.PHP?id=<?php echo (int) $ev['id']; ?>" class="team-card team-directory-card" data-team-id="<?php echo (int) $ev['id']; ?>">
                                    <?php if (!empty($ev['image'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/images/events/<?php echo htmlspecialchars($ev['image']); ?>" alt="<?php echo htmlspecialchars($ev['name']); ?>" class="event-card-image" onerror="this.src='<?php echo SITE_URL; ?>/images/alvetrix.png'">
                                    <?php else: ?>
                                        <img src="<?php echo SITE_URL; ?>/images/alvetrix.png" alt="<?php echo htmlspecialchars($ev['name']); ?>" class="event-card-image">
                                    <?php endif; ?>
                                    <h3 class="team-name"><?php echo htmlspecialchars($ev['name']); ?></h3>
                                    <p class="team-label"><?php echo !empty($ev['category']) ? htmlspecialchars($ev['category']) : '-'; ?></p>
                                    <span class="event-chip <?php echo strtolower((string) ($ev['registration_status'] ?? 'closed')) === 'open' ? 'open' : 'closed'; ?>">
                                        <?php echo strtolower((string) ($ev['registration_status'] ?? 'closed')) === 'open' ? 'Open' : 'Closed'; ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
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
    </div>
</div>

<script>
function toggleDropdown(element, dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    if (!dropdown) return;
    dropdown.classList.toggle('show');
    element.classList.toggle('open');
}

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
    sidebarOverlay.addEventListener('click', () => setSidebarOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setSidebarOpen(false);
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 992) setSidebarOpen(false);
    });
}
</script>
<script>
const SITE_URL = '<?php echo SITE_URL; ?>';
</script>
<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
