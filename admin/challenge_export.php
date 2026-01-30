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

// Query untuk mengambil semua data challenges
$query = "SELECT c.*, 
          t1.name as challenger_name, t1.sport_type as challenger_sport,
          t2.name as opponent_name,
          v.name as venue_name, v.location as venue_location
          FROM challenges c
          LEFT JOIN teams t1 ON c.challenger_id = t1.id
          LEFT JOIN teams t2 ON c.opponent_id = t2.id
          LEFT JOIN venues v ON c.venue_id = v.id
          WHERE 1=1";

if (!empty($search)) {
    $search_term = "%{$search}%";
    $query .= " AND (c.challenge_code LIKE ? OR c.status LIKE ? 
              OR t1.name LIKE ? OR t2.name LIKE ?)";
}

$query .= " ORDER BY c.challenge_date DESC";

try {
    if (!empty($search)) {
        $stmt = $conn->prepare($query);
        $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->execute();
    }
    
    $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="challenges_export_' . date('Y-m-d_H-i-s') . '.xls"');
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
echo '<x:Name>Challenges Data</x:Name>';
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
echo '<th>Kode Challenge</th>';
echo '<th>Status</th>';
echo '<th>Challenger</th>';
echo '<th>Opponent</th>';
echo '<th>Venue</th>';
echo '<th>Tanggal & Waktu</th>';
echo '<th>Expiry Date</th>';
echo '<th>Cabor</th>';
echo '<th>Match Status</th>';
echo '<th>Skor Challenger</th>';
echo '<th>Skor Opponent</th>';
echo '<th>Pemenang</th>';
echo '<th>Catatan</th>';
echo '<th>Dibuat Pada</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$no = 1;
foreach ($challenges as $challenge) {
    // Determine winner name
    $winner = '';
    if ($challenge['challenger_score'] !== null && $challenge['opponent_score'] !== null) {
        if ($challenge['challenger_score'] > $challenge['opponent_score']) {
            $winner = $challenge['challenger_name'];
        } elseif ($challenge['opponent_score'] > $challenge['challenger_score']) {
            $winner = $challenge['opponent_name'];
        } else {
            $winner = 'Seri';
        }
    }
    
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars($challenge['challenge_code']) . '</td>';
    echo '<td>' . htmlspecialchars($challenge['status']) . '</td>';
    echo '<td>' . htmlspecialchars($challenge['challenger_name']) . '</td>';
    echo '<td>' . htmlspecialchars($challenge['opponent_name'] ?? 'TBD') . '</td>';
    echo '<td>' . htmlspecialchars($challenge['venue_name'] ?? '-') . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($challenge['challenge_date'])) . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($challenge['expiry_date'])) . '</td>';
    echo '<td>' . htmlspecialchars($challenge['sport_type']) . '</td>';
    echo '<td>' . htmlspecialchars($challenge['match_status'] ?? 'Belum Mulai') . '</td>';
    echo '<td>' . ($challenge['challenger_score'] !== null ? $challenge['challenger_score'] : '-') . '</td>';
    echo '<td>' . ($challenge['opponent_score'] !== null ? $challenge['opponent_score'] : '-') . '</td>';
    echo '<td>' . $winner . '</td>';
    echo '<td>' . htmlspecialchars($challenge['notes'] ?? '-') . '</td>';
    echo '<td>' . date('d/m/Y H:i', strtotime($challenge['created_at'])) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</body>';
echo '</html>';
?>