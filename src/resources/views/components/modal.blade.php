@props([
    'id',
    'maxWidth' => 'max-w-3xl',
    'heightClass' => 'max-h-[85vh]',
    'showClose' => true,
    // 読み上げ用の名前。見出しがあるときは labelledby にその id を渡す
    'label' => null,
    'labelledby' => null,
    'describedby' => null,
])

<!-- 汎用モーダルコンポーネント -->
{{-- 閉じている間は inert にして、Tab での移動や読み上げの対象から外す（開閉時の切り替えは下のスクリプト） --}}
<div id="{{ $id }}" class="fixed inset-0 z-50 flex items-center justify-center p-4 md:p-6 opacity-0 pointer-events-none transition-all duration-300" role="dialog" aria-modal="true" inert
  @if ($labelledby) aria-labelledby="{{ $labelledby }}" @elseif ($label) aria-label="{{ $label }}" @endif
  @if ($describedby) aria-describedby="{{ $describedby }}" @endif>
  <!-- 背景オーバーレイ -->
  <div class="absolute inset-0 bg-slate-950/85 backdrop-blur-md transition-opacity duration-300 modal-bg-overlay"></div>

  <!-- 閉じるボタン (モバイル時は fixed 画面右上、デスクトップ時は absolute カード右上) -->
  @if($showClose)
    <button type="button" class="fixed md:absolute top-4 right-4 md:top-4 md:right-4 z-50 md:z-20 p-2 rounded-full bg-slate-950/60 border border-slate-800/80 text-slate-400 hover:text-white hover:bg-slate-800/80 transition duration-300 cursor-pointer active:scale-95 modal-close-btn" aria-label="閉じる">
      <x-icon name="close" class="w-5 h-5" stroke-width="2.5" />
    </button>
  @endif

  <!-- モーダル本体 (カード) -->
  <div class="relative w-full {{ $maxWidth }} {{ $heightClass }} flex flex-col bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl z-10 transition-transform duration-300 scale-95 modal-content-card">
    {{ $slot }}
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('{{ $id }}');
    if (!modal) return;

    const overlay = modal.querySelector('.modal-bg-overlay');
    const closeBtn = modal.querySelector('.modal-close-btn');
    const cancelBtn = modal.querySelector('#confirm-modal-cancel');
    const contentCard = modal.querySelector('.modal-content-card');

    let localScrollPosition = 0;
    let wasClosed = modal.classList.contains('opacity-0') || modal.classList.contains('hidden');

    // スクロールロックの定義
    const lockScroll = () => {
      if (typeof lockBodyScroll === 'function') {
        lockBodyScroll();
      } else {
        localScrollPosition = window.pageYOffset || document.documentElement.scrollTop;
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.top = `-${localScrollPosition}px`;
        document.body.style.width = '100%';
      }
    };

    const unlockScroll = () => {
      if (typeof unlockBodyScroll === 'function') {
        unlockBodyScroll();
      } else {
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('position');
        document.body.style.removeProperty('top');
        document.body.style.removeProperty('width');
        window.scrollTo(0, localScrollPosition);
      }
    };

    // 閉じるアクションのトリガー
    const triggerClose = () => {
      if (closeBtn) {
        closeBtn.click();
      } else if (cancelBtn) {
        cancelBtn.click();
      } else {
        // フォールバック: クラスを直接変更して閉じる
        modal.classList.add('opacity-0', 'pointer-events-none');
        if (contentCard) {
          contentCard.classList.remove('scale-100');
          contentCard.classList.add('scale-95');
        }
      }
    };

    // 背景オーバーレイのクリックで閉じる
    if (overlay) {
      overlay.addEventListener('click', (e) => {
        e.stopPropagation();
        triggerClose();
      });
    }

    const isOpen = () => !modal.classList.contains('opacity-0') && !modal.classList.contains('hidden');

    // フォーカスを移せる要素 (表示されているものだけ)
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const getFocusable = () => Array.from(modal.querySelectorAll(focusableSelector))
      .filter((el) => el.offsetParent !== null || getComputedStyle(el).position === 'fixed');

    // 開く前にフォーカスがあった要素。閉じたらここへ戻す
    let returnFocusTo = null;

    const onOpened = () => {
      returnFocusTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      modal.inert = false;
      // data-autofocus があればそれを、無ければ最初の操作できる要素を選ぶ
      const target = modal.querySelector('[data-autofocus]') || getFocusable()[0];
      if (target) {
        target.focus({ preventScroll: true });
      } else {
        modal.setAttribute('tabindex', '-1');
        modal.focus({ preventScroll: true });
      }
    };

    const onClosed = () => {
      modal.inert = true;
      if (returnFocusTo && document.contains(returnFocusTo)) {
        returnFocusTo.focus({ preventScroll: true });
      }
      returnFocusTo = null;
    };

    // ESCキーで閉じる / Tab でモーダルの外へ出ないようにする
    window.addEventListener('keydown', (e) => {
      if (!isOpen()) return;

      if (e.key === 'Escape') {
        triggerClose();
        return;
      }

      if (e.key === 'Tab') {
        const focusable = getFocusable();
        if (focusable.length === 0) {
          e.preventDefault();
          return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (!modal.contains(document.activeElement)) {
          e.preventDefault();
          first.focus();
        } else if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    });

    // MutationObserver による表示クラス（表示・非表示）の自動監視
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.attributeName === 'class') {
          const hasHiddenClass = modal.classList.contains('hidden');
          const hasOpacityZeroClass = modal.classList.contains('opacity-0');
          const isCurrentlyClosed = hasHiddenClass || hasOpacityZeroClass;

          if (isCurrentlyClosed && !wasClosed) {
            unlockScroll();
            wasClosed = true;
            onClosed();
          } else if (!isCurrentlyClosed && wasClosed) {
            lockScroll();
            wasClosed = false;
            onOpened();
          }
        }
      });
    });

    observer.observe(modal, { attributes: true, attributeFilter: ['class'] });
  });
</script>
