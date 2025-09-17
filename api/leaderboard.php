<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// load config
$config = require dirname(__DIR__) . '/config.php';
$db = $config['database'] ?? [];

// DSN
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $db['host'],
    $db['port'],
    $db['name'],
    $db['charset'] ?? 'utf8mb4'
);

try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // optional: read query params (your frontend sends ?type=weekly)
    $type = $_GET['type'] ?? 'weekly';
    $campaign = 'TX'; // adjust/derive if needed

    // Top 20
    $stmt = $pdo->prepare("
        SELECT username, wager_amount
        FROM users
        WHERE campaign_code = :campaign
        ORDER BY wager_amount DESC
        LIMIT 20
    ");
    $stmt->execute([':campaign' => $campaign]);
    $rows = $stmt->fetchAll() ?: [];

    // Stats
    $s = $pdo->prepare("
        SELECT
            COUNT(*)                       AS total_players,
            COALESCE(SUM(wager_amount),0)  AS total_wagered,
            COALESCE(MAX(wager_amount),0)  AS highest_wager
        FROM users
        WHERE campaign_code = :campaign
    ");
    $s->execute([':campaign' => $campaign]);
    $stats = $s->fetch() ?: ['total_players'=>0,'total_wagered'=>0,'highest_wager'=>0];

    // Prize map
    // Prize map for ranks 1–20
$prizes = [
    1 => 1000,
    2 => 750,
    3 => 500,
    4 => 275,
    5 => 175,
    6 => 75,
    7 => 75,
    8 => 50,
    9 => 50,
    10 => 50,
    11 => 0,
    12 => 0,
    13 => 0,
    14 => 0,
    15 => 0,
    16 => 0,
    17 => 0,
    18 => 0,
    19 => 0,
    20 => 0,
];


    // Build entries
    $entries = [];
    $rank = 1;
    foreach ($rows as $row) {
        $entries[] = [
            'rank'          => $rank,
            'username'      => $row['username'],
            'total_wagered' => (float)$row['wager_amount'],
            'prize'         => (int)($prizes[$rank] ?? 0),
        ];
        $rank++;
    }

    // Your JS accepts either `data[range]` OR a flat object.
    $payload = [
        'entries' => $entries,
        'stats'   => [
            'total_players' => (int)$stats['total_players'],
            'total_wagered' => (float)$stats['total_wagered'],
            'highest_wager' => (float)$stats['highest_wager'],
        ],
        'prizes'  => $prizes,
    ];

    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>