<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * 同一ブラウザセッションでログイン中のアカウント（マルチアカウント）を管理します。
 *
 * セッションに積むのは「このセッションで実際に認証に成功したユーザーID」だけです。
 * アクティブなアカウントは常に Auth::id() で、スタックはその切り替え候補になります。
 */
class AccountSession
{
  /** ログイン中ユーザーIDを保持するセッションキー */
  public const SESSION_KEY = 'multi_account.user_ids';

  /** 1セッションで同時にログインできるアカウント数の上限 */
  public const MAX_ACCOUNTS = 5;

  /** 同一リクエスト内で User の再取得を避けるためのメモのコンテナキー */
  private const CACHE_KEY = 'multi_account.resolved_accounts';

  /**
   * ログイン中のユーザーID一覧を追加順で取得します。
   *
   * @return array<int, string>
   */
  public static function ids(): array
  {
    $ids = session(self::SESSION_KEY, []);
    $ids = is_array($ids) ? array_values(array_unique(array_filter($ids, 'is_string'))) : [];

    // remember me によるセッション復帰などでスタックが空の場合に、現在のユーザーで補完する
    $current = Auth::id();
    if ($current && ! in_array($current, $ids, true)) {
      $ids[] = $current;
      self::store($ids);
    }

    return $ids;
  }

  /**
   * 指定ユーザーがこのセッションでログイン済みかを判定します。
   */
  public static function has(string $userId): bool
  {
    return in_array($userId, self::ids(), true);
  }

  /**
   * これ以上アカウントを追加できない状態かを判定します。
   */
  public static function isFull(): bool
  {
    return count(self::ids()) >= self::MAX_ACCOUNTS;
  }

  /**
   * ログイン中アカウントとしてユーザーIDを追加します（重複時は何もしません）。
   */
  public static function add(string $userId): void
  {
    $ids = self::ids();
    if (in_array($userId, $ids, true)) {
      return;
    }

    $ids[] = $userId;
    self::store(array_slice($ids, -self::MAX_ACCOUNTS));
  }

  /**
   * ログイン中アカウントからユーザーIDを取り除きます。
   */
  public static function forget(string $userId): void
  {
    self::store(array_values(array_filter(self::ids(), fn ($id) => $id !== $userId)));
  }

  /**
   * ログイン中アカウントをすべて破棄します。
   */
  public static function clear(): void
  {
    self::flushCache();
    session()->forget(self::SESSION_KEY);
  }

  /**
   * ログイン中のアカウントを追加順の User コレクションで取得します。
   * 退会などで存在しなくなったIDはスタックからも取り除かれます。
   *
   * @return Collection<int, User>
   */
  public static function accounts(): Collection
  {
    if (app()->bound(self::CACHE_KEY)) {
      return app()->make(self::CACHE_KEY);
    }

    $ids = self::ids();
    if ($ids === []) {
      return self::cache(collect());
    }

    $users = User::with('blueskyAccount')->whereIn('id', $ids)->get()->keyBy('id');

    $accounts = collect($ids)
      ->map(fn ($id) => $users->get($id))
      ->filter(fn (?User $user) => $user !== null && $user->blueskyAccount !== null)
      ->values();

    // 存在しなくなったアカウントをスタックから掃除する
    $aliveIds = $accounts->pluck('id')->all();
    if (count($aliveIds) !== count($ids)) {
      self::store($aliveIds);
    }

    return self::cache($accounts);
  }

  /**
   * アクティブなアカウントを除いた、切り替え先アカウントを取得します。
   *
   * @return Collection<int, User>
   */
  public static function others(): Collection
  {
    $current = Auth::id();

    return self::accounts()->filter(fn (User $user) => $user->id !== $current)->values();
  }

  /**
   * 指定ユーザーへ切り替えます（アクティブなアカウントを差し替えます）。
   */
  public static function switchTo(User $user): void
  {
    Auth::login($user, true);
    self::add($user->id);
  }

  /**
   * アクティブなアカウントだけをログアウトし、残っていれば次のアカウントへ切り替えます。
   * 切り替え先が無い場合は null を返します（呼び出し側でセッション破棄まで行ってください）。
   */
  public static function logoutCurrentAndSwitchNext(): ?User
  {
    $current = Auth::id();
    if ($current) {
      self::forget($current);
    }

    Auth::logout();

    $next = self::accounts()->first();
    if (! $next) {
      return null;
    }

    self::switchTo($next);

    return $next;
  }

  /**
   * ユーザーID一覧をセッションへ保存します。
   *
   * @param  array<int, string>  $ids
   */
  private static function store(array $ids): void
  {
    self::flushCache();

    if ($ids === []) {
      session()->forget(self::SESSION_KEY);

      return;
    }

    session([self::SESSION_KEY => array_values($ids)]);
  }

  /**
   * 解決済みアカウントをリクエストスコープのコンテナへ保存します。
   *
   * @param  Collection<int, User>  $accounts
   * @return Collection<int, User>
   */
  private static function cache(Collection $accounts): Collection
  {
    app()->instance(self::CACHE_KEY, $accounts);

    return $accounts;
  }

  /**
   * 解決済みアカウントのメモを破棄します。
   */
  private static function flushCache(): void
  {
    if (app()->bound(self::CACHE_KEY)) {
      app()->forgetInstance(self::CACHE_KEY);
    }
  }
}
