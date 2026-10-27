<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransitHub extends Model
{
    protected $fillable = ['name'];

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'current_hub_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ShipmentStatusLog::class);
    }
}
