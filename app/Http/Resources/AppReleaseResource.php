<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tablet-facing shape of the latest published build.
 *
 * `download_url` points at our own authenticated endpoint, never at a public
 * path: the binary lives on the private disk like every other file we store.
 * `file_hash` lets the device verify the APK before handing it to the package
 * installer, and `file_size` lets it show real download progress.
 *
 * @mixin \App\Models\AppRelease
 */
class AppReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'version_code'               => $this->version_code,
            'version_name'               => $this->version_name,
            'release_notes'              => $this->release_notes,
            'download_url'               => route('app.releases.download', ['appRelease' => $this->id]),
            'file_name'                  => $this->file_name,
            'file_size'                  => $this->file_size,
            'file_hash'                  => $this->file_hash,
            'min_supported_version_code' => $this->min_supported_version_code,
            'is_critical'                => $this->is_critical,
            'published_at'               => $this->published_at?->toIso8601String(),
        ];
    }
}
