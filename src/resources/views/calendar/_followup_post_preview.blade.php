@php
  $type = $type ?? 'followup'; // 'followup' or 'prev'
  $targetPost = $type === 'followup' ? $followupPost : $prevPost;
  
  $images = [];
  $external = null;
  $hasQuote = false;
  
  if ($targetPost) {
    $embed = $targetPost->raw_data['post']['embed'] ?? null;
    $images = \App\Models\Post::extractImages($embed);

    if ($embed && str_contains($embed['$type'] ?? '', 'app.bsky.embed.external')) {
      $external = $embed['external'] ?? null;
    }
    
    if ($embed) {
      $embedType = $embed['$type'] ?? '';
      $hasQuote = str_contains($embedType, 'app.bsky.embed.record');
    }
  }
@endphp

@if ($targetPost)
  <div id="{{ $previewId }}" class="hidden mb-3.5 p-4 pl-5 rounded-r-xl border-l-2 border-sky-500/30 bg-slate-950/30 text-xs text-slate-400 select-none">
    <!-- ヘッダー -->
    <div class="flex items-center justify-between pb-2 mb-2.5 border-b border-slate-900/60">
      @if ($type === 'followup')
        <span class="inline-flex items-center gap-1 text-[8px] text-sky-400 font-bold tracking-wider uppercase leading-none">
          <x-icon name="reply" class="h-2.5 w-2.5 text-sky-400 rotate-180 translate-y-px" stroke-width="2" />
          <span>続きの投稿</span>
        </span>
      @else
        <span class="inline-flex items-center gap-1 text-[8px] text-slate-500 font-bold tracking-wider uppercase leading-none">
          <x-icon name="list" class="h-3 w-3 text-slate-500 shrink-0" />
          <span>直前の投稿</span>
        </span>
      @endif
      
      @if ($targetPost->bsky_url)
        <a href="{{ $targetPost->bsky_url }}" target="_blank" class="flex items-center gap-0.5 text-[9px] font-bold text-slate-500 hover:text-sky-400 transition cursor-pointer">
          <span>Blueskyで表示 ➔</span>
        </a>
      @endif
    </div>

    <!-- 本文 -->
    <p class="text-slate-300 leading-relaxed whitespace-pre-wrap">{!! $targetPost->html_text !!}</p>

    <!-- 外部リンクカード -->
    @if ($external)
      <a href="{{ $external['uri'] }}" target="_blank" class="block cursor-pointer rounded-lg overflow-hidden bg-slate-950 border border-slate-900 hover:border-slate-800 transition mt-2.5 text-[10px] select-none">
        <div class="p-2.5 flex gap-3 items-center">
          @if (isset($external['thumb']))
            <img src="{{ $external['thumb'] }}" alt="" class="w-12 h-12 object-cover rounded shrink-0 opacity-85">
          @endif
          <div class="min-w-0 flex-1">
            @php $domain = parse_url($external['uri'], PHP_URL_HOST); @endphp
            @if ($domain)
              <div class="flex items-center gap-1 text-[8px] text-slate-500 font-bold mb-0.5">
                <x-icon name="globe" class="h-3 w-3 text-slate-500 shrink-0" stroke-width="2.5" />
                <span>{{ $domain }}</span>
              </div>
            @endif
            <h5 class="font-bold text-white mb-0.5 truncate">{{ $external['title'] }}</h5>
            <p class="text-[9px] text-slate-500 line-clamp-1 leading-relaxed">{{ $external['description'] }}</p>
          </div>
        </div>
      </a>
    @endif

    <!-- 添付画像の小さなサムネイル表示 -->
    @if (!empty($images))
      @include('calendar._parent_images', ['images' => $images, 'postId' => $previewId])
    @endif

    <!-- 引用ポストが存在することを示すバッジ -->
    @if ($hasQuote)
      <div class="inline-flex items-center gap-1 text-[9px] font-bold text-slate-500 bg-slate-850/40 border border-slate-800 px-2 py-0.5 rounded-full mt-2.5">
        <x-icon name="link" class="h-2.5 w-2.5 text-slate-500 shrink-0" stroke-width="2.5" />
        <span>引用ポストあり</span>
      </div>
    @endif
  </div>
@endif
