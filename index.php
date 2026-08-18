<?php
require_once __DIR__ . '/api/config.php';
$currentUser = get_current_auth_user();
$guestInfo = get_guest_info();

// If admin or staff accesses the customer order page, redirect to their dedicated dashboard
if ($currentUser) {
    if ($currentUser['role'] === 'admin') {
        header('Location: admin.php');
        exit;
    } elseif ($currentUser['role'] === 'staff') {
        header('Location: kitchen.php');
        exit;
    }
}

// If user is not logged in AND not a guest, redirect to login page
if (!$currentUser && !$guestInfo) {
    $queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
    $redirectUrl = 'index.php' . $queryString;
    header('Location: login.php?redirect=' . urlencode($redirectUrl));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sate Tulang Madu - Charcoal Grilled Skewers & Platters</title>
    <meta name="description" content="Order authentic charcoal-grilled Sate Tulang Madu, Satay Ayam, Daging, Kambing, and Sharing Platters with rich peanut sauce for Dine-In, Takeaway, and Delivery.">
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

    <!-- Top Navigation Bar -->
    <header class="navbar">
        <div class="nav-brand">
            <div class="nav-logo-icon">S</div>
            <div class="brand-text">
                <h1>SATE TULANG MADU</h1>
                <p>Authentic Charcoal Grilled Skewers</p>
            </div>
        </div>

        <div class="nav-actions" style="display:flex; align-items:center; gap:0.75rem;">
            <!-- Order History Button -->
            <button class="btn btn-secondary btn-sm" onclick="CustomerApp.showOrderHistoryModal()" id="btn-my-orders">
                My Orders
            </button>

            <!-- Customer Identity & Auth Menu Container -->
            <div id="customer-nav-auth-container">
                <?php if ($currentUser): ?>
                    <!-- Logged-in User Dropdown -->
                    <div class="customer-profile-menu">
                        <button class="profile-trigger-btn" onclick="CustomerApp.toggleProfileDropdown(event)">
                            <span id="nav-user-name"><?php echo htmlspecialchars($currentUser['full_name']); ?></span>
                            <span class="badge <?php echo $currentUser['role'] === 'customer' ? 'role-tag-customer' : 'badge-primary'; ?>" style="font-size:0.65rem; padding:1px 6px;">
                                <?php echo strtoupper($currentUser['role']); ?>
                            </span>
                            <span style="font-size:0.65rem;">&#9662;</span>
                        </button>
                        <div class="profile-dropdown-menu" id="profile-dropdown-menu">
                            <?php if ($currentUser['role'] === 'customer'): ?>
                                <button class="profile-dropdown-item" onclick="CustomerApp.openProfileModal()">
                                    My Saved Profile
                                </button>
                                <button class="profile-dropdown-item" onclick="CustomerApp.showOrderHistoryModal()">
                                    My Past Orders
                                </button>
                                <div class="profile-dropdown-divider"></div>
                            <?php elseif ($currentUser['role'] === 'staff'): ?>
                                <a href="kitchen.php" class="profile-dropdown-item">
                                    Open Kitchen Display
                                </a>
                                <div class="profile-dropdown-divider"></div>
                            <?php elseif ($currentUser['role'] === 'admin'): ?>
                                <a href="admin.php" class="profile-dropdown-item">
                                    Admin Control Center
                                </a>
                                <a href="kitchen.php" class="profile-dropdown-item">
                                    Kitchen Display (KDS)
                                </a>
                                <div class="profile-dropdown-divider"></div>
                            <?php endif; ?>
                            <a href="logout.php?redirect=login.php" class="profile-dropdown-item" style="color:var(--danger);">
                                Sign Out
                            </a>
                        </div>
                    </div>
                <?php elseif ($guestInfo): ?>
                    <!-- Guest Mode Badge & Switch to Sign In / Exit -->
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span class="badge badge-gold" style="font-size:0.75rem; padding:0.3rem 0.7rem; border-radius:var(--radius-full);">
                            <?php echo htmlspecialchars($guestInfo['guest_name']); ?> (Guest)
                        </span>
                        <a href="login.php?tab=login" class="btn btn-primary btn-sm" style="font-size:0.75rem; padding:0.35rem 0.75rem;">
                            Sign In / Register
                        </a>
                        <a href="logout.php?redirect=login.php" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:0.35rem 0.6rem; color:var(--danger);" title="Exit Guest Mode">
                            Exit
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>


    <main class="container">
        <!-- Hero Banner -->
        <section class="hero-banner">
            <div class="hero-content">
                <div class="badge badge-primary" style="margin-bottom:0.75rem;">Fresh Charcoal Grilled Daily</div>
                <h2>Aromatic, Juicy & Smokey <span>Satay Perfection</span></h2>
                <p>Handcrafted skewers marinated in rich traditional spices, grilled over red-hot charcoal and served with thick velvety peanut sauce.</p>

                <!-- Dining Mode Selector -->
                <div class="dining-mode-selector">
                    <button type="button" class="mode-btn active" data-mode="dine_in">
                        Dine-In
                    </button>
                    <button type="button" class="mode-btn" data-mode="takeaway">
                        Takeaway
                    </button>
                    <button type="button" class="mode-btn" data-mode="delivery">
                        Delivery
                    </button>
                </div>
            </div>

            <div style="min-width: 260px; text-align: right; display: flex; flex-direction: column; gap: 0.75rem;">
                <input type="text" id="search-menu" class="form-input" placeholder="Search skewers, platters..." style="background: var(--bg-card);">
                <div style="font-size:0.8rem; color:var(--text-muted); text-align:left;">
                    <em>Tip: Satay skewers require a minimum order of 5 sticks per meat type.</em>
                </div>
            </div>
        </section>
        
        <!-- Scanned Table Banner (Dynamically displayed when arriving via QR code) -->
        <div id="table-scanned-banner" style="display:none;"></div>

        <!-- Category Nav Filter -->
        <nav class="category-nav" id="category-nav">
            <!-- Dynamically populated -->
        </nav>

        <!-- Menu Cards Grid -->
        <section class="menu-grid" id="menu-grid">
            <!-- Dynamically loaded via customer.js -->
        </section>
    </main>

    <!-- Floating Cart Trigger Button -->
    <button class="cart-floating-btn" id="floating-cart-btn" aria-label="View Order Cart">
        <span>View Cart</span>
        <span class="cart-counter-bubble" id="cart-bubble-count">0</span>
        <span id="floating-cart-total" style="margin-left:auto; font-weight:800;">RM 0.00</span>
    </button>

    <!-- Slide-over Cart Drawer -->
    <div class="drawer-backdrop" id="cart-backdrop"></div>
    <aside class="drawer" id="cart-drawer">
        <div class="drawer-header">
            <h3 style="font-size:1.1rem;">Your Order Cart</h3>
            <button class="btn btn-secondary btn-icon" id="close-cart-btn" aria-label="Close Cart">&#10005;</button>
        </div>

        <div class="drawer-body" id="cart-items-list">
            <!-- Populated via customer.js -->
        </div>

        <div class="drawer-footer">
            <div style="margin-bottom:1rem; font-size:0.86rem;">
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span style="color:var(--text-muted);">Total Skewers:</span>
                    <strong id="cart-stick-count" style="color:var(--primary);">0 Sticks</strong>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span style="color:var(--text-muted);">Subtotal:</span>
                    <span id="cart-subtotal">RM 0.00</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                    <span style="color:var(--text-muted);">SST (6%):</span>
                    <span id="cart-tax">RM 0.00</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <span style="color:var(--text-muted);">Delivery Fee:</span>
                    <span id="cart-delivery-fee">RM 0.00</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:1.15rem; font-weight:800; padding-top:6px; border-top:1px solid var(--border-color);">
                    <span>Total:</span>
                    <span id="cart-total" style="color:var(--primary);">RM 0.00</span>
                </div>
            </div>

            <button type="button" class="btn btn-primary" style="width:100%; font-size:0.95rem; padding:0.8rem;" onclick="CustomerApp.proceedToCheckout()">
                Proceed to Checkout
            </button>
        </div>
    </aside>

    <!-- Skewer Customizer Modal -->
    <div class="modal-overlay" id="customizer-modal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Customize Your Order</h3>
                <button class="btn btn-secondary btn-icon" onclick="SatayApp.closeModal('customizer-modal')">&#10005;</button>
            </div>
            <div class="modal-body" id="customizer-modal-content">
                <!-- Populated dynamically -->
            </div>
        </div>
    </div>

    <!-- Customer Auth Modal (Login / Register / Guest Mode) -->
    <div class="modal-overlay" id="customer-auth-modal">
        <div class="modal-box" style="width: 480px;">
            <div class="modal-header">
                <h3 id="auth-modal-title">Customer Account & Access</h3>
                <button class="btn btn-secondary btn-icon" onclick="SatayApp.closeModal('customer-auth-modal')">&#10005;</button>
            </div>
            <div class="modal-body">
                <!-- Auth Tabs Navigation -->
                <div class="auth-tabs-nav">
                    <button type="button" class="auth-tab-btn active" data-tab="auth-login" onclick="CustomerApp.switchAuthTab('auth-login')">
                        Sign In
                    </button>
                    <button type="button" class="auth-tab-btn" data-tab="auth-register" onclick="CustomerApp.switchAuthTab('auth-register')">
                        Register
                    </button>
                    <button type="button" class="auth-tab-btn" data-tab="auth-guest" onclick="CustomerApp.switchAuthTab('auth-guest')">
                        Guest Mode
                    </button>
                </div>

                <!-- 1. Sign In Form -->
                <div class="auth-tab-pane active" id="auth-login">
                    <form id="customer-login-form">
                        <div class="form-group">
                            <label class="form-label" for="cust-login-user">Username, Email, or Phone:</label>
                            <input type="text" class="form-input" id="cust-login-user" placeholder="e.g. ahmad or 012-9876543" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="cust-login-pass">Password:</label>
                            <input type="password" class="form-input" id="cust-login-pass" placeholder="••••••••" required>
                        </div>
                        <div id="cust-login-error" style="display:none; color:var(--danger); font-size:0.85rem; margin-bottom:0.75rem; text-align:center;"></div>
                        <button type="submit" class="btn btn-primary" id="btn-cust-login" style="width:100%; padding:0.8rem;">
                            Sign In to Account
                        </button>
                    </form>
                </div>

                <!-- 2. Register Form -->
                <div class="auth-tab-pane" id="auth-register">
                    <form id="customer-register-form">
                        <div class="form-group">
                            <label class="form-label" for="reg-fullname">Full Name:</label>
                            <input type="text" class="form-input" id="reg-fullname" placeholder="e.g. Ahmad Firdaus" required>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                            <div class="form-group">
                                <label class="form-label" for="reg-username">Username:</label>
                                <input type="text" class="form-input" id="reg-username" placeholder="ahmad123" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="reg-phone">Phone Number:</label>
                                <input type="tel" class="form-input" id="reg-phone" placeholder="012-3456789" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reg-email">Email Address (Optional):</label>
                            <input type="email" class="form-input" id="reg-email" placeholder="ahmad@example.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reg-address">Delivery Address (Optional):</label>
                            <textarea class="form-textarea" id="reg-address" rows="2" placeholder="House/Unit #, Street, City, Postcode"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="reg-password">Create Password:</label>
                            <input type="password" class="form-input" id="reg-password" placeholder="Min. 4 characters" required>
                        </div>
                        <div id="cust-reg-error" style="display:none; color:var(--danger); font-size:0.85rem; margin-bottom:0.75rem; text-align:center;"></div>
                        <button type="submit" class="btn btn-primary" id="btn-cust-register" style="width:100%; padding:0.8rem;">
                            Create Account & Start Ordering
                        </button>
                    </form>
                </div>

                <!-- 3. Guest Mode Form -->
                <div class="auth-tab-pane" id="auth-guest">
                    <div style="background:var(--gold-light); border:1px solid rgba(155, 106, 47, 0.25); border-radius:var(--radius-md); padding:1rem; margin-bottom:1.25rem;">
                        <h4 style="color:var(--gold); font-size:0.92rem; margin-bottom:0.35rem; font-family:var(--font-body); font-weight:700;">Quick Guest Ordering</h4>
                        <p style="font-size:0.82rem; color:var(--text-muted); line-height:1.4;">
                            No account needed! Just enter your nickname and phone number so the kitchen knows who to call when your skewers are hot & ready.
                        </p>
                    </div>

                    <form id="customer-guest-form">
                        <div class="form-group">
                            <label class="form-label" for="guest-nickname">Your Nickname / Name:</label>
                            <input type="text" class="form-input" id="guest-nickname" placeholder="e.g. Bob or Sarah" required value="<?php echo htmlspecialchars($guestInfo['guest_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="guest-phone">Phone Number (For WhatsApp / SMS Alerts):</label>
                            <input type="tel" class="form-input" id="guest-phone" placeholder="e.g. 012-3456789" value="<?php echo htmlspecialchars($guestInfo['guest_phone'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%; padding:0.8rem;">
                            Continue as Guest
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Profile Modal -->
    <div class="modal-overlay" id="profile-modal">
        <div class="modal-box" style="width: 480px;">
            <div class="modal-header">
                <h3>My Customer Profile</h3>
                <button class="btn btn-secondary btn-icon" onclick="SatayApp.closeModal('profile-modal')">&#10005;</button>
            </div>
            <form id="customer-profile-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="prof-fullname">Full Name:</label>
                        <input type="text" class="form-input" id="prof-fullname" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="prof-phone">Phone Number:</label>
                        <input type="tel" class="form-input" id="prof-phone">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="prof-email">Email Address:</label>
                        <input type="email" class="form-input" id="prof-email">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="prof-address">Default Delivery Address:</label>
                        <textarea class="form-textarea" id="prof-address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="SatayApp.closeModal('profile-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Checkout Modal -->
    <div class="modal-overlay" id="checkout-modal">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Confirm & Checkout</h3>
                <button class="btn btn-secondary btn-icon" onclick="SatayApp.closeModal('checkout-modal')">&#10005;</button>
            </div>
            <form id="checkout-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="checkout-name">Your Name / Nickname:</label>
                        <input type="text" class="form-input" id="checkout-name" placeholder="e.g. Encik Farhan" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="checkout-phone">Phone Number (For WhatsApp / SMS status):</label>
                        <input type="tel" class="form-input" id="checkout-phone" placeholder="e.g. 012-3456789">
                    </div>

                    <!-- Dine-in specific -->
                    <div class="form-group" id="dine-in-fields">
                        <label class="form-label" for="checkout-table">Choose Dining Table:</label>
                        <select class="form-select" id="checkout-table">
                            <option value="">-- Select Table --</option>
                        </select>
                    </div>

                    <!-- Delivery specific -->
                    <div class="form-group" id="delivery-fields" style="display:none;">
                        <label class="form-label" for="checkout-address">Delivery Address:</label>
                        <textarea class="form-textarea" id="checkout-address" rows="2" placeholder="Full delivery address with unit/floor number"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="checkout-payment">Payment Method:</label>
                        <select class="form-select" id="checkout-payment">
                            <option value="cash">Cash at Counter / COD</option>
                            <option value="qr_pay">DuitNow QR / Touch 'n Go eWallet</option>
                            <option value="card">Debit / Credit Card</option>
                            <option value="online">Online Banking (FPX)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="checkout-order-notes">Special Cooking Request:</label>
                        <input type="text" class="form-input" id="checkout-order-notes" placeholder="e.g. Bakar garing, kuah jangan pedas sangat...">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="SatayApp.closeModal('checkout-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-order">Confirm & Place Order</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Live Order Tracker Modal -->
    <div class="modal-overlay" id="tracker-modal">
        <div class="modal-box" style="width: 620px;">
            <div class="modal-header">
                <h3>Live Kitchen Tracking</h3>
                <button class="btn btn-secondary btn-icon" onclick="CustomerApp.stopLiveTracking()">&#10005;</button>
            </div>
            <div class="modal-body" id="tracker-details-container">
                <!-- Real-time progress updates -->
            </div>
        </div>
    </div>

    <!-- Order History Modal -->
    <div class="modal-overlay" id="history-modal">
        <div class="modal-box" style="width: 580px;">
            <div class="modal-header">
                <h3>My Orders & History</h3>
                <button class="btn btn-secondary btn-icon" onclick="SatayApp.closeModal('history-modal')">&#10005;</button>
            </div>
            <div class="modal-body" id="history-modal-content">
                <!-- Populated via customer.js -->
            </div>
        </div>
    </div>

    <!-- Core Scripts -->
    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
    <script src="assets/js/customer.js?v=<?php echo time(); ?>"></script>
</body>
</html>
