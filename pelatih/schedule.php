<?php
$page_title = 'Jadwal Pertandingan';
$current_page = 'schedule';
require_once 'config/database.php';
require_once 'includes/header.php';

// Get pelatih's team_id directly from session (like in dashboard.php)
$my_team_id = $_SESSION['team_id'] ?? 0;

// Fallback: If not in session, try to get from database using pelatih_id
if ($my_team_id == 0) {
    $pelatih_id = $_SESSION['pelatih_id'] ?? 0;
    try {
        $stmtTeam = $conn->prepare("SELECT team_id FROM team_staff WHERE id = ?");
        $stmtTeam->execute([$pelatih_id]);
        $staff = $stmtTeam->fetch(PDO::FETCH_ASSOC);
        $my_team_id = $staff['team_id'] ?? 0;
        
        // Save to session for future use
        if ($my_team_id > 0) {
            $_SESSION['team_id'] = $my_team_id;
        }
    } catch (PDOException $e) {
        // Ignore error
    }
}

// Handle search dan filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sport_filter = isset($_GET['sport']) ? trim($_GET['sport']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Ambil semua tipe olahraga yang tersedia untuk filter
$sport_types = [];
try {
    $sport_query = "SELECT DISTINCT sport_type FROM challenges WHERE sport_type IS NOT NULL AND sport_type != '' ORDER BY sport_type";
    $sport_stmt = $conn->prepare($sport_query);
    $sport_stmt->execute();
    $sport_types = $sport_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Jika error, sport_types akan tetap array kosong
}

// Base query untuk challenges dengan join ke teams untuk nama team
$base_query = "SELECT 
    c.*,
    t1.name as challenger_name,
    t1.logo as challenger_logo,
    t2.name as opponent_name,
    t2.logo as opponent_logo,
    v.name as venue_name
    FROM challenges c
    LEFT JOIN teams t1 ON c.challenger_id = t1.id
    LEFT JOIN teams t2 ON c.opponent_id = t2.id
    LEFT JOIN venues v ON c.venue_id = v.id
    WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM challenges c WHERE 1=1";

// Tambahkan kondisi untuk search
if (!empty($search)) {
    $search_term = "%{$search}%";
    $base_query .= " AND (c.challenge_code LIKE ? 
                OR t1.name LIKE ? 
                OR t2.name LIKE ? 
                OR c.sport_type LIKE ?
                OR c.status LIKE ?
                OR c.match_status LIKE ?)";
    $count_query .= " AND (c.challenge_code LIKE ? 
                OR EXISTS (SELECT 1 FROM teams t WHERE t.id = c.challenger_id AND t.name LIKE ?)
                OR EXISTS (SELECT 1 FROM teams t WHERE t.id = c.opponent_id AND t.name LIKE ?)
                OR c.sport_type LIKE ?
                OR c.status LIKE ?
                OR c.match_status LIKE ?)";
}

// Tambahkan kondisi untuk filter olahraga
if (!empty($sport_filter)) {
    $base_query .= " AND c.sport_type = ?";
    $count_query .= " AND c.sport_type = ?";
}

$base_query .= " ORDER BY c.challenge_date DESC";

$total_data = 0;
$total_pages = 1;
$challenges = [];

