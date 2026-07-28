<?php

namespace App\Services;

use App\Models\AppRelease;
use App\Support\ApkFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppReleaseService
{
    /**
     * Move an uploaded APK onto the private disk and return the columns that
     * describe it. Hashing is done on the temp file before the move so we never
     * read a 150 MB binary twice.
     *
     * @return array{file_path: string, file_name: string, file_hash: string, file_size: int}
     */
    public function storeApk(UploadedFile $file, string $platform, int $versionCode): array
    {
        $hash = (string) hash_file('sha256', $file->getRealPath());
        $size = (int) $file->getSize();

        $path = $file->storeAs(
            $this->directoryFor($platform),
            Str::uuid()->toString().'.apk',
            $this->disk()
        );

        return [
            'file_path' => $path,
            'file_name' => $this->releaseFileName($versionCode),
            'file_hash' => $hash,
            'file_size' => $size,
        ];
    }

    /**
     * APKs sitting in the staging directory, newest first.
     *
     * `is_valid_apk` is how an operator tells a finished transfer from one
     * still in flight — a cut upload has no ZIP central directory. Checking it
     * only reads the archive index, so listing stays cheap even for 150 MB
     * files; the content hash is computed once, later, at registration.
     *
     * @return list<array{file_name: string, size: int, is_valid_apk: bool, modified_at: string}>
     */
    public function stagedFiles(int $limit = 20): array
    {
        $disk    = Storage::disk($this->disk());
        $staging = $this->stagingPath();

        if (! $disk->exists($staging)) {
            $disk->makeDirectory($staging);

            return [];
        }

        $files = [];

        foreach ($disk->files($staging) as $path) {
            if (! Str::endsWith(Str::lower($path), '.apk')) {
                continue;
            }

            $absolute = $disk->path($path);

            $files[] = [
                'file_name'    => basename($path),
                'size'         => (int) $disk->size($path),
                'is_valid_apk' => ApkFile::isValid($absolute),
                'modified_at'  => now()->setTimestamp((int) $disk->lastModified($path))->toIso8601String(),
                '_sort'        => (int) $disk->lastModified($path),
            ];
        }

        usort($files, fn ($a, $b) => $b['_sort'] <=> $a['_sort']);

        return array_map(
            fn (array $file) => \Illuminate\Support\Arr::except($file, '_sort'),
            array_slice($files, 0, $limit)
        );
    }

    /**
     * Absolute path of a staged file, or null when it does not resolve to a
     * regular file directly inside the staging directory.
     *
     * This is the security boundary of the whole staging feature: without the
     * containment check, a crafted `staged_file` would let an admin register
     * (and then publish) any file the web user can read. Names are basenames
     * only, symlinks and subdirectories are refused.
     */
    public function resolveStagedPath(string $fileName): ?string
    {
        if ($fileName === '' || $fileName !== basename($fileName)) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9._-]+$/', $fileName) !== 1 || Str::startsWith($fileName, '.')) {
            return null;
        }

        if (! Str::endsWith(Str::lower($fileName), '.apk')) {
            return null;
        }

        $disk    = Storage::disk($this->disk());
        $staging = realpath($disk->path($this->stagingPath()));

        if ($staging === false) {
            return null;
        }

        $candidate = realpath($staging.DIRECTORY_SEPARATOR.$fileName);

        if ($candidate === false || is_link($staging.DIRECTORY_SEPARATOR.$fileName) || ! is_file($candidate)) {
            return null;
        }

        // realpath() resolved both sides, so a path that still starts with the
        // staging directory cannot have escaped it.
        return Str::startsWith($candidate, $staging.DIRECTORY_SEPARATOR) ? $candidate : null;
    }

    /**
     * Adopt a staged file as a release binary: move it out of staging into the
     * canonical path so it can never be overwritten by the next SFTP upload.
     *
     * @return array{file_path: string, file_name: string, file_hash: string, file_size: int}
     */
    public function promoteStaged(string $fileName, string $platform, int $versionCode, string $hash): array
    {
        $disk   = Storage::disk($this->disk());
        $from   = $this->stagingPath().'/'.$fileName;
        $to     = $this->directoryFor($platform).'/'.Str::uuid()->toString().'.apk';
        $size   = (int) $disk->size($from);

        $disk->move($from, $to);

        return [
            'file_path' => $to,
            'file_name' => $this->releaseFileName($versionCode),
            'file_hash' => $hash,
            'file_size' => $size,
        ];
    }

    /** Remove the binary of a release that is being deleted. */
    public function deleteApk(AppRelease $release): void
    {
        Storage::disk($this->disk())->delete($release->file_path);
    }

    /** Absolute path admins need for the SFTP upload, shown by the staged listing. */
    public function stagingAbsolutePath(): string
    {
        return Storage::disk($this->disk())->path($this->stagingPath());
    }

    private function directoryFor(string $platform): string
    {
        return 'app-releases/'.$platform;
    }

    private function releaseFileName(int $versionCode): string
    {
        return 'visitors-app-v'.$versionCode.'.apk';
    }

    private function stagingPath(): string
    {
        return trim((string) config('app_updates.staging_path'), '/');
    }

    private function disk(): string
    {
        return (string) config('app_updates.disk');
    }
}
