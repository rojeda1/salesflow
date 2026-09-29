<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    public function test_new_users_have_the_seller_role(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            UserRole::Seller,
            $user->fresh()->role
        );
    }
}
