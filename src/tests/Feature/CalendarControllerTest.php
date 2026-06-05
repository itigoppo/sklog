<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\BlueskyAccount;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarControllerTest extends TestCase
{
  use RefreshDatabase;

  private User $user;
  private BlueskyAccount $account;

  protected function setUp(): void
  {
    parent::setUp();

    // テスト用のユーザーとBlueskyアカウントを設定
    $this->user = User::create([
      'name' => 'テストユーザー',
      'email' => 'test@example.com',
      'password' => bcrypt('password'),
    ]);

    $this->account = BlueskyAccount::create([
      'user_id' => $this->user->id,
      'did' => 'did:plc:test123456',
      'handle' => 'test.bsky.social',
      'encrypted_app_password' => 'secret_app_pass',
      'is_private' => false,
      'description' => 'テストプロフィールです',
    ]);

    // テスト用のデモハンドルを設定値にバインド（ローカルの環境変数に依存させない）
    config(['services.bluesky.demo_handle' => 'demo-account.bsky.social']);
  }

  /**
   * メインカレンダー/ブログページがデフォルトで全期間の投稿をページネーション表示することを確認。
   */
  public function test_main_calendar_page_displays_all_posts_by_default(): void
  {
    // 過去の投稿を作成
    $post1 = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post1',
      'cid' => 'cid1',
      'text' => '昨日の日記',
      'raw_data' => ['post' => ['likeCount' => 3]],
      'posted_at' => Carbon::now()->subDay(),
    ]);

    $post2 = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post2',
      'cid' => 'cid2',
      'text' => '今日の日記',
      'raw_data' => ['post' => ['likeCount' => 5]],
      'posted_at' => Carbon::now(),
    ]);

    $response = $this->get("/@test.bsky.social");

    $response->assertStatus(200);
    $response->assertSee('テストユーザー');
    $response->assertSee('test.bsky.social');
    $response->assertSee('テストプロフィールです');
    $response->assertSee('昨日の日記');
    $response->assertSee('今日の日記');
  }

  /**
   * 投稿が0件かつ同期履歴がない場合、isFirstSyncNeeded が true となり自動同期スクリプトが出力されることを確認。
   */
  public function test_first_sync_needed_flag_and_script_are_rendered_when_no_posts_and_sync_history(): void
  {
    // 同期履歴がなく、投稿も0件の状態
    $this->account->last_synced_at = null;
    $this->account->save();

    // 投稿をすべて削除（念のため）
    Post::where('user_id', $this->user->id)->delete();

    $response = $this->get("/@test.bsky.social");

    $response->assertStatus(200);
    $response->assertViewHas('isFirstSyncNeeded', true);
    $response->assertSee('初回データを同期中...');
    $response->assertSee('syncForm.submit()');
  }

  /**
   * 引用ポストが存在する場合に、引用元の投稿情報が正しく描画されることを確認。
   */
  public function test_quoted_post_is_correctly_rendered_in_post_card(): void
  {
    $quotedFeedItem = [
      'post' => [
        'uri' => 'at://did:plc:test123456/app.bsky.feed.post/my-post-with-quote',
        'cid' => 'cid-main',
        'likeCount' => 2,
        'repostCount' => 1,
        'embed' => [
          '$type' => 'app.bsky.embed.record#view',
          'record' => [
            '$type' => 'app.bsky.embed.record#viewRecord',
            'uri' => 'at://did:plc:other98765/app.bsky.feed.post/quoted-post-rkey',
            'cid' => 'cid-quoted',
            'author' => [
              'did' => 'did:plc:other98765',
              'handle' => 'quoted-author.bsky.social',
              'displayName' => '引用された親切な人',
              'avatar' => 'https://example.com/avatar.jpg'
            ],
            'value' => [
              'text' => 'これは引用された元の素晴らしい投稿文です。',
              'createdAt' => '2026-05-26T06:00:00.000Z'
            ]
          ]
        ]
      ]
    ];

    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/my-post-with-quote',
      'cid' => 'cid-main',
      'text' => 'こちらの投稿を引用します！',
      'raw_data' => $quotedFeedItem,
      'posted_at' => Carbon::now(),
    ]);

    $response = $this->get("/@test.bsky.social");

    $response->assertStatus(200);
    $response->assertSee('こちらの投稿を引用します！');
    // 引用された側の情報が表示されていること
    $response->assertSee('引用された親切な人');
    $response->assertSee('@quoted-author.bsky.social');
    $response->assertSee('これは引用された元の素晴らしい投稿文です。');
  }

  /**
   * リポストされた投稿が存在する場合に、リポストバッジおよび元の投稿者情報が正しく描画されることを確認。
   */
  public function test_reposted_post_is_correctly_rendered_in_post_card(): void
  {
    $repostFeedItem = [
      'post' => [
        'uri' => 'at://did:plc:other98765/app.bsky.feed.post/original-post-rkey',
        'cid' => 'cid-original',
        'author' => [
          'did' => 'did:plc:other98765',
          'handle' => 'original-author.bsky.social',
          'displayName' => 'オリジナル投稿の作者',
          'avatar' => 'https://example.com/original-avatar.jpg'
        ],
        'record' => [
          'text' => 'これは元の誰かの素晴らしい投稿です。',
          'createdAt' => '2026-05-26T05:00:00.000Z'
        ],
        'likeCount' => 10,
        'repostCount' => 3
      ],
      'reason' => [
        '$type' => 'app.bsky.feed.defs#reasonRepost',
        'by' => [
          'did' => $this->account->did,
          'handle' => $this->account->handle,
          'displayName' => $this->user->name
        ],
        'indexedAt' => '2026-05-26T06:00:00.000Z'
      ]
    ];

    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:other98765/app.bsky.feed.post/original-post-rkey',
      'cid' => 'cid-original',
      'text' => 'これは元の誰かの素晴らしい投稿です。',
      'raw_data' => $repostFeedItem,
      'posted_at' => Carbon::now(),
    ]);

    $response = $this->get("/@test.bsky.social");

    $response->assertStatus(200);
    $response->assertSee('これは元の誰かの素晴らしい投稿です。');
    // リポストバッジとリポスト元の情報が表示されていること
    $response->assertSee('リポスト');
    $response->assertSee('オリジナル投稿の作者');
    $response->assertSee('@original-author.bsky.social');
  }

  /**
   * 年アーカイブページが正しいデータを取得して表示することを確認。
   */
  public function test_annual_archive_page_displays_correct_yearly_data(): void
  {
    $this->actingAs($this->user);
    $targetYear = 2025;

    // 2025年5月の投稿を作成
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_y1',
      'cid' => 'cidy1',
      'text' => '2025年5月の日記',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::create($targetYear, 5, 10, 12, 0, 0),
    ]);

    $response = $this->get("/@test.bsky.social/{$targetYear}");

    $response->assertStatus(200);
    $response->assertSee("2025年のログ");
    $response->assertSee("1 件のログ"); // 5月のカウント表示など
  }

  /**
   * 月アーカイブページがその月の投稿のみを正しく取得して表示することを確認。
   */
  public function test_monthly_archive_page_displays_correct_monthly_posts(): void
  {
    $this->actingAs($this->user);
    $year = '2026';
    $month = '05';

    // 対象月の投稿
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_m1',
      'cid' => 'cidm1',
      'text' => '2026年5月の日記',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::create(2026, 5, 15, 10, 0, 0),
    ]);

    // 対象外の月の投稿
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_m2',
      'cid' => 'cidm2',
      'text' => '2026年6月の日記',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::create(2026, 6, 15, 10, 0, 0),
    ]);

    $response = $this->get("/@test.bsky.social/{$year}/{$month}");

    $response->assertStatus(200);
    $response->assertSee('2026年');
    $response->assertSee('5月のログ');
    $response->assertSee('2026年5月の日記');
    $response->assertDontSee('2026年6月の日記');
  }

  /**
   * 日アーカイブページがその日の投稿を時系列順に取得して表示することを確認。
   */
  public function test_daily_archive_page_displays_correct_daily_posts(): void
  {
    $this->actingAs($this->user);
    $year = '2026';
    $month = '05';
    $day = '26';

    // 順序確認用の2つの投稿（時間順）
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_d2',
      'cid' => 'cidd2',
      'text' => '夜の投稿',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::create(2026, 5, 26, 21, 30, 0),
    ]);

    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_d1',
      'cid' => 'cidd1',
      'text' => '朝の投稿',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::create(2026, 5, 26, 8, 0, 0),
    ]);

    $response = $this->get("/@test.bsky.social/{$year}/{$month}/{$day}");

    $response->assertStatus(200);
    $response->assertSee('2026年05月26日');
    $response->assertSee('朝の投稿');
    $response->assertSee('夜の投稿');
  }

  /**
   * 非公開アカウントの場合、未ログイン（ゲスト）や他ユーザーからのアクセスに対して404を返すことを確認。
   */
  public function test_private_account_requires_auth_and_denies_unauthorized_guests(): void
  {
    // 非公開アカウントに設定
    $this->account->update(['is_private' => true]);

    // ゲスト（未ログイン）からのアクセス -> 404
    $response = $this->get("/@test.bsky.social");
    $response->assertStatus(404);

    // 別ユーザーからのアクセス -> 404
    $otherUser = User::create([
      'name' => '別ユーザー',
      'email' => 'other@example.com',
      'password' => bcrypt('password'),
    ]);

    $response = $this->actingAs($otherUser)->get("/@test.bsky.social");
    $response->assertStatus(404);

    // 本人からのアクセス -> 200 OK
    $response = $this->actingAs($this->user)->get("/@test.bsky.social");
    $response->assertStatus(200);
  }

  /**
   * 非公開ユーザーのコンテンツ（リポスト元・引用元・返信相手）がゲストに対して正しくマスキングされることを確認。
   * ただし、カレンダーの所有者本人には見えて良いことを確認。
   */
  public function test_privacy_masking_for_private_users_posts(): void
  {
    // 1. 本システム上の非公開のBlueskyアカウントを持つユーザーを作成
    $privateUser = User::create([
      'name' => '非公開テストユーザー',
      'email' => 'private-test@example.com',
      'password' => bcrypt('password'),
    ]);
    $privateAccount = BlueskyAccount::create([
      'user_id' => $privateUser->id,
      'handle' => 'private-target.bsky.social',
      'did' => 'did:plc:private123456',
      'is_private' => true,
      'encrypted_app_password' => 'dummy_encrypted_password',
    ]);

    // 2. 他者の非公開ユーザーの投稿をリポストした投稿レコード
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'repost-did:plc:test123456-repost_private_target',
      'cid' => 'cid_repost_private',
      'text' => '非公開ユーザーの秘密のつぶやき内容',
      'raw_data' => [
        'post' => [
          'author' => [
            'did' => 'did:plc:private123456',
            'handle' => 'private-target.bsky.social',
            'displayName' => '非公開の秘密部屋',
            'avatar' => 'https://example.com/avatar.png',
          ]
        ],
        'reason' => [
          '$type' => 'app.bsky.feed.defs#reasonRepost'
        ]
      ],
      'posted_at' => Carbon::now(),
    ]);

    // 3. 他者の非公開ユーザーの投稿を引用した投稿レコード
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/quote_private_target',
      'cid' => 'cid_quote_private',
      'text' => '非公開ユーザーを引用しました',
      'raw_data' => [
        'post' => [
          'embed' => [
            '$type' => 'app.bsky.embed.record#view',
            'record' => [
              '$type' => 'app.bsky.embed.record#viewRecord',
              'uri' => 'at://did:plc:private123456/app.bsky.feed.post/post123',
              'author' => [
                'did' => 'did:plc:private123456',
                'handle' => 'private-target.bsky.social',
                'displayName' => '非公開の秘密部屋',
              ],
              'value' => [
                'text' => '引用された非公開ユーザーの内緒の発言',
                'createdAt' => Carbon::now()->toIso8601String(),
              ]
            ]
          ]
        ]
      ],
      'posted_at' => Carbon::now()->subMinute(),
    ]);

    // 4. 他者の非公開ユーザーへの返信投稿レコード
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/reply_private_target',
      'cid' => 'cid_reply_private',
      'text' => '返信の本文です',
      'raw_data' => [
        'post' => [],
        'reply' => [
          'parent' => [
            'uri' => 'at://did:plc:private123456/app.bsky.feed.post/post456',
            'author' => [
              'did' => 'did:plc:private123456',
              'handle' => 'private-target.bsky.social',
              'displayName' => '非公開の秘密部屋',
            ],
            'record' => [
              'text' => '返信元の非公開ユーザーの内緒の発言',
            ]
          ],
          'root' => [
            'uri' => 'at://did:plc:private123456/app.bsky.feed.post/post456',
            'author' => [
              'did' => 'did:plc:private123456',
              'handle' => 'private-target.bsky.social',
            ]
          ]
        ]
      ],
      'posted_at' => Carbon::now()->subMinutes(2),
    ]);

    // 5. ゲスト（未ログイン）からのアクセス時のアサーション (マスキングされていること)
    $response = $this->get("/@test.bsky.social");
    $response->assertStatus(200);

    // リポスト元が非公開 -> 本文とアバター/表示名がマスキングされていること
    $response->assertSee('非公開アカウントの投稿（リポスト）のため、内容は表示できません。');
    $response->assertDontSee('非公開ユーザーの秘密のつぶやき内容');
    $response->assertSee('非公開ユーザー');
    $response->assertSee('@private.user');

    // 引用元が非公開 -> ロック表示になり発言がマスキングされていること
    $response->assertSee('非公開アカウントの投稿のため表示できません。');
    $response->assertDontSee('引用された非公開ユーザーの内緒の発言');

    // 返信相手が非公開 -> 返信先の名前がマスキングされ、会話表示ボタンが出ないこと
    $response->assertSee('非公開ユーザー');
    $response->assertDontSee('会話を表示');
    $response->assertDontSee('返信元の非公開ユーザーの内緒の発言');

    // 6. カレンダーの所有者本人からのアクセス時のアサーション (すべて表示されていること)
    $response2 = $this->actingAs($this->user)->get("/@test.bsky.social");
    $response2->assertStatus(200);

    // リポスト元が非公開 -> 本文もアバター元の名前もそのまま見えていること
    $response2->assertSee('非公開ユーザーの秘密のつぶやき内容');
    $response2->assertSee('非公開の秘密部屋');

    // 引用元が非公開 -> 引用内容がそのまま見えていること
    $response2->assertSee('引用された非公開ユーザーの内緒の発言');

    // 返信相手が非公開 -> 返信先の発言や会話表示ボタンがそのまま見えていること
    $response2->assertSee('返信元の非公開ユーザーの内緒の発言');
    $response2->assertSee('会話を表示');
  }

  /**
   * キーワード検索が単一キーワードで正しく機能することを確認。
   */
  public function test_search_posts_by_single_keyword(): void
  {
    $this->actingAs($this->user);
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/s1',
      'cid' => 'cids1',
      'text' => 'Laravelのテストを書いています',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/s2',
      'cid' => 'cids2',
      'text' => 'Symfonyで開発中',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now()->subHour(),
    ]);

    // 「Laravel」で検索
    $response = $this->get("/@test.bsky.social?q=Laravel");

    $response->assertStatus(200);
    $response->assertSee('Laravelのテストを書いています');
    $response->assertDontSee('Symfonyで開発中');
    $response->assertSee('検索結果');
    $response->assertSee('1件');
  }
 
  /**
   * キーワード検索時に、リポストされた他人の投稿（repost- 疑似URI）が検索結果から完全に除外されることを確認。
   */
  public function test_search_posts_excludes_reposts(): void
  {
    $this->actingAs($this->user);
    // 1. 検索ワード「Laravel」を含むオリジナル投稿
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/orig_search',
      'cid' => 'cid_orig_search',
      'text' => 'Laravelの勉強は本当に楽しいですね。',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);
 
    // 2. 検索ワード「Laravel」を含む他人の投稿（自分がリポストした投稿）
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'repost-did:plc:test123456-repost_search',
      'cid' => 'cid_repost_search',
      'text' => '他人の発言：Laravelは非常に強力なフレームワークです。',
      'raw_data' => [
        'post' => [],
        'reason' => [
          '$type' => 'app.bsky.feed.defs#reasonRepost',
          'by' => ['did' => 'did:plc:test123456']
        ]
      ],
      'posted_at' => Carbon::now()->subHour(),
    ]);
 
    // 「Laravel」で検索
    $response = $this->get("/@test.bsky.social?q=Laravel");
 
    $response->assertStatus(200);
    // オリジナル投稿は表示され、リポストは非表示になっていることを確認
    $response->assertSee('Laravelの勉強は本当に楽しいですね。');
    $response->assertDontSee('他人の発言：Laravelは非常に強力なフレームワークです。');
    
    // 検索件数が 1件 であることを検証
    $response->assertSee('1件');
  }

  /**
   * スペースで区切った複数キーワードでのAND検索が正しく機能することを確認。
   */
  public function test_search_posts_by_multiple_keywords_and_mode(): void
  {
    $this->actingAs($this->user);
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/s3',
      'cid' => 'cids3',
      'text' => '今日は天気が良くて暖かいですね。お散歩日和です。',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/s4',
      'cid' => 'cids4',
      'text' => '今日は雨が降っています。室内で勉強します。',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now()->subHour(),
    ]);

    // 「今日 天気」でAND検索（全角スペースと半角スペース両方を混在）
    $response = $this->get("/@test.bsky.social?q=今日　天気&mode=and");

    $response->assertStatus(200);
    $response->assertSee('今日は天気が良くて暖かいですね。お散歩日和です。');
    $response->assertDontSee('今日は雨が降っています。室内で勉強します。');
  }

  /**
   * スペースで区切った複数キーワードでのOR検索が正しく機能することを確認。
   */
  public function test_search_posts_by_multiple_keywords_or_mode(): void
  {
    $this->actingAs($this->user);
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/s5',
      'cid' => 'cids5',
      'text' => 'PHPの勉強中',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/s6',
      'cid' => 'cids6',
      'text' => 'Rubyを書いてみる',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now()->subHour(),
    ]);

    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/s7',
      'cid' => 'cids7',
      'text' => 'Pythonでお茶を濁す',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now()->subDays(2),
    ]);

    // 「PHP Ruby」でOR検索
    $response = $this->get("/@test.bsky.social?q=PHP+Ruby&mode=or");

    $response->assertStatus(200);
    $response->assertSee('PHPの勉強中');
    $response->assertSee('Rubyを書いてみる');
    $response->assertDontSee('Pythonでお茶を濁す');
  }

  /**
   * 検索結果のページネーションリンクに、検索クエリが引き継がれることを確認。
   */
  public function test_search_retains_query_parameters_in_pagination(): void
  {
    $this->actingAs($this->user);
    // ページネーションが発生するように大量の投稿を作成 (15件以上)
    for ($i = 1; $i <= 20; $i++) {
      Post::create([
        'user_id' => $this->user->id,
        'uri' => "at://did:plc:test123456/app.bsky.feed.post/page_{$i}",
        'cid' => "cid_page_{$i}",
        'text' => "検索テスト対象の投稿番号 {$i}",
        'raw_data' => ['post' => []],
        'posted_at' => Carbon::now()->subDays($i),
      ]);
    }

    $response = $this->get("/@test.bsky.social?q=検索テスト対象&mode=and");

    $response->assertStatus(200);
    // 2ページ目へのリンクに「q=検索テスト対象」と「mode=and」が含まれていることを検証
    $response->assertSee('q=%E6%A4%9C%E7%B4%A2%E3%83%86%E3%82%B9%E3%83%88%E5%AF%BE%E8%B1%A1'); // 「検索テスト対象」のURLエンコード
    $response->assertSee('mode=and');
  }

  /**
   * 手動同期が15分間に5回までに制限され、6回目で制限エラーになることを検証。
   */
  public function test_manual_sync_is_rate_limited_to_five_attempts(): void
  {
    $this->actingAs($this->user);

    // RateLimiterをクリア
    \Illuminate\Support\Facades\RateLimiter::clear('sync-user:' . $this->user->id);

    $blueskyService = \Mockery::mock(\App\Services\BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')->andReturn([
      'accessJwt' => 'mock_token',
      'did' => 'did:plc:test123456',
    ]);
    $blueskyService->shouldReceive('getProfile')->andReturn([
      'description' => 'テスト',
    ]);
    $blueskyService->shouldReceive('getAuthorFeed')->andReturn([
      'feed' => [],
      'cursor' => null,
    ]);
    $this->app->instance(\App\Services\BlueskyService::class, $blueskyService);

    // 5回までは正常に同期できること
    for ($i = 0; $i < 5; $i++) {
      $response = $this->post("/sync");
      $response->assertStatus(302);
      $response->assertSessionHas('success');
    }

    // 6回目はレート制限がかかること
    $response = $this->post("/sync");
    $response->assertStatus(302);
    $response->assertSessionHas('error');
    $this->assertStringContainsString('手動同期の回数上限を超えました', session('error'));
  }

  /**
   * 同期中の場合は手動同期リクエストが拒否されることを検証。
   */
  public function test_manual_sync_is_blocked_when_is_syncing_is_true(): void
  {
    $this->actingAs($this->user);

    // アカウントの状態を同期中に更新
    $this->account->update(['is_syncing' => true]);

    // 同期をリクエスト
    $response = $this->post("/sync");

    $response->assertStatus(302);
    $response->assertSessionHas('error');
    $this->assertStringContainsString('現在、自動同期または手動同期が実行中です', session('error'));
  }

  /**
   * ポストの作成時にハッシュタグが正しく抽出され、サイドバーに集計順に表示されることを検証。
   */
  public function test_hashtags_are_correctly_extracted_and_displayed_in_sidebar(): void
  {
    $this->actingAs($this->user);
    // ハッシュタグを含むテスト投稿を作成
    $post1 = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/tag_p1',
      'cid' => 'cid_tp1',
      'text' => '今日は #Laravel と #PHP の勉強！',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    $post2 = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/tag_p2',
      'cid' => 'cid_tp2',
      'text' => 'また #Laravel を書いています。楽しい。',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now()->subHour(),
    ]);

    // 中間テーブル post_tags に手動登録
    $tagLaravel = \App\Models\Tag::create(['name' => 'Laravel']);
    $tagPHP = \App\Models\Tag::create(['name' => 'PHP']);

    $post1->tags()->sync([$tagLaravel->id, $tagPHP->id]);
    $post2->tags()->sync([$tagLaravel->id]);

    // キャッシュをクリア
    \Illuminate\Support\Facades\Cache::forget('user-tags:' . $this->user->id);

    // カレンダーブログページを表示
    $response = $this->get("/@test.bsky.social");

    $response->assertStatus(200);
    // サイドバーのタグ一覧に表示されているか検証
    $response->assertSee('Laravel');
    $response->assertSee('PHP');
    
    // 頻度順での件数（Laravel: 2, PHP: 1）が表示されているか
    $response->assertSee('2');
    $response->assertSee('1');
  }

  /**
   * bluesky:sync-all コマンドが is_auto_sync_enabled === true のユーザーのみ同期ジョブを登録することを検証。
   */
  public function test_sync_all_command_only_syncs_users_with_auto_sync_enabled(): void
  {
    \Illuminate\Support\Facades\Bus::fake();

    // ユーザーA: 自動同期有効
    $userA = User::create([
      'name' => 'ユーザーA',
      'email' => 'usera@example.com',
      'password' => bcrypt('password'),
    ]);
    \App\Models\BlueskyAccount::create([
      'user_id' => $userA->id,
      'did' => 'did:plc:usera123',
      'handle' => 'usera.bsky.social',
      'encrypted_app_password' => 'secret_a',
      'is_private' => false,
      'is_auto_sync_enabled' => true, // 有効
    ]);

    // ユーザーB: 自動同期無効
    $userB = User::create([
      'name' => 'ユーザーB',
      'email' => 'userb@example.com',
      'password' => bcrypt('password'),
    ]);
    \App\Models\BlueskyAccount::create([
      'user_id' => $userB->id,
      'did' => 'did:plc:userb123',
      'handle' => 'userb.bsky.social',
      'encrypted_app_password' => 'secret_b',
      'is_private' => false,
      'is_auto_sync_enabled' => false, // 無効
    ]);

    // コマンドを実行
    $this->artisan('bluesky:sync-all')
      ->assertSuccessful();

    // ユーザーAのジョブはディスパッチされたことを検証
    \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\SyncBlueskyPostsJob::class, function ($job) use ($userA) {
      $refJob = new \ReflectionClass($job);
      $refProp = $refJob->getProperty('user');
      $refProp->setAccessible(true);
      return $refProp->getValue($job)->id === $userA->id;
    });

    // ユーザーBのジョブはディスパッチされなかったことを検証
    \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\SyncBlueskyPostsJob::class, function ($job) use ($userB) {
      $refJob = new \ReflectionClass($job);
      $refProp = $refJob->getProperty('user');
      $refProp->setAccessible(true);
      return $refProp->getValue($job)->id === $userB->id;
    });
  }

  /**
   * カレンダーAjax取得APIエンドポイントが、指定年月のカレンダーHTMLを正しく返すことを検証。
   */
  public function test_ajax_calendar_html_endpoint_returns_correct_html(): void
  {
    $this->actingAs($this->user);
    // 指定月の投稿を作成
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_ajax1',
      'cid' => 'cidajax1',
      'text' => 'Ajaxテスト投稿',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::create(2026, 8, 15, 12, 0, 0),
    ]);

    $response = $this->get("/@test.bsky.social/calendar-api/2026/08");

    $response->assertStatus(200);
    // カレンダーの月表示や、投稿日（15日）のリンクが含まれているか確認
    $response->assertSee('2026年');
    $response->assertSee('08月');
    // 15日の日付セルが投稿ありリンクとして出力されていることを検証
    $response->assertSee('/test.bsky.social/2026/08/15');
  }

  /**
   * 非公開アカウントの場合、カレンダーAjax取得APIもゲストを拒否することを検証。
   */
  public function test_ajax_calendar_html_endpoint_respects_private_status(): void
  {
    // 非公開アカウントに設定
    $this->account->update(['is_private' => true]);

    // ゲストアクセス -> 403 Forbidden
    $response = $this->get("/@test.bsky.social/calendar-api/2026/08");
    $response->assertStatus(403);

    // 本人アクセス -> 200 OK
    $response = $this->actingAs($this->user)->get("/@test.bsky.social/calendar-api/2026/08");
    $response->assertStatus(200);
  }

  /**
   * 全アーカイブ一覧ページがすべての月アーカイブと代表画像を美しく表示することを確認。
   */
  public function test_archives_page_displays_all_monthly_archives(): void
  {
    $this->actingAs($this->user);
    // 2026年5月の投稿を作成 (画像付きの代表投稿として模擬)
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/archive_post1',
      'cid' => 'cid_arch1',
      'text' => '2026年5月の投稿',
      'raw_data' => [
        'post' => [
          'embed' => [
            '$type' => 'app.bsky.embed.images#view',
            'images' => [
              ['thumb' => 'https://example.com/thumb5.jpg']
            ]
          ]
        ]
      ],
      'posted_at' => Carbon::create(2026, 5, 10, 12, 0, 0),
    ]);

    // 2025年12月の投稿を作成
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/archive_post2',
      'cid' => 'cid_arch2',
      'text' => '2025年12月の投稿',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::create(2025, 12, 25, 12, 0, 0),
    ]);

    $response = $this->get("/@test.bsky.social/archives");

    $response->assertStatus(200);
    $response->assertSee('月別アーカイブ');
    $response->assertSee('2026年');
    $response->assertSee('5'); // 5月
    $response->assertSee('2025年');
    $response->assertSee('12'); // 12月
    $response->assertSee('https://example.com/thumb5.jpg'); // 代表写真
  }

  /**
   * 非公開アカウントの場合、全アーカイブ一覧ページもゲストアクセスを拒否して404を返すことを確認。
   */
  public function test_archives_page_respects_private_status(): void
  {
    // 非公開アカウントに設定
    $this->account->update(['is_private' => true]);

    // ゲストアクセス -> 404
    $response = $this->get("/@test.bsky.social/archives");
    $response->assertStatus(404);

    // 本人アクセス -> 200 OK
    $response = $this->actingAs($this->user)->get("/@test.bsky.social/archives");
    $response->assertStatus(200);
  }

  /**
   * 全タグ一覧ページがすべてのハッシュタグと件数を美しく表示することを確認。
   */
  public function test_tags_page_displays_all_hashtags(): void
  {
    $this->actingAs($this->user);
    // タグを含む投稿を作成
    $post1 = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/tag_p1',
      'cid' => 'cid_tp1',
      'text' => '今日は #Laravel と #PHP の勉強！',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    $tagLaravel = \App\Models\Tag::create(['name' => 'Laravel']);
    $tagPHP = \App\Models\Tag::create(['name' => 'PHP']);

    $post1->tags()->sync([$tagLaravel->id, $tagPHP->id]);

    $response = $this->get("/@test.bsky.social/tags");

    $response->assertStatus(200);
    $response->assertSee('タグ一覧');
    $response->assertSee('Laravel');
    $response->assertSee('PHP');
    $response->assertSee('1 件'); // 件数表示
  }

  /**
   * 非公開アカウントの場合、全タグ一覧ページもゲストアクセスを拒否して404を返すことを確認。
   */
  public function test_tags_page_respects_private_status(): void
  {
    // 非公開アカウントに設定
    $this->account->update(['is_private' => true]);

    // ゲストアクセス -> 404
    $response = $this->get("/@test.bsky.social/tags");
    $response->assertStatus(404);

    // 本人アクセス -> 200 OK
    $response = $this->actingAs($this->user)->get("/@test.bsky.social/tags");
    $response->assertStatus(200);
  }

  /**
   * サイドバーに統計アクティビティカードが正しく表示されることを確認。
   */
  public function test_activity_stats_are_correctly_displayed_in_sidebar(): void
  {
    $this->actingAs($this->user);
    // 最古の投稿 (2025年5月10日)
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_old',
      'cid' => 'cid_old',
      'text' => '最古の投稿です',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::create(2025, 5, 10, 12, 0, 0),
    ]);

    // 最新の投稿 (本日)
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_new',
      'cid' => 'cid_new',
      'text' => '最新の投稿です',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    // 本家投稿数（posts_count）を 5件 に設定 (同期数 2 < 本家数 5 -> 未同期 3)
    $this->account->update([
      'posts_count' => 5,
      'registered_at' => null, // 最初は登録日なし(フォールバック検証)
    ]);

    // キャッシュをクリア
    \Illuminate\Support\Facades\Cache::forget('user-stats-summary:' . $this->user->id);

    $response = $this->get("/@test.bsky.social");

    $response->assertStatus(200);
    $response->assertSee('Stats');
    $response->assertSee('5'); // 本家ポスト数
    $response->assertSee('最古の記録日'); // 始まりの日 (フォールバック)
    $response->assertSee('2025年05月10日'); // 始まりの日 (フォールバック)

    // 登録日を設定して検証
    $this->account->update([
      'registered_at' => Carbon::create(2024, 10, 15, 12, 0, 0),
    ]);
    \Illuminate\Support\Facades\Cache::forget('user-stats-summary:' . $this->user->id);

    $response2 = $this->get("/@test.bsky.social");
    $response2->assertStatus(200);
    $response2->assertSee('Bluesky参加日');
    $response2->assertSee('2024年10月15日'); // 本物の登録日

    // 登録記念日の検証（タイムゾーンを揃えて本日の午前0時に設定し、確実に月日一致と満2年経過を両立）
    $this->account->update([
      'registered_at' => Carbon::now('Asia/Tokyo')->subYears(2)->startOfDay(), 
    ]);

    $response3 = $this->get("/@test.bsky.social");
    $response3->assertStatus(200);
    $response3->assertSee('Happy Anniv.');
    $response3->assertSee('Bluesky Anniversary');
    $response3->assertSee('2周年');

    // クエリパラメータ anniversary=1 を使ったデバッグモード検証
    $this->account->update([
      'registered_at' => Carbon::create(2020, 5, 5, 12, 0, 0), // 今日と異なる日
    ]);

    $response4 = $this->get("/@test.bsky.social?anniversary=1");
    $response4->assertStatus(200);
    $response4->assertSee('Happy Anniv.');
    $response4->assertSee('Bluesky Anniversary');
  }

  /**
   * デモアカウントに設定されたハンドル名に対しては、他者からであってもすべてのフル機能が閲覧・利用できることを検証。
   */
  public function test_demo_account_allows_full_access_for_others(): void
  {
    // test.bsky.social アカウントをデモハンドルに設定
    config(['services.bluesky.demo_handle' => 'test.bsky.social']);

    // 2026年5月の投稿を作成
    Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_demo_m1',
      'cid' => 'cid_demo_m1',
      'text' => 'デモ用の投稿',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::create(2026, 5, 15, 12, 0, 0),
    ]);

    // 未ログイン（他者）として、デモアカウントの各フル機能エンドポイントへリクエスト
    $responseShow = $this->get("/@test.bsky.social");
    $responseShow->assertStatus(200);
    $responseShow->assertSee('デモ体験ページのため、すべての機能（カレンダー・検索・統計など）が特別に解放されています。');
    $responseShow->assertSee('Stats'); // サイドバーが表示されていること

    $responseMonth = $this->get("/@test.bsky.social/2026/05");
    $responseMonth->assertStatus(200);
    $responseMonth->assertSee('2026年');
    $responseMonth->assertSee('5月のログ');

    $responseYear = $this->get("/@test.bsky.social/2026");
    $responseYear->assertStatus(200);
    $responseYear->assertSee('2026年');

    $responseArchives = $this->get("/@test.bsky.social/archives");
    $responseArchives->assertStatus(200);
    $responseArchives->assertSee('月別アーカイブ');

    $responseTags = $this->get("/@test.bsky.social/tags");
    $responseTags->assertStatus(200);
    $responseTags->assertSee('タグ一覧');
  }

  /**
   * 他者（ゲスト）が一般公開カレンダーを閲覧する際は noindex が出力されること、
   * および本人またはデモページを閲覧する際は noindex が出力されないことを検証。
   */
  public function test_noindex_tag_output_rules(): void
  {
    // 公開アカウントの場合 -> 他者、本人いずれも noindex が出力されないこと
    $this->account->update(['is_private' => false]);
    
    $responseGuest = $this->get("/@test.bsky.social");
    $responseGuest->assertStatus(200);
    $responseGuest->assertDontSee('<meta name="robots" content="noindex, nofollow">', false);

    $responseOwner = $this->actingAs($this->user)->get("/@test.bsky.social");
    $responseOwner->assertStatus(200);
    $responseOwner->assertDontSee('<meta name="robots" content="noindex, nofollow">', false);

    // 非公開アカウントの場合 -> ログイン本人のみアクセスでき、noindex が出力されること
    $this->account->update(['is_private' => true]);
    $responsePrivateOwner = $this->actingAs($this->user)->get("/@test.bsky.social");
    $responsePrivateOwner->assertStatus(200);
    $responsePrivateOwner->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    // 設定ページ（settings）の場合 -> アカウントの公開・非公開設定にかかわらず、常に noindex が出力されること
    $this->account->update(['is_private' => false]);
    $responseSettingsOpen = $this->actingAs($this->user)->get("/@test.bsky.social/settings");
    $responseSettingsOpen->assertStatus(200);
    $responseSettingsOpen->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    $this->account->update(['is_private' => true]);
    $responseSettingsPrivate = $this->actingAs($this->user)->get("/@test.bsky.social/settings");
    $responseSettingsPrivate->assertStatus(200);
    $responseSettingsPrivate->assertSee('<meta name="robots" content="noindex, nofollow">', false);
  }

  /**
   * 投稿内のURL（http/https または www. から始まるもの）が安全に自動リンク化されることを検証。
   */
  public function test_post_text_auto_linking(): void
  {
    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_link_1',
      'cid' => 'cidlink1',
      'text' => "お気に入りのショップはこちらです： www.palcloset.jp/display/item/1234/\n詳細はこちら: https://example.com/info 登録してね！\nこちら短い: https://standard.site あと http://google.com/",
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    $htmlText = $post->html_text;

    // www.palcloset.jp/... が https:// 付与で a タグ化され、ドメイン注釈 (palcloset.jp) が付いていること
    $this->assertStringContainsString('<a href="https://www.palcloset.jp/display/item/1234/" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">www.palcloset.jp/display/item/1234/</a> <span class="text-[9px] text-slate-500 font-bold hover:no-underline select-none">(palcloset.jp)</span>', $htmlText);

    // https://example.com/info がそのまま a タグ化され、ドメイン注釈 (example.com) が付いていること
    $this->assertStringContainsString('<a href="https://example.com/info" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">https://example.com/info</a> <span class="text-[9px] text-slate-500 font-bold hover:no-underline select-none">(example.com)</span>', $htmlText);

    // https://standard.site はリンクだけのとき（外部リンクカードなし）は、常にドメイン注釈 (standard.site) が付くこと
    $this->assertStringContainsString('<a href="https://standard.site" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">https://standard.site</a> <span class="text-[9px] text-slate-500 font-bold hover:no-underline select-none">(standard.site)</span>', $htmlText);

    // http://google.com/ にも同様にドメイン注釈 (google.com) が付くこと
    $this->assertStringContainsString('<a href="http://google.com/" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">http://google.com/</a> <span class="text-[9px] text-slate-500 font-bold hover:no-underline select-none">(google.com)</span>', $htmlText);

    // 通常の文字列がエスケープされていること
    $this->assertStringContainsString('お気に入りのショップはこちらです：', $htmlText);
  }

  /**
   * 省略されたURL文字列（例: app.foursquare.com/share/checki...）であっても、
   * raw_data内の facets メタデータを使用して正確に完全なURLへと自動リンク化されることを検証。
   */
  public function test_post_text_facets_linking_with_shortened_url(): void
  {
    $text = "チェックインしました！ app.foursquare.com/share/checki...";
    
    // 省略されたURLのバイト開始位置（UTF-8）を正確にモック
    // 「チェックインしました！」は日本語（3バイト文字）×11文字 = 33バイト
    // さらに半角スペース（1バイト）を含めて、URLの開始位置は34バイト。
    // 「app.foursquare.com/share/checki...」は34バイト。
    $byteStart = 34;
    $byteEnd = 68;

    $rawData = [
      'post' => [
        'record' => [
          'text' => $text,
          'facets' => [
            [
              'index' => [
                'byteStart' => $byteStart,
                'byteEnd' => $byteEnd,
              ],
              'features' => [
                [
                  '$type' => 'app.bsky.richtext.facet#link',
                  'uri' => 'https://app.foursquare.com/share/checkin/1234567890abcdef',
                ]
              ]
            ]
          ]
        ]
      ]
    ];

    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_link_facets',
      'cid' => 'cidlinkfacets',
      'text' => $text,
      'raw_data' => $rawData,
      'posted_at' => Carbon::now(),
    ]);

    $htmlText = $post->html_text;

    // 省略された表示テキスト（app.foursquare.com/share/checki...）のまま、
    // リンク先は完全なURL（https://app.foursquare.com/share/checkin/...）になり、
    // ドメイン注釈 (app.foursquare.com) が付加されていること
    $this->assertStringContainsString('<a href="https://app.foursquare.com/share/checkin/1234567890abcdef" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">app.foursquare.com/share/checki...</a> <span class="text-[9px] text-slate-500 font-bold hover:no-underline select-none">(app.foursquare.com)</span>', $htmlText);

    // 地の文が安全にエスケープされて出力されていること
    $this->assertStringContainsString('チェックインしました！', $htmlText);
  }

  /**
   * 投稿に外部リンクカードが存在する場合、本文中の対応するドメインリンクへの自動ドメイン注釈が
   * スキップ（非表示）され、重複表示が防止されることを検証。
   */
  public function test_post_text_linking_skips_domain_annotation_when_matching_external_card(): void
  {
    $text = "チェックインしました！ app.foursquare.com/share/checki...";
    $byteStart = 34;
    $byteEnd = 68;

    $rawData = [
      'post' => [
        'record' => [
          'text' => $text,
          'facets' => [
            [
              'index' => [
                'byteStart' => $byteStart,
                'byteEnd' => $byteEnd,
              ],
              'features' => [
                [
                  '$type' => 'app.bsky.richtext.facet#link',
                  'uri' => 'https://app.foursquare.com/share/checkin/1234567890abcdef',
                ]
              ]
            ]
          ]
        ],
        'embed' => [
          '$type' => 'app.bsky.embed.external#view',
          'external' => [
            'uri' => 'https://app.foursquare.com/share/checkin/1234567890abcdef',
            'title' => "Hisato Sakanoue's check in at Tokyo Station",
            'description' => "チェックインしました",
          ]
        ]
      ]
    ];

    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_link_facets_skip',
      'cid' => 'cidlinkfacetsskip',
      'text' => $text,
      'raw_data' => $rawData,
      'posted_at' => Carbon::now(),
    ]);

    $htmlText = $post->html_text;

    // リンクタグ自体は正しく生成されていること
    $this->assertStringContainsString('<a href="https://app.foursquare.com/share/checkin/1234567890abcdef" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">app.foursquare.com/share/checki...</a>', $htmlText);
    
    // 同一ドメインの外部リンクカードが紐づいているため、ドメイン注釈 (app.foursquare.com) が【付加されていない】こと
    $this->assertStringNotContainsString('(app.foursquare.com)', $htmlText);
  }

  /**
   * 1つのポスト内に複数のURLが存在する場合に、外部リンクカードのドメインと一致する方の
   * ドメイン注釈のみがスキップされ、一致しない他のドメインの注釈は正しく表示されたままであることを検証。
   */
  public function test_post_text_linking_with_multiple_urls_skips_only_matching_external_card_domain(): void
  {
    // 本文中に2つのURLが含まれる
    // 1. https://standard.site/long-path (カードドメインと一致するため注釈はスキップされるべき)
    // 2. https://substack.com/app (カードドメインと一致しないため (substack.com) 注釈が表示されるべき)
    $text = "リンク2個: https://standard.site/long-path と https://substack.com/app です。";

    $rawData = [
      'post' => [
        'record' => [
          'text' => $text,
        ],
        'embed' => [
          '$type' => 'app.bsky.embed.external#view',
          'external' => [
            'uri' => 'https://standard.site/long-path',
            'title' => "Standard.site - One schema",
            'description' => "Standard.site provides shared lexicons",
          ]
        ]
      ]
    ];

    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_link_multiple_skip',
      'cid' => 'cidlinkmultipleskip',
      'text' => $text,
      'raw_data' => $rawData,
      'posted_at' => Carbon::now(),
    ]);

    $htmlText = $post->html_text;

    // 1. https://standard.site/long-path のリンクは生成されているが、ドメイン注釈 (standard.site) は【付加されていない】こと
    $this->assertStringContainsString('<a href="https://standard.site/long-path" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">https://standard.site/long-path</a>', $htmlText);
    $this->assertStringNotContainsString('(standard.site)', $htmlText);

    // 2. https://substack.com/app のリンクには、ドメイン注釈 (substack.com) が【正しく付加されている】こと
    $this->assertStringContainsString('<a href="https://substack.com/app" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">https://substack.com/app</a> <span class="text-[9px] text-slate-500 font-bold hover:no-underline select-none">(substack.com)</span>', $htmlText);
  }

  /**
   * 日付をまたぐセルフスレッド（例: 親投稿が5月28日、子投稿が5月29日）の場合でも、
   * スレッド全体が親投稿の投稿日（5月28日）のセクションに自動的にまとめてグループ化され、
   * 途切れることなく実線で結合されて表示されることを検証。
   */
  public function test_cross_day_threads_are_grouped_together_under_root_post_date(): void
  {
    $date1 = Carbon::create(2026, 5, 28, 23, 50, 0); // 5月28日の親投稿
    $date2 = Carbon::create(2026, 5, 29, 0, 10, 0);  // 5月29日の子投稿

    // 親投稿の作成
    $parentPost = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/parent_post_cross',
      'cid' => 'cidparentcross',
      'text' => "親投稿（5月28日）",
      'raw_data' => [
        'post' => [
          'uri' => 'at://did:plc:test123456/app.bsky.feed.post/parent_post_cross',
          'cid' => 'cidparentcross',
          'author' => ['did' => $this->user->blueskyAccount->did],
          'record' => ['text' => "親投稿（5月28日）"]
        ]
      ],
      'posted_at' => $date1,
    ]);

    // 子投稿（翌日・日付またぎスレッド）の作成
    $childPost = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/child_post_cross',
      'cid' => 'cidchildcross',
      'text' => "子投稿（5月29日・返信）",
      'raw_data' => [
        'post' => [
          'uri' => 'at://did:plc:test123456/app.bsky.feed.post/child_post_cross',
          'cid' => 'cidchildcross',
          'author' => ['did' => $this->user->blueskyAccount->did],
          'record' => ['text' => "子投稿（5月29日・返信）"]
        ],
        'reply' => [
          'parent' => [
            'uri' => 'at://did:plc:test123456/app.bsky.feed.post/parent_post_cross',
            'author' => ['did' => $this->user->blueskyAccount->did]
          ],
          'root' => [
            'uri' => 'at://did:plc:test123456/app.bsky.feed.post/parent_post_cross',
            'author' => ['did' => $this->user->blueskyAccount->did]
          ]
        ]
      ],
      'posted_at' => $date2,
    ]);

    // ログインしてカレンダーページを表示
    $response = $this->actingAs($this->user)
      ->get(route('calendar.show', ['handle' => $this->user->blueskyAccount->handle]));

    $response->assertStatus(200);

    // 両方の投稿が表示されていること
    $response->assertSee("親投稿（5月28日）");
    $response->assertSee("子投稿（5月29日・返信）");

    // 日付ヘッダーとして「2026年05月28日」が存在し、翌日の日付「2026年05月29日」は日付ヘッダーとして表示されないこと
    // （5月29日の子投稿が5月28日のセクションにグループ化されているため）
    $response->assertSee("2026年05月28日");
    $response->assertDontSee("2026年05月29日 (");
  }

  /**
   * 投稿内のハッシュタグが、所有者本人・デモアカウントと、ゲストユーザーで適切に出し分けられて自動リンク化されることを検証。
   */
  public function test_post_text_hashtag_linking_only_for_owner_or_demo(): void
  {
    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/post_tag_linking_1',
      'cid' => 'cidtaglink1',
      'text' => "今日は、 #Laravel の日！詳細はこちら: https://example.com/info #PHP も楽しいね！",
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    // 1. ゲストユーザー（非ログイン）が閲覧したとき
    \Illuminate\Support\Facades\Auth::logout();

    $htmlGuest = $post->html_text;
    
    // URLは自動リンク化されていること
    $this->assertStringContainsString('href="https://example.com/info"', $htmlGuest);
    
    // ハッシュタグはBluesky公式検索（別タブ）リンクに自動リンク化されていること
    $this->assertStringContainsString('<a href="https://bsky.app/search?q=%23Laravel" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">#Laravel</a>', $htmlGuest);
    $this->assertStringContainsString('<a href="https://bsky.app/search?q=%23PHP" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">#PHP</a>', $htmlGuest);
    
    // ローカル検索リンクは含まれていないこと
    $this->assertStringNotContainsString('/@test.bsky.social?q=%23Laravel', $htmlGuest);

    // 2. 所有者本人ログイン時の閲覧
    $this->actingAs($this->user);
    $htmlOwner = $post->html_text;

    // URLは自動リンク化されていること
    $this->assertStringContainsString('href="https://example.com/info"', $htmlOwner);
    
    // ハッシュタグは同一タブのローカル検索リンクに自動リンク化されていること
    $expectedUrlLaravel = route('calendar.show', ['handle' => $this->user->blueskyAccount->handle]) . '?q=' . urlencode('#Laravel');
    $expectedUrlPHP = route('calendar.show', ['handle' => $this->user->blueskyAccount->handle]) . '?q=' . urlencode('#PHP');
    
    $this->assertStringContainsString('<a href="' . $expectedUrlLaravel . '" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">#Laravel</a>', $htmlOwner);
    $this->assertStringContainsString('<a href="' . $expectedUrlPHP . '" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">#PHP</a>', $htmlOwner);
    
    // 外部リンク（target="_blank"）やBluesky検索は含まれていないこと
    $this->assertStringNotContainsString('https://bsky.app/search?q=%23Laravel', $htmlOwner);

    // 3. デモアカウント閲覧時 (非ログインでも、デモハンドル設定されていればローカル検索に化けること)
    \Illuminate\Support\Facades\Auth::logout();
    config(['services.bluesky.demo_handle' => 'test.bsky.social']);

    $htmlDemo = $post->html_text;
    
    // デモアカウント扱いなので、ローカル検索リンクになっていること
    $this->assertStringContainsString('<a href="' . $expectedUrlLaravel . '" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer">#Laravel</a>', $htmlDemo);
    $this->assertStringNotContainsString('https://bsky.app/search?q=%23Laravel', $htmlDemo);
  }

  /**
   * ハッシュタグ検索が LIKE 部分一致ではなく tags リレーションを通じて厳密に実行されることを検証。
   * ハッシュタグとして検知され登録されている投稿のみがヒットし、
   * ハッシュマークはあるがスペース区切り等の要件を満たさず検知されていないノイズ投稿が除外されることをアサート。
   * ハッシュタグと通常キーワードが混在した複合検索や複数ハッシュタグの検索も検証。
   */
  public function test_search_posts_by_hashtag_strictly_filters_only_tagged_posts(): void
  {
    $this->actingAs($this->user);

    // 1. ハッシュタグが正常検知され、tags に登録されている投稿
    $post1 = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/tag_strict_1',
      'cid' => 'cidstrict1',
      'text' => "ハッシュタグテスト #sklog",
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);
    $tagSklog = \App\Models\Tag::create(['name' => 'sklog']);
    $post1->tags()->sync([$tagSklog->id]);

    // 2. ハッシュタグが正常検知され、かつ「勉強」という単語を含む投稿
    $post2 = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/tag_strict_2',
      'cid' => 'cidstrict2',
      'text' => "#sklog の勉強中！",
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now()->subMinutes(10),
    ]);
    $post2->tags()->sync([$tagSklog->id]);

    // 3. 文面中に #sklog は含まれるが、前方にスペースがなくハッシュタグ登録されていない投稿（ノイズ投稿）
    $postNoise = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/tag_strict_noise',
      'cid' => 'cidstrictnoise',
      'text' => "公式は句読点のあと、#sklog ハッシュって判定してんの？勉強になります。",
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now()->subMinutes(20),
    ]);

    // A. 単一のハッシュタグ検索 ("#sklog" もしくは大文字混ざりの "#SkLoG")
    // 大文字小文字の差異があっても、リレーションで同一タグと見なされヒットすることを検証
    $responseA = $this->get("/@test.bsky.social?q=%23SkLoG");
    $responseA->assertStatus(200);
    $responseA->assertSee('ハッシュタグテスト');
    $responseA->assertSee('#sklog');
    $responseA->assertSee('の勉強中！');
    $responseA->assertSee('ハッシュタグ検索'); // 新設バッジの検証
    // ハッシュタグ登録されていないノイズ投稿は、テキスト中に "#sklog" があっても完全に除外されていること
    $responseA->assertDontSee('公式は句読点のあと、#sklog ハッシュって判定してんの？');

    // B. ハッシュタグと通常語句の複合AND検索 ("#sklog 勉強")
    $responseB = $this->get("/@test.bsky.social?q=%23sklog+%E5%8B%89%E5%BC%B7&mode=and");
    $responseB->assertStatus(200);
    // 「#sklog が登録されており、かつ『勉強』を含む」投稿のみがヒットすること
    $responseB->assertSee('#sklog');
    $responseB->assertSee('の勉強中！');
    $responseB->assertSee('ハッシュタグ ＋ キーワード'); // 新設バッジの検証
    // 「#sklog は登録されているが『勉強』を含まない」投稿は除外されていること
    $responseB->assertDontSee('ハッシュタグテスト');
    // 「『勉強』を含んでいるが #sklog は登録されていない」投稿は除外されていること
    $responseB->assertDontSee('公式は句読点のあと、#sklog ハッシュって判定してんの？');
  }

  /**
   * ログイン中のカレンダー所有者本人が、ポストをローカルで削除（論理削除）できることを検証。
   * Bluesky上にポストが存在する場合は、論理削除（ソフトデリート）され、Undo可能なポストIDがセッションにセットされること。
   */
  public function test_owner_can_delete_post_locally(): void
  {
    $this->actingAs($this->user);

    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/to_delete_1',
      'cid' => 'ciddelete1',
      'text' => '削除される予定のローカルつぶやき',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    // BlueskyService のモック (存在する想定)
    $blueskyService = \Mockery::mock(\App\Services\BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with($this->account->handle, $this->account->encrypted_app_password)
      ->andReturn([
        'accessJwt' => 'mock_jwt_token',
        'did' => $this->account->did,
      ]);
    $blueskyService->shouldReceive('getPosts')
      ->once()
      ->with([$post->uri], 'mock_jwt_token')
      ->andReturn([
        'posts' => [
          ['uri' => $post->uri]
        ]
      ]);
    $this->app->instance(\App\Services\BlueskyService::class, $blueskyService);

    // 削除前の確認
    $responseBefore = $this->get("/test.bsky.social");
    $responseBefore->assertStatus(200);
    $responseBefore->assertSee('削除される予定のローカルつぶやき');

    // 削除リクエストの送信
    $response = $this->delete("/test.bsky.social/posts/{$post->id}/delete");
    $response->assertStatus(302); // リダイレクト
    $response->assertSessionHas('success');
    $response->assertSessionHas('deleted_post_id', $post->id);

    // 削除後の確認（DBには論理削除で残るが、取得・表示されなくなる）
    $this->assertSoftDeleted('posts', ['id' => $post->id]);

    $responseAfter = $this->get("/test.bsky.social");
    $responseAfter->assertStatus(200);
    $responseAfter->assertDontSee('削除される予定のローカルつぶやき');
  }

  /**
   * ポスト削除時、該当のポストがすでにBluesky上で削除されていた場合、物理削除されることを検証。
   */
  public function test_owner_delete_post_locally_force_deletes_when_missing_on_bluesky(): void
  {
    $this->actingAs($this->user);

    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/to_delete_missing',
      'cid' => 'ciddeletemissing',
      'text' => 'すでにBluesky上で消えているつぶやき',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    // BlueskyService のモック (存在しない想定)
    $blueskyService = \Mockery::mock(\App\Services\BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with($this->account->handle, $this->account->encrypted_app_password)
      ->andReturn([
        'accessJwt' => 'mock_jwt_token',
        'did' => $this->account->did,
      ]);
    $blueskyService->shouldReceive('getPosts')
      ->once()
      ->with([$post->uri], 'mock_jwt_token')
      ->andReturn([
        'posts' => [] // 空配列を返す
      ]);
    $this->app->instance(\App\Services\BlueskyService::class, $blueskyService);

    // 削除リクエストの送信
    $response = $this->delete("/test.bsky.social/posts/{$post->id}/delete");
    $response->assertStatus(302);
    $response->assertSessionHas('success');
    $response->assertSessionMissing('deleted_post_id'); // Undo不可

    // 物理削除されているため、DBに存在しないことをアサート
    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
  }

  /**
   * ポスト削除時、Bluesky APIへの通信（認証やデータ取得）でエラーが発生した場合は、削除が中断されることを検証。
   */
  public function test_owner_delete_post_fails_when_bluesky_api_errors(): void
  {
    $this->actingAs($this->user);

    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/to_delete_error',
      'cid' => 'ciddeleteerror',
      'text' => '通信エラー時のつぶやき',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    // BlueskyService のモック (認証で例外をスロー)
    $blueskyService = \Mockery::mock(\App\Services\BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with($this->account->handle, $this->account->encrypted_app_password)
      ->andThrow(new \Exception('Connection timeout'));
    $this->app->instance(\App\Services\BlueskyService::class, $blueskyService);

    // 削除リクエストの送信
    $response = $this->delete("/test.bsky.social/posts/{$post->id}/delete");
    $response->assertStatus(302);
    $response->assertSessionHas('error');
    $this->assertStringContainsString('Blueskyとの通信に失敗したため、削除処理を中断しました', session('error'));

    // DB上から削除されずに残っていることをアサート
    $this->assertDatabaseHas('posts', [
      'id' => $post->id,
      'deleted_at' => null, // 削除されていない
    ]);
  }

  /**
   * 未ログイン（ゲスト）や他ユーザーがポストの削除を要求した場合、403 Forbidden になることを検証。
   */
  public function test_guest_cannot_delete_post(): void
  {
    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/to_delete_2',
      'cid' => 'ciddelete2',
      'text' => '他人のつぶやき',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    // 1. 未ログインゲストからの削除リクエスト -> 302 リダイレクト（Authミドルウェアによるログイン画面遷移）
    $responseGuest = $this->delete("/@test.bsky.social/posts/{$post->id}/delete");
    $responseGuest->assertStatus(302);
    $responseGuest->assertRedirect(route('login'));
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);

    // 2. 他ユーザーからの削除リクエスト -> 403 Forbidden
    $otherUser = User::create([
      'name' => '別ユーザー',
      'email' => 'other_user@example.com',
      'password' => bcrypt('password'),
    ]);
    
    $responseOther = $this->actingAs($otherUser)->delete("/@test.bsky.social/posts/{$post->id}/delete");
    $responseOther->assertStatus(403);
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
  }

  /**
   * ローカルで削除されたポストが、同期ジョブ実行時に再インポートされて復活しないことを検証。
   */
  public function test_deleted_post_is_not_reimported_on_sync(): void
  {
    $this->actingAs($this->user);

    $uri = 'at://did:plc:test123456/app.bsky.feed.post/to_delete_and_sync';

    // 1. 最初は投稿を作成して論理削除する
    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => $uri,
      'cid' => 'cidsyncdelete',
      'text' => '削除後に同期しても復活しないテスト投稿',
      'raw_data' => [
        'post' => [
          'uri' => $uri,
          'cid' => 'cidsyncdelete',
          'author' => ['did' => 'did:plc:test123456'],
          'record' => [
            'text' => '削除後に同期しても復活しないテスト投稿',
            'createdAt' => Carbon::now()->toIso8601String()
          ]
        ]
      ],
      'posted_at' => Carbon::now(),
    ]);

    $post->delete(); // 論理削除

    // 2. 同期ジョブを実行して、削除した投稿と同じ feed アイテムを模擬
    $feedItem = [
      'post' => [
        'uri' => $uri,
        'cid' => 'cidsyncdelete',
        'author' => [
          'did' => 'did:plc:test123456',
          'handle' => 'test.bsky.social',
        ],
        'record' => [
          'text' => '削除後に同期しても復活しないテスト投稿',
          'createdAt' => Carbon::now()->toIso8601String()
        ]
      ]
    ];

    $blueskyService = \Mockery::mock(\App\Services\BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')->andReturn([
      'accessJwt' => 'mock_token',
      'did' => 'did:plc:test123456',
    ]);
    $blueskyService->shouldReceive('getProfile')->andReturn([
      'did' => 'did:plc:test123456',
      'postsCount' => 1,
    ]);
    $blueskyService->shouldReceive('getAuthorFeed')->andReturn([
      'feed' => [$feedItem], // 削除されたものと同じ feed アイテムを返す
      'cursor' => null,
    ]);
    $this->app->instance(\App\Services\BlueskyService::class, $blueskyService);

    // 同期処理の実行
    $job = new \App\Jobs\SyncBlueskyPostsJob($this->user);
    $job->handle($blueskyService);

    // 論理削除されたままである（復活していない）ことをアサート
    $this->assertSoftDeleted('posts', ['id' => $post->id]);
    
    // カレンダー画面上でも表示されないことを確認
    $response = $this->get("/@test.bsky.social");
    $response->assertStatus(200);
    $response->assertDontSee('削除後に同期しても復活しないテスト投稿');
  }

  /**
   * 削除されたポストを所有者本人が復元できることを検証。
   */
  public function test_owner_can_restore_deleted_post(): void
  {
    $this->actingAs($this->user);

    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/to_restore_1',
      'cid' => 'cidrestore1',
      'text' => '復元される予定のつぶやき',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    $post->delete();
    $this->assertSoftDeleted('posts', ['id' => $post->id]);

    // 復元リクエストの送信
    $response = $this->post("/@test.bsky.social/posts/{$post->id}/restore");
    $response->assertStatus(302);
    $response->assertSessionHas('success');

    // DBで復元されていることをアサート
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);

    // カレンダー画面に再度表示されていることをアサート
    $responseAfter = $this->get("/@test.bsky.social");
    $responseAfter->assertStatus(200);
    $responseAfter->assertSee('復元される予定のつぶやき');
  }

  /**
   * 未ログインまたは他者が復元を試みた場合、拒否されることを検証。
   */
  public function test_guest_cannot_restore_deleted_post(): void
  {
    $post = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/to_restore_2',
      'cid' => 'cidrestore2',
      'text' => '他人の復元待ちつぶやき',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);

    $post->delete();

    // 1. 未ログインゲスト -> 302 リダイレクト
    $responseGuest = $this->post("/@test.bsky.social/posts/{$post->id}/restore");
    $responseGuest->assertStatus(302);
    $this->assertSoftDeleted('posts', ['id' => $post->id]);

    // 2. 他ユーザー -> 403
    $otherUser = User::create([
      'name' => '別ユーザー2',
      'email' => 'other_user2@example.com',
      'password' => bcrypt('password'),
    ]);
    $responseOther = $this->actingAs($otherUser)->post("/@test.bsky.social/posts/{$post->id}/restore");
    $responseOther->assertStatus(403);
    $this->assertSoftDeleted('posts', ['id' => $post->id]);
  }

  /**
   * 設定画面のアクセス制限を検証。
   */
  public function test_settings_access_control(): void
  {
    // 1. 未ログインゲスト -> 302 リダイレクト
    $responseGuest = $this->get("/@test.bsky.social/settings");
    $responseGuest->assertStatus(302);

    // 2. 他ユーザー -> 403
    $otherUser = User::create([
      'name' => '別ユーザー3',
      'email' => 'other_user3@example.com',
      'password' => bcrypt('password'),
    ]);
    $responseOther = $this->actingAs($otherUser)->get("/@test.bsky.social/settings");
    $responseOther->assertStatus(403);

    // 3. 本人ログイン時 -> 200
    $responseOwner = $this->actingAs($this->user)->get("/@test.bsky.social/settings");
    $responseOwner->assertStatus(200);
    $responseOwner->assertSee('特定のポストを取り込む');
  }

  /**
   * 複数URLから個別インポート（復元と新規取得）が一括で実行できることを検証。
   */
  public function test_owner_can_import_multiple_posts_via_urls(): void
  {
    $this->actingAs($this->user);

    // 1. 既存でソフトデリートされたポストを用意 (復元テスト用)
    $restoredUri = 'at://did:plc:test123456/app.bsky.feed.post/to_import_restore';
    $postToRestore = Post::create([
      'user_id' => $this->user->id,
      'uri' => $restoredUri,
      'cid' => 'cidimportrestore',
      'text' => '復元されるインポートつぶやき',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now(),
    ]);
    $postToRestore->delete();

    // 2. 新規にAPI経由でインポートされるポストを用意 (新規テスト用)
    $newUri = 'at://did:plc:test123456/app.bsky.feed.post/to_import_new';
    $newPostData = [
      'uri' => $newUri,
      'cid' => 'cidimportnew',
      'record' => [
        'text' => '新しくインポートされるつぶやき #ハッシュタグ',
        'createdAt' => Carbon::now()->toIso8601String(),
      ]
    ];

    // BlueskyService のモック作成
    $blueskyService = \Mockery::mock(\App\Services\BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')->andReturn([
      'accessJwt' => 'mock_token_import',
      'did' => 'did:plc:test123456',
    ]);
    // 新規分だけが getPosts に渡されるはず
    $blueskyService->shouldReceive('getPosts')
      ->with([$newUri], 'mock_token_import')
      ->andReturn([
        'posts' => [$newPostData]
      ]);
    $this->app->instance(\App\Services\BlueskyService::class, $blueskyService);

    // インポート実行リクエスト
    $urlsInput = "https://bsky.app/profile/test.bsky.social/post/to_import_restore\n" . 
                 "https://bsky.app/profile/test.bsky.social/post/to_import_new\n" .
                 "https://bsky.app/profile/other.bsky.social/post/invalid"; // 他人のURL（除外対象）

    $response = $this->post("/@test.bsky.social/settings/import-posts", [
      'urls' => $urlsInput,
    ]);

    $response->assertStatus(302);
    $response->assertSessionHas('success');

    // ソフトデリートされていた投稿が復元されていること
    $this->assertDatabaseHas('posts', ['id' => $postToRestore->id, 'deleted_at' => null]);

    // 新規投稿がDBに追加されていること
    $this->assertDatabaseHas('posts', [
      'uri' => $newUri,
      'text' => '新しくインポートされるつぶやき #ハッシュタグ',
      'deleted_at' => null
    ]);

    // 新規投稿のタグが生成・紐付けられていること
    $newPost = Post::where('uri', $newUri)->first();
    $this->assertTrue($newPost->tags()->where('name', 'ハッシュタグ')->exists());
  }

  /**
   * 画像付きポストのみの絞り込み機能（media=1）を検証。
   */
  public function test_owner_can_filter_posts_by_media(): void
  {
    $this->actingAs($this->user);

    // 1. 画像ありポストを作成
    $postWithImage = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/with_image',
      'cid' => 'cidimage1',
      'text' => '画像があるよ',
      'raw_data' => [
        'post' => [
          'embed' => [
            '$type' => 'app.bsky.embed.images',
            'images' => [
              [
                'thumb' => 'https://example.com/thumb.jpg',
                'fullsize' => 'https://example.com/full.jpg',
                'alt' => 'テスト画像'
              ]
            ]
          ]
        ]
      ],
      'posted_at' => Carbon::now(),
    ]);

    // 2. 画像なしテキストのみポストを作成
    $postTextOnly = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'at://did:plc:test123456/app.bsky.feed.post/text_only',
      'cid' => 'cidtextonly1',
      'text' => 'テキストだけだよ',
      'raw_data' => ['post' => []],
      'posted_at' => Carbon::now()->subMinute(),
    ]);

    // 3. 画像ありリポスト（他人の投稿の共有）を作成
    $postRepostWithImage = Post::create([
      'user_id' => $this->user->id,
      'uri' => 'repost-12345',
      'cid' => 'cidrepost1',
      'text' => 'リポストした画像だよ',
      'raw_data' => [
        'reason' => [
          '$type' => 'app.bsky.feed.defs#reasonRepost',
        ],
        'post' => [
          'embed' => [
            '$type' => 'app.bsky.embed.images',
            'images' => [
              [
                'thumb' => 'https://example.com/repost-thumb.jpg',
                'fullsize' => 'https://example.com/repost-full.jpg',
                'alt' => 'リポスト画像'
              ]
            ]
          ]
        ]
      ],
      'posted_at' => Carbon::now()->subMinutes(2),
    ]);

    // 4. 通常状態でアクセス -> 投稿やリポストが表示されること
    $responseNormal = $this->get("/@test.bsky.social");
    $responseNormal->assertStatus(200);
    $responseNormal->assertSee('画像があるよ');
    $responseNormal->assertSee('テキストだけだよ');
    $responseNormal->assertSee('repost-thumb.jpg');

    // 5. 画像絞り込みを有効にしてアクセス -> 自投稿の画像ありのみ表示され、リポスト画像は除外されること
    $responseFiltered = $this->get("/@test.bsky.social?media=1");
    $responseFiltered->assertStatus(200);
    $responseFiltered->assertSee('画像があるよ');
    $responseFiltered->assertDontSee('テキストだけだよ');
    $responseFiltered->assertDontSee('repost-thumb.jpg');
    $responseFiltered->assertSee('data-fullsize="https://example.com/full.jpg"', false);
    $responseFiltered->assertSee('gallery-image-link');
  }

  public function test_owner_can_update_app_password_with_valid_credentials(): void
  {
    // 初期状態として、同期エラーを設定しておく
    $this->account->update([
      'sync_error_type' => 'auth_failed',
      'sync_error_message' => 'Old error',
      'auth_failure_count' => 2,
    ]);

    $blueskyService = \Mockery::mock(\App\Services\BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->with('test.bsky.social', 'new_valid_password')
      ->andReturn([
        'accessJwt' => 'mock_token',
        'did' => 'did:plc:test123456',
      ]);
    $this->app->instance(\App\Services\BlueskyService::class, $blueskyService);

    $response = $this->actingAs($this->user)
      ->post("/@test.bsky.social/settings/update-password", [
        'app_password' => 'new_valid_password',
      ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->account->refresh();
    $this->assertEquals('new_valid_password', $this->account->encrypted_app_password);
    $this->assertNull($this->account->sync_error_type);
    $this->assertNull($this->account->sync_error_message);
    $this->assertEquals(0, $this->account->auth_failure_count);
    $this->assertTrue($this->account->is_auto_sync_enabled);
  }

  public function test_owner_cannot_update_app_password_with_invalid_credentials(): void
  {
    $blueskyService = \Mockery::mock(\App\Services\BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')
      ->once()
      ->andThrow(new \Exception('Authentication failed'));
    $this->app->instance(\App\Services\BlueskyService::class, $blueskyService);

    $response = $this->actingAs($this->user)
      ->post("/@test.bsky.social/settings/update-password", [
        'app_password' => 'invalid_password',
      ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['app_password']);

    $this->account->refresh();
    $this->assertEquals('secret_app_pass', $this->account->encrypted_app_password); // 変更されていないこと
  }

  public function test_owner_can_delete_account_and_is_logged_out(): void
  {
    $response = $this->actingAs($this->user)
      ->post("/@test.bsky.social/settings/delete-account");

    $response->assertRedirect('/login');
    $response->assertSessionHas('success');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
    $this->assertDatabaseMissing('bluesky_accounts', ['id' => $this->account->id]);
  }

  public function test_guest_cannot_delete_other_account(): void
  {
    $otherUser = User::create([
      'name' => '他人',
      'email' => 'other@example.com',
      'password' => bcrypt('password'),
    ]);

    $response = $this->actingAs($otherUser)
      ->post("/@test.bsky.social/settings/delete-account");

    $response->assertStatus(403);
    $this->assertDatabaseHas('users', ['id' => $this->user->id]); // 削除されていないこと
  }

  public function test_calendar_page_ogp_meta_tags(): void
  {
    // 1. 公開アカウント時の OGP タグの検証
    $response = $this->get("/@test.bsky.social");
    $response->assertStatus(200);
    $response->assertSee('<meta property="og:title" content="テストユーザー のカレンダー | ', false);
    $response->assertSee('<meta property="og:description" content="テストプロフィールです', false);

    // 2. 非公開アカウント時の OGP マスクの検証
    $this->account->update(['is_private' => true]);
    
    // ゲスト状態でアクセス（非公開なので 403 / リダイレクトなどを期待するが、テストでの振る舞いを確認）
    // カレンダーはプライベート状態のとき、ゲストアクセスするとログイン画面にリダイレクトされる仕様。
    // そのため、所有者ログイン状態でアクセスしてOGPを見る。
    $responsePrivate = $this->actingAs($this->user)->get("/@test.bsky.social");
    $responsePrivate->assertStatus(200);
    $responsePrivate->assertSee('<meta property="og:title" content="非公開のカレンダー | ', false);
    $responsePrivate->assertSee('<meta property="og:description" content="このカレンダーは所有者本人にのみ公開されています。', false);
  }
}


