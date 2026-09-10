<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * 同一ブラウザセッションでログイン中のアカウント（マルチアカウント）を管理します。
 *
 * セッションに積むのは「このセッションで実際に認証に成功したユーザーID」だけです。
 * アクティブなアカウントは常に Auth::id() で、スタックはその切り替え候補になります。
 *
 * 並び順は「直近に使った順」で、先頭が最も直近です。切り替えパネルの表示順、上限時に外す
 * 候補の並び、そしてアクティブなアカウントをログアウトしたときの遷移先が、すべてこの順に
 * 従います（ログアウトすると、パネルの一番上のアカウントへ移ります）。
 *
 * ログイン状態を保持する選択はアカウントごとに持ちます。保持を選んだアカウントは、スタックを
 * cookie にも書き出します。セッションは
 * SESSION_LIFETIME で切れるため、これが無いと remember me で復帰した朝には切り替え候補が
 * アクティブな1件に減ってしまいます。cookie にはユーザーIDと remember_token の組を入れて
 * おき、復元時に token の一致を確かめます。これで Laravel の remember me と強度が揃い、
 * 他端末でログアウトされたアカウント（token が振り直されたアカウント）は候補から落ちます。
 */
class AccountSession
{
  /** ログイン中ユーザーIDを保持するセッションキー */
  public const SESSION_KEY = 'multi_account.user_ids';

  /** このセッションでログイン状態の保持（remember me）を選んだユーザーIDを持つセッションキー */
  public const REMEMBERED_KEY = 'multi_account.remembered_ids';

  /** 切り替え候補をセッションより長く持たせる cookie の名前 */
  public const COOKIE_NAME = 'multi_account';

  /** 切り替え候補 cookie の寿命（分）。SessionGuard の remember me と同じ長さに揃える */
  public const COOKIE_MINUTES = 576000;

  /** 1セッションで同時にログインできるアカウント数の上限 */
  public const MAX_ACCOUNTS = 5;

  /** 同一リクエスト内で User の再取得を避けるためのメモのコンテナキー */
  private const CACHE_KEY = 'multi_account.resolved_accounts';

  /**
   * ログイン中のユーザーID一覧を、直近に使った順（先頭が最も直近）で取得します。
   *
   * @return array<int, string>
   */
  public static function ids(): array
  {
    $ids = session(self::SESSION_KEY, []);
    $ids = is_array($ids) ? array_values(array_unique(array_filter($ids, 'is_string'))) : [];

    // セッションが切れて remember me で復帰した場合に、切り替え候補を cookie から取り戻す
    if ($ids === []) {
      $ids = self::idsFromCookie();
      if ($ids !== []) {
        self::store($ids);
      }
    }

    // それでもスタックが空の場合に、現在のユーザーで補完する
    $current = Auth::id();
    if ($current && ! in_array($current, $ids, true)) {
      array_unshift($ids, $current);
      self::store($ids);
    }

    return $ids;
  }

  /**
   * 指定アカウントが、このセッションでログイン状態を保持する選択をしているかを判定します。
   *
   * ログイン時のチェックボックスで決まり、切り替えやログアウト後の引き継ぎは
   * そのアカウント自身の選択を踏襲します。
   */
  public static function isRemembered(string $userId): bool
  {
    return in_array($userId, self::rememberedIds(), true);
  }

  /**
   * ログイン状態の保持を選んだユーザーID一覧を返します。
   *
   * @return array<int, string>
   */
  private static function rememberedIds(): array
  {
    $ids = session(self::REMEMBERED_KEY, []);
    $ids = is_array($ids) ? array_values(array_unique(array_filter($ids, 'is_string'))) : [];

    // recaller cookie だけでセッションが復帰した場合に、そのアカウントの選択を取り戻す
    $current = Auth::id();
    if ($current && Auth::viaRemember() && ! in_array($current, $ids, true)) {
      $ids[] = $current;
      session([self::REMEMBERED_KEY => $ids]);
    }

    return $ids;
  }

  /**
   * 指定アカウントの「ログイン状態を保持する」選択を記録します。
   */
  private static function setRemembered(string $userId, bool $remember): void
  {
    $ids = array_values(array_filter(self::rememberedIds(), fn ($id) => $id !== $userId));
    if ($remember) {
      $ids[] = $userId;
    }

    session([self::REMEMBERED_KEY => $ids]);
  }

