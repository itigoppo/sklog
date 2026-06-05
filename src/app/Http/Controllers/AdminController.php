<?php

namespace App\Http\Controllers;

use App\Mail\InviteCodeMail;
use App\Models\InviteCode;
use App\Models\WaitlistEntry;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AdminController extends Controller
{
  public function showLogin()
  {
    if (session('admin_authenticated')) {
      return redirect()->route('admin.waitlist');
    }

    return view('admin.login');
  }

  public function login(Request $request)
  {
    $rules = [
      'password' => 'required|string',
    ];

    // 2FAが有効な場合はワンタイムパスワードも必須に
    $is2faEnabled = config('services.admin.2fa_enabled');
    if ($is2faEnabled) {
      $rules['totp_code'] = 'required|string|size:6';
    }

    $request->validate($rules);

    // ログイン制限のキー定義（IPごと）
    $throttleKey = 'admin-login:' . $request->ip();

    // 試行回数チェック（1分間に5回まで）
    if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
      $seconds = RateLimiter::availableIn($throttleKey);
      return back()->withErrors([
        'password' => "ログイン試行回数が多すぎます。{$seconds}秒後に再試行してください。"
      ])->withInput();
    }

    // パスワード照合
    $adminPassword = config('services.admin.password');
    if (!$adminPassword || $request->password !== $adminPassword) {
      RateLimiter::hit($throttleKey, 60);
      return back()->withErrors(['password' => 'パスワードが正しくありません。'])->withInput();
    }

    // 二要素認証の検証
    if ($is2faEnabled) {
      $secret = config('services.admin.2fa_secret');
      if (!$secret || !TotpService::verify($secret, $request->totp_code)) {
        RateLimiter::hit($throttleKey, 60);
        return back()->withErrors(['totp_code' => 'ワンタイムパスワードが正しくありません。'])->withInput();
      }
      session(['admin_2fa_verified' => true]);
    }

    // 成功したらレートリミットをクリア
    RateLimiter::clear($throttleKey);

    // セッション再生成（Session Fixation対策）
    $request->session()->regenerate();

    session(['admin_authenticated' => true]);

    // 2FAが無効な場合はセキュリティ設定画面に強制遷移
    if (!config('services.admin.2fa_enabled')) {
      return redirect()->route('admin.security')->with('warning', '個人情報を保護するため、まず二要素認証 (2FA) の設定を完了してください。');
    }

    return redirect()->route('admin.waitlist');
  }

  public function logout(Request $request)
  {
    $request->session()->forget(['admin_authenticated', 'admin_2fa_verified']);

    return redirect()->route('admin.login');
  }

  public function index(Request $request)
  {
    // 2FAが有効化されていない場合は、申請者一覧を表示させずセキュリティ画面へ強制リダイレクト
    if (!config('services.admin.2fa_enabled')) {
      return redirect()->route('admin.security')->with('error', 'ウェイトリストを閲覧するには、二要素認証 (2FA) を有効化する必要があります。');
    }

    // 全体の統計情報を取得（フィルタ条件にかかわらず常に全体の総数を出す）
    $stats = [
      'total'           => WaitlistEntry::count(),
      'pending'         => WaitlistEntry::where('status', 'pending')->count(),
      'invited'         => WaitlistEntry::where('status', 'invited')->count(),
      'registered'      => WaitlistEntry::where('status', 'registered')->count(),
      'expired_pending' => WaitlistEntry::where('status', 'expired_pending')->count(),
    ];

    $query = WaitlistEntry::with('inviteCode');

    // ハンドル名での部分一致検索
    if ($request->filled('handle')) {
      $handle = trim($request->handle);
      $handle = ltrim($handle, '@'); // @が付いている場合は除去
      $query->where('handle', 'like', '%' . $handle . '%');
    }

    // ステータスでのフィルタ
    if ($request->filled('status')) {
      $status = $request->status;
      if (in_array($status, ['pending', 'invited', 'registered', 'expired_pending'])) {
        $query->where('status', $status);
      }
    }

    $entries = $query->orderBy('created_at', 'desc')->get();

    return view('admin.waitlist', compact('entries', 'stats'));
  }

  public function securitySettings(Request $request)
  {
    $tempSecret = null;
    $qrCodeUrl = null;

    if (!config('services.admin.2fa_enabled')) {
      // セキュリティ上の理由から、画面が開かれる（リロードされる）たびに常に新しいシークレットキーを新規生成する
      $tempSecret = TotpService::generateSecret();
      session(['admin_temp_2fa_secret' => $tempSecret]);
      
      $label = 'admin';
      $issuer = config('app.name', 'SkLoG') . ' Admin';
      $qrCodeUrl = TotpService::getQrCodeUrl($label, $tempSecret, $issuer);
    }

    return view('admin.security', compact('tempSecret', 'qrCodeUrl'));
  }

  public function verify2faSetup(Request $request)
  {
    $request->validate(['code' => 'required|string|size:6']);

    $tempSecret = session('admin_temp_2fa_secret');
    if (!$tempSecret) {
      return response()->json(['success' => false, 'message' => 'セッションの有効期限が切れました。ページを再読み込みしてください。']);
    }

    if (TotpService::verify($tempSecret, $request->code)) {
      return response()->json(['success' => true]);
    }

    return response()->json(['success' => false, 'message' => 'ワンタイムパスワードが正しくありません。']);
  }

  public function sendInvite(WaitlistEntry $entry)
  {
    if (!$entry->isPending()) {
      return back()->with('error', 'すでに招待済みまたは登録済みです。');
    }

    $code = strtoupper(implode('-', str_split(Str::random(12), 4)));

    $inviteCode = InviteCode::create([
      'code'              => $code,
      'handle'            => $entry->handle,
      'did'               => $entry->did,
      'waitlist_entry_id' => $entry->id,
      'expires_at'        => Carbon::now()->addHours(config('services.admin.invite_expiry_hours', 24)), // 設定された有効期限
    ]);

    $entry->update([
      'status'     => 'invited',
      'invited_at' => Carbon::now(),
    ]);

    Mail::to($entry->email)->send(new InviteCodeMail($entry, $inviteCode));

    return back()->with('success', "@{$entry->handle} に招待メールを送信しました。");
  }

  /**
   * 期限切れまたは再送リクエスト中のユーザーに対し、招待期限を延長（コード再生成）してメールを再送します。
   */
  public function reinvite(WaitlistEntry $entry)
  {
    if ($entry->status === 'registered') {
      return back()->with('error', 'すでに登録済みのユーザーです。');
    }

    // 既存の招待コードがあれば削除（失効）
    if ($entry->inviteCode) {
      $entry->inviteCode->delete();
    }

    $code = strtoupper(implode('-', str_split(Str::random(12), 4)));

    $inviteCode = InviteCode::create([
      'code'              => $code,
      'handle'            => $entry->handle,
      'did'               => $entry->did,
      'waitlist_entry_id' => $entry->id,
      'expires_at'        => Carbon::now()->addHours(config('services.admin.invite_expiry_hours', 24)), // 設定された有効期限で延長
    ]);

    $entry->update([
      'status'     => 'invited',
      'invited_at' => Carbon::now(),
    ]);

    Mail::to($entry->email)->send(new InviteCodeMail($entry, $inviteCode));

    return back()->with('success', "@{$entry->handle} の招待期限を延長し、新しい招待メールを再送信しました。");
  }
}
