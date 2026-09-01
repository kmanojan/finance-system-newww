# 28. PWA, Session Security & Core UI Components

## 1. Overview
This document covers the Progressive Web App (PWA) configuration, long-lived authentication sessions, security controls, and core reusable Blade components across the Finance Management System.

---

## 2. Session & Authentication Management

### Multi-Year Persistent Sessions
- **Session Lifetime:** Configured for 5+ years (`SESSION_LIFETIME=2629800` minutes in `.env` and `config/session.php`).
- **Remember Me Tokens:** `Auth::attempt($credentials, $remember)` automatically generates a long-lived remember cookie (`remember_web_...`).
- **Login UI:**
  - "Keep me signed in (Remember me)" option enabled by default.
  - Interactive **Show / Hide Password** toggle (`eye-outline` / `eye-off-outline`) on password inputs.
  - Clean initial state: No hardcoded demo credentials pre-filled in login fields.

---

## 3. Progressive Web App (PWA) Features

### Web App Manifest & Service Worker Specifications
- **Manifest (`/manifest.json`)**:
  - `start_url`: `"/"` (auto-redirects to `/dashboard` for authenticated users and `/login` for guests).
  - `display`: `"standalone"` (launches full-screen as a native Android/iOS application with no browser URL bar).
  - `orientation`: `"any"`.
  - `theme_color`: `"#8b5cf6"` | `background_color`: `"#1a1d27"`.
  - **Adaptive / Maskable Icons**: High-resolution icons (`192x192`, `512x512`, `icon-maskable.png`, vector `icon.svg`) matching Android's adaptive circle/square icon shapes without generic browser badge overlays.
- **Service Worker (`/sw.js`)**:
  - Versioned cache (`finance-system-v2`).
  - Pre-caches core static assets and offline fallback page (`/offline.html`).
  - Network-first navigation strategy with automatic fallback to `/offline.html` when network is unavailable.
- **Dedicated Route Headers (`routes/web.php`)**:
  - `GET /manifest.json` served with `Content-Type: application/manifest+json; charset=utf-8` and `Access-Control-Allow-Origin: *`.
  - `GET /sw.js` served with `Content-Type: application/javascript; charset=utf-8` and `Service-Worker-Allowed: /`.

### Android WebAPK Installation & In-App Prompt
- **Native Install Prompt (`beforeinstallprompt`)**:
  - Automatically captures the browser's install event and displays an elegant floating **"Install Finance App"** banner at the bottom of the screen.
  - Tapping **"Install"** triggers Chrome's native WebAPK installation dialog directly, creating a true standalone app in the Android App Drawer rather than a bookmark shortcut.
- **Dedicated Install App Page (`/profile?tab=app`):**
  - Standalone mode indicator (`window.matchMedia('(display-mode: standalone)')`).
  - "Install App Now" button triggering the native install prompt on Chrome / Android / Desktop.
  - Step-by-step installation instructions for iOS Safari (*Share > Add to Home Screen*) and Android/Desktop Chrome.

---

## 4. Reusable Blade Components

### Core Selectors
1. **`<x-category-selector />`** (`resources/views/components/category-selector.blade.php`):
   - Real-time search by category name or type.
   - Income and Expense color-coded badges.
   - Dynamic type filtering (`filterCategorySelectorByType(id, type)`).

2. **`<x-payment-mode-selector />`** (`resources/views/components/payment-mode-selector.blade.php`):
   - Standardized selection: `Normal`, `Bank Transfer`, `Petty Cash`, `Credit Card`, `Cash`.

3. **`<x-department-selector />`** (`resources/views/components/department-selector.blade.php`):
   - Organizational tree hierarchy with code chips and search.

4. **`<x-amount-input />`** (`resources/views/components/amount-input.blade.php`):
   - Thousand separators with 2-decimal validation on blur and hidden raw float value syncing.

5. **`<x-sidebar.quick-add-transaction />`** (`resources/views/components/sidebar/quick-add-transaction.blade.php`):
   - Global transaction creation widget docked to the main navigation sidebar.
