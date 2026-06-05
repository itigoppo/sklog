@if (isset($user) && $user->posts()->exists())
  @php
    // 統計情報の算出 (キャッシュ連動・30分間)
    $stats = \Illuminate\Support\Facades\Cache::remember('user-stats-summary:' . $user->id, 1800, function() use ($user, $bskyAccount) {
      $totalPosts = $user->posts()->withTrashed()->where('uri', 'not like', 'repost-%')->count();
      $bskyPosts = $bskyAccount->posts_count ?? 0;
      
      // 登録日を優先、なければローカル最古
      $registeredDate = $bskyAccount->registered_at;
      $firstPostDateStr = $user->posts()->min('posted_at');
      $fallbackDate = $firstPostDateStr ? \Illuminate\Support\Carbon::parse($firstPostDateStr) : null;
      
      $baseDate = $registeredDate ?? $fallbackDate;
      
      $days = 1;
      $postsPerDay = $bskyPosts; // 本家ベースの日平均
      
      if ($baseDate) {
        $days = max(1, $baseDate->diffInDays(\Illuminate\Support\Carbon::now()));
        $postsPerDay = round($bskyPosts / $days, 2);
      }
      
      return [
        'total' => $totalPosts,
        'bsky_total' => $bskyPosts,
        'first_date' => $registeredDate ? $registeredDate->format('Y年m月d日') : ($fallbackDate ? $fallbackDate->format('Y年m月d日') : '不明'),
        'per_day' => $postsPerDay,
        'days' => $days,
        'is_bsky_registered' => !empty($registeredDate)
      ];
    });

    // 登録記念日の判定 (キャッシュに依存せずリアルタイム判定)
    $isAnniversary = false;
    $anniversaryYears = 0;
    if ($bskyAccount && $bskyAccount->registered_at) {
        // タイムゾーンを明示的に JST (Asia/Tokyo) に統一して判定・計算の狂いを防止
        $registeredAt = $bskyAccount->registered_at->timezone('Asia/Tokyo');
        $nowJst = now()->timezone('Asia/Tokyo');
        
        $anniversaryYears = (int) abs($nowJst->diffInYears($registeredAt));
        
        // 登録当日は除外（1年以上経過した周年記念日のみお祝い。デバッグパラメータ ?anniversary=1 は例外）
        $isAnniversary = (request()->query('anniversary') == 1) || 
                         (($anniversaryYears > 0) && ($registeredAt->format('m-d') === $nowJst->format('m-d')));
    }
  @endphp

  @if($isAnniversary)
    <style>
      @keyframes gold-glow {
        0%, 100% {
          border-color: rgba(245, 158, 11, 0.35);
          box-shadow: 0 0 12px rgba(245, 158, 11, 0.1);
        }
        50% {
          border-color: rgba(245, 158, 11, 0.65);
          box-shadow: 0 0 22px rgba(245, 158, 11, 0.25);
        }
      }
      .anniversary-card {
        animation: gold-glow 4s ease-in-out infinite;
        background: linear-gradient(135deg, rgba(15, 23, 42, 0.9) 0%, rgba(15, 23, 42, 0.85) 60%, rgba(245, 158, 11, 0.06) 100%) !important;
      }
    </style>
  @endif

  <div class="glass-card rounded-2xl p-4 border {{ $isAnniversary ? 'anniversary-card border-amber-500/40' : 'border-slate-800/80' }} space-y-3 select-none">
    <h4 class="text-xs font-bold text-white mb-2 flex items-center justify-between border-b border-slate-850 pb-2.5">
      <div class="flex items-center gap-2">
        <x-icon name="bar-chart" class="h-4 w-4 text-sky-400 shrink-0" stroke-width="2" />
        Stats
      </div>
      @if($isAnniversary)
        <span class="px-2 py-0.5 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-400 text-[9px] font-bold tracking-wider inline-flex items-center gap-1 shrink-0 whitespace-nowrap animate-bounce select-none" style="animation-duration: 2.5s;">
          <x-icon name="award" class="h-3 w-3 text-amber-400 shrink-0" stroke-width="2.5" />
          Happy Anniv.
        </span>
      @endif
    </h4>

    @if($isAnniversary)
      <!-- 記念日特別お祝い枠 -->
      <div class="bg-linear-to-r from-amber-500/10 to-yellow-500/5 border border-amber-500/20 rounded-xl p-2.5 flex items-center gap-2.5 text-amber-300">
        <x-icon name="gift" class="h-5 w-5 text-amber-400 shrink-0 select-none" stroke-width="2" />
        <div class="flex flex-col min-w-0">
          <span class="text-[9px] font-extrabold tracking-wider text-amber-400 uppercase leading-none mb-1">Bluesky Anniversary</span>
          <span class="text-[9px] text-slate-300 font-semibold leading-relaxed">
            本日、Bluesky登録から <strong>{{ $anniversaryYears }}周年</strong> を迎えました！<br />
            おめでとうございます！
          </span>
        </div>
      </div>
    @endif

    <div class="grid grid-cols-3 gap-1.5 text-center">
      <!-- 総ポスト数 (Bluesky) -->
      <div class="bg-slate-950/40 border border-slate-900 rounded-xl p-1.5 flex flex-col justify-center">
        <span class="text-[8px] font-bold text-slate-500 tracking-wider">総ポスト数</span>
        <span class="text-xs font-extrabold text-white mt-0.5 tracking-tight leading-none">
          {{ number_format($stats['bsky_total']) }}
        </span>
        <span class="text-[7px] text-slate-600 font-bold mt-0.5">posts</span>
      </div>

      <!-- 日平均投稿数 -->
      <div class="bg-slate-950/40 border border-slate-900 rounded-xl p-1.5 flex flex-col justify-center">
        <span class="text-[8px] font-bold text-slate-500 tracking-wider">日平均</span>
        <span class="text-xs font-extrabold text-white mt-0.5 tracking-tight leading-none">
          {{ $stats['per_day'] }}
        </span>
        <span class="text-[7px] text-slate-600 font-bold mt-0.5">posts/日</span>
      </div>

      <!-- 記録日数 -->
      <div class="bg-slate-950/40 border border-slate-900 rounded-xl p-1.5 flex flex-col justify-center">
        <span class="text-[8px] font-bold text-slate-500 tracking-wider">活動日数</span>
        <span class="text-xs font-extrabold text-white mt-0.5 tracking-tight leading-none">
          {{ number_format($stats['days']) }}
        </span>
        <span class="text-[7px] text-slate-600 font-bold mt-0.5">日</span>
      </div>
    </div>

    <!-- Bluesky参加日 -->
    <div class="flex items-center justify-between text-[9px] bg-slate-950/20 px-2.5 py-1.5 rounded-lg border border-slate-900">
      <span class="text-slate-500 font-bold">
        {{ ($stats['is_bsky_registered'] ?? false) ? 'Bluesky参加日' : '最古の記録日' }}
      </span>
      <span class="text-slate-300 font-extrabold tracking-wider">
        {{ $stats['first_date'] }}
      </span>
    </div>
  </div>
@endif
