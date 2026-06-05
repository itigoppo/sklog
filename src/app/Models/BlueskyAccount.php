<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

#[Fillable([
  'user_id',
  'did',
  'handle',
  'encrypted_app_password',
  'is_private',
  'registered_at',
  'avatar_url',
  'banner_url',
  'description',
  'posts_count',
  'last_synced_at',
  'next_cursor',
  'is_archive_complete',
  'is_syncing',
  'is_auto_sync_enabled',
  'auth_failure_count',
  'sync_error_type',
  'sync_error_message',
])]
#[Hidden([
  'encrypted_app_password',
 ])]
class BlueskyAccount extends Model
{
  use HasUuids;

  /**
   * Get the user that owns the Bluesky account.
   */
  public function user()
  {
    return $this->belongsTo(User::class);
  }

  /**
   * Get the attributes that should be cast.
   *
   * @return array<string, string>
   */
  protected function casts(): array
  {
    return [
      'encrypted_app_password' => 'encrypted',
      'is_private' => 'boolean',
      'registered_at' => 'datetime',
      'last_synced_at' => 'datetime',
      'is_archive_complete' => 'boolean',
      'is_syncing' => 'boolean',
      'is_auto_sync_enabled' => 'boolean',
      'auth_failure_count' => 'integer',
    ];
  }

  /**
   * プロフィール情報からアカウントが非公開（Logged-out visibility制限）設定であるかを判定します。
   */
  public static function isPrivateFromProfile(array $profile): bool
  {
    if (!isset($profile['labels'])) {
      return false;
    }

    foreach ($profile['labels'] as $label) {
      $val = $label['val'] ?? '';
      if (str_contains($val, 'no-unauthenticated')) {
        return true;
      }
    }

    return false;
  }

  /**
   * 自己紹介文（description）のURLをリンク化したHTMLを取得します。
   */
  public function getHtmlDescriptionAttribute(): string
  {
    if (empty($this->description)) {
      return '';
    }

    // XSS対策で事前にHTMLエスケープ
    $escaped = e($this->description);

    // URLを検知する正規表現（末尾の句読点や括弧などを除外する実用的なパターン）
    $pattern = '#(https?://[a-zA-Z0-9\-\.\/\?\&\=\#\%\~\_\:\@\+]+[^.,\s\(\)\[\]\{\}\<\>])#i';

    return preg_replace_callback($pattern, function ($matches) {
      $url = $matches[1];
      return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline hover:text-sky-300 transition cursor-pointer inline-flex items-center gap-0.5">' . $url . '</a>';
    }, $escaped);
  }
}

