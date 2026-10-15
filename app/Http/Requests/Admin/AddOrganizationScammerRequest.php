<?php

namespace App\Http\Requests\Admin;

use Illuminate\Validation\Rule;

class AddOrganizationScammerRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'scammer_id' => [
                'required',
                'integer',
                Rule::exists('scammers', 'id')->whereNull('deleted_at'),
            ],
        ];
    }
}
