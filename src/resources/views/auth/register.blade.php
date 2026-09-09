@php
  // ログイン中に別アカウントを追加登録するモードかどうか
  $currentHandle = Auth::check() ? Auth::user()->blueskyAccount?->handle : null;
  $isAdding = ($isAdding ?? false) && $currentHandle !== null;
  $pageTitle = $isAdding ? 'アカウントを追加登録' : '新規登録';

  // 招待メールのリンク経由の場合、招待先のハンドル名と招待コードを固定表示する
  $lockedHandle = $lockedHandle ?? null;
  $lockedCode = $lockedCode ?? null;
  $canUnlockHandle = ($canUnlockHandle ?? false) && $lockedHandle;
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
  <meta property="og:description" content="Blueskyカレンダーアーカイブ「{{ config('app.name', 'SkLoG') }}」のアカウント連携・新規登録ページです。">
  <meta property="og:image" content="{{ url('logo.png') }}">
  <meta property="og:url" content="{{ url('/register') }}">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="{{ config('app.name', 'SkLoG') }}">
  <meta name="twitter:card" content="summary_large_image">

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

  <!-- 背景装飾（はみ出しをカットして不要なスクロールバーを防止） -->
  <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
    <div class="glow-effect"></div>
    <div class="glow-effect-right"></div>
  </div>

  <div class="w-full max-w-md z-10">

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

    <!-- 登録カード -->
    <div class="glass-card rounded-2xl p-8 relative overflow-hidden">

      <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
        <x-icon name="key" class="h-6 w-6 text-sky-400" stroke-width="2" />
        {{ $pageTitle }}
      </h2>

      <!-- アカウント追加モードの案内 -->
      @if ($isAdding)
        <div class="mb-6 p-4 rounded-lg bg-sky-500/10 border border-sky-500/30 text-sky-300 text-xs leading-relaxed flex items-start gap-2">
          <x-icon name="info" class="h-4 w-4 shrink-0 mt-0.5" stroke-width="2" />
          <span>
            現在 <span class="font-bold">{{ '@' . $currentHandle }}</span> でログイン中です。<br>
            招待コードで別のアカウントを登録すると、そのまま追加されて切り替えられるようになります。
          </span>
        </div>
      @endif

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

      @if (session('info'))
        <div class="mb-6 p-4 rounded-lg bg-sky-500/10 border border-sky-500/30 text-sky-300 text-xs flex items-center gap-2">
          <x-icon name="info" class="h-4 w-4 shrink-0" stroke-width="2" />
          <span>{{ session('info') }}</span>
        </div>
      @endif

      @if (session('success_resend'))
        <div class="mb-6 p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-start gap-2">
          <x-icon name="success" class="h-4 w-4 shrink-0 mt-0.5" stroke-width="2" />
          <span>{{ session('success_resend') }}</span>
        </div>
      @endif

      @if (session('is_expired') || $errors->has('is_expired'))
        <div class="mb-6 p-5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs space-y-4">
          <div class="flex items-start gap-2 font-bold leading-normal">
            <x-icon name="alert" class="h-4 w-4 shrink-0 mt-0.5" stroke-width="2.5" />
            <span>招待コードの有効期限が切れています</span>
          </div>
          <p class="leading-relaxed text-slate-300">この招待コードの有効期限が過ぎてしまいました。ご不便をおかけいたしますが、以下のボタンから管理者へ再送を依頼してください。</p>
          <form action="{{ route('register.resend') }}" method="POST">
            @csrf
            <input type="hidden" name="expired_code" value="{{ session('expired_code') ?? old('invite_code') }}">
            <button type="submit"
                    class="py-2 px-3 bg-amber-600 hover:bg-amber-500 text-white font-semibold rounded-md shadow-md focus:outline-none transition duration-300 active:scale-[0.98] cursor-pointer">
              再送をリクエストする ➔
            </button>
          </form>
        </div>
      @endif

      <form id="registerForm" action="{{ url('/register') }}" method="POST" class="space-y-5">
        @csrf

        <!-- 招待コード -->
        <div>
          <label for="invite_code" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2 flex items-center gap-1.5">
            招待コード
            @if ($lockedCode)
              <x-icon name="lock" class="h-3 w-3 text-slate-500" stroke-width="2.5" />
            @endif
          </label>
          <input type="text" name="invite_code" id="invite_code"
                 value="{{ old('invite_code', $lockedCode ?? request('code')) }}"
                 placeholder="XXXX-XXXX-XXXX"
                 @if ($lockedCode) readonly @endif
                 class="w-full px-4 py-3 rounded-lg border text-sm focus:outline-none transition duration-300 tracking-widest font-mono uppercase {{ $errors->has('invite_code') ? 'border-rose-500' : 'border-slate-700/60' }} {{ $lockedCode ? 'bg-slate-950/60 border-slate-800 text-slate-300 cursor-default select-all' : 'bg-slate-900/60 text-white placeholder-slate-500 focus:border-sky-500 focus:ring-1 focus:ring-sky-500' }}"
                 autocomplete="off">
          @if ($lockedCode)
            <p class="mt-1.5 text-[10px] text-slate-500">※ 招待メールのリンクから開いたため、招待コードを自動入力しました</p>
          @endif
          @error('invite_code')
            <p class="mt-1 text-[10px] text-rose-400">{{ $message }}</p>
          @enderror
        </div>

        <!-- ハンドル入力 -->
        <div>
          <input type="hidden" name="handle" id="finalHandle" value="{{ old('handle', $lockedHandle) }}">

          <!-- 招待先のハンドル名（固定表示） -->
          @if ($lockedHandle)
            <div id="lockedHandleBlock">
              <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                Bluesky ハンドル名
                <x-icon name="lock" class="h-3 w-3 text-slate-500" stroke-width="2.5" />
              </label>
              <div class="w-full px-4 py-3 rounded-lg bg-slate-950/60 border border-slate-800 flex items-center gap-2">
                <span class="text-sm font-semibold text-white truncate">{{ '@' . $lockedHandle }}</span>
                <span class="ml-auto shrink-0 text-[9px] font-extrabold tracking-widest text-sky-400 bg-sky-500/10 border border-sky-500/20 px-1.5 py-0.5 rounded uppercase leading-none select-none">招待済み</span>
              </div>
              <p class="mt-1.5 text-[10px] text-slate-500 leading-relaxed">
                ※ この招待コードの発行先アカウントです。このアカウントのアプリパスワードで登録してください
              </p>
              @if ($canUnlockHandle)
                <button type="button" id="unlockHandleBtn" class="mt-1.5 text-[10px] text-sky-400 hover:text-sky-300 hover:underline font-semibold cursor-pointer focus:outline-none">
                  Blueskyでハンドル名を変更した方はこちら
                </button>
              @endif
            </div>
          @endif

          <div id="freeHandleBlock" @class(['hidden' => $lockedHandle])>
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
            <p class="mt-1.5 text-[10px] text-slate-500">
              <span id="handleHint">※「.bsky.social」を除いたユーザー名だけを入力してください</span>
            </p>
          </div>
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

        <!-- 利用規約・プライバシーに関する同意 -->
        <div class="flex items-start gap-2.5 pt-2">
          <input type="checkbox" name="terms" id="terms"
                 class="mt-0.5 h-4 w-4 rounded-sm border-slate-700/60 bg-slate-900/60 text-sky-500 focus:ring-sky-500 cursor-pointer">
          <label for="terms" class="text-xs text-slate-400 leading-normal select-none">
            本サービスへの投稿データの保存や公開条件等について、<a href="{{ route('about') }}" target="_blank" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">サービス利用にあたっての説明・注意点</a>を確認し、同意します。
          </label>
        </div>

        <!-- 送信ボタン -->
        <button type="submit" id="registerBtn"
                class="w-full py-3.5 px-4 rounded-lg cursor-pointer bg-linear-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-semibold text-sm shadow-lg shadow-sky-950/50 hover:shadow-sky-500/10 focus:outline-none transition duration-300 transform active:scale-[0.98] flex items-center justify-center gap-2">
          <span id="registerBtnText" class="flex items-center justify-center gap-2">{{ $isAdding ? '登録してアカウントを追加する ➔' : '登録する ➔' }}</span>
        </button>

      </form>
    </div>

    <!-- ログインへの導線 -->
    <div class="text-center mt-6 space-y-3">
      @if ($isAdding)
        <a href="{{ route('accounts.add') }}" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-sky-400 font-semibold transition duration-300 cursor-pointer">
          <x-icon name="login" class="h-3.5 w-3.5" stroke-width="2.5" />
          登録済みのアカウントを追加する場合はこちら
        </a>
        <br>
        <a href="{{ route('calendar.show', ['handle' => $currentHandle]) }}" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-sky-400 font-semibold transition duration-300 cursor-pointer">
          <x-icon name="arrow-left" class="h-3.5 w-3.5" stroke-width="2.5" />
          追加せず {{ '@' . $currentHandle }} のカレンダーへ戻る
        </a>
      @else
        <a href="/login" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-sky-400 font-semibold transition duration-300 cursor-pointer">
          <x-icon name="login" class="h-3.5 w-3.5" stroke-width="2.5" />
          すでにアカウントをお持ちの方はこちら
        </a>
        <br>
        <a href="/" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-sky-400 font-semibold transition duration-300 cursor-pointer">
          <x-icon name="arrow-left" class="h-3.5 w-3.5" stroke-width="2.5" />
          トップページへ戻る
        </a>
      @endif
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

      // 固定表示されたハンドル名を、自由入力に切り替える（Bluesky側でハンドル名を変更した人向け）
      const unlockBtn = document.getElementById('unlockHandleBtn');
      if (unlockBtn) {
        unlockBtn.addEventListener('click', () => {
          document.getElementById('lockedHandleBlock').classList.add('hidden');
          document.getElementById('freeHandleBlock').classList.remove('hidden');
          handleInput.focus();
          handleInput.select();
        });
      }
    })();

    document.getElementById('registerForm').addEventListener('submit', function() {
      const btn = document.getElementById('registerBtn');
      const btnText = document.getElementById('registerBtnText');
      if (btn && btnText) {
        btn.disabled = true;
        btn.classList.add('cursor-not-allowed', 'opacity-70');
        btn.classList.remove('cursor-pointer', 'hover:from-sky-400', 'hover:to-indigo-500', 'active:scale-[0.98]');
        btnText.innerHTML = `
          <svg class="animate-spin h-4 w-4 text-white shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          登録処理中...
        `;
      }
    });
  </script>
</body>
</html>
