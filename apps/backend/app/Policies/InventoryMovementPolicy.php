<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class InventoryMovementPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::Admin,
            UserRole::Supervisor,
        ], true);
    }
}
