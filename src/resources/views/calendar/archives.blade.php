@extends('layouts.app')

@section('title', $user->name . ' (@' . $bskyAccount->handle . ') の月別アーカイブ一覧 | ' . config('app.name', 'SkLoG'))

@section('extra_css')
  <style>
    html {
      scroll-behavior: smooth;
    }
    .year-section {
      scroll-margin-top: 1.5rem;
    }
    /* 月アーカイブカードのプレミアムホバー効果 */
    .archive-month-card {
      aspect-ratio: 16 / 9;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .archive-month-card:hover {
      transform: translateY(-6px);
      border-color: rgba(56, 189, 248, 0.4);
      box-shadow: 0 20px 40px rgba(56, 189, 248, 0.12);
    }
    .archive-month-card:hover .archive-bg-img {
      transform: scale(1.08);
      filter: saturate(1.1) brightness(0.65);
    }
    .archive-bg-img {
      transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }
  </style>
@endsection


@section('content')
  <!-- 2カラムレイアウト -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
    
    <!-- ================= 左カラム：全アーカイブ一覧 ================= -->
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
              <span class="text-sky-400 ml-1 font-bold">月別アーカイブ</span>
            </div>
          </li>
        </ol>
      </nav>


      <!-- アーカイブ一覧メインコンテンツ -->
      <div class="space-y-10">
        @if ($archivesByYear->isEmpty())
          <div class="glass-card rounded-2xl p-12 text-center">
            <h4 class="text-base font-bold text-slate-300 mb-1">アーカイブがありません</h4>
            <p class="text-xs text-slate-500">投稿データが同期されると、月別のアーカイブが自動的に作成されます。</p>
          </div>
        @else
          <!-- 年選択クイックフィルター -->
          @if ($archivesByYear->count() > 1)
            <div class="glass-card rounded-2xl p-4 border border-slate-800/80 space-y-2 select-none">
              <span class="text-xs font-bold text-slate-400 flex items-center gap-1.5 mb-1">
                <x-icon name="filter" class="h-3.5 w-3.5 text-sky-400" stroke-width="2" />
                年を選択してジャンプ
              </span>
              <div class="flex flex-wrap gap-2">
                @foreach ($archivesByYear->keys() as $y)
                  <a href="#year-{{ $y }}" 
                     class="px-4.5 py-2 rounded-xl bg-slate-950/60 hover:bg-sky-500/10 border border-slate-800 hover:border-sky-500/20 text-slate-300 hover:text-sky-400 text-xs font-extrabold transition duration-300 shadow-sm cursor-pointer active:scale-[0.97]">
                    {{ $y }}年
                  </a>
                @endforeach
              </div>
            </div>
          @endif

          @foreach ($archivesByYear as $year => $archives)
            <div id="year-{{ $year }}" class="year-section space-y-4">
              <!-- 年ヘッダー -->
              <h3 class="text-lg font-extrabold text-white tracking-wide flex items-center gap-2 border-b border-slate-900/60 pb-2 mb-2 select-none">
                <x-icon name="calendar" class="h-5 w-5 text-sky-400" stroke-width="2" />
                <span>{{ $year }}年</span>
              </h3>


              <!-- 月カードのグリッド表示 -->
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                @foreach ($archives as $archive)
                  @php
                    $archiveDate = \Illuminate\Support\Carbon::parse($archive->month . '-01');
                    $bgImage = $monthlyImages[$archive->month] ?? null;
                  @endphp
                  
                  <a href="{{ route('calendar.show.month', ['handle' => $bskyAccount->handle, 'year' => $archiveDate->format('Y'), 'month' => $archiveDate->format('m')]) }}"
                     class="archive-month-card glass-card rounded-2xl overflow-hidden relative flex flex-col justify-end p-4.5 group border border-slate-800/80 cursor-pointer shadow-lg"
                     @style([
                       "background: linear-gradient(to top, rgba(2, 6, 23, 0.9) 0%, rgba(15, 23, 42, 0.4) 100%)" => !$bgImage
                     ])>
                    
                    @if ($bgImage)
                      <!-- 代表写真を背景に配置 -->
                      <img src="{{ $bgImage }}" alt="" class="archive-bg-img absolute inset-0 w-full h-full object-cover opacity-30 filter saturate-[0.85] brightness-[0.55]">
                      <div class="absolute inset-0 bg-linear-to-t from-slate-950 via-slate-950/40 to-transparent"></div>
                    @endif

                    <!-- コンテンツ -->
                    <div class="relative z-10 select-none">
                      <span class="text-2xl font-extrabold text-white tracking-tight block mb-1 group-hover:text-sky-400 transition duration-300">
                        {{ $archiveDate->format('n') }} <span class="text-xs font-normal text-slate-400 tracking-wide">月</span>
                      </span>
                      <div class="flex items-center justify-between">
                        <span class="text-xs text-sky-400 font-semibold tracking-wider">
                          {{ $archive->count }} 件のログ
                        </span>
                        <span class="text-[10px] text-slate-500 font-bold group-hover:text-sky-400 transition duration-300 flex items-center gap-0.5">
                          見る ➔
                        </span>
                      </div>
                    </div>
                  </a>
                @endforeach
              </div>
            </div>
          @endforeach
        @endif
      </div>

    </div>

    <!-- ================= 右カラム：サイドバー ================= -->
    @include('calendar._sidebar', ['hasMoreArchives' => false]) {{-- 二重読み込みや無限ループを防止するためにフラグを偽で渡す --}}

  </div>
@endsection
