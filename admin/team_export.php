<?php
session_start();

// Load database config
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query untuk mengambil semua data teams
$query = "SELECT t.*, 
          (SELECT COUNT(*) FROM players p WHERE p.team_id = t.id AND p.status = 'active') as player_count,
          (SELECT COUNT(*) FROM team_staff ts WHERE ts.team_id = t.id) as staff_count
          FROM teams t WHERE 1=1";

if (!empty($search)) {
    $search_term = "%{$search}%";
    $query .= " AND (t.name LIKE ? OR t.alias LIKE ? OR t.coach LIKE ? OR t.sport_type LIKE ?)";
}

$query .= " ORDER BY t.created_at DESC";

try {
    if (!empty($search)) {
        $stmt = $conn->prepare($query);
        $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->execute();
    }
    
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="teams_export_' . date('Y-m-d_H-i-s') . '.xls"');
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
echo '<x:Name>Teams Data</x:Name>';
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
echo '<th>Nama Team</th>';
echo '<th>Alias</th>';
echo '<th>Manager</th>';
echo '<th>Tanggal Berdiri</th>';
echo '<th>Warna Kostum</th>';
echo '<th>Jumlah Player</th>';
echo '<th>Jumlah Staff</th>';
echo '<th>Basecamp</th>';
echo '<th>Cabor</th>';
echo '<th>Status</th>';
echo '<th>Tanggal Dibuat</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
foreach ($teams as $team) {
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars($team['name'] ?? '') . '</td>';
    echo '<td>' . (!empty($team['alias']) ? htmlspecialchars($team['alias']) : '-') . '</td>';
    echo '<td>' . htmlspecialchars($team['coach'] ?? '') . '</td>';
    $established_display = '-';
    if (!empty($team['established_year'])) {
        $timestamp = strtotime($team['established_year']);
        $established_display = $timestamp ? date('d/m/Y', $timestamp) : $team['established_year'];
    }
    echo '<td>' . htmlspecialchars($established_display) . '</td>';
    echo '<td>' . (!empty($team['uniform_color']) ? htmlspecialchars($team['uniform_color']) : '-') . '</td>';
    echo '<td>' . $team['player_count'] . '</td>';
    echo '<td>' . $team['staff_count'] . '</td>';
    echo '<td>' . (!empty($team['basecamp']) ? htmlspecialchars($team['basecamp']) : '-') . '</td>';
    echo '<td>' . htmlspecialchars($team['sport_type'] ?? '') . '</td>';
    echo '<td>' . ($team['is_active'] ? 'Aktif' : 'Non-Aktif') . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($team['created_at'])) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
?>
