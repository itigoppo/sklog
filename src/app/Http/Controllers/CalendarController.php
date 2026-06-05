<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\BlueskyAccount;
use App\Jobs\SyncBlueskyPostsJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Exception;

class CalendarController extends Controller
{
  /**
   * メインブログ画面を表示します (全期間の最新順ページネーション)。
   */
  public function show(Request $request, string $handle)
  {
    $handle = ltrim(trim($handle), '@');

    // 該当のBlueskyアカウントが存在するかチェック
    $bskyAccount = BlueskyAccount::where('handle', $handle)->first();
    if (!$bskyAccount) {
      abort(404, 'ユーザーが見つかりません。');
    }

    $user = $bskyAccount->user;

    // 非公開アカウントの認可チェック
    if ($bskyAccount->is_private) {
      if (!Auth::check() || Auth::id() !== $user->id) {
        abort(404);
      }
    }

    // 所有者本人、またはデモ用サンプルアカウントの判定
    $isOwner = Auth::check() && Auth::id() === $user->id;
    $isDemo = $bskyAccount->handle === config('services.bluesky.demo_handle');
    $isOwnerOrDemo = $isOwner || $isDemo;

    // 検索条件の取得とクエリの準備（所有者本人またはデモアカウントのみ有効）
    $q = null;
    $searchMode = 'and';
    $includeRt = false;

    if ($isOwnerOrDemo) {
      if ($request->has('q') && trim($request->query('q', '')) === '') {
        return redirect()->route('calendar.show', ['handle' => $handle]);
      }

      $q = $request->query('q');
      $searchMode = $request->query('mode', 'and');
      if ($searchMode !== 'or') {
        $searchMode = 'and';
      }
      $includeRt = $request->query('rt') === '1';
    }

    $postsQuery = $user->posts();

    // 画像付きのみ絞り込み
    $onlyMedia = $request->query('media') === '1';
    if ($onlyMedia) {
      $postsQuery->hasImages();
    }

    // 検索キーワードのフィルタリング
    if (is_string($q) && trim($q) !== '') {
      // RTを含まないオプション（デフォルト）の場合はリポスト（他人の発言）を除外する
      if (!$includeRt) {
        $postsQuery->where('uri', 'not like', 'repost-%');
      }

      $keywords = collect(explode(' ', str_replace('　', ' ', trim($q))))
        ->filter(function ($value) {
          return $value !== '';
        })
        ->values();

      if ($keywords->isNotEmpty()) {
        $postsQuery->where(function ($query) use ($keywords, $searchMode) {
          foreach ($keywords as $index => $keyword) {
            // キーワードがハッシュタグ形式であるかを判定 (例: "#SkLog")
            $tagName = \App\Models\Tag::parseHashtag($keyword);
            $isHashtag = !is_null($tagName);

            if ($index === 0) {
              if ($isHashtag && $tagName) {
                // ハッシュタグの場合は、tags リレーションで厳密に絞り込む
                $query->whereHas('tags', function ($q) use ($tagName) {
                  $q->whereRaw('lower(name) = ?', [strtolower($tagName)]);
                });
              } else {
                // 通常の単語の場合は、LIKE で部分一致検索
                $query->where('text', 'like', '%' . $keyword . '%');
              }
            } else {
              if ($searchMode === 'or') {
                if ($isHashtag && $tagName) {
                  $query->orWhereHas('tags', function ($q) use ($tagName) {
                    $q->whereRaw('lower(name) = ?', [strtolower($tagName)]);
                  });
                } else {
                  $query->orWhere('text', 'like', '%' . $keyword . '%');
                }
              } else {
                if ($isHashtag && $tagName) {
                  $query->whereHas('tags', function ($q) use ($tagName) {
                    $q->whereRaw('lower(name) = ?', [strtolower($tagName)]);
                  });
                } else {
                  $query->where('text', 'like', '%' . $keyword . '%');
                }
              }
            }
          }
        });
      }
    }

    // 投稿を最新順でページネーション取得 (検索パラメータを引き継ぐ)
    $perPage = $onlyMedia ? 24 : 15;
    $posts = $postsQuery
      ->orderBy('posted_at', 'desc')
      ->paginate($perPage)
      ->withQueryString();

    // サイドバーのカレンダー用には「今月」を表示する
    $currentMonth = Carbon::now()->startOfMonth();
    $startOfMonth = $currentMonth->copy()->startOfMonth();
    $endOfMonth = $currentMonth->copy()->endOfMonth();

    // カレンダー用の「今月」の全投稿を取得
    $allMonthPosts = $user->posts()
      ->whereBetween('posted_at', [$startOfMonth, $endOfMonth])
      ->get();

    $daysInMonth = $currentMonth->daysInMonth;
    $firstDayOfWeek = $startOfMonth->dayOfWeek;

    $postsByDay = $allMonthPosts->groupBy(function ($post) {
      return $post->posted_at->day;
    });

    $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
    $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

    // 月ごとアーカイブのリストを取得（10件制限、もっと見るリンク判定のため11件取得）
    $allMonthArchives = $user->posts()
      ->selectRaw("substr(posted_at, 1, 7) as month, count(*) as count")
      ->groupBy('month')
      ->orderBy('month', 'desc')
      ->limit(11)
      ->get();

    $hasMoreArchives = $allMonthArchives->count() > 10;
    $monthlyArchives = $hasMoreArchives ? $allMonthArchives->take(10) : $allMonthArchives;

    // 初回同期が必要かどうかの判定（投稿が0件かつ同期履歴がない）
    $isFirstSyncNeeded = ($user->posts()->count() === 0 && !$bskyAccount->last_synced_at);

    // ページのタイトル表示用
    $title = 'すべてのログ';
    $isMainPage = true;

    return view('calendar.show', compact(
      'user',
      'bskyAccount',
      'posts',
      'postsByDay',
      'currentMonth',
      'daysInMonth',
      'firstDayOfWeek',
      'prevMonth',
      'nextMonth',
      'title',
      'isMainPage',
      'monthlyArchives',
      'hasMoreArchives',
      'q',
      'searchMode',
      'includeRt',
      'isFirstSyncNeeded',
      'onlyMedia'
    ));

  }

