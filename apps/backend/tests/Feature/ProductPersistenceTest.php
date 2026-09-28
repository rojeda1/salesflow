<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class ProductPersistenceTest extends TestCase
{
    public function test_it_persists_a_product_with_an_exact_price(): void
    {
        $product = Product::create([
            'sku' => 'KEYBOARD-001',
            'name' => 'Teclado',
            'price' => '149.90',
        ]);

        $product->refresh();

        $this->assertSame('149.90', $product->price);
        $this->assertTrue($product->is_active);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'sku' => 'KEYBOARD-001',
            'name' => 'Teclado',
        ]);
    }

    public function test_it_rejects_duplicate_skus(): void
    {
        Product::create([
            'sku' => 'KEYBOARD-001',
            'name' => 'Teclado',
            'price' => '149.90',
        ]);

        try {
            Product::create([
                'sku' => 'KEYBOARD-001',
                'name' => 'Otro teclado',
                'price' => '199.90',
            ]);
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0]);

            return;
        }

        $this->fail('PostgreSQL permitió un SKU duplicado.');
    }

    public function test_it_rejects_negative_prices(): void
    {
        try {
            Product::create([
                'sku' => 'INVALID-001',
                'name' => 'Producto inválido',
                'price' => '-0.01',
            ]);
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);

            return;
        }

        $this->fail('PostgreSQL permitió un precio negativo.');
    }

    public function test_it_accepts_a_zero_price(): void
    {
        $product = Product::create([
            'sku' => 'FREE-001',
            'name' => 'Muestra gratuita',
            'price' => '0.00',
        ]);

        $this->assertSame('0.00', $product->fresh()->price);
    }
}
