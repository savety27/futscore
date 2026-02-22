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
$filter_active = isset($_GET['active']) ? trim((string) $_GET['active']) : '';
if (!in_array($filter_active, ['', '1', '0'], true)) {
    $filter_active = '';
}

// Query untuk mengambil semua data venues
$query = "SELECT * FROM venues WHERE 1=1";

if (!empty($search)) {
    $search_term = "%{$search}%";
    $query .= " AND (name LIKE ? OR location LIKE ? OR facilities LIKE ?)";
}

if ($filter_active !== '') {
    $query .= " AND is_active = ?";
}

$query .= " ORDER BY created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $params = [];
    if (!empty($search)) {
        $params = [$search_term, $search_term, $search_term];
    }
    if ($filter_active !== '') {
        $params[] = (int) $filter_active;
    }
    $stmt->execute($params);
    
    $venues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="venues_export_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// Start Excel content dengan format UTF-8
echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
echo '<style>';
echo 'td { border: 0.5pt solid #000000; padding: 5px; font-family: Arial, sans-serif; }';
echo 'th { border: 0.5pt solid #000000; padding: 8px; background-color: #f2f2f2; font-weight: bold; font-family: Arial, sans-serif; }';
echo '.number { mso-number-format:\#\,\#\#0; }'; // Format angka tanpa separator ribuan
echo '</style>';
echo '</head>';
echo '<body>';

echo '<table border="1" cellpadding="0" cellspacing="0">';
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
    echo '<td>' . htmlspecialchars($venue['name'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars($venue['location'], ENT_QUOTES, 'UTF-8') . '</td>';
    // Tambahkan class "number" untuk sel kapasitas dan format tanpa separator ribuan
    echo '<td class="number">' . $venue['capacity'] . '</td>';
    echo '<td>' . (!empty($venue['facilities']) ? htmlspecialchars($venue['facilities'], ENT_QUOTES, 'UTF-8') : '-') . '</td>';
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
