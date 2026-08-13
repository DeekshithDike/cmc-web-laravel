<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_income_runs', function (Blueprint $table) {
            $table->id();
            $table->date('as_of')->unique();
            $table->string('status', 16);
            $table->string('triggered_by', 32);
            $table->unsignedInteger('processed')->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_income_runs');
    }
};
