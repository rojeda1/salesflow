<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->paginate(15);

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        try {
            $product = DB::transaction(
                fn () => Product::create($request->validated())
            );
        } catch (UniqueConstraintViolationException $exception) {
            if (
                ! str_contains(
                    $exception->errorInfo[2] ?? '',
                    'products_sku_unique'
                )
            ) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'sku' => ['El SKU ya está registrado.'],
            ]);
        }

        return new ProductResource($product);
    }
}
