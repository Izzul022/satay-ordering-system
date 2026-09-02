# Sate Tulang Madu - Online Cloud Deployment & Mobile App Guide

This guide walks you through deploying your Satay Ordering & Restaurant Management System to the cloud (using **Render.com** and **Docker**) and setting up **online QR code table ordering** and the **Staff/Admin mobile app**.

---

## 🌟 Architecture Overview

```text
[ Customer Phone Camera ]  ---> Scan Table QR ---> [ Cloud Web Ordering (PWA) ]
                                                               |
                                                               v
[ Staff / Admin Mobile App (APK) ] <----------------- [ Render Cloud Server (PHP+SQLite) ]
  • Kitchen KDS (Live Grill Queue)
  • Cashier POS Terminal
  • Admin Control Center
```

---

## Part 1: Deploy to Free Cloud Hosting (Render.com)

Render allows you to host the application online for free with automatic SSL (`https://`).

### Step 1: Push your project to GitHub
1. Open your terminal in this project folder (`c:\xampp\htdocs\Order_system_app`):
   ```bash
   git init
   git add .
   git commit -m "feat: complete online cloud deployment, QR ordering, and mobile app"
   ```
2. Create a new repository on [GitHub.com](https://github.com/new) (e.g. `satay-ordering-system`).
3. Push your code to GitHub:
   ```bash
   git remote add origin https://github.com/YOUR_USERNAME/satay-ordering-system.git
   git branch -M main
   git push -u origin main
   ```

### Step 2: Deploy on Render.com
1. Sign up for a free account at [https://render.com](https://render.com).
2. Click **New +** -> **Web Service**.
3. Select **Build and deploy from a Git repository** and connect your GitHub repository.
4. Fill in the deployment details:
   - **Name**: `satay-ordering-system` (or your chosen name)
   - **Region**: `Singapore` (Recommended for low latency in Malaysia / SEA)
   - **Runtime**: `Docker`
   - **Instance Type**: `Free`
5. Click **Create Web Service**.
6. Render will build the Docker container and provide you with a live public HTTPS URL:
   ```text
   https://satay-ordering-system.onrender.com
   ```

---

## 🗄️ Database Setup: Connecting Online MySQL to Render

> [!WARNING]
> **Why customer orders reappear on Render when using default local SQLite:**
> Render container storage is **ephemeral**. Whenever your container restarts (every 2-3 days or on re-deploy), local SQLite database files (`data/satay.sqlite`) get **wiped and reset** back to initial repository state.

To make all order status changes, menu edits, and sales data **100% persistent**, connect a free online MySQL database to your Render service:

### Step 1: Create a Free MySQL Cloud Database
You can use any free MySQL provider:

#### Option A: Aiven.io (Recommended — Free 5GB MySQL)
1. Sign up at [https://aiven.io](https://aiven.io).
2. Create a new service -> select **MySQL** -> choose **Free Tier**.
3. Copy your service connection details:
   - **Host**: `mysql-xxx.aivencloud.com`
   - **Port**: `12345`
   - **Database**: `defaultdb` (or `satay_db`)
   - **User**: `avnadmin`
   - **Password**: `xxxxxxxxxxxx`

#### Option B: TiDB Cloud / Railway / Clever Cloud
- Create a free MySQL database and get your Host, Port, Database, User, and Password.

### Step 2: Import Database Schema
1. Open your MySQL management tool (phpMyAdmin, DBeaver, Aiven console, or MySQL CLI).
2. Import the included [`schema.sql`](file:///c:/xampp/htdocs/Order_system_app/schema.sql) file located in the root of your project folder.
3. This creates all 8 required tables (`orders`, `order_items`, `users`, `settings`, `categories`, `menu_items`, `tables`, `auth_tokens`) and pre-seeds default admin/staff logins.

### Step 3: Add Environment Variables in Render
1. Go to your Render Dashboard -> Select your `satay-ordering-system` Web Service.
2. Click **Environment** in the left sidebar.
3. Click **Add Environment Variable** and enter:
   - `DB_DRIVER`: `mysql`
   - `DB_HOST`: `your-mysql-host.aivencloud.com`
   - `DB_PORT`: `12345`
   - `DB_NAME`: `defaultdb`
   - `DB_USER`: `avnadmin`
   - `DB_PASS`: `your_password_here`
   *(Alternatively, you can provide a single `MYSQL_URL` string: `mysql://user:pass@host:port/dbname`)*
4. Click **Save Changes**. Render will automatically redeploy your application. Now all deleted/rejected customer orders, new orders, menu changes, and settings will persist forever!

---

## Part 2: Configure Online QR Code Table Ordering

Once your site is live online, configure your domain in the Admin Control Center:

1. Open your online admin portal:
   `https://satay-ordering-system.onrender.com/admin.php`
   *(Login with `admin` / `admin123`)*
2. Go to the **Settings** tab.
3. Under **Public Online Domain & QR Redirection**:
   - Click **🔍 Auto-Detect** or enter your live domain:
     `https://satay-ordering-system.onrender.com/`
   - Click **💾 Save System Settings**.
4. Go to the **Table QRs** tab:
   - All dining tables will now generate QR codes linking to your live domain:
     `https://satay-ordering-system.onrender.com/index.php?table=T01&token=TBL-XXXX`
5. Click **🖨️ Print QR Stand** to print table cards and place them on your dining tables.

### How it works for customers:
1. Customer sits at Table 01 and points their smartphone camera at the QR stand.
2. The phone opens the web ordering page directly.
3. Table 01 is automatically locked in with a verified badge.
4. Customer chooses satay, selects spiciness, and places their order instantly via Guest Mode or Account.

---

## Part 3: Staff & Admin Mobile App Setup

Staff (Kitchen KDS + Cashier) and Admins can access the system via two mobile methods:

### Method A: Progressive Web App (PWA) — Instant & No App Store Needed
1. On any Android phone, iPhone, or iPad, open:
   `https://satay-ordering-system.onrender.com/login.php`
2. Tap the browser menu:
   - **Chrome on Android**: Tap `⋮` -> **Add to Home screen** / **Install App**.
   - **Safari on iOS**: Tap `Share` -> **Add to Home Screen**.
3. An app icon **Sate Tulang** will appear on the device home screen.
4. It opens in full-screen standalone mode without URL address bars.

---

### Method B: Native Android APK (`android-app`)
A complete native Android Studio project is provided in the `android-app/android` directory.

#### Features included in the Android App:
- **Dedicated Role Bar**: 1-tap switching between **Kitchen KDS** (`kitchen.php`), **Admin Control** (`admin.php`), and **Cashier POS**.
- **Kitchen Screen Wake-Lock**: Automatically keeps the tablet display awake during service hours so the kitchen queue never dims.
- **Loud Audio Chime Support**: HTML5 audio and vibration alerts for new orders.
- **Server URL Switcher**: Tap the ⚙️ gear icon in the top right to switch between your online Render server and local Wi-Fi testing server.

#### How to build the APK:
1. Open **Android Studio**.
2. Click **Open** and select the folder:
   `c:\xampp\htdocs\Order_system_app\android-app\android`
3. Let Gradle sync dependencies.
4. Go to **Build** -> **Build Bundle(s) / APK(s)** -> **Build APK(s)**.
5. Transfer the generated `.apk` to your kitchen tablet or staff phone and install it.

---

## Default System Credentials

| Role | Username | Password | Target Interface |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | `admin.php` |
| **Kitchen Staff** | `staff` | `staff123` | `kitchen.php` |
| **Customer** | *(Guest or Register)* | *(Self Defined)* | `index.php` |

---

## Support & Troubleshooting

* **Q: The QR code scans to `localhost` instead of my public URL.**
  * *Fix*: Go to `admin.php` -> **Settings** -> Click **Auto-Detect** or enter your Render domain -> Click **Save System Settings**. Then re-print your table QR cards.
* **Q: Kitchen audio does not play on tablet.**
  * *Fix*: Modern browsers require 1 tap on the screen to initialize the Web Audio context. Tap anywhere on the kitchen screen once at the start of your shift.
