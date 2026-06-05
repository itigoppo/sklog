<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\BlueskyAccount;
use App\Models\Post;
use App\Models\Tag;
use App\Services\BlueskyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Mockery;

class HandleChangeConfirmTest extends TestCase
{
  use RefreshDatabase;

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  /**
   * ログイン時にハンドル名の変更が検知され、確認画面へリダイレクトされること。
   */
  public function test_login_redirects_to_confirm_handle_change_when_handle_mismatch(): void
  {
    $user = User::create(['name' => 'テスト太郎']);
    $bskyAccount = BlueskyAccount::create([
      'id' => \Illuminate\Support\Str::uuid(),
      'user_id' => $user->id,
      'did' => 'did:plc:test777',
      'handle' => 'old-handle.bsky.social', // 古いハンドル名
      'encrypted_app_password' => 'secret_app_pass',
    ]);

    $blueskyService = Mockery::mock(BlueskyService::class);
    // 認証時に新ハンドルが返ってくるモック
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with('new-handle.bsky.social', 'secret_app_pass')
      ->andReturn([
        'did' => 'did:plc:test777',
        'handle' => 'new-handle.bsky.social',
        'accessJwt' => 'mock_token',
      ]);

    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->with('did:plc:test777', 'mock_token')
      ->andReturn([
        'displayName' => 'テスト太郎',
        'avatar' => 'new_avatar_url',
        'description' => '新しい自己紹介文',
        'postsCount' => 150,
      ]);
    $this->app->instance(BlueskyService::class, $blueskyService);

    // ログイン実行
    $response = $this->post('/login', [
      'handle' => 'new-handle.bsky.social',
      'app_password' => 'secret_app_pass',
    ]);

    $response->assertStatus(302);
    $response->assertRedirect('/login/confirm-handle-change');
    $response->assertSessionHas('handle_change_pending');

    // ログイン状態になっていないことをアサート
    $this->assertGuest();
  }

  /**
   * 保留状態が無い場合は確認画面からログイン画面へリダイレクトされること。
   */
  public function test_confirm_handle_change_page_redirects_to_login_without_session(): void
  {
    $response = $this->get('/login/confirm-handle-change');
    $response->assertStatus(302);
    $response->assertRedirect('/login');
  }

  /**
   * 保留状態がある場合は確認画面が正常に表示されること。
   */
  public function test_confirm_handle_change_page_renders_successfully(): void
  {
    $response = $this->withSession([
      'handle_change_pending' => [
        'user_id' => 1,
        'old_handle' => 'old-handle.bsky.social',
        'new_handle' => 'new-handle.bsky.social',
      ]
    ])->get('/login/confirm-handle-change');

    $response->assertStatus(200);
    $response->assertSee('ハンドル変更の確認');
    $response->assertSee('old-handle.bsky.social');
    $response->assertSee('new-handle.bsky.social');
  }

  /**
   * 同意（consent）アクションでハンドル名が更新され、ログインが完了すること。
   */
  public function test_confirm_handle_change_action_consent_succeeds(): void
  {
    $user = User::create(['name' => 'テスト太郎']);
    $bskyAccount = BlueskyAccount::create([
      'id' => \Illuminate\Support\Str::uuid(),
      'user_id' => $user->id,
      'did' => 'did:plc:test777',
      'handle' => 'old-handle.bsky.social',
      'encrypted_app_password' => 'old_secret_app_pass',
    ]);

    $response = $this->withSession([
      'handle_change_pending' => [
        'user_id' => $user->id,
        'old_handle' => 'old-handle.bsky.social',
        'new_handle' => 'new-handle.bsky.social',
        'app_password' => 'new_secret_app_pass',
        'profile' => [
          'displayName' => 'テスト太郎改',
          'avatar' => 'new_avatar_url',
          'description' => '新しい自己紹介文',
          'postsCount' => 200,
        ],
        'is_private' => false,
        'throttle_key' => 'login-attempt:127.0.0.1|new-handle.bsky.social',
      ]
    ])->post('/login/confirm-handle-change', [
      'action' => 'consent'
    ]);

    $response->assertStatus(302);
    $response->assertRedirect('/profile/new-handle.bsky.social');

    // ログイン完了をアサート
    $this->assertAuthenticatedAs($user);

    // アカウント情報が更新されていることを検証
    $bskyAccount->refresh();
    $this->assertEquals('new-handle.bsky.social', $bskyAccount->handle);
    $this->assertEquals('new_secret_app_pass', $bskyAccount->encrypted_app_password);
    $this->assertEquals('new_avatar_url', $bskyAccount->avatar_url);

    // セッションの退避情報が消去されていることを検証
    $this->assertNull(session('handle_change_pending'));
  }

