<?php
require_once __DIR__ . '/api/config.php';

$currentUser = get_current_auth_user();
$redirect = $_GET['redirect'] ?? '';
$activeTab = $_GET['tab'] ?? 'login'; // 'login', 'register', 'guest'
$logged_out = isset($_GET['logged_out']);
$error_type = $_GET['error'] ?? '';

// If already logged in, redirect automatically based on role
if ($currentUser) {
    if ($currentUser['role'] === 'admin') {
        header('Location: ' . (!empty($redirect) && (strpos($redirect, 'admin.php') !== false || strpos($redirect, 'kitchen.php') !== false) ? $redirect : 'admin.php'));
        exit;
    } elseif ($currentUser['role'] === 'staff') {
        header('Location: ' . (!empty($redirect) && strpos($redirect, 'kitchen.php') !== false ? $redirect : 'kitchen.php'));
        exit;
    } else {
        header('Location: ' . (!empty($redirect) && strpos($redirect, 'admin.php') === false && strpos($redirect, 'kitchen.php') === false ? $redirect : 'index.php'));
        exit;
    }
}

// Normalize tab selection
if (!in_array($activeTab, ['login', 'register', 'guest'])) {
    $activeTab = 'login';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Sate Tulang Madu</title>
    <meta name="description" content="Sign in, register, or order in guest mode for authentic charcoal-grilled skewers at Sate Tulang Madu.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22 fill=%22%238B4513%22>S</text></svg>">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#8B4513">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Sate Tulang">
</head>
<body>

    <div class="auth-wrapper">
        <div class="auth-card">
            <!-- Brand Header -->
            <div style="text-align:center; margin-bottom:1.75rem;">
                <div class="nav-logo-icon" style="margin:0 auto 0.75rem; width:54px; height:54px; font-size:1.4rem;">
                    S
                </div>
                <h1 style="font-size:1.45rem; font-weight:800; margin-bottom:0.25rem; letter-spacing:-0.01em;">SATE TULANG MADU</h1>
                <p style="color:var(--text-muted); font-size:0.85rem; font-family:var(--font-body);">Authentic Charcoal-Grilled Skewers</p>
            </div>

            <!-- Notification Messages -->
            <?php if ($logged_out): ?>
                <div style="background:var(--success-light); border:1px solid var(--success); color:var(--success); padding:0.75rem; border-radius:var(--radius-md); font-size:0.84rem; margin-bottom:1.25rem; text-align:center; font-weight:500;">
                    You have been signed out successfully.
                </div>
            <?php elseif ($error_type === 'auth_required'): ?>
                <div style="background:var(--primary-light); border:1px solid var(--primary); color:var(--primary); padding:0.75rem; border-radius:var(--radius-md); font-size:0.84rem; margin-bottom:1.25rem; text-align:center; font-weight:500;">
                    Please sign in or continue as guest to access that page.
                </div>
            <?php elseif ($error_type === 'forbidden'): ?>
                <div style="background:var(--danger-light); border:1px solid var(--danger); color:var(--danger); padding:0.75rem; border-radius:var(--radius-md); font-size:0.84rem; margin-bottom:1.25rem; text-align:center; font-weight:500;">
                    Access Denied: You do not have permission for that section.
                </div>
            <?php endif; ?>

            <!-- Global Error / Alert Box -->
            <div id="auth-error-msg" style="display:none; background:var(--danger-light); border:1px solid var(--danger); color:var(--danger); padding:0.75rem; border-radius:var(--radius-md); font-size:0.84rem; margin-bottom:1.25rem; text-align:center; font-weight:600;"></div>

            <!-- Navigation Tabs (Sign In, Register, Guest Mode) -->
            <div class="auth-tabs-nav">
                <button type="button" class="auth-tab-btn <?php echo ($activeTab === 'login') ? 'active' : ''; ?>" id="tab-btn-login" onclick="AuthApp.switchTab('login')">
                    Sign In
                </button>
                <button type="button" class="auth-tab-btn <?php echo ($activeTab === 'register') ? 'active' : ''; ?>" id="tab-btn-register" onclick="AuthApp.switchTab('register')">
                    Register
                </button>
                <button type="button" class="auth-tab-btn <?php echo ($activeTab === 'guest') ? 'active' : ''; ?>" id="tab-btn-guest" onclick="AuthApp.switchTab('guest')">
                    Guest Mode
                </button>
            </div>

            <!-- Hidden Redirect Target -->
            <input type="hidden" id="auth-redirect" value="<?php echo htmlspecialchars($redirect); ?>">

            <!-- ===================================================================
                 1. SIGN IN TAB
                 =================================================================== -->
            <div class="auth-tab-pane <?php echo ($activeTab === 'login') ? 'active' : ''; ?>" id="pane-login">
                <form id="form-login" onsubmit="AuthApp.handleLogin(event)">
                    <div class="form-group">
                        <label class="form-label" for="login-username">Username, Email, or Phone:</label>
                        <input type="text" class="form-input" id="login-username" placeholder="e.g. username, email, or phone" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="login-password">Password:</label>
                        <div class="password-input-wrap">
                            <input type="password" class="form-input" id="login-password" placeholder="••••••••" required>
                            <button type="button" class="password-toggle-btn" onclick="AuthApp.togglePassword('login-password', this)" title="Show/Hide Password">👁️</button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btn-login-submit" style="width:100%; padding:0.8rem; font-size:0.92rem; font-weight:600; margin-top:0.5rem;">
                        Sign In
                    </button>
                </form>

                <div style="margin-top:1.25rem; text-align:center; font-size:0.82rem; color:var(--text-muted); display:flex; justify-content:center; gap:1rem;">
                    <a href="javascript:void(0)" onclick="AuthApp.switchTab('register')" style="color:var(--primary); font-weight:600;">Create Account</a>
                    <span>•</span>
                    <a href="javascript:void(0)" onclick="AuthApp.switchTab('guest')" style="color:var(--text-muted);">Continue as Guest</a>
                </div>
            </div>

            <!-- ===================================================================
                 2. REGISTER TAB
                 =================================================================== -->
            <div class="auth-tab-pane <?php echo ($activeTab === 'register') ? 'active' : ''; ?>" id="pane-register">
                <form id="form-register" onsubmit="AuthApp.handleRegister(event)">
                    <div class="form-group">
                        <label class="form-label" for="reg-fullname">Full Name: <span style="color:var(--danger);">*</span></label>
                        <input type="text" class="form-input" id="reg-fullname" placeholder="e.g. Ahmad Razali" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-username">Username: <span style="color:var(--danger);">*</span></label>
                        <input type="text" class="form-input" id="reg-username" placeholder="e.g. ahmad99" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-phone">Phone Number (For WhatsApp updates):</label>
                        <input type="tel" class="form-input" id="reg-phone" placeholder="e.g. 012-3456789">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-email">Email Address (Optional):</label>
                        <input type="email" class="form-input" id="reg-email" placeholder="e.g. ahmad@gmail.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-address">Delivery Address (Optional):</label>
                        <textarea class="form-textarea" id="reg-address" rows="2" placeholder="Street, Postcode, City (Saved for delivery orders)"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="reg-password">Password: <span style="color:var(--danger);">*</span></label>
                        <div class="password-input-wrap">
                            <input type="password" class="form-input" id="reg-password" placeholder="Min. 4 characters" required minlength="4">
                            <button type="button" class="password-toggle-btn" onclick="AuthApp.togglePassword('reg-password', this)" title="Show/Hide Password">👁️</button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="btn-register-submit" style="width:100%; padding:0.8rem; font-size:0.92rem; font-weight:600; margin-top:0.5rem;">
                        Create Account & Start Ordering
                    </button>
                </form>

                <div style="margin-top:1.25rem; text-align:center; font-size:0.82rem; color:var(--text-muted); display:flex; justify-content:center; gap:1rem;">
                    <a href="javascript:void(0)" onclick="AuthApp.switchTab('login')" style="color:var(--primary); font-weight:600;">Already registered? Sign In</a>
                    <span>•</span>
                    <a href="javascript:void(0)" onclick="AuthApp.switchTab('guest')" style="color:var(--text-muted);">Continue as Guest</a>
                </div>
            </div>

            <!-- ===================================================================
                 3. GUEST MODE TAB
                 =================================================================== -->
            <div class="auth-tab-pane <?php echo ($activeTab === 'guest') ? 'active' : ''; ?>" id="pane-guest">
                <div class="guest-highlight-box">
                    <h4>Instant Guest Ordering</h4>
                    <p>No password or registration needed. Browse our charcoal-grilled satay menu and order right away.</p>
                    
                    <button type="button" class="btn btn-primary" onclick="AuthApp.handleInstantGuest()" id="btn-instant-guest" style="width:100%; padding:0.75rem; font-size:0.9rem; font-weight:700; background:var(--gold); border-color:var(--gold);">
                        ⚡ Instant 1-Click Guest Access
                    </button>
                </div>

                <div style="text-align:center; margin:1rem 0; font-size:0.78rem; color:var(--text-dim); text-transform:uppercase; letter-spacing:0.05em;">
                    — Or Enter Your Name For Receipt —
                </div>

                <form id="form-guest" onsubmit="AuthApp.handleGuest(event)">
                    <div class="form-group">
                        <label class="form-label" for="guest-name">Your Name / Nickname:</label>
                        <input type="text" class="form-input" id="guest-name" placeholder="e.g. Encik Farhan" value="Guest Customer">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="guest-phone">Phone Number (Optional):</label>
                        <input type="tel" class="form-input" id="guest-phone" placeholder="e.g. 012-3456789">
                    </div>

                    <button type="submit" class="btn btn-secondary" id="btn-guest-submit" style="width:100%; padding:0.75rem; font-size:0.88rem; font-weight:600;">
                        Continue to Menu
                    </button>
                </form>

                <div style="margin-top:1.25rem; text-align:center; font-size:0.82rem; color:var(--text-muted);">
                    <a href="javascript:void(0)" onclick="AuthApp.switchTab('login')" style="color:var(--primary); font-weight:600;">Sign in to existing account</a>
                </div>
            </div>

        </div>
    </div>

    <!-- Core Scripts -->
    <script src="assets/js/app.js"></script>
    <script>
        const AuthApp = {
            currentTab: '<?php echo $activeTab; ?>',

            switchTab(tab) {
                this.currentTab = tab;
                this.clearError();

                // Update Tab Buttons
                document.querySelectorAll('.auth-tab-btn').forEach(btn => btn.classList.remove('active'));
                const activeBtn = document.getElementById(`tab-btn-${tab}`);
                if (activeBtn) activeBtn.classList.add('active');

                // Update Panes
                document.querySelectorAll('.auth-tab-pane').forEach(pane => pane.classList.remove('active'));
                const targetPane = document.getElementById(`pane-${tab}`);
                if (targetPane) targetPane.classList.add('active');

                // Update URL parameter without reload
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tab);
                window.history.replaceState({}, '', url);
            },

            togglePassword(inputId, btn) {
                const input = document.getElementById(inputId);
                if (!input) return;
                if (input.type === 'password') {
                    input.type = 'text';
                    btn.innerText = '🙈';
                } else {
                    input.type = 'password';
                    btn.innerText = '👁️';
                }
            },

            showError(message) {
                const errBox = document.getElementById('auth-error-msg');
                if (errBox) {
                    errBox.innerText = message;
                    errBox.style.display = 'block';
                }
                SatayApp.showToast(message, 'danger');
            },

            clearError() {
                const errBox = document.getElementById('auth-error-msg');
                if (errBox) {
                    errBox.innerText = '';
                    errBox.style.display = 'none';
                }
            },

            getRedirectUrl(defaultUrl = 'index.php') {
                const redirectInput = document.getElementById('auth-redirect');
                return (redirectInput && redirectInput.value.trim()) ? redirectInput.value.trim() : defaultUrl;
            },

            async handleLogin(e) {
                e.preventDefault();
                this.clearError();

                const username = document.getElementById('login-username').value.trim();
                const password = document.getElementById('login-password').value.trim();
                const submitBtn = document.getElementById('btn-login-submit');
                const redirect = this.getRedirectUrl('');

                if (!username || !password) {
                    this.showError('Please enter both your username/email/phone and password.');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.innerText = 'Signing In...';

                try {
                    const res = await fetch('api/auth.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ username: username, password: password, redirect: redirect })
                    });
                    const data = await res.json();

                    if (data.success) {
                        SatayApp.showToast(data.message || 'Signed in successfully!', 'success');
                        setTimeout(() => {
                            window.location.href = data.redirect_url || 'index.php';
                        }, 400);
                    } else {
                        this.showError(data.message || 'Invalid credentials. Please try again.');
                        submitBtn.disabled = false;
                        submitBtn.innerText = 'Sign In';
                    }
                } catch (err) {
                    this.showError('Connection error. Please check your network and try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Sign In';
                }
            },

            async handleRegister(e) {
                e.preventDefault();
                this.clearError();

                const fullName = document.getElementById('reg-fullname').value.trim();
                const username = document.getElementById('reg-username').value.trim();
                const phone = document.getElementById('reg-phone').value.trim();
                const email = document.getElementById('reg-email').value.trim();
                const address = document.getElementById('reg-address').value.trim();
                const password = document.getElementById('reg-password').value.trim();
                const submitBtn = document.getElementById('btn-register-submit');
                const redirect = this.getRedirectUrl('index.php');

                if (!fullName || !username || !password) {
                    this.showError('Please fill in your Full Name, Username, and Password.');
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.innerText = 'Creating Account...';

                try {
                    const res = await fetch('api/auth.php?action=register', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            full_name: fullName,
                            username: username,
                            phone: phone,
                            email: email,
                            address: address,
                            password: password
                        })
                    });
                    const data = await res.json();

                    if (data.success) {
                        SatayApp.showToast(data.message || 'Account created successfully!', 'success');
                        setTimeout(() => {
                            window.location.href = data.redirect_url || redirect;
                        }, 400);
                    } else {
                        this.showError(data.message || 'Registration failed. Please try again.');
                        submitBtn.disabled = false;
                        submitBtn.innerText = 'Create Account & Start Ordering';
                    }
                } catch (err) {
                    this.showError('Connection error. Please check your network and try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerText = 'Create Account & Start Ordering';
                }
            },

            async handleInstantGuest() {
                const btn = document.getElementById('btn-instant-guest');
                btn.disabled = true;
                btn.innerText = 'Entering Guest Mode...';
                await this.submitGuestMode('Guest Customer', '');
            },

            async handleGuest(e) {
                e.preventDefault();
                this.clearError();

                const guestName = document.getElementById('guest-name').value.trim() || 'Guest Customer';
                const guestPhone = document.getElementById('guest-phone').value.trim();
                const btn = document.getElementById('btn-guest-submit');

                btn.disabled = true;
                btn.innerText = 'Entering Guest Mode...';
                await this.submitGuestMode(guestName, guestPhone);
            },

            async submitGuestMode(name, phone) {
                const redirect = this.getRedirectUrl('index.php');
                try {
                    const res = await fetch('api/auth.php?action=guest', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ name: name, phone: phone })
                    });
                    const data = await res.json();

                    if (data.success) {
                        SatayApp.showToast('Continuing in Guest Mode', 'success');
                        setTimeout(() => {
                            window.location.href = redirect;
                        }, 350);
                    } else {
                        this.showError(data.message || 'Failed to enter guest mode.');
                    }
                } catch (err) {
                    this.showError('Connection error. Please check your network and try again.');
                }
            }
        };
    </script>
</body>
</html>
