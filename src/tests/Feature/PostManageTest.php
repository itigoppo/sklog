<?php

namespace Tests\Feature;

use App\Models\BlueskyAccount;
use App\Models\Post;
use App\Models\User;
use App\Services\BlueskyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class PostManageTest extends TestCase
{
  use RefreshDatabase;

  private const MY_DID = 'did:plc:test123456';

  private const OTHER_DID = 'did:plc:other98765';

  private User $user;

  private BlueskyAccount $account;

  protected function setUp(): void
  {
    parent::setUp();

    $this->user = User::create([
      'name' => 'テストユーザー',
      'email' => 'test@example.com',
      'password' => bcrypt('password'),
    ]);

    $this->account = BlueskyAccount::create([
      'user_id' => $this->user->id,
      'did' => self::MY_DID,
      'handle' => 'test.bsky.social',
      'encrypted_app_password' => 'secret_app_pass',
      'is_private' => false,
    ]);

    config(['services.bluesky.demo_handle' => 'demo-account.bsky.social']);
  }

  /**
   * 自分の投稿を作ります。$parent を渡すと、その投稿への返信（連投の続き）にします。
   */
  private function makePost(string $rkey, string $text, Carbon $postedAt, ?Post $parent = null, ?Post $root = null): Post
  {
    $uri = 'at://'.self::MY_DID.'/app.bsky.feed.post/'.$rkey;
    $rawData = ['post' => ['uri' => $uri, 'author' => ['did' => self::MY_DID]]];

    if ($parent) {
      $rootUri = ($root ?? $parent)->uri;
      $rawData['reply'] = [
        'root' => ['uri' => $rootUri, 'author' => ['did' => self::MY_DID]],
        'parent' => ['uri' => $parent->uri, 'author' => ['did' => self::MY_DID]],
      ];
    }

    return Post::create([
      'user_id' => $this->user->id,
      'uri' => $uri,
      'cid' => 'cid-'.$rkey,
      'text' => $text,
      'raw_data' => $rawData,
      'posted_at' => $postedAt,
    ]);
  }

  /**
   * 他人の投稿をリポストしたものを作ります。
   */
  private function makeRepost(string $originalRkey, string $text, Carbon $postedAt): Post
  {
    return Post::create([
      'user_id' => $this->user->id,
      'uri' => 'repost-'.self::MY_DID.'-'.$originalRkey,
      'cid' => 'cid-repost-'.$originalRkey,
      'text' => $text,
      'raw_data' => [
        'post' => [
          'uri' => 'at://'.self::OTHER_DID.'/app.bsky.feed.post/'.$originalRkey,
          'author' => ['did' => self::OTHER_DID, 'handle' => 'other.bsky.social'],
        ],
        'reason' => ['$type' => 'app.bsky.feed.defs#reasonRepost'],
      ],
      'posted_at' => $postedAt,
    ]);
  }

  /**
   * 認証だけ通る BlueskyService のモックを登録して返します。
   */
  private function mockBluesky(): Mockery\MockInterface
  {
    $blueskyService = Mockery::mock(BlueskyService::class);
    $blueskyService->shouldReceive('authenticate')
      ->with($this->account->handle, $this->account->encrypted_app_password)
      ->andReturn(['accessJwt' => 'mock_jwt_token', 'did' => self::MY_DID]);
    $this->app->instance(BlueskyService::class, $blueskyService);

    return $blueskyService;
  }

  public function test_owner_sees_manage_page_with_thread_grouped_under_parent(): void
  {
    $this->actingAs($this->user);

    // 親は前月、続きは今月。今月の削除モードでも親のかたまりとして続きと一緒に並ぶ
    $parent = $this->makePost('parent', '親のつぶやき', Carbon::create(2026, 9, 30, 23, 50));
    $child = $this->makePost('child', '続きのつぶやき', Carbon::create(2026, 10, 1, 0, 5), $parent);
    $this->makePost('single', '単発のつぶやき', Carbon::create(2026, 10, 3, 12, 0));
    $this->makeRepost('orig1', 'リポストした他人の投稿', Carbon::create(2026, 10, 2, 9, 0));

    $response = $this->get('/profile/test.bsky.social/manage?period=2026-10');

    $response->assertStatus(200);
    $response->assertSee('削除モード');
    $response->assertSee('2026年10月');
    $response->assertSee('親のつぶやき');
    $response->assertSee('続きのつぶやき');
    $response->assertSee('単発のつぶやき');
    $response->assertDontSee('リポストした他人の投稿');
    // 続きは親を指している
    $response->assertSee('data-parent-uri="'.$parent->uri.'"', false);
    // かたまりは親の日付の見出しの下に入る
    $response->assertSeeInOrder(['2026年10月3日', '単発のつぶやき', '2026年9月30日', '親のつぶやき', '続きのつぶやき']);
    $this->assertNotNull($child);
  }

  public function test_reposts_tab_lists_only_reposts(): void
  {
    $this->actingAs($this->user);

    $this->makePost('mine', '自分の投稿', Carbon::create(2026, 10, 3, 12, 0));
    $this->makeRepost('orig1', 'リポストした他人の投稿', Carbon::create(2026, 10, 2, 9, 0));

    $response = $this->get('/profile/test.bsky.social/manage?tab=reposts');

    $response->assertStatus(200);
    $response->assertSee('リポストした他人の投稿');
    $response->assertSee('@other.bsky.social');
    $response->assertSee('Blueskyのリポストも取り消す');
    $response->assertDontSee('自分の投稿');
  }

  public function test_manage_page_applies_search_filter(): void
  {
    $this->actingAs($this->user);

    $this->makePost('hit', '今日はカレーを食べた', Carbon::create(2026, 10, 3, 12, 0));
    $this->makePost('miss', '今日はうどんを食べた', Carbon::create(2026, 10, 3, 13, 0));

    $response = $this->get('/profile/test.bsky.social/manage?q='.urlencode('カレー'));

    $response->assertStatus(200);
    $response->assertSee('今日はカレーを食べた');
    $response->assertDontSee('今日はうどんを食べた');
  }

  public function test_manage_page_is_only_for_owner(): void
  {
    // ゲストはログイン画面へ
    $this->get('/profile/test.bsky.social/manage')->assertRedirect(route('login'));

    // 他のユーザーは 404
    $other = User::create(['name' => '別ユーザー', 'email' => 'other@example.com', 'password' => bcrypt('password')]);
    BlueskyAccount::create([
      'user_id' => $other->id,
      'did' => self::OTHER_DID,
      'handle' => 'other.bsky.social',
      'encrypted_app_password' => 'other_pass',
    ]);
    $this->actingAs($other)->get('/profile/test.bsky.social/manage')->assertStatus(404);
  }

  public function test_calendar_pages_link_to_manage_page_for_owner_only(): void
  {
    $this->makePost('one', 'つぶやき', Carbon::create(2026, 10, 3, 12, 0));

    $this->get('/profile/test.bsky.social')->assertDontSee('削除モード');

    $this->actingAs($this->user);
    $this->get('/profile/test.bsky.social/2026/10')
      ->assertSee(route('calendar.manage', ['handle' => 'test.bsky.social', 'period' => '2026-10']), false);
    $this->get('/profile/test.bsky.social/2026/10/03')
      ->assertSee(route('calendar.manage', ['handle' => 'test.bsky.social', 'period' => '2026-10-03']), false);
  }

  public function test_owner_can_hide_posts_from_calendar_without_touching_bluesky(): void
  {
    $this->actingAs($this->user);

    $blueskyService = Mockery::mock(BlueskyService::class);
    $blueskyService->shouldNotReceive('authenticate');
    $this->app->instance(BlueskyService::class, $blueskyService);

    $post1 = $this->makePost('a', '消すつぶやき1', Carbon::now());
    $post2 = $this->makePost('b', '消すつぶやき2', Carbon::now());
    $keep = $this->makePost('c', '残すつぶやき', Carbon::now());

    $response = $this->delete('/profile/test.bsky.social/manage', ['post_ids' => [$post1->id, $post2->id]]);

    $response->assertStatus(302);
    $response->assertSessionHas('success');
    $this->assertStringContainsString('2件の投稿をカレンダーから非表示にしました', session('success'));
    $response->assertSessionHas('deleted_post_ids', [$post1->id, $post2->id]);
    $this->assertSoftDeleted('posts', ['id' => $post1->id]);
    $this->assertSoftDeleted('posts', ['id' => $post2->id]);
    $this->assertDatabaseHas('posts', ['id' => $keep->id, 'deleted_at' => null]);
  }

  public function test_undo_toast_restores_all_hidden_posts(): void
  {
    $this->actingAs($this->user);

    $post1 = $this->makePost('a', '戻すつぶやき1', Carbon::now());
    $post2 = $this->makePost('b', '戻すつぶやき2', Carbon::now());

    $this->delete('/profile/test.bsky.social/manage', ['post_ids' => [$post1->id, $post2->id]]);

    // 直後の画面に、2件を戻すフォームが出る
    $page = $this->get('/profile/test.bsky.social');
    $page->assertSee('元に戻す (Undo)');
    $page->assertSee('value="'.$post1->id.'"', false);
    $page->assertSee('value="'.$post2->id.'"', false);

    $response = $this->post('/profile/test.bsky.social/posts/restore', ['post_ids' => [$post1->id, $post2->id]]);

    $response->assertStatus(302);
    $response->assertSessionHas('success', '投稿の削除を取り消しました。');
    $this->assertDatabaseHas('posts', ['id' => $post1->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('posts', ['id' => $post2->id, 'deleted_at' => null]);
  }

  public function test_owner_can_delete_posts_on_bluesky_too(): void
  {
    $this->actingAs($this->user);

    $exists = $this->makePost('exists', 'Blueskyにあるつぶやき', Carbon::now());
    $gone = $this->makePost('gone', 'Blueskyで消えているつぶやき', Carbon::now());

    $blueskyService = $this->mockBluesky();
    $blueskyService->shouldReceive('getPosts')
      ->once()
      ->with([$exists->uri, $gone->uri], 'mock_jwt_token')
      ->andReturn(['posts' => [['uri' => $exists->uri]]]);
    // Bluesky 上にあるものだけを消しに行く
    $blueskyService->shouldReceive('deleteRecords')
      ->once()
      ->with(self::MY_DID, [['collection' => 'app.bsky.feed.post', 'rkey' => 'exists']], 'mock_jwt_token');

    $response = $this->delete('/profile/test.bsky.social/manage', [
      'post_ids' => [$exists->id, $gone->id],
      'also_bluesky' => '1',
    ]);

    $response->assertStatus(302);
    $this->assertStringContainsString('2件の投稿をBlueskyとカレンダーから削除しました', session('success'));
    $response->assertSessionMissing('deleted_post_ids'); // Undo不可
    $response->assertSessionMissing('error');
    $this->assertDatabaseMissing('posts', ['id' => $exists->id]);
    $this->assertDatabaseMissing('posts', ['id' => $gone->id]);
  }

  public function test_owner_can_undo_reposts_on_bluesky(): void
  {
    $this->actingAs($this->user);

    $reposted = $this->makeRepost('orig1', 'まだリポストしている投稿', Carbon::now());
    $already = $this->makeRepost('orig2', 'もう取り消してある投稿', Carbon::now());

    $blueskyService = $this->mockBluesky();
    $blueskyService->shouldReceive('getPosts')
      ->once()
      ->andReturn(['posts' => [
        [
          'uri' => 'at://'.self::OTHER_DID.'/app.bsky.feed.post/orig1',
          'viewer' => ['repost' => 'at://'.self::MY_DID.'/app.bsky.feed.repost/myrepost1'],
        ],
        [
          'uri' => 'at://'.self::OTHER_DID.'/app.bsky.feed.post/orig2',
          'viewer' => [],
        ],
      ]]);
    // 取り消すのは自分のリポストのレコード
    $blueskyService->shouldReceive('deleteRecords')
      ->once()
      ->with(self::MY_DID, [['collection' => 'app.bsky.feed.repost', 'rkey' => 'myrepost1']], 'mock_jwt_token');

    $response = $this->delete('/profile/test.bsky.social/manage', [
      'post_ids' => [$reposted->id, $already->id],
      'also_bluesky' => '1',
    ]);

    $response->assertStatus(302);
    $this->assertStringContainsString('2件のリポストをBlueskyとカレンダーから削除しました', session('success'));
    $this->assertDatabaseMissing('posts', ['id' => $reposted->id]);
    $this->assertDatabaseMissing('posts', ['id' => $already->id]);
  }

  public function test_nothing_is_deleted_when_bluesky_lookup_fails(): void
  {
    $this->actingAs($this->user);

    $post = $this->makePost('a', '通信エラー時のつぶやき', Carbon::now());

    $blueskyService = $this->mockBluesky();
    $blueskyService->shouldReceive('getPosts')->andThrow(new \Exception('Connection timeout'));
    $blueskyService->shouldNotReceive('deleteRecords');

    $response = $this->delete('/profile/test.bsky.social/manage', [
      'post_ids' => [$post->id],
      'also_bluesky' => '1',
    ]);

    $response->assertStatus(302);
    $this->assertStringContainsString('Blueskyとの通信に失敗したため、削除処理を中断しました', session('error'));
    $response->assertSessionMissing('success');
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
  }

  public function test_nothing_is_deleted_when_bluesky_delete_fails(): void
  {
    $this->actingAs($this->user);

    $post = $this->makePost('a', '削除失敗時のつぶやき', Carbon::now());

    $blueskyService = $this->mockBluesky();
    $blueskyService->shouldReceive('getPosts')->andReturn(['posts' => [['uri' => $post->uri]]]);
    $blueskyService->shouldReceive('deleteRecords')->andThrow(new \Exception('Rate limited'));

    $response = $this->delete('/profile/test.bsky.social/manage', [
      'post_ids' => [$post->id],
      'also_bluesky' => '1',
    ]);

    $response->assertStatus(302);
    $this->assertStringContainsString('Blueskyでの削除に失敗しました', session('error'));
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
  }

  public function test_others_cannot_delete_or_restore_posts(): void
  {
    $post = $this->makePost('a', '他人に消されないつぶやき', Carbon::now());

    // ゲストはログイン画面へ
    $this->delete('/profile/test.bsky.social/manage', ['post_ids' => [$post->id]])
      ->assertRedirect(route('login'));

    $other = User::create(['name' => '別ユーザー', 'email' => 'other@example.com', 'password' => bcrypt('password')]);
    BlueskyAccount::create([
      'user_id' => $other->id,
      'did' => self::OTHER_DID,
      'handle' => 'other.bsky.social',
      'encrypted_app_password' => 'other_pass',
    ]);

    // 他人のハンドルの画面は 403
    $this->actingAs($other)
      ->delete('/profile/test.bsky.social/manage', ['post_ids' => [$post->id]])
      ->assertStatus(403);

    // 自分のハンドルの画面でも、他人の投稿の id は対象にならない
    $this->actingAs($other)
      ->delete('/profile/other.bsky.social/manage', ['post_ids' => [$post->id]])
      ->assertSessionHas('error', '削除する投稿が見つかりませんでした。');

    $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);

    // 復元も同様
    $post->delete();
    $this->actingAs($other)
      ->post('/profile/test.bsky.social/posts/restore', ['post_ids' => [$post->id]])
      ->assertStatus(403);
    $this->actingAs($other)
      ->post('/profile/other.bsky.social/posts/restore', ['post_ids' => [$post->id]])
      ->assertSessionHas('error');
    $this->assertSoftDeleted('posts', ['id' => $post->id]);
  }

  public function test_delete_requires_selection(): void
  {
    $this->actingAs($this->user);

    $this->delete('/profile/test.bsky.social/manage', [])
      ->assertSessionHas('error', '削除する投稿を選んでください。');
  }

  public function test_bluesky_service_deletes_records_with_apply_writes(): void
  {
    Http::fake(['*' => Http::response([], 200)]);

    app(BlueskyService::class)->deleteRecords(self::MY_DID, [
      ['collection' => 'app.bsky.feed.post', 'rkey' => 'abc'],
      ['collection' => 'app.bsky.feed.repost', 'rkey' => 'def'],
    ], 'token');

    Http::assertSent(function (HttpRequest $request) {
      return str_ends_with($request->url(), 'com.atproto.repo.applyWrites')
        && $request->hasHeader('Authorization', 'Bearer token')
        && $request['repo'] === self::MY_DID
        && $request['writes'] === [
          ['$type' => 'com.atproto.repo.applyWrites#delete', 'collection' => 'app.bsky.feed.post', 'rkey' => 'abc'],
          ['$type' => 'com.atproto.repo.applyWrites#delete', 'collection' => 'app.bsky.feed.repost', 'rkey' => 'def'],
        ];
    });
  }
}
