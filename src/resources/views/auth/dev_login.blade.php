<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>開発用ログイン | {{ config('app.name', 'SkLoG') }}</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    body {
      font-family: 'Outfit', 'Noto Sans JP', sans-serif;
      background: radial-gradient(circle at top, rgba(30, 41, 59, 0.5) 0%, rgba(2, 6, 23, 1) 100%);
    }
  </style>
</head>
<body class="min-h-full flex items-center justify-center p-4">
  <div class="w-full max-w-md space-y-4">
    <div class="p-4 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs leading-relaxed">
      <p class="font-bold mb-1">開発用ログイン</p>
      <p>Bluesky の認証なしでログインします。APP_ENV=local かつ DEV_LOGIN=true のときだけ表示されます。</p>
      @if (\App\Support\LocalDev::fakeBlueskyEnabled())
        <p class="mt-1">BLUESKY_FAKE=true のため、同期や削除は本物の Bluesky につながりません。</p>
      @else
        <p class="mt-1 text-rose-300 font-bold">BLUESKY_FAKE=false のため、同期や「Blueskyからも削除」は本物の Bluesky に対して行われます。</p>
      @endif
    </div>

    <div class="rounded-xl border border-slate-800 bg-slate-900/60 divide-y divide-slate-800">
      @forelse ($accounts as $account)
        <a href="{{ route('dev.login.as', ['handle' => $account->handle]) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-800/60 transition">
          <span class="min-w-0">
            <span class="block text-sm font-bold text-white truncate">{{ $account->user?->name ?: $account->handle }}</span>
            <span class="block text-xs text-slate-400 truncate">{{ '@' . $account->handle }}</span>
          </span>
          <span class="text-xs font-bold text-sky-400 shrink-0">ログイン ➔</span>
        </a>
      @empty
        <p class="px-4 py-6 text-center text-xs text-slate-400">アカウントがありません。make seed でテスト用のアカウントを作れます。</p>
      @endforelse
    </div>

    <a href="{{ route('login') }}" class="block text-center text-xs text-slate-500 hover:text-sky-400 transition">通常のログインへ戻る</a>
  </div>
</body>
</html>
