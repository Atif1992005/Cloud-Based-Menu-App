<?php
session_start();
require_once __DIR__ . '/order-utils.php';

$partnerSession = $_SESSION['food_book_partner'] ?? null;
$mysqli = getFoodBookDb();
ensureFoodBookOrderTables($mysqli);

$statusMessage = '';
$statusType = 'success';
$restaurant = null;
$orders = [];

if (!$partnerSession || empty($partnerSession['uid'])) {
    $statusMessage = 'Please log in through the restaurant partner portal first.';
    $statusType = 'error';
} else {
    $uid = trim((string) $partnerSession['uid']);

    $partnerStatement = $mysqli->prepare("
        SELECT restaurant_id
        FROM restaurant_partner_users
        WHERE firebase_uid = ?
        LIMIT 1
    ");
    $partnerStatement->bind_param('s', $uid);
    $partnerStatement->execute();
    $partnerResult = $partnerStatement->get_result();
    $partner = $partnerResult->fetch_assoc();
    $partnerStatement->close();

    $restaurantId = (int) ($partner['restaurant_id'] ?? 0);

    if ($restaurantId <= 0) {
        $statusMessage = 'No restaurant is linked to this partner account yet.';
        $statusType = 'error';
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = trim((string) ($_POST['action'] ?? ''));
            $orderId = (int) ($_POST['order_id'] ?? 0);

            if ($orderId > 0) {
                if ($action === 'accept') {
                    $waitingMinutes = max(1, (int) ($_POST['waiting_minutes'] ?? 15));

                    $statement = $mysqli->prepare("
                        UPDATE orders
                        SET status = 'accepted',
                            waiting_minutes = ?,
                            accepted_at = NOW(),
                            rejected_reason = NULL
                        WHERE id = ? AND restaurant_id = ?
                    ");
                    $statement->bind_param('iii', $waitingMinutes, $orderId, $restaurantId);
                    $statement->execute();
                    $statement->close();

                    $statusMessage = 'Order accepted successfully.';
                }

                if ($action === 'reject') {
                    $rejectedReason = trim((string) ($_POST['rejected_reason'] ?? 'Restaurant is unable to prepare this order right now.'));
                    if ($rejectedReason === '') {
                        $rejectedReason = 'Restaurant is unable to prepare this order right now.';
                    }

                    $statement = $mysqli->prepare("
                        UPDATE orders
                        SET status = 'rejected',
                            rejected_reason = ?,
                            waiting_minutes = 0
                        WHERE id = ? AND restaurant_id = ?
                    ");
                    $statement->bind_param('sii', $rejectedReason, $orderId, $restaurantId);
                    $statement->execute();
                    $statement->close();

                    $statusMessage = 'Order rejected.';
                }

                if ($action === 'update_status') {
                    $allowedStatuses = ['accepted', 'cooking', 'ready'];
                    $nextStatus = trim((string) ($_POST['next_status'] ?? 'accepted'));
                    $waitingMinutes = max(0, (int) ($_POST['waiting_minutes'] ?? 0));

                    if (!in_array($nextStatus, $allowedStatuses, true)) {
                        $nextStatus = 'accepted';
                    }

                    $statement = $mysqli->prepare("
                        UPDATE orders
                        SET status = ?,
                            waiting_minutes = ?,
                            accepted_at = COALESCE(accepted_at, NOW())
                        WHERE id = ? AND restaurant_id = ? AND status <> 'rejected'
                    ");
                    $statement->bind_param('siii', $nextStatus, $waitingMinutes, $orderId, $restaurantId);
                    $statement->execute();
                    $statement->close();

                    $statusMessage = 'Order status updated successfully.';
                }
            }
        }

        $restaurantStatement = $mysqli->prepare("
            SELECT id, name, image_path, address
            FROM restaurants
            WHERE id = ?
            LIMIT 1
        ");
        $restaurantStatement->bind_param('i', $restaurantId);
        $restaurantStatement->execute();
        $restaurantResult = $restaurantStatement->get_result();
        $restaurant = $restaurantResult->fetch_assoc();
        $restaurantStatement->close();

        $ordersStatement = $mysqli->prepare("
            SELECT *
            FROM orders
            WHERE restaurant_id = ?
            ORDER BY created_at DESC, id DESC
        ");
        $ordersStatement->bind_param('i', $restaurantId);
        $ordersStatement->execute();
        $ordersResult = $ordersStatement->get_result();
        $orders = $ordersResult->fetch_all(MYSQLI_ASSOC);
        $ordersStatement->close();

        foreach ($orders as &$order) {
            $details = fetchOrderWithItems($mysqli, (int) $order['id']);
            $order = $details ?: $order;
        }
        unset($order);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FOOD BOOK | Restaurant Orders</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .orders-page { min-height: 100vh; background: #111; color: #fff; padding: 32px; }
        .orders-shell { max-width: 1120px; margin: 0 auto; }
        .orders-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
        .orders-title { font-size: 2.4rem; color: #e53935; margin-bottom: 8px; }
        .orders-subtitle { color: #c7c7c7; }
        .panel-link { color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 10px 16px; background: rgba(255,255,255,0.06); }
        .status-banner { margin-bottom: 18px; padding: 14px 16px; border-radius: 18px; }
        .status-banner.success { background: rgba(79,209,146,0.12); color: #d5ffe7; }
        .status-banner.error { background: rgba(255,87,87,0.12); color: #ffd4d4; }
        .restaurant-strip { position: relative; min-height: 220px; border-radius: 30px; overflow: hidden; margin-bottom: 26px; background-position: center; background-size: cover; box-shadow: 14px 14px 26px #0b0b0b, -8px -8px 20px #242424; }
        .restaurant-strip::before { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,0.18), rgba(0,0,0,0.74)); }
        .restaurant-strip-content { position: relative; z-index: 1; min-height: 220px; padding: 24px; display: flex; flex-direction: column; justify-content: flex-end; }
        .chip { display: inline-flex; width: fit-content; padding: 10px 14px; border-radius: 999px; margin-bottom: 10px; background: rgba(255,255,255,0.12); color: #fff; }
        .orders-list { display: grid; gap: 20px; }
        .order-card { background: #1b1b1b; border-radius: 28px; padding: 22px; box-shadow: 10px 10px 20px #0b0b0b, -6px -6px 16px #242424; }
        .order-top { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .order-id { font-size: 1.2rem; font-weight: 700; }
        .status-chip { display: inline-flex; padding: 8px 12px; border-radius: 999px; background: rgba(255,255,255,0.08); }
        .meta-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin-bottom: 18px; }
        .meta-box { background: rgba(255,255,255,0.04); border-radius: 18px; padding: 14px; }
        .meta-label { color: #c8c3bc; font-size: 0.84rem; margin-bottom: 6px; }
        .meta-value { font-weight: 700; }
        .order-items { display: grid; gap: 12px; margin-bottom: 18px; }
        .order-item { display: grid; grid-template-columns: 62px 1fr auto; gap: 12px; align-items: center; padding: 12px; border-radius: 18px; background: rgba(255,255,255,0.04); }
        .order-item-image { width: 62px; height: 62px; border-radius: 16px; background-position: center; background-size: cover; background-color: #2b2b2b; }
        .item-name { font-weight: 700; margin-bottom: 4px; }
        .item-meta, .small-note { color: #c8c3bc; font-size: 0.9rem; line-height: 1.6; }
        .action-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 16px; }
        .action-card { background: rgba(255,255,255,0.04); border-radius: 20px; padding: 16px; }
        .action-card form { display: grid; gap: 12px; }
        .action-card input, .action-card select, .action-card textarea { width: 100%; padding: 12px 14px; border-radius: 14px; border: 1px solid rgba(255,255,255,0.08); background: #222; color: #fff; }
        .primary-btn, .secondary-btn, .danger-btn { border: none; border-radius: 14px; padding: 12px 14px; font-weight: 700; cursor: pointer; }
        .primary-btn { background: #d32f2f; color: #fff; }
        .secondary-btn { background: rgba(255,255,255,0.08); color: #fff; }
        .danger-btn { background: #5a2020; color: #fff; }
        .empty-state { background: #1b1b1b; border-radius: 24px; padding: 20px; color: #d4cec8; }
        @media (max-width: 860px) {
            .meta-grid, .action-grid { grid-template-columns: 1fr; }
            .order-item { grid-template-columns: 1fr; }
            .order-item-image { width: 100%; height: 180px; }
        }
    </style>
</head>
<body>
    <div class="orders-page">
        <div class="orders-shell">
            <div class="orders-header">
                <div>
                    <h1 class="orders-title">Restaurant Orders</h1>
                    <p class="orders-subtitle">Review new orders, accept or reject them, and move them through the preparing and delivered flow.</p>
                </div>
                <div style="display:flex; gap:12px; flex-wrap:wrap;">
                    <a class="panel-link" href="partner-restaurant.php">Back to Owner Panel</a>
                </div>
            </div>

            <?php if ($statusMessage !== ''): ?>
                <div class="status-banner <?php echo htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($restaurant): ?>
                <div class="restaurant-strip" style="background-image:url('<?php echo htmlspecialchars($restaurant['image_path'], ENT_QUOTES, 'UTF-8'); ?>');">
                    <div class="restaurant-strip-content">
                        <div class="chip"><?php echo htmlspecialchars($restaurant['address'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <h2 style="font-size:2.2rem; margin-bottom:8px;"><?php echo htmlspecialchars($restaurant['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <div class="small-note">Incoming customer orders from FOOD BOOK will appear here.</div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="orders-list">
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-top">
                                <div>
                                    <div class="order-id">Order #<?php echo (int) $order['id']; ?></div>
                                    <div class="small-note"><?php echo htmlspecialchars($order['customer_name'], ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($order['customer_email']) ? ' | ' . htmlspecialchars($order['customer_email'], ENT_QUOTES, 'UTF-8') : ''; ?></div>
                                </div>
                                <div class="status-chip"><?php echo htmlspecialchars($order['status_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>

                            <div class="meta-grid">
                                <div class="meta-box">
                                    <div class="meta-label">Table No.</div>
                                    <div class="meta-value"><?php echo htmlspecialchars($order['table_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="meta-box">
                                    <div class="meta-label">Total Bill</div>
                                    <div class="meta-value">Rs. <?php echo htmlspecialchars(number_format((float) $order['items_total'], 0), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="meta-box">
                                    <div class="meta-label">Total Items</div>
                                    <div class="meta-value"><?php echo (int) $order['total_quantity']; ?></div>
                                </div>
                                <div class="meta-box">
                                    <div class="meta-label">Waiting Time</div>
                                    <div class="meta-value"><?php echo (int) $order['remaining_wait_seconds'] > 0 ? ceil(((int) $order['remaining_wait_seconds']) / 60) . ' min left' : ((int) $order['waiting_minutes'] > 0 ? '0 min left' : 'Not set'); ?></div>
                                </div>
                            </div>

                            <div class="small-note" style="margin-bottom:14px;">
                                <strong>Cooking Instructions:</strong>
                                <?php echo htmlspecialchars($order['cooking_instructions'] !== '' ? $order['cooking_instructions'] : 'No special instructions.', ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                            <?php if ((string) $order['status'] === 'rejected' && !empty($order['rejected_reason'])): ?>
                                <div class="small-note" style="margin-bottom:14px; color:#ffd4d4;">
                                    <strong>Reject Reason:</strong> <?php echo htmlspecialchars($order['rejected_reason'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>

                            <div class="order-items">
                                <?php foreach (($order['items'] ?? []) as $item): ?>
                                    <div class="order-item">
                                        <div class="order-item-image" style="background-image:url('<?php echo htmlspecialchars($item['item_image_path'] ?: '', ENT_QUOTES, 'UTF-8'); ?>');"></div>
                                        <div>
                                            <div class="item-name"><?php echo htmlspecialchars($item['menu_item_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="item-meta"><?php echo (int) $item['quantity']; ?> x Rs. <?php echo htmlspecialchars(number_format((float) $item['unit_price'], 0), ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                        <div class="item-meta">Rs. <?php echo htmlspecialchars(number_format((float) $item['line_total'], 0), ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="action-grid">
                                <?php if ((string) $order['status'] === 'pending'): ?>
                                    <div class="action-card">
                                        <form method="post">
                                            <input type="hidden" name="action" value="accept">
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                            <label class="small-note" for="waiting_minutes_<?php echo (int) $order['id']; ?>">Waiting Time (minutes)</label>
                                            <input type="number" id="waiting_minutes_<?php echo (int) $order['id']; ?>" name="waiting_minutes" min="1" value="20" required>
                                            <button type="submit" class="primary-btn">Accept Order</button>
                                        </form>
                                    </div>
                                    <div class="action-card">
                                        <form method="post">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                            <label class="small-note" for="rejected_reason_<?php echo (int) $order['id']; ?>">Reject Reason</label>
                                            <textarea id="rejected_reason_<?php echo (int) $order['id']; ?>" name="rejected_reason" rows="3">Restaurant is unable to prepare this order right now.</textarea>
                                            <button type="submit" class="danger-btn">Reject Order</button>
                                        </form>
                                    </div>
                                <?php elseif ((string) $order['status'] !== 'rejected'): ?>
                                    <div class="action-card" style="grid-column: 1 / -1;">
                                        <form method="post">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                            <div style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px;">
                                                <div>
                                                    <label class="small-note" for="next_status_<?php echo (int) $order['id']; ?>">Current Status</label>
                                                    <select id="next_status_<?php echo (int) $order['id']; ?>" name="next_status">
                                                        <option value="accepted" <?php echo $order['status'] === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                                        <option value="cooking" <?php echo $order['status'] === 'cooking' ? 'selected' : ''; ?>>Preparing</option>
                                                        <option value="ready" <?php echo $order['status'] === 'ready' ? 'selected' : ''; ?>>Delivered</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="small-note" for="waiting_update_<?php echo (int) $order['id']; ?>">Waiting Time (minutes)</label>
                                                    <input type="number" id="waiting_update_<?php echo (int) $order['id']; ?>" name="waiting_minutes" min="0" value="<?php echo (int) $order['waiting_minutes']; ?>">
                                                </div>
                                                <div style="display:flex; align-items:flex-end;">
                                                    <button type="submit" class="secondary-btn" style="width:100%;">Update Order</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="small-note">This order has been rejected and is no longer active.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No restaurant orders yet. When customers place orders, they will appear here.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
