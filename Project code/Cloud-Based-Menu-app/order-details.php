<?php
require_once __DIR__ . '/order-utils.php';

$orderId = (int) ($_GET['order_id'] ?? 0);
$mysqli = getFoodBookDb();
$order = $orderId > 0 ? fetchOrderWithItems($mysqli, $orderId) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FOOD BOOK | Order Details</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .order-page { min-height: 100vh; background: #111; color: #fff; padding: 32px; }
        .order-shell { max-width: 1040px; margin: 0 auto; }
        .order-header { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 24px; }
        .order-title { font-size: 2.4rem; color: #e53935; margin-bottom: 8px; }
        .order-subtitle { color: #c7c7c7; }
        .panel-link { color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 10px 16px; background: rgba(255,255,255,0.06); }
        .hero-card, .details-card, .items-card { background: linear-gradient(145deg, #202020, #171717); border-radius: 30px; padding: 24px; border: 1px solid rgba(255,255,255,0.08); box-shadow: 14px 14px 26px #0b0b0b, -8px -8px 20px #242424; }
        .hero-card { margin-bottom: 22px; }
        .status-chip, .wait-chip { display: inline-flex; padding: 10px 14px; border-radius: 999px; background: rgba(255,255,255,0.08); margin-right: 10px; margin-bottom: 12px; }
        .status-text { font-size: 1.3rem; font-weight: 700; margin-bottom: 8px; }
        .info-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 14px; margin-top: 14px; }
        .info-box { background: rgba(255,255,255,0.04); border-radius: 18px; padding: 14px; }
        .info-label { color: #c8c3bc; font-size: 0.84rem; margin-bottom: 6px; }
        .info-value { font-weight: 700; }
        .details-card { margin-bottom: 22px; }
        .section-title { font-size: 1.35rem; margin-bottom: 14px; }
        .detail-line { color: #ded8d1; line-height: 1.8; margin-bottom: 10px; }
        .items-list { display: grid; gap: 14px; }
        .item-row { display: grid; grid-template-columns: 72px 1fr auto; gap: 14px; align-items: center; padding: 14px; border-radius: 18px; background: rgba(255,255,255,0.04); }
        .item-image { width: 72px; height: 72px; border-radius: 16px; background-position: center; background-size: cover; background-color: #2b2b2b; }
        .item-name { font-weight: 700; margin-bottom: 4px; }
        .item-meta, .empty-state { color: #c8c3bc; line-height: 1.6; }
        .rating-box { display: flex; gap: 8px; align-items: center; margin-top: 8px; flex-wrap: wrap; }
        .rating-btn {
            border: 1px solid rgba(255,255,255,0.18);
            background: rgba(255,255,255,0.05);
            color: #fff;
            border-radius: 10px;
            padding: 6px 10px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .rating-note { color: #d6d1cb; font-size: 0.84rem; }
        .review-entry { margin-top: 10px; display: grid; gap: 8px; }
        .review-input {
            width: 100%;
            min-height: 84px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.05);
            color: #fff;
            padding: 10px 12px;
            resize: vertical;
        }
        .review-submit {
            width: fit-content;
            border: none;
            border-radius: 10px;
            background: #d32f2f;
            color: #fff;
            padding: 8px 12px;
            font-size: 0.84rem;
            font-weight: 700;
            cursor: pointer;
        }
        .error-card { background: rgba(255,87,87,0.12); border: 1px solid rgba(255,87,87,0.22); border-radius: 24px; padding: 20px; color: #ffd4d4; }
        @media (max-width: 860px) {
            .info-grid { grid-template-columns: 1fr; }
            .item-row { grid-template-columns: 1fr; }
            .item-image { width: 100%; height: 200px; }
        }
    </style>
</head>
<body>
    <div class="order-page">
        <div class="order-shell">
            <div class="order-header">
                <div>
                    <h1 class="order-title">Order Details</h1>
                    <p class="order-subtitle">Track restaurant response, cooking progress, and waiting time in one place.</p>
                </div>
                <?php if ($order): ?>
                    <a class="panel-link" href="menu.php?restaurant=<?php echo urlencode($order['restaurant_name']); ?>">Back to Menu</a>
                <?php else: ?>
                    <a class="panel-link" href="restaurants.php">Back to Restaurants</a>
                <?php endif; ?>
            </div>

            <?php if (!$order): ?>
                <div class="error-card">Order not found. Please go back and place a valid order.</div>
            <?php else: ?>
                <div class="hero-card" id="orderHero" data-order-id="<?php echo (int) $order['id']; ?>">
                    <div class="status-chip" id="statusChip"><?php echo htmlspecialchars($order['status_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="wait-chip" id="waitChip">
                        <?php if ((int) $order['remaining_wait_seconds'] > 0): ?>
                            <?php echo ceil(((int) $order['remaining_wait_seconds']) / 60); ?> min left
                        <?php elseif ((string) $order['status'] === 'rejected'): ?>
                            Order closed
                        <?php else: ?>
                            Waiting time will appear here
                        <?php endif; ?>
                    </div>
                    <div class="status-text" id="statusText"><?php echo htmlspecialchars($order['status_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="detail-line">Restaurant: <strong><?php echo htmlspecialchars($order['restaurant_name'], ENT_QUOTES, 'UTF-8'); ?></strong></div>

                    <div class="info-grid">
                        <div class="info-box">
                            <div class="info-label">Order Number</div>
                            <div class="info-value">#<?php echo (int) $order['id']; ?></div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">Table No.</div>
                            <div class="info-value"><?php echo htmlspecialchars($order['table_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">Total Bill</div>
                            <div class="info-value">Rs. <?php echo htmlspecialchars(number_format((float) $order['items_total'], 0), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="info-box">
                            <div class="info-label">Items</div>
                            <div class="info-value"><?php echo (int) $order['total_quantity']; ?></div>
                        </div>
                    </div>
                </div>

                <div class="details-card">
                    <div class="section-title">Order Progress</div>
                    <div class="detail-line" id="progressText"><?php echo htmlspecialchars($order['status_label'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="detail-line" id="rejectText" <?php echo (string) $order['status'] === 'rejected' && !empty($order['rejected_reason']) ? '' : 'style="display:none;"'; ?>>
                        <?php if ((string) $order['status'] === 'rejected' && !empty($order['rejected_reason'])): ?>
                            Reject reason: <?php echo htmlspecialchars($order['rejected_reason'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="detail-line">Cooking Instructions: <?php echo htmlspecialchars($order['cooking_instructions'] !== '' ? $order['cooking_instructions'] : 'No special instructions.', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>

                <div class="items-card">
                    <div class="section-title">Ordered Items</div>
                    <div class="items-list">
                        <?php foreach (($order['items'] ?? []) as $item): ?>
                            <div class="item-row">
                                <div class="item-image" style="background-image:url('<?php echo htmlspecialchars($item['item_image_path'] ?: '', ENT_QUOTES, 'UTF-8'); ?>');"></div>
                                <div>
                                    <div class="item-name"><?php echo htmlspecialchars($item['menu_item_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="item-meta"><?php echo (int) $item['quantity']; ?> x Rs. <?php echo htmlspecialchars(number_format((float) $item['unit_price'], 0), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="rating-box" data-order-item-id="<?php echo (int) $item['id']; ?>" data-submitted-rating="<?php echo (int) ($item['submitted_rating'] ?? 0); ?>">
                                        <span class="rating-note rating-message">
                                            <?php if ((int) ($item['submitted_rating'] ?? 0) > 0): ?>
                                                Your rating: <?php echo (int) $item['submitted_rating']; ?> / 5
                                            <?php elseif (canCustomerRateOrder($order)): ?>
                                                Rate this item:
                                            <?php else: ?>
                                                Rating unlocks after order acceptance.
                                            <?php endif; ?>
                                        </span>
                                        <div class="rating-actions" style="<?php echo ((int) ($item['submitted_rating'] ?? 0) > 0 || !canCustomerRateOrder($order)) ? 'display:none;' : 'display:inline-flex; gap:8px; flex-wrap:wrap;'; ?>">
                                            <?php for ($star = 1; $star <= 5; $star++): ?>
                                                <button type="button" class="rating-btn" data-rating="<?php echo $star; ?>"><?php echo $star; ?> star</button>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="review-entry" data-order-item-id="<?php echo (int) $item['id']; ?>" data-submitted-review="<?php echo htmlspecialchars((string) ($item['submitted_review'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php if (!empty($item['submitted_review'])): ?>
                                            <span class="rating-note review-message">Your review: <?php echo htmlspecialchars($item['submitted_review'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php elseif (canCustomerRateOrder($order)): ?>
                                            <span class="rating-note review-message">Write a review:</span>
                                            <textarea class="review-input" placeholder="Share your experience for this item"></textarea>
                                            <button type="button" class="review-submit">Submit Review</button>
                                        <?php else: ?>
                                            <span class="rating-note review-message">Review unlocks after order acceptance.</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="item-meta">Rs. <?php echo htmlspecialchars(number_format((float) $item['line_total'], 0), ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($order): ?>
        <script>
            const orderId = <?php echo (int) $order['id']; ?>;
            const statusChip = document.getElementById('statusChip');
            const waitChip = document.getElementById('waitChip');
            const statusText = document.getElementById('statusText');
            const progressText = document.getElementById('progressText');
            const rejectText = document.getElementById('rejectText');
            const ratingBoxes = document.querySelectorAll('.rating-box');
            const reviewEntries = document.querySelectorAll('.review-entry');
            let remainingWaitSeconds = <?php echo (int) $order['remaining_wait_seconds']; ?>;
            let currentStatus = <?php echo json_encode($order['status']); ?>;

            function renderWaitChip() {
                if (currentStatus === 'rejected') {
                    waitChip.textContent = 'Order closed';
                    return;
                }

                if (remainingWaitSeconds > 0) {
                    const minutes = Math.floor(remainingWaitSeconds / 60);
                    const seconds = remainingWaitSeconds % 60;
                    waitChip.textContent = minutes + 'm ' + String(seconds).padStart(2, '0') + 's left';
                    return;
                }

                waitChip.textContent = currentStatus === 'pending' ? 'Waiting for restaurant response' : '0m 00s left';
            }

            function applyOrderStatus(order) {
                currentStatus = order.status;
                remainingWaitSeconds = Number(order.remaining_wait_seconds || 0);
                statusChip.textContent = order.status_label;
                statusText.textContent = order.status_label;
                progressText.textContent = order.status_label;

                if (order.status === 'rejected' && order.rejected_reason) {
                    rejectText.style.display = 'block';
                    rejectText.textContent = 'Reject reason: ' + order.rejected_reason;
                } else {
                    rejectText.style.display = 'none';
                    rejectText.textContent = '';
                }

                ratingBoxes.forEach((box) => {
                    const submittedRating = Number(box.dataset.submittedRating || 0);
                    const message = box.querySelector('.rating-message');
                    const actions = box.querySelector('.rating-actions');
                    if (!message || !actions) {
                        return;
                    }

                    if (submittedRating > 0) {
                        message.textContent = 'Your rating: ' + submittedRating + ' / 5';
                        actions.style.display = 'none';
                        return;
                    }

                    if (statusAllowsRating()) {
                        message.textContent = 'Rate this item:';
                        actions.style.display = 'inline-flex';
                    } else {
                        message.textContent = 'Rating unlocks after order acceptance.';
                        actions.style.display = 'none';
                    }
                });

                reviewEntries.forEach((entry) => {
                    const submittedReview = (entry.dataset.submittedReview || '').trim();
                    const message = entry.querySelector('.review-message');
                    if (!message) {
                        return;
                    }

                    if (submittedReview !== '') {
                        message.textContent = 'Your review: ' + submittedReview;
                        return;
                    }

                    let input = entry.querySelector('.review-input');
                    let submit = entry.querySelector('.review-submit');

                    if (statusAllowsRating()) {
                        message.textContent = 'Write a review:';
                        if (!input) {
                            input = document.createElement('textarea');
                            input.className = 'review-input';
                            input.placeholder = 'Share your experience for this item';
                            entry.appendChild(input);
                        }
                        if (!submit) {
                            submit = document.createElement('button');
                            submit.type = 'button';
                            submit.className = 'review-submit';
                            submit.textContent = 'Submit Review';
                            entry.appendChild(submit);
                            bindReviewSubmit(entry, submit, input);
                        }
                    } else {
                        message.textContent = 'Review unlocks after order acceptance.';
                        if (input) {
                            input.remove();
                        }
                        if (submit) {
                            submit.remove();
                        }
                    }
                });

                renderWaitChip();
            }

            async function refreshOrderStatus() {
                try {
                    const response = await fetch('order-status.php?order_id=' + encodeURIComponent(orderId), {
                        cache: 'no-store'
                    });
                    const data = await response.json();

                    if (response.ok && data.success && data.order) {
                        applyOrderStatus(data.order);
                    }
                } catch (error) {
                    // keep the last visible state if refresh fails
                }
            }

            function statusAllowsRating() {
                return ['accepted', 'cooking', 'ready'].includes(currentStatus);
            }

            ratingBoxes.forEach((box) => {
                const submittedRating = Number(box.dataset.submittedRating || 0);
                if (submittedRating > 0) {
                    return;
                }

                box.querySelectorAll('.rating-btn').forEach((button) => {
                    button.addEventListener('click', async () => {
                        if (!statusAllowsRating()) {
                            return;
                        }

                        const ratingValue = Number(button.dataset.rating || 0);
                        const orderItemId = Number(box.dataset.orderItemId || 0);
                        if (ratingValue < 1 || ratingValue > 5 || orderItemId <= 0) {
                            return;
                        }

                        button.disabled = true;
                        try {
                            const response = await fetch('order-status.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    action: 'submit_rating',
                                    order_id: orderId,
                                    order_item_id: orderItemId,
                                    rating: ratingValue
                                })
                            });
                            const data = await response.json();
                            if (!response.ok || !data.success) {
                                throw new Error(data.message || 'Unable to submit rating.');
                            }

                            box.dataset.submittedRating = String(ratingValue);
                            const message = box.querySelector('.rating-message');
                            const actions = box.querySelector('.rating-actions');
                            if (message) {
                                message.textContent = 'Your rating: ' + ratingValue + ' / 5';
                            }
                            if (actions) {
                                actions.style.display = 'none';
                            }
                        } catch (error) {
                            button.disabled = false;
                        }
                    });
                });
            });

            function bindReviewSubmit(entry, button, input) {
                button.addEventListener('click', async () => {
                    if (!statusAllowsRating()) {
                        return;
                    }
                    const orderItemId = Number(entry.dataset.orderItemId || 0);
                    const reviewText = (input.value || '').trim();

                    if (orderItemId <= 0 || reviewText === '') {
                        return;
                    }

                    button.disabled = true;
                    try {
                        const response = await fetch('order-status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'submit_review',
                                order_id: orderId,
                                order_item_id: orderItemId,
                                review_text: reviewText
                            })
                        });
                        const data = await response.json();
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Unable to submit review.');
                        }

                        entry.dataset.submittedReview = reviewText;
                        const message = entry.querySelector('.review-message');
                        if (message) {
                            message.textContent = 'Your review: ' + reviewText;
                        }
                        if (input) {
                            input.remove();
                        }
                        button.remove();
                    } catch (error) {
                        button.disabled = false;
                    }
                });
            }

            reviewEntries.forEach((entry) => {
                const button = entry.querySelector('.review-submit');
                const input = entry.querySelector('.review-input');
                if (button && input) {
                    bindReviewSubmit(entry, button, input);
                }
            });

            renderWaitChip();
            window.setInterval(() => {
                if (remainingWaitSeconds > 0) {
                    remainingWaitSeconds -= 1;
                    renderWaitChip();
                }
            }, 1000);
            window.setInterval(refreshOrderStatus, 5000);
        </script>
    <?php endif; ?>
</body>
</html>
