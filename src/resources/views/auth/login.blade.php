@php
  // ログイン中に別アカウントを追加するモードかどうか
  $currentHandle = Auth::check() ? Auth::user()->blueskyAccount?->handle : null;
  $isAdding = ($isAdding ?? false) && $currentHandle !== null;
  $pageTitle = $isAdding ? 'アカウントを追加' : 'ログイン';
@endphp
<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <x-google-analytics />
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $pageTitle }} | {{ config('app.name', 'SkLoG') }}</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

  <!-- OGP (Open Graph Protocol) -->
  <meta property="og:title" content="{{ $pageTitle }} | {{ config('app.name', 'SkLoG') }}">
  <meta property="og:description" content="Blueskyカレンダーアーカイブ「{{ config('app.name', 'SkLoG') }}」のログインページです。">
  <meta property="og:image" content="{{ url('logo.png') }}">
  <meta property="og:url" content="{{ url('/login') }}">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="{{ config('app.name', 'SkLoG') }}">
  <meta name="twitter:card" content="summary_large_image">
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Noto+Sans+JP:wght@300;400;700&display=swap" rel="stylesheet">

  <!-- Tailwind CSS (Vite経由) -->
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
    .glow-effect {
      position: absolute;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, rgba(56, 189, 248, 0) 70%);
      top: -150px;
      left: -150px;
      pointer-events: none;
      z-index: 0;
    }
    .glow-effect-right {
      position: absolute;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(147, 51, 234, 0.1) 0%, rgba(147, 51, 234, 0) 70%);
      bottom: -200px;
      right: -200px;
      pointer-events: none;
      z-index: 0;
    }
  </style>
