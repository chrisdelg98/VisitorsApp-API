<?php

namespace App\Services;

use App\Models\AppRelease;
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

        $fileName = 'visitors-app-v'.$versionCode.'.apk';
        $path     = $file->storeAs(
            'app-releases/'.$platform,
            Str::uuid()->toString().'.apk',
            $this->disk()
        );

        return [
            'file_path' => $path,
            'file_name' => $fileName,
            'file_hash' => $hash,
            'file_size' => $size,
        ];
    }

    /** Remove the binary of a release that is being deleted. */
    public function deleteApk(AppRelease $release): void
    {
        Storage::disk($this->disk())->delete($release->file_path);
    }

    private function disk(): string
    {
        return (string) config('app_updates.disk');
    }
}
