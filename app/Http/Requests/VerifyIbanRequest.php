<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyIbanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $iban = strtoupper(
            preg_replace('/[\s-]+/', '', (string) $this->iban)
        );

        $this->merge([
            'iban' => $iban,
        ]);
    }

    public function rules(): array
    {
        return [
            'iban' => [
                'required',
                'string',
                'size:24',
                'regex:/^SA\d{4}[A-Z0-9]{18}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'iban.required' => 'IBAN is required.',
            'iban.size' => 'Saudi IBAN must contain 24 characters.',
            'iban.regex' => 'Please enter a valid Saudi IBAN format.',
        ];
    }
}