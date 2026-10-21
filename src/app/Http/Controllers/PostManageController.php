<?php

namespace App\Http\Controllers;

use App\Models\BlueskyAccount;
use App\Models\Post;
use App\Models\User;
use App\Services\BlueskyService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 削除モード（投稿の一括削除・リポストの一括取り消し）を扱います。
 */
class PostManageController extends Controller
{
  /**
   * 1ページに並べる投稿の件数（スレッドの続きは別に加わります）。
   */
  private const PER_PAGE = 50;

  /**
   * 一度に削除できる件数の上限。
   */
  private const MAX_SELECTION = 500;

  /**
   * 削除モードの一覧を表示します。
   *
   * 元の画面の絞り込み条件（期間・検索語・画像のみ）をクエリで引き継ぎます。
   */
  public function index(Request $request, string $handle)
  {
    [$user, $bskyAccount] = $this->resolveOwner($handle, 404);

    $tab = $request->query('tab') === 'reposts' ? 'reposts' : 'posts';
    $period = $this->parsePeriod($request->query('period'));
    $q = trim((string) $request->query('q', ''));
    $searchMode = $request->query('mode') === 'or' ? 'or' : 'and';
    $onlyMedia = $request->query('media') === '1';

    $postsQuery = $user->posts();
    if ($tab === 'reposts') {
      $postsQuery->where('uri', 'like', 'repost-%');
    } else {
      $postsQuery->where('uri', 'not like', 'repost-%');
    }

    if ($period) {
      $postsQuery->whereBetween('posted_at', [$period['start'], $period['end']]);
    }
    if ($onlyMedia) {
      $postsQuery->hasImages();
    }
    if ($q !== '') {
      $postsQuery->searchKeywords($q, $searchMode);
    }

    $posts = $postsQuery
      ->orderBy('posted_at', 'desc')
      ->paginate(self::PER_PAGE)
      ->withQueryString();

    // 投稿はスレッドごとのかたまりに、リポストは1件ずつのかたまりにする
    if ($tab === 'posts') {
      $groups = Post::threadGroups($posts->getCollection());
    } else {
      $groups = $posts->getCollection()
        ->map(fn (Post $post) => [
          'start' => $post,
          'rows' => [['post' => $post, 'parent_uri' => null, 'depth' => 0]],
        ])
        ->all();
    }

    // Bluesky へのリンクを組み立てるときに、行ごとに user を引き直さないようにする
    foreach ($groups as $group) {
      foreach ($group['rows'] as $row) {
        $row['post']->setRelation('user', $user);
      }
    }

    // かたまりを起点の投稿日ごとに見出しで区切る
    $groupsByDay = collect($groups)->groupBy(fn (array $group) => $group['start']->posted_at->format('Y-m-d'));

    $filters = array_filter([
      'period' => $period['raw'] ?? null,
      'q' => $q !== '' ? $q : null,
      'mode' => $q !== '' && $searchMode === 'or' ? 'or' : null,
      'media' => $onlyMedia ? '1' : null,
    ]);

    return view('calendar.manage', [
      'user' => $user,
      'bskyAccount' => $bskyAccount,
      'tab' => $tab,
      'posts' => $posts,
      'groupsByDay' => $groupsByDay,
      'period' => $period,
      'q' => $q,
      'searchMode' => $searchMode,
      'onlyMedia' => $onlyMedia,
      'filters' => $filters,
      'backUrl' => $this->backUrl($bskyAccount, $period, $filters),
    ]);
  }

  /**
   * 選んだ投稿・リポストをまとめて削除します。
   *
   * also_bluesky が無ければカレンダーから非表示にするだけ（論理削除・Undo可）。
   * ある場合は Bluesky 側も消し（リポストは取り消し）、カレンダーからも物理削除します。
   */
  public function destroy(Request $request, string $handle)
  {
    [$user, $bskyAccount] = $this->resolveOwner($handle, 403);

    $ids = $this->requestedIds($request);
    if (empty($ids)) {
      return back()->with('error', '削除する投稿を選んでください。');
    }
    if (count($ids) > self::MAX_SELECTION) {
      return back()->with('error', '一度に削除できるのは'.self::MAX_SELECTION.'件までです。');
    }

    $posts = $user->posts()->whereIn('id', $ids)->get();
    if ($posts->isEmpty()) {
      return back()->with('error', '削除する投稿が見つかりませんでした。');
    }

    if (! $request->boolean('also_bluesky')) {
      Post::whereIn('id', $posts->pluck('id'))->delete(); // 論理削除 (ソフトデリート)
      $this->forgetUserCaches($user);

      return back()
        ->with('success', $this->describe($posts).'をカレンダーから非表示にしました（※Bluesky側はそのままです）。')
        ->with('deleted_post_ids', $posts->pluck('id')->all());
    }

    [$doneIds, $error] = $this->removeFromBluesky($bskyAccount, $posts);

    if (! empty($doneIds)) {
      Post::whereIn('id', $doneIds)->forceDelete(); // 物理削除
      $this->forgetUserCaches($user);
    }

    $done = $posts->whereIn('id', $doneIds);
    $redirect = back();

    if ($done->isNotEmpty()) {
      $redirect = $redirect->with('success', $this->describe($done).'をBlueskyとカレンダーから削除しました。');
    }

    if ($error) {
      $left = $posts->count() - $done->count();
      $redirect = $redirect->with('error', $error.($done->isNotEmpty() ? "（{$left}件は削除せずに残しています）" : ''));
    }

    return $redirect;
  }

