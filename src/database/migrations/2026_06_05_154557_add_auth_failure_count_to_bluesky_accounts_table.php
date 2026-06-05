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
        Schema::table('bluesky_accounts', function (Blueprint $table) {
            $table->integer('auth_failure_count')->default(0)->after('is_auto_sync_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bluesky_accounts', function (Blueprint $table) {
            $table->dropColumn('auth_failure_count');
        });
    }
};
