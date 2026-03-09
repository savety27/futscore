<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'pelatih') {
    header('Location: ../login.php');
    exit;
}

require_once 'config/database.php';

$team_id = (int)($_SESSION['team_id'] ?? 0);
$filter_category = trim((string)($_GET['category'] ?? ''));
$filter_search = trim((string)($_GET['q'] ?? ''));

function pelatihFormatPlayerGender(string $gender): string
{
    if ($gender === 'L') {
        return 'Laki-laki';
    }

    if ($gender === 'P') {
        return 'Perempuan';
    }

    return $gender !== '' ? $gender : '-';
}

try {
    $query = "SELECT
                name,
                nik,
                nisn,
                jersey_number,
                position,
                sport_type,
                birth_place,
                birth_date,
                gender,
                height,
                weight,
                phone,
                email,
                nationality,
                street,
                city,
                province,
                postal_code,
                country,
                dominant_foot,
                status,
                created_at
              FROM players
              WHERE team_id = :team_id";

    $params = [':team_id' => $team_id];

    if ($filter_category !== '') {
        $query .= " AND sport_type = :sport_type";
        $params[':sport_type'] = $filter_category;
    }

    if ($filter_search !== '') {
        $query .= " AND (name LIKE :search_name OR jersey_number LIKE :search_jersey)";
        $search_like = '%' . $filter_search . '%';
        $params[':search_name'] = $search_like;
        $params[':search_jersey'] = $search_like;
    }

    $query .= " ORDER BY jersey_number ASC, name ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error fetching data: ' . $e->getMessage());
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="pelatih_players_export_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Pemain</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
echo '<style>td { border: 0.5pt solid #000000; padding: 5px; } th { border: 0.5pt solid #000000; padding: 8px; background-color: #f2f2f2; font-weight: bold; }</style>';
echo '</head>';
echo '<body>';
echo '<table border="1">';
echo '<thead><tr>';
echo '<th>No</th>';
echo '<th>Nama</th>';
echo '<th>NIK</th>';
echo '<th>NISN</th>';
echo '<th>No Punggung</th>';
echo '<th>Posisi</th>';
echo '<th>Kategori</th>';
echo '<th>Tempat Lahir</th>';
echo '<th>Tanggal Lahir</th>';
echo '<th>Jenis Kelamin</th>';
echo '<th>Tinggi</th>';
echo '<th>Berat</th>';
echo '<th>Telepon</th>';
echo '<th>Email</th>';
echo '<th>Kewarganegaraan</th>';
echo '<th>Alamat</th>';
echo '<th>Kota</th>';
echo '<th>Provinsi</th>';
echo '<th>Kode Pos</th>';
echo '<th>Negara</th>';
echo '<th>Kaki Dominan</th>';
echo '<th>Status</th>';
echo '<th>Tanggal Dibuat</th>';
echo '</tr></thead>';
echo '<tbody>';

$status_map = [
    'active' => 'Aktif',
    'inactive' => 'Non-aktif',
    'injured' => 'Cedera',
    'suspended' => 'Skorsing'
];

$no = 1;
foreach ($players as $player) {
    $status_key = strtolower(trim((string)($player['status'] ?? '')));
    $birth_date = trim((string)($player['birth_date'] ?? ''));
    $birth_date_label = $birth_date !== '' ? $birth_date : '-';
    $address = trim((string)($player['street'] ?? ''));

    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['name'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['nik'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['nisn'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['jersey_number'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['position'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['sport_type'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['birth_place'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars($birth_date_label) . '</td>';
    echo '<td>' . htmlspecialchars(pelatihFormatPlayerGender((string)($player['gender'] ?? ''))) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['height'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['weight'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['phone'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['email'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['nationality'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars($address !== '' ? $address : '-') . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['city'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['province'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['postal_code'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['country'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['dominant_foot'] ?? '')) . '</td>';
    echo '<td>' . htmlspecialchars($status_map[$status_key] ?? ($status_key !== '' ? ucfirst($status_key) : '-')) . '</td>';
    echo '<td>' . htmlspecialchars((string)($player['created_at'] ?? '')) . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '</body></html>';
