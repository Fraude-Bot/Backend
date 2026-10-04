<?php

namespace App\Http\Requests\Public;

use App\Domain\Contact\Enums\PlatformType;
use App\Domain\PaymentMethod\Enums\PaymentMethodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationReportRequest extends FormRequest
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

        $organization = $this->input('organization');

        if (is_array($organization)) {
            if (is_string($organization['name'] ?? null)) {
                $organization['name'] = trim($organization['name']);
            }

            $merge['organization'] = $organization;
        }

        $products = $this->input('products');

        if (is_array($products)) {
            $merge['products'] = array_map(
                fn (mixed $name): mixed => is_string($name) ? trim($name) : $name,
                $products,
            );
        }

        $scammers = $this->input('scammers');

        if (is_array($scammers)) {
            $merge['scammers'] = array_map(function (mixed $scammer): mixed {
                if (! is_array($scammer)) {
                    return $scammer;
                }

                if (is_string($scammer['name'] ?? null)) {
                    $scammer['name'] = trim($scammer['name']);
                }

                if (is_array($scammer['contacts'] ?? null)) {
                    $scammer['contacts'] = $this->resolveEnumItems(
                        $scammer['contacts'],
                        'platform',
                        PlatformType::tryFromInput(...),
                    );
                }

                if (is_array($scammer['payment_methods'] ?? null)) {
                    $scammer['payment_methods'] = $this->resolveEnumItems(
                        $scammer['payment_methods'],
                        'type',
                        PaymentMethodType::tryFromInput(...),
                    );
                }

                return $scammer;
            }, $scammers);
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'profile_picture' => ['required', 'string'],
            'proofs' => ['sometimes', 'array'],
            'proofs.*' => ['required', 'string'],
            'organization' => ['required', 'array'],
            'organization.name' => ['required', 'string', 'max:100'],
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.name' => ['required', 'string', 'max:50'],
            'contacts.*.platform' => ['required', Rule::enum(PlatformType::class)],
            'contacts.*.reference' => ['required', 'string', 'max:255'],
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*.type' => ['required', Rule::enum(PaymentMethodType::class)],
            'payment_methods.*.reference' => ['required', 'string', 'max:255'],
            'products' => ['required', 'array', 'min:1'],
            'products.*' => ['required', 'string', 'max:75'],
            'scammers' => ['sometimes', 'array'],
            'scammers.*.name' => ['required', 'string', 'max:100'],
            'scammers.*.contacts' => ['sometimes', 'array'],
            'scammers.*.contacts.*.name' => ['required', 'string', 'max:50'],
            'scammers.*.contacts.*.platform' => ['required', Rule::enum(PlatformType::class)],
            'scammers.*.contacts.*.reference' => ['required', 'string', 'max:255'],
            'scammers.*.payment_methods' => ['sometimes', 'array'],
            'scammers.*.payment_methods.*.type' => ['required', Rule::enum(PaymentMethodType::class)],
            'scammers.*.payment_methods.*.reference' => ['required', 'string', 'max:255'],
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

        return $this->resolveEnumItems($items, $enumKey, $resolve);
    }

    /**
     * @param  array<mixed>  $items
     * @param  callable(mixed): (?\BackedEnum)  $resolve
     * @return array<mixed>
     */
    private function resolveEnumItems(array $items, string $enumKey, callable $resolve): array
    {
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
