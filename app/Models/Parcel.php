<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parcel extends Model
{
    protected $fillable = [
        'tracking_number',
        'sender_customer_id',
        'recipient_customer_id',
        'current_hub_id',
        'parcel_weight_kg',
        'delivery_type',
        'shipping_cost',
    ];

    protected function casts(): array
    {
        return [
            'parcel_weight_kg' => 'decimal:3',
            'shipping_cost' => 'decimal:2',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'sender_customer_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'recipient_customer_id');
    }

    public function currentHub(): BelongsTo
    {
        return $this->belongsTo(TransitHub::class, 'current_hub_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ShipmentStatusLog::class)->orderBy('sequence');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CourierPayment::class);
    }
}
