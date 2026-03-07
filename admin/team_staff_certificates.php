<?php
require_once __DIR__ . '/includes/auth_guard.php';

// Load database config
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

// Set header for JSON response
header('Content-Type: application/json');

// Get staff ID
$staff_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($staff_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID']);
    exit;
}

try {
    // Fetch certificates
    $stmt = $conn->prepare("
        SELECT certificate_name, certificate_file, issuing_authority, issue_date
        FROM staff_certificates
        WHERE staff_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$staff_id]);
    $certificates = array_map(static function (array $certificate): array {
        return [
            'certificate_name' => (string) ($certificate['certificate_name'] ?? ''),
            'certificate_file' => (string) ($certificate['certificate_file'] ?? ''),
            'issuing_authority' => (string) ($certificate['issuing_authority'] ?? ''),
            'issue_date' => isset($certificate['issue_date']) && $certificate['issue_date'] !== ''
                ? (string) $certificate['issue_date']
                : null,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    
    echo json_encode([
        'success' => true,
        'certificates' => $certificates,
        'count' => count($certificates)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
