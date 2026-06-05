<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <x-google-analytics />
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Blueskyカレンダーアーカイブ | {{ config('app.name', 'SkLoG') }}</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

  <!-- OGP (Open Graph Protocol) -->
  <meta property="og:title" content="Blueskyカレンダーアーカイブ | {{ config('app.name', 'SkLoG') }}">
  <meta property="og:description" content="Blueskyの投稿を自動同期し、カレンダー形式でログ保存・公開できるアーカイブツールです。">
  <meta property="og:image" content="{{ url('logo.png') }}">
  <meta property="og:url" content="{{ url('/') }}">
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
      background: radial-gradient(circle at top, rgba(30, 41, 59, 0.4) 0%, rgba(2, 6, 23, 1) 100%);
    }
    .glass-card {
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.4);
    }
    .glow-effect {
      position: absolute;
      width: 500px;
      height: 500px;
      background: radial-gradient(circle, rgba(56, 189, 248, 0.12) 0%, rgba(56, 189, 248, 0) 70%);
      top: -200px;
      left: 50%;
      transform: translateX(-50%);
      pointer-events: none;
      z-index: 0;
    }
    .glow-effect-bottom {
      position: absolute;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(147, 51, 234, 0.08) 0%, rgba(147, 51, 234, 0) 70%);
      bottom: -100px;
      right: 10%;
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
<body class="min-h-full flex flex-col justify-between p-4 md:p-8 relative overflow-x-hidden">

  <div class="glow-effect"></div>
  <div class="glow-effect-bottom"></div>

  <!-- メインコンテンツコンテナ -->
  <div class="w-full max-w-5xl mx-auto z-10 space-y-12 md:space-y-16 py-8">
    
    <!-- ヒーローセクション -->
    <div class="text-center space-y-6 max-w-2xl mx-auto select-none">
      
      <!-- ロゴ & 見出し (クリックでリロード) -->
      <div class="text-center mb-4 select-none">
        <a href="/" class="inline-block group cursor-pointer">
          <!-- アニメーションする蝶ロゴ -->
          <div class="flex justify-center mb-4">
            <div class="w-20 h-20 rounded-3xl bg-linear-to-br from-sky-400 to-indigo-600 flex items-center justify-center shadow-lg shadow-sky-500/20 group-hover:scale-105 transition duration-300 animate-butterfly">
              <x-icon name="logo" class="w-[43px] h-[43px]" />
            </div>
          </div>

          <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight bg-linear-to-r from-sky-400 via-indigo-400 to-purple-500 bg-clip-text text-transparent group-hover:from-sky-300 group-hover:to-purple-400 transition duration-300">
            {{ config('app.name', 'SkLoG') }}
          </h1>
        </a>
      </div>
        <p class="text-slate-200 text-base md:text-lg font-medium leading-relaxed">
          Blueskyの投稿を、カレンダー形式で保存・検索。
        </p>
      </div>

      <!-- アクションボタン -->
      <div class="pt-4 flex justify-center gap-4">
        @auth
          @if ($authBskyAccount = Auth::user()->blueskyAccount)
            <a href="{{ route('calendar.show', ['handle' => $authBskyAccount->handle]) }}" class="px-6 py-3.5 rounded-xl cursor-pointer bg-linear-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-bold text-xs shadow-lg shadow-sky-950/50 hover:shadow-sky-500/15 focus:outline-none transition duration-300 transform hover:scale-[1.05] active:scale-[0.98] flex items-center gap-2">
              <x-icon name="calendar" class="h-4 w-4" stroke-width="2.5" />
              自分のカレンダーを見る
            </a>
          @endif
        @else
          <a href="/login" class="px-6 py-3.5 rounded-xl cursor-pointer bg-linear-to-r from-sky-500 to-indigo-600 hover:from-sky-400 hover:to-indigo-500 text-white font-bold text-xs shadow-lg shadow-sky-950/50 hover:shadow-sky-500/15 focus:outline-none transition duration-300 transform hover:scale-[1.05] active:scale-[0.98] flex items-center gap-2">
            <x-icon name="login" class="h-4 w-4" stroke-width="2.5" />
            ログイン
          </a>
          <a href="/join" class="px-6 py-3.5 rounded-xl cursor-pointer bg-slate-900/60 hover:bg-slate-800 border border-slate-700 hover:border-sky-500/40 text-slate-300 hover:text-sky-400 font-bold text-xs transition duration-300 transform hover:scale-[1.05] active:scale-[0.98] flex items-center gap-2">
            <x-icon name="mail" class="h-4 w-4" stroke-width="2.5" />
            参加申請
          </a>
        @endauth
      </div>
    </div>

    <!-- サンプルアカウント案内バナー -->
    <div class="max-w-xl mx-auto">
      <a href="{{ route('calendar.show', ['handle' => config('services.bluesky.demo_handle')]) }}" 
         class="glass-card rounded-2xl p-4 block border border-sky-500/20 hover:border-sky-500/40 bg-linear-to-r from-sky-950/20 via-slate-900/60 to-indigo-950/20 hover:scale-[1.02] transition duration-300 relative group overflow-hidden select-none">
        <!-- バナー内の流麗な光彩 -->
        <div class="absolute -right-10 -top-10 w-24 h-24 bg-sky-500/10 rounded-full blur-2xl group-hover:bg-sky-500/20 transition duration-500"></div>
        
        <div class="flex items-center gap-4">
          <!-- デモ用のアバターロゴ -->
          <div class="w-12 h-12 rounded-xl bg-linear-to-br from-sky-500/20 to-indigo-500/20 border border-sky-500/30 flex items-center justify-center shrink-0 shadow-lg">
            <x-icon name="logo" class="w-6 h-6 text-sky-400" />
          </div>
          
          <div class="flex-1 min-w-0 text-left">
            <div class="flex items-center gap-1.5 mb-0.5">
              <span class="text-[9px] font-extrabold tracking-widest text-sky-400 bg-sky-500/10 border border-sky-500/20 px-1.5 py-0.5 rounded uppercase leading-none">Sample</span>
              <span class="text-xs font-bold text-white truncate">サンプルカレンダーを体験する</span>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed truncate">
              実際のアーカイブ画面を見ることができます。
            </p>
          </div>
          
          <!-- 矢印 -->
          <x-icon name="chevron-right" class="h-5 w-5 text-slate-500 group-hover:text-sky-400 group-hover:translate-x-1 transition duration-300 shrink-0" stroke-width="2.5" />
        </div>
      </a>
      <div class="mt-3 px-1.5 text-[10px] text-slate-500 font-semibold leading-relaxed select-none text-left space-y-1">
        <div class="flex items-start gap-1">
          <span class="text-slate-600 shrink-0">※</span>
          <span>プライバシー保護のため、ご本人以外のログを閲覧する際はカレンダーや検索などの詳細機能は制限され、シンプルなタイムライン（全件表示）となります。</span>
        </div>
        <div class="flex items-start gap-1 text-sky-500/70">
          <span class="text-sky-500/60 shrink-0">💡</span>
          <span>上記のサンプルページでは、すべての機能を特別にデモ体験いただけます。</span>
        </div>
      </div>
    </div>

    <!-- 公開中のユーザーセクション -->
    <div class="space-y-6">
      <div class="border-b border-slate-900/60 pb-3 flex items-center justify-between">
        <h2 class="text-sm font-extrabold text-white tracking-widest uppercase flex items-center gap-2 select-none">
          <x-icon name="book-open" class="h-4 w-4 text-sky-400 shrink-0" stroke-width="2" />
          公開中のユーザー一覧
        </h2>
        <span class="px-2.5 py-0.5 rounded-full bg-slate-900/80 border border-slate-800 text-[10px] text-slate-500 font-bold select-none">
          {{ $publicAccounts->count() }} アカウント
        </span>
      </div>

      @if ($publicAccounts->isNotEmpty())
        <!-- ユーザーグリッド -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          @foreach ($publicAccounts as $account)
            <a href="{{ route('calendar.show', ['handle' => $account->handle]) }}" 
               class="glass-card rounded-2xl p-5 block cursor-pointer hover:scale-[1.03] transition duration-300 relative overflow-hidden group">
              <!-- カード背景バナー (極小表示) -->
              <div class="absolute inset-0 h-16 w-full overflow-hidden z-0 bg-slate-900">
                @if ($account->banner_url)
                  <img src="{{ $account->banner_url }}" alt="" class="w-full h-full object-cover opacity-35 group-hover:scale-105 transition duration-500">
                @endif
                <div class="absolute inset-0 bg-linear-to-t from-slate-950/80 to-transparent"></div>
              </div>

              <!-- ユーザープロフィール要約 -->
              <div class="relative z-10 pt-6 flex items-start gap-3.5">
                <!-- アバター -->
                <div class="h-14 w-14 rounded-full overflow-hidden border-2 border-slate-950 bg-slate-900 shrink-0 shadow-lg">
                  @if ($account->avatar_url)
                    <img src="{{ $account->avatar_url }}" alt="Avatar" class="w-full h-full object-cover">
                  @else
                    <div class="w-full h-full flex items-center justify-center bg-linear-to-br from-sky-400 to-indigo-600 text-white text-lg font-bold">
                      {{ mb_substr($account->user->name ?: $account->handle, 0, 1) }}
                    </div>
                  @endif
                </div>

                <!-- 名前 & ハンドル -->
                <div class="min-w-0 pt-3">
                  <h3 class="text-sm font-bold text-white tracking-wide truncate hover:text-sky-400 transition duration-300">
                    {{ $account->user->name ?: '@' . $account->handle }}
                  </h3>
                  <p class="text-sky-400 font-semibold text-xs truncate">
                    {{ '@' . $account->handle }}
                  </p>
                </div>
              </div>

              <!-- 自己紹介 & 投稿数 -->
              <div class="relative z-10 mt-4 space-y-3">
                <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed min-h-10">
                  {{ $account->description ?? '自己紹介はありません。' }}
                </p>

                <!-- メタデータ -->
                <div class="flex items-center justify-end text-[10px] text-slate-500 border-t border-slate-900/60 pt-3 font-semibold">
                  <span class="text-sky-400 flex items-center gap-1 font-bold group-hover:translate-x-1 transition duration-300">
                    カレンダーを見る ➔
                  </span>
                </div>
              </div>
            </a>
          @endforeach
        </div>
      @else
        <!-- アカウントが存在しない場合の親切なメッセージ -->
        <div class="glass-card rounded-2xl p-8 text-center space-y-4 max-w-md mx-auto select-none">
          <div class="w-12 h-12 rounded-full bg-slate-900 flex items-center justify-center mx-auto text-slate-500 border border-slate-800">
            <x-icon name="alert" class="h-6 w-6" stroke-width="2" />
          </div>
          <div class="space-y-1.5">
            <h3 class="text-sm font-bold text-white">公開中のアカウントはありません</h3>
            <p class="text-slate-400 text-[11px] leading-relaxed">
              現在、公開設定のアカウントは登録されていません。<br>アカウントを連携して、カレンダーを作成しましょう。
            </p>
          </div>
        </div>
      @endif
    </div>

  </div>

  @include('layouts._footer')

</body>
</html>
