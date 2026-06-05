<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>申請完了 | {{ config('app.name', 'SkLoG') }}</title>
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
    @keyframes scale-up-check { 0%{transform:scale(0.8);opacity:0} 100%{transform:scale(1);opacity:1} }
    .animate-check { animation: scale-up-check 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
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

    <div class="glass-card rounded-2xl p-8 text-center">

      <!-- 成功チェックマークアイコン -->
      <div class="flex justify-center mb-6">
        <div class="w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shadow-lg shadow-emerald-500/10 animate-check">
          <x-icon name="check" class="w-8 h-8" stroke-width="3" />
        </div>
      </div>

      <h2 class="text-xl font-bold text-white mb-2">
        申請を受け付けました
      </h2>
      
      @if (session('handle'))
        <p class="text-slate-300 text-xs mb-6">
          Blueskyハンドル: <span class="font-semibold text-sky-400">&#64;{{ session('handle') }}</span>
        </p>
      @else
        <p class="text-slate-400 text-xs mb-6 leading-relaxed">
          ウェイトリストへの参加申請を正常に受け付けました。
        </p>
      @endif

      <!-- メール不送信に関する重要なお知らせ -->
      <div class="p-4 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs leading-relaxed text-left space-y-2">
        <div class="flex items-center gap-1.5 font-bold">
          <x-icon name="alert" class="h-4 w-4 shrink-0" stroke-width="2.5" />
          <span>【重要】自動返信メールは送信されません</span>
        </div>
        <p>
          申請完了の自動返信メールは送信しておりません。<br>
          管理者によるアカウントの確認と承認が完了しましたら、ご入力いただいたメールアドレス宛に招待コードを含むメールをお送りいたします。招待メールが届くまで今しばらくお待ちください。
        </p>
      </div>

      <!-- アクションボタン -->
      <div class="mt-8">
        <a href="/"
           class="inline-block w-full py-3.5 px-4 rounded-lg cursor-pointer bg-linear-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-semibold text-sm shadow-lg focus:outline-none transition duration-300 active:scale-[0.98]">
          トップページへ戻る
        </a>
      </div>

    </div>

    <div class="text-center mt-6">
      <a href="/join" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-sky-400 font-semibold transition cursor-pointer">
        <x-icon name="arrow-left" class="h-3.5 w-3.5" stroke-width="2.5" />
        申請フォームへ戻る
      </a>
    </div>

    @include('layouts._footer')
  </div>
</body>
</html>
