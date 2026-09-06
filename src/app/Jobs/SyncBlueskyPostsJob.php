<?php

namespace App\Jobs;

use App\Models\BlueskyAccount;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\BlueskyService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncBlueskyPostsJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  protected User $user;

  /**
   * Create a new job instance.
   */
  public function __construct(User $user)
  {
    $this->user = $user;
  }

  /**
   * Execute the job.
   */
  public function handle(BlueskyService $blueskyService): void
  {
    $bskyAccount = $this->user->blueskyAccount;
    if (! $bskyAccount) {
      Log::warning("SyncJob: User {$this->user->id} has no Bluesky account.");

      return;
    }

    // 二重起動防止：すでに実行中の場合は処理をスキップ
    if ($bskyAccount->is_syncing) {
      Log::info("SyncJob: User {$this->user->id} (@{$bskyAccount->handle}) is already syncing. Skipping.");

      return;
    }

    try {
      // ロックを獲得
      $bskyAccount->update(['is_syncing' => true]);

      // 暗号化されたアプリパスワードは自動で復号される（Model of casts設定による）
      $password = $bskyAccount->encrypted_app_password;

      // セッション作成
      $session = $blueskyService->authenticate($bskyAccount->handle, $password);
      $token = $session['accessJwt'];
      $did = $session['did'];

      // 1. プロフィール情報を取得して最新キャッシュに更新
      $profile = $blueskyService->getProfile($did, $token);

      $isPrivate = BlueskyAccount::isPrivateFromProfile($profile);

      $bskyAccount->update([
        'avatar_url' => $profile['avatar'] ?? null,
        'banner_url' => $profile['banner'] ?? null,
        'description' => $profile['description'] ?? null,
        'posts_count' => $profile['postsCount'] ?? 0,
        'registered_at' => isset($profile['createdAt']) ? Carbon::parse($profile['createdAt'])->timezone('Asia/Tokyo') : null,
        'is_private' => $isPrivate,
      ]);

      // 2. 最新の投稿（未来方向）を同期
      // cursorを指定せずに直近の最大100件を取得
      $latestFeedData = $blueskyService->getAuthorFeed($did, limit: 100, token: $token);
      $latestFeed = $latestFeedData['feed'] ?? [];
      $latestCursor = $latestFeedData['cursor'] ?? null;

      foreach ($latestFeed as $item) {
        $postData = $item['post'] ?? null;
        if (! $postData) {
          continue;
        }

        $uri = $postData['uri'];

        // 自身のオリジナルポスト、または本人のリポストのみを収集対象とする
        $authorDid = $postData['author']['did'] ?? '';
        $isRepost = isset($item['reason']) && (($item['reason']['$type'] ?? '') === 'app.bsky.feed.defs#reasonRepost');

        if ($authorDid !== $did && ! $isRepost) {
          continue;
        }

        if ($isRepost) {
          // リポストの場合、元の投稿のURIをそのまま使うと、複数ユーザー間でのデータ上書きや、
          // 同一ユーザーが過去に引用した際の重複・上書きが発生するため、自分専用のユニークな疑似URIを生成する
          $rkey = basename($uri);
          $uri = "repost-{$did}-{$rkey}";
        }

        // すでにDBに登録済みの場合のチェック（ソフトデリート済レコードも含む）
        $existingPost = Post::withTrashed()->where('uri', $uri)->first();
        if ($existingPost) {
          // すでにローカルで削除済みの場合は再インポートしない
          if ($existingPost->trashed()) {
            continue;
          }
          // アーカイブ完了後（通常同期）は早期終了。
          // アーカイブ未完了の場合は過去に途中失敗した可能性があるためスキップして続行する。
          if ($bskyAccount->is_archive_complete) {
            break;
          }

          continue;
        }

        $cid = $postData['cid'];
        $record = $postData['record'] ?? [];
        $text = $record['text'] ?? '';

        if ($isRepost) {
          // リポストの場合は「自分がリポストした日時」を使用する
          $createdAtString = $item['reason']['indexedAt'] ?? null;
        } else {
          // オリジナルポストの場合は「投稿作成日時」を使用する
          $createdAtString = $record['createdAt'] ?? null;
        }

        if (! $createdAtString) {
          continue;
        }

        $postedAt = Carbon::parse($createdAtString)->timezone('Asia/Tokyo');

        $post = Post::updateOrCreate(
          ['uri' => $uri],
          [
            'user_id' => $this->user->id,
            'cid' => $cid,
            'text' => $text,
            'raw_data' => $item,
            'posted_at' => $postedAt,
          ]
        );

        // ハッシュタグの抽出と紐付け（オリジナル投稿のみ対象、リポストのハッシュタグは集計から除外するためスキップ）
        $tagIds = [];
        if (! $isRepost) {
          foreach (Tag::extractTags($text) as $tagName) {
            $tag = Tag::firstOrCreate(['name' => $tagName]);
            $tagIds[] = $tag->id;
          }
        }
        $post->tags()->sync($tagIds);
      }

      // 初回同期時（まだ過去遡り用カーソルがなく、DBへの登録が初めてのとき）の初期設定
      // 最新の同期で取得した最後の cursor を next_cursor にセットし、過去方向への遡り起点とする
      if (! $bskyAccount->next_cursor && ! $bskyAccount->is_archive_complete) {
        if ($latestCursor) {
          $bskyAccount->update([
            'next_cursor' => $latestCursor,
          ]);
        } else {
          // cursor が返ってこない＝投稿が100件未満で、過去ログがそもそも存在しない場合
          $bskyAccount->update([
            'is_archive_complete' => true,
          ]);
        }
      }

      // 3. 過去の投稿（過去方向）を同期
      // 過去ログのインポートが完了しておらず、かつ遡り用カーソルがある場合に実行
      $bskyAccount->refresh();
      if (! $bskyAccount->is_archive_complete && $bskyAccount->next_cursor) {
        $archiveFeedData = $blueskyService->getAuthorFeed(
          $did,
          limit: 100,
          cursor: $bskyAccount->next_cursor,
          token: $token
        );
        $archiveFeed = $archiveFeedData['feed'] ?? [];
        $nextCursor = $archiveFeedData['cursor'] ?? null;

        foreach ($archiveFeed as $item) {
          $postData = $item['post'] ?? null;
          if (! $postData) {
            continue;
          }

          $uri = $postData['uri'];
          $authorDid = $postData['author']['did'] ?? '';
          $isRepost = isset($item['reason']) && (($item['reason']['$type'] ?? '') === 'app.bsky.feed.defs#reasonRepost');

          if ($authorDid !== $did && ! $isRepost) {
            continue;
          }

          if ($isRepost) {
            // リポストの場合、元の投稿のURIをそのまま使うと、複数ユーザー間でのデータ上書きや、
            // 同一ユーザーが過去に引用した際の重複・上書きが発生するため、自分専用のユニークな疑似URIを生成する
            $rkey = basename($uri);
            $uri = "repost-{$did}-{$rkey}";
          }

          // すでにDBに登録済みの場合のチェック（ソフトデリート済レコードも含む）
          $existingPost = Post::withTrashed()->where('uri', $uri)->first();
          if ($existingPost) {
            // すでにローカルで削除済みの場合は再インポートしない
            if ($existingPost->trashed()) {
              continue;
            }

            // 過去ログはすでにDBにあるためスキップ
            continue;
          }

          $cid = $postData['cid'];
          $record = $postData['record'] ?? [];
          $text = $record['text'] ?? '';

          if ($isRepost) {
            // リポストの場合は「自分がリポストした日時」を使用する
            $createdAtString = $item['reason']['indexedAt'] ?? null;
          } else {
            // オリジナルポストの場合は「投稿作成日時」を使用する
            $createdAtString = $record['createdAt'] ?? null;
          }

          if (! $createdAtString) {
            continue;
          }

          $postedAt = Carbon::parse($createdAtString)->timezone('Asia/Tokyo');

          $post = Post::updateOrCreate(
            ['uri' => $uri],
            [
              'user_id' => $this->user->id,
              'cid' => $cid,
              'text' => $text,
              'raw_data' => $item,
              'posted_at' => $postedAt,
            ]
          );

          // ハッシュタグの抽出と紐付け（オリジナル投稿のみ対象、リポストのハッシュタグは集計から除外するためスキップ）
          $tagIds = [];
          if (! $isRepost) {
            foreach (Tag::extractTags($text) as $tagName) {
              $tag = Tag::firstOrCreate(['name' => $tagName]);
              $tagIds[] = $tag->id;
            }
          }
          $post->tags()->sync($tagIds);
        }

        // 過去ログ用カーソルの更新
        if ($nextCursor) {
          $bskyAccount->update([
            'next_cursor' => $nextCursor,
          ]);
        } else {
          // 次のカーソルがない＝最古の投稿まで遡り完了
          $bskyAccount->update([
            'next_cursor' => null,
            'is_archive_complete' => true,
          ]);
        }
      }

      // 既存ポストに対する一括ハッシュタグ初回移行ロジック (中間テーブルが空の場合のみ自動実行)
      if (DB::table('post_tags')->count() === 0) {
        $existingPosts = Post::where('user_id', $this->user->id)->get();
        foreach ($existingPosts as $existingPost) {
          // リポストの場合はハッシュタグを集計対象外にするため移行をスキップ
          if ($existingPost->is_repost) {
            continue;
          }

          $tagIds = [];
          foreach (Tag::extractTags($existingPost->text) as $tagName) {
            $tag = Tag::firstOrCreate(['name' => $tagName]);
            $tagIds[] = $tag->id;
          }
          if (! empty($tagIds)) {
            $existingPost->tags()->sync($tagIds);
          }
        }
      }

      // ハッシュタグキャッシュのクリア（次回のページ読み込み時に最新で再計算されます）
      Cache::forget('user-tags:'.$this->user->id);

      // 同期成功日時を記録し、直近のエラー情報をクリア
      $bskyAccount->update([
        'last_synced_at' => Carbon::now(),
        'sync_error_type' => null,
        'sync_error_message' => null,
        'auth_failure_count' => 0,          // 成功時に認証エラーカウントをクリア
        'is_auto_sync_enabled' => true,     // 同期成功時に自動同期を再有効化
      ]);

      Log::info("SyncJob: Successfully synced posts for user {$this->user->id} (@{$bskyAccount->handle})");

    } catch (Exception $e) {
      $errorMessage = $e->getMessage();
      Log::error("SyncJob Error for user {$this->user->id}: ".$errorMessage);

      // アプリパスワード無効による認証エラーかどうかを特定
      $errorType = 'api_failed'; // デフォルトは汎用APIエラー
      if (str_contains($errorMessage, 'ハンドル名またはアプリパスワードが正しくありません') ||
          str_contains($errorMessage, 'Authentication failed') ||
          str_contains($errorMessage, 'Invalid identifier or password')) {
        $errorType = 'auth_failed';
      }

      $updateData = [
        'sync_error_type' => $errorType,
        'sync_error_message' => $errorMessage,
      ];

      // 認証エラー（auth_failed）の場合に失敗カウントをインクリメントし、3回連続で失敗した場合は自動同期を無効化
      if ($errorType === 'auth_failed') {
        $nextFailureCount = ($bskyAccount->auth_failure_count ?? 0) + 1;
        $updateData['auth_failure_count'] = $nextFailureCount;

        if ($nextFailureCount >= 3) {
          $updateData['is_auto_sync_enabled'] = false;
          Log::warning("SyncJob: Auto sync disabled for user {$this->user->id} due to consecutive authentication failures ({$nextFailureCount} times).");
        }
      }

      $bskyAccount->update($updateData);

      // コントローラーでの手動同期時にエラーを通知するために再スロー
      throw $e;
    } finally {
      // 例外が発生した場合でも、確実にロックを解除する
      $bskyAccount->refresh();
      if ($bskyAccount) {
        $bskyAccount->update(['is_syncing' => false]);
      }
    }
  }
}
