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
$staff_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Database connection
$conn = $db->getConnection();
$has_challenge_event_id = false;
$check_event_id_col = $conn->query("SHOW COLUMNS FROM challenges LIKE 'event_id'");
if ($check_event_id_col && $check_event_id_col->num_rows > 0) {
    $has_challenge_event_id = true;
}

$has_match_staff_assignments_table = false;
$check_staff_assignment_table = $conn->query("SHOW TABLES LIKE 'match_staff_assignments'");
if ($check_staff_assignment_table && $check_staff_assignment_table->num_rows > 0) {
    $has_match_staff_assignments_table = true;
}

// Query for Total Records (for pagination)
$count_query = "SELECT COUNT(*) as total FROM team_staff ts WHERE ts.is_active = 1";
if (!empty($search)) {
    $count_query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ?)";
}

$stmt_count = $conn->prepare($count_query);
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt_count->bind_param("sss", $search_param, $search_param, $search_param);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
if ($count_result) {
    $total_records = $count_result->fetch_assoc()['total'];
} else {
    $total_records = 0;
}
$total_pages = ceil($total_records / $limit);

// Query for Staff Data with Team Info and Counts
$query = "SELECT 
    ts.*, 
    t.name as team_name, 
    t.logo as team_logo,
    t.alias as team_alias,
    (SELECT COUNT(*) FROM staff_certificates sc WHERE sc.staff_id = ts.id) as certificate_count
    FROM team_staff ts 
    LEFT JOIN teams t ON ts.team_id = t.id 
    WHERE ts.is_active = 1";

if (!empty($search)) {
    $query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ?)";
}
$query .= " ORDER BY ts.created_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!empty($search)) {
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $limit, $offset);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$staffs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$staff_match_counts = [];
$staff_event_stats = [];
if ($has_match_staff_assignments_table && !empty($staffs)) {
    $staff_ids = array_map('intval', array_column($staffs, 'id'));
    if (!empty($staff_ids)) {
        $placeholders = implode(',', array_fill(0, count($staff_ids), '?'));

        $sql_match_counts = "
            SELECT msa.staff_id, COUNT(DISTINCT msa.match_id) AS total_match
            FROM match_staff_assignments msa
            INNER JOIN challenges c ON msa.match_id = c.id
            WHERE msa.staff_id IN ($placeholders)
            GROUP BY msa.staff_id
        ";
        $stmt_match_counts = $conn->prepare($sql_match_counts);
        if ($stmt_match_counts) {
            $types = str_repeat('i', count($staff_ids));
            $stmt_match_counts->bind_param($types, ...$staff_ids);
            $stmt_match_counts->execute();
            $result_match_counts = $stmt_match_counts->get_result();
            while ($row = $result_match_counts->fetch_assoc()) {
                $staff_match_counts[(int)$row['staff_id']] = (int)$row['total_match'];
            }
            $stmt_match_counts->close();
        }

        if ($has_challenge_event_id) {
            $sql_event_counts = "
                SELECT
                    msa.staff_id,
                    TRIM(COALESCE(NULLIF(e.name, ''), NULLIF(c.sport_type, ''))) AS event_name,
                    COUNT(DISTINCT msa.match_id) AS total_match_in_event
                FROM match_staff_assignments msa
                INNER JOIN challenges c ON msa.match_id = c.id
                LEFT JOIN events e ON c.event_id = e.id
                WHERE msa.staff_id IN ($placeholders)
                GROUP BY msa.staff_id, event_name
                HAVING event_name IS NOT NULL AND event_name <> ''
            ";
        } else {
            $sql_event_counts = "
                SELECT
                    msa.staff_id,
                    TRIM(c.sport_type) AS event_name,
                    COUNT(DISTINCT msa.match_id) AS total_match_in_event
                FROM match_staff_assignments msa
                INNER JOIN challenges c ON msa.match_id = c.id
                WHERE msa.staff_id IN ($placeholders)
                GROUP BY msa.staff_id, c.sport_type
                HAVING event_name IS NOT NULL AND event_name <> ''
            ";
        }

        $stmt_event_counts = $conn->prepare($sql_event_counts);
        if ($stmt_event_counts) {
            $types = str_repeat('i', count($staff_ids));
            $stmt_event_counts->bind_param($types, ...$staff_ids);
            $stmt_event_counts->execute();
            $result_event_counts = $stmt_event_counts->get_result();
            while ($row = $result_event_counts->fetch_assoc()) {
                $sid = (int)$row['staff_id'];
                $event_name = trim((string)($row['event_name'] ?? ''));
                if ($event_name === '') {
                    continue;
                }
                if (!isset($staff_event_stats[$sid])) {
                    $staff_event_stats[$sid] = [];
                }
                $staff_event_stats[$sid][$event_name] = (int)$row['total_match_in_event'];
            }
            $stmt_event_counts->close();
        }
    }
}

