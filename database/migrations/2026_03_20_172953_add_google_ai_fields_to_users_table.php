<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('google_ai_api_key')->nullable()->after('onboarding_completed');
            $table->string('google_ai_model', 100)->nullable()->after('google_ai_api_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['google_ai_api_key', 'google_ai_model']);
        });
    }
};
