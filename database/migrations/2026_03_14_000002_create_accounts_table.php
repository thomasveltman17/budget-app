<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['checking', 'savings', 'credit_card', 'investment']);
            $table->bigInteger('balance')->default(0);
            $table->string('broker')->nullable();
            $table->text('api_key')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
