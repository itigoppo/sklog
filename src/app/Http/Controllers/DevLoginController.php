<?php

namespace App\Http\Controllers;

use App\Models\BlueskyAccount;
use App\Support\AccountSession;
use App\Support\LocalDev;

/**
 * ローカル開発用: Bluesky の認証なしで、DB にあるアカウントへログインします。
 *
 * ルートは LocalDev::loginEnabled() のときだけ登録しますが、念のためここでも確かめます。
 */
class DevLoginController extends Controller
{
  /**
   * ログインできるアカウントの一覧を表示します。
   */
  public function index()
  {
    abort_unless(LocalDev::loginEnabled(), 404);

    $accounts = BlueskyAccount::with('user')->orderBy('handle')->get();

    return view('auth.dev_login', compact('accounts'));
  }

  /**
   * 指定したハンドルのアカウントとしてログインします（同時ログインの一覧にも加えます）。
   */
  public function login(string $handle)
  {
    abort_unless(LocalDev::loginEnabled(), 404);

    $bskyAccount = BlueskyAccount::where('handle', ltrim(trim($handle), '@'))->firstOrFail();
    AccountSession::switchTo($bskyAccount->user, false);

    return redirect()->route('calendar.show', ['handle' => $bskyAccount->handle])
      ->with('success', '開発用ログインで @'.$bskyAccount->handle.' としてログインしました。');
  }
}
