<?php

namespace Database\Seeders;

use App\Models\BlueskyAccount;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 既存のテスト関連データを一括消去してクリーンに
        Post::query()->delete();
        BlueskyAccount::query()->delete();
        User::where('email', 'like', '%@example.com')->delete();

        // 各種日時の決定
        $now = Carbon::now()->timezone('Asia/Tokyo');
        $baseDate = $now->copy()->subDays(2)->startOfDay()->addHours(23)->addMinutes(50); // 一昨日の23:50
        $nextDate = $baseDate->copy()->addMinutes(20); // 昨日の00:10 (日付またぎ)

        // 仮アバターURLの定義 (Unsplashより)
        $testUserAvatar = 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&h=150&q=80';
        $privateUserAvatar = 'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?auto=format&fit=crop&w=150&h=150&q=80';
        $publicUserAvatar = 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=150&h=150&q=80';

        // 表示名の定義
        $testUserDisplayName = 'テストユーザー';
        $privateUserDisplayName = '非公開の知人';
        $publicUserDisplayName = '公開の友人';

        // ==========================================
        // 1. ユーザー＆アカウントの作成
        // ==========================================

        // メイン確認用テストユーザー (test-user)
        $user = User::create([
            'name' => $testUserDisplayName,
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $bskyAccount = BlueskyAccount::create([
            'user_id' => $user->id,
            'handle' => 'test-user.bsky.social',
            'did' => 'did:plc:testuser1234567890',
            'avatar_url' => $testUserAvatar,
            'description' => 'これは動作検証用のテストアカウントです。',
            'is_auto_sync_enabled' => true,
            'encrypted_app_password' => 'dummy_password',
            'last_synced_at' => $now,
        ]);

        // 非公開アカウント（返信・リポスト・引用テスト用の相手）
        $privateUser = User::create([
            'name' => $privateUserDisplayName,
            'email' => 'private@example.com',
            'password' => bcrypt('password'),
        ]);

        $privateAccount = BlueskyAccount::create([
            'user_id' => $privateUser->id,
            'handle' => 'private-boss.bsky.social',
            'did' => 'did:plc:privateboss111111',
            'avatar_url' => $privateUserAvatar,
            'description' => 'プライベートな非公開アカウントです。',
            'is_private' => true,
            'is_auto_sync_enabled' => false,
            'encrypted_app_password' => 'dummy_password',
            'last_synced_at' => $now,
        ]);

        // 公開アカウント（返信・リポスト・引用テスト用の相手）
        $publicUser = User::create([
            'name' => $publicUserDisplayName,
            'email' => 'public@example.com',
            'password' => bcrypt('password'),
        ]);

        $publicAccount = BlueskyAccount::create([
            'user_id' => $publicUser->id,
            'handle' => 'public-friend.bsky.social',
            'did' => 'did:plc:publicfriend22222',
            'avatar_url' => $publicUserAvatar,
            'description' => 'フレンドリーな公開アカウントです。',
            'is_private' => false,
            'is_auto_sync_enabled' => false,
            'encrypted_app_password' => 'dummy_password',
            'last_synced_at' => $now,
        ]);

        // ==========================================
        // 1.5 他のアカウントの元の投稿（Postモデルとして実際にDBへ構築）
        // ==========================================

        // 公開の友人の投稿 1 (通常のテキスト投稿)
        $friendPost1 = Post::create([
            'user_id' => $publicUser->id,
            'uri' => 'at://did:plc:publicfriend22222/app.bsky.feed.post/friend_post_1',
            'cid' => 'cidfriendpost1',
            'text' => '週末に開発者向けのオープンミートアップを開催します！',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:publicfriend22222/app.bsky.feed.post/friend_post_1',
                    'cid' => 'cidfriendpost1',
                    'likeCount' => 10,
                    'repostCount' => 2,
                    'author' => $this->createAuthorField($publicAccount, $publicUserDisplayName),
                    'record' => [
                        'text' => '週末に開発者向けのオープンミートアップを開催します！',
                        'createdAt' => $now->copy()->subDays(1)->subHours(3)->subMinutes(5)->toIso8601String(),
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(3)->subMinutes(5),
        ]);

        // 公開の友人の投稿 2 (テキスト投稿)
        $friendPost2 = Post::create([
            'user_id' => $publicUser->id,
            'uri' => 'at://did:plc:publicfriend22222/app.bsky.feed.post/friend_post_2',
            'cid' => 'cidfriendpost2',
            'text' => '生産性を倍にするための最新ショートカットキー一覧をまとめました！',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:publicfriend22222/app.bsky.feed.post/friend_post_2',
                    'cid' => 'cidfriendpost2',
                    'likeCount' => 45,
                    'repostCount' => 12,
                    'author' => $this->createAuthorField($publicAccount, $publicUserDisplayName),
                    'record' => [
                        'text' => '生産性を倍にするための最新ショートカットキー一覧をまとめました！',
                        'createdAt' => $now->copy()->subDays(1)->subHours(6)->subMinutes(5)->toIso8601String(),
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(6)->subMinutes(5),
        ]);

        // 公開の友人の投稿 3 (テキスト投稿)
        $friendPost3 = Post::create([
            'user_id' => $publicUser->id,
            'uri' => 'at://did:plc:publicfriend22222/app.bsky.feed.post/friend_post_3',
            'cid' => 'cidfriendpost3',
            'text' => '美味しいコーヒーの淹れ方について、徹底解説記事を書きました！ぜひご一読を。',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:publicfriend22222/app.bsky.feed.post/friend_post_3',
                    'cid' => 'cidfriendpost3',
                    'likeCount' => 120,
                    'repostCount' => 45,
                    'author' => $this->createAuthorField($publicAccount, $publicUserDisplayName),
                    'record' => [
                        'text' => '美味しいコーヒーの淹れ方について、徹底解説記事を書きました！ぜひご一読を。',
                        'createdAt' => $now->copy()->subDays(1)->subHours(7)->toIso8601String(),
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(7)->subMinutes(5),
        ]);

        // 公開の友人のピザ投稿 (画像付き)
        $friendPostPizza = Post::create([
            'user_id' => $publicUser->id,
            'uri' => 'at://did:plc:publicfriend22222/app.bsky.feed.post/friend_post_pizza',
            'cid' => 'cidfriendpizza',
            'text' => '今日のお昼は自家製窯焼きピザです！とろーりチーズが最高に美味しい。 #pizza',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:publicfriend22222/app.bsky.feed.post/friend_post_pizza',
                    'cid' => 'cidfriendpizza',
                    'likeCount' => 88,
                    'repostCount' => 12,
                    'author' => $this->createAuthorField($publicAccount, $publicUserDisplayName),
                    'record' => [
                        'text' => '今日のお昼は自家製窯焼きピザです！とろーりチーズが最高に美味しい。 #pizza',
                        'createdAt' => $now->copy()->subDays(1)->subHours(9)->toIso8601String(),
                    ],
                    'embed' => [
                        '$type' => 'app.bsky.embed.images#view',
                        'images' => [
                            [
                                'thumb' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=600&q=80',
                                'fullsize' => 'https://images.unsplash.com/photo-1513104890138-7c749659a591?auto=format&fit=crop&w=1200&q=80',
                                'alt' => '美味しそうなチーズピザ',
                            ],
                        ],
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(9),
        ]);

        // 公開の友人のハイキング投稿 (引用テスト用の画像付き投稿)
        $friendPostNature = Post::create([
            'user_id' => $publicUser->id,
            'uri' => 'at://did:plc:publicfriend22222/app.bsky.feed.post/friend_post_nature',
            'cid' => 'cidfriendnature',
            'text' => '今朝ハイキング中に撮った素晴らしい森と光のコントラストです。自然の力に癒やされます。 #nature #hiking',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:publicfriend22222/app.bsky.feed.post/friend_post_nature',
                    'cid' => 'cidfriendnature',
                    'likeCount' => 74,
                    'repostCount' => 5,
                    'author' => $this->createAuthorField($publicAccount, $publicUserDisplayName),
                    'record' => [
                        'text' => '今朝ハイキング中に撮った素晴らしい森と光のコントラストです。自然の力に癒やされます。 #nature #hiking',
                        'createdAt' => $now->copy()->subDays(1)->subHours(11)->toIso8601String(),
                    ],
                    'embed' => [
                        '$type' => 'app.bsky.embed.images#view',
                        'images' => [
                            [
                                'thumb' => 'https://images.unsplash.com/photo-1447752875215-b2761acb3c5d?auto=format&fit=crop&w=600&q=80',
                                'fullsize' => 'https://images.unsplash.com/photo-1447752875215-b2761acb3c5d?auto=format&fit=crop&w=1200&q=80',
                                'alt' => '光が差し込む森の小道',
                            ],
                        ],
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(11),
        ]);

        // 公開の友人のキーボード投稿 (外部リンクカード付き)
        $friendKeyboardPost = Post::create([
            'user_id' => $publicUser->id,
            'uri' => 'at://did:plc:publicfriend22222/app.bsky.feed.post/friend_post_keyboard',
            'cid' => 'cidfriendkeyboard',
            'text' => '新しいメカニカルキーボードを購入しました！打鍵感とRGBライティングが素晴らしいです。 https://keychron.com',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:publicfriend22222/app.bsky.feed.post/friend_post_keyboard',
                    'cid' => 'cidfriendkeyboard',
                    'likeCount' => 54,
                    'repostCount' => 2,
                    'author' => $this->createAuthorField($publicAccount, $publicUserDisplayName),
                    'record' => [
                        'text' => '新しいメカニカルキーボードを購入しました！打鍵感とRGBライティングが素晴らしいです。 https://keychron.com',
                        'createdAt' => $now->copy()->subDays(1)->subHours(12)->toIso8601String(),
                    ],
                    'embed' => [
                        '$type' => 'app.bsky.embed.external#view',
                        'external' => [
                            'uri' => 'https://keychron.com',
                            'title' => 'Keychron - Mechanical Keyboards for Mac, Windows and Phones',
                            'description' => 'Keychron designs wireless mechanical keyboards for various platforms, featuring premium hot-swappable keycaps.',
                            'thumb' => 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?auto=format&fit=crop&w=600&q=80',
                        ],
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(12),
        ]);

        // 非公開の知人の投稿
        $bossPost1 = Post::create([
            'user_id' => $privateUser->id,
            'uri' => 'at://did:plc:privateboss111111/app.bsky.feed.post/boss_post_1',
            'cid' => 'cidbosspost1',
            'text' => '【非公開アカウントの元の投稿】ログイン中のみこの内容と私のアイコンが見えます。',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:privateboss111111/app.bsky.feed.post/boss_post_1',
                    'cid' => 'cidbosspost1',
                    'likeCount' => 3,
                    'repostCount' => 0,
                    'author' => $this->createAuthorField($privateAccount, $privateUserDisplayName),
                    'record' => [
                        'text' => '【非公開アカウントの元の投稿】ログイン中のみこの内容と私のアイコンが見えます。',
                        'createdAt' => $now->copy()->subDays(1)->subHours(2)->subMinutes(5)->toIso8601String(),
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(2)->subMinutes(5),
        ]);

        $bossPost2 = Post::create([
            'user_id' => $privateUser->id,
            'uri' => 'at://did:plc:privateboss111111/app.bsky.feed.post/boss_post_2',
            'cid' => 'cidbosspost2',
            'text' => '【非公開の引用元】来月以降のクローズドなロードマップについて話し合っています。',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:privateboss111111/app.bsky.feed.post/boss_post_2',
                    'cid' => 'cidbosspost2',
                    'likeCount' => 5,
                    'repostCount' => 1,
                    'author' => $this->createAuthorField($privateAccount, $privateUserDisplayName),
                    'record' => [
                        'text' => '【非公開の引用元】来月以降のクローズドなロードマップについて話し合っています。',
                        'createdAt' => $now->copy()->subDays(1)->subHours(6)->subMinutes(5)->toIso8601String(),
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(6)->subMinutes(5),
        ]);

        $bossPost3 = Post::create([
            'user_id' => $privateUser->id,
            'uri' => 'at://did:plc:privateboss111111/app.bsky.feed.post/boss_post_3',
            'cid' => 'cidbosspost3',
            'text' => '【非公開のリポスト元】極秘会議のメモを関係者のみに共有しました。他者がこのページを見ると内容が非表示になり、リポスト主の名前もマスクされます。',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:privateboss111111/app.bsky.feed.post/boss_post_3',
                    'cid' => 'cidbosspost3',
                    'likeCount' => 2,
                    'repostCount' => 0,
                    'author' => $this->createAuthorField($privateAccount, $privateUserDisplayName),
                    'record' => [
                        'text' => '【非公開のリポスト元】極秘会議のメモを関係者のみに共有しました。他者がこのページを見ると内容が非表示になり、リポスト主の名前もマスクされます。',
                        'createdAt' => $now->copy()->subDays(1)->subHours(8)->toIso8601String(),
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(8)->subMinutes(5),
        ]);

        // ==========================================
        // 2. 基本的な投稿パターンのシード
        // ==========================================

        // 2-1. 通常のテキスト投稿 (1日前)
        Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/normal_1',
            'cid' => 'cidnormal1',
            'text' => 'ログカレンダーのテスト投稿です。非常にシンプルで美しいダークテーマのUIが確認できます。',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/normal_1',
                    'cid' => 'cidnormal1',
                    'likeCount' => 1,
                    'repostCount' => 0,
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => [
                        'text' => 'ログカレンダーのテスト投稿です。非常にシンプルで美しいダークテーマのUIが確認できます。',
                        'createdAt' => $now->copy()->subDays(1)->subHours(5)->toIso8601String(),
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(5),
        ]);

        // 2-1.5 ハッシュタグ付きの投稿 (1日前)
        $tagLaravel = Tag::firstOrCreate(['name' => 'Laravel']);
        $tagPHP = Tag::firstOrCreate(['name' => 'PHP']);
        $tagCoffee = Tag::firstOrCreate(['name' => 'coffee']);

        $taggedPost1 = Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/tagged_post_1',
            'cid' => 'cidtaggedpost1',
            'text' => '今日は朝から #coffee を飲みながら、#Laravel の新しいルーティング設計を考えています。生産性が高い！',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/tagged_post_1',
                    'cid' => 'cidtaggedpost1',
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => [
                        'text' => '今日は朝から #coffee を飲みながら、#Laravel の新しいルーティング設計を考えています。生産性が高い！',
                        'createdAt' => $now->copy()->subDays(1)->subHours(8)->toIso8601String(),
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(8),
        ]);
        $taggedPost1->tags()->sync([$tagCoffee->id, $tagLaravel->id]);

        $taggedPost2 = Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/tagged_post_2',
            'cid' => 'cidtaggedpost2',
            'text' => '昨夜は遅くまで #PHP のパフォーマンス改善に取り組んでいました。メモリ使用量が激減して大満足。 #Laravel',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/tagged_post_2',
                    'cid' => 'cidtaggedpost2',
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => [
                        'text' => '昨夜は遅くまで #PHP のパフォーマンス改善に取り組んでいました。メモリ使用量が激減して大満足。 #Laravel',
                        'createdAt' => $now->copy()->subDays(1)->subHours(14)->toIso8601String(),
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(14),
        ]);
        $taggedPost2->tags()->sync([$tagPHP->id, $tagLaravel->id]);

        // 2-2. 画像添付のある投稿 (1日前)
        Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/image_1',
            'cid' => 'cidimage1',
            'text' => '今日はオフィスからきれいな夕焼けが見えました！画像表示テストです。',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/image_1',
                    'cid' => 'cidimage1',
                    'likeCount' => 12,
                    'repostCount' => 3,
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => [
                        'text' => '今日はオフィスからきれいな夕焼けが見えました！画像表示テストです。',
                        'createdAt' => $now->copy()->subDays(1)->subHours(4)->toIso8601String(),
                    ],
                    'embed' => [
                        '$type' => 'app.bsky.embed.images#view',
                        'images' => [
                            [
                                'thumb' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=600&q=80',
                                'fullsize' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1200&q=80',
                                'alt' => '美しい山と川 of 自然',
                            ],
                            [
                                'thumb' => 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=600&q=80',
                                'fullsize' => 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?auto=format&fit=crop&w=1200&q=80',
                                'alt' => '霧がかった森 of 自然',
                            ],
                        ],
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(4),
        ]);

        // 2-3. 画像を10枚添付した投稿 (新形式の app.bsky.embed.gallery / 1日前)
        $galleryPhotoIds = [
            'photo-1506744038136-46273834b3fb',
            'photo-1470071459604-3b5ec3a7fe05',
            'photo-1513104890138-7c749659a591',
            'photo-1447752875215-b2761acb3c5d',
            'photo-1618384887929-16ec33fab9ef',
            'photo-1488590528505-98d2b5aba04b',
            'photo-1441974231531-c6227db76b6e',
            'photo-1501785888041-af3ef285b470',
            'photo-1439066615861-d1af74d74000',
            'photo-1475924156734-496f6cac6ec1',
        ];
        $galleryItems = [];
        foreach ($galleryPhotoIds as $index => $photoId) {
            $galleryItems[] = [
                '$type' => 'app.bsky.embed.gallery#viewImage',
                'thumbnail' => "https://images.unsplash.com/{$photoId}?auto=format&fit=crop&w=600&q=80",
                'fullsize' => "https://images.unsplash.com/{$photoId}?auto=format&fit=crop&w=1200&q=80",
                'alt' => '画像'.($index + 1).' of ギャラリー',
                'aspectRatio' => ['width' => 2556, 'height' => 1179],
            ];
        }

        Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/gallery_1',
            'cid' => 'cidgallery1',
            'text' => '画像10枚つけれるようになったと聞いて',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/gallery_1',
                    'cid' => 'cidgallery1',
                    'likeCount' => 30,
                    'repostCount' => 5,
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => [
                        'text' => '画像10枚つけれるようになったと聞いて',
                        'createdAt' => $now->copy()->subDays(1)->subHours(3)->toIso8601String(),
                    ],
                    'embed' => [
                        '$type' => 'app.bsky.embed.gallery#view',
                        'items' => $galleryItems,
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(3),
        ]);

        // ==========================================
        // 3. 公開・非公開アカウントとの各種関係シード (1日前)
        // ==========================================

        // 3-1. 公開アカウントへの返信
        Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/reply_to_public',
            'cid' => 'cidreplypublic',
            'text' => 'こちらのイベント、とても楽しみですね！',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/reply_to_public',
                    'cid' => 'cidreplypublic',
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => ['text' => 'こちらのイベント、とても楽しみですね！'],
                ],
                'reply' => $this->createReplyField($friendPost1),
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(3),
        ]);

        // 3-2. 非公開アカウントへの返信 (アバター・名前等がマスクされるべき)
        Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/reply_to_private',
            'cid' => 'cidreplyprivate',
            'text' => '非公開の相手への返信テストです。この画面にログインしていない『第三者（他者）』がこのページを見ると、返信相手のアイコンがダミーになり、名前も『非公開ユーザー（@private.user）』にマスクされ、会話トグルも非表示になります。ログアウトして動作を比較してみてください！',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/reply_to_private',
                    'cid' => 'cidreplyprivate',
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => ['text' => '非公開の相手への返信テストです。この画面にログインしていない『第三者（他者）』がこのページを見ると、返信相手のアイコンがダミーになり、名前も『非公開ユーザー（@private.user）』にマスクされ、会話トグルも非表示になります。ログアウトして動作を比較してみてください！'],
                ],
                'reply' => $this->createReplyField($bossPost1),
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(2),
        ]);

        // 3-3. 公開アカウントの引用ポスト
        Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/quote_public',
            'cid' => 'cidquotepublic',
            'text' => 'これは誰にでも役立つ素晴らしい情報ですね！シェアさせていただきます。',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/quote_public',
                    'cid' => 'cidquotepublic',
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => ['text' => 'これは誰にでも役立つ素晴らしい情報ですね！シェアさせていただきます。'],
                    'embed' => $this->createQuoteEmbedField($friendPost2),
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(1),
        ]);

        // 3-4. 非公開アカウントの引用ポスト (「非公開アカウントのため表示できません」とマスクされるべき)
        Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/quote_private',
            'cid' => 'cidquoteprivate',
            'text' => '非公開の相手の引用テストです。ログインしていない他者が見ると、下部の引用カード全体が『非公開アカウントの投稿のため表示できません。』と完全に非表示（マスク）になります。ログアウトして確かめてみてください。',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/quote_private',
                    'cid' => 'cidquoteprivate',
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => ['text' => '非公開の相手の引用テストです。ログインしていない他者が見ると、下部の引用カード全体が『非公開アカウントの投稿のため表示できません。』と完全に非表示（マスク）になります。ログアウトして確かめてみてください。'],
                    'embed' => $this->createQuoteEmbedField($bossPost2),
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subMinutes(30),
        ]);

        // 3-5. 公開アカウントのリポスト
        Post::create([
            'user_id' => $user->id,
            'uri' => 'repost-public-repost-12345',
            'cid' => 'cidrepostpublic',
            'text' => '（公開アカウントのリポスト）',
            'raw_data' => [
                'reason' => [
                    '$type' => 'app.bsky.feed.defs#reasonRepost',
                    'by' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                ],
                'post' => $this->createPostField($friendPost3),
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(6),
        ]);

        // 3-6. 非公開アカウントのリポスト (リポスト元が非公開ユーザーとしてマスクされるべき)
        Post::create([
            'user_id' => $user->id,
            'uri' => 'repost-private-repost-12345',
            'cid' => 'cidrepostprivate',
            'text' => '（非公開アカウントのリポスト）',
            'raw_data' => [
                'reason' => [
                    '$type' => 'app.bsky.feed.defs#reasonRepost',
                    'by' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                ],
                'post' => $this->createPostField($bossPost3),
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(7),
        ]);

        // ==========================================
        // 3.5 追加の応用・リッチ埋め込み表示パターン (1日前)
        // ==========================================

        // A. リポストされた投稿の中に「画像/リンク」がある
        Post::create([
            'user_id' => $user->id,
            'uri' => 'repost-public-repost-pizza-image',
            'cid' => 'cidrepostpizza',
            'text' => '（画像付きリポスト）',
            'raw_data' => [
                'reason' => [
                    '$type' => 'app.bsky.feed.defs#reasonRepost',
                    'by' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                ],
                'post' => $this->createPostField($friendPostPizza),
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(9),
        ]);

        // B. GIF選択投稿 (GIPHY)
        Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/gif_cat',
            'cid' => 'cidgifcat',
            'text' => '週末だーー！テンション爆上がりのネコGIFです。 #cats',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/gif_cat',
                    'cid' => 'cidgifcat',
                    'likeCount' => 18,
                    'repostCount' => 1,
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => [
                        'text' => '週末だーー！テンション爆上がりのネコGIFです。 #cats',
                        'createdAt' => $now->copy()->subDays(1)->subHours(10)->toIso8601String(),
                    ],
                    'embed' => [
                        '$type' => 'app.bsky.embed.external#view',
                        'external' => [
                            'uri' => 'https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExM2FqdTV0Z2I2MnU2cHpmZ21pZmh6dHY1azhicnloZGF3ZHBpeXZiZiZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/31lPv5L3a10dm/giphy.gif',
                            'title' => 'Excited Cat GIF - Giphy',
                            'description' => 'A super excited cat jumping around in joy.',
                            'thumb' => 'https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExM2FqdTV0Z2I2MnU2cHpmZ21pZmh6dHY1azhicnloZGF3ZHBpeXZiZiZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/31lPv5L3a10dm/giphy.gif',
                        ],
                    ],
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(10),
        ]);

        // C. 引用ポストの中に「画像/リンク」がある
        Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/quote_nature_image',
            'cid' => 'cidquotenature',
            'text' => '素晴らしい景色ですね！今度ここに行ってみたいです。',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/quote_nature_image',
                    'cid' => 'cidquotenature',
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => ['text' => '素晴らしい景色ですね！今度ここに行ってみたいです。'],
                    'embed' => $this->createQuoteEmbedField($friendPostNature),
                ],
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(11),
        ]);

        // D. 会話表示時のリプライ元（親ポスト）に「画像/リンク」がある
        Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/reply_to_keyboard_external',
            'cid' => 'cidreplykeyboard',
            'text' => 'この打鍵感、最高に気持ちよさそうですね。私もキーボード買い換えようかな。',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/reply_to_keyboard_external',
                    'cid' => 'cidreplykeyboard',
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => ['text' => 'この打鍵感、最高に気持ちよさそうですね。私もキーボード買い換えようかな。'],
                ],
                'reply' => $this->createReplyField($friendKeyboardPost),
            ],
            'posted_at' => $now->copy()->subDays(1)->subHours(12),
        ]);

        // ==========================================
        // 4. ドメイン表示・複数URL・二重表示排除 (一昨日)
        // ==========================================

        // 4-1. リンクカードなし・複数URLの投稿
        $text3_part1 = "リンクが2個あるとき（カードなし）\n\n";
        $text3_part2 = 'standard.site';
        $text3_part3 = "\n\n";
        $text3_part4 = 'substack.com/app';
        $text3 = $text3_part1.$text3_part2.$text3_part3.$text3_part4;

        $start3_1 = strlen($text3_part1);
        $end3_1 = $start3_1 + strlen($text3_part2);
        $start3_2 = $end3_1 + strlen($text3_part3);
        $end3_2 = $start3_2 + strlen($text3_part4);

        Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/demo_nocard_1',
            'cid' => 'cidnocard1',
            'text' => $text3,
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/demo_nocard_1',
                    'cid' => 'cidnocard1',
                    'likeCount' => 2,
                    'repostCount' => 0,
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => [
                        'text' => $text3,
                        'createdAt' => $baseDate->copy()->subHours(10)->toIso8601String(),
                        'facets' => [
                            [
                                'index' => ['byteStart' => $start3_1, 'byteEnd' => $end3_1],
                                'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => 'https://standard.site']],
                            ],
                            [
                                'index' => ['byteStart' => $start3_2, 'byteEnd' => $end3_2],
                                'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => 'https://substack.com/app']],
                            ],
                        ],
                    ],
                ],
            ],
            'posted_at' => $baseDate->copy()->subHours(10),
        ]);

        // ==========================================
        // 5. 日付またぎスレッドのシード (一昨日〜昨日の境界線)
        // ==========================================

        // 5-1. 親投稿（外部リンクカード付き）
        $text1_part1 = "リンクが2個あるとき（サムネイルあり）\n\n";
        $text1_part2 = 'standard.site';
        $text1_part3 = "\n\n";
        $text1_part4 = 'substack.com/app';
        $text1 = $text1_part1.$text1_part2.$text1_part3.$text1_part4;

        $start1_1 = strlen($text1_part1);
        $end1_1 = $start1_1 + strlen($text1_part2);
        $start1_2 = $end1_1 + strlen($text1_part3);
        $end1_2 = $start1_2 + strlen($text1_part4);

        $parentPost = Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/demo_parent_1',
            'cid' => 'cidparent1',
            'text' => $text1,
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/demo_parent_1',
                    'cid' => 'cidparent1',
                    'likeCount' => 3,
                    'repostCount' => 1,
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => [
                        'text' => $text1,
                        'createdAt' => $baseDate->toIso8601String(),
                        'facets' => [
                            [
                                'index' => ['byteStart' => $start1_1, 'byteEnd' => $end1_1],
                                'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => 'https://standard.site']],
                            ],
                            [
                                'index' => ['byteStart' => $start1_2, 'byteEnd' => $end1_2],
                                'features' => [['$type' => 'app.bsky.richtext.facet#link', 'uri' => 'https://substack.com/app']],
                            ],
                        ],
                    ],
                    'embed' => [
                        '$type' => 'app.bsky.embed.external#view',
                        'external' => [
                            'uri' => 'https://standard.site',
                            'title' => 'Standard.site - One schema. Every platform.',
                            'description' => 'Standard.site provides shared lexicons for long-form publishing on AT Protocol.',
                            'thumb' => 'https://images.unsplash.com/photo-1488590528505-98d2b5aba04b?auto=format&fit=crop&w=800&q=80',
                        ],
                    ],
                ],
            ],
            'posted_at' => $baseDate,
        ]);

        // 5-2. 日付またぎの子投稿（翌日 00:10・自動的に前日へマージされ連結されるべき）
        Post::create([
            'user_id' => $user->id,
            'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/demo_child_1',
            'cid' => 'cidchild1',
            'text' => '日付が変わったけど、スレッドが繋がっているおかげで1つの日記として読みやすい。',
            'raw_data' => [
                'post' => [
                    'uri' => 'at://did:plc:testuser1234567890/app.bsky.feed.post/demo_child_1',
                    'cid' => 'cidchild1',
                    'likeCount' => 5,
                    'repostCount' => 0,
                    'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                    'record' => [
                        'text' => '日付が変わったけど、スレッドが繋がっているおかげで1つの日記として読みやすい。',
                        'createdAt' => $nextDate->toIso8601String(),
                    ],
                ],
                'reply' => $this->createReplyField($parentPost),
            ],
            'posted_at' => $nextDate,
        ]);

        // ==========================================
        // 6. ページまたぎスレッド (全18件の超長文セルフスレッド) (三日前)
        // ==========================================
        $threadStartDate = $now->copy()->subDays(3)->startOfDay()->addHours(10);
        $threadCidPrefix = 'cidpagedthread';
        $threadUriPrefix = 'at://did:plc:testuser1234567890/app.bsky.feed.post/paged_thread_';

        $rootPost = null;
        $previousPost = null;

        for ($i = 1; $i <= 18; $i++) {
            $postUri = $threadUriPrefix.$i;
            $postCid = $threadCidPrefix.$i;
            $postedAt = $threadStartDate->copy()->addMinutes(($i - 1) * 10);
            $text = "ページまたぎスレッド検証用投稿 #{$i}。このスレッドは全部で18件あります。";
            if ($i === 1) {
                $text .= ' これが最初の投稿（親）です。';
            } elseif ($i === 18) {
                $text .= ' これが最後の投稿（18件目）です。ページネーションを超えて連結されているはずです。';
            }

            $rawReply = null;
            if ($i > 1) {
                $rawReply = $this->createReplyField($previousPost, $rootPost);
            }

            $currentPost = Post::create([
                'user_id' => $user->id,
                'uri' => $postUri,
                'cid' => $postCid,
                'text' => $text,
                'raw_data' => [
                    'post' => [
                        'uri' => $postUri,
                        'cid' => $postCid,
                        'likeCount' => 2,
                        'repostCount' => 0,
                        'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                        'record' => [
                            'text' => $text,
                            'createdAt' => $postedAt->toIso8601String(),
                        ],
                    ],
                    'reply' => $rawReply,
                ],
                'posted_at' => $postedAt,
            ]);

            if ($i === 1) {
                $rootPost = $currentPost;
            }
            $previousPost = $currentPost;
        }

        // ==========================================
        // 7. 削除モードのページ送り検証用 (4日前〜33日前)
        // ==========================================
        // 削除モードは1ページ50件。ここまでの自分の投稿 (約33件) と合わせて2ページ目ができるよう、
        // 1日2件ずつ足す。12日前は4件のスレッドにして、ページの切れ目がスレッドにかかるようにする
        $pagingThreadDay = 12;

        for ($daysAgo = 4; $daysAgo <= 33; $daysAgo++) {
            $day = $now->copy()->subDays($daysAgo)->startOfDay();

            if ($daysAgo === $pagingThreadDay) {
                $rootPost = null;
                $previousPost = null;

                for ($i = 1; $i <= 4; $i++) {
                    $postUri = 'at://did:plc:testuser1234567890/app.bsky.feed.post/manage_paging_thread_'.$i;
                    $postedAt = $day->copy()->addHours(21)->addMinutes(($i - 1) * 10);
                    $text = "削除モードのページまたぎスレッド #{$i}/4。ページの切れ目がこのスレッドにかかります。";

                    $currentPost = Post::create([
                        'user_id' => $user->id,
                        'uri' => $postUri,
                        'cid' => 'cidmanagepagingthread'.$i,
                        'text' => $text,
                        'raw_data' => [
                            'post' => [
                                'uri' => $postUri,
                                'cid' => 'cidmanagepagingthread'.$i,
                                'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                                'record' => [
                                    'text' => $text,
                                    'createdAt' => $postedAt->toIso8601String(),
                                ],
                            ],
                            'reply' => $previousPost ? $this->createReplyField($previousPost, $rootPost) : null,
                        ],
                        'posted_at' => $postedAt,
                    ]);

                    $rootPost ??= $currentPost;
                    $previousPost = $currentPost;
                }

                continue;
            }

            foreach ([9, 20] as $hour) {
                $postUri = "at://did:plc:testuser1234567890/app.bsky.feed.post/manage_paging_{$daysAgo}_{$hour}";
                $postedAt = $day->copy()->addHours($hour);
                $text = "{$daysAgo}日前の{$hour}時のつぶやき（削除モードのページ送り確認用）";

                Post::create([
                    'user_id' => $user->id,
                    'uri' => $postUri,
                    'cid' => "cidmanagepaging{$daysAgo}{$hour}",
                    'text' => $text,
                    'raw_data' => [
                        'post' => [
                            'uri' => $postUri,
                            'cid' => "cidmanagepaging{$daysAgo}{$hour}",
                            'author' => $this->createAuthorField($bskyAccount, $testUserDisplayName),
                            'record' => [
                                'text' => $text,
                                'createdAt' => $postedAt->toIso8601String(),
                            ],
                        ],
                    ],
                    'posted_at' => $postedAt,
                ]);
            }
        }
    }

    /**
     * アカウント情報から Bluesky 形式の 'author' 配列を構築します。
     */
    private function createAuthorField(BlueskyAccount $account, string $displayName): array
    {
        return [
            'did' => $account->did,
            'handle' => $account->handle,
            'displayName' => $displayName,
            'avatar' => $account->avatar_url,
        ];
    }

    /**
     * 既存の Post レコードから、Bluesky 形式の 'post' プレビュー配列を動的に構築します。
     */
    private function createPostField(Post $post): array
    {
        $account = $post->user->blueskyAccount;

        $postData = [
            'uri' => $post->uri,
            'cid' => $post->cid,
            'likeCount' => $post->raw_data['post']['likeCount'] ?? 0,
            'repostCount' => $post->raw_data['post']['repostCount'] ?? 0,
            'author' => $this->createAuthorField($account, $post->user->name),
            'record' => [
                'text' => $post->text,
                'createdAt' => $post->posted_at->toIso8601String(),
            ],
        ];

        // 親投稿に画像や外部リンクなどの embed 要素があれば引き継ぐ
        if (! empty($post->raw_data['post']['embed'])) {
            $postData['embed'] = $post->raw_data['post']['embed'];
        }

        return $postData;
    }

    /**
     * 既存の Post レコードから、返信用の 'reply' 親子関係配列を動的に構築します。
     */
    private function createReplyField(Post $parentPost, ?Post $rootPost = null): array
    {
        $parentPostField = $this->createPostField($parentPost);
        $rootPostField = $rootPost ? $this->createPostField($rootPost) : $parentPostField;

        return [
            'parent' => [
                'uri' => $parentPostField['uri'],
                'author' => $parentPostField['author'],
                'record' => $parentPostField['record'],
                'embed' => $parentPostField['embed'] ?? null,
            ],
            'root' => [
                'uri' => $rootPostField['uri'],
                'author' => [
                    'did' => $rootPostField['author']['did'],
                    'handle' => $rootPostField['author']['handle'],
                ],
            ],
        ];
    }

    /**
     * 既存の Post レコードから、引用用の 'embed.record' プレビュー配列を動的に構築します。
     */
    private function createQuoteEmbedField(Post $quotedPost): array
    {
        $account = $quotedPost->user->blueskyAccount;

        $recordData = [
            '$type' => 'app.bsky.embed.record#view',
            'record' => [
                '$type' => 'app.bsky.embed.record#viewRecord',
                'uri' => $quotedPost->uri,
                'author' => $this->createAuthorField($account, $quotedPost->user->name),
                'value' => [
                    'text' => $quotedPost->text,
                    'createdAt' => $quotedPost->posted_at->toIso8601String(),
                ],
            ],
        ];

        // 引用ポスト内に画像等があれば embeds として引き継ぐ
        if (! empty($quotedPost->raw_data['post']['embed'])) {
            $embed = $quotedPost->raw_data['post']['embed'];
            $type = $embed['$type'] ?? '';

            if (in_array($type, ['app.bsky.embed.images#view', 'app.bsky.embed.gallery#view'], true)) {
                $recordData['record']['embeds'] = [
                    $embed,
                ];
            } elseif ($type === 'app.bsky.embed.recordWithMedia#view' && isset($embed['media'])) {
                // メディア付き引用の場合、中のメディアを引き継ぐ
                $recordData['record']['embeds'] = [
                    $embed['media'],
                ];
            }
        }

        return $recordData;
    }
}
