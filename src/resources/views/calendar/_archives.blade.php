@php
  // 必要な変数が渡されていない場合のフォールバック処理 (年ページや他ページでの読み込みエラーを防止)
  if (!isset($monthlyArchives) && isset($user)) {
    $allMonthArchives = $user->posts()
      ->selectRaw("substr(posted_at, 1, 7) as month, count(*) as count")
      ->groupBy('month')
      ->orderBy('month', 'desc')
      ->limit(11)
      ->get();
    
    $hasMoreArchives = $allMonthArchives->count() > 10;
    $monthlyArchives = $hasMoreArchives ? $allMonthArchives->take(10) : $allMonthArchives;
  } else {
    $monthlyArchives = $monthlyArchives ?? collect();
    // コントローラー等から制限なく渡された場合でも強制的に10件に切り詰める防御処理
    if ($monthlyArchives->count() > 10) {
      $hasMoreArchives = true;
      $monthlyArchives = $monthlyArchives->take(10);
    } else {
      $hasMoreArchives = isset($hasMoreArchives) ? $hasMoreArchives : false;
    }
  }
@endphp

@if ($monthlyArchives->isNotEmpty())
  <div class="glass-card rounded-2xl p-5 border border-slate-800/80">
    <h4 class="text-sm font-bold text-white mb-4 flex items-center gap-2 border-b border-slate-850 pb-2.5">
      <x-icon name="calendar" class="h-4 w-4 text-sky-400 shrink-0" stroke-width="2" />
      Archives
    </h4>
    
    <ul class="space-y-1.5">
      @foreach ($monthlyArchives as $archive)
        @php
          $archiveDate = \Illuminate\Support\Carbon::parse($archive->month . '-01');
        @endphp
        <li>
          <a href="{{ route('calendar.show.month', ['handle' => $bskyAccount->handle, 'year' => $archiveDate->format('Y'), 'month' => $archiveDate->format('m')]) }}"
             class="flex items-center justify-between px-3 py-1.5 rounded-lg bg-slate-950/20 hover:bg-sky-500/10 border border-slate-900/60 hover:border-sky-500/20 text-slate-300 hover:text-sky-400 font-medium text-xs transition duration-300 cursor-pointer group">
            <span class="flex items-center gap-1.5">
              <x-icon name="chevron-right" class="h-3 w-3 text-slate-500 group-hover:text-sky-400 shrink-0 transition" stroke-width="2" />
              <span>{{ $archiveDate->format('Y年m月') }}</span>
            </span>
            <span class="px-2 py-0.5 rounded-full bg-slate-900 group-hover:bg-sky-950 text-[10px] text-slate-500 group-hover:text-sky-400 border border-slate-850 group-hover:border-sky-900 font-bold transition">
              {{ $archive->count }}
            </span>
          </a>
        </li>
      @endforeach
    </ul>

    @if ($hasMoreArchives)
      <div class="mt-4 pt-3 border-t border-slate-850 text-center">
        <a href="{{ route('calendar.archives', ['handle' => $bskyAccount->handle]) }}" 
           class="inline-flex items-center gap-1 text-xs text-sky-400 hover:text-sky-300 font-bold transition cursor-pointer">
          アーカイブ一覧を表示 ➔
        </a>
      </div>
    @endif
  </div>
@endif
