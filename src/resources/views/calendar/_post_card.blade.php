@php
  // 閲覧者がこのカレンダーの所有者本人であるか
  $isOwner = Auth::check() && Auth::id() === $user->id;

  $isConnectedToNext = $isConnectedToNext ?? false;
  $isConnectedToPrev = $isConnectedToPrev ?? false;

  // 連投の通し番号 ['index' => 何番目, 'total' => 件数]。1件だけの場合は出さない
  $threadNumber = $threadNumber ?? null;
  $hasThreadNumber = $threadNumber && ($threadNumber['total'] ?? 1) > 1;

  // 前のページ・前日からの継続判定
  $isContinuationFromPrev = $post->is_self_thread && !$isConnectedToPrev;

  // 次のページ・翌日への後続判定 (接続していない場合のみデータベースから後続投稿のIDを取得)
  $followupPostId = null;
  if (!$isConnectedToNext) {
      $followupPostId = \Illuminate\Support\Facades\Cache::remember(
          'post-followup-post-id:' . $post->id,
          300, // 5分間キャッシュ
          function() use ($post) {
              $followup = \App\Models\Post::where('user_id', $post->user_id)
                  ->where('raw_data->reply->parent->uri', $post->uri)
                  ->first(['id']);
              return $followup ? $followup->id : null;
          }
      );
  }

  $followupPost = null;
  if ($followupPostId) {
      $followupPost = \App\Models\Post::find($followupPostId);
  }
  $hasThreadFollowup = !is_null($followupPost);

  // 前のページ・前日からの継続判定（接続していない場合のみデータベースから直前の投稿を取得）
  $prevPost = null;
  if ($isContinuationFromPrev) {
      $parentUri = $post->raw_data['reply']['parent']['uri'] ?? null;
      if ($parentUri) {
          $prevPostId = \Illuminate\Support\Facades\Cache::remember(
              'post-prev-post-id:' . $post->id,
              300, // 5分間キャッシュ
              function() use ($post, $parentUri) {
                  $prev = \App\Models\Post::where('user_id', $post->user_id)
                      ->where('uri', $parentUri)
                      ->first(['id']);
                  return $prev ? $prev->id : null;
              }
          );
          if ($prevPostId) {
              $prevPost = \App\Models\Post::find($prevPostId);
          }
      }
  }

  $isThread = $isConnectedToNext || $isConnectedToPrev || $isContinuationFromPrev || $hasThreadFollowup;

  // 1. リポストされた元の投稿主が本サービス内で「非公開アカウント」であるか
  $isRepostPrivate = false;
  if ($post->is_repost && $repostAuthor = $post->repost_author) {
      $isRepostPrivate = $post->isPrivateUser($repostAuthor['did']);
  }
  $shouldMaskRepost = $isRepostPrivate && !$isOwner;

  // 2. 返信先の相手が本サービス内で「非公開アカウント」であるか
  $isReplyParentPrivate = false;
  if ($post->is_reply_to_others && $replyAuthor = $post->reply_parent_author) {
      $isReplyParentPrivate = $post->isPrivateUser($replyAuthor['did']);
  }
  $shouldMaskReply = $isReplyParentPrivate && !$isOwner;

  // 3. 引用ポスト元が本サービス内で「非公開アカウント」であるか
  $isQuotePrivate = false;
  $quotedPost = $post->quoted_post;
  if ($quotedPost && !($quotedPost['is_not_found'] ?? false) && !($quotedPost['is_blocked'] ?? false)) {
      $isQuotePrivate = isset($quotedPost['author_did']) && $post->isPrivateUser($quotedPost['author_did']);
  }
  $shouldMaskQuote = $isQuotePrivate && !$isOwner;

  // リポスト本文等のパース処理 (非表示時には空にする)
  $embed = $post->raw_data['post']['embed'] ?? null;
  $images = $shouldMaskRepost ? [] : \App\Models\Post::extractImages($embed);
  $external = null;
  if (!$shouldMaskRepost && $embed && str_contains($embed['$type'] ?? '', 'app.bsky.embed.external')) {
    $external = $embed['external'] ?? null;
  }

  // リポスト情報の変数化
  $repostName = null;
  $repostHandle = null;
  $repostAvatar = null;
  if ($post->is_repost && $repostAuthor = $post->repost_author) {
      if ($shouldMaskRepost) {
          $repostName = '非公開ユーザー';
          $repostHandle = 'private.user';
          $repostAvatar = null;
      } else {
          $repostName = $repostAuthor['name'];
          $repostHandle = $repostAuthor['handle'];
          $repostAvatar = $repostAuthor['avatar'];
      }
  }

  // 返信先情報の変数化
  $replyName = null;
  $replyHandle = null;
  $replyAvatar = null;
  if ($post->is_reply_to_others && $replyAuthor = $post->reply_parent_author) {
      if ($post->is_reply_parent_not_found) {
          // 返信先が削除などで見つからない（投稿者も分からない）
          $replyName = '投稿が見つかりません';
      } elseif ($shouldMaskReply) {
          $replyName = '非公開ユーザー';
          $replyHandle = 'private.user';
          $replyAvatar = null;
      } else {
          $replyName = $replyAuthor['name'];
          $replyHandle = $replyAuthor['handle'];
          $replyAvatar = $replyAuthor['avatar'];
      }
  }
