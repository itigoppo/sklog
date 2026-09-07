@php
  // 7枚以上 (app.bsky.embed.gallery) は 6枚だけ見せて、6枚目に残り枚数を重ねる
  $imageCount = count($images ?? []);
  $maxVisible = $maxVisible ?? 6;
  $hiddenCount = max(0, $imageCount - $maxVisible);

  // 5枚以上は2列だと縦に伸びすぎるため、3列にして高さを抑える
  $isManyImages = $imageCount >= 5;

  // デフォルト値の設定 (メイン画像用スタイル)
  $containerClass = $containerClass ?? 'grid ' . ($isManyImages ? 'grid-cols-3' : 'grid-cols-2') . ' gap-2 mb-2 rounded-xl overflow-hidden';
  $linkClass = $linkClass ?? 'image-preview-link cursor-pointer overflow-hidden border border-slate-900 hover:border-slate-800 transition';
  $imgClass = $imgClass ?? 'w-full ' . ($isManyImages ? 'h-28 md:h-32' : 'h-40 md:h-48') . ' object-cover hover:scale-105 transition duration-300';
@endphp

@if (!empty($images))
  <div class="{{ $containerClass }}">
    @foreach ($images as $img)
      @php
        // 拡大モーダルは同じ data-post-id のリンクを集めてスライドを組むため、
        // 省略した画像もリンクごと消さずに hidden で隠す（隠した分もモーダルで送れる）
        $isOverflow = $hiddenCount > 0 && $loop->index >= $maxVisible;
        $isLastVisible = $hiddenCount > 0 && $loop->index === $maxVisible - 1;
      @endphp
      <a href="{{ $img['fullsize'] ?? '#' }}" target="_blank"
         class="{{ $linkClass }}{{ $isLastVisible ? ' relative' : '' }}{{ $isOverflow ? ' hidden' : '' }}"
         data-post-id="{{ $postId }}"
         data-index="{{ $loop->index }}">
        <img src="{{ $img['thumb'] }}" alt="{{ $img['alt'] ?? '添付画像' }}" class="{{ $imgClass }}">
        @if ($isLastVisible)
          <span class="absolute inset-0 flex items-center justify-center bg-slate-950/70 text-white text-lg font-bold backdrop-blur-[1px] pointer-events-none">
            +{{ $hiddenCount }}
          </span>
        @endif
      </a>
    @endforeach
  </div>
@endif
