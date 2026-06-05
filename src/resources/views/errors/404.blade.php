<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <x-google-analytics />
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ページが見つかりません (404) | {{ config('app.name', 'SkLoG') }}</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Noto+Sans+JP:wght@300;400;700&display=swap" rel="stylesheet">

  <!-- Tailwind CSS (Vite経由) -->
  @vite(['resources/css/app.css', 'resources/js/app.js'])

  <style>
    body {
      font-family: 'Outfit', 'Noto Sans JP', sans-serif;
      background: radial-gradient(circle at top, rgba(30, 41, 59, 0.45) 0%, rgba(2, 6, 23, 1) 100%);
    }
    .glass-card {
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
    }
    .glow-effect {
      position: absolute;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(56, 189, 248, 0.12) 0%, rgba(56, 189, 248, 0) 70%);
      top: -100px;
      left: 50%;
      transform: translateX(-50%);
      pointer-events: none;
      z-index: 0;
    }
    @keyframes butterfly-float {
      0%, 100% {
        transform: translateY(0) scale(1);
        filter: drop-shadow(0 0 10px rgba(56, 189, 248, 0.2));
      }
      50% {
        transform: translateY(-8px) scale(1.03);
        filter: drop-shadow(0 0 20px rgba(56, 189, 248, 0.4));
      }
    }
    .animate-butterfly {
      animation: butterfly-float 4s ease-in-out infinite;
    }
  </style>
</head>
<body class="h-full flex flex-col justify-center items-center p-4 relative overflow-hidden">

  <div class="glow-effect"></div>

  <div class="w-full max-w-lg z-10 text-center space-y-8 select-none">
    
    <!-- アニメーションするブランド蝶ロゴ -->
    <div class="flex justify-center mb-2">
      <div class="w-20 h-20 rounded-3xl bg-linear-to-br from-sky-400 to-indigo-600 flex items-center justify-center shadow-lg shadow-sky-500/20 animate-butterfly">
        <x-icon name="logo" class="w-[43px] h-[43px]" />
      </div>
    </div>

    <!-- 巨大な404エラーテキスト -->
    <div class="space-y-2">
      <h1 class="text-8xl md:text-9xl font-extrabold tracking-widest bg-linear-to-r from-sky-400 via-indigo-400 to-purple-500 bg-clip-text text-transparent">
        404
      </h1>
      <p class="text-sky-400 font-bold uppercase tracking-widest text-xs md:text-sm">Page Not Found</p>
    </div>

    <!-- エラー説明カード -->
    <div class="glass-card rounded-2xl p-6 md:p-8 space-y-6">
      <div class="space-y-2.5">
        <h2 class="text-lg md:text-xl font-bold text-white leading-snug">お探しのページが見つかりません</h2>
        <p class="text-slate-400 text-xs md:text-sm leading-relaxed max-w-md mx-auto">
          アクセスしようとしたURLが正しいかご確認ください。<br>ページが移動または削除された可能性があります。
        </p>
      </div>

      <!-- アクションボタン -->
      <div class="pt-2">
        <a href="/" class="inline-flex items-center justify-center px-6 py-3.5 rounded-xl cursor-pointer bg-linear-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-bold text-xs shadow-lg shadow-sky-950/50 hover:shadow-sky-500/15 focus:outline-none transition duration-300 transform hover:scale-[1.05] active:scale-[0.98] gap-2">
          <x-icon name="home" class="h-4 w-4" stroke-width="2.5" />
          トップページへ戻る
        </a>
      </div>
    </div>

    @include('layouts._footer')
  </div>

</body>
</html>
