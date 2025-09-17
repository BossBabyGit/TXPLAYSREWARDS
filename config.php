<?php
return [
    'database' => [
        'host' => getenv('TXPLAYS_DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('TXPLAYS_DB_PORT') ?: 3306),
        'name' => getenv('TXPLAYS_DB_NAME') ?: 'u382006905_tx',
        'user' => getenv('TXPLAYS_DB_USER') ?: 'u382006905_tx',
        'pass' => getenv('TXPLAYS_DB_PASS') ?: '3i>e#5ZrV',
        'charset' => getenv('TXPLAYS_DB_CHARSET') ?: 'utf8mb4',
    ],
    'discord' => [
        'client_id' => getenv('TXPLAYS_DISCORD_CLIENT_ID') ?: '1399198616102637658',
        'client_secret' => getenv('TXPLAYS_DISCORD_CLIENT_SECRET') ?: 'IfU1JT1CaJg0hEmHAYXN04lUvJbcTiou',
        'redirect_uri' => getenv('TXPLAYS_DISCORD_REDIRECT_URI') ?: 'https://txplays.com/discord-callback.php',
        'scopes' => array_values(array_filter(array_map('trim', explode(' ', getenv('TXPLAYS_DISCORD_SCOPES') ?: 'identify email')))),
        'prompt' => getenv('TXPLAYS_DISCORD_PROMPT') ?: null,
    ],
    'app' => [
        'base_url' => getenv('https://txplays.com') ?: '',
    ],
];

