<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tablet-facing shape of a published template. The owning document_type is
 * flattened into the row so the device resolves everything in one call and
 * never needs a second lookup table.
 */
class DocumentTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'document_type_id'  => $this->document_type_id,
            // --- embedded document_type ---
            'country_id'        => $this->documentType?->country_id,
            'code'              => $this->documentType?->code,
            'name'              => $this->documentType?->name,
            'document_kind'     => $this->documentType?->document_kind,
            'subdivision'       => $this->documentType?->subdivision,
            // --- template itself ---
            'version'           => $this->version,
            'side'              => $this->side,
            'extraction_method' => $this->extraction_method,
            'signature'         => $this->signature ?? [],
            'fields'            => $this->fields ?? [],
            'sample_meta'       => $this->sample_meta,
            'published_at'      => $this->published_at?->toIso8601String(),
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }
}