  /**
   * カレンダーから非表示にした投稿をまとめて元に戻します。
   */
  public function restore(Request $request, string $handle)
  {
    [$user] = $this->resolveOwner($handle, 403);

    $ids = $this->requestedIds($request);
    $restored = empty($ids) ? 0 : $user->posts()->onlyTrashed()->whereIn('id', $ids)->restore();

    if ($restored === 0) {
      return back()->with('error', '元に戻せる投稿が見つかりませんでした。');
    }

    $this->forgetUserCaches($user);

    return back()->with('success', '投稿の削除を取り消しました。');
  }

  /**
   * ログイン中の本人が、自分のハンドルの画面を操作しているかを確かめます。
   *
   * @return array{0: User, 1: BlueskyAccount}
   */
  private function resolveOwner(string $handle, int $status): array
  {
    $user = Auth::user();
    $bskyAccount = $user->blueskyAccount;

    if (! $bskyAccount || ltrim(trim($handle), '@') !== $bskyAccount->handle) {
      abort($status, 'この操作は許可されていません。');
    }

    return [$user, $bskyAccount];
  }

  /**
   * リクエストの post_ids[] を、文字列の id の配列にして返します。
   *
   * @return array<int, string>
   */
  private function requestedIds(Request $request): array
  {
    $ids = $request->input('post_ids', []);
    if (! is_array($ids)) {
      return [];
    }

    return array_values(array_unique(array_filter($ids, fn ($id) => is_string($id) && $id !== '')));
  }

  /**
   * 選んだ投稿を Bluesky 側から消します（リポストは取り消します）。
   *
   * すでに Bluesky に無いものは消せたものとして扱います。
   * 途中で失敗した場合は、それまでに消せたものだけを返します。
   *
   * @param  Collection<int, Post>  $posts
   * @return array{0: array<int, string>, 1: ?string} [Bluesky から消えた投稿の id, エラーメッセージ]
   */
  private function removeFromBluesky(BlueskyAccount $bskyAccount, Collection $posts): array
  {
    $blueskyService = app(BlueskyService::class);

    try {
      $session = $blueskyService->authenticate($bskyAccount->handle, $bskyAccount->encrypted_app_password);
      $token = $session['accessJwt'];
      $did = $session['did'] ?? $bskyAccount->did;

      // 投稿は自身の uri、リポストは元投稿の uri で、Bluesky 上の今の状態を引く
      $lookupUris = $posts
        ->map(fn (Post $post) => $post->is_repost ? ($post->raw_data['post']['uri'] ?? null) : $post->uri)
        ->filter(fn ($uri) => is_string($uri) && str_starts_with($uri, 'at://'))
        ->unique()
        ->values();

      $views = [];
      foreach ($lookupUris->chunk(BlueskyService::MAX_GET_POSTS) as $chunk) {
        $response = $blueskyService->getPosts($chunk->values()->all(), $token);
        foreach ($response['posts'] ?? [] as $view) {
          if (isset($view['uri'])) {
            $views[$view['uri']] = $view;
          }
        }
      }
    } catch (Exception $e) {
      Log::error('Bulk delete Bluesky lookup failed: '.$e->getMessage());

      return [[], 'Blueskyとの通信に失敗したため、削除処理を中断しました。しばらく時間をおいてから再度お試しください。'];
    }

    $doneIds = [];
    $pending = [];
    foreach ($posts as $post) {
      if ($post->is_repost) {
        // 取り消すのは自分のリポストのレコード。getPosts の viewer.repost に今の uri が入っている
        $originalUri = $post->raw_data['post']['uri'] ?? null;
        $recordUri = $views[$originalUri]['viewer']['repost'] ?? null;
        $collection = 'app.bsky.feed.repost';
      } else {
        $recordUri = isset($views[$post->uri]) ? $post->uri : null;
        $collection = 'app.bsky.feed.post';
      }

      if (! $recordUri) {
        // Bluesky 上にもう無い（元投稿が消えた・リポストを取り消し済み）
        $doneIds[] = $post->id;

        continue;
      }

      $record = $this->parseRecordUri($recordUri, $did, $collection);
      if ($record) {
        $pending[$post->id] = $record;
      }
      // 自分のレコードとして解釈できないものは、Bluesky 側を消せないので残す
    }

    foreach (array_chunk($pending, BlueskyService::MAX_WRITES, true) as $chunk) {
      try {
        $blueskyService->deleteRecords($did, array_values($chunk), $token);
      } catch (Exception $e) {
        Log::error('Bulk delete applyWrites failed: '.$e->getMessage());

        return [$doneIds, 'Blueskyでの削除に失敗しました。しばらく時間をおいてから再度お試しください。'];
      }
      $doneIds = array_merge($doneIds, array_keys($chunk));
    }

    if (count($doneIds) < $posts->count()) {
      return [$doneIds, 'Bluesky側で削除できない投稿がありました。'];
    }

    return [$doneIds, null];
  }

