<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\InviteCode;
use App\Models\WaitlistEntry;
use App\Services\BlueskyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Mockery;

class WaitlistAndRegistrationTest extends TestCase
{
  use RefreshDatabase;

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  /**
   * 参加申請画面が正常に表示されること。
   */
  public function test_waitlist_apply_page_renders_successfully(): void
  {
    $response = $this->get('/join');
    $response->assertStatus(200);
    $response->assertSee('参加申請');
  }

  /**
   * セッションが空の状態で申請完了画面が正常に表示されること。
   */
  public function test_waitlist_completed_page_renders_successfully_without_session(): void
  {
    $response = $this->get('/join/completed');
    $response->assertStatus(200);
    $response->assertSee('申請を受け付けました');
    $response->assertSee('自動返信メールは送信しておりません');
  }

  /**
   * 実在するハンドルでの申請が成功すること。
   */
  public function test_waitlist_store_submits_successfully_when_handle_exists(): void
  {
    $blueskyService = Mockery::mock(BlueskyService::class);
    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->with('test.bsky.social')
      ->andReturn([
        'did' => 'did:plc:test123456',
        'handle' => 'test.bsky.social',
        'displayName' => '申請テスト太郎',
        'avatar' => 'avatar_url',
        'description' => '自己紹介文です',
      ]);
    $this->app->instance(BlueskyService::class, $blueskyService);

    $response = $this->followingRedirects()->post('/join', [
      'email' => 'user@example.com',
      'handle' => '@test.bsky.social',
    ]);

    $response->assertStatus(200);
    $response->assertSee('申請を受け付けました');
    $response->assertSee('test.bsky.social');
    $response->assertSee('自動返信メールは送信しておりません');

    $this->assertDatabaseHas('waitlist_entries', [
      'email' => 'user@example.com',
      'handle' => 'test.bsky.social',
      'display_name' => '申請テスト太郎',
      'avatar_url' => 'avatar_url',
      'description' => '自己紹介文です',
      'status' => 'pending',
    ]);
  }

