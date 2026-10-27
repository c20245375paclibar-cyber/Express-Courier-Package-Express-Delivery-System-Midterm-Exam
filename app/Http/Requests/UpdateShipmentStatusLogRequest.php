<?php

namespace App\Http\Requests;

use App\Services\CourierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShipmentStatusLogRequest extends FormRequest
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
            'status' => ['required', Rule::in(CourierService::SHIPMENT_STATUSES)],
            'current_hub_name' => ['nullable', 'string', 'max:120'],
            'occurred_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Choose a valid shipment status.',
            'occurred_at.required' => 'A date and time is required for this status update.',
        ];
    }
}
