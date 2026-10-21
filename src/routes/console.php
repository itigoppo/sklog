<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
  $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 全ユーザーのBluesky同期コマンドを1時間ごとに自動実行
Schedule::command('bluesky:sync-all')->hourly();

// キューに溜まったジョブを5分ごとに処理する
Schedule::command('queue:work --stop-when-empty')
  ->everyFiveMinutes()
  ->withoutOverlapping()
  ->runInBackground()
  ->appendOutputTo(storage_path('logs/queue.log'));
