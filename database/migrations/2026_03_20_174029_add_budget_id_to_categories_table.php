<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->foreignId('budget_id')->nullable()->constrained()->nullOnDelete()->after('parent_id');
        });

        Schema::dropIfExists('budget_category');
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropForeignIdFor(\App\Models\Budget::class);
            $table->dropColumn('budget_id');
        });

        Schema::create('budget_category', function (Blueprint $table): void {
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['budget_id', 'category_id']);
        });
    }
};
