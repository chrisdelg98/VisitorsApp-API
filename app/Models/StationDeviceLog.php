<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StationDeviceLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'station_id',
        'device_imei',
        'device_android_id',
        'device_model',
        'registered_ip',
        'registered_at',
        'unregistered_by',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }
}
