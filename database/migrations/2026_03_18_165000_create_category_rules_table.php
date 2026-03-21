<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_rules', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');
            $table->enum('match_type', ['contains', 'starts_with', 'ends_with'])->default('contains');
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_rules');
    }
};
