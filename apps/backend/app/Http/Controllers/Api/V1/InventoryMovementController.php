<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Inventory\RecordInventoryMovement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Http\Resources\InventoryMovementResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class InventoryMovementController extends Controller
{
    public function store(
        StoreInventoryMovementRequest $request,
        Product $product,
        RecordInventoryMovement $action,
    ): JsonResponse {
        $data = $request->validated();

        $movement = $action->execute(
            productId: $product->id,
            actor: $request->user(),
            quantity: (int) $data['quantity'],
            reason: $data['reason'],
        );

        return (new InventoryMovementResource($movement))
            ->response()
            ->setStatusCode(201);
    }
}
