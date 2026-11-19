<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/order-utils.php';

$restaurantName = isset($_GET['restaurant']) ? trim($_GET['restaurant']) : 'Selected Restaurant';
$mysqli = getFoodBookDb();
ensureFoodBookOrderTables($mysqli);

$restaurantStatement = $mysqli->prepare("
    SELECT id, name, image_path, address, description, is_active
    FROM restaurants
    WHERE name = ?
    LIMIT 1
");
$restaurantStatement->bind_param('s', $restaurantName);
$restaurantStatement->execute();
$restaurantResult = $restaurantStatement->get_result();
$restaurantRow = $restaurantResult->fetch_assoc();
$restaurantStatement->close();

$selectedRestaurant = $restaurantRow ?: [
    'id' => 0,
    'name' => $restaurantName,
    'image_path' => 'cafe_image.png',
    'address' => 'Nanded',
    'description' => 'Browse dishes, add items to cart, and continue to billing.',
    'is_active' => 1
];

$menuItems = [];
if ((int) $selectedRestaurant['id'] > 0) {
    $menuStatement = $mysqli->prepare("
        SELECT
            menu_items.id,
            menu_items.name,
            menu_items.image_path,
            menu_items.price,
            menu_items.description,
            menu_items.orders_count,
            menu_items.review_text,
            COALESCE(ROUND(AVG(menu_item_ratings.rating), 1), menu_items.rating) AS average_rating,
            (
                SELECT GROUP_CONCAT(CONCAT(COALESCE(menu_item_reviews.customer_name, 'Customer'), ': ', menu_item_reviews.review_text) ORDER BY menu_item_reviews.created_at DESC SEPARATOR '\n')
                FROM menu_item_reviews
                WHERE menu_item_reviews.menu_item_id = menu_items.id
                LIMIT 5
            ) AS latest_reviews
        FROM menu_items
        LEFT JOIN menu_item_ratings ON menu_item_ratings.menu_item_id = menu_items.id
        WHERE menu_items.restaurant_id = ? AND menu_items.is_active = 1
        GROUP BY menu_items.id
        ORDER BY menu_items.sort_order ASC, menu_items.id ASC
    ");
    $restaurantId = (int) $selectedRestaurant['id'];
    $menuStatement->bind_param('i', $restaurantId);
    $menuStatement->execute();
    $menuResult = $menuStatement->get_result();
    $menuItems = $menuResult->fetch_all(MYSQLI_ASSOC);
    $menuStatement->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FOOD BOOK | Menu</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .menu-page {
            min-height: 100vh;
            background: #111;
            color: #fff;
            padding: 40px;
        }
        .menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            gap: 20px;
            flex-wrap: wrap;
        }
        .menu-title {
            font-size: 2.5rem;
            color: #e53935;
            margin-bottom: 10px;
        }
        .menu-subtitle {
            color: #c7c7c7;
        }
        .menu-hero {
            position: relative;
            min-height: 320px;
            border-radius: 30px;
            overflow: hidden;
            margin-bottom: 30px;
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            box-shadow: 14px 14px 26px #0b0b0b, -8px -8px 20px #242424;
        }
        .menu-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.18), rgba(0, 0, 0, 0.68));
        }
        .menu-hero-content {
            position: relative;
            z-index: 1;
            min-height: 320px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 28px;
        }
        .menu-location {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            color: #fff;
            margin-bottom: 14px;
            font-size: 0.95rem;
        }
        .menu-status {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(86, 195, 110, 0.16);
            color: #d6ffe0;
            margin-bottom: 14px;
            font-size: 0.92rem;
            font-weight: 700;
        }
        .menu-hero-title {
            font-size: clamp(2rem, 4vw, 3.5rem);
            margin-bottom: 12px;
        }
        .menu-hero-subtitle {
            color: #e2dfdb;
            max-width: 720px;
            line-height: 1.7;
        }
        .back-link {
            color: #fff;
            text-decoration: none;
            border: 1px solid #444;
            border-radius: 14px;
            padding: 10px 16px;
        }
        .menu-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .cart-link {
            color: #fff;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.06);
        }
        .order-details-link {
            display: none;
        }
        .order-details-link.is-visible {
            display: inline-block;
        }
        .menu-grid {
            display: grid;
            grid-template-columns: minmax(320px, 760px);
            justify-content: start;
            gap: 25px;
        }
        .food-card {
            background: #1b1b1b;
            border-radius: 28px;
            padding: 22px;
            box-shadow: 10px 10px 20px #0b0b0b, -6px -6px 16px #242424;
            display: grid;
            grid-template-columns: 132px minmax(260px, 1fr) 136px;
            gap: 18px;
            align-items: center;
        }
        .food-image {
            height: 160px;
            width: 132px;
            border-radius: 20px;
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
            overflow: hidden;
            position: relative;
        }
        .food-image::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.04), rgba(0, 0, 0, 0.16));
        }
        .food-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .food-info {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .food-desc {
            color: #c9c9c9;
            font-size: 0.9rem;
            line-height: 1.55;
            margin-bottom: 10px;
            max-width: 100%;
        }
        .food-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }
        .food-chip {
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
            color: #ece7e1;
            font-size: 0.84rem;
        }
        .food-price {
            color: #ef5350;
            font-weight: bold;
            font-size: 1.08rem;
            margin-bottom: 12px;
        }
        .food-side {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            min-width: 136px;
        }
        .food-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: stretch;
            width: 100%;
        }
        .order-btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 14px;
            background: #d32f2f;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
        }
        .quantity-control {
            display: none;
            width: 100%;
            grid-template-columns: 42px 1fr 42px;
            gap: 8px;
            align-items: center;
        }
        .quantity-control.is-visible {
            display: grid;
        }
        .qty-btn,
        .qty-value {
            height: 42px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .qty-btn {
            cursor: pointer;
        }
        .review-btn {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
            font-weight: bold;
            cursor: pointer;
        }
        .review-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(0, 0, 0, 0.6);
            z-index: 50;
        }
        .review-modal.is-open {
            display: flex;
        }
        .review-modal-card {
            width: min(100%, 420px);
            background: linear-gradient(145deg, #2a2a2a, #181818);
            border-radius: 24px;
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            box-shadow: 14px 14px 26px #0b0b0b, -8px -8px 20px #242424;
        }
        .review-modal-title {
            font-size: 1.3rem;
            margin-bottom: 12px;
            color: #fff7f2;
        }
        .review-author {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(229, 57, 53, 0.16);
            color: #ffd8d3;
            font-size: 0.88rem;
            font-weight: 700;
            margin-bottom: 14px;
        }
        .review-modal-text {
            color: #f0ece7;
            line-height: 1.9;
            margin-bottom: 18px;
            white-space: pre-line;
        }
        .review-close-btn {
            padding: 12px 16px;
            border: none;
            border-radius: 14px;
            background: #d32f2f;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
        }
        @media (max-width: 640px) {
            .food-card {
                grid-template-columns: 1fr;
            }
            .food-image {
                width: 100%;
            }
            .food-side {
                min-width: 0;
            }
        }
    </style>
</head>
<body>
    <div class="menu-page">
        <div class="menu-header">
            <div>
                <h1 class="menu-title"><?php echo htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8'); ?> Menu</h1>
                <p class="menu-subtitle">Browse dishes, add items to cart, and continue to billing.</p>
            </div>
            <div class="menu-actions">
                <a class="cart-link" href="cart.php?restaurant=<?php echo urlencode($restaurantName); ?>">Open Cart</a>
                <a class="cart-link order-details-link" id="orderDetailsLink" href="#">View Order Details</a>
                <a class="back-link" href="restaurants.php">Back to Restaurants</a>
            </div>
        </div>

        <div class="menu-hero" style="background-image: url('<?php echo htmlspecialchars($selectedRestaurant['image_path'], ENT_QUOTES, 'UTF-8'); ?>');">
            <div class="menu-hero-content">
                <div class="menu-location"><?php echo htmlspecialchars($selectedRestaurant['address'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="menu-status"><?php echo (int) $selectedRestaurant['is_active'] === 1 ? 'Open Now' : 'Closed'; ?></div>
                <h2 class="menu-hero-title"><?php echo htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="menu-hero-subtitle"><?php echo htmlspecialchars($selectedRestaurant['description'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>

        <div class="menu-grid">
            <?php foreach ($menuItems as $item): ?>
                <div class="food-card">
                    <div class="food-image" style="background-image: url('<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
                    <div class="food-info">
                        <div class="food-name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="food-desc"><?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="food-meta">
                            <span class="food-chip"><?php echo htmlspecialchars(number_format((float) $item['average_rating'], 1), ENT_QUOTES, 'UTF-8'); ?> star</span>
                            <span class="food-chip"><?php echo htmlspecialchars((string) $item['orders_count'], ENT_QUOTES, 'UTF-8'); ?> orders</span>
                        </div>
                    </div>
                    <div class="food-side">
                        <div class="food-price"><?php echo htmlspecialchars('Rs. ' . number_format((float) $item['price'], 0), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="food-actions">
                            <button
                                class="order-btn add-to-cart-btn"
                                type="button"
                                data-item-id="<?php echo (int) $item['id']; ?>"
                                data-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-price="<?php echo htmlspecialchars((string) $item['price'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-image="<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-restaurant="<?php echo htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8'); ?>"
                            >Add to Cart</button>
                            <div
                                class="quantity-control"
                                data-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-restaurant="<?php echo htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                <button type="button" class="qty-btn qty-minus">-</button>
                                <div class="qty-value">1</div>
                                <button type="button" class="qty-btn qty-plus">+</button>
                            </div>
                            <button class="review-btn" type="button" data-review="<?php echo htmlspecialchars($item['latest_reviews'] ?: "No customer reviews yet.", ENT_QUOTES, 'UTF-8'); ?>" data-item-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">Reviews</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

        <div class="review-modal" id="reviewModal">
        <div class="review-modal-card">
            <h3 class="review-modal-title" id="reviewTitle">Item Review</h3>
            <div class="review-author" id="reviewAuthor">Reviewed by Atif</div>
            <p class="review-modal-text" id="reviewText"></p>
            <button type="button" class="review-close-btn" id="reviewCloseBtn">Close</button>
        </div>
    </div>

    <script>
        const reviewModal = document.getElementById('reviewModal');
        const reviewTitle = document.getElementById('reviewTitle');
        const reviewAuthor = document.getElementById('reviewAuthor');
        const reviewText = document.getElementById('reviewText');
        const reviewCloseBtn = document.getElementById('reviewCloseBtn');
        const reviewButtons = document.querySelectorAll('.review-btn');
        const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
        const quantityControls = document.querySelectorAll('.quantity-control');
        const cartStorageKey = 'foodBookCart';
        const lastOrderStorageKey = 'foodBookLastOrderByRestaurant';
        const menuRestaurantName = <?php echo json_encode($restaurantName, JSON_UNESCAPED_SLASHES); ?>;
        const orderDetailsLink = document.getElementById('orderDetailsLink');

        function getCartItems() {
            return JSON.parse(localStorage.getItem(cartStorageKey) || '[]');
        }

        function saveCartItems(items) {
            localStorage.setItem(cartStorageKey, JSON.stringify(items));
        }

        function findCartItem(items, name, restaurant) {
            return items.find((item) => item.name === name && item.restaurant === restaurant);
        }

        function getLatestOrderForRestaurant(restaurantName) {
            const savedOrders = JSON.parse(localStorage.getItem(lastOrderStorageKey) || '{}');
            const entry = savedOrders[restaurantName];
            if (!entry || !entry.order_id) {
                return null;
            }

            return {
                orderId: Number(entry.order_id),
                redirectUrl: entry.redirect_url || ('order-details.php?order_id=' + entry.order_id)
            };
        }

        function updateOrderDetailsLink() {
            if (!orderDetailsLink) {
                return;
            }

            const latestOrder = getLatestOrderForRestaurant(menuRestaurantName);
            if (!latestOrder || latestOrder.orderId <= 0) {
                orderDetailsLink.classList.remove('is-visible');
                orderDetailsLink.setAttribute('href', '#');
                return;
            }

            orderDetailsLink.classList.add('is-visible');
            orderDetailsLink.setAttribute('href', latestOrder.redirectUrl);
        }

        function updateMenuControls() {
            const cartItems = getCartItems();

            addToCartButtons.forEach((button) => {
                const control = button.parentElement.querySelector('.quantity-control');
                const item = findCartItem(cartItems, button.dataset.name, button.dataset.restaurant);

                if (item && item.quantity > 0) {
                    button.style.display = 'none';
                    control.classList.add('is-visible');
                    control.querySelector('.qty-value').textContent = item.quantity;
                } else {
                    button.style.display = 'block';
                    control.classList.remove('is-visible');
                    control.querySelector('.qty-value').textContent = '1';
                }
            });
        }

        reviewButtons.forEach((button) => {
            button.addEventListener('click', () => {
                reviewTitle.textContent = button.dataset.itemName + ' Review';
                reviewAuthor.textContent = 'Customer Reviews';
                reviewText.textContent = button.dataset.review;
                reviewModal.classList.add('is-open');
            });
        });

        reviewCloseBtn.addEventListener('click', () => {
            reviewModal.classList.remove('is-open');
        });

        reviewModal.addEventListener('click', (event) => {
            if (event.target === reviewModal) {
                reviewModal.classList.remove('is-open');
            }
        });

        addToCartButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const cartItems = getCartItems();
                const itemName = button.dataset.name;
                const itemPrice = Number(button.dataset.price || 0);
                const restaurant = button.dataset.restaurant;

                const existingItem = findCartItem(cartItems, itemName, restaurant);

                if (existingItem) {
                    existingItem.quantity += 1;
                } else {
                    cartItems.push({
                        item_id: Number(button.dataset.itemId || 0),
                        name: itemName,
                        price: itemPrice,
                        image: button.dataset.image,
                        restaurant: restaurant,
                        quantity: 1
                    });
                }

                saveCartItems(cartItems);
                updateMenuControls();
            });
        });

        quantityControls.forEach((control) => {
            const minusBtn = control.querySelector('.qty-minus');
            const plusBtn = control.querySelector('.qty-plus');

            plusBtn.addEventListener('click', () => {
                const cartItems = getCartItems();
                const item = findCartItem(cartItems, control.dataset.name, control.dataset.restaurant);

                if (item) {
                    item.quantity += 1;
                    saveCartItems(cartItems);
                    updateMenuControls();
                }
            });

            minusBtn.addEventListener('click', () => {
                const cartItems = getCartItems();
                const itemIndex = cartItems.findIndex((item) => item.name === control.dataset.name && item.restaurant === control.dataset.restaurant);

                if (itemIndex >= 0) {
                    cartItems[itemIndex].quantity -= 1;

                    if (cartItems[itemIndex].quantity <= 0) {
                        cartItems.splice(itemIndex, 1);
                    }

                    saveCartItems(cartItems);
                    updateMenuControls();
                }
            });
        });

        updateMenuControls();
        updateOrderDetailsLink();
        window.addEventListener('pageshow', updateMenuControls);
        window.addEventListener('focus', updateMenuControls);
        window.addEventListener('storage', (event) => {
            if (event.key === cartStorageKey) {
                updateMenuControls();
            }
            if (event.key === lastOrderStorageKey) {
                updateOrderDetailsLink();
            }
        });
    </script>
</body>
</html>
