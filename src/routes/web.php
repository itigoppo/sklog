<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\WaitlistController;
use App\Http\Controllers\AdminController;

// 1. ルートURLへのアクセス
Route::get('/', function () {
  // 公開されているBlueskyアカウントを最新登録順に取得
  $publicAccounts = \App\Models\BlueskyAccount::where('is_private', false)
    ->where('handle', '!=', config('services.bluesky.demo_handle'))
    ->with('user')
    ->orderBy('created_at', 'desc')
    ->get();

  return view('welcome', compact('publicAccounts'));
});

// 2. サービスについて・利用規約ページ (固定)
Route::view('/about', 'about')->name('about');

// 3. 認証関連 (固定)
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/login/confirm-handle-change', [AuthController::class, 'showConfirmHandleChange'])->name('login.confirm_handle_change');
Route::post('/login/confirm-handle-change', [AuthController::class, 'confirmHandleChange']);
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 4. 参加申請（ウェイトリスト - 固定）
Route::get('/join', [WaitlistController::class, 'show'])->name('waitlist.show');
Route::post('/join', [WaitlistController::class, 'store'])->name('waitlist.store');
Route::get('/join/completed', [WaitlistController::class, 'completed'])->name('waitlist.completed');
Route::post('/register/resend', [AuthController::class, 'requestResend'])->name('register.resend');

// 5. 手動同期トリガー（固定 - ログイン中の本人限定。コントローラー内で認可チェック）
Route::post('/sync', [CalendarController::class, 'sync'])
  ->middleware('auth')
  ->name('calendar.sync');

// 6. 管理画面 (固定)
Route::get('/admin', fn() => redirect()->route('admin.login'));
Route::get('/admin/login', [AdminController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login.post');
Route::post('/admin/logout', [AdminController::class, 'logout'])->name('admin.logout');
Route::middleware(\App\Http\Middleware\AdminAuth::class)->group(function () {
  Route::get('/admin/waitlist', [AdminController::class, 'index'])->name('admin.waitlist');
  Route::post('/admin/waitlist/{entry}/invite', [AdminController::class, 'sendInvite'])->name('admin.waitlist.invite');
  Route::post('/admin/waitlist/{entry}/reinvite', [AdminController::class, 'reinvite'])->name('admin.waitlist.reinvite');
  
  // セキュリティ設定関連
  Route::get('/admin/security', [AdminController::class, 'securitySettings'])->name('admin.security');
  Route::post('/admin/security/verify', [AdminController::class, 'verify2faSetup'])->name('admin.security.verify');
});

// =========================================================================
// 7. ユーザー個別カレンダー関連 (ワイルドカード)
// 固定ルートの衝突を防ぐため、定義順を最後に配置し、ドメイン名等のハンドルにマッチさせます。
// =========================================================================

$handlePattern = '[a-zA-Z0-9\.-]+';

// メインブログページ (全期間の最新順ページネーション表示)
Route::get('/profile/{handle}', [CalendarController::class, 'show'])
  ->where('handle', $handlePattern)
  ->name('calendar.show');

// 個別アーカイブ・設定・ポスト管理のルーティンググループ
Route::prefix('/profile/{handle}')->where(['handle' => $handlePattern])->group(function () {
  // 年ページ
  Route::get('/{year}', [CalendarController::class, 'showYear'])
    ->where('year', '[0-9]{4}')
    ->name('calendar.show.year');

  // 月ページ
  Route::get('/{year}/{month}', [CalendarController::class, 'showMonth'])
    ->where(['year' => '[0-9]{4}', 'month' => '[0-9]{2}'])
    ->name('calendar.show.month');

  // 日ページ
  Route::get('/{year}/{month}/{day}', [CalendarController::class, 'showDay'])
    ->where(['year' => '[0-9]{4}', 'month' => '[0-9]{2}', 'day' => '[0-9]{2}'])
    ->name('calendar.show.day');

  // カレンダーAjax取得 (非同期月めくり用)
  Route::get('/calendar-api/{year}/{month}', [CalendarController::class, 'getCalendarHtml'])
    ->where(['year' => '[0-9]{4}', 'month' => '[0-9]{2}'])
    ->name('calendar.api.month');

  // 全アーカイブ一覧ページ
  Route::get('/archives', [CalendarController::class, 'showArchives'])
    ->name('calendar.archives');

  // 全タグ一覧ページ
  Route::get('/tags', [CalendarController::class, 'showTags'])
    ->name('calendar.tags');

  // 設定画面（ログイン中の本人限定）
  Route::get('/settings', [CalendarController::class, 'showSettings'])
    ->middleware('auth')
    ->name('calendar.settings');

  // 複数ポストインポート処理（ログイン中の本人限定）
  Route::post('/settings/import-posts', [CalendarController::class, 'importPosts'])
    ->middleware('auth')
    ->name('calendar.settings.import_posts');

  // アプリパスワードの更新（ログイン中の本人限定）
  Route::post('/settings/update-password', [CalendarController::class, 'updatePassword'])
    ->middleware('auth')
    ->name('calendar.settings.update_password');

  // 退会・アカウント削除（ログイン中の本人限定）
  Route::post('/settings/delete-account', [CalendarController::class, 'deleteAccount'])
    ->middleware('auth')
    ->name('calendar.settings.delete_account');

  // ポストのローカル削除（ログイン中の本人限定）
  Route::delete('/posts/{post}/delete', [CalendarController::class, 'destroy'])
    ->middleware('auth')
    ->name('calendar.post.destroy');

  // ポストの復元（ログイン中の本人限定）
  Route::post('/posts/{post_id}/restore', [CalendarController::class, 'restore'])
    ->middleware('auth')
    ->name('calendar.post.restore');
});

