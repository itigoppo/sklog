@extends('layouts.app')

@section('title', ($user->name ? $user->name . ' (' . $bskyAccount->handle . ')' : $bskyAccount->handle) . '/' . $targetDay->format('Y年m月d日') . ' - ' . config('app.name', 'SkLoG'))



@section('content')
  <!-- 2カラムレイアウト -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
    
    <!-- ================= 左カラム：投稿リスト ================= -->
    <div class="lg:col-span-7 xl:col-span-8 space-y-8">
      
      <!-- パンくずリスト & タイトル -->
      <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-slate-800 pb-3 mb-2 gap-2">
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
                <a href="{{ route('calendar.show.year', ['handle' => $bskyAccount->handle, 'year' => $targetDay->format('Y')]) }}" class="hover:text-sky-400 cursor-pointer transition ml-1">
                  {{ $targetDay->format('Y') }}年
                </a>
              </div>
            </li>
            <li>
              <div class="flex items-center">
                <x-icon name="chevron-right" class="w-2.5 h-2.5 text-slate-600 mx-1" stroke-width="2" />
                <a href="{{ route('calendar.show.month', ['handle' => $bskyAccount->handle, 'year' => $targetDay->format('Y'), 'month' => $targetDay->format('m')]) }}" class="hover:text-sky-400 cursor-pointer transition ml-1">
                  {{ $targetDay->format('n') }}月
                </a>
              </div>
            </li>
            <li aria-current="page">
              <div class="flex items-center">
                <x-icon name="chevron-right" class="w-2.5 h-2.5 text-slate-600 mx-1" stroke-width="2" />
                <span class="text-sky-400 ml-1 font-bold">{{ $targetDay->format('n') }}月{{ $targetDay->format('j') }}日のログ</span>
              </div>
            </li>
          </ol>
        </nav>
        
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

          <a href="{{ route('calendar.show', ['handle' => $bskyAccount->handle]) }}" class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white text-xs font-semibold transition flex items-center gap-1">
            すべてのログを表示 ➔
          </a>
        </div>
      </div>

      <!-- 投稿一覧 -->
      @if ($posts->isEmpty())
        <div class="glass-card rounded-2xl p-12 text-center">
          <h4 class="text-base font-bold text-slate-300 mb-1">この日はまだログがありません</h4>
          <p class="text-xs text-slate-500">Blueskyでの新規投稿がある場合は、手動で同期してください。</p>
        </div>
      @else
        @if (request('media') === '1')
          @include('calendar._gallery_grid')
          @include('calendar._gallery_modal')
        @else
          @php
            $postsList = $posts->values();

            // --- スレッド内古い順 (昇順), スレッド間新しい順 (降順) に並び替え ---
            $postsByUri = [];
            foreach ($postsList as $p) {
                if ($p->uri) {
                    $postsByUri[$p->uri] = $p;
                }
            }

            $childrenMap = [];
            $hasParentInList = [];
            foreach ($postsList as $p) {
                $parentUri = $p->is_self_thread ? ($p->raw_data['reply']['parent']['uri'] ?? null) : null;
                if ($parentUri && isset($postsByUri[$parentUri])) {
                    $childrenMap[$parentUri][] = $p;
                    $hasParentInList[$p->uri] = true;
                } else {
                    $hasParentInList[$p->uri] = false;
                }
            }

            $threads = [];
            foreach ($postsList as $p) {
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
                'isConnectedToPrev' => $isConnectedToPrev
              ])
            @endforeach
          </div>
        @endif
      @endif

    </div>

    @include('calendar._sidebar')

  </div>
@endsection
