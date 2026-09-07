@extends('layouts.app')

@section('title', ($user->name ? $user->name . ' (' . $bskyAccount->handle . ')' : $bskyAccount->handle) . ($isMainPage ? '' : '/' . $currentMonth->format('Y年m月')) . ' - ' . config('app.name', 'SkLoG'))

@section('content')
  @php
    $isOwner = Auth::check() && Auth::id() === $user->id;
    $isDemo = $bskyAccount->handle === config('services.bluesky.demo_handle');
    $isOwnerOrDemo = $isOwner || $isDemo;
  @endphp

  <!-- ゲスト閲覧用ヘッダーバナー (ログイン/自分のカレンダーへの誘導) -->
  @if (!$isOwner)
    <div class="glass-card rounded-2xl p-4 md:p-5 border border-slate-800/80 bg-linear-to-r from-sky-500/5 to-indigo-500/5 mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
      <div class="grid grid-cols-[auto_1fr] gap-x-3.5 gap-y-2 flex-1 w-full">
        <!-- サービスロゴマーク -->
        <div class="w-8 h-8 rounded-lg bg-linear-to-br from-sky-400 to-indigo-600 flex items-center justify-center shadow-md shadow-sky-500/20 shrink-0">
          <x-icon name="logo" class="h-4.5 w-4.5 text-white" />
        </div>
        
        <!-- タイトル -->
        <h4 class="text-xs font-extrabold text-white tracking-wider leading-none flex items-center gap-1.5 self-center">
          <span>{{ config('app.name', 'SkLoG') }}</span>
          <span class="text-[9px] text-slate-500 font-bold uppercase tracking-widest px-1.5 py-0.5 rounded bg-slate-950 border border-slate-900 leading-none">Viewer</span>
        </h4>
        
        <!-- 説明文と注意書き (モバイルではロゴの下に全幅で回り込み、PCではロゴの右側に配置) -->
        <div class="col-span-2 sm:col-span-1 sm:col-start-2 text-left select-none">
          <p class="text-[10px] text-slate-400 font-medium leading-relaxed">
            @auth
              {{ $user->name ?: '@' . $bskyAccount->handle }} さんのログを閲覧中
            @else
              {{ $user->name ?: '@' . $bskyAccount->handle }} さんのログを閲覧中。あなたもBlueskyをカレンダーでアーカイブしてみませんか？
            @endauth
          </p>
          
          <div class="mt-2 flex items-start gap-1.5 text-[9px] text-slate-500 font-semibold select-none leading-relaxed">
            @if ($isDemo)
              <span class="text-sky-400 shrink-0">💡</span>
              <span>デモ体験ページのため、すべての機能（カレンダー・検索・統計など）が特別に解放されています。</span>
            @else
              <span class="shrink-0">🔒</span>
              <span>プライバシー保護のため、ご本人以外によるカレンダーや検索などの詳細機能は制限されています。</span>
            @endif
          </div>
        </div>
      </div>
      
      <div class="flex items-center gap-2 shrink-0 select-none w-full sm:w-auto mt-2 sm:mt-0">
        @auth
          <!-- すでにログインしている場合 -->
          @if ($authBskyAccount = Auth::user()->blueskyAccount)
            <a href="{{ route('calendar.show', ['handle' => $authBskyAccount->handle]) }}" class="w-full sm:w-auto px-4 py-2 bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 hover:border-sky-500/50 text-sky-400 font-bold text-xs rounded-xl transition duration-300 flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
              <span>マイカレンダーへ ➔</span>
            </a>
          @endif
        @else
          <!-- ゲスト -->
          <a href="{{ route('login') }}" class="w-full sm:w-auto px-4 py-2 bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 hover:border-sky-500/50 text-sky-400 font-bold text-xs rounded-xl transition duration-300 flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap">
            <span>自分も始める ➔</span>
          </a>
        @endauth
      </div>
    </div>
  @endif

  @if ($isOwnerOrDemo)
    <!-- 2カラムレイアウト (本人用のフル機能カレンダー表示) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      <div class="lg:col-span-7 xl:col-span-8 space-y-8">
  @else
    <!-- 1カラムレイアウト (他人用のスッキリした全件タイムライン表示 - プロフィール幅と完全に一致) -->
    <div class="max-w-6xl mx-auto space-y-8">
  @endif
      
      <!-- タイムラインのタイトル & 全期間戻るボタン -->
      <div class="flex flex-col md:flex-row md:items-start justify-between border-b border-slate-800 pb-3 mb-2 gap-2">
        @if (request('q'))
          @php
            $q = request('q');
            $keywords = collect(explode(' ', str_replace('　', ' ', trim($q))))->filter(fn($v) => $v !== '')->values();
            $totalKeywords = $keywords->count();
            $hashtagCount = $keywords->filter(function($kw) {
                return preg_match(\App\Models\Tag::STRICT_REGEX, $kw);
            })->count();
            
            $isStrictHashtagSearch = $totalKeywords > 0 && $hashtagCount === $totalKeywords;
            $isHybridSearch = $totalKeywords > 0 && $hashtagCount > 0 && $hashtagCount < $totalKeywords;
          @endphp
          <!-- 検索結果ヘッダー -->
          <div class="space-y-1">
            <h3 class="text-lg font-bold text-white tracking-wide flex items-center gap-2 select-none">
              <x-icon name="search" class="h-5 w-5 text-sky-400" stroke-width="2" />
              検索結果
            </h3>
            <p class="text-xs text-slate-400 flex items-center gap-1.5 flex-wrap">
              <span><span class="text-sky-400 font-bold">"{{ request('q') }}"</span> の検索結果 ({{ $posts->total() }}件)</span>
              <span class="px-1.5 py-0.5 rounded bg-slate-900 text-slate-500 border border-slate-800 text-[9px] font-bold uppercase tracking-wider select-none">{{ request('mode', 'and') === 'or' ? 'OR検索' : 'AND検索' }}</span>
              @if ($isStrictHashtagSearch)
                <span class="px-1.5 py-0.5 rounded bg-sky-500/10 text-sky-400 border border-sky-500/20 text-[9px] font-bold tracking-wider select-none flex items-center gap-1">
                  <span class="font-extrabold text-[10px] text-sky-400 leading-none">#</span>ハッシュタグ検索
                </span>
              @elseif ($isHybridSearch)
                <span class="px-1.5 py-0.5 rounded bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-[9px] font-bold tracking-wider select-none flex items-center gap-1">
                  <span class="font-extrabold text-[10px] text-indigo-400 leading-none">#</span>ハッシュタグ ＋ キーワード
                </span>
              @endif
            </p>
          </div>
        @elseif ($isMainPage)
          <h3 class="text-lg font-bold text-white tracking-wide flex items-center gap-2">
            <x-icon name="list" class="h-5 w-5 text-sky-400" stroke-width="2" />
            すべてのログ
          </h3>
        @else
          <!-- 月ページ用のパンくずリスト -->
          <nav class="flex text-slate-400 text-xs md:text-sm font-semibold" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-2">
              <li class="inline-flex items-center">
                <a href="{{ route('calendar.show', ['handle' => $bskyAccount->handle]) }}" class="hover:text-sky-400 transition flex items-center gap-1">
                  すべてのログ
                </a>
              </li>
              <li>
                <div class="flex items-center">
                  <x-icon name="chevron-right" class="w-2.5 h-2.5 text-slate-600 mx-1" stroke-width="2" />
                  <a href="{{ route('calendar.show.year', ['handle' => $bskyAccount->handle, 'year' => $currentMonth->format('Y')]) }}" class="hover:text-sky-400 transition ml-1">
                    {{ $currentMonth->format('Y') }}年
                  </a>
                </div>
              </li>
              <li aria-current="page">
                <div class="flex items-center">
                  <x-icon name="chevron-right" class="w-2.5 h-2.5 text-slate-600 mx-1" stroke-width="2" />
                  <span class="text-sky-400 ml-1 font-bold">{{ $currentMonth->format('n') }}月のログ</span>
                </div>
              </li>
            </ol>
          </nav>
        @endif

        <div class="flex items-center gap-2">
          <!-- 「画像のみ」トグルボタン -->
          @php
            $isMediaFiltered = request('media') === '1';
            $mediaToggleUrl = \App\Http\Controllers\CalendarController::buildUrl([
                'media' => $isMediaFiltered ? null : '1',
                'page' => null
            ]);
          @endphp
          <a href="{{ $mediaToggleUrl }}" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition duration-300 flex items-center gap-1.5 cursor-pointer active:scale-95 {{ $isMediaFiltered ? 'bg-sky-500/10 border-sky-500/30 text-sky-400 hover:bg-sky-500/20' : 'bg-slate-900/40 border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white' }}" title="画像付きポストのみ表示">
            <x-icon name="image" class="h-3.5 w-3.5" />
            <span>画像のみ</span>
          </a>

          @if(request('q'))
            <!-- 検索解除ボタン -->
            @php
              $clearUrl = \App\Http\Controllers\CalendarController::buildUrl([
                  'q' => null,
                  'mode' => null,
                  'page' => null
              ]);
            @endphp
            <a href="{{ $clearUrl }}" class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-rose-500/30 hover:bg-rose-500/10 text-slate-400 hover:text-rose-400 font-semibold text-xs transition duration-300 flex items-center gap-1 cursor-pointer">
              <x-icon name="close" class="h-3.5 w-3.5" stroke-width="2" />
              検索クリア
            </a>
          @elseif(!$isMainPage)
            <a href="{{ route('calendar.show', ['handle' => $bskyAccount->handle]) }}" class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white text-xs font-semibold transition flex items-center gap-1">
              すべてのログを表示 ➔
            </a>
          @endif
        </div>
      </div>

      @if ($posts->isEmpty())
        <div class="glass-card rounded-2xl p-12 text-center">
          @if (request('q'))
            <h4 class="text-base font-bold text-slate-300 mb-1">検索条件にマッチする投稿が見つかりませんでした</h4>
            <p class="text-xs text-slate-500">別のキーワードや、AND / OR条件を変更して再度お試しください。</p>
          @else
            <h4 class="text-base font-bold text-slate-300 mb-1">この期間はまだログがありません</h4>
            <p class="text-xs text-slate-500">Blueskyでの新規投稿がある場合は、手動で同期してください。</p>
          @endif
        </div>
      @else
        @if (request('media') === '1')
          @include('calendar._gallery_grid')
          @include('calendar._gallery_modal')
        @else
          @php
            // 一括でスレッドルートの投稿を取得してインメモリキャッシュ（N+1問題の防止）
            $rootUris = $posts->getCollection()
                ->map(function ($p) {
                    return $p->is_self_thread ? ($p->raw_data['reply']['root']['uri'] ?? null) : null;
                })
                ->filter()
                ->unique()
                ->values();

            $rootPosts = \App\Models\Post::whereIn('uri', $rootUris)->get()->keyBy('uri');

            // 連投の通し番号 (1/n) をページ単位でまとめて算出する
            $threadNumbers = \App\Models\Post::threadNumbers($posts->getCollection());

            // 現在のページの投稿を、スレッド開始元の投稿日（なければ自身の投稿日）でグループ化
            $postsGroupedByDay = $posts->getCollection()->groupBy(function($post) use ($rootPosts) {
                $rootUri = $post->is_self_thread ? ($post->raw_data['reply']['root']['uri'] ?? null) : null;
                if ($rootUri && isset($rootPosts[$rootUri])) {
                    return $rootPosts[$rootUri]->posted_at->format('Y-m-d');
                }
                return $post->posted_at->format('Y-m-d');
            });
          @endphp

          <div class="space-y-10">
            @foreach ($postsGroupedByDay as $dateStr => $dayPosts)
              @php
                $postedDate = \Illuminate\Support\Carbon::parse($dateStr);
                $dayPostsList = $dayPosts->values();

                // --- スレッド内古い順 (昇順), スレッド間新しい順 (降順) に並び替え ---
                $postsByUri = [];
                foreach ($dayPostsList as $p) {
                    if ($p->uri) {
                        $postsByUri[$p->uri] = $p;
                    }
                }

                $childrenMap = [];
                $hasParentInList = [];
                foreach ($dayPostsList as $p) {
                    $parentUri = $p->is_self_thread ? ($p->raw_data['reply']['parent']['uri'] ?? null) : null;
                    if ($parentUri && isset($postsByUri[$parentUri])) {
                        $childrenMap[$parentUri][] = $p;
                        $hasParentInList[$p->uri] = true;
                    } else {
                        $hasParentInList[$p->uri] = false;
                    }
                }

                $threads = [];
                foreach ($dayPostsList as $p) {
                    if (!$hasParentInList[$p->uri]) {
                        $threadGroup = [];
                        $current = $p;
                        while ($current) {
                            $threadGroup[] = $current;
                            $children = $childrenMap[$current->uri] ?? [];
                            $current = !empty($children) ? end($children) : null;
                        }
                        $threads[] = [
                            'posts' => $threadGroup,
                            'start_time' => $threadGroup[0]->posted_at
                        ];
                    }
                }

                usort($threads, function($a, $b) {
                    return $b['start_time']->timestamp <=> $a['start_time']->timestamp;
                });

                $sortedPosts = [];
                foreach ($threads as $t) {
                    foreach ($t['posts'] as $p) {
                        $sortedPosts[] = $p;
                    }
                }
              @endphp
              <div class="space-y-4">
                
                <!-- 日付ヘッダー -->
                <h4 class="text-sm font-bold text-sky-400 border-b border-slate-900/60 pb-1.5 mb-1.5">
                  <a href="{{ route('calendar.show.day', ['handle' => $bskyAccount->handle, 'year' => $postedDate->format('Y'), 'month' => $postedDate->format('m'), 'day' => $postedDate->format('d')]) }}" class="flex items-center gap-2 cursor-pointer hover:text-sky-300 transition duration-300 w-fit">
                    <x-icon name="calendar" class="h-4 w-4" stroke-width="2" />
                    <span>{{ $postedDate->isoFormat('YYYY年MM月DD日 (dd)') }}</span>
                  </a>
                </h4>

                <!-- その日の投稿一覧 -->
                @foreach ($sortedPosts as $index => $post)
                  @php
                    $nextPost = $sortedPosts[$index + 1] ?? null;
                    $prevPost = $sortedPosts[$index - 1] ?? null;
                    
                    $isConnectedToNext = false;
                    $isConnectedToPrev = false;
                    
                    if ($nextPost && $nextPost->is_self_thread && ($nextPost->raw_data['reply']['parent']['uri'] ?? null) === $post->uri) {
                        $isConnectedToNext = true;
                    }
                    
                    if ($prevPost && $post->is_self_thread && ($post->raw_data['reply']['parent']['uri'] ?? null) === $prevPost->uri) {
                        $isConnectedToPrev = true;
                    }
                  @endphp
                  @include('calendar._post_card', [
                    'post' => $post,
                    'isConnectedToNext' => $isConnectedToNext,
                    'isConnectedToPrev' => $isConnectedToPrev,
                    'threadNumber' => $threadNumbers[$post->id] ?? null
                  ])
                @endforeach
              </div>
            @endforeach
          </div>
        @endif

        <!-- Laravel標準 ページネーションリンク -->
        @if ($posts->hasPages())
          <div class="mt-12 bg-slate-900/40 p-4 rounded-2xl border border-slate-800/80">
            {{ $posts->onEachSide(1)->links() }}
          </div>
        @endif
      @endif
    </div>

    @if ($isOwnerOrDemo)
      @include('calendar._sidebar')
    </div>
    @endif

  @if (isset($isFirstSyncNeeded) && $isFirstSyncNeeded)
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        // ローディングオーバーレイを即時表示
        const overlay = document.getElementById('loadingOverlay');
        const title = document.getElementById('loadingTitle');
        const subtitle = document.getElementById('loadingSubtitle');
        
        if (overlay) {
          if (title) title.textContent = '初回データを同期中...';
          if (subtitle) subtitle.textContent = '初回のみ、すべての過去ログを収集するため数十秒かかる場合があります。しばらくお待ちください。';
          
          overlay.classList.remove('hidden');
          overlay.classList.add('flex');
        }
        
        // 同期フォームを自動送信
        const syncForm = document.getElementById('syncForm');
        if (syncForm) {
          // レンダリングをスムーズにするため、少しディレイを置いて自動送信
          setTimeout(() => {
            syncForm.submit();
          }, 500);
        }
      });
    </script>
  @endif
@endsection
