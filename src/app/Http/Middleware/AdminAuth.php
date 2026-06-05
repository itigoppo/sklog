<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminAuth
{
  public function handle(Request $request, Closure $next)
  {
    if (!session('admin_authenticated')) {
      return redirect()->route('admin.login');
    }

    // セキュリティ設定画面および検証API以外のルートに対してのみ、2FAセッションチェックを適用する
    $currentRoute = $request->route() ? $request->route()->getName() : null;
    $bypassRoutes = ['admin.security', 'admin.security.verify'];

    if (!in_array($currentRoute, $bypassRoutes)) {
      // 2FAが有効化されているのに、セッションが2FA未認証（パスワードのみの古いセッション）の場合は強制ログアウト
      if (config('services.admin.2fa_enabled') && !session('admin_2fa_verified')) {
        $request->session()->forget(['admin_authenticated', 'admin_2fa_verified']);
        return redirect()->route('admin.login')->withErrors(['password' => 'セキュリティ設定が更新されました。再度ログインしてください。']);
      }
    }

    return $next($request);
  }
}
