<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductStoreTest extends TestCase
{
    public function test_guests_cannot_create_products(): void
    {
        $this->postJson('/api/v1/products', $this->validPayload())
            ->assertUnauthorized();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_sellers_cannot_create_products(): void
    {
        $this->actingAs($this->userWithRole(UserRole::Seller), 'web')
            ->postJson('/api/v1/products', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_supervisors_cannot_create_products(): void
    {
        $this->actingAs($this->userWithRole(UserRole::Supervisor), 'web')
            ->postJson('/api/v1/products', $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('products', 0);
    }

    public function test_admins_can_create_products(): void
    {
        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->postJson('/api/v1/products', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.sku', 'KEYBOARD-001')
            ->assertJsonPath('data.name', 'Teclado')
            ->assertJsonPath('data.price', '149.90');

        $this->assertDatabaseCount('products', 1);

        $this->assertDatabaseHas('products', [
            'sku' => 'KEYBOARD-001',
            'name' => 'Teclado',
            'price' => '149.90',
            'is_active' => true,
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->postJson('/api/v1/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku', 'name', 'price']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_duplicate_skus_are_rejected(): void
    {
        Product::create($this->validPayload());

        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->postJson('/api/v1/products', $this->validPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);

        $this->assertDatabaseCount('products', 1);
    }

    public function test_negative_prices_are_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['price'] = '-0.01';

        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->postJson('/api/v1/products', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_prices_with_extra_decimal_places_are_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['price'] = '149.999';

        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->postJson('/api/v1/products', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_unvalidated_fields_cannot_override_product_defaults(): void
    {
        $payload = $this->validPayload();
        $payload['is_active'] = false;

        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->postJson('/api/v1/products', $payload)
            ->assertCreated();

        $this->assertTrue(
            Product::where('sku', 'KEYBOARD-001')->firstOrFail()->is_active
        );
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();

        $user->role = $role;
        $user->save();

        return $user;
    }

    private function validPayload(): array
    {
        return [
            'sku' => 'KEYBOARD-001',
            'name' => 'Teclado',
            'description' => 'Producto de prueba',
            'price' => '149.90',
        ];
    }

    #[DataProvider('invalidProductData')]
    public function test_it_rejects_invalid_product_fields(
        string $field,
        mixed $value
    ): void {
        $payload = $this->validPayload();
        $payload[$field] = $value;

        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->postJson('/api/v1/products', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);

        $this->assertDatabaseCount('products', 0);
    }

    public static function invalidProductData(): array
    {
        return [
            'lowercase SKU' => ['sku', 'keyboard-001'],
            'SKU with spaces' => ['sku', 'KEYBOARD 001'],
            'SKU too long' => ['sku', str_repeat('A', 51)],
            'name too long' => ['name', str_repeat('A', 151)],
            'description too long' => ['description', str_repeat('A', 5001)],
            'numeric price instead of string' => ['price', 149.90],
            'price without decimals' => ['price', '149'],
            'price with one decimal' => ['price', '149.9'],
            'price above database limit' => ['price', '10000000000.00'],
        ];
    }

    #[DataProvider('validBoundaryPrices')]
    public function test_it_accepts_boundary_prices(string $price): void
    {
        $payload = $this->validPayload();
        $payload['price'] = $price;

        $this->actingAs($this->userWithRole(UserRole::Admin), 'web')
            ->postJson('/api/v1/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.price', $price);

        $this->assertSame(
            $price,
            Product::where('sku', $payload['sku'])->firstOrFail()->price
        );
    }

    public static function validBoundaryPrices(): array
    {
        return [
            'zero' => ['0.00'],
            'maximum' => ['9999999999.99'],
        ];
    }

    public function test_it_handles_a_sku_conflict_during_insertion(): void
    {
        $admin = $this->userWithRole(UserRole::Admin);
        $payload = $this->validPayload();

        Product::creating(function (Product $product): void {
            DB::table('products')->insert([
                'sku' => $product->sku,
                'name' => 'Producto que provoca el conflicto',
                'price' => '10.00',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->actingAs($admin, 'web')
            ->postJson('/api/v1/products', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku'])
            ->assertJsonPath(
                'errors.sku.0',
                'El SKU ya está registrado.'
            );

        $this->assertDatabaseCount('products', 0);
    }
}
