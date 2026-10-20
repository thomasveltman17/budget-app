<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('destination')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->bigInteger('budget_amount')->nullable();
            $table->unsignedTinyInteger('participants')->default(1);
            $table->enum('status', ['planned', 'active', 'settling', 'settled'])->default('planned');
            $table->boolean('counts_toward_budget')->default(true);
            $table->foreignId('savings_goal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('color', 7)->default('#F59E0B');
            $table->string('icon')->default('heroicon-o-map');
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
