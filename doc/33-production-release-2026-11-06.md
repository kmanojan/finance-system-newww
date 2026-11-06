# Production release — 6 November 2026

Target: GitHub `kmanojan/finance-system-newww`, Vercel `finance-system-newww`, Supabase project `ztqbhrrzpxnndrrchfdt`.

The release includes the shared design system, loan balances/flow/attachments/search, complete monthly payroll and scheduled advances, employee party linking, selectors and the finance integrity/access-control fixes developed in this workspace.

Before deployment, a restricted snapshot of all 68 existing public application tables was created in `finance_backup_20261106`. The reviewed SQL in `database/releases/2026-11-06-supabase.sql` was applied in a transaction. It adds the compatible legacy columns, loan type/flow data, payroll tables, salary-component entry modes, scheduled recovery records, employee party links and durable private document storage. The migration linked 47 employees to 47 parties. Existing loans, transactions, journals, salary values and prior parties were compared with the snapshot and unchanged. New payroll/private-document tables have RLS enabled and no anon/authenticated access.

The database changes are additive. A code rollback should retain these tables and the private documents; do not run destructive rollback migrations. The backup is a data snapshot, not a replacement for managed backup/PITR or an export of the original database schema and grants.

Validation before push: 113 PHPUnit tests / 991 assertions passed, production assets built successfully, and the changed source passed whitespace checks. Browser checks during implementation used isolated fixtures and no screenshots.

The payroll/loan combined party payable report remains deferred. Scheduler/queue infrastructure is not created by a Git push. Existing historical finance entries are not automatically reposted or recalculated by this release.

## Production verification and recovery

Commit `4ed8af8b4b6af8614cd3f9a1f271462e46677179` passed GitHub checks and Vercel build/deployment (`dpl_BLgaYbLjbcBZ9FMwfX1geVw5Ss2w`). Runtime verification found the pre-existing Vercel APP_KEY secret has an unsupported encryption-key length. The previous release had supplied a valid key in its deployment configuration; removing that embedded key exposed the invalid project secret. Do not restore hardcoded secrets to the repository.

The production alias `https://apptimus-finance.vercel.app` was restored with Vercel Instant Rollback to deployment `dpl_D3oaCeKN8AaB7LMo54xSM4b3Er6e` (commit `4763f3a`). Verified HTTP 200 for `/up` and `/login`, and the authenticated dashboard rendered on the production alias. Database migrations and the latest GitHub code are retained. This was a temporary recovery; the promotion below supersedes it.

The key issue was subsequently resolved outside the agent’s credential-entry flow. Deployment `dpl_FhM6P1Pr3fgfnZm4WttebJcx2rvL` (commit `0c9479f`) rendered the modern login successfully and was promoted using Manage Rollback, removing the temporary rollback and assigning the production alias to the latest release. Do not use a login password as APP_KEY or rotate it without checking encrypted-data compatibility.

The frontend build command now runs Vite and copies public assets without invoking PHP. Optional optimized font fallbacks are explicitly disabled, matching the prior fallback behavior without the missing-fontaine warning. The production build passed in a Node-only PATH where PHP was unavailable.

Final production checks on 7 November 2026: `/up`, the redesigned `/login`, and its first-party CSS/JavaScript assets returned HTTP 200. The authenticated dashboard, monthly payroll creation screen, loan list, and loan details rendered on `https://apptimus-finance.vercel.app`. One loan-list request returned 500 during verification; fresh and normal-URL repeats and the loan detail request succeeded. The available Vercel log retained only the tail of that exception, so its cause was not established. No payroll drafts, payments, or loan records were changed by these smoke checks.