  /**
   * 実在しないハンドルでの申請がエラーになること。
   */
  public function test_waitlist_store_fails_when_handle_does_not_exist(): void
  {
    $blueskyService = Mockery::mock(BlueskyService::class);
    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->with('fake.bsky.social')
      ->andThrow(new \Exception('プロフィールの取得に失敗しました。'));
    $this->app->instance(BlueskyService::class, $blueskyService);

    $response = $this->post('/join', [
      'email' => 'user@example.com',
      'handle' => 'fake.bsky.social',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['handle']);

    $this->assertDatabaseMissing('waitlist_entries', [
      'email' => 'user@example.com',
      'handle' => 'fake.bsky.social',
    ]);
  }

  /**
   * 重複申請の際にエラーにならずサイレント成功すること。
   */
  public function test_waitlist_store_handles_duplicate_silently(): void
  {
    // すでにDBに申請を登録しておく
    WaitlistEntry::create([
      'email' => 'existing@example.com',
      'handle' => 'existing.bsky.social',
      'status' => 'pending',
    ]);

    // APIモックは呼ばれないはず（重複時点で即時返却されるため）
    $blueskyService = Mockery::mock(BlueskyService::class);
    $blueskyService->shouldNotReceive('getProfile');
    $this->app->instance(BlueskyService::class, $blueskyService);

    // 同じメールアドレスで再申請
    $response1 = $this->post('/join', [
      'email' => 'existing@example.com',
      'handle' => 'other.bsky.social',
    ]);

    $response1->assertStatus(302);
    $response1->assertRedirect('/join/completed');
    $response1->assertSessionHas('handle', 'other.bsky.social');

    // 同じハンドル名で再申請
    $response2 = $this->post('/join', [
      'email' => 'new-email@example.com',
      'handle' => 'existing.bsky.social',
    ]);

    $response2->assertStatus(302);
    $response2->assertRedirect('/join/completed');
    $response2->assertSessionHas('handle', 'existing.bsky.social');

    // DBに余計な二重データが登録されていないことをアサート
    $this->assertEquals(1, WaitlistEntry::count());
  }

  /**
   * 無効な招待コードでの登録が拒否されること。
   */
  public function test_register_fails_with_invalid_invite_code(): void
  {
    $response = $this->post('/register', [
      'invite_code' => 'INVALID-CODE-999',
      'handle' => 'test.bsky.social',
      'app_password' => 'secret_app_pass',
      'terms' => '1',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['invite_code']);
  }

  /**
   * 使用済みの招待コードでの登録が拒否されること。
   */
  public function test_register_fails_with_used_invite_code(): void
  {
    $user = User::create(['name' => '既存ユーザー']);
    $inviteCode = InviteCode::create([
      'code' => 'USED-CODE-111',
      'waitlist_entry_id' => null,
      'used_by_user_id' => $user->id,
      'used_at' => Carbon::now(),
    ]);

    $response = $this->post('/register', [
      'invite_code' => 'USED-CODE-111',
      'handle' => 'test.bsky.social',
      'app_password' => 'secret_app_pass',
      'terms' => '1',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['invite_code']);
  }

  /**
   * 招待コードに紐付けられた did と、登録しようとしているBlueskyの did が不一致な場合に登録拒否されること。
   */
  public function test_register_fails_when_did_mismatch_with_invite_code(): void
  {
    // 'did:plc:assigned123' 専用の招待コードを作成
    $inviteCode = InviteCode::create([
      'code' => 'VALID-CODE-222',
      'handle' => 'assigned.bsky.social',
      'did' => 'did:plc:assigned123',
      'waitlist_entry_id' => null,
    ]);

    $blueskyService = Mockery::mock(BlueskyService::class);
    // 認証は成功するが 'did:plc:mismatch123' が返ってくるモック
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with('mismatch.bsky.social', 'secret_app_pass')
      ->andReturn([
        'did' => 'did:plc:mismatch123',
        'handle' => 'mismatch.bsky.social',
        'accessJwt' => 'mock_token',
      ]);
    $this->app->instance(BlueskyService::class, $blueskyService);

    $response = $this->post('/register', [
      'invite_code' => 'VALID-CODE-222',
      'handle' => 'mismatch.bsky.social',
      'app_password' => 'secret_app_pass',
      'terms' => '1',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['handle']);
  }

  /**
   * 正しい招待コードおよびBluesky認証で正常に新規登録でき、ログインされること。
   */
  public function test_register_succeeds_with_valid_invite_code_and_auth(): void
  {
    $entry = WaitlistEntry::create([
      'email' => 'applicant@example.com',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant777',
      'status' => 'invited',
    ]);

    $inviteCode = InviteCode::create([
      'code' => 'VALID-CODE-777',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant777',
      'waitlist_entry_id' => $entry->id,
    ]);

    $blueskyService = Mockery::mock(BlueskyService::class);
    // 認証モック
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with('registrant.bsky.social', 'secret_app_pass')
      ->andReturn([
        'did' => 'did:plc:registrant777',
        'handle' => 'registrant.bsky.social',
        'accessJwt' => 'mock_token',
      ]);

    // getProfileモック
    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->with('did:plc:registrant777', 'mock_token')
      ->andReturn([
        'displayName' => '登録テスト太郎',
        'avatar' => 'avatar_url',
        'description' => '自己紹介です',
        'postsCount' => 120,
      ]);
    $this->app->instance(BlueskyService::class, $blueskyService);

    $response = $this->post('/register', [
      'invite_code' => 'VALID-CODE-777',
      'handle' => 'registrant.bsky.social',
      'app_password' => 'secret_app_pass',
      'terms' => '1',
    ]);

    $response->assertStatus(302);
    $response->assertRedirect('/profile/registrant.bsky.social');

    // DBの各モデルが正常に生成・更新されたか検証
    $this->assertDatabaseHas('users', [
      'name' => '登録テスト太郎',
    ]);

    $this->assertDatabaseHas('bluesky_accounts', [
      'did' => 'did:plc:registrant777',
      'handle' => 'registrant.bsky.social',
      'avatar_url' => 'avatar_url',
    ]);

    // 招待コードが使用済みになっているかアサート
    $inviteCode->refresh();
    $this->assertNotNull($inviteCode->used_by_user_id);
    $this->assertNotNull($inviteCode->used_at);

    // ウェイトリストのステータスが registered に更新されているかアサート
    $entry->refresh();
    $this->assertEquals('registered', $entry->status);

    // ログイン状態になっているかアサート
    $user = User::where('name', '登録テスト太郎')->firstOrFail();
    $this->assertAuthenticatedAs($user);
  }

  /**
   * 申請済みかつ招待済み(invited)のユーザーがログインを試みた場合、新規登録画面へリダイレクトされること。
   */
  public function test_login_redirects_to_register_when_waitlist_invited(): void
  {
    WaitlistEntry::create([
      'email' => 'invited@example.com',
      'handle' => 'invited.bsky.social',
      'status' => 'invited',
    ]);

    $blueskyService = Mockery::mock(BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with('invited.bsky.social', 'secret_app_pass')
      ->andReturn([
        'did' => 'did:plc:invited123',
        'handle' => 'invited.bsky.social',
        'accessJwt' => 'mock_token',
      ]);
    $this->app->instance(BlueskyService::class, $blueskyService);

    $response = $this->post('/login', [
      'handle' => 'invited.bsky.social',
      'app_password' => 'secret_app_pass',
    ]);

    $response->assertStatus(302);
    $response->assertRedirect('/register');
    $response->assertSessionHas('info', '招待コードで新規登録してください。');
  }

  /**
   * 申請済みだが未招待(pending)のユーザーがログインを試みた場合、新規登録画面へ行かずエラーになること。
   */
  public function test_login_fails_when_waitlist_pending(): void
  {
    WaitlistEntry::create([
      'email' => 'pending@example.com',
      'handle' => 'pending.bsky.social',
      'status' => 'pending',
    ]);

    $blueskyService = Mockery::mock(BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with('pending.bsky.social', 'secret_app_pass')
      ->andReturn([
        'did' => 'did:plc:pending123',
        'handle' => 'pending.bsky.social',
        'accessJwt' => 'mock_token',
      ]);
    $this->app->instance(BlueskyService::class, $blueskyService);

    $response = $this->post('/login', [
      'handle' => 'pending.bsky.social',
      'app_password' => 'secret_app_pass',
    ]);

    $response->assertStatus(302);
    // 新規登録画面へのリダイレクトではなく、ログイン元画面(back)に戻っていることを確認
    $response->assertSessionHasErrors(['handle' => 'ハンドル名またはアプリパスワードが正しくありません。']);
  }

  /**
   * そもそも未申請のユーザーがログインを試みた場合、新規登録画面へ行かずエラーになること。
   */
  public function test_login_fails_when_not_waitlisted(): void
  {
    $blueskyService = Mockery::mock(BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with('unapplied.bsky.social', 'secret_app_pass')
      ->andReturn([
        'did' => 'did:plc:unapplied123',
        'handle' => 'unapplied.bsky.social',
        'accessJwt' => 'mock_token',
      ]);
    $this->app->instance(BlueskyService::class, $blueskyService);

    $response = $this->post('/login', [
      'handle' => 'unapplied.bsky.social',
      'app_password' => 'secret_app_pass',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['handle' => 'ハンドル名またはアプリパスワードが正しくありません。']);
  }

  /**
   * 管理画面：デフォルトで全申請者が表示されること。
   */
  public function test_admin_waitlist_index_displays_all_by_default(): void
  {
    config(['services.admin.2fa_enabled' => true]);

    WaitlistEntry::create([
      'email' => 'user1@example.com',
      'handle' => 'alpha.bsky.social',
      'status' => 'pending',
    ]);
    WaitlistEntry::create([
      'email' => 'user2@example.com',
      'handle' => 'beta.bsky.social',
      'status' => 'invited',
    ]);

    $response = $this->withSession([
      'admin_authenticated' => true,
      'admin_2fa_verified' => true,
    ])
      ->get('/admin/waitlist');

    $response->assertStatus(200);
    $response->assertSee('alpha.bsky.social');
    $response->assertSee('beta.bsky.social');
    $response->assertSee('申請合計: 2 件');
  }

  /**
   * 管理画面：ハンドル名での絞り込みが正常に機能すること。
   */
  public function test_admin_waitlist_index_filters_by_handle(): void
  {
    config(['services.admin.2fa_enabled' => true]);

    WaitlistEntry::create([
      'email' => 'user1@example.com',
      'handle' => 'alpha.bsky.social',
      'status' => 'pending',
    ]);
    WaitlistEntry::create([
      'email' => 'user2@example.com',
      'handle' => 'beta.bsky.social',
      'status' => 'invited',
    ]);

    // 'alpha' で部分一致検索
    $response = $this->withSession([
      'admin_authenticated' => true,
      'admin_2fa_verified' => true,
    ])
      ->get('/admin/waitlist?handle=alpha');

    $response->assertStatus(200);
    $response->assertSee('alpha.bsky.social');
    $response->assertDontSee('beta.bsky.social');
    $response->assertSee('検索結果:');
    $response->assertSee('1');
    $response->assertSee('件');
    $response->assertSee('申請合計: 2 件');
  }

  /**
   * 管理画面：ステータスでの絞り込みが正常に機能すること。
   */
  public function test_admin_waitlist_index_filters_by_status(): void
  {
    config(['services.admin.2fa_enabled' => true]);

    WaitlistEntry::create([
      'email' => 'user1@example.com',
      'handle' => 'alpha.bsky.social',
      'status' => 'pending',
    ]);
    WaitlistEntry::create([
      'email' => 'user2@example.com',
      'handle' => 'beta.bsky.social',
      'status' => 'invited',
    ]);

    // ステータス 'invited' で絞り込み
    $response = $this->withSession([
      'admin_authenticated' => true,
      'admin_2fa_verified' => true,
    ])
      ->get('/admin/waitlist?status=invited');

    $response->assertStatus(200);
    $response->assertSee('beta.bsky.social');
    $response->assertDontSee('alpha.bsky.social');
    $response->assertSee('検索結果:');
    $response->assertSee('1');
    $response->assertSee('件');
    $response->assertSee('申請合計: 2 件');
  }

  /**
   * ハンドル名が変わっていても、didが一致していれば登録が正常に成功すること。
   */
  public function test_register_succeeds_when_handle_changed_but_did_matches(): void
  {
    $entry = WaitlistEntry::create([
      'email' => 'applicant@example.com',
      'handle' => 'old-handle.bsky.social',
      'did' => 'did:plc:registrant777',
      'status' => 'invited',
    ]);

    $inviteCode = InviteCode::create([
      'code' => 'VALID-CODE-777',
      'handle' => 'old-handle.bsky.social',
      'did' => 'did:plc:registrant777',
      'waitlist_entry_id' => $entry->id,
    ]);

    $blueskyService = Mockery::mock(BlueskyService::class);
    // 認証時に新ハンドルが返ってくるが did は一致するモック
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with('new-handle.bsky.social', 'secret_app_pass')
      ->andReturn([
        'did' => 'did:plc:registrant777',
        'handle' => 'new-handle.bsky.social',
        'accessJwt' => 'mock_token',
      ]);

    $blueskyService->shouldReceive('getProfile')
      ->once()
      ->with('did:plc:registrant777', 'mock_token')
      ->andReturn([
        'displayName' => '登録テスト太郎',
        'avatar' => 'avatar_url',
        'description' => '自己紹介です',
        'postsCount' => 120,
      ]);
    $this->app->instance(BlueskyService::class, $blueskyService);

    // 新しいハンドル名で登録を試みる
    $response = $this->post('/register', [
      'invite_code' => 'VALID-CODE-777',
      'handle' => 'new-handle.bsky.social',
      'app_password' => 'secret_app_pass',
      'terms' => '1',
    ]);

    $response->assertStatus(302);
    $response->assertRedirect('/profile/new-handle.bsky.social');

    // DB上でも新しいハンドル名でBlueskyAccountが生成されていることをアサート
    $this->assertDatabaseHas('bluesky_accounts', [
      'did' => 'did:plc:registrant777',
      'handle' => 'new-handle.bsky.social',
    ]);
  }

  /**
   * 有効期限切れの招待コードでの登録が拒否されること。
   */
  public function test_register_fails_when_invite_code_is_expired(): void
  {
    $entry = WaitlistEntry::create([
      'email' => 'applicant@example.com',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant777',
      'status' => 'invited',
    ]);

    // 期限切れの招待コードを作成（1秒前に期限切れ）
    $inviteCode = InviteCode::create([
      'code' => 'EXPIRED-CODE-999',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant777',
      'waitlist_entry_id' => $entry->id,
      'expires_at' => Carbon::now()->subSecond(),
    ]);

    $response = $this->post('/register', [
      'invite_code' => 'EXPIRED-CODE-999',
      'handle' => 'registrant.bsky.social',
      'app_password' => 'secret_app_pass',
      'terms' => '1',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['invite_code']);
  }

  /**
   * 期限切れ時に、ユーザーが再送リクエストを送信し、ステータスが expired_pending になること。
   */
  public function test_user_can_request_resend_when_invite_code_is_expired(): void
  {
    $entry = WaitlistEntry::create([
      'email' => 'applicant@example.com',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant777',
      'status' => 'invited',
    ]);

    $inviteCode = InviteCode::create([
      'code' => 'EXPIRED-CODE-999',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant777',
      'waitlist_entry_id' => $entry->id,
      'expires_at' => Carbon::now()->subSecond(),
    ]);

    $response = $this->post('/register/resend', [
      'expired_code' => 'EXPIRED-CODE-999',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHas('success_resend');

    // エントリーのステータスが expired_pending に更新されていることを確認
    $entry->refresh();
    $this->assertEquals('expired_pending', $entry->status);
  }

  /**
   * 管理者が期限切れ・再送要望のユーザーに対して新しい招待コードを再発行し、古いコードが削除されること。
   */
  public function test_admin_can_reinvite_expired_user_with_new_code(): void
  {
    config(['services.admin.2fa_enabled' => true]);

    $entry = WaitlistEntry::create([
      'email' => 'applicant@example.com',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant777',
      'status' => 'expired_pending',
    ]);

    $oldInviteCode = InviteCode::create([
      'code' => 'EXPIRED-CODE-999',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant777',
      'waitlist_entry_id' => $entry->id,
      'expires_at' => Carbon::now()->subSecond(),
    ]);

    $response = $this->withSession([
      'admin_authenticated' => true,
      'admin_2fa_verified' => true,
    ])
      ->post("/admin/waitlist/{$entry->id}/reinvite");

    $response->assertStatus(302);
    $response->assertSessionHas('success');

    // 古いコードが削除（失効）されていることをアサート
    $this->assertDatabaseMissing('invite_codes', [
      'code' => 'EXPIRED-CODE-999',
    ]);

    // 新しいコードが生成され、期限が延長（未来の時刻）されていることをアサート
    $entry->refresh();
    $this->assertEquals('invited', $entry->status);
    $this->assertNotNull($entry->inviteCode);
    $this->assertNotEquals('EXPIRED-CODE-999', $entry->inviteCode->code);
    $this->assertTrue($entry->inviteCode->expires_at->isFuture());
  }

  /**
   * すでに再送リクエスト済み（expired_pending）の状態で再送リクエストを送った場合、二重送信を防ぐメッセージが返却されること。
   */
  public function test_user_cannot_resend_twice_when_already_expired_pending(): void
  {
    $entry = WaitlistEntry::create([
      'email' => 'applicant@example.com',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant777',
      'status' => 'expired_pending',
    ]);

    $inviteCode = InviteCode::create([
      'code' => 'EXPIRED-CODE-999',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant777',
      'waitlist_entry_id' => $entry->id,
      'expires_at' => Carbon::now()->subSecond(),
    ]);

    $response = $this->post('/register/resend', [
      'expired_code' => 'EXPIRED-CODE-999',
    ]);

     $response->assertStatus(302);
    $response->assertSessionHas('success_resend', '管理者へ招待コードの再送リクエストを送信しました。メールが届くまでしばらくお待ちください。');
  }

  /**
   * すでに登録完了（registered）の状態で再送リクエストを送った場合、エラーになること。
   */
  public function test_user_cannot_request_resend_when_already_registered(): void
  {
    $entry = WaitlistEntry::create([
      'email' => 'applicant@example.com',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant777',
      'status' => 'registered',
    ]);

    $inviteCode = InviteCode::create([
      'code' => 'EXPIRED-CODE-999',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant777',
      'waitlist_entry_id' => $entry->id,
      'expires_at' => Carbon::now()->subSecond(),
    ]);

    $response = $this->post('/register/resend', [
      'expired_code' => 'EXPIRED-CODE-999',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['invite_code' => '無効な招待コードです。']);
  }

  /**
   * 同意チェックボックスが未チェックの場合に登録が拒否されること。
   */
  public function test_register_fails_without_terms_consent(): void
  {
    $response = $this->post('/register', [
      'invite_code' => 'SOME-CODE-123',
      'handle' => 'test.bsky.social',
      'app_password' => 'secret_app_pass',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasErrors(['terms']);
  }
}

