<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <x-google-analytics />
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ config('app.name', 'SkLoG') }}について | {{ config('app.name', 'SkLoG') }}</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  
  <!-- OGP (Open Graph Protocol) -->
  <meta property="og:title" content="{{ config('app.name', 'SkLoG') }}について | {{ config('app.name', 'SkLoG') }}">
  <meta property="og:description" content="Blueskyカレンダーアーカイブ「{{ config('app.name', 'SkLoG') }}」の利用規約およびプライバシーポリシーのご案内ページです。">
  <meta property="og:image" content="{{ url('logo.png') }}">
  <meta property="og:url" content="{{ url('/about') }}">
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
  </style>
</head>
<body class="min-h-full flex flex-col items-center justify-center p-4 md:p-8 relative overflow-x-hidden">

  <!-- 背景の光彩エフェクト -->
  <div class="absolute top-[-200px] left-1/2 -translate-x-1/2 w-[500px] h-[500px] bg-radial from-sky-500/10 to-transparent pointer-events-none z-0"></div>

  <div class="w-full max-w-2xl glass-card rounded-2xl p-6 md:p-10 space-y-8 z-10 relative">
    
    <!-- ヘッダー・ロゴ -->
    <div class="text-center space-y-3">
      <div class="inline-flex w-12 h-12 rounded-xl bg-linear-to-br from-sky-400 to-indigo-600 items-center justify-center shadow-lg shadow-sky-500/20 select-none">
        <x-icon name="logo" class="h-7 w-7 text-white" />
      </div>
      <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-white select-none">
        {{ config('app.name', 'SkLoG') }} について
      </h1>
      <p class="text-xs text-sky-400 font-bold tracking-wider uppercase select-none">
        About &amp; Privacy Policy
      </p>
    </div>

    <!-- 説明コンテンツ -->
    <div class="space-y-6 text-sm text-slate-300 leading-relaxed">
      
      <section class="space-y-2 border-b border-slate-800/60 pb-5">
        <h2 class="text-base font-bold text-white flex items-center gap-2">
          <span class="w-1.5 h-4 bg-sky-400 rounded-full"></span>
          本サービスの概要
        </h2>
        <p>
          本サービスは、ご自身のBlueskyアカウントの投稿（ポスト）を自動的に取得し、カレンダー形式でログ保存・公開できるアーカイブツールです。
        </p>
      </section>

      <section class="space-y-2 border-b border-slate-800/60 pb-5">
        <h2 class="text-base font-bold text-white flex items-center gap-2">
          <span class="w-1.5 h-4 bg-sky-400 rounded-full"></span>
          アプリパスワードの取り扱いについて
        </h2>
        <ul class="list-disc list-inside space-y-1.5 pl-1">
          <li>登録時に入力された<strong>「アプリパスワード」</strong>は、最新の暗号化技術を用いてデータベースに安全に保管されます。</li>
          <li>このパスワードは、Bluesky APIへのログイン認証および投稿データの自動同期（バックグラウンド処理）の目的以外には<strong>一切使用されません</strong>。</li>
          <li>本物のログインパスワードではなく、必ずBlueskyの管理画面で生成した<strong>専用のアプリパスワード</strong>をご使用ください。</li>
        </ul>
      </section>

      <section class="space-y-2 border-b border-slate-800/60 pb-5">
        <h2 class="text-base font-bold text-white flex items-center gap-2">
          <span class="w-1.5 h-4 bg-sky-400 rounded-full"></span>
          データの収集・保存範囲
        </h2>
        <ul class="list-disc list-inside space-y-1.5 pl-1">
          <li><strong>同期対象</strong>: ご自身のオリジナル投稿（テキスト、添付画像、ハッシュタグ）および本人のリポストを取得します。</li>
          <li><strong>非対象</strong>: 他人のオリジナル投稿やリポストされた投稿に含まれるハッシュタグは、カレンダーや統計情報の集計には含まれません。</li>
          <li><strong>削除時の挙動</strong>: 本サービスのカレンダーから任意のポストを削除した場合、そのポストは再インポート（再同期）されなくなります。</li>
        </ul>
      </section>

      <section class="space-y-2 border-b border-slate-800/60 pb-5">
        <h2 class="text-base font-bold text-white flex items-center gap-2">
          <span class="w-1.5 h-4 bg-sky-400 rounded-full"></span>
          公開・非公開（公開範囲）の連動について
        </h2>
        <ul class="list-disc list-inside space-y-1.5 pl-1">
          <li>カレンダーの公開範囲は、<strong>Bluesky側のアカウント設定と自動的に連動</strong>します。</li>
          <li>Blueskyのプライバシー設定で「Logged-out visibility（ログアウト時の公開範囲）」を制限（非公開）している場合、本サービスのカレンダーも<strong>自動的に本人以外のアクセスを遮断し、非公開化</strong>されます。</li>
          <li>Bluesky側が公開設定である場合のみ、本サービスのカレンダーも一般に公開され、検索エンジンの登録（インデックス）対象となります。</li>
        </ul>
      </section>

      <section class="space-y-2 border-b border-slate-800/60 pb-5">
        <h2 class="text-base font-bold text-white flex items-center gap-2">
          <span class="w-1.5 h-4 bg-sky-400 rounded-full"></span>
          退会およびデータの完全削除
        </h2>
        <p>
          本サービスから退会（アカウント削除）を行った場合、またはハンドル変更時にデータの引き継ぎを拒否された場合、<strong>取得したすべてのポスト、画像キャッシュ、登録情報、および暗号化されたアプリパスワードは、サーバーから即座に完全に物理削除</strong>されます。バックアップを含め、復元することはできません。
        </p>
      </section>

      <section class="space-y-2 border-b border-slate-800/60 pb-5">
        <h2 class="text-base font-bold text-white flex items-center gap-2">
          <span class="w-1.5 h-4 bg-sky-400 rounded-full"></span>
          長期間同期が停止しているアカウントの取り扱い
        </h2>
        <p>
          アプリパスワードの無効化などにより長期間（おおむね30日以上）同期エラーが継続している場合、または長期間にわたり本サービスのご利用（アクセス）がない休眠状態のアカウントは、サーバー資源の最適化のため、事前の通告なくアカウントおよび蓄積されたカレンダーデータを完全に削除させていただく場合があります。
        </p>
      </section>

      <section class="space-y-2 border-b border-slate-800/60 pb-5">
        <h2 class="text-base font-bold text-white flex items-center gap-2">
          <span class="w-1.5 h-4 bg-sky-400 rounded-full"></span>
          お問い合わせについて
        </h2>
        <p>
          本サービスに関するご質問、不具合のご報告、ご意見などがございましたら、本サービス公式のBlueskyアカウント（<a href="https://bsky.app/profile/sklog.nono150.com" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">@sklog.nono150.com</a>）までダイレクトメッセージでお知らせください。
        </p>
      </section>

    </div>

    <!-- 戻るアクション -->
    <div class="pt-4 flex justify-center">
      <button onclick="window.history.back()" class="px-6 py-2.5 rounded-xl cursor-pointer bg-slate-900 hover:bg-slate-850 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white font-bold text-xs transition duration-300 flex items-center gap-1.5 active:scale-[0.98]">
        ← 元のページに戻る
      </button>
    </div>

  </div>

</body>
</html>
