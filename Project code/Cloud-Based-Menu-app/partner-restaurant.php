<?php
session_start();
require_once __DIR__ . '/db.php';

$partnerSession = $_SESSION['food_book_partner'] ?? null;
$mysqli = getFoodBookDb();

$statusMessage = '';
$statusType = 'success';
$selectedRestaurant = null;
$menuItems = [];

if (!$partnerSession || empty($partnerSession['uid'])) {
    $statusMessage = 'Please log in through the restaurant partner portal first.';
    $statusType = 'error';
} else {
    $uid = trim($partnerSession['uid']);

    $mysqli->query("ALTER TABLE restaurant_partner_users ADD COLUMN IF NOT EXISTS restaurant_id INT UNSIGNED DEFAULT NULL AFTER email");
    $mysqli->query("ALTER TABLE restaurants ADD COLUMN IF NOT EXISTS tables_count INT UNSIGNED NOT NULL DEFAULT 10 AFTER closing_time");
    $mysqli->query("
        CREATE TABLE IF NOT EXISTS menu_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            restaurant_id INT UNSIGNED NOT NULL,
            name VARCHAR(150) NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            description TEXT NOT NULL,
            rating DECIMAL(2,1) NOT NULL DEFAULT 0,
            orders_count INT UNSIGNED NOT NULL DEFAULT 0,
            review_text TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_menu_items_restaurant (restaurant_id)
        )
    ");

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

    if (!$partnerUser) {
        $statusMessage = 'Partner account was not found. Please log in again.';
        $statusType = 'error';
    } else {
        $restaurantId = (int) ($partnerUser['restaurant_id'] ?? 0);

        if ($restaurantId <= 0) {
            $defaultRestaurantName = trim((string) $partnerUser['full_name']) !== '' ? trim((string) $partnerUser['full_name']) . ' Restaurant' : 'My Restaurant';
            $insertRestaurant = $mysqli->prepare("
                INSERT INTO restaurants (
                    name,
                    city,
                    state,
                    image_path,
                    opening_time,
                    closing_time,
                    rating,
                    address,
                    description,
                    is_active
                ) VALUES (?, 'Nanded', 'Maharashtra', 'assets/restaurants/cafe_image.png', '10:00:00', '23:00:00', 4.0, 'Nanded', 'Update your restaurant description here.', 1)
            ");
            $insertRestaurant->bind_param('s', $defaultRestaurantName);
            $insertRestaurant->execute();
            $restaurantId = (int) $insertRestaurant->insert_id;
            $insertRestaurant->close();

            $updatePartnerRestaurant = $mysqli->prepare("
                UPDATE restaurant_partner_users
                SET restaurant_id = ?
                WHERE id = ?
            ");
            $updatePartnerRestaurant->bind_param('ii', $restaurantId, $partnerUser['id']);
            $updatePartnerRestaurant->execute();
            $updatePartnerRestaurant->close();

            $_SESSION['food_book_partner']['restaurant_id'] = $restaurantId;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formType = trim($_POST['form_type'] ?? '');

            if ($formType === 'restaurant_details') {
                $name = trim($_POST['name'] ?? '');
                $city = trim($_POST['city'] ?? '');
                $state = trim($_POST['state'] ?? '');
                $imagePath = trim($_POST['image_path'] ?? '');
                $openingTime = trim($_POST['opening_time'] ?? '');
                $closingTime = trim($_POST['closing_time'] ?? '');
                $tablesCount = trim($_POST['tables_count'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if (
                    $name === '' || $city === '' || $state === '' || $imagePath === '' ||
                    $openingTime === '' || $closingTime === '' || $tablesCount === '' || $address === '' || $description === ''
                ) {
                    $statusMessage = 'Please fill all required restaurant details before saving.';
                    $statusType = 'error';
                } else {
                    $statement = $mysqli->prepare("
                        UPDATE restaurants
                        SET
                            name = ?,
                            city = ?,
                            state = ?,
                            image_path = ?,
                            opening_time = ?,
                            closing_time = ?,
                            tables_count = ?,
                            address = ?,
                            description = ?,
                            is_active = ?
                        WHERE id = ?
                    ");
                    $statement->bind_param(
                        'sssssssisii',
                        $name,
                        $city,
                        $state,
                        $imagePath,
                        $openingTime,
                        $closingTime,
                        $tablesCount,
                        $address,
                        $description,
                        $isActive,
                        $restaurantId
                    );
                    $statement->execute();
                    $statement->close();

                    $statusMessage = 'Restaurant details updated successfully.';
                    $statusType = 'success';
                }
            }

            if ($formType === 'menu_item_update') {
                $itemId = (int) ($_POST['item_id'] ?? 0);
                $name = trim($_POST['item_name'] ?? '');
                $imagePath = trim($_POST['item_image_path'] ?? '');
                $price = trim($_POST['item_price'] ?? '');
                $description = trim($_POST['item_description'] ?? '');
                $sortOrder = trim($_POST['item_sort_order'] ?? '');
                $isActive = isset($_POST['item_is_active']) ? 1 : 0;

                if (
                    $itemId <= 0 || $name === '' || $imagePath === '' || $price === '' ||
                    $description === '' || $sortOrder === ''
                ) {
                    $statusMessage = 'Please fill all menu item fields before saving.';
                    $statusType = 'error';
                } else {
                    $statement = $mysqli->prepare("
                        UPDATE menu_items
                        SET
                            name = ?,
                            image_path = ?,
                            price = ?,
                            description = ?,
                            is_active = ?,
                            sort_order = ?
                        WHERE id = ? AND restaurant_id = ?
                    ");
                    $statement->bind_param(
                        'ssdsiiii',
                        $name,
                        $imagePath,
                        $price,
                        $description,
                        $isActive,
                        $sortOrder,
                        $itemId,
                        $restaurantId
                    );
                    $statement->execute();
                    $statement->close();

                    $statusMessage = 'Menu item updated successfully.';
                    $statusType = 'success';
                }
            }

            if ($formType === 'menu_item_create') {
                $name = trim($_POST['new_item_name'] ?? '');
                $imagePath = trim($_POST['new_item_image_path'] ?? '');
                $price = trim($_POST['new_item_price'] ?? '');
                $description = trim($_POST['new_item_description'] ?? '');
                $sortOrder = trim($_POST['new_item_sort_order'] ?? '');
                $isActive = isset($_POST['new_item_is_active']) ? 1 : 0;

                if (
                    $name === '' || $imagePath === '' || $price === '' || $description === '' || $sortOrder === ''
                ) {
                    $statusMessage = 'Please fill all new menu item fields before adding it.';
                    $statusType = 'error';
                } else {
                    $statement = $mysqli->prepare("
                        INSERT INTO menu_items (
                            restaurant_id,
                            name,
                            image_path,
                            price,
                            description,
                            is_active,
                            sort_order
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $statement->bind_param(
                        'issdsii',
                        $restaurantId,
                        $name,
                        $imagePath,
                        $price,
                        $description,
                        $isActive,
                        $sortOrder
                    );
                    $statement->execute();
                    $statement->close();

                    $statusMessage = 'New menu item added successfully.';
                    $statusType = 'success';
                }
            }
        }

        $restaurantStatement = $mysqli->prepare("
            SELECT id, name, city, state, image_path, opening_time, closing_time, tables_count, rating, address, description, is_active
            FROM restaurants
            WHERE id = ?
            LIMIT 1
        ");
        $restaurantStatement->bind_param('i', $restaurantId);
        $restaurantStatement->execute();
        $restaurantResult = $restaurantStatement->get_result();
        $selectedRestaurant = $restaurantResult->fetch_assoc();
        $restaurantStatement->close();

        $menuStatement = $mysqli->prepare("
            SELECT
                menu_items.id,
                menu_items.name,
                menu_items.image_path,
                menu_items.price,
                menu_items.description,
                menu_items.rating,
                menu_items.orders_count,
                menu_items.is_active,
                menu_items.sort_order,
                (
                    SELECT GROUP_CONCAT(CONCAT(COALESCE(menu_item_reviews.customer_name, 'Customer'), ': ', menu_item_reviews.review_text) ORDER BY menu_item_reviews.created_at DESC SEPARATOR '\n')
                    FROM menu_item_reviews
                    WHERE menu_item_reviews.menu_item_id = menu_items.id
                    LIMIT 5
                ) AS latest_reviews
            FROM menu_items
            WHERE restaurant_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $menuStatement->bind_param('i', $restaurantId);
        $menuStatement->execute();
        $menuResult = $menuStatement->get_result();
        $menuItems = $menuResult->fetch_all(MYSQLI_ASSOC);
        $menuStatement->close();
    }
}

$restaurantStatus = ($selectedRestaurant && (int) $selectedRestaurant['is_active'] === 1) ? 'Open Now' : 'Closed';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FOOD BOOK | Restaurant Owner Panel</title>
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
            max-width: 720px;
        }
        .menu-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .panel-link {
            color: #fff;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 10px 16px;
            background: rgba(255, 255, 255, 0.06);
            cursor: pointer;
        }
        .owner-status {
            margin-bottom: 20px;
            padding: 14px 16px;
            border-radius: 18px;
            line-height: 1.6;
        }
        .owner-status.success {
            background: rgba(79, 209, 146, 0.12);
            border: 1px solid rgba(79, 209, 146, 0.22);
            color: #d5ffe7;
        }
        .owner-status.error {
            background: rgba(255, 87, 87, 0.12);
            border: 1px solid rgba(255, 87, 87, 0.22);
            color: #ffd4d4;
        }
        .menu-hero {
            position: relative;
            min-height: 320px;
            border-radius: 30px;
            overflow: hidden;
            margin-bottom: 24px;
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            box-shadow: 14px 14px 26px #0b0b0b, -8px -8px 20px #242424;
        }
        .menu-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.18), rgba(0, 0, 0, 0.72));
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
        .menu-location,
        .menu-status,
        .owner-meta-chip {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 10px 14px;
            border-radius: 999px;
            margin-bottom: 12px;
            font-size: 0.92rem;
        }
        .menu-location,
        .owner-meta-chip {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            color: #fff;
        }
        .menu-status {
            background: rgba(86, 195, 110, 0.16);
            color: #d6ffe0;
            font-weight: 700;
        }
        .menu-status.is-closed {
            background: rgba(255, 133, 92, 0.18);
            color: #ffe1d2;
        }
        .menu-hero-title {
            font-size: clamp(2rem, 4vw, 3.5rem);
            margin-bottom: 12px;
        }
        .menu-hero-subtitle {
            color: #e2dfdb;
            max-width: 760px;
            line-height: 1.7;
            margin-bottom: 18px;
        }
        .hero-button-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .hero-button,
        .secondary-button,
        .save-button,
        .toggle-edit-btn {
            padding: 12px 16px;
            border-radius: 14px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }
        .hero-button,
        .save-button {
            background: #d32f2f;
            color: #fff;
        }
        .secondary-button,
        .toggle-edit-btn {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .edit-panel {
            display: none;
            margin-bottom: 28px;
            background: #1b1b1b;
            border-radius: 28px;
            padding: 24px;
            box-shadow: 10px 10px 20px #0b0b0b, -6px -6px 16px #242424;
        }
        .edit-panel.is-open {
            display: block;
        }
        .panel-title {
            font-size: 1.4rem;
            margin-bottom: 18px;
            color: #fff7f2;
        }
        .owner-form {
            display: grid;
            gap: 18px;
        }
        .owner-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #e5dfd8;
            font-size: 0.95rem;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: linear-gradient(145deg, #2a2a2a, #191919);
            color: #fff;
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .toggle-row {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ddd6cf;
        }
        .menu-grid {
            display: grid;
            grid-template-columns: minmax(320px, 860px);
            justify-content: start;
            gap: 20px;
        }
        .food-card {
            background: #1b1b1b;
            border-radius: 28px;
            padding: 22px;
            box-shadow: 10px 10px 20px #0b0b0b, -6px -6px 16px #242424;
            display: grid;
            grid-template-columns: 132px minmax(260px, 1fr) 170px;
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
            position: relative;
            overflow: hidden;
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
        .food-desc {
            color: #c9c9c9;
            font-size: 0.9rem;
            line-height: 1.55;
            margin-bottom: 10px;
        }
        .food-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .food-reviews {
            margin-top: 10px;
            color: #d8d2cb;
            font-size: 0.85rem;
            line-height: 1.6;
            white-space: pre-line;
        }
        .food-chip {
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
            color: #ece7e1;
            font-size: 0.84rem;
        }
        .food-side {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        .food-price {
            color: #ef5350;
            font-weight: 700;
            font-size: 1.08rem;
        }
        .item-state {
            font-size: 0.86rem;
            color: #d3cdc7;
        }
        .item-edit-panel {
            display: none;
            grid-column: 1 / -1;
            margin-top: 8px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
        .item-edit-panel.is-open {
            display: block;
        }
        .empty-state {
            background: #1b1b1b;
            border-radius: 24px;
            padding: 20px;
            color: #d4cec8;
        }
        @media (max-width: 860px) {
            .food-card {
                grid-template-columns: 1fr;
            }
            .food-image {
                width: 100%;
            }
            .owner-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 640px) {
            .menu-page {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="menu-page">
        <div class="menu-header">
            <div>
                <h1 class="menu-title">Restaurant Owner Panel</h1>
                <p class="menu-subtitle">Manage the same restaurant and menu details that customers see on FOOD BOOK.</p>
            </div>
            <div class="menu-actions">
                <a class="panel-link" href="restaurant-orders.php">Restaurant Orders</a>
                <a class="panel-link" href="partner-login.php">Back to Partner Login</a>
            </div>
        </div>

        <?php if ($statusMessage !== ''): ?>
            <div class="owner-status <?php echo htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($selectedRestaurant && $partnerSession): ?>
            <div class="menu-hero" style="background-image: url('<?php echo htmlspecialchars($selectedRestaurant['image_path'], ENT_QUOTES, 'UTF-8'); ?>');">
                <div class="menu-hero-content">
                    <div class="owner-meta-chip">Logged in as <?php echo htmlspecialchars($partnerSession['full_name'] ?: $partnerSession['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="menu-location"><?php echo htmlspecialchars($selectedRestaurant['address'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="menu-status<?php echo $restaurantStatus === 'Open Now' ? '' : ' is-closed'; ?>"><?php echo htmlspecialchars($restaurantStatus, ENT_QUOTES, 'UTF-8'); ?></div>
                    <h2 class="menu-hero-title"><?php echo htmlspecialchars($selectedRestaurant['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="menu-hero-subtitle"><?php echo htmlspecialchars($selectedRestaurant['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="hero-button-row">
                        <button type="button" class="hero-button" data-toggle-target="restaurantEditPanel">Edit Restaurant Details</button>
                    </div>
                </div>
            </div>

            <section class="edit-panel" id="restaurantEditPanel">
                <h3 class="panel-title">Edit Restaurant Details</h3>
                <form method="post" class="owner-form">
                    <input type="hidden" name="form_type" value="restaurant_details">
                    <div class="owner-grid">
                        <div class="form-group">
                            <label for="name">Restaurant Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($selectedRestaurant['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="image_path">Image Path</label>
                            <input type="text" id="image_path" name="image_path" value="<?php echo htmlspecialchars($selectedRestaurant['image_path'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($selectedRestaurant['city'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="state">State</label>
                            <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($selectedRestaurant['state'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="opening_time">Opening Time</label>
                            <input type="time" id="opening_time" name="opening_time" value="<?php echo htmlspecialchars($selectedRestaurant['opening_time'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="closing_time">Closing Time</label>
                            <input type="time" id="closing_time" name="closing_time" value="<?php echo htmlspecialchars($selectedRestaurant['closing_time'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="tables_count">Number Of Tables</label>
                            <input type="number" id="tables_count" name="tables_count" min="1" value="<?php echo htmlspecialchars((string) ($selectedRestaurant['tables_count'] ?? 10), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($selectedRestaurant['address'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Restaurant Details / Description</label>
                        <textarea id="description" name="description" required><?php echo htmlspecialchars($selectedRestaurant['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <div class="toggle-row">
                        <input type="checkbox" id="is_active" name="is_active" <?php echo (int) $selectedRestaurant['is_active'] === 1 ? 'checked' : ''; ?>>
                        <label for="is_active">Restaurant is active / open for listing</label>
                    </div>

                    <button type="submit" class="save-button">Save Restaurant Details</button>
                </form>
            </section>

            <div class="menu-header">
                <div>
                    <h2 class="menu-title" style="font-size: 2rem; margin-bottom: 6px;">Menu Items</h2>
                    <p class="menu-subtitle">Every edit here updates the same menu that customers see after opening your restaurant.</p>
                </div>
                <div class="menu-actions">
                    <button type="button" class="panel-link" data-toggle-target="newItemPanel">Add New Menu Item</button>
                </div>
            </div>

            <section class="edit-panel" id="newItemPanel">
                <h3 class="panel-title">Add New Menu Item</h3>
                <form method="post" class="owner-form">
                    <input type="hidden" name="form_type" value="menu_item_create">
                    <div class="owner-grid">
                        <div class="form-group">
                            <label for="new_item_name">Item Name</label>
                            <input type="text" id="new_item_name" name="new_item_name" required>
                        </div>

                        <div class="form-group">
                            <label for="new_item_image_path">Image Path</label>
                            <input type="text" id="new_item_image_path" name="new_item_image_path" placeholder="assets/menu/item.png" required>
                        </div>

                        <div class="form-group">
                            <label for="new_item_price">Price</label>
                            <input type="number" id="new_item_price" name="new_item_price" min="0" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <label for="new_item_sort_order">Sort Order</label>
                            <input type="number" id="new_item_sort_order" name="new_item_sort_order" min="0" value="<?php echo count($menuItems) + 1; ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_item_description">Description</label>
                        <textarea id="new_item_description" name="new_item_description" required></textarea>
                    </div>

                    <div class="toggle-row">
                        <input type="checkbox" id="new_item_is_active" name="new_item_is_active" checked>
                        <label for="new_item_is_active">Show this menu item to customers</label>
                    </div>

                    <button type="submit" class="save-button">Add Menu Item</button>
                </form>
            </section>
            <div class="menu-grid">
                <?php if (!empty($menuItems)): ?>
                    <?php foreach ($menuItems as $item): ?>
                        <div class="food-card">
                            <div class="food-image" style="background-image: url('<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
                            <div class="food-info">
                                <div class="food-name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="food-desc"><?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="food-meta">
                                    <span class="food-chip"><?php echo htmlspecialchars((string) $item['rating'], ENT_QUOTES, 'UTF-8'); ?> star</span>
                                    <span class="food-chip"><?php echo htmlspecialchars((string) $item['orders_count'], ENT_QUOTES, 'UTF-8'); ?> orders</span>
                                    <span class="food-chip">Sort <?php echo htmlspecialchars((string) $item['sort_order'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="food-reviews">
                                    Customer reviews:
                                    <?php echo htmlspecialchars($item['latest_reviews'] ?: "\nNo customer reviews yet.", ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                            <div class="food-side">
                                <div class="food-price"><?php echo htmlspecialchars('Rs. ' . number_format((float) $item['price'], 0), ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="item-state"><?php echo (int) $item['is_active'] === 1 ? 'Visible to customers' : 'Hidden from customers'; ?></div>
                                <button type="button" class="toggle-edit-btn" data-toggle-target="itemEditPanel<?php echo (int) $item['id']; ?>">Edit Item</button>
                            </div>

                            <div class="item-edit-panel" id="itemEditPanel<?php echo (int) $item['id']; ?>">
                                <form method="post" class="owner-form">
                                    <input type="hidden" name="form_type" value="menu_item_update">
                                    <input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>">

                                    <div class="owner-grid">
                                        <div class="form-group">
                                            <label for="item_name_<?php echo (int) $item['id']; ?>">Item Name</label>
                                            <input type="text" id="item_name_<?php echo (int) $item['id']; ?>" name="item_name" value="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="item_image_path_<?php echo (int) $item['id']; ?>">Image Path</label>
                                            <input type="text" id="item_image_path_<?php echo (int) $item['id']; ?>" name="item_image_path" value="<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="item_price_<?php echo (int) $item['id']; ?>">Price</label>
                                            <input type="number" id="item_price_<?php echo (int) $item['id']; ?>" name="item_price" min="0" step="0.01" value="<?php echo htmlspecialchars((string) $item['price'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="item_sort_order_<?php echo (int) $item['id']; ?>">Sort Order</label>
                                            <input type="number" id="item_sort_order_<?php echo (int) $item['id']; ?>" name="item_sort_order" min="0" value="<?php echo htmlspecialchars((string) $item['sort_order'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="item_description_<?php echo (int) $item['id']; ?>">Description</label>
                                        <textarea id="item_description_<?php echo (int) $item['id']; ?>" name="item_description" required><?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>

                                    <div class="toggle-row">
                                        <input type="checkbox" id="item_is_active_<?php echo (int) $item['id']; ?>" name="item_is_active" <?php echo (int) $item['is_active'] === 1 ? 'checked' : ''; ?>>
                                        <label for="item_is_active_<?php echo (int) $item['id']; ?>">Show this menu item to customers</label>
                                    </div>

                                    <button type="submit" class="save-button">Save Item Changes</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No menu items added yet. Use the button above to create your first menu item.</div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">No restaurant profile is linked to this account yet.</div>
        <?php endif; ?>
    </div>

    <script>
        document.querySelectorAll('[data-toggle-target]').forEach((button) => {
            button.addEventListener('click', () => {
                const target = document.getElementById(button.dataset.toggleTarget);

                if (target) {
                    target.classList.toggle('is-open');
                }
            });
        });
    </script>
</body>
</html>
