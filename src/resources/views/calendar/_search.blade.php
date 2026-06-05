<div class="glass-card rounded-2xl p-4 border border-slate-800/80">
  <form action="{{ route('calendar.show', ['handle' => $bskyAccount->handle]) }}" method="GET" 
        onsubmit="if (this.querySelector('input[name=\'q\']').value.trim() === '') { event.preventDefault(); const mediaInput = this.querySelector('input[name=\'media\']'); window.location.href = this.action + (mediaInput ? '?media=1' : ''); }"
        class="space-y-2.5">
    @if (request('media') === '1')
      <input type="hidden" name="media" value="1">
    @endif
    <!-- 検索入力 ＆ 検索ボタン（インライン横並びで省スペース＆モダンに） -->
    <div class="flex items-center gap-2">
      <div class="relative flex-1 flex items-center">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="投稿を検索..." 
               class="w-full bg-slate-950/60 border border-slate-800/80 hover:border-slate-700/80 focus:border-sky-500/80 text-white rounded-xl py-2 pl-9 pr-4 text-xs placeholder-slate-500 focus:outline-hidden transition shadow-inner">
        <!-- 検索虫眼鏡アイコン -->
        <span class="absolute left-3 text-slate-500 select-none">
          <x-icon name="search" class="h-4 w-4" stroke-width="2" />
        </span>
      </div>
      
      <!-- 検索実行ボタン（横並び結合で縦幅を大幅削減） -->
      <button type="submit" aria-label="検索" class="p-2 bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 hover:border-sky-500/50 text-sky-400 rounded-xl transition duration-300 shadow-sm cursor-pointer active:scale-95 shrink-0 flex items-center justify-center">
        <x-icon name="search" class="h-4 w-4" stroke-width="2.5" />
      </button>
    </div>
    
    <!-- オプション展開トグルボタン（左右のバランスを美しく調整） -->
    <div class="flex justify-between items-center select-none pt-0.5">
      <span class="text-[9px] text-slate-600 font-bold tracking-wider uppercase leading-none">Search Settings</span>
      <button type="button" 
              onclick="document.getElementById('search-options').classList.toggle('hidden'); document.getElementById('search-options-arrow').classList.toggle('rotate-180');"
              class="text-[9px] font-bold text-slate-500 hover:text-slate-300 transition flex items-center gap-1 cursor-pointer select-none">
        <x-icon name="settings" class="h-3 w-3 text-slate-500" />
        <span>検索オプション</span>
        <span id="search-options-arrow" class="inline-block text-[7px] transition-transform duration-200 {{ request('mode') === 'or' || request('rt') === '1' ? 'rotate-180' : '' }}">▼</span>
      </button>
    </div>

    <!-- 検索オプション（デフォルトは隠す） -->
    @php
      $showOptions = request('mode') === 'or' || request('rt') === '1';
    @endphp
    <div id="search-options" class="{{ $showOptions ? '' : 'hidden' }} space-y-2.5 pt-2 border-t border-slate-900/40">
      <!-- AND/OR 条件切替 -->
      <div class="flex items-center justify-between gap-4 text-[10px] text-slate-400 font-bold select-none">
        <span class="text-slate-500">一致条件</span>
        <div class="flex items-center gap-3">
          <label class="flex items-center gap-1.5 cursor-pointer hover:text-slate-200 transition">
            <input type="radio" name="mode" value="and" {{ request('mode', 'and') === 'and' ? 'checked' : '' }} 
                   class="accent-sky-500 cursor-pointer">
            AND
          </label>
          <label class="flex items-center gap-1.5 cursor-pointer hover:text-slate-200 transition">
            <input type="radio" name="mode" value="or" {{ request('mode') === 'or' ? 'checked' : '' }} 
                   class="accent-sky-500 cursor-pointer">
            OR
          </label>
        </div>
      </div>

      <!-- リポスト（RT）の扱い -->
      <div class="flex items-center justify-between gap-4 text-[10px] text-slate-400 font-bold select-none">
        <span class="text-slate-500">リポストの除外</span>
        <label class="flex items-center gap-1.5 cursor-pointer hover:text-slate-200 transition">
          <input type="checkbox" name="rt" value="1" {{ request('rt') === '1' ? 'checked' : '' }} 
                 class="accent-sky-500 cursor-pointer rounded">
          リポストを含める
        </label>
      </div>
    </div>
  </form>
</div>
