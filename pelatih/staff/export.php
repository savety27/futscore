<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'pelatih') {
    header('Location: ../../login.php');
    exit;
}

require_once '../config/database.php';

$team_id = (int)($_SESSION['team_id'] ?? 0);
$search = trim((string)($_GET['search'] ?? ''));
$filter_active = trim((string)($_GET['active'] ?? ''));
if (!in_array($filter_active, ['', '1', '0'], true)) {
    $filter_active = '';
}

function pelatihStaffExportValue($value, string $fallback = '-'): string
{
    $text = trim((string)$value);
    return $text !== '' ? $text : $fallback;
}

function pelatihStaffExportDate($value, string $format = 'd/m/Y', string $fallback = '-'): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return $fallback;
    }

    $timestamp = strtotime($text);
    return $timestamp ? date($format, $timestamp) : $text;
}

function pelatihStaffExportAge($birthDate): string
{
    $text = trim((string)$birthDate);
    if ($text === '') {
        return '-';
    }

    try {
        $birth = new DateTime($text);
        $today = new DateTime();
        return (string)$today->diff($birth)->y;
    } catch (Exception $e) {
        return '-';
    }
}

try {
    $query = "SELECT ts.*,
                     t.name AS team_name,
                     (SELECT COUNT(*) FROM staff_certificates sc WHERE sc.staff_id = ts.id) AS certificate_count
              FROM team_staff ts
              LEFT JOIN teams t ON ts.team_id = t.id
              WHERE ts.team_id = ?";
    $params = [$team_id];

    if ($search !== '') {
        $searchTerm = '%' . $search . '%';
        $query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ? OR t.name LIKE ?)";
        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }

    if ($filter_active !== '') {
        $query .= " AND ts.is_active = ?";
        $params[] = (int)$filter_active;
    }

    $query .= " ORDER BY ts.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error fetching data: ' . $e->getMessage());
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="pelatih_team_staff_export_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Staf Team</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
echo '<style>td { border: 0.5pt solid #000000; padding: 5px; } th { border: 0.5pt solid #000000; padding: 8px; background-color: #f2f2f2; font-weight: bold; }</style>';
echo '</head>';
echo '<body>';
echo '<table border="1">';
echo '<thead><tr>';
echo '<th>No</th>';
echo '<th>Nama</th>';
echo '<th>Jabatan</th>';
echo '<th>Team</th>';
echo '<th>Email</th>';
echo '<th>Telepon</th>';
echo '<th>Tempat Lahir</th>';
echo '<th>Tanggal Lahir</th>';
echo '<th>Usia</th>';
echo '<th>Alamat</th>';
echo '<th>Kota</th>';
echo '<th>Provinsi</th>';
echo '<th>Kode Pos</th>';
echo '<th>Negara</th>';
echo '<th>Jumlah Sertifikat</th>';
echo '<th>Status</th>';
echo '<th>Tanggal Dibuat</th>';
echo '</tr></thead>';
echo '<tbody>';

$no = 1;
foreach ($staffList as $staff) {
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportValue($staff['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportValue($staff['position'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportValue($staff['team_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportValue($staff['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportValue($staff['phone'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportValue($staff['birth_place'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportDate($staff['birth_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportAge($staff['birth_date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportValue($staff['address'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportValue($staff['city'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportValue($staff['province'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportValue($staff['postal_code'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportValue($staff['country'] ?? '', 'Indonesia'), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . (int)($staff['certificate_count'] ?? 0) . '</td>';
    echo '<td>' . htmlspecialchars(!empty($staff['is_active']) ? 'Aktif' : 'Non-Aktif', ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihStaffExportDate($staff['created_at'] ?? '', 'd/m/Y H:i'), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '</body></html>';
?>
