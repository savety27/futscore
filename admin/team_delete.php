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

// Set header for JSON response
header('Content-Type: application/json');

// Get team ID
$team_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($team_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid team ID']);
    exit;
}

try {
    // Start transaction
    $conn->beginTransaction();
    
    // Check if team has players
    $stmt = $conn->prepare("SELECT COUNT(*) as player_count FROM players WHERE team_id = ?");
    $stmt->execute([$team_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['player_count'] > 0) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus team yang masih memiliki players. Pindahkan players terlebih dahulu.']);
        exit;
    }
    
    // Check if team has staff
    $stmt = $conn->prepare("SELECT COUNT(*) as staff_count FROM team_staff WHERE team_id = ?");
    $stmt->execute([$team_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['staff_count'] > 0) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Tidak dapat menghapus team yang masih memiliki staff. Pindahkan staff terlebih dahulu.']);
        exit;
    }
    
    // Get team logo path for deletion
    $stmt = $conn->prepare("SELECT logo FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$team) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Team tidak ditemukan']);
        exit;
    }
    
    // Delete team
    $stmt = $conn->prepare("DELETE FROM teams WHERE id = ?");
    $success = $stmt->execute([$team_id]);
    
    if ($success) {
        // Delete team logo if exists
        if (!empty($team['logo'])) {
            $logo_path = $team['logo'];
            // Normalize path: handle both bare filenames and images/teams/ prefixed paths
            if (strpos($logo_path, 'images/teams/') === false) {
                $full_path = '../images/teams/' . $logo_path;
            } else {
                $full_path = '../' . $logo_path;
            }
            
            if (file_exists($full_path)) {
                @unlink($full_path);
            }
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Team berhasil dihapus']);
    } else {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus team']);
    }
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
