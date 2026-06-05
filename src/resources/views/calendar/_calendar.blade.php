@php
  // 必要な変数が渡されていない場合のフォールバック処理 (年ページや他ページでの読み込みエラーを防止)
  if (!isset($currentMonth)) {
    $currentMonth = \Illuminate\Support\Carbon::now()->startOfMonth();
  }
  if (!isset($daysInMonth)) {
    $daysInMonth = $currentMonth->daysInMonth;
  }
  if (!isset($firstDayOfWeek)) {
    $firstDayOfWeek = $currentMonth->copy()->startOfMonth()->dayOfWeek;
  }
  if (!isset($prevMonth)) {
    $prevMonth = $currentMonth->copy()->subMonth()->format('Y-m');
  }
  if (!isset($nextMonth)) {
    $nextMonth = $currentMonth->copy()->addMonth()->format('Y-m');
  }
  if (!isset($postsByDay) && isset($user)) {
    $startOfMonth = $currentMonth->copy()->startOfMonth();
    $endOfMonth = $currentMonth->copy()->endOfMonth();
    $allMonthPosts = $user->posts()
      ->whereBetween('posted_at', [$startOfMonth, $endOfMonth])
      ->get();
    $postsByDay = $allMonthPosts->groupBy(function ($post) {
      return $post->posted_at->day;
    });
  } else {
    $postsByDay = $postsByDay ?? collect();
  }
@endphp

