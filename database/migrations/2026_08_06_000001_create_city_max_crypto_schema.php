<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * City Max Crypto domain schema.
 * Alters default users table, then creates MLM tables with indexes and FKs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('customer')->after('password');
            $table->string('status', 32)->default('inactive')->after('role');
            $table->boolean('is_active')->default(false)->after('status');
            $table->boolean('payment_status')->default(false)->after('is_active');
            $table->boolean('is_power_id')->default(false)->after('payment_status');
            $table->unsignedBigInteger('sponsor_id')->nullable()->after('is_power_id');
            $table->unsignedBigInteger('parent_id')->nullable()->after('sponsor_id');
            $table->string('position', 16)->nullable()->after('parent_id');
            $table->unsignedBigInteger('package_id')->nullable()->after('position');
            $table->date('expiry_date')->nullable()->after('package_id');
            $table->string('phone', 32)->nullable()->after('expiry_date');
            $table->string('country', 64)->nullable()->after('phone');
            $table->string('wallet_address', 128)->nullable()->after('country');
            $table->decimal('wallet_balance', 10, 2)->default(0)->after('wallet_address');

            $table->index(['role', 'status']);
            $table->index(['is_active', 'payment_status']);
            $table->index(['role', 'is_active', 'payment_status', 'expiry_date'], 'users_daily_income_idx');
            $table->index(['is_power_id', 'is_active']);
            $table->index('sponsor_id');
            $table->index('parent_id');
            $table->index('package_id');
            $table->index('expiry_date');
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('amount', 10, 2);
            $table->decimal('roi_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('sponsor_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('package_id')->references('id')->on('packages')->nullOnDelete();
        });

        Schema::create('binary_trees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('users_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('position', 16)->nullable();
            $table->foreignId('left_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('right_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['parent_id', 'position']);
            $table->index('parent_id');
        });

        Schema::create('binary_tree_lefts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->date('business_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'business_date']);
        });

        Schema::create('binary_tree_rights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->date('business_date')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'business_date']);
        });

        Schema::create('referral_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->date('earned_on')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'earned_on']);
        });

        Schema::create('binary_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('left_volume', 10, 2)->default(0);
            $table->decimal('right_volume', 10, 2)->default(0);
            $table->date('earned_on')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'earned_on']);
        });

        Schema::create('carry_forwards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('left_carry', 10, 2)->default(0);
            $table->decimal('right_carry', 10, 2)->default(0);
            $table->date('as_of')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'as_of']);
        });

        Schema::create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('roi_amount', 10, 2)->default(0);
            $table->decimal('binary_amount', 10, 2)->default(0);
            $table->decimal('referral_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->date('paid_on');
            $table->timestamps();

            $table->unique(['user_id', 'paid_on']);
            $table->index('paid_on');
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('payable_amount', 10, 2);
            $table->string('wallet_address', 128);
            $table->string('status', 32)->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'user_id']);
            $table->index('created_at');
            $table->index('processed_at');
        });

        Schema::create('renewal_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('renewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('previous_expiry')->nullable();
            $table->date('new_expiry')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('provider_ref')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 8)->default('USD');
            $table->string('status', 32)->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_ref']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('mcc_values', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('calculation_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_type', 64);
            $table->string('external_id')->nullable();
            $table->string('status', 32)->default('queued');
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['job_type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_jobs');
        Schema::dropIfExists('mcc_values');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('renewal_histories');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('payment_details');
        Schema::dropIfExists('carry_forwards');
        Schema::dropIfExists('binary_incomes');
        Schema::dropIfExists('referral_incomes');
        Schema::dropIfExists('binary_tree_rights');
        Schema::dropIfExists('binary_tree_lefts');
        Schema::dropIfExists('binary_trees');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['sponsor_id']);
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['package_id']);
        });

        Schema::dropIfExists('packages');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'status']);
            $table->dropIndex(['is_active', 'payment_status']);
            $table->dropIndex('users_daily_income_idx');
            $table->dropIndex(['is_power_id', 'is_active']);
            $table->dropIndex(['sponsor_id']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['package_id']);
            $table->dropIndex(['expiry_date']);

            $table->dropColumn([
                'role',
                'status',
                'is_active',
                'payment_status',
                'is_power_id',
                'sponsor_id',
                'parent_id',
                'position',
                'package_id',
                'expiry_date',
                'phone',
                'country',
                'wallet_address',
                'wallet_balance',
            ]);
        });
    }
};
