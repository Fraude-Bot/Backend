<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachedReportRequest extends FormRequest
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

        $products = $this->input('products');

        if (is_array($products)) {
            $merge['products'] = array_map(
                fn (mixed $name): mixed => is_string($name)
                    ? mb_convert_case(trim($name), MB_CASE_TITLE, 'UTF-8')
                    : $name,
                $products,
            );
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'proofs' => ['sometimes', 'array'],
            'proofs.*' => ['required', 'string'],
            'products' => ['required', 'array', 'min:1'],
            'products.*' => ['required', 'string', 'max:75'],
        ];
    }
}
