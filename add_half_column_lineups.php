<?php
require_once 'c:/xampp/htdocs/alvetrix/admin/config/database.php';

try {
    // Check if column exists
    $stmt = $conn->query("SHOW COLUMNS FROM lineups LIKE 'half'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE lineups ADD COLUMN half TINYINT(1) NOT NULL DEFAULT 1 AFTER position");
        echo "Column 'half' added successfully.";
    } else {
        echo "Column 'half' already exists.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
