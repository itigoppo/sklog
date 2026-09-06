<?php

namespace Tests\Feature;

use App\Models\BlueskyAccount;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\BlueskyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

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
      'id' => Str::uuid(),
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
   * ログイン画面で古い（変更前の）ハンドル名を入力してログインしようとした際、
   * APIが認証エラーを返しても、裏で最新のハンドルを特定して再認証に成功すれば、
   * 自動的にハンドル変更確認画面へ遷移すること。
   */
  public function test_login_with_old_handle_redirects_to_confirm_handle_change_on_successful_fallback_auth(): void
  {
    $user = User::create(['name' => 'テスト太郎']);
    $bskyAccount = BlueskyAccount::create([
      'id' => Str::uuid(),
      'user_id' => $user->id,
      'did' => 'did:plc:test777',
      'handle' => 'old-handle.bsky.social', // 登録されている古いハンドル名
      'encrypted_app_password' => 'secret_app_pass',
    ]);

    $blueskyService = Mockery::mock(BlueskyService::class);

    // 1. 最初は古いハンドル名での認証要求で例外を発生させる
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with('old-handle.bsky.social', 'secret_app_pass')
      ->andThrow(new \Exception('Invalid identifier or password'));

    // 2. DIDからパブリックAPIで最新のプロフィールを取得する
    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->with('did:plc:test777')
      ->andReturn([
        'did' => 'did:plc:test777',
        'handle' => 'new-handle.bsky.social', // 新しいハンドル
      ]);

    // 3. 新しいハンドル名で再認証を試み、成功させる
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with('new-handle.bsky.social', 'secret_app_pass')
      ->andReturn([
        'did' => 'did:plc:test777',
        'handle' => 'new-handle.bsky.social',
        'accessJwt' => 'mock_token',
      ]);

    // 4. 新ハンドルでのログイン成功後のプロフィール情報取得
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

    // 古いハンドル名でログインを実行
    $response = $this->post('/login', [
      'handle' => 'old-handle.bsky.social',
      'app_password' => 'secret_app_pass',
    ]);

    $response->assertStatus(302);
    $response->assertRedirect('/login/confirm-handle-change');
    $response->assertSessionHas('handle_change_pending');

    // セッションの保留データに新旧ハンドル名が正しく設定されていること
    $this->assertEquals('new-handle.bsky.social', session('handle_change_pending.new_handle'));
    $this->assertEquals('old-handle.bsky.social', session('handle_change_pending.old_handle'));

    // ログイン状態にはなっていないこと
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
      ],
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
      'id' => Str::uuid(),
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
      ],
    ])->post('/login/confirm-handle-change', [
      'action' => 'consent',
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
      'id' => Str::uuid(),
      'user_id' => $user->id,
      'did' => 'did:plc:test777',
      'handle' => 'old-handle.bsky.social',
      'encrypted_app_password' => 'secret_app_pass',
    ]);

    // 紐づくダミー投稿も作成
    $post = Post::create([
      'id' => Str::uuid(),
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
      ],
    ])->post('/login/confirm-handle-change', [
      'action' => 'delete',
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
      'id' => Str::uuid(),
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
      ],
    ])->post('/login/confirm-handle-change', [
      'action' => 'cancel',
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
