<?php
header('Content-Type: application/json');
ob_start();

try {
    if (!isset($_GET['perangkat_id']) || !is_numeric($_GET['perangkat_id'])) {
        throw new Exception('Invalid perangkat ID');
    }

    $perangkatId = (int) $_GET['perangkat_id'];
    if ($perangkatId <= 0) {
        throw new Exception('Invalid perangkat ID');
    }

    require_once __DIR__ . '/functions.php';
    global $db;

    if (!isset($db) || !is_object($db) || !method_exists($db, 'getConnection')) {
        throw new Exception('Database connection is not available');
    }

    $conn = $db->getConnection();
    $query = "SELECT id, perangkat_id, license_name, license_file, issuing_authority, issue_date, created_at
              FROM perangkat_licenses
              WHERE perangkat_id = ?
              ORDER BY issue_date DESC, created_at DESC, id DESC";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception('Failed to prepare query');
    }

    $stmt->bind_param("i", $perangkatId);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query');
    }

    $result = $stmt->get_result();
    $licenses = [];

    while ($row = $result->fetch_assoc()) {
        $licenseFile = $row['license_file'] ?? '';
        if ($licenseFile !== '') {
            $licenseFile = basename($licenseFile);
        }

        // Keep key names aligned with existing front-end renderer.
        $licenses[] = [
            'id' => (int) ($row['id'] ?? 0),
            'staff_id' => (int) ($row['perangkat_id'] ?? 0),
            'certificate_name' => $row['license_name'] ?? 'Lisensi',
            'certificate_file' => $licenseFile,
            'issue_date' => $row['issue_date'] ?? null,
            'issuing_authority' => $row['issuing_authority'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    $stmt->close();
    ob_end_clean();

    echo json_encode([
        'success' => true,
        'certificates' => $licenses,
        'count' => count($licenses),
    ]);
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

exit;
?>
