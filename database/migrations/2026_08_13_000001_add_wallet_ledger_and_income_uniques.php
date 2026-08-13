<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('reason', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('reason');
        });

        Schema::table('carry_forwards', function (Blueprint $table) {
            $table->unique(['user_id', 'as_of']);
        });

        Schema::table('binary_incomes', function (Blueprint $table) {
            $table->unique(['user_id', 'earned_on']);
        });
    }

    public function down(): void
    {
        Schema::table('binary_incomes', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'earned_on']);
        });

        Schema::table('carry_forwards', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'as_of']);
        });

        Schema::dropIfExists('wallet_transactions');
    }
};
