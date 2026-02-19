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

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

echo "<h2>Memperbaiki tabel teams dengan SQL langsung...</h2>";

try {
    // Drop tabel teams jika ada dan buat ulang dengan struktur yang benar
    echo "<h3>1. Menghapus tabel teams (jika ada)...</h3>";
    $conn->exec("DROP TABLE IF EXISTS teams");
    echo "✅ Tabel teams berhasil dihapus (jika ada)<br>";
    
    // Buat tabel teams dengan struktur yang benar
    echo "<h3>2. Membuat tabel teams dengan struktur yang benar...</h3>";
    $create_table_sql = "
    CREATE TABLE teams (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        alias VARCHAR(100),
        coach VARCHAR(255) NOT NULL,
        established_year DATE,
        uniform_color VARCHAR(100),
        basecamp VARCHAR(255),
        sport_type VARCHAR(50) NOT NULL,
        logo VARCHAR(255),
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_sport_type (sport_type),
        INDEX idx_is_active (is_active)
    )";
    
    $conn->exec($create_table_sql);
    echo "✅ Tabel teams berhasil dibuat dengan struktur yang benar!<br>";
    
    // Periksa struktur tabel
    echo "<h3>3. Memeriksa struktur tabel teams:</h3>";
    $stmt = $conn->prepare("SHOW COLUMNS FROM teams");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Periksa apakah kolom alias ada
    $alias_exists = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'alias') {
            $alias_exists = true;
            break;
        }
    }
    
    if ($alias_exists) {
        echo "<h3>4. ✅ Kolom 'alias' sudah ada di tabel teams!</h3>";
    } else {
        echo "<h3>4. ❌ Kolom 'alias' tidak ditemukan!</h3>";
    }
    
    echo "<h3>5. Hasil akhir:</h3>";
    echo "<strong>✅ Perbaikan struktur tabel teams selesai!</strong>";
    echo "<br>Sekarang Anda bisa menambahkan team baru tanpa error.";
    echo "<br><br><a href='team.php' style='background: linear-gradient(135deg, #0A2463, #4CC9F0); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;'>➡️ Lihat Daftar Team (Kosong)</a>";
    echo "<br><a href='team/add.php' style='background: linear-gradient(135deg, #2E7D32, #4CAF50); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 10px; display: inline-block;'>➕ Tambah Team Baru</a>";
} catch (PDOException $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>";
    echo "<p>Gagal memperbaiki struktur tabel teams. Silakan coba lagi.</p>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fix Teams SQL</title>
<style>
    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
        color: #1A1A2E;
        min-height: 100vh;
        padding: 20px;
    }
    .container {
        background: white;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        max-width: 800px;
        margin: 0 auto;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
    }
    th, td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    th {
        background-color: #f8f9fa;
        font-weight: 600;
    }
    h1, h2, h3 {
        color: #0A2463;
    }
    .btn {
        background: linear-gradient(135deg, #0A2463, #4CC9F0);
        color: white;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        transition: transform 0.2s;
        display: inline-block;
        margin: 10px 5px;
    }
    .btn:hover {
        transform: translateY(-2px);
    }
    .btn-success {
        background: linear-gradient(135deg, #2E7D32, #4CAF50);
    }
</style>
</head>
<body>
    <div class="container">
        <h1>Fix Teams SQL</h1>
        <p>Memperbaiki struktur tabel teams dengan SQL langsung.</p>
    </div>
</body>
</html>