$staff_detail = null;
$staff_detail_match_count = 0;
$staff_detail_event_stats = [];
$staff_detail_event_count = 0;
$staff_detail_event_info = [];
$staff_detail_address = '-';
$staff_share_url = '';
$staff_share_text = '';
$staff_share_whatsapp_url = '#';
$staff_share_facebook_url = '#';
$staff_share_telegram_url = '#';
$staff_share_x_url = '#';
if ($staff_id > 0) {
    $detail_query = "
        SELECT
            ts.*,
            t.name AS team_name,
            t.logo AS team_logo,
            t.alias AS team_alias,
            (SELECT COUNT(*) FROM staff_certificates sc WHERE sc.staff_id = ts.id) AS certificate_count
        FROM team_staff ts
        LEFT JOIN teams t ON ts.team_id = t.id
        WHERE ts.id = ?
        LIMIT 1
    ";
    $detail_stmt = $conn->prepare($detail_query);
    if ($detail_stmt) {
        $detail_stmt->bind_param("i", $staff_id);
        $detail_stmt->execute();
        $staff_detail = $detail_stmt->get_result()->fetch_assoc();
        $detail_stmt->close();
    }

    if ($staff_detail && $has_match_staff_assignments_table) {
        $detail_match_query = "
            SELECT COUNT(DISTINCT msa.match_id) AS total_match
            FROM match_staff_assignments msa
            INNER JOIN challenges c ON msa.match_id = c.id
            WHERE msa.staff_id = ?
        ";
        $detail_match_stmt = $conn->prepare($detail_match_query);
        if ($detail_match_stmt) {
            $detail_match_stmt->bind_param("i", $staff_id);
            $detail_match_stmt->execute();
            $detail_match_result = $detail_match_stmt->get_result()->fetch_assoc();
            $staff_detail_match_count = (int)($detail_match_result['total_match'] ?? 0);
            $detail_match_stmt->close();
        }

        if ($has_challenge_event_id) {
            $detail_event_query = "
                SELECT
                    TRIM(COALESCE(NULLIF(e.name, ''), NULLIF(c.sport_type, ''))) AS event_name,
                    COUNT(DISTINCT msa.match_id) AS total_match_in_event
                FROM match_staff_assignments msa
                INNER JOIN challenges c ON msa.match_id = c.id
                LEFT JOIN events e ON c.event_id = e.id
                WHERE msa.staff_id = ?
                GROUP BY event_name
                HAVING event_name IS NOT NULL AND event_name <> ''
            ";
        } else {
            $detail_event_query = "
                SELECT
                    TRIM(c.sport_type) AS event_name,
                    COUNT(DISTINCT msa.match_id) AS total_match_in_event
                FROM match_staff_assignments msa
                INNER JOIN challenges c ON msa.match_id = c.id
                WHERE msa.staff_id = ?
                GROUP BY event_name
                HAVING event_name IS NOT NULL AND event_name <> ''
            ";
        }

        $detail_event_stmt = $conn->prepare($detail_event_query);
        if ($detail_event_stmt) {
            $detail_event_stmt->bind_param("i", $staff_id);
            $detail_event_stmt->execute();
            $detail_event_result = $detail_event_stmt->get_result();
            while ($event_row = $detail_event_result->fetch_assoc()) {
                $event_name = trim((string)($event_row['event_name'] ?? ''));
                if ($event_name === '') {
                    continue;
                }
                $staff_detail_event_stats[$event_name] = (int)($event_row['total_match_in_event'] ?? 0);
            }
            $detail_event_stmt->close();
        }
    }

    if ($staff_detail) {
        $staff_detail_event_count = count($staff_detail_event_stats);
        foreach ($staff_detail_event_stats as $event_name => $event_match_total) {
            $staff_detail_event_info[] = $event_name . ' (' . $event_match_total . ' match)';
        }

        $address_parts = [];
        foreach (['address', 'city', 'province', 'postal_code', 'country'] as $field) {
            $value = trim((string)($staff_detail[$field] ?? ''));
            if ($value !== '') {
                $address_parts[] = $value;
            }
        }
        if (!empty($address_parts)) {
            $staff_detail_address = implode(', ', $address_parts);
        }

        $share_staff_name = trim((string)($staff_detail['name'] ?? ''));
        $staff_share_url = SITE_URL . '/staff.php?' . http_build_query([
            'id' => (int)($staff_detail['id'] ?? 0),
            'page' => max(1, (int)$page),
            'search' => (string)$search
        ]) . '#staff-detail';
        $staff_share_text = 'Lihat profil staff ' . ($share_staff_name !== '' ? $share_staff_name : 'ini') . ' di ALVETRIX';
        $staff_share_combined_text = $staff_share_text . ' ' . $staff_share_url;
        $staff_share_whatsapp_url = 'https://wa.me/?text=' . rawurlencode($staff_share_combined_text);
        $staff_share_facebook_url = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($staff_share_url);
        $staff_share_telegram_url = 'https://t.me/share/url?url=' . rawurlencode($staff_share_url) . '&text=' . rawurlencode($staff_share_text);
        $staff_share_x_url = 'https://twitter.com/intent/tweet?text=' . rawurlencode($staff_share_text) . '&url=' . rawurlencode($staff_share_url);
    }
}

