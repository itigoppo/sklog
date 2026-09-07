@php
  // このセッションでログイン中のアカウント（現在のアカウントを含む、追加順）
  $accounts = \App\Support\AccountSession::accounts();
  $currentId = Auth::id();
  $current = $accounts->firstWhere('id', $currentId);
  $others = $accounts->reject(fn ($account) => $account->id === $currentId)->values();
  $canAddAccount = ! \App\Support\AccountSession::isFull();
@endphp

@if ($current)
  {{-- サイドバーはPC版とモバイルドロワーで同一ページに2回描画されるため、id は使わずクラスで参照する --}}
  <div class="pt-3 border-t border-slate-800/80 js-account-switcher">
    <!-- 現在のアカウント（クリックで一覧を開閉） -->
    <button type="button" aria-expanded="false"
            class="js-account-switcher-toggle w-full px-2.5 py-2 rounded-xl cursor-pointer bg-slate-900/60 hover:bg-slate-800/80 border border-slate-800/80 hover:border-sky-500/40 text-left transition duration-300 flex items-center gap-2.5 group active:scale-[0.98]"
            title="ログイン中のアカウントを開閉する">
      <span class="h-7 w-7 rounded-full overflow-hidden bg-slate-800 shrink-0 flex items-center justify-center">
        @if ($current->blueskyAccount->avatar_url)
          <img src="{{ $current->blueskyAccount->avatar_url }}" alt="" class="w-full h-full object-cover">
        @else
          <span class="w-full h-full flex items-center justify-center bg-linear-to-br from-sky-400 to-indigo-600 text-white text-[11px] font-bold">
            {{ mb_substr($current->name ?: $current->blueskyAccount->handle, 0, 1) }}
          </span>
        @endif
      </span>

      <span class="flex-1 min-w-0">
        <span class="block text-xs font-bold text-slate-200 group-hover:text-white truncate leading-tight">
          {{ $current->name ?: '@' . $current->blueskyAccount->handle }}
        </span>
        <span class="block text-[10px] text-slate-500 truncate leading-tight">{{ '@' . $current->blueskyAccount->handle }}</span>
      </span>

      @if ($others->isNotEmpty())
        <span class="shrink-0 text-[9px] font-extrabold tracking-widest text-sky-400 bg-sky-500/10 border border-sky-500/20 px-1.5 py-0.5 rounded leading-none whitespace-nowrap select-none">他{{ $others->count() }}件</span>
      @endif
      <x-icon name="chevron-down" class="js-account-switcher-chevron h-3.5 w-3.5 shrink-0 text-slate-500 group-hover:text-sky-400 transition duration-300" stroke-width="2.5" />
    </button>

    <!-- 開閉するパネル（切り替え先・アカウントの追加・一括ログアウト） -->
    <div class="js-account-switcher-panel hidden mt-2 space-y-2">
      @if ($others->isNotEmpty())
        <p class="text-[10px] font-extrabold tracking-widest text-slate-500 uppercase select-none">切り替え先</p>
      @endif

      @foreach ($others as $account)
        <form action="{{ route('accounts.switch') }}" method="POST">
          @csrf
          <input type="hidden" name="user_id" value="{{ $account->id }}">
          <button type="submit" class="w-full px-2.5 py-2 rounded-xl cursor-pointer bg-slate-900/60 hover:bg-slate-800/80 border border-slate-800/80 hover:border-sky-500/40 text-left transition duration-300 flex items-center gap-2.5 group active:scale-[0.98]"
                  title="{{ '@' . $account->blueskyAccount->handle }} に切り替える">
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
              <span class="block text-xs font-bold text-slate-200 group-hover:text-white truncate leading-tight">
                {{ $account->name ?: '@' . $account->blueskyAccount->handle }}
              </span>
              <span class="block text-[10px] text-slate-500 truncate leading-tight">{{ '@' . $account->blueskyAccount->handle }}</span>
            </span>

            <span class="shrink-0 text-[10px] font-extrabold tracking-wider text-slate-500 group-hover:text-sky-400 whitespace-nowrap select-none transition duration-300">切り替え</span>
          </button>
        </form>
      @endforeach

      @if ($canAddAccount)
        <!-- アカウントの追加 -->
        <a href="{{ route('accounts.add') }}" class="w-full px-2.5 py-2 rounded-xl cursor-pointer bg-slate-900/40 hover:bg-sky-500/10 border border-dashed border-slate-800 hover:border-sky-500/40 text-slate-400 hover:text-sky-400 font-bold text-xs transition duration-300 flex items-center justify-center gap-1.5 active:scale-[0.98]"
           title="登録済みのBlueskyアカウントを、ログイン状態を保ったまま追加します">
          <x-icon name="user-plus" class="h-3.5 w-3.5 shrink-0" stroke-width="2.5" />
          <span>別のアカウントを追加</span>
        </a>
        <p class="text-[10px] text-slate-600 text-center leading-relaxed select-none px-1">
          登録済みのアカウントのみ追加できます
        </p>
      @else
        <p class="text-[10px] text-slate-600 text-center leading-relaxed select-none px-1">
          同時にログインできるのは最大{{ \App\Support\AccountSession::MAX_ACCOUNTS }}件です。
        </p>
      @endif

      @if ($others->isNotEmpty())
        <!-- すべてのアカウントから一括ログアウト -->
        <form action="{{ route('logout') }}" method="POST">
          @csrf
          <input type="hidden" name="all" value="1">
          <button type="submit" class="w-full py-2 rounded-xl cursor-pointer text-slate-500 hover:text-rose-400 font-bold text-[11px] transition duration-300">
            すべてのアカウントからログアウト
          </button>
        </form>
      @endif
    </div>
  </div>
@endif
