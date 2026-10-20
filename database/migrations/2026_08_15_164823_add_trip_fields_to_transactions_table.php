<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('trip_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->bigInteger('share_amount')->nullable()->after('amount');
            $table->boolean('is_settlement')->default(false)->after('is_reviewed');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
            $table->dropColumn(['trip_id', 'share_amount', 'is_settlement']);
        });
    }
};
