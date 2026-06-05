@php
  // デフォルト値の設定 (メイン画像用スタイル)
  $containerClass = $containerClass ?? 'grid grid-cols-2 gap-2 mb-2 rounded-xl overflow-hidden';
  $linkClass = $linkClass ?? 'image-preview-link cursor-pointer overflow-hidden border border-slate-900 hover:border-slate-800 transition';
  $imgClass = $imgClass ?? 'w-full h-40 md:h-48 object-cover hover:scale-105 transition duration-300';
@endphp

@if (!empty($images))
  <div class="{{ $containerClass }}">
    @foreach ($images as $img)
      <a href="{{ $img['fullsize'] ?? '#' }}" target="_blank" class="{{ $linkClass }}"
         data-post-id="{{ $postId }}"
         data-index="{{ $loop->index }}">
        <img src="{{ $img['thumb'] }}" alt="{{ $img['alt'] ?? '添付画像' }}" class="{{ $imgClass }}">
      </a>
    @endforeach
  </div>
@endif
