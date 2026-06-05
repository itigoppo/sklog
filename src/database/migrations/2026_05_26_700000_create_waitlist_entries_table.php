<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('waitlist_entries', function (Blueprint $table) {
      $table->id();
      $table->string('email')->unique();
      $table->string('handle');
      $table->string('did')->nullable(); // 不変のBluesky ID
      $table->string('display_name')->nullable();
      $table->text('avatar_url')->nullable();
      $table->text('description')->nullable();
      $table->enum('status', ['pending', 'invited', 'registered', 'expired_pending'])->default('pending'); // expired_pending (再送要望)を追加
      $table->timestamp('invited_at')->nullable();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('waitlist_entries');
  }
};
