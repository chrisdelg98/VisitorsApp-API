<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StationDeviceLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'device_imei'       => $this->device_imei,
            'device_android_id' => $this->device_android_id,
            'device_model'      => $this->device_model,
            'registered_ip'     => $this->registered_ip,
            'registered_at'     => $this->registered_at?->toIso8601String(),
            'unregistered_by'   => $this->unregistered_by,
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}
