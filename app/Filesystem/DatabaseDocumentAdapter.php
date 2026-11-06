<?php

namespace App\Filesystem;

use Illuminate\Support\Facades\DB;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;

/** Private durable documents for serverless deployments; never exposes public URLs. */
class DatabaseDocumentAdapter implements FilesystemAdapter
{
    private function query()
    {
        return DB::table('private_document_files');
    }

    public function fileExists(string $path): bool
    {
        return $this->query()->where('path', $path)->exists();
    }

    public function directoryExists(string $path): bool
    {
        return $this->query()->where('path', 'like', rtrim($path, '/').'/%')->exists();
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->query()->updateOrInsert(['path' => $path], [
            'contents' => base64_encode($contents), 'size' => strlen($contents),
            'mime_type' => (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream',
            'modified_at' => time(),
        ]);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        $value = $this->query()->where('path', $path)->value('contents');
        if ($value === null) {
            throw UnableToReadFile::fromLocation($path);
        }

        return base64_decode($value, true);
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->query()->where('path', $path)->delete();
    }

    public function deleteDirectory(string $path): void
    {
        foreach ($this->listContents($path, true) as $file) {
            $this->delete($file->path());
        }
    }

    public function createDirectory(string $path, Config $config): void {}

    public function setVisibility(string $path, string $visibility): void
    {
        if ($visibility !== 'private') {
            throw UnableToSetVisibility::atLocation($path, 'Documents are private.');
        }
    }

    private function attributes(string $path): FileAttributes
    {
        $row = $this->query()->where('path', $path)->first(['path', 'size', 'mime_type', 'modified_at']);
        if (! $row) {
            throw UnableToReadFile::fromLocation($path);
        }

        return new FileAttributes($path, (int) $row->size, 'private', (int) $row->modified_at, $row->mime_type);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->attributes($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->attributes($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->attributes($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->attributes($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = $path === '' ? '' : rtrim($path, '/').'/';
        $directories = [];
        foreach ($this->query()->select(['path', 'size', 'mime_type', 'modified_at'])->orderBy('path')->cursor() as $row) {
            if (! str_starts_with($row->path, $prefix)) {
                continue;
            }
            $relative = substr($row->path, strlen($prefix));
            if (! $deep && str_contains($relative, '/')) {
                $directory = $prefix.explode('/', $relative)[0];
                if (! isset($directories[$directory])) {
                    $directories[$directory] = true;
                    yield new DirectoryAttributes($directory);
                }

                continue;
            }
            yield new FileAttributes($row->path, (int) $row->size, 'private', (int) $row->modified_at, $row->mime_type);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        DB::transaction(function () use ($source, $destination, $config) {
            $this->copy($source, $destination, $config);
            if ($source !== $destination) {
                $this->delete($source);
            }
        });
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->write($destination, $this->read($source), $config);
    }
}
