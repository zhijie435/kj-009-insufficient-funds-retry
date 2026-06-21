<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_retries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('required_amount');
            $table->unsignedInteger('current_balance');
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('max_retry')->default(5);
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->text('fail_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_retries');
    }
};
