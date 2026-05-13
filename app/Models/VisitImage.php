<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitImage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'visit_id',
        'type',
        'file_path',
        'file_hash',
    ];

    protected $hidden = [
        'file_path',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
