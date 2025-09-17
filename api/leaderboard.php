<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!function_exists('leaderboardTableExists')) {
    function leaderboardTableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
}

if (!function_exists('legacyRangeHasData')) {
    function legacyRangeHasData(PDO $pdo, string $campaign, string $start, string $end): bool
    {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE campaign_code = :campaign AND created_at >= :start AND created_at < :end');
            $stmt->execute([
                ':campaign' => $campaign,
                ':start' => $start,
                ':end' => $end,
            ]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}

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

    // optional: read query params (frontend uses "weekly" for current and "monthly" for previous)
    $type = strtolower(trim($_GET['type'] ?? 'weekly'));
    $campaign = 'TX'; // adjust/derive if needed

    $tz = new DateTimeZone('America/Chicago');
    $now = new DateTimeImmutable('now', $tz);
    $currentStart = $now->modify('first day of this month 00:00:00');
    $nextStart = $currentStart->modify('first day of next month 00:00:00');
    $previousStart = $currentStart->modify('-1 month');

    $rangeKey = match ($type) {
        'monthly', 'previous' => 'previous',
        default => 'current',
    };

    $rangeStartDate = $rangeKey === 'previous' ? $previousStart : $currentStart;
    $rangeEndDate = $rangeKey === 'previous' ? $currentStart : $nextStart;

    $rangeStart = $rangeStartDate->format('Y-m-d H:i:s');
    $rangeEnd = $rangeEndDate->format('Y-m-d H:i:s');
    $displayStart = $rangeStartDate->format('Y-m-d H:i:s');
    $displayEnd = $rangeEndDate->modify('-1 second')->format('Y-m-d H:i:s');

    $rows = [];
    $stats = ['total_players' => 0, 'total_wagered' => 0, 'highest_wager' => 0];
    $dataSource = 'users';

    $periodStart = $rangeStart;
    $useEntriesTable = leaderboardTableExists($pdo, 'leaderboard_entries');

    if ($useEntriesTable) {
        try {
            $params = [
                ':campaign' => $campaign,
                ':period'   => $periodStart,
            ];

            $entriesSql = '
                SELECT username, wager_amount
                FROM leaderboard_entries
                WHERE campaign_code = :campaign AND period_start = :period
                ORDER BY wager_amount DESC
                LIMIT 20
            ';
            $stmt = $pdo->prepare($entriesSql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll() ?: [];

            $statsSql = '
                SELECT
                    COUNT(*)                      AS total_players,
                    COALESCE(SUM(wager_amount),0) AS total_wagered,
                    COALESCE(MAX(wager_amount),0) AS highest_wager
                FROM leaderboard_entries
                WHERE campaign_code = :campaign AND period_start = :period
            ';
            $s = $pdo->prepare($statsSql);
            $s->execute($params);
            $stats = $s->fetch() ?: $stats;

            if (!$rows && (int) ($stats['total_players'] ?? 0) === 0 && legacyRangeHasData($pdo, $campaign, $rangeStart, $rangeEnd)) {
                $useEntriesTable = false;
                $rows = [];
                $stats = ['total_players' => 0, 'total_wagered' => 0, 'highest_wager' => 0];
            } else {
                $dataSource = 'leaderboard_entries';
            }
        } catch (PDOException $e) {
            $useEntriesTable = false;
        }
    }

    if (!$useEntriesTable) {
        $rangeClause = ' AND created_at >= :start AND created_at < :end';
        $params = [
            ':campaign' => $campaign,
            ':start'    => $rangeStart,
            ':end'      => $rangeEnd,
        ];
        $fallbackParams = [':campaign' => $campaign];

        $execute = static function (PDO $pdo, string $sqlBase, array $params, array $fallbackParams, string $rangeClause): PDOStatement {
            $sql = sprintf($sqlBase, $rangeClause);
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            } catch (PDOException $e) {
                if ($rangeClause === '') {
                    throw $e;
                }
                $stmt = $pdo->prepare(sprintf($sqlBase, ''));
                $stmt->execute($fallbackParams);
                return $stmt;
            }
        };

        $entriesSql = "
            SELECT username, wager_amount
            FROM users
            WHERE campaign_code = :campaign%s
            ORDER BY wager_amount DESC
            LIMIT 20
        ";
        $stmt = $execute($pdo, $entriesSql, $params, $fallbackParams, $rangeClause);
        $rows = $stmt->fetchAll() ?: [];

        $statsSql = "
            SELECT
                COUNT(*)                      AS total_players,
                COALESCE(SUM(wager_amount),0) AS total_wagered,
                COALESCE(MAX(wager_amount),0) AS highest_wager
            FROM users
            WHERE campaign_code = :campaign%s
        ";
        $s = $execute($pdo, $statsSql, $params, $fallbackParams, $rangeClause);
        $stats = $s->fetch() ?: ['total_players' => 0, 'total_wagered' => 0, 'highest_wager' => 0];
    }

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
        'range'   => [
            'requested'     => $type,
            'effective'     => $rangeKey,
            'start'         => $rangeStart,
            'end'           => $rangeEnd,
            'display_start' => $displayStart,
            'display_end'   => $displayEnd,
            'timezone'      => $tz->getName(),
            'source'        => $dataSource,
        ],
    ];

    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>