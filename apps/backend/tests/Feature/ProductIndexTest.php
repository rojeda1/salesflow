<?php

namespace Tests\Feature;

use App\Models\Product;
use Tests\TestCase;

class ProductIndexTest extends TestCase
{
    public function test_it_returns_an_empty_catalog(): void
    {
        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_it_returns_only_active_products(): void
    {
        $activeProduct = Product::create([
            'sku' => 'ACTIVE-001',
            'name' => 'Teclado',
            'description' => 'Teclado de prueba',
            'price' => '149.90',
            'is_active' => true,
        ]);

        Product::create([
            'sku' => 'INACTIVE-001',
            'name' => 'Producto desactivado',
            'price' => '50.00',
            'is_active' => false,
        ]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0', [
                'id' => $activeProduct->id,
                'sku' => 'ACTIVE-001',
                'name' => 'Teclado',
                'description' => 'Teclado de prueba',
                'price' => '149.90',
            ])
            ->assertJsonMissing([
                'sku' => 'INACTIVE-001',
            ]);
    }

    public function test_it_paginates_products_in_id_order(): void
    {
        $productIds = [];

        for ($number = 1; $number <= 16; $number++) {
            $product = Product::create([
                'sku' => sprintf('PRODUCT-%03d', $number),
                'name' => "Producto {$number}",
                'price' => '10.00',
            ]);

            $productIds[] = $product->id;
        }

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.*.id', array_slice($productIds, 0, 15));

        $this->getJson('/api/v1/products?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('data.0.id', $productIds[15]);
    }
}
