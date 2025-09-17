<?php
declare(strict_types=1);

// Database configuration
$dbHost = 'localhost';
$dbName = 'u382006905_tx';
$dbUser = 'u382006905_tx';
$dbPass = '3i>e#5ZrV';

$apiKey = '14b391d6-47d6-44eb-b6b4-977cf5a9e481';
$baseUrl = 'https://affiliate.shuffle.com/stats/' . $apiKey;

$timezone = new DateTimeZone('America/Chicago');
$now = new DateTimeImmutable('now', $timezone);

$currentStart = $now->modify('first day of this month 00:00:00');
$nextStart = $currentStart->modify('first day of next month 00:00:00');
$previousStart = $currentStart->modify('-1 month');

$ranges = [
    'current' => ['start' => $currentStart, 'end' => $nextStart],
    'previous' => ['start' => $previousStart, 'end' => $currentStart],
];

echo "Processing leaderboard sync for the following periods:\n";
foreach ($ranges as $label => $range) {
    $displayStart = $range['start']->format('Y-m-d H:i:s');
    $displayEnd = $range['end']->modify('-1 second')->format('Y-m-d H:i:s');
    echo sprintf("- %s: %s to %s\n", ucfirst($label), $displayStart, $displayEnd);
}
echo "\n";

// Database connection
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "Database connection successful.\n\n";
    ensureLeaderboardEntriesTable($pdo);
    migrateLegacyUsers($pdo);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

foreach ($ranges as $label => $range) {
    $start = $range['start'];
    $end = $range['end'];

    $startTime = $start->getTimestamp();
    $endTime = $end->modify('-1 second')->getTimestamp();
    $apiUrl = sprintf('%s?startTime=%d&endTime=%d', $baseUrl, $startTime, $endTime);

    echo str_repeat('=', 40) . "\n";
    echo sprintf("Fetching %s leaderboard data\n", $label);
    echo str_repeat('-', 40) . "\n";
    echo "API URL: " . $apiUrl . "\n";
    echo "Start Time: " . $start->format('Y-m-d H:i:s') . " (Unix: $startTime)\n";
    echo "End Time: " . $end->modify('-1 second')->format('Y-m-d H:i:s') . " (Unix: $endTime)\n\n";

    try {
        $data = fetchLeaderboardData($apiUrl);
    } catch (RuntimeException $e) {
        echo "Failed to fetch data: " . $e->getMessage() . "\n\n";
        continue;
    }

    $activeUsers = filterActiveUsers($data);
    echo "Active users (wager > 0): " . count($activeUsers) . "\n";

    if (empty($activeUsers)) {
        echo "No active users returned for this period. Skipping database update.\n\n";
        continue;
    }

    usort($activeUsers, static function (array $a, array $b): int {
        $wagerA = is_numeric($a['wagerAmount'] ?? null) ? (float) $a['wagerAmount'] : 0.0;
        $wagerB = is_numeric($b['wagerAmount'] ?? null) ? (float) $b['wagerAmount'] : 0.0;
        return $wagerB <=> $wagerA;
    });

    echo "\nTop 5 users by wager amount:\n";
    foreach (array_slice($activeUsers, 0, 5) as $index => $user) {
        $wager = is_numeric($user['wagerAmount']) ? number_format((float) $user['wagerAmount'], 2) : ($user['wagerAmount'] ?? '0');
        $username = $user['username'] ?? 'Unknown';
        echo sprintf("%d. %s - Wager: %s\n", $index + 1, $username, $wager);
    }
    echo "\n";

    $campaignCodes = extractCampaignCodes($activeUsers);
    clearExistingRange($pdo, $start, $end, $campaignCodes);
    $insertStats = insertUsers($pdo, $activeUsers, $start, $end);

    echo "Database insertion completed for {$label} period:\n";
    echo "- Inserted/Updated users: " . $insertStats['inserted'] . "\n";
    if (isset($insertStats['entries_inserted'])) {
        echo "- Inserted leaderboard entries: " . $insertStats['entries_inserted'] . "\n";
    }
    echo "- Total wagered amount: " . number_format($insertStats['total'], 2) . "\n";
    echo "- Date range: " . $start->format('Y-m-d H:i:s') . " to " . $end->modify('-1 second')->format('Y-m-d H:i:s') . "\n";

    summarizeDatabaseRange($pdo, $start, $end);

    echo "\n";
}

