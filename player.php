<?php
$hideNavbars = true;
$pageTitle = "Player Directory";
require_once 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo getAssetVersion('/css/redesign_core.css'); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/player_redesign.css?v=<?php echo getAssetVersion('/css/player_redesign.css'); ?>">
<style>
    .player-table-new {
        min-width: 1120px;
    }
    .player-table-new th {
        padding: 12px 12px;
        font-size: 12px;
        letter-spacing: 0.6px;
        line-height: 1.2;
        white-space: nowrap;
        text-align: center !important;
    }
    .player-table-new td {
        padding: 11px 12px;
        font-size: 14px;
        line-height: 1.3;
    }
    .player-table-new td.cell-name .player-link {
        font-size: 14px;
        line-height: 1.35;
        display: inline-block;
        text-align: center;
    }
    .player-table-new th.col-name-head,
    .player-table-new td.cell-name {
        min-width: 100px;
        text-align: center !important;
    }
    .player-table-new th:first-child,
    .player-table-new td:first-child {
        padding-left: 12px;
    }
    .player-table-new th:last-child,
    .player-table-new td:last-child {
        padding-right: 12px;
    }
    .player-table-new .col-nik {
        min-width: 140px;
        white-space: nowrap;
    }
    .player-table-new .col-nisn {
        min-width: 110px;
        white-space: nowrap;
    }
    .col-team-stack {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: flex-start;
        gap: 8px;
        text-align: left;
    }
    .team-name-stack {
        font-weight: 600;
        line-height: 1.3;
        font-size: 12px;
    }
    .player-table-new th.col-team-head,
    .player-table-new td.cell-team {
        min-width: 160px;
    }
    .player-table-new th.col-team-head {
        padding-left: 12px;
        text-align: left;
    }
    .player-table-new td.cell-team {
        text-align: left;
    }
    .mobile-profile-cell {
        display: none;
    }
    .mobile-profile-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 10px 12px;
        border-radius: 10px;
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
        box-shadow: 0 8px 16px rgba(37, 99, 235, 0.22);
    }
    .player-table-new th.col-jersey,
    .player-table-new td.col-jersey {
        width: 108px;
        min-width: 108px;
        max-width: 108px;
        white-space: nowrap;
    }
    .player-table-new th.col-jersey {
        text-align: left;
        padding-left: 8px;
    }
    .player-photo-sm,
    .placeholder-img {
        width: 42px !important;
        height: 42px !important;
        border-radius: 8px !important;
    }
    .team-logo-small {
        width: 24px !important;
        height: 24px !important;
        border-radius: 6px !important;
    }
    .match-count-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
    }
    .match-count-badge.zero {
        background: #f1f5f9;
        color: #475569;
        border-color: #cbd5e1;
    }
    .event-count-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #fcd34d;
    }
    .event-count-badge.zero {
        background: #f1f5f9;
        color: #475569;
        border-color: #cbd5e1;
    }
    /* Popover for event count badge */
    .event-count-badge-wrap {
        position: relative;
        display: inline-block;
    }
    .event-count-badge {
        cursor: pointer;
        user-select: none;
    }
    .event-popover {
        display: none;
        position: absolute;
        z-index: 9999;
        bottom: calc(100% + 6px);
        left: 50%;
        transform: translateX(-50%);
        background: #1e293b;
        color: #f1f5f9;
        font-size: 12px;
        font-weight: 500;
        line-height: 1.6;
        padding: 8px 12px;
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.22);
        pointer-events: none;
        min-width: 160px;
        max-width: 280px;
        white-space: pre-line;
        word-break: break-word;
        text-align: left;
    }
    .event-popover::after {
        content: '';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 6px solid transparent;
        border-top-color: #1e293b;
    }
    .event-count-badge-wrap:hover .event-popover,
    .event-count-badge-wrap.pop-open .event-popover {
        display: block;
    }
    .history-btn {
        width: 32px;
        height: 32px;
        border: 0;
        border-radius: 8px;
        background: #dbeafe;
        color: #1d4ed8;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: 0.2s ease;
        font-size: 14px;
    }
    .history-btn:hover {
        background: #bfdbfe;
        transform: translateY(-1px);
    }
    .history-modal {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .history-modal.open {
        display: flex;
    }
    .history-modal-content {
        width: min(1120px, 100%);
        max-height: calc(100vh - 40px);
        overflow: hidden;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 20px 50px rgba(2, 6, 23, 0.25);
        display: flex;
        flex-direction: column;
    }
    .history-modal-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
    }
    .history-modal-header h3 {
        margin: 0;
        font-size: 18px;
        color: #0f172a;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .history-modal-meta {
        margin-top: 4px;
        color: #64748b;
        font-size: 13px;
    }
    .history-close-btn {
        width: 34px;
        height: 34px;
        border: 0;
        border-radius: 10px;
        background: #f1f5f9;
        color: #334155;
        cursor: pointer;
    }
    .history-modal-body {
        padding: 16px 20px 20px;
        overflow: auto;
    }
    .history-loading,
    .history-empty {
        padding: 30px 12px;
        text-align: center;
        color: #64748b;
    }
    .history-loading i,
    .history-empty i {
        margin-right: 8px;
    }
    .history-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1120px;
    }
    .history-table th,
    .history-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #e2e8f0;
        text-align: left;
        font-size: 13px;
        color: #0f172a;
        vertical-align: top;
    }
    .history-table td.col-no,
    .history-table td.col-event,
    .history-table td.col-kategori,
    .history-table td.col-match,
    .history-table td.col-tanggal,
    .history-table td.col-babak,
    .history-table td.col-posisi,
    .history-table td.col-peran,
    .history-table td.col-hasil {
        white-space: nowrap;
    }
    .history-table td.col-pertandingan {
        min-width: 260px;
        white-space: normal;
    }
    .history-table td.col-match {
        min-width: 120px;
    }
    .history-table td.col-event,
    .history-table td.col-kategori {
        min-width: 140px;
    }
    .history-table td.col-tanggal {
        min-width: 130px;
    }
    .history-table td.col-hasil {
        min-width: 84px;
    }
    .history-table th {
        background: #f8fafc;
        font-size: 12px;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        white-space: nowrap;
    }
    .h-event-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 999px;
        font-size: 11px;
        border: 1px solid #93c5fd;
        background: #dbeafe;
        color: #1d4ed8;
    }
    .h-starter-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }
    .h-starter-yes {
        background: #fef9c3;
        color: #854d0e;
    }
    .h-starter-sub {
        background: #e2e8f0;
        color: #334155;
    }
    .h-half-pill {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #334155;
        font-size: 11px;
        font-weight: 700;
    }
    .h-result-win {
        color: #166534;
        font-weight: 700;
    }
    .h-result-lose {
        color: #991b1b;
        font-weight: 700;
    }
    .h-result-draw {
        color: #1e3a8a;
        font-weight: 700;
    }
    .h-result-na {
        color: #64748b;
        font-weight: 700;
    }
    .history-table-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .player-detail-actions {
        flex-direction: column;
        flex-wrap: nowrap;
        align-items: stretch;
        gap: 10px;
        width: max-content;
    }
    .player-share-section {
        position: relative;
        width: 100%;
    }
    .player-share-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 20px;
        padding-right: 42px;
        width: 100%;
        border-radius: 12px;
        border: 1px solid #dbeafe;
        background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
        color: #fff;
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 0;
        position: relative;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .player-share-toggle:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 16px rgba(37, 99, 235, 0.2);
    }
    .player-share-toggle-icon {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        transition: transform 0.2s ease;
    }
    .player-share-section.open .player-share-toggle-icon {
        transform: translateY(-50%) rotate(180deg);
    }
    .player-share-menu {
        display: none;
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        left: auto;
        z-index: 20;
        width: min(430px, 90vw);
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 14px;
        padding: 10px;
        box-shadow: 0 16px 34px rgba(15, 23, 42, 0.16);
    }
    .player-share-section.open .player-share-menu {
        display: block;
    }
    .player-share-buttons {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }
    .player-share-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 36px;
        padding: 8px 11px;
        border-radius: 10px;
        border: 1px solid #dbeafe;
        background: #eff6ff;
        color: #1e3a8a;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease, background 0.2s ease;
    }
    .player-share-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 14px rgba(37, 99, 235, 0.16);
    }
    .player-share-btn.whatsapp {
        background: #25d366;
        border-color: #25d366;
        color: #fff;
    }
    .player-share-btn.facebook {
        background: #1877f2;
        border-color: #1877f2;
        color: #fff;
    }
    .player-share-btn.telegram {
        background: #0088cc;
        border-color: #0088cc;
        color: #fff;
    }
    .player-share-btn.twitter {
        background: #0f172a;
        border-color: #0f172a;
        color: #fff;
    }
    .player-share-btn.copy {
        background: #1d4ed8;
        border-color: #1d4ed8;
        color: #fff;
        font-family: inherit;
    }
    .player-share-btn.copy.copied {
        background: #166534;
        border-color: #166534;
    }
    .player-share-btn.native {
        display: none;
        border-color: #1d4ed8;
    }
    .player-share-feedback {
        margin-top: 8px;
        min-height: 14px;
        font-size: 11px;
        font-weight: 700;
        color: #047857;
    }
    .player-share-feedback.error {
        color: #b91c1c;
    }
    .player-share-x-icon {
        width: 12px;
        height: 12px;
        fill: currentColor;
        flex: 0 0 auto;
    }
    @media (max-width: 768px) {
        .player-table-container,
        .player-table-responsive {
            overflow: visible !important;
        }
        .player-table-new {
            display: block !important;
            min-width: 0 !important;
        }
        .player-table-new thead {
            display: none !important;
        }
        .player-table-new tbody {
            display: flex !important;
            flex-direction: column !important;
            gap: 14px !important;
        }
        .player-table-new tr {
            display: flex !important;
            flex-direction: column !important;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 14px !important;
            overflow: hidden !important;
        }
        .player-table-new td {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            padding: 10px 12px;
            font-size: 13px;
            border-bottom: 1px solid #eef2f7 !important;
            color: #334155 !important;
            opacity: 1 !important;
            visibility: visible !important;
            text-align: left !important;
        }
        .player-table-new td:last-child {
            border-bottom: none !important;
        }
        .player-table-new td::before {
            content: attr(data-label) !important;
            flex: 0 0 96px;
            font-size: 10px;
            font-weight: 800 !important;
            color: #64748b !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }
        .player-table-new td.cell-no {
            display: none !important;
        }
        .player-table-new td.col-jersey {
            width: auto;
            min-width: 0;
            max-width: none;
            white-space: nowrap;
        }
        .player-table-new td.cell-team {
            align-items: center;
        }
        .col-team-stack {
            flex-direction: row;
            align-items: center;
            text-align: left;
            gap: 8px;
        }
        .team-name-stack {
            font-size: 12px;
            line-height: 1.2;
        }
        .player-photo-sm,
        .placeholder-img {
            width: 38px !important;
            height: 38px !important;
            border-radius: 10px !important;
        }
        .team-logo-small {
            width: 22px !important;
            height: 22px !important;
        }
        .match-count-badge,
        .event-count-badge {
            font-size: 10px;
            padding: 2px 7px;
            gap: 4px;
        }
        .history-btn {
            width: 30px;
            height: 30px;
            font-size: 13px;
        }
        .mobile-profile-cell {
            display: block !important;
            order: 99;
            padding: 10px 12px !important;
            border-bottom: none !important;
            background: #f8fbff;
        }
        .player-table-new td.mobile-profile-cell::before {
            content: none !important;
            display: none !important;
        }
        .mobile-profile-btn {
            min-height: 38px;
            font-size: 12px;
            letter-spacing: 0.2px;
        }
        .history-modal {
            padding: 10px;
        }
        .history-modal-content {
            max-height: calc(100vh - 20px);
            border-radius: 12px;
        }
        .history-modal-header {
            padding: 12px 14px;
        }
        .history-modal-header h3 {
            font-size: 16px;
        }
        .history-modal-meta {
            font-size: 12px;
            line-height: 1.35;
        }
        .history-modal-body {
            padding: 10px 12px 14px;
        }
        .history-table th,
        .history-table td {
            padding: 8px 9px;
            font-size: 12px;
        }
        .player-detail-actions {
            width: 100%;
        }
        .player-share-section {
            width: 100%;
            display: flex;
            flex-direction: column;
        }
        .player-share-toggle {
            width: 100%;
            justify-content: center;
            min-height: 48px;
            padding: 16px 24px;
            font-size: 14px;
        }
        .player-share-menu {
            position: static;
            width: 100%;
            max-width: 100%;
            margin-top: 8px;
            box-shadow: none;
        }
        .player-share-buttons {
            width: 100%;
        }
        .player-share-btn {
            width: 100%;
        }
        .player-share-feedback {
            text-align: center;
        }
    }
    @media (max-width: 480px) {
        .player-table-new td {
            padding: 9px 10px;
            font-size: 12px;
        }
        .player-table-new td::before {
            flex: 0 0 88px;
        }
        .team-name-stack {
            font-size: 11px;
        }
        .match-count-badge,
        .event-count-badge {
            font-size: 9px;
        }
        .player-share-buttons {
            grid-template-columns: 1fr;
        }
    }
</style>
<?php

// Logic for Search and Pagination
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 40;
$offset = ($page - 1) * $limit;
$player_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Database connection
$conn = $db->getConnection();
$has_challenge_event_id = false;
$check_event_id_col = $conn->query("SHOW COLUMNS FROM challenges LIKE 'event_id'");
if ($check_event_id_col && $check_event_id_col->num_rows > 0) {
    $has_challenge_event_id = true;
}

// Query for Total Records (for pagination)
$count_query = "SELECT COUNT(*) as total FROM players p WHERE p.status = 'active'";
if (!empty($search)) {
    $count_query .= " AND (p.name LIKE ? OR p.nisn LIKE ?)";
}

$stmt_count = $conn->prepare($count_query);
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt_count->bind_param("ss", $search_param, $search_param);
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
    $query .= " AND (p.name LIKE ? OR p.nisn LIKE ?)";
}
$query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!empty($search)) {
    $stmt->bind_param("ssii", $search_param, $search_param, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$players = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Preload jumlah match yang diikuti oleh player yang tampil di halaman ini
$player_match_counts = [];
$player_event_stats = [];
if (!empty($players)) {
    $player_ids = array_map('intval', array_column($players, 'id'));
    if (!empty($player_ids)) {
        $placeholders = implode(',', array_fill(0, count($player_ids), '?'));
        $sql_counts = "
            SELECT l.player_id, COUNT(l.id) as total_match
            FROM lineups l
            INNER JOIN challenges c ON l.match_id = c.id
            WHERE l.player_id IN ($placeholders)
            GROUP BY l.player_id
        ";
        $stmt_counts = $conn->prepare($sql_counts);
        if ($stmt_counts) {
            $types = str_repeat('i', count($player_ids));
            $stmt_counts->bind_param($types, ...$player_ids);
            $stmt_counts->execute();
            $result_counts = $stmt_counts->get_result();
            while ($row = $result_counts->fetch_assoc()) {
                $player_match_counts[(int) $row['player_id']] = (int) $row['total_match'];
            }
            $stmt_counts->close();
        }

        if ($has_challenge_event_id) {
            $sql_event_counts = "
                SELECT
                    l.player_id,
                    CASE
                        WHEN e.name IS NULL OR TRIM(e.name) = '' THEN 'Tanpa Event'
                        ELSE TRIM(e.name)
                    END AS event_name,
                    COUNT(l.id) as total_match_in_event
                FROM lineups l
                INNER JOIN challenges c ON l.match_id = c.id
                LEFT JOIN events e ON c.event_id = e.id
                WHERE l.player_id IN ($placeholders)
                GROUP BY l.player_id,
                         CASE
                             WHEN e.name IS NULL OR TRIM(e.name) = '' THEN 'Tanpa Event'
                             ELSE TRIM(e.name)
                         END
            ";
            $stmt_event_counts = $conn->prepare($sql_event_counts);
            if ($stmt_event_counts) {
                $types = str_repeat('i', count($player_ids));
                $stmt_event_counts->bind_param($types, ...$player_ids);
                $stmt_event_counts->execute();
                $result_event_counts = $stmt_event_counts->get_result();
                while ($row = $result_event_counts->fetch_assoc()) {
                    $pid = (int) $row['player_id'];
                    $event_name = trim((string) ($row['event_name'] ?? ''));
                    if ($event_name === '') $event_name = 'Tanpa Event';
                    if (!isset($player_event_stats[$pid])) {
                        $player_event_stats[$pid] = [];
                    }
                    $player_event_stats[$pid][$event_name] = (int) $row['total_match_in_event'];
                }
                $stmt_event_counts->close();
            }
        } else {
            $sql_event_counts = "
                SELECT
                    l.player_id,
                    'Tanpa Event' AS event_name,
                    COUNT(l.id) as total_match_in_event
                FROM lineups l
                INNER JOIN challenges c ON l.match_id = c.id
                WHERE l.player_id IN ($placeholders)
                GROUP BY l.player_id
            ";
            $stmt_event_counts = $conn->prepare($sql_event_counts);
            if ($stmt_event_counts) {
                $types = str_repeat('i', count($player_ids));
                $stmt_event_counts->bind_param($types, ...$player_ids);
                $stmt_event_counts->execute();
                $result_event_counts = $stmt_event_counts->get_result();
                while ($row = $result_event_counts->fetch_assoc()) {
                    $pid = (int) $row['player_id'];
                    if (!isset($player_event_stats[$pid])) {
                        $player_event_stats[$pid] = [];
                    }
                    $player_event_stats[$pid]['Tanpa Event'] = (int) ($row['total_match_in_event'] ?? 0);
                }
                $stmt_event_counts->close();
            }
        }
    }
}

// Optional Player Detail (by ID)
$player_detail = null;
$player_detail_category_label = '-';
$player_share_url = '';
$player_share_text = '';
$player_share_whatsapp_url = '#';
$player_share_facebook_url = '#';
$player_share_telegram_url = '#';
$player_share_x_url = '#';
if ($player_id > 0) {
    $detail_query = "SELECT p.*, t.name as team_name, t.logo as team_logo 
                     FROM players p 
                     LEFT JOIN teams t ON p.team_id = t.id 
                     WHERE p.id = ? LIMIT 1";
    $detail_stmt = $conn->prepare($detail_query);
    $detail_stmt->bind_param("i", $player_id);
    $detail_stmt->execute();
    $player_detail = $detail_stmt->get_result()->fetch_assoc();
    
    // Single category label: prioritize player sport_type (same behavior as admin player page)
    if ($player_detail) {
        if (!empty($player_detail['sport_type'])) {
            $player_detail_category_label = $player_detail['sport_type'];
        } elseif (!empty($player_detail['team_id'])) {
            $team_info = getTeamById($player_detail['team_id']);
            if ($team_info && !empty($team_info['events_array'][0])) {
                $player_detail_category_label = $team_info['events_array'][0];
            }
        }

        $share_player_name = trim((string) ($player_detail['name'] ?? ''));
        $player_share_url = SITE_URL . '/player.php?' . http_build_query([
            'id' => (int) ($player_detail['id'] ?? 0),
            'page' => max(1, (int) $page),
            'search' => (string) $search
        ]) . '#player-detail';
        $player_share_text = 'Lihat profil pemain ' . ($share_player_name !== '' ? $share_player_name : 'ini') . ' di ALVETRIX';
        $player_share_combined_text = $player_share_text . ' ' . $player_share_url;
        $player_share_whatsapp_url = 'https://wa.me/?text=' . rawurlencode($player_share_combined_text);
        $player_share_facebook_url = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($player_share_url);
        $player_share_telegram_url = 'https://t.me/share/url?url=' . rawurlencode($player_share_url) . '&text=' . rawurlencode($player_share_text);
        $player_share_x_url = 'https://twitter.com/intent/tweet?text=' . rawurlencode($player_share_text) . '&url=' . rawurlencode($player_share_url);
    }
}

// Helper Functions
function calculateAgeV2($birth_date) {
    if (empty($birth_date) || $birth_date == '0000-00-00') return '-';
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $diff = $today->diff($birth);
    return $diff->y . ' tahun ' . $diff->m . ' bulan';
}

function maskNIK($nik) {
    if (empty($nik)) return '-';
    if (strlen($nik) < 8) return $nik;
    return substr($nik, 0, 3) . str_repeat('*', 9) . substr($nik, -4);
}

?>

<div class="dashboard-wrapper">
<?php 
$currentPage = 'player';
include 'includes/sidebar.php'; 
?>

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

        <div class="dashboard-body <?php echo ($player_id > 0) ? 'has-player-detail' : ''; ?>">
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
                                            <div class="team-label">Team</div>
                                            <div class="team-name"><?php echo htmlspecialchars($player_detail['team_name'] ?: '-'); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="player-detail-actions">
                                <a href="player.php?<?php echo http_build_query(['page' => $page ?: 1, 'search' => $search ?: null]); ?>" class="btn-filter-reset" style="margin-top: 25px;">
                                    <i class="fas fa-arrow-left"></i> Kembali ke daftar
                                </a>
                                <div class="player-share-section" id="playerSharePanel" data-share-url="<?php echo htmlspecialchars($player_share_url, ENT_QUOTES, 'UTF-8'); ?>" data-share-text="<?php echo htmlspecialchars($player_share_text, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="button" class="player-share-toggle" style="padding-right: 42px;" id="playerShareToggle" aria-expanded="false" aria-controls="playerShareMenu">
                                        <span><i class="fas fa-share-alt"></i> Share Profil</span>
                                        <i class="fas fa-chevron-down player-share-toggle-icon" aria-hidden="true"></i>
                                    </button>
                                    <div class="player-share-menu" id="playerShareMenu">
                                        <div class="player-share-buttons">
                                            <button type="button" class="player-share-btn native" id="playerShareNativeBtn" aria-label="Bagikan profil pemain">
                                                <i class="fas fa-share-nodes"></i> <span>Share</span>
                                            </button>
                                            <a href="<?php echo htmlspecialchars($player_share_whatsapp_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="player-share-btn whatsapp" aria-label="Bagikan ke WhatsApp">
                                                <i class="fab fa-whatsapp"></i> <span>WhatsApp</span>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($player_share_facebook_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="player-share-btn facebook" aria-label="Bagikan ke Facebook">
                                                <i class="fab fa-facebook-f"></i> <span>Facebook</span>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($player_share_telegram_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="player-share-btn telegram" aria-label="Bagikan ke Telegram">
                                                <i class="fab fa-telegram-plane"></i> <span>Telegram</span>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($player_share_x_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="player-share-btn twitter" aria-label="Bagikan ke X">
                                                <svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="player-share-x-icon" aria-hidden="true" focusable="false"><path d="M14.234 10.162 22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z"/></svg>
                                                <span>X</span>
                                            </a>
                                            <button type="button" class="player-share-btn copy" id="playerShareCopyBtn" aria-label="Salin tautan profil pemain">
                                                <i class="far fa-copy"></i> <span>Salin Link</span>
                                            </button>
                                        </div>
                                        <div class="player-share-feedback" id="playerShareFeedback" aria-live="polite"></div>
                                    </div>
                                </div>
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
                                <span class="detail-label">Kategori</span>
                                <span class="detail-value">
                                    <span class="event-badge" style="font-size: 11px; background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; white-space: normal; overflow: visible; text-overflow: clip; max-width: none; word-break: break-word; overflow-wrap: anywhere;"><?php echo htmlspecialchars($player_detail_category_label ?: '-'); ?></span>
                                </span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Dibuat Pada</span>
                                <span class="detail-value"><?php echo date('d M Y, H:i', strtotime($player_detail['created_at'])); ?></span>
                            </div>
                        </div>

                        <div class="player-skills-section" id="playerSkillsPanel">
                            <button type="button" class="player-skills-toggle" id="playerSkillsToggle" aria-expanded="false" aria-controls="playerSkillsContent">
                                <span class="player-skills-toggle-label">
                                    <i class="fas fa-bolt"></i> Skill Pemain
                                </span>
                                <i class="fas fa-chevron-down player-skills-toggle-icon" aria-hidden="true"></i>
                            </button>

                            <div class="player-skills-content" id="playerSkillsContent">
                                <div class="player-skills-head">
                                    <h3 style="margin-bottom: 10px;">Skill Pemain</h3>
                                    <span class="player-skills-scale">Skala 0 - 10</span>
                                </div>

                                <div class="player-skills-grid">
                                    <?php
                                    $detailSkills = [
                                        'dribbling' => ['label' => 'Dribbling', 'icon' => 'fa-person-running'],
                                        'technique' => ['label' => 'Technique', 'icon' => 'fa-gears'],
                                        'speed' => ['label' => 'Speed', 'icon' => 'fa-gauge-high'],
                                        'juggling' => ['label' => 'Juggling', 'icon' => 'fa-futbol'],
                                        'shooting' => ['label' => 'Shooting', 'icon' => 'fa-crosshairs'],
                                        'setplay_position' => ['label' => 'Setplay Position', 'icon' => 'fa-chess-board'],
                                        'passing' => ['label' => 'Passing', 'icon' => 'fa-share-nodes'],
                                        'control' => ['label' => 'Control', 'icon' => 'fa-sliders']
                                    ];

                                    foreach ($detailSkills as $key => $meta):
                                        $rawSkill = isset($player_detail[$key]) ? (int) $player_detail[$key] : 5;
                                        $skillValue = max(0, min(10, $rawSkill));
                                        $skillPercent = (int) round(($skillValue / 10) * 100);
                                    ?>
                                        <div class="player-skill-item">
                                            <div class="player-skill-top">
                                                <span class="player-skill-name"><i class="fas <?php echo $meta['icon']; ?>"></i> <?php echo htmlspecialchars($meta['label']); ?></span>
                                                <span class="player-skill-value"><?php echo $skillValue; ?>/10</span>
                                            </div>
                                            <div class="player-skill-bar">
                                                <span class="player-skill-fill" style="width: <?php echo $skillPercent; ?>%;"></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="player-history-panel" id="playerHistoryPanel" data-player-id="<?php echo (int) ($player_detail['id'] ?? 0); ?>" data-player-name="<?php echo htmlspecialchars((string)($player_detail['name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>" data-player-team="<?php echo htmlspecialchars((string)($player_detail['team_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="button" class="player-history-toggle" id="playerHistoryToggle" aria-expanded="false" aria-controls="playerHistoryContent">
                                <span class="player-history-toggle-label">
                                    <i class="fas fa-chart-line"></i> Riwayat Match Player
                                </span>
                                <i class="fas fa-chevron-down player-history-toggle-icon" aria-hidden="true"></i>
                            </button>
                            <div class="player-history-content" id="playerHistoryContent">
                                <div class="history-loading">
                                    <i class="fas fa-spinner"></i> Klik tombol di atas untuk melihat riwayat pertandingan.
                                </div>
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
                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari nama atau NISN...">
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
                <div class="player-table-responsive">
                    <table class="player-table-new">
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th class="col-photo">Foto</th>
                            <th class="col-name-head">Nama</th>
                            <th class="col-team-head">Team</th>
                            <th class="col-center col-jersey">No Punggung</th>
                            <th class="col-center">Tgl Lahir</th>
                            <th class="col-center">Usia</th>
                            <th class="col-center">JK</th>
                            <th class="col-nisn">NISN</th>
                            <th class="col-nik">NIK</th>
                            <th>Kategori</th>
                            <th class="col-center">Match</th>
                            <th class="col-center">Event</th>
                            <th>Dibuat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($players)): ?>
                            <tr>
                                <td colspan="14">
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
                                <td class="cell-team" data-label="Team">
                                    <div class="col-team-stack">
                                        <?php if (!empty($p['team_logo']) && file_exists('images/teams/' . $p['team_logo'])): ?>
                                            <img src="<?php echo SITE_URL; ?>/images/teams/<?php echo $p['team_logo']; ?>" class="team-logo-small" alt="">
                                        <?php else: ?>
                                            <div class="team-logo-small team-logo-placeholder"></div>
                                        <?php endif; ?>
                                        <span class="team-name-stack"><?php echo htmlspecialchars($p['team_name'] ?: '-'); ?></span>
                                    </div>
                                </td>
                                <td class="col-center col-jersey" data-label="No Punggung"><?php echo $p['jersey_number'] ?: '-'; ?></td>
                                <td class="col-center" data-label="Tgl Lahir"><?php echo !empty($p['birth_date']) ? date('d M Y', strtotime($p['birth_date'])) : '-'; ?></td>
                                <td class="col-center" data-label="Usia"><?php echo calculateAgeV2($p['birth_date']); ?></td>
                                <td class="col-center" data-label="JK"><?php echo $p['gender'] ?: '-'; ?></td>
                                <td class="col-nisn" data-label="NISN"><?php echo htmlspecialchars($p['nisn'] ?: '-'); ?></td>
                                <td class="col-nik" data-label="NIK"><?php echo maskNIK($p['nik']); ?></td>
                                <td data-label="Kategori">

                                    <?php 
                                    $p_categories = [];
                                    if (!empty($p['team_id'])) {
                                        $p_team = getTeamById($p['team_id']);
                                        if ($p_team && !empty($p_team['events_array'])) {
                                            $p_categories = $p_team['events_array'];
                                        }
                                    }
                                    $categoryLabel = !empty($p['sport_type']) ? $p['sport_type'] : (!empty($p_categories[0]) ? $p_categories[0] : '');
                                    ?>

                                    <?php if (!empty($categoryLabel)): ?>
                                        <div class="team-events-badges" style="display: flex; flex-wrap: wrap; gap: 4px;">
                                            <span class="event-badge" style="font-size: 9px; padding: 1px 6px; background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; white-space: normal; overflow: visible; text-overflow: clip; max-width: none; word-break: break-word; overflow-wrap: anywhere;"><?php echo htmlspecialchars($categoryLabel); ?></span>
                                        </div>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="col-center" data-label="Match">
                                    <?php $match_count = $player_match_counts[(int) $p['id']] ?? 0; ?>
                                    <span class="match-count-badge <?php echo $match_count === 0 ? 'zero' : ''; ?>">
                                        <i class="fas fa-futbol"></i> <?php echo $match_count; ?>
                                    </span>
                                </td>
                                <td class="col-center" data-label="Event">
                                    <?php
                                    $event_stats = $player_event_stats[(int) $p['id']] ?? [];
                                    $event_count = count($event_stats);
                                    $event_full_info = [];
                                    foreach ($event_stats as $event_name => $event_match_total) {
                                        $event_full_info[] = $event_name . ' (' . $event_match_total . ' match)';
                                    }
                                    ?>
                                    <span class="event-count-badge-wrap">
                                        <span class="event-count-badge <?php echo $event_count === 0 ? 'zero' : ''; ?>">
                                            <i class="fas fa-calendar-check"></i> <?php echo $event_count; ?>
                                        </span>
                                        <?php if ($event_count > 0): ?>
                                        <div class="event-popover"><?php echo htmlspecialchars(implode("\n", $event_full_info)); ?></div>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td data-label="Dibuat Pada"><?php echo date('d M Y, H:i', strtotime($p['created_at'])); ?></td>
                                <td class="mobile-profile-cell">
                                    <a href="player.php?id=<?php echo $p['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>#player-detail" class="mobile-profile-btn">
                                        <i class="fas fa-user"></i> Cek Profil Pemain
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
</script>

<script>
const escapeHtml = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

const skillsPanel = document.getElementById('playerSkillsPanel');
const skillsToggle = document.getElementById('playerSkillsToggle');
const skillsContent = document.getElementById('playerSkillsContent');

if (skillsPanel && skillsToggle && skillsContent) {
    const mobileSkillsMedia = window.matchMedia('(max-width: 768px)');
    let mobileSkillsInitialized = false;

    const refreshSkillsHeight = () => {
        if (!skillsPanel.classList.contains('open')) return;
        skillsContent.style.maxHeight = 'none';
        const nextHeight = skillsContent.scrollHeight;
        skillsContent.style.maxHeight = `${nextHeight}px`;
    };

    const openSkillsPanel = () => {
        skillsToggle.setAttribute('aria-expanded', 'true');
        skillsPanel.classList.add('open');
        requestAnimationFrame(() => {
            skillsContent.style.maxHeight = `${skillsContent.scrollHeight}px`;
        });
    };

    const closeSkillsPanel = () => {
        skillsToggle.setAttribute('aria-expanded', 'false');
        skillsContent.style.maxHeight = `${skillsContent.scrollHeight}px`;
        requestAnimationFrame(() => {
            skillsPanel.classList.remove('open');
            skillsContent.style.maxHeight = '0px';
        });
    };

    const syncSkillsPanelByViewport = () => {
        if (mobileSkillsMedia.matches) {
            if (!mobileSkillsInitialized) {
                mobileSkillsInitialized = true;
                closeSkillsPanel();
            } else if (skillsPanel.classList.contains('open')) {
                requestAnimationFrame(refreshSkillsHeight);
            }
        } else {
            skillsPanel.classList.add('open');
            skillsToggle.setAttribute('aria-expanded', 'true');
            skillsContent.style.maxHeight = 'none';
        }
    };

    skillsToggle.addEventListener('click', () => {
        if (!mobileSkillsMedia.matches) return;
        const isOpen = skillsToggle.getAttribute('aria-expanded') === 'true';
        if (isOpen) {
            closeSkillsPanel();
        } else {
            openSkillsPanel();
        }
    });

    window.addEventListener('resize', syncSkillsPanelByViewport);
    syncSkillsPanelByViewport();
}

const playerSharePanel = document.getElementById('playerSharePanel');
const playerShareToggle = document.getElementById('playerShareToggle');
const playerShareMenu = document.getElementById('playerShareMenu');
const playerShareCopyBtn = document.getElementById('playerShareCopyBtn');
const playerShareNativeBtn = document.getElementById('playerShareNativeBtn');
const playerShareFeedback = document.getElementById('playerShareFeedback');

if (playerSharePanel) {
    const shareUrl = playerSharePanel.dataset.shareUrl || window.location.href;
    const shareText = playerSharePanel.dataset.shareText || 'Lihat profil pemain ini di ALVETRIX';

    const setShareFeedback = (message, isError = false) => {
        if (!playerShareFeedback) return;
        playerShareFeedback.textContent = message;
        playerShareFeedback.classList.toggle('error', isError);
    };
    const openShareMenu = () => {
        playerSharePanel.classList.add('open');
        if (playerShareToggle) {
            playerShareToggle.setAttribute('aria-expanded', 'true');
        }
    };
    const closeShareMenu = () => {
        playerSharePanel.classList.remove('open');
        if (playerShareToggle) {
            playerShareToggle.setAttribute('aria-expanded', 'false');
        }
    };

    const fallbackCopyLink = (text) => {
        const tempInput = document.createElement('textarea');
        tempInput.value = text;
        tempInput.setAttribute('readonly', '');
        tempInput.style.position = 'absolute';
        tempInput.style.left = '-9999px';
        document.body.appendChild(tempInput);
        tempInput.select();
        let copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (err) {
            copied = false;
        }
        document.body.removeChild(tempInput);
        return copied;
    };

    if (playerShareToggle && playerShareMenu) {
        playerShareToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = playerSharePanel.classList.contains('open');
            if (isOpen) {
                closeShareMenu();
            } else {
                openShareMenu();
            }
        });

        playerShareMenu.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        document.addEventListener('click', (event) => {
            if (!playerSharePanel.contains(event.target)) {
                closeShareMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeShareMenu();
            }
        });
    }

    if (playerShareCopyBtn) {
        playerShareCopyBtn.addEventListener('click', () => {
            const handleCopySuccess = () => {
                playerShareCopyBtn.classList.add('copied');
                setShareFeedback('Tautan profil berhasil disalin.');
                setTimeout(() => {
                    playerShareCopyBtn.classList.remove('copied');
                    setShareFeedback('');
                }, 1600);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shareUrl)
                    .then(handleCopySuccess)
                    .catch(() => {
                        if (fallbackCopyLink(shareUrl)) {
                            handleCopySuccess();
                        } else {
                            setShareFeedback('Gagal menyalin tautan profil.', true);
                        }
                    });
            } else if (fallbackCopyLink(shareUrl)) {
                handleCopySuccess();
            } else {
                setShareFeedback('Gagal menyalin tautan profil.', true);
            }
        });
    }

    if (playerShareNativeBtn && navigator.share) {
        playerShareNativeBtn.style.display = 'inline-flex';
        playerShareNativeBtn.addEventListener('click', () => {
            navigator.share({
                title: 'Profil Pemain ALVETRIX',
                text: shareText,
                url: shareUrl
            }).then(() => {
                setShareFeedback('Profil berhasil dibagikan.');
                setTimeout(() => setShareFeedback(''), 1600);
            }).catch((err) => {
                if (err && err.name === 'AbortError') return;
                setShareFeedback('Gagal membuka menu bagikan.', true);
            });
        });
    }
}

const renderPlayerHistoryRows = (matches) => {
    let rows = '';

    matches.forEach((m, i) => {
        let resultHtml = '<span class="h-result-na">-</span>';
        if (m.status === 'completed' && m.player_team_side !== null) {
            const myScore = m.player_team_side === 'challenger' ? parseInt(m.challenger_score, 10) : parseInt(m.opponent_score, 10);
            const oppScore = m.player_team_side === 'challenger' ? parseInt(m.opponent_score, 10) : parseInt(m.challenger_score, 10);
            if (!Number.isNaN(myScore) && !Number.isNaN(oppScore)) {
                if (myScore > oppScore) resultHtml = `<span class="h-result-win">M ${myScore}-${oppScore}</span>`;
                else if (myScore < oppScore) resultHtml = `<span class="h-result-lose">K ${myScore}-${oppScore}</span>`;
                else if (myScore === oppScore) resultHtml = `<span class="h-result-draw">S ${myScore}-${oppScore}</span>`;
            }
        }

        const hasLineup = (m.appearance_source || '') === 'lineup';
        const starterHtml = hasLineup
            ? (Number(m.is_starting) === 1
                ? '<span class="h-starter-badge h-starter-yes"><i class="fas fa-star"></i> Starter</span>'
                : '<span class="h-starter-badge h-starter-sub"><i class="fas fa-random"></i> Sub</span>')
            : '<span class="h-starter-badge h-starter-sub"><i class="fas fa-info-circle"></i> Belum ada lineup</span>';

        const halfLabel = hasLineup
            ? (m.half ? `<span class="h-half-pill">Babak ${escapeHtml(String(m.half))}</span>` : '<span class="h-half-pill">B1</span>')
            : '<span class="h-half-pill">-</span>';
        const lawan = m.player_team_side === 'challenger' ? m.opponent_name : m.challenger_name;
        const myTeam = m.player_team_side === 'challenger' ? m.challenger_name : m.opponent_name;

        rows += `
            <tr>
                <td class="col-no">${i + 1}</td>
                <td class="col-event"><span class="h-event-badge" title="${escapeHtml(m.event_name || '-')}">${escapeHtml(m.event_name || '-')}</span></td>
                <td class="col-kategori"><span class="h-event-badge" title="${escapeHtml(m.sport_type || '-')}">${escapeHtml(m.sport_type || '-')}</span></td>
                <td class="col-match">#${escapeHtml(String(m.challenge_id || ''))}<br><small style="color:#94a3b8">${escapeHtml(m.challenge_code || '-')}</small></td>
                <td class="col-pertandingan">${escapeHtml(myTeam || '-')} <span style="color:#94a3b8">vs</span> ${escapeHtml(lawan || '-')}</td>
                <td class="col-tanggal">${escapeHtml(m.challenge_date_fmt || '-')}</td>
                <td class="col-babak">${halfLabel}</td>
                <td class="col-posisi">${escapeHtml(m.position || '-')}</td>
                <td class="col-peran">${starterHtml}</td>
                <td class="col-hasil">${resultHtml}</td>
            </tr>
        `;
    });

    return `
        <div class="history-table-wrap">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Event</th>
                        <th>Kategori</th>
                        <th>Match</th>
                        <th>Pertandingan</th>
                        <th>Tanggal</th>
                        <th>Babak</th>
                        <th>Posisi</th>
                        <th>Peran</th>
                        <th>Hasil</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
    `;
};

const historyPanel = document.getElementById('playerHistoryPanel');
const historyToggle = document.getElementById('playerHistoryToggle');
const historyContent = document.getElementById('playerHistoryContent');

if (historyPanel && historyToggle && historyContent) {
    let loaded = false;
    let loading = false;

    const playerId = historyPanel.dataset.playerId || '0';
    const playerTeam = historyPanel.dataset.playerTeam || '-';
    const refreshPanelHeight = () => {
        if (!historyPanel.classList.contains('open')) return;
        historyContent.style.maxHeight = 'none';
        const nextHeight = historyContent.scrollHeight;
        historyContent.style.maxHeight = `${nextHeight}px`;
    };
    const openPanel = () => {
        historyToggle.setAttribute('aria-expanded', 'true');
        historyPanel.classList.add('open');
        requestAnimationFrame(() => {
            historyContent.style.maxHeight = `${historyContent.scrollHeight}px`;
        });
    };
    const closePanel = () => {
        historyToggle.setAttribute('aria-expanded', 'false');
        historyContent.style.maxHeight = `${historyContent.scrollHeight}px`;
        requestAnimationFrame(() => {
            historyPanel.classList.remove('open');
            historyContent.style.maxHeight = '0px';
        });
    };

    const loadHistory = () => {
        if (loaded || loading || !playerId || playerId === '0') return;
        loading = true;
        historyContent.innerHTML = '<div class="history-loading"><i class="fas fa-spinner"></i> Memuat riwayat pertandingan...</div>';

        fetch(`get_player_match_history.php?player_id=${encodeURIComponent(playerId)}`)
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    historyContent.innerHTML = `<div class="history-empty"><i class="fas fa-exclamation-circle"></i><p>${escapeHtml(data.message || 'Terjadi kesalahan.')}</p></div>`;
                    requestAnimationFrame(refreshPanelHeight);
                    return;
                }

                if (!data.matches || data.matches.length === 0) {
                    historyContent.innerHTML = '<div class="history-empty"><i class="fas fa-futbol"></i><p>Belum ada riwayat pertandingan untuk pemain ini.</p></div>';
                    requestAnimationFrame(refreshPanelHeight);
                    return;
                }

                const eventCount = Array.isArray(data.event_summary) ? data.event_summary.length : (data.event_total || 0);
                const summaryHtml = `
                    <div class="player-history-summary">
                        <span class="meta-pill meta-pill-outline"><i class="fas fa-shield-alt"></i> Tim: ${escapeHtml(playerTeam)}</span>
                        <span class="meta-pill meta-pill-outline"><i class="fas fa-calendar-check"></i> Event: ${escapeHtml(String(eventCount))}</span>
                        <span class="meta-pill meta-pill-outline"><i class="fas fa-futbol"></i> Match: ${escapeHtml(String(data.total || data.matches.length))}</span>
                    </div>
                `;
                historyContent.innerHTML = summaryHtml + renderPlayerHistoryRows(data.matches);
                loaded = true;
                requestAnimationFrame(refreshPanelHeight);
            })
            .catch(() => {
                historyContent.innerHTML = '<div class="history-empty"><i class="fas fa-exclamation-circle"></i><p>Gagal memuat data. Periksa koneksi server.</p></div>';
                requestAnimationFrame(refreshPanelHeight);
            })
            .finally(() => {
                loading = false;
            });
    };

    historyToggle.addEventListener('click', () => {
        const isOpen = historyToggle.getAttribute('aria-expanded') === 'true';
        if (isOpen) {
            closePanel();
        } else {
            openPanel();
            loadHistory();
        }
    });
}
</script>

<script>
// Define SITE_URL for JavaScript
const SITE_URL = '<?php echo SITE_URL; ?>';
</script>
<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo getAssetVersion('/js/script.js'); ?>"></script>
<script>
// Tap-to-expand popover for event count badges (mobile-friendly)
(function () {
    document.addEventListener('click', function (e) {
        var wrap = e.target.closest('.event-count-badge-wrap');
        document.querySelectorAll('.event-count-badge-wrap.pop-open').forEach(function (el) {
            if (el !== wrap) el.classList.remove('pop-open');
        });
        if (wrap) {
            var badge = wrap.querySelector('.event-count-badge');
            if (badge && e.target.closest('.event-count-badge')) {
                wrap.classList.toggle('pop-open');
            }
        }
    });
})();
</script>
</body>
</html>
