<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <x-google-analytics />
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>@yield('title', $bskyAccount->handle . ' のカレンダー | ' . config('app.name', 'SkLoG'))</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">

  <!-- Tailwind CSS (Vite経由) -->
  @vite(['resources/css/app.css', 'resources/js/app.js'])

  <!-- OGP (Open Graph Protocol) -->
  @if (isset($bskyAccount))
    @if ($bskyAccount->is_private)
      <!-- 非公開カレンダー用のマスクされたメタデータ -->
      <meta property="og:title" content="非公開のカレンダー | {{ config('app.name', 'SkLoG') }}">
      <meta property="og:description" content="このカレンダーは所有者本人にのみ公開されています。">
    @else
       <!-- 公開カレンダー用のリッチメタデータ -->
      <meta property="og:title" content="{{ ($user->name ?: '@' . $bskyAccount->handle) }} のカレンダー | {{ config('app.name', 'SkLoG') }}">
      <meta property="og:description" content="{{ $bskyAccount->description ? str($bskyAccount->description)->squish()->limit(120) : 'Blueskyの投稿をカレンダー形式でログ保存・公開しているアーカイブです。' }}">
    @endif
    <meta property="og:image" content="{{ url('logo.png') }}">
    <meta property="og:url" content="{{ route('calendar.show', ['handle' => $bskyAccount->handle]) }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name', 'SkLoG') }}">
    <meta name="twitter:card" content="summary_large_image">
  @endif

  <style>
    body {
      font-family: 'Outfit', 'Noto Sans JP', sans-serif;
      background: radial-gradient(circle at top, rgba(15, 23, 42, 0.4) 0%, rgba(2, 6, 23, 1) 100%);
    }
    .glass-header {
      background: rgba(15, 23, 42, 0.45);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    .glass-card {
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.06);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .glass-card:hover {
      border-color: rgba(56, 189, 248, 0.3);
      box-shadow: 0 15px 35px rgba(56, 189, 248, 0.08);
    }
  </style>
  @if ((isset($bskyAccount) && $bskyAccount->is_private) || request()->routeIs('calendar.settings'))
    <meta name="robots" content="noindex, nofollow">
  @endif

  @yield('extra_css')
</head>
<body class="min-h-full flex flex-col pb-12">

  <!-- ヘッダーバナー -->
  <div class="relative h-48 md:h-72 w-full overflow-hidden bg-linear-to-r from-sky-900 via-indigo-900 to-purple-950">
    @if ($bskyAccount->banner_url)
      <img src="{{ $bskyAccount->banner_url }}" alt="Banner" class="w-full h-full object-cover opacity-85">
    @endif
    <div class="absolute inset-0 bg-linear-to-t from-slate-950 to-transparent"></div>

    <!-- グローバルサイトヘッダー (バナーの上に重ねる) -->
    <header class="absolute top-0 left-0 w-full z-20 bg-linear-to-b from-slate-950/80 via-slate-950/30 to-transparent">
      <div class="max-w-6xl w-full mx-auto px-4 py-3.5 flex items-center justify-between">
        <!-- サイト名ロゴ -->
        <a href="/" class="flex items-center gap-2.5 group cursor-pointer select-none">
          <!-- グラデーションアイコン -->
          <div class="w-6.5 h-6.5 rounded-lg bg-linear-to-br from-sky-400 to-indigo-600 flex items-center justify-center shadow-md shadow-sky-500/20 group-hover:scale-105 transition duration-300">
            <x-icon name="logo" class="h-3.5 w-3.5" />
          </div>
          <span class="text-sm font-extrabold tracking-wider text-white/90 group-hover:text-sky-400 transition duration-300 select-none">
            {{ config('app.name', 'SkLoG') }}
          </span>
        </a>
        
        <!-- サブテキスト -->
        <div class="text-[9px] md:text-[10px] font-bold text-sky-400/90 tracking-widest bg-slate-950/40 backdrop-blur-xs py-1 px-2.5 rounded-full border border-sky-500/20 select-none flex items-center gap-1.5">
          <span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-pulse"></span>
          Bluesky Archiver &amp; Calendar
        </div>
      </div>
    </header>
  </div>

  <!-- ユーザープロフィール & アクション -->
  <div class="max-w-6xl w-full mx-auto px-4 z-10 -mt-14 md:-mt-18">
    <div class="flex flex-col md:flex-row md:items-start gap-6 mb-8">
      <div class="flex flex-col md:flex-row items-center md:items-start gap-5 text-center md:text-left flex-1 min-w-0 w-full">
        <!-- アバター -->
        <a href="{{ route('calendar.show', ['handle' => $bskyAccount->handle]) }}" class="block h-28 w-28 md:h-36 md:w-36 rounded-full overflow-hidden border-4 border-slate-950 bg-slate-900 shadow-2xl hover:scale-105 hover:border-sky-500/50 transition duration-300 cursor-pointer">
          @if ($bskyAccount->avatar_url)
            <img src="{{ $bskyAccount->avatar_url }}" alt="Avatar" class="w-full h-full object-cover">
          @else
            <div class="w-full h-full flex items-center justify-center bg-linear-to-br from-sky-400 to-indigo-600 text-white text-4xl font-bold">
              {{ mb_substr($user->name ?: $bskyAccount->handle, 0, 1) }}
            </div>
          @endif
        </a>

        <!-- 名前 & ハンドル -->
        <div class="md:pt-6 flex-1 min-w-0 w-full">
          <h2 class="text-2xl md:text-3xl font-extrabold text-white tracking-tight hover:text-sky-400 transition duration-300 leading-tight mb-2 text-center md:text-left">
            <a href="{{ route('calendar.show', ['handle' => $bskyAccount->handle]) }}" class="cursor-pointer">
              {{ $user->name ?: '@' . $bskyAccount->handle }}
            </a>
          </h2>
          <div class="flex flex-col md:flex-row md:items-center justify-center md:justify-start gap-2.5 md:gap-3 mb-2.5">
            <p class="text-sky-400 font-semibold text-sm md:text-base hover:text-sky-300 transition duration-300 leading-none text-center md:text-left">
              <a href="https://bsky.app/profile/{{ $bskyAccount->handle }}" target="_blank" rel="noopener noreferrer" class="cursor-pointer inline-flex items-center gap-1" title="Blueskyプロフィールを開く ➔">
                {{ '@' . $bskyAccount->handle }}
                <x-icon name="link" class="h-3.5 w-3.5 opacity-60" />
              </a>
            </p>
            <div class="flex flex-wrap items-center justify-center md:justify-start gap-2 shrink-0">
              <!-- 公開・非公開バッジ -->
              @if ($bskyAccount->is_private)
                <span class="px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[10px] font-semibold tracking-wider inline-flex items-center gap-1 uppercase shrink-0 whitespace-nowrap select-none">
                  <x-icon name="lock" class="h-3 w-3" />
                  自分のみ
                </span>
              @else
                <span class="px-2 py-0.5 rounded bg-sky-500/10 border border-sky-500/30 text-sky-400 text-[10px] font-semibold tracking-wider inline-flex items-center gap-1 uppercase shrink-0 whitespace-nowrap select-none">
                  <x-icon name="globe" class="h-3 w-3" />
                  公開中
                </span>
              @endif

              @auth
                @if (Auth::id() === $user->id)
                  <a href="{{ route('calendar.settings', ['handle' => $bskyAccount->handle]) }}" class="px-2 py-0.5 rounded bg-slate-900 border border-slate-800 hover:border-sky-500/50 text-slate-400 hover:text-sky-400 text-[10px] font-semibold tracking-wider inline-flex items-center gap-1 shrink-0 whitespace-nowrap transition duration-300 select-none cursor-pointer" title="設定画面を開く">
                    <x-icon name="settings" class="h-3 w-3 text-slate-500" />
                    設定
                  </a>
                @endif
              @endauth

              <!-- 最終同期日時表示 -->
              @if (isset($bskyAccount) && $bskyAccount->last_synced_at)
                <div class="relative group inline-flex">
                  <span class="px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-slate-400 text-[10px] font-semibold tracking-wider inline-flex items-center gap-1 shrink-0 whitespace-nowrap select-none cursor-help">
                    <x-icon name="sync" class="h-3 w-3 text-slate-500 shrink-0" />
                    {{ $bskyAccount->last_synced_at->diffInDays(now()) >= 3 ? '最終同期: ' . $bskyAccount->last_synced_at->format('m/d H:i') : '最終同期: ' . $bskyAccount->last_synced_at->diffForHumans() }}
                  </span>
                  <!-- スタイリッシュな即時ホバーツールチップ -->
                  <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2.5 py-1.5 rounded-lg bg-slate-900 border border-slate-800 text-[10px] text-slate-300 font-bold tracking-wider opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity duration-200 shadow-2xl whitespace-nowrap z-50 select-none">
                    {{ $bskyAccount->last_synced_at->format('Y/m/d H:i:s') }}
                  </div>
                </div>
              @endif
            </div>
          </div>
          @if ($bskyAccount->description)
            <p class="text-slate-400 text-xs md:text-sm w-full leading-relaxed whitespace-pre-wrap text-left mx-auto md:mx-0">{!! $bskyAccount->html_description !!}</p>
          @endif
        </div>
      </div>
    </div>

    <!-- 同期認証エラー警告 (所有者本人のみ表示) -->
    @auth
      @if (Auth::id() === $user->id && isset($bskyAccount) && $bskyAccount->sync_error_type === 'auth_failed')
        <div class="mb-6 p-4 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex items-center gap-2.5">
            <x-icon name="alert" class="h-5 w-5 text-rose-400 shrink-0 animate-pulse" />
            <div>
              <p class="font-bold mb-0.5">Blueskyとの自動同期が停止しています</p>
              <p class="text-[11px] text-rose-400/90 leading-relaxed">アプリパスワードが変更または無効化された可能性があります。同期を再開するには、一度ログアウトして新しいアプリパスワードでログインし直してください。</p>
            </div>
          </div>
          <form action="{{ route('logout') }}" method="POST" class="shrink-0 m-0">
            @csrf
            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 hover:scale-[1.03] text-white font-bold text-[11px] transition duration-200 cursor-pointer shadow-md">
              ログアウトして再ログイン
            </button>
          </form>
        </div>
      @endif
    @endauth

    <!-- アラートトースト -->
    @if (session('success'))
      <div id="toastSuccess" class="mb-6 p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs flex items-center justify-between gap-3 shadow-md">
        <div class="flex items-center gap-2">
          <x-icon name="success" class="h-4 w-4 text-emerald-400" />
          <span>{{ session('success') }}</span>
        </div>
        @if (session('deleted_post_id'))
          <form action="{{ route('calendar.post.restore', ['handle' => $bskyAccount->handle, 'post_id' => session('deleted_post_id')]) }}" method="POST" class="m-0 shrink-0 select-none">
            @csrf
            <button type="submit" class="px-2 py-1 rounded bg-emerald-500/20 hover:bg-emerald-500/35 border border-emerald-500/30 text-emerald-300 hover:text-white text-[10px] font-extrabold tracking-wider transition duration-200 cursor-pointer active:scale-95 flex items-center gap-1">
              <x-icon name="sync" class="h-3 w-3" />
              元に戻す (Undo)
            </button>
          </form>
        @endif
      </div>
    @endif
    @if (session('error'))
      <div class="mb-6 p-4 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2">
        <x-icon name="alert" class="h-4 w-4" />
        <span>{{ session('error') }}</span>
      </div>
    @endif

    <!-- 各画面ごとの個別コンテンツ -->
    @yield('content')

    @include('layouts._footer')
  </div>

  <!-- ================= 同期中ローディングオーバーレイ ================= -->
  <div id="loadingOverlay" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm items-center justify-center hidden">
    <div class="text-center">
      <div class="inline-block relative w-16 h-16 mb-4">
        <div class="absolute inset-0 rounded-full border-4 border-sky-500/20"></div>
        <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-sky-400 animate-spin"></div>
      </div>
      <h4 id="loadingTitle" class="text-lg font-bold text-white mb-1.5">Blueskyから同期中...</h4>
      <p id="loadingSubtitle" class="text-xs text-slate-400">これには数秒かかる場合があります。しばらくお待ちください。</p>
    </div>
  </div>


  <script>
    // 同期ローディング表示
    function startSync() {
      const overlay = document.getElementById('loadingOverlay');
      overlay.classList.remove('hidden');
      overlay.classList.add('flex');

      // トーストを即時非表示にする
      const successToast = document.getElementById('toastSuccess');
      if (successToast) {
        successToast.style.display = 'none';
      }
    }

    // スクロールロック制御（スタック管理）
    let bodyScrollLockCount = 0;
    let scrollPosition = 0;
    function lockBodyScroll() {
      bodyScrollLockCount++;
      if (bodyScrollLockCount === 1) {
        scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.top = `-${scrollPosition}px`;
        document.body.style.width = '100%';
      }
    }
    function unlockBodyScroll() {
      bodyScrollLockCount = Math.max(0, bodyScrollLockCount - 1);
      if (bodyScrollLockCount === 0) {
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('position');
        document.body.style.removeProperty('top');
        document.body.style.removeProperty('width');
        window.scrollTo(0, scrollPosition);
      }
    }

    // 画像拡大モーダル関連
    document.addEventListener('DOMContentLoaded', () => {
      const modal = document.getElementById('imageModal');
      const modalImg = document.getElementById('modalImage');
      const prevBtn = document.getElementById('prevImageBtn');
      const nextBtn = document.getElementById('nextImageBtn');
      const indicator = document.getElementById('imageIndicator');

      let currentImages = [];
      let currentIndex = 0;

      // カルーセル表示状態のアップデート
      function updateCarousel() {
        if (currentImages.length <= 1) {
          prevBtn.classList.add('hidden');
          nextBtn.classList.add('hidden');
          indicator.classList.add('hidden');
          modalImg.classList.remove('cursor-zoom-in');
          modalImg.classList.add('cursor-default');
        } else {
          prevBtn.classList.remove('hidden');
          nextBtn.classList.remove('hidden');
          indicator.classList.remove('hidden');
          indicator.textContent = `${currentIndex + 1} / ${currentImages.length}`;
          modalImg.classList.remove('cursor-default');
          modalImg.classList.add('cursor-zoom-in');
        }

        const spinner = document.getElementById('modalImageSpinner');
        if (spinner) {
          spinner.classList.remove('hidden');
          spinner.classList.add('flex');
        }
        modalImg.classList.remove('opacity-100');
        modalImg.classList.add('opacity-0');

        modalImg.onload = () => {
          modalImg.classList.remove('opacity-0');
          modalImg.classList.add('opacity-100');
          if (spinner) {
            spinner.classList.add('hidden');
            spinner.classList.remove('flex');
          }
        };
        modalImg.onerror = () => {
          if (spinner) {
            spinner.classList.add('hidden');
            spinner.classList.remove('flex');
          }
        };

        modalImg.src = currentImages[currentIndex];
      }

      // 画像プレビューリンク of クリックイベント
      document.body.addEventListener('click', (e) => {
        const link = e.target.closest('.image-preview-link');
        if (link) {
          e.preventDefault();
          const postId = link.getAttribute('data-post-id');
          currentIndex = parseInt(link.getAttribute('data-index'), 10) || 0;

          // 同じポストの全プレビューリンクを取得
          const siblingLinks = Array.from(document.querySelectorAll(`.image-preview-link[data-post-id="${postId}"]`));
          currentImages = siblingLinks.map(l => l.getAttribute('href'));

          updateCarousel();
          
          modal.classList.remove('opacity-0', 'pointer-events-none');
          const modalContainer = modal.querySelector('.modal-content-card');
          if (modalContainer) {
            modalContainer.classList.remove('scale-95');
            modalContainer.classList.add('scale-100');
          }
        }
      });

      // 前へボタン
      prevBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        currentIndex = (currentIndex - 1 + currentImages.length) % currentImages.length;
        updateCarousel();
      });

      // 次へボタン
      nextBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        currentIndex = (currentIndex + 1) % currentImages.length;
        updateCarousel();
      });

      // 画像をクリックしても次の画像へ
      modalImg.addEventListener('click', (e) => {
        e.stopPropagation();
        if (currentImages.length > 1) {
          currentIndex = (currentIndex + 1) % currentImages.length;
          updateCarousel();
        }
      });

      // 閉じるボタンのイベント紐付け
      const modalClose = modal.querySelector('.modal-close-btn');
      if (modalClose) {
        modalClose.addEventListener('click', closeImageModal);
      }
    });

    function closeImageModal() {
      const modal = document.getElementById('imageModal');
      const modalImg = document.getElementById('modalImage');
      const modalContainer = modal.querySelector('.modal-content-card');
      
      modal.classList.add('opacity-0', 'pointer-events-none');
      if (modalContainer) {
        modalContainer.classList.remove('scale-100');
        modalContainer.classList.add('scale-95');
      }
      
      // アニメーション終了後に非表示にする
      setTimeout(() => {
        modalImg.src = '';
        modalImg.classList.remove('opacity-100');
        modalImg.classList.add('opacity-0');
        const spinner = document.getElementById('modalImageSpinner');
        if (spinner) {
          spinner.classList.add('hidden');
          spinner.classList.remove('flex');
        }
      }, 300);
    }

    // ================= モバイルドロワー開閉ロジック =================
    document.addEventListener('DOMContentLoaded', () => {
      const drawer = document.getElementById('mobileDrawer');
      const overlay = document.getElementById('drawerOverlay');
      const menuBtn = document.getElementById('mobileMenuBtn');
      const closeBtn = document.getElementById('closeDrawerBtn');

      function openDrawer() {
        if (!drawer || !overlay) return;
        drawer.classList.remove('translate-x-full');
        drawer.classList.add('translate-x-0');
        overlay.classList.remove('opacity-0', 'pointer-events-none');
        overlay.classList.add('opacity-100', 'pointer-events-auto');
        lockBodyScroll();
      }

      function closeDrawer() {
        if (!drawer || !overlay) return;
        drawer.classList.remove('translate-x-0');
        drawer.classList.add('translate-x-full');
        overlay.classList.remove('opacity-100', 'pointer-events-auto');
        overlay.classList.add('opacity-0', 'pointer-events-none');
        unlockBodyScroll();
      }

      if (menuBtn) menuBtn.addEventListener('click', openDrawer);
      if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
      if (overlay) overlay.addEventListener('click', closeDrawer);
    });

    // ================= アカウント切り替えパネルの開閉 =================
    // サイドバーはPC版とモバイルドロワーで2回描画されるため、id ではなくクラスで委譲処理する
    document.addEventListener('click', (e) => {
      const toggle = e.target.closest('.js-account-switcher-toggle');
      if (!toggle) return;

      const switcher = toggle.closest('.js-account-switcher');
      const panel = switcher?.querySelector('.js-account-switcher-panel');
      if (!panel) return;

      const willOpen = panel.classList.contains('hidden');
      panel.classList.toggle('hidden', !willOpen);
      toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      toggle.querySelector('.js-account-switcher-chevron')?.classList.toggle('rotate-180', willOpen);
    });

    // ================= ページ上部スクロールボタン (Back to Top) ロジック =================
    document.addEventListener('DOMContentLoaded', () => {
      const backToTopBtn = document.getElementById('backToTopBtn');
      
      if (backToTopBtn) {
        window.addEventListener('scroll', () => {
          if (window.scrollY > 300) {
            backToTopBtn.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-4');
            backToTopBtn.classList.add('opacity-100', 'pointer-events-auto', 'translate-y-0');
          } else {
            backToTopBtn.classList.remove('opacity-100', 'pointer-events-auto', 'translate-y-0');
            backToTopBtn.classList.add('opacity-0', 'pointer-events-none', 'translate-y-4');
          }
        });

        backToTopBtn.addEventListener('click', () => {
          window.scrollTo({
            top: 0,
            behavior: 'smooth'
          });
        });
      }
    });

    // ================= ミニカレンダー非同期（Ajax）月切り替え =================
    document.addEventListener('click', async (e) => {
      const navBtn = e.target.closest('.js-calendar-nav');
      if (!navBtn) return;

      e.preventDefault();

      const container = document.getElementById('miniCalendarContainer');
      if (!container) {
        // コンテナがなければ通常のページ遷移へフォールバック
        window.location.href = navBtn.getAttribute('href');
        return;
      }

      const handle = container.getAttribute('data-handle');
      const year = navBtn.getAttribute('data-year');
      const month = navBtn.getAttribute('data-month');

      if (!handle || !year || !month) {
        window.location.href = navBtn.getAttribute('href');
        return;
      }

      // フェードアウト
      const grid = document.getElementById('calendarGrid');
      if (grid) {
        grid.style.opacity = '0';
      } else {
        container.style.opacity = '0.5';
      }

      try {
        const url = `/profile/${handle}/calendar-api/${year}/${month}`;
        const response = await fetch(url, {
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        if (!response.ok) throw new Error('Network response was not ok');

        const html = await response.text();

        // 差し替え (フェードトランジション用に少しディレイを置く)
        setTimeout(() => {
          container.innerHTML = html;
          
          const newGrid = document.getElementById('calendarGrid');
          if (newGrid) {
            // 一瞬 opacity 0 に設定
            newGrid.style.transition = 'none';
            newGrid.style.opacity = '0';
            
            // 強制リフロー (リフレッシュ)
            newGrid.offsetHeight;
            
            // トランジションを有効化してフェードイン
            newGrid.style.transition = 'opacity 150ms ease-in-out';
            newGrid.style.opacity = '1';
          } else {
            container.style.opacity = '1';
          }
        }, 150);

      } catch (error) {
        console.error('Failed to switch calendar asynchronously:', error);
        // エラー時は安全のために通常のページ遷移へフォールバック
        window.location.href = navBtn.getAttribute('href');
      }
    });

  </script>

  <x-confirm-modal />

  <!-- ================= 画像拡大モーダル ================= -->
  <x-modal id="imageModal" maxWidth="max-w-4xl" heightClass="h-[80vh] md:h-[75vh]" :showClose="true">
    <!-- 前へボタン (カード内に absolute 配置して、画像の左右に重ねる) -->
    <button id="prevImageBtn" class="absolute left-4 top-1/2 -translate-y-1/2 p-2.5 rounded-full cursor-pointer bg-slate-950/60 border border-slate-800/80 text-white hover:text-white hover:bg-slate-800/80 transition duration-300 z-30 shadow-2xl hidden" style="margin-top: -20px;">
      <x-icon name="chevron-left" class="h-5 w-5" />
    </button>

    <!-- 次へボタン -->
    <button id="nextImageBtn" class="absolute right-4 top-1/2 -translate-y-1/2 p-2.5 rounded-full cursor-pointer bg-slate-950/60 border border-slate-800/80 text-white hover:text-white hover:bg-slate-800/80 transition duration-300 z-30 shadow-2xl hidden" style="margin-top: -20px;">
      <x-icon name="chevron-right" class="h-5 w-5" />
    </button>

    <!-- 画像表示エリア -->
    <div class="flex-1 bg-slate-950/80 flex items-center justify-center relative p-6 select-none overflow-hidden">
      <!-- ローディングスピナー -->
      <div id="modalImageSpinner" class="absolute inset-0 items-center justify-center pointer-events-none hidden">
        <svg class="w-8 h-8 text-sky-500 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>
      <img id="modalImage" src="" alt="拡大画像" class="max-w-full max-h-full object-contain rounded-lg shadow-lg opacity-0 transition-opacity duration-300 z-10 cursor-zoom-in">
      <!-- インジケーター（1/3 などの枚数表示） -->
      <div id="imageIndicator" class="absolute bottom-4 left-1/2 -translate-x-1/2 px-3 py-1.5 rounded-full bg-slate-900/90 border border-slate-800 text-[10px] font-bold text-slate-300 tracking-wider hidden shadow-lg z-20"></div>
    </div>
  </x-modal>

  <!-- 子ビューから動的に挿入されるモーダル用のスタック -->
  @stack('modals')


  <!-- ================= ページ上部スクロールボタン (Back to Top) ================= -->
  <button id="backToTopBtn" class="fixed bottom-22 lg:bottom-6 right-6 z-40 w-12 h-12 rounded-full glass-card border border-slate-800 hover:border-sky-500/50 flex items-center justify-center text-slate-400 hover:text-sky-400 shadow-xl opacity-0 pointer-events-none transition-all duration-300 transform translate-y-4 hover:scale-110 active:scale-95 cursor-pointer">
    <x-icon name="chevron-up" class="h-5.5 w-5.5" stroke-width="2.5" />
  </button>

  <!-- ================= モバイル専用フローティングメニューボタン ================= -->
  <button id="mobileMenuBtn" class="fixed bottom-6 right-6 z-40 lg:hidden w-12 h-12 rounded-full bg-linear-to-br from-sky-400 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-sky-500/30 hover:scale-110 active:scale-95 transition duration-300 cursor-pointer">
    <x-icon name="menu" class="h-5.5 w-5.5" stroke-width="2.5" />
  </button>

  <!-- ================= モバイルドロワー背景オーバーレイ ================= -->
  <div id="drawerOverlay" class="fixed inset-0 z-45 bg-slate-950/60 backdrop-blur-xs opacity-0 pointer-events-none transition-opacity duration-300"></div>

  <!-- ================= モバイル専用スライドドロワー ================= -->
  <div id="mobileDrawer" class="fixed top-0 right-0 h-full w-80 max-w-[85vw] z-50 bg-slate-950/95 backdrop-blur-md border-l border-slate-850 p-5 space-y-6 overflow-y-auto transform translate-x-full transition-transform duration-300 shadow-2xl flex flex-col lg:hidden">
    <!-- ドロワーヘッダー（タイトル＆閉じるボタン） -->
    <div class="flex items-center justify-between border-b border-slate-850 pb-3 mb-2 shrink-0 select-none">
      <h3 class="text-sm font-bold text-white tracking-wider uppercase flex items-center gap-1.5">
        <x-icon name="menu" class="h-4 w-4 text-sky-400" stroke-width="2.5" />
        Menu
      </h3>
      <button id="closeDrawerBtn" class="p-1.5 rounded-lg cursor-pointer bg-slate-900 border border-slate-800 text-slate-400 hover:text-white transition duration-300">
        <x-icon name="close" class="h-4 w-4" stroke-width="2.5" />
      </button>
    </div>
    
    <!-- ドロワーコンテンツ (PCのサイドバーコンテンツをそのまま再利用) -->
    <div class="flex-1 space-y-6">
      @include('calendar._sidebar')
    </div>
  </div>
</body>
</html>
