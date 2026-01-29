<?php
// includes/ajax_get_certificates.php
header('Content-Type: application/json');

// Start output buffering
ob_start();

try {
    // Check if staff_id is provided
    if (!isset($_GET['staff_id']) || !is_numeric($_GET['staff_id'])) {
        throw new Exception('Invalid staff ID');
    }
    
    $staff_id = (int)$_GET['staff_id'];
    
    // Include database configuration
    require_once __DIR__ . '/header.php';
    
    // Get database connection
    $conn = $db->getConnection();
    
    // Query to get certificates
    $query = "SELECT * FROM staff_certificates WHERE staff_id = ? ORDER BY issue_date DESC, created_at DESC";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $staff_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $certificates = [];
    
    while ($row = $result->fetch_assoc()) {
        // Clean file path
        $certificate_file = $row['certificate_file'] ?? '';
        if ($certificate_file) {
            // Extract just filename from path
            $certificate_file = basename($certificate_file);
        }
        
        $certificates[] = [
            'id' => $row['id'],
            'staff_id' => $row['staff_id'],
            'certificate_name' => $row['certificate_name'] ?? 'Unnamed Certificate',
            'certificate_file' => $certificate_file,
            'issue_date' => $row['issue_date'] ?? null,
            'issuing_authority' => $row['issuing_authority'] ?? null,
            'created_at' => $row['created_at'] ?? null
        ];
    }
    
    $stmt->close();
    
    // Clear any output
    ob_end_clean();
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'certificates' => $certificates,
        'count' => count($certificates)
    ]);
    
} catch (Exception $e) {
    // Clear any output
    ob_end_clean();
    
    // Return error as JSON
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;
?>