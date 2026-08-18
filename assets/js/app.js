/**
 * Satay Ordering System - Shared Core Client Helpers & Utilities
 */

const SatayApp = {
  currency: 'RM',

  // Format currency string
  formatPrice(amount) {
    return `${this.currency} ${parseFloat(amount || 0).toFixed(2)}`;
  },

  // Parse order timestamp reliably across UTC / Local formats
  parseOrderDate(dateStr) {
    if (!dateStr) return new Date();
    if (typeof dateStr === 'number') return new Date(dateStr * 1000);
    
    const str = String(dateStr).trim();
    if (!str) return new Date();

    // If string is ISO with Z or offset
    if (str.includes('T') && (str.endsWith('Z') || str.includes('+'))) {
      return new Date(str);
    }

    // Standard SQLite format: 'YYYY-MM-DD HH:MM:SS'
    // Attempt local parse:
    const localParsed = new Date(str.replace(/-/g, '/'));
    // Attempt UTC parse:
    const utcParsed = new Date(str.replace(' ', 'T') + 'Z');

    const now = new Date();
    const diffLocal = Math.abs(now.getTime() - localParsed.getTime());
    const diffUtc = Math.abs(now.getTime() - utcParsed.getTime());

    // If local diff is huge (e.g. ~8 hours = 480 mins) and UTC diff is much smaller, use UTC!
    if (!isNaN(utcParsed.getTime()) && !isNaN(localParsed.getTime())) {
      return (diffUtc < diffLocal) ? utcParsed : localParsed;
    }
    return !isNaN(localParsed.getTime()) ? localParsed : new Date();
  },

  getElapsedMinutes(dateStr) {
    const created = this.parseOrderDate(dateStr);
    return Math.max(0, Math.floor((new Date().getTime() - created.getTime()) / 60000));
  },

  formatTimeAgo(dateStr) {
    const created = this.parseOrderDate(dateStr);
    const diffSec = Math.max(0, Math.floor((new Date().getTime() - created.getTime()) / 1000));

    if (diffSec < 45) return 'Just now';
    const mins = Math.floor(diffSec / 60);
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ${mins % 60}m ago`;
    const days = Math.floor(hours / 24);
    return `${days}d ago`;
  },

  // HTML escaping utility for XSS protection
  escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  },

  // Show Toast Notification
  showToast(message, type = 'info', duration = 3500) {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const icons = {
      info: 'ℹ️',
      success: '✅',
      warning: '⚠️',
      danger: '❌'
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `<span>${icons[type] || ''}</span> <span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },

  // Audio Alert Synthesizer for Kitchen, Drinks & Order Alerts
  playChime(type = 'new_order') {
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();

      const now = ctx.currentTime;
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.connect(gain);
      gain.connect(ctx.destination);

      if (type === 'new_order') {
        // Energetic 3-tone chime for incoming orders
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, now); // D5
        osc.frequency.setValueAtTime(880.00, now + 0.15); // A5
        osc.frequency.setValueAtTime(1174.66, now + 0.3); // D6

        gain.gain.setValueAtTime(0.35, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.75);

        osc.start(now);
        osc.stop(now + 0.75);
      } else if (type === 'drink_order') {
        // High, refreshing multi-tone melody for incoming drink orders (C5 -> E5 -> G5 -> C6)
        osc.type = 'sine';
        osc.frequency.setValueAtTime(523.25, now); // C5
        osc.frequency.setValueAtTime(659.25, now + 0.12); // E5
        osc.frequency.setValueAtTime(783.99, now + 0.24); // G5
        osc.frequency.setValueAtTime(1046.50, now + 0.36); // C6

        gain.gain.setValueAtTime(0.45, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.9);

        osc.start(now);
        osc.stop(now + 0.9);
      } else if (type === 'ready') {
        // High bell for order ready
        osc.type = 'triangle';
        osc.frequency.setValueAtTime(1046.50, now); // C6
        osc.frequency.setValueAtTime(1318.51, now + 0.2); // E6

        gain.gain.setValueAtTime(0.35, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.6);

        osc.start(now);
        osc.stop(now + 0.6);
      }
    } catch (e) {
      console.warn('Audio playback not permitted or unavailable:', e);
    }
  },

  // Handphone / Mobile Vibration Alert via Web Vibration API
  vibratePhone(pattern = [400, 150, 400, 150, 600]) {
    try {
      if ('vibrate' in navigator) {
        navigator.vibrate(pattern);
      }
    } catch (e) {
      console.warn('Vibration API not supported or blocked:', e);
    }
  },

  // Browser System / Handphone Push Notification
  async showPushNotification(title, options = {}) {
    try {
      if (!('Notification' in window)) return;
      
      const defaultOpts = {
        icon: 'assets/icons/icon-192.png',
        badge: 'assets/icons/icon-192.png',
        vibrate: [400, 150, 400, 150, 600],
        tag: 'satay-order-alert',
        renotify: true,
        requireInteraction: true,
        ...options
      };

      if (Notification.permission === 'granted') {
        const notif = new Notification(title, defaultOpts);
        notif.onclick = function() {
          window.focus();
          this.close();
        };
      } else if (Notification.permission !== 'denied') {
        const perm = await Notification.requestPermission();
        if (perm === 'granted') {
          const notif = new Notification(title, defaultOpts);
          notif.onclick = function() {
            window.focus();
            this.close();
          };
        }
      }
    } catch (e) {
      console.warn('System push notification error:', e);
    }
  },

  // Modal Manager
  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('open');
      document.body.style.overflow = 'hidden';
    }
  },

  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('open');
      document.body.style.overflow = '';
    }
  },

  // App Side Navigation Drawer Manager
  openSideNav() {
    const sideNav = document.getElementById('app-side-nav');
    const backdrop = document.getElementById('app-sidenav-backdrop');
    if (sideNav) sideNav.classList.add('open');
    if (backdrop) backdrop.classList.add('open');
    document.body.style.overflow = 'hidden';
  },

  closeSideNav() {
    const sideNav = document.getElementById('app-side-nav');
    const backdrop = document.getElementById('app-sidenav-backdrop');
    if (sideNav) sideNav.classList.remove('open');
    if (backdrop) backdrop.classList.remove('open');
    document.body.style.overflow = '';
  },

  toggleSideNav() {
    const sideNav = document.getElementById('app-side-nav');
    if (sideNav && sideNav.classList.contains('open')) {
      this.closeSideNav();
    } else {
      this.openSideNav();
    }
  },

  // Thermal Receipt Builder & Printer
  printThermalReceipt(order) {
    if (!order) return;

    const subtotal = parseFloat(order.subtotal || 0) || (order.items || []).reduce((sum, it) => sum + (parseFloat(it.total_price) || (parseFloat(it.unit_price || 0) * parseInt(it.quantity || 1))), 0);
    const taxAmount = parseFloat(order.tax_amount || 0);
    const serviceFee = parseFloat(order.service_fee || 0);
    const totalAmount = parseFloat(order.total_amount || 0) || (subtotal + taxAmount + serviceFee);

    const itemsHtml = (order.items || []).map(item => `
      <div class="receipt-item">
        <div class="receipt-item-main">
          <span>${item.quantity}x ${SatayApp.escapeHtml(item.item_name || item.name || 'Satay')}</span>
          <span>${SatayApp.formatPrice(item.total_price || (item.unit_price * item.quantity))}</span>
        </div>
        ${item.spicy_level && item.spicy_level !== 'normal' ? `<div class="receipt-item-sub">Pedas: ${SatayApp.escapeHtml(item.spicy_level)}</div>` : ''}
        ${item.special_notes ? `<div class="receipt-item-sub">* ${SatayApp.escapeHtml(item.special_notes)}</div>` : ''}
      </div>
    `).join('') || '<div class="receipt-item-main"><span>1x Satay Items Set</span><span>' + SatayApp.formatPrice(totalAmount) + '</span></div>';

    const printHtml = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="UTF-8">
        <title>Receipt #${SatayApp.escapeHtml(order.order_number || '')}</title>
        <style>
          @page { size: 80mm auto; margin: 4mm; }
          * { box-sizing: border-box; }
          body {
            font-family: 'Courier New', Courier, monospace;
            background: #f1f5f9;
            color: #000;
            margin: 0;
            padding: 16px 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 12px;
            line-height: 1.35;
          }
          .no-print-bar {
            width: 100%;
            max-width: 320px;
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
          }
          .btn-print {
            flex: 1;
            padding: 9px;
            background: #8B4513;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
          }
          .btn-close {
            padding: 9px 14px;
            background: #e2e8f0;
            color: #333;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
          }
          .receipt-paper {
            background: #fff;
            width: 100%;
            max-width: 310px;
            padding: 16px 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
          }
          .text-center { text-align: center; }
          .bold { font-weight: bold; }
          .header-title { font-size: 16px; font-weight: 900; margin: 0 0 2px; }
          .header-sub { font-size: 10px; color: #444; margin: 1px 0; }
          .divider { border-top: 1px dashed #555; margin: 8px 0; }
          .double-divider { border-top: 2px dashed #000; margin: 8px 0; }
          .order-badge { font-size: 13px; font-weight: 900; margin: 4px 0; }
          .row { display: flex; justify-content: space-between; margin: 3px 0; font-size: 12px; }
          .receipt-item { margin: 4px 0; }
          .receipt-item-main { display: flex; justify-content: space-between; font-weight: 700; }
          .receipt-item-sub { font-size: 10px; color: #555; padding-left: 10px; }
          .total-row { font-size: 14px; font-weight: 900; margin: 6px 0; }
          @media print {
            body { background: transparent; padding: 0; }
            .no-print-bar { display: none !important; }
            .receipt-paper { box-shadow: none; border: none; padding: 0; max-width: 100%; width: 100%; }
          }
        </style>
      </head>
      <body>
        <div class="no-print-bar">
          <button class="btn-print" onclick="window.print()">🖨️ Print Receipt</button>
          <button class="btn-close" onclick="window.close()">✕ Close</button>
        </div>
        <div class="receipt-paper">
          <div class="text-center">
            <div class="header-title">SATE TULANG MADU</div>
            <div class="header-sub">Kg. Kubang Batang, 16210 Tumpat, Kelantan</div>
            <div class="header-sub">Tel: +6017-9885400</div>
            <div class="divider"></div>
            <div class="order-badge">ORDER #${SatayApp.escapeHtml(order.order_number || '')}</div>
            <div style="font-size: 11px; color: #333;">${SatayApp.escapeHtml(order.created_at || new Date().toLocaleString())}</div>
            <div style="font-size: 12px; font-weight: 800; margin-top: 3px;">
              ${SatayApp.escapeHtml((order.dining_type || '').toUpperCase().replace('_', ' '))} ${order.table_number ? `• TABLE ${SatayApp.escapeHtml(order.table_number)}` : ''}
            </div>
            ${order.customer_name ? `<div style="font-size: 11px; color: #333; margin-top: 2px;">Cust: ${SatayApp.escapeHtml(order.customer_name)} ${order.customer_phone ? `(${SatayApp.escapeHtml(order.customer_phone)})` : ''}</div>` : ''}
          </div>
          <div class="divider"></div>
          <div style="margin: 6px 0;">
            ${itemsHtml}
          </div>
          <div class="divider"></div>
          <div class="row">
            <span>Subtotal:</span>
            <span>${SatayApp.formatPrice(subtotal)}</span>
          </div>
          ${taxAmount > 0 ? `
            <div class="row">
              <span>SST (6%):</span>
              <span>${SatayApp.formatPrice(taxAmount)}</span>
            </div>
          ` : ''}
          ${serviceFee > 0 ? `
            <div class="row">
              <span>Delivery Fee:</span>
              <span>${SatayApp.formatPrice(serviceFee)}</span>
            </div>
          ` : ''}
          <div class="double-divider"></div>
          <div class="row total-row">
            <span>TOTAL:</span>
            <span>${SatayApp.formatPrice(totalAmount)}</span>
          </div>
          <div class="row" style="font-size: 11px; margin-top: 4px;">
            <span>Payment:</span>
            <span class="bold">${SatayApp.escapeHtml((order.payment_method || 'CASH').toUpperCase())} (${SatayApp.escapeHtml((order.payment_status || 'PAID').toUpperCase())})</span>
          </div>
          <div class="divider"></div>
          <div class="text-center" style="margin-top: 10px;">
            <div class="bold" style="font-size: 11px;">Terima Kasih! / Thank You!</div>
            <div style="font-size: 10px; color: #555;">Silakan Datang Lagi</div>
          </div>
        </div>
        <script>
          window.onload = function() {
            setTimeout(function() {
              window.print();
            }, 300);
          };
        </script>
      </body>
      </html>
    `;

    const printWin = window.open('', '_blank', 'width=420,height=650');
    if (printWin) {
      printWin.document.write(printHtml);
      printWin.document.close();
    } else {
      let iframe = document.getElementById('print-receipt-iframe');
      if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'print-receipt-iframe';
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        document.body.appendChild(iframe);
      }
      iframe.contentWindow.document.open();
      iframe.contentWindow.document.write(printHtml);
      iframe.contentWindow.document.close();
      setTimeout(() => {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
      }, 500);
    }
  }
};

// Global click handler to close modals and side drawers when clicking backdrop
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
  if (e.target.classList.contains('drawer-backdrop') || e.target.classList.contains('app-sidenav-backdrop')) {
    SatayApp.closeSideNav();
    const drawer = document.querySelector('.drawer.open');
    if (drawer) drawer.classList.remove('open');
  }
});

// PWA Service Worker Registration
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('sw.js')
      .then((reg) => {
        console.log('[PWA] Service Worker registered with scope:', reg.scope);
      })
      .catch((err) => {
        console.warn('[PWA] Service Worker registration failed:', err);
      });
  });
}
