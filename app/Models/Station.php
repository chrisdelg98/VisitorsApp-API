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
        'pin_lookup',
        'is_active',
        'device_imei',
        'device_android_id',
        'device_model',
        'registered_ip',
        'registered_at',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'registered_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
        'pin',
        'pin_lookup',
    ];

    public static function makePinLookup(string $pin): string
    {
        return hash_hmac('sha256', $pin, config('app.key'));
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
