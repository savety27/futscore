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

$query = "SELECT tr.id,
                 tr.player_id,
                 tr.from_team_id,
                 tr.to_team_id,
                 tr.transfer_date,
                 p.name AS player_name,
                 t1.name AS from_team_name,
                 t2.name AS to_team_name
          FROM transfers tr
          LEFT JOIN players p ON tr.player_id = p.id
          LEFT JOIN teams t1 ON tr.from_team_id = t1.id
          LEFT JOIN teams t2 ON tr.to_team_id = t2.id
          WHERE 1=1";

$params = [];
if ($search !== '') {
    $search_term = "%{$search}%";
    $query .= " AND (
        p.name LIKE ?
        OR t1.name LIKE ?
        OR t2.name LIKE ?
        OR DATE_FORMAT(tr.transfer_date, '%d/%m/%Y') LIKE ?
    )";
    $params = [$search_term, $search_term, $search_term, $search_term];
}

$query .= " ORDER BY tr.transfer_date DESC, tr.id DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="transfer_export_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');
header('Pragma: public');

echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
echo '<style>';
echo 'td { border: 0.5pt solid #000000; padding: 5px; font-family: Arial, sans-serif; }';
echo 'th { border: 0.5pt solid #000000; padding: 8px; background-color: #f2f2f2; font-weight: bold; font-family: Arial, sans-serif; }';
echo '</style>';
echo '</head>';
echo '<body>';

echo '<table border="1" cellpadding="0" cellspacing="0">';
echo '<thead>';
echo '<tr>';
echo '<th>No</th>';
echo '<th>Nama Pemain</th>';
echo '<th>Tim Asal</th>';
echo '<th>Tim Tujuan</th>';
echo '<th>Tanggal Transfer</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
foreach ($transfers as $tr) {
    $playerName = !empty($tr['player_name']) ? $tr['player_name'] : 'Unknown';
    $fromTeam = !empty($tr['from_team_name']) ? $tr['from_team_name'] : 'Free Agent';
    $toTeam = !empty($tr['to_team_name']) ? $tr['to_team_name'] : 'Free Agent';
    $transferDate = !empty($tr['transfer_date']) ? date('d/m/Y', strtotime($tr['transfer_date'])) : '-';

    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars((string) $playerName, ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $fromTeam, ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $toTeam, ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars((string) $transferDate, ENT_QUOTES, 'UTF-8') . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
?>
