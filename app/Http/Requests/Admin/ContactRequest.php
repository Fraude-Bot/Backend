<?php

namespace App\Http\Requests\Admin;

class ContactRequest extends AdminRequest
{
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'platform' => ['prohibited'],
            'reference' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
