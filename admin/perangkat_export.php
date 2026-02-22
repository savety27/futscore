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

function formatPerangkatAge($value)
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return '-';
    }

    $dob = DateTimeImmutable::createFromFormat('Y-m-d', $raw);
    if ($dob && $dob->format('Y-m-d') === $raw) {
        $today = new DateTimeImmutable('today');
        if ($dob > $today) {
            return '-';
        }
        return (string) $dob->diff($today)->y;
    }

    if (is_numeric($raw)) {
        return (string) max(0, (int) $raw);
    }

    return '-';
}

$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$filter_active = isset($_GET['active']) ? trim((string) $_GET['active']) : '';
if (!in_array($filter_active, ['', '1', '0'], true)) {
    $filter_active = '';
}

$query = "SELECT p.*,
          (SELECT COUNT(*) FROM perangkat_licenses pl WHERE pl.perangkat_id = p.id) AS license_count
          FROM perangkat p
          WHERE 1=1";
$params = [];

if ($search !== '') {
    $search_term = "%{$search}%";
    $query .= " AND (p.name LIKE ? OR p.no_ktp LIKE ? OR p.email LIKE ? OR p.phone LIKE ? OR p.city LIKE ? OR p.province LIKE ?)";
    $params = [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term];
}

if ($filter_active !== '') {
    $query .= " AND p.is_active = ?";
    $params[] = (int) $filter_active;
}

$query .= " ORDER BY p.created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $perangkat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="perangkat_export_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '<!--[if gte mso 9]>';
echo '<xml>';
echo '<x:ExcelWorkbook>';
echo '<x:ExcelWorksheets>';
echo '<x:ExcelWorksheet>';
echo '<x:Name>Perangkat Data</x:Name>';
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
echo '<th>Nama</th>';
echo '<th>No. KTP</th>';
echo '<th>Tempat Lahir</th>';
echo '<th>Tanggal Lahir</th>';
echo '<th>Usia</th>';
echo '<th>Email</th>';
echo '<th>Telepon</th>';
echo '<th>Alamat</th>';
echo '<th>Kota</th>';
echo '<th>Provinsi</th>';
echo '<th>Kode Pos</th>';
echo '<th>Negara</th>';
echo '<th>Jumlah Lisensi</th>';
echo '<th>Status</th>';
echo '<th>Tanggal Dibuat</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
foreach ($perangkat_list as $row) {
    $birthDateFormatted = '-';
    $birthDateRaw = trim((string) ($row['age'] ?? ''));
    if ($birthDateRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDateRaw)) {
        $birthDateFormatted = date('d/m/Y', strtotime($birthDateRaw));
    }

    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars((string) ($row['name'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string) ($row['no_ktp'] ?? '')) . '</td>';
    echo '<td>' . (!empty($row['birth_place']) ? htmlspecialchars((string) $row['birth_place']) : '-') . '</td>';
    echo '<td>' . $birthDateFormatted . '</td>';
    echo '<td>' . htmlspecialchars(formatPerangkatAge($row['age'] ?? '')) . '</td>';
    echo '<td>' . (!empty($row['email']) ? htmlspecialchars((string) $row['email']) : '-') . '</td>';
    echo '<td>' . (!empty($row['phone']) ? htmlspecialchars((string) $row['phone']) : '-') . '</td>';
    echo '<td>' . (!empty($row['address']) ? htmlspecialchars((string) $row['address']) : '-') . '</td>';
    echo '<td>' . (!empty($row['city']) ? htmlspecialchars((string) $row['city']) : '-') . '</td>';
    echo '<td>' . (!empty($row['province']) ? htmlspecialchars((string) $row['province']) : '-') . '</td>';
    echo '<td>' . (!empty($row['postal_code']) ? htmlspecialchars((string) $row['postal_code']) : '-') . '</td>';
    echo '<td>' . (!empty($row['country']) ? htmlspecialchars((string) $row['country']) : 'Indonesia') . '</td>';
    echo '<td>' . (int) ($row['license_count'] ?? 0) . '</td>';
    echo '<td>' . ((int) ($row['is_active'] ?? 0) === 1 ? 'Aktif' : 'Non-Aktif') . '</td>';
    echo '<td>' . (!empty($row['created_at']) ? date('d/m/Y H:i', strtotime((string) $row['created_at'])) : '-') . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
?>
