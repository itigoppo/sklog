<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; padding: 40px 20px; }
    .container { max-width: 480px; margin: 0 auto; }
    .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 40px; }
    .logo { text-align: center; margin-bottom: 32px; }
    .logo-name { font-size: 28px; font-weight: 800; color: #38bdf8; background: linear-gradient(to right, #38bdf8, #818cf8, #a855f7); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
    h1 { font-size: 20px; font-weight: 700; color: #f1f5f9; margin: 0 0 8px; }
    p { font-size: 14px; color: #94a3b8; line-height: 1.7; margin: 0 0 16px; }
    .code-box { background: #0f172a; border: 2px dashed #38bdf8; border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0; }
    .code { font-size: 26px; font-weight: 800; letter-spacing: 6px; color: #38bdf8; font-family: monospace; }
    .code-label { font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 8px; }
    .btn { display: block; background: linear-gradient(to right, #0ea5e9, #6366f1); color: #fff !important; text-decoration: none; text-align: center; padding: 14px 24px; border-radius: 10px; font-weight: 700; font-size: 14px; margin: 24px 0; }
    .note { font-size: 12px; color: #475569; border-top: 1px solid #1e293b; padding-top: 16px; margin-top: 24px; }
    .handle { color: #38bdf8; font-weight: 600; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="logo">
        <div class="logo-name">{{ config('app.name', 'SkLoG') }}</div>
      </div>

      <h1>{{ config('app.name', 'SkLoG') }}へご招待します</h1>
      <p><span class="handle">{{ '@' . $entry->handle }}</span> さん、お申し込みいただきありがとうございます。<br>以下の招待コードで登録を完了してください。</p>

      <div class="code-box">
        <div class="code-label">招待コード</div>
        <div class="code">{{ $inviteCode->code }}</div>
      </div>

      <a href="{{ url('/register') }}?code={{ $inviteCode->code }}" class="btn">
        登録ページへ進む ➔
      </a>

      <p style="font-size: 12px; color: #64748b; line-height: 1.7;">
        すでに別のアカウントで SkLoG をご利用中の方は、このボタンではなく、ログイン後の
        <strong>アカウント追加画面</strong>から上の招待コードを入力してください。
        このボタンから進むと、ログイン中のアカウントはすべてログアウトされます。
      </p>

      <div class="note">
        <p style="margin:0">・このコードは1回のみ使用可能です。<br>
        @if ($inviteCode->expires_at)
        ・有効期限は <strong>{{ $inviteCode->expires_at->format('Y-m-d H:i') }} まで</strong> です。<br>
        @endif
        ・登録時のBlueskyハンドルは <strong style="color:#94a3b8">{{ '@' . $entry->handle }}</strong> を使用してください。<br>
        ・本メールに心当たりのない場合は無視してください。</p>
      </div>
    </div>
  </div>
</body>
</html>
