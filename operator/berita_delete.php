<?php
session_start();
header('Content-Type: application/json');

// Load database config
$config_path = __DIR__ . '/../admin/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    echo json_encode(['success' => false, 'message' => 'Database configuration not found']);
    exit;
}

if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_role'] ?? '') !== 'operator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get berita ID
$berita_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$operator_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_username = trim((string)($_SESSION['admin_username'] ?? ''));

if ($berita_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid berita ID']);
    exit;
}

if (!function_exists('adminHasColumn')) {
    function adminHasColumn(PDO $conn, $tableName, $columnName) {
        try {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $tableName);
            if ($safeTable === '') {
                return false;
            }
            $quotedColumn = $conn->quote((string) $columnName);
            $stmt = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE {$quotedColumn}");
            return $stmt && $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

try {
    $berita_has_created_by = adminHasColumn($conn, 'berita', 'created_by');
    $ownership_where_sql = '';
    $ownership_params = [];
    if ($berita_has_created_by && $operator_id > 0) {
        $ownership_where_sql = " AND created_by = ?";
        $ownership_params[] = $operator_id;
    } else {
        $ownership_where_sql = " AND (penulis = ? OR penulis = ?)";
        $ownership_params[] = $admin_name;
        $ownership_params[] = $admin_username;
    }

    // Get gambar path before deletion
    $stmt = $conn->prepare("SELECT gambar FROM berita WHERE id = ?" . $ownership_where_sql);
    $stmt->execute(array_merge([$berita_id], $ownership_params));
    $berita = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$berita) {
        echo json_encode(['success' => false, 'message' => 'Berita not found or no permission']);
        exit;
    }
    
    // Delete the berita
    $stmt = $conn->prepare("DELETE FROM berita WHERE id = ?" . $ownership_where_sql);
    $stmt->execute(array_merge([$berita_id], $ownership_params));
    
    // Delete associated image if exists
    $gambar = trim((string)($berita['gambar'] ?? ''));
    if ($gambar !== '' && file_exists('../images/berita/' . $gambar)) {
        @unlink('../images/berita/' . $gambar);
    }
    
    echo json_encode(['success' => true, 'message' => 'Berita berhasil dihapus']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
