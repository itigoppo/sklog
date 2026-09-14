@php
  // ログイン中のハンドル名はここでは出さない。招待リンクを開いた人が、
  // ログイン中の人と同じとは限らないため
  $isJoin = $to === 'join';
  $pageTitle = $isJoin ? '参加申請' : '新規登録';
@endphp
<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <x-google-analytics />
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $pageTitle }} | {{ config('app.name', 'SkLoG') }}</title>
  <meta name="robots" content="noindex,nofollow">
  @vite(['resources/css/app.css'])
</head>
<body class="min-h-full flex items-center justify-center p-4">
  <div class="w-full max-w-md">

    <div class="text-center mb-8">
      <a href="/" class="inline-flex items-center gap-2.5 cursor-pointer">
        <x-icon name="logo" class="w-[43px] h-[43px]" />
        <h1 class="text-2xl font-extrabold tracking-tight text-white">{{ config('app.name', 'SkLoG') }}</h1>
      </a>
    </div>

    <div class="glass-card rounded-2xl p-8 space-y-6">
      <h2 class="text-xl font-bold text-white flex items-center gap-2">
        <x-icon name="alert" class="h-6 w-6 text-amber-400" stroke-width="2" />
        ログアウトが必要です
      </h2>

      <div class="p-4 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs leading-relaxed">
        このブラウザではすでにログイン中のアカウントがあります。<br>
        {{ $isJoin ? '参加申請' : '登録' }}を続けると、<span class="font-bold">ログイン中のアカウントはすべてログアウトされます。</span>
      </div>

      <div class="p-4 rounded-lg bg-slate-900/60 border border-slate-800 text-xs leading-relaxed text-slate-400">
        すでにご利用中のアカウントに追加したい場合は、ログアウトせずに
        <span class="font-bold text-slate-200">アカウント追加画面</span>から
        {{ $isJoin ? '手続きしてください' : '招待コードを入力してください' }}。
      </div>

      <form action="{{ route('logout_and_continue') }}" method="POST" class="space-y-3">
        @csrf
        <input type="hidden" name="to" value="{{ $to }}">
        @if ($inviteCode)
          <input type="hidden" name="invite_code" value="{{ $inviteCode }}">
        @endif
        <button type="submit"
                class="w-full py-3.5 px-4 rounded-lg cursor-pointer bg-linear-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-semibold text-sm shadow-lg shadow-sky-950/50 focus:outline-none transition duration-300 active:scale-[0.98]">
          ログアウトして{{ $isJoin ? '申請' : '登録' }}に進む ➔
        </button>
      </form>

      <div class="text-center">
        <a href="{{ route('accounts.add', $isJoin ? [] : ['mode' => 'register']) }}"
           class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-sky-400 font-semibold transition duration-300 cursor-pointer">
          <x-icon name="user-plus" class="h-3.5 w-3.5" stroke-width="2.5" />
          ログアウトせずにアカウント追加画面へ
        </a>
      </div>
    </div>

    @include('layouts._footer')
  </div>
</body>
</html>
