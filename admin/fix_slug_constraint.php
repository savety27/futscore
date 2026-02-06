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

echo "Memeriksa dan memperbaiki constraint slug...\n";

try {
    // Periksa constraint unik pada kolom slug
    $stmt = $conn->prepare("SHOW INDEXES FROM teams WHERE Column_name = 'slug'");
    $stmt->execute();
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Constraint pada kolom slug:\n";
    foreach ($indexes as $index) {
        echo "- {$index['Key_name']} (Non_unique: {$index['Non_unique']})\n";
    }
    echo "\n";
    
    // Periksa data slug yang ada
    $stmt = $conn->prepare("SELECT id, name, slug FROM teams");
    $stmt->execute();
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Data slug saat ini:\n";
    foreach ($teams as $team) {
        echo "- ID: {$team['id']}, Name: {$team['name']}, Slug: '{$team['slug']}'\n";
    }
    echo "\n";
    
    // Hapus constraint unik pada kolom slug jika ada
    foreach ($indexes as $index) {
        if ($index['Non_unique'] == 0 && $index['Key_name'] != 'PRIMARY') {
            echo "Menghapus constraint unik '{$index['Key_name']}' pada kolom slug...\n";
            $conn->exec("ALTER TABLE teams DROP INDEX {$index['Key_name']}");
            echo "✅ Constraint unik '{$index['Key_name']}' berhasil dihapus!\n";
        }
    }
    
    // Atau alternatif: buat slug menjadi nullable dan hapus nilai kosong
    echo "Mengubah kolom slug menjadi nullable dan membersihkan nilai kosong...\n";
    $conn->exec("ALTER TABLE teams MODIFY COLUMN slug VARCHAR(255) NULL");
    $conn->exec("UPDATE teams SET slug = NULL WHERE slug = '' OR slug IS NULL");
    echo "✅ Kolom slug berhasil diubah menjadi nullable dan nilai kosong dibersihkan!\n";
    
    echo "\n✅ Perbaikan constraint slug selesai!\n";
    echo "Sekarang Anda bisa menambahkan team baru tanpa error constraint slug.\n";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>