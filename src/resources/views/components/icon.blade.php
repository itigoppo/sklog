@props(['name'])

@php
  // 塗りつぶし型（fill）アイコンかどうかの判定
  $isFillIcon = in_array($name, ['logo', 'user', 'repost', 'heart', 'bluesky']);

  // 独自のviewBoxを持つアイコンの判定（24x24以外の座標系）
  $hasCustomViewBox = in_array($name, ['logo', 'bluesky', 'user', 'repost', 'heart']);

  if ($isFillIcon) {
      // 塗りつぶしアイコン用のデフォルト設定（境界線なし）
      $attributes = $attributes->merge([
          'class' => 'h-4 w-4 shrink-0',
          'fill' => $name === 'logo' ? 'white' : 'currentColor',
      ]);
      if (!$hasCustomViewBox) {
          $attributes = $attributes->merge(['viewBox' => '0 0 24 24']);
      }
  } else {
      // 線画アイコン用のデフォルト設定（境界線あり）
      $attributes = $attributes->merge([
          'class' => 'h-4 w-4 shrink-0',
          'fill' => 'none',
          'stroke' => 'currentColor',
          'stroke-width' => '2',
      ]);
      if (!$hasCustomViewBox) {
          $attributes = $attributes->merge(['viewBox' => '0 0 24 24']);
      }
  }
@endphp

