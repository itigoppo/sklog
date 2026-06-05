@extends('layouts.app')

@section('title', $user->name . ' (@' . $bskyAccount->handle . ') のタグ一覧 | ' . config('app.name', 'SkLoG'))

@section('extra_css')
  <style>
    /* タグカードのプレミアムホバー効果 */
    .tag-card {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .tag-card:hover {
      transform: translateY(-3px) scale(1.02);
      border-color: rgba(56, 189, 248, 0.4);
      box-shadow: 0 10px 25px rgba(56, 189, 248, 0.08);
    }
  </style>
@endsection

@section('content')
  <!-- 2カラムレイアウト -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
    
    <!-- ================= 左カラム：タグ一覧 ================= -->
    <div class="lg:col-span-7 xl:col-span-8 space-y-8">
      
      <!-- パンくずリスト -->
      <nav class="flex text-slate-400 text-xs md:text-sm font-semibold border-b border-slate-800 pb-3 mb-2" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-2">
          <li class="inline-flex items-center">
            <a href="{{ route('calendar.show', ['handle' => $bskyAccount->handle]) }}" class="hover:text-sky-400 transition flex items-center gap-1">
              すべてのログ
            </a>
          </li>
          <li aria-current="page">
            <div class="flex items-center">
              <x-icon name="chevron-right" class="w-2.5 h-2.5 text-slate-600 mx-1" stroke-width="2" />
              <span class="text-sky-400 ml-1 font-bold">タグ一覧</span>
            </div>
          </li>
        </ol>
      </nav>

      <!-- メインコンテンツ -->
      <div class="space-y-6">
        <div class="flex items-center justify-between border-b border-slate-900/60 pb-3 mb-2 select-none">
          <h3 class="text-lg font-extrabold text-white tracking-wide flex items-center gap-2">
            <x-icon name="tag" class="h-5 w-5 text-sky-400" stroke-width="2" />
            <span>タグ一覧</span>
          </h3>
          <span class="text-xs text-slate-400 font-bold bg-slate-900 border border-slate-850 px-3 py-1 rounded-full">
            総計 {{ $tags->total() }} 種類のタグ
          </span>
        </div>

        @if ($tags->isEmpty())
          <div class="glass-card rounded-2xl p-12 text-center select-none">
            <h4 class="text-base font-bold text-slate-300 mb-1">タグがありません</h4>
            <p class="text-xs text-slate-500">投稿データにハッシュタグが含まれていると、自動的にここに抽出されます。</p>
          </div>
        @else
          <!-- タググリッド -->
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            @foreach ($tags as $tag)
              <a href="{{ route('calendar.show', ['handle' => $bskyAccount->handle, 'q' => '#' . $tag->name]) }}"
                 class="tag-card glass-card rounded-2xl p-4 border border-slate-800/80 flex flex-col justify-between gap-3 shadow-md hover:border-sky-500/20 active:scale-[0.98]">
                
                <div class="flex items-start gap-1 text-slate-200 group-hover:text-sky-400 transition select-none">
                  <span class="text-slate-500 font-extrabold text-sm mt-0.5 font-mono">#</span>
                  <span class="text-sm font-extrabold break-all tracking-wide leading-snug">
                    {{ $tag->name }}
                  </span>
                </div>

                <div class="flex items-center justify-between select-none">
                  <span class="px-2 py-0.5 rounded-lg bg-slate-950/80 text-[10px] text-sky-400 border border-slate-900/60 font-bold">
                    {{ $tag->count }} 件
                  </span>
                  <span class="text-[9px] text-slate-500 font-extrabold flex items-center gap-0.5">
                    見る ➔
                  </span>
                </div>
              </a>
            @endforeach
          </div>

          <!-- ページネーション -->
          <div class="mt-8">
            {{ $tags->links() }}
          </div>
        @endif
      </div>

    </div>

    <!-- ================= 右カラム：サイドバー ================= -->
    @include('calendar._sidebar', ['hasMoreTags' => false])

  </div>
@endsection
