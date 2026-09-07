<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

#[Fillable([
  'user_id',
  'uri',
  'cid',
  'text',
  'raw_data',
  'posted_at',
])]
class Post extends Model
{
  use HasUuids, SoftDeletes;

  /**
   * 画像が含まれる embed の型。raw_data の絞り込みに使います。
   */
  private const IMAGE_EMBED_TYPES = [
    'app.bsky.embed.images',
    'app.bsky.embed.gallery',
  ];

  /**
   * ギャラリー形式（app.bsky.embed.gallery#view）の画像アイテムの型。
   */
  private const GALLERY_VIEW_IMAGE_TYPE = 'app.bsky.embed.gallery#viewImage';

  /**
   * Get the user that owns the post.
   */
  public function user()
  {
    return $this->belongsTo(User::class);
  }

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'raw_data' => 'array',
      'posted_at' => 'datetime',
    ];
  }

  /**
   * Blueskyの元の投稿へのURLを取得します。
   */
  public function getBskyUrlAttribute(): ?string
  {
    if (! $this->uri) {
      return null;
    }

    // リポストの場合、リポストされた元の他人の投稿のBluesky URLを返す
    if ($this->is_repost) {
      $originalUri = $this->raw_data['post']['uri'] ?? null;
      $originalHandle = $this->raw_data['post']['author']['handle'] ?? null;

      return $this->buildBskyUrl($originalUri, $originalHandle);
    }

    // at://did:plc:xxx/app.bsky.feed.post/yyy 形式をパース
    if (preg_match('/at:\/\/([^\/]+)\/app\.bsky\.feed\.post\/([^\/]+)/', $this->uri, $matches)) {
      $did = $matches[1];
      $rkey = $matches[2];

      // 自身のハンドル名があればそれを使用し、無ければDIDをフォールバックにする
      $identifier = $this->user->blueskyAccount->handle ?? $did;

      return "https://bsky.app/profile/{$identifier}/post/{$rkey}";
    }

    return null;
  }

  /**
   * embed（view 形式）から添付画像を取り出し、Blade で扱いやすい形に正規化します。
   *
   * Bluesky には画像の embed が2種類あるため、どちらも同じキーで扱えるようにします。
   * - app.bsky.embed.images#view: 従来の形式（4枚まで）。画像は images[]、サムネは thumb
   * - app.bsky.embed.gallery#view: 新しい形式（10枚まで）。画像は items[]、サムネは thumbnail
   *
   * app.bsky.embed.recordWithMedia#view（メディア付き引用ポスト）の場合は media 側を見ます。
   *
   * @return array<int, array{thumb: ?string, fullsize: ?string, alt: string, aspectRatio: ?array}>
   */
  public static function extractImages(?array $embed): array
  {
    $type = $embed['$type'] ?? '';

    // メディア付き引用ポストは media 側に画像が入っている
    if (str_contains($type, 'app.bsky.embed.recordWithMedia')) {
      $media = $embed['media'] ?? null;

      return self::extractImages(is_array($media) ? $media : null);
    }

    if (str_contains($type, 'app.bsky.embed.images')) {
      $images = array_filter($embed['images'] ?? [], 'is_array');

      return array_values(array_map(
        fn (array $image) => self::normalizeImage($image, 'thumb'),
        $images
      ));
    }

    if (str_contains($type, 'app.bsky.embed.gallery')) {
      // items は union なので、将来 image 以外の要素が来ても拾わないようにする
      $items = array_filter(
        $embed['items'] ?? [],
        fn ($item) => is_array($item)
          && isset($item['thumbnail'])
          && ($item['$type'] ?? self::GALLERY_VIEW_IMAGE_TYPE) === self::GALLERY_VIEW_IMAGE_TYPE
      );

      return array_values(array_map(
        fn (array $item) => self::normalizeImage($item, 'thumbnail'),
        $items
      ));
    }

    return [];
  }

  /**
   * 画像1枚分のデータを ['thumb', 'fullsize', 'alt', 'aspectRatio'] に揃えます。
   *
   * @param  string  $thumbKey  サムネイルURLが入っているキー名（形式によって異なる）
   * @return array{thumb: ?string, fullsize: ?string, alt: string, aspectRatio: ?array}
   */
  private static function normalizeImage(array $image, string $thumbKey): array
  {
    $thumb = $image[$thumbKey] ?? null;

    return [
      'thumb' => $thumb,
      'fullsize' => $image['fullsize'] ?? $thumb,
      'alt' => $image['alt'] ?? '',
      'aspectRatio' => $image['aspectRatio'] ?? null,
    ];
  }

  /**
   * 引用ポストのデータを取得します。
   */
  public function getQuotedPostAttribute(): ?array
  {
    if (empty($this->raw_data['post']['embed'])) {
      return null;
    }

    $embed = $this->raw_data['post']['embed'];
    $type = $embed['$type'] ?? '';

    // 1. 純粋な引用ポスト (app.bsky.embed.record#view)
    if ($type === 'app.bsky.embed.record#view' && isset($embed['record'])) {
      return $this->formatQuotedRecord($embed['record']);
    }

    // 2. メディア付き引用ポスト (app.bsky.embed.recordWithMedia#view)
    if ($type === 'app.bsky.embed.recordWithMedia#view' && isset($embed['record']['record'])) {
      return $this->formatQuotedRecord($embed['record']['record']);
    }

    return null;
  }

  /**
   * 引用レコードのデータ構造をBladeで扱いやすい配列に整形します。
   */
  private function formatQuotedRecord(array $record): ?array
  {
    // $type が app.bsky.embed.record#viewRecord であることを確認
    $type = $record['$type'] ?? '';
    if ($type !== 'app.bsky.embed.record#viewRecord') {
      // 削除されたりアクセス制限されたポストの場合は viewRecord ではない場合がある
      if ($type === 'app.bsky.embed.record#viewNotFound') {
        return [
          'is_not_found' => true,
          'is_blocked' => false,
          'text' => 'この投稿は削除されたか、見つかりません。',
        ];
      }
      if ($type === 'app.bsky.embed.record#viewBlocked') {
        return [
          'is_not_found' => false,
          'is_blocked' => true,
          'text' => 'この投稿はブロックされているため表示できません。',
        ];
      }

      return null;
    }

    $author = $record['author'] ?? [];
    $value = $record['value'] ?? [];
    $text = $value['text'] ?? '';
    $postedAt = isset($value['createdAt']) ? Carbon::parse($value['createdAt'])->timezone('Asia/Tokyo') : null;

    // 引用ポスト内の画像を取得する
    $images = [];
    if (isset($record['embeds'])) {
      foreach ($record['embeds'] as $embedView) {
        $images = array_merge($images, self::extractImages(is_array($embedView) ? $embedView : null));
      }
    }

    return [
      'is_not_found' => false,
      'is_blocked' => false,
      'uri' => $record['uri'] ?? null,
      'author_did' => $author['did'] ?? null,
      'author_name' => $author['displayName'] ?? ($author['handle'] ?? 'Unknown'),
      'author_handle' => $author['handle'] ?? '',
      'author_avatar' => $author['avatar'] ?? null,
      'text' => $text,
      'posted_at' => $postedAt,
      'images' => $images,
      'bsky_url' => $this->buildBskyUrl($record['uri'] ?? null, $author['handle'] ?? $author['did'] ?? null),
      'raw_data' => $record,
    ];
  }

  /**
   * 引用元の投稿URLを構築します。
   */
  private function buildBskyUrl(?string $uri, ?string $did): ?string
  {
    if (! $uri) {
      return null;
    }
    if (preg_match('/at:\/\/[^\/]+\/app\.bsky\.feed\.post\/([^\/]+)/', $uri, $matches)) {
      $rkey = $matches[1];
      $did = $did ?? 'profile';

      return "https://bsky.app/profile/{$did}/post/{$rkey}";
    }

    return null;
  }

  /**
   * このポストが純粋なリポストであるかを判定します。
   */
  public function getIsRepostAttribute(): bool
  {
    return isset($this->raw_data['reason']) &&
           (($this->raw_data['reason']['$type'] ?? '') === 'app.bsky.feed.defs#reasonRepost');
  }

  /**
   * リポストされた元の投稿主の情報を取得します。
   */
  public function getRepostAuthorAttribute(): ?array
  {
    if (! $this->is_repost) {
      return null;
    }

    $author = $this->raw_data['post']['author'] ?? [];

    return [
      'name' => $author['displayName'] ?? ($author['handle'] ?? 'Unknown'),
      'handle' => $author['handle'] ?? '',
      'avatar' => $author['avatar'] ?? null,
      'did' => $author['did'] ?? null,
    ];
  }

  /**
   * このポストがリプライ（他者または自分自身への返信）であるかを判定します。
   */
  public function getIsReplyAttribute(): bool
  {
    return isset($this->raw_data['reply']);
  }

  /**
   * このポストが自分自身への返信（セルフスレッド）であるかを判定します。
   */
  public function getIsSelfThreadAttribute(): bool
  {
    if (! $this->is_reply) {
      return false;
    }

    $parentDid = $this->raw_data['reply']['parent']['author']['did'] ?? null;
    $myDid = $this->user->blueskyAccount->did ?? null;

    return $parentDid && $myDid && ($parentDid === $myDid);
  }

  /**
   * このポストが他者への返信であるかを判定します。
   */
  public function getIsReplyToOthersAttribute(): bool
  {
    return $this->is_reply && ! $this->is_self_thread;
  }

  /**
   * 返信先のユーザー情報（ハンドル・表示名・アバター）を取得します。
   */
  public function getReplyParentAuthorAttribute(): ?array
  {
    if (! $this->is_reply) {
      return null;
    }

    $author = $this->raw_data['reply']['parent']['author'] ?? [];

    return [
      'name' => $author['displayName'] ?? ($author['handle'] ?? 'Unknown'),
      'handle' => $author['handle'] ?? '',
      'avatar' => $author['avatar'] ?? null,
      'did' => $author['did'] ?? null,
    ];
  }

  /**
   * 返信先（親ポスト）のBluesky詳細URLを取得します。
   */
  public function getReplyParentUrlAttribute(): ?string
  {
    if (! $this->is_reply) {
      return null;
    }

    $parent = $this->raw_data['reply']['parent'] ?? [];
    $uri = $parent['uri'] ?? null;
    $handle = $parent['author']['handle'] ?? $parent['author']['did'] ?? null;

    return $this->buildBskyUrl($uri, $handle);
  }

  /**
   * The tags that belong to the post.
   */
  public function tags()
  {
    return $this->belongsToMany(Tag::class, 'post_tags');
  }

  /**
   * 指定した DID のユーザーが本サービス内で「非公開アカウント(is_private = true)」に設定されているかを判定します。
   */
  public function isPrivateUser(?string $did): bool
  {
    if (! $did) {
      return false;
    }

    return Cache::remember('is-private-did:'.$did, 300, function () use ($did) {
        return BlueskyAccount::where('did', $did)
            ->where('is_private', true)
            ->exists();
    });
  }

  /**
   * テキスト内のURL（http/https または www. から始まるもの）を安全に自動リンク化（aタグ化）します（XSSエスケープ済み）。
   * raw_data内の facets（メタデータ）が存在する場合は、省略されたURLであっても正確にオリジナルリンクを復元します。
   */
  public static function formatTextToHtml(?string $text, ?array $rawData = null): string
  {
    if (! $text) {
      return '';
    }

    $facets = null;
    $externalUri = null;
    if ($rawData) {
      // 1. 通常の投稿アイテム内の facets
      $facets = $rawData['post']['record']['facets'] ?? null;

      // 2. 引用ポスト内レコードの facets
      if (! $facets && isset($rawData['record']['facets'])) {
        $facets = $rawData['record']['facets'];
      }

      // 3. プレーンな record オブジェクトの facets
      if (! $facets && isset($rawData['facets'])) {
        $facets = $rawData['facets'];
      }

      // 外部リンクカードのURLを抽出
      $embed = $rawData['post']['embed'] ?? $rawData['embed'] ?? $rawData['record']['embed'] ?? null;
      if ($embed && str_contains($embed['$type'] ?? '', 'app.bsky.embed.external')) {
        $externalUri = $embed['external']['uri'] ?? null;
      }
    }

    if (empty($facets)) {
      // facets メタデータがない場合は、正規表現（フォールバック）で自動リンク化
      return self::fallbackFormatTextToHtml($text, $rawData);
    }

    // facets メタデータがある場合、UTF-8 バイト位置を基準に正確にパース
    $bytes = $text;
    $lastOffset = strlen($bytes);
    $htmlParts = [];

    // byteStart の降順（テキストの後方から）ソート
    usort($facets, function ($a, $b) {
      return ($b['index']['byteStart'] ?? 0) <=> ($a['index']['byteStart'] ?? 0);
    });

    foreach ($facets as $facet) {
      $start = $facet['index']['byteStart'] ?? null;
      $end = $facet['index']['byteEnd'] ?? null;
      if ($start === null || $end === null || $start > $lastOffset || $end > $lastOffset || $start > $end) {
        continue;
      }

      $features = $facet['features'] ?? [];
      $linkUrl = null;
      $isLink = false;

      foreach ($features as $feature) {
        if (($feature['$type'] ?? '') === 'app.bsky.richtext.facet#link') {
          $linkUrl = $feature['uri'] ?? null;
          $isLink = true;
          break;
        }
      }

      if ($isLink && $linkUrl) {
        // facet より後ろの「地の文」を切り出し、HTMLエスケープ
        $trailingText = substr($bytes, $end, $lastOffset - $end);
        $htmlParts[] = e($trailingText);

        // 省略されたり途切れたりしているリンクテキスト自体を安全にエスケープ
        $origText = substr($bytes, $start, $end - $start);

        // ドメイン名の自動注釈を追加（表示テキストがドメインと完全に一致しない場合）
        $annotation = '';
        $domain = parse_url($linkUrl, PHP_URL_HOST);
        if ($domain) {
            $cleanDomain = preg_replace('/^www\./i', '', $domain);
            $cleanOrigText = preg_replace('/^https?:\/\//i', '', trim(strtolower($origText)));
            $cleanOrigText = preg_replace('/^www\./i', '', $cleanOrigText);
            $cleanOrigText = rtrim($cleanOrigText, '/');

            // 外部リンクカードのドメインと同じであるかを判定
            $isSameAsExternal = false;
            if ($externalUri) {
                $parsedLink = parse_url($linkUrl);
                $parsedExt = parse_url($externalUri);
                if (isset($parsedLink['host']) && isset($parsedExt['host']) && $parsedLink['host'] === $parsedExt['host']) {
                    $isSameAsExternal = true;
                }
            }

            if (! $isSameAsExternal) {
                $annotation = ' <span class="text-[9px] text-slate-500 font-bold hover:no-underline select-none">('.e($cleanDomain).')</span>';
            }
        }

        // facets 内の完全なリンク先URLを使用して a タグを作成
        $htmlParts[] = '<a href="'.e($linkUrl).'" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">'.e($origText).'</a>'.$annotation;

        $lastOffset = $start;
      }
    }

    // 先頭（最初の facet より手前）の地の文をエスケープして追加
    $leadingText = substr($bytes, 0, $lastOffset);
    $htmlParts[] = e($leadingText);

    // 後ろから順に処理したため、逆順にして結合
    return implode('', array_reverse($htmlParts));
  }

  /**
   * 正規表現による自動リンク化のフォールバック処理。
   */
  public static function fallbackFormatTextToHtml(string $text, ?array $rawData = null): string
  {
    $safeText = e($text);

    $externalUri = null;
    if ($rawData) {
      $embed = $rawData['post']['embed'] ?? $rawData['embed'] ?? $rawData['record']['embed'] ?? null;
      if ($embed && str_contains($embed['$type'] ?? '', 'app.bsky.embed.external')) {
        $externalUri = $embed['external']['uri'] ?? null;
      }
    }

    // http/https から始まるURLをプレースホルダーに退避
    $patternHttp = '/https?:\/\/[^\s\r\n<>]+/i';
    $links = [];
    $placeholderPrefix = '___LINK_PLACEHOLDER_';

    $safeText = preg_replace_callback($patternHttp, function ($matches) use (&$links, $placeholderPrefix, $externalUri) {
      $url = $matches[0];
      $rawUrl = htmlspecialchars_decode($url, ENT_QUOTES);
      $placeholder = $placeholderPrefix.count($links).'___';

      $annotation = '';
      $domain = parse_url($rawUrl, PHP_URL_HOST);
      if ($domain) {
          $cleanDomain = preg_replace('/^www\./i', '', $domain);
          $cleanOrigText = preg_replace('/^https?:\/\//i', '', trim(strtolower($rawUrl)));
          $cleanOrigText = preg_replace('/^www\./i', '', $cleanOrigText);
          $cleanOrigText = rtrim($cleanOrigText, '/');

          // 外部リンクカードのドメインと同じであるかを判定
          $isSameAsExternal = false;
          if ($externalUri) {
              $parsedLink = parse_url($rawUrl);
              $parsedExt = parse_url($externalUri);
              if (isset($parsedLink['host']) && isset($parsedExt['host']) && $parsedLink['host'] === $parsedExt['host']) {
                  $isSameAsExternal = true;
              }
          }

          if (! $isSameAsExternal) {
              $annotation = ' <span class="text-[9px] text-slate-500 font-bold hover:no-underline select-none">('.e($cleanDomain).')</span>';
          }
      }

      $links[$placeholder] = '<a href="'.$url.'" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">'.$url.'</a>'.$annotation;

      return $placeholder;
    }, $safeText);

    // www. から始まるURLをリンク化
    $patternWww = '/www\.[^\s\r\n<>]+/i';
    $safeText = preg_replace_callback($patternWww, function ($matches) use ($externalUri) {
      $url = $matches[0];
      $rawUrl = 'https://'.htmlspecialchars_decode($url, ENT_QUOTES);

      $annotation = '';
      $domain = parse_url($rawUrl, PHP_URL_HOST);
      if ($domain) {
          $cleanDomain = preg_replace('/^www\./i', '', $domain);
          $cleanOrigText = preg_replace('/^https?:\/\//i', '', trim(strtolower($rawUrl)));
          $cleanOrigText = preg_replace('/^www\./i', '', $cleanOrigText);
          $cleanOrigText = rtrim($cleanOrigText, '/');

          // 外部リンクカードのドメインと同じであるかを判定
          $isSameAsExternal = false;
          if ($externalUri) {
              $parsedLink = parse_url($rawUrl);
              $parsedExt = parse_url($externalUri);
              if (isset($parsedLink['host']) && isset($parsedExt['host']) && $parsedLink['host'] === $parsedExt['host']) {
                  $isSameAsExternal = true;
              }
          }

          if (! $isSameAsExternal) {
              $annotation = ' <span class="text-[9px] text-slate-500 font-bold hover:no-underline select-none">('.e($cleanDomain).')</span>';
          }
      }

      return '<a href="https://'.$url.'" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">'.$url.'</a>'.$annotation;
    }, $safeText);

    // プレースホルダーを元の a タグに復元
    if (! empty($links)) {
      $safeText = strtr($safeText, $links);
    }

    return $safeText;
  }

  /**
   * 画像が含まれている投稿のみに絞り込むローカルスコープ。
   */
  public function scopeHasImages(Builder $query): Builder
  {
    return $query->where(function (Builder $query) {
      foreach (self::IMAGE_EMBED_TYPES as $type) {
        $query->orWhere('raw_data', 'like', '%'.$type.'%');
      }
    });
  }

  /**
   * リンク化されたリッチテキストHTMLを返却するアクセサ。
   */
  public function getHtmlTextAttribute(): string
  {
    $html = self::formatTextToHtml($this->text, $this->raw_data);

    // ハッシュタグのリンク化
    $isOwner = Auth::check() && Auth::id() === $this->user_id;
    $isDemo = ($this->user->blueskyAccount->handle ?? null) === config('services.bluesky.demo_handle');
    $isOwnerOrDemo = $isOwner || $isDemo;

    if ($this->user->blueskyAccount->handle ?? null) {
        $handle = $this->user->blueskyAccount->handle;

        // 1. すでに存在する a タグ（およびドメイン注釈を含む span タグ）をプレースホルダーに退避
        $placeholders = [];
        $placeholderPrefix = '___TAG_PLACEHOLDER_';

        $html = preg_replace_callback('/<a\b[^>]*>.*?<\/a>(\s*<span\b[^>]*>.*?<\/span>)?/is', function ($matches) use (&$placeholders, $placeholderPrefix) {
            $placeholder = $placeholderPrefix.count($placeholders).'___';
            $placeholders[$placeholder] = $matches[0];

            return $placeholder;
        }, $html);

        // 2. 残ったテキスト部分でハッシュタグをリンク化
        $pattern = Tag::EXTRACT_REGEX;
        $html = preg_replace_callback($pattern, function ($matches) use ($handle, $isOwnerOrDemo) {
            $hashtag = $matches[0]; // 例: "#Laravel"
            $tagName = $matches[1]; // 例: "Laravel"

            if ($isOwnerOrDemo) {
                // 本人・デモ閲覧時は同一タブでローカル検索リンク
                $url = route('calendar.show', ['handle' => $handle]).'?q='.urlencode('#'.$tagName);

                return '<a href="'.e($url).'" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">'.e($hashtag).'</a>';
            } else {
                // ゲスト閲覧時は別タブでBluesky公式検索リンク
                $url = 'https://bsky.app/search?q='.urlencode('#'.$tagName);

                return '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">'.e($hashtag).'</a>';
            }
        }, $html);

        // 3. 退避させた a タグを復元
        if (! empty($placeholders)) {
            $html = strtr($html, $placeholders);
        }
    }

    return $html;
  }
}
