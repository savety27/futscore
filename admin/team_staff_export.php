<?php
require_once __DIR__ . '/includes/auth_guard.php';
require_once __DIR__ . '/includes/team_staff_helpers.php';

// Load database config
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

// Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$team_id = isset($_GET['team_id']) ? (int) $_GET['team_id'] : 0;
$filter_active = isset($_GET['active']) ? trim((string) $_GET['active']) : '';
if (!in_array($filter_active, ['', '1', '0'], true)) {
    $filter_active = '';
}

// Query untuk mengambil semua data staff
$query = "SELECT ts.*, 
          t.name as team_name,
          t.alias as team_alias,
          (SELECT COUNT(*) FROM staff_certificates sc WHERE sc.staff_id = ts.id) as certificate_count
          FROM team_staff ts
          LEFT JOIN teams t ON ts.team_id = t.id
          WHERE 1=1";

if (!empty($search)) {
    $search_term = "%{$search}%";
    $query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ? OR t.name LIKE ? OR t.alias LIKE ?)";
}

if ($team_id > 0) {
    $query .= " AND t.id = ?";
}

if ($filter_active !== '') {
    $query .= " AND ts.is_active = ?";
}

$query .= " ORDER BY ts.created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $params = [];
    if (!empty($search)) {
        $params = [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term];
    }
    if ($team_id > 0) {
        $params[] = $team_id;
    }
    if ($filter_active !== '') {
        $params[] = (int) $filter_active;
    }
    $stmt->execute($params);
    
    $staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate age for each staff
    foreach ($staff_list as &$staff) {
        if (!empty($staff['birth_date'])) {
            $birthDate = new DateTime($staff['birth_date']);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            $staff['age'] = $age;
        } else {
            $staff['age'] = '-';
        }
        
    }
    unset($staff); // Break the reference with the last element
    
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="staff_export_' . date('Y-m-d_H-i-s') . '.xls"');
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
echo '<x:Name>Staff Data</x:Name>';
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
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
foreach ($staff_list as $staff) {
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo teamStaffExportTextCell($staff['name'] ?? '', '');
    echo teamStaffExportTextCell(teamStaffPositionLabel($staff['position'] ?? ''));
    echo teamStaffExportTextCell($staff['team_name'] ?? '', '');
    echo teamStaffExportTextCell($staff['email'] ?? '');
    echo teamStaffExportTextCell($staff['phone'] ?? '');
    echo teamStaffExportTextCell($staff['birth_place'] ?? '');
    echo '<td>' . (!empty($staff['birth_date']) ? date('d/m/Y', strtotime($staff['birth_date'])) : '-') . '</td>';
    echo '<td>' . $staff['age'] . '</td>';
    echo teamStaffExportTextCell($staff['address'] ?? '');
    echo teamStaffExportTextCell($staff['city'] ?? '');
    echo teamStaffExportTextCell($staff['province'] ?? '');
    echo teamStaffExportTextCell($staff['postal_code'] ?? '');
    echo teamStaffExportTextCell(!empty($staff['country']) ? $staff['country'] : 'Indonesia');
    echo '<td>' . $staff['certificate_count'] . '</td>';
    echo teamStaffExportTextCell($staff['is_active'] ? 'Aktif' : 'Non-Aktif');
    echo '<td>' . date('d/m/Y H:i', strtotime($staff['created_at'])) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
?>
