# Production release — 6 November 2026

Target: GitHub `kmanojan/finance-system-newww`, Vercel `finance-system-newww`, Supabase project `ztqbhrrzpxnndrrchfdt`.

The release includes the shared design system, loan balances/flow/attachments/search, complete monthly payroll and scheduled advances, employee party linking, selectors and the finance integrity/access-control fixes developed in this workspace.

Before deployment, a restricted snapshot of all 68 existing public application tables was created in `finance_backup_20261106`. The reviewed SQL in `database/releases/2026-11-06-supabase.sql` was applied in a transaction. It adds the compatible legacy columns, loan type/flow data, payroll tables, salary-component entry modes, scheduled recovery records, employee party links and durable private document storage. The migration linked 47 employees to 47 parties. Existing loans, transactions, journals, salary values and prior parties were compared with the snapshot and unchanged. New payroll/private-document tables have RLS enabled and no anon/authenticated access.

The database changes are additive. A code rollback should retain these tables and the private documents; do not run destructive rollback migrations. The backup is a data snapshot, not a replacement for managed backup/PITR or an export of the original database schema and grants.

Validation before push: 113 PHPUnit tests / 991 assertions passed, production assets built successfully, and the changed source passed whitespace checks. Browser checks during implementation used isolated fixtures and no screenshots.

The payroll/loan combined party payable report remains deferred. Scheduler/queue infrastructure is not created by a Git push. Existing historical finance entries are not automatically reposted or recalculated by this release.
