<?php
require_once __DIR__ . '/db.php';

date_default_timezone_set('Asia/Calcutta');

function ensureFoodBookOrderTables(mysqli $mysqli): void
{
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            restaurant_id INT UNSIGNED NOT NULL,
            customer_uid VARCHAR(191) DEFAULT NULL,
            customer_name VARCHAR(191) NOT NULL,
            customer_email VARCHAR(191) DEFAULT NULL,
            restaurant_name VARCHAR(191) NOT NULL,
            table_number VARCHAR(20) NOT NULL,
            cooking_instructions TEXT DEFAULT NULL,
            items_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_quantity INT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            waiting_minutes INT UNSIGNED NOT NULL DEFAULT 0,
            accepted_at DATETIME DEFAULT NULL,
            rejected_reason VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_orders_restaurant (restaurant_id),
            INDEX idx_orders_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $mysqli->query("
        CREATE TABLE IF NOT EXISTS order_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            menu_item_id INT UNSIGNED DEFAULT NULL,
            menu_item_name VARCHAR(191) NOT NULL,
            item_image_path VARCHAR(255) DEFAULT NULL,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            quantity INT UNSIGNED NOT NULL DEFAULT 1,
            line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order_items_order (order_id),
            INDEX idx_order_items_menu_item (menu_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $mysqli->query("
        CREATE TABLE IF NOT EXISTS menu_item_ratings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            menu_item_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NOT NULL,
            order_item_id INT UNSIGNED NOT NULL UNIQUE,
            customer_uid VARCHAR(191) DEFAULT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_menu_item_ratings_item (menu_item_id),
            INDEX idx_menu_item_ratings_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $mysqli->query("
        CREATE TABLE IF NOT EXISTS menu_item_reviews (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            menu_item_id INT UNSIGNED NOT NULL,
            order_id INT UNSIGNED NOT NULL,
            order_item_id INT UNSIGNED NOT NULL UNIQUE,
            customer_uid VARCHAR(191) DEFAULT NULL,
            customer_name VARCHAR(191) DEFAULT NULL,
            review_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_menu_item_reviews_item (menu_item_id),
            INDEX idx_menu_item_reviews_order (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $mysqli->query("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS menu_item_id INT UNSIGNED DEFAULT NULL AFTER order_id");
    $mysqli->query("ALTER TABLE order_items ADD INDEX IF NOT EXISTS idx_order_items_menu_item (menu_item_id)");
}

function getOrderStatusLabel(string $status): string
{
    return match ($status) {
        'pending' => 'Waiting for restaurant response',
        'accepted' => 'Order accepted',
        'cooking' => 'Preparing',
        'ready' => 'Delivered',
        'rejected' => 'Order rejected',
        default => 'Order received',
    };
}

function fetchOrderWithItems(mysqli $mysqli, int $orderId): ?array
{
    ensureFoodBookOrderTables($mysqli);

    $orderStatement = $mysqli->prepare("
        SELECT
            orders.*,
            CASE
                WHEN accepted_at IS NOT NULL
                    AND waiting_minutes > 0
                    AND status IN ('accepted', 'cooking', 'ready')
                THEN GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(accepted_at, INTERVAL waiting_minutes MINUTE)))
                ELSE 0
            END AS remaining_wait_seconds
        FROM orders
        WHERE id = ?
        LIMIT 1
    ");
    $orderStatement->bind_param('i', $orderId);
    $orderStatement->execute();
    $orderResult = $orderStatement->get_result();
    $order = $orderResult->fetch_assoc();
    $orderStatement->close();

    if (!$order) {
        return null;
    }

    $itemsStatement = $mysqli->prepare("
        SELECT
            order_items.id,
            order_items.menu_item_id,
            order_items.menu_item_name,
            order_items.item_image_path,
            order_items.unit_price,
            order_items.quantity,
            order_items.line_total,
            menu_item_ratings.rating AS submitted_rating,
            menu_item_reviews.review_text AS submitted_review
        FROM order_items
        LEFT JOIN menu_item_ratings ON menu_item_ratings.order_item_id = order_items.id
        LEFT JOIN menu_item_reviews ON menu_item_reviews.order_item_id = order_items.id
        WHERE order_items.order_id = ?
        ORDER BY order_items.id ASC
    ");
    $itemsStatement->bind_param('i', $orderId);
    $itemsStatement->execute();
    $itemsResult = $itemsStatement->get_result();
    $order['items'] = $itemsResult->fetch_all(MYSQLI_ASSOC);
    $itemsStatement->close();

    $order['status_label'] = getOrderStatusLabel((string) $order['status']);
    $order['remaining_wait_seconds'] = (int) ($order['remaining_wait_seconds'] ?? 0);

    return $order;
}

function canCustomerRateOrder(array $order): bool
{
    $status = (string) ($order['status'] ?? '');
    return in_array($status, ['accepted', 'cooking', 'ready'], true);
}
?>
