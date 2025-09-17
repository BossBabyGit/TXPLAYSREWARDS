<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/discord.php';
require_once __DIR__ . '/lib/session.php';

if (!isDiscordConfigured()) {
    http_response_code(500);
    echo 'Discord OAuth is not configured. Please update config.php.';
    exit;
}

$returnTo = sanitizeReturnUrl($_GET['return'] ?? null);
if ($returnTo) {
    rememberReturnUrl($returnTo);
}

try {
    $state = generateOAuthState();
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Unable to initiate Discord authentication.';
    error_log('Failed to generate OAuth state: ' . $exception->getMessage());
    exit;
}

$authorizeUrl = buildDiscordAuthorizeUrl($state);

header('Location: ' . $authorizeUrl);
exit;