echo "Script completed successfully.\n";

function fetchLeaderboardData(string $apiUrl): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PHP API Client/1.0');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new RuntimeException('cURL Error: ' . $curlError);
    }

    if ($httpCode !== 200) {
        throw new RuntimeException('HTTP Error: ' . $httpCode . '\nResponse: ' . $response);
    }

    if ($response === false || $response === '') {
        throw new RuntimeException('Error: Empty response from API');
    }

    echo "Full Raw API Response:\n" . $response . "\n\n";

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('JSON Decode Error: ' . json_last_error_msg());
    }

    if (!is_array($data)) {
        throw new RuntimeException('Error: API response is not an array.');
    }

    echo 'Total records received: ' . count($data) . "\n";
    if (!empty($data)) {
        echo "Sample record structure:\n";
        print_r(array_slice($data, 0, 1));
        echo "\n";
    }

    return $data;
}

function filterActiveUsers(array $data): array
{
    $active = array_filter($data, static function ($user): bool {
        if (!is_array($user)) {
            return false;
        }

        if (!isset($user['wagerAmount'])) {
            return false;
        }

        $wagerAmount = is_numeric($user['wagerAmount']) ? (float) $user['wagerAmount'] : 0.0;
        return $wagerAmount > 0;
    });

    return array_values($active);
}

function clearExistingRange(PDO $pdo, DateTimeImmutable $start, DateTimeImmutable $end, array $campaignCodes = []): void
{
    try {
        $stmt = $pdo->prepare('DELETE FROM users WHERE created_at >= ? AND created_at < ?');
        $stmt->execute([
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
        ]);
        echo 'Cleared existing data for the date range. Rows affected: ' . $stmt->rowCount() . "\n";
    } catch (PDOException $e) {
        echo 'Warning: Could not clear existing data: ' . $e->getMessage() . "\n";
    }

    clearLeaderboardEntries($pdo, $start, $end, $campaignCodes);
}

