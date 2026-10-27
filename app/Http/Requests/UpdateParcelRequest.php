<?php

namespace App\Http\Requests;

use App\Services\CourierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParcelRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(app(CourierService::class)->sanitizeParcelInput($this->all()));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $parcelId = $this->route('parcel');

        return [
            'tracking_number' => ['required', 'string', 'max:50', Rule::unique('parcels', 'tracking_number')->ignore($parcelId)],
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_address' => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_address' => ['required', 'string', 'max:255'],
            'parcel_weight_kg' => ['required', 'numeric', 'gt:0', 'max:99999.999'],
            'delivery_type' => ['required', Rule::in(CourierService::DELIVERY_TYPES)],
            'current_hub_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'tracking_number.unique' => 'This tracking number is already in use.',
            'parcel_weight_kg.gt' => 'Parcel weight must be greater than zero.',
            'delivery_type.in' => 'Choose standard, express, or overnight delivery.',
        ];
    }
}
