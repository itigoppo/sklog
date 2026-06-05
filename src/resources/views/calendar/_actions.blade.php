@if (!($isMainPage ?? false))
  <!-- 戻るボタン -->
  <a href="{{ route('calendar.show', ['handle' => $bskyAccount->handle]) }}" class="w-full py-2.5 rounded-xl cursor-pointer bg-slate-900/60 hover:bg-slate-850 border border-slate-800/80 hover:border-slate-700 text-slate-300 hover:text-white font-bold text-xs transition duration-300 flex items-center justify-center gap-1.5 shadow-md active:scale-[0.98] mb-3">
    <x-icon name="home" class="h-4 w-4 text-sky-400 shrink-0" stroke-width="2" />
    <span>カレンダー / 全履歴へ</span>
  </a>
@endif

<div class="glass-card rounded-2xl p-4 border border-slate-800/80 space-y-3">
  @auth
    @if (Auth::id() === $user->id)
      @php
        $userId = Auth::id();
        $limiterKey = 'sync-user:' . $userId;
        $tooManyAttempts = \Illuminate\Support\Facades\RateLimiter::tooManyAttempts($limiterKey, 5);
        $availableInSeconds = \Illuminate\Support\Facades\RateLimiter::availableIn($limiterKey);
        $availableInMinutes = ceil($availableInSeconds / 60);
      @endphp

      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full">
        <!-- 手動同期フォーム -->
        <form action="{{ route('calendar.sync') }}" method="POST" id="syncForm" class="w-full sm:flex-1">
          @csrf
          @if ($bskyAccount && $bskyAccount->is_syncing)
            <button type="button" class="w-full py-2.5 rounded-xl cursor-not-allowed bg-sky-500/5 border border-sky-500/10 text-sky-500/60 font-bold text-xs flex items-center justify-center gap-1.5 opacity-60 select-none" title="現在、バックグラウンドで投稿を同期しています。完了するまでしばらくお待ちください。">
              <svg class="animate-spin h-4 w-4 text-sky-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>同期中...</span>
            </button>
          @elseif ($tooManyAttempts)
            <button type="button" class="w-full py-2.5 rounded-xl cursor-not-allowed bg-slate-900/60 border border-slate-800 text-slate-500 font-bold text-xs flex items-center justify-center gap-1.5 opacity-60 select-none" title="API制限保護のため、現在同期できません。残り{{ $availableInMinutes }}分で制限が解除されます。">
              <x-icon name="lock" class="h-4 w-4 shrink-0 text-slate-500" stroke-width="2" />
              <span id="syncText">制限中({{ $availableInMinutes }}分)</span>
            </button>
          @else
            <button type="submit" onclick="startSync()" class="w-full py-2.5 rounded-xl cursor-pointer bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 hover:border-sky-500/50 text-sky-400 font-bold text-xs transition duration-300 flex items-center justify-center gap-1.5 active:scale-[0.98]">
              <x-icon id="syncIcon" name="sync" class="h-4 w-4 shrink-0" stroke-width="2" />
              <span id="syncText" class="flex items-center gap-1.5 whitespace-nowrap">
                今すぐ同期
                @if (isset($bskyAccount) && !$bskyAccount->is_archive_complete)
                  <span class="text-[9px] text-amber-400 bg-amber-500/10 border border-amber-500/20 px-1 py-0.5 rounded font-normal leading-none shrink-0 scale-90 select-none" title="取り込めていない過去ログがあります。同期するたびに続きを取り込みます">過去ログ残あり</span>
                @elseif (isset($bskyAccount) && isset($user) && ($bskyAccount->posts_count ?? 0) > (\Illuminate\Support\Facades\Cache::get('user-stats-summary:' . $user->id)['total'] ?? $user->posts()->withTrashed()->where('uri', 'not like', 'repost-%')->count()))
                  <span class="text-[9px] text-sky-400 bg-sky-500/10 border border-sky-500/20 px-1 py-0.5 rounded font-normal leading-none shrink-0 scale-90 select-none" title="Blueskyに未同期の新着投稿があります">新着あり</span>
                @endif
              </span>
            </button>
          @endif
        </form>

        <!-- ログアウトフォーム -->
        <form action="{{ route('logout') }}" method="POST" class="shrink-0 w-full sm:w-auto">
          @csrf
          <button type="submit" class="w-full sm:px-4 py-2.5 rounded-xl cursor-pointer bg-slate-900/60 hover:bg-rose-500/10 border border-slate-700/60 hover:border-rose-500/30 text-slate-400 hover:text-rose-400 font-bold text-xs transition duration-300 active:scale-[0.98]">
            ログアウト
          </button>
        </form>
      </div>
    @else
      <!-- ログイン済みだが他ユーザーのページを閲覧中 -->
      @php
        $myHandle = Auth::user()->blueskyAccount?->handle;
      @endphp
      @if ($myHandle)
        <a href="{{ route('calendar.show', ['handle' => $myHandle]) }}" class="w-full py-2.5 rounded-xl cursor-pointer bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 hover:border-sky-500/50 text-sky-400 font-bold text-xs transition duration-300 flex items-center justify-center gap-1.5 active:scale-[0.98]">
          <x-icon name="home" class="h-4 w-4 shrink-0" stroke-width="2" />
          <span>自分のカレンダーへ</span>
        </a>
      @endif
    @endif
  @else
    <!-- ログインしていない場合はログイン画面へのリンク -->
    <a href="{{ route('login') }}" class="w-full py-2.5 rounded-xl bg-linear-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-bold text-xs shadow-lg transition duration-300 flex items-center justify-center gap-1.5 active:scale-[0.98]">
      <x-icon name="login" class="h-4 w-4 text-white" stroke-width="2" />
      <span>ログインして始める</span>
    </a>
  @endauth
</div>
