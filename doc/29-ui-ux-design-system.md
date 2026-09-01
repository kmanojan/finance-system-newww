# 29. UI/UX Design System & Mobile PWA Ergonomics

This document details the frontend architecture, design system tokens, responsive navigation, and reusable Blade components powering the **Apptimus Finance System**.

---

## 29.1 Design System & Theming Tokens

The application supports seamless **Dark Mode** and **Light Mode** switching with persistent `localStorage` preference and system matching.

### Core CSS Variables (`public/styles.css`)

```css
:root {
    --primary: #8b5cf6;
    --primary-hover: #a78bfa;
    --primary-light: rgba(139, 92, 246, 0.15);
    --primary-gradient: linear-gradient(135deg, #a78bfa 0%, #7c3aed 100%);
    --danger: #ef4444;
    --success: #10b981;
    --warning: #f59e0b;
}

html[data-theme="dark"] {
    --bg-page: #1a1d27;
    --bg-sidebar-primary: #12141c;
    --bg-sidebar-secondary: #161923;
    --bg-card: #202431;
    --text-heading: #f8fafc;
    --text-main: #cbd5e1;
    --text-muted: #94a3b8;
    --text-light: #64748b;
    --border: rgba(255, 255, 255, 0.12);
    --border-light: rgba(255, 255, 255, 0.06);
}

[data-theme="light"], :root {
    --bg-page: #F3F4F6;
    --bg-sidebar-primary: #FFFFFF;
    --bg-sidebar-secondary: #F9FAFB;
    --bg-card: #FFFFFF;
    --text-heading: #111827;
    --text-main: #374151;
    --text-muted: #6B7280;
    --text-light: #9CA3AF;
    --border: #E5E7EB;
    --border-light: #F3F4F6;
}
```

### Semantic Adaptive Badges
Translucent alpha badges ensure high contrast and readability on both dark and light surfaces:
- `.badge-success` — Emerald Green (`#10b981`, `rgba(16, 185, 129, 0.15)`)
- `.badge-danger` — Rose Red (`#ef4444`, `rgba(239, 68, 68, 0.15)`)
- `.badge-warning` — Amber Yellow (`#f59e0b`, `rgba(245, 158, 11, 0.15)`)
- `.badge-info` — Sky Blue (`#3b82f6`, `rgba(59, 130, 246, 0.15)`)
- `.badge-primary` / `.badge-purple` — Violet (`var(--primary)`, `var(--primary-light)`)
- `.badge-neutral` / `.badge-draft` — Slate Gray (`--badge-draft-color`, `--badge-draft-bg`)

---

## 29.2 Tabular Numerals & Monetary Presentation

All financial figures, ledger balances, project limits, and invoice sums implement **tabular numbers**:
- `.tabular-nums`: sets `font-variant-numeric: tabular-nums;` ensuring monospaced alignment of digits `0-9` and decimal separators.
- `.amount-cell`: right-aligned monetary cell class with `tabular-nums` and bold weight.

---

## 29.3 Reusable Blade Components

### 1. `<x-amount-input>`
Interactive currency amount input that formats live user input with thousand separators (commas) and manages raw decimal values for form submission:
```blade
<x-amount-input name="amount" :value="$loan->principal_amount" required="true" />
```

### 2. `<x-amount-display>`
Standardized formatted currency output with optional positive/negative color coding:
```blade
<x-amount-display :amount="$tx->amount" currency="{{ $tx->currency }}" class="text-heading font-medium" />
```

### 3. `<x-empty-state>`
Engaging empty state card with icon, title, description, and primary action button or modal trigger:
```blade
<x-empty-state 
    icon="briefcase-outline" 
    title="No Projects Found" 
    description="Track client deliverables, payment milestones, and timesheets." 
    actionModal="createProjectModal" 
    actionText="Create First Project" 
/>
```

### 4. `<x-confirm-modal>` & `window.confirmAction()`
Themed replacement for native browser `confirm()` prompts with danger styling and form submission handlers:
```javascript
confirmAction({
    title: 'Delete Invoice?',
    message: 'Are you sure you want to delete invoice INV-2026-001?',
    confirmText: 'Delete Invoice',
    formId: 'delete_inv_1'
});
```

---

## 29.4 Mobile & PWA Ergonomics

1. **Fixed Mobile Bottom Navigation Bar**:
   - App-like bottom bar on screens `< 768px` providing 1-handed thumb navigation to:
     - 📊 **Home** (`/dashboard`)
     - 💳 **Ledger** (`/transactions`)
     - 💼 **Projects** (`/projects`)
     - 📄 **Invoices** (`/invoices`)
     - 💳 **Loans** (`/loans`)
     - ☰ **More** (slide-out navigation drawer)
2. **Safe Area Insets**:
   - `padding-bottom: calc(65px + env(safe-area-inset-bottom, 0px))` prevents UI clipping on iOS home indicator bars.
3. **PWA Standalone Mode**:
   - Fullscreen viewport settings (`viewport-fit=cover`, `mobile-web-app-capable`).

---

## 29.5 Micro-interactions & Form Submit Guards

1. **Floating Toast Notifications**:
   - Flash alerts float in the bottom-right corner and auto-dismiss after 4 seconds.
   - Global JS API: `window.showToast(message, 'success' | 'error' | 'warning' | 'info')`.
2. **Double-Submission Protection**:
   - Automated JavaScript form listener disables submit buttons and displays a spinner icon (`<span class="spinner-icon"></span> Processing...`) to prevent duplicate transactions.
3. **Escape Key Modal Dismissal**:
   - Modals automatically close when pressing `Escape` or clicking on the background backdrop.
