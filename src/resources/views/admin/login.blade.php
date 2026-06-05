<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>管理者ログイン | {{ config('app.name', 'SkLoG') }} Admin</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>body { font-family: 'Outfit', sans-serif; background: #020617; }</style>
</head>
<body class="min-h-full flex items-center justify-center p-4">
  <div class="w-full max-w-sm">
    <h1 class="text-2xl font-extrabold text-white text-center mb-8 tracking-tight">{{ config('app.name', 'SkLoG') }} <span class="text-sky-400">Admin</span></h1>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-8">
      @if ($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-start gap-2">
          <x-icon name="alert" class="h-4 w-4 shrink-0 text-rose-400 mt-0.5" />
          <div>
            {{ $errors->first() }}
          </div>
        </div>
      @endif

      <form action="{{ route('admin.login.post') }}" method="POST" class="space-y-4">
        @csrf
        <div>
          <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">パスワード</label>
          <input type="password" name="password" autofocus
                 class="w-full px-4 py-3 rounded-lg bg-slate-950 border border-slate-700 text-white text-sm focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition">
        </div>

        @if (config('services.admin.2fa_enabled'))
          <div>
            <label class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2 flex justify-between items-center">
              <span>ワンタイムパスワード (2FA)</span>
              <span class="text-[10px] text-sky-400 normal-case font-normal">認証アプリの6桁コード</span>
            </label>
            <input type="text" name="totp_code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" placeholder="123456" maxlength="6"
                   class="w-full px-4 py-3 rounded-lg bg-slate-950 border border-slate-700 text-white focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition text-center tracking-[0.5em] font-mono text-lg font-bold">
          </div>
        @endif

        <button type="submit"
                class="w-full py-3 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-bold text-sm transition active:scale-[0.98] cursor-pointer mt-2">
          ログイン
        </button>
      </form>
    </div>

    <div class="text-center mt-6">
      <a href="/" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-sky-400 font-semibold transition duration-300 cursor-pointer">
        <x-icon name="arrow-left" class="h-3.5 w-3.5" stroke-width="2.5" />
        トップページへ戻る
      </a>
    </div>

    @include('layouts._footer')
  </div>
</body>
</html>
