@props(['label' => 'Attachments (optional)'])
@php
    $inputId = 'loan-attachments-'.uniqid();
    $phpFileLimit = ini_parse_quantity(ini_get('upload_max_filesize'));
    $postLimit = ini_parse_quantity(ini_get('post_max_size'));
    $fileLimit = min(10 * 1024 * 1024, $phpFileLimit > 0 ? $phpFileLimit : PHP_INT_MAX, $postLimit > 0 ? $postLimit : PHP_INT_MAX);
    $formatMb = fn ($bytes) => rtrim(rtrim(number_format($bytes / 1048576, 2, '.', ''), '0'), '.');
@endphp
<label for="{{ $inputId }}" class="form-label">{{ $label }}</label>
<input id="{{ $inputId }}" type="file" name="attachments[]" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" aria-describedby="{{ $inputId }}-help">
<small id="{{ $inputId }}-help" class="text-muted" style="display:block;margin-top:6px;">
    Select multiple PDF, JPG, PNG, WebP or Word files. Up to {{ $formatMb($fileLimit) }} MB per file.@if($postLimit > 0) Keep the total upload below {{ $formatMb($postLimit) }} MB.@endif
    New uploads are added to existing documents.
</small>
