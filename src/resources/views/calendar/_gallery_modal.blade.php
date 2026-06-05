@push('modals')
<!-- ギャラリー用拡大ライトボックスモーダル -->
<x-modal id="galleryModal" maxWidth="max-w-5xl" heightClass="max-h-[85vh] md:h-[80vh] overflow-y-auto md:overflow-hidden" :showClose="true">
  <div class="flex flex-col md:flex-row h-full">
    <!-- 左側: 画像表示エリア -->
    <div class="w-full md:flex-1 h-[45vh] md:h-auto bg-slate-950/80 flex items-center justify-center relative p-2 md:p-4 select-none shrink-0 border-b md:border-b-0 border-slate-800">
      <!-- ローディングスピナー -->
      <div id="galleryModalSpinner" class="absolute inset-0 items-center justify-center pointer-events-none hidden">
        <svg class="w-8 h-8 text-sky-500 animate-spin" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>
      <img id="galleryModalImage" src="" alt="拡大画像" class="max-w-full max-h-full object-contain rounded-lg shadow-lg opacity-0 transition-opacity duration-300 z-10">
    </div>

    <!-- 右側: 投稿情報エリア -->
    <div class="w-full md:w-85 lg:w-96 flex flex-col justify-between md:border-l border-slate-800 p-4 md:p-6 bg-slate-900/40 backdrop-blur-xs overflow-visible md:overflow-y-auto">
      
      <!-- 上部コンテンツ: 投稿者 & 投稿本文 -->
      <div class="space-y-4 md:space-y-5">
        <!-- 投稿者プロフィール -->
        <div class="flex items-center gap-3">
          <img id="galleryModalAvatar" src="" alt="アバター" class="w-10 h-10 rounded-full border border-slate-800 object-cover">
          <div>
            <h4 id="galleryModalAuthorName" class="text-sm font-bold text-slate-200 leading-none mb-1"></h4>
            <span id="galleryModalAuthorHandle" class="text-[10px] text-sky-400 font-semibold block select-all"></span>
          </div>
        </div>

        <!-- 投稿本文 (HTML流し込み) -->
        <div class="text-sm text-slate-300 leading-relaxed warap-break-words whitespace-pre-wrap selection:bg-sky-500/20" id="galleryModalText">
        </div>
      </div>

      <!-- 下部コンテンツ: メタデータ & アクションボタン -->
      <div class="mt-6 md:mt-8 pt-4 md:pt-5 border-t border-slate-800/60 space-y-3.5">
        <!-- 投稿日時 -->
        <div class="flex items-center gap-2 text-xs text-slate-500 font-medium">
          <x-icon name="clock" class="w-3.5 h-3.5 text-slate-600" />
          <span id="galleryModalDate"></span>
        </div>

        <!-- 各種アクションリンク -->
        <div class="flex flex-col gap-2">
          <!-- 日付アーカイブで見る -->
          <a id="galleryModalDetailLink" href="" class="w-full py-2.5 px-4 rounded-xl border border-slate-800 bg-slate-950/40 hover:bg-slate-800/30 text-slate-300 hover:text-white text-xs font-semibold text-center transition duration-300 flex items-center justify-center gap-2">
            <x-icon name="calendar" class="w-4 h-4" />
            <span>この日のログを表示</span>
          </a>

          <!-- Blueskyで開く -->
          <a id="galleryModalBskyLink" href="" target="_blank" rel="noopener noreferrer" class="w-full py-2.5 px-4 rounded-xl bg-sky-600 hover:bg-sky-500 active:scale-98 text-white text-xs font-bold text-center shadow-lg shadow-sky-600/10 hover:shadow-sky-500/20 transition duration-300 flex items-center justify-center gap-2">
            <x-icon name="link" class="w-4 h-4" />
            <span>Bluesky で開く ➔</span>
          </a>
        </div>
      </div>

    </div>
  </div>