</head>
<body class="min-h-full flex flex-col justify-center items-center p-4 relative">

  <!-- 背景装飾（はみ出しをカットして不要なスクロールバーを防止） -->
  <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
    <div class="glow-effect"></div>
    <div class="glow-effect-right"></div>
  </div>

  <div class="w-full max-w-md z-10">
    
    <!-- ロゴ & 見出し (クリックでトップページへ) -->
    <div class="text-center mb-8 select-none">
      <a href="/" class="inline-block group cursor-pointer">
        <!-- アニメーションする蝶ロゴ -->
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

    <!-- ログインカード -->
    <div class="glass-card rounded-2xl p-8 relative overflow-hidden">
      
      <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
        <x-icon name="{{ $isAdding ? 'user-plus' : 'login' }}" class="h-6 w-6 text-sky-400" stroke-width="2" />
        {{ $isAdding ? 'Blueskyアカウントを追加' : 'Blueskyアカウントで接続' }}
      </h2>

      <!-- アカウント追加モードの案内 -->
      @if ($isAdding)
        <div class="mb-6 space-y-3">
          <div class="p-4 rounded-lg bg-sky-500/10 border border-sky-500/30 text-sky-300 text-xs leading-relaxed flex items-start gap-2">
            <x-icon name="info" class="h-4 w-4 shrink-0 mt-0.5" stroke-width="2" />
            <span>
              現在 <span class="font-bold">{{ '@' . $currentHandle }}</span> でログイン中です。<br>
              ログイン状態を保ったまま別のアカウントを追加し、いつでも切り替えられます（最大{{ \App\Support\AccountSession::MAX_ACCOUNTS }}件）。
            </span>
          </div>

          <!-- 追加できるアカウントの条件 -->
          <div class="p-4 rounded-lg bg-slate-900/60 border border-slate-800 text-xs leading-relaxed space-y-2.5">
            <p class="font-bold text-slate-200 flex items-start gap-1.5">
              <x-icon name="alert" class="h-3.5 w-3.5 text-amber-400 shrink-0 mt-0.5" stroke-width="2.5" />
              <span>追加できるのは「登録済み」のアカウントだけです</span>
            </p>
            <p class="text-slate-400 leading-relaxed">
              まだ登録していないアカウントは、先に <a href="{{ route('waitlist.show') }}" class="text-sky-400 hover:text-sky-300 hover:underline font-semibold cursor-pointer">参加申請</a> が必要です。
              招待コードをお持ちの場合は <a href="{{ route('register') }}" class="text-sky-400 hover:text-sky-300 hover:underline font-semibold cursor-pointer">招待コードで登録</a> すると、そのままアカウントが追加されます。
            </p>
          </div>
        </div>
      @endif

      <!-- エラーメッセージ表示 -->
      @if ($errors->any())
        <div class="mb-6 p-4 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs leading-relaxed">
          @foreach ($errors->all() as $error)
            <div class="flex items-start gap-2">
              <span class="mt-0.5">•</span>
              <span>{{ $error }}</span>
            </div>
          @endforeach
        </div>
      @endif

      <!-- ログアウト時などの成功トースト -->
      @if (session('success'))
        <div class="mb-6 p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-center gap-2">
          <x-icon name="success" class="h-4 w-4" stroke-width="2" />
          <span>{{ session('success') }}</span>
        </div>
      @endif

      <form id="loginForm" action="{{ url('/login') }}" method="POST" class="space-y-5">
        @csrf

        <!-- ハンドル入力 -->
        <div>
          <div class="flex justify-between items-center mb-2">
            <label for="handleInput" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
              Bluesky ハンドル名
            </label>
            <!-- ドメイン切り替えスイッチ -->
            <div class="flex rounded-md bg-slate-950 p-0.5 border border-slate-800/80 text-[10px] font-bold select-none">
              <button type="button" id="toggleSuffix" class="px-2 py-0.5 rounded bg-slate-800 text-white transition-all cursor-pointer focus:outline-none">
                .bsky.social
              </button>
              <button type="button" id="toggleCustom" class="px-2 py-0.5 rounded text-slate-500 hover:text-slate-300 transition-all cursor-pointer focus:outline-none">
                カスタムドメイン
              </button>
            </div>
          </div>
          <div class="relative flex items-center">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500 text-sm">@</span>
            <input type="text" id="handleInput"
                   placeholder="username"
                   class="w-full pl-8 pr-28 py-3 rounded-lg bg-slate-900/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition duration-300">
            <span id="bskySuffix" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 text-xs font-mono font-bold select-none pointer-events-none transition-all duration-200">
              .bsky.social
            </span>
          </div>
          <input type="hidden" name="handle" id="finalHandle" value="{{ old('handle') }}">
          <p class="mt-1.5 text-[10px] text-slate-500">
            <span id="handleHint">※「.bsky.social」を除いたユーザー名だけを入力してください</span>
          </p>
        </div>

        <!-- アプリパスワード入力 -->
        <div>
          <label for="app_password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
            アプリパスワード
          </label>
          <input type="password" name="app_password" id="app_password" 
                 placeholder="xxxx-xxxx-xxxx-xxxx" 
                 class="w-full px-4 py-3 rounded-lg bg-slate-900/60 border border-slate-700/60 text-white placeholder-slate-500 text-sm focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition duration-300 tracking-wider">
        </div>

        <!-- アプリパスワードの作り方トグル -->
        <x-app-password-guide />

        <!-- ログイン状態を保持する -->
        <x-remember-me />

        <!-- 送信ボタン -->
        <button type="submit" id="loginBtn"
                class="w-full py-3.5 px-4 rounded-lg cursor-pointer bg-linear-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-semibold text-sm shadow-lg shadow-sky-950/50 hover:shadow-sky-500/10 focus:outline-none transition duration-300 transform active:scale-[0.98] flex items-center justify-center gap-2">
          <span id="loginBtnText" class="flex items-center justify-center gap-2">{{ $isAdding ? 'このアカウントを追加する ➔' : '連携してログインする ➔' }}</span>
        </button>

      </form>

    </div>

    <!-- 導線 -->
    <div class="text-center mt-6 space-y-3">
      @if ($isAdding)
        <a href="{{ route('calendar.show', ['handle' => $currentHandle]) }}" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-sky-400 font-semibold transition duration-300 cursor-pointer">
          <x-icon name="arrow-left" class="h-3.5 w-3.5" stroke-width="2.5" />
          追加せずに {{ '@' . $currentHandle }} のカレンダーへ戻る
        </a>
      @else
        <a href="/join" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-sky-400 font-semibold transition duration-300 cursor-pointer">
          <x-icon name="user-plus" class="h-3.5 w-3.5" stroke-width="2.5" />
          アカウントをお持ちでない方（参加申請はこちら）
        </a>
      @endif
      <br>
      <div class="flex items-center justify-center gap-4 text-xs font-semibold select-none">
        <a href="{{ route('about') }}" class="text-slate-500 hover:text-sky-400 transition duration-300 cursor-pointer">
          {{ config('app.name', 'SkLoG') }}について
        </a>
        <span class="text-slate-700">|</span>
        <a href="/" class="text-slate-500 hover:text-sky-400 transition duration-300 cursor-pointer">
          トップページへ戻る
        </a>
      </div>
    </div>

    @include('layouts._footer')
  </div>

  <script>
    // ハンドル名入力のドメイン補完・切り替えロジック
    (function() {
      const finalHandle = document.getElementById('finalHandle');
      const handleInput = document.getElementById('handleInput');
      const bskySuffix = document.getElementById('bskySuffix');
      const toggleSuffix = document.getElementById('toggleSuffix');
      const toggleCustom = document.getElementById('toggleCustom');
      const handleHint = document.getElementById('handleHint');

      let mode = 'bsky';

      function setMode(newMode) {
        mode = newMode;
        if (mode === 'bsky') {
          toggleSuffix.classList.add('bg-slate-800', 'text-white');
          toggleSuffix.classList.remove('text-slate-500', 'hover:text-slate-300');
          toggleCustom.classList.remove('bg-slate-800', 'text-white');
          toggleCustom.classList.add('text-slate-500', 'hover:text-slate-300');

          bskySuffix.classList.remove('hidden');
          handleInput.placeholder = 'username';
          handleInput.classList.remove('pr-4');
          handleInput.classList.add('pr-28');
          handleHint.textContent = '※「.bsky.social」を除いたユーザー名だけを入力してください';
        } else {
          toggleCustom.classList.add('bg-slate-800', 'text-white');
          toggleCustom.classList.remove('text-slate-500', 'hover:text-slate-300');
          toggleSuffix.classList.remove('bg-slate-800', 'text-white');
          toggleSuffix.classList.add('text-slate-500', 'hover:text-slate-300');

          bskySuffix.classList.add('hidden');
          handleInput.placeholder = 'username.com';
          handleInput.classList.remove('pr-28');
          handleInput.classList.add('pr-4');
          handleHint.textContent = '※カスタムドメイン（例: username.dev）を直接入力してください';
        }
        updateFinalHandle();
      }

      function updateFinalHandle() {
        const val = handleInput.value.trim();
        if (val === '') {
          finalHandle.value = '';
          return;
        }

        if (mode === 'bsky') {
          // .bsky.social を手動で入れてしまった場合は二重に付けない
          if (val.toLowerCase().endsWith('.bsky.social')) {
            finalHandle.value = val;
          } else {
            finalHandle.value = val + '.bsky.social';
          }
        } else {
          finalHandle.value = val;
        }
      }

      toggleSuffix.addEventListener('click', () => setMode('bsky'));
      toggleCustom.addEventListener('click', () => setMode('custom'));
      handleInput.addEventListener('input', updateFinalHandle);

      // 初期状態の復元 (old('handle')など)
      const initialValue = finalHandle.value.trim();
      if (initialValue !== '') {
        if (initialValue.toLowerCase().endsWith('.bsky.social')) {
          handleInput.value = initialValue.slice(0, -12);
          setMode('bsky');
        } else {
          handleInput.value = initialValue;
          setMode('custom');
        }
      } else {
        setMode('bsky');
      }
    })();

    // フォーム送信時の二重送信防止およびローディング表示
    document.getElementById('loginForm').addEventListener('submit', function() {
      const btn = document.getElementById('loginBtn');
      const btnText = document.getElementById('loginBtnText');
      
      if (btn && btnText) {
        // ボタンを無効化
        btn.disabled = true;
        btn.classList.add('cursor-not-allowed', 'opacity-70');
        btn.classList.remove('cursor-pointer', 'hover:from-sky-400', 'hover:to-indigo-500', 'active:scale-[0.98]');
        
        // スピナーを追加してテキストを変更
        btnText.innerHTML = `
          <svg class="animate-spin h-4 w-4 text-white shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          連携処理中...
        `;
      }
    });
  </script>
</body>
</html>
