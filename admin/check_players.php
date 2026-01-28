<?php
// Load database config
require_once 'config/database.php';

try {
    $stmt = $conn->query("DESCRIBE players");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Struktur tabel players:<br>";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")";
        if ($column['Null'] == 'NO') echo " NOT NULL";
        if ($column['Key'] == 'PRI') echo " PRIMARY KEY";
        if ($column['Key'] == 'UNI') echo " UNIQUE";
        if ($column['Default'] !== null) echo " DEFAULT '" . $column['Default'] . "'";
        echo "<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>