<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FOOD BOOK | Restaurant Partner Access</title>
    <link rel="stylesheet" href="css/style.css" />
</head>
<body class="login-body">
    <div class="login-wrapper auth-layout">
        <section class="brand-panel auth-brand-panel">
            <span class="eyebrow">Restaurant Partner Portal</span>
            <h1>FOOD BOOK</h1>
            <p>
                Restaurant partners can register, verify their email, and prepare for future dashboard access through the FOOD BOOK partner portal.
            </p>

            <div class="feature-list">
                <div class="feature-item">
                    <strong>Partner Access</strong>
                    <span>Create a restaurant partner account with secure email and password authentication.</span>
                </div>
                <div class="feature-item">
                    <strong>Email Verification</strong>
                    <span>Verify your email first so your partner account is ready for approval and dashboard access.</span>
                </div>
                <div class="feature-item">
                    <strong>Future Restaurant Mapping</strong>
                    <span>Your Firebase UID is saved for the upcoming restaurant onboarding and approval workflow.</span>
                </div>
            </div>
        </section>

        <section class="login-card auth-card">
            <div class="auth-card-header">
                <h2>Restaurant Partner Login</h2>
                <p>Register or log in with your partner email to continue.</p>
            </div>

            <div class="auth-tabs" role="tablist" aria-label="Partner authentication options">
                <button type="button" class="auth-tab is-active" data-auth-tab="login">Login</button>
                <button type="button" class="auth-tab" data-auth-tab="register">Register</button>
            </div>

            <div id="partnerAuthMessage" class="auth-message" aria-live="polite"></div>

            <form id="partnerLoginForm" class="auth-form is-active" autocomplete="on">
                <div class="form-group">
                    <label for="partnerLoginEmail">Email</label>
                    <input type="email" id="partnerLoginEmail" name="email" placeholder="Enter your partner email" required />
                    <div class="field-error" id="partnerLoginEmailError"></div>
                </div>

                <div class="form-group">
                    <label for="partnerLoginPassword">Password</label>
                    <input type="password" id="partnerLoginPassword" name="password" placeholder="Enter your password" required />
                    <div class="field-error" id="partnerLoginPasswordError"></div>
                </div>

                <button type="submit" class="primary-btn" id="partnerLoginSubmitBtn">Login as Partner</button>
            </form>

            <form id="partnerRegisterForm" class="auth-form" autocomplete="on">
                <div class="form-group">
                    <label for="partnerRegisterName">Full Name</label>
                    <input type="text" id="partnerRegisterName" name="full_name" placeholder="Enter your full name" required />
                    <div class="field-error" id="partnerRegisterNameError"></div>
                </div>

                <div class="form-group">
                    <label for="partnerRegisterEmail">Email</label>
                    <input type="email" id="partnerRegisterEmail" name="email" placeholder="Enter your partner email" required />
                    <div class="field-error" id="partnerRegisterEmailError"></div>
                </div>

                <div class="form-group">
                    <label for="partnerRegisterPassword">Password</label>
                    <input type="password" id="partnerRegisterPassword" name="password" placeholder="Create a password" required />
                    <div class="field-error" id="partnerRegisterPasswordError"></div>
                </div>

                <div class="form-group">
                    <label for="partnerRegisterConfirmPassword">Confirm Password</label>
                    <input type="password" id="partnerRegisterConfirmPassword" name="confirm_password" placeholder="Confirm your password" required />
                    <div class="field-error" id="partnerRegisterConfirmPasswordError"></div>
                </div>

                <button type="submit" class="primary-btn" id="partnerRegisterSubmitBtn">Create Partner Account</button>
            </form>

            <div class="auth-divider">
                <span>restaurant onboarding</span>
            </div>

            <div class="auth-actions">
                <button type="button" id="applyRestaurantBtn" class="secondary-btn">Apply to Open Your Restaurant</button>
            </div>
        </section>
    </div>

    <script type="module" src="js/partner-auth.js"></script>
</body>
</html>
