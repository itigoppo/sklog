<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>申請一覧 | {{ config('app.name', 'SkLoG') }} Admin</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>body { font-family: 'Outfit', 'Noto Sans JP', sans-serif; background: #020617; }</style>
</head>
<body class="min-h-full p-6">

  <div class="max-w-4xl mx-auto">

    <div class="flex items-center justify-between mb-8">
      <h1 class="text-2xl font-extrabold text-white tracking-tight">{{ config('app.name', 'SkLoG') }} <span class="text-sky-400">Admin</span> <span class="text-slate-500 font-normal text-lg">/ 申請一覧</span></h1>
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
      <form action="{{ route('admin.waitlist') }}" method="GET" class="flex flex-col sm:flex-row items-end sm:items-center gap-4">
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

        <!-- ステータスフィルタ -->
        <div class="w-full sm:w-48">
          <label for="status" class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">ステータス</label>
          <select name="status" id="status" 
                  class="w-full px-3 py-2 rounded-lg bg-slate-950 border border-slate-800 text-white text-xs focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition duration-200 cursor-pointer">
            <option value="">すべて</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>申請中</option>
            <option value="invited" {{ request('status') === 'invited' ? 'selected' : '' }}>招待済み</option>
            <option value="registered" {{ request('status') === 'registered' ? 'selected' : '' }}>登録済み</option>
            <option value="expired_pending" {{ request('status') === 'expired_pending' ? 'selected' : '' }}>再送要望</option>
          </select>
        </div>

        <!-- ボタン -->
        <div class="flex items-center gap-2 w-full sm:w-auto">
          <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold transition cursor-pointer active:scale-[0.98] whitespace-nowrap">
            検索
          </button>
          @if(request()->filled('handle') || request()->filled('status'))
            <a href="{{ route('admin.waitlist') }}" class="w-full sm:w-auto px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white text-xs font-bold transition text-center cursor-pointer whitespace-nowrap">
              クリア
            </a>
          @endif
        </div>
      </form>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
      @if ($entries->isEmpty())
        <div class="p-12 text-center text-slate-500 text-sm">申請はまだありません</div>
      @else
        <table class="w-full table-fixed text-sm">
          <thead>
            <tr class="border-b border-slate-800 text-[10px] font-bold text-slate-500 uppercase tracking-wider bg-slate-950/40">
              <th class="w-[44%] px-4 py-3 text-left">Bluesky アカウント</th>
              <th class="w-[18%] px-4 py-3 text-left">メールアドレス</th>
              <th class="w-[11%] px-4 py-3 text-left">ステータス</th>
              <th class="w-[13%] px-4 py-3 text-left">申請日時</th>
              <th class="w-[14%] px-4 py-3 text-right">操作</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/50">
            @foreach ($entries as $entry)
              <tr class="hover:bg-slate-800/20 transition">
                <!-- Blueskyアカウント情報 (アバター、表示名、ハンドル、自己紹介を一括表示) -->
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
                      <!-- 自己紹介 (description) -->
                      @if ($entry->description)
                        <div class="text-[10px] text-slate-400 mt-1.5 leading-relaxed line-clamp-2 break-all" title="{{ $entry->description }}">
                          {{ $entry->description }}
                        </div>
                      @endif
                    </div>
                  </div>
                </td>
                
                <!-- メールアドレス -->
                <td class="px-4 py-3 align-top text-slate-400 text-xs break-all select-all">{{ $entry->email }}</td>
                
                <!-- ステータス -->
                <td class="px-4 py-3 align-top">
                  @if ($entry->status === 'pending')
                    <span class="px-2 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-[9px] font-extrabold tracking-wider whitespace-nowrap inline-block">申請中</span>
                  @elseif ($entry->status === 'invited')
                    @if ($entry->inviteCode && $entry->inviteCode->isExpired())
                      <span class="px-2 py-0.5 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-[9px] font-extrabold tracking-wider whitespace-nowrap inline-block">期限切れ</span>
                      @if ($entry->inviteCode->expires_at)
                        <div class="text-[9px] text-slate-500 mt-1.5 font-medium whitespace-nowrap" title="期限: {{ $entry->inviteCode->expires_at->format('Y/m/d H:i') }}">
                          期限: {{ $entry->inviteCode->expires_at->format('m/d H:i') }}
                        </div>
                      @endif
                    @else
                      <span class="px-2 py-0.5 rounded-full bg-sky-500/10 border border-sky-500/20 text-sky-400 text-[9px] font-extrabold tracking-wider whitespace-nowrap inline-block">招待済み</span>
                      @if ($entry->inviteCode && $entry->inviteCode->expires_at)
                        <div class="text-[9px] text-slate-400 mt-1.5 font-medium whitespace-nowrap" title="期限: {{ $entry->inviteCode->expires_at->format('Y/m/d H:i') }}">
                          期限: {{ $entry->inviteCode->expires_at->format('m/d H:i') }}
                        </div>
                      @endif
                    @endif
                  @elseif ($entry->status === 'expired_pending')
                    <span class="px-2 py-0.5 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-[9px] font-extrabold tracking-wider whitespace-nowrap inline-block">再送要望</span>
                    @if ($entry->inviteCode && $entry->inviteCode->expires_at)
                      <div class="text-[9px] text-rose-400/80 mt-1.5 font-medium whitespace-nowrap" title="期限切れ: {{ $entry->inviteCode->expires_at->format('Y/m/d H:i') }}">
                        期限切れ: {{ $entry->inviteCode->expires_at->format('m/d H:i') }}
                      </div>
                    @endif
                  @else
                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[9px] font-extrabold tracking-wider whitespace-nowrap inline-block">登録済み</span>
                  @endif
                </td>
                
                <!-- 申請日時 -->
                <td class="px-4 py-3 align-top text-slate-500 text-[11px] whitespace-nowrap">{{ $entry->created_at->format('Y/m/d H:i') }}</td>
                
                <!-- 操作 -->
                <td class="px-4 py-3 align-top text-right whitespace-nowrap">
                  @if ($entry->isPending())
                    <form action="{{ route('admin.waitlist.invite', $entry) }}" method="POST" class="inline js-confirm-form">
                      @csrf
                      <button type="submit"
                              data-confirm-title="招待メール送信"
                              data-confirm-message="&#64;{{ $entry->handle }} に招待メールを送信しますか？"
                              data-confirm-action="送信する"
                              data-confirm-btn-color="bg-sky-600 hover:bg-sky-500"
                              class="px-2.5 py-1 rounded bg-sky-600 hover:bg-sky-500 text-white text-[10px] font-bold transition cursor-pointer active:scale-[0.98]">
                        招待メール送信
                      </button>
                    </form>
                  @elseif ($entry->status === 'registered')
                    <span class="text-[10px] text-emerald-500/60 font-medium whitespace-nowrap">登録完了</span>
                  @else
                    {{-- 招待済みのすべてのステータスで再招待を可能に --}}
                    <form action="{{ route('admin.waitlist.reinvite', $entry) }}" method="POST" class="inline js-confirm-form">
                      @csrf
                      @if ($entry->status === 'expired_pending')
                        <button type="submit"
                                data-confirm-title="再招待 (再送要望あり)"
                                data-confirm-message="&#64;{{ $entry->handle }} から再送要望が届いています。<br>期限を延長し、新しい招待メールを再送信しますか？"
                                data-confirm-action="再招待する"
                                data-confirm-btn-color="bg-rose-600 hover:bg-rose-500"
                                class="px-2.5 py-1 rounded bg-rose-600 hover:bg-rose-500 text-white text-[10px] font-extrabold transition cursor-pointer active:scale-[0.98]"
                                title="ユーザーから再送要望が届いています">
                          再招待
                        </button>
                      @elseif ($entry->inviteCode && $entry->inviteCode->isExpired())
                        <button type="submit"
                                data-confirm-title="再招待"
                                data-confirm-message="&#64;{{ $entry->handle }} の期限を延長し、新しい招待メールを再送信しますか？"
                                data-confirm-action="再招待する"
                                data-confirm-btn-color="bg-amber-600 hover:bg-amber-500"
                                class="px-2.5 py-1 rounded bg-amber-600 hover:bg-amber-500 text-white text-[10px] font-bold transition cursor-pointer active:scale-[0.98]">
                          再招待
                        </button>
                      @else
                        <button type="submit"
                                data-confirm-title="再招待"
                                data-confirm-message="&#64;{{ $entry->handle }} はまだ招待期限内ですが、現在のコードを無効化して新しい招待メールを再送信しますか？"
                                data-confirm-action="再招待する"
                                data-confirm-btn-color="bg-slate-700 hover:bg-slate-600"
                                class="px-2.5 py-1 rounded bg-slate-700 hover:bg-slate-600 text-slate-300 hover:text-white text-[10px] font-semibold transition cursor-pointer active:scale-[0.98]"
                                title="期限内ですが、現在のコードを無効にして新コードを再送します">
                          再招待
                        </button>
                      @endif
                    </form>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>

    <p class="mt-4 text-xs text-slate-600 text-right">
      @if(request()->filled('handle') || request()->filled('status'))
        検索結果: <span class="text-sky-400 font-bold">{{ $entries->count() }}</span> 件 / 
      @endif
      申請合計: {{ $stats['total'] }} 件
      （申請中: {{ $stats['pending'] }}
      / 招待済み: {{ $stats['invited'] }}
      / 登録済み: {{ $stats['registered'] }}
      / 再送要望: {{ $stats['expired_pending'] }}）
    </p>
  </div>

  @include('layouts._footer')

  <x-confirm-modal />

</body>
</html>
