<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Order;
use App\Models\RechargeTransaction;
use App\Models\BalanceRetry;
use App\Models\Wallet;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF;');
        DB::table('balance_retries')->truncate();
        DB::table('recharge_transactions')->truncate();
        DB::table('wallet_transactions')->truncate();
        DB::table('wallets')->truncate();
        DB::table('orders')->truncate();
        DB::table('users')->truncate();
        DB::statement('PRAGMA foreign_keys = ON;');

        $user1 = User::create([
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'password' => bcrypt('password'),
        ]);

        $user2 = User::create([
            'name' => '李四',
            'email' => 'lisi@example.com',
            'password' => bcrypt('password'),
        ]);

        $user3 = User::create([
            'name' => '王五',
            'email' => 'wangwu@example.com',
            'password' => bcrypt('password'),
        ]);

        $wallet1 = Wallet::create([
            'user_id' => $user1->id,
            'balance' => 15000,
            'frozen' => 0,
        ]);

        $wallet2 = Wallet::create([
            'user_id' => $user2->id,
            'balance' => 50000,
            'frozen' => 0,
        ]);

        $wallet3 = Wallet::create([
            'user_id' => $user3->id,
            'balance' => 5000,
            'frozen' => 0,
        ]);

        $order1 = Order::create([
            'user_id' => $user1->id,
            'order_no' => 'ORD' . date('YmdHis') . '0001',
            'title' => '购买商品A',
            'amount' => 19900,
            'status' => 'insufficient_balance',
            'retry_count' => 2,
            'max_retries' => 5,
            'retried_at' => now()->addMinutes(10),
            'fail_reason' => '余额不足，待充值后重试',
        ]);

        $order2 = Order::create([
            'user_id' => $user2->id,
            'order_no' => 'ORD' . date('YmdHis') . '0002',
            'title' => '购买商品B',
            'amount' => 29900,
            'status' => 'paid',
            'retry_count' => 0,
            'max_retries' => 5,
        ]);

        $order3 = Order::create([
            'user_id' => $user3->id,
            'order_no' => 'ORD' . date('YmdHis') . '0003',
            'title' => '购买商品C',
            'amount' => 8900,
            'status' => 'insufficient_balance',
            'retry_count' => 1,
            'max_retries' => 5,
            'retried_at' => now()->addMinutes(5),
            'fail_reason' => '余额不足，待充值后重试',
        ]);

        $order4 = Order::create([
            'user_id' => $user1->id,
            'order_no' => 'ORD' . date('YmdHis') . '0004',
            'title' => '购买商品D',
            'amount' => 9900,
            'status' => 'pending',
            'retry_count' => 0,
            'max_retries' => 5,
        ]);

        RechargeTransaction::create([
            'user_id' => $user2->id,
            'transaction_no' => 'TXN' . date('YmdHis') . '0001',
            'amount' => 29900,
            'payment_method' => 'wechat',
            'status' => 'completed',
            'paid_at' => now()->subDays(1),
        ]);

        RechargeTransaction::create([
            'user_id' => $user1->id,
            'transaction_no' => 'TXN' . date('YmdHis') . '0002',
            'amount' => 10000,
            'payment_method' => 'alipay',
            'status' => 'completed',
            'paid_at' => now()->subDays(2),
        ]);

        RechargeTransaction::create([
            'user_id' => $user3->id,
            'transaction_no' => 'TXN' . date('YmdHis') . '0003',
            'amount' => 5000,
            'payment_method' => 'wechat',
            'status' => 'pending',
        ]);

        BalanceRetry::create([
            'order_id' => $order1->id,
            'user_id' => $user1->id,
            'required_amount' => 19900,
            'current_balance' => 15000,
            'retry_count' => 2,
            'max_retry' => 5,
            'status' => 0,
            'last_retry_at' => now()->subMinutes(30),
            'next_retry_at' => now()->addMinutes(10),
        ]);

        BalanceRetry::create([
            'order_id' => $order3->id,
            'user_id' => $user3->id,
            'required_amount' => 8900,
            'current_balance' => 5000,
            'retry_count' => 1,
            'max_retry' => 5,
            'status' => 0,
            'last_retry_at' => now()->subMinutes(15),
            'next_retry_at' => now()->addMinutes(5),
        ]);

        BalanceRetry::create([
            'order_id' => $order2->id,
            'user_id' => $user2->id,
            'required_amount' => 29900,
            'current_balance' => 50000,
            'retry_count' => 0,
            'max_retry' => 5,
            'status' => 2,
            'last_retry_at' => now()->subDays(1),
        ]);
    }
}
