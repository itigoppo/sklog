<!-- フッター -->
<footer class="w-full text-center py-6 mt-8 border-t border-slate-900/60 z-10 select-none space-y-2">
  <p class="text-xs font-semibold tracking-wider">
    <a href="{{ route('about') }}" class="text-slate-400 hover:text-sky-400 transition duration-300 cursor-pointer">
      {{ config('app.name', 'SkLoG') }}について
    </a>
  </p>
  <p class="text-[10px] text-slate-400 tracking-wider">
    &copy; 2026 {{ config('app.name', 'SkLoG') }}.
  </p>
</footer>
