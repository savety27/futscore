<?php
session_start();

// Load database config
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found");
}

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$query = "SELECT * FROM berita WHERE 1=1";
$params = [];

if (!empty($search)) {
    $search_term = "%{$search}%";
    $query .= " AND (judul LIKE ? OR konten LIKE ? OR penulis LIKE ? OR tag LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $berita = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for Excel file
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="berita_export_' . date('Y-m-d_H-i-s') . '.xls"');
    
    // Start output
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>ID</th>";
    echo "<th>Judul</th>";
    echo "<th>Slug</th>";
    echo "<th>Penulis</th>";
    echo "<th>Status</th>";
    echo "<th>Tags</th>";
    echo "<th>Views</th>";
    echo "<th>Tanggal Dibuat</th>";
    echo "<th>Tanggal Diupdate</th>";
    echo "</tr>";
    
    $no = 1;
    foreach ($berita as $b) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($b['id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($b['judul'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($b['slug'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($b['penulis'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($b['status'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($b['tag'] ?? '-') . "</td>";
        echo "<td>" . htmlspecialchars($b['views'] ?? 0) . "</td>";
        echo "<td>" . htmlspecialchars($b['created_at'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($b['updated_at'] ?? '') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>