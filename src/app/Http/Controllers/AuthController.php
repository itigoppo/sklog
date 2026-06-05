<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\BlueskyAccount;
use App\Models\InviteCode;
use App\Services\BlueskyService;
use App\Jobs\SyncBlueskyPostsJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Exception;

class AuthController extends Controller
{
  protected BlueskyService $blueskyService;

  public function __construct(BlueskyService $blueskyService)
  {
    $this->blueskyService = $blueskyService;
  }

  /**
   * ログイン・登録画面を表示します。
   */
  public function showLoginForm()
  {
    if (Auth::check()) {
      $handle = Auth::user()->blueskyAccount->handle;
      return redirect()->route('calendar.show', ['handle' => $handle]);
    }

    return view('auth.login');
  }

  /**
   * 新規登録画面を表示します。
   */
  public function showRegisterForm()
  {
    if (Auth::check()) {
      $handle = Auth::user()->blueskyAccount->handle;
      return redirect()->route('calendar.show', ['handle' => $handle]);
    }

    return view('auth.register');
  }

  /**
   * 招待コードを用いた新規ユーザー登録処理を行います。
   */
  public function register(Request $request)
  {
    $request->validate([
      'invite_code' => 'required|string',
      'handle' => 'required|string',
      'app_password' => 'required|string',
    ]);

    // 招待コードの検証
    $inviteCode = InviteCode::where('code', strtoupper(trim($request->invite_code)))->first();
    if (!$inviteCode || $inviteCode->isUsed()) {
      return back()->withErrors([
        'invite_code' => '招待コードが無効または使用済みです。',
      ])->withInput($request->only('handle', 'invite_code'));
    }

    if ($inviteCode->isExpired()) {
      return back()->withErrors([
        'invite_code' => '招待コードの有効期限が切れています。下のボタンから再送を依頼してください。',
      ])->withInput($request->only('handle', 'invite_code'))
        ->with('is_expired', true)
        ->with('expired_code', $inviteCode->code);
    }

    try {
      $session = $this->blueskyService->authenticate($request->handle, $request->app_password);

      $did = $session['did'];
      $handle = $session['handle'];
      $token = $session['accessJwt'];

      // すでに登録済みのアカウントは新規登録不可
      if (BlueskyAccount::where('did', $did)->exists()) {
        return back()->withErrors([
          'handle' => 'このBlueskyアカウントはすでに登録されています。ログインページからログインしてください。',
        ])->withInput($request->only('handle', 'invite_code'));
      }

      // did または ハンドル名 による厳密な本人照合（ハンドルが変わっていても did が一致すればパス）
      if ($inviteCode->did) {
        if ($inviteCode->did !== $did) {
          return back()->withErrors([
            'handle' => 'このハンドル名では登録できません。申請時に使用したハンドルで登録してください。',
          ])->withInput($request->only('handle', 'invite_code'));
        }
      } elseif ($inviteCode->handle) {
        // did移行前や手動作成時などのためのフォールバック照合
        $expectedHandle = strtolower(ltrim($inviteCode->handle, '@'));
        $actualHandle   = strtolower(ltrim($handle, '@'));
        if ($expectedHandle !== $actualHandle) {
          return back()->withErrors([
            'handle' => 'このハンドル名では登録できません。申請時に使用したハンドルで登録してください。',
          ])->withInput($request->only('handle', 'invite_code'));
        }
      }

      $profile = [];
      try {
        $profile = $this->blueskyService->getProfile($did, $token);
      } catch (Exception $e) {
        Log::warning('Register: Failed to fetch profile details: ' . $e->getMessage());
      }

      $displayName = $profile['displayName'] ?? $handle;
      $isPrivate = BlueskyAccount::isPrivateFromProfile($profile);

      $user = DB::transaction(function () use ($did, $handle, $request, $displayName, $profile, $isPrivate, $inviteCode) {
        $user = User::create(['name' => $displayName]);

        $user->blueskyAccount()->create([
          'did' => $did,
          'handle' => $handle,
          'encrypted_app_password' => $request->app_password,
          'avatar_url' => $profile['avatar'] ?? null,
          'banner_url' => $profile['banner'] ?? null,
          'description' => $profile['description'] ?? null,
          'posts_count' => $profile['postsCount'] ?? 0,
          'is_private' => $isPrivate,
        ]);

        $inviteCode->update([
          'used_by_user_id' => $user->id,
          'used_at' => Carbon::now(),
        ]);

        // ウェイトリストのステータスを登録済みに更新
        if ($inviteCode->waitlist_entry_id) {
          \App\Models\WaitlistEntry::where('id', $inviteCode->waitlist_entry_id)
            ->update(['status' => 'registered']);
        }

        return $user;
      });

      Auth::login($user, true);

      return redirect()->route('calendar.show', ['handle' => $handle])
        ->with('success', '登録が完了しました。ようこそ！');

    } catch (Exception $e) {
      return back()->withErrors([
        'handle' => $e->getMessage(),
      ])->withInput($request->only('handle', 'invite_code'));
    }
  }

