<?php

namespace Tests\Feature;

use App\Jobs\SyncBlueskyPostsJob;
use App\Models\BlueskyAccount;
use App\Models\Post;
use App\Models\User;
use App\Services\BlueskyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class SyncBlueskyPostsJobTest extends TestCase
{
  use RefreshDatabase;

  private User $user;

  private BlueskyAccount $account;

  protected function setUp(): void
  {
    parent::setUp();

    $this->user = User::create([
      'name' => 'テストユーザー',
      'email' => 'test@example.com',
      'password' => bcrypt('password'),
    ]);

    $this->account = BlueskyAccount::create([
      'user_id' => $this->user->id,
      'did' => 'did:plc:test123456',
      'handle' => 'test.bsky.social',
      'encrypted_app_password' => 'secret_app_pass',
      'is_private' => false,
      'description' => 'テストプロフィールです',
    ]);
  }

  public function test_initial_sync_sets_next_cursor(): void
  {
    // BlueskyServiceのモックを作成
    $blueskyService = Mockery::mock(BlueskyService::class);

    // authenticateのモック
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with('test.bsky.social', 'secret_app_pass')
      ->andReturn([
        'accessJwt' => 'mock_token',
        'did' => 'did:plc:test123456',
      ]);

    // getProfile의モック
    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->with('did:plc:test123456', 'mock_token')
      ->andReturn([
        'avatar' => 'mock_avatar',
        'banner' => 'mock_banner',
        'description' => 'テストプロフィールです',
        'postsCount' => 150,
      ]);

    // getAuthorFeed (最新の同期: cursorなし) のモック
    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->with('did:plc:test123456', 100, null, 'mock_token')
      ->andReturn([
        'feed' => [
          [
            'post' => [
              'uri' => 'at://did:plc:test123456/app.bsky.feed.post/latest1',
              'cid' => 'cid_latest1',
              'author' => ['did' => 'did:plc:test123456'],
              'record' => [
                'text' => '最新の投稿',
                'createdAt' => Carbon::now()->toIso8601String(),
              ],
            ],
          ],
        ],
        'cursor' => 'cursor_for_past_1',
      ]);

    // getAuthorFeed (過去の同期: cursor_for_past_1を指定) のモック
    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->with('did:plc:test123456', 100, 'cursor_for_past_1', 'mock_token')
      ->andReturn([
        'feed' => [
          [
            'post' => [
              'uri' => 'at://did:plc:test123456/app.bsky.feed.post/past1',
              'cid' => 'cid_past1',
              'author' => ['did' => 'did:plc:test123456'],
              'record' => [
                'text' => '過去の古い投稿',
                'createdAt' => Carbon::now()->subDay()->toIso8601String(),
              ],
            ],
          ],
        ],
        'cursor' => 'cursor_for_past_2',
      ]);

    // アプリケーションコンテナへモックを登録
    $this->app->instance(BlueskyService::class, $blueskyService);

    // ジョブを実行
    SyncBlueskyPostsJob::dispatchSync($this->user);

    // アカウントの検証
    $this->account->refresh();
    $this->assertEquals('cursor_for_past_2', $this->account->next_cursor);
    $this->assertFalse($this->account->is_archive_complete);

    // データベースにポストが保存されたか検証
    $this->assertDatabaseHas('posts', [
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/latest1',
      'text' => '最新の投稿',
    ]);

    $this->assertDatabaseHas('posts', [
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/past1',
      'text' => '過去の古い投稿',
    ]);
  }

  public function test_subsequent_sync_uses_next_cursor_and_updates_it(): void
  {
    // next_cursorをあらかじめセットしておく
    $this->account->update([
      'next_cursor' => 'existing_cursor',
    ]);

    $blueskyService = Mockery::mock(BlueskyService::class);

    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->andReturn([
        'accessJwt' => 'mock_token',
        'did' => 'did:plc:test123456',
      ]);

    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->andReturn([
        'description' => 'テストプロフィールです',
      ]);

    // 1. 最新の同期 (早期終了するケース：すでに投稿が存在)
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/latest_existing',
      'cid' => 'cid_latest_existing',
      'text' => 'すでにDBにある最新投稿',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->with('did:plc:test123456', 100, null, 'mock_token')
      ->andReturn([
        'feed' => [
          [
            'post' => [
              'uri' => 'at://did:plc:test123456/app.bsky.feed.post/latest_existing',
              'cid' => 'cid_latest_existing',
              'author' => ['did' => 'did:plc:test123456'],
              'record' => [
                'text' => 'すでにDBにある最新投稿',
                'createdAt' => Carbon::now()->toIso8601String(),
              ],
            ],
          ],
        ],
        'cursor' => 'new_latest_cursor',
      ]);

    // 2. 過去の同期 (existing_cursorを使用)
    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->with('did:plc:test123456', 100, 'existing_cursor', 'mock_token')
      ->andReturn([
        'feed' => [
          [
            'post' => [
              'uri' => 'at://did:plc:test123456/app.bsky.feed.post/past1',
              'cid' => 'cid_past1',
              'author' => ['did' => 'did:plc:test123456'],
              'record' => [
                'text' => '過去の古い投稿',
                'createdAt' => Carbon::now()->subDays(2)->toIso8601String(),
              ],
            ],
          ],
        ],
        'cursor' => 'next_past_cursor',
      ]);

    $this->app->instance(BlueskyService::class, $blueskyService);

    SyncBlueskyPostsJob::dispatchSync($this->user);

    $this->account->refresh();
    $this->assertEquals('next_past_cursor', $this->account->next_cursor);
    $this->assertFalse($this->account->is_archive_complete);

    // データベースに過去ポストが保存されたか検証
    $this->assertDatabaseHas('posts', [
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/past1',
      'text' => '過去の古い投稿',
    ]);
  }

  public function test_sync_completes_archive_when_no_more_cursors(): void
  {
    $this->account->update([
      'next_cursor' => 'last_cursor',
    ]);

    $blueskyService = Mockery::mock(BlueskyService::class);

    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->andReturn([
        'accessJwt' => 'mock_token',
        'did' => 'did:plc:test123456',
      ]);

    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->andReturn([
        'description' => 'テストプロフィールです',
      ]);

    // 最新同期 (投稿なし)
    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->with('did:plc:test123456', 100, null, 'mock_token')
      ->andReturn([
        'feed' => [],
        'cursor' => null,
      ]);

    // 過去同期 (最古に到達、新しいcursorが返らない)
    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->with('did:plc:test123456', 100, 'last_cursor', 'mock_token')
      ->andReturn([
        'feed' => [
          [
            'post' => [
              'uri' => 'at://did:plc:test123456/app.bsky.feed.post/very_old',
              'cid' => 'cid_very_old',
              'author' => ['did' => 'did:plc:test123456'],
              'record' => [
                'text' => '最古の投稿',
                'createdAt' => Carbon::now()->subDays(10)->toIso8601String(),
              ],
            ],
          ],
        ],
        'cursor' => null, // 終了！
      ]);

    $this->app->instance(BlueskyService::class, $blueskyService);

    SyncBlueskyPostsJob::dispatchSync($this->user);

    $this->account->refresh();
    $this->assertNull($this->account->next_cursor);
    $this->assertTrue($this->account->is_archive_complete);

    $this->assertDatabaseHas('posts', [
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/very_old',
      'text' => '最古の投稿',
    ]);
  }

  public function test_sync_extracts_valid_hashtags_and_excludes_invalid_ones(): void
  {
    $blueskyService = Mockery::mock(BlueskyService::class);

    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->andReturn([
        'accessJwt' => 'mock_token',
        'did' => 'did:plc:test123456',
      ]);

    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->andReturn([
        'description' => 'テストプロフィールです',
      ]);

    // 最新の同期: カラーコードや不適切なハッシュタグ、正しいハッシュタグを含む投稿
    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->with('did:plc:test123456', 100, null, 'mock_token')
      ->andReturn([
        'feed' => [
          [
            'post' => [
              'uri' => 'at://did:plc:test123456/app.bsky.feed.post/tag_test',
              'cid' => 'cid_tag_test',
              'author' => ['did' => 'did:plc:test123456'],
              'record' => [
                'text' => "#noSpaceAfterTag\nwebくん#000か#fffしか背景色 #validTag \n #anotherTag\n記号直結#Laravelは除外 #12345 #000\n#noSpaceBeforeTag",
                'createdAt' => Carbon::now()->toIso8601String(),
              ],
            ],
          ],
        ],
        'cursor' => null,
      ]);

    $this->app->instance(BlueskyService::class, $blueskyService);

    // ジョブを実行
    SyncBlueskyPostsJob::dispatchSync($this->user);

    // 正しいタグはDBに登録されていること
    $this->assertDatabaseHas('tags', ['name' => 'validTag']);
    $this->assertDatabaseHas('tags', ['name' => 'anotherTag']);
    $this->assertDatabaseHas('tags', ['name' => 'noSpaceBeforeTag']);
    $this->assertDatabaseHas('tags', ['name' => 'noSpaceAfterTag']);

    // 不正なタグ（カラーコードや文字の間に埋め込まれた#、数字のみのハッシュタグ）はDBに登録されていないこと
    $this->assertDatabaseMissing('tags', ['name' => '000']);
    $this->assertDatabaseMissing('tags', ['name' => 'fff']);
    $this->assertDatabaseMissing('tags', ['name' => 'Laravelは除外']);
    $this->assertDatabaseMissing('tags', ['name' => '12345']);
  }

  public function test_sync_job_controls_is_syncing_lifecycle(): void
  {
    $blueskyService = Mockery::mock(BlueskyService::class);

    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->andReturn([
        'accessJwt' => 'mock_token',
        'did' => 'did:plc:test123456',
      ]);

    // getProfile呼び出し時（ジョブ処理中）にis_syncingがtrueになっていることを検証
    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->andReturnUsing(function () {
        $this->account->refresh();
        $this->assertTrue($this->account->is_syncing);

        return [
          'description' => 'テストプロフィールです',
        ];
      });

    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->andReturn([
        'feed' => [],
        'cursor' => null,
      ]);

    $this->app->instance(BlueskyService::class, $blueskyService);

    // 実行前は false
    $this->account->refresh();
    $this->assertFalse($this->account->is_syncing);

    // ジョブを実行
    SyncBlueskyPostsJob::dispatchSync($this->user);

    // 実行完了後は false に戻っていること
    $this->account->refresh();
    $this->assertFalse($this->account->is_syncing);
  }

  public function test_sync_does_not_extract_hashtags_from_reposts(): void
  {
    $blueskyService = Mockery::mock(BlueskyService::class);

    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->andReturn([
        'accessJwt' => 'mock_token',
        'did' => 'did:plc:test123456',
      ]);

    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->andReturn([
        'description' => 'テストプロフィールです',
      ]);

    // 最新の同期: リポストにハッシュタグが含まれているケース
    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->with('did:plc:test123456', 100, null, 'mock_token')
      ->andReturn([
        'feed' => [
          [
            'post' => [
              'uri' => 'at://did:plc:other98765/app.bsky.feed.post/reposted_post',
              'cid' => 'cid_repost',
              'author' => ['did' => 'did:plc:other98765'],
              'record' => [
                'text' => 'これはリポストに含まれる #RepostTag です',
                'createdAt' => Carbon::now()->toIso8601String(),
              ],
            ],
            'reason' => [
              '$type' => 'app.bsky.feed.defs#reasonRepost',
              'by' => [
                'did' => 'did:plc:test123456',
                'handle' => 'test.bsky.social',
              ],
              'indexedAt' => Carbon::now()->toIso8601String(),
            ],
          ],
        ],
        'cursor' => null,
      ]);

    $this->app->instance(BlueskyService::class, $blueskyService);

    // ジョブを実行
    SyncBlueskyPostsJob::dispatchSync($this->user);

    // リポストされたポストはDBに保存されていること
    $this->assertDatabaseHas('posts', [
      'uri' => 'repost-did:plc:test123456-reposted_post',
    ]);

    // しかし、リポスト内のハッシュタグはDBに登録されていないことをアサート
    $this->assertDatabaseMissing('tags', ['name' => 'RepostTag']);
  }

  public function test_sync_generates_unique_pseudo_uri_for_reposts_and_prevents_overwrite(): void
  {
    $blueskyService = Mockery::mock(BlueskyService::class);

    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->andReturn([
        'accessJwt' => 'mock_token',
        'did' => 'did:plc:test123456',
      ]);

    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->andReturn([
        'description' => 'テストプロフィールです',
      ]);

    // すでに別の形で「他人のオリジナル投稿」が自分のデータベースに存在している状態をシミュレート
    // （例：過去に誰かに引用されたりして保存されていた場合）
    $originalUri = 'at://did:plc:other98765/app.bsky.feed.post/target_post';
    Post::create([
      'user_id' => $this->user->id,
      'uri' => $originalUri,
      'cid' => 'cid_original',
      'text' => '他人のオリジナル投稿',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now()->subDays(5),
    ]);

    // 最新の同期で、同じ投稿を自分が「リポスト」したフィードが返ってくるケース
    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->with('did:plc:test123456', 100, null, 'mock_token')
      ->andReturn([
        'feed' => [
          [
            'post' => [
              'uri' => $originalUri,
              'cid' => 'cid_repost_new',
              'author' => ['did' => 'did:plc:other98765'],
              'record' => [
                'text' => '他人のオリジナル投稿',
                'createdAt' => Carbon::now()->subDays(5)->toIso8601String(),
              ],
            ],
            'reason' => [
              '$type' => 'app.bsky.feed.defs#reasonRepost',
              'by' => [
                'did' => 'did:plc:test123456',
                'handle' => 'test.bsky.social',
              ],
              'indexedAt' => Carbon::now()->toIso8601String(), // 自分がリポストした時間（最新）
            ],
          ],
        ],
        'cursor' => null,
      ]);

    $this->app->instance(BlueskyService::class, $blueskyService);

    // ジョブを実行
    SyncBlueskyPostsJob::dispatchSync($this->user);

    // オリジナル投稿のレコードが上書きされず、元の uri と 5日前の posted_at を維持していることを検証
    $this->assertDatabaseHas('posts', [
      'uri' => $originalUri,
      'posted_at' => Carbon::now()->subDays(5)->format('Y-m-d H:i:s'),
    ]);

    // 自分がリポストした「リポストカード」が、ユニークな疑似URIで独立して保存されていることを検証
    $this->assertDatabaseHas('posts', [
      'uri' => 'repost-did:plc:test123456-target_post',
      'posted_at' => Carbon::now()->format('Y-m-d H:i:s'),
    ]);

    // データベースに両方のレコードが綺麗に共存していることをアサート（合計2件）
    $this->assertEquals(2, Post::where('user_id', $this->user->id)->count());
  }

  public function test_sync_job_automatically_detects_private_account_status(): void
  {
    $blueskyService = Mockery::mock(BlueskyService::class);

    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->andReturn([
        'accessJwt' => 'mock_token',
        'did' => 'did:plc:test123456',
      ]);

    // getProfile が !no-unauthenticated ラベルを含むプロフィールを返す
    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->andReturn([
        'description' => 'テストプロフィールです',
        'labels' => [
          ['val' => '!no-unauthenticated'],
        ],
      ]);

    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->andReturn([
        'feed' => [],
        'cursor' => null,
      ]);

    $this->app->instance(BlueskyService::class, $blueskyService);

    // 同期前は is_private は false
    $this->assertFalse($this->account->is_private);

    // ジョブを実行
    SyncBlueskyPostsJob::dispatchSync($this->user);

    // 同期完了後は is_private が true に更新されていること
    $this->account->refresh();
    $this->assertTrue($this->account->is_private);
  }

  public function test_sync_saves_registered_at_from_profile_created_at(): void
  {
    $blueskyService = Mockery::mock(BlueskyService::class);

    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->andReturn([
        'accessJwt' => 'mock_token',
        'did' => 'did:plc:test123456',
      ]);

    // getProfile が createdAt を返す
    $createdAtStr = '2024-10-15T12:34:56.789Z';
    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->andReturn([
        'description' => 'テストプロフィールです',
        'createdAt' => $createdAtStr,
      ]);

    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->andReturn([
        'feed' => [],
        'cursor' => null,
      ]);

    $this->app->instance(BlueskyService::class, $blueskyService);

    // 同期前は registered_at は null
    $this->assertNull($this->account->registered_at);

    // ジョブを実行
    SyncBlueskyPostsJob::dispatchSync($this->user);

    // 同期完了後は registered_at が正しく保存・パースされていること
    $this->account->refresh();
    $this->assertNotNull($this->account->registered_at);
    $this->assertEquals('2024-10-15 21:34:56', $this->account->registered_at->format('Y-m-d H:i:s')); // JST (Asia/Tokyo)
  }

  public function test_initial_sync_fetches_all_posts_even_if_partial_run_left_some_in_db(): void
  {
    // 初回同期が途中失敗して一部だけDBに入っているケースをシミュレート
    // next_cursor=null、is_archive_complete=false の状態で1件だけ保存済み
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_newest',
      'cid' => 'cid_newest',
      'text' => '最新の投稿（前回の途中失敗で保存済み）',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    $blueskyService = Mockery::mock(BlueskyService::class);

    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->andReturn([
        'accessJwt' => 'mock_token',
        'did' => 'did:plc:test123456',
      ]);

    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->andReturn([
        'description' => 'テストプロフィールです',
      ]);

    // Step2: 最新100件のうち、1件目は保存済み、2件目と3件目は未保存
    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->with('did:plc:test123456', 100, null, 'mock_token')
      ->andReturn([
        'feed' => [
          [
            'post' => [
              'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_newest',
              'cid' => 'cid_newest',
              'author' => ['did' => 'did:plc:test123456'],
              'record' => [
                'text' => '最新の投稿（前回の途中失敗で保存済み）',
                'createdAt' => Carbon::now()->toIso8601String(),
              ],
            ],
          ],
          [
            'post' => [
              'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_middle1',
              'cid' => 'cid_middle1',
              'author' => ['did' => 'did:plc:test123456'],
              'record' => [
                'text' => '中間の投稿1（未保存、breakだとスキップされてしまう）',
                'createdAt' => Carbon::now()->subHour()->toIso8601String(),
              ],
            ],
          ],
          [
            'post' => [
              'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_middle2',
              'cid' => 'cid_middle2',
              'author' => ['did' => 'did:plc:test123456'],
              'record' => [
                'text' => '中間の投稿2（未保存、breakだとスキップされてしまう）',
                'createdAt' => Carbon::now()->subHours(2)->toIso8601String(),
              ],
            ],
          ],
        ],
        'cursor' => 'cursor_to_archive',
      ]);

    // Step3: アーカイブ
    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->with('did:plc:test123456', 100, 'cursor_to_archive', 'mock_token')
      ->andReturn([
        'feed' => [
          [
            'post' => [
              'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_old',
              'cid' => 'cid_old',
              'author' => ['did' => 'did:plc:test123456'],
              'record' => [
                'text' => '古い投稿',
                'createdAt' => Carbon::now()->subDay()->toIso8601String(),
              ],
            ],
          ],
        ],
        'cursor' => null,
      ]);

    $this->app->instance(BlueskyService::class, $blueskyService);

    SyncBlueskyPostsJob::dispatchSync($this->user);

    // 中間の投稿（未保存分）がすべて保存されていること
    $this->assertDatabaseHas('posts', [
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_middle1',
    ]);
    $this->assertDatabaseHas('posts', [
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_middle2',
    ]);
    $this->assertDatabaseHas('posts', [
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_old',
    ]);

    $this->account->refresh();
    $this->assertTrue($this->account->is_archive_complete);
    $this->assertEquals(4, Post::where('user_id', $this->user->id)->count());
  }

  public function test_sync_job_handles_consecutive_auth_failures_and_disables_auto_sync(): void
  {
    $blueskyService = Mockery::mock(BlueskyService::class);

    // 認証エラーを返すようにモック（1回目、2回目、3回目）
    $blueskyService->shouldReceive('authenticate')
      ->times(3)
      ->andThrow(new \Exception('Invalid identifier or password'));

    $this->app->instance(BlueskyService::class, $blueskyService);

    // 初期状態の確認
    $this->account->refresh();
    $this->assertTrue($this->account->is_auto_sync_enabled);
    $this->assertEquals(0, $this->account->auth_failure_count);

    // 1回目の失敗
    try {
      SyncBlueskyPostsJob::dispatchSync($this->user);
    } catch (\Exception $e) {
    }
    $this->account->refresh();
    $this->assertTrue($this->account->is_auto_sync_enabled);
    $this->assertEquals(1, $this->account->auth_failure_count);
    $this->assertEquals('auth_failed', $this->account->sync_error_type);

    // 2回目の失敗
    try {
      SyncBlueskyPostsJob::dispatchSync($this->user);
    } catch (\Exception $e) {
    }
    $this->account->refresh();
    $this->assertTrue($this->account->is_auto_sync_enabled);
    $this->assertEquals(2, $this->account->auth_failure_count);

    // 3回目の失敗（ここで is_auto_sync_enabled が false になる）
    try {
      SyncBlueskyPostsJob::dispatchSync($this->user);
    } catch (\Exception $e) {
    }
    $this->account->refresh();
    $this->assertFalse($this->account->is_auto_sync_enabled);
    $this->assertEquals(3, $this->account->auth_failure_count);
  }

  public function test_sync_job_resets_auth_failure_count_on_success(): void
  {
    // あらかじめ失敗カウントを 2 に設定しておく
    $this->account->update([
      'auth_failure_count' => 2,
    ]);

    $blueskyService = Mockery::mock(BlueskyService::class);

    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->andReturn([
        'accessJwt' => 'mock_token',
        'did' => 'did:plc:test123456',
      ]);

    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->andReturn([
        'description' => 'テストプロフィールです',
      ]);

    $blueskyService->shouldReceive('getAuthorFeed')
      ->once()
      ->andReturn([
        'feed' => [],
        'cursor' => null,
      ]);

    $this->app->instance(BlueskyService::class, $blueskyService);

    // ジョブを実行して成功させる
    SyncBlueskyPostsJob::dispatchSync($this->user);

    // 成功したためカウントが 0 にリセットされ、is_auto_sync_enabled は true のままであることを検証
    $this->account->refresh();
    $this->assertEquals(0, $this->account->auth_failure_count);
    $this->assertTrue($this->account->is_auto_sync_enabled);
  }
}
