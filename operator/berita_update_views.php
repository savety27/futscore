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


// Get parameters
$berita_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$operator_id = (int)($_SESSION['admin_id'] ?? 0);
$admin_name = $_SESSION['admin_fullname'] ?? $_SESSION['admin_username'] ?? 'Admin';
$admin_username = trim((string)($_SESSION['admin_username'] ?? ''));

if ($berita_id <= 0 && empty($slug)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
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
    $operator_event_is_active = true;
    if ($operator_id > 0) {
        $stmtOperator = $conn->prepare("
            SELECT COALESCE(e.is_active, 1) AS event_is_active
            FROM admin_users au
            LEFT JOIN events e ON e.id = au.event_id
            WHERE au.id = ?
            LIMIT 1
        ");
        $stmtOperator->execute([$operator_id]);
        $operator_row = $stmtOperator->fetch(PDO::FETCH_ASSOC);
        $operator_event_is_active = ((int)($operator_row['event_is_active'] ?? 1) === 1);
    }

    if (!$operator_event_is_active) {
        echo json_encode(['success' => false, 'message' => 'Event operator sedang non-aktif. Mode hanya lihat data.']);
        exit;
    }

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

    // Update views based on ID or slug
    if ($berita_id > 0) {
        $stmt = $conn->prepare("UPDATE berita SET views = views + 1 WHERE id = ?" . $ownership_where_sql);
        $stmt->execute(array_merge([$berita_id], $ownership_params));
    } elseif (!empty($slug)) {
        $stmt = $conn->prepare("UPDATE berita SET views = views + 1 WHERE slug = ?" . $ownership_where_sql);
        $stmt->execute(array_merge([$slug], $ownership_params));
    }

    if ($stmt->rowCount() <= 0) {
        echo json_encode(['success' => false, 'message' => 'Berita not found or no permission']);
        exit;
    }
    
    echo json_encode(['success' => true, 'message' => 'Views updated']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
