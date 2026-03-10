<?php
// DEBUG MODE
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';

// Cek koneksi database
if (!$db || !$db->getConnection()) {
    die("Database connection failed!");
}

// Cek SITE_URL
if (!defined('SITE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $site_url = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    define('SITE_URL', $site_url);
}

// Logic for Search and Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 40;
$offset = ($page - 1) * $limit;
$perangkat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Database connection
$conn = $db->getConnection();
$has_challenge_event_id = false;
$has_challenge_match_official = false;
if ($check_event_id_col = $conn->query("SHOW COLUMNS FROM challenges LIKE 'event_id'")) {
    $has_challenge_event_id = $check_event_id_col->num_rows > 0;
    $check_event_id_col->close();
}
if ($check_official_col = $conn->query("SHOW COLUMNS FROM challenges LIKE 'match_official'")) {
    $has_challenge_match_official = $check_official_col->num_rows > 0;
    $check_official_col->close();
}

// Query for Total Records (for pagination)
$count_query = "SELECT COUNT(*) as total FROM perangkat p WHERE p.is_active = 1";
if (!empty($search)) {
    $count_query .= " AND (p.name LIKE ?)";
}

$stmt_count = $conn->prepare($count_query);
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt_count->bind_param("s", $search_param);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
if ($count_result) {
    $total_records = $count_result->fetch_assoc()['total'];
} else {
    $total_records = 0;
}
$total_pages = max(1, (int)ceil($total_records / $limit));

// Query for Perangkat Data with license/match/event counts
$matchCountSelect = "0 AS match_count";
$eventCountSelect = "0 AS event_count";
$eventNamesSelect = "'[]' AS event_names";

if ($has_challenge_match_official) {
    $officialNameMatchSql = "(
        LOWER(TRIM(c.match_official)) = LOWER(TRIM(p.name))
        OR FIND_IN_SET(
            LOWER(TRIM(p.name)),
            REPLACE(REPLACE(LOWER(TRIM(c.match_official)), ', ', ','), ' ,', ',')
        ) > 0
    )";

    $matchCountSelect = "(SELECT COUNT(DISTINCT c.id)
        FROM challenges c
        WHERE c.match_official IS NOT NULL
          AND TRIM(c.match_official) <> ''
          AND $officialNameMatchSql
    ) AS match_count";

    if ($has_challenge_event_id) {
        $eventCountSelect = "(SELECT COUNT(DISTINCT e.id)
            FROM challenges c
            INNER JOIN events e ON c.event_id = e.id
            WHERE c.match_official IS NOT NULL
              AND TRIM(c.match_official) <> ''
              AND $officialNameMatchSql
              AND c.event_id IS NOT NULL
        ) AS event_count";

        $eventNamesSelect = "(SELECT COALESCE(
            CONCAT(
                '[',
                GROUP_CONCAT(DISTINCT JSON_QUOTE(TRIM(e.name)) ORDER BY TRIM(e.name) SEPARATOR ','),
                ']'
            ),
            '[]'
        )
            FROM challenges c
            INNER JOIN events e ON c.event_id = e.id
            WHERE c.match_official IS NOT NULL
              AND TRIM(c.match_official) <> ''
              AND $officialNameMatchSql
              AND c.event_id IS NOT NULL
              AND TRIM(e.name) <> ''
        ) AS event_names";
    }
}

$query = "SELECT 
    p.*,
    (SELECT COUNT(*) FROM perangkat_licenses pl WHERE pl.perangkat_id = p.id) as certificate_count,
    $matchCountSelect,
    $eventCountSelect,
    $eventNamesSelect
    FROM perangkat p
    WHERE p.is_active = 1";
    
if (!empty($search)) {
    $query .= " AND (p.name LIKE ?)";
}
$query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!empty($search)) {
    $stmt->bind_param("sii", $search_param, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$perangkatRows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$perangkat_detail = null;
$perangkat_detail_event_names = [];
$perangkat_detail_address = '-';
$perangkat_share_url = '';
$perangkat_share_text = '';
$perangkat_share_whatsapp_url = '#';
$perangkat_share_facebook_url = '#';
$perangkat_share_telegram_url = '#';
$perangkat_share_x_url = '#';
if ($perangkat_id > 0) {
    $detailMatchCountSelect = "0 AS match_count";
    $detailEventCountSelect = "0 AS event_count";
    $detailEventNamesSelect = "'[]' AS event_names";

    if ($has_challenge_match_official) {
        $detailOfficialNameMatchSql = "(
            LOWER(TRIM(c.match_official)) = LOWER(TRIM(pd.name))
            OR FIND_IN_SET(
                LOWER(TRIM(pd.name)),
                REPLACE(REPLACE(LOWER(TRIM(c.match_official)), ', ', ','), ' ,', ',')
            ) > 0
        )";

        $detailMatchCountSelect = "(SELECT COUNT(DISTINCT c.id)
            FROM challenges c
            WHERE c.match_official IS NOT NULL
              AND TRIM(c.match_official) <> ''
              AND $detailOfficialNameMatchSql
        ) AS match_count";

        if ($has_challenge_event_id) {
            $detailEventCountSelect = "(SELECT COUNT(DISTINCT e.id)
                FROM challenges c
                INNER JOIN events e ON c.event_id = e.id
                WHERE c.match_official IS NOT NULL
                  AND TRIM(c.match_official) <> ''
                  AND $detailOfficialNameMatchSql
                  AND c.event_id IS NOT NULL
            ) AS event_count";

            $detailEventNamesSelect = "(SELECT COALESCE(
                CONCAT(
                    '[',
                    GROUP_CONCAT(DISTINCT JSON_QUOTE(TRIM(e.name)) ORDER BY TRIM(e.name) SEPARATOR ','),
                    ']'
                ),
                '[]'
            )
                FROM challenges c
                INNER JOIN events e ON c.event_id = e.id
                WHERE c.match_official IS NOT NULL
                  AND TRIM(c.match_official) <> ''
                  AND $detailOfficialNameMatchSql
                  AND c.event_id IS NOT NULL
                  AND TRIM(e.name) <> ''
            ) AS event_names";
        }
    }

    $detail_query = "SELECT
        pd.*,
        (SELECT COUNT(*) FROM perangkat_licenses pl WHERE pl.perangkat_id = pd.id) AS certificate_count,
        $detailMatchCountSelect,
        $detailEventCountSelect,
        $detailEventNamesSelect
        FROM perangkat pd
        WHERE pd.id = ?
        LIMIT 1";
    $detail_stmt = $conn->prepare($detail_query);
    if ($detail_stmt) {
        $detail_stmt->bind_param("i", $perangkat_id);
        $detail_stmt->execute();
        $perangkat_detail = $detail_stmt->get_result()->fetch_assoc();
        $detail_stmt->close();
    }

    if ($perangkat_detail) {
        $perangkat_detail_event_names = parsePerangkatEventNamesPayload($perangkat_detail['event_names'] ?? '');

        $address_parts = [];
        foreach (['address', 'city', 'province', 'postal_code', 'country'] as $field) {
            $value = trim((string)($perangkat_detail[$field] ?? ''));
            if ($value !== '') {
                $address_parts[] = $value;
            }
        }
        if (!empty($address_parts)) {
            $perangkat_detail_address = implode(', ', $address_parts);
        }

        $share_perangkat_name = trim((string)($perangkat_detail['name'] ?? ''));
        $perangkat_share_url = SITE_URL . '/perangkat.php?' . http_build_query([
            'id' => (int)($perangkat_detail['id'] ?? 0),
            'page' => max(1, (int)$page),
            'search' => (string)$search
        ]) . '#perangkat-detail';
        $perangkat_share_text = 'Lihat profil perangkat pertandingan ' . ($share_perangkat_name !== '' ? $share_perangkat_name : 'ini') . ' di ALVETRIX';
        $perangkat_share_combined_text = $perangkat_share_text . ' ' . $perangkat_share_url;
        $perangkat_share_whatsapp_url = 'https://wa.me/?text=' . rawurlencode($perangkat_share_combined_text);
        $perangkat_share_facebook_url = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($perangkat_share_url);
        $perangkat_share_telegram_url = 'https://t.me/share/url?url=' . rawurlencode($perangkat_share_url) . '&text=' . rawurlencode($perangkat_share_text);
        $perangkat_share_x_url = 'https://twitter.com/intent/tweet?text=' . rawurlencode($perangkat_share_text) . '&url=' . rawurlencode($perangkat_share_url);
    }
}

