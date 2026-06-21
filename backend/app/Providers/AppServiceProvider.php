<?php

namespace App\Providers;

use App\Models\BalanceRetry;
use App\Models\Order;
use App\Models\RechargeTransaction;
use App\Policies\BalanceRetryPolicy;
use App\Policies\OrderPolicy;
use App\Policies\RechargeTransactionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider;

class AppServiceProvider extends AuthServiceProvider
{
    protected $policies = [
        Order::class => OrderPolicy::class,
        BalanceRetry::class => BalanceRetryPolicy::class,
        RechargeTransaction::class => RechargeTransactionPolicy::class,
    ];

    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