  /**
   * 月アーカイブ画面を表示します。
   */
  public function showMonth(Request $request, string $handle, string $year, string $month)
  {
    $handle = ltrim(trim($handle), '@');

    $bskyAccount = BlueskyAccount::where('handle', $handle)->first();
    if (!$bskyAccount) {
      abort(404, 'ユーザーが見つかりません。');
    }

    $user = $bskyAccount->user;

    // 所有者本人またはデモ用サンプルアカウント以外による月アーカイブの閲覧は404で完全に拒否する
    $isOwner = Auth::check() && Auth::id() === $user->id;
    $isDemo = $bskyAccount->handle === config('services.bluesky.demo_handle');
    $isOwnerOrDemo = $isOwner || $isDemo;
    if (!$isOwnerOrDemo) {
      abort(404);
    }

    try {
      $currentMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
    } catch (Exception $e) {
      $currentMonth = Carbon::now()->startOfMonth();
    }

    $startOfMonth = $currentMonth->copy()->startOfMonth();
    $endOfMonth = $currentMonth->copy()->endOfMonth();

    $postsQuery = $user->posts()
      ->whereBetween('posted_at', [$startOfMonth, $endOfMonth]);

    $onlyMedia = $request->query('media') === '1';
    if ($onlyMedia) {
      $postsQuery->hasImages();
    }

    // 指定年月の投稿を最新順でページネーション取得
    $perPage = $onlyMedia ? 24 : 15;
    $posts = $postsQuery
      ->orderBy('posted_at', 'desc')
      ->paginate($perPage)
      ->withQueryString();

    // カレンダーのドット表示用には指定年月の全投稿を取得
    $allMonthPosts = $user->posts()
      ->whereBetween('posted_at', [$startOfMonth, $endOfMonth])
      ->get();

    $daysInMonth = $currentMonth->daysInMonth;
    $firstDayOfWeek = $startOfMonth->dayOfWeek;

    $postsByDay = $allMonthPosts->groupBy(function ($post) {
      return $post->posted_at->day;
    });

    $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
    $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

    // 月ごとアーカイブのリストを取得（10件制限、もっと見るリンク判定のため11件取得）
    $allMonthArchives = $user->posts()
      ->selectRaw("substr(posted_at, 1, 7) as month, count(*) as count")
      ->groupBy('month')
      ->orderBy('month', 'desc')
      ->limit(11)
      ->get();

    $hasMoreArchives = $allMonthArchives->count() > 10;
    $monthlyArchives = $hasMoreArchives ? $allMonthArchives->take(10) : $allMonthArchives;

    $title = $currentMonth->format('Y年m月のログ');
    $isMainPage = false;

    return view('calendar.show', compact(
      'user',
      'bskyAccount',
      'posts',
      'postsByDay',
      'currentMonth',
      'daysInMonth',
      'firstDayOfWeek',
      'prevMonth',
      'nextMonth',
      'title',
      'isMainPage',
      'monthlyArchives',
      'hasMoreArchives',
      'onlyMedia'
    ));

  }

