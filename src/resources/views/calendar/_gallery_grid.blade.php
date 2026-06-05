@php
  // 閲覧者がカレンダー所有者であるか
  $isOwner = Auth::check() && Auth::id() === $user->id;

  $galleryItems = [];
  foreach ($posts as $post) {
      // リポスト（他人の投稿の共有）はギャラリーから除外する
      if ($post->is_repost) {
          continue;
      }

      // 非公開リポストの画像はマスク（スキップ）する
      $isRepostPrivate = false;
      if ($post->is_repost && $repostAuthor = $post->repost_author) {
          $isRepostPrivate = $post->isPrivateUser($repostAuthor['did']);
      }
      $shouldMaskRepost = $isRepostPrivate && !$isOwner;

      if ($shouldMaskRepost) {
          continue;
      }

      $rawData = $post->raw_data;
      if (empty($rawData)) {
          continue;
      }

      // 投稿のembedから画像を取得
      if ($post->is_repost) {
          $embed = $rawData['post']['embed'] ?? null;
      } else {
          $embed = $rawData['post']['embed'] ?? $rawData['embed'] ?? null;
      }

      $images = [];
      if ($embed && str_contains($embed['$type'] ?? '', 'app.bsky.embed.images')) {
          $images = $embed['images'] ?? [];
      } elseif ($embed && str_contains($embed['$type'] ?? '', 'app.bsky.embed.recordWithMedia')) {
          $media = $embed['media'] ?? null;
          if ($media && str_contains($media['$type'] ?? '', 'app.bsky.embed.images')) {
              $images = $media['images'] ?? [];
          }
      }

      foreach ($images as $index => $img) {
          $galleryItems[] = [
              'post_id' => $post->id,
              'thumb' => $img['thumb'],
              'fullsize' => $img['fullsize'] ?? $img['thumb'],
              'alt' => $img['alt'] ?? '',
              'text' => $post->text,
              'html_text' => $post->html_text,
              'posted_at' => $post->posted_at,
              'formatted_date' => $post->posted_at->timezone('Asia/Tokyo')->format('Y-m-d H:i'),
              'bsky_url' => $post->bsky_url,
              'detail_url' => route('calendar.show.day', [
                  'handle' => $bskyAccount->handle,
                  'year' => $post->posted_at->format('Y'),
                  'month' => $post->posted_at->format('m'),
                  'day' => $post->posted_at->format('d')
              ]) . '#post-' . $post->id,
              'author_name' => $post->user->name ?? $bskyAccount->handle,
              'author_avatar' => $post->user->avatar_url ?? $bskyAccount->avatar_url,
          ];
      }
  }
@endphp

@if (empty($galleryItems))
  <div class="glass-card rounded-2xl p-12 text-center">
    <h4 class="text-base font-bold text-slate-300 mb-1">画像つきの投稿が見つかりませんでした</h4>
    <p class="text-xs text-slate-500">この期間内に画像を含む投稿はありません。</p>
  </div>
@else
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 md:gap-5 timeline-container">
    @foreach ($galleryItems as $item)
      <a href="#" class="gallery-image-link group relative aspect-square overflow-hidden rounded-2xl border border-slate-800/80 bg-slate-900/40 hover:border-sky-500/40 shadow-lg hover:shadow-sky-500/5 transition duration-500 cursor-pointer active:scale-98"
         data-fullsize="{{ $item['fullsize'] }}"
         data-avatar="{{ $item['author_avatar'] }}"
         data-author-name="{{ $item['author_name'] }}"
         data-html-text="{{ $item['html_text'] }}"
         data-date="{{ $item['formatted_date'] }}"
         data-detail-url="{{ $item['detail_url'] }}"
         data-bsky-url="{{ $item['bsky_url'] }}">
         
        <!-- ローディングプレースホルダー（スケルトン） -->
        <div class="absolute inset-0 bg-slate-800/30 animate-pulse flex items-center justify-center pointer-events-none img-skeleton">
          <x-icon name="image" class="w-7 h-7 text-slate-700/50" />
        </div>
        <!-- サムネイル画像 -->
        <img src="{{ $item['thumb'] }}" alt="{{ $item['alt'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-all duration-500 opacity-0" loading="lazy" onload="this.classList.remove('opacity-0'); if (this.previousElementSibling && this.previousElementSibling.classList.contains('img-skeleton')) this.previousElementSibling.remove();" onerror="if (this.previousElementSibling && this.previousElementSibling.classList.contains('img-skeleton')) this.previousElementSibling.remove();">

        <!-- ホバー時のオーバーレイ -->
        <div class="absolute inset-0 bg-slate-950/80 opacity-0 group-hover:opacity-100 transition duration-300 flex flex-col justify-between p-4.5">
          <!-- 上部: 投稿主情報 -->
          <div class="flex items-center gap-2">
            <img src="{{ $item['author_avatar'] }}" alt="Avatar" class="w-5.5 h-5.5 rounded-full border border-slate-800 object-cover">
            <span class="text-[10px] font-bold text-slate-300 truncate">{{ $item['author_name'] }}</span>
          </div>

          <!-- 中央: 本文抜粋 -->
          <p class="text-xs text-slate-200 line-clamp-3 leading-relaxed mt-1 select-none">
            {{ $item['text'] }}
          </p>

          <!-- 下部: 日付 -->
          <div class="text-[9px] font-semibold text-slate-500 tracking-wider text-right">
            {{ $item['formatted_date'] }}
          </div>
        </div>
      </a>
    @endforeach
  </div>
@endif
