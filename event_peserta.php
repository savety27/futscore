<?php
$hideNavbars = true;
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/event_peserta.css?v=<?php echo time(); ?>">
<?php

$pageTitle = 'Peserta Event';
$conn = $db->getConnection();

function tableExistsForEventPage($conn, $table_name) {
    $escaped_table = $conn->real_escape_string($table_name);
    $result = $conn->query("SHOW TABLES LIKE '{$escaped_table}'");
    return $result && $result->num_rows > 0;
}

function formatEventPesertaDate($datetime) {
    if (empty($datetime)) {
        return '-';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '-';
    }

    return date('d M Y H:i', $timestamp);
}

function inferCategoryFromLegacyEventName($legacy_event_name) {
    $legacy_event_name = trim((string) $legacy_event_name);
    if ($legacy_event_name === '') {
        return '-';
    }

    if (preg_match('/\bU[\s\-]?(\d{1,2})\b/i', $legacy_event_name, $matches)) {
        return 'U' . (int) $matches[1];
    }

    return $legacy_event_name;
}

function inferGroupNameFromLegacyEventName($legacy_event_name) {
    $legacy_event_name = trim((string) $legacy_event_name);
    if ($legacy_event_name === '') {
        return '';
    }

    $group_name = preg_replace('/\s+U[\s\-]?\d{1,2}\b.*$/i', '', $legacy_event_name);
    $group_name = trim((string) $group_name);

    return $group_name !== '' ? $group_name : $legacy_event_name;
}

function fetchTaxonomyRowsByGroup($conn, $group_slug) {
    $sql = "SELECT event_group_slug, event_group_name, category_name, legacy_event_name, sort_order
            FROM event_taxonomy
            WHERE event_group_slug = ?
            ORDER BY sort_order ASC, category_name ASC, legacy_event_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $group_slug);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchTaxonomyRowByLegacyEvent($conn, $legacy_event) {
    $sql = "SELECT event_group_slug, event_group_name, category_name, legacy_event_name, sort_order
            FROM event_taxonomy
            WHERE legacy_event_name = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $legacy_event);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function fetchParticipantTeamsByLegacyEvent($conn, $legacy_event_name, $player_id_filter = 0) {
    $sql = "SELECT participant.id, participant.name, participant.logo
            FROM (
                SELECT t.id, t.name, t.logo
                FROM teams t
                INNER JOIN team_events te ON te.team_id = t.id
                WHERE te.event_name = ?

                UNION

                SELECT t.id, t.name, t.logo
                FROM teams t
                INNER JOIN challenges c ON c.sport_type = ?
                    AND (c.challenger_id = t.id OR c.opponent_id = t.id)
            ) participant";

    if ($player_id_filter > 0) {
        $sql .= " WHERE EXISTS (
                      SELECT 1
                      FROM players p
                      WHERE p.team_id = participant.id
                        AND p.id = ?
                  )";
    }

    $sql .= ' ORDER BY participant.name ASC';
    $stmt = $conn->prepare($sql);

    if ($player_id_filter > 0) {
        $stmt->bind_param('ssi', $legacy_event_name, $legacy_event_name, $player_id_filter);
    } else {
        $stmt->bind_param('ss', $legacy_event_name, $legacy_event_name);
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchCategoryMatchSummary($conn, $legacy_event_name) {
    $sql = "SELECT
                COUNT(*) AS total_matches,
                SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'completed' THEN 1 ELSE 0 END) AS completed_matches,
                SUM(CASE WHEN LOWER(COALESCE(status, '')) <> 'completed' THEN 1 ELSE 0 END) AS pending_matches,
                MAX(challenge_date) AS last_match_date
            FROM challenges
            WHERE sport_type = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $legacy_event_name);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$group_slug = isset($_GET['group']) ? trim($_GET['group']) : '';
$legacy_event = isset($_GET['legacy_event']) ? trim($_GET['legacy_event']) : '';
$player_id_raw = isset($_GET['player_id']) ? trim($_GET['player_id']) : '';
$player_id_filter = ctype_digit($player_id_raw) ? (int) $player_id_raw : 0;
$player_filter_error = '';

if ($player_id_raw !== '' && $player_id_filter <= 0) {
    $player_filter_error = 'Player ID harus berupa angka.';
}