  /**
   * 不同意・退会（delete）アクションでアカウントが物理削除され、ログアウト状態でログイン画面にリダイレクトされること。
   */
  public function test_confirm_handle_change_action_delete_purges_user_data(): void
  {
    $user = User::create(['name' => 'テスト太郎']);
    $bskyAccount = BlueskyAccount::create([
      'id' => \Illuminate\Support\Str::uuid(),
      'user_id' => $user->id,
      'did' => 'did:plc:test777',
      'handle' => 'old-handle.bsky.social',
      'encrypted_app_password' => 'secret_app_pass',
    ]);

    // 紐づくダミー投稿も作成
    $post = Post::create([
      'id' => \Illuminate\Support\Str::uuid(),
      'user_id' => $user->id,
      'uri' => 'at://did:plc:test777/app.bsky.feed.post/12345',
      'cid' => 'cid123',
      'text' => 'ダミー投稿 #TestTag',
      'raw_data' => [],
      'posted_at' => Carbon::now(),
    ]);

    $tag = Tag::create(['name' => 'TestTag']);
    $post->tags()->sync([$tag->id]);

    $response = $this->withSession([
      'handle_change_pending' => [
        'user_id' => $user->id,
        'old_handle' => 'old-handle.bsky.social',
        'new_handle' => 'new-handle.bsky.social',
        'app_password' => 'secret_app_pass',
        'profile' => [],
        'is_private' => false,
        'throttle_key' => 'login-attempt:127.0.0.1|new-handle.bsky.social',
      ]
    ])->post('/login/confirm-handle-change', [
      'action' => 'delete'
    ]);

    $response->assertStatus(302);
    $response->assertRedirect('/login');
    $response->assertSessionHas('success', 'アカウントとすべてのカレンダーデータを完全に削除しました。');

    // ログイン状態ではないことをアサート
    $this->assertGuest();

    // データベースからデータが完全物理削除されていることを検証
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('bluesky_accounts', ['id' => $bskyAccount->id]);
    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    $this->assertDatabaseMissing('post_tags', ['post_id' => $post->id]);

    // セッションの退避情報が消去されていることを検証
    $this->assertNull(session('handle_change_pending'));
  }

  /**
   * キャンセル（cancel）アクションで一時情報が消去され、ログイン画面に戻ること。
   */
  public function test_confirm_handle_change_action_cancel_redirects_to_login(): void
  {
    $user = User::create(['name' => 'テスト太郎']);
    $bskyAccount = BlueskyAccount::create([
      'id' => \Illuminate\Support\Str::uuid(),
      'user_id' => $user->id,
      'did' => 'did:plc:test777',
      'handle' => 'old-handle.bsky.social',
      'encrypted_app_password' => 'secret_app_pass',
    ]);

    $response = $this->withSession([
      'handle_change_pending' => [
        'user_id' => $user->id,
        'old_handle' => 'old-handle.bsky.social',
        'new_handle' => 'new-handle.bsky.social',
        'app_password' => 'secret_app_pass',
        'profile' => [],
        'is_private' => false,
        'throttle_key' => 'login-attempt:127.0.0.1|new-handle.bsky.social',
      ]
    ])->post('/login/confirm-handle-change', [
      'action' => 'cancel'
    ]);

    $response->assertStatus(302);
    $response->assertRedirect('/login');
    $response->assertSessionHas('info', 'ログインをキャンセルしました。');

    // ログイン状態ではないことをアサート
    $this->assertGuest();

    // データベースのハンドル名は変更されていないことを検証
    $bskyAccount->refresh();
    $this->assertEquals('old-handle.bsky.social', $bskyAccount->handle);

    // セッションの退避情報が消去されていることを検証
    $this->assertNull(session('handle_change_pending'));
  }
}
