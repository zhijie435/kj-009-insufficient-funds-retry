<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Order;
use App\Models\RechargeRecord;
use App\Models\BalanceRetry;
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
        DB::table('recharge_records')->truncate();
        DB::table('orders')->truncate();
        DB::table('users')->truncate();
        DB::statement('PRAGMA foreign_keys = ON;');

        $user1 = User::create([
            'name' => '张三',
            'email' => 'zhangsan@example.com',
            'password' => bcrypt('password'),
            'balance' => 150.00,
        ]);

        $user2 = User::create([
            'name' => '李四',
            'email' => 'lisi@example.com',
            'password' => bcrypt('password'),
            'balance' => 500.00,
        ]);

        $user3 = User::create([
            'name' => '王五',
            'email' => 'wangwu@example.com',
            'password' => bcrypt('password'),
            'balance' => 50.00,
        ]);

        $order1 = Order::create([
            'user_id' => $user1->id,
            'order_no' => 'ORD' . date('YmdHis') . '0001',
            'amount' => 199.00,
            'status' => 2,
            'retry_count' => 2,
            'retry_at' => now()->addMinutes(10),
            'remark' => '购买商品A，余额不足待重试',
        ]);

        $order2 = Order::create([
            'user_id' => $user2->id,
            'order_no' => 'ORD' . date('YmdHis') . '0002',
            'amount' => 299.00,
            'status' => 1,
            'retry_count' => 0,
            'remark' => '购买商品B，已支付',
        ]);

        $order3 = Order::create([
            'user_id' => $user3->id,
            'order_no' => 'ORD' . date('YmdHis') . '0003',
            'amount' => 89.00,
            'status' => 2,
            'retry_count' => 1,
            'retry_at' => now()->addMinutes(5),
            'remark' => '购买商品C，余额不足待重试',
        ]);

        $order4 = Order::create([
            'user_id' => $user1->id,
            'order_no' => 'ORD' . date('YmdHis') . '0004',
            'amount' => 99.00,
            'status' => 0,
            'retry_count' => 0,
            'remark' => '购买商品D，待支付',
        ]);

        RechargeRecord::create([
            'user_id' => $user2->id,
            'order_id' => $order2->id,
            'transaction_no' => 'TXN' . date('YmdHis') . '0001',
            'amount' => 299.00,
            'pay_type' => 1,
            'status' => 1,
            'paid_at' => now()->subDays(1),
        ]);

        RechargeRecord::create([
            'user_id' => $user1->id,
            'transaction_no' => 'TXN' . date('YmdHis') . '0002',
            'amount' => 100.00,
            'pay_type' => 2,
            'status' => 1,
            'paid_at' => now()->subDays(2),
        ]);

        RechargeRecord::create([
            'user_id' => $user3->id,
            'transaction_no' => 'TXN' . date('YmdHis') . '0003',
            'amount' => 50.00,
            'pay_type' => 1,
            'status' => 0,
        ]);

        BalanceRetry::create([
            'order_id' => $order1->id,
            'user_id' => $user1->id,
            'required_amount' => 199.00,
            'current_balance' => 150.00,
            'retry_count' => 2,
            'max_retry' => 5,
            'status' => 0,
            'last_retry_at' => now()->subMinutes(30),
            'next_retry_at' => now()->addMinutes(10),
        ]);

        BalanceRetry::create([
            'order_id' => $order3->id,
            'user_id' => $user3->id,
            'required_amount' => 89.00,
            'current_balance' => 50.00,
            'retry_count' => 1,
            'max_retry' => 5,
            'status' => 0,
            'last_retry_at' => now()->subMinutes(15),
            'next_retry_at' => now()->addMinutes(5),
        ]);

        BalanceRetry::create([
            'order_id' => $order2->id,
            'user_id' => $user2->id,
            'required_amount' => 299.00,
            'current_balance' => 500.00,
            'retry_count' => 0,
            'max_retry' => 5,
            'status' => 2,
            'last_retry_at' => now()->subDays(1),
        ]);
    }
}
