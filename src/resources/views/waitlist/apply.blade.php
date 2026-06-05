<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>参加申請 | {{ config('app.name', 'SkLoG') }}</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Noto+Sans+JP:wght@300;400;700&display=swap" rel="stylesheet">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>
    body { font-family: 'Outfit', 'Noto Sans JP', sans-serif; background: radial-gradient(circle at top, rgba(30,41,59,0.5) 0%, rgba(2,6,23,1) 100%); }
    .glass-card { background: rgba(15,23,42,0.65); backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 20px 50px rgba(0,0,0,0.4); }
    @keyframes butterfly-float { 0%,100%{transform:translateY(0) scale(1);filter:drop-shadow(0 0 10px rgba(56,189,248,0.2))} 50%{transform:translateY(-8px) scale(1.03);filter:drop-shadow(0 0 20px rgba(56,189,248,0.4))} }
    .animate-butterfly { animation: butterfly-float 4s ease-in-out infinite; }
  </style>
</head>
<body class="min-h-full flex flex-col justify-center items-center p-4">

  <div class="w-full max-w-md">

    <div class="text-center mb-8 select-none">
      <a href="/" class="inline-block group cursor-pointer">
        <div class="flex justify-center mb-4">
          <div class="w-20 h-20 rounded-3xl bg-linear-to-br from-sky-400 to-indigo-600 flex items-center justify-center shadow-lg shadow-sky-500/20 group-hover:scale-105 transition duration-300 animate-butterfly">
            <x-icon name="logo" class="w-[43px] h-[43px]" />
          </div>
        </div>
        <h1 class="text-4xl font-extrabold tracking-tight bg-linear-to-r from-sky-400 via-indigo-400 to-purple-500 bg-clip-text text-transparent group-hover:from-sky-300 group-hover:to-purple-400 transition duration-300">
          {{ config('app.name', 'SkLoG') }}
        </h1>
      </a>
    </div>

    <div class="glass-card rounded-2xl p-8">

      <h2 class="text-xl font-bold text-white mb-2 flex items-center gap-2">
        <x-icon name="mail" class="h-6 w-6 text-sky-400" stroke-width="2" />
        参加申請
      </h2>
      <p class="text-slate-400 text-xs mb-6 leading-relaxed">メールアドレスとBlueskyハンドル名を登録してください。承認後、招待コードをメールでお送りします。</p>

      @if (session('success'))
        <div class="mb-6 p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-start gap-2">
          <x-icon name="success" class="h-4 w-4 shrink-0 mt-0.5" stroke-width="2" />
          <span>{{ session('success') }}</span>
        </div>
      @endif

      @if ($errors->any())
        <div class="mb-6 p-4 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs leading-relaxed">
          @foreach ($errors->all() as $error)
            <div class="flex items-start gap-2"><span class="mt-0.5">•</span><span>{{ $error }}</span></div>
          @endforeach
        </div>
      @endif

      <form action="{{ route('waitlist.store') }}" method="POST" class="space-y-5">
        @csrf

        <div>
          <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">メールアドレス</label>
          <input type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com"
                 class="w-full px-4 py-3 rounded-lg bg-slate-900/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition duration-300"
                 required>
          <p class="mt-1 text-[10px] text-slate-500">※メールアドレスは、招待コードの送信用にのみ使用されます</p>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Bluesky ハンドル名</label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500 text-sm">@</span>
            <input type="text" name="handle" value="{{ old('handle') }}" placeholder="username.bsky.social"
                   class="w-full pl-8 pr-4 py-3 rounded-lg bg-slate-900/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition duration-300"
                   required>
          </div>
          <p class="mt-1 text-[10px] text-slate-500">登録時に使用するハンドルと一致させてください</p>
        </div>

        <button type="submit"
                class="w-full py-3.5 px-4 rounded-lg cursor-pointer bg-linear-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-semibold text-sm shadow-lg focus:outline-none transition duration-300 active:scale-[0.98]">
          申請する ➔
        </button>
      </form>
    </div>

    <div class="text-center mt-6 space-y-3">
      <a href="/login" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-sky-400 font-semibold transition cursor-pointer">
        <x-icon name="login" class="h-3.5 w-3.5" stroke-width="2.5" />
        すでにアカウントをお持ちの方はログイン
      </a>
      <br>
      <a href="/" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-sky-400 font-semibold transition cursor-pointer">
        <x-icon name="arrow-left" class="h-3.5 w-3.5" stroke-width="2.5" />
        トップページへ戻る
      </a>
    </div>

    @include('layouts._footer')
  </div>
</body>
</html>
