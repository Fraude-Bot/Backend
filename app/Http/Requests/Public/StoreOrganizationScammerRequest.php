<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationScammerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'profile_picture_path' => ['sometimes', 'nullable', 'string'],
            'contacts' => ['sometimes', 'array'],
            'contacts.*.platform' => ['prohibited'],
            'contacts.*.reference' => ['required', 'string', 'max:255'],
            'payment_methods' => ['sometimes', 'array'],
            'payment_methods.*.type' => ['prohibited'],
            'payment_methods.*.reference' => ['required', 'string', 'max:255'],
        ];
    }
}
