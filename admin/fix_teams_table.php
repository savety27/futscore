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
    header("Location: ../login.php");
    exit;
}

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

echo "<h2>Memperbaiki struktur tabel teams...</h2>";

try {
    // Create teams table with all required columns
    $create_table_sql = "
    CREATE TABLE IF NOT EXISTS teams (
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_sport_type (sport_type),
        INDEX idx_is_active (is_active)
    )";
    
    $conn->exec($create_table_sql);
    echo "✅ Tabel 'teams' berhasil dibuat atau sudah ada dengan struktur yang benar!<br>";
    
    // Check if alias column exists, if not add it
    $stmt = $conn->prepare("SHOW COLUMNS FROM teams LIKE 'alias'");
    $stmt->execute();
    $alias_exists = $stmt->fetch();
    
    if (!$alias_exists) {
        $conn->exec("ALTER TABLE teams ADD COLUMN alias VARCHAR(100) AFTER name");
        echo "✅ Kolom 'alias' berhasil ditambahkan ke tabel teams!<br>";
    } else {
        echo "✅ Kolom 'alias' sudah ada di tabel teams!<br>";
    }
    
    // Hapus semua data sample jika ada
    $stmt = $conn->prepare("DELETE FROM teams");
    $stmt->execute();
    $deleted_count = $stmt->rowCount();
    echo "✅ Berhasil menghapus $deleted_count data sample dari tabel teams!<br>";
    
    echo "<br><strong>✅ Perbaikan struktur tabel teams selesai!</strong>";
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
<title>Fix Teams Table - FutScore</title>
<style>
    body {
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
        color: #1A1A2E;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .container {
        background: white;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        text-align: center;
        max-width: 600px;
    }
    .success-icon {
        font-size: 60px;
        color: #2E7D32;
        margin-bottom: 20px;
    }
    .error-icon {
        font-size: 60px;
        color: #D32F2F;
        margin-bottom: 20px;
    }
    h1, h2 {
        font-size: 28px;
        margin-bottom: 10px;
        color: #0A2463;
    }
    p {
        font-size: 16px;
        color: #6C757D;
        line-height: 1.6;
        margin-bottom: 30px;
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
        <div class="success-icon">✅</div>
        <h1>Fix Teams Table</h1>
        <p>Perbaikan struktur tabel teams telah selesai.</p>
    </div>
</body>
</html>