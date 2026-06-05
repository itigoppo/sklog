@php
  $size = $size ?? 'w-6 h-6';
  $iconSize = $iconSize ?? 'h-3.5 w-3.5';
@endphp

@if ($avatarUrl)
  <img src="{{ $avatarUrl }}" alt="" class="{{ $size }} rounded-full object-cover border border-slate-800 shrink-0">
@else
  <div class="{{ $size }} rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center shrink-0">
    <x-icon name="user" class="{{ $iconSize }} text-slate-500" />
  </div>
@endif
