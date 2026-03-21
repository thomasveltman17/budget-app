<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('budget_start_day')->default(1);
            $table->bigInteger('monthly_income')->default(0);
            $table->enum('budgeting_method', ['fifty_thirty_twenty', 'seventy_twenty_ten', 'pay_yourself_first', 'custom'])->default('fifty_thirty_twenty');
            $table->boolean('onboarding_completed')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['budget_start_day', 'monthly_income', 'budgeting_method', 'onboarding_completed']);
        });
    }
};
