<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Station extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'location',
        'code',
        'api_key',
        'pin',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
        'pin',
    ];

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
