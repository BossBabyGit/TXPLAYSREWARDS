<?php
// Database configuration
$dbHost = 'localhost';
$dbName = 'u382006905_tx';
$dbUser = 'u382006905_tx';
$dbPass = '3i>e#5ZrV';

$apiKey = '14b391d6-47d6-44eb-b6b4-977cf5a9e481';
$baseUrl = 'https://affiliate.shuffle.com/stats/' . $apiKey;

// Set start and end time (Unix timestamp in seconds)
$startTime = strtotime('2025-09-01 00:00:00');
$endTime = strtotime('2025-09-31 23:59:59');

// Add parameters to the URL
$apiUrl = $baseUrl . '?startTime=' . $startTime . '&endTime=' . $endTime;

echo "API URL: " . $apiUrl . "\n";
echo "Start Time: " . date('Y-m-d H:i:s', $startTime) . " (Unix: $startTime)\n";
echo "End Time: " . date('Y-m-d H:i:s', $endTime) . " (Unix: $endTime)\n\n";

// Database connection
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "Database connection successful.\n";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// API request with better error handling
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

// Better error handling
if ($curlError) {
    die("cURL Error: " . $curlError . "\n");
}

if ($httpCode !== 200) {
    die("HTTP Error: " . $httpCode . "\nResponse: " . $response . "\n");
}

if (empty($response)) {
    die("Error: Empty response from API\n");
}

// Debug: Show full raw response
echo "Full Raw API Response:\n" . $response . "\n\n";

// JSON data to array
$data = json_decode($response, true);

// Better JSON error handling
if (json_last_error() !== JSON_ERROR_NONE) {
    die("JSON Decode Error: " . json_last_error_msg() . "\n");
}

// Check if data was received and is array
if (!is_array($data)) {
    die("Error: API response is not an array. Response: " . print_r($data, true) . "\n");
}

echo "Total records received: " . count($data) . "\n";

// Debug: Show structure of first record
if (!empty($data)) {
    echo "Sample record structure:\n";
    print_r(array_slice($data, 0, 1));
    echo "\n";
}

// Filter users with wagerAmount > 0
$activeUsers = array_filter($data, function($user) {
    if (!is_array($user)) {
        return false;
    }
    
    if (!isset($user['wagerAmount'])) {
        return false;
    }
    
    $wagerAmount = is_numeric($user['wagerAmount']) ? (float)$user['wagerAmount'] : 0;
    return $wagerAmount > 0;
});

echo "Active users (wager > 0): " . count($activeUsers) . "\n";

// Sort by wager amount (handle both string and numeric values)
usort($activeUsers, function($a, $b) {
    $wagerA = is_numeric($a['wagerAmount']) ? (float)$a['wagerAmount'] : 0;
    $wagerB = is_numeric($b['wagerAmount']) ? (float)$b['wagerAmount'] : 0;
    return $wagerB <=> $wagerA;
});

// Show top 5 users for debugging
echo "\nTop 5 users by wager amount:\n";
for ($i = 0; $i < min(5, count($activeUsers)); $i++) {
    $user = $activeUsers[$i];
    $wager = is_numeric($user['wagerAmount']) ? number_format($user['wagerAmount'], 2) : $user['wagerAmount'];
    echo ($i + 1) . ". " . $user['username'] . " - Wager: " . $wager . "\n";
}
echo "\n";

// Clear existing data for the date range (optional - remove if you want to keep historical data)
try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE created_at >= ? AND created_at <= ?");
    $stmt->execute([
        date('Y-m-d H:i:s', $startTime),
        date('Y-m-d H:i:s', $endTime)
    ]);
    echo "Cleared existing data for the date range.\n";
} catch (PDOException $e) {
    echo "Warning: Could not clear existing data: " . $e->getMessage() . "\n";
}

// Prepare insert statement
$insertStmt = $pdo->prepare("
    INSERT INTO users (username, campaign_code, wager_amount) 
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE 
    campaign_code = VALUES(campaign_code),
    wager_amount = VALUES(wager_amount),
    updated_at = CURRENT_TIMESTAMP
");

// Insert users into database
$insertedCount = 0;
$totalWagered = 0;

foreach ($activeUsers as $user) {
    try {
        $username = isset($user['username']) ? trim($user['username']) : 'Unknown';
        $campaignCode = isset($user['campaignCode']) ? trim($user['campaignCode']) : '';
        $wagerAmount = is_numeric($user['wagerAmount']) ? (float)$user['wagerAmount'] : 0;
        
        $insertStmt->execute([$username, $campaignCode, $wagerAmount]);
        $insertedCount++;
        $totalWagered += $wagerAmount;
        
    } catch (PDOException $e) {
        echo "Error inserting user " . ($user['username'] ?? 'unknown') . ": " . $e->getMessage() . "\n";
    }
}

echo "\nDatabase insertion completed:\n";
echo "- Inserted/Updated users: $insertedCount\n";
echo "- Total wagered amount: " . number_format($totalWagered, 2) . "\n";
echo "- Date range: " . date('Y-m-d H:i:s', $startTime) . " to " . date('Y-m-d H:i:s', $endTime) . "\n";

// Show some statistics from the database
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_users, SUM(wager_amount) as total_wagers FROM users WHERE wager_amount > 0");
    $stats = $stmt->fetch();
    
    echo "\nDatabase Statistics:\n";
    echo "- Total active users in database: " . $stats['total_users'] . "\n";
    echo "- Total wagers in database: " . number_format($stats['total_wagers'], 2) . "\n";
    
    // Show top 5 users from database
    $stmt = $pdo->query("SELECT username, campaign_code, wager_amount FROM users WHERE wager_amount > 0 ORDER BY wager_amount DESC LIMIT 5");
    $topUsers = $stmt->fetchAll();
    
    echo "\nTop 5 users from database:\n";
    foreach ($topUsers as $index => $user) {
        echo ($index + 1) . ". " . $user['username'] . " - Wager: " . number_format($user['wager_amount'], 2) . 
             " (Campaign: " . $user['campaign_code'] . ")\n";
    }
    
} catch (PDOException $e) {
    echo "Error retrieving statistics: " . $e->getMessage() . "\n";
}

echo "\nScript completed successfully.\n";
?>
