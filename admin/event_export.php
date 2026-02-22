<?php
session_start();

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

if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$filter_registration = isset($_GET['registration']) ? trim((string) $_GET['registration']) : '';
$filter_active = isset($_GET['active']) ? trim((string) $_GET['active']) : '';

if (!in_array($filter_registration, ['', 'open', 'closed'], true)) {
    $filter_registration = '';
}
if (!in_array($filter_active, ['', '1', '0'], true)) {
    $filter_active = '';
}

$query = "SELECT * FROM events WHERE 1=1";
$params = [];

if ($search !== '') {
    $query .= " AND (name LIKE ? OR category LIKE ? OR location LIKE ? OR contact LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($filter_registration !== '') {
    $query .= " AND registration_status = ?";
    $params[] = $filter_registration;
}

if ($filter_active !== '') {
    $query .= " AND is_active = ?";
    $params[] = (int) $filter_active;
}

$query .= " ORDER BY created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="event_export_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '<!--[if gte mso 9]>';
echo '<xml>';
echo '<x:ExcelWorkbook>';
echo '<x:ExcelWorksheets>';
echo '<x:ExcelWorksheet>';
echo '<x:Name>Event Data</x:Name>';
echo '<x:WorksheetOptions>';
echo '<x:DisplayGridlines/>';
echo '</x:WorksheetOptions>';
echo '</x:ExcelWorksheet>';
echo '</x:ExcelWorksheets>';
echo '</x:ExcelWorkbook>';
echo '</xml>';
echo '<![endif]-->';
echo '<style>';
echo 'td { border: 0.5pt solid #000000; padding: 5px; vertical-align: top; }';
echo 'th { border: 0.5pt solid #000000; padding: 8px; background-color: #f2f2f2; font-weight: bold; }';
echo '</style>';
echo '</head>';
echo '<body>';

echo '<table border="1">';
echo '<thead>';
echo '<tr>';
echo '<th>No</th>';
echo '<th>Nama Event</th>';
echo '<th>Slug</th>';
echo '<th>Tipe</th>';
echo '<th>Lokasi</th>';
echo '<th>Tanggal Mulai</th>';
echo '<th>Tanggal Selesai</th>';
echo '<th>Pendaftaran</th>';
echo '<th>Status</th>';
echo '<th>Kontak</th>';
echo '<th>Deskripsi</th>';
echo '<th>Tanggal Dibuat</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
foreach ($events as $event) {
    $startDate = !empty($event['start_date']) ? date('d/m/Y', strtotime($event['start_date'])) : '-';
    $endDate = !empty($event['end_date']) ? date('d/m/Y', strtotime($event['end_date'])) : '-';
    $registrationLabel = (($event['registration_status'] ?? '') === 'open') ? 'Open' : 'Closed';
    $statusLabel = ((int) ($event['is_active'] ?? 1) === 1) ? 'Aktif' : 'Nonaktif';
    $description = trim(strip_tags((string) ($event['description'] ?? '')));
    $description = $description !== '' ? $description : '-';

    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars((string) ($event['name'] ?? '-')) . '</td>';
    echo '<td>' . htmlspecialchars((string) ($event['slug'] ?? '-')) . '</td>';
    echo '<td>' . htmlspecialchars((string) ($event['category'] ?? '-')) . '</td>';
    echo '<td>' . htmlspecialchars((string) ($event['location'] ?? '-')) . '</td>';
    echo '<td>' . htmlspecialchars($startDate) . '</td>';
    echo '<td>' . htmlspecialchars($endDate) . '</td>';
    echo '<td>' . $registrationLabel . '</td>';
    echo '<td>' . $statusLabel . '</td>';
    echo '<td>' . htmlspecialchars((string) (!empty($event['contact']) ? $event['contact'] : '-')) . '</td>';
    echo '<td>' . htmlspecialchars($description) . '</td>';
    echo '<td>' . (!empty($event['created_at']) ? date('d/m/Y H:i', strtotime($event['created_at'])) : '-') . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
?>
