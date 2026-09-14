<?php

namespace Tests\Feature;

use App\Models\BlueskyAccount;
use App\Models\InviteCode;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegisterHandleLockTest extends TestCase
{
  use RefreshDatabase;

  /**
   * 招待コードを1件作成します。
   */
  private function createInvite(array $overrides = []): InviteCode
  {
    $entry = WaitlistEntry::create([
      'email' => 'applicant@example.com',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant',
      'status' => 'invited',
    ]);

    return InviteCode::create(array_merge([
      'code' => 'AAAA-BBBB-CCCC',
      'handle' => 'registrant.bsky.social',
      'did' => 'did:plc:registrant',
      'waitlist_entry_id' => $entry->id,
      'expires_at' => Carbon::now()->addDay(),
    ], $overrides));
  }

  /**
   * 招待メールのリンク経由では、招待先のハンドル名が固定表示されること。
   */
  public function test_invite_link_locks_handle_to_the_invited_account(): void
  {
    $invite = $this->createInvite();

    $response = $this->get('/register?code='.$invite->code);

    $response->assertOk();
    $response->assertSee('id="lockedHandleBlock"', false);
    $response->assertSee('@registrant.bsky.social');
    $response->assertSee('招待済み');
    // 送信される値も招待先のハンドル名で固定される
    $response->assertSee('id="finalHandle" value="registrant.bsky.social"', false);
    // 招待コードも自動入力され、編集できない
    $response->assertSee('value="AAAA-BBBB-CCCC"', false);
    $response->assertSee('readonly', false);
  }

  /**
   * did を持つ招待では、ハンドル名を変更した人のために固定を解除できること。
   */
  public function test_invite_with_did_offers_unlock(): void
  {
    $invite = $this->createInvite();

    $response = $this->get('/register?code='.$invite->code);

    $response->assertOk();
    $response->assertSee('Blueskyでハンドル名を変更した方はこちら');
  }

  /**
   * did を持たない招待では、ハンドル名の完全一致が必須なので解除リンクを出さないこと。
   */
  public function test_invite_without_did_does_not_offer_unlock(): void
  {
    $invite = $this->createInvite(['did' => null]);

    $response = $this->get('/register?code='.$invite->code);

    $response->assertOk();
    $response->assertSee('id="lockedHandleBlock"', false);
    $response->assertDontSee('Blueskyでハンドル名を変更した方はこちら');
  }

  /**
   * 期限切れの招待コードでは、ハンドル名を固定しないこと。
   */
  public function test_expired_invite_does_not_lock_handle(): void
  {
    $invite = $this->createInvite(['expires_at' => Carbon::now()->subDay()]);

    $response = $this->get('/register?code='.$invite->code);

    $response->assertOk();
    $response->assertDontSee('id="lockedHandleBlock"', false);
  }

  /**
   * 使用済みの招待コードでは、ハンドル名を固定しないこと（コードからハンドル名が引けないようにする）。
   */
  public function test_used_invite_does_not_lock_handle(): void
  {
    $user = User::create(['name' => '既存ユーザー']);
    $invite = $this->createInvite([
      'used_by_user_id' => $user->id,
      'used_at' => Carbon::now(),
    ]);

    $response = $this->get('/register?code='.$invite->code);

    $response->assertOk();
    $response->assertDontSee('id="lockedHandleBlock"', false);
    $response->assertDontSee('registrant.bsky.social');
  }

  /**
   * 存在しない招待コードでは、ハンドル名を固定しないこと。
   */
  public function test_unknown_invite_code_does_not_lock_handle(): void
  {
    $response = $this->get('/register?code=ZZZZ-ZZZZ-ZZZZ');

    $response->assertOk();
    $response->assertDontSee('id="lockedHandleBlock"', false);
  }

  /**
   * 入力エラーで戻ってきた場合（URLにcodeが無い場合）も、固定表示が維持されること。
   */
  public function test_lock_survives_validation_failure_without_query_string(): void
  {
    $invite = $this->createInvite();

    $response = $this->withSession(['_old_input' => ['invite_code' => $invite->code]])
      ->get('/register');

    $response->assertOk();
    $response->assertSee('id="lockedHandleBlock"', false);
    $response->assertSee('id="finalHandle" value="registrant.bsky.social"', false);
  }

  /**
   * ログイン中に参加申請画面を開いたら、全ログアウトの確認を出すこと。
   */
  public function test_waitlist_page_asks_to_log_out_while_authenticated(): void
  {
    $user = User::create(['name' => 'ログイン中ユーザー']);
    BlueskyAccount::create([
      'id' => Str::uuid(),
      'user_id' => $user->id,
      'did' => 'did:plc:current',
      'handle' => 'current.bsky.social',
      'encrypted_app_password' => 'dummy',
    ]);

    $response = $this->actingAs($user)->get('/join');

    $response->assertOk();
    // 申請は外から来る経路なので、ログイン中なら全ログアウトの確認を出す
    $response->assertSee('ログイン中のアカウントはすべてログアウトされます。');
    $response->assertDontSee('current.bsky.social');
  }
}
