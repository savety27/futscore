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
    $query .= " AND (ts.name LIKE ? OR ts.email LIKE ? OR ts.phone LIKE ? OR ts.position LIKE ? OR t.name LIKE ?)";
}

$query .= " ORDER BY ts.created_at DESC";

try {
    if (!empty($search)) {
        $stmt = $conn->prepare($query);
        $stmt->execute([$search_term, $search_term, $search_term, $search_term, $search_term]);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->execute();
    }
    
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
        
        // Format position
        $position_labels = [
            'manager' => 'Manager',
            'headcoach' => 'Head Coach',
            'coach' => 'Coach',
            'goalkeeper_coach' => 'GK Coach',
            'medic' => 'Medic',
            'official' => 'Official'
        ];
        $staff['position_formatted'] = $position_labels[$staff['position']] ?? ucfirst($staff['position']);
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
    echo '<td>' . htmlspecialchars($staff['name'] ?? '') . '</td>';
    echo '<td>' . $staff['position_formatted'] . '</td>';
    echo '<td>' . htmlspecialchars($staff['team_name'] ?? '') . '</td>';
    echo '<td>' . (!empty($staff['email']) ? htmlspecialchars($staff['email']) : '-') . '</td>';
    echo '<td>' . (!empty($staff['phone']) ? htmlspecialchars($staff['phone']) : '-') . '</td>';
    echo '<td>' . (!empty($staff['birth_place']) ? htmlspecialchars($staff['birth_place']) : '-') . '</td>';
    echo '<td>' . (!empty($staff['birth_date']) ? date('d/m/Y', strtotime($staff['birth_date'])) : '-') . '</td>';
    echo '<td>' . $staff['age'] . '</td>';
    echo '<td>' . (!empty($staff['address']) ? htmlspecialchars($staff['address']) : '-') . '</td>';
    echo '<td>' . (!empty($staff['city']) ? htmlspecialchars($staff['city']) : '-') . '</td>';
    echo '<td>' . (!empty($staff['province']) ? htmlspecialchars($staff['province']) : '-') . '</td>';
    echo '<td>' . (!empty($staff['postal_code']) ? htmlspecialchars($staff['postal_code']) : '-') . '</td>';
    echo '<td>' . (!empty($staff['country']) ? htmlspecialchars($staff['country']) : 'Indonesia') . '</td>';
    echo '<td>' . $staff['certificate_count'] . '</td>';
    echo '<td>' . ($staff['is_active'] ? 'Aktif' : 'Non-Aktif') . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($staff['created_at'])) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
?>