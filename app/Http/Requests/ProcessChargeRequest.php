<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'payment_id' => 'nullable|integer',
            'order_id' => 'nullable',
            'marketplace_order_id' => 'nullable',
            'token' => 'nullable|string',
            'card_token' => 'nullable|string',
            'charge_id' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_id.required' => 'The payment ID is required.',
            'payment_id.exists' => 'The specified payment record was not found.',
            'token.required' => 'The Tap card token is required.',
        ];
    }
}
