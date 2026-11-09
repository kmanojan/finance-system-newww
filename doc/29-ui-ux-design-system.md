# Finance UI design system

The maintained source is `public/design-system.css`, loaded after the legacy stylesheet by the authenticated and shared layouts. `public/design-system.js` supplies common accessibility behavior. `/design-system` is a signed-in component reference.

For all implemented workflows and their limits, see the [full feature guide](35-implemented-features.md); this document governs their interface treatment.

## Direction

A compact finance workspace: a narrow labelled navigation rail, soft white surfaces, a pale purple balance highlight, purple primary actions, and restrained status colours. The revised direction follows the request for a more contemporary interface without sacrificing workspace width. Lexend Deca remains the product typeface. Data, tasks and clear actions lead each screen; decoration must not compete with money.

## Tokens and layout

| Role | Light | Dark |
|---|---|---|
| Page | `#f6f7fb` | `#141416` |
| Surface | `#ffffff` | `#1d1d20` |
| Heading | `#18191c` | `#f6f6f7` |
| Body | `#303238` | `#e2e2e6` |
| Muted text | `#5b606b` | `#b9bac2` |
| Brand/data | `#6941c6` | `#b9a0f7` |
| Primary action | `#6941c6` | `#b9a0f7` |

Use semantic variables (`--bg-card`, `--text-main`, `--text-muted`, `--action`, `--danger`), not new per-page colours. Surface radius is 20px; control radius is 10px. Small status badges use 6px. Spacing uses 4, 8, 12, 16, 24px increments.

Desktop restores the original 70px primary rail with icons and visible 12px short labels, plus an optional 200px section navigation. Full destination names are available as accessible names and tooltips. The rail scrolls independently on shorter screens while account controls stay reachable. Main gutters are 32px, reduced to 24px on narrower desktops. At 1100px and below, navigation collapses and content uses the full viewport with 16px gutters. Phone tables retain column labels on each row; dense financial comparisons may use an explicitly scrollable table instead.

## Typography and numbers

- Body and controls: 14px; metadata and column labels: at least 12px.
- Page titles: 28px desktop, 22px phone; section titles: 16–20px.
- Money: tabular numerals, right aligned, explicit currency code and two decimals.
- Summaries that combine currencies must convert using the reporting service; never add raw amounts with different currencies.
- Use sentence case, concrete labels and concise help text. Do not display implementation jargon in normal user workflows.

## Components

Use `.page-header`, `.btn.btn-primary`, `.btn.btn-outline`, `.ds-panel`, `.ds-stack`, `.ds-form-grid`, `.data-table`, `.amount-cell`, `.ds-notice`, `.overview-balances` and `.work-row`. Existing Blade selectors, amount inputs, date-range pickers and empty states remain available.

Keep one primary screen action. Keep balance summaries compact. Overview balances use softly rounded cards with one pale purple cash highlight; detailed transaction totals use a divided summary. Do not add decorative icons, invented trends or extra colours to financial figures. Preserve real empty states with a next step; do not fill them with invented data.

Every input needs a visible associated label. Error messages must explain how to correct the value and keep entered data. Use `old()` for server forms and retain form state on failed API requests. Name icon-only actions with `aria-label` or `title`. Mark unavailable actions and explain access restrictions.

Dialogs use `.modal-backdrop` and `.modal-card`. The shared script adds dialog semantics, initial focus, Tab trapping, focus return, labelled close buttons and background scroll locking. The existing shell handles Escape. Avoid nested dialogs. Respect reduced motion and preserve browser zoom. Phone touch targets are at least 44px.

Table cells should include `data-label` in Blade. The shared script adds missing labels for legacy static tables; dynamic rows must declare them explicitly. Do not use colour as the only status indicator.

## Verification for changes

1. Run the affected feature tests on an isolated database.
2. Inspect a populated and empty state, validation errors, and the primary action.
3. Check browser layouts at 1440px, 1280px, 768px and 390px using DOM measurements, including content below the fold. Do not create screenshots for the current revision, as requested by the user.
4. Check light/dark themes, keyboard navigation, modal focus and Escape.
5. Confirm no clipped content, unlabeled mobile amounts, low contrast text or dead controls.

