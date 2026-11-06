-- Retained, access-restricted snapshot of the existing public application data.
BEGIN ISOLATION LEVEL REPEATABLE READ;
CREATE SCHEMA IF NOT EXISTS finance_backup_20261106;
REVOKE ALL ON SCHEMA finance_backup_20261106 FROM PUBLIC, anon, authenticated;
DO $$ DECLARE t record; BEGIN
 FOR t IN SELECT tablename FROM pg_tables WHERE schemaname='public' LOOP
  EXECUTE format('CREATE TABLE IF NOT EXISTS finance_backup_20261106.%I AS TABLE public.%I',t.tablename,t.tablename);
  EXECUTE format('REVOKE ALL ON finance_backup_20261106.%I FROM PUBLIC, anon, authenticated',t.tablename);
 END LOOP;
END $$;
COMMIT;
SELECT count(*) AS backed_up_tables FROM pg_tables WHERE schemaname='finance_backup_20261106';
