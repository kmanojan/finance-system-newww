-- Add reconciliation links only. Existing payroll amounts and ledger entries are untouched.
BEGIN;
CREATE TABLE IF NOT EXISTS public.payroll_historical_loan_reconciliations (
    payroll_item_id BIGINT PRIMARY KEY REFERENCES public.payroll_items(id) ON DELETE RESTRICT,
    journal_line_id BIGINT NOT NULL REFERENCES public.journal_entry_lines(id) ON DELETE RESTRICT,
    source_reference VARCHAR(255) NOT NULL,
    balance_cents BIGINT NOT NULL,
    deduction_cents BIGINT NOT NULL,
    verified_by BIGINT NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);
CREATE INDEX IF NOT EXISTS payroll_historical_loan_reconciliations_journal_line_id_index
    ON public.payroll_historical_loan_reconciliations(journal_line_id);
ALTER TABLE public.payroll_historical_loan_reconciliations ENABLE ROW LEVEL SECURITY;
INSERT INTO public.migrations(migration, batch)
    SELECT '2026_11_09_000001_create_payroll_historical_loan_reconciliations',
        (SELECT COALESCE(MAX(batch), 0) + 1 FROM public.migrations)
    WHERE NOT EXISTS (
        SELECT 1 FROM public.migrations WHERE migration = '2026_11_09_000001_create_payroll_historical_loan_reconciliations'
    );
COMMIT;
SELECT relrowsecurity AS row_security_enabled
    FROM pg_class WHERE oid = 'public.payroll_historical_loan_reconciliations'::regclass;
