<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const TX_SESSION_USER_KEY = 'txplays.discord_user_id';
const TX_SESSION_STATE_KEY = 'txplays.discord.oauth_state';
const TX_SESSION_RETURN_KEY = 'txplays.discord.return_url';

function tx_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $secure,
    ]);

    session_start();
}

function getAuthenticatedUser(): ?array
{
    tx_session_start();

    if (!empty($GLOBALS['txplays_current_user_loaded'])) {
        return $GLOBALS['txplays_current_user'] ?? null;
    }

    $GLOBALS['txplays_current_user_loaded'] = true;

    if (empty($_SESSION[TX_SESSION_USER_KEY])) {
        $GLOBALS['txplays_current_user'] = null;
        return null;
    }

    $userId = (string) $_SESSION[TX_SESSION_USER_KEY];

    try {
        $pdo = appDb();
        $stmt = $pdo->prepare('SELECT id, username, discriminator, global_name, avatar, email, locale, mfa_enabled, token_type, token_scope, token_expires_at, created_at, updated_at FROM discord_users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            unset($_SESSION[TX_SESSION_USER_KEY]);
            $GLOBALS['txplays_current_user'] = null;
            return null;
        }

        $GLOBALS['txplays_current_user'] = $row;
        return $row;
    } catch (Throwable $exception) {
        error_log('Failed to load authenticated user: ' . $exception->getMessage());
        $GLOBALS['txplays_current_user'] = null;
        return null;
    }
}

function setAuthenticatedUser(string $userId, ?array $user = null): void
{
    tx_session_start();
    $_SESSION[TX_SESSION_USER_KEY] = (string) $userId;
    session_regenerate_id(true);
    $GLOBALS['txplays_current_user'] = $user;
    $GLOBALS['txplays_current_user_loaded'] = true;
}

function clearAuthenticatedUser(): void
{
    tx_session_start();
    unset($_SESSION[TX_SESSION_USER_KEY]);
    $GLOBALS['txplays_current_user'] = null;
    $GLOBALS['txplays_current_user_loaded'] = true;
}

function serializeDiscordUser(array $user): array
{
    return [
        'id' => isset($user['id']) ? (string) $user['id'] : null,
        'username' => $user['username'] ?? null,
        'discriminator' => $user['discriminator'] ?? null,
        'global_name' => $user['global_name'] ?? null,
        'avatar' => $user['avatar'] ?? null,
        'email' => $user['email'] ?? null,
        'locale' => $user['locale'] ?? null,
        'mfa_enabled' => array_key_exists('mfa_enabled', $user) ? (bool) $user['mfa_enabled'] : null,
        'updated_at' => $user['updated_at'] ?? null,
    ];
}

function sessionPayload(?array $user = null): array
{
    if ($user === null) {
        $user = getAuthenticatedUser();
    }

    return [
        'authenticated' => $user !== null,
        'user' => $user ? serializeDiscordUser($user) : null,
    ];
}

function generateOAuthState(): string
{
    tx_session_start();
    $state = bin2hex(random_bytes(16));
    $_SESSION[TX_SESSION_STATE_KEY] = $state;
    return $state;
}

function validateOAuthState(?string $state): bool
{
    tx_session_start();
    $expected = $_SESSION[TX_SESSION_STATE_KEY] ?? null;
    unset($_SESSION[TX_SESSION_STATE_KEY]);

    if (!$expected || !$state) {
        return false;
    }

    return hash_equals($expected, (string) $state);
}

function rememberReturnUrl(?string $url): void
{
    tx_session_start();
    if ($url) {
        $_SESSION[TX_SESSION_RETURN_KEY] = $url;
    } else {
        unset($_SESSION[TX_SESSION_RETURN_KEY]);
    }
}

function consumeReturnUrl(): ?string
{
    tx_session_start();
    if (empty($_SESSION[TX_SESSION_RETURN_KEY])) {
        return null;
    }

    $url = (string) $_SESSION[TX_SESSION_RETURN_KEY];
    unset($_SESSION[TX_SESSION_RETURN_KEY]);

    return $url;
}
