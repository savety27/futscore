<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$perangkat_id = isset($_GET['perangkat_id']) ? (int) $_GET['perangkat_id'] : 0;
if ($perangkat_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID perangkat tidak valid.']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $has_event_id_column = false;
    if ($result = $conn->query("SHOW COLUMNS FROM challenges LIKE 'event_id'")) {
        $has_event_id_column = $result->num_rows > 0;
        $result->close();
    }

    $has_match_official_column = false;
    if ($result = $conn->query("SHOW COLUMNS FROM challenges LIKE 'match_official'")) {
        $has_match_official_column = $result->num_rows > 0;
        $result->close();
    }

    $stmt_perangkat = $conn->prepare("SELECT id, name FROM perangkat WHERE id = ? LIMIT 1");
    if (!$stmt_perangkat) {
        throw new Exception('Gagal menyiapkan query perangkat.');
    }
    $stmt_perangkat->bind_param('i', $perangkat_id);
    $stmt_perangkat->execute();
    $perangkat_res = $stmt_perangkat->get_result();
    $perangkat = $perangkat_res ? $perangkat_res->fetch_assoc() : null;
    $stmt_perangkat->close();

    if (!$perangkat) {
        echo json_encode(['success' => false, 'message' => 'Perangkat tidak ditemukan.']);
        exit;
    }

    if (!$has_match_official_column) {
        echo json_encode([
            'success' => true,
            'perangkat_id' => $perangkat_id,
            'perangkat_name' => $perangkat['name'] ?? '',
            'total' => 0,
            'event_total' => 0,
            'event_summary' => [],
            'matches' => [],
        ]);
        exit;
    }

    $event_select = $has_event_id_column ? "TRIM(e.name) AS event_name," : "'' AS event_name,";
    $event_join = $has_event_id_column ? "LEFT JOIN events e ON c.event_id = e.id" : "";

    $sql = "
        SELECT
            c.id AS challenge_id,
            c.challenge_code,
            c.sport_type,
            c.challenge_date,
            c.status,
            c.challenger_score,
            c.opponent_score,
            c.challenger_id,
            c.opponent_id,
            t1.name AS challenger_name,
            t2.name AS opponent_name,
            $event_select
            c.match_official
        FROM challenges c
        $event_join
        LEFT JOIN teams t1 ON c.challenger_id = t1.id
        LEFT JOIN teams t2 ON c.opponent_id = t2.id
        WHERE c.match_official IS NOT NULL
          AND TRIM(c.match_official) <> ''
          AND (
            LOWER(TRIM(c.match_official)) = LOWER(TRIM(?))
            OR FIND_IN_SET(
                LOWER(TRIM(?)),
                REPLACE(REPLACE(LOWER(TRIM(c.match_official)), ', ', ','), ' ,', ',')
            ) > 0
          )
        ORDER BY c.challenge_date DESC, c.id DESC
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Gagal menyiapkan query riwayat pertandingan.');
    }
    $perangkat_name = trim((string)($perangkat['name'] ?? ''));
    $stmt->bind_param('ss', $perangkat_name, $perangkat_name);
    $stmt->execute();
    $res = $stmt->get_result();

    $matches = [];
    $event_counter = [];

    while ($row = $res->fetch_assoc()) {
        $challenge_date_fmt = '-';
        if (!empty($row['challenge_date'])) {
            $timestamp = strtotime((string)$row['challenge_date']);
            if ($timestamp !== false) {
                $bulan = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agt', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
                $month_num = (int) date('n', $timestamp);
                $challenge_date_fmt = date('d', $timestamp) . ' ' . ($bulan[$month_num] ?? date('M', $timestamp)) . ' ' . date('Y H:i', $timestamp);
            }
        }

        $event_name = trim((string)($row['event_name'] ?? ''));
        if ($event_name !== '') {
            if (!isset($event_counter[$event_name])) {
                $event_counter[$event_name] = 0;
            }
            $event_counter[$event_name]++;
        }

        $matches[] = [
            'challenge_id' => (int)($row['challenge_id'] ?? 0),
            'challenge_code' => (string)($row['challenge_code'] ?? ''),
            'event_name' => $event_name,
            'sport_type' => (string)($row['sport_type'] ?? ''),
            'challenge_date' => (string)($row['challenge_date'] ?? ''),
            'challenge_date_fmt' => $challenge_date_fmt,
            'status' => (string)($row['status'] ?? ''),
            'challenger_name' => (string)($row['challenger_name'] ?? ''),
            'opponent_name' => (string)($row['opponent_name'] ?? ''),
            'challenger_score' => $row['challenger_score'] !== null ? (int)$row['challenger_score'] : null,
            'opponent_score' => $row['opponent_score'] !== null ? (int)$row['opponent_score'] : null,
        ];
    }

    $stmt->close();
    $conn->close();

    $event_summary = [];
    foreach ($event_counter as $event_name => $match_count) {
        $event_summary[] = [
            'name' => $event_name,
            'match_count' => (int)$match_count,
        ];
    }
    usort($event_summary, function ($a, $b) {
        return $b['match_count'] <=> $a['match_count'];
    });

    echo json_encode([
        'success' => true,
        'perangkat_id' => $perangkat_id,
        'perangkat_name' => $perangkat_name,
        'total' => count($matches),
        'event_total' => count($event_summary),
        'event_summary' => $event_summary,
        'matches' => $matches,
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal memuat riwayat pertandingan perangkat.']);
}

