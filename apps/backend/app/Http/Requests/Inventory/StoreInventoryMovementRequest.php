<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryMovement;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            InventoryMovement::class
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'quantity' => [
                'required',
                'integer',
                'between:-2147483647,2147483647',
                'not_in:0',
            ],
            'reason' => ['required', 'string', 'max:500'],
            'user_id' => ['prohibited'],
            'product_id' => ['prohibited'],
            'stock_after' => ['prohibited'],
            'created_at' => ['prohibited'],
        ];
    }
}
