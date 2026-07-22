<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of identity document the tablets can read (DUI, DPI, passport...).
 * Written by the admin portal via Eloquent; the API only reads it for sync.
 */
class DocumentType extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'country_id',
        'code',
        'name',
        'document_kind',
        'subdivision',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class);
    }
}