<div id="miniCalendarInner" class="transition-opacity duration-150">
  
  <!-- 月変更ナビゲーション -->
  <div id="calendarNav" class="flex items-center justify-between gap-3 mb-4 border-b border-slate-850 pb-3">
    @php
      $prevYearStr = substr($prevMonth, 0, 4);
      $prevMonthStr = substr($prevMonth, 5, 2);
      $nextYearStr = substr($nextMonth, 0, 4);
      $nextMonthStr = substr($nextMonth, 5, 2);

      // 前月に戻れるかどうかの判定 (Bluesky登録日を基準とする)
      $canGoPrev = true;
      if ($bskyAccount->registered_at) {
          $registeredMonth = $bskyAccount->registered_at->copy()->startOfMonth();
          $canGoPrev = $currentMonth->gt($registeredMonth);
      }
    @endphp
    
    @if ($canGoPrev)
      <a href="{{ route('calendar.show.month', ['handle' => $bskyAccount->handle, 'year' => $prevYearStr, 'month' => $prevMonthStr]) }}" 
         class="js-calendar-nav p-2 rounded-lg cursor-pointer bg-slate-950 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white transition shadow-sm"
         data-year="{{ $prevYearStr }}"
         data-month="{{ $prevMonthStr }}">
        <x-icon name="chevron-left" class="h-3.5 w-3.5" stroke-width="2" />
      </a>
    @else
      <div class="p-2 rounded-lg bg-slate-950/20 border border-slate-900 text-slate-650 opacity-30 cursor-not-allowed select-none shadow-xs">
        <x-icon name="chevron-left" class="h-3.5 w-3.5" stroke-width="2" />
      </div>
    @endif
    
    <div class="flex items-center gap-1 text-sm font-bold tracking-wide">
      <a href="{{ route('calendar.show.year', ['handle' => $bskyAccount->handle, 'year' => $currentMonth->format('Y')]) }}" class="text-white cursor-pointer hover:text-sky-400 transition">
        {{ $currentMonth->format('Y') }}年
      </a>
      <a href="{{ route('calendar.show.month', ['handle' => $bskyAccount->handle, 'year' => $currentMonth->format('Y'), 'month' => $currentMonth->format('m')]) }}" class="text-white cursor-pointer hover:text-sky-400 transition">
        {{ $currentMonth->format('m') }}月
      </a>
    </div>
    
    @if ($currentMonth->lt(\Illuminate\Support\Carbon::now()->startOfMonth()))
      <a href="{{ route('calendar.show.month', ['handle' => $bskyAccount->handle, 'year' => $nextYearStr, 'month' => $nextMonthStr]) }}" 
         class="js-calendar-nav p-2 rounded-lg cursor-pointer bg-slate-950 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white transition shadow-sm"
         data-year="{{ $nextYearStr }}"
         data-month="{{ $nextMonthStr }}">
        <x-icon name="chevron-right" class="h-3.5 w-3.5" stroke-width="2" />
      </a>
    @else
      <div class="p-2 rounded-lg bg-slate-950/20 border border-slate-900 text-slate-650 opacity-30 cursor-not-allowed select-none shadow-xs">
        <x-icon name="chevron-right" class="h-3.5 w-3.5" stroke-width="2" />
      </div>
    @endif
  </div>
  
  <!-- 曜日見出し (点滅させないための固定ヘッダー) -->
  <div class="grid grid-cols-7 gap-1 text-center mb-1 select-none">
    @foreach (['日', '月', '火', '水', '木', '金', '土'] as $i => $dayName)
      <div class="py-1 text-[10px] font-bold uppercase tracking-wider {{ $i === 0 ? 'text-rose-500' : ($i === 6 ? 'text-sky-500' : 'text-slate-500') }}">
        {{ $dayName }}
      </div>
    @endforeach
  </div>

  <!-- 日付グリッド (ここだけをフェード対象にする) -->
  <div id="calendarGrid" class="transition-opacity duration-150 grid grid-cols-7 gap-1 text-center">
    <!-- 前月空白 -->
    @for ($i = 0; $i < $firstDayOfWeek; $i++)
      <div class="calendar-cell bg-slate-950/10 rounded-lg border border-transparent opacity-10"></div>
    @endfor

    <!-- 日付セル -->
    @php
      $today = \Illuminate\Support\Carbon::now();
    @endphp
    @for ($day = 1; $day <= $daysInMonth; $day++)
      @php
        $dayPosts = $postsByDay->get($day, collect());
        $hasPosts = $dayPosts->isNotEmpty();
        $wday = ($firstDayOfWeek + $day - 1) % 7;
        
        $cellDate = $currentMonth->copy()->day($day);
        $isToday = $today->isSameDay($cellDate);
        $isSelectedDay = isset($targetDay) && $targetDay->isSameDay($cellDate);
      @endphp

      @if ($hasPosts)
        <a href="{{ route('calendar.show.day', ['handle' => $bskyAccount->handle, 'year' => $currentMonth->format('Y'), 'month' => $currentMonth->format('m'), 'day' => sprintf('%02d', $day)]) }}"
           class="calendar-cell glass-card rounded-md py-1 px-0.5 flex items-center justify-center group cursor-pointer hover:scale-[1.08] transition duration-300 active:scale-[0.95]
                  {{ $isSelectedDay ? 'bg-sky-500! text-slate-950! font-extrabold border-sky-400! shadow-lg shadow-sky-500/20 scale-[1.05] z-10 opacity-100!' : ($isToday ? 'bg-amber-500/10 border-amber-500/70 shadow-md shadow-amber-500/10 z-10' : 'bg-sky-500/10 border-sky-500/30 hover:border-sky-400/60') }}">
      @else
        <div class="calendar-cell glass-card rounded-md py-1 px-0.5 flex items-center justify-center border transition duration-300 cursor-default
                    {{ $isSelectedDay ? 'bg-sky-500! text-slate-950! font-extrabold border-sky-400! shadow-lg scale-[1.05] z-10 opacity-100!' : ($isToday ? 'bg-amber-500/10 border-amber-500/70 shadow-md shadow-amber-500/10 z-10 opacity-100' : 'bg-slate-950/15 border-transparent opacity-60') }}">
      @endif
        
        <!-- 日付数字 -->
        <span class="text-xs font-bold transition duration-300 
                     {{ $isSelectedDay ? 'text-slate-950!' : ($isToday ? 'text-amber-400' : ($wday === 0 ? ($hasPosts ? 'text-rose-500' : 'text-rose-900/70') : ($wday === 6 ? ($hasPosts ? 'text-sky-500' : 'text-sky-900/70') : ($hasPosts ? 'text-slate-100' : 'text-slate-600')))) }}">
          {{ $day }}
        </span>

      @if ($hasPosts)
        </a>
      @else
        </div>
      @endif
    @endfor
  </div>
</div>
