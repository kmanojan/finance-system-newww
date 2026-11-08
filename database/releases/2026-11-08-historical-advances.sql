-- Add source links only. Does not create payments or change any existing payroll.
BEGIN;
CREATE TABLE IF NOT EXISTS public.payroll_historical_advance_links (
 id BIGSERIAL PRIMARY KEY,
 payroll_item_id BIGINT NOT NULL UNIQUE REFERENCES public.payroll_items(id) ON DELETE RESTRICT,
 payroll_payment_id BIGINT NOT NULL UNIQUE REFERENCES public.payroll_payments(id) ON DELETE RESTRICT,
 source_sha256 VARCHAR(64) NOT NULL,
 source_sheet VARCHAR(100) NOT NULL,
 source_row INTEGER NOT NULL,
 source_amount_cents BIGINT NOT NULL,
 created_by BIGINT NOT NULL,
 created_at TIMESTAMP(0) WITHOUT TIME ZONE,
 updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);
ALTER TABLE public.payroll_historical_advance_links ENABLE ROW LEVEL SECURITY;
INSERT INTO public.migrations(migration,batch)
 SELECT '2026_11_08_000002_create_historical_salary_advance_links', (SELECT COALESCE(MAX(batch),0)+1 FROM public.migrations)
 WHERE NOT EXISTS (SELECT 1 FROM public.migrations WHERE migration='2026_11_08_000002_create_historical_salary_advance_links');
COMMIT;
SELECT (SELECT relrowsecurity FROM pg_class WHERE oid='public.payroll_historical_advance_links'::regclass) AS row_security_enabled,
 (SELECT count(*) FROM public.payroll_historical_advance_links) AS historical_links,
 (SELECT count(*) FROM public.payroll_payments WHERE kind='advance') AS existing_advances,
 (SELECT count(*) FROM public.payroll_runs) AS payroll_runs;
