<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'station_id'              => $this->station_id,
            'visitor_id'              => $this->visitor_id,
            'visitor_type'            => $this->visitor_type,
            'visit_reason'            => $this->visit_reason,
            'visit_reason_custom'     => $this->visit_reason_custom,
            'visiting_person'         => $this->visiting_person,
            'check_in'                => $this->check_in?->toIso8601String(),
            'check_out'               => $this->check_out?->toIso8601String(),
            'status'                  => $this->status,
            'badge_printed'           => (bool) $this->badge_printed,
            'notes'                   => $this->notes,
            // Re-entry traceability (null for regular visits)
            'original_visit_id'       => $this->original_visit_id,
            'reentry_from_station_id' => $this->reentry_from_station_id,
            // Relations (loaded on demand)
            'visitor'                 => new VisitorResource($this->whenLoaded('visitor')),
            'station'                 => new StationResource($this->whenLoaded('station')),
            'images'                  => VisitImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
