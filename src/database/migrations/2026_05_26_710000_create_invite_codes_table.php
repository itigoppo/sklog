<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('invite_codes', function (Blueprint $table) {
      $table->uuid('id')->primary(); // 主キーを UUID に変更
      $table->string('code', 32)->unique();
      $table->string('handle')->nullable();
      $table->string('did')->nullable(); // 申請者の不変Bluesky ID
      $table->foreignId('waitlist_entry_id')->nullable()->constrained('waitlist_entries')->nullOnDelete();
      $table->foreignUuid('used_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamp('used_at')->nullable();
      $table->timestamp('expires_at')->nullable(); // 有効期限
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('invite_codes');
  }
};
