<?php

namespace Tests\Feature;

use App\Actions\Inventory\RecordInventoryMovement;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    public function test_it_records_an_entry_and_updates_stock(): void
    {
        $product = $this->createProduct();
        $actor = User::factory()->create();

        $movement = app(RecordInventoryMovement::class)->execute(
            $product->id,
            $actor,
            10,
            'Existencias iniciales',
        );

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertSame(10, $movement->quantity);
        $this->assertSame(10, $movement->stock_after);
        $this->assertSame($actor->id, $movement->user_id);
        $this->assertSame($product->id, $movement->product_id);
        $this->assertSame('Existencias iniciales', $movement->reason);
        $this->assertNotNull($movement->created_at);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_it_records_an_exit_and_preserves_history(): void
    {
        $product = $this->createProduct();
        $actor = User::factory()->create();
        $action = app(RecordInventoryMovement::class);

        $entry = $action->execute(
            $product->id,
            $actor,
            10,
            'Existencias iniciales',
        );

        $exit = $action->execute(
            $product->id,
            $actor,
            -3,
            'Retiro de unidades dañadas',
        );

        $this->assertSame(7, $product->fresh()->stock);
        $this->assertSame(-3, $exit->quantity);
        $this->assertSame(7, $exit->stock_after);
        $this->assertSame(10, $entry->fresh()->stock_after);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_it_rejects_an_exit_without_enough_stock(): void
    {
        $product = $this->createProduct();
        $actor = User::factory()->create();

        try {
            app(RecordInventoryMovement::class)->execute(
                $product->id,
                $actor,
                -1,
                'Salida sin existencias',
            );

            $this->fail('Se permitió una salida sin existencias.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    private function createProduct(): Product
    {
        return Product::create([
            'sku' => 'INVENTORY-001',
            'name' => 'Producto de inventario',
            'price' => '25.00',
        ]);
    }

    public function test_it_rolls_back_stock_when_recording_the_movement_fails(): void
    {
        $product = $this->createProduct();
        $actor = User::factory()->create();

        InventoryMovement::creating(function (): void {
            throw new \RuntimeException('Simulated history failure');
        });

        try {
            app(RecordInventoryMovement::class)->execute(
                $product->id,
                $actor,
                10,
                'Entrada de prueba',
            );

            $this->fail('La operación debía fallar al guardar el historial.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Simulated history failure',
                $exception->getMessage()
            );
        }

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    #[DataProvider('invalidMovementData')]
    public function test_it_rejects_invalid_movement_data(
        int $quantity,
        string $reason,
        string $expectedField,
    ): void {
        $product = $this->createProduct();
        $actor = User::factory()->create();

        try {
            app(RecordInventoryMovement::class)->execute(
                $product->id,
                $actor,
                $quantity,
                $reason,
            );

            $this->fail('Se aceptó un movimiento inválido.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                $expectedField,
                $exception->errors()
            );
        }

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public static function invalidMovementData(): array
    {
        return [
            'zero quantity' => [0, 'Entrada', 'quantity'],
            'quantity above maximum' => [2_147_483_648, 'Entrada', 'quantity'],
            'quantity below minimum' => [-2_147_483_648, 'Salida', 'quantity'],
            'empty reason' => [1, '', 'reason'],
            'blank reason' => [1, '   ', 'reason'],
            'reason too long' => [1, str_repeat('A', 501), 'reason'],
        ];
    }

    public function test_it_rejects_movements_for_inactive_products(): void
    {
        $product = $this->createProduct();
        $product->is_active = false;
        $product->save();

        $actor = User::factory()->create();

        try {
            app(RecordInventoryMovement::class)->execute(
                $product->id,
                $actor,
                1,
                'Entrada de prueba',
            );

            $this->fail('Se modificó un producto inactivo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product', $exception->errors());
        }

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_it_accepts_maximum_stock_but_rejects_overflow(): void
    {
        $product = $this->createProduct();
        $actor = User::factory()->create();
        $action = app(RecordInventoryMovement::class);

        $entry = $action->execute(
            $product->id,
            $actor,
            2_147_483_647,
            'Entrada máxima',
        );

        $this->assertSame(2_147_483_647, $entry->stock_after);

        try {
            $action->execute(
                $product->id,
                $actor,
                1,
                'Entrada que excede el límite',
            );

            $this->fail('Se permitió superar el stock máximo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $this->assertSame(2_147_483_647, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_it_allows_an_exit_that_leaves_zero_stock(): void
    {
        $product = $this->createProduct();
        $actor = User::factory()->create();
        $action = app(RecordInventoryMovement::class);

        $action->execute($product->id, $actor, 10, 'Entrada inicial');

        $exit = $action->execute(
            $product->id,
            $actor,
            -10,
            'Salida completa',
        );

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertSame(0, $exit->stock_after);
        $this->assertDatabaseCount('inventory_movements', 2);
    }
}
