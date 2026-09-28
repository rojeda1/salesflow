<?php

namespace App\Http\Requests\Products;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'sku' => [
                'required',
                'string',
                'max:50',
                'regex:/\A[A-Z0-9]+(?:-[A-Z0-9]+)*\z/',
                Rule::unique('products', 'sku'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => [
                'required',
                'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})\.[0-9]{2}\z/',
            ],
        ];
    }
}
