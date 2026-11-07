-- Apply in the production Supabase SQL editor before deploying statutory tracking.
-- Adds an empty, RLS-protected event table; existing payrolls, payments and salaries are unchanged.
BEGIN;
CREATE TABLE IF NOT EXISTS public.payroll_statutory_events (
 id BIGSERIAL PRIMARY KEY,
 payroll_run_id BIGINT NOT NULL REFERENCES public.payroll_runs(id) ON DELETE RESTRICT,
 kind VARCHAR(10) NOT NULL,
 action VARCHAR(20) NOT NULL,
 event_on DATE NOT NULL,
 prepared_on DATE NOT NULL,
 payment_due_on DATE NOT NULL,
 prepared_amount_cents BIGINT NOT NULL,
 reference VARCHAR(255),
 notes TEXT,
 evidence_path VARCHAR(255),
 evidence_name VARCHAR(255),
 request_key VARCHAR(255) NOT NULL,
 created_by BIGINT NOT NULL,
 created_at TIMESTAMP(0) WITHOUT TIME ZONE,
 updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
 CONSTRAINT payroll_statutory_events_request_key_unique UNIQUE (request_key)
);
ALTER TABLE public.payroll_statutory_events ENABLE ROW LEVEL SECURITY;
CREATE INDEX IF NOT EXISTS payroll_statutory_run_kind_index ON public.payroll_statutory_events(payroll_run_id, kind, id);
INSERT INTO public.migrations(migration,batch)
 SELECT '2026_11_08_000001_create_payroll_statutory_events', (SELECT COALESCE(MAX(batch),0)+1 FROM public.migrations)
 WHERE NOT EXISTS (SELECT 1 FROM public.migrations WHERE migration='2026_11_08_000001_create_payroll_statutory_events');
COMMIT;
SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='payroll_statutory_events') AS statutory_table_ready,
 EXISTS (SELECT 1 FROM public.migrations WHERE migration='2026_11_08_000001_create_payroll_statutory_events') AS migration_recorded,
 (SELECT relrowsecurity FROM pg_class WHERE oid='public.payroll_statutory_events'::regclass) AS row_security_enabled,
 (SELECT COUNT(*) FROM public.payroll_statutory_events) AS events_created,
 (SELECT COUNT(*) FROM public.payroll_runs) AS existing_payroll_runs,
 (SELECT COUNT(*) FROM public.payroll_items) AS existing_payroll_items;
