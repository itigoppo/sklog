<!DOCTYPE html>
<html lang="ja" class="h-full bg-slate-950 text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>セキュリティ設定 | {{ config('app.name', 'SkLoG') }} Admin</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <style>body { font-family: 'Outfit', 'Noto Sans JP', sans-serif; background: #020617; }</style>
</head>
<body class="min-h-full p-6">

  <div class="max-w-4xl mx-auto">

    <!-- ヘッダー -->
    <div class="flex items-center justify-between mb-8">
      <h1 class="text-2xl font-extrabold text-white tracking-tight">
        {{ config('app.name', 'SkLoG') }} <span class="text-sky-400">Admin</span> 
        <span class="text-slate-500 font-normal text-lg">/ セキュリティ設定</span>
      </h1>
      <div class="flex items-center gap-3">
        @if (config('services.admin.2fa_enabled'))
          <a href="{{ route('admin.waitlist') }}" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-bold transition flex items-center gap-1.5 duration-200">
            <x-icon name="arrow-left" class="h-3.5 w-3.5" stroke-width="2.5" />
            申請一覧に戻る
          </a>
        @endif
        <form action="{{ route('admin.logout') }}" method="POST">
          @csrf
          <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white text-xs font-bold transition cursor-pointer">
            ログアウト
          </button>
        </form>
      </div>
    </div>

    <!-- セッション警告メッセージ -->
    @if (session('error'))
      <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-2.5 shadow-md">
        <x-icon name="alert" class="h-5 w-5 text-rose-400 shrink-0" />
        <div>
          <span class="font-bold">アクセス制限:</span> {{ session('error') }}
        </div>
      </div>
    @endif
    @if (session('warning'))
      <div class="mb-6 p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs flex items-center gap-2.5 shadow-md">
        <x-icon name="alert" class="h-5 w-5 text-amber-400 shrink-0" />
        <div>
          <span class="font-bold">推奨アクション:</span> {{ session('warning') }}
        </div>
      </div>
    @endif

    <!-- ステータスカード -->
    <div class="mb-8 p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-lg">
      <h2 class="text-sm font-bold text-white uppercase tracking-wider mb-4 flex items-center gap-2">
        🛡️ 現在のセキュリティステータス
      </h2>
      <div class="flex items-center gap-4">
        @if (config('services.admin.2fa_enabled'))
          <div class="p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 shrink-0">
            <x-icon name="shield-check" class="h-8 w-8" stroke-width="2.5" />
          </div>
          <div>
            <h3 class="font-bold text-white text-base">二要素認証 (2FA) が有効です</h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
              ログイン時に管理者パスワードとワンタイムパスワードの両方が正しく要求される状態になっており、セキュリティは極めて強固です。
            </p>
          </div>
        @else
          <div class="p-3 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 shrink-0">
            <x-icon name="alert" class="h-8 w-8" />
          </div>
          <div>
            <h3 class="font-bold text-amber-400 text-base">二要素認証 (2FA) は無効です</h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
              現在、管理画面はパスワードのみで保護されており、総当たり攻撃などのリスクにさらされています。セキュリティ向上のため、以下の手順でセットアップすることを強く推奨します。
            </p>
          </div>
        @endif
      </div>
    </div>

    <!-- セットアップ手順（無効時のみ表示） -->
    @if (!config('services.admin.2fa_enabled') && isset($tempSecret))
      <div class="p-8 rounded-2xl bg-slate-900 border border-slate-800 shadow-xl relative overflow-hidden">
        <div class="absolute -right-24 -top-24 w-48 h-48 bg-sky-500/5 rounded-full blur-3xl pointer-events-none"></div>

        <h2 class="text-base font-extrabold text-white mb-6 flex items-center gap-2">
          ⚙️ 二要素認証 (2FA) の新規セットアップ手順
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
          
          <!-- ステップ1: スキャン -->
          <div class="flex flex-col items-center text-center bg-slate-950/40 p-5 rounded-2xl border border-slate-800/80">
            <span class="w-7 h-7 rounded-full bg-sky-500/10 border border-sky-500/30 text-sky-400 text-sm flex items-center justify-center font-extrabold mb-3">1</span>
            <span class="font-bold text-sm text-white mb-3">認証アプリでスキャン</span>
            <div class="p-2.5 bg-white rounded-2xl inline-flex items-center justify-center shadow-lg mb-3 w-[146px] h-[146px] shrink-0">
              <canvas id="qr-canvas" class="w-[120px] h-[120px] block"></canvas>
            </div>
            <p class="text-[11px] text-slate-400 leading-normal">
              Google Authenticator などの認証アプリを起動し、このQRコードをカメラでスキャンしてください。
            </p>
            <div class="mt-4 pt-4 border-t border-slate-800 w-full">
              <span class="text-[10px] text-slate-500 block mb-1">手動入力用シークレットキー</span>
              <code class="px-2 py-1 rounded bg-slate-900 border border-slate-800 text-sky-400 font-mono text-[11px] font-bold select-all tracking-wider inline-block">{{ $tempSecret }}</code>
            </div>
          </div>

          <!-- ステップ2: 接続検証テスト -->
          <div class="flex flex-col items-center text-center bg-slate-950/40 p-5 rounded-2xl border border-slate-800/80 justify-between">
            <div class="w-full flex flex-col items-center">
              <span class="w-7 h-7 rounded-full bg-sky-500/10 border border-sky-500/30 text-sky-400 text-sm flex items-center justify-center font-extrabold mb-3">2</span>
              <span class="font-bold text-sm text-white mb-3">接続テスト (詰み防止)</span>
              <p class="text-[11px] text-slate-400 mb-4 leading-normal">
                スマホアプリに登録された 6 桁のワンタイムパスワードを入力し、正しく同期できているかテストします。
              </p>
              
              <div class="w-full max-w-[160px] space-y-3">
                <input type="text" id="verify-code" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="123456" 
                       class="w-full px-4 py-2.5 rounded-xl bg-slate-900 border border-slate-800 text-white text-center tracking-[0.4em] font-mono text-base font-bold focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 transition duration-200">
                
                <button type="button" onclick="verify2faSetup()" id="btn-verify"
                        class="w-full py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 active:scale-[0.98] text-white font-bold text-xs transition duration-200 cursor-pointer">
                  テストを実行する
                </button>
              </div>
            </div>

            <!-- 結果表示エリア -->
            <div id="verify-result" class="w-full mt-4 p-3 rounded-xl hidden text-xs leading-normal">
              <!-- JSで動的にメッセージを流し込みます -->
            </div>
          </div>

          <!-- ステップ3: 環境変数設定 (アンロックされるエリア) -->
          <div class="flex flex-col items-center text-center bg-slate-950/40 p-5 rounded-2xl border border-slate-800/80 justify-between relative overflow-hidden">
            <!-- ロック用オーバーレイ -->
            <div id="lock-overlay" class="absolute inset-0 bg-slate-950/90 backdrop-blur-[3px] flex flex-col items-center justify-center p-6 z-10 transition duration-500 select-none">
              <div class="p-3 rounded-2xl bg-slate-900 border border-slate-800 text-slate-400 mb-3 shadow-md">
                <x-icon name="lock" class="h-6 w-6 animate-pulse" stroke-width="2" />
              </div>
              <span class="font-bold text-xs text-slate-300">ステップ2に合格すると解除</span>
              <p class="text-[9px] text-slate-500 mt-2 leading-tight">誤って登録前に設定し、ログインできなくなるリスクを防ぐための安全ロックです。</p>
            </div>

            <div class="w-full flex flex-col items-center">
              <span class="w-7 h-7 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-center justify-center font-extrabold mb-3">3</span>
              <span class="font-bold text-sm text-emerald-400 mb-3">環境変数に適用して有効化</span>
              <p class="text-[11px] text-slate-400 mb-4 leading-normal">
                テストに合格しました！サーバーの `.env` ファイルに以下を追記し、有効化を完了してください。
              </p>
              
              <pre id="env-code" class="w-full p-3 rounded-xl bg-slate-950 border border-slate-800 text-emerald-400 font-mono text-[10px] text-left select-all leading-normal whitespace-pre">ADMIN_2FA_ENABLED=true
