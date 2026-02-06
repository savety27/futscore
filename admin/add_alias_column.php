<?php
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

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

echo "Menambahkan kolom alias ke tabel teams...\n";

try {
    // Periksa apakah kolom alias sudah ada
    $stmt = $conn->prepare("SHOW COLUMNS FROM teams LIKE 'alias'");
    $stmt->execute();
    $alias_exists = $stmt->fetch();
    
    if (!$alias_exists) {
        // Tambahkan kolom alias
        $conn->exec("ALTER TABLE teams ADD COLUMN alias VARCHAR(100) AFTER name");
        echo "✅ Kolom 'alias' berhasil ditambahkan ke tabel teams!\n";
    } else {
        echo "✅ Kolom 'alias' sudah ada di tabel teams!\n";
    }
    
    // Hapus semua data sample
    $stmt = $conn->prepare("DELETE FROM teams");
    $stmt->execute();
    $deleted_count = $stmt->rowCount();
    echo "✅ Berhasil menghapus $deleted_count data sample dari tabel teams!\n";
    
    echo "✅ Perbaikan struktur tabel teams selesai!\n";
    echo "Sekarang Anda bisa menambahkan team baru tanpa error.\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>