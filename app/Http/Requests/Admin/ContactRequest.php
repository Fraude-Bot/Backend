<?php

namespace App\Http\Requests\Admin;

use App\Domain\Contact\Enums\PlatformType;
use Illuminate\Validation\Rule;

class ContactRequest extends AdminRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('platform')) {
            $platform = PlatformType::tryFromInput($this->input('platform'));
            $this->merge(['platform' => $platform instanceof PlatformType
                ? $platform->value
                : $this->input('platform')]);
        }
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'platform' => [$creating ? 'required' : 'sometimes', Rule::enum(PlatformType::class)],
            'reference' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
