{{-- アプリパスワードの作り方（安全）共通コンポーネント --}}
<div class="bg-slate-900/40 rounded-lg border border-slate-800 p-4">
  <button type="button" id="toggleAppPwdInstructions" class="text-xs font-medium text-sky-400 hover:text-sky-300 flex items-center gap-1.5 focus:outline-none cursor-pointer">
    <x-icon name="info" class="h-4 w-4" stroke-width="2" />
    アプリパスワードの作り方（安全）
  </button>
  <div id="appPwdInstructions" class="hidden mt-3 text-[11px] text-slate-400 space-y-2 leading-relaxed border-t border-slate-800/80 pt-3">
    <p>通常のログインパスワードではなく、外部アプリ用の安全なパスワードを使用します。</p>
    <ol class="list-decimal pl-4 space-y-1">
      <li>Bluesky公式アプリで <strong class="text-slate-200">設定 ➔ プライバシーとセキュリティ</strong> を開きます。</li>
      <li>「アプリパスワード」を選択し、<strong class="text-slate-200">「アプリパスワードの追加」</strong> をタップします。</li>
      <li>名前に「{{ config('app.name', 'SkLoG') }}」などと入力して生成されたパスワード（ハイフンを含む16桁 of 文字列）をコピーし、上に貼り付けます。</li>
    </ol>
    <p class="text-[10px] text-sky-400/80">※ いつでもBlueskyの設定から削除可能で、通常のログインパスワードをシステムに明かす必要がないため安全です。</p>
  </div>
</div>

<script>
  document.getElementById('toggleAppPwdInstructions').addEventListener('click', function() {
    const el = document.getElementById('appPwdInstructions');
    el.classList.toggle('hidden');
  });
</script>