if ($group_slug === '' && $legacy_event === '') {
    header('Location: event.php');
    exit;
}

$has_event_taxonomy = tableExistsForEventPage($conn, 'event_taxonomy');
$categories = [];
$event_group_name = '';
$event_group_slug = '';
$event_not_found = false;
$is_single_legacy_event = false;

if ($has_event_taxonomy && $group_slug !== '') {
    $taxonomy_rows = fetchTaxonomyRowsByGroup($conn, $group_slug);
    if (!empty($taxonomy_rows)) {
        $event_group_name = trim((string) ($taxonomy_rows[0]['event_group_name'] ?? ''));
        $event_group_slug = trim((string) ($taxonomy_rows[0]['event_group_slug'] ?? ''));

        foreach ($taxonomy_rows as $row) {
            $legacy_name = trim((string) ($row['legacy_event_name'] ?? ''));
            if ($legacy_name === '') {
                continue;
            }

            $categories[] = [
                'category_name' => trim((string) ($row['category_name'] ?? '')),
                'legacy_event_name' => $legacy_name,
                'sort_order' => (int) ($row['sort_order'] ?? 0)
            ];
        }
    }
}

if ($has_event_taxonomy && empty($categories) && $legacy_event !== '') {
    $legacy_row = fetchTaxonomyRowByLegacyEvent($conn, $legacy_event);

    if (!empty($legacy_row)) {
        $mapped_group_slug = trim((string) ($legacy_row['event_group_slug'] ?? ''));

        if ($mapped_group_slug !== '') {
            $taxonomy_rows = fetchTaxonomyRowsByGroup($conn, $mapped_group_slug);
            if (!empty($taxonomy_rows)) {
                $event_group_name = trim((string) ($taxonomy_rows[0]['event_group_name'] ?? ''));
                $event_group_slug = trim((string) ($taxonomy_rows[0]['event_group_slug'] ?? ''));

                foreach ($taxonomy_rows as $row) {
                    $legacy_name = trim((string) ($row['legacy_event_name'] ?? ''));
                    if ($legacy_name === '') {
                        continue;
                    }

                    $categories[] = [
                        'category_name' => trim((string) ($row['category_name'] ?? '')),
                        'legacy_event_name' => $legacy_name,
                        'sort_order' => (int) ($row['sort_order'] ?? 0)
                    ];
                }
            }
        }
    }
}

if (empty($categories) && $legacy_event !== '') {
    $event_group_name = inferGroupNameFromLegacyEventName($legacy_event);
    $categories[] = [
        'category_name' => inferCategoryFromLegacyEventName($legacy_event),
        'legacy_event_name' => $legacy_event,
        'sort_order' => 0
    ];
    $is_single_legacy_event = true;
}

if (empty($categories) && $group_slug !== '') {
    $event_not_found = true;
    $event_group_name = $group_slug;
}

$category_total = count($categories);
$participant_total = 0;
$event_total_matches = 0;
$event_completed_matches = 0;
$event_pending_matches = 0;
$event_last_match_date = null;
$unique_team_ids = [];

if (!$event_not_found) {
    foreach ($categories as &$category) {
        $legacy_name = $category['legacy_event_name'];
        $teams = fetchParticipantTeamsByLegacyEvent($conn, $legacy_name, $player_id_filter);
        $summary = fetchCategoryMatchSummary($conn, $legacy_name);

        $category['category_name'] = $category['category_name'] !== ''
            ? $category['category_name']
            : inferCategoryFromLegacyEventName($legacy_name);
        $category['teams'] = $teams;
        $category['team_count'] = count($teams);
        $category['summary'] = $summary;

        foreach ($teams as $team) {
            $team_id = (int) ($team['id'] ?? 0);
            if ($team_id > 0) {
                $unique_team_ids[$team_id] = true;
            }
        }

        $event_total_matches += (int) ($summary['total_matches'] ?? 0);
        $event_completed_matches += (int) ($summary['completed_matches'] ?? 0);
        $event_pending_matches += (int) ($summary['pending_matches'] ?? 0);

        $category_last_match = $summary['last_match_date'] ?? null;
        if (!empty($category_last_match)) {
            if ($event_last_match_date === null || strtotime($category_last_match) > strtotime($event_last_match_date)) {
                $event_last_match_date = $category_last_match;
            }
        }
    }
    unset($category);
}

