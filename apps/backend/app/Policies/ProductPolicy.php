<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class ProductPolicy
{
    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
