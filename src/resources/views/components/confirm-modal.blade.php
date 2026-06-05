<!-- 洗練されたカスタム確認モーダル共通コンポーネント -->
<x-modal id="custom-confirm-modal" maxWidth="max-w-sm" heightClass="h-auto" :showClose="false">
  <div class="p-6">
    <div class="flex items-start gap-4 mb-5">
      <!-- アイコンエリア (情報マーク) -->
      <div class="w-10 h-10 rounded-full bg-sky-500/10 border border-sky-500/20 flex items-center justify-center shrink-0 text-sky-400">
        <x-icon name="info" class="h-5 w-5" stroke-width="2" />
      </div>
      <div class="min-w-0 flex-1">
        <h3 id="confirm-modal-title" class="text-sm font-bold text-white tracking-wide">確認</h3>
        <p id="confirm-modal-message" class="text-xs text-slate-400 mt-2 leading-relaxed wrap-break-words">本当に実行しますか？</p>
      </div>
    </div>
    <div class="flex items-center justify-end gap-3 border-t border-slate-800/60 pt-4">
      <button id="confirm-modal-cancel" type="button" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition cursor-pointer select-none">
        キャンセル
      </button>
      <button id="confirm-modal-btn" type="button" class="px-4 py-2 rounded-lg text-xs font-bold text-white transition cursor-pointer active:scale-[0.98] select-none">
        実行する
      </button>
    </div>
  </div>
</x-modal>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const confirmModal = document.getElementById('custom-confirm-modal');
    if (!confirmModal) return;

    const confirmTitle = confirmModal.querySelector('#confirm-modal-title');
    const confirmMessage = confirmModal.querySelector('#confirm-modal-message');
    const confirmBtn = confirmModal.querySelector('#confirm-modal-btn');
    const cancelBtn = confirmModal.querySelector('#confirm-modal-cancel');
    const contentCard = confirmModal.querySelector('.modal-content-card');
    let formToSubmit = null;

    const closeModal = () => {
      confirmModal.classList.add('opacity-0', 'pointer-events-none');
      if (contentCard) {
        contentCard.classList.remove('scale-100');
        contentCard.classList.add('scale-95');
      }
      formToSubmit = null;
    };

    // フォームの送信イベントを監視 (イベント委譲)
    document.body.addEventListener('submit', (e) => {
      const form = e.target.closest('.js-confirm-form');
      if (form && formToSubmit !== form) {
        e.preventDefault();
        formToSubmit = form;

        const button = e.submitter || form.querySelector('button[type="submit"]') || form;
        const message = button.getAttribute('data-confirm-message') || form.getAttribute('data-confirm-message') || '本当に実行しますか？';
        const title = button.getAttribute('data-confirm-title') || form.getAttribute('data-confirm-title') || '確認';
        const actionText = button.getAttribute('data-confirm-action') || form.getAttribute('data-confirm-action') || '送信する';
        const btnColorClass = button.getAttribute('data-confirm-btn-color') || form.getAttribute('data-confirm-btn-color') || 'bg-sky-600 hover:bg-sky-500';

        if (confirmTitle) confirmTitle.textContent = title;
        if (confirmMessage) confirmMessage.innerHTML = message;
        if (confirmBtn) {
          confirmBtn.textContent = actionText;
          confirmBtn.className = `px-4 py-2 rounded-lg text-xs font-bold text-white transition cursor-pointer active:scale-[0.98] select-none ${btnColorClass}`;
        }

        // 表示切り替え (MutationObserver が検知して自動でスクロールロックする)
        confirmModal.classList.remove('opacity-0', 'pointer-events-none');
        setTimeout(() => {
          if (contentCard) {
            contentCard.classList.remove('scale-95');
            contentCard.classList.add('scale-100');
          }
        }, 10);
      }
    });

    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    if (confirmBtn) {
      confirmBtn.addEventListener('click', () => {
        if (formToSubmit) {
          formToSubmit.submit();
        }
        closeModal();
      });
    }
  });
</script>