try {
    // Hitung total data dengan filter
    if (!empty($search) && !empty($sport_filter)) {
        // Keduanya: search dan filter olahraga
        $stmt = $conn->prepare($count_query);
        $stmt->execute([
            $search_term, $search_term, $search_term, 
            $search_term, $search_term, $search_term,
            $sport_filter
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
    } elseif (!empty($search)) {
        // Hanya search
        $stmt = $conn->prepare($count_query);
        $stmt->execute([
            $search_term, $search_term, $search_term, 
            $search_term, $search_term, $search_term
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
    } elseif (!empty($sport_filter)) {
        // Hanya filter olahraga
        $stmt = $conn->prepare($count_query);
        $stmt->execute([$sport_filter]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
    } else {
        // Tidak ada filter
        $stmt = $conn->prepare($count_query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_data = $result['total'];
    }
    
    $total_pages = ceil($total_data / $limit);
    
    // Query data dengan pagination
    $query = $base_query . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    
    if (!empty($search) && !empty($sport_filter)) {
        // Keduanya: search dan filter olahraga
        $stmt->bindValue(1, $search_term);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $search_term);
        $stmt->bindValue(4, $search_term);
        $stmt->bindValue(5, $search_term);
        $stmt->bindValue(6, $search_term);
        $stmt->bindValue(7, $sport_filter);
        $stmt->bindValue(8, $limit, PDO::PARAM_INT);
        $stmt->bindValue(9, $offset, PDO::PARAM_INT);
    } elseif (!empty($search)) {
        // Hanya search
        $stmt->bindValue(1, $search_term);
        $stmt->bindValue(2, $search_term);
        $stmt->bindValue(3, $search_term);
        $stmt->bindValue(4, $search_term);
        $stmt->bindValue(5, $search_term);
        $stmt->bindValue(6, $search_term);
        $stmt->bindValue(7, $limit, PDO::PARAM_INT);
        $stmt->bindValue(8, $offset, PDO::PARAM_INT);
    } elseif (!empty($sport_filter)) {
        // Hanya filter olahraga
        $stmt->bindValue(1, $sport_filter);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    } else {
        // Tidak ada filter
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format tanggal dan waktu
    foreach ($challenges as &$challenge) {
        // Format challenge_date
        if (!empty($challenge['challenge_date'])) {
            $date = new DateTime($challenge['challenge_date']);
            $challenge['formatted_date'] = $date->format('d M Y');
            $challenge['formatted_time'] = $date->format('H:i');
        } else {
            $challenge['formatted_date'] = '-';
            $challenge['formatted_time'] = '-';
        }
        
        // Format match status badge color
        $challenge['match_status_badge'] = 'gray';
        if (!empty($challenge['match_status'])) {
            switch(strtolower($challenge['match_status'])) {
                case 'completed':
                    $challenge['match_status_badge'] = 'success';
                    break;
                case 'scheduled':
                    $challenge['match_status_badge'] = 'primary';
                    break;
                case 'cancelled':
                case 'abandoned':
                    $challenge['match_status_badge'] = 'danger';
                    break;
                case 'postponed':
                    $challenge['match_status_badge'] = 'warning';
                    break;
                default:
                    $challenge['match_status_badge'] = 'gray';
            }
        }
        
        // Format status badge color
        $challenge['status_badge'] = 'gray';
        if (!empty($challenge['status'])) {
            switch(strtolower($challenge['status'])) {
                case 'accepted':
                    $challenge['status_badge'] = 'success';
                    break;
                case 'open':
                    $challenge['status_badge'] = 'primary';
                    break;
                case 'rejected':
                    $challenge['status_badge'] = 'danger';
                    break;
                case 'expired':
                    $challenge['status_badge'] = 'warning';
                    break;
                default:
                    $challenge['status_badge'] = 'gray';
            }
        }
        
        // Set default logos jika kosong
        $challenge['challenger_logo'] = $challenge['challenger_logo'] ?: 'default-team.png';
        $challenge['opponent_logo'] = $challenge['opponent_logo'] ?: 'default-team.png';
    }
    unset($challenge);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<style>
.main {
    background: linear-gradient(180deg, #eaf6ff 0%, #dff1ff 45%, #f4fbff 100%) !important;
}

.filter-container {
    margin-bottom: 25px;
}

.schedule-filter-card {
    padding: 16px;
    border: 1px solid #dbe5f3;
    border-radius: 14px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    box-shadow: 0 8px 20px rgba(10, 36, 99, 0.06);
}

.schedule-filter-form {
    display: grid;
    grid-template-columns: minmax(240px, 1fr) minmax(210px, 0.72fr) auto;
    gap: 12px;
    align-items: center;
}

.schedule-search-group {
    position: relative;
}

.schedule-search-group i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #7b8797;
    font-size: 13px;
}

.schedule-search-input,
.schedule-custom-select-trigger {
    width: 100%;
    height: 42px;
    border: 1px solid #d3dcea;
    border-radius: 10px;
    background: #ffffff;
    color: #1f2937;
    font-size: 14px;
    transition: all 0.2s ease;
}

.schedule-search-input {
    padding: 0 12px 0 36px;
}

.schedule-select-wrap {
    position: relative;
}

.schedule-custom-select {
    position: relative;
}

.schedule-custom-select-trigger {
    padding: 0 34px 0 12px;
    text-align: left;
    cursor: pointer;
    display: flex;
    align-items: center;
}

.schedule-custom-select-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    min-width: 0;
}

.schedule-custom-select-label i {
    color: #7b8797;
    font-size: 13px;
    flex: 0 0 auto;
}

.schedule-custom-select-trigger span.schedule-custom-select-text {
    display: block;
    width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.schedule-custom-select-trigger .select-icon-right {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 11px;
    color: #7b8797;
    transition: transform 0.2s ease;
}

.schedule-custom-select.open .schedule-custom-select-trigger .select-icon-right {
    transform: translateY(-50%) rotate(180deg);
}

.schedule-custom-select-menu {
    display: none;
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 6px);
    background: #ffffff;
    border: 1px solid #d3dcea;
    border-radius: 10px;
    box-shadow: 0 12px 24px rgba(10, 36, 99, 0.16);
    padding: 6px;
    max-height: 260px;
    overflow-y: auto;
    z-index: 20;
}

.schedule-custom-select.open .schedule-custom-select-menu {
    display: block;
}

.schedule-custom-option {
    width: 100%;
    text-align: left;
    border: none;
    background: transparent;
    color: #1f2937;
    font-size: 13px;
    font-weight: 600;
    border-radius: 8px;
    padding: 9px 10px;
    cursor: pointer;
}

.schedule-custom-option:hover {
    background: #f2f6fc;
}

.schedule-custom-option.active {
    background: #e8f0ff;
    color: #0f3d8b;
}

.schedule-search-input:focus,
.schedule-custom-select-trigger:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(10, 36, 99, 0.12);
}

.schedule-filter-actions {
    display: flex;
    gap: 8px;
}

.schedule-filter-actions .btn-filter,
.schedule-filter-actions .clear-filter-btn {
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

.schedule-filter-actions .btn-filter {
    background: linear-gradient(135deg, var(--primary), #1a4f9e);
    color: #ffffff;
}

.schedule-filter-actions .btn-filter:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(10, 36, 99, 0.22);
}

.schedule-filter-actions .clear-filter-btn {
    background: #ffffff;
    border-color: #d3dcea;
    color: #3b4a5f;
}

.schedule-filter-actions .clear-filter-btn:hover {
    background: #f2f6fc;
}

@media (max-width: 768px) {
    .schedule-filter-form {
        grid-template-columns: 1fr;
    }

    .schedule-filter-actions .btn-filter,
    .schedule-filter-actions .clear-filter-btn {
        width: 100%;
        justify-content: center;
    }

    .schedule-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .data-table {
        min-width: 1120px;
    }

    .data-table th,
    .data-table td {
        padding: 10px 9px;
        font-size: 12px;
        white-space: nowrap;
        vertical-align: middle;
    }

    .data-table td:nth-child(3) > div {
        min-width: 220px;
    }

    .event-badge {
        font-size: 11px !important;
        white-space: nowrap;
        line-height: 1.2;
    }
}

.data-table tbody tr {
    transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    position: relative;
    will-change: transform;
}

.event-badge {
    display: inline-block;
    max-width: none;
    white-space: nowrap;
    overflow: visible;
    text-overflow: clip;
    line-height: 1.25;
    text-align: center;
    word-break: normal;
    vertical-align: middle;
}

.data-table tbody tr:hover,
.data-table tbody tr:focus-within {
    background: #eef5ff;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(10, 36, 99, 0.18), 0 0 0 1px rgba(76, 138, 255, 0.35);
    z-index: 2;
}

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

<div class="card">
    <div class="section-header">
        <h2 class="section-title">Jadwal Pertandingan</h2>
        <!-- Read Only: No Add Button -->
    </div>

    <div class="filter-container">
        <div class="schedule-filter-card">
            <form action="" method="GET" class="schedule-filter-form">
                <div class="schedule-search-group">
                    <i class="fas fa-search"></i>
                    <input
                        type="text"
                        name="search"
                        placeholder="Cari kode, team, event, atau status pertandingan..."
                        value="<?php echo htmlspecialchars($search); ?>"
                        class="schedule-search-input"
                    >
                </div>

                <div class="schedule-select-wrap">
                    <div class="schedule-custom-select" id="scheduleSportSelect">
                        <input type="hidden" name="sport" id="scheduleSportValue" value="<?php echo htmlspecialchars($sport_filter); ?>">
                        <button type="button" class="schedule-custom-select-trigger" id="scheduleSportTrigger" aria-expanded="false">
                            <span class="schedule-custom-select-label">
                                <i class="fas fa-trophy"></i>
                                <span id="scheduleSportLabel" class="schedule-custom-select-text"><?php echo $sport_filter !== '' ? htmlspecialchars($sport_filter) : 'Semua Event'; ?></span>
                            </span>
                            <i class="fas fa-chevron-down select-icon-right"></i>
                        </button>
                        <div class="schedule-custom-select-menu" id="scheduleSportMenu">
                            <button type="button" class="schedule-custom-option <?php echo $sport_filter === '' ? 'active' : ''; ?>" data-value="">Semua Event</button>
                            <?php foreach ($sport_types as $sport): ?>
                                <button type="button" class="schedule-custom-option <?php echo $sport_filter === $sport ? 'active' : ''; ?>" data-value="<?php echo htmlspecialchars($sport); ?>">
                                    <?php echo htmlspecialchars($sport); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="schedule-filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> Terapkan
                    </button>
                    <?php if (!empty($search) || !empty($sport_filter)): ?>
                    <a href="schedule.php" class="clear-filter-btn">
                        <i class="fas fa-times"></i> Reset
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($challenges)): ?>
        <p style="text-align: center; color: var(--gray); padding: 20px;">Pertandingan tidak ditemukan.</p>
    <?php else: ?>
        <div class="schedule-table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kode Pertandingan</th>
                        <th>Tanggal</th>
                        <th>Team</th>
                        <th>Event</th>
                        <th>Lokasi</th>
                        <th>Skor</th>
                        <th>Status</th>
                        <th>Status Pertandingan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($challenges as $challenge): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($challenge['challenge_code'] ?? ''); ?></strong>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: var(--dark);"><?php echo $challenge['formatted_date']; ?></div>
                            <div style="font-size: 13px; color: var(--gray);"><?php echo $challenge['formatted_time']; ?></div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="text-align: center;">
                                    <img src="../images/teams/<?php echo htmlspecialchars($challenge['challenger_logo']); ?>" 
                                         alt="<?php echo htmlspecialchars($challenge['challenger_name'] ?? ''); ?>" 
                                         class="team-logo"
                                         onerror="this.onerror=null; this.src='../images/teams/default-team.png'">
                                    <div style="font-size: 12px; margin-top: 5px; font-weight: 600;">
                                        <?php echo htmlspecialchars($challenge['challenger_name'] ?? ''); ?>
                                    </div>
                                </div>
                                <div style="color: var(--gray);">vs</div>
                                <div style="text-align: center;">
                                    <img src="../images/teams/<?php echo htmlspecialchars($challenge['opponent_logo']); ?>" 
                                         alt="<?php echo htmlspecialchars($challenge['opponent_name'] ?? ''); ?>" 
                                         class="team-logo"
                                         onerror="this.onerror=null; this.src='../images/teams/default-team.png'">
                                    <div style="font-size: 12px; margin-top: 5px; font-weight: 600;">
                                        <?php echo htmlspecialchars($challenge['opponent_name'] ?? ''); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="event-badge" title="<?php echo htmlspecialchars($challenge['sport_type'] ?? ''); ?>" style="padding: 6px 12px; background: #f0f7ff; color: var(--primary); border-radius: 20px; font-size: 12px; font-weight: 600;">
                                <?php echo htmlspecialchars($challenge['sport_type'] ?? ''); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($challenge['venue_name'])): ?>
                                <span><?php echo htmlspecialchars($challenge['venue_name'] ?? ''); ?></span>
                            <?php else: ?>
                                <span style="color: var(--gray); font-style: italic;">Akan ditentukan</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($challenge['challenger_score']) || !empty($challenge['opponent_score'])): ?>
                                <div style="font-weight: 700; font-size: 18px; text-align: center; color: var(--primary);">
                                    <?php echo htmlspecialchars($challenge['challenger_score'] ?? 0); ?> - <?php echo htmlspecialchars($challenge['opponent_score'] ?? 0); ?>
                                </div>
                                <?php if (!empty($challenge['winner_team_id'])): ?>
                                    <div style="font-size: 11px; color: var(--success); text-align: center; font-weight: 600;">
                                        <?php 
                                        $winner_name = ($challenge['winner_team_id'] == $challenge['challenger_id']) 
                                            ? $challenge['challenger_name'] 
                                            : $challenge['opponent_name'];
                                        echo htmlspecialchars($winner_name ?? '');
                                        ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: var(--gray); font-style: italic;">Belum dimainkan</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($challenge['status'])): ?>
                                <?php 
                                $badge_class = '';
                                switch($challenge['status_badge']) {
                                    case 'success': $badge_class = 'background: #e8f5e9; color: var(--success);'; break;
                                    case 'primary': $badge_class = 'background: #f0f7ff; color: var(--primary);'; break;
                                    case 'danger': $badge_class = 'background: #ffebee; color: var(--danger);'; break;
                                    case 'warning': $badge_class = 'background: #fff8e1; color: var(--warning);'; break;
                                    default: $badge_class = 'background: #f5f5f5; color: var(--gray);';
                                }
                                ?>
                                <span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; <?php echo $badge_class; ?>">
                                <?php 
                                    $s_status = strtolower($challenge['status']);
                                    $s_status_map = ['accepted' => 'Diterima', 'open' => 'Terbuka', 'rejected' => 'Ditolak', 'expired' => 'Kedaluwarsa'];
                                    echo htmlspecialchars($s_status_map[$s_status ?? ''] ?? ucfirst($challenge['status'] ?? '')); 
                                ?>
                            </span>
                        <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($challenge['match_status'])): ?>
                                <?php 
                                $match_badge_class = '';
                                switch($challenge['match_status_badge']) {
                                    case 'success': $match_badge_class = 'background: #e8f5e9; color: var(--success);'; break;
                                    case 'primary': $match_badge_class = 'background: #f0f7ff; color: var(--primary);'; break;
                                    case 'danger': $match_badge_class = 'background: #ffebee; color: var(--danger);'; break;
                                    case 'warning': $match_badge_class = 'background: #fff8e1; color: var(--warning);'; break;
                                    default: $match_badge_class = 'background: #f5f5f5; color: var(--gray);';
                                }
                                ?>
                                <span style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; <?php echo $match_badge_class; ?>">
                                    <?php 
                                        $m_status = strtolower($challenge['match_status']);
                                        $m_status_map = ['completed' => 'Selesai', 'scheduled' => 'Terjadwal', 'cancelled' => 'Dibatalkan', 'abandoned' => 'Dihentikan', 'postponed' => 'Ditunda'];
                                        echo htmlspecialchars($m_status_map[$m_status ?? ''] ?? ucfirst($challenge['match_status'] ?? '')); 
                                    ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--gray); font-style: italic;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($challenge['status'] === 'accepted' && ($my_team_id == $challenge['challenger_id'] || $my_team_id == $challenge['opponent_id'])): ?>
                            <a href="match_lineup.php?id=<?php echo $challenge['id']; ?>" class="btn-sm btn-primary" style="text-decoration: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; display: inline-block;">
                                <i class="fas fa-users-cog"></i> Atur Lineup
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&sport=<?php echo urlencode($sport_filter); ?>" class="page-link">&laquo; Seb</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&sport=<?php echo urlencode($sport_filter); ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&sport=<?php echo urlencode($sport_filter); ?>" class="page-link">Sel &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectRoot = document.getElementById('scheduleSportSelect');
    if (!selectRoot) return;

    const trigger = document.getElementById('scheduleSportTrigger');
    const menu = document.getElementById('scheduleSportMenu');
    const hiddenInput = document.getElementById('scheduleSportValue');
    const label = document.getElementById('scheduleSportLabel');
    const options = menu.querySelectorAll('.schedule-custom-option');

    function closeMenu() {
        selectRoot.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    }

    function openMenu() {
        selectRoot.classList.add('open');
        trigger.setAttribute('aria-expanded', 'true');
    }

    trigger.addEventListener('click', function() {
        if (selectRoot.classList.contains('open')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    options.forEach(function(opt) {
        opt.addEventListener('click', function() {
            const value = opt.getAttribute('data-value') || '';
            hiddenInput.value = value;
            label.textContent = opt.textContent.trim();
            options.forEach(function(o) { o.classList.remove('active'); });
            opt.classList.add('active');
            closeMenu();
        });
    });

    document.addEventListener('click', function(e) {
        if (!selectRoot.contains(e.target)) {
            closeMenu();
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
