<?php

namespace App\Actions\Inventory;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordInventoryMovement
{
    private const MAX_STOCK = 2_147_483_647;

    public function execute(
        int $productId,
        User $actor,
        int $quantity,
        string $reason,
    ): InventoryMovement {
        $reason = trim($reason);

        if (
            $quantity === 0
            || $quantity < -self::MAX_STOCK
            || $quantity > self::MAX_STOCK
        ) {
            throw ValidationException::withMessages([
                'quantity' => ['La cantidad debe ser un entero distinto de cero dentro del rango permitido.'],
            ]);
        }

        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'reason' => ['El motivo es obligatorio y no puede superar 500 caracteres.'],
            ]);
        }

        return DB::transaction(function () use ($productId, $actor, $quantity, $reason): InventoryMovement {
            $product = Product::query()
                ->lockForUpdate()
                ->findOrFail($productId);

            if (! $product->is_active) {
                throw ValidationException::withMessages([
                    'product' => ['No se puede modificar el inventario de un producto inactivo.'],
                ]);
            }

            $newStock = $product->stock + $quantity;

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['No hay existencias suficientes para esta salida.'],
                ]);
            }

            if ($newStock > self::MAX_STOCK) {
                throw ValidationException::withMessages([
                    'quantity' => ['El movimiento supera el stock máximo permitido.'],
                ]);
            }

            $product->stock = $newStock;
            $product->save();

            $movement = new InventoryMovement;
            $movement->product_id = $product->id;
            $movement->user_id = $actor->id;
            $movement->quantity = $quantity;
            $movement->stock_after = $newStock;
            $movement->reason = $reason;
            $movement->save();

            return $movement->refresh();
        }, 3);
    }
}
