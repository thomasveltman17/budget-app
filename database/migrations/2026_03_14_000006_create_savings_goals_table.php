<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('target_amount');
            $table->bigInteger('current_amount')->default(0);
            $table->date('deadline');
            $table->text('notes')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_goals');
    }
};