  /**
   * 日アーカイブ画面を表示します。
   */
  public function showDay(Request $request, string $handle, string $year, string $month, string $day)
  {
    $handle = ltrim(trim($handle), '@');

    $bskyAccount = BlueskyAccount::where('handle', $handle)->first();
    if (!$bskyAccount) {
      abort(404, 'ユーザーが見つかりません。');
    }

    $user = $bskyAccount->user;

    // 所有者本人またはデモ用サンプルアカウント以外による日アーカイブの閲覧は404で完全に拒否する
    $isOwner = Auth::check() && Auth::id() === $user->id;
    $isDemo = $bskyAccount->handle === config('services.bluesky.demo_handle');
    $isOwnerOrDemo = $isOwner || $isDemo;
    if (!$isOwnerOrDemo) {
      abort(404);
    }

    try {
      $targetDay = Carbon::createFromDate($year, $month, $day)->startOfDay();
    } catch (Exception $e) {
      $targetDay = Carbon::now()->startOfDay();
    }

    $postsQuery = $user->posts()
      ->whereBetween('posted_at', [$targetDay->copy()->startOfDay(), $targetDay->copy()->endOfDay()]);

    $onlyMedia = $request->query('media') === '1';
    if ($onlyMedia) {
      $postsQuery->hasImages();
    }

    // 指定日24時間の投稿を最新順（降順）で取得
    $posts = $postsQuery
      ->orderBy('posted_at', 'desc')
      ->get();

    $prevDay = $targetDay->copy()->subDay();
    $nextDay = $targetDay->copy()->addDay();

    // 右カラムのミニカレンダー用データ（指定日の属する月）
    $currentMonth = $targetDay->copy()->startOfMonth();
    $startOfMonth = $currentMonth->copy()->startOfMonth();
    $endOfMonth = $currentMonth->copy()->endOfMonth();

    // カレンダーのドット表示・背景画像判定用
    $allMonthPosts = $user->posts()
      ->whereBetween('posted_at', [$startOfMonth, $endOfMonth])
      ->get();

    $daysInMonth = $currentMonth->daysInMonth;
    $firstDayOfWeek = $startOfMonth->dayOfWeek;

    $postsByDay = $allMonthPosts->groupBy(function ($post) {
      return $post->posted_at->day;
    });

    $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
    $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

    // 月ごとアーカイブのリストを取得（10件制限、もっと見るリンク判定のため11件取得）
    $allMonthArchives = $user->posts()
      ->selectRaw("substr(posted_at, 1, 7) as month, count(*) as count")
      ->groupBy('month')
      ->orderBy('month', 'desc')
      ->limit(11)
      ->get();

    $hasMoreArchives = $allMonthArchives->count() > 10;
    $monthlyArchives = $hasMoreArchives ? $allMonthArchives->take(10) : $allMonthArchives;

    return view('calendar.show_day', compact(
      'user',
      'bskyAccount',
      'posts',
      'targetDay',
      'prevDay',
      'nextDay',
      'currentMonth',
      'daysInMonth',
      'firstDayOfWeek',
      'postsByDay',
      'prevMonth',
      'nextMonth',
      'monthlyArchives',
      'hasMoreArchives',
      'onlyMedia'
    ));

  }

