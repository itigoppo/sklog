<?php

namespace Tests\Feature;

use App\Http\Controllers\DevLoginController;
use App\Models\BlueskyAccount;
use App\Models\Post;
use App\Models\User;
use App\Services\BlueskyService;
use App\Services\FakeBlueskyService;
use App\Support\LocalDev;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalDevTest extends TestCase
{
  use RefreshDatabase;

  private const MY_DID = 'did:plc:test123456';

  private User $user;

  protected function setUp(): void
  {
    parent::setUp();

    $this->user = User::create([
      'name' => 'テストユーザー',
      'email' => 'test@example.com',
      'password' => bcrypt('password'),
    ]);

    BlueskyAccount::create([
      'user_id' => $this->user->id,
      'did' => self::MY_DID,
      'handle' => 'test.bsky.social',
      'encrypted_app_password' => 'secret_app_pass',
      'is_private' => false,
    ]);
  }

  /**
   * 環境をローカルに見せかけ、開発用ログインのルートをテスト内で登録します。
   * （本物のルートは起動時に APP_ENV=local のときだけ登録されるため）
   */
  private function enableLocalDevLogin(): void
  {
    $this->app['env'] = 'local';
    config(['app.dev_login' => true]);

    Route::middleware('web')->group(function () {
      Route::get('/dev/login', [DevLoginController::class, 'index'])->name('dev.login');
      Route::get('/dev/login/{handle}', [DevLoginController::class, 'login'])->name('dev.login.as');
    });
    Route::getRoutes()->refreshNameLookups();
  }

  public function test_local_dev_tools_are_disabled_outside_local_even_if_flags_are_on(): void
  {
    config(['app.dev_login' => true, 'services.bluesky.fake' => true]);

    foreach (['testing', 'production', 'staging'] as $env) {
      $this->app['env'] = $env;
      $this->assertFalse(LocalDev::loginEnabled(), "{$env} で開発用ログインが有効になっている");
      $this->assertFalse(LocalDev::fakeBlueskyEnabled(), "{$env} で偽の Bluesky が有効になっている");
    }

    // 起動時にルートも差し替えも行われていない
    $this->get('/dev/login')->assertStatus(404);
    $this->get('/dev/login/test.bsky.social')->assertStatus(404);
    $this->assertGuest();
    $this->assertNotInstanceOf(FakeBlueskyService::class, app(BlueskyService::class));
  }

  public function test_local_dev_tools_need_their_flags_even_in_local(): void
  {
    $this->app['env'] = 'local';
    config(['app.dev_login' => false, 'services.bluesky.fake' => false]);

    $this->assertFalse(LocalDev::loginEnabled());
    $this->assertFalse(LocalDev::fakeBlueskyEnabled());

    config(['app.dev_login' => true, 'services.bluesky.fake' => true]);

    $this->assertTrue(LocalDev::loginEnabled());
    $this->assertTrue(LocalDev::fakeBlueskyEnabled());
  }

  public function test_dev_login_logs_in_without_bluesky(): void
  {
    $this->enableLocalDevLogin();

    Http::fake();

    $this->get('/dev/login')
      ->assertStatus(200)
      ->assertSee('@test.bsky.social');

    $response = $this->get('/dev/login/test.bsky.social');

    $response->assertRedirect(route('calendar.show', ['handle' => 'test.bsky.social']));
    $this->assertSame($this->user->id, Auth::id());
    Http::assertNothingSent();
  }

  public function test_dev_login_controller_refuses_when_disabled(): void
  {
    $this->enableLocalDevLogin();
    config(['app.dev_login' => false]);

    $this->get('/dev/login')->assertStatus(404);
    $this->get('/dev/login/test.bsky.social')->assertStatus(404);
    $this->assertGuest();
  }

  public function test_fake_bluesky_never_calls_real_api_and_supports_bulk_delete(): void
  {
    Http::fake();
    $this->app->instance(BlueskyService::class, new FakeBlueskyService);

    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://'.self::MY_DID.'/app.bsky.feed.post/mine',
      'cid' => 'cid-mine',
      'text' => '自分の投稿',
      'raw_data' => ['post' => ['uri' => 'at://'.self::MY_DID.'/app.bsky.feed.post/mine']],
      'posted_at' => Carbon::now(),
    ]);
    $repost = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'repost-'.self::MY_DID.'-orig',
      'cid' => 'cid-repost',
      'text' => '他人の投稿',
      'raw_data' => [
        'post' => ['uri' => 'at://did:plc:other/app.bsky.feed.post/orig', 'author' => ['did' => 'did:plc:other']],
        'reason' => ['$type' => 'app.bsky.feed.defs#reasonRepost'],
      ],
      'posted_at' => Carbon::now(),
    ]);

    $fake = app(BlueskyService::class);
    $session = $fake->authenticate('test.bsky.social', 'どんなパスワードでも通る');
    $this->assertSame(self::MY_DID, $session['did']);

    // リポストは、自分のリポストのレコードが viewer.repost に入って返る
    $views = collect($fake->getPosts(['at://did:plc:other/app.bsky.feed.post/orig'], $session['accessJwt'])['posts']);
    $this->assertStringStartsWith('at://'.self::MY_DID.'/app.bsky.feed.repost/', $views->first()['viewer']['repost']);

    // 削除モードの「Blueskyからも削除」が最後まで通る
    $this->actingAs($this->user)
      ->delete('/profile/test.bsky.social/manage', [
        'post_ids' => [$post->id, $repost->id],
        'also_bluesky' => '1',
      ])
      ->assertSessionHas('success');

    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    $this->assertDatabaseMissing('posts', ['id' => $repost->id]);
    Http::assertNothingSent();
  }

  public function test_fake_bluesky_keeps_saved_profile_on_login(): void
  {
    Http::fake();
    $this->app->instance(BlueskyService::class, new FakeBlueskyService);

    BlueskyAccount::where('did', self::MY_DID)->update([
      'avatar_url' => 'https://example.com/avatar.png',
      'description' => '保存済みの自己紹介',
    ]);

    // 通常のログイン画面からも、アプリパスワードを問わずログインできる
    $this->post('/login', ['handle' => 'test.bsky.social', 'app_password' => 'anything'])
      ->assertRedirect(route('calendar.show', ['handle' => 'test.bsky.social']));

    $this->assertSame($this->user->id, Auth::id());
    $this->assertDatabaseHas('bluesky_accounts', [
      'did' => self::MY_DID,
      'avatar_url' => 'https://example.com/avatar.png',
      'description' => '保存済みの自己紹介',
    ]);
    Http::assertNothingSent();
  }
}
