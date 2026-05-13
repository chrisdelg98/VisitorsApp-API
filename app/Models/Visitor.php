<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'first_name',
        'last_name',
        'document_number',
        'document_type',
        'email',
        'phone',
        'company',
    ];

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function latestVisit()
    {
        return $this->hasOne(Visit::class)->latestOfMany('check_in');
    }
}
