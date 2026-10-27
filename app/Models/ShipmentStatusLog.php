<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentStatusLog extends Model
{
    protected $fillable = ['parcel_id', 'transit_hub_id', 'sequence', 'status', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function transitHub(): BelongsTo
    {
        return $this->belongsTo(TransitHub::class);
    }
}
