<?php

namespace Tests\Feature;

use App\Models\BlueskyAccount;
use App\Models\InviteCode;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\BlueskyService;
use App\Support\AccountSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class MultiAccountLoginTest extends TestCase
{
  use RefreshDatabase;

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  /**
   * テスト用に User と BlueskyAccount の組を作成します。
   */
  private function createAccount(string $suffix): User
  {
    $user = User::create(['name' => "ユーザー{$suffix}"]);
    BlueskyAccount::create([
      'id' => Str::uuid(),
      'user_id' => $user->id,
      'did' => "did:plc:{$suffix}",
      'handle' => "{$suffix}.bsky.social",
      'encrypted_app_password' => 'secret_app_pass',
    ]);

    return $user->refresh();
  }

  /**
   * Bluesky 認証が成功するモックを差し込みます。
   */
  private function mockBlueskySuccess(string $suffix): void
  {
    $blueskyService = Mockery::mock(BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with("{$suffix}.bsky.social", 'secret_app_pass')
      ->andReturn([
        'did' => "did:plc:{$suffix}",
        'handle' => "{$suffix}.bsky.social",
        'accessJwt' => 'mock_token',
      ]);
    $blueskyService->shouldReceive('getProfile')
      ->andReturn([
        'displayName' => "ユーザー{$suffix}",
        'postsCount' => 10,
      ]);
    $this->app->instance(BlueskyService::class, $blueskyService);
  }

  /**
   * レスポンスに remember me の recaller cookie が積まれているかを返します。
   */
  private function hasRecallerCookie($response): bool
  {
    foreach ($response->headers->getCookies() as $cookie) {
      if (str_starts_with($cookie->getName(), 'remember_web_')) {
        return $cookie->getValue() !== '';
      }
    }

    return false;
  }

  /**
   * 「ログイン状態を保持する」を付けたログインは、remember me を有効にすること。
   */
  public function test_login_with_remember_keeps_login_beyond_the_session(): void
  {
    $user = $this->createAccount('alpha');
    $this->mockBlueskySuccess('alpha');

    $response = $this->post('/login', [
      'handle' => 'alpha.bsky.social',
      'app_password' => 'secret_app_pass',
      'remember' => '1',
    ]);

    $this->assertAuthenticatedAs($user);
    $this->assertTrue($this->hasRecallerCookie($response), 'recaller cookie が発行されていない');
    $this->assertTrue(session(AccountSession::REMEMBER_KEY));
    $this->assertNotEmpty($user->refresh()->remember_token);
  }

  /**
   * 「ログイン状態を保持する」を外したログインは、セッション限りにすること。
   */
  public function test_login_without_remember_stays_session_only(): void
  {
    $user = $this->createAccount('alpha');
    $this->mockBlueskySuccess('alpha');

    $response = $this->post('/login', [
      'handle' => 'alpha.bsky.social',
      'app_password' => 'secret_app_pass',
      'remember' => '0',
    ]);

    $this->assertAuthenticatedAs($user);
    $this->assertFalse($this->hasRecallerCookie($response), 'remember me を外したのに recaller cookie が発行された');
    $this->assertFalse(session(AccountSession::REMEMBER_KEY));
  }

  /**
   * アカウントの切り替えは、そのセッションでの remember me の選択を踏襲すること。
   */
  public function test_switching_account_follows_the_session_remember_choice(): void
  {
    $userA = $this->createAccount('alpha');
    $userB = $this->createAccount('bravo');

    $response = $this->actingAs($userB)
      ->withSession([
        AccountSession::SESSION_KEY => [$userA->id, $userB->id],
        AccountSession::REMEMBER_KEY => false,
      ])
      ->post('/accounts/switch', ['user_id' => $userA->id]);

    $this->assertAuthenticatedAs($userA);
    $this->assertFalse($this->hasRecallerCookie($response), '保持しない選択なのに切り替えで recaller cookie が発行された');

    $response = $this->actingAs($userB)
      ->withSession([
        AccountSession::SESSION_KEY => [$userA->id, $userB->id],
        AccountSession::REMEMBER_KEY => true,
      ])
      ->post('/accounts/switch', ['user_id' => $userA->id]);

    $this->assertAuthenticatedAs($userA);
    $this->assertTrue($this->hasRecallerCookie($response), '保持する選択なのに切り替えで recaller cookie が発行されない');
  }

  /**
   * ログイン中に別アカウントでログインすると、既存のログインを保ったまま追加され、
   * アクティブなアカウントが新しい方に切り替わること。
   */
  public function test_login_while_authenticated_adds_account_to_session(): void
  {
    $userA = $this->createAccount('alpha');
    $userB = $this->createAccount('bravo');

    $this->mockBlueskySuccess('bravo');

    $response = $this->actingAs($userA)->post('/login', [
      'handle' => 'bravo.bsky.social',
      'app_password' => 'secret_app_pass',
    ]);

    $response->assertRedirect(route('calendar.show', ['handle' => 'bravo.bsky.social']));
    $this->assertAuthenticatedAs($userB);
    $this->assertSame([$userA->id, $userB->id], session(AccountSession::SESSION_KEY));
  }

  /**
   * セッションに積まれたアカウントへ切り替えられること。
   */
  public function test_user_can_switch_to_another_logged_in_account(): void
  {
    $userA = $this->createAccount('alpha');
    $userB = $this->createAccount('bravo');

    $response = $this->actingAs($userB)
      ->withSession([AccountSession::SESSION_KEY => [$userA->id, $userB->id]])
      ->post('/accounts/switch', ['user_id' => $userA->id]);

    $response->assertRedirect(route('calendar.show', ['handle' => 'alpha.bsky.social']));
    $this->assertAuthenticatedAs($userA);
    $this->assertSame([$userA->id, $userB->id], session(AccountSession::SESSION_KEY));
  }

  /**
   * このセッションでログインしていないアカウントへは切り替えられないこと。
   */
  public function test_user_cannot_switch_to_account_not_logged_in_this_session(): void
  {
    $userA = $this->createAccount('alpha');
    $stranger = $this->createAccount('stranger');

    $response = $this->actingAs($userA)
      ->from(route('calendar.show', ['handle' => 'alpha.bsky.social']))
      ->post('/accounts/switch', ['user_id' => $stranger->id]);

    $response->assertRedirect(route('calendar.show', ['handle' => 'alpha.bsky.social']));
    $response->assertSessionHas('error');
    $this->assertAuthenticatedAs($userA);
  }

  /**
   * 複数ログイン中のログアウトは、アクティブなアカウントだけを外して次のアカウントへ切り替えること。
   */
  public function test_logout_switches_to_remaining_account(): void
  {
    $userA = $this->createAccount('alpha');
    $userB = $this->createAccount('bravo');

    $response = $this->actingAs($userB)
      ->withSession([AccountSession::SESSION_KEY => [$userA->id, $userB->id]])
      ->post('/logout');

    $response->assertRedirect(route('calendar.show', ['handle' => 'alpha.bsky.social']));
    $this->assertAuthenticatedAs($userA);
    $this->assertSame([$userA->id], session(AccountSession::SESSION_KEY));
  }

  /**
   * all 付きのログアウトは、セッション内のすべてのアカウントをログアウトすること。
   */
  public function test_logout_all_clears_every_account(): void
  {
    $userA = $this->createAccount('alpha');
    $userB = $this->createAccount('bravo');

    $response = $this->actingAs($userB)
      ->withSession([AccountSession::SESSION_KEY => [$userA->id, $userB->id]])
      ->post('/logout', ['all' => '1']);

    $response->assertRedirect('/login');
    $this->assertGuest();
    $this->assertNull(session(AccountSession::SESSION_KEY));
  }

  /**
   * 単一アカウントのログアウトは、これまで通りログイン画面へ戻ること。
   */
  public function test_logout_with_single_account_returns_to_login(): void
  {
    $userA = $this->createAccount('alpha');

    $response = $this->actingAs($userA)
      ->withSession([AccountSession::SESSION_KEY => [$userA->id]])
      ->post('/logout');

    $response->assertRedirect('/login');
    $this->assertGuest();
  }

  /**
   * 上限に達している場合は、アカウントを追加できないこと。
   */
  public function test_cannot_add_account_beyond_limit(): void
  {
    $users = collect(range(1, AccountSession::MAX_ACCOUNTS))
      ->map(fn ($i) => $this->createAccount("user{$i}"));
    $extra = $this->createAccount('extra');

    $session = [AccountSession::SESSION_KEY => $users->pluck('id')->all()];

    $this->actingAs($users->first())
      ->withSession($session)
      ->get('/accounts/add')
      ->assertRedirect(route('calendar.show', ['handle' => 'user1.bsky.social']));

    $response = $this->actingAs($users->first())
      ->withSession($session)
      ->from('/accounts/add')
      ->post('/login', [
        'handle' => 'extra.bsky.social',
        'app_password' => 'secret_app_pass',
      ]);

    $response->assertSessionHasErrors('handle');
    $this->assertAuthenticatedAs($users->first());
    $this->assertFalse(AccountSession::has($extra->id));
  }

  /**
   * 退会すると、そのアカウントだけがセッションから外れ、残りのアカウントへ切り替わること。
   */
  public function test_delete_account_switches_to_remaining_account(): void
  {
    $userA = $this->createAccount('alpha');
    $userB = $this->createAccount('bravo');

    $response = $this->actingAs($userB)
      ->withSession([AccountSession::SESSION_KEY => [$userA->id, $userB->id]])
      ->post(route('calendar.settings.delete_account', ['handle' => 'bravo.bsky.social']));

    $response->assertRedirect(route('calendar.show', ['handle' => 'alpha.bsky.social']));
    $this->assertAuthenticatedAs($userA);
    $this->assertSame([$userA->id], session(AccountSession::SESSION_KEY));
    $this->assertDatabaseMissing('users', ['id' => $userB->id]);
  }

  /**
   * 未登録のBlueskyアカウントを追加しようとした場合、登録が必要である旨が明示されること。
   */
  public function test_adding_unregistered_account_explains_registration_is_required(): void
  {
    $userA = $this->createAccount('alpha');

    $this->mockBlueskySuccess('newbie');

    $response = $this->actingAs($userA)
      ->withSession([AccountSession::SESSION_KEY => [$userA->id]])
      ->from(route('accounts.add'))
      ->post('/login', [
        'handle' => 'newbie.bsky.social',
        'app_password' => 'secret_app_pass',
      ]);

    $response->assertRedirect(route('accounts.add'));
    $response->assertSessionHasErrors(['handle' => '@newbie.bsky.social はまだ'.config('app.name', 'SkLoG').'に登録されていません。追加できるのは、参加申請 → 招待コードの受け取り → 登録 まで済んだアカウントだけです。']);
    $this->assertAuthenticatedAs($userA);
    $this->assertSame([$userA->id], session(AccountSession::SESSION_KEY));
  }

  /**
   * 招待済みのアカウントを追加しようとした場合、追加登録画面へ案内されること。
   */
  public function test_adding_invited_account_is_guided_to_register(): void
  {
    $userA = $this->createAccount('alpha');

    WaitlistEntry::create([
      'email' => 'invited@example.com',
      'handle' => 'invited.bsky.social',
      'status' => 'invited',
    ]);

    $this->mockBlueskySuccess('invited');

    $response = $this->actingAs($userA)
      ->withSession([AccountSession::SESSION_KEY => [$userA->id]])
      ->post('/login', [
        'handle' => 'invited.bsky.social',
        'app_password' => 'secret_app_pass',
      ]);

    $response->assertRedirect('/register');
    $response->assertSessionHas('info', 'このアカウントには招待コードが発行されています。コードを入力して追加登録してください。');
    $this->assertAuthenticatedAs($userA);
  }

  /**
   * ログイン中でも登録画面が「アカウントの追加登録」として表示されること（従来は無言で弾かれていた）。
   */
  public function test_register_form_is_shown_as_add_mode_while_authenticated(): void
  {
    $userA = $this->createAccount('alpha');

    $response = $this->actingAs($userA)
      ->withSession([AccountSession::SESSION_KEY => [$userA->id]])
      ->get('/register');

    $response->assertOk();
    $response->assertSee('アカウントを追加登録');
    $response->assertSee('@alpha.bsky.social');
  }

  /**
   * ログイン中に招待コードで登録すると、既存のログインを保ったままアカウントが追加されること。
   */
  public function test_register_while_authenticated_adds_account(): void
  {
    $userA = $this->createAccount('alpha');

    $entry = WaitlistEntry::create([
      'email' => 'second@example.com',
      'handle' => 'second.bsky.social',
      'did' => 'did:plc:second',
      'status' => 'invited',
    ]);
    InviteCode::create([
      'code' => 'ADD-CODE-001',
      'handle' => 'second.bsky.social',
      'did' => 'did:plc:second',
      'waitlist_entry_id' => $entry->id,
    ]);

    $this->mockBlueskySuccess('second');

    $response = $this->actingAs($userA)
      ->withSession([AccountSession::SESSION_KEY => [$userA->id]])
      ->post('/register', [
        'invite_code' => 'ADD-CODE-001',
        'handle' => 'second.bsky.social',
        'app_password' => 'secret_app_pass',
        'terms' => '1',
      ]);

    $response->assertRedirect(route('calendar.show', ['handle' => 'second.bsky.social']));

    $added = BlueskyAccount::where('did', 'did:plc:second')->firstOrFail();
    $this->assertAuthenticatedAs(User::find($added->user_id));
    $this->assertSame([$userA->id, $added->user_id], session(AccountSession::SESSION_KEY));
  }

  /**
   * アカウント追加画面に、登録済みアカウントだけが追加できる旨の案内が出ること。
   */
  public function test_add_account_page_explains_registration_requirement(): void
  {
    $userA = $this->createAccount('alpha');

    $response = $this->actingAs($userA)
      ->withSession([AccountSession::SESSION_KEY => [$userA->id]])
      ->get(route('accounts.add'));

    $response->assertOk();
    $response->assertSee('追加できるのは「登録済み」のアカウントだけです');
    $response->assertSee(route('waitlist.show'));
    $response->assertSee(route('register'));
  }

  /**
   * カレンダー画面に、切り替え先アカウントとアカウント追加の導線が表示されること。
   */
  public function test_calendar_page_shows_account_switcher(): void
  {
    $userA = $this->createAccount('alpha');
    $userB = $this->createAccount('bravo');

    $response = $this->actingAs($userA)
      ->withSession([AccountSession::SESSION_KEY => [$userA->id, $userB->id]])
      ->get(route('calendar.show', ['handle' => 'alpha.bsky.social']));

    $response->assertOk();
    // 折りたたまれた状態では、現在のアカウントの1行だけが常時表示される
    $response->assertSee('js-account-switcher-toggle', false);
    $response->assertSee('@alpha.bsky.social');
    $response->assertSee('他1件');
    // 展開時の中身（切り替え先・追加・一括ログアウト）もDOM上には存在する
    $response->assertSee('js-account-switcher-panel hidden', false);
    $response->assertSee('@bravo.bsky.social に切り替える');
    $response->assertSee(route('accounts.add'));
    $response->assertSee('すべてのアカウントからログアウト');
  }

  /**
   * ログイン中のアカウントが1件だけでも、そのアカウントが一覧に表示されること。
   */
  public function test_switcher_lists_the_only_logged_in_account(): void
  {
    $userA = $this->createAccount('alpha');

    $response = $this->actingAs($userA)
      ->withSession([AccountSession::SESSION_KEY => [$userA->id]])
      ->get(route('calendar.show', ['handle' => 'alpha.bsky.social']));

    $response->assertOk();
    $response->assertSee('js-account-switcher-toggle', false);
    $response->assertSee('@alpha.bsky.social');
    // 切り替え先が無いので、件数バッジも切り替えボタンも一括ログアウトも出さない
    $response->assertDontSee('他1件');
    $response->assertDontSee('に切り替える');
    $response->assertDontSee('すべてのアカウントからログアウト');
    // アカウントの追加導線は残る
    $response->assertSee(route('accounts.add'));
  }

  /**
   * remember me などでスタックが無い状態でも、ログイン中ユーザーが補完されること。
   */
  public function test_session_stack_is_seeded_from_current_user(): void
  {
    $userA = $this->createAccount('alpha');

    $this->actingAs($userA)->get(route('calendar.show', ['handle' => 'alpha.bsky.social']))
      ->assertOk();

    $this->assertSame([$userA->id], session(AccountSession::SESSION_KEY));
  }
}
