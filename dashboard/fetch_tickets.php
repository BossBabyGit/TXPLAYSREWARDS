<?php
// CONFIG
$dbHost = 'localhost';
$dbName = 'u382006905_tx';
$dbUser = 'u382006905_tx';
$dbPass = '3i>e#5ZrV';
$apiKey = '14b391d6-47d6-44eb-b6b4-977cf5a9e481';

// === 1. LOAD EVENT DATA FROM JSON ===
$eventJson = file_get_contents(__DIR__ . '/current_event.json');
$eventData = json_decode($eventJson, true);

if (
    !$eventData ||
    !isset($eventData['start_date'], $eventData['end_date'], $eventData['ticket_methods']) ||
    !is_array($eventData['ticket_methods'])
) {
    die("Invalid event data in current_event.json\n");
}

$eventTitle = $eventData['title'];
$startTime = strtotime($eventData['start_date']);
$endTime   = strtotime($eventData['end_date']);

// === FIND SLOT METHOD ===
$slotMethod = null;
foreach ($eventData['ticket_methods'] as $method) {
    if (strtolower($method['name']) === 'slots') {
        $slotMethod = $method;
        break;
    }
}

if (!$slotMethod) {
    die("Slot-based ticketing method not found in ticket_methods\n");
}

$wagerPerTicket = floatval($slotMethod['wager_amount']);
$ticketsPerUnit = floatval($slotMethod['tickets_earned']);

// === 2. DB CONNECTION ===
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ DB connected.\n";
} catch (PDOException $e) {
    die("❌ DB error: " . $e->getMessage());
}

// === 3. FETCH API DATA ===
$apiUrl = "https://affiliate.shuffle.com/stats/{$apiKey}?startTime={$startTime}&endTime={$endTime}";
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode !== 200 || empty($response)) {
    die("❌ API Error: $curlError / HTTP $httpCode\n$response\n");
}

$data = json_decode($response, true);
if (!is_array($data)) die("❌ Invalid JSON response\n");

echo "📦 API data received for event '$eventTitle' (" . count($data) . " users)\n";

// === 4. PREPARE DB TABLE ===
$pdo->exec("
    CREATE TABLE IF NOT EXISTS event_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_title VARCHAR(255),
        username VARCHAR(100),
        total_wagered DECIMAL(12,2),
        tickets INT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_event_user (event_title, username)
    )
");

// === 5. PROCESS USERS ===
$insertStmt = $pdo->prepare("
    INSERT INTO event_tickets (event_title, username, total_wagered, tickets)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        total_wagered = VALUES(total_wagered),
        tickets = VALUES(tickets),
        updated_at = CURRENT_TIMESTAMP
");

$inserted = 0;
foreach ($data as $user) {
    if (!isset($user['username'], $user['wagerAmount'])) continue;

    $username = trim($user['username']);
    $wagered = floatval($user['wagerAmount']);
    if ($wagered <= 0) continue;

    $tickets = floor(($wagered / $wagerPerTicket) * $ticketsPerUnit);
    if ($tickets < 1) continue;

    try {
        $insertStmt->execute([$eventTitle, $username, $wagered, $tickets]);
        $inserted++;
    } catch (PDOException $e) {
        echo "⚠️ Failed to insert $username: " . $e->getMessage() . "\n";
    }
}

echo "✅ Stored $inserted users with at least 1 ticket.\n";
echo "📅 Event: $eventTitle\n🕒 Range: " . date('Y-m-d H:i', $startTime) . " – " . date('Y-m-d H:i', $endTime) . "\n";
?>
