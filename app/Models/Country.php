<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'flag_emoji',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
