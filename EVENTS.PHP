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
function hasTableMySQLi($conn, $table) {
    $table = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $result && $result->num_rows > 0;
}

$hasIsActive = hasColumnMySQLi($conn, 'events', 'is_active');
$events = [];
$event = null;
$eventParticipantGroups = [];
$eventParticipantTotal = 0;
$eventDataReady = false;
$eventStandings = [];
$eventPlayerCards = [];
$hasEventValueTables = false;

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

if ($eventId > 0 && !empty($event)) {
    $hasChallengeEventId = hasColumnMySQLi($conn, 'challenges', 'event_id');
    if ($hasChallengeEventId) {
        $categorySql = "SELECT sport_type AS category_name, COUNT(*) AS total_matches
                        FROM challenges
                        WHERE event_id = ?
                          AND sport_type IS NOT NULL
                          AND sport_type <> ''
                        GROUP BY sport_type
                        ORDER BY sport_type ASC";
        $categoryStmt = $conn->prepare($categorySql);
        $categoryStmt->bind_param("i", $eventId);
        $categoryStmt->execute();
        $categoryResult = $categoryStmt->get_result();

        $participantTotalRows = 0;
        while ($categoryRow = $categoryResult->fetch_assoc()) {
            $categoryName = trim((string) ($categoryRow['category_name'] ?? ''));
            if ($categoryName === '') {
                continue;
            }

            $teamSql = "SELECT t.id, t.name, t.logo
                        FROM teams t
                        INNER JOIN (
                            SELECT challenger_id AS team_id
                            FROM challenges
                            WHERE event_id = ? AND sport_type = ?
                            UNION
                            SELECT opponent_id AS team_id
                            FROM challenges
                            WHERE event_id = ? AND sport_type = ?
                        ) participant ON participant.team_id = t.id
                        ORDER BY t.name ASC";
            $teamStmt = $conn->prepare($teamSql);
            $teamStmt->bind_param("isis", $eventId, $categoryName, $eventId, $categoryName);
            $teamStmt->execute();
            $teamResult = $teamStmt->get_result();

            $teams = [];
            while ($teamRow = $teamResult->fetch_assoc()) {
                $teams[] = $teamRow;
            }
            $participantTotalRows += count($teams);

            $eventParticipantGroups[] = [
                'category_name' => $categoryName,
                'total_matches' => (int) ($categoryRow['total_matches'] ?? 0),
                'teams' => $teams
            ];
        }

        $eventParticipantTotal = $participantTotalRows;
        $eventDataReady = true;
    }

    $hasEventValueTables = hasTableMySQLi($conn, 'event_team_values') && hasTableMySQLi($conn, 'player_event_cards');
    if ($hasEventValueTables) {
        $standingSql = "SELECT etv.*, t.name AS team_name
                        FROM event_team_values etv
                        INNER JOIN teams t ON t.id = etv.team_id
                        WHERE etv.event_id = ?
                        ORDER BY etv.kls ASC, etv.points DESC, etv.sg DESC, etv.gm DESC, t.name ASC";
        $standingStmt = $conn->prepare($standingSql);
        $standingStmt->bind_param("i", $eventId);
        $standingStmt->execute();
        $standingResult = $standingStmt->get_result();
        while ($standingRow = $standingResult->fetch_assoc()) {
            $eventStandings[] = $standingRow;
        }

        $cardSql = "SELECT pec.*, p.name AS player_name, t.name AS team_name
                    FROM player_event_cards pec
                    INNER JOIN players p ON p.id = pec.player_id
                    INNER JOIN teams t ON t.id = pec.team_id
                    WHERE pec.event_id = ?
                    ORDER BY
                        CASE WHEN pec.suspension_until IS NOT NULL AND pec.suspension_until >= CURDATE() THEN 0 ELSE 1 END ASC,
                        pec.updated_at DESC";
        $cardStmt = $conn->prepare($cardSql);
        $cardStmt->bind_param("i", $eventId);
        $cardStmt->execute();
        $cardResult = $cardStmt->get_result();
        while ($cardRow = $cardResult->fetch_assoc()) {
            $eventPlayerCards[] = $cardRow;
        }
    }
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
.event-participant-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 18px;
    margin-top: 16px;
}
.event-participant-card {
    border: 1px solid #dbe6f3;
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
}
.event-participant-head {
    background: #0f172a;
    color: #fff;
    padding: 10px 12px;
    font-weight: 700;
    font-size: 14px;
}
.event-participant-table {
    width: 100%;
    border-collapse: collapse;
}
.event-participant-table th,
.event-participant-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #edf2f7;
    font-size: 13px;
}
.event-participant-table th {
    text-align: left;
    background: #f8fafc;
}
.event-team-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.event-team-logo {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid #dbe6f3;
}
.event-team-info-link {
    color: #2563eb;
    text-decoration: none;
}
.event-standing-wrap { overflow-x: auto; margin-top: 12px; border: 1px solid #dbe6f3; border-radius: 12px; }
.event-standing-table { width: 100%; border-collapse: collapse; min-width: 980px; }
.event-standing-table th, .event-standing-table td { padding: 8px 10px; border-bottom: 1px solid #edf2f7; font-size: 13px; text-align: center; }
.event-standing-table thead th { background: #0f172a; color: #fff; }
.event-standing-table th:nth-child(2), .event-standing-table td:nth-child(2) { text-align: left; }
.event-card-note { margin: 8px 0 0 0; color: #475569; font-size: 12px; }
.suspend-pill {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}
.suspend-pill.active { background: rgba(239, 68, 68, 0.14); color: #991b1b; }
.suspend-pill.done { background: rgba(16, 185, 129, 0.14); color: #065f46; }
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
            <a href="all.php"><i class="fas fa-trophy"></i> <span>CHALLENGE</span></a>
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
                        <a href="event.php" class="btn-primary"><i class="fas fa-sitemap"></i> Event Kategori</a>
                        <a href="EVENTS.PHP" class="btn-secondary"><i class="fas fa-arrow-left"></i> Semua Event</a>
                        <a href="team.php" class="btn-primary"><i class="fas fa-users"></i> Lihat Tim</a>
                    <?php else: ?>
                        <a href="event.php" class="btn-primary"><i class="fas fa-sitemap"></i> Event Kategori</a>
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

                        <div class="event-desc" id="eventParticipants">
                            <h3 style="margin:0 0 10px 0;">Peserta (<?php echo (int) $eventParticipantTotal; ?>)</h3>
                            <p style="margin:0 0 12px 0; color:#475569;">*klik icon info untuk melihat statistik tim di kategori ini</p>

                            <?php if (!$eventDataReady): ?>
                                <p style="margin:0; color:#64748b;">Data peserta belum tersedia (kolom relasi event belum aktif).</p>
                            <?php elseif (empty($eventParticipantGroups)): ?>
                                <p style="margin:0; color:#64748b;">Belum ada challenge yang terhubung ke event ini.</p>
                            <?php else: ?>
                                <div class="event-participant-grid">
                                    <?php foreach ($eventParticipantGroups as $group): ?>
                                        <article class="event-participant-card">
                                            <div class="event-participant-head">
                                                <?php echo htmlspecialchars($group['category_name'] ?? '-'); ?>
                                            </div>
                                            <table class="event-participant-table">
                                                <thead>
                                                    <tr>
                                                        <th style="width:44px;">No</th>
                                                        <th>Team</th>
                                                        <th style="width:44px;">Info</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($group['teams'])): ?>
                                                        <tr><td colspan="3" style="color:#64748b;">Belum ada tim</td></tr>
                                                    <?php else: ?>
                                                        <?php foreach ($group['teams'] as $idx => $team): ?>
                                                            <?php
                                                            $teamId = (int) ($team['id'] ?? 0);
                                                            $teamLink = 'all.php?status=result&event=' . urlencode((string) ($group['category_name'] ?? '')) . '&team=' . urlencode((string) $teamId);
                                                            ?>
                                                            <tr>
                                                                <td><?php echo $idx + 1; ?></td>
                                                                <td>
                                                                    <div class="event-team-row">
                                                                        <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo htmlspecialchars($team['logo'] ?? 'default-team.png'); ?>"
                                                                             alt="<?php echo htmlspecialchars($team['name'] ?? ''); ?>"
                                                                             class="event-team-logo"
                                                                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>/images/teams/default-team.png'">
                                                                        <span><?php echo htmlspecialchars($team['name'] ?? '-'); ?></span>
                                                                    </div>
                                                                </td>
                                                                <td>
                                                                    <a class="event-team-info-link" href="<?php echo htmlspecialchars($teamLink); ?>" title="Lihat pertandingan tim">
                                                                        <i class="fas fa-circle-info"></i>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="event-desc" id="eventStandings">
                            <h3 style="margin:0 0 8px 0;">Klasemen & Disiplin</h3>
                            <p style="margin:0 0 8px 0; color:#475569;">Poin: M=3, MP=2, KP=1, Seri=0, Kalah=0.</p>
                            <p class="event-card-note">Catatan kartu: hanya dihitung jumlahnya (tanpa poin).</p>
                            <p class="event-card-note">Aturan: 2x kartu kuning, pemain tidak boleh tanding 1 minggu lalu boleh lagi.</p>

                            <?php if (!$hasEventValueTables): ?>
                                <p style="margin:10px 0 0 0; color:#64748b;">Tabel nilai event belum tersedia. Jalankan migration untuk mulai input data dari admin.</p>
                            <?php elseif (empty($eventStandings)): ?>
                                <p style="margin:10px 0 0 0; color:#64748b;">Belum ada data klasemen untuk event ini.</p>
                            <?php else: ?>
                                <div class="event-standing-wrap">
                                    <table class="event-standing-table">
                                        <thead>
                                            <tr>
                                                <th>Team</th>
                                                <th>MN</th>
                                                <th>M</th>
                                                <th>MP</th>
                                                <th>S</th>
                                                <th>KP</th>
                                                <th>K</th>
                                                <th>GM</th>
                                                <th>GK</th>
                                                <th>SG</th>
                                                <th>P</th>
                                                <th>KLS</th>
                                                <th>Merah</th>
                                                <th>Kuning</th>
                                                <th>Hijau</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($eventStandings as $idx => $standing): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($standing['team_name'] ?? '-'); ?></td>
                                                    <td><?php echo (int) ($standing['mn'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($standing['m'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($standing['mp'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($standing['s'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($standing['kp'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($standing['k'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($standing['gm'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($standing['gk'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($standing['sg'] ?? 0); ?></td>
                                                    <td><strong><?php echo (int) ($standing['points'] ?? 0); ?></strong></td>
                                                    <td><?php echo (int) (($standing['kls'] ?? 0) > 0 ? $standing['kls'] : ($idx + 1)); ?></td>
                                                    <td><?php echo (int) ($standing['red_cards'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($standing['yellow_cards'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($standing['green_cards'] ?? 0); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <h4 style="margin:18px 0 8px 0;">Status Suspend Pemain</h4>
                            <?php if (!$hasEventValueTables): ?>
                                <p style="margin:0; color:#64748b;">Data kartu pemain belum tersedia.</p>
                            <?php elseif (empty($eventPlayerCards)): ?>
                                <p style="margin:0; color:#64748b;">Belum ada data kartu pemain.</p>
                            <?php else: ?>
                                <div class="event-standing-wrap">
                                    <table class="event-standing-table" style="min-width:760px;">
                                        <thead>
                                            <tr>
                                                <th>Pemain</th>
                                                <th>Tim</th>
                                                <th>Kuning</th>
                                                <th>Merah</th>
                                                <th>Hijau</th>
                                                <th>Suspend Sampai</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($eventPlayerCards as $card): ?>
                                                <?php
                                                $suspendUntil = !empty($card['suspension_until']) ? $card['suspension_until'] : null;
                                                $isSuspended = $suspendUntil && $suspendUntil >= date('Y-m-d');
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($card['player_name'] ?? '-'); ?></td>
                                                    <td><?php echo htmlspecialchars($card['team_name'] ?? '-'); ?></td>
                                                    <td><?php echo (int) ($card['yellow_cards'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($card['red_cards'] ?? 0); ?></td>
                                                    <td><?php echo (int) ($card['green_cards'] ?? 0); ?></td>
                                                    <td><?php echo $suspendUntil ? htmlspecialchars(date('d M Y', strtotime($suspendUntil))) : '-'; ?></td>
                                                    <td>
                                                        <span class="suspend-pill <?php echo $isSuspended ? 'active' : 'done'; ?>">
                                                            <?php echo $isSuspended ? 'Tidak Boleh Main' : 'Boleh Main'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
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
