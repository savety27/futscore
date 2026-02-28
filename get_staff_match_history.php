<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$staff_id = isset($_GET['staff_id']) ? (int) $_GET['staff_id'] : 0;
if ($staff_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID staff tidak valid.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $has_staff_assignment_table = false;
    if ($result = $conn->query("SHOW TABLES LIKE 'match_staff_assignments'")) {
        $has_staff_assignment_table = $result->num_rows > 0;
        $result->close();
    }
    if (!$has_staff_assignment_table) {
        echo json_encode([
            'success' => false,
            'message' => 'Fitur assignment staff belum aktif. Jalankan migrasi match_staff_assignments.'
        ]);
        exit;
    }

    $has_event_id_column = false;
    if ($result = $conn->query("SHOW COLUMNS FROM challenges LIKE 'event_id'")) {
        $has_event_id_column = $result->num_rows > 0;
        $result->close();
    }
    $has_match_status_column = false;
    if ($result = $conn->query("SHOW COLUMNS FROM challenges LIKE 'match_status'")) {
        $has_match_status_column = $result->num_rows > 0;
        $result->close();
    }
    $has_staff_half_column = false;
    if ($result = $conn->query("SHOW COLUMNS FROM match_staff_assignments LIKE 'half'")) {
        $has_staff_half_column = $result->num_rows > 0;
        $result->close();
    }

    $event_name_select = $has_event_id_column
        ? "TRIM(COALESCE(NULLIF(e.name, ''), NULLIF(c.sport_type, ''))) AS event_name,"
        : "TRIM(c.sport_type) AS event_name,";
    $event_join = $has_event_id_column ? "LEFT JOIN events e ON c.event_id = e.id" : "";
    $status_select = $has_match_status_column
        ? "COALESCE(NULLIF(c.match_status, ''), c.status) AS status,"
        : "c.status AS status,";
    $half_select = $has_staff_half_column
        ? "COALESCE(msa.half, 1) AS half,"
        : "1 AS half,";
    $order_suffix = $has_staff_half_column
        ? "ORDER BY c.challenge_date DESC, COALESCE(msa.half, 1) ASC, msa.id DESC"
        : "ORDER BY c.challenge_date DESC, msa.id DESC";

    $sql = "
        SELECT
            msa.staff_id,
            msa.role,
            $event_name_select
            c.id AS challenge_id,
            c.challenge_code,
            c.sport_type,
            c.challenge_date,
            $status_select
            $half_select
            c.challenger_id,
            c.opponent_id,
            t1.name AS challenger_name,
            t2.name AS opponent_name,
            ts.team_id AS staff_team_id
        FROM match_staff_assignments msa
        INNER JOIN challenges c ON msa.match_id = c.id
        $event_join
        INNER JOIN teams t1 ON c.challenger_id = t1.id
        INNER JOIN teams t2 ON c.opponent_id = t2.id
        INNER JOIN team_staff ts ON msa.staff_id = ts.id
        WHERE msa.staff_id = ?
        $order_suffix
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Gagal menyiapkan query.');
    }
    $stmt->bind_param('i', $staff_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $matches = [];
    $event_counter = [];
    while ($row = $res->fetch_assoc()) {
        $challenge_date_fmt = '-';
        if (!empty($row['challenge_date'])) {
            $timestamp = strtotime($row['challenge_date']);
            if ($timestamp !== false) {
                $bulan = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agt', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
                $month_num = (int) date('n', $timestamp);
                $challenge_date_fmt = date('d', $timestamp) . ' ' . ($bulan[$month_num] ?? date('M', $timestamp)) . ' ' . date('Y H:i', $timestamp);
            }
        }

        $event_name = trim((string) ($row['event_name'] ?? ''));
        $challenge_id = (int)($row['challenge_id'] ?? 0);
        if ($event_name !== '' && $challenge_id > 0) {
            if (!isset($event_counter[$event_name])) {
                $event_counter[$event_name] = [];
            }
            $event_counter[$event_name][$challenge_id] = true;
        }

        $half = (int)($row['half'] ?? 1);
        $half_label = $half === 2 ? 'Babak 2' : 'Babak 1';

        $matches[] = [
            'challenge_id' => $challenge_id,
            'challenge_code' => $row['challenge_code'] ?? '',
            'event_name' => $event_name,
            'sport_type' => $row['sport_type'] ?? '',
            'challenge_date' => $row['challenge_date'] ?? '',
            'challenge_date_fmt' => $challenge_date_fmt,
            'status' => $row['status'] ?? '',
            'half' => $half,
            'half_label' => $half_label,
            'challenger_id' => (int) $row['challenger_id'],
            'opponent_id' => (int) $row['opponent_id'],
            'challenger_name' => $row['challenger_name'] ?? '',
            'opponent_name' => $row['opponent_name'] ?? '',
            'role' => $row['role'] ?? '',
        ];
    }

    $stmt->close();
    $conn->close();

    $event_summary = [];
    foreach ($event_counter as $event_name => $match_map) {
        $event_summary[] = [
            'name' => $event_name,
            'match_count' => count($match_map),
        ];
    }
    usort($event_summary, function ($a, $b) {
        return $b['match_count'] <=> $a['match_count'];
    });

    echo json_encode([
        'success' => true,
        'staff_id' => $staff_id,
        'total' => count($matches),
        'event_total' => count($event_summary),
        'event_summary' => $event_summary,
        'matches' => $matches,
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal memuat riwayat staff.']);
}
