<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Inventory\RecordInventoryMovement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Http\Resources\InventoryMovementResource;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

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

    public function index(Product $product): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InventoryMovement::class);

        $movements = InventoryMovement::query()
            ->where('product_id', $product->id)
            ->orderByDesc('id')
            ->paginate(20);

        return InventoryMovementResource::collection($movements)
            ->additional([
                'product' => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'stock' => $product->stock,
                    'is_active' => $product->is_active,
                ],
            ]);
    }
}
