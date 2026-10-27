<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Parcel;
use App\Models\ShipmentStatusLog;
use App\Models\TransitHub;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourierService
{
    public const DELIVERY_TYPES = ['standard', 'express', 'overnight'];

    public const SHIPMENT_STATUSES = ['received', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'exception'];

    private const BASE_RATE_PER_KG = 5.00;

    private const SPEED_MULTIPLIERS = [
        'standard' => 1.0,
        'express' => 1.5,
        'overnight' => 2.5,
    ];

    public function sanitizeParcelInput(array $data): array
    {
        foreach (['tracking_number', 'sender_name', 'recipient_name', 'current_hub_name', 'initial_status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->cleanText($data[$field]);
            }
        }

        foreach (['sender_address', 'recipient_address'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->cleanAddress($data[$field]);
            }
        }

        if (array_key_exists('tracking_number', $data)) {
            $data['tracking_number'] = mb_strtoupper($data['tracking_number'], 'UTF-8');
        }

        if (array_key_exists('initial_status', $data)) {
            $data['initial_status'] = mb_strtolower($data['initial_status'], 'UTF-8');
        }

        if (array_key_exists('delivery_type', $data)) {
            $data['delivery_type'] = mb_strtolower($this->cleanText($data['delivery_type']), 'UTF-8');
        }

        if (isset($data['parcel_weight_kg']) && is_numeric($data['parcel_weight_kg'])) {
            $data['parcel_weight_kg'] = number_format(round((float) $data['parcel_weight_kg'], 3), 3, '.', '');
        }

        return $data;
    }

    public function sanitizeStatusLogInput(array $data): array
    {
        foreach (['current_hub_name', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->cleanText($data[$field]);
            }
        }

        if (array_key_exists('status', $data)) {
            $data['status'] = mb_strtolower($data['status'], 'UTF-8');
        }

        return $data;
    }

    public function normalizeSearch(mixed $search): ?string
    {
        if (! is_string($search)) {
            return null;
        }

        $search = $this->cleanText($search);

        return $search === '' ? null : $search;
    }

    public function paginateParcels(?string $search = null): LengthAwarePaginator
    {
        $query = Parcel::query()
            ->with(['sender', 'recipient', 'currentHub', 'statusLogs'])
            ->latest();

        if ($search !== null) {
            $query->where(function ($query) use ($search) {
                $query->where('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('sender', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('recipient', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('currentHub', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('statusLogs', fn ($query) => $query->where('status', 'like', "%{$search}%"));
            });
        }

        return $query->paginate(10)->withQueryString();
    }

    public function paginateStatusLogs(?string $search = null): LengthAwarePaginator
    {
        $query = ShipmentStatusLog::query()
            ->with(['parcel', 'transitHub'])
            ->latest('occurred_at');

        if ($search !== null) {
            $query->where(function ($query) use ($search) {
                $query->where('status', 'like', "%{$search}%")
                    ->orWhereHas('parcel', fn ($query) => $query->where('tracking_number', 'like', "%{$search}%"))
                    ->orWhereHas('transitHub', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->paginate(10)->withQueryString();
    }

    public function getStatusLogCreationData(): array
    {
        return [
            'parcels' => Parcel::query()
                ->orderBy('tracking_number')
                ->get(['id', 'tracking_number']),
        ];
    }

    public function findParcel(int $id): Parcel
    {
        return Parcel::query()->findOrFail($id);
    }

    public function findStatusLog(int $id): ShipmentStatusLog
    {
        return ShipmentStatusLog::query()->findOrFail($id);
    }

    public function createParcel(array $data): Parcel
    {
        $data = $this->sanitizeParcelInput($data);
        $this->assertValidStatus($data['initial_status'], 'initial_status');

        return DB::transaction(function () use ($data) {
            $sender = $this->findOrCreateCustomer($data['sender_name'], $data['sender_address']);
            $recipient = $this->findOrCreateCustomer($data['recipient_name'], $data['recipient_address']);
            $hub = $this->findOrCreateHub($data['current_hub_name'] ?? null);
            $deliveryType = $data['delivery_type'];

            $weight = round((float) $data['parcel_weight_kg'], 3);
            $cost = $this->calculateShippingCost($weight, $deliveryType);

            $parcel = Parcel::create([
                'tracking_number' => $data['tracking_number'],
                'sender_customer_id' => $sender->id,
                'recipient_customer_id' => $recipient->id,
                'current_hub_id' => $hub?->id,
                'parcel_weight_kg' => $weight,
                'delivery_type' => $deliveryType,
                'shipping_cost' => $cost,
            ]);

            $parcel->statusLogs()->create([
                'transit_hub_id' => $hub?->id,
                'sequence' => 1,
                'status' => $data['initial_status'],
                'occurred_at' => $this->parseDate($data['status_occurred_at'] ?? null),
            ]);

            $parcel->payments()->create([
                'amount' => $cost,
                'payment_status' => 'pending',
            ]);

            return $parcel;
        });
    }

    public function updateParcel(Parcel $parcel, array $data): Parcel
    {
        $data = $this->sanitizeParcelInput($data);

        return DB::transaction(function () use ($parcel, $data) {
            $sender = $this->findOrCreateCustomer($data['sender_name'], $data['sender_address']);
            $recipient = $this->findOrCreateCustomer($data['recipient_name'], $data['recipient_address']);
            $hub = $this->findOrCreateHub($data['current_hub_name'] ?? null);
            $deliveryType = $data['delivery_type'];

            $weight = round((float) $data['parcel_weight_kg'], 3);
            $cost = $this->calculateShippingCost($weight, $deliveryType);

            $parcel->update([
                'tracking_number' => $data['tracking_number'],
                'sender_customer_id' => $sender->id,
                'recipient_customer_id' => $recipient->id,
                'current_hub_id' => $hub?->id,
                'parcel_weight_kg' => $weight,
                'delivery_type' => $deliveryType,
                'shipping_cost' => $cost,
            ]);
            $parcel->payments()->where('payment_status', 'pending')->update(['amount' => $cost]);

            return $parcel->refresh();
        });
    }

    public function createStatusLog(array $data): ShipmentStatusLog
    {
        $data = $this->sanitizeStatusLogInput($data);
        $this->assertValidStatus($data['status'], 'status');

        return DB::transaction(function () use ($data) {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($data['parcel_id']);
            $lastLog = $parcel->statusLogs()->lockForUpdate()->orderByDesc('sequence')->first();
            $occurredAt = $this->parseDate($data['occurred_at'] ?? null);

            if ($lastLog && $occurredAt->lt($lastLog->occurred_at)) {
                throw ValidationException::withMessages([
                    'occurred_at' => 'The status update cannot be earlier than the previous parcel update.',
                ]);
            }

            $hub = $this->findOrCreateHub($data['current_hub_name'] ?? null);
            $log = $parcel->statusLogs()->create([
                'transit_hub_id' => $hub?->id,
                'sequence' => ($lastLog?->sequence ?? 0) + 1,
                'status' => $data['status'],
                'occurred_at' => $occurredAt,
            ]);

            $parcel->update(['current_hub_id' => $hub?->id]);

            return $log;
        });
    }

    public function updateStatusLog(ShipmentStatusLog $log, array $data): ShipmentStatusLog
    {
        $data = $this->sanitizeStatusLogInput($data);
        $this->assertValidStatus($data['status'], 'status');

        return DB::transaction(function () use ($log, $data) {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($log->parcel_id);
            $log = ShipmentStatusLog::query()->lockForUpdate()->findOrFail($log->id);
            $previous = $parcel->statusLogs()->where('sequence', '<', $log->sequence)->orderByDesc('sequence')->first();
            $next = $parcel->statusLogs()->where('sequence', '>', $log->sequence)->orderBy('sequence')->first();
            $occurredAt = $this->parseDate($data['occurred_at']);

            if (($previous && $occurredAt->lt($previous->occurred_at)) || ($next && $occurredAt->gt($next->occurred_at))) {
                throw ValidationException::withMessages([
                    'occurred_at' => 'This date and time must remain between the adjacent parcel updates.',
                ]);
            }

            $hub = $this->findOrCreateHub($data['current_hub_name'] ?? null);
            $log->update([
                'transit_hub_id' => $hub?->id,
                'status' => $data['status'],
                'occurred_at' => $occurredAt,
            ]);

            if (! $next) {
                $parcel->update(['current_hub_id' => $hub?->id]);
            }

            return $log->refresh();
        });
    }

    private function findOrCreateCustomer(string $name, string $address): Customer
    {
        $name = $this->cleanText($name);
        $address = $this->cleanAddress($address);

        return Customer::query()->firstOrCreate([
            'name' => $name,
            'address' => $address,
        ]);
    }

    private function findOrCreateHub(?string $name): ?TransitHub
    {
        $name = $name === null ? '' : $this->cleanText($name);

        return $name === '' ? null : TransitHub::query()->firstOrCreate(['name' => $name]);
    }

    private function cleanText(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        return trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));
    }

    private function cleanAddress(mixed $address): string
    {
        return mb_convert_case($this->cleanText($address), MB_CASE_TITLE, 'UTF-8');
    }

    private function parseDate(?string $date): Carbon
    {
        return $date ? Carbon::parse($date) : now();
    }

    private function calculateShippingCost(float $weight, string $deliveryType): float
    {
        if (! isset(self::SPEED_MULTIPLIERS[$deliveryType])) {
            throw ValidationException::withMessages([
                'delivery_type' => 'Choose a supported delivery speed.',
            ]);
        }

        return round($weight * self::BASE_RATE_PER_KG * self::SPEED_MULTIPLIERS[$deliveryType], 2);
    }

    private function assertValidStatus(string $status, string $field): void
    {
        if (! in_array($status, self::SHIPMENT_STATUSES, true)) {
            throw ValidationException::withMessages([
                $field => 'Choose a valid shipment status.',
            ]);
        }
    }
}
