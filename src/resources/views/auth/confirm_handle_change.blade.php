<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ハンドル変更の確認 | {{ config('app.name', 'SkLoG') }}</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Noto+Sans+JP:wght@300;400;700&display=swap" rel="stylesheet">

  @vite(['resources/css/app.css', 'resources/js/app.js'])

  <style>
    body {
      font-family: 'Outfit', 'Noto Sans JP', sans-serif;
      background: radial-gradient(circle at top, rgba(30, 41, 59, 0.5) 0%, rgba(2, 6, 23, 1) 100%);
    }
    .glass-card {
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
    }
    @keyframes butterfly-float {
      0%, 100% { transform: translateY(0) scale(1); filter: drop-shadow(0 0 10px rgba(56, 189, 248, 0.2)); }
      50% { transform: translateY(-8px) scale(1.03); filter: drop-shadow(0 0 20px rgba(56, 189, 248, 0.4)); }
    }
    .animate-butterfly { animation: butterfly-float 4s ease-in-out infinite; }
    .glow-effect {
      position: absolute; width: 300px; height: 300px;
      background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, rgba(56, 189, 248, 0) 70%);
      top: -150px; left: -150px; pointer-events: none; z-index: 0;
    }
    .glow-effect-right {
      position: absolute; width: 400px; height: 400px;
      background: radial-gradient(circle, rgba(147, 51, 234, 0.1) 0%, rgba(147, 51, 234, 0) 70%);
      bottom: -200px; right: -200px; pointer-events: none; z-index: 0;
    }
  </style>
</head>
<body class="min-h-full flex flex-col justify-center items-center p-4 relative">

  <!-- 背景装飾 -->
  <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
    <div class="glow-effect"></div>
    <div class="glow-effect-right"></div>
  </div>

  <div class="w-full max-w-lg z-10">

    <!-- ロゴ -->
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

    <!-- 確認カード -->
    <div class="glass-card rounded-2xl p-8 relative overflow-hidden">

      <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
        <x-icon name="alert" class="h-6 w-6 text-amber-400" stroke-width="2" />
        ハンドル変更の確認
      </h2>

      <div class="space-y-6 text-sm leading-relaxed text-slate-300">
        <p>
          Blueskyアカウントのハンドル名が変更されていることを検知しました。
        </p>

        <!-- ハンドル比較表示 -->
        <div class="p-4 rounded-xl bg-slate-900/80 border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-around gap-4 text-center font-mono">
          <div>
            <span class="block text-[10px] text-slate-500 uppercase font-sans font-bold tracking-wider mb-1">旧ハンドル</span>
            <span class="text-slate-400 text-xs truncate max-w-[180px] block">{{ '@' . $oldHandle }}</span>
          </div>
          <x-icon name="chevron-right" class="h-5 w-5 text-sky-400 mx-auto rotate-90 sm:rotate-0" stroke-width="2.5" />
          <div>
            <span class="block text-[10px] text-sky-400 uppercase font-sans font-bold tracking-wider mb-1">新ハンドル</span>
            <span class="text-white text-sm font-bold truncate max-w-[180px] block">{{ '@' . $newHandle }}</span>
          </div>
        </div>

        <p class="text-slate-300">
          カレンダーのデータを引き継ぎ、新しいハンドル名で本サービスを引き続き公開・使用しますか？
        </p>

        <div class="border-t border-slate-800/80 pt-6 space-y-4">
          <!-- 同意ボタンフォーム -->
          <form action="{{ url('/login/confirm-handle-change') }}" method="POST">
            @csrf
            <input type="hidden" name="action" value="consent">
            <button type="submit" class="w-full py-3.5 px-4 rounded-lg cursor-pointer bg-linear-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-bold text-sm shadow-lg shadow-sky-950/50 hover:shadow-sky-500/10 focus:outline-none transition duration-300 transform active:scale-[0.98] flex items-center justify-center gap-2">
              はい、新しいハンドルに引き継いで使い続ける
            </button>
          </form>

          <!-- 削除（退会）ボタンフォーム -->
          <form action="{{ url('/login/confirm-handle-change') }}" method="POST" onsubmit="return confirm('本当にアカウントとカレンダーの全データを完全に削除しますか？この操作は取り消せません。');">
            @csrf
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="w-full py-3 px-4 rounded-lg cursor-pointer bg-rose-950/20 hover:bg-rose-900/30 border border-rose-800/40 hover:border-rose-500/50 text-rose-300 hover:text-rose-200 text-xs font-semibold transition duration-300 transform active:scale-[0.98] flex items-center justify-center gap-2">
              いいえ、カレンダーの利用を停止し、全データを削除する（退会）
            </button>
          </form>

          <!-- キャンセルリンクフォーム -->
          <form action="{{ url('/login/confirm-handle-change') }}" method="POST">
            @csrf
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="w-full py-2 text-center text-xs text-slate-500 hover:text-slate-400 transition duration-300 cursor-pointer focus:outline-none">
              ログインをキャンセル
            </button>
          </form>
        </div>
      </div>
    </div>

    @include('layouts._footer')
  </div>
</body>
</html>
