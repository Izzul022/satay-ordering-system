/**
 * Satay Ordering System - Customer Portal Logic
 */

if (typeof SatayApp !== 'undefined' && !SatayApp.escapeHtml) {
  SatayApp.escapeHtml = function(str) {
    if (str === null || str === undefined) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  };
}

const CustomerApp = {
 menuCategories: [],
 menuItems: [],
 activeCategory: 'all',
 searchQuery: '',
 diningType: 'dine_in', // dine_in, takeaway, delivery
 tableNumber: '',
 cart: [],
 activeTrackingOrderNumber: null,
 trackingInterval: null,
 currentUser: null,
 guestInfo: null,
 tables: [],
 taxRatePercent: 6.0,

 async init() {
 this.loadCartFromStorage();
 await this.checkAuthState();
 await this.fetchTables();
 this.checkUrlParams();
 this.fetchMenu();
 this.bindEvents();
 },

 async checkAuthState() {
 try {
 const res = await fetch('api/auth.php');
 const data = await res.json();
 if (data.success) {
 if (data.authenticated && data.user) {
 this.currentUser = data.user;
 this.guestInfo = null;
 } else if (data.is_guest && data.guest) {
 this.currentUser = null;
 this.guestInfo = data.guest;
 } else {
 this.currentUser = null;
 this.guestInfo = null;
 }
 this.renderNavAuthUI();
 }
 } catch (e) {
 console.error('Error checking auth state:', e);
 }
 },

  renderNavAuthUI() {
    const container = document.getElementById('customer-nav-auth-container');
    if (!container) return;

    if (this.currentUser) {
      container.innerHTML = `
        <div class="customer-profile-menu">
          <button class="profile-trigger-btn" onclick="CustomerApp.toggleProfileDropdown(event)">
            <span id="nav-user-name">${SatayApp.escapeHtml(this.currentUser.full_name)}</span>
            <span style="font-size:0.7rem;">&#9662;</span>
          </button>
          <div class="profile-dropdown-menu" id="profile-dropdown-menu">
            ${this.currentUser.role === 'customer' ? `
              <button class="profile-dropdown-item" onclick="CustomerApp.openProfileModal()">
                My Saved Profile
              </button>
              <button class="profile-dropdown-item" onclick="CustomerApp.showOrderHistoryModal()">
                My Past Orders
              </button>
              <div class="profile-dropdown-divider"></div>
            ` : (this.currentUser.role === 'staff' ? `
              <a href="kitchen.php" class="profile-dropdown-item">
                Open Kitchen Display
              </a>
              <div class="profile-dropdown-divider"></div>
            ` : `
              <a href="admin.php" class="profile-dropdown-item">
                Admin Control Center
              </a>
              <a href="kitchen.php" class="profile-dropdown-item">
                Kitchen Display (KDS)
              </a>
              <div class="profile-dropdown-divider"></div>
            `)}
            <a href="logout.php?redirect=login.php" class="profile-dropdown-item" style="color:var(--danger);">
              Sign Out
            </a>
          </div>
        </div>
      `;
    } else if (this.guestInfo) {
      container.innerHTML = `
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <a href="login.php?tab=login" class="btn btn-primary btn-sm" style="font-size:0.75rem; padding:0.35rem 0.75rem;">
            Sign In / Register
          </a>
          <a href="logout.php?redirect=login.php" class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:0.35rem 0.6rem; color:var(--danger);" title="Exit Guest Mode">
            Exit
          </a>
        </div>
      `;
    } else {
      container.innerHTML = `
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <button type="button" class="btn btn-primary btn-sm" onclick="CustomerApp.openAuthModal('register')" style="font-size:0.78rem; padding:0.4rem 0.85rem; font-weight:600;">
            ✨ Register / Sign In
          </button>
        </div>
      `;
    }
  },

  toggleProfileDropdown(e) {
    if (e) e.stopPropagation();
    const menu = document.getElementById('profile-dropdown-menu');
    if (menu) {
      menu.classList.toggle('show');
    }
  },

  openProfileModal() {
    if (!this.currentUser) return;
    const nameInput = document.getElementById('prof-fullname');
    const phoneInput = document.getElementById('prof-phone');
    const emailInput = document.getElementById('prof-email');
    const addrInput = document.getElementById('prof-address');

    if (nameInput) nameInput.value = this.currentUser.full_name || '';
    if (phoneInput) phoneInput.value = this.currentUser.phone || '';
    if (emailInput) emailInput.value = this.currentUser.email || '';
    if (addrInput) addrInput.value = this.currentUser.address || '';

    const drop = document.getElementById('profile-dropdown-menu');
    if (drop) drop.classList.remove('show');

    SatayApp.openModal('profile-modal');
  },

  openAuthModal(defaultTab = 'register') {
    const tabMap = {
      'login': 'auth-login',
      'register': 'auth-register',
      'guest': 'auth-guest'
    };
    const tabId = tabMap[defaultTab] || defaultTab;
    this.switchAuthTab(tabId);
    SatayApp.openModal('customer-auth-modal');
  },

  switchAuthTab(tabId) {
    document.querySelectorAll('#customer-auth-modal .auth-tab-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.tab === tabId);
    });
    document.querySelectorAll('#customer-auth-modal .auth-tab-pane').forEach(p => {
      p.classList.toggle('active', p.id === tabId);
    });
  },

  toggleInputPassword(inputId, btn) {
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

  async fetchTables() {
    try {
      const res = await fetch('api/tables.php');
      const data = await res.json();
      if (data.success) {
        this.tables = data.tables || [];
        this.populateTableDropdown();
      }
    } catch (e) {
      console.error('Error loading tables:', e);
    }
  },

  populateTableDropdown() {
    const tableSelect = document.getElementById('checkout-table');
    if (!tableSelect) return;

    if (!this.tables || this.tables.length === 0) {
      tableSelect.innerHTML = `<option value="">-- No Tables Available --</option>`;
      return;
    }

    let html = `<option value="">-- Select Dining Table --</option>`;
    this.tables.forEach(tbl => {
      const isSelected = (this.tableNumber && (this.tableNumber.toUpperCase() === tbl.table_number.toUpperCase())) ? 'selected' : '';
      html += `<option value="${SatayApp.escapeHtml(tbl.table_number)}" ${isSelected}>${SatayApp.escapeHtml(tbl.table_number)} (${tbl.capacity} Pax Seats)</option>`;
    });
    tableSelect.innerHTML = html;

    if (this.tableNumber) {
      tableSelect.value = this.tableNumber;
    }
  },

  checkUrlParams() {
    const urlParams = new URLSearchParams(window.location.search);
    const table = urlParams.get('table');
    const token = urlParams.get('token');
    const track = urlParams.get('track');

    if (table) {
      this.tableNumber = table.trim();
      this.diningType = 'dine_in';
      this.populateTableDropdown();
      this.updateDiningModeUI();
      setTimeout(() => {
        SatayApp.showToast(`🍽️ Welcome! Ordering for Table ${this.tableNumber}`, 'success');
      }, 400);
    }

    if (track) {
      this.startLiveTracking(track);
    }
  },

 bindEvents() {
 // Close profile dropdown on outside click
 document.addEventListener('click', () => {
 const menu = document.getElementById('profile-dropdown-menu');
 if (menu) menu.classList.remove('show');
 });

 // Dining mode selector buttons
 document.querySelectorAll('.mode-btn').forEach(btn => {
 btn.addEventListener('click', () => {
 const mode = btn.dataset.mode;
 this.setDiningMode(mode);
 });
 });

 // Search bar
 const searchInput = document.getElementById('search-menu');
 if (searchInput) {
 searchInput.addEventListener('input', (e) => {
 this.searchQuery = e.target.value.toLowerCase();
 this.renderMenuGrid();
 });
 }

 // Cart toggle button
 const cartBtn = document.getElementById('floating-cart-btn');
 if (cartBtn) {
 cartBtn.addEventListener('click', () => this.openCartDrawer());
 }

 const closeCartBtn = document.getElementById('close-cart-btn');
 if (closeCartBtn) {
 closeCartBtn.addEventListener('click', () => this.closeCartDrawer());
 }

 // Checkout form submission
 const checkoutForm = document.getElementById('checkout-form');
 if (checkoutForm) {
 checkoutForm.addEventListener('submit', (e) => {
 e.preventDefault();
 this.processCheckout();
 });
 }

 // Customer Login Form
 const loginForm = document.getElementById('customer-login-form');
 if (loginForm) {
 loginForm.addEventListener('submit', async (e) => {
 e.preventDefault();
 const username = document.getElementById('cust-login-user')?.value.trim();
 const password = document.getElementById('cust-login-pass')?.value.trim();
 const errDiv = document.getElementById('cust-login-error');
 const btn = document.getElementById('btn-cust-login');

 if (errDiv) errDiv.style.display = 'none';
 if (btn) { btn.disabled = true; btn.innerText = 'Signing In...'; }

 try {
 const res = await fetch('api/auth.php', {
 method: 'POST',
 headers: { 'Content-Type': 'application/json' },
 body: JSON.stringify({ username, password })
 });
 const data = await res.json();
 if (data.success) {
 this.currentUser = data.user;
 this.guestInfo = null;
 SatayApp.closeModal('customer-auth-modal');
 SatayApp.showToast(`Welcome back, ${data.user.full_name}!`, 'success');
 this.renderNavAuthUI();
 } else {
 if (errDiv) {
 errDiv.innerText = data.message || 'Login failed';
 errDiv.style.display = 'block';
 }
 }
 } catch (err) {
 if (errDiv) {
 errDiv.innerText = 'Network error during sign in';
 errDiv.style.display = 'block';
 }
 } finally {
 if (btn) { btn.disabled = false; btn.innerText = 'Sign In to Account '; }
 }
 });
 }

 // Customer Register Form
 const regForm = document.getElementById('customer-register-form');
 if (regForm) {
 regForm.addEventListener('submit', async (e) => {
 e.preventDefault();
 const full_name = document.getElementById('reg-fullname')?.value.trim();
 const username = document.getElementById('reg-username')?.value.trim();
 const phone = document.getElementById('reg-phone')?.value.trim();
 const email = document.getElementById('reg-email')?.value.trim();
 const address = document.getElementById('reg-address')?.value.trim();
 const password = document.getElementById('reg-password')?.value.trim();
 const errDiv = document.getElementById('cust-reg-error');
 const btn = document.getElementById('btn-cust-register');

 if (errDiv) errDiv.style.display = 'none';
 if (btn) { btn.disabled = true; btn.innerText = 'Creating Account...'; }

 try {
 const res = await fetch('api/auth.php?action=register', {
 method: 'POST',
 headers: { 'Content-Type': 'application/json' },
 body: JSON.stringify({ full_name, username, phone, email, address, password })
 });
 const data = await res.json();
 if (data.success) {
 this.currentUser = data.user;
 this.guestInfo = null;
 SatayApp.closeModal('customer-auth-modal');
 SatayApp.showToast(`Welcome to Sate Tulang Madu, ${data.user.full_name}!`, 'success');
 this.renderNavAuthUI();
 } else {
 if (errDiv) {
 errDiv.innerText = data.message || 'Registration failed';
 errDiv.style.display = 'block';
 }
 }
 } catch (err) {
 if (errDiv) {
 errDiv.innerText = 'Network error during registration';
 errDiv.style.display = 'block';
 }
 } finally {
 if (btn) { btn.disabled = false; btn.innerText = 'Create Account & Start Ordering '; }
 }
 });
 }

 // Customer Guest Form
 const guestForm = document.getElementById('customer-guest-form');
 if (guestForm) {
 guestForm.addEventListener('submit', async (e) => {
 e.preventDefault();
 const name = document.getElementById('guest-nickname')?.value.trim();
 const phone = document.getElementById('guest-phone')?.value.trim();

 try {
 const res = await fetch('api/auth.php?action=guest', {
 method: 'POST',
 headers: { 'Content-Type': 'application/json' },
 body: JSON.stringify({ name, phone })
 });
 const data = await res.json();
 if (data.success) {
 this.currentUser = null;
 this.guestInfo = data.guest;
 SatayApp.closeModal('customer-auth-modal');
 SatayApp.showToast(`Ordering as Guest: ${data.guest.name}`, 'success');
 this.renderNavAuthUI();
 }
 } catch (err) {
 console.error(err);
 }
 });
 }

 // Profile Update Form
 const profileForm = document.getElementById('customer-profile-form');
 if (profileForm) {
 profileForm.addEventListener('submit', async (e) => {
 e.preventDefault();
 const full_name = document.getElementById('prof-fullname')?.value.trim();
 const phone = document.getElementById('prof-phone')?.value.trim();
 const email = document.getElementById('prof-email')?.value.trim();
 const address = document.getElementById('prof-address')?.value.trim();

 try {
 const res = await fetch('api/auth.php?action=profile_update', {
 method: 'POST',
 headers: { 'Content-Type': 'application/json' },
 body: JSON.stringify({ full_name, phone, email, address })
 });
 const data = await res.json();
 if (data.success) {
 this.currentUser = data.user;
 SatayApp.closeModal('profile-modal');
 SatayApp.showToast('Profile updated successfully!', 'success');
 this.renderNavAuthUI();
 } else {
 SatayApp.showToast(data.message || 'Failed to update profile', 'danger');
 }
 } catch (err) {
 SatayApp.showToast('Network error saving profile', 'danger');
 }
 });
 }
 },

 setDiningMode(mode) {
 this.diningType = mode;
 this.updateDiningModeUI();
 this.updateCartSummary();
 },

 updateDiningModeUI() {
 document.querySelectorAll('.mode-btn').forEach(btn => {
 btn.classList.toggle('active', btn.dataset.mode === this.diningType);
 });

 const dineInFields = document.getElementById('dine-in-fields');
 const deliveryFields = document.getElementById('delivery-fields');

 if (dineInFields) dineInFields.style.display = (this.diningType === 'dine_in') ? 'block' : 'none';
 if (deliveryFields) deliveryFields.style.display = (this.diningType === 'delivery') ? 'block' : 'none';
 },

  async fetchMenu() {
    try {
      const res = await fetch('api/menu.php');
      const data = await res.json();
      if (data.success) {
        this.menuCategories = data.categories || [];
        this.menuItems = data.items || [];
        if (data.tax_rate_percent !== undefined) {
          this.taxRatePercent = parseFloat(data.tax_rate_percent);
        }
        this.renderCategoryNav();
        this.renderMenuGrid();
        this.updateCartSummary();
      }
    } catch (err) {
      SatayApp.showToast('Failed to load menu items', 'danger');
      console.error(err);
    }
  },

 renderCategoryNav() {
 const nav = document.getElementById('category-nav');
 if (!nav) return;

 let html = `
 <button class="cat-pill ${this.activeCategory === 'all' ? 'active' : ''}" onclick="CustomerApp.filterCategory('all')">
 All Items
 </button>
 `;

 this.menuCategories.forEach(cat => {
 html += `
 <button class="cat-pill ${this.activeCategory == cat.id ? 'active' : ''}" onclick="CustomerApp.filterCategory(${cat.id})">
 <span>${cat.icon || ''}</span> ${cat.name}
 </button>
 `;
 });

 nav.innerHTML = html;
 },

 filterCategory(catId) {
 this.activeCategory = catId;
 this.renderCategoryNav();
 this.renderMenuGrid();
 },

 renderMenuGrid() {
 const grid = document.getElementById('menu-grid');
 if (!grid) return;

 let filtered = this.menuItems;

 if (this.activeCategory !== 'all') {
 filtered = filtered.filter(it => it.category_id == this.activeCategory);
 }

 if (this.searchQuery) {
 filtered = filtered.filter(it => 
 it.name.toLowerCase().includes(this.searchQuery) ||
 (it.description && it.description.toLowerCase().includes(this.searchQuery))
 );
 }

    if (filtered.length === 0) {
      if (this.menuItems.length === 0) {
        grid.innerHTML = `
          <div style="grid-column: 1/-1; text-align:center; padding: 4rem 1.5rem; background: var(--bg-surface); border-radius: var(--radius-lg); border: 1px dashed var(--border-color); color: var(--text-muted); max-width: 600px; margin: 1.5rem auto;">
            <div style="font-size:3.5rem; margin-bottom:1rem;">🍢</div>
            <h3 style="color:var(--text-main); margin-bottom:0.5rem; font-size:1.35rem;">Menu is Being Prepared</h3>
            <p style="font-size:0.92rem; line-height:1.6; margin-bottom:0;">Our kitchen is currently setting up fresh offerings. Please check back shortly or place an inquiry with our counter!</p>
          </div>
        `;
      } else {
        grid.innerHTML = `
          <div style="grid-column: 1/-1; text-align:center; padding: 3.5rem 1rem; color: var(--text-muted);">
            <div style="font-size:3rem; margin-bottom:1rem;">🔍</div>
            <h3 style="color:var(--text-main); margin-bottom:0.4rem;">No matching items found</h3>
            <p style="font-size:0.9rem;">Try clearing your search query or selecting another category.</p>
          </div>
        `;
      }
      return;
    }

    grid.innerHTML = filtered.map(item => {
      const minQty = parseInt(item.min_quantity) || 1;
      const stock = parseInt(item.stock_quantity !== null && item.stock_quantity !== undefined ? item.stock_quantity : 100);
      const isAvailable = (parseInt(item.is_available) === 1 && stock > 0);

      return `
        <div class="dish-card ${!isAvailable ? 'sold-out' : ''}">
          <div class="dish-img-wrap">
            <img src="${item.image_url}" alt="${item.name}" class="dish-img" onerror="this.src='assets/images/sate_ayam.png'">
            ${item.is_popular == 1 ? `<div class="dish-badge-pop badge badge-primary"> Popular</div>` : ''}
            ${isAvailable && stock <= 15 ? `<div class="badge badge-gold" style="position:absolute; top:8px; left:8px; font-size:0.72rem; z-index:2; padding:0.2rem 0.5rem;">Only ${stock} left</div>` : ''}
            <div class="dish-badge-unit">${item.unit_name ? `per ${item.unit_name}` : ''} ${minQty > 1 ? `(Min ${minQty})` : ''}</div>
          </div>
          <div class="dish-body">
            <h3 class="dish-title">${item.name}</h3>
            <p class="dish-desc">${item.description || ''}</p>
            <div class="dish-footer">
              <div class="dish-price">
                ${SatayApp.formatPrice(item.price_per_unit)}
              </div>
              <div>
                ${isAvailable ? `
                  <button class="btn btn-primary btn-sm" onclick="CustomerApp.openCustomizer(${item.id})">
                    <span>+</span> Order
                  </button>
                ` : `
                  <span class="badge badge-neutral">Sold Out</span>
                `}
              </div>
            </div>
          </div>
        </div>
      `;
    }).join('');
  },

  // Open Customizer Modal for Satay Skewers / Platters
  openCustomizer(itemId) {
    const item = this.menuItems.find(i => i.id == itemId);
    if (!item) return;

    const stock = parseInt(item.stock_quantity !== null && item.stock_quantity !== undefined ? item.stock_quantity : 100);
    if (item.is_available == 0 || stock <= 0) {
      SatayApp.showToast('Sorry, this item is currently Sold Out.', 'warning');
      return;
    }

    const minQty = parseInt(item.min_quantity) || 1;
    const isSkewer = item.unit_name === 'cucuk' || item.stick_meat_type;

    const content = document.getElementById('customizer-modal-content');
    if (!content) return;

    content.innerHTML = `
      <div style="display:flex; gap:1.25rem; margin-bottom:1.5rem; align-items:center;">
        <img src="${item.image_url}" style="width:90px; height:80px; border-radius:var(--radius-md); object-fit:cover; border:1px solid var(--border-color);" onerror="this.src='assets/images/sate_ayam.png'">
        <div>
          <h3 style="font-size:1.2rem;">${item.name}</h3>
          <div style="color:var(--gold); font-weight:700; font-size:1.1rem;">${SatayApp.formatPrice(item.price_per_unit)} <span style="font-size:0.8rem; color:var(--text-muted);">/ ${item.unit_name}</span></div>
          ${minQty > 1 ? `<div style="font-size:0.8rem; color:var(--primary); font-weight:600; margin-top:2px;"> Minimum order: ${minQty} sticks</div>` : ''}
          ${stock <= 20 ? `<div style="font-size:0.8rem; color:var(--danger); font-weight:700; margin-top:3px;">🔥 Only ${stock} left in stock!</div>` : `<div style="font-size:0.75rem; color:var(--text-muted); margin-top:3px;">Quantity left: ${stock}</div>`}
        </div>
      </div>

      <!-- Stick Multiplier Quick Buttons -->
      ${isSkewer ? `
        <div class="form-group">
          <label class="form-label">Quick Stick Quantity:</label>
          <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            ${[minQty, 10, 20, 30, 50].filter((v, i, a) => a.indexOf(v) === i && v <= stock).map(v => `
              <button type="button" class="btn btn-secondary btn-sm" onclick="CustomerApp.setCustomizerQty(${v})">${v} Sticks</button>
            `).join('')}
          </div>
        </div>
      ` : ''}

      <!-- Quantity Stepper -->
      <div class="form-group" style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card); padding:0.75rem 1rem; border-radius:var(--radius-md); border:1px solid var(--border-color);">
        <div>
          <div style="font-weight:700; font-size:0.95rem;">Select Quantity</div>
          <div style="font-size:0.78rem; color:var(--text-muted);">${item.unit_name || 'units'} (Max: ${stock})</div>
        </div>
        <div class="qty-stepper">
          <button type="button" class="qty-btn" onclick="CustomerApp.adjustCustomizerQty(-${isSkewer && minQty > 1 ? 5 : 1})">-</button>
          <div class="qty-val" id="customizer-qty-display">${minQty}</div>
          <button type="button" class="qty-btn" onclick="CustomerApp.adjustCustomizerQty(${isSkewer && minQty > 1 ? 5 : 1})">+</button>
        </div>
      </div>

      <!-- Condiments & Addons -->
      <div class="form-group">
        <label class="form-label">Condiments / Notes:</label>
        <textarea class="form-textarea" id="customizer-notes" rows="2" placeholder="e.g. Lebih kuah kacang, bawang asing, bakar garing..."></textarea>
      </div>

      <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.5rem; padding-top:1rem; border-top:1px solid var(--border-color);">
        <div>
          <div style="font-size:0.78rem; color:var(--text-muted);">Total for item:</div>
          <div id="customizer-total-price" style="font-size:1.35rem; font-weight:800; color:var(--gold);">
            ${SatayApp.formatPrice(item.price_per_unit * minQty)}
          </div>
        </div>
        <button type="button" class="btn btn-primary" onclick="CustomerApp.confirmAddToCart(${item.id})">
          Add to Cart 
        </button>
      </div>
    `;

    this.currentCustomizingItem = item;
    this.currentCustomizingQty = Math.min(minQty, stock);
    SatayApp.openModal('customizer-modal');
  },

  setCustomizerQty(val) {
    const item = this.currentCustomizingItem;
    if (!item) return;
    const minQty = parseInt(item.min_quantity) || 1;
    const stock = parseInt(item.stock_quantity !== null && item.stock_quantity !== undefined ? item.stock_quantity : 100);
    this.currentCustomizingQty = Math.max(minQty, Math.min(stock, val));
    if (val > stock) {
      SatayApp.showToast(`Only ${stock} left in stock!`, 'warning');
    }
    this.updateCustomizerDisplay();
  },

  adjustCustomizerQty(delta) {
    const item = this.currentCustomizingItem;
    if (!item) return;
    const minQty = parseInt(item.min_quantity) || 1;
    const stock = parseInt(item.stock_quantity !== null && item.stock_quantity !== undefined ? item.stock_quantity : 100);
    const newQty = this.currentCustomizingQty + delta;
    if (newQty > stock) {
      this.currentCustomizingQty = stock;
      SatayApp.showToast(`Only ${stock} left in stock!`, 'warning');
    } else {
      this.currentCustomizingQty = Math.max(minQty, newQty);
    }
    this.updateCustomizerDisplay();
  },

  updateCustomizerDisplay() {
    const display = document.getElementById('customizer-qty-display');
    const priceDisplay = document.getElementById('customizer-total-price');
    if (display && this.currentCustomizingItem) {
      display.innerText = this.currentCustomizingQty;
      const total = this.currentCustomizingQty * parseFloat(this.currentCustomizingItem.price_per_unit);
      if (priceDisplay) priceDisplay.innerText = SatayApp.formatPrice(total);
    }
  },

  confirmAddToCart(itemId) {
    const item = this.menuItems.find(i => i.id == itemId);
    if (!item) return;

    const stock = parseInt(item.stock_quantity !== null && item.stock_quantity !== undefined ? item.stock_quantity : 100);
    const qty = this.currentCustomizingQty;

    if (qty > stock) {
      SatayApp.showToast(`Cannot add ${qty}. Only ${stock} left in stock!`, 'danger');
      return;
    }

    const notes = document.getElementById('customizer-notes')?.value || '';

    // Add to cart
    this.cart.push({
      cart_id: Date.now() + Math.random().toString(36).substr(2, 4),
      menu_item_id: item.id,
      name: item.name,
      meat_type: item.stick_meat_type,
      unit_price: parseFloat(item.price_per_unit),
      quantity: qty,
      special_notes: notes,
      unit_name: item.unit_name
    });

    this.saveCartToStorage();
    this.updateCartSummary();
    SatayApp.closeModal('customizer-modal');
    SatayApp.showToast(`Added ${qty}x ${item.name} to cart!`, 'success');
  },

 removeFromCart(cartId) {
 this.cart = this.cart.filter(item => item.cart_id !== cartId);
 this.saveCartToStorage();
 this.updateCartSummary();
 this.renderCartDrawerItems();
 },

  adjustCartItemQty(cartId, delta) {
    const item = this.cart.find(i => i.cart_id === cartId);
    if (!item) return;

    if (delta > 0) {
      const menuItem = this.menuItems.find(m => m.id == item.menu_item_id);
      const stock = menuItem ? parseInt(menuItem.stock_quantity !== null && menuItem.stock_quantity !== undefined ? menuItem.stock_quantity : 100) : 100;
      if (item.quantity + delta > stock) {
        SatayApp.showToast(`Cannot add more. Only ${stock} left in stock!`, 'warning');
        return;
      }
    }

    item.quantity += delta;
    if (item.quantity <= 0) {
      this.removeFromCart(cartId);
    } else {
      this.saveCartToStorage();
      this.updateCartSummary();
      this.renderCartDrawerItems();
    }
  },

 saveCartToStorage() {
 localStorage.setItem('satay_cart', JSON.stringify(this.cart));
 },

 loadCartFromStorage() {
 try {
 const saved = localStorage.getItem('satay_cart');
 if (saved) this.cart = JSON.parse(saved);
 } catch (e) {
 this.cart = [];
 }
 this.updateCartSummary();
 },

 openCartDrawer() {
 this.renderCartDrawerItems();
 const drawer = document.getElementById('cart-drawer');
 const backdrop = document.getElementById('cart-backdrop');
 if (drawer) drawer.classList.add('open');
 if (backdrop) backdrop.classList.add('open');
 },

 closeCartDrawer() {
 const drawer = document.getElementById('cart-drawer');
 const backdrop = document.getElementById('cart-backdrop');
 if (drawer) drawer.classList.remove('open');
 if (backdrop) backdrop.classList.remove('open');
 },

 renderCartDrawerItems() {
    const list = document.getElementById('cart-items-list');
    if (!list) return;

    if (this.cart.length === 0) {
      list.innerHTML = `
        <div style="text-align:center; padding:3rem 1rem; color:var(--text-muted);">
          <div style="font-size:2.5rem; margin-bottom:0.75rem;"></div>
          <h4>Your cart is empty</h4>
          <p style="font-size:0.85rem;">Select your favorite satay skewers to start ordering!</p>
        </div>
      `;
      return;
    }

    list.innerHTML = this.cart.map(it => `
      <div class="cart-item">
        <div class="cart-item-info" style="flex-grow:1;">
          <h4>${it.name}</h4>
          <div class="cart-item-meta">
            ${SatayApp.formatPrice(it.unit_price)} each
          </div>
          ${it.special_notes ? `<div style="font-size:0.75rem; color:var(--gold); margin-top:2px;"> ${it.special_notes}</div>` : ''}
          <div style="font-weight:700; color:var(--gold); margin-top:4px;">
            ${SatayApp.formatPrice(it.unit_price * it.quantity)}
          </div>
        </div>
        <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px;">
          <div class="qty-stepper">
            <button type="button" class="qty-btn" onclick="CustomerApp.adjustCartItemQty('${it.cart_id}', -1)">-</button>
            <div class="qty-val">${it.quantity}</div>
            <button type="button" class="qty-btn" onclick="CustomerApp.adjustCartItemQty('${it.cart_id}', 1)">+</button>
          </div>
          <button class="btn btn-sm btn-secondary" style="font-size:0.75rem; padding:2px 6px;" onclick="CustomerApp.removeFromCart('${it.cart_id}')">Remove</button>
        </div>
      </div>
    `).join('');
  },

  updateCartSummary() {
    let subtotal = 0;
    let stickCount = 0;

    this.cart.forEach(it => {
      subtotal += (it.unit_price * it.quantity);
      if (it.meat_type) {
        if (it.meat_type.includes('combo_30')) stickCount += (30 * it.quantity);
        else if (it.meat_type.includes('combo_50')) stickCount += (50 * it.quantity);
        else if (it.meat_type.includes('combo_15')) stickCount += (15 * it.quantity);
        else stickCount += it.quantity;
      }
    });

    const taxPercent = (this.taxRatePercent !== undefined && !isNaN(this.taxRatePercent)) ? this.taxRatePercent : 6.0;
    const taxRate = taxPercent / 100;
    const tax = subtotal * taxRate;
    const deliveryFee = (this.diningType === 'delivery' && subtotal > 0) ? 5.00 : 0.00;
    const total = subtotal + tax + deliveryFee;

    // Update floating bubble
    const bubble = document.getElementById('cart-bubble-count');
    const floatPrice = document.getElementById('floating-cart-total');
    if (bubble) bubble.innerText = this.cart.length;
    if (floatPrice) floatPrice.innerText = SatayApp.formatPrice(total);

    // Update drawer summary
    const subtotalEl = document.getElementById('cart-subtotal');
    const taxEl = document.getElementById('cart-tax');
    const taxLabelEl = document.getElementById('cart-tax-label');
    const deliveryEl = document.getElementById('cart-delivery-fee');
    const totalEl = document.getElementById('cart-total');
    const stickEl = document.getElementById('cart-stick-count');

    if (subtotalEl) subtotalEl.innerText = SatayApp.formatPrice(subtotal);
    if (taxLabelEl) taxLabelEl.innerText = `SST (${taxPercent}%):`;
    if (taxEl) taxEl.innerText = SatayApp.formatPrice(tax);
    if (deliveryEl) deliveryEl.innerText = SatayApp.formatPrice(deliveryFee);
    if (totalEl) totalEl.innerText = SatayApp.formatPrice(total);
    if (stickEl) stickEl.innerText = `${stickCount} Sticks`;
  },

  proceedToCheckout() {
    if (this.cart.length === 0) {
      SatayApp.showToast('Your cart is empty! Please add some satay first.', 'warning');
      return;
    }

    // Auto pre-populate checkout fields from current session
    const nameInput = document.getElementById('checkout-name');
    const phoneInput = document.getElementById('checkout-phone');
    const addressInput = document.getElementById('checkout-address');

    if (this.currentUser) {
      if (nameInput && !nameInput.value) nameInput.value = this.currentUser.full_name || '';
      if (phoneInput && !phoneInput.value) phoneInput.value = this.currentUser.phone || '';
      if (addressInput && !addressInput.value) addressInput.value = this.currentUser.address || '';
    } else if (this.guestInfo) {
      if (nameInput && !nameInput.value) nameInput.value = this.guestInfo.name || (this.tableNumber ? `Guest Table ${this.tableNumber}` : 'Guest Customer');
      if (phoneInput && !phoneInput.value) phoneInput.value = this.guestInfo.phone || '';
    }

    this.populateTableDropdown();
    this.closeCartDrawer();
    this.updateDiningModeUI();
    SatayApp.openModal('checkout-modal');
  },

  async processCheckout() {
    const name = document.getElementById('checkout-name')?.value.trim() || (this.currentUser ? this.currentUser.full_name : (this.tableNumber ? `Guest Table ${this.tableNumber}` : 'Guest'));
    const phone = document.getElementById('checkout-phone')?.value.trim() || '';
    const table = document.getElementById('checkout-table')?.value.trim() || this.tableNumber;
    const address = document.getElementById('checkout-address')?.value.trim() || '';
    const notes = document.getElementById('checkout-order-notes')?.value.trim() || '';
    const payment = document.getElementById('checkout-payment')?.value || 'cash';

    if (this.diningType === 'dine_in' && !table) {
      SatayApp.showToast('Please choose your Dining Table from the dropdown.', 'danger');
      return;
    }

    if (this.diningType === 'delivery' && !address) {
      SatayApp.showToast('Please enter your delivery address.', 'danger');
      return;
    }

    const payload = {
      customer_name: name,
      customer_phone: phone,
      dining_type: this.diningType,
      table_number: table,
      delivery_address: address,
      notes: notes,
      payment_method: payment,
      items: this.cart.map(it => ({
        menu_item_id: it.menu_item_id,
        item_name: it.name,
        unit_price: it.unit_price,
        quantity: it.quantity,
        spicy_level: 'normal',
        special_notes: it.special_notes
      }))
    };

    const submitBtn = document.getElementById('btn-submit-order');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerText = 'Submitting Order...';
    }

    try {
      const res = await fetch('api/orders.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      const data = await res.json();
      if (data.success) {
        // Clear cart
        this.cart = [];
        this.saveCartToStorage();
        this.updateCartSummary();

        // Save to customer local history
        this.saveOrderToHistory(data.order_number);

        SatayApp.closeModal('checkout-modal');
        SatayApp.showToast('🎉 Order placed successfully!', 'success');
        SatayApp.playChime('new_order');

        // Switch to live tracking
        this.startLiveTracking(data.order_number);
      } else {
        SatayApp.showToast(data.message || 'Order failed', 'danger');
      }
    } catch (err) {
      console.error(err);
      SatayApp.showToast('Network error submitting order', 'danger');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Confirm & Place Order';
      }
    }
  },

 saveOrderToHistory(orderNumber) {
 try {
 let history = JSON.parse(localStorage.getItem('satay_history') || '[]');
 if (!history.includes(orderNumber)) {
 history.unshift(orderNumber);
 localStorage.setItem('satay_history', JSON.stringify(history.slice(0, 15)));
 }
 } catch (e) {}
 },

 // Live Order Tracking
 async startLiveTracking(orderNumber) {
 this.activeTrackingOrderNumber = orderNumber;
 const trackerModal = document.getElementById('tracker-modal');
 if (trackerModal) SatayApp.openModal('tracker-modal');

 await this.pollOrderStatus();

 if (this.trackingInterval) clearInterval(this.trackingInterval);
 this.trackingInterval = setInterval(() => this.pollOrderStatus(), 4000);
 },

 stopLiveTracking() {
 if (this.trackingInterval) clearInterval(this.trackingInterval);
 this.activeTrackingOrderNumber = null;
 SatayApp.closeModal('tracker-modal');
 },

 async pollOrderStatus() {
 if (!this.activeTrackingOrderNumber) return;

 try {
 const res = await fetch(`api/orders.php?order_number=${this.activeTrackingOrderNumber}`);
 const data = await res.json();

 if (data.success && data.order) {
 this.renderTrackerUI(data.order);
 }
 } catch (err) {
 console.error('Tracking poll error:', err);
 }
 },

 renderTrackerUI(order) {
    const trackerContainer = document.getElementById('tracker-details-container');
    if (!trackerContainer) return;

    const stages = ['pending', 'confirmed', 'grilling', 'ready', 'completed'];
    const stageLabels = {
      pending: '1. Order Sent',
      confirmed: '2. Kitchen Accepted',
      grilling: '3. Charcoal Grilling',
      ready: '4. Ready for You',
      completed: '5. Completed'
    };

    const stageHeroMeta = {
      pending: {
        icon: '📋',
        class: 'status-anim-pending',
        title: 'Order Queued & Sent!',
        subtitle: 'Your ticket has reached the cashier & kitchen display. Awaiting chef acceptance.'
      },
      confirmed: {
        icon: '👨‍🍳',
        class: 'status-anim-confirmed',
        title: 'Kitchen Accepted Order!',
        subtitle: 'Chef has accepted your order! Preparing fresh skewers & marinades.'
      },
      grilling: {
        icon: '🔥',
        class: 'status-anim-grilling',
        title: 'Sizzling on Charcoal Grill!',
        subtitle: 'Skewers are grilling over red-hot charcoal with sweet honey glaze.'
      },
      ready: {
        icon: '🛎️',
        class: 'status-anim-ready',
        title: 'Ready For Pickup / Serving!',
        subtitle: 'Hot and freshly plated! Please collect from the counter or wait at your table.'
      },
      completed: {
        icon: '🎉',
        class: 'status-anim-completed',
        title: 'Order Completed!',
        subtitle: 'Thank you for dining with Sate Tulang Madu! Enjoy your meal.'
      }
    };

    const currentIdx = Math.max(0, stages.indexOf(order.order_status));
    const heroInfo = stageHeroMeta[order.order_status] || stageHeroMeta.pending;

    // Timeline percentage calculation (0%, 25%, 50%, 75%, 100%)
    const progressWidth = Math.round((currentIdx / (stages.length - 1)) * 100);

    const stepIcons = ['📋', '👨‍🍳', '🔥', '🛎️', '✅'];

    const isOrderCompleted = order.order_status === 'completed';

    const timelineHtml = stages.map((stg, idx) => {
      const isCompleted = isOrderCompleted ? true : (idx < currentIdx);
      const isActive = isOrderCompleted ? false : (idx === currentIdx);
      return `
        <div class="tracker-step ${isActive ? 'active' : ''} ${isCompleted ? 'completed' : ''}">
          <div class="step-node">
            ${isCompleted ? `
              <div class="step-check-icon">
                <svg class="step-check-svg" viewBox="0 0 24 24">
                  <path class="check-path" fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" d="M4 12.5L9.5 18L20 6" />
                </svg>
              </div>
            ` : (isActive ? `<span class="step-active-icon">${stepIcons[idx]}</span>` : `<span class="step-num">${idx + 1}</span>`)}
          </div>
          <div class="step-label">${stageLabels[stg]}</div>
        </div>
      `;
    }).join('');

    const itemsHtml = (order.items || []).map(it => `
      <div style="display:flex; justify-content:space-between; padding:0.4rem 0; border-bottom:1px solid var(--border-color); font-size:0.88rem;">
        <div>
          <strong>${it.quantity}x</strong> ${SatayApp.escapeHtml(it.item_name)}
        </div>
        <div style="font-weight:700; color:var(--gold);">${SatayApp.formatPrice(it.total_price)}</div>
      </div>
    `).join('');

    // Table display formatting (avoid "Table Table 2")
    let tableInfo = '';
    if (order.table_number) {
      const rawTable = SatayApp.escapeHtml(order.table_number);
      tableInfo = ` • ${rawTable.toLowerCase().startsWith('table') ? rawTable : `Table ${rawTable}`}`;
    }

    trackerContainer.innerHTML = `
      <!-- Hero Status Header with Animations -->
      <div class="tracker-hero-box">
        <div style="display:flex; justify-content:center; align-items:center; gap:0.5rem; margin-bottom:0.6rem; flex-wrap:wrap;">
          <div class="badge badge-gold" style="font-size:0.75rem; padding:3px 10px; margin-bottom:0.75rem;">
            ORDER #${SatayApp.escapeHtml(order.order_number)}
          </div>
        </div>

        <div class="tracker-hero-icon-wrap ${heroInfo.class}">
          <span class="hero-anim-icon">${heroInfo.icon}</span>
        </div>

        <h2 class="tracker-hero-title">${heroInfo.title}</h2>
        <p class="tracker-hero-subtitle">${heroInfo.subtitle}</p>

        <p style="color:var(--text-muted); font-size:0.85rem; font-weight:500;">
          Dining Mode: <strong style="color:var(--text-main);">${(order.dining_type || '').toUpperCase().replace('_', ' ')}</strong>${tableInfo}
        </p>
      </div>

      <!-- Animated Progress Bar & Steps -->
      <div class="tracker-timeline">
        <div class="tracker-progress-line" style="width: ${progressWidth}%;"></div>
        ${timelineHtml}
      </div>

      <!-- Order Details Summary -->
      <div style="background:var(--bg-card); border-radius:var(--radius-md); padding:1.25rem; border:1px solid var(--border-color); margin-bottom:1.5rem;">
        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
          <span style="color:var(--text-muted); font-size:0.88rem;">Estimated Prep Time:</span>
          <strong style="color:var(--primary); font-size:0.95rem;">~${order.estimated_minutes || 15} Minutes</strong>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:0.75rem;">
          <span style="color:var(--text-muted); font-size:0.88rem;">Payment Status:</span>
          <strong style="text-transform:uppercase; font-size:0.88rem; color:${order.payment_status === 'paid' ? 'var(--success)' : 'var(--gold)'};">${order.payment_status}</strong>
        </div>
        <div style="margin-top:0.75rem; padding-top:0.75rem; border-top:1px solid var(--border-color);">
          <div style="font-weight:700; font-size:0.88rem; margin-bottom:0.5rem;">Ordered Items:</div>
          ${itemsHtml}
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:1rem; font-size:1.15rem; font-weight:800; border-top:1px dashed var(--border-color); padding-top:0.75rem;">
          <span>Total Amount:</span>
          <span style="color:var(--gold);">${SatayApp.formatPrice(order.total_amount)}</span>
        </div>
      </div>

      ${!this.currentUser ? `
        <!-- Optional Post-Order Account Creation Card for Guests -->
        <div style="background:linear-gradient(135deg, rgba(139, 69, 19, 0.08), rgba(212, 160, 23, 0.12)); border:1px dashed var(--gold); border-radius:var(--radius-md); padding:1rem 1.25rem; margin-bottom:1.5rem; text-align:center; animation:fadeIn 0.3s ease;">
          <div style="font-weight:700; font-size:0.92rem; color:var(--text-main); margin-bottom:0.25rem;">✨ Want to save your order history?</div>
          <p style="font-size:0.8rem; color:var(--text-muted); line-height:1.4; margin-bottom:0.65rem;">Create a free account in 20 seconds to easily access your receipts, save favorite satay items, and re-order with 1 tap.</p>
          <button type="button" class="btn btn-secondary btn-sm" onclick="CustomerApp.openAuthModal('register')" style="border-color:var(--gold); color:var(--primary); font-weight:600; font-size:0.8rem; padding:0.35rem 0.9rem;">
            ✨ Create Free Account (Optional)
          </button>
        </div>
      ` : ''}

      <!-- Action Buttons -->
      <div style="display:flex; gap:0.75rem; justify-content:center;">
        <button class="btn btn-secondary" onclick="SatayApp.printThermalReceipt(${JSON.stringify(order).replace(/"/g, '&quot;')})">
          View / Print Receipt
        </button>
        <button class="btn btn-primary" onclick="CustomerApp.stopLiveTracking()">
          Done
        </button>
      </div>
    `;
  },

 // Show past orders modal (Enhanced with DB query for registered users & local search for guests)
 async showOrderHistoryModal() {
 const container = document.getElementById('history-modal-content');
 if (!container) return;

 SatayApp.openModal('history-modal');
 container.innerHTML = `
 <div style="text-align:center; padding:2rem 1rem; color:var(--text-muted);">
 <div class="loading-spinner" style="margin:0 auto 0.75rem;"></div>
 <p>Loading your orders...</p>
 </div>
 `;

 try {
 let orders = [];

 if (this.currentUser) {
 // Fetch logged-in user order history
 const res = await fetch('api/orders.php?my_orders=1');
 const data = await res.json();
 if (data.success) {
 orders = data.orders || [];
 }
 } else {
 // Fetch guest orders by local storage order numbers
 const history = JSON.parse(localStorage.getItem('satay_history') || '[]');
 if (history.length > 0) {
 const res = await fetch(`api/orders.php?order_numbers=${encodeURIComponent(history.join(','))}`);
 const data = await res.json();
 if (data.success) {
 orders = data.orders || [];
 }
 }
 }

 if (orders.length === 0) {
 container.innerHTML = `
 <div style="text-align:center; padding:2.5rem 1rem; color:var(--text-muted);">
 <div style="font-size:2.5rem; margin-bottom:0.5rem;"></div>
 <h4 style="color:var(--text-main); margin-bottom:0.25rem;">No past orders found</h4>
 <p style="font-size:0.85rem; margin-bottom:1.25rem;">You haven't placed any orders yet on this account or device.</p>
 ${!this.currentUser ? `
 <button class="btn btn-primary btn-sm" onclick="SatayApp.closeModal('history-modal'); CustomerApp.openAuthModal('login');">
 Sign In to View Account Orders
 </button>
 ` : ''}
 </div>
 `;
 return;
 }

 const statusColors = {
 pending: 'badge-gold',
 confirmed: 'badge-primary',
 grilling: 'badge-primary',
 ready: 'badge-success',
 completed: 'badge-secondary',
 cancelled: 'badge-danger'
 };

 let guestBanner = '';
 if (!this.currentUser) {
 guestBanner = `
 <div style="background:rgba(245, 158, 11, 0.1); border:1px solid rgba(245, 158, 11, 0.25); border-radius:var(--radius-md); padding:0.75rem 1rem; margin-bottom:1rem; display:flex; justify-content:space-between; align-items:center;">
 <span style="font-size:0.8rem; color:#fde68a;">
 <strong>Ordering as Guest?</strong> Sign in to sync your orders across all devices.
 </span>
 <button class="btn btn-secondary btn-sm" style="font-size:0.75rem; padding:3px 8px;" onclick="SatayApp.closeModal('history-modal'); CustomerApp.openAuthModal('login');">
 Sign In →
 </button>
 </div>
 `;
 }

 const ordersListHtml = orders.map(ord => {
 const badgeClass = statusColors[ord.order_status] || 'badge-secondary';
 const itemsCount = (ord.items || []).reduce((acc, it) => acc + parseInt(it.quantity || 1), 0);
 const itemsNames = (ord.items || []).map(it => `${it.quantity}x ${it.item_name}`).slice(0, 2).join(', ');

 return `
 <div style="background:var(--bg-card); padding:1rem; border-radius:var(--radius-md); border:1px solid var(--border-color); margin-bottom:0.75rem;">
 <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.5rem;">
 <div>
 <strong style="font-size:0.95rem; color:var(--text-main);">#${SatayApp.escapeHtml(ord.order_number)}</strong>
 <div style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">
 ${ord.created_at} • <span style="text-transform:capitalize;">${(ord.dining_type || '').replace('_', ' ')}</span>
 </div>
 </div>
 <div style="text-align:right;">
 <span class="badge ${badgeClass}" style="text-transform:uppercase; font-size:0.7rem;">
 ${ord.order_status}
 </span>
 <div style="font-weight:700; color:var(--gold); font-size:0.95rem; margin-top:3px;">
 ${SatayApp.formatPrice(ord.total_amount)}
 </div>
 </div>
 </div>

 <div style="font-size:0.82rem; color:var(--text-muted); margin-bottom:0.75rem; background:rgba(0,0,0,0.25); padding:6px 10px; border-radius:var(--radius-sm);">
 ${itemsCount} items: <em>${SatayApp.escapeHtml(itemsNames)}${(ord.items && ord.items.length > 2) ? '...' : ''}</em>
 </div>

 <div style="display:flex; gap:0.5rem; justify-content:flex-end;">
 <button class="btn btn-secondary btn-sm" style="font-size:0.78rem; padding:4px 10px;" onclick="SatayApp.printThermalReceipt(${JSON.stringify(ord).replace(/"/g, '&quot;')})">
 Receipt
 </button>
 <button class="btn btn-primary btn-sm" style="font-size:0.78rem; padding:4px 10px;" onclick="CustomerApp.startLiveTracking('${SatayApp.escapeHtml(ord.order_number)}'); SatayApp.closeModal('history-modal');">
 View Live Tracker
 </button>
 </div>
 </div>
 `;
 }).join('');

 container.innerHTML = `
 ${guestBanner}
 <div style="max-height:420px; overflow-y:auto; padding-right:4px;">
 ${ordersListHtml}
 </div>
 `;

 } catch (e) {
 console.error('History load error:', e);
 container.innerHTML = `
 <div style="text-align:center; padding:2rem 1rem; color:var(--danger);">
 <p>Failed to load order history. Please try again.</p>
 </div>
 `;
 }
 }
};

document.addEventListener('DOMContentLoaded', () => {
 CustomerApp.init();
});