function insertUsers(PDO $pdo, array $users, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
{
    $insertStmt = $pdo->prepare('
        INSERT INTO users (username, campaign_code, wager_amount, created_at)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            campaign_code = VALUES(campaign_code),
            wager_amount = VALUES(wager_amount),
            created_at = VALUES(created_at),
            updated_at = CURRENT_TIMESTAMP
    ');

    $insertedCount = 0;
    $totalWagered = 0.0;

    $entryStmt = prepareLeaderboardEntryStatement($pdo);
    $periodStart = $rangeStart->format('Y-m-d H:i:s');
    $periodEnd = $rangeEnd->format('Y-m-d H:i:s');
    $now = new DateTimeImmutable('now', $rangeStart->getTimezone());
    $nowString = $now->format('Y-m-d H:i:s');

    $entriesInserted = 0;

    foreach ($users as $index => $user) {
        try {
            $username = isset($user['username']) ? trim((string) $user['username']) : 'Unknown';
            $campaignCode = normalizeCampaignCode($user['campaignCode'] ?? null);
            $wagerAmount = is_numeric($user['wagerAmount']) ? (float) $user['wagerAmount'] : 0.0;

            $createdAt = $rangeStart->modify('+' . $index . ' seconds')->format('Y-m-d H:i:s');

            $insertStmt->execute([$username, $campaignCode, $wagerAmount, $createdAt]);
            $insertedCount++;
            $totalWagered += $wagerAmount;

            if ($entryStmt instanceof PDOStatement) {
                try {
                    $entryStmt->execute([
                        $username,
                        $campaignCode,
                        $wagerAmount,
                        $periodStart,
                        $periodEnd,
                        $createdAt,
                        $nowString,
                    ]);
                    $entriesInserted++;
                } catch (PDOException $e) {
                    echo 'Error inserting leaderboard entry ' . $username . ': ' . $e->getMessage() . "\n";
                }
            }
        } catch (PDOException $e) {
            echo 'Error inserting user ' . ($user['username'] ?? 'unknown') . ': ' . $e->getMessage() . "\n";
        }
    }

    return [
        'inserted' => $insertedCount,
        'total' => $totalWagered,
        'entries_inserted' => $entriesInserted,
    ];
}

function summarizeDatabaseRange(PDO $pdo, DateTimeImmutable $start, DateTimeImmutable $end): void
{
    $rangeStart = $start->format('Y-m-d H:i:s');
    $rangeEnd = $end->format('Y-m-d H:i:s');

    if (tableExists($pdo, 'leaderboard_entries')) {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) as total_users, SUM(wager_amount) as total_wagers FROM leaderboard_entries WHERE period_start = ? AND period_end = ?');
            $stmt->execute([$rangeStart, $rangeEnd]);
            $stats = $stmt->fetch() ?: ['total_users' => 0, 'total_wagers' => 0];

            echo "\nDatabase Statistics for this period (leaderboard_entries):\n";
            echo '- Total active users in database: ' . (int) ($stats['total_users'] ?? 0) . "\n";
            echo '- Total wagers in database: ' . number_format((float) ($stats['total_wagers'] ?? 0), 2) . "\n";

            $stmt = $pdo->prepare('SELECT username, campaign_code, wager_amount FROM leaderboard_entries WHERE period_start = ? AND period_end = ? ORDER BY wager_amount DESC LIMIT 5');
            $stmt->execute([$rangeStart, $rangeEnd]);
            $topUsers = $stmt->fetchAll();

            echo "Top 5 users stored in leaderboard_entries for this period:\n";
            foreach ($topUsers as $index => $user) {
                echo sprintf(
                    "%d. %s - Wager: %s (Campaign: %s)\n",
                    $index + 1,
                    $user['username'] ?? 'unknown',
                    number_format((float) ($user['wager_amount'] ?? 0), 2),
                    $user['campaign_code'] ?? ''
                );
            }
            return;
        } catch (PDOException $e) {
            echo 'Warning: Could not summarize leaderboard_entries: ' . $e->getMessage() . "\n";
        }
    }

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) as total_users, SUM(wager_amount) as total_wagers FROM users WHERE wager_amount > 0 AND created_at >= ? AND created_at < ?');
        $stmt->execute([$rangeStart, $rangeEnd]);
        $stats = $stmt->fetch() ?: ['total_users' => 0, 'total_wagers' => 0];

        echo "\nDatabase Statistics for this period (users table):\n";
        echo '- Total active users in database: ' . (int) ($stats['total_users'] ?? 0) . "\n";
        echo '- Total wagers in database: ' . number_format((float) ($stats['total_wagers'] ?? 0), 2) . "\n";

        $stmt = $pdo->prepare('SELECT username, campaign_code, wager_amount FROM users WHERE wager_amount > 0 AND created_at >= ? AND created_at < ? ORDER BY wager_amount DESC LIMIT 5');
        $stmt->execute([$rangeStart, $rangeEnd]);
        $topUsers = $stmt->fetchAll();

        echo "Top 5 users stored in database for this period:\n";
        foreach ($topUsers as $index => $user) {
            echo sprintf(
                "%d. %s - Wager: %s (Campaign: %s)\n",
                $index + 1,
                $user['username'] ?? 'unknown',
                number_format((float) ($user['wager_amount'] ?? 0), 2),
                $user['campaign_code'] ?? ''
            );
        }
    } catch (PDOException $e) {
        echo 'Error retrieving statistics: ' . $e->getMessage() . "\n";
    }
}

function normalizeCampaignCode($code): string
{
    if (is_string($code)) {
        $trimmed = trim($code);
        if ($trimmed !== '') {
            return $trimmed;
        }
    }

    return 'TX';
}

function extractCampaignCodes(array $users): array
{
    if (!$users) {
        return ['TX'];
    }

    $codes = [];
    foreach ($users as $user) {
        $codes[normalizeCampaignCode($user['campaignCode'] ?? null)] = true;
    }

    return array_keys($codes);
}

function tableExists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