  /**
   * 年アーカイブ画面を表示します。
   */
  public function showYear(Request $request, string $handle, string $year)
  {
    $handle = ltrim(trim($handle), '@');

    $bskyAccount = BlueskyAccount::where('handle', $handle)->first();
    if (!$bskyAccount) {
      abort(404, 'ユーザーが見つかりません。');
    }

    $user = $bskyAccount->user;

    // 所有者本人またはデモ用サンプルアカウント以外による年アーカイブの閲覧は404で完全に拒否する
    $isOwner = Auth::check() && Auth::id() === $user->id;
    $isDemo = $bskyAccount->handle === config('services.bluesky.demo_handle');
    $isOwnerOrDemo = $isOwner || $isDemo;
    if (!$isOwnerOrDemo) {
      abort(404);
    }

    $targetYear = (int)$year;
    $startOfYear = Carbon::createFromDate($targetYear, 1, 1)->startOfYear();
    $endOfYear = Carbon::createFromDate($targetYear, 12, 31)->endOfYear();

    // 1年間の全投稿を取得（投稿順）
    $yearPosts = $user->posts()
      ->whereBetween('posted_at', [$startOfYear, $endOfYear])
      ->orderBy('posted_at', 'asc')
      ->get();

    // 1〜12月のデフォルトを0埋めした配列を作成
    $monthlyCounts = array_fill(1, 12, 0);
    $monthlyImages = array_fill(1, 12, null);

    foreach ($yearPosts as $post) {
      $m = $post->posted_at->month;
      $monthlyCounts[$m]++;
      
      if ($monthlyImages[$m]) {
        continue;
      }
      
      $embed = $post->raw_data['post']['embed'] ?? null;
      if ($embed && str_contains($embed['$type'] ?? '', 'app.bsky.embed.images')) {
        $monthlyImages[$m] = $embed['images'][0]['thumb'] ?? null;
      }
    }

    $prevYear = $targetYear - 1;
    $nextYear = $targetYear + 1;
    $totalYearPosts = array_sum($monthlyCounts);

    return view('calendar.show_year', compact(
      'user',
      'bskyAccount',
      'targetYear',
      'monthlyCounts',
      'monthlyImages',
      'prevYear',
      'nextYear',
      'totalYearPosts'
    ));
  }

  public function sync(Request $request)
  {
    $user = Auth::user();
    $bskyAccount = $user->blueskyAccount;

    // 二重起動防止：現在すでに同期処理が実行中の場合はブロック
    if ($bskyAccount && $bskyAccount->is_syncing) {
      return back()->with('error', '現在、自動同期または手動同期が実行中です。完了するまでお待ちください。');
    }

    // レート制限のチェック（15分間に5回まで）
    $userId = Auth::id();
    $limiterKey = 'sync-user:' . $userId;

    if (RateLimiter::tooManyAttempts($limiterKey, 5)) {
      $seconds = RateLimiter::availableIn($limiterKey);
      $minutes = ceil($seconds / 60);
      return back()->with('error', "API制限保護のため、手動同期の回数上限を超えました。あと {$minutes} 分お待ちください。");
    }

    try {
      SyncBlueskyPostsJob::dispatchSync($user);

      // 同期完了時に統計キャッシュをクリア
      \Illuminate\Support\Facades\Cache::forget('user-stats-summary:' . $user->id);
      
      // 同期成功時にレート制限のヒットを記録（15分間 = 900秒）
      RateLimiter::hit($limiterKey, 900);
      
      if ($bskyAccount) {
        $bskyAccount->refresh();
        if ($bskyAccount->is_archive_complete) {
          return back()->with('success', 'Blueskyから最新の投稿を同期しました！（過去ログはすべて同期済みです）');
        } else {
          return back()->with('success', '最新の投稿を同期し、過去ログの続き（100件）を取り込みました。さらに過去ログを取り込むには、もう一度同期を実行してください。');
        }
      }

      return back()->with('success', 'Blueskyから最新の投稿を同期しました！');
    } catch (Exception $e) {
      return back()->with('error', '同期中にエラーが発生しました: ' . $e->getMessage());
    }
  }

