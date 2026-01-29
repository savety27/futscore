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

// Create teams table
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

// Check if alias column exists, if not add it
try {
    $stmt = $conn->prepare("SHOW COLUMNS FROM teams LIKE 'alias'");
    $stmt->execute();
    $alias_exists = $stmt->fetch();
    
    if (!$alias_exists) {
        $conn->exec("ALTER TABLE teams ADD COLUMN alias VARCHAR(100) AFTER name");
        echo "<br>✅ Kolom 'alias' berhasil ditambahkan ke tabel teams!";
    } else {
        echo "<br>✅ Kolom 'alias' sudah ada di tabel teams!";
    }
} catch (PDOException $e) {
    echo "<br>⚠️ Peringatan: Tidak dapat memeriksa atau menambahkan kolom alias: " . $e->getMessage();
}

// Check if all required columns exist
$required_columns = ['name', 'alias', 'coach', 'established_year', 'uniform_color', 'basecamp', 'sport_type', 'logo', 'is_active'];
$missing_columns = [];

foreach ($required_columns as $column) {
    try {
        $stmt = $conn->prepare("SHOW COLUMNS FROM teams LIKE '$column'");
        $stmt->execute();
        $column_exists = $stmt->fetch();
        
        if (!$column_exists) {
            $missing_columns[] = $column;
        }
    } catch (PDOException $e) {
        $missing_columns[] = $column;
    }
}

if (!empty($missing_columns)) {
    echo "<br>⚠️ Kolom yang hilang: " . implode(', ', $missing_columns);
} else {
    echo "<br>✅ Semua kolom yang dibutuhkan sudah ada di tabel teams!";
}

try {
    $conn->exec($create_table_sql);
    echo "✅ Tabel 'teams' berhasil dibuat atau sudah ada!";
} catch (PDOException $e) {
    echo "❌ Error membuat tabel teams: " . $e->getMessage();
    exit;
}

// Create some sample teams
$sample_teams = [
    [
        'name' => 'BUFC (Balikpapan United Futsal Club)',
        'alias' => 'BUFC',
        'coach' => 'Coach Ahmad',
        'established_year' => 2020,
        'uniform_color' => 'Biru-Kuning',
        'basecamp' => 'Gelanggang Futsal Balikpapan',
        'sport_type' => 'Futsal',
        'logo' => 'images/teams/014-bufc.png'
    ],
    [
        'name' => 'Antri Futsal',
        'alias' => 'Antri',
        'coach' => 'Coach Budi',
        'established_year' => 2018,
        'uniform_color' => 'Merah-Putih',
        'basecamp' => 'Futsal Center Balikpapan',
        'sport_type' => 'Futsal',
        'logo' => 'images/teams/antri-futsal.png'
    ],
    [
        'name' => 'Apollo Futsal',
        'alias' => 'Apollo',
        'coach' => 'Coach Surya',
        'established_year' => 2019,
        'uniform_color' => 'Hijau-Kuning',
        'basecamp' => 'Apollo Sport Center',
        'sport_type' => 'Futsal',
        'logo' => 'images/teams/apollo futsal.png'
    ],
    [
        'name' => 'Bahati Futsal',
        'alias' => 'Bahati',
        'coach' => 'Coach Rizki',
        'established_year' => 2021,
        'uniform_color' => 'Biru-Orange',
        'basecamp' => 'Bahati Sport Hall',
        'sport_type' => 'Futsal',
        'logo' => 'images/teams/bahati-futsal.png'
    ],
    [
        'name' => 'Famili Balikpapan',
        'alias' => 'Famili',
        'coach' => 'Coach Dedi',
        'established_year' => 2017,
        'uniform_color' => 'Hitam-Putih',
        'basecamp' => 'Famili Futsal Arena',
        'sport_type' => 'Futsal',
        'logo' => 'images/teams/famili-balikpapan.png'
    ],
    [
        'name' => 'Generasi Fab',
        'alias' => 'GenFab',
        'coach' => 'Coach Fabian',
        'established_year' => 2022,
        'uniform_color' => 'Ungu-Kuning',
        'basecamp' => 'Generasi Sport Center',
        'sport_type' => 'Futsal',
        'logo' => 'images/teams/generasi-fab.png'
    ],
    [
        'name' => 'Kuda Laut Nusantara',
        'alias' => 'Kuda Laut',
        'coach' => 'Coach Laut',
        'established_year' => 2016,
        'uniform_color' => 'Biru-Hijau',
        'basecamp' => 'Nusantara Futsal',
        'sport_type' => 'Futsal',
        'logo' => 'images/teams/kuda-laut-nusantara.png'
    ],
    [
        'name' => 'Mess Futsal',
        'alias' => 'Mess',
        'coach' => 'Coach Maman',
        'established_year' => 2020,
        'uniform_color' => 'Merah-Biru',
        'basecamp' => 'Mess Sport Center',
        'sport_type' => 'Futsal',
        'logo' => 'images/teams/mess-futsal.png'
    ],
    [
        'name' => 'PAFCA',
        'alias' => 'PAFCA',
        'coach' => 'Coach Pafca',
        'established_year' => 2019,
        'uniform_color' => 'Kuning-Hijau',
        'basecamp' => 'PAFCA Arena',
        'sport_type' => 'Futsal',
        'logo' => 'images/teams/PAFCA.png'
    ],
    [
        'name' => 'Two In One',
        'alias' => 'Two In One',
        'coach' => 'Coach Two',
        'established_year' => 2021,
        'uniform_color' => 'Orange-Biru',
        'basecamp' => 'Two In One Sport',
        'sport_type' => 'Futsal',
        'logo' => 'images/teams/two in one.png'
    ]
];

// Insert sample teams
$insert_count = 0;
foreach ($sample_teams as $team) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM teams WHERE name = ?");
    $stmt->execute([$team['name']]);
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $stmt = $conn->prepare("INSERT INTO teams (name, alias, coach, established_year, uniform_color, basecamp, sport_type, logo, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())");
        $stmt->execute([$team['name'], $team['alias'], $team['coach'], $team['established_year'], $team['uniform_color'], $team['basecamp'], $team['sport_type'], $team['logo']]);
        $insert_count++;
    }
}

echo "<br>✅ Berhasil menambahkan $insert_count team sample ke database!";

echo "<br><br><a href='team.php' style='background: linear-gradient(135deg, #0A2463, #4CC9F0); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;'>➡️ Lihat Daftar Team</a>";
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Teams Table - FutScore</title>
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
    h1 {
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
    }
    .btn:hover {
        transform: translateY(-2px);
    }
</style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h1>Setup Teams Table</h1>
        <p>Tabel teams telah berhasil dibuat dan sample data telah ditambahkan ke database.</p>
        <a href="team.php" class="btn">➡️ Lihat Daftar Team</a>
    </div>
</body>
</html>