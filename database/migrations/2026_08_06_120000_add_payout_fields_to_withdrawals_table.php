<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('payout_provider', 32)->nullable()->after('status');
            $table->string('payout_ref')->nullable()->after('payout_provider');
            $table->json('meta')->nullable()->after('payout_ref');
            $table->index(['payout_provider', 'payout_ref']);
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropIndex(['payout_provider', 'payout_ref']);
            $table->dropColumn(['payout_provider', 'payout_ref', 'meta']);
        });
    }
};
