<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

function discordConfig(): array
{
    $config = appConfigValue('discord', []);
    if (!is_array($config)) {
        return [];
    }

    $scopes = $config['scopes'] ?? ['identify'];
    if (!is_array($scopes)) {
        $scopes = array_filter(array_map('trim', explode(' ', (string) $scopes)));
    }

    if (empty($scopes)) {
        $scopes = ['identify'];
    }

    $config['scopes'] = array_values(array_filter(array_map('trim', $scopes)));

    return $config;
}

function isDiscordConfigured(): bool
{
    $config = discordConfig();
    $clientId = $config['client_id'] ?? '';
    $clientSecret = $config['client_secret'] ?? '';
    $redirectUri = $config['redirect_uri'] ?? '';

    if (!$clientId || !$clientSecret || !$redirectUri) {
        return false;
    }

    if ($clientId === 'YOUR_DISCORD_CLIENT_ID' || $clientSecret === 'YOUR_DISCORD_CLIENT_SECRET') {
        return false;
    }

    if (stripos($redirectUri, 'your-domain.example') !== false) {
        return false;
    }

    return true;
}

function buildDiscordAuthorizeUrl(string $state): string
{
    $config = discordConfig();
    $params = [
        'client_id' => $config['client_id'] ?? '',
        'redirect_uri' => $config['redirect_uri'] ?? '',
        'response_type' => 'code',
        'scope' => implode(' ', $config['scopes'] ?? ['identify']),
        'state' => $state,
    ];

    if (!empty($config['prompt'])) {
        $params['prompt'] = (string) $config['prompt'];
    }

    return 'https://discord.com/api/oauth2/authorize?' . http_build_query($params);
}

function exchangeDiscordCode(string $code): ?array
{
    $config = discordConfig();
    $body = http_build_query([
        'client_id' => $config['client_id'] ?? '',
        'client_secret' => $config['client_secret'] ?? '',
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $config['redirect_uri'] ?? '',
    ]);

    $response = httpRequest('POST', 'https://discord.com/api/oauth2/token', [
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        'body' => $body,
    ]);

    if (!$response || $response['status'] < 200 || $response['status'] >= 300) {
        return null;
    }

    $decoded = json_decode($response['body'], true);
    return is_array($decoded) ? $decoded : null;
}

function fetchDiscordUserProfile(string $accessToken): ?array
{
    $response = httpRequest('GET', 'https://discord.com/api/users/@me', [
        'headers' => ['Authorization' => 'Bearer ' . $accessToken],
    ]);

    if (!$response || $response['status'] < 200 || $response['status'] >= 300) {
        return null;
    }

    $decoded = json_decode($response['body'], true);
    return is_array($decoded) ? $decoded : null;
}