// Helper Functions
function calculatePerangkatAge($birthDateRaw) {
    if (empty($birthDateRaw) || $birthDateRaw === '0000-00-00') return '-';

    $dob = DateTimeImmutable::createFromFormat('Y-m-d', (string)$birthDateRaw);
    if ($dob && $dob->format('Y-m-d') === (string)$birthDateRaw) {
        $today = new DateTimeImmutable('today');
        if ($dob > $today) return '-';
        return (string)$dob->diff($today)->y . ' tahun';
    }

    if (is_numeric($birthDateRaw)) {
        return max(0, (int)$birthDateRaw) . ' tahun';
    }

    return '-';
}

// Helper function to check file exists and return correct path
function getFileUrl($filename, $directory, $defaultIcon = 'fa-user') {
    if (empty($filename)) {
        return [
            'url' => null,
            'found' => false,
            'icon' => $defaultIcon
        ];
    }
    
    // Extract just the filename (remove path if exists)
    $basename = basename($filename);
    
    // Check various possible locations
    $locations = [
        $directory . '/' . $basename,
        'uploads/' . $directory . '/' . $basename,
        'images/' . $directory . '/' . $basename,
        '../uploads/' . $directory . '/' . $basename,
        '../images/' . $directory . '/' . $basename,
        'assets/' . $directory . '/' . $basename,
        $filename
    ];
    
    foreach ($locations as $location) {
        // Clean path
        $clean_path = str_replace(['../', './', '//'], '', $location);
        
        if (file_exists($clean_path) && is_file($clean_path)) {
            return [
                'url' => SITE_URL . '/' . $clean_path,
                'found' => true,
                'icon' => $defaultIcon
            ];
        }
    }
    
    // If not found, return placeholder
    return [
        'url' => null,
        'found' => false,
        'icon' => $defaultIcon
    ];
}

function maskPerangkatKtp($noKtp) {
    $raw = trim((string)$noKtp);
    if ($raw === '') {
        return '-';
    }

    $length = strlen($raw);
    if ($length <= 7) {
        return substr($raw, 0, 1) . str_repeat('*', max($length - 2, 1)) . substr($raw, -1);
    }

    return substr($raw, 0, 3) . str_repeat('*', max($length - 7, 1)) . substr($raw, -4);
}


// Page Metadata
$pageTitle = "Perangkat Pertandingan";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
   <style>
    /* Hero Banner Styles */