  /**
   * Bluesky認証を用いたログイン処理を行います（既存ユーザー専用）。
   */
  public function login(Request $request)
  {
    $credentials = $request->validate([
      'handle' => 'required|string',
      'app_password' => 'required|string',
    ]);

    $throttleKey = 'login-attempt:' . $request->ip() . '|' . strtolower($credentials['handle']);

    // 1分間に5回までのログイン試行制限
    if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($throttleKey, 5)) {
      $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($throttleKey);
      return back()->withErrors([
        'handle' => "ログイン試行回数の上限を超えました。{$seconds}秒後に再度お試しください。",
      ])->withInput($request->only('handle'));
    }

    try {
      $session = $this->blueskyService->authenticate($credentials['handle'], $credentials['app_password']);

      $did = $session['did'];
      $handle = $session['handle'];
      $token = $session['accessJwt'];

      // 未登録ユーザーの処理
      $bskyAccount = BlueskyAccount::where('did', $did)->first();
      if (!$bskyAccount) {
        // ログイン試行制限をクリア（正しいパスワード・ログイン情報だが、SkLoG未登録という場合も一旦クリア）
        \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);

        $normalizedHandle = strtolower(ltrim(trim($handle), '@'));

        // 招待済みのウェイトリストエントリー、またはこのハンドル宛ての有効な（未使用の）招待コードが存在するか確認
        $hasActiveInvite = InviteCode::where('handle', $normalizedHandle)
          ->whereNull('used_at')
          ->exists();

        $isInvitedInWaitlist = \App\Models\WaitlistEntry::where('handle', $normalizedHandle)
          ->where('status', 'invited')
          ->exists();

        if ($hasActiveInvite || $isInvitedInWaitlist) {
          return redirect()->route('register')
            ->with('info', '招待コードで新規登録してください。')
            ->withInput(['handle' => $credentials['handle']]);
        }

        // 申請中（pending）または未申請の場合は、ログイン画面に戻してエラーとする（申請済みであることを隠すため）
        return back()->withErrors([
          'handle' => 'ハンドル名またはアプリパスワードが正しくありません。',
        ])->withInput($request->only('handle'));
      }

      $profile = [];
      try {
        $profile = $this->blueskyService->getProfile($did, $token);
      } catch (Exception $e) {
        Log::warning('Login: Failed to fetch profile details: ' . $e->getMessage());
      }

      $isPrivate = BlueskyAccount::isPrivateFromProfile($profile);

      $user = $bskyAccount->user;
      $user->update(['name' => $profile['displayName'] ?? $handle]);
      $bskyAccount->update([
        'handle' => $handle,
        'encrypted_app_password' => $credentials['app_password'],
        'avatar_url' => $profile['avatar'] ?? null,
        'banner_url' => $profile['banner'] ?? null,
        'description' => $profile['description'] ?? null,
        'posts_count' => $profile['postsCount'] ?? 0,
        'is_private' => $isPrivate,
        'sync_error_type' => null,      // ログイン成功時に認証エラーを即座にクリア
        'sync_error_message' => null,   // 同上
      ]);

      // ログイン成功時に試行制限カウントをクリア
      \Illuminate\Support\Facades\RateLimiter::clear($throttleKey);

      Auth::login($user, true);

      return redirect()->route('calendar.show', ['handle' => $handle])
        ->with('success', 'ログインしました。');

    } catch (Exception $e) {
      // ログイン失敗時に制限カウントを増やす（有効期限60秒）
      \Illuminate\Support\Facades\RateLimiter::hit($throttleKey, 60);

      return back()->withErrors([
        'handle' => 'ハンドル名またはアプリパスワードが正しくありません。',
      ])->withInput($request->only('handle'));
    }
  }

  /**
   * ログアウト処理を行います。
   */
  public function logout(Request $request)
  {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login')->with('success', 'ログアウトしました。');
  }

  /**
   * 期限切れの招待コードに対して、再送（再申請）リクエストを受け付けます。
   */
  public function requestResend(Request $request)
  {
    $request->validate([
      'expired_code' => 'required|string',
    ]);

    $inviteCode = InviteCode::where('code', strtoupper(trim($request->expired_code)))->first();
    if (!$inviteCode || $inviteCode->isUsed() || ($inviteCode->waitlistEntry && $inviteCode->waitlistEntry->status === 'registered')) {
      return back()->withErrors(['invite_code' => '無効な招待コードです。']);
    }

    $entry = $inviteCode->waitlistEntry;
    if (!$entry) {
      return back()->withErrors(['invite_code' => '無効な招待コードです。']);
    }

    // すでに再送要望中の場合はDB更新をせず、同一の成功メッセージを返してステータス漏洩を防ぐ
    if ($entry->status !== 'expired_pending') {
      $entry->update(['status' => 'expired_pending']);
    }

    return back()->with('success_resend', '管理者へ招待コードの再送リクエストを送信しました。メールが届くまでしばらくお待ちください。');
  }
}
