<?php

namespace Tests\Feature;

use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ログイン試行制限のカウントを各テスト前にリセット
        RateLimiter::clear('admin-login:127.0.0.1');
        
        // ダミー管理者パスワードをセット
        config(['services.admin.password' => 'super-secret-admin-pass']);
    }

    /**
     * 2FAが無効な状態で、正しいパスワードを入力するとログインに成功し、セキュリティ設定画面へリダイレクトされること。
     */
    public function test_admin_login_without_2fa_succeeds_with_correct_password(): void
    {
        config(['services.admin.2fa_enabled' => false]);

        $response = $this->post('/admin/login', [
            'password' => 'super-secret-admin-pass',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/security');
        $this->assertTrue(session('admin_authenticated'));
    }

    /**
     * 2FAが無効な状態で、間違ったパスワードではログインできないこと。
     */
    public function test_admin_login_without_2fa_fails_with_incorrect_password(): void
    {
        config(['services.admin.2fa_enabled' => false]);

        $response = $this->post('/admin/login', [
            'password' => 'wrong-pass',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password' => 'パスワードが正しくありません。']);
        $this->assertNull(session('admin_authenticated'));
    }

    /**
     * 2FAが有効な状態で、正しいパスワードと正しいOTPを入力するとログインできること。
     */
    public function test_admin_login_with_2fa_succeeds_with_correct_password_and_otp(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP'; // Base32のテスト用シークレット (US-ASCII 'Hello!')
        config([
            'services.admin.2fa_enabled' => true,
            'services.admin.2fa_secret' => $secret,
        ]);

        // 現在有効な正しいOTPを取得
        $otp = TotpService::generateCode($secret);

        $response = $this->post('/admin/login', [
            'password' => 'super-secret-admin-pass',
            'totp_code' => $otp,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/admin/waitlist');
        $this->assertTrue(session('admin_authenticated'));
        $this->assertTrue(session('admin_2fa_verified'));
    }

    /**
     * 2FAが有効な状態で、パスワードは正しいがOTPが正しくない場合はログインできないこと。
     */
    public function test_admin_login_with_2fa_fails_with_correct_password_but_incorrect_otp(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        config([
            'services.admin.2fa_enabled' => true,
            'services.admin.2fa_secret' => $secret,
        ]);

        $response = $this->post('/admin/login', [
            'password' => 'super-secret-admin-pass',
            'totp_code' => '000000', // 不正なOTP
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['totp_code' => 'ワンタイムパスワードが正しくありません。']);
        $this->assertNull(session('admin_authenticated'));
    }

    /**
     * 連続で5回ログインに失敗するとロックアウトされること。
     */
    public function test_admin_login_rate_limiting_locks_out_after_max_attempts(): void
    {
        config(['services.admin.2fa_enabled' => false]);

        // 5回失敗させる
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/admin/login', [
                'password' => 'wrong-pass',
            ]);
            $response->assertSessionHasErrors(['password']);
        }

        // 6回目の試行でレートリミットによるロックが作動することを確認
        $response = $this->post('/admin/login', [
            'password' => 'super-secret-admin-pass', // 正しいパスワードでもロックされるはず
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['password']);
        
        // エラーメッセージに「ログイン試行回数が多すぎます」が含まれていることをアサート
        $errors = session('errors');
        $this->assertStringContainsString('ログイン試行回数が多すぎます', $errors->first('password'));
        
        $this->assertNull(session('admin_authenticated'));
    }

    /**
     * 管理者がログインしている状態のとき、セキュリティ設定画面に正常にアクセスできること。
     */
    public function test_admin_can_access_security_settings_page_when_authenticated(): void
    {
        config(['services.admin.2fa_enabled' => false]);

        $response = $this->withSession(['admin_authenticated' => true])
            ->get('/admin/security');

        $response->assertStatus(200);
        $response->assertSee('セキュリティ設定');
        $response->assertSee('新規セットアップ手順');
        
        // セッションに仮のシークレットキーが格納されていることを検証
        $this->assertNotNull(session('admin_temp_2fa_secret'));
    }

    /**
     * 未ログイン状態でセキュリティ設定画面にアクセスしようとすると、ログイン画面にリダイレクトされること。
     */
    public function test_admin_cannot_access_security_settings_page_when_unauthenticated(): void
    {
        $response = $this->get('/admin/security');

        $response->assertStatus(302);
        $response->assertRedirect('/admin/login');
    }

    /**
     * 正しいコードを使用して2FAセットアップの接続検証テストに成功すること。
     */
    public function test_admin_can_verify_2fa_setup_with_correct_code(): void
    {
        $tempSecret = 'JBSWY3DPEHPK3PXP';

        // セッションにあらかじめ仮のシークレットをセット
        $response = $this->withSession([
            'admin_authenticated' => true,
            'admin_temp_2fa_secret' => $tempSecret,
        ])
        ->postJson('/admin/security/verify', [
            'code' => TotpService::generateCode($tempSecret),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * 間違ったコードを使用した場合は2FAセットアップの接続検証テストに失敗すること。
     */
    public function test_admin_cannot_verify_2fa_setup_with_incorrect_code(): void
    {
        $tempSecret = 'JBSWY3DPEHPK3PXP';

        $response = $this->withSession([
            'admin_authenticated' => true,
            'admin_temp_2fa_secret' => $tempSecret,
        ])
        ->postJson('/admin/security/verify', [
            'code' => '000000', // 不正なコード
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => false,
            'message' => 'ワンタイムパスワードが正しくありません。'
        ]);
    }

    /**
     * 2FAが無効な状態でウェイトリスト画面にアクセスしようとすると、セキュリティ設定画面に強制リダイレクトされること。
     */
    public function test_admin_cannot_access_waitlist_when_2fa_is_disabled(): void
    {
        config(['services.admin.2fa_enabled' => false]);

        $response = $this->withSession(['admin_authenticated' => true])
            ->get('/admin/waitlist');

        $response->assertStatus(302);
        $response->assertRedirect('/admin/security');
        $response->assertSessionHas('error', 'ウェイトリストを閲覧するには、二要素認証 (2FA) を有効化する必要があります。');
    }

    /**
     * すでにパスワードのみでログインしているセッションであっても、
     * 途中で2FAが有効化された場合、ミドルウェアが自動検知して強制ログアウト（リダイレクト）させること。
     */
    public function test_old_session_without_2fa_is_force_logged_out_when_2fa_gets_enabled(): void
    {
        // 2FAが有効な状態で、パスワードのみのセッションを持つ管理者がアクセスを試みる
        config(['services.admin.2fa_enabled' => true]);

        $response = $this->withSession([
            'admin_authenticated' => true,
            // 'admin_2fa_verified' は欠落している状態
        ])
        ->get('/admin/waitlist');

        $response->assertStatus(302);
        $response->assertRedirect('/admin/login');
        
        // エラーセッションが返っていること
        $response->assertSessionHasErrors(['password' => 'セキュリティ設定が更新されました。再度ログインしてください。']);
        
        // セッションが安全に破棄されていること
        $this->assertNull(session('admin_authenticated'));
        $this->assertNull(session('admin_2fa_verified'));
    }
}
