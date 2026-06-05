<!-- フッター -->
<div class="w-full text-center py-6 mt-8 border-t border-slate-900/60 z-10 select-none space-y-2">
  <p class="text-[11px] font-semibold tracking-wider">
    <a href="{{ route('about') }}" class="text-slate-600 hover:text-sky-400 transition duration-300 cursor-pointer">
      {{ config('app.name', 'SkLoG') }}について
    </a>
  </p>
  <p class="text-[10px] text-slate-700 tracking-wider">
    &copy; 2026 {{ config('app.name', 'SkLoG') }}.
  </p>
</div>
