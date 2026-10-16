<?php

namespace App\Http\Requests\Public;

use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $type = PaymentMethodType::tryFromInput($this->input('type'));

        $this->merge([
            'type' => $type instanceof PaymentMethodType ? $type->value : $this->input('type'),
            'reference' => is_string($this->input('reference'))
                ? trim($this->input('reference'))
                : $this->input('reference'),
        ]);
    }

    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PaymentMethodType::class)],
        ];
    }
}
