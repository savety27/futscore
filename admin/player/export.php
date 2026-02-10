<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: ../index.php");
    exit;
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="players_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Get search parameter
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Query untuk mengambil data players
    $query = "SELECT 
                p.name,
                p.nik,
                p.nisn,
                p.birth_place,
                p.birth_date,
                p.gender,
                p.sport_type,
                p.height,
                p.weight,
                p.email,
                p.phone,
                p.nationality,
                p.street,
                p.city,
                p.province,
                p.postal_code,
                p.country,
                p.jersey_number,
                p.dominant_foot,
                p.position,
                p.dribbling,
                p.technique,
                p.speed,
                p.juggling,
                p.shooting,
                p.setplay_position,
                p.passing,
                p.control,
                t.name as team_name,
                p.created_at
              FROM players p 
              LEFT JOIN teams t ON p.team_id = t.id 
              WHERE 1=1";
    
    $params = [];
    
    if (!empty($search)) {
        $search_term = "%{$search}%";
        $query .= " AND (p.name LIKE ? OR p.nik LIKE ? OR p.nisn LIKE ?)";
        $params = array_merge($params, [$search_term, $search_term, $search_term]);
    }
    
    // Filter hanya active players
    $query .= " AND p.status = 'active'";
    
    // Order by name
    $query .= " ORDER BY p.name ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Function untuk format gender
    function formatGenderExport($gender) {
        if (empty($gender)) return '-';
        
        if ($gender == 'L') {
            return 'Laki-laki';
        } elseif ($gender == 'P') {
            return 'Perempuan';
        }
        
        $gender_lower = strtolower($gender);
        
        if (strpos($gender_lower, 'perempuan') !== false || $gender_lower == 'p' || $gender_lower == 'perempuan') {
            return 'Perempuan';
        } elseif (strpos($gender_lower, 'laki') !== false || $gender_lower == 'l' || $gender_lower == 'laki-laki') {
            return 'Laki-laki';
        } else {
            return ucfirst($gender ?? '');
        }
    }
    
    // Start Excel output
    echo "<table border='1'>";
    echo "<tr>";
    echo "<th>No</th>";
    echo "<th>Nama</th>";
    echo "<th>NIK</th>";
    echo "<th>NISN</th>";
    echo "<th>Tempat Lahir</th>";
    echo "<th>Tanggal Lahir</th>";
    echo "<th>Jenis Kelamin</th>";
    echo "<th>Event</th>";
    echo "<th>Tinggi (cm)</th>";
    echo "<th>Berat (kg)</th>";
    echo "<th>Email</th>";
    echo "<th>Telpon</th>";
    echo "<th>Kewarganegaraan</th>";
    echo "<th>Alamat</th>";
    echo "<th>Kota</th>";
    echo "<th>Provinsi</th>";
    echo "<th>Kode Pos</th>";
    echo "<th>Negara</th>";
    echo "<th>No Punggung</th>";
    echo "<th>Kaki Dominan</th>";
    echo "<th>Posisi</th>";
    echo "<th>Team</th>";
    echo "<th>Dribbling</th>";
    echo "<th>Technique</th>";
    echo "<th>Speed</th>";
    echo "<th>Juggling</th>";
    echo "<th>Shooting</th>";
    echo "<th>Setplay Position</th>";
    echo "<th>Passing</th>";
    echo "<th>Control</th>";
    echo "<th>Tanggal Daftar</th>";
    echo "</tr>";
    
    $no = 1;
    foreach ($players as $player) {
        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($player['name']) . "</td>";
        echo "<td>" . htmlspecialchars($player['nik']) . "</td>";
        echo "<td>" . htmlspecialchars($player['nisn']) . "</td>";
        echo "<td>" . htmlspecialchars($player['birth_place']) . "</td>";
        echo "<td>" . $player['birth_date'] . "</td>";
        echo "<td>" . formatGenderExport($player['gender']) . "</td>";
        echo "<td>" . htmlspecialchars($player['sport_type']) . "</td>";
        echo "<td>" . $player['height'] . "</td>";
        echo "<td>" . $player['weight'] . "</td>";
        echo "<td>" . htmlspecialchars($player['email']) . "</td>";
        echo "<td>" . htmlspecialchars($player['phone']) . "</td>";
        echo "<td>" . htmlspecialchars($player['nationality']) . "</td>";
        
        // Combine address fields
        $address = $player['street'] . ', ' . $player['city'] . ', ' . 
                  $player['province'] . ', ' . $player['postal_code'] . ', ' . 
                  $player['country'];
        echo "<td>" . htmlspecialchars($address) . "</td>";
        echo "<td>" . htmlspecialchars($player['city']) . "</td>";
        echo "<td>" . htmlspecialchars($player['province']) . "</td>";
        echo "<td>" . htmlspecialchars($player['postal_code']) . "</td>";
        echo "<td>" . htmlspecialchars($player['country']) . "</td>";
        
        echo "<td>" . $player['jersey_number'] . "</td>";
        echo "<td>" . htmlspecialchars($player['dominant_foot']) . "</td>";
        echo "<td>" . htmlspecialchars($player['position']) . "</td>";
        echo "<td>" . htmlspecialchars($player['team_name']) . "</td>";
        
        // Skills
        echo "<td>" . $player['dribbling'] . "</td>";
        echo "<td>" . $player['technique'] . "</td>";
        echo "<td>" . $player['speed'] . "</td>";
        echo "<td>" . $player['juggling'] . "</td>";
        echo "<td>" . $player['shooting'] . "</td>";
        echo "<td>" . $player['setplay_position'] . "</td>";
        echo "<td>" . $player['passing'] . "</td>";
        echo "<td>" . $player['control'] . "</td>";
        
        echo "<td>" . $player['created_at'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>