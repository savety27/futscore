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

function resolveSchemaPath(string $projectRoot): string
{
    $configuredPath = getenv('TEST_SCHEMA_PATH');

    if ($configuredPath === false || trim($configuredPath) === '') {
        $configuredPath = 'migrations/futscore_db (8).sql';
    }

    $isAbsolutePath = preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1
        || str_starts_with($configuredPath, '\\\\');
    $isAbsolutePath = $isAbsolutePath || str_starts_with($configuredPath, '/');
    $schemaPath = $isAbsolutePath
        ? $configuredPath
        : $projectRoot . '/' . ltrim($configuredPath, '/\\');

    if (!file_exists($schemaPath)) {
        fwrite(STDERR, "Schema file not found: {$schemaPath}\n");
        exit(1);
    }

    if (!is_file($schemaPath)) {
        fwrite(STDERR, "Schema path is not a file: {$schemaPath}\n");
        exit(1);
    }

    return $schemaPath;
}

function applySqlFile(PDO $pdo, string $path): bool
{
    if (!file_exists($path)) {
        fwrite(STDERR, "SQL file not found: {$path}\n");
        exit(1);
    }

    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        fwrite(STDERR, "SQL file is empty or unreadable: {$path}\n");
        exit(1);
    }

    // Parse SQL manually to handle quotes and comments correctly
    $statements = [];
    $buffer = '';
    $inString = false;
    $quoteChar = '';
    $escaped = false;

    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];

        if ($inString) {
            if ($escaped) {
                $escaped = false;
            } elseif ($char === '\\') {
                $escaped = true;
            } elseif ($char === $quoteChar) {
                $inString = false;
            }
        } else {
            if ($char === "'" || $char === '"') {
                $inString = true;
                $quoteChar = $char;
            } elseif ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            } elseif ($char === '#' || ($char === '-' && ($i + 1 < $length && $sql[$i + 1] === '-'))) {
                // Skip comments until end of line
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                // Skip appending the comment characters or newline to buffer
                // The loop puts $i at \n or end of string.
                // We 'continue' to next iteration of for loop, which increments $i.
                // Effectively skipping the entire comment line including the newline.
                continue;
            }
        }
        $buffer .= $char;
    }

    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    $atLeastOneExecuted = false;

    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $atLeastOneExecuted = true;
        } catch (PDOException $e) {
            fwrite(STDERR, "Failed applying statement in '{$path}':\nSQL: {$statement}\nError: {$e->getMessage()}\n");
            exit(1);
        }
    }

    return $atLeastOneExecuted;
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
    'DROP DATABASE IF EXISTS `%s`',
    $database
));

$basePdo->exec(sprintf(
    'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    $database
));

$dbDsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
$dbPdo = new PDO($dbDsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$projectRoot = dirname(__DIR__, 2);
$schemaPath = resolveSchemaPath($projectRoot);
$seedPath = __DIR__ . '/../database/seed.sql';

if (!file_exists($seedPath)) {
    fwrite(STDERR, "Seed file not found: {$seedPath}\n");
    exit(1);
}

fwrite(STDOUT, 'Loading schema: ' . basename($schemaPath) . " ... ");
applySqlFile($dbPdo, $schemaPath);
fwrite(STDOUT, "DONE\n");

fwrite(STDOUT, 'Loading seed: ' . basename($seedPath) . " ... ");
applySqlFile($dbPdo, $seedPath);
fwrite(STDOUT, "DONE\n");

fwrite(STDOUT, "Test database '{$database}' has been reset successfully.\n");
