@extends('layouts.app')

@section('title', $user->name . ' (@' . $bskyAccount->handle . ') の' . $targetYear . '年のアーカイブ | ' . config('app.name', 'SkLoG'))

@section('extra_css')
  <style>
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
    
    <!-- ================= 左カラム：指定年の月アーカイブ ================= -->
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
              <span class="text-sky-400 ml-1 font-bold">{{ $targetYear }}年</span>
            </div>
          </li>
        </ol>
      </nav>


      <!-- 年ナビゲーション -->
      <div class="glass-card rounded-2xl p-5 border border-slate-800/80">
        <div class="flex items-center justify-between gap-4">
          <!-- 前年ボタン -->
          <a href="{{ route('calendar.show.year', ['handle' => $bskyAccount->handle, 'year' => $prevYear]) }}" 
             class="px-3.5 py-2 rounded-xl cursor-pointer bg-slate-950/60 hover:bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white transition flex items-center gap-1.5 shadow-sm text-xs font-bold active:scale-[0.97]">
            <x-icon name="chevron-left" class="h-4 w-4 text-sky-400" stroke-width="2" />
            <span>{{ $prevYear }}年</span>
          </a>

          <!-- 当年の日付情報 -->
          <div class="text-center">
            <h1 class="text-lg md:text-2xl font-extrabold text-white tracking-tight">
              {{ $targetYear }}年のログ
            </h1>
            <p class="text-[10px] text-sky-400 font-semibold tracking-wider mt-0.5">
              年間総計 {{ $totalYearPosts }} 件のログ
            </p>
          </div>

          <!-- 翌年ボタン -->
          <a href="{{ route('calendar.show.year', ['handle' => $bskyAccount->handle, 'year' => $nextYear]) }}" 
             class="px-3.5 py-2 rounded-xl cursor-pointer bg-slate-950/60 hover:bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white transition flex items-center gap-1.5 shadow-sm text-xs font-bold active:scale-[0.97]">
            <span>{{ $nextYear }}年</span>
            <x-icon name="chevron-right" class="h-4 w-4 text-sky-400" stroke-width="2" />
          </a>
        </div>
      </div>

      <!-- 月カードのグリッド表示 -->
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
        @php
          $hasAnyPosts = false;
        @endphp
        @for ($m = 1; $m <= 12; $m++)
          @php
            $count = $monthlyCounts[$m] ?? 0;
            $bgImage = $monthlyImages[$m] ?? null;
            $monthStr = sprintf('%02d', $m);
          @endphp

          @if ($count > 0)
            @php
              $hasAnyPosts = true;
            @endphp
            <a href="{{ route('calendar.show.month', ['handle' => $bskyAccount->handle, 'year' => $targetYear, 'month' => $monthStr]) }}"
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
                  {{ $m }} <span class="text-xs font-normal text-slate-400 tracking-wide">月</span>
                </span>
                <div class="flex items-center justify-between">
                  <span class="text-xs text-sky-400 font-semibold tracking-wider">
                    {{ $count }} 件のログ

                  </span>
                  <span class="text-[10px] text-slate-500 font-bold group-hover:text-sky-400 transition duration-300 flex items-center gap-0.5">
                    見る ➔
                  </span>
                </div>
              </div>
            </a>
          @endif
        @endfor

        @if (!$hasAnyPosts)
          <div class="col-span-full glass-card rounded-2xl p-12 text-center select-none">
            <h4 class="text-base font-bold text-slate-300 mb-1">ログがありません</h4>
            <p class="text-xs text-slate-500">{{ $targetYear }}年のログはまだ同期されていません。</p>
          </div>
        @endif
      </div>

    </div>

    <!-- ================= 右カラム：サイドバー ================= -->
    @include('calendar._sidebar', ['hasMoreArchives' => false])

  </div>
@endsection