function storeDiscordUser(array $profile, array $token): ?array
{
    if (empty($profile['id'])) {
        return null;
    }

    $pdo = appDb();
    $expiresAt = null;
    if (isset($token['expires_in'])) {
        $expiresAt = gmdate('Y-m-d H:i:s', time() + (int) $token['expires_in']);
    }

    $mfaEnabled = null;
    if (array_key_exists('mfa_enabled', $profile)) {
        $mfaEnabled = $profile['mfa_enabled'] ? 1 : 0;
    }

    $sql = 'INSERT INTO discord_users (id, username, discriminator, global_name, avatar, email, locale, mfa_enabled, access_token, refresh_token, token_type, token_scope, token_expires_at)
            VALUES (:id, :username, :discriminator, :global_name, :avatar, :email, :locale, :mfa_enabled, :access_token, :refresh_token, :token_type, :token_scope, :token_expires_at)
            ON DUPLICATE KEY UPDATE
                username = VALUES(username),
                discriminator = VALUES(discriminator),
                global_name = VALUES(global_name),
                avatar = VALUES(avatar),
                email = VALUES(email),
                locale = VALUES(locale),
                mfa_enabled = VALUES(mfa_enabled),
                access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                token_type = VALUES(token_type),
                token_scope = VALUES(token_scope),
                token_expires_at = VALUES(token_expires_at),
                updated_at = CURRENT_TIMESTAMP';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', (string) $profile['id']);
    $stmt->bindValue(':username', $profile['username'] ?? '');
    $stmt->bindValue(':discriminator', $profile['discriminator'] ?? null);
    $stmt->bindValue(':global_name', $profile['global_name'] ?? null);
    $stmt->bindValue(':avatar', $profile['avatar'] ?? null);
    $stmt->bindValue(':email', $profile['email'] ?? null);
    $stmt->bindValue(':locale', $profile['locale'] ?? null);
    if ($mfaEnabled === null) {
        $stmt->bindValue(':mfa_enabled', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':mfa_enabled', $mfaEnabled, PDO::PARAM_INT);
    }
    $stmt->bindValue(':access_token', $token['access_token'] ?? '');
    $stmt->bindValue(':refresh_token', $token['refresh_token'] ?? null);
    $stmt->bindValue(':token_type', $token['token_type'] ?? null);
    $stmt->bindValue(':token_scope', $token['scope'] ?? null);
    if ($expiresAt === null) {
        $stmt->bindValue(':token_expires_at', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':token_expires_at', $expiresAt);
    }

    $stmt->execute();

    return findDiscordUserById((string) $profile['id']);
}

function findDiscordUserById(string $userId): ?array
{
    try {
        $pdo = appDb();
        $stmt = $pdo->prepare('SELECT id, username, discriminator, global_name, avatar, email, locale, mfa_enabled, token_type, token_scope, token_expires_at, created_at, updated_at FROM discord_users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    } catch (Throwable $exception) {
        error_log('Failed to load Discord user from database: ' . $exception->getMessage());
        return null;
    }
}

function httpRequest(string $method, string $url, array $options = []): ?array
{
    $headers = $options['headers'] ?? [];
    $body = $options['body'] ?? null;
    $timeout = (int) ($options['timeout'] ?? 15);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if (!empty($headers)) {
            $formatted = [];
            foreach ($headers as $key => $value) {
                if (is_int($key)) {
                    $formatted[] = $value;
                } else {
                    $formatted[] = $key . ': ' . $value;
                }
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formatted);
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return null;
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $payload = substr($response, $headerSize);
        curl_close($ch);

        return ['status' => (int) $status, 'body' => $payload];
    }

    $headerLines = '';
    if (!empty($headers)) {
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                $headerLines .= $value . "\r\n";
            } else {
                $headerLines .= $key . ': ' . $value . "\r\n";
            }
        }
    }

    $contextOptions = [
        'http' => [
            'method' => strtoupper($method),
            'header' => $headerLines,
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ];

    if ($body !== null) {
        $contextOptions['http']['content'] = $body;
    }

    $context = stream_context_create($contextOptions);
    $payload = @file_get_contents($url, false, $context);
    if ($payload === false) {
        return null;
    }

    $statusLine = $http_response_header[0] ?? 'HTTP/1.1 500';
    $status = 0;
    if (preg_match('{HTTP/\S+\s(\d+)}', $statusLine, $matches)) {
        $status = (int) $matches[1];
    }

    return ['status' => $status, 'body' => $payload];
}

function sanitizeReturnUrl(?string $url): ?string
{
    if (!$url) {
        return null;
    }

    $url = trim($url);
    if ($url === '') {
        return null;
    }

    if ($url[0] === '/') {
        return $url;
    }

    if (preg_match('#^https?://#i', $url) !== 1) {
        return null;
    }

    $parsed = parse_url($url);
    if (!$parsed) {
        return null;
    }

    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($parsed['host']) && $currentHost && strcasecmp($parsed['host'], $currentHost) !== 0) {
        return null;
    }

    $path = $parsed['path'] ?? '/';
    $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
    $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';

    return $path . $query . $fragment;
}
