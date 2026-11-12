@php
  $parentText = $post->raw_data['reply']['parent']['record']['text'] ?? null;
  if ($parentText) {
      $parentEmbed = $post->raw_data['reply']['parent']['embed'] ?? null;
      
      // 添付画像の抽出
      $parentImages = \App\Models\Post::extractImages($parentEmbed);

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

      $parentAuthor = $post->raw_data['reply']['parent']['author'] ?? null;
      $rootAuthor = $post->raw_data['reply']['root']['author'] ?? null;
      $parentUri = $post->raw_data['reply']['parent']['uri'] ?? null;
      $rootUri = $post->raw_data['reply']['root']['uri'] ?? null;
      $myDid = $user->blueskyAccount->did ?? null;

      // 表示名を決める。自分なら自分の名前、本人以外が見ていて非公開アカウントなら伏せる
      $displayNameFor = function (?array $author) use ($myDid, $user, $post, $isOwner) {
          if (! $author) {
              return null;
          }
          $did = $author['did'] ?? null;
          if ($myDid && $did === $myDid) {
              return $user->name ?: '@' . $user->blueskyAccount->handle;
          }
          if ($post->isPrivateUser($did) && ! $isOwner) {
              return '非公開ユーザー';
          }
          return ($author['displayName'] ?? '') ?: ($author['handle'] ?? 'Unknown');
      };

      // 見出しの「○○ の投稿」。会話を開いたときに、誰の投稿かが分かるようにする
      $parentAuthorName = $displayNameFor($parentAuthor);

      // 返信先の投稿がさらに誰かへの返信なら、その相手を「（↩ ○○ への返信）」として添える
      $isParentReplyToOthers = false;
      $replyTargetName = null;

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
              // 返信先の投稿が返信した相手は grandparentAuthor に入る（Bluesky のフィードが返す場合だけ）。
              // URL から取り込んだ投稿などで無いときは、スレッドを始めた人で代わりに表示する
              $replyTargetName = $displayNameFor($post->raw_data['reply']['grandparentAuthor'] ?? $rootAuthor);
          }
      }
  }
@endphp

@if ($parentText)
  <div id="{{ $previewId }}" class="hidden mb-3.5 p-4 pl-5 rounded-r-xl border-l-2 border-sky-500/30 bg-slate-950/30 text-xs text-slate-400 select-none">
    @if ($parentAuthorName)
      {{-- 例: 「C の投稿（↩ B への返信）」。本文に混ぜるとその人が書いた文に見えるので、別の行に出す --}}
      <div class="flex items-center gap-1 flex-wrap pb-2.5 mb-2.5 border-b border-slate-900/60 text-xs">
        <span class="font-bold text-slate-300 break-all">{{ $parentAuthorName }}</span>
        <span class="text-slate-500">の投稿</span>
        @if ($isParentReplyToOthers && $replyTargetName)
          <span class="inline-flex items-center gap-1 text-slate-500">
            （<x-icon name="reply" class="h-3 w-3 shrink-0" stroke-width="2" /><span class="break-all">{{ $replyTargetName }}</span> への返信）
          </span>
        @endif
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
              <div class="flex items-center gap-1 text-[10px] text-slate-500 font-bold mb-0.5">
                <x-icon name="globe" class="h-3 w-3 text-slate-500 shrink-0" stroke-width="2.5" />
                <span>{{ $domain }}</span>
              </div>
            @endif
            <h4 class="font-bold text-white mb-0.5 truncate">{{ $parentExternal['title'] }}</h4>
            <p class="text-xs text-slate-500 line-clamp-1 leading-relaxed">{{ $parentExternal['description'] }}</p>
          </div>
        </div>
      </a>
    @endif

    <!-- 添付画像の小さなサムネイル表示 (w-16 h-16) -->
    @include('calendar._parent_images', ['images' => $parentImages, 'postId' => $previewId])

    <!-- 引用ポストが存在することを示す上品なバッジ -->
    @if ($hasParentQuote)
      <div class="inline-flex items-center gap-1 text-[9px] font-bold text-slate-500 bg-slate-800/40 border border-slate-800 px-2 py-0.5 rounded-full mt-2.5">
        <x-icon name="link" class="h-2.5 w-2.5 text-slate-500 shrink-0" stroke-width="2.5" />
        <span>引用ポストあり</span>
      </div>
    @endif
  </div>
@endif
