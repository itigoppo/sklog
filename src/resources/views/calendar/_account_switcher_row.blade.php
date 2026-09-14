{{-- 切り替えパネルの1行の中身。切り替えボタンと再ログインリンクで共用する --}}
<span class="h-7 w-7 rounded-full overflow-hidden bg-slate-800 shrink-0 flex items-center justify-center {{ $needsReauth ? 'opacity-50' : '' }}">
  @if ($account->blueskyAccount->avatar_url)
    <img src="{{ $account->blueskyAccount->avatar_url }}" alt="" class="w-full h-full object-cover">
  @else
    <span class="w-full h-full flex items-center justify-center bg-linear-to-br from-sky-400 to-indigo-600 text-white text-[11px] font-bold">
      {{ mb_substr($account->name ?: $account->blueskyAccount->handle, 0, 1) }}
    </span>
  @endif
</span>

<span class="flex-1 min-w-0">
  <span class="block text-xs font-bold {{ $needsReauth ? 'text-slate-400' : 'text-slate-200 group-hover:text-white' }} truncate leading-tight">
    {{ $account->name ?: '@' . $account->blueskyAccount->handle }}
  </span>
  <span class="block text-[10px] text-slate-500 truncate leading-tight">{{ '@' . $account->blueskyAccount->handle }}</span>
</span>

@if ($needsReauth)
  <span class="shrink-0 text-[10px] font-extrabold tracking-wider text-amber-500/80 group-hover:text-amber-400 whitespace-nowrap select-none transition duration-300">再ログイン</span>
@else
  <span class="shrink-0 text-[10px] font-extrabold tracking-wider text-slate-500 group-hover:text-sky-400 whitespace-nowrap select-none transition duration-300">切り替え</span>
@endif
