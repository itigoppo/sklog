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
    // tagsテーブルの作成
    Schema::create('tags', function (Blueprint $table) {
      $table->uuid('id')->primary();
      $table->string('name')->unique();
      $table->timestamps();
    });

    // post_tags中間テーブルの作成
    Schema::create('post_tags', function (Blueprint $table) {
      $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
      $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();
      $table->timestamps();

      $table->unique(['post_id', 'tag_id']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('post_tags');
    Schema::dropIfExists('tags');
  }
};
