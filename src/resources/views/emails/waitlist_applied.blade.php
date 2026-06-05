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
    .info-box { background: #0f172a; border: 1px solid #334155; border-radius: 12px; padding: 20px; margin: 24px 0; }
    .info-row { display: flex; gap: 12px; margin-bottom: 10px; font-size: 13px; }
    .info-row:last-child { margin-bottom: 0; }
    .info-label { color: #64748b; min-width: 80px; }
    .info-value { color: #e2e8f0; font-weight: 600; word-break: break-all; }
    .handle { color: #38bdf8; }
    .btn { display: block; background: linear-gradient(to right, #0ea5e9, #6366f1); color: #fff !important; text-decoration: none; text-align: center; padding: 14px 24px; border-radius: 10px; font-weight: 700; font-size: 14px; margin: 24px 0; }
    .note { font-size: 12px; color: #475569; border-top: 1px solid #334155; padding-top: 16px; margin-top: 8px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="logo">
        <div class="logo-name">{{ config('app.name', 'SkLoG') }}</div>
      </div>

      <h1>新しい参加申請があります</h1>
      <p>{{ config('app.name') }} に新しい参加申請が届きました。管理画面から招待メールを送信してください。</p>

      <div class="info-box">
        <div class="info-row">
          <span class="info-label">メール</span>
          <span class="info-value">{{ $entry->email }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">ハンドル</span>
          <span class="info-value handle">{{ '@' . $entry->handle }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">申請日時</span>
          <span class="info-value">{{ $entry->created_at->format('Y/m/d H:i') }}</span>
        </div>
      </div>

      <a href="{{ url('/admin/waitlist') }}" class="btn">
        管理画面で確認する ➔
      </a>

      <div class="note">
        <p style="margin:0">このメールは {{ config('app.name') }} の管理者通知です。</p>
      </div>
    </div>
  </div>
</body>
</html>
