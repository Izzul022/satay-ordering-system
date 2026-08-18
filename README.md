# Sate Tulang Madu - Satay Ordering & Restaurant Management System

A production-grade, full-featured **Satay Restaurant & Kitchen Management System** featuring a unified authentication hub, role-based access control, and dedicated views:

1. **Unified Authentication Hub** (`login.php`):
   - **All unauthenticated visitors are automatically routed to `login.php`**.
   - **Customer Access**: Choose between **Sign In**, **Register**, or **1-Click Guest Mode**.
   - **Staff Access**: Direct sign-in for Kitchen KDS & Cashier POS.
   - **Admin Access**: Secure executive sign-in for the Admin Control Center.
2. **Customer Ordering Experience** (`index.php`):
   - Browse the charcoal-grilled satay catalog.
   - Customer account profile with saved delivery address and synced order history.
   - Fast Guest Mode ordering for table QR dine-in and quick takeaway.
   - Live order tracker with stages & printable thermal receipts.
3. **Kitchen & POS Terminal** (`kitchen.php`):
   - **Requires Staff or Admin authentication**.
   - Live Kitchen Display System (KDS) with stick aggregation, audio notifications, and 1-click status progression.
   - Walk-in Cashier POS terminal with cash & change calculator.
   - Live Stock Control availability toggles.
4. **Admin Control Center** (`admin.php`):
   - **Requires Master Administrator authentication**.
   - Real-time sales analytics & revenue metrics.
   - **Menu Catalog Management**: Add, edit, and delete dishes, meat types, prices, and photos dynamically.
   - **User & Customer Accounts Management**: Manage staff accounts, admins, and inspect registered customer accounts.
   - Master Orders Directory with live filters and CSV export.
   - Contactless Table QR code stand generator.
   - Store and system configuration.

---

## How to Run

### Option A: Using XAMPP Apache
1. Open XAMPP Control Panel and start **Apache**.
2. Open your browser and navigate to:
   - **Unified Login**: `http://localhost/Ordering%20System/login.php`
   - **Customer Menu**: `http://localhost/Ordering%20System/index.php`
   - **Kitchen Display (KDS)**: `http://localhost/Ordering%20System/kitchen.php`
   - **Admin Control Center**: `http://localhost/Ordering%20System/admin.php`

### Option C: Online Cloud Deployment (Render.com / Docker)
Deploy online with 1 click using **Render.com** (Free Tier):
1. Push code to your GitHub repository.
2. Go to **Render.com** -> **New Web Service** -> Select GitHub repo -> Choose **Docker** runtime.
3. Your live HTTPS app is deployed at `https://your-app.onrender.com`.
4. Detailed guide available in [DEPLOYMENT.md](file:///c:/xampp/htdocs/Order_system_app/DEPLOYMENT.md).

---

## 📱 Mobile App & PWA Architecture

1. **Customer Experience (Zero-Install Frictionless PWA)**:
   - Scans Table QR stand with default phone camera.
   - Automatically navigates to `index.php?table=Table-01&token=...`.
   - Verified table lock-in, browse charcoal catalog, pick spiciness, and instant Guest/User checkout.
2. **Staff & Admin Native Android App (`android-app/`)**:
   - Installable `.apk` for kitchen tablets and manager phones.
   - Persistent screen wake-lock (keeps KDS display on during peak hours).
   - Loud order alert chime and vibration.
   - Server URL configuration dialog for seamless cloud or local connectivity.

---

## Database Architecture

The application uses **PDO SQLite** (`data/satay.sqlite`) with runtime schema migrations:
- `categories`: Menu categories (Skewers, Platters, Sides, Drinks).
- `menu_items`: Dynamic menu catalog created and managed by the Admin.
- `tables`: Dining tables and unique QR tokens.
- `orders`: Master orders linked to registered customer accounts (`user_id`) or guest sessions (`is_guest`).
- `order_items`: Line items with quantities, meat types, spicy levels, and special notes.
- `users`: User accounts with roles (`customer`, `staff`, `admin`), hashed passwords (`bcrypt`), and profile details.
- `settings`: System, store, and public domain configurations (`public_base_url`, `kitchen_sound_alert`, etc.).

