<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/session.php';

tx_session_start();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (strtoupper($method) !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo 'Method Not Allowed';
    exit;
}

try {
    clearAuthenticatedUser();
    session_regenerate_id(true);
} catch (Throwable $exception) {
    error_log('Failed to logout: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to logout'], JSON_UNESCAPED_SLASHES);
    exit;
}

$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
$isJsonRequest = stripos($accept, 'application/json') !== false || strtolower($requestedWith) === 'xmlhttprequest';

if ($isJsonRequest) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true], JSON_UNESCAPED_SLASHES);
    exit;
}

header('Location: /');
exit;
