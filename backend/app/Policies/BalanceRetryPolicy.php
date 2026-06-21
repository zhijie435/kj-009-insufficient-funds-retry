<?php

namespace App\Policies;

use App\Models\BalanceRetry;
use App\Models\User;

class BalanceRetryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, BalanceRetry $balanceRetry): bool
    {
        return $user->isAdmin() || $balanceRetry->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, BalanceRetry $balanceRetry): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, BalanceRetry $balanceRetry): bool
    {
        return $user->isAdmin();
    }

    public function retry(User $user, BalanceRetry $balanceRetry): bool
    {
        return $user->isAdmin() || $balanceRetry->user_id === $user->id;
    }

    public function cancel(User $user, BalanceRetry $balanceRetry): bool
    {
        return $user->isAdmin() || $balanceRetry->user_id === $user->id;
    }

    public function pending(User $user): bool
    {
        return $user->isAdmin();
    }
}
