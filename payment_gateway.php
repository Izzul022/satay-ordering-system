<?php
/**
 * Satay Ordering System - Online Payment Gateway Simulator
 * A simulated payment portal (FPX, Cards, E-Wallets) for testing online payments.
 */

require_once __DIR__ . '/api/config.php';

$orderNumber = trim($_GET['order_number'] ?? $_GET['order'] ?? '');
$token = trim($_GET['token'] ?? '');

$pdo = get_db_connection();
$order = null;
$orderItems = [];
$error = null;

if (!empty($orderNumber)) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if ($order) {
        $itemStmt = $pdo->prepare("SELECT item_name, quantity, unit_price, total_price, special_notes FROM order_items WHERE order_id = ?");
        $itemStmt->execute([$order['id']]);
        $orderItems = $itemStmt->fetchAll();
    } else {
        $error = "Order #{$orderNumber} was not found in our database.";
    }
} else {
    $error = "No order number was provided for payment processing.";
}

$isAlreadyPaid = ($order && $order['payment_status'] === 'paid');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Payment Gateway Simulator - Sate Tulang Madu</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22 fill=%22%238B4513%22>S</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page: #0f1117;
            --bg-card: #181b24;
            --bg-surface: #222634;
            --bg-hover: #2b3042;
            --border-color: rgba(255, 255, 255, 0.08);
            --border-accent: rgba(255, 179, 0, 0.3);
            --primary: #f59e0b;
            --primary-hover: #d97706;
            --primary-glow: rgba(245, 158, 11, 0.25);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.25);
            --danger: #ef4444;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --text-light: #6b7280;
            --radius-lg: 16px;
            --radius-md: 10px;
            --radius-sm: 6px;
            --font-main: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-main);
            background: var(--bg-page);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* Top Simulation Banner */
        .sim-banner {
            background: linear-gradient(90deg, #b45309, #d97706, #b45309);
            color: #fff;
            padding: 0.5rem 1rem;
            text-align: center;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            z-index: 10;
        }

        .sim-banner-badge {
            background: rgba(0, 0, 0, 0.3);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        /* Gateway Layout */
        .gateway-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1.25rem;
            width: 100%;
            flex: 1;
        }

        .gateway-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 1.75rem;
            align-items: start;
        }

        @media (max-width: 860px) {
            .gateway-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .gateway-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.75rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            position: relative;
            overflow: hidden;
        }

        /* Left Column: Order Summary */
        .merchant-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.25rem;
        }

        .merchant-logo {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: linear-gradient(135deg, #f59e0b, #b45309);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 800;
            color: #fff;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        .merchant-name {
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
        }

        .merchant-sub {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .amount-display {
            background: var(--bg-surface);
            border-radius: var(--radius-md);
            padding: 1.2rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(245, 158, 11, 0.15);
            text-align: center;
        }

        .amount-label {
            font-size: 0.78rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 4px;
        }

        .amount-val {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary);
            font-family: var(--font-mono);
            line-height: 1.1;
        }

        .order-meta-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .order-meta-label {
            color: var(--text-muted);
        }

        .order-meta-val {
            font-weight: 600;
            color: var(--text-main);
            text-align: right;
        }

        .items-accordion {
            margin-top: 1.25rem;
            background: var(--bg-surface);
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .items-accordion-header {
            padding: 0.75rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            user-select: none;
        }

        .items-accordion-header:hover {
            color: #fff;
        }

        .items-accordion-body {
            padding: 0.5rem 1rem 0.75rem;
            border-top: 1px solid var(--border-color);
            max-height: 200px;
            overflow-y: auto;
            font-size: 0.82rem;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 0.35rem 0;
            border-bottom: 1px dashed rgba(255, 255, 255, 0.05);
        }

        .item-row:last-child {
            border-bottom: none;
        }

        /* Right Column: Payment Methods */
        .gateway-title {
            font-size: 1.35rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 0.35rem;
        }

        .gateway-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        /* Tabs */
        .payment-tabs {
            display: flex;
            gap: 8px;
            background: var(--bg-surface);
            padding: 4px;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
        }

        .payment-tab-btn {
            flex: 1;
            padding: 0.7rem 0.5rem;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .payment-tab-btn:hover {
            color: #fff;
        }

        .payment-tab-btn.active {
            background: var(--bg-card);
            color: var(--primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(245, 158, 11, 0.25);
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
            animation: fadeIn 0.25s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Bank Select Grid (FPX) */
        .bank-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 10px;
            margin-bottom: 1.5rem;
        }

        .bank-chip {
            background: var(--bg-surface);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.75rem 0.6rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .bank-chip:hover {
            border-color: rgba(245, 158, 11, 0.4);
            background: var(--bg-hover);
        }

        .bank-chip.selected {
            border-color: var(--primary);
            background: rgba(245, 158, 11, 0.1);
            box-shadow: 0 0 0 1px var(--primary);
        }

        .bank-chip.selected::after {
            content: '✓';
            position: absolute;
            top: 4px;
            right: 6px;
            font-size: 0.75rem;
            color: var(--primary);
            font-weight: 800;
        }

        .bank-icon {
            font-size: 1.5rem;
            margin-bottom: 4px;
        }

        .bank-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .bank-sub {
            font-size: 0.68rem;
            color: var(--text-muted);
        }

        /* QR & E-Wallets Tab */
        .qr-card-box {
            text-align: center;
            padding: 1.5rem 1rem;
            background: var(--bg-surface);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .mock-qr-svg {
            width: 170px;
            height: 170px;
            background: #fff;
            padding: 12px;
            border-radius: 12px;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ewallet-selector {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .ewallet-btn {
            background: var(--bg-card);
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 6px 14px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-main);
            cursor: pointer;
            transition: all 0.2s;
        }

        .ewallet-btn.selected {
            border-color: var(--primary);
            background: rgba(245, 158, 11, 0.15);
            color: #fff;
        }

        /* Card Form Tab */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.75rem 1rem;
            color: #fff;
            font-family: inherit;
            font-size: 0.92rem;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .test-card-helper {
            background: rgba(245, 158, 11, 0.08);
            border: 1px dashed rgba(245, 158, 11, 0.3);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            color: #fcd34d;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .btn-autofill {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid var(--primary);
            color: #fff;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-autofill:hover {
            background: var(--primary);
            color: #000;
        }

        /* Action Buttons */
        .action-group {
            margin-top: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn-pay {
            width: 100%;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #0f1117;
            border: none;
            border-radius: var(--radius-md);
            padding: 1rem;
            font-size: 1.05rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 15px var(--primary-glow);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-pay:hover:not(:disabled) {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px var(--primary-glow);
        }

        .btn-pay:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-cancel {
            width: 100%;
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.75rem;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .btn-cancel:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Security Badges Footer */
        .security-footer {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1.5rem;
            color: var(--text-light);
            font-size: 0.78rem;
            flex-wrap: wrap;
        }

        .security-badge {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Processing Modal / Overlay */
        .processing-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 17, 23, 0.92);
            backdrop-filter: blur(8px);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1.5rem;
            text-align: center;
        }

        .processing-overlay.active {
            display: flex;
        }

        .processing-box {
            background: var(--bg-card);
            border: 1px solid var(--border-accent);
            border-radius: var(--radius-lg);
            padding: 2.5rem 2rem;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(245, 158, 11, 0.2);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-bottom: 1.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .success-checkmark {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--success);
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #fff;
            margin-bottom: 1.5rem;
            animation: scaleIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 0 30px var(--success-glow);
        }

        @keyframes scaleIn {
            from { transform: scale(0.5); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .proc-status-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 0.5rem;
        }

        .proc-status-sub {
            font-size: 0.88rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .proc-steps-list {
            margin-top: 1.25rem;
            width: 100%;
            font-size: 0.82rem;
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            gap: 6px;
            text-align: left;
            background: var(--bg-surface);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-sm);
        }

        .proc-step-item.active {
            color: var(--primary);
            font-weight: 600;
        }

        .proc-step-item.done {
            color: var(--success);
            font-weight: 600;
        }
    </style>
</head>
<body>

    <!-- Environment Notice -->
    <div class="sim-banner">
        <span class="sim-banner-badge">TEST GATEWAY SIMULATOR</span>
        <span>Secure Sandbox Simulation &bull; No Real Money Charged &bull; Malaysian FPX &amp; Card Simulator</span>
    </div>

    <div class="gateway-container">
        <?php if ($error): ?>
            <div class="gateway-card" style="max-width:520px; margin:3rem auto; text-align:center;">
                <div style="font-size:3rem; margin-bottom:1rem;">⚠️</div>
                <h2 style="margin-bottom:0.5rem;">Order Lookup Failed</h2>
                <p style="color:var(--text-muted); margin-bottom:1.5rem;"><?php echo htmlspecialchars($error); ?></p>
                <a href="index.php" class="btn-cancel" style="display:inline-block; width:auto; padding:0.6rem 1.5rem;">Return to Menu</a>
            </div>
        <?php elseif ($isAlreadyPaid): ?>
            <div class="gateway-card" style="max-width:520px; margin:3rem auto; text-align:center;">
                <div style="font-size:3.5rem; margin-bottom:1rem;">✅</div>
                <h2 style="margin-bottom:0.5rem; color:var(--success);">Order Already Paid!</h2>
                <p style="color:var(--text-muted); margin-bottom:1rem;">
                    Order <strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong> was successfully paid on 
                    <?php echo htmlspecialchars($order['paid_at'] ?? 'record'); ?>.
                </p>
                <?php if (!empty($order['payment_gateway_ref'])): ?>
                    <div style="background:var(--bg-surface); padding:8px 12px; border-radius:6px; font-family:var(--font-mono); font-size:0.85rem; color:var(--primary); margin-bottom:1.5rem;">
                        Ref: <?php echo htmlspecialchars($order['payment_gateway_ref']); ?>
                    </div>
                <?php endif; ?>
                <a href="index.php?order=<?php echo urlencode($order['order_number']); ?>&payment=success" class="btn-pay" style="text-decoration:none;">
                    View Live Order Tracker ➔
                </a>
            </div>
        <?php else: ?>
            <div class="gateway-grid">
                <!-- Left: Order Summary Column -->
                <div class="gateway-card">
                    <div class="merchant-header">
                        <div class="merchant-logo">S</div>
                        <div>
                            <div class="merchant-name">SATE TULANG MADU</div>
                            <div class="merchant-sub">Authentic Charcoal Skewers</div>
                        </div>
                    </div>

                    <div class="amount-display">
                        <div class="amount-label">Total Payment Due</div>
                        <div class="amount-val">RM <?php echo number_format((float)$order['total_amount'], 2); ?></div>
                    </div>

                    <div>
                        <div class="order-meta-row">
                            <span class="order-meta-label">Order Number</span>
                            <span class="order-meta-val" style="font-family:var(--font-mono); color:var(--primary);">
                                #<?php echo htmlspecialchars($order['order_number']); ?>
                            </span>
                        </div>
                        <div class="order-meta-row">
                            <span class="order-meta-label">Customer Name</span>
                            <span class="order-meta-val"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                        </div>
                        <div class="order-meta-row">
                            <span class="order-meta-label">Dining Mode</span>
                            <span class="order-meta-val" style="text-transform:capitalize;">
                                <?php echo htmlspecialchars(str_replace('_', ' ', $order['dining_type'])); ?>
                                <?php if (!empty($order['table_number'])): ?>
                                    (Table <?php echo htmlspecialchars($order['table_number']); ?>)
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="order-meta-row">
                            <span class="order-meta-label">Subtotal</span>
                            <span class="order-meta-val">RM <?php echo number_format((float)$order['subtotal'], 2); ?></span>
                        </div>
                        <div class="order-meta-row">
                            <span class="order-meta-label">SST (6%)</span>
                            <span class="order-meta-val">RM <?php echo number_format((float)$order['tax_amount'], 2); ?></span>
                        </div>
                    </div>

                    <!-- Collapsible Item Breakdown -->
                    <div class="items-accordion">
                        <div class="items-accordion-header" onclick="toggleItemsAccordion()">
                            <span>Ordered Items (<?php echo count($orderItems); ?>)</span>
                            <span id="accordion-arrow">▼</span>
                        </div>
                        <div class="items-accordion-body" id="items-accordion-body" style="display:none;">
                            <?php foreach ($orderItems as $it): ?>
                                <div class="item-row">
                                    <span><?php echo (int)$it['quantity']; ?>x <?php echo htmlspecialchars($it['item_name']); ?></span>
                                    <strong style="color:#fcd34d;">RM <?php echo number_format((float)$it['total_price'], 2); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Right: Payment Method Selector Column -->
                <div class="gateway-card">
                    <div class="gateway-title">Select Payment Method</div>
                    <div class="gateway-subtitle">Choose how you would like to simulate your transaction.</div>

                    <!-- Payment Rail Tabs -->
                    <div class="payment-tabs">
                        <button type="button" class="payment-tab-btn active" data-tab="fpx" onclick="switchTab('fpx')">
                            🏦 FPX Banking
                        </button>
                        <button type="button" class="payment-tab-btn" data-tab="ewallet" onclick="switchTab('ewallet')">
                            📱 DuitNow / eWallet
                        </button>
                        <button type="button" class="payment-tab-btn" data-tab="card" onclick="switchTab('card')">
                            💳 Debit / Credit Card
                        </button>
                    </div>

                    <!-- Tab 1: FPX Online Banking -->
                    <div class="tab-pane active" id="pane-fpx">
                        <div style="font-size:0.85rem; font-weight:600; color:var(--text-muted); margin-bottom:10px;">
                            Select Your Bank (FPX Simulation):
                        </div>
                        <div class="bank-grid">
                            <div class="bank-chip selected" onclick="selectBank('Maybank2u', this)">
                                <div class="bank-icon">🟡</div>
                                <div class="bank-name">Maybank</div>
                                <div class="bank-sub">Maybank2u</div>
                            </div>
                            <div class="bank-chip" onclick="selectBank('CIMB Clicks', this)">
                                <div class="bank-icon">🔴</div>
                                <div class="bank-name">CIMB Bank</div>
                                <div class="bank-sub">CIMB Clicks</div>
                            </div>
                            <div class="bank-chip" onclick="selectBank('Public Bank', this)">
                                <div class="bank-icon">🔴</div>
                                <div class="bank-name">Public Bank</div>
                                <div class="bank-sub">PBe Online</div>
                            </div>
                            <div class="bank-chip" onclick="selectBank('RHB Now', this)">
                                <div class="bank-icon">🔵</div>
                                <div class="bank-name">RHB Bank</div>
                                <div class="bank-sub">RHB Now</div>
                            </div>
                            <div class="bank-chip" onclick="selectBank('Hong Leong Connect', this)">
                                <div class="bank-icon">⚪</div>
                                <div class="bank-name">Hong Leong</div>
                                <div class="bank-sub">HLB Connect</div>
                            </div>
                            <div class="bank-chip" onclick="selectBank('Bank Islam', this)">
                                <div class="bank-icon">🟢</div>
                                <div class="bank-name">Bank Islam</div>
                                <div class="bank-sub">IB Retail</div>
                            </div>
                            <div class="bank-chip" onclick="selectBank('AmBank', this)">
                                <div class="bank-icon">🔴</div>
                                <div class="bank-name">AmBank</div>
                                <div class="bank-sub">AmOnline</div>
                            </div>
                            <div class="bank-chip" onclick="selectBank('Affin Bank', this)">
                                <div class="bank-icon">🔵</div>
                                <div class="bank-name">Affin Bank</div>
                                <div class="bank-sub">AffinAlways</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: DuitNow QR & E-Wallets -->
                    <div class="tab-pane" id="pane-ewallet">
                        <div class="qr-card-box">
                            <div style="font-size:0.85rem; font-weight:700; color:var(--text-muted); margin-bottom:0.75rem;">
                                DuitNow QR / Multi-eWallet Simulation
                            </div>
                            <div class="mock-qr-svg">
                                <!-- Clean vector QR simulator -->
                                <svg viewBox="0 0 100 100" width="100%" height="100%">
                                    <rect width="100" height="100" fill="#ffffff" />
                                    <!-- Top-Left Marker -->
                                    <rect x="10" y="10" width="24" height="24" fill="#0f1117" />
                                    <rect x="14" y="14" width="16" height="16" fill="#ffffff" />
                                    <rect x="18" y="18" width="8" height="8" fill="#d97706" />
                                    <!-- Top-Right Marker -->
                                    <rect x="66" y="10" width="24" height="24" fill="#0f1117" />
                                    <rect x="70" y="14" width="16" height="16" fill="#ffffff" />
                                    <rect x="74" y="18" width="8" height="8" fill="#d97706" />
                                    <!-- Bottom-Left Marker -->
                                    <rect x="10" y="66" width="24" height="24" fill="#0f1117" />
                                    <rect x="14" y="70" width="16" height="16" fill="#ffffff" />
                                    <rect x="18" y="74" width="8" height="8" fill="#d97706" />
                                    <!-- Center S icon -->
                                    <rect x="42" y="42" width="16" height="16" rx="3" fill="#b45309" />
                                    <text x="50" y="54" font-size="11" font-weight="bold" fill="#ffffff" text-anchor="middle">S</text>
                                    <!-- Matrix pixels -->
                                    <rect x="38" y="12" width="6" height="6" fill="#1f2937" />
                                    <rect x="48" y="18" width="6" height="6" fill="#1f2937" />
                                    <rect x="14" y="42" width="6" height="6" fill="#1f2937" />
                                    <rect x="26" y="48" width="6" height="6" fill="#1f2937" />
                                    <rect x="68" y="44" width="6" height="6" fill="#1f2937" />
                                    <rect x="80" y="52" width="6" height="6" fill="#1f2937" />
                                    <rect x="42" y="68" width="6" height="6" fill="#1f2937" />
                                    <rect x="54" y="78" width="6" height="6" fill="#1f2937" />
                                    <rect x="68" y="72" width="6" height="6" fill="#1f2937" />
                                    <rect x="78" y="80" width="6" height="6" fill="#1f2937" />
                                </svg>
                            </div>
                            <div style="font-size:0.8rem; color:var(--text-muted);">
                                Supported apps: Touch 'n Go, GrabPay, Boost, ShopeePay
                            </div>
                            <div class="ewallet-selector">
                                <button type="button" class="ewallet-btn selected" onclick="selectEWallet('Touch \'n Go eWallet', this)">TNG eWallet</button>
                                <button type="button" class="ewallet-btn" onclick="selectEWallet('GrabPay', this)">GrabPay</button>
                                <button type="button" class="ewallet-btn" onclick="selectEWallet('ShopeePay', this)">ShopeePay</button>
                                <button type="button" class="ewallet-btn" onclick="selectEWallet('Boost', this)">Boost</button>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 3: Debit / Credit Card -->
                    <div class="tab-pane" id="pane-card">
                        <div class="test-card-helper">
                            <span>🧪 Sandbox Mode: Use dummy card numbers</span>
                            <button type="button" class="btn-autofill" onclick="autofillCard()">Auto-fill Test Card</button>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="card-number">Card Number</label>
                            <input type="text" class="form-input" id="card-number" placeholder="4242 &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; 4242" maxlength="19">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="card-exp">Expiry Date</label>
                                <input type="text" class="form-input" id="card-exp" placeholder="MM / YY" maxlength="5">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="card-cvv">CVV</label>
                                <input type="password" class="form-input" id="card-cvv" placeholder="123" maxlength="4">
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-group">
                        <button type="button" class="btn-pay" id="btn-pay-now" onclick="executePaymentSimulation('success')">
                            <span>🔒 Authorize &amp; Pay RM <?php echo number_format((float)$order['total_amount'], 2); ?></span>
                        </button>
                        <button type="button" class="btn-cancel" id="btn-cancel-pay" onclick="executePaymentSimulation('failed')">
                            Cancel &amp; Return to Order
                        </button>
                    </div>

                    <div class="security-footer">
                        <div class="security-badge">🔒 256-Bit SSL Encrypted</div>
                        <div class="security-badge">🛡️ Bank-Grade Verification</div>
                        <div class="security-badge">⚡ Instant Confirmation</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Processing Overlay Modal -->
    <div class="processing-overlay" id="processing-overlay">
        <div class="processing-box">
            <div class="spinner" id="proc-spinner"></div>
            <div class="success-checkmark" id="proc-success-icon">✓</div>
            <div class="proc-status-title" id="proc-title">Contacting Bank Gateway...</div>
            <div class="proc-status-sub" id="proc-sub">Please do not refresh or close this window while we verify your payment.</div>

            <div class="proc-steps-list">
                <div class="proc-step-item active" id="step-1">1. Establishing secure handshake</div>
                <div class="proc-step-item" id="step-2">2. Processing authorization via <span id="proc-provider-name">Maybank2u</span></div>
                <div class="proc-step-item" id="step-3">3. Finalizing receipt &amp; updating kitchen order</div>
            </div>
        </div>
    </div>

    <script>
        const ORDER_DATA = {
            orderNumber: <?php echo json_encode($orderNumber); ?>,
            token: <?php echo json_encode($token); ?>,
            totalAmount: <?php echo json_encode((float)($order['total_amount'] ?? 0)); ?>
        };

        let currentTab = 'fpx';
        let selectedProvider = 'Maybank2u';

        function switchTab(tabId) {
            currentTab = tabId;
            document.querySelectorAll('.payment-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tabId));
            document.querySelectorAll('.tab-pane').forEach(p => p.classList.toggle('active', p.id === 'pane-' + tabId));
            
            if (tabId === 'fpx') selectedProvider = document.querySelector('.bank-chip.selected .bank-name')?.innerText || 'Maybank2u';
            else if (tabId === 'ewallet') selectedProvider = document.querySelector('.ewallet-btn.selected')?.innerText || 'TNG eWallet';
            else if (tabId === 'card') selectedProvider = 'Visa/Mastercard';
        }

        function selectBank(name, el) {
            document.querySelectorAll('.bank-chip').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            selectedProvider = name;
        }

        function selectEWallet(name, el) {
            document.querySelectorAll('.ewallet-btn').forEach(b => b.classList.remove('selected'));
            el.classList.add('selected');
            selectedProvider = name;
        }

        function autofillCard() {
            document.getElementById('card-number').value = '4242 •••• •••• 4242';
            document.getElementById('card-exp').value = '12/28';
            document.getElementById('card-cvv').value = '888';
        }

        function toggleItemsAccordion() {
            const body = document.getElementById('items-accordion-body');
            const arrow = document.getElementById('accordion-arrow');
            if (!body) return;
            const isOpen = body.style.display !== 'none';
            body.style.display = isOpen ? 'none' : 'block';
            if (arrow) arrow.innerText = isOpen ? '▼' : '▲';
        }

        async function executePaymentSimulation(action) {
            const overlay = document.getElementById('processing-overlay');
            const spinner = document.getElementById('proc-spinner');
            const successIcon = document.getElementById('proc-success-icon');
            const title = document.getElementById('proc-title');
            const sub = document.getElementById('proc-sub');
            const providerNameSpan = document.getElementById('proc-provider-name');

            if (providerNameSpan) providerNameSpan.innerText = selectedProvider;

            if (action === 'failed') {
                if (!confirm('Are you sure you want to cancel this payment? The order will remain unpaid.')) {
                    return;
                }
            }

            // Show processing overlay
            if (overlay) overlay.classList.add('active');

            if (action === 'success') {
                // Step 1
                document.getElementById('step-1')?.classList.add('active');
                await new Promise(r => setTimeout(r, 600));

                // Step 2
                document.getElementById('step-1')?.classList.remove('active');
                document.getElementById('step-1')?.classList.add('done');
                document.getElementById('step-2')?.classList.add('active');
                if (title) title.innerText = `Authorizing with ${selectedProvider}...`;
                await new Promise(r => setTimeout(r, 700));

                // Call Backend API
                try {
                    const res = await fetch('api/payment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            order_number: ORDER_DATA.orderNumber,
                            token: ORDER_DATA.token,
                            action: 'success',
                            payment_type: currentTab,
                            provider: selectedProvider
                        })
                    });

                    const data = await res.json();

                    // Step 3
                    document.getElementById('step-2')?.classList.remove('active');
                    document.getElementById('step-2')?.classList.add('done');
                    document.getElementById('step-3')?.classList.add('done');

                    if (data.success) {
                        if (spinner) spinner.style.display = 'none';
                        if (successIcon) successIcon.style.display = 'flex';
                        if (title) {
                            title.innerText = 'Payment Successful!';
                            title.style.color = 'var(--success)';
                        }
                        if (sub) sub.innerText = `Approved! Redirecting you back to your live kitchen tracker...`;

                        setTimeout(() => {
                            window.location.href = data.redirect_url || `index.php?order=${encodeURIComponent(ORDER_DATA.orderNumber)}&payment=success`;
                        }, 1200);
                    } else {
                        alert(data.message || 'Payment processing failed.');
                        if (overlay) overlay.classList.remove('active');
                    }
                } catch (e) {
                    console.error(e);
                    alert('Network error while communicating with gateway API.');
                    if (overlay) overlay.classList.remove('active');
                }
            } else {
                // Cancellation Flow
                try {
                    const res = await fetch('api/payment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            order_number: ORDER_DATA.orderNumber,
                            token: ORDER_DATA.token,
                            action: 'failed'
                        })
                    });
                    const data = await res.json();
                    window.location.href = data.redirect_url || `index.php?order=${encodeURIComponent(ORDER_DATA.orderNumber)}&payment=failed`;
                } catch (e) {
                    window.location.href = `index.php?order=${encodeURIComponent(ORDER_DATA.orderNumber)}&payment=failed`;
                }
            }
        }
    </script>
</body>
</html>
