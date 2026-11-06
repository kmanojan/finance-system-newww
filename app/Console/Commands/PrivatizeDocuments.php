<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PrivatizeDocuments extends Command
{
    protected $signature = 'finance:privatize-documents {--apply : Copy, verify, then remove referenced public originals}';

    protected $description = 'Preview or migrate referenced finance documents from public to private storage';

    public function handle(): int
    {
        $name = config('filesystems.documents_disk', 'local');
        if ($name === 'public') {
            $this->error('DOCUMENTS_DISK must be private.');

            return self::FAILURE;
        }
        $source = Storage::disk('public');
        $target = Storage::disk($name);
        $paths = DB::table('attachments')->whereNotNull('file_path')->pluck('file_path')->merge(DB::table('project_documents')->whereNotNull('file_path')->pluck('file_path'))->unique();
        $count = 0;
        foreach ($paths as $path) {
            if (! str_starts_with($path, 'attachments/') || str_contains($path, '..')) {
                $this->warn('Skipped an unexpected document path; inspect legacy storage manually.');

                continue;
            }
            if (! $source->exists($path)) {
                continue;
            }
            $count++;
            $this->line(($this->option('apply') ? 'Migrating: ' : 'Would migrate: ').$path);
            if (! $this->option('apply')) {
                continue;
            }
            $contents = $source->get($path);
            if ($target->exists($path) && hash('sha256', $target->get($path)) !== hash('sha256', $contents)) {
                $this->error('Private destination differs; public original retained.');

                return self::FAILURE;
            }
            if (! $target->exists($path) && ! $target->put($path, $contents, 'private')) {
                $this->error('Copy failed; public original retained.');

                return self::FAILURE;
            }
            if (hash('sha256', $target->get($path)) !== hash('sha256', $contents)) {
                $this->error('Verification failed; public original retained.');

                return self::FAILURE;
            }
            if (! $source->delete($path)) {
                $this->error('Private copy verified, but public original could not be removed.');

                return self::FAILURE;
            }
        }
        $this->info($count.' referenced public files '.($this->option('apply') ? 'migrated.' : 'found. No files changed. Use --apply after reviewing the list and taking a backup.'));

        return self::SUCCESS;
    }
}
