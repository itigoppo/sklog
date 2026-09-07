<?php

namespace Tests\Feature;

use App\Models\BlueskyAccount;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ThreadNumberTest extends TestCase
{
  use RefreshDatabase;

  private const MY_DID = 'did:plc:test123456';

  private const OTHER_DID = 'did:plc:other98765';

  private User $user;

  protected function setUp(): void
  {
    parent::setUp();

    $this->user = User::create([
      'name' => 'テストユーザー',
      'email' => 'test@example.com',
      'password' => bcrypt('password'),
    ]);

    BlueskyAccount::create([
      'user_id' => $this->user->id,
      'did' => self::MY_DID,
      'handle' => 'test.bsky.social',
      'encrypted_app_password' => 'secret_app_pass',
      'is_private' => false,
    ]);
  }

  /**
   * 自分の連投に、投稿順の通し番号が振られることを確認。
   */
  public function test_self_thread_posts_are_numbered_in_order(): void
  {
    $first = $this->createPost('t1', '1本目', Carbon::create(2026, 5, 1, 10, 0, 0));
    $second = $this->createPost('t2', '2本目', Carbon::create(2026, 5, 1, 10, 1, 0), $this->uri('t1'), $this->uri('t1'));
    $third = $this->createPost('t3', '3本目', Carbon::create(2026, 5, 1, 10, 2, 0), $this->uri('t2'), $this->uri('t1'));

    $numbers = Post::threadNumbers([$first, $second, $third]);

    $this->assertSame(['index' => 1, 'total' => 3], $numbers[$first->id]);
    $this->assertSame(['index' => 2, 'total' => 3], $numbers[$second->id]);
    $this->assertSame(['index' => 3, 'total' => 3], $numbers[$third->id]);
  }

  /**
   * 渡したリストに含まれない投稿まで数えることを確認。
   *
   * 日付やページをまたいだ連投は、その画面のリストにスレッド全体が載らない。
   * リスト内だけで数えると実際より小さい n になってしまう。
   */
  public function test_thread_number_counts_posts_outside_the_given_list(): void
  {
    // 5月1日から5月3日にまたがる3連投
    $this->createPost('d1', '1日目', Carbon::create(2026, 5, 1, 23, 50, 0));
    $this->createPost('d2', '2日目', Carbon::create(2026, 5, 2, 0, 10, 0), $this->uri('d1'), $this->uri('d1'));
    $lastDay = $this->createPost('d3', '3日目', Carbon::create(2026, 5, 3, 0, 10, 0), $this->uri('d2'), $this->uri('d1'));

    // 最後の1件だけを渡しても、スレッド全体の件数で数えられること
    $numbers = Post::threadNumbers([$lastDay]);

    $this->assertSame(['index' => 3, 'total' => 3], $numbers[$lastDay->id]);
  }

  /**
   * 連投ではない単発の投稿は n = 1 になり、番号として表示されないことを確認。
   */
  public function test_single_post_is_not_numbered(): void
  {
    $post = $this->createPost('single', '単発の投稿', Carbon::create(2026, 5, 1, 10, 0, 0));

    $numbers = Post::threadNumbers([$post]);

    $this->assertSame(1, $numbers[$post->id]['total']);
  }

  /**
   * 同じスレッドへ別々に返信しただけの投稿には番号が付かないことを確認。
   *
   * root uri は同じでも親子でつながっていないため、連投とは扱わない。
   */
  public function test_separate_replies_to_the_same_thread_are_not_numbered(): void
  {
    $othersRoot = $this->uri('others-root', self::OTHER_DID);

    $reply1 = $this->createPost('r1', '1つ目の返信', Carbon::create(2026, 5, 1, 10, 0, 0), $othersRoot, $othersRoot, self::OTHER_DID);
    $reply2 = $this->createPost('r2', '2つ目の返信', Carbon::create(2026, 5, 1, 11, 0, 0), $othersRoot, $othersRoot, self::OTHER_DID);

    $numbers = Post::threadNumbers([$reply1, $reply2]);

    $this->assertSame(1, $numbers[$reply1->id]['total']);
    $this->assertSame(1, $numbers[$reply2->id]['total']);
  }

  /**
   * 他人の投稿への返信から始まる連投も、自分の1件目を起点に番号が振られることを確認。
   */
  public function test_thread_starting_from_a_reply_to_others_is_numbered(): void
  {
    $othersRoot = $this->uri('others-root', self::OTHER_DID);

    $first = $this->createPost('o1', '他人への返信', Carbon::create(2026, 5, 1, 10, 0, 0), $othersRoot, $othersRoot, self::OTHER_DID);
    $second = $this->createPost('o2', 'その続き', Carbon::create(2026, 5, 1, 10, 1, 0), $this->uri('o1'), $othersRoot);

    $numbers = Post::threadNumbers([$first, $second]);

    $this->assertSame(['index' => 1, 'total' => 2], $numbers[$first->id]);
    $this->assertSame(['index' => 2, 'total' => 2], $numbers[$second->id]);
  }

  /**
   * リポストには番号が付かないことを確認。
   */
  public function test_reposts_are_not_numbered(): void
  {
    $repost = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'repost-1',
      'cid' => 'cid_repost1',
      'text' => 'リポストした投稿',
      'raw_data' => [
        'reason' => ['$type' => 'app.bsky.feed.defs#reasonRepost'],
        'post' => ['uri' => $this->uri('others-post', self::OTHER_DID)],
      ],
      'posted_at' => Carbon::create(2026, 5, 1, 10, 0, 0),
    ]);

    $this->assertSame([], Post::threadNumbers([$repost]));
  }

  /**
   * カレンダー画面に連投の通し番号が描画され、単発の投稿には出ないことを確認。
   */
  public function test_thread_number_is_rendered_on_the_calendar(): void
  {
    $this->createPost('t1', '連投の1本目', Carbon::create(2026, 5, 1, 10, 0, 0));
    $this->createPost('t2', '連投の2本目', Carbon::create(2026, 5, 1, 10, 1, 0), $this->uri('t1'), $this->uri('t1'));
    $this->createPost('single', '単発の投稿', Carbon::create(2026, 5, 2, 10, 0, 0));

    $response = $this->get('/profile/test.bsky.social');

    $response->assertStatus(200);
    $response->assertSee('この連投の2件中1件目');
    $response->assertSee('この連投の2件中2件目');
    $response->assertSee('1/2');
    $response->assertSee('2/2');

    // 単発の投稿に 1/1 が出ないこと
    $response->assertDontSee('1/1');
  }

  /**
   * 日ページ（オーナー限定）にも連投の通し番号が描画されることを確認。
   */
  public function test_thread_number_is_rendered_on_the_day_page(): void
  {
    $this->createPost('t1', '連投の1本目', Carbon::create(2026, 5, 1, 10, 0, 0));
    $this->createPost('t2', '連投の2本目', Carbon::create(2026, 5, 1, 10, 1, 0), $this->uri('t1'), $this->uri('t1'));

    $response = $this->actingAs($this->user)->get('/profile/test.bsky.social/2026/05/01');

    $response->assertStatus(200);
    $response->assertSee('この連投の2件中1件目');
    $response->assertSee('この連投の2件中2件目');
  }

  /**
   * at:// 形式の投稿 uri を組み立てます。
   */
  private function uri(string $rkey, string $did = self::MY_DID): string
  {
    return "at://{$did}/app.bsky.feed.post/{$rkey}";
  }

  /**
   * 投稿を作成します。$parentUri を渡すと返信（連投）になります。
   */
  private function createPost(
    string $rkey,
    string $text,
    Carbon $postedAt,
    ?string $parentUri = null,
    ?string $rootUri = null,
    string $parentDid = self::MY_DID
  ): Post {
    $uri = $this->uri($rkey);

    $rawData = [
      'post' => [
        'uri' => $uri,
        'cid' => 'cid_'.$rkey,
        'author' => ['did' => self::MY_DID, 'handle' => 'test.bsky.social'],
        'record' => ['text' => $text, 'createdAt' => $postedAt->toIso8601String()],
      ],
    ];

    if ($parentUri) {
      $rawData['reply'] = [
        'parent' => [
          'uri' => $parentUri,
          'author' => ['did' => $parentDid],
          'record' => ['text' => '親の投稿'],
        ],
        'root' => ['uri' => $rootUri ?? $parentUri],
      ];
    }

    return Post::create([
      'user_id' => $this->user->id,
      'uri' => $uri,
      'cid' => 'cid_'.$rkey,
      'text' => $text,
      'raw_data' => $rawData,
      'posted_at' => $postedAt,
    ]);
  }
}
