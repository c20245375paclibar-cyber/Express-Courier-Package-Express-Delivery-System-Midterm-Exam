<?php

namespace Tests\Feature;

use App\Models\CourierPayment;
use App\Models\Customer;
use App\Models\Parcel;
use App\Models\ShipmentStatusLog;
use App\Models\User;
use App\Services\CourierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourierWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_parcel_creation_normalizes_data_and_creates_related_records(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('parcels.store'), [
            'tracking_number' => '<b>cxp-0001</b>',
            'sender_name' => '<i>Sender</i>',
            'sender_address' => '  12 MAIN   street  ',
            'recipient_name' => 'Recipient',
            'recipient_address' => '45 Oak Road',
            'parcel_weight_kg' => 2,
            'delivery_type' => 'express',
            'current_hub_name' => 'Central Hub',
            'initial_status' => 'received',
            'status_occurred_at' => null,
        ])->assertRedirect(route('parcels.index'));

        $parcel = Parcel::query()->with(['sender', 'recipient', 'currentHub'])->firstOrFail();

        $this->assertSame('CXP-0001', $parcel->tracking_number);
        $this->assertSame('Sender', $parcel->sender->name);
        $this->assertSame('12 Main Street', $parcel->sender->address);
        $this->assertSame('Central Hub', $parcel->currentHub->name);
        $this->assertSame('15.00', $parcel->shipping_cost);
        $this->assertSame(1, $parcel->statusLogs()->count());
        $this->assertSame('received', $parcel->statusLogs()->first()->status);
        $this->assertSame('pending', $parcel->payments()->first()->payment_status);
        $this->assertSame('15.00', $parcel->payments()->first()->amount);
    }

    public function test_status_updates_are_numbered_and_cannot_precede_the_previous_event(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();

        $this->post(route('shipment-status-logs.store'), [
            'parcel_id' => $parcel->id,
            'status' => '<b>IN_TRANSIT</b>',
            'current_hub_name' => ' <i>North Hub</i> ',
            'occurred_at' => '2026-10-27 10:00:00',
        ])->assertRedirect(route('shipment-status-logs.index'));

        $this->assertSame(2, ShipmentStatusLog::query()->where('parcel_id', $parcel->id)->orderByDesc('sequence')->firstOrFail()->sequence);
        $this->assertSame('in_transit', ShipmentStatusLog::query()->where('parcel_id', $parcel->id)->orderByDesc('sequence')->firstOrFail()->status);
        $this->assertSame('North Hub', $parcel->fresh()->currentHub->name);

        $this->from(route('shipment-status-logs.create'))
            ->post(route('shipment-status-logs.store'), [
                'parcel_id' => $parcel->id,
                'status' => 'delivered',
                'occurred_at' => '2026-10-26 10:00:00',
            ])
            ->assertSessionHasErrors('occurred_at');
    }

    public function test_parcel_update_recalculates_shipping_and_updates_pending_payment(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel([
            'tracking_number' => 'CXP-OLD',
            'parcel_weight_kg' => 1,
            'delivery_type' => 'standard',
            'shipping_cost' => 5,
        ]);
        $payment = CourierPayment::query()->create([
            'parcel_id' => $parcel->id,
            'amount' => 5,
            'payment_status' => 'pending',
        ]);

        $this->patch(route('parcels.update', $parcel), [
            'tracking_number' => 'cxp-new',
            'sender_name' => 'Sender',
            'sender_address' => '1 Example Street',
            'recipient_name' => 'Recipient',
            'recipient_address' => '2 Example Street',
            'parcel_weight_kg' => 3,
            'delivery_type' => 'overnight',
            'current_hub_name' => '',
        ])->assertRedirect(route('parcels.index'));

        $this->assertSame('CXP-NEW', $parcel->fresh()->tracking_number);
        $this->assertSame('37.50', $parcel->fresh()->shipping_cost);
        $this->assertSame('37.50', $payment->fresh()->amount);
    }

    public function test_status_log_update_preserves_sequence_and_updates_latest_hub(): void
    {
        $this->actingAs(User::factory()->create());
        $parcel = $this->makeParcel();
        $log = $parcel->statusLogs()->firstOrFail();

        $this->patch(route('shipment-status-logs.update', $log), [
            'status' => 'picked_up',
            'current_hub_name' => 'Airport Hub',
            'occurred_at' => '2026-10-27 09:30:00',
        ])->assertRedirect(route('shipment-status-logs.index'));

        $this->assertSame(1, $log->fresh()->sequence);
        $this->assertSame('picked_up', $log->fresh()->status);
        $this->assertSame('Airport Hub', $parcel->fresh()->currentHub->name);
    }

    public function test_parcel_index_search_is_normalized_and_filters_results(): void
    {
        $this->actingAs(User::factory()->create());
        $matchingParcel = $this->makeParcel(['tracking_number' => 'CXP-SEARCH']);
        $this->makeParcel(['tracking_number' => 'CXP-OTHER']);

        $service = app(CourierService::class);
        $results = $service->paginateParcels($service->normalizeSearch('<b>cxp-search</b>'));

        $this->assertCount(1, $results->items());
        $this->assertSame($matchingParcel->id, $results->items()[0]->id);
    }

    public function test_status_log_index_search_filters_by_tracking_number(): void
    {
        $this->actingAs(User::factory()->create());
        $matchingParcel = $this->makeParcel(['tracking_number' => 'CXP-STATUS']);
        $this->makeParcel(['tracking_number' => 'CXP-OTHER']);

        $service = app(CourierService::class);
        $results = $service->paginateStatusLogs($service->normalizeSearch('CXP-STATUS'));

        $this->assertCount(1, $results->items());
        $this->assertSame($matchingParcel->id, $results->items()[0]->parcel->id);
    }

    private function makeParcel(array $attributes = []): Parcel
    {
        $trackingNumber = $attributes['tracking_number'] ?? 'CXP-TEST';
        $sender = Customer::query()->create(['name' => "Test Sender {$trackingNumber}", 'address' => "1 {$trackingNumber} Street"]);
        $recipient = Customer::query()->create(['name' => "Test Recipient {$trackingNumber}", 'address' => "2 {$trackingNumber} Street"]);
        $parcel = Parcel::query()->create(array_merge([
            'tracking_number' => $trackingNumber,
            'sender_customer_id' => $sender->id,
            'recipient_customer_id' => $recipient->id,
            'parcel_weight_kg' => 1,
            'delivery_type' => 'standard',
            'shipping_cost' => 5,
        ], $attributes));

        $parcel->statusLogs()->create([
            'sequence' => 1,
            'status' => 'received',
            'occurred_at' => '2026-10-27 09:00:00',
        ]);

        return $parcel;
    }
}
