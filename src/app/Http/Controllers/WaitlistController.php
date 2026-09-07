<?php

namespace App\Http\Controllers;

use App\Mail\WaitlistAppliedMail;
use App\Models\WaitlistEntry;
use App\Services\BlueskyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class WaitlistController extends Controller
{
  /**
   * 参加申請画面を表示します。
   *
   * ログイン中でも、別アカウントの参加申請ができるように表示します。
   */
  public function show()
  {
    return view('waitlist.apply', [
      'currentHandle' => Auth::user()?->blueskyAccount?->handle,
    ]);
  }

  public function store(Request $request, BlueskyService $blueskyService)
  {
    $request->validate([
      'email' => 'required|email',
      'handle' => 'required|string',
    ]);

    $handle = strtolower(ltrim(trim($request->handle), '@'));
    $email = strtolower(trim($request->email));

    // Blueskyアカウントの実在チェックおよびプロフィールの取得
    // 申請の同一性は不変のDIDで判定するため、重複チェックの前にプロフィールを引く
    $profile = [];
    try {
      $profile = $blueskyService->getProfile($handle);
    } catch (\Exception $e) {
      return back()->withErrors([
        'handle' => '入力されたハンドル名（@'.$handle.'）のBlueskyアカウントが見つかりません。スペルを確認してください。',
      ])->withInput();
    }

    $did = $profile['did'] ?? null;
    $attributes = [
      'handle' => $handle,
      'display_name' => $profile['displayName'] ?? null,
      'avatar_url' => $profile['avatar'] ?? null,
      'description' => $profile['description'] ?? null,
    ];

    // すでに申請済みのアカウントの場合、エラーにせず静かに成功を返す（重複登録や二重の管理者通知を防ぐため）
    // ハンドル名はBluesky側で変更でき、手放したハンドルは別アカウントが再利用できるため、不変のDIDで照合する
    $existing = $did ? WaitlistEntry::where('did', $did)->first() : null;

    // did移行前のレコードは did を持たないため、その場合だけハンドル名でフォールバック照合する
    $existing ??= WaitlistEntry::whereNull('did')->where('handle', $handle)->first();

    if ($existing) {
      // 申請後にハンドル名を変更していた場合に備えて表示情報を更新し、did移行前のレコードには did を補完する
      $existing->update($attributes + ($existing->did ? [] : ['did' => $did]));

      return redirect()->route('waitlist.completed')->with('handle', $handle);
    }

    $entry = WaitlistEntry::create($attributes + [
      'email' => $email,
      'did' => $did,
    ]);

    $adminEmail = config('services.admin.notification_email');
    if ($adminEmail) {
      Mail::to($adminEmail)->send(new WaitlistAppliedMail($entry));
    }

    return redirect()->route('waitlist.completed')->with('handle', $handle);
  }

  public function completed()
  {
    return view('waitlist.completed');
  }
}
