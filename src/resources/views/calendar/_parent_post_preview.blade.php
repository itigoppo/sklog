@php
  $parentText = $post->raw_data['reply']['parent']['record']['text'] ?? null;
  if ($parentText) {
      $parentEmbed = $post->raw_data['reply']['parent']['embed'] ?? null;
      
      // 添付画像の抽出
      $parentImages = [];
      if ($parentEmbed && str_contains($parentEmbed['$type'] ?? '', 'app.bsky.embed.images')) {
        $parentImages = $parentEmbed['images'] ?? [];
      }

      // 外部リンクの抽出
      $parentExternal = null;
      if ($parentEmbed && str_contains($parentEmbed['$type'] ?? '', 'app.bsky.embed.external')) {
        $parentExternal = $parentEmbed['external'] ?? null;
      }
      
      // 引用ポストの有無判定 (レコード埋め込み)
      $hasParentQuote = false;
      if ($parentEmbed) {
        $parentEmbedType = $parentEmbed['$type'] ?? '';
        $hasParentQuote = str_contains($parentEmbedType, 'app.bsky.embed.record');
      }

      $rootAuthor = $post->raw_data['reply']['root']['author'] ?? null;
      $parentUri = $post->raw_data['reply']['parent']['uri'] ?? null;
      $rootUri = $post->raw_data['reply']['root']['uri'] ?? null;
      $myDid = $user->blueskyAccount->did ?? null;
      
      $isParentReplyToOthers = false;
      $rootAuthorName = null;
      
      if ($rootAuthor) {
          if ($post->is_reply_to_others) {
              if ($parentUri && $rootUri && ($parentUri !== $rootUri)) {
                  $isParentReplyToOthers = true;
              }
          } elseif ($post->is_self_thread) {
              if ($myDid && ($rootAuthor['did'] !== $myDid)) {
                  $isParentReplyToOthers = true;
              }
          }
          
          if ($isParentReplyToOthers) {
              if ($myDid && ($rootAuthor['did'] === $myDid)) {
                  $rootAuthorName = $user->name ?: '@' . $user->blueskyAccount->handle;
              } else {
                  $isRootPrivate = $post->isPrivateUser($rootAuthor['did']);
                  $shouldMaskRoot = $isRootPrivate && !$isOwner;
                  
                  if ($shouldMaskRoot) {
                      $rootAuthorName = '非公開ユーザー';
                  } else {
                      $rootAuthorName = $rootAuthor['displayName'] ?? ($rootAuthor['handle'] ?? 'Unknown');
                  }
              }
          }
      }
  }
@endphp

@if ($parentText)
  <div id="{{ $previewId }}" class="hidden mb-3.5 p-4 pl-5 rounded-r-xl border-l-2 border-sky-500/30 bg-slate-950/30 text-xs text-slate-400 select-none">
    @if ($isParentReplyToOthers && $rootAuthorName)
      <div class="flex items-center gap-1.5 pb-2.5 mb-2.5 border-b border-slate-900/60">
        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-900/80 border border-slate-800 text-[8px] text-slate-400 font-medium shrink-0">
          <x-icon name="reply" class="h-2 w-2 text-slate-400 translate-y-[-1.2px]" stroke-width="2" />
          返信先: {{ $rootAuthorName }}
        </span>
        <span class="text-[9px] text-slate-500">への返信ポスト</span>
      </div>
    @endif

    <!-- 本文 -->
    <p class="italic text-slate-300/85 leading-relaxed whitespace-pre-wrap">{!! \App\Models\Post::formatTextToHtml($parentText, $post->raw_data['reply']['parent'] ?? null) !!}</p>

    <!-- 外部リンクカード -->
    @if ($parentExternal)
      <a href="{{ $parentExternal['uri'] }}" target="_blank" class="block cursor-pointer rounded-lg overflow-hidden bg-slate-950 border border-slate-900 hover:border-slate-800 transition mt-2.5 text-[10px] select-none">
        <div class="p-2.5 flex gap-3 items-center">
          @if (isset($parentExternal['thumb']))
            <img src="{{ $parentExternal['thumb'] }}" alt="" class="w-12 h-12 object-cover rounded shrink-0 opacity-85">
          @endif
          <div class="min-w-0 flex-1">
            @php $domain = parse_url($parentExternal['uri'], PHP_URL_HOST); @endphp
            @if ($domain)
              <div class="flex items-center gap-1 text-[8px] text-slate-500 font-bold mb-0.5">
                <x-icon name="globe" class="h-3 w-3 text-slate-500 shrink-0" stroke-width="2.5" />
                <span>{{ $domain }}</span>
              </div>
            @endif
            <h5 class="font-bold text-white mb-0.5 truncate">{{ $parentExternal['title'] }}</h5>
            <p class="text-[9px] text-slate-500 line-clamp-1 leading-relaxed">{{ $parentExternal['description'] }}</p>
          </div>
        </div>
      </a>
    @endif

    <!-- 添付画像の小さなサムネイル表示 (w-16 h-16) -->
    @include('calendar._parent_images', ['images' => $parentImages, 'postId' => $previewId])

    <!-- 引用ポストが存在することを示す上品なバッジ -->
    @if ($hasParentQuote)
      <div class="inline-flex items-center gap-1 text-[9px] font-bold text-slate-500 bg-slate-850/40 border border-slate-800 px-2 py-0.5 rounded-full mt-2.5">
        <x-icon name="link" class="h-2.5 w-2.5 text-slate-500 shrink-0" stroke-width="2.5" />
        <span>引用ポストあり</span>
      </div>
    @endif
  </div>
@endif
