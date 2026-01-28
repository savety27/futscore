<?php
// Load database config
$config_path = __DIR__ . '/config/database.php';
if (file_exists($config_path)) {
    require_once $config_path;
} else {
    die("Database configuration file not found at: $config_path");
}

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

echo "Menghapus semua data team...\n";

try {
    // Hapus data dari tabel teams
    $stmt = $conn->prepare("DELETE FROM teams");
    $stmt->execute();
    $deleted_count = $stmt->rowCount();
    
    echo "✅ Berhasil menghapus $deleted_count team dari database!\n";
    echo "Semua data team telah dihapus. Sekarang Anda bisa menambahkan team baru sendiri.\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Gagal menghapus data team. Silakan coba lagi.\n";
}
?>