  /**
   * Ajax用：指定年月のカレンダー部分のみのHTMLを取得します。
   */
  public function getCalendarHtml(Request $request, string $handle, string $year, string $month)
  {
    $handle = ltrim(trim($handle), '@');
    $bskyAccount = BlueskyAccount::where('handle', $handle)->first();
    if (!$bskyAccount) {
      return response()->json(['error' => 'User not found'], 404);
    }
    $user = $bskyAccount->user;

    $isOwner = Auth::check() && Auth::id() === $user->id;
    $isDemo = $bskyAccount->handle === config('services.bluesky.demo_handle');
    $isOwnerOrDemo = $isOwner || $isDemo;
    if (!$isOwnerOrDemo) {
      return response()->json(['error' => 'Unauthorized'], 403);
    }

    try {
      $currentMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
    } catch (Exception $e) {
      $currentMonth = Carbon::now()->startOfMonth();
    }

    $startOfMonth = $currentMonth->copy()->startOfMonth();
    $endOfMonth = $currentMonth->copy()->endOfMonth();

    // カレンダーのドット表示用には指定年月の全投稿を取得
    $allMonthPosts = $user->posts()
      ->whereBetween('posted_at', [$startOfMonth, $endOfMonth])
      ->get();

    $daysInMonth = $currentMonth->daysInMonth;
    $firstDayOfWeek = $startOfMonth->dayOfWeek;

    $postsByDay = $allMonthPosts->groupBy(function ($post) {
      return $post->posted_at->day;
    });

    $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
    $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');

    return view('calendar._calendar', compact(
      'user',
      'bskyAccount',
      'currentMonth',
      'daysInMonth',
      'firstDayOfWeek',
      'prevMonth',
      'nextMonth',
      'postsByDay'
    ))->render();
  }

  /**
   * 全アーカイブ一覧ページを表示します。
   */
  public function showArchives(Request $request, string $handle)
  {
    $handle = ltrim(trim($handle), '@');
    $bskyAccount = BlueskyAccount::where('handle', $handle)->first();
    if (!$bskyAccount) {
      abort(404, 'ユーザーが見つかりません。');
    }
    $user = $bskyAccount->user;

    // 所有者本人またはデモ用サンプルアカウント以外によるアーカイブ一覧の閲覧は404で完全に拒否する
    $isOwner = Auth::check() && Auth::id() === $user->id;
    $isDemo = $bskyAccount->handle === config('services.bluesky.demo_handle');
    $isOwnerOrDemo = $isOwner || $isDemo;
    if (!$isOwnerOrDemo) {
      abort(404);
    }

    // 全期間の月アーカイブを取得
    $allArchives = $user->posts()
      ->selectRaw("substr(posted_at, 1, 7) as month, count(*) as count")
      ->groupBy('month')
      ->orderBy('month', 'desc')
      ->get();

    // 各月の代表画像を取得
    $monthlyImages = [];
    foreach ($allArchives as $archive) {
      $startOfMonth = Carbon::parse($archive->month . '-01')->startOfMonth();
      $endOfMonth = Carbon::parse($archive->month . '-01')->endOfMonth();
      
      $imagePost = $user->posts()
        ->whereBetween('posted_at', [$startOfMonth, $endOfMonth])
        ->where('raw_data', 'like', '%app.bsky.embed.images%')
        ->orderBy('posted_at', 'asc')
        ->first();
      
      if ($imagePost) {
        $embed = $imagePost->raw_data['post']['embed'] ?? null;
        if ($embed && str_contains($embed['$type'] ?? '', 'app.bsky.embed.images')) {
          $monthlyImages[$archive->month] = $embed['images'][0]['thumb'] ?? null;
        }
      } else {
        $monthlyImages[$archive->month] = null;
      }
    }

    // 年ごとにグループ化
    $archivesByYear = $allArchives->groupBy(function ($archive) {
      return substr($archive->month, 0, 4); // 年を取得
    });

    return view('calendar.archives', compact('user', 'bskyAccount', 'archivesByYear', 'monthlyImages'));
  }

