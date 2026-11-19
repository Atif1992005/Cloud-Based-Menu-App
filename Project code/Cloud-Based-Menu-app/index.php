<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FOOD BOOK | Customer Authentication</title>
    <link rel="stylesheet" href="css/style.css" />
</head>
<body class="login-body">
    <div class="login-wrapper auth-layout">
        <section class="brand-panel auth-brand-panel">
            <span class="eyebrow">Smart Restaurant Menu and order</span>
            <h1>FOOD BOOK</h1>
            <p>
                FOOD BOOK brings restaurant discovery, digital menu browsing, and smooth ordering together in one customer-friendly experience.
            </p>

            <div class="feature-list">
                <div class="feature-item">
                    <strong>Smart Restaurant Access</strong>
                    <span>Find restaurants quickly and enter their digital menu in a few steps.</span>
                </div>
                <div class="feature-item">
                    <strong>Easy Ordering Flow</strong>
                    <span>Browse dishes, choose your favorites, and continue smoothly.</span>
                </div>
                <div class="feature-item">
                    <strong>Customer Convenience</strong>
                    <span>Enjoy a simple and modern dining experience with quick access and clean navigation.</span>
                </div>
            </div>
        </section>

        <section class="login-card auth-card">
            <div class="auth-card-header">
                <h2>Customer Access</h2>
                <p>Choose a login method to continue to the restaurants page.</p>
            </div>

            <div class="auth-tabs" role="tablist" aria-label="Authentication options">
                <button type="button" class="auth-tab is-active" data-auth-tab="login">Login</button>
                <button type="button" class="auth-tab" data-auth-tab="register">Register</button>
            </div>

            <div id="authMessage" class="auth-message" aria-live="polite"></div>

            <form id="loginForm" class="auth-form is-active" autocomplete="on">
                <div class="form-group">
                    <label for="loginEmail">Email</label>
                    <input type="email" id="loginEmail" name="email" placeholder="Enter your email" required />
                    <div class="field-error" id="loginEmailError"></div>
                </div>

                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <input type="password" id="loginPassword" name="password" placeholder="Enter your password" required />
                    <div class="field-error" id="loginPasswordError"></div>
                </div>

                <button type="submit" class="primary-btn" id="loginSubmitBtn">Login with Email</button>
            </form>

            <form id="registerForm" class="auth-form" autocomplete="on">
                <div class="form-group">
                    <label for="registerName">Full Name</label>
                    <input type="text" id="registerName" name="full_name" placeholder="Enter your full name" required />
                    <div class="field-error" id="registerNameError"></div>
                </div>

                <div class="form-group">
                    <label for="registerEmail">Email</label>
                    <input type="email" id="registerEmail" name="email" placeholder="Enter your email" required />
                    <div class="field-error" id="registerEmailError"></div>
                </div>

                <div class="form-group">
                    <label for="registerPassword">Password</label>
                    <input type="password" id="registerPassword" name="password" placeholder="Create a password" required />
                    <div class="field-error" id="registerPasswordError"></div>
                </div>

                <div class="form-group">
                    <label for="registerConfirmPassword">Confirm Password</label>
                    <input type="password" id="registerConfirmPassword" name="confirm_password" placeholder="Confirm your password" required />
                    <div class="field-error" id="registerConfirmPasswordError"></div>
                </div>

                <button type="submit" class="primary-btn" id="registerSubmitBtn">Create Account</button>
            </form>

            <div class="auth-divider">
                <span>or continue with</span>
            </div>

            <div class="auth-actions">
                <button type="button" id="googleLoginBtn" class="secondary-btn">Continue with Google</button>
                <button type="button" id="guestLoginBtn" class="ghost-btn">Continue as Guest</button>
            </div>
        </section>
    </div>

    <script type="module" src="js/firebase-auth.js"></script>
</body>
</html>
