<?php
session_start();

// Load database config
$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'operator') {
    header("Location: ../login.php");
    exit;
}

if (!function_exists('adminHasColumn')) {
    function adminHasColumn(PDO $conn, $tableName, $columnName) {
        try {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tableName);
            if ($safeTable === '') {
                return false;
            }
            $quotedColumn = $conn->quote((string) $columnName);
            $stmt = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quotedColumn}");
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('adminHasTable')) {
    function adminHasTable(PDO $conn, $tableName) {
        try {
            $quotedTable = $conn->quote((string) $tableName);
            $stmt = $conn->query("SHOW TABLES LIKE {$quotedTable}");
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

$challenge_has_event_id = adminHasColumn($conn, 'challenges', 'event_id');
$events_table_exists = adminHasTable($conn, 'events');
$can_join_event_name = $challenge_has_event_id && $events_table_exists;

// Resolve operator event assignment (server-side lock).
$operator_id = (int)($_SESSION['admin_id'] ?? 0);
$operator_event_id = (int)($_SESSION['event_id'] ?? 0);
if ($operator_id > 0) {
    try {
        $stmtOperator = $conn->prepare("SELECT event_id FROM admin_users WHERE id = ? LIMIT 1");
        $stmtOperator->execute([$operator_id]);
        $operator_row = $stmtOperator->fetch(PDO::FETCH_ASSOC);
        $operator_event_id = (int)($operator_row['event_id'] ?? $operator_event_id);
        $_SESSION['event_id'] = $operator_event_id > 0 ? $operator_event_id : null;
    } catch (PDOException $e) {
        // Keep session value if query fails.
    }
}

// Handle search (from challenge list URL query)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_event_id = $operator_event_id;

// Query export data challenges
$event_select = $can_join_event_name ? "e.name as event_name," : "NULL as event_name,";
$event_join = $can_join_event_name ? "LEFT JOIN events e ON c.event_id = e.id" : "";

$query = "SELECT c.*,
          {$event_select}
          t1.name as challenger_name, t1.sport_type as challenger_sport,
          t2.name as opponent_name,
          v.name as venue_name, v.location as venue_location
          FROM challenges c
          {$event_join}
          LEFT JOIN teams t1 ON c.challenger_id = t1.id
          LEFT JOIN teams t2 ON c.opponent_id = t2.id
          LEFT JOIN venues v ON c.venue_id = v.id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $search_term = "%{$search}%";
    if ($can_join_event_name) {
        $query .= " AND (c.challenge_code LIKE ? OR c.status LIKE ? OR c.sport_type LIKE ? OR e.name LIKE ? OR t1.name LIKE ? OR t2.name LIKE ?)";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term]);
    } else {
        $query .= " AND (c.challenge_code LIKE ? OR c.status LIKE ? OR c.sport_type LIKE ? OR t1.name LIKE ? OR t2.name LIKE ?)";
        $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    }
}

if ($can_join_event_name) {
    if ($selected_event_id > 0) {
        $query .= " AND c.event_id = ?";
        $params[] = $selected_event_id;
    } else {
        // Operator tanpa event assignment tidak boleh export lintas event.
        $query .= " AND 1=0";
    }
}

$query .= " ORDER BY c.challenge_date DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    
    $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="challenges_export_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

// Start Excel content
echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '<!--[if gte mso 9]>';
echo '<xml>';
echo '<x:ExcelWorkbook>';
echo '<x:ExcelWorksheets>';
echo '<x:ExcelWorksheet>';
echo '<x:Name>Challenges Data</x:Name>';
echo '<x:WorksheetOptions>';
echo '<x:DisplayGridlines/>';
echo '</x:WorksheetOptions>';
echo '</x:ExcelWorksheet>';
echo '</x:ExcelWorksheets>';
echo '</x:ExcelWorkbook>';
echo '</xml>';
echo '<![endif]-->';
echo '<style>';
echo 'td { border: 0.5pt solid #000000; padding: 5px; }';
echo 'th { border: 0.5pt solid #000000; padding: 8px; background-color: #f2f2f2; font-weight: bold; }';
echo '</style>';
echo '</head>';
echo '<body>';

echo '<table border="1">';
echo '<thead>';
echo '<tr>';
echo '<th>No</th>';
echo '<th>Kode Challenge</th>';
echo '<th>Status</th>';
echo '<th>Challenger</th>';
echo '<th>Opponent</th>';
echo '<th>Venue</th>';
echo '<th>Tanggal & Waktu</th>';
echo '<th>Expiry Date</th>';
echo '<th>Events</th>';
echo '<th>Kategori</th>';
echo '<th>Match Status</th>';
echo '<th>Skor Challenger</th>';
echo '<th>Skor Opponent</th>';
echo '<th>Pemenang</th>';
echo '<th>Catatan</th>';
echo '<th>Dibuat Pada</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
foreach ($challenges as $challenge) {
    // Determine winner name
    $winner = '';
    if ($challenge['challenger_score'] !== null && $challenge['opponent_score'] !== null) {
        if ($challenge['challenger_score'] > $challenge['opponent_score']) {
            $winner = $challenge['challenger_name'];
        } elseif ($challenge['opponent_score'] > $challenge['challenger_score']) {
            $winner = $challenge['opponent_name'];
        } else {
            $winner = 'Seri';
        }
    }
    
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars($challenge['challenge_code'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($challenge['status'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($challenge['challenger_name'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($challenge['opponent_name'] ?? 'TBD') . '</td>';
    echo '<td>' . htmlspecialchars($challenge['venue_name'] ?? '-') . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($challenge['challenge_date'])) . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($challenge['expiry_date'])) . '</td>';
    echo '<td>' . htmlspecialchars($challenge['event_name'] ?? '-') . '</td>';
    echo '<td>' . htmlspecialchars($challenge['sport_type'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($challenge['match_status'] ?? 'Belum Mulai') . '</td>';
    echo '<td>' . ($challenge['challenger_score'] !== null ? $challenge['challenger_score'] : '-') . '</td>';
    echo '<td>' . ($challenge['opponent_score'] !== null ? $challenge['opponent_score'] : '-') . '</td>';
    echo '<td>' . $winner . '</td>';
    echo '<td>' . htmlspecialchars($challenge['notes'] ?? '-') . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($challenge['created_at'])) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
?>