  /**
   * 全タグ一覧ページを表示します (投稿件数順ページネーション)。
   */
  public function showTags(Request $request, string $handle)
  {
    $handle = ltrim(trim($handle), '@');
    $bskyAccount = BlueskyAccount::where('handle', $handle)->first();
    if (!$bskyAccount) {
      abort(404, 'ユーザーが見つかりません。');
    }
    $user = $bskyAccount->user;

    // 所有者本人またはデモ用サンプルアカウント以外によるタグ一覧の閲覧は404で完全に拒否する
    $isOwner = Auth::check() && Auth::id() === $user->id;
    $isDemo = $bskyAccount->handle === config('services.bluesky.demo_handle');
    $isOwnerOrDemo = $isOwner || $isDemo;
    if (!$isOwnerOrDemo) {
      abort(404);
    }

    // 全ハッシュタグを投稿件数の降順で取得してページネーション (1ページあたり30件)
    $tags = \App\Models\Tag::join('post_tags', 'tags.id', '=', 'post_tags.tag_id')
      ->join('posts', 'post_tags.post_id', '=', 'posts.id')
      ->where('posts.user_id', $user->id)
      ->whereNull('posts.deleted_at')
      ->selectRaw('tags.name, count(*) as count')
      ->groupBy('tags.id', 'tags.name')
      ->orderBy('count', 'desc')
      ->paginate(30);

    return view('calendar.tags', compact('user', 'bskyAccount', 'tags'));
  }

  /**
   * ポストをローカルで削除（論理削除）します。
   */
  public function destroy(string $handle, \App\Models\Post $post)
  {
    $user = Auth::user();
    $bskyAccount = $user->blueskyAccount;

    // 削除対象のポストがログインユーザー本人のものであるかを厳密にチェック
    if ($post->user_id !== $user->id || !$bskyAccount || ltrim(trim($handle), '@') !== $bskyAccount->handle) {
      abort(403, 'この操作は許可されていません。');
    }

    $post->delete();

    // キャッシュをクリアして統計やタグ一覧を再計算させる
    \Illuminate\Support\Facades\Cache::forget('user-tags:' . $user->id);
    \Illuminate\Support\Facades\Cache::forget('user-stats-summary:' . $user->id);

    return back()
      ->with('success', '投稿をカレンダーから削除しました（※Bluesky側の投稿は削除されません）。')
      ->with('deleted_post_id', $post->id);
  }

  /**
   * ソフトデリートされたポストを復元します。
   */
  public function restore(string $handle, string $postId)
  {
    $user = Auth::user();
    $bskyAccount = $user->blueskyAccount;

    if (!$bskyAccount || ltrim(trim($handle), '@') !== $bskyAccount->handle) {
      abort(403, 'この操作は許可されていません。');
    }

    // ソフトデリートされたものを含めて検索
    $post = \App\Models\Post::withTrashed()->findOrFail($postId);

    if ($post->user_id !== $user->id) {
      abort(403, 'この操作は許可されていません。');
    }

    $post->restore();

    // キャッシュをクリアして統計やタグ一覧を再計算させる
    \Illuminate\Support\Facades\Cache::forget('user-tags:' . $user->id);
    \Illuminate\Support\Facades\Cache::forget('user-stats-summary:' . $user->id);

    return back()->with('success', '投稿の削除を取り消しました。');
  }

  /**
   * 設定画面を表示します。
   */
  public function showSettings(string $handle)
  {
    $handle = ltrim(trim($handle), '@');
    $bskyAccount = \App\Models\BlueskyAccount::where('handle', $handle)->first();
    if (!$bskyAccount) {
      abort(404, 'ユーザーが見つかりません。');
    }
    $user = $bskyAccount->user;

    // 本人確認
    if (!Auth::check() || Auth::id() !== $user->id) {
      abort(403, 'この操作は許可されていません。');
    }

    return view('calendar.settings', compact('user', 'bskyAccount'));
  }

  /**
   * 複数のポストURLから個別インポートまたは復元を行います。
   */
  public function importPosts(Request $request, string $handle)
  {
    $user = Auth::user();
    $bskyAccount = $user->blueskyAccount;

    if (!$bskyAccount || ltrim(trim($handle), '@') !== $bskyAccount->handle) {
      abort(403, 'この操作は許可されていません。');
    }

    // レート制限のチェック（15分間に5回まで）
    $userId = Auth::id();
    $limiterKey = 'import-user:' . $userId;

    if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($limiterKey, 5)) {
      $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($limiterKey);
      $minutes = ceil($seconds / 60);
      return back()->with('error', "API制限保護のため、個別インポートの回数上限を超えました。あと {$minutes} 分お待ちください。");
    }

    $request->validate([
      'urls' => 'required|string',
    ]);

