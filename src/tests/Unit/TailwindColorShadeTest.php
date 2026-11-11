<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * ビューで Tailwind の既定に無い色の濃さ（slate-850 など）を使っていないことを確かめます。
 *
 * 既定に無い濃さはクラスが生成されず、何も起きないまま見た目だけが崩れます
 * （border なら色が currentColor になり、白い線が出ていました）。
 */
class TailwindColorShadeTest extends TestCase
{
  private const SHADES = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];

  private const COLORS = [
    'slate', 'gray', 'zinc', 'neutral', 'stone', 'red', 'orange', 'amber', 'yellow', 'lime', 'green',
    'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose',
  ];

  public function test_views_use_only_default_color_shades(): void
  {
    $viewsPath = dirname(__DIR__, 2).'/resources/views';
    $pattern = '/\b(?:'.implode('|', self::COLORS).')-(\d+)\b/';
    $violations = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));
    foreach ($files as $file) {
      if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
      }

      foreach (file($file->getPathname()) as $index => $line) {
        preg_match_all($pattern, $line, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
          if (! in_array($match[1], self::SHADES, true)) {
            $relativePath = substr($file->getPathname(), strlen($viewsPath) + 1);
            $violations[] = $relativePath.':'.($index + 1).' '.$match[0];
          }
        }
      }
    }

    $this->assertSame([], $violations, "Tailwind の既定に無い色の濃さが使われています:\n".implode("\n", $violations));
  }
}
