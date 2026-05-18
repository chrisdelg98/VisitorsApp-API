<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-scoped station representation that exposes the api_key.
 * Only used inside /v1/admin/* routes.
 */
class AdminStationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'location'      => $this->location,
            'code'          => $this->code,
            'api_key'       => $this->getAttribute('api_key'),
            'is_active'     => (bool) $this->is_active,
            'is_registered' => $this->registered_at !== null,
            'registered_at' => $this->registered_at?->toIso8601String(),
            'device_model'  => $this->device_model,
            'registered_ip' => $this->registered_ip,
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
