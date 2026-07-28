<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin view of a release: everything the tablet sees plus the governance
 * fields (status, who uploaded it, where the binary lives).
 *
 * @mixin \App\Models\AppRelease
 */
class AdminAppReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'platform'                   => $this->platform,
            'version_code'               => $this->version_code,
            'version_name'               => $this->version_name,
            'status'                     => $this->status,
            'release_notes'              => $this->release_notes,
            'min_supported_version_code' => $this->min_supported_version_code,
            'is_critical'                => $this->is_critical,
            'file_name'                  => $this->file_name,
            'file_size'                  => $this->file_size,
            'file_hash'                  => $this->file_hash,
            'download_url'               => route('app.releases.download', ['appRelease' => $this->id]),
            'published_at'               => $this->published_at?->toIso8601String(),
            'created_by'                 => $this->whenLoaded('createdBy', fn () => [
                'id'    => $this->createdBy?->id,
                'name'  => $this->createdBy?->name,
                'email' => $this->createdBy?->email,
            ]),
            'created_at'                 => $this->created_at?->toIso8601String(),
            'updated_at'                 => $this->updated_at?->toIso8601String(),
        ];
    }
}
