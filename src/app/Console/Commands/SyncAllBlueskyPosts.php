<?php

namespace App\Console\Commands;

use App\Jobs\SyncBlueskyPostsJob;
use App\Models\User;
use Illuminate\Console\Command;

class SyncAllBlueskyPosts extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'bluesky:sync-all';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = '全アクティブユーザーのBluesky投稿を同期します';

  /**
   * Execute the console command.
   */
  public function handle()
  {
    $this->info('Starting Bluesky synchronization for all users...');

    $users = User::whereHas('blueskyAccount', function ($query) {
      $query->where('is_auto_sync_enabled', true);
    })->get();

    if ($users->isEmpty()) {
      $this->warn('No users found with auto-sync enabled Bluesky accounts.');

      return Command::SUCCESS;
    }

    foreach ($users as $user) {
      $handle = $user->blueskyAccount->handle;
      $this->info("Dispatching sync job for user: @{$handle} (ID: {$user->id})");

      // ジョブをキューへ投入
      SyncBlueskyPostsJob::dispatch($user);
    }

    $this->info('All sync jobs have been successfully dispatched.');

    return Command::SUCCESS;
  }
}
