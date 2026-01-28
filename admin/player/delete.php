<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$player_id = (int)$_GET['id'];

try {
    // First get player data to delete files
    $stmt = $conn->prepare("SELECT photo, ktp_image, kk_image, birth_cert_image, diploma_image FROM players WHERE id = ?");
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($player) {
        // Delete files from server
        $upload_dir = '../../images/players/';
        
        $files_to_delete = [
            $player['photo'],
            $player['ktp_image'],
            $player['kk_image'],
            $player['birth_cert_image'],
            $player['diploma_image']
        ];
        
        foreach ($files_to_delete as $file) {
            if (!empty($file)) {
                // Cek berbagai kemungkinan lokasi file
                $possible_paths = [
                    $upload_dir . $file,
                    '../../' . $file,
                    'images/players/' . $file,
                    'uploads/players/' . $file,
                    $file
                ];
                
                foreach ($possible_paths as $file_path) {
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                        break;
                    }
                }
            }
        }
    }
    
    // HARD DELETE (benar-benar hapus dari database)
    $stmt = $conn->prepare("DELETE FROM players WHERE id = ?");
    $stmt->execute([$player_id]);
    
    // Juga hapus dari tabel player_documents dan player_skills jika ada
    try {
        $stmt = $conn->prepare("DELETE FROM player_documents WHERE player_id = ?");
        $stmt->execute([$player_id]);
    } catch (Exception $e) {
        // Table mungkin tidak ada, tidak perlu khawatir
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM player_skills WHERE player_id = ?");
        $stmt->execute([$player_id]);
    } catch (Exception $e) {
        // Table mungkin tidak ada, tidak perlu khawatir
    }
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Player berhasil dihapus permanen']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Player tidak ditemukan']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>