@switch($name)
  @case('logo')
    {{-- サイトロゴ：美しくデフォルメされた完璧な蝶 of シルエット --}}
    <svg {{ $attributes->merge(['viewBox' => '0 0.7 24 24', 'fill' => 'white']) }}>
      <path d="M12 10.3c-.6-1.5-1.8-3-3.5-3.8C6.3 5.4 3.5 5.8 2.2 7c-1.3 1.2-1.5 4 .2 6.6.9 1.4 2.3 2.8 4.1 3.8.5.3 1.1.5 1.7.7-1 1.5-2 3-2 4.1 0 .6.4 1 1 1 .6 0 1.2-.4 1.8-1 1.2-1.2 2.2-2.8 3-4.4.8 1.6 1.8 3.2 3 4.4.6.6 1.2 1 1.8 1 .6 0 1-.4 1-1 0-1.1-1-2.6-2-4.1.6-.2 1.2-.4 1.7-.7 1.8-1 3.2-2.4 4.1-3.8 1.7-2.6 1.5-5.4.2-6.6-1.3-1.2-4.1-1.6-6.3-.5-1.7.8-2.9 2.3-3.5 3.8z"/>
    </svg>
    @break

  @case('sync')
    {{-- 最終同期：時計回り巡回矢印 --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.72 2.72L21 8" />
      <polyline points="21 3 21 8 16 8" />
    </svg>
    @break

  @case('reply')
    {{-- リプライ・返信：左曲がり矢印 --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <polyline points="9 17 4 12 9 7" />
      <path d="M20 18v-2a4 4 0 0 0-4-4H4" />
    </svg>
    @break

  @case('lock')
    {{-- 自分のみ：鍵マーク --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
    </svg>
    @break

  @case('globe')
    {{-- 公開中：地球マーク --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <circle cx="12" cy="12" r="10" />
      <line x1="2" y1="12" x2="22" y2="12" />
      <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
    </svg>
    @break

  @case('link')
    {{-- 外部リンクマーク --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
    </svg>
    @break

  @case('alert')
    {{-- 警告・エラー：三角注意マーク --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
    </svg>
    @break

  @case('success')
    {{-- 成功・完了：丸チェックマーク --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
    </svg>
    @break

  @case('chevron-up')
    {{-- バックツートップなど：上向き矢印 --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M5 10l7-7m0 0l7 7m-7-7v18" />
    </svg>
    @break

  @case('menu')
    {{-- 三本線メニュー --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M4 6h16M4 12h16M4 18h16" />
    </svg>
    @break

  @case('close')
    {{-- バツ印閉じる --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M6 18L18 6M6 6l12 12" />
    </svg>
    @break

  @case('chevron-left')
    {{-- 前へ、左戻る --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M15 19l-7-7 7-7" />
    </svg>
    @break

  @case('chevron-right')
    {{-- 次へ、右進む --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M9 5l7 7-7 7" />
    </svg>
    @break

  @case('calendar')
    {{-- カレンダーマーク --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </svg>
    @break

  @case('login')
    {{-- ログイン・サインイン --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
    </svg>
    @break

  @case('mail')
    {{-- メール、参加申請 --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </svg>
    @break

  @case('book-open')
    {{-- 本を開く、公開ドキュメント・ユーザー一覧 --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
    </svg>
    @break

  @case('key')
    {{-- 鍵、パスワード、登録コード --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
    </svg>
    @break

  @case('info')
    {{-- インフォメーションマーク --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    @break

  @case('arrow-left')
    {{-- 左向き矢印、戻る --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M10 19l-7-7m0 0l7-7m-7 7h18" />
    </svg>
    @break

  @case('user')
    {{-- ユーザープロフィールプレースホルダー --}}
    <svg {{ $attributes->merge(['viewBox' => '0 0 20 20', 'fill' => 'currentColor']) }}>
      <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
    </svg>
    @break

  @case('repost')
    {{-- リポスト（巡回矢印） --}}
    <svg {{ $attributes->merge(['viewBox' => '0 0 20 20', 'fill' => 'currentColor']) }}>
      <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 110 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
    </svg>
    @break

  @case('clock')
    {{-- 時計、投稿日時 --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    @break

  @case('heart')
    {{-- いいね、ハートマーク --}}
    <svg {{ $attributes->merge(['viewBox' => '0 0 20 20', 'fill' => 'currentColor']) }}>
      <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
    </svg>
    @break

  @case('bluesky')
    {{-- Bluesky 公式の蝶ロゴ --}}
    <svg {{ $attributes->merge(['viewBox' => '0 0 568 501', 'fill' => 'currentColor']) }}>
      <path d="M123.121 33.6637C188.241 82.5526 258.281 181.681 284 234.873C309.719 181.681 379.759 82.5526 444.879 33.6637C491.866 -1.61183 568 -28.9064 568 57.9464C568 75.2916 558.055 203.659 552.222 224.501C531.947 296.954 458.067 315.434 392.347 304.249C507.222 323.8 536.444 388.56 473.333 453.32C353.473 576.312 301.061 422.461 287.631 383.039C285.169 375.812 284.017 372.431 284 375.306C283.983 372.431 282.831 375.812 280.369 383.039C266.939 422.461 214.527 576.312 94.6667 453.32C31.5556 388.56 60.7778 323.8 175.653 304.249C109.933 315.434 36.0535 296.954 15.7778 224.501C9.94525 203.659 0 75.2916 0 57.9464C0 -28.9064 76.1345 -1.61183 123.121 33.6637Z" />
    </svg>
    @break

  @case('home')
    {{-- ホーム、ダッシュボード、カレンダー/全履歴 --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
    </svg>
    @break

  @case('search')
    {{-- 検索用虫眼鏡マーク --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
    @break

  @case('tag')
    {{-- ハッシュタグマーク --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
    </svg>
    @break

  @case('list')
    {{-- リストマーク、すべてのログ --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M4 6h16M4 12h16M4 18h7" />
    </svg>
    @break

  @case('bar-chart')
    {{-- グラフ、統計、アクティビティ --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" />
    </svg>
    @break

  @case('filter')
    {{-- フィルター、絞り込み、年選択 --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 8.293A1 1 0 013 7.586V4z" />
    </svg>
    @break

  @case('user-plus')
    {{-- アカウント作成、ユーザー追加 --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
    </svg>
    @break

  @case('shield-check')
    {{-- 盾とチェックマーク、セキュリティ安全 --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
    </svg>
    @break

  @case('copy')
    {{-- クリップボードにコピー --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 002 2h2a2 2 0 002-2" />
    </svg>
    @break

  @case('check')
    {{-- チェックマーク、成功・完了（丸なし） --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M5 13l4 4L19 7" />
    </svg>
    @break

  @case('gift')
    {{-- お祝い・プレゼント --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <polyline points="20 12 20 22 4 22 4 12" />
      <rect x="2" y="7" width="20" height="5" />
      <line x1="12" y1="22" x2="12" y2="7" />
      <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z" />
      <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z" />
    </svg>
    @break

  @case('award')
    {{-- アワード・アニバーサリー勲章 --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <circle cx="12" cy="8" r="7" />
      <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88" />
    </svg>
    @break

  @case('image')
    {{-- 画像・メディア・ギャラリーマーク --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
      <circle cx="8.5" cy="8.5" r="1.5" />
      <polyline points="21 15 16 10 5 21" />
    </svg>
    @break

  @case('settings')
    {{-- 設定ギアマーク --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <circle cx="12" cy="12" r="3" />
      <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
    </svg>
    @break

  @case('trash')
    {{-- ゴミ箱マーク、削除 --}}
    <svg {{ $attributes->merge(['stroke-linecap' => 'round', 'stroke-linejoin' => 'round']) }}>
      <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>
    @break
@endswitch
