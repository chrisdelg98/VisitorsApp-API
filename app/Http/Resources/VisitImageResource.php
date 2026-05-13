<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'visit_id'   => $this->visit_id,
            'type'       => $this->type,
            // Authenticated URL — actual bytes served by ImageController@show.
            'url'        => route('visits.images.show', [
                'visit' => $this->visit_id,
                'type'  => $this->type,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
