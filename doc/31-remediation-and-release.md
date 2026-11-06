# Finance remediation and release notes — 2026-11-06

This change implements the shared UI system and a broad set of correctness, access-control and workflow fixes. It is a local implementation, not a production deployment or a claim that every feature in the specification catalogue is finished.

## Implemented

| Area | Result |
|---|---|
| Shared UI | Neutral light/dark surfaces, labelled navigation, consistent controls, readable typography, tabular money, mobile table labels, responsive summaries and visible focus states. Dashboard and project overview were simplified around balances and work. |
| Accessibility | Browser zoom restored; skip link; dialog roles, focus entry/trapping/return, Escape, close labels, scroll locking and reduced motion. |
| Access | Removed web migration endpoints; inactive sessions rejected; central write/approval/admin boundaries; parent-child checks on nested project/loan routes; login and shared-password throttling. Staff/viewer are read-only; managers/accountants can write operational records; managers/admins approve; only admins manage users, settings and master-data writes. |
| Atomic writes | Protected financial commands run within a database transaction. Failed commands roll back their database changes. File storage is outside the database transaction. |
| Schema | Additive migration aligns invoice discounts, sharing, visits, attachment mappings, activity history, journal fields, payment modes, vendor payments, reminder snoozes and milestones. |
| Ledger | Balanced, positive, single-currency journal validation, real account references, transaction/invoice posting, reversals and audit records. Financing is separated from operating cash movement. |
| Payments | Positive amounts, matching payment-mode totals, valid invoice allocation scope, outstanding limits, project currency checks and bank currency/company checks in posting paths. |
| Invoices | Draft edit/duplicate, pagination/search, guarded lifecycle transitions, deletion limited to unpaid drafts, preserved rendering snapshots and automatic posting on issue/cancellation. |
| Loans | Validated amounts, repayment limits, atomic activation, status changes routed through activation, closed-balance requirements and protection of posted financial terms. |
| Reporting | Ledger-based P&L/balance sheet, explicit historical-posting warning, historical FX lookup, partial-payment aging, client health, client-statement opening balance, report exports and several date/currency corrections. Missing exchange rates are errors, not invented conversions. |
| Operational screens | HTML chart of accounts, financial years and purchase orders; partial vendor payments; CSV/OFX/QFX statement import; manual match/unmatch and conservative auto-match. |
| Period close | Overlap protection, closed-period enforcement, logged period status changes, year-end transfer to retained earnings and prevention of duplicate year closing/reopening closed-year periods. |
| Project operations | Fixed/percentage milestones, pending milestone edit/delete/skip reason, cost-allocation edit/delete and posting of project commission payments. Cost allocations remain costing facts rather than cash transactions. |
| Reminders/schedules | Recurring invoices scheduled daily with overlap protection and approval state; snoozed custom reminders resume automatically. |
| Documents/sharing | New finance documents use private storage; authenticated downloads; public invoice/document links enforce token, expiry, password, project scope and download permission; client-private documents excluded. |
| Maintenance | Component reference at `/design-system`, updated design rules, isolated regression tests, CI workflow, ledger diagnostic and a dry-run-first legacy document migration command. |

The former aggregate party-settlement writer could update balances without the corresponding bank/ledger entries. Its UI now takes users to source-document payment workflows. The old POST endpoint rejects aggregate writes with an explanation.

## Validation

- PHPUnit: 51 tests, 267 assertions at the final full run (original suite: 25 tests).
- HTTP render checks: all 50 established pages returned HTML successfully in an isolated SQLite review database; search/design-system were also browser checked.
- Report exports: all 14 report types returned successful Excel and PDF responses (28 combinations).
- Browser review: 51 routes captured at 1440px and 390px. Dashboard, project overview and transaction ledger also inspected at 768px. Key screens reviewed in both themes.
- No horizontal document overflow or missing visible static-table cell labels in the final key-screen checks. Dialog focus entry, body lock, Escape and focus return passed.
- The three detailed inspector runs have zero FAIL findings. Short synthetic data queues still generate empty-panel warnings on dashboard/project; they are not filled with fictional business data.
- PHP formatting and source linting are run locally. CI has been added but has not run on a remote provider. No production or MySQL integration run was performed.

These checks are evidence for the tested paths, not exhaustive proof across all legacy workflows, integrations and production data combinations.

## Deployment and data gates

1. Back up the database and uploaded documents. Deploy first to staging with a production-shaped database copy and realistic row counts.
2. Run `php artisan migrate --force` using the release process. Never expose migrations through a browser route. Clear/rebuild route and view caches as part of deployment (`php artisan optimize:clear`, then the deployment's normal cache commands).
3. Configure `DOCUMENTS_DISK=local` with persistent private storage, or a private S3-compatible disk. Serverless ephemeral storage is unsuitable for durable uploads.
4. Run `php artisan finance:privatize-documents` to preview referenced public files. After reviewing the list and backup, run with `--apply`. It copies, verifies content hashes, and removes only verified referenced public originals. Review unreferenced legacy files and CDN/public caches separately. Until this is done, old public URLs may remain reachable.
5. Run `php artisan finance:audit-ledger`. Reconcile historical invoices, project receipts, vendor balances, loans, previous manual journals and bank opening balances before relying on posted-ledger reports. The new code does **not** silently backfill old money movements: that could duplicate existing manual entries. Prepare and review a migration/opening journal using the actual records.
6. Configure the application scheduler (`schedule:run` every minute) and queue workers required by existing integrations. Confirm timezone, persistent cache locks and storage credentials in staging.
7. Review the role policy against real staff accounts. Test a full invoice → approval → receipt → reconciliation flow, vendor payment, loan activation/settlement and year-end close with the deployment database engine.
8. Confirm historical FX coverage. Cross-currency reports now stop on missing rates rather than silently using a fixed conversion.

The compatibility migration intentionally retains financial history/columns on rollback. Rollback planning requires a database backup and release-specific review; `migrate:rollback` is not a complete undo for this release.

## Specification features still requiring separate implementation/acceptance

The documents include a larger product roadmap than this remediation pass. Bulk invoice processing and outbound email delivery/reminder automation, AP credit notes and full payable aging, fixed-asset disposal/automated depreciation scheduling, configurable over-budget approval rules, share-link settings/visit-detail management and notifications, and the full advanced report catalogue are not completed here. Some older aggregate views still need deeper company/department/date/FX consistency work. These should not be represented as implemented simply because a navigation item or database field exists.

Legacy page templates still contain substantial inline CSS/JavaScript. New screens should use the shared system. Extract older detail-tab forms incrementally into Blade components with regression coverage; avoid another broad stylistic rewrite during financial-data migration.
