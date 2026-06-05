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
  public function show()
  {
    if (Auth::check()) {
      return redirect()->route('calendar.show', ['handle' => Auth::user()->blueskyAccount->handle]);
    }

    return view('waitlist.apply');
  }

  public function store(Request $request, BlueskyService $blueskyService)
  {
    $request->validate([
      'email'  => 'required|email',
      'handle' => 'required|string',
    ]);

    $handle = strtolower(ltrim(trim($request->handle), '@'));
    $email  = strtolower(trim($request->email));

    // すでに申請済みのメールまたはハンドルの場合、エラーにせず静かに成功を返す（重複登録や二重の管理者通知を防ぐため）
    $alreadyExists = WaitlistEntry::where('email', $email)
      ->orWhere('handle', $handle)
      ->exists();

    if ($alreadyExists) {
      return redirect()->route('waitlist.completed')->with('handle', $handle);
    }

    // Blueskyアカウントの実在チェックおよびプロフィールの取得
    $profile = [];
    try {
      $profile = $blueskyService->getProfile($handle);
    } catch (\Exception $e) {
      return back()->withErrors([
        'handle' => '入力されたハンドル名（@' . $handle . '）のBlueskyアカウントが見つかりません。スペルを確認してください。'
      ])->withInput();
    }

    $entry = WaitlistEntry::create([
      'email'        => $email,
      'handle'       => $handle,
      'did'          => $profile['did'] ?? null,
      'display_name' => $profile['displayName'] ?? null,
      'avatar_url'   => $profile['avatar'] ?? null,
      'description'  => $profile['description'] ?? null,
    ]);

    $adminEmail = env('ADMIN_NOTIFICATION_EMAIL');
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
