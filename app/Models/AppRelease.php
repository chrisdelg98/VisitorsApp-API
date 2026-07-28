<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per shipped build of the tablet app. Only `published` rows are ever
 * offered for download; a bad release is rolled back by moving it to
 * `deprecated`, which makes the previous published build the latest again.
 */
class AppRelease extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'platform',
        'version_code',
        'version_name',
        'status',
        'file_path',
        'file_name',
        'file_hash',
        'file_size',
        'release_notes',
        'min_supported_version_code',
        'is_critical',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'version_code'               => 'integer',
        'min_supported_version_code' => 'integer',
        'file_size'                  => 'integer',
        'is_critical'                => 'boolean',
        'published_at'               => 'datetime',
    ];

    /** Releases the download endpoint is allowed to serve. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * The build a device should be running: the highest published version_code
     * for the platform. Deliberately not "most recently published" — that would
     * make a re-published old build look newer than the current one.
     */
    public static function latestPublished(string $platform): ?self
    {
        return static::query()
            ->where('platform', $platform)
            ->published()
            ->orderByDesc('version_code')
            ->first();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
