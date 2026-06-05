@extends('layouts.app')

@section('title', '設定 | ' . $bskyAccount->handle . ' のカレンダー | ' . config('app.name', 'SkLoG'))

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
  
  <!-- 左・中カラム：メイン設定カード -->
  <div class="lg:col-span-2 space-y-6">
    
    <!-- 特定ポストのインポートカード -->
    <div class="glass-card rounded-2xl p-6 border border-slate-800/80 space-y-4">
      <div class="flex items-center gap-2.5 border-b border-slate-850 pb-4 mb-2">
        <div class="p-2 rounded-lg bg-sky-500/10 text-sky-400">
          <x-icon name="sync" class="h-5 w-5" stroke-width="2" />
        </div>
        <div>
          <h3 class="text-base font-bold text-white tracking-wide">特定のポストを取り込む</h3>
          <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">
            APIの取得漏れや、誤って非表示にしたポストのURLを入力して再インポート（またはカレンダーへの復元）を行います。
          </p>
        </div>
      </div>

      <form action="{{ route('calendar.settings.import_posts', ['handle' => $bskyAccount->handle]) }}" method="POST" id="importPostsForm" class="space-y-4 m-0">
        @csrf
        <div class="space-y-2">
          <label for="urls" class="text-xs font-bold text-slate-300 block select-none">
            ポストのURL (複数ある場合は改行して入力してください)
          </label>
          <textarea 
            name="urls" 
            id="urls" 
            rows="6" 
            class="w-full rounded-xl bg-slate-950/60 border border-slate-800 focus:border-sky-500/60 focus:ring-1 focus:ring-sky-500/30 text-slate-200 text-xs px-3.5 py-3 placeholder-slate-600 transition duration-200 focus:outline-hidden font-mono leading-relaxed"
            placeholder="https://bsky.app/profile/{{ $bskyAccount->handle }}/post/3kp5u2jx5hk2b&#10;https://bsky.app/profile/{{ $bskyAccount->handle }}/post/3kp..."
            required></textarea>
          <span class="text-[10px] text-slate-500 block leading-relaxed">
            ※ご自身（{{ '@' . $bskyAccount->handle }}）のオリジナルポストのみが対象となります。他人のポストやリポストのURLはインポートできません。
          </span>
        </div>

        <div class="pt-2 flex items-center justify-end">
          <button 
            type="submit" 
            id="importSubmitBtn" 
            class="px-5 py-2.5 rounded-xl bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 hover:border-sky-500/50 text-sky-400 font-bold text-xs transition duration-300 flex items-center gap-2 active:scale-[0.98] cursor-pointer">
            <x-icon id="importBtnIcon" name="sync" class="h-4 w-4 shrink-0" stroke-width="2" />
            <span id="importBtnText">取り込みを実行</span>
          </button>
        </div>
      </form>
    </div>

    <!-- アプリパスワードの更新カード -->
    <div class="glass-card rounded-2xl p-6 border border-slate-800/80 space-y-4">
      <div class="flex items-center gap-2.5 border-b border-slate-850 pb-4 mb-2">
        <div class="p-2 rounded-lg bg-sky-500/10 text-sky-400">
          <x-icon name="key" class="h-5 w-5" stroke-width="2" />
        </div>
        <div>
          <h3 class="text-base font-bold text-white tracking-wide">アプリパスワードの更新</h3>
          <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">
            Bluesky側でアプリパスワードを再生成した場合や、同期エラー（認証エラー）を解除したい場合は、新しいアプリパスワードを入力して更新してください。
          </p>
        </div>
      </div>

      <form action="{{ route('calendar.settings.update_password', ['handle' => $bskyAccount->handle]) }}" method="POST" class="space-y-4 m-0">
        @csrf
        
        <!-- エラーメッセージ -->
        @error('app_password')
          <div class="p-3 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs">
            {{ $message }}
          </div>
        @enderror

        <div class="space-y-2">
          <label for="app_password" class="text-xs font-bold text-slate-300 block select-none">
            新しいアプリパスワード
          </label>
          <input 
            type="password" 
            name="app_password" 
            id="app_password" 
            placeholder="xxxx-xxxx-xxxx-xxxx"
            class="w-full rounded-xl bg-slate-950/60 border border-slate-800 focus:border-sky-500/60 focus:ring-1 focus:ring-sky-500/30 text-slate-200 text-xs px-3.5 py-3 transition duration-200 focus:outline-hidden tracking-wider"
            required>
        </div>

        <!-- アプリパスワードの作り方トグル -->
        <x-app-password-guide />

        <div class="pt-2 flex items-center justify-end">
          <button 
            type="submit" 
            class="px-5 py-2.5 rounded-xl bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 hover:border-sky-500/50 text-sky-400 font-bold text-xs transition duration-300 flex items-center gap-2 active:scale-[0.98] cursor-pointer">
            <x-icon name="sync" class="h-4 w-4 shrink-0" stroke-width="2" />
            <span>パスワードを更新</span>
          </button>
        </div>
      </form>
    </div>

    <!-- 退会処理カード -->
    <div class="glass-card rounded-2xl p-6 border border-rose-950/40 bg-rose-950/5 space-y-4">
      <div class="flex items-center gap-2.5 border-b border-rose-950/30 pb-4 mb-2">
        <div class="p-2 rounded-lg bg-rose-500/10 text-rose-400">
          <x-icon name="trash" class="h-5 w-5" stroke-width="2" />
        </div>
        <div>
          <h3 class="text-base font-bold text-rose-300 tracking-wide">退会（アカウントの削除）</h3>
          <p class="text-[11px] text-rose-400/80 mt-1 leading-relaxed">
            本サービスから完全に退会し、これまでに取り込んだすべてのポストやアバター画像、連携パスワードなどのデータをすべて消去します。
          </p>
        </div>
      </div>

      <div class="p-3.5 rounded-xl bg-rose-500/5 border border-rose-500/15 text-[11px] text-rose-300/90 leading-relaxed space-y-1">
        <p class="font-bold">⚠️ 注意事項:</p>
        <p>• 削除されたデータはバックアップを含め、一切復元することができません。</p>
        <p>• カレンダーURL（{{ route('calendar.show', ['handle' => $bskyAccount->handle]) }}）も即時無効となり、アクセス不可になります。</p>
      </div>

      <form action="{{ route('calendar.settings.delete_account', ['handle' => $bskyAccount->handle]) }}" method="POST" class="js-confirm-form m-0"
            data-confirm-title="退会とカレンダーデータの完全削除"
            data-confirm-message="本当に退会しますか？<br><br><span class='text-rose-400 font-bold'>この操作は取り消せません。</span>これまでに取り込んだすべてのポストやアバター画像、連携パスワードなどの情報は、即座にすべて消去され、元に戻すことはできません。"
            data-confirm-action="退会する（全データ削除）"
            data-confirm-btn-color="bg-rose-600 hover:bg-rose-500 shadow-lg shadow-rose-950/50">
        @csrf
        <div class="flex items-center justify-end">
          <button 
            type="submit" 
            class="px-5 py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 hover:border-rose-500/50 text-rose-400 font-bold text-xs transition duration-300 flex items-center gap-1.5 active:scale-[0.98] cursor-pointer">
            <x-icon name="trash" class="h-4 w-4 shrink-0" stroke-width="2" />
            <span>カレンダーを削除して退会</span>
          </button>
        </div>
      </form>
    </div>

  </div>

  <!-- 右カラム：アカウント情報サマリー -->
  <div class="space-y-6">
    <div class="glass-card rounded-2xl p-5 border border-slate-800/80 space-y-4">
      <h4 class="text-xs font-bold text-white mb-2 flex items-center gap-2 border-b border-slate-850 pb-3">
        <x-icon name="user" class="h-4 w-4 text-sky-400 shrink-0" />
        連携ステータス
      </h4>

      <div class="space-y-3.5 text-xs">
        <div class="flex items-center justify-between py-1.5 border-b border-slate-900">
          <span class="text-slate-400 font-bold">Bluesky ID</span>
          <span class="text-slate-200 font-mono font-bold tracking-tight select-all text-[10px]">
            {{ $bskyAccount->did }}
          </span>
        </div>

        <div class="flex items-center justify-between py-1.5 border-b border-slate-900">
          <span class="text-slate-400 font-bold">Blueskyポスト数</span>
          <span class="text-white font-extrabold tracking-wider">
            {{ number_format($bskyAccount->posts_count) }} <span class="text-[9px] text-slate-500 font-bold">posts</span>
          </span>
        </div>

        <div class="flex items-center justify-between py-1.5 border-b border-slate-900">
          <span class="text-slate-400 font-bold">{{ config('app.name', 'SkLoG') }}内ポスト数</span>
          <span class="text-white font-extrabold tracking-wider">
            {{ number_format($user->posts()->where('uri', 'not like', 'repost-%')->count()) }} <span class="text-[9px] text-slate-500 font-bold">posts</span>
          </span>
        </div>

        <div class="flex items-center justify-between py-1.5 border-b border-slate-900">
          <span class="text-slate-400 font-bold">{{ config('app.name', 'SkLoG') }}内削除ポスト数</span>
          <span class="text-rose-400 font-extrabold tracking-wider">
            {{ number_format($user->posts()->onlyTrashed()->where('uri', 'not like', 'repost-%')->count()) }} <span class="text-[9px] text-slate-500 font-bold">posts</span>
          </span>
        </div>

        <div class="flex items-center justify-between py-1.5 border-b border-slate-900">
          <span class="text-slate-400 font-bold">同期状態</span>
          @if ($bskyAccount->is_archive_complete)
            <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[9px] font-bold select-none whitespace-nowrap">
              過去ログ同期完了
            </span>
          @else
            <span class="px-2 py-0.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-[9px] font-bold select-none whitespace-nowrap">
              過去ログ未同期あり
            </span>
          @endif
        </div>
      </div>

      <!-- カレンダーへ戻るボタン -->
      <div class="pt-2">
        <a href="{{ route('calendar.show', ['handle' => $bskyAccount->handle]) }}" class="w-full py-2.5 rounded-xl cursor-pointer bg-slate-900 hover:bg-slate-850 border border-slate-800 hover:border-slate-700 text-slate-300 hover:text-white font-bold text-xs transition duration-300 flex items-center justify-center gap-1.5 shadow-md active:scale-[0.98]">
          <x-icon name="home" class="h-4 w-4 text-sky-400 shrink-0" stroke-width="2" />
          <span>カレンダーへ戻る</span>
        </a>
      </div>
    </div>
  </div>

</div>

<!-- 取り込み実行中のローディングJS -->
<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('importPostsForm');
    const submitBtn = document.getElementById('importSubmitBtn');
    const btnText = document.getElementById('importBtnText');
    const btnIcon = document.getElementById('importBtnIcon');

    if (form && submitBtn) {
      form.addEventListener('submit', (e) => {
        // ボタンを無効化
        submitBtn.disabled = true;
        submitBtn.classList.remove('hover:bg-sky-500/20', 'hover:border-sky-500/50', 'text-sky-400');
        submitBtn.classList.add('opacity-60', 'cursor-not-allowed', 'text-sky-500/60');
        
        // テキストをインポート中に変更
        btnText.textContent = "インポート中...";
        
        // アイコンをローディングスピナーに変更
        btnIcon.outerHTML = `
          <svg id="importBtnIcon" class="animate-spin h-4 w-4 text-sky-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        `;
      });
    }
  });
</script>
@endsection
