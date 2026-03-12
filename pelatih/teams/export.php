<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'pelatih') {
    header('Location: ../../login.php');
    exit;
}

require_once '../config/database.php';

$search = trim((string)($_GET['search'] ?? ''));

function pelatihExportValue($value, string $fallback = '-'): string
{
    $text = trim((string)$value);
    return $text !== '' ? $text : $fallback;
}

function pelatihExportDate($value, string $format = 'd/m/Y', string $fallback = '-'): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return $fallback;
    }

    $timestamp = strtotime($text);
    return $timestamp ? date($format, $timestamp) : $text;
}

try {
    $query = "SELECT t.*,
                     (SELECT COUNT(*) FROM players p WHERE p.team_id = t.id AND p.status = 'active') AS player_count,
                     (SELECT COUNT(*) FROM team_staff ts WHERE ts.team_id = t.id) AS staff_count,
                     (SELECT COUNT(*) FROM challenges c
                        WHERE (c.challenger_id = t.id OR c.opponent_id = t.id)
                          AND (c.status = 'accepted' OR c.status = 'completed')) AS match_count
              FROM teams t
              WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $searchTerm = '%' . $search . '%';
        $query .= " AND (t.name LIKE ? OR t.alias LIKE ? OR t.coach LIKE ? OR t.sport_type LIKE ?)";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }

    $query .= " ORDER BY t.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error fetching data: ' . $e->getMessage());
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="pelatih_team_export_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Team</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
echo '<style>td { border: 0.5pt solid #000000; padding: 5px; } th { border: 0.5pt solid #000000; padding: 8px; background-color: #f2f2f2; font-weight: bold; }</style>';
echo '</head>';
echo '<body>';
echo '<table border="1">';
echo '<thead><tr>';
echo '<th>No</th>';
echo '<th>Nama Team</th>';
echo '<th>Alias</th>';
echo '<th>Manager</th>';
echo '<th>Kategori</th>';
echo '<th>Pemain Aktif</th>';
echo '<th>Staf</th>';
echo '<th>Pertandingan</th>';
echo '<th>Tanggal Berdiri</th>';
echo '<th>Status</th>';
echo '<th>Tanggal Dibuat</th>';
echo '</tr></thead>';
echo '<tbody>';

$no = 1;
foreach ($teams as $team) {
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars(pelatihExportValue($team['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihExportValue($team['alias'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihExportValue($team['coach'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihExportValue($team['sport_type'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . (int)($team['player_count'] ?? 0) . '</td>';
    echo '<td>' . (int)($team['staff_count'] ?? 0) . '</td>';
    echo '<td>' . (int)($team['match_count'] ?? 0) . '</td>';
    echo '<td>' . htmlspecialchars(pelatihExportDate($team['established_year'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(!empty($team['is_active']) ? 'Aktif' : 'Non-Aktif', ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihExportDate($team['created_at'] ?? '', 'd/m/Y H:i'), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '</body></html>';
?>
