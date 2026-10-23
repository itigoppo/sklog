<?php

namespace App\Support;

/**
 * ローカル開発用の仕組み（開発用ログイン・偽の Bluesky）を有効にしてよいかを判定します。
 *
 * どちらも認証を素通りする仕組みなので、APP_ENV=local かつ .env のフラグがオンのときだけ有効にします。
 */
class LocalDev
{
  /**
   * /dev/login から Bluesky の認証なしでログインできるか。
   */
  public static function loginEnabled(): bool
  {
    return app()->environment('local') && config('app.dev_login') === true;
  }

  /**
   * BlueskyService を偽物 (FakeBlueskyService) に差し替えるか。
   */
  public static function fakeBlueskyEnabled(): bool
  {
    return app()->environment('local') && config('services.bluesky.fake') === true;
  }
}