.staff-hero {
    background: linear-gradient(135deg, #1a1a1a 0%, #c00 100%);
    padding: 60px 0;
    text-align: center;
    color: #fff;
    margin-bottom: 40px;
}

.staff-hero h1 {
    font-size: 48px;
    font-weight: 800;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 3px;
}

/* CSS Reset and Base for the section */
.staff-list-section {
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

.staff-table-container {
    background: #fff;
    border-radius: 8px;
    overflow-x: auto;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    border: 1px solid #333;
}

.staff-table {
    width: 100%;
    border-collapse: collapse;
    color: #333;
    font-size: 13px;
    min-width: 1200px;
}

.staff-table thead tr {
    background: linear-gradient(to right, #000, #c00); /* Dark to Red gradient */
    color: #fff;
}

.staff-table th {
    padding: 12px 10px;
    text-align: left;
    font-weight: 700;
    text-transform: capitalize;
    border-right: 1px solid rgba(255,255,255,0.1);
}

.staff-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

.staff-table tbody tr:hover {
    background-color: #f9f9f9;
}

/* Specific alignments and styles from reference */
.col-no { width: 40px; text-align: center; }
.col-photo { width: 80px; text-align: center; }
.col-name { color: #111827; font-weight: 500; }
.col-team { display: flex; align-items: center; gap: 8px; }
.team-logo-small { width: 24px; height: 24px; border-radius: 50%; object-fit: contain; background: #eee; }
.col-center { text-align: center; }

/* Staff Photo Styles */
.staff-photo-wrapper {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 50px;
}

.staff-img-sm {
    width: 100%;
    height: 100%;
    border-radius: 4px; /* Changed from 50% to 4px */
    object-fit: cover;
    border: 1px solid #ddd;
}

.photo-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 4px; /* Changed from 50% to 4px */
    background: #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    font-size: 18px;
    border: 1px solid #ddd;
}

/* Team Badge Styles */
.team-badge {
    position: absolute;
    bottom: -5px;
    right: -5px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: white;
    border: 2px solid white;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.team-badge-img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.team-badge-placeholder {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: linear-gradient(135deg, #48bb78, #38a169);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 9px;
}

/* Staff Info Styles */
.staff-name {
    font-weight: 600;
    color: #111827;
    font-size: 14px;
    margin-bottom: 3px;
}

.staff-contact {
    font-size: 11px;
    color: #718096;
}

.staff-contact i {
    margin-right: 5px;
    width: 12px;
}

/* Team Display */
.team-display {
    display: flex;
    align-items: center;
    gap: 8px;
}

.team-logo {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    background: #e2e8f0;
}

.team-info {
    display: flex;
    flex-direction: column;
}

.team-name {
    font-weight: 600;
    color: #2d3748;
    font-size: 13px;
}

.team-alias {
    font-size: 10px;
    color: #718096;
}

/* Position Badge Styles */
.col-position {
    text-align: center;
    width: 120px;
}

.position-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.manager-badge { background: #1e40af; color: white; }
.headcoach-badge { background: #059669; color: white; }
.coach-badge { background: #7c3aed; color: white; }
.goalkeeper_coach-badge { background: #d97706; color: white; }
.medic-badge { background: #dc2626; color: white; }
.official-badge { background: #475569; color: white; }
.assistant_coach-badge { background: #0891b2; color: white; }
.fitness_coach-badge { background: #ea580c; color: white; }
.analyst-badge { background: #9333ea; color: white; }
.scout-badge { background: #65a30d; color: white; }

/* Certificate Count */
.col-certificate {
    text-align: center;
    width: 80px;
}

.cert-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 10px;
    background: #10b981;
    color: white;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.cert-count:hover {
    background: #059669;
    transform: scale(1.05);
}

.cert-count i {
    margin-right: 4px;
    font-size: 10px;
}

/* Events & Matches Count */
.col-events, .col-matches {
    text-align: center;
    width: 70px;
}

.event-match-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 2px 8px;
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
}
.event-match-count.zero {
    background: #f1f5f9;
    color: #334155;
    border-color: #cbd5e1;
}
.event-match-count.event {
    background: #fef3c7;
    color: #92400e;
    border-color: #fcd34d;
}
.event-match-count.event.zero {
    background: #f1f5f9;
    color: #334155;
    border-color: #cbd5e1;
}

.event-match-count i {
    font-size: 10px;
}

/* Popover for event count badge */
.event-count-badge-wrap {
    position: relative;
    display: inline-block;
}
.event-match-count.event.event-popover-trigger {
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
    padding: 0;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.22);
    pointer-events: auto;
    width: clamp(220px, 32vw, 340px);
    max-height: 280px;
    overflow: hidden;
    white-space: normal;
    text-align: left;
}
.event-popover-header {
    padding: 8px 12px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #bfdbfe;
    border-bottom: 1px solid rgba(148, 163, 184, 0.35);
}
.event-popover-list {
    list-style: none;
    margin: 0;
    padding: 6px 0;
    max-height: calc(280px - 34px);
    overflow-y: auto;
    overscroll-behavior: contain;
}
.event-popover-item {
    display: block;
    padding: 6px 12px;
}
.event-popover-item + .event-popover-item {
    border-top: 1px solid rgba(148, 163, 184, 0.2);
}
.event-popover-name {
    color: #f8fafc;
    word-break: break-word;
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
    width: 30px;
    height: 30px;
    border: 0;
    border-radius: 8px;
    background: #dbeafe;
    color: #1d4ed8;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.history-btn:hover {
    background: #bfdbfe;
    transform: translateY(-1px);
}

.match-history-modal {
    position: fixed;
    inset: 0;
    z-index: 10002;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(2, 6, 23, 0.6);
    padding: 20px;
}
.match-history-modal.open {
    display: flex;
}
.match-history-content {
    width: min(1080px, 100%);
    max-height: calc(100vh - 40px);
    background: #fff;
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 24px 50px rgba(0, 0, 0, 0.3);
}
.match-history-header {
    padding: 14px 18px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
}
.match-history-header h3 {
    margin: 0;
    font-size: 18px;
    color: #0f172a;
}
.match-history-meta {
    margin-top: 4px;
    color: #64748b;
    font-size: 13px;
}
.match-history-close {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 10px;
    background: #f1f5f9;
    color: #334155;
    cursor: pointer;
}
.match-history-body {
    padding: 14px 18px 18px;
    overflow: auto;
}
.match-history-loading,
.match-history-empty {
    text-align: center;
    color: #64748b;
    padding: 30px 10px;
}
.match-history-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 920px;
}
.match-history-table th,
.match-history-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 13px;
    color: #0f172a;
    text-align: left;
    vertical-align: top;
}
.match-history-table th {
    background: #f8fafc;
    font-size: 12px;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}
.history-status-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}
.history-status-pill.completed { background: #dcfce7; color: #166534; }
.history-status-pill.accepted { background: #dbeafe; color: #1d4ed8; }
.history-status-pill.pending { background: #fef3c7; color: #92400e; }
.history-status-pill.default { background: #e2e8f0; color: #475569; }

/* Created At */
.col-created {
    color: #718096;
    font-size: 12px;
    white-space: nowrap;
}

/* No Data Row */
.no-data {
    text-align: center;
    padding: 40px !important;
    color: #718096;
    font-size: 14px;
}

.no-data i {
    font-size: 36px;
    margin-bottom: 10px;
    color: #cbd5e0;
}

/* Pagination Styles */
.pagination-info {
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #ccc;
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

/* Header sort icons */
.sort-icon::after {
    content: " \21D5";
    font-size: 10px;
    opacity: 0.5;
}

/* Horizontal scrollbar styling */
.staff-table-container::-webkit-scrollbar {
    height: 10px;
}
.staff-table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
}
.staff-table-container::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 5px;
}
.staff-table-container::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Modal Styles (minimal for certificates) */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-content {
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f7fafc;
}

.modal-title {
    color: #1a365d;
    font-size: 18px;
    font-weight: 700;
    margin: 0;
}

.modal-title i {
    margin-right: 8px;
    color: #10b981;
}

.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    color: #718096;
    cursor: pointer;
    padding: 5px;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.close-modal:hover {
    background: #fed7d7;
    color: #dc2626;
}

.modal-body {
    padding: 20px;
}

/* Certificates Grid */
.certificates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.certificate-card {
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    overflow: hidden;
    background: white;
}

.certificate-header {
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #e2e8f0;
}

.certificate-title {
    font-size: 16px;
    font-weight: 600;
    color: #1a365d;
    margin-bottom: 8px;
}

.certificate-meta {
    font-size: 12px;
    color: #718096;
}

.certificate-meta i {
    width: 14px;
    margin-right: 6px;
    color: #4a5568;
}

.certificate-preview {
    padding: 15px;
    text-align: center;
    background: #f8f9fa;
}

.certificate-image {
    max-width: 100%;
    max-height: 150px;
    border-radius: 4px;
    cursor: pointer;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.file-preview {
    padding: 20px;
    text-align: center;
    background: #f8f9fa;
}

.file-icon {
    font-size: 40px;
    color: #4a5568;
    margin-bottom: 10px;
}

.file-name {
    font-size: 12px;
    color: #718096;
    word-break: break-all;
    margin-bottom: 15px;
}

.file-actions {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.btn-view, .btn-download {
    padding: 6px 12px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-view {
    background: #3b82f6;
    color: white;
}

.btn-download {
    background: #10b981;
    color: white;
}

.no-certificates {
    text-align: center;
    padding: 40px 20px;
    color: #718096;
}

.no-certificates i {
    font-size: 40px;
    margin-bottom: 15px;
    color: #cbd5e0;
}

.no-certificates h3 {
    color: #4a5568;
    margin-bottom: 10px;
}

/* Image Viewer */
.image-viewer {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    z-index: 10001;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 20px;
}

.image-viewer img {
    max-width: 90%;
    max-height: 80%;
    object-fit: contain;
    border-radius: 4px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.5);
}

.image-viewer .close-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.image-viewer .image-title {
    color: white;
    margin-top: 15px;
    font-size: 16px;
    text-align: center;
    max-width: 80%;
}

/* Loading Spinner */
.loading-container {
    text-align: center;
    padding: 40px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #e2e8f0;
    border-top-color: #0066cc;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .staff-table th,
    .staff-table td {
        padding: 10px 8px;
        font-size: 12px;
    }
    
    .staff-img-sm {
        width: 45px;
        height: 45px;
    }
    
    .photo-placeholder {
        width: 45px;
        height: 45px;
    }
}

@media (max-width: 768px) {
    .search-container {
        max-width: 100%;
    }

    .event-popover {
        width: min(320px, calc(100vw - 24px));
        max-height: 45vh;
    }

    .event-popover-list {
        max-height: calc(45vh - 34px);
    }
    
    .pagination-info {
        flex-direction: column;
        gap: 10px;
        text-align: center;
    }
    
    .certificates-grid {
        grid-template-columns: 1fr;
    }
}
</style>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo getAssetVersion('/css/redesign_core.css'); ?>">
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/staff_redesign.css?v=<?php echo getAssetVersion('/css/staff_redesign.css'); ?>">
<style>
.staff-table-new td.col-name .staff-name {
    color: #111827 !important;
}

.dashboard-body.has-profile-detail .filter-card {
    margin-top: 0;
}

.entity-profile-card {
    background: var(--white);
    border-radius: 22px;
    padding: 24px;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--gray-100);
    margin-top: -36px;
    margin-bottom: 20px;
    position: relative;
    z-index: 9;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.entity-empty-state {
    padding: 22px 16px;
    text-align: center;
    color: var(--gray-500);
}

.entity-empty-state h3 {
    margin: 12px 0 6px;
    font-size: 18px;
    color: var(--navy);
}

.entity-profile-header {
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.entity-profile-identity {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.entity-profile-photo {
    width: 130px;
    height: 130px;
    border-radius: 18px;
    overflow: hidden;
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    justify-content: center;
}

.entity-profile-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.entity-profile-photo-placeholder {
    color: var(--gray-400);
    font-size: 30px;
}

.entity-profile-main h2 {
    margin: 0 0 10px;
    font-size: 24px;
    color: var(--navy);
}

.entity-profile-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.entity-meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.6px;
    text-transform: uppercase;
}

.entity-meta-pill.outline {
    background: var(--white-blue);
    color: var(--navy);
    border: 1px solid var(--gray-200);
}

.entity-profile-actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    width: min(520px, 100%);
    align-content: flex-start;
}

.entity-profile-actions > .btn-filter-reset,
.entity-profile-actions > .player-share-section {
    grid-column: 1 / -1;
}

.entity-profile-actions .btn-filter-reset,
.entity-profile-actions .entity-profile-btn {
    width: 100%;
    justify-content: center;
}

.entity-profile-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 42px;
    padding: 10px 16px;
    border-radius: 12px;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #1e3a8a;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.entity-profile-btn-accent {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    border-color: #1d4ed8;
    color: #fff;
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
    left: 0;
    right: auto;
    z-index: 20;
    width: 100%;
    max-width: none;
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

.entity-profile-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.entity-detail-item {
    background: var(--gray-50);
    border: 1px solid var(--gray-200);
    border-radius: 12px;
    padding: 12px;
}

.entity-detail-label {
    display: block;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--gray-500);
    font-weight: 700;
    margin-bottom: 6px;
}

.entity-detail-value {
    font-size: 13px;
    color: var(--navy);
    font-weight: 700;
    line-height: 1.5;
}

.entity-detail-sub {
    display: block;
    margin-top: 5px;
    font-size: 11px;
    color: var(--gray-500);
    font-weight: 500;
}

.staff-name-link {
    text-decoration: none;
    display: inline-block;
}

.staff-name-link:hover {
    text-decoration: underline;
}

@media (min-width: 992px) {
    .entity-profile-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: flex-start;
    }

    .entity-profile-identity {
        flex-direction: row;
        align-items: center;
        gap: 18px;
    }

    .entity-profile-actions {
        justify-content: initial;
    }
}

@media (min-width: 768px) {
    .entity-profile-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .entity-profile-card {
        margin-top: -14px;
        border-radius: 16px;
        padding: 16px;
    }

    .entity-profile-photo {
        width: 100px;
        height: 100px;
    }

    .entity-profile-actions,
    .entity-profile-actions .btn-filter-reset,
    .entity-profile-btn {
        width: 100%;
    }

    .entity-profile-actions {
        grid-template-columns: 1fr;
    }

    .entity-profile-actions > .btn-filter-reset,
    .entity-profile-actions > .player-share-section {
        grid-column: auto;
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
    .player-share-buttons {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<!-- Certificate Modal -->
<div class="modal-overlay" id="certificateModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-certificate"></i> <span id="modalPerangkatName">Lisensi Perangkat</span></h2>
            <button class="close-modal" onclick="closeCertificateModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="certificateContent">
                <!-- Certificate content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Image Viewer -->
<div class="image-viewer" id="imageViewer">
    <button class="close-btn" onclick="closeImageViewer()">&times;</button>
    <img id="fullSizeImage" src="" alt="">
    <div class="image-title" id="imageTitle"></div>
</div>

<div class="dashboard-wrapper">
<?php 
$currentPage = 'perangkat';
include 'includes/sidebar.php'; 
?>

    <!-- Main Content -->
    <div class="main-content-dashboard">
        <header class="dashboard-header dashboard-header-staff">
            <div class="dashboard-header-inner">
                <div>
                    <div class="header-eyebrow">ALVETRIX</div>
                    <h1>PERANGKAT PERTANDINGAN</h1>
                    <p class="header-subtitle">Direktori perangkat, lisensi, dan identitas resmi untuk memantau kualitas personel pertandingan.</p>
                </div>
                <div class="header-actions">
                    <div class="header-stat">
                        <span class="stat-label">Total Perangkat Aktif</span>
                        <span class="stat-value"><?php echo number_format($total_records); ?></span>
                    </div>
                    <a href="match.php" class="btn-secondary"><i class="fas fa-futbol"></i> Lihat Match</a>
                </div>
            </div>
        </header>

        <div class="dashboard-body <?php echo ($perangkat_id > 0) ? 'has-profile-detail' : ''; ?>">
            <?php if ($perangkat_id > 0): ?>
                <section class="entity-profile-card" id="perangkat-detail">
                    <?php if (!$perangkat_detail): ?>
                        <div class="entity-empty-state">
                            <div><i class="fas fa-user-slash"></i></div>
                            <h3>Profil perangkat tidak ditemukan</h3>
                            <p>Data perangkat dengan ID tersebut tidak tersedia.</p>
                        </div>
                    <?php else: ?>
                        <?php
                        $perangkat_detail_photo = getFileUrl($perangkat_detail['photo'] ?? '', 'perangkat', 'fa-user-tie');
                        $perangkat_back_query = http_build_query([
                            'page' => max(1, (int)$page),
                            'search' => $search !== '' ? $search : null
                        ]);
                        ?>
                        <div class="entity-profile-header">
                            <div class="entity-profile-identity">
                                <div class="entity-profile-photo">
                                    <?php if ($perangkat_detail_photo['found']): ?>
                                        <img src="<?php echo $perangkat_detail_photo['url']; ?>" 
                                                 class="staff-img-sm" 
                                                 alt="<?php echo htmlspecialchars((string)($perangkat_detail['name'] ?? '')); ?>">
                                    <?php else: ?>
                                        <div class="entity-profile-photo-placeholder"><i class="fas <?php echo htmlspecialchars($perangkat_detail_photo['icon']); ?>"></i></div>
                                    <?php endif; ?>
                                </div>

                                <div class="entity-profile-main">
                                    <h2><?php echo htmlspecialchars((string)($perangkat_detail['name'] ?? '-')); ?></h2>
                                    <div class="entity-profile-meta">
                                        <span class="entity-meta-pill"><i class="fas fa-id-card"></i> <?php echo htmlspecialchars(maskPerangkatKtp($perangkat_detail['no_ktp'] ?? '')); ?></span>
                                        <span class="entity-meta-pill outline"><i class="fas fa-user-clock"></i> <?php echo htmlspecialchars(calculatePerangkatAge($perangkat_detail['age'] ?? null)); ?></span>
                                        <span class="entity-meta-pill outline"><i class="fas fa-circle-check"></i> <?php echo ((int)($perangkat_detail['is_active'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif'; ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="entity-profile-actions">
                                <a href="perangkat.php<?php echo $perangkat_back_query !== '' ? '?' . $perangkat_back_query : ''; ?>" class="btn-filter-reset">
                                    <i class="fas fa-arrow-left"></i> Kembali ke daftar
                                </a>
                                <div class="player-share-section" id="perangkatSharePanel" data-share-url="<?php echo htmlspecialchars($perangkat_share_url, ENT_QUOTES, 'UTF-8'); ?>" data-share-text="<?php echo htmlspecialchars($perangkat_share_text, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="button" class="player-share-toggle" id="perangkatShareToggle" aria-expanded="false" aria-controls="perangkatShareMenu">
                                        <span><i class="fas fa-share-alt"></i> Share Profil</span>
                                        <i class="fas fa-chevron-down player-share-toggle-icon" aria-hidden="true"></i>
                                    </button>
                                    <div class="player-share-menu" id="perangkatShareMenu">
                                        <div class="player-share-buttons">
                                            <button type="button" class="player-share-btn native" id="perangkatShareNativeBtn" aria-label="Bagikan profil perangkat pertandingan">
                                                <i class="fas fa-share-nodes"></i> <span>Share</span>
                                            </button>
                                            <a href="<?php echo htmlspecialchars($perangkat_share_whatsapp_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="player-share-btn whatsapp" aria-label="Bagikan ke WhatsApp">
                                                <i class="fab fa-whatsapp"></i> <span>WhatsApp</span>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($perangkat_share_facebook_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="player-share-btn facebook" aria-label="Bagikan ke Facebook">
                                                <i class="fab fa-facebook-f"></i> <span>Facebook</span>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($perangkat_share_telegram_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="player-share-btn telegram" aria-label="Bagikan ke Telegram">
                                                <i class="fab fa-telegram-plane"></i> <span>Telegram</span>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($perangkat_share_x_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="player-share-btn twitter" aria-label="Bagikan ke X">
                                                <svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="player-share-x-icon" aria-hidden="true" focusable="false"><path d="M14.234 10.162 22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z"/></svg>
                                                <span>X</span>
                                            </a>
                                            <button type="button" class="player-share-btn copy" id="perangkatShareCopyBtn" aria-label="Salin tautan profil perangkat pertandingan">
                                                <i class="far fa-copy"></i> <span>Salin Link</span>
                                            </button>
                                        </div>
                                        <div class="player-share-feedback" id="perangkatShareFeedback" aria-live="polite"></div>
                                    </div>
                                </div>
                                <?php if ((int)($perangkat_detail['certificate_count'] ?? 0) > 0): ?>
                                    <button
                                        type="button"
                                        class="entity-profile-btn entity-profile-btn-accent"
                                        onclick="loadLicenses(<?php echo (int)$perangkat_detail['id']; ?>, '<?php echo htmlspecialchars(addslashes((string)($perangkat_detail['name'] ?? ''))); ?>')"
                                    >
                                        <i class="fas fa-certificate"></i> Lihat Lisensi
                                    </button>
                                <?php endif; ?>
                                <button
                                    type="button"
                                    class="entity-profile-btn btn-perangkat-history"
                                    data-perangkat-id="<?php echo (int)$perangkat_detail['id']; ?>"
                                    data-perangkat-name="<?php echo htmlspecialchars((string)($perangkat_detail['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <i class="fas fa-chart-line"></i> Riwayat Match
                                </button>
                            </div>
                        </div>

                        <div class="entity-profile-grid">
                            <div class="entity-detail-item">
                                <span class="entity-detail-label">No. KTP</span>
                                <span class="entity-detail-value"><?php echo htmlspecialchars(maskPerangkatKtp($perangkat_detail['no_ktp'] ?? '')); ?></span>
                            </div>
                            <div class="entity-detail-item">
                                <span class="entity-detail-label">Email</span>
                                <span class="entity-detail-value"><?php echo !empty($perangkat_detail['email']) ? htmlspecialchars((string)$perangkat_detail['email']) : '-'; ?></span>
                            </div>
                            <div class="entity-detail-item">
                                <span class="entity-detail-label">Telepon</span>
                                <span class="entity-detail-value"><?php echo !empty($perangkat_detail['phone']) ? htmlspecialchars((string)$perangkat_detail['phone']) : '-'; ?></span>
                            </div>
                            <div class="entity-detail-item">
                                <span class="entity-detail-label">Alamat</span>
                                <span class="entity-detail-value"><?php echo htmlspecialchars($perangkat_detail_address); ?></span>
                            </div>
                            <div class="entity-detail-item">
                                <span class="entity-detail-label">Lisensi</span>
                                <span class="entity-detail-value"><?php echo (int)($perangkat_detail['certificate_count'] ?? 0); ?> Dokumen</span>
                            </div>
                            <div class="entity-detail-item">
                                <span class="entity-detail-label">Event</span>
                                <span class="entity-detail-value"><?php echo (int)($perangkat_detail['event_count'] ?? 0); ?> Event</span>
                                <?php if (!empty($perangkat_detail_event_names)): ?>
                                    <span class="entity-detail-sub"><?php echo htmlspecialchars(implode(', ', $perangkat_detail_event_names)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="entity-detail-item">
                                <span class="entity-detail-label">Match</span>
                                <span class="entity-detail-value"><?php echo (int)($perangkat_detail['match_count'] ?? 0); ?> Match</span>
                            </div>
                            <div class="entity-detail-item">
                                <span class="entity-detail-label">Status</span>
                                <span class="entity-detail-value"><?php echo ((int)($perangkat_detail['is_active'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif'; ?></span>
                            </div>
                            <div class="entity-detail-item">
                                <span class="entity-detail-label">Dibuat Pada</span>
                                <span class="entity-detail-value"><?php echo !empty($perangkat_detail['created_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime((string)$perangkat_detail['created_at']))) : '-'; ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <div class="filter-card staff-filter-card">
                <form action="" method="GET" class="filter-row">
                    <div class="filter-group">
                        <label for="search">Pencarian Perangkat</label>
                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Cari nama perangkat...">
                    </div>
                    <div class="filter-actions-new">
                        <button type="submit" class="btn-filter-apply">
                            <i class="fas fa-search"></i> Cari
                        </button>
                        <a href="perangkat.php" class="btn-filter-reset">
                            <i class="fas fa-redo"></i> Atur Ulang
                        </a>
                    </div>
                    <?php if ($page > 1): ?>
                        <input type="hidden" name="page" value="<?php echo $page; ?>">
                    <?php endif; ?>
                </form>
                <div class="filter-summary">
                    <div class="summary-item">
                        <span class="summary-label">Menampilkan</span>
                        <span class="summary-value"><?php echo min($offset + 1, $total_records); ?> - <?php echo min($offset + $limit, $total_records); ?> dari <?php echo number_format($total_records); ?> data</span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Total Perangkat</span>
                        <span class="summary-value"><?php echo number_format($total_records); ?></span>
                    </div>
                </div>
            </div>

            <div class="table-container-new">
                <table class="staff-table-new">
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th class="col-photo">Foto</th>
                            <th style="text-align:center;">Nama Perangkat</th>
                            <th>No. KTP</th>
                            <th class="col-age">Usia</th>
                            <th class="col-certificate">Lisensi</th>
                            <th class="col-matches">Match</th>
                            <th class="col-events">Event</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($perangkatRows)): ?>
                            <tr>
                                <td colspan="10" class="no-data">
                                    <i class="fas fa-user-slash"></i>
                                    <p>Tidak ada perangkat ditemukan</p>
                                    <?php if (!empty($search)): ?>
                                        <p class="no-data-keyword">
                                            Kata kunci: "<?php echo htmlspecialchars($search); ?>"
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $no = $offset + 1;
                            foreach ($perangkatRows as $p):
                                $perangkat_photo = getFileUrl($p['photo'], 'perangkat', 'fa-user-tie');
                            ?>
                            <tr>
                                <td class="col-no" data-label="No"><?php echo $no++; ?></td>
                                
                                <td class="col-photo" data-label="Foto">
                                    <div class="staff-photo-wrapper">
                                        <?php if ($perangkat_photo['found']): ?>
                                            <img src="<?php echo $perangkat_photo['url']; ?>" 
                                                 class="staff-img-sm" 
                                                 alt="<?php echo htmlspecialchars($p['name'] ?? ''); ?>"
                                                 onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <?php endif; ?>
                                        
                                        <div class="photo-placeholder" style="<?php echo $perangkat_photo['found'] ? 'display: none;' : ''; ?>">
                                            <i class="fas <?php echo $perangkat_photo['icon']; ?>"></i>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="col-name" data-label="Nama">
                                    <a href="perangkat.php?id=<?php echo (int)$p['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>#perangkat-detail" class="staff-name staff-name-link">
                                        <?php echo htmlspecialchars($p['name'] ?? ''); ?>
                                    </a>
                                    <div class="staff-contact">
                                        <?php if (!empty($p['email'])): ?>
                                            <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($p['email'] ?? ''); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($p['phone'])): ?>
                                            <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($p['phone'] ?? ''); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td data-label="No. KTP">
                                    <?php
                                    $noKtp = trim((string)($p['no_ktp'] ?? ''));
                                    if ($noKtp !== '') {
                                        if (strlen($noKtp) > 7) {
                                            echo htmlspecialchars(substr($noKtp, 0, 3) . str_repeat('*', max(strlen($noKtp) - 7, 1)) . substr($noKtp, -4));
                                        } else {
                                            echo htmlspecialchars(substr($noKtp, 0, 1) . str_repeat('*', max(strlen($noKtp) - 2, 1)) . substr($noKtp, -1));
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                
                                <td class="col-age" data-label="Usia"><?php echo htmlspecialchars(calculatePerangkatAge($p['age'] ?? null)); ?></td>
                                
                                <td class="col-certificate" data-label="Lisensi">
                                    <?php if ((int)($p['certificate_count'] ?? 0) > 0): ?>
                                        <div class="cert-count" 
                                             onclick="loadLicenses(<?php echo (int)$p['id']; ?>, '<?php echo htmlspecialchars(addslashes($p['name'] ?? '')); ?>')">
                                            <i class="fas fa-certificate"></i>
                                            <span><?php echo (int)$p['certificate_count']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="muted">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="col-matches" data-label="Match">
                                    <?php $matchCount = (int)($p['match_count'] ?? 0); ?>
                                    <span class="event-match-count <?php echo $matchCount === 0 ? 'zero' : ''; ?>">
                                        <i class="fas fa-futbol"></i><?php echo $matchCount; ?>
                                    </span>
                                </td>

                                <td class="col-events" data-label="Event">
                                    <?php
                                    $eventCount = (int)($p['event_count'] ?? 0);
                                    $eventNamesList = parsePerangkatEventNamesPayload($p['event_names'] ?? '');
                                    $event_popover_id = 'perangkat-event-popover-' . (int)($p['id'] ?? 0);
                                    ?>
                                    <span class="event-count-badge-wrap">
                                        <span
                                            class="event-match-count event <?php echo $eventCount === 0 ? 'zero' : 'event-popover-trigger'; ?>"
                                            <?php if ($eventCount > 0): ?>
                                            role="button"
                                            tabindex="0"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                            aria-controls="<?php echo htmlspecialchars($event_popover_id); ?>"
                                            <?php endif; ?>>
                                            <i class="fas fa-calendar-check"></i><?php echo $eventCount; ?>
                                        </span>
                                        <?php if ($eventCount > 0): ?>
                                        <div class="event-popover" id="<?php echo htmlspecialchars($event_popover_id); ?>" role="tooltip">
                                            <div class="event-popover-header">Total <?php echo $eventCount; ?> event</div>
                                            <ul class="event-popover-list">
                                                <?php foreach ($eventNamesList as $eventNameItem): ?>
                                                <li class="event-popover-item">
                                                    <span class="event-popover-name"><?php echo htmlspecialchars($eventNameItem); ?></span>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <?php endif; ?>
                                    </span>
                                </td>

                                <td data-label="Status">
                                    <?php if ((int)($p['is_active'] ?? 0) === 1): ?>
                                        <span class="position-badge manager-badge">Aktif</span>
                                    <?php else: ?>
                                        <span class="position-badge medic-badge">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="col-created" data-label="Dibuat">
                                    <?php if (!empty($p['created_at'])): ?>
                                        <?php echo date('d M Y', strtotime($p['created_at'])); ?><br>
                                        <small><?php echo date('H:i', strtotime($p['created_at'])); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-bar">
                <div class="pagination-info">
                    <div class="info-text">
                        Menampilkan <?php echo min($offset + 1, $total_records); ?> sampai <?php echo min($offset + $limit, $total_records); ?> dari <?php echo number_format($total_records); ?> data
                    </div>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="pagination-controls">
                    <!-- Sebelumnya -->
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

                    <!-- Berikutnya -->
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
    </div>
</div>

<div class="match-history-modal" id="matchHistoryModal">
    <div class="match-history-content">
        <div class="match-history-header">
            <div>
                <h3><i class="fas fa-chart-line"></i> <span id="historyPerangkatName">-</span></h3>
                <div class="match-history-meta" id="historyPerangkatMeta">Memuat data...</div>
            </div>
            <button class="match-history-close" id="historyPerangkatCloseBtn" type="button">&times;</button>
        </div>
        <div class="match-history-body" id="historyPerangkatBody">
            <div class="match-history-loading"><i class="fas fa-spinner"></i> Memuat riwayat pertandingan...</div>
        </div>
    </div>
</div>

<script>
const SITE_URL = '<?php echo SITE_URL; ?>';


const perangkatSharePanel = document.getElementById('perangkatSharePanel');
const perangkatShareToggle = document.getElementById('perangkatShareToggle');
const perangkatShareMenu = document.getElementById('perangkatShareMenu');
const perangkatShareCopyBtn = document.getElementById('perangkatShareCopyBtn');
const perangkatShareNativeBtn = document.getElementById('perangkatShareNativeBtn');
const perangkatShareFeedback = document.getElementById('perangkatShareFeedback');

if (perangkatSharePanel) {
    const shareUrl = perangkatSharePanel.dataset.shareUrl || window.location.href;
    const shareText = perangkatSharePanel.dataset.shareText || 'Lihat profil perangkat pertandingan ini di ALVETRIX';

    const setShareFeedback = (message, isError = false) => {
        if (!perangkatShareFeedback) return;
        perangkatShareFeedback.textContent = message;
        perangkatShareFeedback.classList.toggle('error', isError);
    };
    const openShareMenu = () => {
        perangkatSharePanel.classList.add('open');
        if (perangkatShareToggle) {
            perangkatShareToggle.setAttribute('aria-expanded', 'true');
        }
    };
    const closeShareMenu = () => {
        perangkatSharePanel.classList.remove('open');
        if (perangkatShareToggle) {
            perangkatShareToggle.setAttribute('aria-expanded', 'false');
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

    if (perangkatShareToggle && perangkatShareMenu) {
        perangkatShareToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const isOpen = perangkatSharePanel.classList.contains('open');
            if (isOpen) {
                closeShareMenu();
            } else {
                openShareMenu();
            }
        });

        perangkatShareMenu.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        document.addEventListener('click', (event) => {
            if (!perangkatSharePanel.contains(event.target)) {
                closeShareMenu();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeShareMenu();
            }
        });
    }

    if (perangkatShareCopyBtn) {
        perangkatShareCopyBtn.addEventListener('click', () => {
            const handleCopySuccess = () => {
                perangkatShareCopyBtn.classList.add('copied');
                setShareFeedback('Tautan profil berhasil disalin.');
                setTimeout(() => {
                    perangkatShareCopyBtn.classList.remove('copied');
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

    if (perangkatShareNativeBtn && navigator.share) {
        perangkatShareNativeBtn.style.display = 'inline-flex';
        perangkatShareNativeBtn.addEventListener('click', () => {
            navigator.share({
                title: 'Profil Perangkat Pertandingan ALVETRIX',
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

const matchHistoryModal = document.getElementById('matchHistoryModal');
const historyPerangkatBody = document.getElementById('historyPerangkatBody');
const historyPerangkatName = document.getElementById('historyPerangkatName');
const historyPerangkatMeta = document.getElementById('historyPerangkatMeta');
const historyPerangkatCloseBtn = document.getElementById('historyPerangkatCloseBtn');

const escapeHtmlText = (value) => {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
};

function closeMatchHistoryModal() {
    if (matchHistoryModal) {
        matchHistoryModal.classList.remove('open');
    }
}

document.querySelectorAll('.btn-perangkat-history').forEach((btn) => {
    btn.addEventListener('click', function () {
        const perangkatId = this.dataset.perangkatId;
        const perangkatName = this.dataset.perangkatName || '-';

        historyPerangkatName.textContent = perangkatName;
        historyPerangkatMeta.textContent = 'Memuat ringkasan event dan match...';
        historyPerangkatBody.innerHTML = '<div class="match-history-loading"><i class="fas fa-spinner"></i> Memuat riwayat pertandingan...</div>';
        matchHistoryModal.classList.add('open');

        fetch(`get_perangkat_match_history.php?perangkat_id=${encodeURIComponent(perangkatId)}`)
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) {
                    historyPerangkatBody.innerHTML = `<div class="match-history-empty"><i class="fas fa-exclamation-circle"></i><p>${escapeHtmlText(data.message || 'Terjadi kesalahan saat memuat data.')}</p></div>`;
                    return;
                }

                const eventTotal = Number(data.event_total || 0);
                const matchTotal = Number(data.total || 0);
                historyPerangkatMeta.textContent = `Event: ${eventTotal} | Match: ${matchTotal}`;

                if (!Array.isArray(data.matches) || data.matches.length === 0) {
                    historyPerangkatBody.innerHTML = '<div class="match-history-empty"><i class="fas fa-futbol"></i><p>Belum ada match yang tercatat untuk perangkat ini.</p></div>';
                    return;
                }

                let rows = '';
                data.matches.forEach((m, idx) => {
                    const safeStatus = String(m.status || 'default').toLowerCase();
                    const statusClass = ['completed', 'accepted', 'pending'].includes(safeStatus) ? safeStatus : 'default';
                    const scoreText = (m.challenger_score !== null && m.opponent_score !== null)
                        ? `${m.challenger_score} - ${m.opponent_score}`
                        : '-';

                    rows += `
                        <tr>
                            <td>${idx + 1}</td>
                            <td>${escapeHtmlText(m.event_name || '-')}</td>
                            <td>${escapeHtmlText(m.sport_type || '-')}</td>
                            <td>#${escapeHtmlText(String(m.challenge_id || ''))}<br><small style="color:#94a3b8">${escapeHtmlText(m.challenge_code || '-')}</small></td>
                            <td>${escapeHtmlText(m.challenger_name || '-')} <span style="color:#94a3b8">vs</span> ${escapeHtmlText(m.opponent_name || '-')}</td>
                            <td>${escapeHtmlText(m.challenge_date_fmt || '-')}</td>
                            <td><span class="history-status-pill ${statusClass}">${escapeHtmlText(m.status || '-')}</span></td>
                            <td>${escapeHtmlText(scoreText)}</td>
                        </tr>
                    `;
                });

                historyPerangkatBody.innerHTML = `
                    <table class="match-history-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Event</th>
                                <th>Kategori</th>
                                <th>Match</th>
                                <th>Pertandingan</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Skor</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                `;
            })
            .catch(() => {
                historyPerangkatBody.innerHTML = '<div class="match-history-empty"><i class="fas fa-exclamation-circle"></i><p>Gagal memuat data riwayat. Periksa koneksi server.</p></div>';
            });
    });
});

if (historyPerangkatCloseBtn) {
    historyPerangkatCloseBtn.addEventListener('click', closeMatchHistoryModal);
}
if (matchHistoryModal) {
    matchHistoryModal.addEventListener('click', function (e) {
        if (e.target === this) {
            closeMatchHistoryModal();
        }
    });
}

// Function to load perangkat licenses
function loadLicenses(perangkatId, perangkatName) {
    console.log('Loading licenses for:', perangkatName, 'ID:', perangkatId);
    
    const modal = document.getElementById('certificateModal');
    const modalTitle = document.getElementById('modalPerangkatName');
    const content = document.getElementById('certificateContent');
    
    modalTitle.textContent = `Lisensi: ${perangkatName}`;
    content.innerHTML = `
        <div class="loading-container">
            <div class="spinner"></div>
            <p>Memuat data lisensi...</p>
        </div>
    `;
    
    modal.style.display = 'flex';
    
    // URL yang benar untuk AJAX request
    const ajaxPath = 'includes/ajax_get_perangkat_licenses.php';
    const url = `${ajaxPath}?perangkat_id=${perangkatId}`;
    
    console.log('Fetching certificates from:', url);
    
    fetch(url)
        .then(response => {
            console.log('Response status:', response.status);
            
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Certificates data:', data);
            
            if (data.success && data.certificates && data.certificates.length > 0) {
                displayCertificates(data.certificates);
            } else {
                content.innerHTML = `
                    <div class="no-certificates">
                        <i class="fas fa-file-alt"></i>
                        <h3>Tidak Ada Lisensi</h3>
                        <p>Perangkat ini belum memiliki lisensi.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading certificates:', error);
            
            // Debug: test with direct URL
            const testUrl = ajaxPath + '?perangkat_id=' + perangkatId;
            content.innerHTML = `
                <div class="no-certificates">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Gagal Memuat Data</h3>
                    <p>${error.message}</p>
                    <div style="margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                        <small>Debug info:</small><br>
                        <small>URL: ${testUrl}</small><br>
                        <small>Perangkat ID: ${perangkatId}</small>
                    </div>
                    <p style="font-size: 12px; color: #666;">
                        Pastikan file <code>${ajaxPath}</code> ada di server.
                    </p>
                    <button onclick="loadLicenses(${perangkatId}, '${perangkatName}')" 
                            style="margin-top: 15px; padding: 8px 16px; background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-redo"></i> Coba Lagi
                    </button>
                </div>
            `;
        });
    
    function displayCertificates(certificates) {
        let html = `
            <div style="margin-bottom: 20px;">
                <p style="color: #4a5568; font-size: 14px;">
                    <i class="fas fa-info-circle"></i> Ditemukan ${certificates.length} lisensi
                </p>
            </div>
            <div class="certificates-grid">
        `;
        
        certificates.forEach((cert, index) => {
            const fileName = cert.certificate_file ? 
                cert.certificate_file.split('/').pop() : 'Tidak ada file';
            const fileExt = fileName.split('.').pop().toLowerCase();
            const isImage = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(fileExt);
            const isPDF = fileExt === 'pdf';
            
            // Build file URL - gunakan SITE_URL yang sudah didefinisikan
            const fileUrl = cert.certificate_file ? 
                `${SITE_URL}/uploads/perangkat/licenses/${fileName}` : '#';
            
            const formattedDate = cert.issue_date ? 
                new Date(cert.issue_date).toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }) : 'Tidak ada tanggal';
            
            // Escape quotes untuk onclick
            const safeCertName = (cert.certificate_name || 'Lisensi ' + (index + 1)).replace(/'/g, "\\'");
            const safeFileUrl = fileUrl.replace(/'/g, "\\'");
            
            html += `
            <div class="certificate-card">
                <div class="certificate-header">
                    <h3 class="certificate-title">${cert.certificate_name || 'Lisensi ' + (index + 1)}</h3>
                    <div class="certificate-meta">
                        ${cert.issuing_authority ? `
                            <div><i class="fas fa-building"></i> ${cert.issuing_authority}</div>
                        ` : ''}
                        <div><i class="fas fa-calendar"></i> ${formattedDate}</div>
                        <div><i class="fas fa-file"></i> ${fileName}</div>
                    </div>
                </div>
                
                <div class="certificate-preview">
            `;
            
            if (cert.certificate_file && fileName !== 'Tidak ada file') {
                if (isImage) {
                    html += `
                        <img src="${fileUrl}" 
                             alt="${safeCertName}" 
                             class="certificate-image"
                             onclick="viewImage('${safeFileUrl}', '${safeCertName}')">
                        <div class="file-actions" style="margin-top: 15px;">
                            <a href="${fileUrl}" target="_blank" class="btn-view">
                                <i class="fas fa-external-link-alt"></i> Lihat
                            </a>
                            <a href="${fileUrl}" download class="btn-download">
                                <i class="fas fa-download"></i> Unduh
                            </a>
                        </div>
                    `;
                } else if (isPDF) {
                    html += `
                        <div class="file-preview">
                            <div class="file-icon">
                                <i class="fas fa-file-pdf" style="color: #dc2626;"></i>
                            </div>
                            <div class="file-name">${fileName}</div>
                            <div class="file-actions">
                                <a href="${fileUrl}" target="_blank" class="btn-view">
                                    <i class="fas fa-external-link-alt"></i> Buka
                                </a>
                                <a href="${fileUrl}" download class="btn-download">
                                    <i class="fas fa-download"></i> Unduh
                                </a>
                            </div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="file-preview">
                            <div class="file-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="file-name">${fileName}</div>
                            <div class="file-actions">
                                <a href="${fileUrl}" target="_blank" class="btn-view">
                                    <i class="fas fa-external-link-alt"></i> Lihat
                                </a>
                                <a href="${fileUrl}" download class="btn-download">
                                    <i class="fas fa-download"></i> Unduh
                                </a>
                            </div>
                        </div>
                    `;
                }
            } else {
                html += `
                    <div class="file-preview">
                        <div class="file-icon">
                            <i class="fas fa-times-circle" style="color: #a0aec0;"></i>
                        </div>
                        <div class="file-name">Tidak ada file terlampir</div>
                    </div>
                `;
            }
            
            html += `
                </div>
            </div>
            `;
        });
        
        html += `</div>`;
        content.innerHTML = html;
    }
}

// Function to view image in full screen
function viewImage(imageUrl, title) {
    const viewer = document.getElementById('imageViewer');
    const image = document.getElementById('fullSizeImage');
    const imageTitle = document.getElementById('imageTitle');
    
    image.src = imageUrl;
    imageTitle.textContent = title;
    viewer.style.display = 'flex';
    
    // Prevent body scrolling
    document.body.style.overflow = 'hidden';
}

// Function to close image viewer
function closeImageViewer() {
    const viewer = document.getElementById('imageViewer');
    viewer.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Function to close certificate modal
function closeCertificateModal() {
    document.getElementById('certificateModal').style.display = 'none';
}

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCertificateModal();
        closeImageViewer();
        closeMatchHistoryModal();
    }
});

// Close modal when clicking outside
document.getElementById('certificateModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCertificateModal();
    }
});

document.getElementById('imageViewer').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageViewer();
    }
});

// Image error handler for certificate images
document.addEventListener('DOMContentLoaded', function() {
    // Handler untuk gambar sertifikat (jika ada)
    const certImages = document.querySelectorAll('.certificate-image');
    certImages.forEach(img => {
        img.addEventListener('error', function() {
            console.error('Certificate image failed to load:', this.src);
            this.style.display = 'none';
            const parent = this.parentElement;
            if (parent) {
                const fileUrl = this.src;
                const fileName = fileUrl.split('/').pop();
                parent.innerHTML = `
                    <div class="file-preview">
                        <div class="file-icon">
                            <i class="fas fa-file-image" style="color: #a0aec0;"></i>
                        </div>
                        <div class="file-name">${fileName}</div>
                        <div class="file-actions">
                            <a href="${fileUrl}" target="_blank" class="btn-view">
                                <i class="fas fa-external-link-alt"></i> Buka Link
                            </a>
                        </div>
                    </div>
                `;
            }
        });
    });
});
</script>

<script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo getAssetVersion('/js/script.js'); ?>"></script>
<script>
// Tap-to-expand popover for event count badges (mobile-friendly)
(function () {
    function syncAria(wrap, expanded) {
        var trigger = wrap.querySelector('.event-popover-trigger');
        if (trigger) {
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    }

    function closeWrap(wrap) {
        if (!wrap) return;
        wrap.classList.remove('pop-open');
        syncAria(wrap, false);
    }

    function closeAll(exceptWrap) {
        document.querySelectorAll('.event-count-badge-wrap.pop-open').forEach(function (el) {
            if (el !== exceptWrap) {
                closeWrap(el);
            }
        });
    }

    function toggleWrap(wrap) {
        if (!wrap) return;
        var shouldOpen = !wrap.classList.contains('pop-open');
        closeAll(wrap);
        if (shouldOpen) {
            wrap.classList.add('pop-open');
            syncAria(wrap, true);
        } else {
            closeWrap(wrap);
        }
    }

    document.querySelectorAll('.event-popover-trigger').forEach(function (trigger) {
        trigger.setAttribute('aria-expanded', 'false');
    });

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('.event-popover-trigger');
        if (trigger) {
            toggleWrap(trigger.closest('.event-count-badge-wrap'));
            return;
        }

        if (!e.target.closest('.event-count-badge-wrap')) {
            closeAll(null);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAll(null);
            return;
        }

        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }

        var trigger = e.target.closest('.event-popover-trigger');
        if (!trigger) {
            return;
        }
        e.preventDefault();
        toggleWrap(trigger.closest('.event-count-badge-wrap'));
    });
})();
</script>

</body>
</html>

