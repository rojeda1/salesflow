<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryMovementStoreTest extends TestCase
{
    public function test_guests_cannot_record_movements(): void
    {
        $product = $this->createProduct();

        $this->postJson($this->endpoint($product), $this->validPayload())
            ->assertUnauthorized();

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_sellers_cannot_record_movements(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->userWithRole(UserRole::Seller), 'web')
            ->postJson($this->endpoint($product), $this->validPayload())
            ->assertForbidden();

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    #[DataProvider('authorizedRoles')]
    public function test_authorized_users_can_record_movements(
        UserRole $role,
    ): void {
        $product = $this->createProduct();
        $actor = $this->userWithRole($role);

        $this->actingAs($actor, 'web')
            ->postJson($this->endpoint($product), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.user_id', $actor->id)
            ->assertJsonPath('data.quantity', 10)
            ->assertJsonPath('data.stock_after', 10)
            ->assertJsonPath('data.reason', 'Recepción de mercadería');

        $this->assertSame(10, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 1);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'user_id' => $actor->id,
            'quantity' => 10,
            'stock_after' => 10,
        ]);
    }

    public static function authorizedRoles(): array
    {
        return [
            'administrator' => [UserRole::Admin],
            'supervisor' => [UserRole::Supervisor],
        ];
    }

    public function test_it_records_an_exit(): void
    {
        $product = $this->createProduct();
        $actor = $this->userWithRole(UserRole::Supervisor);

        $this->actingAs($actor, 'web')
            ->postJson($this->endpoint($product), $this->validPayload())
            ->assertCreated();

        $this->postJson($this->endpoint($product), [
            'quantity' => -3,
            'reason' => 'Retiro de unidades dañadas',
        ])
            ->assertCreated()
            ->assertJsonPath('data.quantity', -3)
            ->assertJsonPath('data.stock_after', 7);

        $this->assertSame(7, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_it_rejects_insufficient_stock(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->postJson($this->endpoint($product), [
                'quantity' => -1,
                'reason' => 'Salida sin existencias',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_it_rejects_inactive_products(): void
    {
        $product = $this->createProduct();
        $product->is_active = false;
        $product->save();

        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->postJson($this->endpoint($product), $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product']);

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_it_requires_quantity_and_reason(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->postJson($this->endpoint($product), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity', 'reason']);

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    #[DataProvider('invalidFields')]
    public function test_it_rejects_invalid_or_server_managed_fields(
        string $field,
        mixed $value,
    ): void {
        $product = $this->createProduct();
        $payload = $this->validPayload();
        $payload[$field] = $value;

        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->postJson($this->endpoint($product), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, $product->fresh()->stock);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public static function invalidFields(): array
    {
        return [
            'zero quantity' => ['quantity', 0],
            'fractional quantity' => ['quantity', 1.5],
            'quantity above maximum' => ['quantity', 2_147_483_648],
            'blank reason' => ['reason', '   '],
            'reason too long' => ['reason', str_repeat('A', 501)],
            'forged user' => ['user_id', 999],
            'forged product' => ['product_id', 999],
            'forged balance' => ['stock_after', 999],
            'forged timestamp' => ['created_at', '2020-01-01T00:00:00Z'],
        ];
    }

    private function createProduct(): Product
    {
        return Product::create([
            'sku' => 'INVENTORY-HTTP-001',
            'name' => 'Producto de prueba HTTP',
            'price' => '25.00',
        ]);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function endpoint(Product $product): string
    {
        return "/api/v1/products/{$product->id}/inventory-movements";
    }

    private function validPayload(): array
    {
        return [
            'quantity' => 10,
            'reason' => 'Recepción de mercadería',
        ];
    }
}
