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

echo "Menambahkan kolom updated_at ke tabel teams...\n";

try {
    // Periksa apakah kolom updated_at sudah ada
    $stmt = $conn->prepare("SHOW COLUMNS FROM teams LIKE 'updated_at'");
    $stmt->execute();
    $updated_at_exists = $stmt->fetch();
    
    if (!$updated_at_exists) {
        // Tambahkan kolom updated_at
        $conn->exec("ALTER TABLE teams ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        echo "✅ Kolom 'updated_at' berhasil ditambahkan ke tabel teams!\n";
    } else {
        echo "✅ Kolom 'updated_at' sudah ada di tabel teams!\n";
    }
    
    // Periksa struktur tabel teams setelah penambahan
    echo "\nStruktur tabel teams setelah perbaikan:\n";
    $stmt = $conn->prepare("SHOW COLUMNS FROM teams");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\n✅ Perbaikan struktur tabel teams selesai!\n";
    echo "Sekarang Anda bisa menambahkan team baru tanpa error.\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>