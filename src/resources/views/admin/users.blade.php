<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>登録ユーザー管理 | {{ config('app.name', 'SkLoG') }} Admin</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>body { font-family: 'Outfit', 'Noto Sans JP', sans-serif; background: #020617; }</style>
</head>
<body class="min-h-full p-6">

  <div class="max-w-6xl mx-auto">

    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-extrabold text-white tracking-tight">
        <a href="{{ route('admin.waitlist') }}" class="hover:opacity-80 transition">
          {{ config('app.name', 'SkLoG') }} <span class="text-sky-400">Admin</span>
        </a>
        <span class="text-slate-500 font-normal text-lg">/ 登録ユーザー管理</span>
      </h1>
      <div class="flex items-center gap-3">
        <a href="{{ route('admin.security') }}" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5 duration-200">
          <x-icon name="lock" class="h-3.5 w-3.5 text-sky-400" stroke-width="2" />
          セキュリティ設定
        </a>
        <form action="{{ route('admin.logout') }}" method="POST">
          @csrf
          <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white text-xs font-bold transition cursor-pointer">
            ログアウト
          </button>
        </form>
      </div>
    </div>

    <!-- ナビゲーションタブ -->
    <div class="flex border-b border-slate-800 mb-6 text-sm font-semibold select-none">
      <a href="{{ route('admin.waitlist') }}" class="px-5 py-2.5 text-slate-500 hover:text-slate-300 transition duration-200">
        申請管理 (未登録)
      </a>
      <a href="{{ route('admin.users') }}" class="px-5 py-2.5 border-b-2 border-sky-500 text-sky-400 -mb-px transition duration-200">
        登録ユーザー管理
      </a>
    </div>

    @if (!config('services.admin.2fa_enabled'))
      <div class="mb-6 p-4 rounded-xl bg-amber-500/5 border border-amber-500/20 text-slate-300 text-xs flex items-center justify-between shadow-md">
        <div class="flex items-center gap-2.5">
          <x-icon name="alert" class="h-4 w-4 text-amber-400 shrink-0" />
          <div>
            <span class="font-bold text-amber-400">セキュリティ警告:</span> 二要素認証 (2FA) が現在有効化されていません。安全のため、有効化することを強く推奨します。
          </div>
        </div>
        <a href="{{ route('admin.security') }}" class="px-3 py-1.5 rounded bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 text-[10px] font-bold transition whitespace-nowrap duration-200">
          設定する →
        </a>
      </div>
    @endif

    @if (session('success'))
      <div class="mb-6 p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm">
        {{ session('success') }}
      </div>
    @endif
    @if (session('error'))
      <div class="mb-6 p-4 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm">
        {{ session('error') }}
      </div>
    @endif

    <!-- フィルターフォーム -->
    <div class="mb-6 p-4 rounded-xl bg-slate-900 border border-slate-800">
      <form action="{{ route('admin.users') }}" method="GET" class="flex flex-col sm:flex-row items-end sm:items-center gap-4">
        <!-- ハンドル検索 -->
        <div class="w-full sm:w-auto flex-1">
          <label for="handle" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">ハンドル</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500 text-sm">@</span>
            <input type="text" name="handle" id="handle" 
                   value="{{ request('handle') }}"
                   placeholder="username" 
                   class="w-full pl-7 pr-3 py-2 rounded-lg bg-slate-950 border border-slate-800 text-white placeholder-slate-600 text-xs focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition duration-200">
          </div>
        </div>

        <!-- 同期状況フィルタ -->
        <div class="w-full sm:w-56">
          <label for="sync_status" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">同期状況</label>
          <x-select name="sync_status" id="sync_status">
            <option value="">すべて</option>
            <option value="active" {{ request('sync_status') === 'active' ? 'selected' : '' }}>自動同期: ON</option>
            <option value="inactive" {{ request('sync_status') === 'inactive' ? 'selected' : '' }}>自動同期: OFF</option>
            <option value="error" {{ request('sync_status') === 'error' ? 'selected' : '' }}>同期エラーあり</option>
            <option value="syncing" {{ request('sync_status') === 'syncing' ? 'selected' : '' }}>同期処理中</option>
            <option value="inactive_30d" {{ request('sync_status') === 'inactive_30d' ? 'selected' : '' }}>30日以上未同期</option>
          </x-select>
        </div>

        <!-- ボタン -->
        <div class="flex items-center gap-2 w-full sm:w-auto">
          <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold transition cursor-pointer active:scale-[0.98] whitespace-nowrap">
            検索
          </button>
          @if(request()->filled('handle') || request()->filled('sync_status'))
            <a href="{{ route('admin.users') }}" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white text-xs font-bold transition text-center cursor-pointer whitespace-nowrap">
              クリア
            </a>
          @endif
          <a href="{{ request()->fullUrl() }}" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer whitespace-nowrap" title="現在の条件で情報を更新します">
            <x-icon name="sync" class="h-3.5 w-3.5" stroke-width="2.5" />
            <span>更新</span>
          </a>
        </div>
      </form>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
      @if ($entries->isEmpty())
        <div class="p-12 text-center text-slate-500 text-sm">登録ユーザーはいません</div>
      @else
        <table class="w-full table-fixed text-sm">
          <thead>
            <tr class="border-b border-slate-800 text-[10px] font-bold text-slate-500 uppercase tracking-wider bg-slate-950/40">
              <th class="w-[40%] px-4 py-3 text-left">Bluesky アカウント</th>
              <th class="w-[30%] px-4 py-3 text-left">同期状況</th>
              <th class="w-[18%] px-4 py-3 text-left">登録日時</th>
              <th class="w-[12%] px-4 py-3 text-right">操作</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/50">
            @foreach ($entries as $entry)
              <tr class="hover:bg-slate-800/20 transition">
                <!-- Blueskyアカウント情報 (アバター、表示名、ハンドル、メール、自己紹介を一括表示) -->
                <td class="px-4 py-3 align-top">
                  <div class="flex items-start gap-3">
                    <!-- アバター画像 -->
                    @if ($entry->avatar_url)
                      <img src="{{ $entry->avatar_url }}" alt="avatar" class="w-9 h-9 rounded-full shrink-0 border border-slate-700/60 object-cover shadow-inner bg-slate-950">
                    @else
                      <div class="w-9 h-9 rounded-full shrink-0 bg-slate-800 flex items-center justify-center text-[10px] font-bold text-slate-400 border border-slate-700/60 shadow-inner">
                        BS
                      </div>
                    @endif
                    
                    <div class="min-w-0 flex-1">
                      <!-- 表示名 -->
                      <div class="font-bold text-white text-xs truncate">{{ $entry->display_name ?? $entry->handle }}</div>
                      <!-- ハンドルリンク -->
                      <div class="mt-0.5">
                        <a href="https://bsky.app/profile/{{ $entry->handle }}" target="_blank" class="text-[11px] text-sky-400 hover:text-sky-300 hover:underline transition inline-flex items-center gap-0.5">
                          <span>{{ '@' . $entry->handle }}</span>
                          <x-icon name="link" class="h-2.5 w-2.5 shrink-0 opacity-60" stroke-width="2.5" />
                        </a>
                      </div>
                      <!-- メールアドレス -->
                      <div class="text-[10px] text-slate-400 mt-1 select-all" title="登録メールアドレス">
                        {{ $entry->email }}
                      </div>
                      <!-- 自己紹介 (description) -->
                      @if ($entry->description)
                        <div class="text-[10px] text-slate-500 mt-1.5 leading-relaxed line-clamp-2 break-all" title="{{ $entry->description }}">
                          {{ $entry->description }}
                        </div>
                      @endif
                    </div>
                  </div>
                </td>
                
                <!-- 同期状況 -->
                <td class="px-4 py-3 align-top text-xs">
                  @php
                    $account = $entry->inviteCode?->usedBy?->blueskyAccount;
                  @endphp
                  @if ($account)
                    <div class="space-y-1">
                      <!-- 自動同期状況 -->
                      <div class="flex items-center gap-1.5">
                        @if ($account->is_syncing)
                          <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                          <span class="text-amber-400 text-[10px] font-bold">同期中...</span>
                        @elseif ($account->sync_error_type)
                          <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                          <span class="text-rose-400 text-[10px] font-bold" title="{{ $account->sync_error_message }}">
                            同期エラー
                            @if ($account->auth_failure_count > 0)
                              <span class="text-[9px] text-rose-400/80 font-medium">({{ $account->auth_failure_count }}回連続)</span>
                            @endif
                          </span>
                        @elseif ($account->is_auto_sync_enabled)
                          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                          <span class="text-emerald-400 text-[10px] font-bold">自動同期: ON</span>
                        @else
                          <span class="w-2 h-2 rounded-full bg-slate-500"></span>
                          <span class="text-slate-400 text-[10px] font-bold">自動同期: OFF</span>
                        @endif
                      </div>

                      <!-- 最終同期時間 -->
                      <div class="text-[10px] text-slate-500">
                        最終: {{ $account->last_synced_at ? $account->last_synced_at->format('m/d H:i') : '未同期' }}
                      </div>

                      <!-- 同期エラーメッセージ -->
                      @if ($account->sync_error_message)
                        <div class="text-[9px] text-rose-500/80 leading-tight break-all" title="{{ $account->sync_error_message }}">
                          {{ $account->sync_error_message }}
                        </div>
                      @endif
                    </div>
                  @else
                    <span class="text-slate-600 text-[10px]">—</span>
                  @endif
                </td>

                <!-- 登録日時 -->
                <td class="px-4 py-3 align-top text-slate-500 text-[11px] whitespace-nowrap">
                  {{ $entry->inviteCode?->used_at ? $entry->inviteCode->used_at->format('Y/m/d H:i') : '不明' }}
                </td>
                
                <!-- 操作 -->
                <td class="px-4 py-3 align-top text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-2">
                    <form action="{{ route('admin.users.force_delete', $entry) }}" method="POST" class="inline js-confirm-form">
                      @csrf
                      <button type="submit"
                              data-confirm-title="ユーザーの強制退会"
                              data-confirm-message="本当に &#64;{{ $entry->handle }} を強制退会させますか？<br>この操作を実行すると、ユーザーアカウント、暗号化されたアプリパスワード、取得済みのすべてのカレンダーデータ（投稿ログなど）がサーバーから完全に物理削除され、復元することはできません。"
                              data-confirm-action="強制退会させる"
                              data-confirm-btn-color="bg-rose-600 hover:bg-rose-500"
                              class="px-2 py-0.5 rounded border border-rose-800/40 hover:border-rose-500/50 bg-rose-950/20 hover:bg-rose-900/30 text-rose-300 text-[9px] font-bold transition cursor-pointer active:scale-[0.98]">
                        強制退会
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>

    <p class="mt-4 text-xs text-slate-600 text-right">
      @if(request()->filled('handle') || request()->filled('sync_status'))
        検索結果: <span class="text-sky-400 font-bold">{{ $entries->count() }}</span> 件 / 
      @endif
      登録ユーザー合計: {{ $stats['registered'] }} 件
    </p>
  </div>

  @include('layouts._footer')

  <x-confirm-modal />

</body>
</html>
