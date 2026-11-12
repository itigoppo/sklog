<?php

namespace App\Support;

use App\Services\BlueskyService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * URL から取り込んだ投稿に、同期と同じ形の返信情報 (raw_data['reply']) を組み立てます。
 *
 * 同期は getAuthorFeed の1件分をそのまま保存するので、返信なら reply.root / reply.parent
 * （本文と投稿者を含む投稿）と reply.grandparentAuthor が入ります。
 * 一方 getPosts は投稿本体しか返さず、record.reply に返信先とスレッドトップの URI があるだけです。
 * そのままだと返信として扱われない（Post::is_reply が false になる）ので、その URI の投稿を
 * getPosts で取り直して、同期と同じ形にそろえます。
 */
class ReplyRef
{
  /**
   * 投稿ごとの reply を、投稿の URI をキーにして返します。返信でない投稿は含めません。
   *
   * 返信先などの取得に失敗したときは、投稿の取り込み自体は止めずに空の配列を返します
   * （返信の情報が無いまま保存され、あとの同期で範囲に入れば上書きされます）。
   *
   * @param  array  $postViews  getPosts が返した投稿 (app.bsky.feed.defs#postView) の配列
   * @return array<string, array>
   */
  public static function buildForPosts(array $postViews, BlueskyService $service, ?string $token): array
  {
    $refs = [];
    foreach ($postViews as $view) {
      $reply = $view['record']['reply'] ?? null;
      if (! empty($view['uri']) && ! empty($reply['parent']['uri']) && ! empty($reply['root']['uri'])) {
        $refs[$view['uri']] = $reply;
      }
    }

    if (empty($refs)) {
      return [];
    }

    try {
      // 返信先とスレッドトップ
      $views = self::fetchViews(
        collect($refs)->flatMap(fn ($ref) => [$ref['parent']['uri'], $ref['root']['uri']])->all(),
        $service,
        $token
      );

      // 返信先の投稿がさらに返信なら、その相手の投稿（grandparentAuthor の元）
      $grandparentUris = [];
      foreach ($refs as $ref) {
        $grandparentUri = $views[$ref['parent']['uri']]['record']['reply']['parent']['uri'] ?? null;
        if ($grandparentUri && ! isset($views[$grandparentUri])) {
          $grandparentUris[] = $grandparentUri;
        }
      }
      $views += self::fetchViews($grandparentUris, $service, $token);
    } catch (Exception $e) {
      Log::warning('ReplyRef: 返信先の取得に失敗したため、返信の情報なしで取り込みます: '.$e->getMessage());

      return [];
    }

    $result = [];
    foreach ($refs as $uri => $ref) {
      $parentUri = $ref['parent']['uri'];
      $reply = [
        'root' => self::viewOrNotFound($views, $ref['root']['uri']),
        'parent' => self::viewOrNotFound($views, $parentUri),
      ];

      $grandparentUri = $views[$parentUri]['record']['reply']['parent']['uri'] ?? null;
      if ($grandparentUri && isset($views[$grandparentUri]['author'])) {
        $reply['grandparentAuthor'] = $views[$grandparentUri]['author'];
      }

      $result[$uri] = $reply;
    }

    return $result;
  }

  /**
   * URI の投稿を getPosts でまとめて取り、URI をキーにして返します。
   *
   * @return array<string, array>
   */
  private static function fetchViews(array $uris, BlueskyService $service, ?string $token): array
  {
    $views = [];
    foreach (array_chunk(array_values(array_unique($uris)), BlueskyService::MAX_GET_POSTS) as $chunk) {
      foreach ($service->getPosts($chunk, $token)['posts'] ?? [] as $view) {
        if (! empty($view['uri'])) {
          $views[$view['uri']] = $view;
        }
      }
    }

    return $views;
  }

  /**
   * 取れた投稿は同期と同じ postView の形で、取れなかった投稿（削除・非公開など）は
   * 同期で返ってくるのと同じ notFoundPost の形で返します。
   */
  private static function viewOrNotFound(array $views, string $uri): array
  {
    if (isset($views[$uri])) {
      return ['$type' => 'app.bsky.feed.defs#postView'] + $views[$uri];
    }

    return [
      '$type' => 'app.bsky.feed.defs#notFoundPost',
      'uri' => $uri,
      'notFound' => true,
    ];
  }
}
