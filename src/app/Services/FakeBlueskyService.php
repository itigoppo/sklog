<?php

namespace App\Services;

use App\Models\BlueskyAccount;
use App\Models\Post;
use Illuminate\Support\Facades\Log;

/**
 * ローカル開発用の偽の BlueskyService です。本物の Bluesky には一切つながりません。
 *
 * - 認証は、DB にあるアカウントならどのアプリパスワードでも通します
 * - プロフィールや投稿は、DB に保存済みの内容をそのまま返します
 * - 同期で取ってくる新しい投稿はありません
 * - 削除はログに出すだけで、何も消しません（「消したことにする」）
 *
 * LocalDev::fakeBlueskyEnabled() のときだけ AppServiceProvider で差し替えます。
 */
class FakeBlueskyService extends BlueskyService
{
  /**
   * トークンに did を埋め込んでおき、後続の呼び出しで誰の操作かを分かるようにします。
   */
  private const TOKEN_PREFIX = 'fake-token:';

  public function authenticate(string $handle, string $appPassword): array
  {
    $handle = ltrim(trim($handle), '@');
    $account = BlueskyAccount::where('handle', $handle)->first();
    $did = $account->did ?? 'did:plc:fake'.substr(md5($handle), 0, 16);

    return [
      'accessJwt' => self::TOKEN_PREFIX.$did,
      'refreshJwt' => self::TOKEN_PREFIX.$did,
      'did' => $did,
      'handle' => $handle,
    ];
  }

  public function getProfile(string $actor, ?string $token = null): array
  {
    $actor = ltrim(trim($actor), '@');
    $account = BlueskyAccount::where('did', $actor)->orWhere('handle', $actor)->first();

    if (! $account) {
      return [
        'did' => str_starts_with($actor, 'did:') ? $actor : 'did:plc:fake'.substr(md5($actor), 0, 16),
        'handle' => str_starts_with($actor, 'did:') ? 'unknown.bsky.social' : $actor,
        'postsCount' => 0,
      ];
    }

    // 保存済みの値を返して、ログインや同期でプロフィールが消えないようにする
    return array_filter([
      'did' => $account->did,
      'handle' => $account->handle,
      'displayName' => $account->user?->name,
      'avatar' => $account->avatar_url,
      'banner' => $account->banner_url,
      'description' => $account->description,
      'postsCount' => $account->posts_count,
      'labels' => $account->is_private ? [['val' => '!no-unauthenticated']] : [],
    ], fn ($value) => ! is_null($value));
  }

  public function getAuthorFeed(string $actor, int $limit = 50, ?string $cursor = null, ?string $token = null): array
  {
    return ['feed' => [], 'cursor' => null];
  }

  public function getPosts(array $uris, ?string $token = null): array
  {
    $did = $this->didFromToken($token);
    $views = [];

    foreach ($uris as $uri) {
      // 自分の投稿は、保存してある投稿のデータをそのまま返す
      $post = Post::withTrashed()->where('uri', $uri)->first();
      if ($post) {
        $views[] = ['uri' => $uri] + ($post->raw_data['post'] ?? []);

        continue;
      }

      // リポストした元の投稿は、自分のリポストのレコードを viewer.repost に入れて返す
      $repost = Post::withTrashed()
        ->where('uri', 'like', 'repost-%')
        ->where('raw_data->post->uri', $uri)
        ->first();
      if ($repost) {
        $rkey = basename($uri);
        $views[] = ['uri' => $uri] + ($repost->raw_data['post'] ?? []) + [
          'viewer' => ['repost' => "at://{$did}/app.bsky.feed.repost/fake{$rkey}"],
        ];
      }
    }

    return ['posts' => $views];
  }

  public function deleteRecords(string $repo, array $records, string $token): void
  {
    Log::info('FakeBlueskyService: Bluesky側の削除は行わず、消したことにしました。', [
      'repo' => $repo,
      'records' => $records,
    ]);
  }

  private function didFromToken(?string $token): string
  {
    if ($token && str_starts_with($token, self::TOKEN_PREFIX)) {
      return substr($token, strlen(self::TOKEN_PREFIX));
    }

    return 'did:plc:fake';
  }
}
