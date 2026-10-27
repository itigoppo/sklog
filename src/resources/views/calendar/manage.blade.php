@extends('layouts.app')

@section('title', '削除モード | ' . $bskyAccount->handle . ' のカレンダー | ' . config('app.name', 'SkLoG'))

@section('content')
  @php
    $isRepostTab = $tab === 'reposts';
    $weekdays = ['日', '月', '火', '水', '木', '金', '土'];
  @endphp

  <div class="max-w-3xl mx-auto space-y-6 pb-28">

    <!-- タイトル & 戻るボタン -->
    <div class="flex flex-col md:flex-row md:items-start justify-between border-b border-slate-800 pb-3 gap-3">
      <div class="space-y-1.5">
        <h3 class="text-lg font-bold text-white tracking-wide flex items-center gap-2 select-none">
          <x-icon name="trash" class="h-5 w-5 text-rose-400" stroke-width="2" />
          削除モード
        </h3>
        <!-- 引き継いだ絞り込み条件 -->
        <div class="flex items-center gap-1.5 flex-wrap text-[10px] font-bold select-none">
          <span class="px-1.5 py-0.5 rounded bg-slate-900 border border-slate-800 text-slate-400">{{ $period['label'] ?? 'すべての期間' }}</span>
          @if ($q !== '')
            <span class="px-1.5 py-0.5 rounded bg-sky-500/10 border border-sky-500/20 text-sky-400">"{{ $q }}" {{ $searchMode === 'or' ? 'OR' : 'AND' }}</span>
          @endif
          @if ($onlyMedia)
            <span class="px-1.5 py-0.5 rounded bg-sky-500/10 border border-sky-500/20 text-sky-400 flex items-center gap-1">
              <x-icon name="image" class="h-3 w-3" />画像のみ
            </span>
          @endif
          <span class="text-slate-500">{{ $posts->total() }}件</span>
        </div>
      </div>

      <a href="{{ $backUrl }}" class="js-manage-leave px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white text-xs font-semibold transition flex items-center gap-1 shrink-0 self-start">
        <x-icon name="arrow-left" class="h-3.5 w-3.5" />
        削除モードを終わる
      </a>
    </div>

    <!-- 投稿 / リポストのタブ -->
    <div class="flex items-center gap-2 select-none">
      @foreach (['posts' => '投稿', 'reposts' => 'リポスト'] as $tabKey => $tabLabel)
        <a href="{{ route('calendar.manage', ['handle' => $bskyAccount->handle] + $filters + ($tabKey === 'reposts' ? ['tab' => 'reposts'] : [])) }}"
           class="js-manage-leave px-4 py-1.5 rounded-lg border text-xs font-bold transition duration-300 flex items-center gap-1.5 {{ $tab === $tabKey ? 'bg-rose-500/10 border-rose-500/30 text-rose-300' : 'bg-slate-900/40 border-slate-800 hover:border-slate-700 text-slate-400 hover:text-white' }}">
          <x-icon :name="$tabKey === 'reposts' ? 'repost' : 'list'" class="h-3.5 w-3.5" />
          {{ $tabLabel }}
        </a>
      @endforeach
    </div>

    <!-- 削除モードの中で検索する (タブ・期間・画像のみの条件は引き継ぐ) -->
    <form action="{{ route('calendar.manage', ['handle' => $bskyAccount->handle]) }}" method="GET" id="manageSearch"
          class="flex flex-wrap items-center gap-x-3 gap-y-2 m-0"
          data-confirm-title="選択が外れます" data-confirm-action="検索する">
      @if ($isRepostTab)
        <input type="hidden" name="tab" value="reposts">
      @endif
      @isset($filters['period'])
        <input type="hidden" name="period" value="{{ $filters['period'] }}">
      @endisset
      @isset($filters['media'])
        <input type="hidden" name="media" value="1">
      @endisset
      <div class="relative flex-1 min-w-48 flex items-center">
        <input type="text" name="q" value="{{ $q }}" placeholder="{{ $isRepostTab ? 'リポストを検索...' : '投稿を検索...' }}"
               class="w-full bg-slate-950/60 border border-slate-800/80 hover:border-slate-700/80 focus:border-sky-500/80 text-white rounded-xl py-2 pl-9 pr-4 text-xs placeholder-slate-500 focus:outline-hidden transition shadow-inner">
        <span class="absolute left-3 text-slate-500 select-none">
          <x-icon name="search" class="h-4 w-4" stroke-width="2" />
        </span>
      </div>
      <div class="flex items-center gap-3 text-[10px] text-slate-400 font-bold select-none">
        @foreach (['and' => 'AND', 'or' => 'OR'] as $modeKey => $modeLabel)
          <label class="flex items-center gap-1.5 cursor-pointer hover:text-slate-200 transition">
            <input type="radio" name="mode" value="{{ $modeKey }}" {{ $searchMode === $modeKey ? 'checked' : '' }} class="accent-sky-500 cursor-pointer">
            {{ $modeLabel }}
          </label>
        @endforeach
        <button type="submit" aria-label="検索" class="p-2 bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 hover:border-sky-500/50 text-sky-400 rounded-xl transition duration-300 cursor-pointer active:scale-95 flex items-center justify-center">
          <x-icon name="search" class="h-4 w-4" stroke-width="2.5" />
        </button>
        @if ($q !== '')
          <a href="{{ route('calendar.manage', ['handle' => $bskyAccount->handle] + array_diff_key($filters, array_flip(['q', 'mode'])) + ($isRepostTab ? ['tab' => 'reposts'] : [])) }}"
             class="js-manage-leave text-slate-500 hover:text-white transition">
            検索を解除
          </a>
        @endif
      </div>
    </form>

    <p class="text-[11px] text-slate-500 leading-relaxed">
      @if ($isRepostTab)
        カレンダーから非表示にするか、Bluesky のリポストも取り消すかを選べます。行を押すと本文の全体を確認できます。
      @else
        スレッドは最初の投稿の日付にまとめて表示しています。親を選ぶと続きの投稿も選ばれ、続きは個別に外せます。行を押すと本文の全体を確認できます。
      @endif
    </p>

    @if ($groupsByDay->isEmpty())
      <div class="glass-card rounded-2xl p-12 text-center">
        <h4 class="text-base font-bold text-slate-300 mb-1">{{ $isRepostTab ? 'リポストはありません' : '投稿はありません' }}</h4>
        <p class="text-xs text-slate-500">この条件で削除できる{{ $isRepostTab ? 'リポスト' : '投稿' }}は見つかりませんでした。</p>
      </div>
    @else
      <form action="{{ route('calendar.manage.destroy', ['handle' => $bskyAccount->handle]) }}" method="POST" id="manageForm" class="js-confirm-form space-y-6 m-0" data-tab="{{ $tab }}">
        @csrf
        @method('DELETE')

        @foreach ($groupsByDay as $dateStr => $groups)
          @php
            $day = \Illuminate\Support\Carbon::parse($dateStr);
          @endphp
          <section class="space-y-2 js-manage-day">
            <div class="flex items-center justify-between gap-2 select-none">
              <h4 class="text-xs font-bold text-slate-300 tracking-wide">
                {{ $day->format('Y年n月j日') }}<span class="text-slate-500 ml-1">({{ $weekdays[$day->dayOfWeek] }})</span>
              </h4>
              <button type="button" class="js-manage-select-day text-[10px] font-bold text-slate-500 hover:text-rose-300 transition cursor-pointer bg-transparent border-0 p-0">
                この日を全選択
              </button>
            </div>

            <div class="glass-card rounded-xl border border-slate-800/80 divide-y divide-slate-800/60 overflow-hidden">
              @foreach ($groups as $group)
                @php
                  $total = count($group['rows']);
                @endphp
                @foreach ($group['rows'] as $index => $row)
                  @php
                    $rowPost = $row['post'];
                    $imageCount = count(\App\Models\Post::extractImages($rowPost->raw_data['post']['embed'] ?? null));
                    $isOtherDay = $rowPost->posted_at->format('Y-m-d') !== $dateStr;
                    $repostAuthor = $isRepostTab ? $rowPost->repost_author : null;
                  @endphp
                  <div class="flex items-start gap-3 px-3 py-2.5 hover:bg-slate-900/40 transition {{ $row['depth'] > 0 ? 'pl-9 bg-slate-950/20' : '' }}">
                    <input type="checkbox"
                           name="post_ids[]"
                           value="{{ $rowPost->id }}"
                           data-uri="{{ $rowPost->uri }}"
                           data-parent-uri="{{ $row['parent_uri'] }}"
                           class="js-manage-check mt-0.5 h-4 w-4 shrink-0 accent-rose-500 cursor-pointer"
                           aria-label="{{ $rowPost->posted_at->format('Y/m/d H:i') }} の{{ $isRepostTab ? 'リポスト' : '投稿' }}を選ぶ">

                    <button type="button" class="js-manage-expand flex-1 min-w-0 text-left bg-transparent border-0 p-0 cursor-pointer">
                      <span class="flex items-center gap-2 text-[10px] text-slate-500 font-bold select-none">
                        <span>{{ $rowPost->posted_at->format($isOtherDay ? 'n/j H:i' : 'H:i') }}</span>
                        @if ($total > 1)
                          <span class="px-1 rounded bg-slate-900 border border-slate-800 text-slate-400">{{ $index + 1 }}/{{ $total }}</span>
                        @endif
                        @if ($imageCount > 0)
                          <span class="flex items-center gap-0.5"><x-icon name="image" class="h-3 w-3" />{{ $imageCount }}</span>
                        @endif
                        @if ($repostAuthor)
                          <span class="flex items-center gap-1 text-emerald-400/80 truncate"><x-icon name="repost" class="h-3 w-3" />{{ '@' . $repostAuthor['handle'] }}</span>
                        @endif
                      </span>
                      <span class="js-manage-text block text-xs text-slate-300 leading-relaxed whitespace-pre-wrap wrap-break-words line-clamp-1 mt-0.5">{{ $rowPost->text !== '' ? $rowPost->text : '（本文なし）' }}</span>
                    </button>

                    @if ($rowPost->bsky_url)
                      <a href="{{ $rowPost->bsky_url }}" target="_blank" rel="noopener noreferrer" class="shrink-0 mt-0.5 text-slate-600 hover:text-sky-400 transition" title="Blueskyで開く">
                        <x-icon name="bluesky" class="h-3.5 w-3.5" />
                      </a>
                    @endif
                  </div>
                @endforeach
              @endforeach
            </div>
          </section>
        @endforeach

        @if ($posts->hasPages())
          <div class="js-manage-pagination pt-2">
            {{ $posts->onEachSide(1)->links() }}
          </div>
        @endif

        <!-- 選択中の件数と削除ボタン (画面下に固定) -->
        <div class="fixed bottom-0 inset-x-0 z-40 bg-slate-950/90 backdrop-blur-md border-t border-slate-800">
          <div class="max-w-3xl mx-auto pl-4 pr-20 lg:pr-4 py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
            <div class="flex items-center gap-3 text-xs select-none">
              <span class="font-bold text-white"><span id="manageCount">0</span>件選択中</span>
              <button type="button" id="manageSelectAll" class="text-[10px] font-bold text-slate-400 hover:text-white transition cursor-pointer bg-transparent border-0 p-0">このページを全選択</button>
              <button type="button" id="manageClear" class="text-[10px] font-bold text-slate-400 hover:text-white transition cursor-pointer bg-transparent border-0 p-0">選択を解除</button>
            </div>
            <div class="flex items-center gap-3 ml-auto">
              <label class="flex items-center gap-1.5 text-[11px] font-bold text-slate-300 cursor-pointer select-none">
                <input type="checkbox" name="also_bluesky" value="1" id="manageAlsoBluesky" class="h-4 w-4 accent-rose-500 cursor-pointer">
                {{ $isRepostTab ? 'Blueskyのリポストも取り消す' : 'Blueskyからも削除' }}
              </label>
              <button type="submit" id="manageSubmit" disabled
                      data-confirm-title="{{ $isRepostTab ? 'リポストの削除' : '投稿の削除' }}"
                      data-confirm-action="削除する"
                      data-confirm-btn-color="bg-rose-600 hover:bg-rose-500"
                      class="px-4 py-2 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold transition cursor-pointer active:scale-[0.98] disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1.5">
                <x-icon name="trash" class="h-3.5 w-3.5" />
                削除する
              </button>
            </div>
          </div>
        </div>
      </form>
    @endif
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const form = document.getElementById('manageForm');
      if (!form) return;

      const isRepostTab = form.dataset.tab === 'reposts';
      const searchForm = document.getElementById('manageSearch');
      const checks = [...form.querySelectorAll('.js-manage-check')];
      const countEl = document.getElementById('manageCount');
      const submitBtn = document.getElementById('manageSubmit');
      const alsoBluesky = document.getElementById('manageAlsoBluesky');

      // 親子関係 (スレッドの続き) を引けるようにする
      const byUri = new Map(checks.map((check) => [check.dataset.uri, check]));
      const childrenOf = new Map();
      checks.forEach((check) => {
        const parentUri = check.dataset.parentUri;
        if (!parentUri || !byUri.has(parentUri)) return;
        if (!childrenOf.has(parentUri)) childrenOf.set(parentUri, []);
        childrenOf.get(parentUri).push(check);
      });

      const leaveLinks = () => [
        ...document.querySelectorAll('a.js-manage-leave'),
        ...form.querySelectorAll('.js-manage-pagination a'),
      ];

      const descendants = (check, found = []) => {
        (childrenOf.get(check.dataset.uri) || []).forEach((child) => {
          found.push(child);
          descendants(child, found);
        });
        return found;
      };

      const confirmMessage = (count) => {
        if (!alsoBluesky.checked) {
          return `${count}件をカレンダーから非表示にしますか？<br><span class='text-[10px] text-slate-500 font-bold'>※Bluesky側はそのままです。削除の直後なら元に戻せます。</span>`;
        }

        let message = isRepostTab
          ? `${count}件のリポストを取り消し、カレンダーからも削除しますか？`
          : `${count}件をBlueskyとカレンダーの両方から削除しますか？`;
        message += `<br><span class='text-[10px] text-rose-400 font-bold'>※Bluesky側も変更され、元に戻せません。</span>`;

        // 親を消して続きを残すと、Bluesky上では返信先が「投稿が見つかりません」になり、スレッドが切れる
        const leavesOrphans = checks.some((check) => check.checked && descendants(check).some((child) => !child.checked));
        if (leavesOrphans) {
          message += `<br><span class='text-[10px] text-amber-400 font-bold'>※続きを残すスレッドがあります。Bluesky上では、残した続きの返信先が「投稿が見つかりません」と表示され、スレッドがそこで切れます。</span>`;
        }

        return message;
      };

      const refresh = () => {
        const count = checks.filter((check) => check.checked).length;
        countEl.textContent = count;
        submitBtn.disabled = count === 0;
        submitBtn.setAttribute('data-confirm-message', confirmMessage(count));

        // 選択中にページ送りやタブ切り替え・検索をすると選択が外れるので、確認してから移る
        const leaveNote = `<br><span class='text-[10px] text-slate-500 font-bold'>※選択はページごとです。先にこのページで削除してください。</span>`;
        leaveLinks().forEach((link) => {
          link.classList.toggle('js-confirm-link', count > 0);
          link.setAttribute('data-confirm-title', '選択が外れます');
          link.setAttribute('data-confirm-message', `${count}件の選択が外れます。このまま移動しますか？${leaveNote}`);
          link.setAttribute('data-confirm-action', '移動する');
        });
        searchForm.classList.toggle('js-confirm-form', count > 0);
        searchForm.setAttribute('data-confirm-message', `${count}件の選択が外れます。このまま検索しますか？${leaveNote}`);
      };

      // 親を付け外しすると、続きもまとめて付け外しする
      checks.forEach((check) => {
        check.addEventListener('change', () => {
          descendants(check).forEach((child) => { child.checked = check.checked; });
          refresh();
        });
      });

      const toggleAll = (targets) => {
        const shouldCheck = !targets.every((check) => check.checked);
        targets.forEach((check) => { check.checked = shouldCheck; });
        refresh();
      };

      form.querySelectorAll('.js-manage-select-day').forEach((button) => {
        button.addEventListener('click', () => {
          toggleAll([...button.closest('.js-manage-day').querySelectorAll('.js-manage-check')]);
        });
      });

      document.getElementById('manageSelectAll').addEventListener('click', () => {
        checks.forEach((check) => { check.checked = true; });
        refresh();
      });

      document.getElementById('manageClear').addEventListener('click', () => {
        checks.forEach((check) => { check.checked = false; });
        refresh();
      });

      alsoBluesky.addEventListener('change', refresh);

      // 行を押すと本文の全体を開閉する
      form.querySelectorAll('.js-manage-expand').forEach((button) => {
        button.addEventListener('click', () => {
          button.querySelector('.js-manage-text').classList.toggle('line-clamp-1');
        });
      });

      // ブラウザの「戻る」でチェックが復元された場合にも件数を合わせる
      refresh();
    });
  </script>
@endsection
