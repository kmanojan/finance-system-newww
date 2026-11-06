<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function download(int $id)
    {
        $attachment = DB::table('attachments')->find($id);
        abort_unless($attachment, 404);
        $disk = Storage::disk(config('filesystems.documents_disk', 'local'));
        if (! $disk->exists($attachment->file_path)) {
            $disk = Storage::disk('public');
        }
        abort_unless($disk->exists($attachment->file_path), 404);

        return $disk->download($attachment->file_path, $attachment->file_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