    // 各行に分割し、空白を除去
    $urls = preg_split('/\r\n|\r|\n/', $request->input('urls', ''));
    $atUris = [];
    $restoredCount = 0;
    $successCount = 0;
    $failedCount = 0;

    foreach ($urls as $url) {
      $url = trim($url);
      if (empty($url)) {
        continue;
      }

      // ウェブURL形式: https://bsky.app/profile/{handle_or_did}/post/{rkey}
      if (preg_match('#/profile/([^/]+)/post/([^/]+)#', $url, $matches)) {
        $handleOrDid = $matches[1];
        $rkey = $matches[2];

        // 本人の投稿であることを検証（ハンドル名、またはDIDの完全一致）
        if ($handleOrDid !== $bskyAccount->handle && $handleOrDid !== $bskyAccount->did) {
          $failedCount++;
          continue;
        }

        $atUris[] = "at://{$bskyAccount->did}/app.bsky.feed.post/{$rkey}";
      }
      // AT-URI形式: at://{did_or_handle}/app.bsky.feed.post/{rkey}
      elseif (preg_match('#^at://([^/]+)/app.bsky.feed.post/([^/]+)$#', $url, $matches)) {
        $didOrHandle = $matches[1];
        $rkey = $matches[2];

        if ($didOrHandle !== $bskyAccount->handle && $didOrHandle !== $bskyAccount->did) {
          $failedCount++;
          continue;
        }

        $atUris[] = "at://{$bskyAccount->did}/app.bsky.feed.post/{$rkey}";
      }
      else {
        $failedCount++;
      }
    }

    $toImport = [];
    foreach ($atUris as $atUri) {
      // ソフトデリートされたものも含めて検索
      $existingPost = \App\Models\Post::withTrashed()->where('uri', $atUri)->first();
      if ($existingPost) {
        if ($existingPost->trashed()) {
          $existingPost->restore();
          $restoredCount++;
        } else {
          // すでに有効な状態で存在する
          $successCount++;
        }
      } else {
        $toImport[] = $atUri;
      }
    }

    if (!empty($toImport)) {
      try {
        $blueskyService = app(\App\Services\BlueskyService::class);
        $password = $bskyAccount->encrypted_app_password;
        $session = $blueskyService->authenticate($bskyAccount->handle, $password);
        $token = $session['accessJwt'];

        // app.bsky.feed.getPosts API を叩く
        $response = $blueskyService->getPosts($toImport, $token);
        $posts = $response['posts'] ?? [];
        $fetchedUris = [];

        foreach ($posts as $postData) {
          $uri = $postData['uri'];
          $cid = $postData['cid'];
          $record = $postData['record'] ?? [];
          $text = $record['text'] ?? '';
          $createdAtString = $record['createdAt'] ?? null;

          if (!$createdAtString) {
            $failedCount++;
            continue;
          }

          $postedAt = \Illuminate\Support\Carbon::parse($createdAtString)->timezone('Asia/Tokyo');

          // インポートデータを保存
          $post = \App\Models\Post::updateOrCreate(
            ['uri' => $uri],
            [
              'user_id' => $user->id,
              'cid' => $cid,
              'text' => $text,
              'raw_data' => ['post' => $postData], // SyncJobの保存構造と統一
              'posted_at' => $postedAt,
            ]
          );

          // ハッシュタグの抽出と紐付け
          $tagIds = [];
          foreach (\App\Models\Tag::extractTags($text) as $tagName) {
            $tag = \App\Models\Tag::firstOrCreate(['name' => $tagName]);
            $tagIds[] = $tag->id;
          }
          $post->tags()->sync($tagIds);

          $fetchedUris[] = $uri;
          $successCount++;
        }

        // 返ってこなかったURIを失敗としてカウント
        $failedCount += (count($toImport) - count($fetchedUris));

      } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error("ImportPosts Exception: " . $e->getMessage());
        $failedCount += count($toImport);
      }

