<?php
declare(strict_types=1);

function appConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = __DIR__ . '/../config.php';
    if (!file_exists($path)) {
        throw new RuntimeException('Configuration file config.php is missing.');
    }

    $loaded = require $path;
    if (!is_array($loaded)) {
        throw new RuntimeException('config.php must return an array.');
    }

    $config = $loaded;
    return $config;
}

function appConfigValue(string $path, $default = null)
{
    $segments = explode('.', $path);
    $value = appConfig();
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}
