<?php

namespace App\Http\Requests\Admin;

class UpdateScammerRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
