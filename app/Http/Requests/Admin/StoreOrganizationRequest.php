<?php

namespace App\Http\Requests\Admin;

class StoreOrganizationRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
