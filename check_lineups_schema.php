<?php
require_once 'c:/xampp/htdocs/alvetrix/admin/config/database.php';

try {
    $stmt = $conn->query("DESCRIBE lineups");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($columns);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
