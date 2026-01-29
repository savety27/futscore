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

// Query untuk mengambil semua data venues
$query = "SELECT * FROM venues WHERE 1=1";

if (!empty($search)) {
    $search_term = "%{$search}%";
    $query .= " AND (name LIKE ? OR location LIKE ? OR facilities LIKE ?)";
}

$query .= " ORDER BY created_at DESC";

try {
    if (!empty($search)) {
        $stmt = $conn->prepare($query);
        $stmt->execute([$search_term, $search_term, $search_term]);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->execute();
    }
    
    $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="venues_export_' . date('Y-m-d_H-i-s') . '.xls"');
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
echo '<x:Name>Venues Data</x:Name>';
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
echo '<th>Nama Venue</th>';
echo '<th>Lokasi</th>';
echo '<th>Kapasitas</th>';
echo '<th>Fasilitas</th>';
echo '<th>Status</th>';
echo '<th>Tanggal Dibuat</th>';
echo '<th>Tanggal Update</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
foreach ($venues as $venue) {
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars($venue['name']) . '</td>';
    echo '<td>' . htmlspecialchars($venue['location']) . '</td>';
    echo '<td>' . number_format($venue['capacity']) . '</td>';
    echo '<td>' . (!empty($venue['facilities']) ? htmlspecialchars($venue['facilities']) : '-') . '</td>';
    echo '<td>' . ($venue['is_active'] ? 'Aktif' : 'Non-Aktif') . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($venue['created_at'])) . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($venue['updated_at'])) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
?>