<?php
require_once 'config/database.php';

echo "=== PEMERIKSAAN SCHEMA DATABASE ===\n\n";

// Check players table
echo "1. Tabel players:\n";
try {
    $stmt = $conn->query('DESCRIBE players');
    $players_fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($players_fields as $field) {
        echo "   - {$field['Field']} ({$field['Type']}) " . ($field['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "   ERROR: Tabel players tidak ditemukan\n";
}

echo "\n2. Tabel player_documents:\n";
try {
    $stmt = $conn->query('DESCRIBE player_documents');
    $docs_fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($docs_fields as $field) {
        echo "   - {$field['Field']} ({$field['Type']}) " . ($field['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "   ERROR: Tabel player_documents tidak ditemukan\n";
}

echo "\n3. Tabel player_skills:\n";
try {
    $stmt = $conn->query('DESCRIBE player_skills');
    $skills_fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($skills_fields as $field) {
        echo "   - {$field['Field']} ({$field['Type']}) " . ($field['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "   ERROR: Tabel player_skills tidak ditemukan\n";
}

echo "\n4. Tabel teams:\n";
try {
    $stmt = $conn->query('DESCRIBE teams');
    $teams_fields = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($teams_fields as $field) {
        echo "   - {$field['Field']} ({$field['Type']}) " . ($field['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "   ERROR: Tabel teams tidak ditemukan\n";
}

echo "\n=== ANALISIS KESALAHAN ===\n";
echo "Masalah pada admin/player/add.php:\n";
echo "- Form mencoba menyimpan NISN, NIK, dll ke tabel players\n";
echo "- Tapi tabel players hanya memiliki kolom terbatas\n";
echo "- Data tambahan harus disimpan ke tabel terpisah\n";
echo "\nSolusi:\n";
echo "1. Perlu membuat tabel player_profiles untuk data pribadi\n";
echo "2. Atau memperluas tabel players dengan kolom tambahan\n";
echo "3. Atau mengubah form untuk hanya menyimpan data dasar\n";