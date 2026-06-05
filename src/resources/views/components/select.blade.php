@props(['disabled' => false])

<div class="relative w-full">
  <select {{ $attributes->merge(['class' => 'w-full pl-3 pr-10 py-2 rounded-lg bg-slate-950 border border-slate-800 text-white text-xs focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition duration-200 cursor-pointer appearance-none']) }} {{ $disabled ? 'disabled' : '' }}>
    {{ $slot }}
  </select>
  <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-slate-500">
    <x-icon name="chevron-down" class="h-3.5 w-3.5" stroke-width="2.5" />
  </div>
</div>
