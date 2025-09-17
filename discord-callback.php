<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/discord.php';
require_once __DIR__ . '/lib/session.php';

if (!isDiscordConfigured()) {
    http_response_code(500);
    echo 'Discord OAuth is not configured.';
    exit;
}

$error = $_GET['error'] ?? null;
if ($error) {
    $target = consumeReturnUrl() ?? '/';
    $separator = str_contains($target, '?') ? '&' : '?';
    header('Location: ' . $target . $separator . 'auth_error=' . rawurlencode($error));
    exit;
}

$state = $_GET['state'] ?? null;
if (!validateOAuthState($state)) {
    $target = consumeReturnUrl() ?? '/';
    header('Location: ' . $target . (str_contains($target, '?') ? '&' : '?') . 'auth_error=invalid_state');
    exit;
}

$code = $_GET['code'] ?? null;
if (!$code) {
    $target = consumeReturnUrl() ?? '/';
    header('Location: ' . $target . (str_contains($target, '?') ? '&' : '?') . 'auth_error=missing_code');
    exit;
}

try {
    $token = exchangeDiscordCode($code);
    if (!$token || empty($token['access_token'])) {
        throw new RuntimeException('Failed to exchange authorization code.');
    }

    $profile = fetchDiscordUserProfile($token['access_token']);
    if (!$profile || empty($profile['id'])) {
        throw new RuntimeException('Unable to fetch Discord profile.');
    }

    $storedUser = storeDiscordUser($profile, $token);
    if (!$storedUser) {
        throw new RuntimeException('Unable to store Discord user.');
    }

    setAuthenticatedUser((string) $storedUser['id'], $storedUser);
} catch (Throwable $exception) {
    error_log('Discord OAuth failed: ' . $exception->getMessage());
    $target = consumeReturnUrl() ?? '/';
    header('Location: ' . $target . (str_contains($target, '?') ? '&' : '?') . 'auth_error=oauth_failure');
    exit;
}

$redirectTo = consumeReturnUrl() ?? '/';

header('Location: ' . $redirectTo);
exit;
