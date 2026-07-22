<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per document version x side. `signature` says how to recognize the
 * document, `fields` says how to extract each value (normalized 0..1 coords).
 *
 * Only rows with status = 'published' are ever served to tablets.
 */
class DocumentTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_type_id',
        'version',
        'side',
        'extraction_method',
        'status',
        'signature',
        'fields',
        'sample_meta',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'signature'    => 'array',
        'fields'       => 'array',
        'sample_meta'  => 'array',
        'published_at' => 'datetime',
    ];

    /** Templates the tablet catalog is allowed to serve. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
