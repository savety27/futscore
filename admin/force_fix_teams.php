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

echo "Memperbaiki tabel teams...\n";

try {
    // Drop tabel teams jika ada
    $conn->exec("DROP TABLE IF EXISTS teams");
    echo "✅ Tabel teams dihapus (jika ada)\n";
    
    // Buat tabel teams dengan struktur yang benar
    $create_table_sql = "
    CREATE TABLE teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        alias VARCHAR(100),
        coach VARCHAR(255) NOT NULL,
        established_year INT,
        uniform_color VARCHAR(100),
        basecamp VARCHAR(255),
        sport_type VARCHAR(50) NOT NULL,
        logo VARCHAR(255),
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $conn->exec($create_table_sql);
    echo "✅ Tabel teams berhasil dibuat dengan struktur yang benar!\n";
    
    // Periksa apakah kolom alias ada
    $stmt = $conn->prepare("SHOW COLUMNS FROM teams LIKE 'alias'");
    $stmt->execute();
    $alias_exists = $stmt->fetch();
    
    if ($alias_exists) {
        echo "✅ Kolom 'alias' sudah ada di tabel teams!\n";
    } else {
        echo "❌ Kolom 'alias' tidak ditemukan!\n";
    }
    
    echo "✅ Perbaikan struktur tabel teams selesai!\n";
    echo "Sekarang Anda bisa menambahkan team baru tanpa error.\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>