<?php
require_once 'config/database.php';
session_start();

// Ensure user is logged in and is a pelatih
if (!isset($_SESSION['team_id']) || $_SESSION['admin_role'] !== 'pelatih') {
    die("Unauthorized access");
}

$team_id = $_SESSION['team_id'];
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- ADD PLAYER ---
    if ($action === 'add') {
        $name = $_POST['name'];
        $jersey_number = $_POST['jersey_number'];
        $position = $_POST['position'];
        $birth_date = $_POST['birth_date'];
        $gender = $_POST['gender'];
        $height = $_POST['height'] ?: null;
        $weight = $_POST['weight'] ?: null;
        
        // Slug generation
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        // Append random string to ensure uniqueness
        $slug .= '-' . substr(md5(uniqid()), 0, 5);

        // Photo Upload Logic (Simplified)
        $photo = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $target_dir = "../images/players/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
            $new_filename = "player_" . uniqid() . "." . $file_extension;
            if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_dir . $new_filename)) {
                $photo = $new_filename;
            }
        }

        try {
            $stmt = $conn->prepare("INSERT INTO players (name, slug, team_id, jersey_number, position, birth_date, gender, height, weight, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $team_id, $jersey_number, $position, $birth_date, $gender, $height, $weight, $photo]);
            
            header("Location: players.php?msg=added");
        } catch (PDOException $e) {
            die("Error adding player: " . $e->getMessage());
        }
    }
    
    // --- EDIT PLAYER ---
    elseif ($action === 'edit') {
        $id = $_POST['id'];
        
        // Ownership verified in the UPDATE query clause "AND team_id = ?"
        $name = $_POST['name'];
        $jersey_number = $_POST['jersey_number'];
        $position = $_POST['position'];
        $birth_date = $_POST['birth_date'];
        $gender = $_POST['gender'];
        $height = $_POST['height'] ?: null;
        $weight = $_POST['weight'] ?: null;

        $sql = "UPDATE players SET name=?, jersey_number=?, position=?, birth_date=?, gender=?, height=?, weight=? WHERE id=? AND team_id=?";
        $params = [$name, $jersey_number, $position, $birth_date, $gender, $height, $weight, $id, $team_id];

        // Handle Photo Update
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
             $target_dir = "../images/players/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES["photo"]["name"], PATHINFO_EXTENSION);
            $new_filename = "player_" . uniqid() . "." . $file_extension;
            if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_dir . $new_filename)) {
                $sql = "UPDATE players SET name=?, jersey_number=?, position=?, birth_date=?, gender=?, height=?, weight=?, photo=? WHERE id=? AND team_id=?";
                $params = [$name, $jersey_number, $position, $birth_date, $gender, $height, $weight, $new_filename, $id, $team_id];
            }
        }

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->rowCount() > 0) {
                header("Location: players.php?msg=updated");
            } else {
                 // Could mean no changes were made OR tested ID doesn't belong to team
                 header("Location: players.php?msg=no_changes_or_unauthorized");
            }
        } catch (PDOException $e) {
            die("Error updating player: " . $e->getMessage());
        }
    }
    
    // --- DELETE PLAYER ---
    elseif ($action === 'delete') {
        $id = $_POST['id'];
        
        try {
            $stmt = $conn->prepare("DELETE FROM players WHERE id=? AND team_id=?");
            $stmt->execute([$id, $team_id]);
            
            header("Location: players.php?msg=deleted");
        } catch (PDOException $e) {
            die("Error deleting player: " . $e->getMessage());
        }
    }
}
?>
