<?php
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

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
$isGuest = (int) ($payload['is_guest'] ?? 0);

if ($uid === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Firebase UID is required.'
    ]);
    exit;
}

if ($fullName === '') {
    $fullName = $isGuest === 1 ? 'Guest User' : 'FOOD BOOK Customer';
}

$dbHost = 'localhost';
$dbUser = 'root';
$dbPassword = '';
$dbName = 'food_book';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);
    $mysqli->set_charset('utf8mb4');

    $createTableSql = "
        CREATE TABLE IF NOT EXISTS customer_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            firebase_uid VARCHAR(191) NOT NULL UNIQUE,
            full_name VARCHAR(191) NOT NULL,
            email VARCHAR(191) DEFAULT NULL,
            auth_provider VARCHAR(50) NOT NULL DEFAULT 'password',
            is_guest TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    $mysqli->query($createTableSql);

    $upsertSql = "
        INSERT INTO customer_users (firebase_uid, full_name, email, auth_provider, is_guest)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            full_name = VALUES(full_name),
            email = VALUES(email),
            auth_provider = VALUES(auth_provider),
            is_guest = VALUES(is_guest)
    ";

    $statement = $mysqli->prepare($upsertSql);
    $statement->bind_param('ssssi', $uid, $fullName, $email, $provider, $isGuest);
    $statement->execute();
    $statement->close();

    $_SESSION['food_book_user'] = [
        'uid' => $uid,
        'full_name' => $fullName,
        'email' => $email,
        'provider' => $provider,
        'is_guest' => $isGuest
    ];

    echo json_encode([
        'success' => true,
        'message' => 'User saved successfully.'
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $exception->getMessage()
    ]);
}
?>
