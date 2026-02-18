<?php

declare(strict_types=1);

function envOrDefault(string $key, string $default): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function assertSafeDatabaseName(string $databaseName): void
{
    if ($databaseName !== 'futscore_test') {
        fwrite(STDERR, "Refusing to reset database '{$databaseName}'. Expected 'futscore_test'.\n");
        exit(1);
    }
}

$host = envOrDefault('DB_HOST', '127.0.0.1');
$port = envOrDefault('DB_PORT', '3306');
$database = envOrDefault('DB_NAME', 'futscore_test');
$username = envOrDefault('DB_USER', 'root');
$password = envOrDefault('DB_PASS', '');

assertSafeDatabaseName($database);

$baseDsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);

$basePdo = new PDO($baseDsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$basePdo->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    $database
));

$dbDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
$dbPdo = new PDO($dbDsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$schemaPath = __DIR__ . '/../database/schema.sql';
$seedPath = __DIR__ . '/../database/seed.sql';

if (!file_exists($schemaPath)) {
    fwrite(STDERR, "Schema file not found: {$schemaPath}\n");
    exit(1);
}

if (!file_exists($seedPath)) {
    fwrite(STDERR, "Seed file not found: {$seedPath}\n");
    exit(1);
}

$schemaSql = file_get_contents($schemaPath);
$seedSql = file_get_contents($seedPath);

if ($schemaSql === false || trim($schemaSql) === '') {
    fwrite(STDERR, "Schema file is empty or unreadable.\n");
    exit(1);
}

if ($seedSql === false) {
    fwrite(STDERR, "Seed file is unreadable.\n");
    exit(1);
}

$dbPdo->exec($schemaSql);
$dbPdo->exec($seedSql);

fwrite(STDOUT, "Test database '{$database}' has been reset successfully.\n");
