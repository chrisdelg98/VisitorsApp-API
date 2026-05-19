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
        'country_id',
        'latitude',
        'longitude',
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
        return hash_hmac('sha256', $pin, (string) config('app.key'));
    }

    public function country(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function deviceLogs(): HasMany
    {
        return $this->hasMany(StationDeviceLog::class)->latest();
    }

    /**
     * Save current device data to log and clear device registration.
     * $by: 'device_logout' | 'admin_reset'
     */
    public function unregisterDevice(string $by): void
    {
        if ($this->registered_at === null) {
            return;
        }

        $snapshot = [
            'station_id'        => $this->id,
            'device_imei'       => $this->device_imei,
            'device_android_id' => $this->device_android_id,
            'device_model'      => $this->device_model,
            'registered_ip'     => $this->registered_ip,
            'registered_at'     => $this->registered_at,
            'unregistered_by'   => $by,
        ];

        // Clear first — logout must always succeed even if the log table is missing
        $this->update([
            'device_imei'       => null,
            'device_android_id' => null,
            'device_model'      => null,
            'registered_ip'     => null,
            'registered_at'     => null,
        ]);

        try {
            StationDeviceLog::create($snapshot);
        } catch (\Throwable) {
            // Non-critical — registration is already cleared above
        }
    }
}
