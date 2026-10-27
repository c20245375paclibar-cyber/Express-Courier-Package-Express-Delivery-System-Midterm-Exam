<?php

namespace App\Http\Requests;

use App\Services\CourierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShipmentStatusLogRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(app(CourierService::class)->sanitizeStatusLogInput($this->all()));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parcel_id' => ['required', 'integer', 'exists:parcels,id'],
            'status' => ['required', Rule::in(CourierService::SHIPMENT_STATUSES)],
            'current_hub_name' => ['nullable', 'string', 'max:120'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'parcel_id.exists' => 'Select an existing parcel.',
            'status.in' => 'Choose a valid shipment status.',
        ];
    }
}
