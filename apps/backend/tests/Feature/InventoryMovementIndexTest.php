<?php

namespace Tests\Feature;

use App\Actions\Inventory\RecordInventoryMovement;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryMovementIndexTest extends TestCase
{
    public function test_guests_cannot_read_inventory(): void
    {
        $product = $this->createProduct();

        $this->getJson($this->endpoint($product))
            ->assertUnauthorized();
    }

    public function test_sellers_cannot_read_inventory(): void
    {
        $product = $this->createProduct();

        $this->actingAs($this->userWithRole(UserRole::Seller), 'web')
            ->getJson($this->endpoint($product))
            ->assertForbidden();
    }

    #[DataProvider('authorizedRoles')]
    public function test_authorized_users_can_read_an_empty_history(
        UserRole $role,
    ): void {
        $product = $this->createProduct();

        $this->actingAs($this->userWithRole($role), 'web')
            ->getJson($this->endpoint($product))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('product.id', $product->id)
            ->assertJsonPath('product.stock', 0);
    }

    public static function authorizedRoles(): array
    {
        return [
            'administrator' => [UserRole::Admin],
            'supervisor' => [UserRole::Supervisor],
        ];
    }

    public function test_it_returns_only_the_requested_product_history(): void
    {
        $product = $this->createProduct();
        $otherProduct = $this->createProduct('OTHER-001');
        $actor = $this->userWithRole(UserRole::Admin);
        $action = app(RecordInventoryMovement::class);

        $entry = $action->execute(
            $product->id,
            $actor,
            10,
            'Entrada inicial',
        );

        $exit = $action->execute(
            $product->id,
            $actor,
            -3,
            'Salida de unidades',
        );

        $otherMovement = $action->execute(
            $otherProduct->id,
            $actor,
            20,
            'Entrada de otro producto',
        );

        $this->actingAs($actor, 'web')
            ->getJson($this->endpoint($product))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.*.id', [$exit->id, $entry->id])
            ->assertJsonPath('data.0.product_id', $product->id)
            ->assertJsonPath('data.0.user_id', $actor->id)
            ->assertJsonPath('data.0.quantity', -3)
            ->assertJsonPath('data.0.stock_after', 7)
            ->assertJsonMissingPath('data.0.user')
            ->assertJsonPath('product.stock', 7);

        $this->assertDatabaseHas('inventory_movements', [
            'id' => $otherMovement->id,
            'product_id' => $otherProduct->id,
        ]);
    }

    public function test_it_paginates_the_history_in_descending_id_order(): void
    {
        $product = $this->createProduct();
        $actor = $this->userWithRole(UserRole::Supervisor);
        $action = app(RecordInventoryMovement::class);
        $movementIds = [];

        for ($number = 1; $number <= 21; $number++) {
            $movement = $action->execute(
                $product->id,
                $actor,
                1,
                "Entrada {$number}",
            );

            $movementIds[] = $movement->id;
        }

        $expectedIds = array_reverse($movementIds);

        $this->actingAs($actor, 'web')
            ->getJson($this->endpoint($product))
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.total', 21)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.*.id', array_slice($expectedIds, 0, 20))
            ->assertJsonPath('product.stock', 21);

        $this->getJson($this->endpoint($product).'?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('data.0.id', $expectedIds[20])
            ->assertJsonPath('product.stock', 21);
    }

    public function test_it_preserves_access_to_inactive_product_history(): void
    {
        $product = $this->createProduct();
        $actor = $this->userWithRole(UserRole::Admin);

        $movement = app(RecordInventoryMovement::class)->execute(
            $product->id,
            $actor,
            5,
            'Entrada antes de desactivar',
        );

        $product->refresh();
        $product->is_active = false;
        $product->save();

        $this->actingAs($actor, 'web')
            ->getJson($this->endpoint($product))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $movement->id)
            ->assertJsonPath('product.is_active', false)
            ->assertJsonPath('product.stock', 5);
    }

    public function test_it_returns_not_found_for_a_missing_product(): void
    {
        $product = $this->createProduct();
        $productId = $product->id;
        $product->delete();

        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->getJson("/api/v1/products/{$productId}/inventory-movements")
            ->assertNotFound();
    }

    private function createProduct(string $sku = 'HISTORY-001'): Product
    {
        return Product::create([
            'sku' => $sku,
            'name' => 'Producto con historial',
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
}
