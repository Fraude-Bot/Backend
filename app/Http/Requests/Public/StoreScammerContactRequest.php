<?php

namespace App\Http\Requests\Public;

use App\Domain\Contact\Enums\PlatformType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScammerContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
        return [
            'platform' => ['required', Rule::enum(PlatformType::class)],
            'reference' => ['required', 'string', 'max:255'],
        ];
    }
}
