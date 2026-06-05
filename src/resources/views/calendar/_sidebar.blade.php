<!-- ================= 右カラム：検索、ミニカレンダー & 各種カード ================= -->
<div class="lg:col-span-5 xl:col-span-4 space-y-6">

  <!-- アクション管理カード (戻るボタン・同期・ログイン・ログアウト) -->
  @include('calendar._actions')

  @php
    $isOwner = Auth::check() && Auth::id() === $user->id;
    $isDemo = $bskyAccount->handle === config('services.bluesky.demo_handle');
    $isOwnerOrDemo = $isOwner || $isDemo;
  @endphp

  @if ($isOwnerOrDemo)
    <!-- 検索フォーム -->
    @include('calendar._search')

    <!-- ミニカレンダーの読み込み -->
    <div id="miniCalendarContainer" data-handle="{{ $bskyAccount->handle }}" class="glass-card rounded-2xl p-4 border border-slate-800/80">
      @include('calendar._calendar')
    </div>

    <!-- 統計アクティビティカード (Stats) -->
    @include('calendar._stats')

    <!-- 月アーカイブのリスト (Archives) -->
    @include('calendar._archives')

    <!-- ハッシュタグのリスト (Tags) -->
    @include('calendar._tags')
  @endif

</div>
