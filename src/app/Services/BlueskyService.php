<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlueskyService
{
  protected const BSKY_API_HOST = 'https://bsky.social/xrpc/';

  /**
   * getPosts に一度に渡せる URI の上限。
   */
  public const MAX_GET_POSTS = 25;

  /**
   * applyWrites で一度に送れる書き込みの上限。
   */
  public const MAX_WRITES = 200;

  /**
   * Blueskyアカウントの認証を行い、セッション情報を返却します。
   *
   * @throws Exception
   */
  public function authenticate(string $handle, string $appPassword): array
  {
    $handle = ltrim(trim($handle), '@');
    $appPassword = trim($appPassword);

    $response = Http::post(self::BSKY_API_HOST.'com.atproto.server.createSession', [
      'identifier' => $handle,
      'password' => $appPassword,
    ]);

    if ($response->failed()) {
      $message = $response->json()['message'] ?? '';
      if ($message === 'Invalid identifier or password') {
        $error = 'ハンドル名またはアプリパスワードが正しくありません。';
      } else {
        $error = ! empty($message) ? $message : '認証に失敗しました。ハンドル名またはアプリパスワードを確認してください。';
      }
      throw new Exception($error);
    }

    return $response->json();
  }

  /**
   * 指定したユーザーのプロフィール詳細を取得します。
   *
   * @param  string  $actor  (did または handle)
   * @param  string|null  $token  (認証トークンがあれば使用)
   *
   * @throws Exception
   */
  public function getProfile(string $actor, ?string $token = null): array
  {
    $actor = ltrim(trim($actor), '@');
    $request = Http::asJson();

    // 認証トークンがない場合は、パブリック専用のAPIエンドポイントを使用する（カスタムドメインの確実な解決のため）
    $apiHost = $token ? self::BSKY_API_HOST : 'https://public.api.bsky.app/xrpc/';

    if ($token) {
      $request = $request->withToken($token);
    }

    $response = $request->get($apiHost.'app.bsky.actor.getProfile', [
      'actor' => $actor,
    ]);

    if ($response->failed()) {
      throw new Exception('プロフィールの取得に失敗しました。');
    }

    return $response->json();
  }

  /**
   * 指定したユーザーの投稿フィードを取得します。
   *
   * @param  string  $actor  (did または handle)
   *
   * @throws Exception
   */
  public function getAuthorFeed(string $actor, int $limit = 50, ?string $cursor = null, ?string $token = null): array
  {
    $actor = ltrim(trim($actor), '@');
    $request = Http::asJson();

    if ($token) {
      $request = $request->withToken($token);
    }

    $params = [
      'actor' => $actor,
      'limit' => $limit,
    ];

    if ($cursor) {
      $params['cursor'] = $cursor;
    }

    $response = $request->get(self::BSKY_API_HOST.'app.bsky.feed.getAuthorFeed', $params);

    if ($response->failed()) {
      Log::error('Bluesky API Error (getAuthorFeed): '.$response->body());
      throw new Exception('フィードの取得に失敗しました。');
    }

    return $response->json();
  }

  /**
   * 指定した URI リストのポスト情報を取得します。
   *
   * @param  array  $uris  (at-uri の配列)
   *
   * @throws Exception
   */
  public function getPosts(array $uris, ?string $token = null): array
  {
    if (empty($uris)) {
      return ['posts' => []];
    }

    $request = Http::asJson();
    if ($token) {
      $request = $request->withToken($token);
    }

    $response = $request->get(self::BSKY_API_HOST.'app.bsky.feed.getPosts', [
      'uris' => $uris,
    ]);

    if ($response->failed()) {
      Log::error('Bluesky API Error (getPosts): '.$response->body());
      throw new Exception('ポストの取得に失敗しました。');
    }

    return $response->json();
  }

  /**
   * 自分のリポジトリにある複数のレコードを、applyWrites でまとめて削除します。
   *
   * 1件でも存在しないレコードが含まれていると全体が失敗するため、
   * 呼び出し側で存在を確かめたものだけを渡してください。
   *
   * @param  string  $repo  (自分の did)
   * @param  array<int, array{collection: string, rkey: string}>  $records  (MAX_WRITES 件まで)
   *
   * @throws Exception
   */
  public function deleteRecords(string $repo, array $records, string $token): void
  {
    if (empty($records)) {
      return;
    }

    $writes = array_map(fn (array $record) => [
      '$type' => 'com.atproto.repo.applyWrites#delete',
      'collection' => $record['collection'],
      'rkey' => $record['rkey'],
    ], array_values($records));

    $response = Http::asJson()
      ->withToken($token)
      ->post(self::BSKY_API_HOST.'com.atproto.repo.applyWrites', [
        'repo' => $repo,
        'writes' => $writes,
      ]);

    if ($response->failed()) {
      Log::error('Bluesky API Error (applyWrites): '.$response->body());
      throw new Exception('Blueskyでの削除に失敗しました。');
    }
  }
}