</x-modal>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('galleryModal');
    if (!modal) return;

    const modalClose = modal.querySelector('.modal-close-btn');
    const modalImage = document.getElementById('galleryModalImage');
    const modalAvatar = document.getElementById('galleryModalAvatar');
    const modalAuthorName = document.getElementById('galleryModalAuthorName');
    const modalAuthorHandle = document.getElementById('galleryModalAuthorHandle');
    const modalText = document.getElementById('galleryModalText');
    const modalDate = document.getElementById('galleryModalDate');
    const modalDetailLink = document.getElementById('galleryModalDetailLink');
    const modalBskyLink = document.getElementById('galleryModalBskyLink');
    const modalContainer = modal.querySelector('.modal-content-card');

    // ギャラリーの画像リンクを取得
    const setupGalleryLinks = () => {
        const links = document.querySelectorAll('.gallery-image-link');
        links.forEach(link => {
            // 重複登録防止
            if (link.dataset.clickEventBound) return;
            link.dataset.clickEventBound = 'true';

            link.addEventListener('click', (e) => {
                e.preventDefault();
                
                const fullsize = link.getAttribute('data-fullsize');
                const avatar = link.getAttribute('data-avatar');
                const name = link.getAttribute('data-author-name');
                const handle = link.getAttribute('data-author-handle');
                const htmlText = link.getAttribute('data-html-text');
                const date = link.getAttribute('data-date');
                const detailUrl = link.getAttribute('data-detail-url');
                const bskyUrl = link.getAttribute('data-bsky-url');

                // モーダルへ流し込み
                const spinner = document.getElementById('galleryModalSpinner');
                if (spinner) {
                    spinner.classList.remove('hidden');
                    spinner.classList.add('flex');
                }

                modalImage.src = fullsize;
                modalImage.style.opacity = '0';
                modalImage.onload = () => {
                    modalImage.style.opacity = '1';
                    if (spinner) {
                        spinner.classList.add('hidden');
                        spinner.classList.remove('flex');
                    }
                };
                modalImage.onerror = () => {
                    if (spinner) {
                        spinner.classList.add('hidden');
                        spinner.classList.remove('flex');
                    }
                };

                modalAvatar.src = avatar || 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&h=150&q=80';
                modalAuthorName.textContent = name;
                modalAuthorHandle.textContent = handle ? '@' + handle : '';
                modalText.innerHTML = htmlText || '';
                modalDate.textContent = date;
                modalDetailLink.href = detailUrl || '#';
                
                if (bskyUrl) {
                    modalBskyLink.href = bskyUrl;
                    modalBskyLink.classList.remove('hidden');
                } else {
                    modalBskyLink.classList.add('hidden');
                }

                // モーダル表示 (MutationObserverが自動でスクロールロックする)
                modal.classList.remove('opacity-0', 'pointer-events-none');
                if (modalContainer) {
                    modalContainer.classList.remove('scale-95');
                    modalContainer.classList.add('scale-100');
                }
            });
        });
    };

    const closeModal = () => {
        modal.classList.add('opacity-0', 'pointer-events-none');
        if (modalContainer) {
            modalContainer.classList.remove('scale-100');
            modalContainer.classList.add('scale-95');
        }
        
        // アニメーション完了後に画像を空にしてチラつきを防ぐ
        setTimeout(() => {
            modalImage.src = '';
            const spinner = document.getElementById('galleryModalSpinner');
            if (spinner) {
                spinner.classList.add('hidden');
                spinner.classList.remove('flex');
            }
        }, 300);
    };

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }

    // 初期化
    setupGalleryLinks();

    // Ajaxでの更新を考慮して監視 (もしあれば)
    const observer = new MutationObserver(() => {
        setupGalleryLinks();
    });
    const timeline = document.querySelector('.timeline-container');
    if (timeline) {
        observer.observe(timeline, { childList: true, subtree: true });
    }
});
</script>
@endpush

