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

// Query untuk mengambil semua data pelatih dengan JOIN ke tabel teams
$query = "SELECT au.*, t.name as team_name 
          FROM admin_users au 
          LEFT JOIN teams t ON au.team_id = t.id 
          WHERE 1=1";

if (!empty($search)) {
    $search_term = "%{$search}%";
    $query .= " AND (au.username LIKE ? OR au.email LIKE ? OR au.full_name LIKE ? OR au.role LIKE ? OR t.name LIKE ?)";
}

if ($filter_active !== '') {
    $query .= " AND au.is_active = ?";
}

$query .= " ORDER BY au.created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $params = [];
    if (!empty($search)) {
        $params = [$search_term, $search_term, $search_term, $search_term, $search_term];
    }
    if ($filter_active !== '') {
        $params[] = (int) $filter_active;
    }
    $stmt->execute($params);
    
    $pelatih = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="pelatih_export_' . date('Y-m-d_H-i-s') . '.xls"');
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
echo '<x:Name>Data Pelatih</x:Name>';
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
echo '.superadmin { background-color: #FFE5B4; }';
echo '.admin { background-color: #E6F3FF; }';
echo '.editor { background-color: #F0F0F0; }';
echo '.status-active { color: #006400; font-weight: bold; }';
echo '.status-inactive { color: #8B0000; font-weight: bold; }';
echo '</style>';
echo '</head>';
echo '<body>';

echo '<h2 style="text-align: center; margin-bottom: 20px;">Data Pelatih / Admin</h2>';
echo '<p style="margin-bottom: 15px;">Tanggal Export: ' . date('d/m/Y H:i:s') . '</p>';

echo '<table border="1">';
echo '<thead>';
echo '<tr>';
echo '<th>No</th>';
echo '<th>Username</th>';
echo '<th>Email</th>';
echo '<th>Nama Lengkap</th>';
echo '<th>Role</th>';
echo '<th>Tim</th>';
echo '<th>Status</th>';
echo '<th>Login Terakhir</th>';
echo '<th>Tanggal Dibuat</th>';
echo '<th>Terakhir Update</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
foreach ($pelatih as $p) {
    // Tentukan class berdasarkan role
    $role_class = '';
    if ($p['role'] === 'superadmin') {
        $role_class = 'superadmin';
    } elseif ($p['role'] === 'admin') {
        $role_class = 'admin';
    } else {
        $role_class = 'editor';
    }
    
    // Tentukan class berdasarkan status
    $status_class = $p['is_active'] ? 'status-active' : 'status-inactive';
    
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars($p['username'] ?? '') . '</td>';
    echo '<td>' . htmlspecialchars($p['email'] ?? '') . '</td>';
    echo '<td>' . (!empty($p['full_name']) ? htmlspecialchars($p['full_name']) : '-') . '</td>';
    echo '<td class="' . $role_class . '">';
    if ($p['role'] === 'superadmin') {
        echo 'Super Admin';
    } elseif ($p['role'] === 'admin') {
        echo 'Admin';
    } elseif ($p['role'] === 'editor') {
        echo 'Editor';
    } else {
        echo htmlspecialchars($p['role'] ?? '');
    }
    echo '</td>';
    echo '<td>' . (!empty($p['team_name']) ? htmlspecialchars($p['team_name']) : '-') . '</td>';
    echo '<td class="' . $status_class . '">' . ($p['is_active'] ? 'Aktif' : 'Non-Aktif') . '</td>';
    echo '<td>' . (!empty($p['last_login']) ? date('d/m/Y H:i', strtotime($p['last_login'])) : '-') . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($p['created_at'])) . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($p['updated_at'])) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

// Tambahkan summary
echo '<div style="margin-top: 30px;">';
echo '<h3>Ringkasan Data:</h3>';
echo '<table border="0" cellpadding="5">';
echo '<tr>';
echo '<td><strong>Total Data:</strong></td>';
echo '<td>' . count($pelatih) . ' pelatih/admin</td>';
echo '</tr>';

// Hitung per role
$role_counts = ['superadmin' => 0, 'admin' => 0, 'editor' => 0, 'other' => 0];
$status_counts = ['active' => 0, 'inactive' => 0];

foreach ($pelatih as $p) {
    if (isset($role_counts[$p['role']])) {
        $role_counts[$p['role']]++;
    } else {
        $role_counts['other']++;
    }
    
    if ($p['is_active']) {
        $status_counts['active']++;
    } else {
        $status_counts['inactive']++;
    }
}

echo '<tr>';
echo '<td><strong>Super Admin:</strong></td>';
echo '<td>' . $role_counts['superadmin'] . '</td>';
echo '</tr>';
echo '<tr>';
echo '<td><strong>Admin:</strong></td>';
echo '<td>' . $role_counts['admin'] . '</td>';
echo '</tr>';
echo '<tr>';
echo '<td><strong>Status Aktif:</strong></td>';
echo '<td>' . $status_counts['active'] . '</td>';
echo '</tr>';
echo '<tr>';
echo '<td><strong>Status Non-Aktif:</strong></td>';
echo '<td>' . $status_counts['inactive'] . '</td>';
echo '</tr>';
echo '</table>';
echo '</div>';

echo '</body>';
echo '</html>';
