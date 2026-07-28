# HR Premium Design System

A design system for a premium HR/Recruitment web application, built with a two-tier sidebar layout, a card-based main content area, and a responsive data table that converts to cards on mobile.

---

## 1. Brand & Foundations

### 1.1 Color Palette

| Token | Value | Usage |
|---|---|---|
| `--primary` | `#5243E8` | Primary actions, active states, links |
| `--primary-hover` | `#4032D6` | Hover state for primary buttons |
| `--primary-light` | `#EEF2FF` | Active nav backgrounds, focus rings |
| `--primary-gradient` | `linear-gradient(135deg, #5B48DF 0%, #4630D4 100%)` | Gradient CTA buttons |

**Backgrounds**

| Token | Value | Usage |
|---|---|---|
| `--bg-page` | `#F6F7FA` | Page background |
| `--bg-sidebar` | `#FCFCFD` | Secondary sidebar background |
| `--bg-card` | `#FFFFFF` | Cards, primary sidebar, table rows (mobile) |

**Typography Colors**

| Token | Value | Usage |
|---|---|---|
| `--text-heading` | `#111827` | Headings, high-emphasis text |
| `--text-main` | `#374151` | Body text |
| `--text-muted` | `#6B7280` | Secondary text |
| `--text-light` | `#9CA3AF` | Placeholders, icons, low-emphasis text |

**Borders**

| Token | Value |
|---|---|
| `--border` | `#E5E7EB` |
| `--border-light` | `#F3F4F6` |

**Shadows**

| Token | Value | Usage |
|---|---|---|
| `--shadow-sm` | `0 1px 2px rgba(0,0,0,0.05)` | Subtle elevation |
| `--shadow-card` | `0 10px 40px -10px rgba(0,0,0,0.04)` | Content card |
| `--shadow-btn` | `0 4px 12px rgba(82,67,232,0.25)` | Primary buttons |
| `--shadow-btn-hover` | `0 6px 14px rgba(82,67,232,0.35)` | Primary buttons on hover |

**Semantic / Status Colors**

| Status | Background | Border | Text |
|---|---|---|---|
| Draft | `#FFFFFF` | `var(--border-light)` | `#4B5563` |
| Expired | `#FFFFFF` | `#FECACA` | `#EF4444` |

### 1.2 Typography

- **Font family:** `Lexend Deca`, sans-serif (Google Fonts, weights 200–700)
- **Base line-height:** 1.5
- **Font smoothing:** antialiased (WebKit/Moz)

| Element | Size | Weight | Color |
|---|---|---|---|
| Page title (`h1`) | 1.7rem | 700 | `--text-heading` |
| Sidebar title | 1.25rem | 600 | `--text-muted` |
| Subtitle | 0.9rem | 500 | `--primary` |
| Nav link | 0.95rem | 500 | `--text-muted` (600 active) |
| Table header | 0.825rem | 500 | `--text-light` |
| Table cell | 0.9rem | 400 | `--text-main` |
| Badge | 0.725rem | 600 | status-dependent |
| Nav icon label | 0.65rem | 500 | `--text-light` |

### 1.3 Iconography

- **Ionicons 7.1.0** loaded via CDN (`ion-icon` web component), ES module with `nomodule` fallback.

### 1.4 Spacing & Radius Conventions

- Card radius: `24px` (desktop) → `16px` (mobile)
- Button/pill radius: `9999px` (full pill)
- Nav item / table corner radius: `8px`–`12px`
- Standard section gaps: `1.5rem`–`2.25rem`

---

## 2. Layout Architecture

The app uses a **three-column layout**: a narrow icon-only primary sidebar, a wider labeled secondary sidebar, and a main content card.

```
┌───┬─────────┬─────────────────────────────┐
│ P │    S    │                             │
│ r │    e    │        Main Content         │
│ i │    c    │        (content-card)        │
│ m │    o    │                             │
│ a │    n     │                             │
│ r │    d     │                             │
│ y │    a     │                             │
│   │    r     │                             │
│ 80│    y    │                             │
│ px│  260px  │        remaining width      │
└───┴─────────┴─────────────────────────────┘
```

### 2.1 Primary Sidebar (`.sidebar-primary`)
- Fixed, `80px` wide, full height, left-aligned, `z-index: 50`
- Contains: square logo mark (40×40, rounded 12px, brand color), icon-only nav items (icon + micro-label), and a circular user avatar at the bottom
- `justify-content: space-between` to pin nav top and avatar bottom

### 2.2 Secondary Sidebar (`.sidebar-secondary`)
- Fixed, `260px` wide, offset `left: 80px` (sits beside primary sidebar)
- Contains a section title (e.g. "Recruit") and a vertical list of text nav links
- Active link: `--primary-light` background, `--primary` text, weight 600

