<?php
require_once __DIR__ . '/order-utils.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    $payload = json_decode(file_get_contents('php://input'), true);
    $action = trim((string) ($payload['action'] ?? ''));

    if ($action !== 'submit_rating') {
        if ($action !== 'submit_review') {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            exit;
        }
    }

    $orderId = (int) ($payload['order_id'] ?? 0);
    $orderItemId = (int) ($payload['order_item_id'] ?? 0);
    $rating = (int) ($payload['rating'] ?? 0);
    $reviewText = trim((string) ($payload['review_text'] ?? ''));

    if ($orderId <= 0 || $orderItemId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Valid order and item are required.']);
        exit;
    }

    if ($action === 'submit_rating' && ($rating < 1 || $rating > 5)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
        exit;
    }

    if ($action === 'submit_review' && $reviewText === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Review text is required.']);
        exit;
    }

    try {
        $mysqli = getFoodBookDb();
        ensureFoodBookOrderTables($mysqli);
        $order = fetchOrderWithItems($mysqli, $orderId);

        if (!$order) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }

        if (!canCustomerRateOrder($order)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Ratings are available only for accepted orders.']);
            exit;
        }

        $itemStatement = $mysqli->prepare("
            SELECT id, menu_item_id
            FROM order_items
            WHERE id = ? AND order_id = ?
            LIMIT 1
        ");
        $itemStatement->bind_param('ii', $orderItemId, $orderId);
        $itemStatement->execute();
        $itemResult = $itemStatement->get_result();
        $orderItem = $itemResult->fetch_assoc();
        $itemStatement->close();

        if (!$orderItem || (int) ($orderItem['menu_item_id'] ?? 0) <= 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order item not found for rating.']);
            exit;
        }

        $customerSession = $_SESSION['food_book_user'] ?? [];
        $customerUid = trim((string) ($customerSession['uid'] ?? ''));
        $menuItemId = (int) $orderItem['menu_item_id'];

        if ($action === 'submit_rating') {
            $insertRating = $mysqli->prepare("
                INSERT INTO menu_item_ratings (menu_item_id, order_id, order_item_id, customer_uid, rating)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE rating = VALUES(rating), customer_uid = VALUES(customer_uid)
            ");
            $insertRating->bind_param('iiisi', $menuItemId, $orderId, $orderItemId, $customerUid, $rating);
            $insertRating->execute();
            $insertRating->close();

            $avgStatement = $mysqli->prepare("
                SELECT ROUND(AVG(rating), 1) AS avg_rating
                FROM menu_item_ratings
                WHERE menu_item_id = ?
            ");
            $avgStatement->bind_param('i', $menuItemId);
            $avgStatement->execute();
            $avgResult = $avgStatement->get_result();
            $avgRow = $avgResult->fetch_assoc();
            $avgStatement->close();

            $averageRating = (float) ($avgRow['avg_rating'] ?? 0);

            $updateMenu = $mysqli->prepare("
                UPDATE menu_items
                SET rating = ?
                WHERE id = ?
            ");
            $updateMenu->bind_param('di', $averageRating, $menuItemId);
            $updateMenu->execute();
            $updateMenu->close();

            echo json_encode([
                'success' => true,
                'message' => 'Rating submitted successfully.',
                'average_rating' => $averageRating
            ]);
            exit;
        }

        $customerName = trim((string) ($customerSession['full_name'] ?? 'Customer'));
        if ($customerName === '') {
            $customerName = 'Customer';
        }

        $insertReview = $mysqli->prepare("
            INSERT INTO menu_item_reviews (menu_item_id, order_id, order_item_id, customer_uid, customer_name, review_text)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                customer_uid = VALUES(customer_uid),
                customer_name = VALUES(customer_name),
                review_text = VALUES(review_text)
        ");
        $insertReview->bind_param('iiisss', $menuItemId, $orderId, $orderItemId, $customerUid, $customerName, $reviewText);
        $insertReview->execute();
        $insertReview->close();

        echo json_encode([
            'success' => true,
            'message' => 'Review submitted successfully.'
        ]);
    } catch (Throwable $exception) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to submit update.']);
    }
    exit;
}

$action = trim((string) ($_GET['action'] ?? ''));
if ($action === 'table_availability') {
    $restaurantName = trim((string) ($_GET['restaurant_name'] ?? ''));

    if ($restaurantName === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Restaurant name is required.']);
        exit;
    }

    try {
        $mysqli = getFoodBookDb();
        ensureFoodBookOrderTables($mysqli);

        $restaurantStatement = $mysqli->prepare("
            SELECT id, tables_count
            FROM restaurants
            WHERE name = ? AND is_active = 1
            LIMIT 1
        ");
        $restaurantStatement->bind_param('s', $restaurantName);
        $restaurantStatement->execute();
        $restaurantResult = $restaurantStatement->get_result();
        $restaurant = $restaurantResult->fetch_assoc();
        $restaurantStatement->close();

        if (!$restaurant) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Restaurant not found.']);
            exit;
        }

        $restaurantId = (int) $restaurant['id'];
        $tablesCount = max(1, (int) ($restaurant['tables_count'] ?? 1));
        $bookedTables = [];

        $bookedStatement = $mysqli->prepare("
            SELECT DISTINCT table_number
            FROM orders
            WHERE restaurant_id = ?
              AND status <> 'rejected'
              AND NOW() < DATE_ADD(created_at, INTERVAL (60 + waiting_minutes) MINUTE)
              AND table_number <> ''
        ");
        $bookedStatement->bind_param('i', $restaurantId);
        $bookedStatement->execute();
        $bookedResult = $bookedStatement->get_result();
        while ($row = $bookedResult->fetch_assoc()) {
            $bookedTables[] = (string) $row['table_number'];
        }
        $bookedStatement->close();

        echo json_encode([
            'success' => true,
            'restaurant_id' => $restaurantId,
            'tables_count' => $tablesCount,
            'booked_tables' => $bookedTables
        ]);
    } catch (Throwable $exception) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to load table availability.']);
    }
    exit;
}

$orderId = (int) ($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Order id is required.']);
    exit;
}

try {
    $mysqli = getFoodBookDb();
    $order = fetchOrderWithItems($mysqli, $orderId);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'order' => [
            'id' => (int) $order['id'],
            'status' => $order['status'],
            'status_label' => $order['status_label'],
            'waiting_minutes' => (int) $order['waiting_minutes'],
            'remaining_wait_seconds' => (int) $order['remaining_wait_seconds'],
            'accepted_at' => $order['accepted_at'],
            'rejected_reason' => $order['rejected_reason'],
            'updated_at' => $order['updated_at']
        ]
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load order status.']);
}
?>