ADMIN_2FA_SECRET={{ $tempSecret }}</pre>
            </div>

            <button type="button" onclick="copyEnvCode()" id="btn-copy"
                    class="mt-4 w-full py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 active:scale-[0.98] text-slate-300 hover:text-white text-xs font-bold transition duration-200 flex items-center justify-center gap-1.5 cursor-pointer">
              <x-icon name="copy" class="h-4 w-4" stroke-width="2" />
              <span>設定コードをコピー</span>
            </button>
          </div>

        </div>
      </div>
    @endif

  </div>

  @include('layouts._footer')

  @if (!config('services.admin.2fa_enabled') && isset($tempSecret))
    <script src="{{ asset('js/qrious.min.js') }}"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // QRコード生成
        var qrCanvas = document.getElementById('qr-canvas');
        if (qrCanvas) {
          new QRious({
            element: qrCanvas,
            value: "{!! $qrCodeUrl !!}",
            size: 240, // Retina高精細化
            background: '#ffffff',
            foreground: '#020617',
            level: 'M'
          });
        }
      });

      // 2FA事前検証テスト
      function verify2faSetup() {
        var codeInput = document.getElementById('verify-code');
        var btnVerify = document.getElementById('btn-verify');
        var resultDiv = document.getElementById('verify-result');
        var lockOverlay = document.getElementById('lock-overlay');
        
        var code = codeInput.value.trim();
        if (code.length !== 6 || isNaN(code)) {
          alert('6桁の半角数字を入力してください。');
          return;
        }

        // 送信中表示
        btnVerify.disabled = true;
        btnVerify.innerText = '検証中...';
        resultDiv.className = 'w-full mt-4 p-3 rounded-xl hidden';

        // CSRFトークンの取得
        var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Ajaxポスト
        fetch("{{ route('admin.security.verify') }}", {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
          },
          body: JSON.stringify({ code: code })
        })
        .then(function(response) {
          return response.json();
        })
        .then(function(data) {
          btnVerify.disabled = false;
          btnVerify.innerText = 'テストを実行する';
          resultDiv.classList.remove('hidden');

          if (data.success) {
            // 検証成功
            resultDiv.className = 'w-full mt-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-left';
            resultDiv.innerHTML = '<div class="font-bold flex items-center gap-1">✅ 接続テストに合格しました！</div><div class="mt-1 text-[10px] text-slate-300">スマホアプリとの同期が正常に確認できました。ステップ3のロックが解除されました。環境変数への適用を行ってください。</div>';
            
            // 安全ロックの解除（ふわっと消えるフェードアウト）
            lockOverlay.classList.add('opacity-0', 'pointer-events-none');
            // 500ms 後に完全にDOM上でも影響しないようにする
            setTimeout(function() {
              lockOverlay.style.display = 'none';
            }, 500);
          } else {
            // 検証失敗
            resultDiv.className = 'w-full mt-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-left';
            resultDiv.innerHTML = '<div class="font-bold flex items-center gap-1">❌ 検証に失敗しました</div><div class="mt-1 text-[10px] text-slate-400">' + (data.message || 'ワンタイムパスワードが正しくありません。') + '</div>';
          }
        })
        .catch(function(error) {
          btnVerify.disabled = false;
          btnVerify.innerText = 'テストを実行する';
          alert('検証リクエストの送信中にエラーが発生しました。インターネット接続やセッション状況を確認してください。');
        });
      }

      // 環境変数設定のクリップボードへのコピー
      function copyEnvCode() {
        var envText = document.getElementById('env-code').innerText;
        navigator.clipboard.writeText(envText).then(function() {
          alert('環境変数の設定コードをクリップボードにコピーしました！\nこれを .env ファイルに貼り付けてサーバーを再起動してください。');
        }).catch(function() {
          alert('コピーに失敗しました。お手数ですが、テキストボックスを選択して手動でコピーしてください。');
        });
      }
    </script>
  @endif

</body>
</html>
