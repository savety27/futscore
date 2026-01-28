<?php
// File untuk membuat tabel tambahan yang dibutuhkan

require_once 'config/database.php';

try {
    // Create player_documents table
    $createDocsTable = "
    CREATE TABLE IF NOT EXISTS player_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT NOT NULL,
        ktp_file VARCHAR(255),
        kk_file VARCHAR(255),
        akte_file VARCHAR(255),
        ijazah_file VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $conn->exec($createDocsTable);
    echo "✓ Tabel player_documents berhasil dibuat\n";
    
    // Create player_skills table
    $createSkillsTable = "
    CREATE TABLE IF NOT EXISTS player_skills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT NOT NULL,
        dribbling INT DEFAULT 5,
        technique INT DEFAULT 5,
        speed INT DEFAULT 5,
        juggling INT DEFAULT 5,
        shooting INT DEFAULT 5,
        setplay_position INT DEFAULT 5,
        passing INT DEFAULT 5,
        control INT DEFAULT 5,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
        CHECK (dribbling BETWEEN 0 AND 10),
        CHECK (technique BETWEEN 0 AND 10),
        CHECK (speed BETWEEN 0 AND 10),
        CHECK (juggling BETWEEN 0 AND 10),
        CHECK (shooting BETWEEN 0 AND 10),
        CHECK (setplay_position BETWEEN 0 AND 10),
        CHECK (passing BETWEEN 0 AND 10),
        CHECK (control BETWEEN 0 AND 10)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $conn->exec($createSkillsTable);
    echo "✓ Tabel player_skills berhasil dibuat\n";
    
    // Check if teams table exists and add sample teams if needed
    try {
        $checkTeamsQuery = "SELECT COUNT(*) as count FROM teams";
        $checkTeamsStmt = $conn->prepare($checkTeamsQuery);
        $checkTeamsStmt->execute();
        echo "✓ Tabel teams sudah ada\n";
        
        // Add some sample teams if they don't exist
        $sampleTeams = [
            ['name' => 'Futsal Academy A', 'coach' => 'Coach John'],
            ['name' => 'Futsal Academy B', 'coach' => 'Coach Mary'],
            ['name' => 'Futsal Academy C', 'coach' => 'Coach David'],
        ];
        
        foreach ($sampleTeams as $team) {
            $checkQuery = "SELECT COUNT(*) as count FROM teams WHERE name = :name";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bindParam(':name', $team['name']);
            $checkStmt->execute();
            $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                // Insert with available columns only
                $insertQuery = "INSERT INTO teams (name, coach) VALUES (:name, :coach)";
                $insertStmt = $conn->prepare($insertQuery);
                $insertStmt->bindParam(':name', $team['name']);
                $insertStmt->bindParam(':coach', $team['coach']);
                $insertStmt->execute();
                echo "✓ Tim " . $team['name'] . " berhasil ditambahkan\n";
            } else {
                echo "ℹ Tim " . $team['name'] . " sudah ada\n";
            }
        }
    } catch (PDOException $e) {
        // Teams table doesn't exist, create it
        $createTeamsTable = "
        CREATE TABLE teams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            logo VARCHAR(255),
            coach VARCHAR(100),
            established_year YEAR,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $conn->exec($createTeamsTable);
        echo "✓ Tabel teams berhasil dibuat\n";
        
        // Add sample teams
        $sampleTeams = [
            ['name' => 'Futsal Academy A', 'coach' => 'Coach John'],
            ['name' => 'Futsal Academy B', 'coach' => 'Coach Mary'],
            ['name' => 'Futsal Academy C', 'coach' => 'Coach David'],
        ];
        
        foreach ($sampleTeams as $team) {
            $insertQuery = "INSERT INTO teams (name, coach, established_year, is_active) VALUES (:name, :coach, YEAR(NOW()), 1)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bindParam(':name', $team['name']);
            $insertStmt->bindParam(':coach', $team['coach']);
            $insertStmt->execute();
            echo "✓ Tim " . $team['name'] . " berhasil ditambahkan\n";
        }
    }
    
    echo "\n=== SETUP TAMBAHAN SELESAI ===\n";
    echo "Semua tabel yang dibutuhkan untuk fitur tambah player sudah siap!\n";
    echo "\n📁 Tabel yang telah dibuat:\n";
    echo "- player_documents (untuk menyimpan file dokumen player)\n";
    echo "- player_skills (untuk menyimpan skill player)\n";
    echo "- teams (untuk referensi team)\n";
    echo "\n🚀 Anda sekarang bisa menggunakan fitur tambah player!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Additional Tables Setup Error: " . $e->getMessage());
}
?>