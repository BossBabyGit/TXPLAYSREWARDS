<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function appDb(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = appConfigValue('database');
    if (!is_array($config)) {
        throw new RuntimeException('Database configuration is missing.');
    }

    $host = $config['host'] ?? '127.0.0.1';
    $port = (int) ($config['port'] ?? 3306);
    $name = $config['name'] ?? 'txplays';
    $charset = $config['charset'] ?? 'utf8mb4';
    $user = $config['user'] ?? '';
    $password = $config['pass'] ?? '';

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        throw new RuntimeException('Unable to connect to the database: ' . $exception->getMessage(), (int) $exception->getCode(), $exception);
    }

    return $pdo;
}
