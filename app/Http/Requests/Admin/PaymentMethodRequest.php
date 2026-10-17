<?php

namespace App\Http\Requests\Admin;

class PaymentMethodRequest extends AdminRequest
{
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
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
