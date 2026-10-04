<?php

namespace App\Http\Requests\Public;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScammerReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'contacts' => $this->enumList('contacts', 'platform', PlatformType::tryFromInput(...)),
            'payment_methods' => $this->enumList('payment_methods', 'type', PaymentMethodType::tryFromInput(...)),
        ];

        if (is_string($this->input('title'))) {
            $merge['title'] = trim($this->input('title'));
        }

        $scammer = $this->input('scammer');

        if (is_array($scammer)) {
            if (is_string($scammer['name'] ?? null)) {
                $scammer['name'] = trim($scammer['name']);
            }

            $merge['scammer'] = $scammer;
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'profile_picture' => ['sometimes', 'nullable', 'string'],
            'proofs' => ['sometimes', 'array'],
            'proofs.*' => ['required', 'string'],
            'scammer' => ['required', 'array'],
            'scammer.name' => ['required', 'string', 'max:100'],
            'contacts' => ['sometimes', 'array'],
            'contacts.*.name' => ['required', 'string', 'max:50'],
            'contacts.*.platform' => ['required', Rule::enum(PlatformType::class)],
            'contacts.*.reference' => ['required', 'string', 'max:255'],
            'payment_methods' => ['sometimes', 'array'],
            'payment_methods.*.type' => ['required', Rule::enum(PaymentMethodType::class)],
            'payment_methods.*.reference' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @param  callable(mixed): (?\BackedEnum)  $resolve
     */
    private function enumList(string $key, string $enumKey, callable $resolve): mixed
    {
        $items = $this->input($key, []);

        if (! is_array($items)) {
            return $items;
        }

        return array_map(function ($item) use ($enumKey, $resolve) {
            if (! is_array($item)) {
                return $item;
            }

            $enum = $resolve($item[$enumKey] ?? null);
            $item[$enumKey] = $enum instanceof \BackedEnum
                ? $enum->value
                : ($item[$enumKey] ?? null);

            return $item;
        }, $items);
    }
}
