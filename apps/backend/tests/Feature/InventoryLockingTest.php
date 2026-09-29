<?php

namespace Tests\Feature;

use App\Actions\Inventory\RecordInventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryLockingTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase()
    {
        if (
            ! app()->environment('testing')
            || DB::connection()->getDriverName() !== 'pgsql'
            || DB::connection()->getDatabaseName() !== 'salesflow_testing'
        ) {
            throw new \RuntimeException(
                'Las pruebas requieren PostgreSQL y la base salesflow_testing.'
            );
        }
    }

    public function test_it_locks_stock_and_rechecks_the_balance(): void
    {
        $product = Product::create([
            'sku' => 'LOCK-001',
            'name' => 'Producto para bloqueo',
            'price' => '25.00',
        ]);

        $actor = User::factory()->create();
        $action = app(RecordInventoryMovement::class);

        $action->execute(
            $product->id,
            $actor,
            10,
            'Existencias iniciales',
        );

        $originalConnection = DB::getDefaultConnection();
        $primary = DB::connection($originalConnection);

        config([
            'database.connections.inventory_contender' => config("database.connections.{$originalConnection}"),
        ]);

        $contender = DB::connection('inventory_contender');

        $contender->statement("SET lock_timeout = '250ms'");

        $this->assertNotSame(
            $primary->selectOne('SELECT pg_backend_pid() AS pid')->pid,
            $contender->selectOne('SELECT pg_backend_pid() AS pid')->pid,
        );

        try {
            $primary->beginTransaction();

            $action->execute(
                $product->id,
                $actor,
                -7,
                'Primera salida',
            );

            DB::setDefaultConnection('inventory_contender');

            try {
                $action->execute(
                    $product->id,
                    $actor,
                    -7,
                    'Salida competidora',
                );

                $this->fail('La segunda conexión no respetó el bloqueo.');
            } catch (QueryException $exception) {
                $this->assertSame(
                    '55P03',
                    $exception->errorInfo[0]
                );
            }

            $primary->commit();

            try {
                $action->execute(
                    $product->id,
                    $actor,
                    -7,
                    'Reintento después del bloqueo',
                );

                $this->fail('Se permitió consumir existencias insuficientes.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey(
                    'quantity',
                    $exception->errors()
                );
            }
        } finally {
            while ($primary->transactionLevel() > 0) {
                $primary->rollBack();
            }

            while ($contender->transactionLevel() > 0) {
                $contender->rollBack();
            }

            DB::setDefaultConnection($originalConnection);
            DB::purge('inventory_contender');
        }

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 2);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'quantity' => -7,
            'stock_after' => 3,
            'reason' => 'Primera salida',
        ]);
    }
}
