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

    clearExistingRange($pdo, $start, $end);
    $insertStats = insertUsers($pdo, $activeUsers, $start);

    echo "Database insertion completed for {$label} period:\n";
    echo "- Inserted/Updated users: " . $insertStats['inserted'] . "\n";
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

function clearExistingRange(PDO $pdo, DateTimeImmutable $start, DateTimeImmutable $end): void
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
}

function insertUsers(PDO $pdo, array $users, DateTimeImmutable $rangeStart): array
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

    foreach ($users as $index => $user) {
        try {
            $username = isset($user['username']) ? trim((string) $user['username']) : 'Unknown';
            $campaignCode = isset($user['campaignCode']) ? trim((string) $user['campaignCode']) : 'TX';
            $wagerAmount = is_numeric($user['wagerAmount']) ? (float) $user['wagerAmount'] : 0.0;

            $createdAt = $rangeStart->modify('+' . $index . ' seconds')->format('Y-m-d H:i:s');

            $insertStmt->execute([$username, $campaignCode, $wagerAmount, $createdAt]);
            $insertedCount++;
            $totalWagered += $wagerAmount;
        } catch (PDOException $e) {
            echo 'Error inserting user ' . ($user['username'] ?? 'unknown') . ': ' . $e->getMessage() . "\n";
        }
    }

    return [
        'inserted' => $insertedCount,
        'total' => $totalWagered,
    ];
}

function summarizeDatabaseRange(PDO $pdo, DateTimeImmutable $start, DateTimeImmutable $end): void
{
    try {
        $rangeStart = $start->format('Y-m-d H:i:s');
        $rangeEnd = $end->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare('SELECT COUNT(*) as total_users, SUM(wager_amount) as total_wagers FROM users WHERE wager_amount > 0 AND created_at >= ? AND created_at < ?');
        $stmt->execute([$rangeStart, $rangeEnd]);
        $stats = $stmt->fetch() ?: ['total_users' => 0, 'total_wagers' => 0];

        echo "\nDatabase Statistics for this period:\n";
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
?>