### 2.3 Main Content (`.main-content`)
- `margin-left: 340px` (80px + 260px) to clear both fixed sidebars
- Wraps a single `.content-card`: white, rounded 24px, `--shadow-card`, min-height fills viewport

### 2.4 Content Card Structure
1. **Page Header** — title (h1) + subtitle + primary CTA button, `justify-content: space-between`
2. **Toolbar** — left-aligned action buttons, right-aligned search input
3. **Data Table** — scrollable table with badges, share links, and row actions
4. **Pagination** — right-aligned page count + circular prev/next buttons

---

## 3. Components

### 3.1 Buttons

| Class | Style |
|---|---|
| `.btn` | Base: inline-flex, 0.9rem, weight 500, `0.65rem 1.25rem` padding |
| `.btn-pill` | `border-radius: 9999px` |
| `.btn-primary` | Solid `--primary` fill, white text, `--shadow-btn` |
| `.btn-primary-gradient` | Diagonal gradient fill, used for high-emphasis CTAs |
| `.btn-outline` | Transparent bg, `1px solid --border`, `--primary` text |
| `.btn-share` | Text-style link button with icon, `--primary` color |
| `.btn-page` | Circular (30×30) pagination button, `#F1F5F9` bg, active = `--primary` fill |
| `.action-btn` | Icon-only row action, transparent bg, hover = `--primary-light` bg + `--primary` icon |

All interactive buttons use a `0.2s ease` transition and a `translateY(-1px)` lift + deeper shadow on hover (primary variants).

### 3.2 Search Input
- Pill-shaped (`9999px`), white background, `1px solid --border`
- Leading search icon, transparent inner `<input>`
- Focus state: `--primary` border + `0 0 0 3px --primary-light` ring

### 3.3 Badges
- Pill-shaped, `0.35em 0.8em` padding, `0.725rem` / weight 600
- **Draft:** white bg, light border, grey text
- **Expired:** white bg, red-tinted border, red text (`#EF4444`)

### 3.4 Data Table
- `border-collapse: separate` with `border-spacing: 0` for rounded header corners
- Header cells: light grey bg (`#F9FAFB`), muted text, top+bottom border only on first/last for rounded corners
- Rows: bottom border between rows, subtle hover background (`#F8FAFC`)
- Cells support a `data-label` attribute — used to relabel each cell as a stacked "Label: Value" row on mobile

### 3.5 Pagination
- Right-aligned row: page-range text (`--primary`, weight 500) + circular prev/next buttons
- Active page button: filled `--primary` circle

---

## 4. Interactivity (script.js)

- **Mobile sidebar toggle:** a hamburger button (`#mobileMenuBtn`) toggles an `.open` class on `#sidebarPrimary`, sliding it into view via CSS transform.
- **Outside-click dismissal:** clicking anywhere outside the open sidebar (and outside the toggle button) closes it automatically.

---

## 5. Responsive Behavior

### Breakpoint: `max-width: 1024px` (tablet)
- Secondary sidebar slides off-screen (`translateX(-100%)`)
- Main content margin collapses to `80px` (primary sidebar width only)

### Breakpoint: `max-width: 768px` (mobile)
- Primary sidebar hidden by default, slides in as an overlay (`.open` class) with drop shadow
- A dedicated **mobile header** appears: logo + hamburger menu, sticky at top (`z-index: 40`)
- Layout stacks vertically (`.app-layout { flex-direction: column }`)
- Main content margin resets to `0`, full width, reduced padding
- Content card radius reduces to `16px`, `min-height: auto`
- Page header stacks vertically; `.mobile-hide` elements are removed from view
- Toolbar stacks vertically; buttons and search expand to full width
- **Table → Card transformation:**
  - `<thead>` hidden
  - Each `<tr>` becomes a bordered, rounded card (`12px` radius, `--shadow-sm`)
  - Each `<td>` becomes a flex row (`justify-content: space-between`) with the `data-label` attribute rendered via `::before` as the row's field label
  - Row hover background disabled (not meaningful on touch/mobile cards)
- Pagination centers instead of right-aligning

---

## 6. Usage Notes

- Fonts and icons are loaded externally (Google Fonts + Ionicons CDN) — ensure `preconnect` tags are retained for performance.
- All color, shadow, and gradient values are defined as CSS custom properties on `:root`, making global re-theming a matter of editing the token values in `styles.css`.
- The `data-label` pattern on table cells is required for the mobile card view to display field names — any new table must include this attribute on every `<td>`.