function ensureLeaderboardEntriesTable(PDO $pdo): void
{
    try {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS leaderboard_entries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) NOT NULL,
                campaign_code VARCHAR(64) NOT NULL,
                wager_amount DECIMAL(20,2) NOT NULL DEFAULT 0,
                period_start DATETIME NOT NULL,
                period_end DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_leaderboard_entry (username, campaign_code, period_start),
                KEY idx_campaign_period (campaign_code, period_start),
                KEY idx_period_range (period_start, period_end)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    } catch (PDOException $e) {
        echo 'Warning: Could not ensure leaderboard_entries table: ' . $e->getMessage() . "\n";
    }
}

function migrateLegacyUsers(PDO $pdo): void
{
    if (!tableExists($pdo, 'leaderboard_entries')) {
        return;
    }

    try {
        $existing = $pdo->query('SELECT COUNT(*) FROM leaderboard_entries');
        if ($existing && (int) $existing->fetchColumn() > 0) {
            return;
        }
    } catch (PDOException $e) {
        echo 'Warning: Could not inspect leaderboard_entries: ' . $e->getMessage() . "\n";
        return;
    }

    try {
        $sql = '
            INSERT INTO leaderboard_entries (username, campaign_code, wager_amount, period_start, period_end, created_at, updated_at)
            SELECT
                username,
                CASE WHEN campaign_code IS NULL OR campaign_code = "" THEN "TX" ELSE campaign_code END AS campaign_code,
                wager_amount,
                DATE_FORMAT(created_at, "%Y-%m-01 00:00:00") AS period_start,
                DATE_FORMAT(DATE_ADD(DATE_FORMAT(created_at, "%Y-%m-01 00:00:00"), INTERVAL 1 MONTH), "%Y-%m-01 00:00:00") AS period_end,
                created_at,
                COALESCE(updated_at, created_at, NOW()) AS updated_at
            FROM users
            WHERE created_at IS NOT NULL AND wager_amount > 0
        ';

        $migrated = $pdo->exec($sql);
        if ($migrated !== false) {
            echo 'Migrated ' . (int) $migrated . " legacy rows into leaderboard_entries.\n";
        }
    } catch (PDOException $e) {
        echo 'Warning: Could not migrate legacy user data: ' . $e->getMessage() . "\n";
    }
}

function prepareLeaderboardEntryStatement(PDO $pdo): ?PDOStatement
{
    if (!tableExists($pdo, 'leaderboard_entries')) {
        return null;
    }

    try {
        return $pdo->prepare('
            INSERT INTO leaderboard_entries
                (username, campaign_code, wager_amount, period_start, period_end, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                wager_amount = VALUES(wager_amount),
                updated_at = VALUES(updated_at)
        ');
    } catch (PDOException $e) {
        echo 'Warning: Could not prepare leaderboard entry statement: ' . $e->getMessage() . "\n";
        return null;
    }
}

function clearLeaderboardEntries(PDO $pdo, DateTimeImmutable $start, DateTimeImmutable $end, array $campaignCodes = []): void
{
    if (!tableExists($pdo, 'leaderboard_entries')) {
        return;
    }

    $periodStart = $start->format('Y-m-d H:i:s');
    $periodEnd = $end->format('Y-m-d H:i:s');

    try {
        if ($campaignCodes) {
            $stmt = $pdo->prepare('DELETE FROM leaderboard_entries WHERE period_start = ? AND period_end = ? AND campaign_code = ?');
            foreach ($campaignCodes as $code) {
                $stmt->execute([$periodStart, $periodEnd, $code]);
                echo 'Cleared leaderboard_entries rows for campaign ' . $code . ': ' . $stmt->rowCount() . "\n";
            }
        } else {
            $stmt = $pdo->prepare('DELETE FROM leaderboard_entries WHERE period_start = ? AND period_end = ?');
            $stmt->execute([$periodStart, $periodEnd]);
            echo 'Cleared leaderboard_entries rows for period. Rows affected: ' . $stmt->rowCount() . "\n";
        }
    } catch (PDOException $e) {
        echo 'Warning: Could not clear leaderboard_entries: ' . $e->getMessage() . "\n";
    }
}
?>