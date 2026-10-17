<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reference'))) {
            $this->merge([
                'reference' => trim($this->input('reference')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:255'],
            'type' => ['prohibited'],
        ];
    }
}
