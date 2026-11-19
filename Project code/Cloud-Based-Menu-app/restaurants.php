<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/order-utils.php';

$mysqli = getFoodBookDb();
ensureFoodBookOrderTables($mysqli);

$restaurantsStatement = $mysqli->prepare("
    SELECT
        restaurants.id,
        restaurants.name,
        restaurants.city,
        restaurants.state,
        restaurants.image_path,
        restaurants.address,
        restaurants.description,
        restaurants.is_active,
        COALESCE(TRUNCATE(AVG(menu_item_stats.item_rating), 1), 0) AS restaurant_rating
    FROM restaurants
    LEFT JOIN (
        SELECT
            menu_items.id,
            menu_items.restaurant_id,
            COALESCE(TRUNCATE(AVG(menu_item_ratings.rating), 1), menu_items.rating, 0) AS item_rating
        FROM menu_items
        LEFT JOIN menu_item_ratings ON menu_item_ratings.menu_item_id = menu_items.id
        WHERE menu_items.is_active = 1
        GROUP BY menu_items.id
    ) AS menu_item_stats ON menu_item_stats.restaurant_id = restaurants.id
    WHERE restaurants.is_active = 1
    GROUP BY restaurants.id
    ORDER BY restaurants.name ASC
");
$restaurantsStatement->execute();
$restaurantsResult = $restaurantsStatement->get_result();
$restaurants = $restaurantsResult->fetch_all(MYSQLI_ASSOC);
$restaurantsStatement->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FOOD BOOK | Restaurants</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --fb-bg: #141414;
            --fb-surface: #1d1d1d;
            --fb-surface-soft: rgba(255, 255, 255, 0.08);
            --fb-surface-glass: rgba(255, 255, 255, 0.06);
            --fb-border: rgba(255, 255, 255, 0.12);
            --fb-border-strong: rgba(255, 255, 255, 0.18);
            --fb-text: #faf7f2;
            --fb-text-soft: #c8c3bb;
            --fb-red: #d33a36;
            --fb-red-soft: #ff6b63;
            --fb-grey: #8a8580;
            --fb-shadow-dark: #0c0c0c;
            --fb-shadow-light: #2a2a2a;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(211, 58, 54, 0.2), transparent 28%),
                radial-gradient(circle at top right, rgba(255, 255, 255, 0.08), transparent 22%),
                linear-gradient(180deg, #181818 0%, #101010 100%);
            color: var(--fb-text);
        }

        .restaurants-page {
            min-height: 100vh;
            padding: 32px;
        }

        .page-shell {
            max-width: 1320px;
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 18px 22px;
            border-radius: 28px;
            background: rgba(28, 28, 28, 0.8);
            border: 1px solid var(--fb-border);
            backdrop-filter: blur(18px);
            box-shadow: 14px 14px 24px var(--fb-shadow-dark), -10px -10px 22px rgba(43, 43, 43, 0.7);
        }

        .brand {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .brand-tag {
            color: var(--fb-red-soft);
            font-size: 0.86rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
        }

        .brand-name {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: 0.08em;
        }

        .brand-copy {
            color: var(--fb-text-soft);
            max-width: 520px;
            line-height: 1.6;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            flex-wrap: wrap;
        }

        .action-card {
            position: relative;
        }

        .action-button {
            min-height: 58px;
            border: 1px solid var(--fb-border);
            border-radius: 22px;
            background: linear-gradient(145deg, #222222, #191919);
            color: var(--fb-text);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 9px 9px 18px rgba(9, 9, 9, 0.95), -5px -5px 14px rgba(47, 47, 47, 0.65);
            cursor: pointer;
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .action-button:hover,
        .action-button:focus-visible {
            transform: translateY(-1px);
            border-color: var(--fb-border-strong);
            outline: none;
        }

        .action-icon {
            width: 36px;
            height: 36px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, rgba(211, 58, 54, 0.28), rgba(255, 255, 255, 0.08));
            font-size: 1rem;
            font-weight: 700;
        }

        .action-copy {
            text-align: left;
        }

        .action-label {
            font-size: 0.78rem;
            color: var(--fb-text-soft);
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }

        .action-value {
            font-size: 0.98rem;
            font-weight: 700;
        }

        .popover {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            width: 320px;
            padding: 18px;
            border-radius: 24px;
            background: rgba(30, 30, 30, 0.88);
            border: 1px solid var(--fb-border);
            backdrop-filter: blur(18px);
            box-shadow: 16px 16px 28px rgba(7, 7, 7, 0.92), -8px -8px 22px rgba(53, 53, 53, 0.55);
            display: none;
            z-index: 20;
        }

        .popover.is-open {
            display: block;
        }

        .popover h3 {
            font-size: 1rem;
            margin-bottom: 8px;
        }

        .popover p {
            color: var(--fb-text-soft);
            line-height: 1.5;
            margin-bottom: 14px;
            font-size: 0.92rem;
        }

        .input-group {
            margin-bottom: 14px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--fb-text-soft);
            font-size: 0.85rem;
        }

        .input-group select,
        .input-group input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 18px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: linear-gradient(145deg, #262626, #171717);
            color: var(--fb-text);
            box-shadow: inset 6px 6px 12px rgba(10, 10, 10, 0.72), inset -4px -4px 10px rgba(55, 55, 55, 0.35);
        }

        .input-group input::placeholder {
            color: #9f9890;
        }

        .input-group select:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .save-btn {
            width: 100%;
            padding: 13px 16px;
            border: none;
            border-radius: 18px;
            background: linear-gradient(145deg, #dd4c46, #af2c2c);
            color: #fff8f4;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 10px 10px 18px rgba(17, 17, 17, 0.85), -5px -5px 14px rgba(255, 96, 96, 0.12);
        }

        .hero-panel {
            display: grid;
            grid-template-columns: minmax(0, 1.2fr) minmax(320px, 0.8fr);
            gap: 26px;
            margin-bottom: 28px;
        }

        .hero-card,
        .city-status,
        .restaurant-card {
            border-radius: 34px;
            background: linear-gradient(145deg, #202020, #171717);
            border: 1px solid var(--fb-border);
            box-shadow: 16px 16px 28px rgba(8, 8, 8, 0.92), -10px -10px 24px rgba(52, 52, 52, 0.52);
        }

        .hero-card {
            padding: 34px;
            position: relative;
            overflow: hidden;
        }

        .hero-card::after {
            content: "";
            position: absolute;
            inset: auto -40px -50px auto;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(211, 58, 54, 0.4), transparent 66%);
            filter: blur(8px);
        }

        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.06);
            color: var(--fb-text-soft);
            margin-bottom: 18px;
        }

        .hero-title {
            font-size: clamp(2.1rem, 4vw, 4.2rem);
            line-height: 1.02;
            margin-bottom: 16px;
            max-width: 720px;
        }

        .hero-title span {
            color: var(--fb-red-soft);
        }

        .hero-copy {
            color: var(--fb-text-soft);
            max-width: 720px;
            line-height: 1.7;
            margin-bottom: 24px;
            font-size: 1rem;
        }

        .hero-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }

        .stat-card {
            min-width: 150px;
            padding: 16px 18px;
            border-radius: 24px;
            background: var(--fb-surface-glass);
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
        }

        .stat-card strong {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 6px;
        }

        .stat-card span {
            color: var(--fb-text-soft);
            font-size: 0.92rem;
        }

        .city-status {
            padding: 26px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            justify-content: space-between;
        }

        .status-label {
            color: var(--fb-text-soft);
            font-size: 0.84rem;
            text-transform: uppercase;
            letter-spacing: 0.14em;
        }

        .status-city {
            font-size: 2rem;
            color: var(--fb-red-soft);
        }

        .status-note,
        .availability-text {
            color: var(--fb-text-soft);
            line-height: 1.6;
        }

        .availability-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(211, 58, 54, 0.14);
            color: #ffd2cf;
            width: fit-content;
        }

        .restaurants-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .restaurants-title {
            font-size: 2.2rem;
            color: var(--fb-text);
            margin-bottom: 8px;
        }

        .restaurants-subtitle {
            color: var(--fb-text-soft);
            max-width: 820px;
            line-height: 1.6;
        }

        .results-badge {
            padding: 12px 16px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.06);
            color: var(--fb-text-soft);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .restaurant-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 420px));
            justify-content: center;
            gap: 24px;
        }

        .restaurant-card {
            padding: 22px;
            width: 100%;
        }

        .restaurant-image {
            height: 170px;
            border-radius: 26px;
            margin-bottom: 18px;
            overflow: hidden;
            background: #2a2a2a;
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
            position: relative;
        }

        .restaurant-image::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.08), rgba(0, 0, 0, 0.22));
        }

        .restaurant-name {
            font-size: 1.22rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .restaurant-meta {
            color: var(--fb-red-soft);
            font-weight: 700;
            margin-bottom: 10px;
        }
        .restaurant-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(86, 195, 110, 0.14);
            color: #d7ffe0;
            font-size: 0.82rem;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .restaurant-desc {
            color: var(--fb-text-soft);
            margin-bottom: 16px;
            line-height: 1.65;
            min-height: 78px;
        }

        .restaurant-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
            color: var(--fb-grey);
            font-size: 0.92rem;
        }

        .view-menu-btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 18px;
            background: linear-gradient(145deg, #dd4c46, #af2c2c);
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            box-shadow: 10px 10px 18px rgba(17, 17, 17, 0.85), -5px -5px 14px rgba(255, 96, 96, 0.1);
        }

        .empty-state {
            display: none;
            padding: 28px;
            margin-top: 10px;
            border-radius: 28px;
            border: 1px dashed rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.04);
            color: var(--fb-text-soft);
            line-height: 1.7;
        }

        .toast {
            position: fixed;
            right: 22px;
            bottom: 22px;
            padding: 14px 18px;
            border-radius: 18px;
            background: rgba(24, 24, 24, 0.94);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--fb-text);
            box-shadow: 14px 14px 24px rgba(8, 8, 8, 0.92);
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: opacity 0.22s ease, transform 0.22s ease;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 980px) {
            .restaurants-page {
                padding: 20px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .topbar-actions {
                width: 100%;
                justify-content: flex-start;
            }

            .hero-panel {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .brand-name {
                font-size: 1.55rem;
            }

            .hero-card,
            .city-status,
            .restaurant-card {
                border-radius: 26px;
            }

            .hero-card {
                padding: 24px;
            }

            .popover {
                width: min(320px, calc(100vw - 40px));
                right: auto;
                left: 0;
            }

            .action-button {
                width: 100%;
            }

            .action-card {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="restaurants-page">
        <div class="page-shell">
            <div class="topbar">
                <div class="brand">
                    <span class="brand-tag">Digital Dining Experience</span>
                    <h1 class="brand-name">FOOD BOOK</h1>
                    <p class="brand-copy">Discover restaurants in your city, personalize your profile, and continue into a smooth digital dining experience.</p>
                </div>

                <div class="topbar-actions">
                    <div class="action-card">
                        <button type="button" class="action-button" id="locationToggle">
                            <span class="action-icon">L</span>
                            <span class="action-copy">
                                <span class="action-label">Select Location</span>
                                <span class="action-value" id="locationSummary">Maharashtra, Nanded</span>
                            </span>
                        </button>

                        <div class="popover" id="locationPopover">
                            <h3>Choose your location</h3>
                            <p>Select a state first, then choose a city. Restaurants will be shown only for the selected city.</p>

                            <div class="input-group">
                                <label for="stateSelect">State</label>
                                <select id="stateSelect">
                                    <option value="">Select State</option>
                                    <option value="Maharashtra">Maharashtra</option>
                                    <option value="Telangana">Telangana</option>
                                    <option value="Karnataka">Karnataka</option>
                                </select>
                            </div>

                            <div class="input-group">
                                <label for="citySelect">City</label>
                                <select id="citySelect" disabled>
                                    <option value="">Select City</option>
                                </select>
                            </div>

                            <button type="button" class="save-btn" id="saveLocationBtn">Save Location</button>
                        </div>
                    </div>

                    <div class="action-card">
                        <button type="button" class="action-button" id="profileToggle">
                            <span class="action-icon" id="profileInitial">G</span>
                            <span class="action-copy">
                                <span class="action-label">Profile</span>
                                <span class="action-value" id="profileNameLabel">Guest User</span>
                            </span>
                        </button>

                        <div class="popover" id="profilePopover">
                            <h3>Your profile</h3>
                            <p>Insert or update your name and phone number here. This stays saved in your browser for now.</p>

                            <div class="input-group">
                                <label for="profileNameInput">Name</label>
                                <input type="text" id="profileNameInput" placeholder="Enter your name">
                            </div>

                            <div class="input-group">
                                <label for="profilePhoneInput">Phone Number</label>
                                <input type="tel" id="profilePhoneInput" placeholder="Enter your phone number">
                            </div>

                            <button type="button" class="save-btn" id="saveProfileBtn">Save Profile</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hero-panel">
                <div class="hero-card">
                    <div class="hero-stats">
                        <div class="stat-card">
                            <strong id="heroRestaurantCount"><?php echo count($restaurants); ?></strong>
                            <span>Restaurants live in this city</span>
                        </div>
                        <div class="stat-card">
                            <strong id="heroCityLabel">Nanded</strong>
                            <span>Currently selected city</span>
                        </div>
                        <div class="stat-card">
                            <strong>Fast</strong>
                            <span>Menu-to-billing digital experience</span>
                        </div>
                    </div>
                </div>

                <div class="city-status">
                    <div>
                        <div class="status-label">Serving Now</div>
                        <div class="status-city" id="statusLocation">Nanded, Maharashtra</div>
                    </div>

                    <div class="availability-pill">Serving restaurants in your selected city</div>
                    <p class="availability-text">Choose your location and explore restaurants available for your area through FOOD BOOK.</p>
                </div>
            </div>

            <div class="restaurants-header">
                <div>
                    <h2 class="restaurants-title">Restaurants Near You</h2>
                    <p class="restaurants-subtitle">Select your location to view nearby restaurants and continue to their digital menu.</p>
                </div>
                <div class="results-badge"><span id="resultsCount"><?php echo count($restaurants); ?></span> restaurants visible</div>
            </div>

            <div class="restaurant-grid" id="restaurantGrid">
                <?php foreach ($restaurants as $restaurant): ?>
                    <div
                        class="restaurant-card"
                        data-state="<?php echo htmlspecialchars($restaurant['state'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-city="<?php echo htmlspecialchars($restaurant['city'], ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <div class="restaurant-image" style="background-image: url('<?php echo htmlspecialchars($restaurant['image_path'], ENT_QUOTES, 'UTF-8'); ?>');"></div>
                        <div class="restaurant-name"><?php echo htmlspecialchars($restaurant['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="restaurant-meta">Restaurant | <?php echo htmlspecialchars(number_format((float) $restaurant['restaurant_rating'], 1), ENT_QUOTES, 'UTF-8'); ?> Rating</div>
                        <div class="restaurant-status">Open Now</div>
                        <div class="restaurant-desc"><?php echo htmlspecialchars($restaurant['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="restaurant-footer">
                            <span><?php echo htmlspecialchars($restaurant['address'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span>Open Daily</span>
                        </div>
                        <a class="view-menu-btn" href="menu.php?restaurant=<?php echo urlencode($restaurant['name']); ?>">View Menu</a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="empty-state" id="emptyState">
                No restaurants are live for this city yet. For now, select <strong>Nanded, Maharashtra</strong> to view available restaurants in FOOD BOOK.
            </div>
        </div>
    </div>

    <div class="toast" id="toast">Saved successfully.</div>

    <script>
        const cityOptions = {
            Maharashtra: ["Nanded", "Mumbai", "Pune"],
            Telangana: ["Hyderabad", "Warangal"],
            Karnataka: ["Bengaluru", "Mysuru"]
        };

        const customerKey = "foodBookCustomer";
        const locationKey = "foodBookLocation";

        const locationToggle = document.getElementById("locationToggle");
        const profileToggle = document.getElementById("profileToggle");
        const locationPopover = document.getElementById("locationPopover");
        const profilePopover = document.getElementById("profilePopover");
        const stateSelect = document.getElementById("stateSelect");
        const citySelect = document.getElementById("citySelect");
        const saveLocationBtn = document.getElementById("saveLocationBtn");
        const saveProfileBtn = document.getElementById("saveProfileBtn");
        const profileNameInput = document.getElementById("profileNameInput");
        const profilePhoneInput = document.getElementById("profilePhoneInput");
        const locationSummary = document.getElementById("locationSummary");
        const profileNameLabel = document.getElementById("profileNameLabel");
        const profileInitial = document.getElementById("profileInitial");
        const heroCityLabel = document.getElementById("heroCityLabel");
        const statusLocation = document.getElementById("statusLocation");
        const resultsCount = document.getElementById("resultsCount");
        const heroRestaurantCount = document.getElementById("heroRestaurantCount");
        const restaurantGrid = document.getElementById("restaurantGrid");
        const emptyState = document.getElementById("emptyState");
        const toast = document.getElementById("toast");

        function showToast(message) {
            toast.textContent = message;
            toast.classList.add("show");
            window.clearTimeout(showToast.timeoutId);
            showToast.timeoutId = window.setTimeout(() => {
                toast.classList.remove("show");
            }, 2200);
        }

        function openPopover(popover) {
            [locationPopover, profilePopover].forEach((item) => {
                if (item !== popover) {
                    item.classList.remove("is-open");
                }
            });
            popover.classList.toggle("is-open");
        }

        function fillCityOptions(selectedState, selectedCity) {
            const cities = cityOptions[selectedState] || [];
            citySelect.innerHTML = '<option value="">Select City</option>';

            cities.forEach((city) => {
                const option = document.createElement("option");
                option.value = city;
                option.textContent = city;
                citySelect.appendChild(option);
            });

            citySelect.disabled = cities.length === 0;
            if (selectedCity && cities.includes(selectedCity)) {
                citySelect.value = selectedCity;
            }
        }

        function updateProfileUI(profile) {
            const profileName = profile.name && profile.name.trim() ? profile.name.trim() : "Guest User";
            profileNameLabel.textContent = profileName;
            profileInitial.textContent = profileName.charAt(0).toUpperCase();
            profileNameInput.value = profile.name || "";
            profilePhoneInput.value = profile.phone || "";
        }

        function updateLocationUI(location) {
            const state = location.state || "Maharashtra";
            const city = location.city || "Nanded";
            const summary = state && city ? state + ", " + city : "Select your city";

            locationSummary.textContent = summary;
            heroCityLabel.textContent = city;
            statusLocation.textContent = city + ", " + state;
            stateSelect.value = state;
            fillCityOptions(state, city);

            const cards = Array.from(restaurantGrid.querySelectorAll(".restaurant-card"));
            let visibleCount = 0;

            cards.forEach((card) => {
                const matches = card.dataset.state === state && card.dataset.city === city;
                card.style.display = matches ? "block" : "none";
                if (matches) {
                    visibleCount += 1;
                }
            });

            resultsCount.textContent = String(visibleCount);
            heroRestaurantCount.textContent = String(visibleCount);
            emptyState.style.display = visibleCount === 0 ? "block" : "none";
        }

        function getStoredProfile() {
            const authProfile = JSON.parse(localStorage.getItem(customerKey) || "{}");
            return {
                name: authProfile.name || "",
                email: authProfile.email || "",
                photo: authProfile.photo || "",
                uid: authProfile.uid || "",
                phone: authProfile.phone || ""
            };
        }

        function getStoredLocation() {
            const savedLocation = JSON.parse(localStorage.getItem(locationKey) || "{}");
            return {
                state: savedLocation.state || "Maharashtra",
                city: savedLocation.city || "Nanded"
            };
        }

        locationToggle.addEventListener("click", () => openPopover(locationPopover));
        profileToggle.addEventListener("click", () => openPopover(profilePopover));

        stateSelect.addEventListener("change", () => {
            fillCityOptions(stateSelect.value, "");
        });

        saveLocationBtn.addEventListener("click", () => {
            const state = stateSelect.value;
            const city = citySelect.value;

            if (!state || !city) {
                showToast("Please select both state and city.");
                return;
            }

            const location = { state, city };
            localStorage.setItem(locationKey, JSON.stringify(location));
            updateLocationUI(location);
            locationPopover.classList.remove("is-open");

            if (state === "Maharashtra" && city === "Nanded") {
                showToast("Location saved. Restaurants for Nanded are now visible.");
            } else {
                showToast("Location saved. Only Nanded restaurants are available right now.");
            }
        });

        saveProfileBtn.addEventListener("click", () => {
            const profile = getStoredProfile();
            const updatedProfile = {
                ...profile,
                name: profileNameInput.value.trim(),
                phone: profilePhoneInput.value.trim()
            };

            localStorage.setItem(customerKey, JSON.stringify(updatedProfile));
            updateProfileUI(updatedProfile);
            profilePopover.classList.remove("is-open");
            showToast("Profile saved successfully.");
        });

        document.addEventListener("click", (event) => {
            if (!locationPopover.contains(event.target) && !locationToggle.contains(event.target)) {
                locationPopover.classList.remove("is-open");
            }

            if (!profilePopover.contains(event.target) && !profileToggle.contains(event.target)) {
                profilePopover.classList.remove("is-open");
            }
        });

        const initialProfile = getStoredProfile();
        const initialLocation = getStoredLocation();
        updateProfileUI(initialProfile);
        updateLocationUI(initialLocation);
    </script>
</body>
</html>