The earlier 2026-11-06 audit covered 51 application pages. The compact-rail revision uses DOM-only browser checks; no new screenshots are created. Existing images from the earlier audit are historical review evidence, not production assets.


## Navigation and party selection

All section menus use `<x-secondary-sidebar>` with one 18px outline icon and a visible text label per link. Loan and ledger menus live in `resources/views/navigation/`; operations and master-data menus keep their shared partials. Keep repeated menus in those partials rather than copying markup into pages. Navigation counts belong inside `.section-navigation-label`.

The primary rail and section menu close independently. Use their chevron buttons to close them and the visible **Main menu** / **Section menu** controls above the content to reopen them. Desktop preferences persist in local storage. On small screens the main menu is a temporary drawer, closed by its button, Escape, a destination link or the backdrop. Closed panels are inert and release their content width. The mobile drawer traps keyboard focus and returns it to the menu trigger.

`<x-party-selector>` has a separate **Clear selected party** button. Clearing restores that instance's placeholder, empties its submitted ID, emits input/change events and calls its own callback with empty values. Clear an automatically copied lender name only if it still matches the selected party; preserve a manually edited lender name. Dropdown options use escaped text, keyboard navigation, Escape and outside-click dismissal.

The current navigation revision is verified with DOM-only browser checks; do not generate screenshots.

## Salary advance register

`/payroll/advances` uses shared Lexend Deca, theme colours, 20px surfaces, 10px controls, and a pale purple remaining-balance panel. The register leads; employee balance detail and the paid-advance form are secondary disclosures. Keep paid, recovered and remaining amounts in separate numeric columns, with currencies explicit and totals grouped by currency. Reversed records remain in history and are excluded from posted balance totals.

Search covers employee name/code and payment reference across all pages. Company, deduction month and recovery status apply to the register and summary; status counts retain the other filters. Paginate at 25 rows, preserve filters in links, and aggregate balances in SQL rather than querying each employee. Desktop tables have a sticky employee column/header and a bounded scroll region. At phone widths, every field stays in labelled cards, company/month filters move into a popover, and payment details open in a native dialog. Full references are readable there. Dialog and popover shadows are intentional floating-surface exceptions.

`salary-advances.css` and `salary-advances.js` are scoped to this page and versioned by content hash. Preserve the shared employee selector and existing posting/recovery services. Historical advances retain their source-payroll link and cannot be rescheduled; recovered payments do not offer reversal. UI review uses DOM measurements at 1440, 1280, 768 and 390px in both themes, with no screenshots.


## Sign-in screen

The login view loads shared design tokens, then `public/auth.css` for its isolated layout and `public/auth.js` for theme, password visibility and install controls. Desktop pairs a pale brand introduction with a form capped at 400px. At 800px and below, the introduction gives way to a single-column form and compact brand header. Inputs and the primary action are at least 50px high; ancillary controls are at least 44px. Browser zoom remains enabled.

Keep visible field labels, username/current-password autocomplete, retained email after errors, blank passwords after failed submissions, and an error summary announced with `role="alert"`. The unchecked remember option explicitly submits zero. Do not add nonfunctional reset links, sample credentials, financial figures or customer data to this public page. Installation suggestions sit below the form and never obscure sign-in.

## Loan detail workspace

The loan single view uses `loan-detail.css` and `loan-detail.js` with the shared theme tokens and compact navigation. A single payable summary leads, supported by principal borrowed, unpaid interest, total paid, and a compact dates row. Avoid repeating the payable total in multiple cards.

Use the full-width tabs in this order: **Interest Schedule**, **Principal Records**, **Loan Flow**, **Loan Details**. Each tab has its own panel and contextual actions. Terms, bank information, description, collateral/guarantor, and documents live in Loan Details. Record repayments and additional draws from Principal Records. The header keeps activation/full settlement and editing visible; pending-loan deletion and status changes sit in a More actions menu. Activated loans do not expose a delete action.

