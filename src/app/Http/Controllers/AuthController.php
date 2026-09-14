<?php

namespace App\Http\Controllers;

use App\Models\BlueskyAccount;
use App\Models\InviteCode;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\BlueskyService;
use App\Support\AccountSession;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

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

    return view('auth.login', ['isAdding' => false, 'formAction' => url('/login'), 'prefillHandle' => null]);
  }

  /**
   * ログイン中に別アカウントを追加するための画面を表示します。
   */
  public function showAddAccountForm(Request $request)
  {
    $handle = Auth::user()?->blueskyAccount?->handle;

    if (! $handle) {
      return redirect('/');
    }

    // 招待コードで未登録のアカウントを追加するモード
    if ($request->query('mode') === 'register') {
      $invite = $this->resolveLockableInvite($request);

      return view('auth.register', [
        'isAdding' => true,
        'formAction' => route('accounts.add.register'),
        'lockedHandle' => $invite ? strtolower(ltrim($invite->handle, '@')) : null,
        'lockedCode' => $invite?->code,
        'canUnlockHandle' => (bool) $invite?->did,
      ]);
    }

    // 上限に達している場合は、画面側がフォームの代わりに
    // 「1件ログアウトしてください」の一覧を出す
    return view('auth.login', [
      'isAdding' => true,
      'formAction' => route('accounts.add.post'),
      // 再ログインの導線から来た場合に、ハンドル名を埋めておく
      'prefillHandle' => $request->query('handle'),
    ]);
  }

  /**
   * 新規登録画面を表示します。
   *
   * ログイン中の場合は、招待コードを持つ別アカウントの「追加登録」として表示します。
   * 招待メールのリンク経由（有効な招待コード付き）の場合は、招待先のハンドル名を固定表示します。
   */
  public function showRegisterForm(Request $request)
  {
    // 招待リンクから来た人がログイン中の人とは限らないので、ログイン中なら確認を挟む。
    // マルチアカウントへ追加したい場合は、追加画面の登録モードから入ってもらう
    if (Auth::check()) {
      return $this->showLogoutToContinue('register', $request->query('code'));
    }

    $invite = $this->resolveLockableInvite($request);

    return view('auth.register', [
      'isAdding' => false,
      'formAction' => url('/register'),
      // 招待先のハンドル名（固定表示する場合のみ）
      'lockedHandle' => $invite ? strtolower(ltrim($invite->handle, '@')) : null,
      'lockedCode' => $invite?->code,
      // did で本人照合できる招待なら、ハンドル名を変更した人のために固定を解除できるようにする
      'canUnlockHandle' => (bool) $invite?->did,
    ]);
  }

  /**
   * 固定表示に使える招待コードを解決します。
   *
   * 使用済み・期限切れ・ハンドル名なしの招待コードは、固定表示せず自由入力のままにします
   * （コードを知っているだけで申請済みのハンドル名が分かる範囲を、有効な招待に限定するためでもあります）。
   */
  private function resolveLockableInvite(Request $request): ?InviteCode
  {
    // 入力エラーで戻ってきた場合も固定表示を維持するため、フラッシュされた入力値も見る
    $code = $request->old('invite_code') ?: $request->query('code');

    if (! is_string($code) || trim($code) === '') {
      return null;
    }

    $invite = InviteCode::where('code', strtoupper(trim($code)))->first();

    if (! $invite || ! $invite->handle || $invite->isUsed() || $invite->isExpired()) {
      return null;
    }

    return $invite;
  }

  /**
   * 招待コードを用いた新規ユーザー登録処理を行います。
   */
  public function register(Request $request)
  {
    // 招待リンクはメールから開かれるので、開いた人とログイン中の人が同じとは限らない。
    // 外から来た経路は常に新規として扱い、続けるなら先に全アカウントをログアウトさせる
    if (Auth::check()) {
      return $this->showLogoutToContinue('register', $request->input('invite_code'));
    }

    return $this->createAccount($request, isAdding: false);
  }

  /**
   * ログイン中に、招待コードで別のアカウントを登録してマルチアカウントへ追加します。
   */
  public function addAccountRegister(Request $request)
  {
    return $this->createAccount($request, isAdding: true);
  }

  /**
   * 招待コードを検証し、アカウントを作成してアクティブにします。
   *
   * @param  bool  $isAdding  ログイン中のアカウントを保ったままの追加かどうか
   */
  private function createAccount(Request $request, bool $isAdding)
  {
    $request->validate([
      'invite_code' => 'required|string',
      'handle' => 'required|string',
      'app_password' => 'required|string',
      'terms' => 'accepted',
    ], [
      'terms.accepted' => 'サービス利用への同意が必要です。',
    ]);

    $remember = $request->boolean('remember');

    if ($isAdding && AccountSession::isFull()) {
      return back()->withErrors([
        'handle' => '同時にログインできるアカウントは最大'.AccountSession::MAX_ACCOUNTS.'件です。不要なアカウントをログアウトしてから追加してください。',
      ])->withInput($request->only('handle', 'invite_code'));
    }

    // 招待コードの検証
    $inviteCode = InviteCode::where('code', strtoupper(trim($request->invite_code)))->first();
    if (! $inviteCode || $inviteCode->isUsed()) {
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
          'handle' => $isAdding
            ? 'このBlueskyアカウントはすでに登録済みです。招待コードは不要なので、「別のアカウントを追加」からログインして追加してください。'
            : 'このBlueskyアカウントはすでに登録されています。ログインページからログインしてください。',
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
        $actualHandle = strtolower(ltrim($handle, '@'));
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
        Log::warning('Register: Failed to fetch profile details: '.$e->getMessage());
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
          WaitlistEntry::where('id', $inviteCode->waitlist_entry_id)
            ->update(['status' => 'registered']);
        }

        return $user;
      });

      AccountSession::switchTo($user, $remember);

      return redirect()->route('calendar.show', ['handle' => $handle])
        ->with('success', $isAdding
          ? '@'.$handle.' の登録が完了し、アカウントを追加しました。'
          : '登録が完了しました。ようこそ！');

    } catch (Exception $e) {
      return back()->withErrors([
        'handle' => $e->getMessage(),
      ])->withInput($request->only('handle', 'invite_code'));
    }
  }

  /**
   * Bluesky認証を用いたログイン処理を行います（既存ユーザー専用）。
   *
   * すでにログイン中の場合は「アカウントの追加」として扱い、
   * 認証に成功したアカウントをアクティブに切り替えます。
   */
  public function login(Request $request)
  {
    // ログイン中というだけで勝手にマルチアカウントへ紐付けない。
    // リストに載せるのは、本人が明示的に追加しに来た /accounts/add からだけにする
    if (Auth::check()) {
      return redirect()->route('accounts.add')
        ->with('error', 'すでにログイン中です。別のアカウントを使うには、この画面から追加してください。');
    }

    return $this->authenticate($request, isAdding: false);
  }

  /**
   * ログイン中に、別のアカウントをマルチアカウントとして追加します。
   */
  public function addAccount(Request $request)
  {
    return $this->authenticate($request, isAdding: true);
  }

  /**
   * Bluesky 認証を行い、成功したアカウントをアクティブにします。
   *
   * @param  bool  $isAdding  ログイン中のアカウントを保ったままの追加かどうか
   */
  private function authenticate(Request $request, bool $isAdding)
  {
    $credentials = $request->validate([
      'handle' => 'required|string',
      'app_password' => 'required|string',
    ]);

    $remember = $request->boolean('remember');

    if ($isAdding && AccountSession::isFull()) {
      return back()->withErrors([
        'handle' => '同時にログインできるアカウントは最大'.AccountSession::MAX_ACCOUNTS.'件です。不要なアカウントをログアウトしてから追加してください。',
      ])->withInput($request->only('handle'));
    }

    $throttleKey = 'login-attempt:'.$request->ip().'|'.strtolower($credentials['handle']);

    // 1分間に5回までのログイン試行制限
    if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
      $seconds = RateLimiter::availableIn($throttleKey);

      return back()->withErrors([
        'handle' => "ログイン試行回数の上限を超えました。{$seconds}秒後に再度お試しください。",
      ])->withInput($request->only('handle'));
    }

    try {
      $session = null;
      try {
        $session = $this->blueskyService->authenticate($credentials['handle'], $credentials['app_password']);
      } catch (Exception $authEx) {
        // 認証失敗時、古いハンドル名でのログイン試行である可能性をチェック
        $bskyAccount = BlueskyAccount::where('handle', $credentials['handle'])->first();
        if ($bskyAccount) {
          try {
            // DIDから最新のプロフィールを取得（トークンなしのパブリックAPI呼び出し）
            $profile = $this->blueskyService->getProfile($bskyAccount->did);
            $currentHandle = $profile['handle'] ?? null;

            // ハンドル名が変更されていた場合、新しいハンドル名で再認証を試みる
            if ($currentHandle && strtolower($bskyAccount->handle) !== strtolower($currentHandle)) {
              $session = $this->blueskyService->authenticate($currentHandle, $credentials['app_password']);
            }
          } catch (Exception $fallbackEx) {
            // プロフィール取得や再認証に失敗した場合は、元の例外をそのまま投げる
            throw $authEx;
          }
        }

        if (! $session) {
          throw $authEx;
        }
      }

      $did = $session['did'];
      $handle = $session['handle'];
      $token = $session['accessJwt'];

      // 未登録ユーザーの処理
      $bskyAccount = BlueskyAccount::where('did', $did)->first();
      if (! $bskyAccount) {
        // ログイン試行制限をクリア（正しいパスワード・ログイン情報だが、SkLoG未登録という場合も一旦クリア）
        RateLimiter::clear($throttleKey);

        $normalizedHandle = strtolower(ltrim(trim($handle), '@'));

        // 不変のDIDで照合する。ハンドル名は変更・再利用されるため、did移行前のレコードに限ってハンドル名でフォールバックする
        $matchesApplicant = function ($query) use ($did, $normalizedHandle) {
          $query->where('did', $did)
            ->orWhere(fn ($q) => $q->whereNull('did')->where('handle', $normalizedHandle));
        };

        // 招待済みのウェイトリストエントリー、またはこのアカウント宛ての有効な（未使用の）招待コードが存在するか確認
        $hasActiveInvite = InviteCode::whereNull('used_at')
          ->where($matchesApplicant)
          ->exists();

        $isInvitedInWaitlist = WaitlistEntry::where('status', 'invited')
          ->where($matchesApplicant)
          ->exists();

        if ($hasActiveInvite || $isInvitedInWaitlist) {
          return redirect()->route('register')
            ->with('info', $isAdding
              ? 'このアカウントには招待コードが発行されています。コードを入力して追加登録してください。'
              : '招待コードで新規登録してください。')
            ->withInput(['handle' => $credentials['handle']]);
        }

        // アカウント追加時は、Bluesky認証に成功した=本人であるため、未登録であることをそのまま伝える
        if ($isAdding) {
          return back()->withErrors([
            'handle' => '@'.$handle.' はまだ'.config('app.name', 'SkLoG').'に登録されていません。追加できるのは、参加申請 → 招待コードの受け取り → 登録 まで済んだアカウントだけです。',
          ])->withInput($request->only('handle'));
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
        Log::warning('Login: Failed to fetch profile details: '.$e->getMessage());
      }

      $isPrivate = BlueskyAccount::isPrivateFromProfile($profile);

      // ハンドル名の変更を検知した場合
      if ($bskyAccount->handle !== $handle) {
        session([
          'handle_change_pending' => [
            'user_id' => $bskyAccount->user_id,
            'old_handle' => $bskyAccount->handle,
            'new_handle' => $handle,
            'app_password' => $credentials['app_password'],
            'profile' => $profile,
            'is_private' => $isPrivate,
            'throttle_key' => $throttleKey,
            'is_adding' => $isAdding,
            'remember' => $remember,
          ],
        ]);

        return redirect()->route('login.confirm_handle_change');
      }

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
        'is_auto_sync_enabled' => true, // ログイン成功時に自動同期を再有効化
        'auth_failure_count' => 0,      // エラーカウントをリセット
        'sync_error_type' => null,      // ログイン成功時に認証エラーを即座にクリア
        'sync_error_message' => null,   // 同上
      ]);

      // ログイン成功時に試行制限カウントをクリア
      RateLimiter::clear($throttleKey);

      $wasActive = Auth::id() === $user->id;
      AccountSession::switchTo($user, $remember);

      return redirect()->route('calendar.show', ['handle' => $handle])
        ->with('success', $isAdding && ! $wasActive ? '@'.$handle.' を追加してログインしました。' : 'ログインしました。');

    } catch (Exception $e) {
      // ログイン失敗時に制限カウントを増やす（有効期限60秒）
      RateLimiter::hit($throttleKey, 60);

      return back()->withErrors([
        'handle' => 'ハンドル名またはアプリパスワードが正しくありません。',
      ])->withInput($request->only('handle'));
    }
  }

  /**
   * ログイン中に、外から来た登録・申請の経路へ入ろうとしたときの確認画面を出します。
   *
   * 招待リンクはメールから開かれるので、開いた人がログイン中の人とは限りません。
   * ログイン中のハンドル名は伏せたまま、全ログアウトして続けるかどうかだけを尋ねます。
   */
  private function showLogoutToContinue(string $to, mixed $inviteCode)
  {
    return view('auth.logout_to_continue', [
      'to' => $to,
      'inviteCode' => is_string($inviteCode) && trim($inviteCode) !== '' ? trim($inviteCode) : null,
    ]);
  }

  /**
   * 確認画面からの送信を受けて、全アカウントをログアウトしてから元の経路へ戻します。
   *
   * 遷移先は登録と参加申請に限定しています（任意のURLへ飛ばさないため）。
   */
  public function logoutAndContinue(Request $request)
  {
    $data = $request->validate([
      'to' => 'required|string|in:register,join',
      'invite_code' => 'nullable|string',
    ]);

    $this->flushAllAccounts($request);

    if ($data['to'] === 'join') {
      return redirect()->route('waitlist.show');
    }

    $code = trim((string) ($data['invite_code'] ?? ''));

    return redirect()->route('register', $code !== '' ? ['code' => $code] : []);
  }

  /**
   * ログアウト処理を行います。
   *
   * 他にログイン中のアカウントが残っている場合は、そのアカウントへ切り替えます。
   * `all` パラメータが真のときは、セッション内のすべてのアカウントをログアウトします。
   * `user_id` パラメータのときは、そのアカウントだけを外します（同時ログイン上限に
   * 達した状態でアカウントを追加するための導線から使います）。
   */
  public function logout(Request $request)
  {
    $targetId = $request->input('user_id');

    // アクティブなアカウントの指定は、下の通常のログアウト（残りへ切り替え）に任せる
    if (! $request->boolean('all') && $targetId && $targetId !== Auth::id()) {
      if (! AccountSession::has($targetId)) {
        return back()->with('error', 'そのアカウントはログイン中ではありません。');
      }

      $targetHandle = User::with('blueskyAccount')->find($targetId)?->blueskyAccount?->handle;
      AccountSession::forget($targetId);

      return back()->with('success', $targetHandle
        ? '@'.$targetHandle.' からログアウトしました。'
        : 'アカウントからログアウトしました。');
    }

    if (! $request->boolean('all')) {
      $currentHandle = Auth::user()?->blueskyAccount?->handle;
      $next = AccountSession::logoutCurrentAndSwitchNext();

      if ($next) {
        $nextHandle = $next->blueskyAccount->handle;

        return redirect()->route('calendar.show', ['handle' => $nextHandle])
          ->with('success', $currentHandle
            ? '@'.$currentHandle.' からログアウトし、@'.$nextHandle.' に切り替えました。'
            : '@'.$nextHandle.' に切り替えました。');
      }
    }

    $this->flushAllAccounts($request);

    return redirect('/login')->with('success', 'ログアウトしました。');
  }

  /**
   * すべてのアカウントをログアウトし、セッションを破棄します。
   *
   * 管理者セッションは利用者のログアウトとは独立しているので、退避して復元します。
   */
  private function flushAllAccounts(Request $request): void
  {
    $adminAuth = $request->session()->get('admin_authenticated');
    $admin2fa = $request->session()->get('admin_2fa_verified');

    AccountSession::clear();
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    if ($adminAuth) {
      $request->session()->put('admin_authenticated', $adminAuth);
    }
    if ($admin2fa) {
      $request->session()->put('admin_2fa_verified', $admin2fa);
    }
  }

  /**
   * このセッションでログイン済みの別アカウントへ切り替えます。
   */
  public function switchAccount(Request $request)
  {
    $request->validate([
      'user_id' => 'required|string',
    ]);

    $userId = $request->input('user_id');

    if ($userId === Auth::id()) {
      return back();
    }

    // このセッションで実際にログインしたアカウントだけを切り替え先として許可する
    if (! AccountSession::has($userId)) {
      return back()->with('error', 'そのアカウントには切り替えられません。もう一度ログインしてください。');
    }

    $user = User::with('blueskyAccount')->find($userId);
    if (! $user || ! $user->blueskyAccount) {
      AccountSession::forget($userId);

      return back()->with('error', 'アカウントが見つかりませんでした。もう一度ログインしてください。');
    }

    $handle = $user->blueskyAccount->handle;

    // 保持しない選択だった、または他の端末でログアウトされたアカウント
    if (AccountSession::needsReauth($userId)) {
      return redirect()->route('accounts.add', ['handle' => $handle])
        ->with('error', '@'.$handle.' はログアウトされています。アプリパスワードを入力して再ログインしてください。');
    }

    AccountSession::switchTo($user);

    return redirect()->route('calendar.show', ['handle' => $handle])
      ->with('success', '@'.$handle.' に切り替えました。');
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
    if (! $inviteCode || $inviteCode->isUsed() || ($inviteCode->waitlistEntry && $inviteCode->waitlistEntry->status === 'registered')) {
      return back()->withErrors(['invite_code' => '無効な招待コードです。']);
    }

    $entry = $inviteCode->waitlistEntry;
    if (! $entry) {
      return back()->withErrors(['invite_code' => '無効な招待コードです。']);
    }

    // すでに再送要望中の場合はDB更新をせず、同一の成功メッセージを返してステータス漏洩を防ぐ
    if ($entry->status !== 'expired_pending') {
      $entry->update(['status' => 'expired_pending']);
    }

    return back()->with('success_resend', '管理者へ招待コードの再送リクエストを送信しました。メールが届くまでしばらくお待ちください。');
  }

  /**
   * ハンドル名変更確認画面を表示します。
   */
  public function showConfirmHandleChange(Request $request)
  {
    $pending = session('handle_change_pending');

    // 別アカウントでログイン中でも、保留中の手続きがあれば確認画面を表示する
    if (! $pending) {
      return Auth::check()
        ? redirect()->route('calendar.show', ['handle' => Auth::user()->blueskyAccount->handle])
        : redirect()->route('login');
    }

    return view('auth.confirm_handle_change', [
      'oldHandle' => $pending['old_handle'],
      'newHandle' => $pending['new_handle'],
    ]);
  }

  /**
   * ハンドル名変更確認の送信処理を行います。
   */
  public function confirmHandleChange(Request $request)
  {
    $pending = session('handle_change_pending');
    if (! $pending) {
      return redirect()->route('login');
    }

    $request->validate([
      'action' => 'required|string|in:consent,delete,cancel',
    ]);

    $action = $request->input('action');
    $throttleKey = $pending['throttle_key'];

    if ($action === 'cancel') {
      session()->forget('handle_change_pending');

      return $this->redirectAfterHandleChangeExit('info', 'ログインをキャンセルしました。');
    }

    $user = User::find($pending['user_id']);
    if (! $user) {
      session()->forget('handle_change_pending');
      AccountSession::forget($pending['user_id']);

      return redirect()->route('login')->withErrors(['handle' => 'ユーザーが見つかりません。']);
    }

    if ($action === 'delete') {
      DB::transaction(function () use ($user) {
        $user->delete(); // 外部キー制約により、関連テーブルのレコードもカスケード削除されます
      });

      session()->forget('handle_change_pending');
      AccountSession::forget($user->id);

      return $this->redirectAfterHandleChangeExit('success', 'アカウントとすべてのカレンダーデータを完全に削除しました。');
    }

    if ($action === 'consent') {
      $bskyAccount = $user->blueskyAccount;
      $profile = $pending['profile'];
      $newHandle = $pending['new_handle'];

      $user->update(['name' => $profile['displayName'] ?? $newHandle]);
      $bskyAccount->update([
        'handle' => $newHandle,
        'encrypted_app_password' => $pending['app_password'],
        'avatar_url' => $profile['avatar'] ?? null,
        'banner_url' => $profile['banner'] ?? null,
        'description' => $profile['description'] ?? null,
        'posts_count' => $profile['postsCount'] ?? 0,
        'is_private' => $pending['is_private'],
        'is_auto_sync_enabled' => true, // 同意成功時に自動同期を再有効化
        'auth_failure_count' => 0,      // エラーカウントをリセット
        'sync_error_type' => null,
        'sync_error_message' => null,
      ]);

      // 試行制限カウントをクリア
      RateLimiter::clear($throttleKey);
      session()->forget('handle_change_pending');

      // ログイン画面で選ばれた「ログイン状態を保持する」をそのまま引き継ぐ
      AccountSession::switchTo($user, $pending['remember'] ?? null);

      return redirect()->route('calendar.show', ['handle' => $newHandle])
        ->with('success', ($pending['is_adding'] ?? false)
          ? '@'.$newHandle.' を追加してログインし、新しいハンドル名を引き継ぎました。'
          : 'ログインし、新しいハンドル名を引き継ぎました。');
    }

    return redirect()->route('login');
  }

  /**
   * ハンドル名変更手続きを中断・完了したあとの遷移先を決めます。
   * 他アカウントでログイン中ならそのカレンダーへ、そうでなければログイン画面へ戻します。
   */
  private function redirectAfterHandleChangeExit(string $key, string $message)
  {
    $handle = Auth::user()?->blueskyAccount?->handle;

    if ($handle) {
      return redirect()->route('calendar.show', ['handle' => $handle])->with($key, $message);
    }

    return redirect()->route('login')->with($key, $message);
  }
}
