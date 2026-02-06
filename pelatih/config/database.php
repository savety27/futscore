<?php
// Include main database config
$db_path = __DIR__ . '/../../admin/config/database.php';
if (file_exists($db_path)) {
    require_once $db_path;
} else {
    die("Main database configuration file not found at: $db_path");
}
?>