  /**
   * at://{did}/{collection}/{rkey} を分解します。自分の did と collection に一致しなければ null。
   *
   * @return array{collection: string, rkey: string}|null
   */
  private function parseRecordUri(string $uri, string $did, string $collection): ?array
  {
    if (! preg_match('#^at://([^/]+)/([^/]+)/([^/]+)$#', $uri, $matches)) {
      return null;
    }
    if ($matches[1] !== $did || $matches[2] !== $collection) {
      return null;
    }

    return ['collection' => $collection, 'rkey' => $matches[3]];
  }

  /**
   * 「3件の投稿と1件のリポスト」のように、件数を種類ごとに言い表します。
   *
   * @param  Collection<int, Post>  $posts
   */
  private function describe(Collection $posts): string
  {
    $reposts = $posts->filter(fn (Post $post) => $post->is_repost)->count();
    $originals = $posts->count() - $reposts;

    return collect([
      $originals > 0 ? "{$originals}件の投稿" : null,
      $reposts > 0 ? "{$reposts}件のリポスト" : null,
    ])->filter()->implode('と');
  }

  /**
   * 期間の指定 (YYYY-MM または YYYY-MM-DD) を解釈します。
   *
   * @return array{raw: string, start: Carbon, end: Carbon, label: string, is_day: bool}|null
   */
  private function parsePeriod(mixed $period): ?array
  {
    if (! is_string($period)) {
      return null;
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $period, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
      $day = Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();

      return [
        'raw' => $period,
        'start' => $day,
        'end' => $day->copy()->endOfDay(),
        'label' => $day->format('Y年n月j日'),
        'is_day' => true,
      ];
    }

    if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m) && checkdate((int) $m[2], 1, (int) $m[1])) {
      $month = Carbon::createFromDate((int) $m[1], (int) $m[2], 1)->startOfMonth();

      return [
        'raw' => $period,
        'start' => $month,
        'end' => $month->copy()->endOfMonth(),
        'label' => $month->format('Y年n月'),
        'is_day' => false,
      ];
    }

    return null;
  }

  /**
   * 削除モードに入る前の画面の URL を、絞り込み条件から組み立てます。
   *
   * @param  array<string, string>  $filters
   */
  private function backUrl(BlueskyAccount $bskyAccount, ?array $period, array $filters): string
  {
    $handle = $bskyAccount->handle;
    $media = isset($filters['media']) ? ['media' => '1'] : [];

    if ($period && $period['is_day']) {
      return route('calendar.show.day', [
        'handle' => $handle,
        'year' => $period['start']->format('Y'),
        'month' => $period['start']->format('m'),
        'day' => $period['start']->format('d'),
      ] + $media);
    }

    if ($period) {
      return route('calendar.show.month', [
        'handle' => $handle,
        'year' => $period['start']->format('Y'),
        'month' => $period['start']->format('m'),
      ] + $media);
    }

    return route('calendar.show', ['handle' => $handle] + array_intersect_key($filters, array_flip(['q', 'mode', 'media'])));
  }

  /**
   * 統計やタグ一覧のキャッシュを消して、再計算させます。
   */
  private function forgetUserCaches(User $user): void
  {
    Cache::forget('user-tags:'.$user->id);
    Cache::forget('user-stats-summary:'.$user->id);
  }
}
