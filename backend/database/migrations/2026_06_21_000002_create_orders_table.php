<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('pending');
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('max_retries')->default(3);
            $table->timestamp('failed_at')->nullable();
            $table->text('fail_reason')->nullable();
            $table->timestamp('retried_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('order_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
