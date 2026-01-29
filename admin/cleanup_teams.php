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

// Hapus semua data team
try {
    // Hapus data dari tabel teams
    $stmt = $conn->prepare("DELETE FROM teams");
    $stmt->execute();
    $deleted_count = $stmt->rowCount();
    
    echo "<h2>✅ Berhasil menghapus $deleted_count team dari database!</h2>";
    echo "<p>Semua data team telah dihapus. Sekarang Anda bisa menambahkan team baru sendiri.</p>";
    echo "<br><a href='team.php' style='background: linear-gradient(135deg, #0A2463, #4CC9F0); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;'>➡️ Lihat Daftar Team (Kosong)</a>";
    echo "<br><a href='team/add.php' style='background: linear-gradient(135deg, #2E7D32, #4CAF50); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 10px; display: inline-block;'>➕ Tambah Team Baru</a>";
} catch (PDOException $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>";
    echo "<p>Gagal menghapus data team. Silakan coba lagi.</p>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cleanup Teams - FutScore</title>
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
    h2 {
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
        <h1>Cleanup Teams</h1>
        <p>Proses penghapusan data team telah selesai.</p>
    </div>
</body>
</html>