      // APIを呼び出した場合にのみレート制限のヒットを記録（15分間 = 900秒）
      \Illuminate\Support\Facades\RateLimiter::hit($limiterKey, 900);
    }

    if ($restoredCount > 0 || $successCount > 0) {
      \Illuminate\Support\Facades\Cache::forget('user-tags:' . $user->id);
      \Illuminate\Support\Facades\Cache::forget('user-stats-summary:' . $user->id);
    }

    // 処理結果のメッセージ生成
    $msgParts = [];
    if ($successCount > 0) {
      $msgParts[] = "新規インポート: {$successCount}件";
    }
    if ($restoredCount > 0) {
      $msgParts[] = "非表示からの復元: {$restoredCount}件";
    }
    if ($failedCount > 0) {
      $msgParts[] = "失敗（または対象外）: {$failedCount}件";
    }

    $message = "インポート処理が完了しました。(" . implode(', ', $msgParts) . ")";

    return back()->with('success', $message);
  }

  /**
   * クエリパラメータを安全にマージ・削除してURLを構築する。
   * 末尾に「?」だけが残るのを防止する。
   *
   * @param array $paramsToMerge
   * @return string
   */
  public static function buildUrl(array $paramsToMerge = []): string
  {
    $queryParams = request()->query();
    foreach ($paramsToMerge as $key => $value) {
      if (is_null($value)) {
        unset($queryParams[$key]);
      } else {
        $queryParams[$key] = (string) $value;
      }
    }
    // 空のパラメータを除外
    $queryParams = array_filter($queryParams, function ($val) {
      return !is_null($val) && $val !== '';
    });
    
    $queryString = http_build_query($queryParams);
    return request()->url() . ($queryString !== '' ? '?' . $queryString : '');
  }

  /**
   * ログインしたままアプリパスワードを更新し、同期エラーを復旧します。
   */
  public function updatePassword(\Illuminate\Http\Request $request, string $handle)
  {
    $request->validate([
      'app_password' => 'required|string',
    ]);

    /** @var \App\Models\User $user */
    $user = \Illuminate\Support\Facades\Auth::user();
    $bskyAccount = $user->blueskyAccount;

    // 認可チェック: handleが一致していること
    if (!$bskyAccount || strtolower($bskyAccount->handle) !== strtolower($handle)) {
      abort(403);
    }

    try {
      // BlueskyServiceインスタンスを取得
      $blueskyService = app(\App\Services\BlueskyService::class);
      
      // 新しいパスワードで認証ができるかテスト
      $blueskyService->authenticate($bskyAccount->handle, $request->app_password);
      
      // 認証成功したため、パスワード更新およびエラーのリセット
      $bskyAccount->update([
        'encrypted_app_password' => $request->app_password,
        'sync_error_type' => null,
        'sync_error_message' => null,
        'auth_failure_count' => 0,
        'is_auto_sync_enabled' => true,
      ]);

      return back()->with('success', 'アプリパスワードを正常に更新し、自動同期を再有効化しました。');

    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error("Password Update Error for user {$user->id}: " . $e->getMessage());
      return back()->withErrors([
        'app_password' => '入力されたアプリパスワードでのBluesky認証に失敗しました。正しいパスワードを入力してください。',
      ]);
    }
  }

  /**
   * カレンダーアカウントおよびデータをすべて物理削除し、退会します。
   */
  public function deleteAccount(\Illuminate\Http\Request $request, string $handle)
  {
    /** @var \App\Models\User $user */
    $user = \Illuminate\Support\Facades\Auth::user();
    $bskyAccount = $user->blueskyAccount;

    // 認可チェック
    if (!$bskyAccount || strtolower($bskyAccount->handle) !== strtolower($handle)) {
      abort(403);
    }

    try {
      \Illuminate\Support\Facades\DB::transaction(function () use ($user) {
        // CascadeOnDeleteにより、Userに関連するBlueskyAccount、Post、中間テーブルなどは自動で物理削除されます
        $user->delete();
      });

      \Illuminate\Support\Facades\Auth::logout();
      $request->session()->invalidate();
      $request->session()->regenerateToken();

      return redirect('/login')->with('success', 'アカウントとすべてのカレンダーデータを完全に削除し、退会しました。');

    } catch (\Exception $e) {
      \Illuminate\Support\Facades\Log::error("Delete Account Error for user {$user->id}: " . $e->getMessage());
      return back()->with('error', '退会処理中にエラーが発生しました。お手数ですがしばらく時間をおいてから再度お試しください。');
    }
  }
}
