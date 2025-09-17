<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/session.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $payload = sessionPayload();
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Failed to encode session payload.');
    }

    echo $json;
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'user' => null,
        'error' => 'Unable to load session',
    ], JSON_UNESCAPED_SLASHES);
    error_log('Session endpoint error: ' . $exception->getMessage());
}