@endphp

<div class="glass-card {{ $isConnectedToPrev ? 'rounded-t-none border-t-0 -mt-4 shadow-none' : '' }} {{ $isConnectedToNext ? 'rounded-b-none' : '' }} p-5 md:p-6 transition-all duration-300 relative {{ $isThread ? 'pl-11 md:pl-14' : '' }}">
  
  <!-- スレッドライン -->
  @if ($isThread)
    <div class="absolute left-6 md:left-8 top-0 bottom-0 w-0.5 bg-slate-800/40 z-0">
      <!-- アクティブなスレッドライン（青・実線接続部） -->
      <div class="absolute left-0 w-full bg-sky-500/30 {{ $isConnectedToPrev ? 'top-0' : 'top-10' }} {{ $isConnectedToNext ? 'bottom-0' : 'bottom-10' }}">
      </div>

      <!-- 上方へのフェード接続（過去のスレッドからの継続） -->
      @if (!$isConnectedToPrev && $isContinuationFromPrev)
        <div class="absolute left-0 w-full top-0 h-10 bg-linear-to-t from-sky-500/30 to-transparent"></div>
      @endif

      <!-- 下方へのフェード接続（未来のスレッドへの継続） -->
      @if (!$isConnectedToNext && $hasThreadFollowup)
        <div class="absolute left-0 w-full bottom-0 h-10 bg-linear-to-b from-sky-500/30 to-transparent"></div>
      @endif
    </div>
    <!-- 投稿を示すドット -->
    <div class="absolute left-6 md:left-8 top-10 -translate-x-1/2 w-2 h-2 rounded-full bg-sky-400 border-2 border-slate-950 z-10 shadow-lg shadow-sky-500/20"></div>
  @endif

  <!-- リポスト情報ヘッダー -->
  @if ($post->is_repost && $repostAuthor = $post->repost_author)
    <div class="flex items-center justify-between mb-3 pb-3 border-b border-slate-900/40 select-none">
      <!-- ユーザー情報 -->
      @if ($originalUrl = ($shouldMaskRepost ? null : $post->bsky_url))
        <a href="{{ $originalUrl }}" target="_blank" class="flex items-center gap-2 hover:opacity-85 transition group">
          @include('calendar._avatar', ['avatarUrl' => $repostAvatar, 'size' => 'w-6 h-6'])
          <div class="flex flex-col min-w-0">
            <span class="text-xs font-bold text-slate-200 group-hover:text-sky-400 transition truncate max-w-[120px] sm:max-w-[180px]">{{ $repostName }}</span>
            <span class="text-[10px] text-slate-500 truncate max-w-[100px] sm:max-w-[150px]">{{ '@' . $repostHandle }}</span>
          </div>
        </a>
      @else
        <div class="flex items-center gap-2">
          @include('calendar._avatar', ['avatarUrl' => $repostAvatar, 'size' => 'w-6 h-6'])
          <div class="flex flex-col min-w-0">
            <span class="text-xs font-bold {{ $shouldMaskRepost ? 'text-slate-400' : 'text-slate-200' }} truncate max-w-[120px] sm:max-w-[180px]">{{ $repostName }}</span>
            <span class="text-[10px] text-slate-500 truncate max-w-[100px] sm:max-w-[150px]">{{ '@' . $repostHandle }}</span>
          </div>
        </div>
      @endif
      
      <!-- リポストバッジ -->
      <span class="flex items-center gap-1 text-[10px] font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-full shrink-0">
        <x-icon name="repost" class="h-3 w-3 text-emerald-400 shrink-0" />
        <span>リポスト</span>
      </span>
    </div>
  @endif

  <!-- 他者への返信ヘッダー -->
  @if ($post->is_reply_to_others && $replyAuthor = $post->reply_parent_author)
    @php
      $parentText = $post->raw_data['reply']['parent']['record']['text'] ?? null;
    @endphp
    <div class="flex items-center justify-between mb-3.5 pb-3 border-b border-slate-900/40 select-none">
      <div class="flex items-center gap-2">
        @include('calendar._avatar', ['avatarUrl' => $replyAvatar, 'size' => 'w-6 h-6'])
        <div class="flex flex-col min-w-0">
          <span class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1">
            <x-icon name="reply" class="h-2.5 w-2.5 text-slate-500 shrink-0 translate-y-[-1.2px]" stroke-width="2.5" />
            <span>返信先</span>
          </span>
          <span class="text-xs font-bold {{ $shouldMaskReply ? 'text-slate-400' : 'text-slate-200' }} truncate max-w-[120px] sm:max-w-[180px]">{{ $replyName }}</span>
        </div>
      </div>
      
      <div class="flex items-center gap-2">
        @if ($shouldMaskReply)
          <span class="text-[10px] font-bold text-slate-600 bg-slate-900/40 border border-slate-900/80 px-2 py-0.5 rounded-full shrink-0 select-none">
            @private.user
          </span>
        @else
          @if ($parentText)
            <button type="button" 
                    onclick="document.getElementById('reply-parent-{{ $post->id }}').classList.toggle('hidden') ? (this.textContent = '会話を表示') : (this.textContent = '会話を非表示')"
                    class="text-[10px] font-bold text-slate-400 hover:text-white bg-slate-900/40 hover:bg-slate-900 border border-slate-800 hover:border-slate-700 px-2 py-0.5 rounded-full transition cursor-pointer select-none">
              会話を表示
            </button>
          @endif

          @if ($post->is_reply_parent_not_found)
            {{-- 返信先が見つからないので、リンクもハンドルも出さない --}}
          @elseif ($parentUrl = $post->reply_parent_url)
            <a href="{{ $parentUrl }}" target="_blank" class="flex items-center gap-1 text-[10px] font-bold text-sky-400 bg-sky-500/10 border border-sky-500/20 px-2 py-0.5 rounded-full hover:bg-sky-500/20 transition cursor-pointer shrink-0">
              <x-icon name="bluesky" class="h-3 w-3 text-sky-400 shrink-0" />
              <span>{{ '@' . $replyHandle }}</span>
            </a>
          @else
            <span class="text-[10px] font-bold text-slate-500 bg-slate-880/40 border border-slate-800 px-2 py-0.5 rounded-full shrink-0">
              {{ '@' . $replyHandle }}
            </span>
          @endif
        @endif
      </div>
    </div>

    <!-- 返信先ポストのトグル表示エリア -->
    @if ($parentText && !$shouldMaskReply)
      @include('calendar._parent_post_preview', [
        'post' => $post,
        'user' => $user,
        'isOwner' => $isOwner,
        'previewId' => 'reply-parent-' . $post->id
      ])
    @endif
  @endif

  <!-- 自分のスレッドの親ポストプレビュー（トグル式） -->
  @if ($post->is_self_thread && !$isConnectedToPrev)
    @php
      $hasParentText = isset($post->raw_data['reply']['parent']['record']['text']);
    @endphp
    @if (!$prevPost && $post->is_reply_parent_not_found)
      <!-- 直前の投稿が Bluesky で削除されていて、カレンダーにも残っていない -->
      <div class="flex items-center gap-1.5 mb-3.5 pb-3 border-b border-slate-900/40 text-[10px] font-bold text-slate-500 flex-wrap select-none">
        <x-icon name="list" class="h-3.5 w-3.5 text-slate-500 shrink-0" />
        <span>スレッドの続き</span>
        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-slate-900 border border-slate-800 text-[9px] text-slate-500 font-bold leading-none">
          <x-icon name="trash" class="h-2.5 w-2.5 shrink-0" />
          直前の投稿は見つかりません
        </span>
      </div>
    @elseif ($prevPost || $hasParentText)
      <div class="flex items-center justify-between mb-3.5 pb-3 border-b border-slate-900/40 select-none">
        <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-500 flex-wrap">
          <x-icon name="list" class="h-3.5 w-3.5 text-slate-500 shrink-0" />
          <span>スレッドの続き</span>
          <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-slate-900 border border-slate-800 text-[7px] text-slate-500 font-bold uppercase tracking-widest leading-none">継続</span>
        </div>
        
        <div class="flex items-center gap-2">
          <button type="button" 
                  onclick="document.getElementById('self-parent-{{ $post->id }}').classList.toggle('hidden') ? (this.textContent = '直前の投稿を表示') : (this.textContent = '直前の投稿を非表示')"
                  class="text-[10px] font-bold text-slate-400 hover:text-white bg-slate-900/40 hover:bg-slate-900 border border-slate-800 hover:border-slate-700 px-2 py-0.5 rounded-full transition cursor-pointer select-none">
            直前の投稿を表示
          </button>

          @if ($parentUrl = $post->reply_parent_url)
            <a href="{{ $parentUrl }}" target="_blank" class="flex items-center gap-1 text-[10px] font-bold text-sky-400 bg-sky-500/10 border border-sky-500/20 px-2 py-0.5 rounded-full hover:bg-sky-500/20 transition cursor-pointer shrink-0">
              <x-icon name="bluesky" class="h-3 w-3 text-sky-400 shrink-0" />
              <span>詳細 ➔</span>
            </a>
          @endif
        </div>
      </div>

      <!-- トグルされる親ポストの内容エリア -->
      @if ($prevPost)
        @include('calendar._followup_post_preview', [
          'prevPost' => $prevPost,
          'type' => 'prev',
          'user' => $user,
          'isOwner' => $isOwner,
          'previewId' => 'self-parent-' . $post->id
        ])
      @else
        @include('calendar._parent_post_preview', [
          'post' => $post,
          'user' => $user,
          'isOwner' => $isOwner,
          'previewId' => 'self-parent-' . $post->id
        ])
      @endif
    @endif
  @endif

  <!-- 本文 -->
  @if ($shouldMaskRepost)
    <p class="text-sm md:text-base text-slate-500 italic whitespace-pre-wrap leading-relaxed mb-2">非公開アカウントの投稿（リポスト）のため、内容は表示できません。</p>
  @else
    <p class="text-sm md:text-base text-slate-200 whitespace-pre-wrap leading-relaxed mb-2">{!! $post->html_text !!}@if ($hasThreadNumber)<span class="inline-block align-middle ml-1.5 px-1.5 py-0.5 rounded-md bg-slate-800/80 text-[10px] font-bold text-slate-400 leading-none tracking-wide select-none" title="この連投の{{ $threadNumber['total'] }}件中{{ $threadNumber['index'] }}件目">{{ $threadNumber['index'] }}/{{ $threadNumber['total'] }}</span>@endif</p>
  @endif

  <!-- 引用ポスト -->
  @if ($quotedPost = $post->quoted_post)
    @php
      $quotedUrl = $quotedPost['bsky_url'] ?? '';
    @endphp
    <div class="mb-3">
      @if ($shouldMaskRepost)
        {{-- リポスト元が非公開の場合は、引用ポスト自体を表示しない --}}
      @elseif ($shouldMaskQuote)
        <div class="p-3.5 rounded-xl border border-slate-900/60 bg-slate-950/40 text-xs text-slate-500 flex items-center gap-2 italic">
          <x-icon name="lock" class="h-4 w-4 text-slate-600 shrink-0" stroke-width="2" />
          <span>非公開アカウントの投稿のため表示できません。</span>
        </div>
      @elseif ($quotedPost['is_not_found'])
        <div class="p-3.5 rounded-xl border border-slate-900/60 bg-slate-950/40 text-xs text-slate-500 flex items-center gap-2 italic">
          <x-icon name="alert" class="h-4 w-4 text-slate-600 shrink-0" stroke-width="2" />
          <span>{{ $quotedPost['text'] }}</span>
        </div>
      @elseif ($quotedPost['is_blocked'])
        <div class="p-3.5 rounded-xl border border-slate-900/60 bg-slate-950/40 text-xs text-slate-500 flex items-center gap-2 italic">
          <x-icon name="lock" class="h-4 w-4 text-slate-600 shrink-0" stroke-width="2" />
          <span>{{ $quotedPost['text'] }}</span>
        </div>
      @else
        <div class="block p-4 rounded-xl border border-slate-900 bg-slate-950/40 select-none">
          <!-- 引用元ヘッダー（アバター & 名前 & 時間） -->
          <div class="flex items-center gap-2 mb-2 select-none">
            @include('calendar._avatar', ['avatarUrl' => $quotedPost['author_avatar'], 'size' => 'w-5 h-5', 'iconSize' => 'h-3 w-3'])
            <span class="text-xs font-bold text-slate-300 truncate max-w-[120px] sm:max-w-[200px]">{{ $quotedPost['author_name'] }}</span>
            <span class="text-[10px] text-slate-500 truncate max-w-[100px] sm:max-w-[150px]">{{ '@' . $quotedPost['author_handle'] }}</span>
            @if ($quotedPost['posted_at'])
              <span class="text-slate-800 text-[10px]">•</span>
              <span class="text-[10px] text-slate-500 font-medium whitespace-nowrap">{{ $quotedPost['posted_at']->format('Y/m/d H:i') }}</span>
            @endif
          </div>
          
          <!-- 引用元本文 -->
          <p class="text-xs text-slate-300 whitespace-pre-wrap leading-relaxed line-clamp-4">{!! \App\Models\Post::formatTextToHtml($quotedPost['text'], $quotedPost['raw_data'] ?? null) !!}</p>
          
          <!-- 引用元画像添付 -->
          @include('calendar._post_images', [
            'images' => $quotedPost['images'],
            'postId' => 'quote-' . $post->id,
            'containerClass' => 'grid grid-cols-3 gap-1.5 mt-2 rounded-lg overflow-hidden select-none',
            'linkClass' => 'image-preview-link block overflow-hidden border border-slate-950 cursor-pointer',
            'imgClass' => 'w-full h-20 object-cover hover:scale-105 transition duration-300'
          ])
        </div>
      @endif
    </div>
  @endif

  <!-- 画像添付 -->
  @include('calendar._post_images', [
    'images' => $images,
    'postId' => $post->id
  ])

  <!-- 外部リンクカード -->
  @if ($external)
    @php
      $isGif = false;
      $gifProvider = 'GIF';
      $uri = $external['uri'] ?? '';
      
      if (str_contains(strtolower($uri), '.gif') || 
          str_contains($uri, 'klipy.com') || 
          str_contains($uri, 'tenor.com') || 
          str_contains($uri, 'giphy.com')) {
          $isGif = true;
          
          if (str_contains($uri, 'klipy.com')) {
              $gifProvider = 'KLIPY';
          } elseif (str_contains($uri, 'tenor.com')) {
              $gifProvider = 'TENOR';
          } elseif (str_contains($uri, 'giphy.com')) {
              $gifProvider = 'GIPHY';
          }
      }
    @endphp

    @if ($isGif && isset($external['thumb']))
      @php
        // 元のGIFアニメーション直URLを特定して優先適用 (静止画サムネイルを動くURLへ差し替え)
        $gifUrl = $external['thumb'];
        if (str_contains(strtolower($uri), '.gif') || str_contains($uri, 'klipy.com')) {
            $gifUrl = $uri;
        }
      @endphp
      <!-- GIFの場合は、画像プレビューリンクとして動作させる -->
      <a href="{{ $gifUrl }}" target="_blank" 
         class="image-preview-link block cursor-pointer rounded-xl overflow-hidden bg-slate-950/80 border border-slate-900 hover:border-slate-800 transition mb-2 relative"
         data-post-id="external-{{ $post->id }}"
         data-index="0">
        <img src="{{ $gifUrl }}" alt="{{ $external['title'] ?? 'GIF' }}" class="w-full h-40 object-cover opacity-80">
        <div class="p-4 pr-16">
          <h5 class="text-sm font-bold text-white mb-1.5 flex items-center gap-1.5">
            <x-icon name="image" class="h-4 w-4 text-sky-400 shrink-0" />
            <span>{{ $external['title'] }}</span>
          </h5>
          <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed">{{ $external['description'] }} (クリックで拡大)</p>
          <!-- GIFバッジ -->
          <span class="absolute right-4 bottom-4 text-[9px] font-bold text-sky-400 bg-sky-500/10 border border-sky-500/20 px-1.5 py-0.5 rounded uppercase select-none tracking-wider">{{ $gifProvider }}</span>
        </div>
      </a>
    @else
      <!-- 通常の外部リンクカード -->
      <a href="{{ $external['uri'] }}" target="_blank" class="block cursor-pointer rounded-xl overflow-hidden bg-slate-950/80 border border-slate-900 hover:border-slate-800 transition mb-2">
        @if (isset($external['thumb']))
          <img src="{{ $external['thumb'] }}" alt="" class="w-full h-40 object-cover opacity-80">
        @endif
        <div class="p-4">
          @php $domain = parse_url($external['uri'], PHP_URL_HOST); @endphp
          @if ($domain)
            <div class="flex items-center gap-1.5 text-[10px] text-slate-500 font-bold mb-1.5 select-none">
              <x-icon name="globe" class="h-3.5 w-3.5 text-slate-500 shrink-0" stroke-width="2.5" />
              <span>{{ $domain }}</span>
            </div>
          @endif
          <h5 class="text-sm font-bold text-white mb-1.5">{{ $external['title'] }}</h5>
          <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed">{{ $external['description'] }}</p>
        </div>
      </a>
    @endif
  @endif

  <!-- フッター（いいね、リポスト数 & 元の投稿リンク） -->
  <div class="flex items-center justify-between text-xs text-slate-500 border-t border-slate-900/60 pt-2 select-none">
    <div class="flex items-center gap-3 md:gap-4 flex-wrap">
      <!-- 投稿日時 -->
      <span class="flex items-center gap-1 text-[10px] text-slate-500 font-bold">
        <x-icon name="clock" class="h-3.5 w-3.5 text-slate-500 shrink-0" stroke-width="2" />
        <span>{{ $post->posted_at->format('Y年m月d日 H:i:s') }}</span>
      </span>
    </div>

    <div class="flex items-center gap-3">
      @if ($hasThreadFollowup && $followupPost)
        <button type="button" 
                onclick="document.getElementById('thread-followup-{{ $post->id }}').classList.toggle('hidden') ? (this.textContent = '続きを表示') : (this.textContent = '続きを非表示')"
                class="text-[10px] font-bold text-sky-400/90 hover:text-white bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/20 hover:border-sky-500/40 px-2 py-0.5 rounded-full transition cursor-pointer select-none">
          続きを表示
        </button>
      @endif

      @if ($post->bsky_url)
        <a href="{{ $post->bsky_url }}" target="_blank" class="flex items-center gap-1 text-[11px] font-bold text-slate-500 hover:text-sky-400 cursor-pointer transition">
          <x-icon name="bluesky" class="h-3.5 w-3.5" />
          <span>Bluesky</span>
        </a>
      @endif
    </div>
  </div>

  <!-- 続きのポストのトグル表示エリア -->
  @if ($hasThreadFollowup && $followupPost)
    <div class="mt-3.5">
      @include('calendar._followup_post_preview', [
        'followupPost' => $followupPost,
        'user' => $user,
        'isOwner' => $isOwner,
        'previewId' => 'thread-followup-' . $post->id
      ])
    </div>
  @endif
</div>
