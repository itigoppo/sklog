{{--
  同時ログイン上限に達したときに、ログイン/登録フォームの代わりに出す一覧。

  外せるのはアクティブ以外のアカウントだけにしている。アクティブを外すと残りのアカウント
  への切り替えが走ってこの画面から離れてしまうため、そちらはサイドバーのログアウトに任せる。
--}}
@php
  $logoutCandidates = \App\Support\AccountSession::others();
  $activeHandle = Auth::user()?->blueskyAccount?->handle;
@endphp

<div class="space-y-4">
  <div class="p-4 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs leading-relaxed flex items-start gap-2">
    <x-icon name="alert" class="h-4 w-4 shrink-0 mt-0.5" stroke-width="2.5" />
    <span>
      同時にログインできるアカウントが上限（{{ \App\Support\AccountSession::MAX_ACCOUNTS }}件）です。<br>
      続けるには、ログイン中のアカウントから1件ログアウトしてください。
    </span>
  </div>

  <div class="space-y-2">
    @foreach ($logoutCandidates as $account)
      <div class="px-2.5 py-2 rounded-xl bg-slate-900/60 border border-slate-800/80 flex items-center gap-2.5">
        <span class="h-7 w-7 rounded-full overflow-hidden bg-slate-800 shrink-0 flex items-center justify-center">
          @if ($account->blueskyAccount->avatar_url)
            <img src="{{ $account->blueskyAccount->avatar_url }}" alt="" class="w-full h-full object-cover">
          @else
            <span class="w-full h-full flex items-center justify-center bg-linear-to-br from-sky-400 to-indigo-600 text-white text-[11px] font-bold">
              {{ mb_substr($account->name ?: $account->blueskyAccount->handle, 0, 1) }}
            </span>
          @endif
        </span>

        <span class="flex-1 min-w-0">
          <span class="block text-xs font-bold text-slate-200 truncate leading-tight">
            {{ $account->name ?: '@' . $account->blueskyAccount->handle }}
          </span>
          <span class="block text-[10px] text-slate-500 truncate leading-tight">{{ '@' . $account->blueskyAccount->handle }}</span>
        </span>

        <form action="{{ route('logout') }}" method="POST" class="shrink-0">
          @csrf
          <input type="hidden" name="user_id" value="{{ $account->id }}">
          <button type="submit"
                  class="px-2.5 py-1.5 rounded-lg cursor-pointer text-[10px] font-extrabold tracking-wider text-slate-400 hover:text-rose-300 hover:bg-rose-500/10 border border-slate-800 hover:border-rose-500/40 whitespace-nowrap transition duration-300 active:scale-[0.98]"
                  title="{{ '@' . $account->blueskyAccount->handle }} からログアウトする">
            ログアウト
          </button>
        </form>
      </div>
    @endforeach
  </div>

  @if ($activeHandle)
    <p class="text-[10px] text-slate-600 leading-relaxed px-1">
      いま使っている {{ '@' . $activeHandle }} は、この画面からは外せません。カレンダー画面のログアウトから行ってください。
    </p>
  @endif
</div>
