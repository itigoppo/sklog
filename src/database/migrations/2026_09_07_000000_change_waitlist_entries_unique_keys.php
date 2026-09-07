<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::table('waitlist_entries', function (Blueprint $table) {
      // メールアドレスは招待コードの配送先でしかなく、申請の同一性判定には使わないため一意制約を外す
      // （同じアドレスで複数のハンドルを申請できるようにする）
      $table->dropUnique(['email']);

      // 申請の同一性は不変のDIDで判定する。
      // ハンドル名はBluesky側で変更でき、手放したハンドルは別アカウントが再利用できるため一意にはできない。
      // did移行前のレコードは null のまま許容する（MySQLは NULL の重複を許す）。
      $table->unique('did');
    });
  }

  public function down(): void
  {
    Schema::table('waitlist_entries', function (Blueprint $table) {
      $table->dropUnique(['did']);
      $table->unique('email');
    });
  }
};
