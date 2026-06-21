<?php

namespace App\Policies;

use App\Models\RechargeTransaction;
use App\Models\User;

class RechargeTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RechargeTransaction $rechargeTransaction): bool
    {
        return $user->isAdmin() || $rechargeTransaction->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, RechargeTransaction $rechargeTransaction): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, RechargeTransaction $rechargeTransaction): bool
    {
        return $user->isAdmin();
    }
}
