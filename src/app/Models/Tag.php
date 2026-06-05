<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

#[Fillable([
  'name',
])]
class Tag extends Model
{
  use HasUuids;

  /**
   * テキスト全体からハッシュタグを抽出する正規表現
   */
  const EXTRACT_REGEX = '/(?<=^|\s)#(?!\d+(?=$|\s))([a-zA-Z0-9ぁ-んァ-ヶー一-龠々ー\w\-_]+)(?=$|\s)/u';

  /**
   * 単一のハッシュタグであるかを厳密に判定する正規表現
   */
  const STRICT_REGEX = '/^#([a-zA-Z0-9ぁ-んァ-ヶー一-龠々ー\w\-_]+)$/u';

  /**
   * テキストからハッシュタグ（#を除く名前部分）を抽出します。
   *
   * @param string $text
   * @return array
   */
  public static function extractTags(string $text): array
  {
    if (preg_match_all(self::EXTRACT_REGEX, $text, $matches) && !empty($matches[1])) {
      return array_unique((array)$matches[1]);
    }
    return [];
  }

  /**
   * キーワードがハッシュタグ形式であるかを判定し、タグ名を返します。
   *
   * @param string $keyword
   * @return string|null タグ名（#を除いたもの）。ハッシュタグでなければnull。
   */
  public static function parseHashtag(string $keyword): ?string
  {
    if (preg_match(self::STRICT_REGEX, $keyword, $matches)) {
      return $matches[1];
    }
    return null;
  }

  /**
   * The posts that belong to the tag.
   */
  public function posts()
  {
    return $this->belongsToMany(Post::class, 'post_tags');
  }
}
