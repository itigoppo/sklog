@php
  // ハッシュタグのフォールバック処理 (中間テーブル経由・キャッシュ連動)
  if (!isset($popularTags) && isset($user)) {
    $allMonthTags = \Illuminate\Support\Facades\Cache::remember('user-tags:' . $user->id, 3600, function() use ($user) {
      return \App\Models\Tag::join('post_tags', 'tags.id', '=', 'post_tags.tag_id')
        ->join('posts', 'post_tags.post_id', '=', 'posts.id')
        ->where('posts.user_id', $user->id)
        ->whereNull('posts.deleted_at')
        ->selectRaw('tags.name, count(*) as count')
        ->groupBy('tags.id', 'tags.name')
        ->orderBy('count', 'desc')
        ->limit(11)
        ->pluck('count', 'tags.name')
        ->toArray();
    });

    $hasMoreTags = count($allMonthTags) > 10;
    $popularTags = $hasMoreTags ? array_slice($allMonthTags, 0, 10, true) : $allMonthTags;
  } else {
    $popularTags = $popularTags ?? [];
    // 外部から制限なく渡された場合でも強制的に10件に切り詰める防御処理
    if (count($popularTags) > 10) {
      $hasMoreTags = true;
      $popularTags = array_slice($popularTags, 0, 10, true);
    } else {
      $hasMoreTags = isset($hasMoreTags) ? $hasMoreTags : false;
    }
  }
@endphp

@if (!empty($popularTags))
  <div class="glass-card rounded-2xl p-5 border border-slate-800/80">
    <h4 class="text-sm font-bold text-white mb-4 flex items-center gap-2 border-b border-slate-850 pb-2.5 select-none">
      <x-icon name="tag" class="h-4 w-4 text-sky-400 shrink-0" stroke-width="2" />
      Tags
    </h4>
    
    <ul class="space-y-1.5">
      @foreach ($popularTags as $tag => $count)
        <li>
          <a href="{{ route('calendar.show', ['handle' => $bskyAccount->handle, 'q' => '#' . $tag]) }}"
             class="flex items-center justify-between px-3 py-1.5 rounded-lg bg-slate-950/20 hover:bg-sky-500/10 border border-slate-900/60 hover:border-sky-500/20 text-slate-300 hover:text-sky-400 font-medium text-xs transition duration-300 cursor-pointer group select-none">
            <span class="flex items-center gap-1.5 min-w-0">
              <span class="text-slate-500 group-hover:text-sky-400 shrink-0 font-bold">#</span>
              <span class="truncate">{{ $tag }}</span>
            </span>
            <span class="px-2 py-0.5 rounded-full bg-slate-900 group-hover:bg-sky-950 text-[10px] text-slate-500 group-hover:text-sky-400 border border-slate-850 group-hover:border-sky-900 font-bold transition shrink-0 ml-2">
              {{ $count }}
            </span>
          </a>
        </li>
      @endforeach
    </ul>

    @if ($hasMoreTags)
      <div class="mt-4 pt-3 border-t border-slate-850 text-center">
        <a href="{{ route('calendar.tags', ['handle' => $bskyAccount->handle]) }}" 
           class="inline-flex items-center gap-1 text-xs text-sky-400 hover:text-sky-300 font-bold transition cursor-pointer">
          タグ一覧を表示 ➔
        </a>
      </div>
    @endif
  </div>
@endif