// Helper Functions
function calculateStaffAge($birth_date)
{
    if (empty($birth_date) || $birth_date == '0000-00-00') return '-';
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    $diff = $today->diff($birth);

    if ($diff->y == 0 && $diff->m == 0) {
        return 'Baru lahir';
    } elseif ($diff->y == 0) {
        return $diff->m . ' bulan';
    } else {
        return $diff->y . ' tahun';
    }
}

function formatPosition($position)
{
    $position_labels = [
        'manager' => 'Manager',
        'headcoach' => 'Head Coach',
        'coach' => 'Coach',
        'goalkeeper_coach' => 'GK Coach',
        'medic' => 'Medic',
        'official' => 'Official',
        'assistant_coach' => 'Asst. Coach',
        'fitness_coach' => 'Fitness Coach',
        'analyst' => 'Analyst',
        'scout' => 'Scout'
    ];

    return $position_labels[$position] ?? ucfirst(str_replace('_', ' ', $position ?? ''));
}

// Helper function to check file exists and return correct path
function getFileUrl($filename, $directory, $defaultIcon = 'fa-user')
{
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

function maskStaffIdentity($identity)
{
    $raw = trim((string)$identity);
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
$pageTitle = "Staff List";
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
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
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
            background: linear-gradient(to right, #000, #c00);
            /* Dark to Red gradient */
            color: #fff;
        }

        .staff-table th {
            padding: 12px 10px;
            text-align: left;
            font-weight: 700;
            text-transform: capitalize;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
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
        .col-no {
            width: 40px;
            text-align: center;
        }

        .col-photo {
            width: 80px;
            text-align: center;
        }

        .col-name {
            color: #0066cc;
            font-weight: 500;
        }

        .col-team {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .team-logo-small {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: contain;
            background: #eee;
        }

        .col-center {
            text-align: center;
        }

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
            border-radius: 4px;
            /* Changed from 50% to 4px */
            object-fit: cover;
            border: 1px solid #ddd;
        }

        .photo-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 4px;
            /* Changed from 50% to 4px */
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
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
            color: #0066cc;
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

        .manager-badge {
            background: #1e40af;
            color: white;
        }

        .headcoach-badge {
            background: #059669;
            color: white;
        }

        .coach-badge {
            background: #7c3aed;
            color: white;
        }

        .goalkeeper_coach-badge {
            background: #d97706;
            color: white;
        }

        .medic-badge {
            background: #dc2626;
            color: white;
        }

        .official-badge {
            background: #475569;
            color: white;
        }

        .assistant_coach-badge {
            background: #0891b2;
            color: white;
        }

        .fitness_coach-badge {
            background: #ea580c;
            color: white;
        }

        .analyst-badge {
            background: #9333ea;
            color: white;
        }

        .scout-badge {
            background: #65a30d;
            color: white;
        }

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
        .col-events,
        .col-matches {
            text-align: center;
            width: 70px;
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

        .staff-history-btn {
            width: 30px;
            height: 30px;
            border: none;
            border-radius: 8px;
            background: #dbeafe;
            color: #1d4ed8;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .staff-history-btn:hover {
            background: #bfdbfe;
            transform: translateY(-1px);
        }

        .match-count-badge i,
        .event-count-badge i {
            margin-right: 4px;
            font-size: 10px;
        }

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

        .pagination-controls a:last-child {
            border-right: none;
        }

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
            background: rgba(0, 0, 0, 0.7);
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
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

        .staff-history-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            z-index: 10002;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .staff-history-modal.open {
            display: flex;
        }

        .staff-history-modal-content {
            width: min(1100px, 100%);
            max-height: calc(100vh - 40px);
            overflow: hidden;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
        }

        .staff-history-modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .staff-history-modal-title {
            margin: 0;
            font-size: 18px;
            color: #0f172a;
        }

        .staff-history-modal-meta {
            margin-top: 4px;
            color: #64748b;
            font-size: 13px;
        }

        .staff-history-close {
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 10px;
            background: #f1f5f9;
            color: #334155;
            cursor: pointer;
        }

        .staff-history-modal-body {
            padding: 16px 20px 20px;
            overflow: auto;
        }

        .staff-history-loading,
        .staff-history-empty {
            padding: 24px 12px;
            text-align: center;
            color: #64748b;
        }

        .staff-history-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .staff-history-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
        }

        .staff-history-table th,
        .staff-history-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 13px;
            color: #0f172a;
            white-space: nowrap;
        }

        .staff-history-table th {
            background: #f8fafc;
            font-size: 12px;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .staff-history-pill {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11px;
            border: 1px solid #93c5fd;
            background: #dbeafe;
            color: #1d4ed8;
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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

        .btn-view,
        .btn-download {
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
            background: rgba(0, 0, 0, 0.9);
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
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.5);
        }

        .image-viewer .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.1);
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
            to {
                transform: rotate(360deg);
            }
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

            .pagination-info {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/redesign_core.css?v=<?php echo getAssetVersion('/css/redesign_core.css'); ?>">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/staff_redesign.css?v=<?php echo getAssetVersion('/css/staff_redesign.css'); ?>">
    <style>
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

        .entity-team-row {
            margin-top: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            padding: 10px 12px;
        }

        .entity-team-logo {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            object-fit: contain;
            background: #fff;
            border: 1px solid var(--gray-200);
            padding: 5px;
        }

        .entity-team-logo-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
            font-size: 18px;
        }

        .entity-team-label {
            font-size: 10px;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .entity-team-name {
            margin-top: 4px;
            font-size: 14px;
            color: var(--navy);
            font-weight: 700;
        }

        .entity-team-alias {
            display: block;
            margin-top: 2px;
            font-size: 11px;
            color: var(--gray-500);
        }

        .entity-profile-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            width: min(520px, 100%);
            align-content: flex-start;
        }

        .entity-profile-actions>.btn-filter-reset,
        .entity-profile-actions>.player-share-section {
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

            .entity-profile-actions>.btn-filter-reset,
            .entity-profile-actions>.player-share-section {
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
                <h2 class="modal-title"><i class="fas fa-certificate"></i> <span id="modalStaffName">Lisensi Staf</span></h2>
                <button class="close-modal" onclick="closeCertificateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="certificateContent">
                    <!-- Certificate content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <div class="staff-history-modal" id="staffHistoryModal">
        <div class="staff-history-modal-content">
            <div class="staff-history-modal-header">
                <div>
                    <h3 class="staff-history-modal-title"><i class="fas fa-clock-rotate-left"></i> <span id="staffHistoryName">-</span></h3>
                    <div class="staff-history-modal-meta" id="staffHistoryMeta">-</div>
                </div>
                <button type="button" class="staff-history-close" id="staffHistoryClose"><i class="fas fa-times"></i></button>
            </div>
            <div class="staff-history-modal-body" id="staffHistoryBody">
                <div class="staff-history-loading"><i class="fas fa-spinner"></i> Memuat riwayat...</div>
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
        $currentPage = 'staff';
        include 'includes/sidebar.php';
        ?>

        <!-- Main Content -->
        <div class="main-content-dashboard">
            <header class="dashboard-header dashboard-header-staff">
                <div class="dashboard-header-inner">
                    <div>
                        <div class="header-eyebrow">ALVETRIX</div>
                        <h1>STAF TEAM</h1>
                        <p class="header-subtitle">Direktori staff, lisensi, dan afiliasi team untuk memantau peran kunci di setiap skuad.</p>
                    </div>
                    <div class="header-actions">
                        <div class="header-stat">
                            <span class="stat-label">Total Staf Aktif</span>
                            <span class="stat-value"><?php echo number_format($total_records); ?></span>
                        </div>
                        <a href="team.php" class="btn-secondary"><i class="fas fa-users"></i> Lihat Team</a>
                    </div>
                </div>
            </header>

            <div class="dashboard-body <?php echo ($staff_id > 0) ? 'has-profile-detail' : ''; ?>">
                <?php if ($staff_id > 0): ?>
                    <section class="entity-profile-card" id="staff-detail">
                        <?php if (!$staff_detail): ?>
                            <div class="entity-empty-state">
                                <div><i class="fas fa-user-slash"></i></div>
                                <h3>Profil staf tidak ditemukan</h3>
                                <p>Data staf dengan ID tersebut tidak tersedia.</p>
                            </div>
                        <?php else: ?>
                            <?php
                            $staff_detail_photo = getFileUrl($staff_detail['photo'] ?? '', 'staff', 'fa-user-tie');
                            $staff_detail_team_logo = getFileUrl($staff_detail['team_logo'] ?? '', 'teams', 'fa-users');
                            $staff_identity_value = $staff_detail['identity_number']
                                ?? ($staff_detail['nik'] ?? ($staff_detail['no_ktp'] ?? ''));
                            $staff_back_query = http_build_query([
                                'page' => max(1, (int)$page),
                                'search' => $search !== '' ? $search : null
                            ]);
                            ?>
                            <div class="entity-profile-header">
                                <div class="entity-profile-identity">
                                    <div class="entity-profile-photo">
                                        <?php if ($staff_detail_photo['found']): ?>
                                            <img src="<?php echo $staff_detail_photo['url']; ?>" alt="<?php echo htmlspecialchars((string)($staff_detail['name'] ?? '')); ?>">
                                        <?php else: ?>
                                            <div class="entity-profile-photo-placeholder"><i class="fas <?php echo htmlspecialchars($staff_detail_photo['icon']); ?>"></i></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="entity-profile-main">
                                        <h2><?php echo htmlspecialchars((string)($staff_detail['name'] ?? '-')); ?></h2>
                                        <div class="entity-profile-meta">
                                            <span class="entity-meta-pill"><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars(formatPosition($staff_detail['position'] ?? '')); ?></span>
                                            <span class="entity-meta-pill outline"><i class="fas fa-user-clock"></i> <?php echo htmlspecialchars(calculateStaffAge($staff_detail['birth_date'] ?? null)); ?></span>
                                            <span class="entity-meta-pill outline"><i class="fas fa-circle-check"></i> <?php echo ((int)($staff_detail['is_active'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif'; ?></span>
                                        </div>

                                        <div class="entity-team-row">
                                            <?php if ($staff_detail_team_logo['found']): ?>
                                                <img src="<?php echo $staff_detail_team_logo['url']; ?>" class="entity-team-logo" alt="<?php echo htmlspecialchars((string)($staff_detail['team_name'] ?? '')); ?>">
                                            <?php else: ?>
                                                <div class="entity-team-logo entity-team-logo-placeholder"><i class="fas <?php echo htmlspecialchars($staff_detail_team_logo['icon']); ?>"></i></div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="entity-team-label">Team</div>
                                                <div class="entity-team-name"><?php echo htmlspecialchars((string)($staff_detail['team_name'] ?? '-')); ?></div>
                                                <?php if (!empty($staff_detail['team_alias'])): ?>
                                                    <span class="entity-team-alias"><?php echo htmlspecialchars((string)$staff_detail['team_alias']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="entity-profile-actions">
                                    <a href="staff.php<?php echo $staff_back_query !== '' ? '?' . $staff_back_query : ''; ?>" class="btn-filter-reset">
                                        <i class="fas fa-arrow-left"></i> Kembali ke daftar
                                    </a>
                                    <div class="player-share-section" id="staffSharePanel" data-share-url="<?php echo htmlspecialchars($staff_share_url, ENT_QUOTES, 'UTF-8'); ?>" data-share-text="<?php echo htmlspecialchars($staff_share_text, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="button" class="player-share-toggle" id="staffShareToggle" aria-expanded="false" aria-controls="staffShareMenu">
                                            <span><i class="fas fa-share-alt"></i> Share Profil</span>
                                            <i class="fas fa-chevron-down player-share-toggle-icon" aria-hidden="true"></i>
                                        </button>
                                        <div class="player-share-menu" id="staffShareMenu">
                                            <div class="player-share-buttons">
                                                <button type="button" class="player-share-btn native" id="staffShareNativeBtn" aria-label="Bagikan profil staff">
                                                    <i class="fas fa-share-nodes"></i> <span>Share</span>
                                                </button>
                                                <a href="<?php echo htmlspecialchars($staff_share_whatsapp_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="player-share-btn whatsapp" aria-label="Bagikan ke WhatsApp">
                                                    <i class="fab fa-whatsapp"></i> <span>WhatsApp</span>
                                                </a>
                                                <a href="<?php echo htmlspecialchars($staff_share_facebook_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="player-share-btn facebook" aria-label="Bagikan ke Facebook">
                                                    <i class="fab fa-facebook-f"></i> <span>Facebook</span>
                                                </a>
                                                <a href="<?php echo htmlspecialchars($staff_share_telegram_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="player-share-btn telegram" aria-label="Bagikan ke Telegram">
                                                    <i class="fab fa-telegram-plane"></i> <span>Telegram</span>
                                                </a>
                                                <a href="<?php echo htmlspecialchars($staff_share_x_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="player-share-btn twitter" aria-label="Bagikan ke X">
                                                    <svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="player-share-x-icon" aria-hidden="true" focusable="false">
                                                        <path d="M14.234 10.162 22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z" />
                                                    </svg>
                                                    <span>X</span>
                                                </a>
                                                <button type="button" class="player-share-btn copy" id="staffShareCopyBtn" aria-label="Salin tautan profil staff">
                                                    <i class="far fa-copy"></i> <span>Salin Link</span>
                                                </button>
                                            </div>
                                            <div class="player-share-feedback" id="staffShareFeedback" aria-live="polite"></div>
                                        </div>
                                    </div>
                                    <?php if ((int)($staff_detail['certificate_count'] ?? 0) > 0): ?>
                                        <button
                                            type="button"
                                            class="entity-profile-btn entity-profile-btn-accent"
                                            onclick="loadCertificates(<?php echo (int)$staff_detail['id']; ?>, '<?php echo htmlspecialchars(addslashes((string)($staff_detail['name'] ?? ''))); ?>')">
                                            <i class="fas fa-certificate"></i> Lihat Lisensi
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($has_match_staff_assignments_table): ?>
                                        <button
                                            type="button"
                                            class="entity-profile-btn btn-staff-history"
                                            id="staffHistoryToggle"
                                            data-staff-id="<?php echo (int)$staff_detail['id']; ?>"
                                            data-staff-name="<?php echo htmlspecialchars((string)($staff_detail['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-team-name="<?php echo htmlspecialchars((string)($staff_detail['team_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-chart-line"></i> Riwayat Match
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="entity-profile-grid">
                                <div class="entity-detail-item">
                                    <span class="entity-detail-label">Jabatan</span>
                                    <span class="entity-detail-value"><?php echo htmlspecialchars(formatPosition($staff_detail['position'] ?? '')); ?></span>
                                </div>
                                <div class="entity-detail-item">
                                    <span class="entity-detail-label">No. Identitas</span>
                                    <span class="entity-detail-value"><?php echo htmlspecialchars(maskStaffIdentity($staff_identity_value)); ?></span>
                                </div>
                                <div class="entity-detail-item">
                                    <span class="entity-detail-label">Tanggal Lahir</span>
                                    <span class="entity-detail-value"><?php echo !empty($staff_detail['birth_date']) ? htmlspecialchars(date('d M Y', strtotime((string)$staff_detail['birth_date']))) : '-'; ?></span>
                                </div>
                                <div class="entity-detail-item">
                                    <span class="entity-detail-label">Alamat</span>
                                    <span class="entity-detail-value"><?php echo htmlspecialchars($staff_detail_address); ?></span>
                                </div>
                                <div class="entity-detail-item">
                                    <span class="entity-detail-label">Email</span>
                                    <span class="entity-detail-value"><?php echo !empty($staff_detail['email']) ? htmlspecialchars((string)$staff_detail['email']) : '-'; ?></span>
                                </div>
                                <div class="entity-detail-item">
                                    <span class="entity-detail-label">Telepon</span>
                                    <span class="entity-detail-value"><?php echo !empty($staff_detail['phone']) ? htmlspecialchars((string)$staff_detail['phone']) : '-'; ?></span>
                                </div>
                                <div class="entity-detail-item">
                                    <span class="entity-detail-label">Lisensi</span>
                                    <span class="entity-detail-value"><?php echo (int)($staff_detail['certificate_count'] ?? 0); ?> Dokumen</span>
                                </div>
                                <div class="entity-detail-item">
                                    <span class="entity-detail-label">Event & Match</span>
                                    <span class="entity-detail-value"><?php echo $staff_detail_event_count; ?> Event | <?php echo $staff_detail_match_count; ?> Match</span>
                                    <?php if (!empty($staff_detail_event_info)): ?>
                                        <span class="entity-detail-sub"><?php echo htmlspecialchars(implode(', ', $staff_detail_event_info)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="entity-detail-item">
                                    <span class="entity-detail-label">Status</span>
                                    <span class="entity-detail-value"><?php echo ((int)($staff_detail['is_active'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif'; ?></span>
                                </div>
                                <div class="entity-detail-item">
                                    <span class="entity-detail-label">Dibuat Pada</span>
                                    <span class="entity-detail-value"><?php echo !empty($staff_detail['created_at']) ? htmlspecialchars(date('d M Y, H:i', strtotime((string)$staff_detail['created_at']))) : '-'; ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php endif; ?>

                <div class="filter-card staff-filter-card">
                    <form action="" method="GET" class="filter-row">
                        <div class="filter-group">
                            <label for="search">Pencarian Staf</label>
                            <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>"
                                placeholder="Cari staf (nama, email, telepon)...">
                        </div>
                        <div class="filter-actions-new">
                            <button type="submit" class="btn-filter-apply">
                                <i class="fas fa-search"></i> Cari
                            </button>
                            <a href="staff.php" class="btn-filter-reset">
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
                            <span class="summary-value"><?php echo min($offset + 1, $total_records); ?> - <?php echo min($offset + $limit, $total_records); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Total Staf</span>
                            <span class="summary-value"><?php echo number_format($total_records); ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!$has_match_staff_assignments_table): ?>
                    <div style="margin-bottom:16px; padding:12px 14px; border-radius:10px; background:#fff7ed; border:1px solid #fdba74; color:#9a3412; font-size:13px;">
                        <i class="fas fa-triangle-exclamation"></i>
                        Histori event/match staff belum aktif. Jalankan migrasi: <code>migrations/migration_create_match_staff_assignments.sql</code>
                    </div>
                <?php endif; ?>

                <div class="table-container-new">
                    <table class="staff-table-new">
                        <thead>
                            <tr>
                                <th class="col-no">No</th>
                                <th class="col-photo">Foto</th>
                                <th>Nama Staf</th>
                                <th>Team</th>
                                <th class="col-position">Jabatan</th>
                                <th class="col-age">Usia</th>
                                <th class="col-certificate">Lisensi</th>
                                <th class="col-events">Event</th>
                                <th class="col-matches">Match</th>
                                <th>Dibuat</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($staffs)): ?>
                                <tr>
                                    <td colspan="10" class="no-data">
                                        <i class="fas fa-user-slash"></i>
                                        <p>Tidak ada staf ditemukan</p>
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
                                foreach ($staffs as $s):
                                    $position_class = $s['position'] . '-badge';

                                    // Get staff photo info
                                    $staff_photo = getFileUrl($s['photo'], 'staff', 'fa-user-tie');

                                    // Get team logo info
                                    $team_logo = getFileUrl($s['team_logo'], 'teams', 'fa-users');
                                    $match_count = $staff_match_counts[(int)$s['id']] ?? 0;
                                    $event_stats = $staff_event_stats[(int)$s['id']] ?? [];
                                    $event_count = count($event_stats);
                                    $event_full_info = [];
                                    foreach ($event_stats as $event_name => $event_match_total) {
                                        $event_full_info[] = $event_name . ' (' . $event_match_total . ' match)';
                                    }
                                ?>
                                    <tr>
                                        <!-- Kolom No -->
                                        <td class="col-no" data-label="No"><?php echo $no++; ?></td>

                                        <!-- Kolom Foto Staff -->
                                        <td class="col-photo" data-label="Foto">
                                            <div class="staff-photo-wrapper">
                                                <?php if ($staff_photo['found']): ?>
                                                    <img src="<?php echo $staff_photo['url']; ?>"
                                                        class="staff-img-sm"
                                                        alt="<?php echo htmlspecialchars($s['name'] ?? ''); ?>"
                                                        onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <?php endif; ?>

                                                <div class="photo-placeholder" style="<?php echo $staff_photo['found'] ? 'display: none;' : ''; ?>">
                                                    <i class="fas <?php echo $staff_photo['icon']; ?>"></i>
                                                </div>

                                                <!-- Team Badge/Lambang di bawah foto -->
                                                <div class="team-badge">
                                                    <?php if ($team_logo['found']): ?>
                                                        <img src="<?php echo $team_logo['url']; ?>"
                                                            class="team-badge-img"
                                                            alt="<?php echo htmlspecialchars($s['team_name'] ?? ''); ?>"
                                                            onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <?php endif; ?>

                                                    <div class="team-badge-placeholder" style="<?php echo $team_logo['found'] ? 'display: none;' : ''; ?>">
                                                        <i class="fas <?php echo $team_logo['icon']; ?>"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Kolom Nama -->
                                        <td class="col-name" data-label="Nama">
                                            <a href="staff.php?id=<?php echo (int)$s['id']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>#staff-detail" class="staff-name staff-name-link">
                                                <?php echo htmlspecialchars($s['name'] ?? ''); ?>
                                            </a>
                                            <div class="staff-contact">
                                                <?php if (!empty($s['email'])): ?>
                                                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($s['email'] ?? ''); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($s['phone'])): ?>
                                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($s['phone'] ?? ''); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Kolom Team -->
                                        <td class="col-team" data-label="Team">
                                            <div class="team-display">
                                                <?php if ($team_logo['found']): ?>
                                                    <img src="<?php echo $team_logo['url']; ?>"
                                                        class="team-logo"
                                                        alt="<?php echo htmlspecialchars($s['team_name'] ?? ''); ?>"
                                                        onerror="this.style.display='none'">
                                                <?php endif; ?>
                                                <div class="team-info">
                                                    <span class="team-name"><?php echo htmlspecialchars($s['team_name'] ?: '-'); ?></span>
                                                    <?php if (!empty($s['team_alias'])): ?>
                                                        <span class="team-alias"><?php echo htmlspecialchars($s['team_alias'] ?? ''); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Kolom Jabatan -->
                                        <td class="col-position" data-label="Jabatan">
                                            <span class="position-badge <?php echo $position_class; ?>">
                                                <?php echo formatPosition($s['position']); ?>
                                            </span>
                                        </td>

                                        <!-- Kolom Usia -->
                                        <td class="col-age" data-label="Usia"><?php echo calculateStaffAge($s['birth_date']); ?></td>

                                        <!-- Kolom Lisensi -->
                                        <td class="col-certificate" data-label="Lisensi">
                                            <?php if ($s['certificate_count'] > 0): ?>
                                                <div class="cert-count"
                                                    onclick="loadCertificates(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'] ?? '')); ?>')">
                                                    <i class="fas fa-certificate"></i>
                                                    <span><?php echo $s['certificate_count']; ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="muted">-</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="col-events" data-label="Event">
                                            <span class="event-count-badge <?php echo $event_count === 0 ? 'zero' : ''; ?>"
                                                title="<?php echo htmlspecialchars(implode(', ', $event_full_info)); ?>">
                                                <i class="fas fa-calendar-check"></i><?php echo $event_count; ?>
                                            </span>
                                        </td>

                                        <td class="col-matches" data-label="Match">
                                            <span class="match-count-badge <?php echo $match_count === 0 ? 'zero' : ''; ?>">
                                                <i class="fas fa-futbol"></i><?php echo $match_count; ?>
                                            </span>
                                        </td>

                                        <!-- Kolom Created At -->
                                        <td class="col-created" data-label="Dibuat">
                                            <?php echo date('d M Y', strtotime($s['created_at'])); ?><br>
                                            <small><?php echo date('H:i', strtotime($s['created_at'])); ?></small>
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
                                echo '<a href="?page=1&search=' . urlencode($search) . '">1</a>';
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
                                echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '">' . $total_pages . '</a>';
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

    <script>
        const SITE_URL = '<?php echo SITE_URL; ?>';
    </script>
    <script src="<?php echo SITE_URL; ?>/js/script.js?v=<?php echo getAssetVersion('/js/script.js'); ?>"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/staff-history.js?v=<?php echo getAssetVersion('/assets/js/staff-history.js'); ?>"></script>
</body>

</html>