Tabs implement `tablist`/`tab`/`tabpanel`, selected state, roving tabindex, Arrow keys, Home/End, and browser history. `#loan-flow` opens Loan Flow; `#loan-description` opens Loan Details and reveals the description. The flow redraws when its panel becomes visible. On narrow screens only the tab strip and flow canvas scroll horizontally; content stays within the page. No screenshots are generated for this redesign.

### Statutory batch payments

Use the Employees submenu label **EPF, ETF & tax**. Keep pending monthly obligations and payment batch history in the same register. The batch form uses the shared payroll panel, table, field and button styles: company/type/year scope first, checkbox selection with sticky month labels, adjustable partial amounts, then payment details with a live total and save feedback. Selected amounts survive validation errors; refreshing a back/forward-cached form reloads authoritative balances. Table scrolling stays inside its region on phones. Light/dark input colours use shared tokens. JavaScript and payroll styles use content hashes to avoid stale PWA assets after deployment.

### Loan settlement reference editing

Reuse the rich-editor component for settlement notes, preserving shared light/dark tokens, formatting controls and slash search. Loan flow uses the existing card design for referenced transactions, dashed lines for context and solid lines for actual funding allocations. Keep notes in their table column and guidance behind the existing help icon. Flow counts include both referenced transactions and related loans. Use content hashes for the flow CSS/JavaScript assets.

### Salary batch workspace and live totals

The salary batch form shows whole-payroll net amount, already-paid salaries and pending balance, including employees whose salaries are fully paid. Only employees with outstanding salary appear in the selectable table. Selecting an employee fills their remaining balance; entering a smaller positive amount records a partial release. Selected payment amount and pending-after-batch preview update across search and all 50-row pages. Invalid amounts suppress the preview and block recording. Select this page affects the current filtered page; Clear selection resets the whole batch.

The layout uses the existing finance type and theme tokens with a shared totals strip, fixed employee column, bounded scrolling table, larger amount inputs and a separate payment-details section. Light/dark and 1440/768/390/320 layouts were checked using browser DOM geometry without screenshots. The design-taste app direction was Achromatic ledger (seed 850163138), adapted to retain the requested existing brand; its inset row marker indicates selection rather than elevation. The main question is how much this release pays and what remains payable. No new financial posting behavior is introduced.


### Loan index redesign (9 November 2026)

The loan register now prioritises **Total to settle**, with unpaid principal and scheduled interest shown separately. Borrowed, paid and current-month interest totals remain available, and a help control explains the type-specific payable balance. Summary amounts use the existing balance service and reflect the current filters.

- An aligned desktop register shows the loan code, lender, status, original principal, full settlement balance, next due/settled date and quick actions. On phones, each loan becomes a labelled list item with touch-sized controls.
- Search, the shared party selector, borrowing-date filters, status links and pagination remain available. Mobile lender/date filters expand on request. Switching status resets pagination, and empty filtered results offer Clear filters.
- Details expand with keyboard-accessible buttons that announce their state. Expand/collapse all stays in sync with individual rows. Detail panels retain principal repayments, interest, facility terms, bank, guarantor, collateral, description, status and delete actions.
- Light and dark themes use the shared typography, violet action, balance, border and status tokens. New scoped assets are `public/loan-index.css` and `public/loan-index.js`. Create/edit forms, payment rules and database records are unchanged.

Design direction: Taste's achromatic-ledger composition (seed 1771249544), adapted to the established Apptimus palette and typography. The main question is “How much remains to settle, and which loan needs attention?” The principal/interest split is the identifying financial detail. No decorative charts or screenshots were added.

Validation: 60 loan tests / 646 assertions; browser checks at 320, 390, 768, 1024 and 1440 pixels with synthetic local loans, including large amounts and long lender names. Checked light/dark themes, search, empty results, status changes from page two, lender picker, quick edit and expand-all. Browser geometry checks found no page-level horizontal overflow.
