<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class BlueskyService
{
  protected const BSKY_API_HOST = 'https://bsky.social/xrpc/';

  /**
   * Blueskyアカウントの認証を行い、セッション情報を返却します。
   *
   * @param string $handle
   * @param string $appPassword
   * @return array
   * @throws Exception
   */
  public function authenticate(string $handle, string $appPassword): array
  {
    $handle = ltrim(trim($handle), '@');
    $appPassword = trim($appPassword);

    $response = Http::post(self::BSKY_API_HOST . 'com.atproto.server.createSession', [
      'identifier' => $handle,
      'password' => $appPassword,
    ]);

    if ($response->failed()) {
      $message = $response->json()['message'] ?? '';
      if ($message === 'Invalid identifier or password') {
        $error = 'ハンドル名またはアプリパスワードが正しくありません。';
      } else {
        $error = !empty($message) ? $message : '認証に失敗しました。ハンドル名またはアプリパスワードを確認してください。';
      }
      throw new Exception($error);
    }

    return $response->json();
  }

  /**
   * 指定したユーザーのプロフィール詳細を取得します。
   *
   * @param string $actor (did または handle)
   * @param string|null $token (認証トークンがあれば使用)
   * @return array
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

    $response = $request->get($apiHost . 'app.bsky.actor.getProfile', [
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
   * @param string $actor (did または handle)
   * @param int $limit
   * @param string|null $cursor
   * @param string|null $token
   * @return array
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

    $response = $request->get(self::BSKY_API_HOST . 'app.bsky.feed.getAuthorFeed', $params);

    if ($response->failed()) {
      Log::error('Bluesky API Error (getAuthorFeed): ' . $response->body());
      throw new Exception('フィードの取得に失敗しました。');
    }

    return $response->json();
  }

  /**
   * 指定した URI リストのポスト情報を取得します。
   *
   * @param array $uris (at-uri の配列)
   * @param string|null $token
   * @return array
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

    $response = $request->get(self::BSKY_API_HOST . 'app.bsky.feed.getPosts', [
      'uris' => $uris,
    ]);

    if ($response->failed()) {
      Log::error('Bluesky API Error (getPosts): ' . $response->body());
      throw new Exception('ポストの取得に失敗しました。');
    }

    return $response->json();
  }
}
