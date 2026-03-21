<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            // Drop the old per-category-per-month structure
            $table->dropForeign(['category_id']);
            $table->dropColumn(['category_id', 'period_year', 'period_month', 'amount', 'spent']);

            // Add new persistent plan columns
            $table->string('name')->after('id');
            $table->unsignedTinyInteger('percentage')->after('name'); // 0–100
            $table->boolean('is_active')->default(true)->after('percentage');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropColumn(['name', 'percentage', 'is_active']);

            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('period_year');
            $table->integer('period_month');
            $table->bigInteger('amount')->default(0);
            $table->bigInteger('spent')->default(0);
        });
    }
};
