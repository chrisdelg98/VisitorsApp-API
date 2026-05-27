<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visit extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'station_id',
        'visitor_id',
        'visitor_type',
        'visit_reason',
        'visit_reason_custom',
        'visiting_person',
        'check_in',
        'check_out',
        'status',
        'badge_printed',
        'notes',
        'original_visit_id',
        'reentry_from_station_id',
        'reentry_count',
        'last_reentry_at',
        'checkout_type',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'badge_printed' => 'boolean',
        'last_reentry_at' => 'datetime',
        'reentry_count' => 'integer',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(VisitImage::class);
    }

    public function originalVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'original_visit_id');
    }

    public function reentryFromStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'reentry_from_station_id');
    }
}