  /**
   * 指定したユーザーIDを、保持を選んだアカウントとして記録します（cookie からの復元用）。
   *
   * @param  array<int, string>  $userIds
   */
  private static function rememberIds(array $userIds): void
  {
    session([self::REMEMBERED_KEY => array_values(array_unique(array_merge(self::rememberedIds(), $userIds)))]);
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
   * ログイン中アカウントとしてユーザーIDを先頭（最も直近に使った位置）へ置きます。
   *
   * すでにある場合も先頭へ繰り上げて保存し直します。並び順を「直近に使った順」に保つほか、
   * remember me の選択や remember_token の変化を cookie へ反映する必要があるためです。
   * 上限を超えた分は、最も長く使っていないアカウントから外れます。
   */
  public static function add(string $userId): void
  {
    $ids = array_values(array_filter(self::ids(), fn ($id) => $id !== $userId));
    array_unshift($ids, $userId);

    self::store(array_slice($ids, 0, self::MAX_ACCOUNTS));
  }

  /**
   * ログイン中アカウントからユーザーIDを取り除きます。
   */
  public static function forget(string $userId): void
  {
    self::setRemembered($userId, false);
    self::store(array_values(array_filter(self::ids(), fn ($id) => $id !== $userId)));
  }

  /**
   * ログイン中アカウントをすべて破棄します。
   */
  public static function clear(): void
  {
    self::flushCache();
    session()->forget([self::SESSION_KEY, self::REMEMBERED_KEY]);
    self::forgetCookie();
  }

  /**
   * ログイン中のアカウントを、直近に使った順の User コレクションで取得します。
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
   *
   * @param  bool|null  $remember  ログイン状態を保持するか。null ならそのアカウント自身の直近の選択を踏襲する
   */
  public static function switchTo(User $user, ?bool $remember = null): void
  {
    $remember ??= self::isRemembered($user->id);

    self::setRemembered($user->id, $remember);
    Auth::login($user, $remember);
    self::add($user->id);
  }

  /**
   * アクティブなアカウントだけをログアウトし、残っていれば直近に使っていたアカウントへ
   * 切り替えます。
   * 切り替え先が無い場合は null を返します（呼び出し側でセッション破棄まで行ってください）。
   */
  public static function logoutCurrentAndSwitchNext(): ?User
  {
    $current = Auth::id();
    if ($current) {
      self::forget($current);
    }

    Auth::logout();

    // 先頭が最も直近に使ったアカウント
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
    self::writeCookie($ids);

    if ($ids === []) {
      session()->forget(self::SESSION_KEY);

      return;
    }

    session([self::SESSION_KEY => array_values($ids)]);
  }

  /**
   * 切り替え候補を cookie へ書き出します。ログイン状態を保持しない選択のときは消します。
   *
   * @param  array<int, string>  $ids
   */
  private static function writeCookie(array $ids): void
  {
    // 保持を選んだアカウントだけを書き出す。remember_token の有無で判断すると、
    // 過去に別の端末で保持ONにしたことがあるだけのアカウントまで残ってしまう
    $ids = array_values(array_filter($ids, fn ($id) => self::isRemembered($id)));

    if ($ids === []) {
      self::forgetCookie();

      return;
    }

    $tokens = User::whereIn('id', $ids)->pluck('remember_token', 'id');

    $entries = [];
    foreach ($ids as $id) {
      $token = $tokens->get($id);
      // remember_token を持たないアカウントは、そもそもこの端末に記憶されていない
      if (is_string($token) && $token !== '') {
        $entries[] = ['id' => $id, 'token' => $token];
      }
    }

    if ($entries === []) {
      self::forgetCookie();

      return;
    }

    Cookie::queue(self::COOKIE_NAME, json_encode($entries), self::COOKIE_MINUTES);
  }

  /**
   * cookie に保存された切り替え候補のうち、いま復元してよいものを取り出します。
   *
   * @return array<int, string>
   */
  private static function idsFromCookie(): array
  {
    // cookie 単体では何も与えない。ログインが成立しているセッションでのみ候補を戻す
    if (! Auth::check()) {
      return [];
    }

    $entries = json_decode((string) request()->cookie(self::COOKIE_NAME), true);
    if (! is_array($entries)) {
      return [];
    }

    $tokens = [];
    foreach ($entries as $entry) {
      if (is_array($entry) && is_string($entry['id'] ?? null) && is_string($entry['token'] ?? null)) {
        $tokens[$entry['id']] = $entry['token'];
      }
    }

    if ($tokens === []) {
      return [];
    }

    $stored = User::whereIn('id', array_keys($tokens))->pluck('remember_token', 'id');

    // remember_token が一致するアカウントだけを戻す。他端末でログアウトされたものはここで落ちる
    $restored = array_values(array_filter(array_keys($tokens), function ($id) use ($tokens, $stored) {
      $token = $stored->get($id);

      return is_string($token) && $token !== '' && hash_equals($token, $tokens[$id]);
    }));

    // cookie に載っていたのは保持を選んだアカウントなので、その選択も復元する
    self::rememberIds($restored);

    return $restored;
  }

  /**
   * 切り替え候補の cookie を削除します。
   */
  private static function forgetCookie(): void
  {
    Cookie::queue(Cookie::forget(self::COOKIE_NAME));
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
