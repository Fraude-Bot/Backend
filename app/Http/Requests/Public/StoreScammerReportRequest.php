<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreScammerReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('title'))) {
            $merge['title'] = trim($this->input('title'));
        }

        if (is_string($this->input('email'))) {
            $merge['email'] = trim($this->input('email'));
        }

        $scammer = $this->input('scammer');

        if (is_array($scammer)) {
            if (is_string($scammer['name'] ?? null)) {
                $scammer['name'] = trim($scammer['name']);
            }

            $merge['scammer'] = $scammer;
        }

        $products = $this->input('products');

        if (is_array($products)) {
            $merge['products'] = array_map(
                fn (mixed $name): mixed => is_string($name)
                    ? mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8')
                    : $name,
                $products,
            );
        }

        $organizations = $this->input('organizations');

        if (is_array($organizations)) {
            $merge['organizations'] = array_map(function (mixed $organization): mixed {
                if (! is_array($organization)) {
                    return $organization;
                }

                if (is_string($organization['name'] ?? null)) {
                    $organization['name'] = trim($organization['name']);
                }

                return $organization;
            }, $organizations);
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'profile_picture' => ['string'],
            'proofs' => ['sometimes', 'array'],
            'proofs.*' => ['required', 'string'],
            'scammer' => ['required', 'array'],
            'scammer.name' => ['required', 'string', 'max:100'],
            'contacts' => ['sometimes', 'array'],
            'contacts.*.platform' => ['prohibited'],
            'contacts.*.reference' => ['required', 'string', 'max:255'],
            'payment_methods' => ['sometimes', 'array'],
            'payment_methods.*.type' => ['prohibited'],
            'payment_methods.*.reference' => ['required', 'string', 'max:255'],
            'products' => ['required', 'array', 'min:1'],
            'products.*' => ['required', 'string', 'max:75'],
            'organizations' => ['sometimes', 'array'],
            'organizations.*.name' => ['required', 'string', 'max:100'],
            'organizations.*.profile_picture' => ['sometimes', 'nullable', 'string'],
            'organizations.*.contacts' => ['sometimes', 'array'],
            'organizations.*.contacts.*.platform' => ['prohibited'],
            'organizations.*.contacts.*.reference' => ['required', 'string', 'max:255'],
            'organizations.*.payment_methods' => ['sometimes', 'array'],
            'organizations.*.payment_methods.*.type' => ['prohibited'],
            'organizations.*.payment_methods.*.reference' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasContactOrPaymentMethod()) {
                return;
            }

            $message = 'A contact or a payment method is required.';
            $validator->errors()->add('contacts', $message);
            $validator->errors()->add('payment_methods', $message);
        });
    }

    private function hasContactOrPaymentMethod(): bool
    {
        $contacts = $this->input('contacts', []);
        $paymentMethods = $this->input('payment_methods', []);

        return (is_array($contacts) && $contacts !== [])
            || (is_array($paymentMethods) && $paymentMethods !== []);
    }
}
