{{-- ログイン状態を保持する（remember me）共通コンポーネント --}}
{{-- hidden を先に置いて、チェックを外したときも remember=0 が必ず送られるようにする --}}
<div class="flex items-start gap-2.5 pt-2">
  <input type="hidden" name="remember" value="0">
  <input type="checkbox" name="remember" id="remember" value="1" @checked(old('remember', '1') !== '0')
         class="mt-0.5 h-4 w-4 rounded-sm border-slate-700/60 bg-slate-900/60 text-sky-500 focus:ring-sky-500 cursor-pointer">
  <label for="remember" class="text-xs text-slate-400 leading-normal select-none">
    ログイン状態を保持する
    <span class="block mt-0.5 text-[10px] text-slate-500">
      共用のパソコンでは外すことをおすすめします。
    </span>
  </label>
</div>
