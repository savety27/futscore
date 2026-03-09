<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'pelatih') {
    header('Location: ../login.php');
    exit;
}

require_once 'config/database.php';

$my_team_id = (int)($_SESSION['team_id'] ?? 0);
if ($my_team_id === 0) {
    $pelatih_id = (int)($_SESSION['pelatih_id'] ?? 0);
    try {
        $stmtTeam = $conn->prepare("SELECT team_id FROM team_staff WHERE id = ?");
        $stmtTeam->execute([$pelatih_id]);
        $staff = $stmtTeam->fetch(PDO::FETCH_ASSOC);
        $my_team_id = (int)($staff['team_id'] ?? 0);
        if ($my_team_id > 0) {
            $_SESSION['team_id'] = $my_team_id;
        }
    } catch (PDOException $e) {
        $my_team_id = 0;
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$sport_filter = trim((string)($_GET['sport'] ?? ''));
$has_challenge_event_id = false;
$has_events_table = false;
$can_join_event_name = false;

function pelatihScheduleExportValue($value, string $fallback = '-'): string
{
    $text = trim((string)$value);
    return $text !== '' ? $text : $fallback;
}

function pelatihScheduleExportDate($value, string $format, string $fallback = '-'): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return $fallback;
    }

    $timestamp = strtotime($text);
    return $timestamp ? date($format, $timestamp) : $text;
}

function pelatihScheduleWinner(array $challenge): string
{
    $winnerTeamId = (int)($challenge['winner_team_id'] ?? 0);
    if ($winnerTeamId > 0) {
        if ($winnerTeamId === (int)($challenge['challenger_id'] ?? 0)) {
            return pelatihScheduleExportValue($challenge['challenger_name'] ?? '');
        }
        if ($winnerTeamId === (int)($challenge['opponent_id'] ?? 0)) {
            return pelatihScheduleExportValue($challenge['opponent_name'] ?? '');
        }
    }

    $challengerScore = $challenge['challenger_score'] ?? null;
    $opponentScore = $challenge['opponent_score'] ?? null;
    if ($challengerScore === null || $opponentScore === null || $challengerScore === '' || $opponentScore === '') {
        return '-';
    }

    if ((int)$challengerScore > (int)$opponentScore) {
        return pelatihScheduleExportValue($challenge['challenger_name'] ?? '');
    }

    if ((int)$opponentScore > (int)$challengerScore) {
        return pelatihScheduleExportValue($challenge['opponent_name'] ?? '');
    }

    return 'Seri';
}

try {
    $checkEventCol = $conn->query("SHOW COLUMNS FROM challenges LIKE 'event_id'");
    $has_challenge_event_id = $checkEventCol && $checkEventCol->rowCount() > 0;
} catch (PDOException $e) {
    $has_challenge_event_id = false;
}

try {
    $checkEventsTbl = $conn->query("SHOW TABLES LIKE 'events'");
    $has_events_table = $checkEventsTbl && $checkEventsTbl->rowCount() > 0;
} catch (PDOException $e) {
    $has_events_table = false;
}

$can_join_event_name = $has_challenge_event_id && $has_events_table;

try {
    $query = "SELECT c.*,
                     " . ($can_join_event_name
                        ? "TRIM(COALESCE(NULLIF(e.name, ''), NULLIF(c.sport_type, ''))) AS event_name,"
                        : "TRIM(c.sport_type) AS event_name,") . "
                     t1.name AS challenger_name,
                     t2.name AS opponent_name,
                     v.name AS venue_name
              FROM challenges c
              " . ($can_join_event_name ? "LEFT JOIN events e ON c.event_id = e.id" : "") . "
              LEFT JOIN teams t1 ON c.challenger_id = t1.id
              LEFT JOIN teams t2 ON c.opponent_id = t2.id
              LEFT JOIN venues v ON c.venue_id = v.id
              WHERE (c.challenger_id = ? OR c.opponent_id = ?)";
    $params = [$my_team_id, $my_team_id];

    if ($search !== '') {
        $searchTerm = '%' . $search . '%';
        $query .= " AND (c.challenge_code LIKE ?
                    OR t1.name LIKE ?
                    OR t2.name LIKE ?
                    OR c.sport_type LIKE ? " . ($can_join_event_name ? "OR e.name LIKE ? " : "") . "
                    OR c.status LIKE ?
                    OR c.match_status LIKE ?)";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        if ($can_join_event_name) {
            $params[] = $searchTerm;
        }
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($sport_filter !== '') {
        $query .= " AND c.sport_type = ?";
        $params[] = $sport_filter;
    }

    $query .= " ORDER BY c.challenge_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error fetching data: ' . $e->getMessage());
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="pelatih_schedule_export_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Jadwal</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
echo '<style>td { border: 0.5pt solid #000000; padding: 5px; } th { border: 0.5pt solid #000000; padding: 8px; background-color: #f2f2f2; font-weight: bold; }</style>';
echo '</head>';
echo '<body>';
echo '<table border="1">';
echo '<thead><tr>';
echo '<th>No</th>';
echo '<th>Kode Pertandingan</th>';
echo '<th>Tanggal</th>';
echo '<th>Waktu</th>';
echo '<th>Team Challenger</th>';
echo '<th>Team Opponent</th>';
echo '<th>Event</th>';
echo '<th>Kategori</th>';
echo '<th>Lokasi</th>';
echo '<th>Status Challenge</th>';
echo '<th>Status Pertandingan</th>';
echo '<th>Skor Challenger</th>';
echo '<th>Skor Opponent</th>';
echo '<th>Pemenang</th>';
echo '<th>Catatan</th>';
echo '<th>Dibuat Pada</th>';
echo '</tr></thead>';
echo '<tbody>';

$no = 1;
foreach ($challenges as $challenge) {
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportValue($challenge['challenge_code'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportDate($challenge['challenge_date'] ?? '', 'd/m/Y'), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportDate($challenge['challenge_date'] ?? '', 'H:i'), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportValue($challenge['challenger_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportValue($challenge['opponent_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportValue($challenge['event_name'] ?? ($challenge['sport_type'] ?? '')), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportValue($challenge['sport_type'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportValue($challenge['venue_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportValue($challenge['status'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportValue($challenge['match_status'] ?? '', 'Belum Mulai'), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportValue($challenge['challenger_score'] ?? '', '-'), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportValue($challenge['opponent_score'] ?? '', '-'), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleWinner($challenge), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportValue($challenge['notes'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td>' . htmlspecialchars(pelatihScheduleExportDate($challenge['created_at'] ?? '', 'd/m/Y H:i'), ENT_QUOTES, 'UTF-8') . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '</body></html>';
?>
