<?php

namespace App\Http\Requests\Admin;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use Illuminate\Validation\Rule;

class StoreScammerRequest extends AdminRequest
{
    protected function prepareForValidation(): void
    {
        $rawContacts = $this->input('contacts', []);
        $contacts = is_array($rawContacts) ? array_map(function ($contact) {
            if (! is_array($contact)) {
                return $contact;
            }
            $platform = PlatformType::tryFromInput($contact['platform'] ?? null);
            $contact['platform'] = $platform instanceof PlatformType
                ? $platform->value
                : ($contact['platform'] ?? null);

            return $contact;
        }, $rawContacts) : $rawContacts;

        $rawPaymentMethods = $this->input('paymentMethods', []);
        $paymentMethods = is_array($rawPaymentMethods) ? array_map(function ($paymentMethod) {
            if (! is_array($paymentMethod)) {
                return $paymentMethod;
            }
            $type = PaymentMethodType::tryFromInput($paymentMethod['type'] ?? null);
            $paymentMethod['type'] = $type instanceof PaymentMethodType
                ? $type->value
                : ($paymentMethod['type'] ?? null);

            return $paymentMethod;
        }, $rawPaymentMethods) : $rawPaymentMethods;

        $this->merge(['contacts' => $contacts, 'paymentMethods' => $paymentMethods]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'contacts' => ['sometimes', 'array'],
            'contacts.*.name' => ['required', 'string', 'max:50'],
            'contacts.*.platform' => ['required', Rule::enum(PlatformType::class)],
            'contacts.*.reference' => ['required', 'string', 'max:255'],
            'contacts.*.is_active' => ['sometimes', 'boolean'],
            'paymentMethods' => ['sometimes', 'array'],
            'paymentMethods.*.reference' => ['required', 'string', 'max:255'],
            'paymentMethods.*.type' => ['required', Rule::enum(PaymentMethodType::class)],
            'paymentMethods.*.is_active' => ['sometimes', 'boolean'],
        ];
    }
}
