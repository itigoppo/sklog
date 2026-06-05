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
    Schema::create('bluesky_accounts', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
      $table->string('did')->unique();
      $table->string('handle')->unique();
      $table->text('encrypted_app_password');
      $table->boolean('is_private')->default(false);
      $table->timestamp('registered_at')->nullable();
      $table->string('avatar_url')->nullable();
      $table->string('banner_url')->nullable();
      $table->text('description')->nullable();
      $table->integer('posts_count')->default(0);
      $table->timestamp('last_synced_at')->nullable();
      $table->string('sync_error_type')->nullable();
      $table->text('sync_error_message')->nullable();
      $table->string('next_cursor')->nullable();
      $table->boolean('is_archive_complete')->default(false);
      $table->boolean('is_syncing')->default(false);
      $table->boolean('is_auto_sync_enabled')->default(true);
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('bluesky_accounts');
  }
};