$participant_total = count($unique_team_ids);

$player_filter_info = null;
if ($player_id_filter > 0) {
    $player_info_query = "SELECT p.id, p.name, t.name AS team_name
                          FROM players p
                          LEFT JOIN teams t ON t.id = p.team_id
                          WHERE p.id = ?
                          LIMIT 1";
    $stmt_player = $conn->prepare($player_info_query);
    $stmt_player->bind_param('i', $player_id_filter);
    $stmt_player->execute();
    $player_filter_info = $stmt_player->get_result()->fetch_assoc();
}

$base_detail_params = [];
if ($group_slug !== '') {
    $base_detail_params['group'] = $group_slug;
} elseif ($event_group_slug !== '') {
    $base_detail_params['group'] = $event_group_slug;
} elseif ($legacy_event !== '') {
    $base_detail_params['legacy_event'] = $legacy_event;
} elseif (!empty($categories[0]['legacy_event_name'])) {
    $base_detail_params['legacy_event'] = $categories[0]['legacy_event_name'];
}

$reset_query = http_build_query($base_detail_params);
$reset_link = 'event_peserta.php' . ($reset_query !== '' ? '?' . $reset_query : '');
?>

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
                <a href="<?php echo ($_SESSION['admin_role'] === 'pelatih' ? SITE_URL . '/pelatih/dashboard.php' : SITE_URL . '/admin/dashboard.php'); ?>">
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

    <main class="main-content-dashboard">
        <header class="dashboard-header dashboard-header-event-peserta">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">ALVETRIX</div>
                    <h1><?php echo htmlspecialchars($event_group_name !== '' ? $event_group_name : 'Detail Peserta Event'); ?></h1>
                    <p class="header-subtitle">Lihat peserta tim per kategori event secara dinamis.</p>
                </div>
                <div class="header-actions">
                    <a href="event.php" class="btn-back-event"><i class="fas fa-arrow-left"></i> Kembali ke Event</a>
                </div>
            </div>
            <?php if (!$event_not_found): ?>
                <div class="event-meta-row">
                    <span class="event-meta-pill"><i class="fas fa-layer-group"></i> <?php echo number_format($category_total); ?> Kategori</span>
                    <span class="event-meta-pill"><i class="fas fa-users"></i> <?php echo number_format($participant_total); ?> Peserta</span>
                    <span class="event-meta-pill"><i class="fas fa-futbol"></i> <?php echo number_format($event_total_matches); ?> Match</span>
                    <span class="event-meta-pill"><i class="fas fa-check-circle"></i> <?php echo number_format($event_completed_matches); ?> Completed</span>
                    <span class="event-meta-pill"><i class="fas fa-hourglass-half"></i> <?php echo number_format($event_pending_matches); ?> Pending</span>
                    <span class="event-meta-pill"><i class="fas fa-calendar-day"></i> Update: <?php echo htmlspecialchars(formatEventPesertaDate($event_last_match_date)); ?></span>
                </div>
            <?php endif; ?>
        </header>

        <div class="dashboard-body">
            <?php if ($event_not_found): ?>
                <section class="section-container section-elevated">
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h4>Event tidak ditemukan</h4>
                        <p>Event yang dipilih tidak memiliki kategori atau belum dipetakan.</p>
                        <a href="event.php" class="btn-back-event btn-back-event-inline"><i class="fas fa-arrow-left"></i> Kembali ke daftar event</a>
                    </div>
                </section>
            <?php else: ?>
                <section class="participants-top-card section-elevated">
                    <div class="participants-top-copy">
                        <h2>Peserta (<?php echo number_format($participant_total); ?>)</h2>
                        <p>*klik icon <i class="fas fa-circle-info"></i> untuk melihat pertandingan tim pada kategori event ini</p>
                        <?php if ($is_single_legacy_event): ?>
                            <div class="notice-chip"><i class="fas fa-circle-exclamation"></i> Event ini belum dipetakan ke event group taxonomy.</div>
                        <?php endif; ?>
                        <?php if ($player_filter_error !== ''): ?>
                            <div class="notice-chip notice-chip-warning"><i class="fas fa-triangle-exclamation"></i> <?php echo htmlspecialchars($player_filter_error); ?></div>
                        <?php elseif ($player_id_filter > 0 && !$player_filter_info): ?>
                            <div class="notice-chip notice-chip-warning"><i class="fas fa-user-slash"></i> Player ID <?php echo (int) $player_id_filter; ?> tidak ditemukan.</div>
                        <?php elseif ($player_id_filter > 0 && $player_filter_info): ?>
                            <div class="notice-chip notice-chip-success">
                                <i class="fas fa-filter"></i>
                                Filter aktif: Player ID <?php echo (int) $player_filter_info['id']; ?> - <?php echo htmlspecialchars($player_filter_info['name'] ?? ''); ?> (<?php echo htmlspecialchars($player_filter_info['team_name'] ?? '-'); ?>)
                            </div>
                        <?php endif; ?>
                    </div>

                    <form method="GET" action="" class="participants-search-form">
                        <?php if (!empty($base_detail_params['group'])): ?>
                            <input type="hidden" name="group" value="<?php echo htmlspecialchars($base_detail_params['group']); ?>">
                        <?php endif; ?>
                        <?php if (empty($base_detail_params['group']) && !empty($base_detail_params['legacy_event'])): ?>
                            <input type="hidden" name="legacy_event" value="<?php echo htmlspecialchars($base_detail_params['legacy_event']); ?>">
                        <?php endif; ?>

                        <label for="player_id" class="search-label">Cari Peserta Berdasarkan Player ID</label>
                        <div class="search-row">
                            <input type="text" name="player_id" id="player_id" value="<?php echo htmlspecialchars($player_id_raw); ?>" placeholder="Cari by Player ID">
                            <button type="submit"><i class="fas fa-search"></i> Cari</button>
                            <a href="<?php echo htmlspecialchars($reset_link); ?>" class="btn-reset-search"><i class="fas fa-rotate-left"></i> Reset</a>
                        </div>
                    </form>
                </section>

                <section class="category-grid-wrapper">
                    <div class="category-grid">
                        <?php foreach ($categories as $category): ?>
                            <article class="category-card section-elevated">
                                <div class="category-card-head">
                                    <h3><?php echo htmlspecialchars($category['category_name'] ?? '-'); ?></h3>
                                    <span class="category-badge"><?php echo number_format((int) ($category['team_count'] ?? 0)); ?> tim</span>
                                </div>
                                <div class="category-subtitle"><?php echo htmlspecialchars($category['legacy_event_name'] ?? '-'); ?></div>

                                <div class="category-table-wrap">
                                    <table class="category-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 56px; text-align: center;">No</th>
                                                <th>Team</th>
                                                <th style="width: 46px;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($category['teams'])): ?>
                                                <tr>
                                                    <td colspan="3" class="empty-team-row">
                                                        <?php if ($player_id_filter > 0): ?>
                                                            Belum ada team yang cocok dengan Player ID ini
                                                        <?php else: ?>
                                                            Belum ada team yang terdaftar
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($category['teams'] as $index => $team): ?>
                                                    <?php
                                                    $team_match_link = 'all.php?status=result&event=' . urlencode((string) ($category['legacy_event_name'] ?? '')) . '&team=' . urlencode((string) ($team['id'] ?? ''));
                                                    ?>
                                                    <tr>
                                                        <td style="text-align: center;"><?php echo $index + 1; ?></td>
                                                        <td>
                                                            <div class="team-row">
                                                                <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($team['logo'] ?? 'default-team.png'); ?>"
                                                                     alt="<?php echo htmlspecialchars($team['name'] ?? ''); ?>"
                                                                     class="team-logo-mini"
                                                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                                                <span class="team-name"><?php echo htmlspecialchars($team['name'] ?? ''); ?></span>
                                                            </div>
                                                        </td>
                                                        <td class="team-action-cell">
                                                            <a href="<?php echo htmlspecialchars($team_match_link); ?>" class="team-detail-link" title="Lihat pertandingan tim pada kategori ini">
                                                                <i class="fas fa-circle-info"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <footer class="dashboard-footer">
                <p>&copy; 2026 ALVETRIX. Semua hak dilindungi.</p>
                <p>
                    <a href="<?php echo SITE_URL; ?>">Beranda</a> |
                    <a href="contact.php">Kontak</a> |
                    <a href="bpjs.php">BPJSTK</a>
                </p>
            </footer>
        </div>
    </main>
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
