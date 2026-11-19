<?php
session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.'
    ]);
    exit;
}

$uid = trim($payload['uid'] ?? '');
$fullName = trim($payload['full_name'] ?? '');
$email = trim($payload['email'] ?? '');
$provider = trim($payload['provider'] ?? 'password');
$emailVerified = (int) ($payload['email_verified'] ?? 0);

if ($uid === '' || $email === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Firebase UID and email are required.'
    ]);
    exit;
}

if ($fullName === '') {
    $fullName = 'Restaurant Partner';
}

$fixedRestaurantId = null;
if (strcasecmp($email, 's23_khan_atifali@mgmcen.ac.in') === 0) {
    $fixedRestaurantId = 1;
}

try {
    $mysqli = getFoodBookDb();

    $createTableSql = "
        CREATE TABLE IF NOT EXISTS restaurant_partner_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            firebase_uid VARCHAR(191) NOT NULL UNIQUE,
            full_name VARCHAR(191) NOT NULL,
            email VARCHAR(191) NOT NULL UNIQUE,
            restaurant_id INT UNSIGNED DEFAULT NULL,
            auth_provider VARCHAR(50) NOT NULL DEFAULT 'password',
            email_verified TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $mysqli->query($createTableSql);
    $mysqli->query("ALTER TABLE restaurant_partner_users ADD COLUMN IF NOT EXISTS restaurant_id INT UNSIGNED DEFAULT NULL AFTER email");

    $upsertSql = "
        INSERT INTO restaurant_partner_users (firebase_uid, full_name, email, restaurant_id, auth_provider, email_verified)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            full_name = VALUES(full_name),
            email = VALUES(email),
            restaurant_id = COALESCE(VALUES(restaurant_id), restaurant_partner_users.restaurant_id),
            auth_provider = VALUES(auth_provider),
            email_verified = VALUES(email_verified)
    ";

    $statement = $mysqli->prepare($upsertSql);
    $statement->bind_param('sssisi', $uid, $fullName, $email, $fixedRestaurantId, $provider, $emailVerified);
    $statement->execute();
    $statement->close();

    $partnerStatement = $mysqli->prepare("
        SELECT id, firebase_uid, full_name, email, restaurant_id
        FROM restaurant_partner_users
        WHERE firebase_uid = ?
        LIMIT 1
    ");
    $partnerStatement->bind_param('s', $uid);
    $partnerStatement->execute();
    $partnerResult = $partnerStatement->get_result();
    $partnerUser = $partnerResult->fetch_assoc();
    $partnerStatement->close();

    $_SESSION['food_book_partner'] = [
        'id' => (int) ($partnerUser['id'] ?? 0),
        'uid' => $uid,
        'full_name' => $fullName,
        'email' => $email,
        'restaurant_id' => isset($partnerUser['restaurant_id']) ? (int) $partnerUser['restaurant_id'] : 0
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Restaurant partner user saved successfully.',
        'restaurant_id' => isset($partnerUser['restaurant_id']) ? (int) $partnerUser['restaurant_id'] : 0
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $exception->getMessage()
    ]);
}
?>
