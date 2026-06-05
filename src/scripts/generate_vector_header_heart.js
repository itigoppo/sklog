import fs from 'fs';
import path from 'path';
import https from 'https';
import { fileURLToPath } from 'url';
import TextToSVG from 'text-to-svg';
import { Resvg } from '@resvg/resvg-js';

// 実行ディレクトリに依存しない絶対パス解決
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const fontPath = path.resolve(__dirname, 'Outfit-ExtraBold.ttf');
const publicDir = path.resolve(__dirname, '../public');

// Google FontsからTTF直リンク(gstatic)を動的に抽出
function getTtfFontUrl() {
  return new Promise((resolve, reject) => {
    https.get('https://fonts.googleapis.com/css2?family=Outfit:wght@800', (res) => {
      let css = '';
      res.on('data', chunk => css += chunk);
      res.on('end', () => {
        const match = css.match(/url\((https:\/\/fonts\.gstatic\.com\/[^)]+\.ttf)\)/);
        if (match && match[1]) {
          resolve(match[1]);
        } else {
          reject(new Error('Font TTF URL not found in CSS:\n' + css));
        }
      });
    }).on('error', reject);
  });
}

async function downloadFile(url, dest) {
  return new Promise((resolve, reject) => {
    const file = fs.createWriteStream(dest);
    https.get(url, (response) => {
      if (response.statusCode !== 200) {
        reject(new Error(`Failed to download: ${response.statusCode}`));
        return;
      }
      response.pipe(file);
      file.on('finish', () => {
        file.close(resolve);
      });
    }).on('error', (err) => {
      fs.unlink(dest, () => reject(err));
    });
  });
}

async function run() {
  try {
    if (!fs.existsSync(fontPath)) {
      console.log('Fetching TTF URL from Google Fonts API...');
      const ttfUrl = await getTtfFontUrl();
      console.log('TTF URL found:', ttfUrl);
      
      console.log('Downloading Outfit font from fonts.gstatic.com...');
      await downloadFile(ttfUrl, fontPath);
      console.log('Font downloaded successfully.');
    }

    // text-to-svg をロード
    const textToSVG = TextToSVG.loadSync(fontPath);

    // SkLoG の文字列をOutfit-ExtraBold(800)に基づいてアウトライン化
    const dPath = textToSVG.getD('SkLoG', {
      x: 0,
      y: 0,
      fontSize: 124,
      anchor: 'left baseline',
    });

    console.log('Generated D-Path length:', dPath.length);

    // ハートの共通パス (viewBox 0 0 24 24 を想定した高精度ハート)
    const heartPathD = "M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z";

    // 1. 本命デザイン (文字のみ・ロゴなし + ハートアクセント)
    const svgContent = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1500 500" width="1500" height="500">
  <defs>
    <!-- 背景用の流麗なグラデーション -->
    <linearGradient id="bg-grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#020617" />
      <stop offset="40%" stop-color="#0b1329" />
      <stop offset="100%" stop-color="#1e1b4b" />
    </linearGradient>

    <!-- タイトルテキスト用の美しい3色グラデーション -->
    <linearGradient id="text-grad" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#38bdf8" />
      <stop offset="50%" stop-color="#818cf8" />
      <stop offset="100%" stop-color="#c084fc" />
    </linearGradient>

    <!-- ハートアクセント用のリッチなピンク・パープルグラデーション -->
    <linearGradient id="heart-grad" x1="0%" y1="100%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#f43f5e" />
      <stop offset="50%" stop-color="#ec4899" />
      <stop offset="100%" stop-color="#d946ef" />
    </linearGradient>

    <!-- 装飾用のグロー効果グラデーション -->
    <radialGradient id="glow-left" cx="20%" cy="30%" r="50%">
      <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.15" />
      <stop offset="100%" stop-color="#38bdf8" stop-opacity="0" />
    </radialGradient>
    <radialGradient id="glow-right" cx="80%" cy="70%" r="60%">
      <stop offset="0%" stop-color="#a855f7" stop-opacity="0.1" />
      <stop offset="100%" stop-color="#a855f7" stop-opacity="0" />
    </radialGradient>

    <style>
      <![CDATA[
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@500;800&display=swap');
      ]]>
    </style>
  </defs>

  <!-- 背景 -->
  <rect width="1500" height="500" fill="url(#bg-grad)" />

  <!-- グロー効果の装飾円 -->
  <rect width="1500" height="500" fill="url(#glow-left)" />
  <rect width="1500" height="500" fill="url(#glow-right)" />

  <!-- 背景のアートワーク：うっすら巨大な蝶のロゴ -->
  <g transform="translate(-100, -100) scale(18)" opacity="0.18">
    <path d="M12 10.3c-.6-1.5-1.8-3-3.5-3.8C6.3 5.4 3.5 5.8 2.2 7c-1.3 1.2-1.5 4 .2 6.6.9 1.4 2.3 2.8 4.1 3.8.5.3 1.1.5 1.7.7-1 1.5-2 3-2 4.1 0 .6.4 1 1 1 .6 0 1.2-.4 1.8-1 1.2-1.2 2.2-2.8 3-4.4.8 1.6 1.8 3.2 3 4.4.6.6 1.2 1 1.8 1 .6 0 1-.4 1-1 0-1.1-1-2.6-2-4.1.6-.2 1.2-.4 1.7-.7 1.8-1 3.2-2.4 4.1-3.8 1.7-2.6 1.5-5.4.2-6.6-1.3-1.2-4.1-1.6-6.3-.5-1.7.8-2.9 2.3-3.5 3.8z" fill="#ffffff" />
  </g>

  <!-- 背景の蝶の周りに漂うハートの浮遊アクセント（浮遊感と魔法のきらめき） -->
  <g opacity="0.25">
    <!-- 蝶の左上 -->
    <path d="${heartPathD}" transform="translate(150, 80) scale(1.5) rotate(-15)" fill="url(#heart-grad)" />
    <!-- 蝶の右下（アバターの少し上） -->
    <path d="${heartPathD}" transform="translate(360, 240) scale(1.2) rotate(20)" fill="url(#heart-grad)" />
    <!-- 蝶の左下 -->
    <path d="${heartPathD}" transform="translate(80, 280) scale(0.9) rotate(-10)" fill="url(#heart-grad)" />
  </g>

  <!-- 抽象的な幾何学グリッドライン -->
  <g stroke="#ffffff" stroke-opacity="0.02" stroke-width="1">
    <line x1="0" y1="100" x2="1500" y2="100" />
    <line x1="0" y1="200" x2="1500" y2="200" />
    <line x1="0" y1="300" x2="1500" y2="300" />
    <line x1="0" y1="400" x2="1500" y2="400" />
    <line x1="300" y1="0" x2="300" y2="500" />
    <line x1="600" y1="0" x2="600" y2="500" />
    <line x1="900" y1="0" x2="900" y2="500" />
    <line x1="1200" y1="0" x2="1200" y2="500" />
  </g>

  <!-- メインコンテンツグループ（中央やや右〜右側に配置） -->
  <g transform="translate(600, 265)">
    <!-- サイトタイトル (ベクターパス化された SkLoG) -->
    <path d="${dPath}" fill="url(#text-grad)" transform="translate(0, -20)" />

    <!-- 文字のすぐ右上のキュートなアクセントハート -->
    <path d="${heartPathD}" transform="translate(420, -115) scale(1.8) rotate(15)" fill="url(#heart-grad)" opacity="0.95" />

    <!-- サブタイトル -->
    <text x="5" y="35" font-family="'Outfit', 'Segoe UI', -apple-system, sans-serif" font-weight="500" font-size="22px" fill="#94a3b8" letter-spacing="6px">Bluesky Archiver &amp; Calendar</text>
  </g>

</svg>`;

    // 2. 別案デザイン (蝶のグラデーションロゴあり + ハートアクセント)
    const svgGradContent = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1500 500" width="1500" height="500">
  <defs>
    <!-- 背景用の流麗なグラデーション -->
    <linearGradient id="bg-grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#020617" />
      <stop offset="40%" stop-color="#0b1329" />
      <stop offset="100%" stop-color="#1e1b4b" />
    </linearGradient>

    <!-- タイトルテキスト用の美しい3色グラデーション -->
    <linearGradient id="text-grad" x1="0%" y1="0%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#38bdf8" />
      <stop offset="50%" stop-color="#818cf8" />
      <stop offset="100%" stop-color="#c084fc" />
    </linearGradient>

    <!-- ハートアクセント用のリッチなピンク・パープルグラデーション -->
    <linearGradient id="heart-grad" x1="0%" y1="100%" x2="100%" y2="0%">
      <stop offset="0%" stop-color="#f43f5e" />
      <stop offset="50%" stop-color="#ec4899" />
      <stop offset="100%" stop-color="#d946ef" />
    </linearGradient>

    <!-- 装飾用のグロー効果グラデーション -->
    <radialGradient id="glow-left" cx="20%" cy="30%" r="50%">
      <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.15" />
      <stop offset="100%" stop-color="#38bdf8" stop-opacity="0" />
    </radialGradient>
    <radialGradient id="glow-right" cx="80%" cy="70%" r="60%">
      <stop offset="0%" stop-color="#a855f7" stop-opacity="0.1" />
      <stop offset="100%" stop-color="#a855f7" stop-opacity="0" />
    </radialGradient>

    <style>
      <![CDATA[
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@500;800&display=swap');
      ]]>
    </style>
  </defs>

  <!-- 背景 -->
  <rect width="1500" height="500" fill="url(#bg-grad)" />

  <!-- グロー効果の装飾円 -->
  <rect width="1500" height="500" fill="url(#glow-left)" />
  <rect width="1500" height="500" fill="url(#glow-right)" />

  <!-- 背景のアートワーク：うっすら巨大な蝶のロゴ -->
  <g transform="translate(-100, -100) scale(18)" opacity="0.18">
    <path d="M12 10.3c-.6-1.5-1.8-3-3.5-3.8C6.3 5.4 3.5 5.8 2.2 7c-1.3 1.2-1.5 4 .2 6.6.9 1.4 2.3 2.8 4.1 3.8.5.3 1.1.5 1.7.7-1 1.5-2 3-2 4.1 0 .6.4 1 1 1 .6 0 1.2-.4 1.8-1 1.2-1.2 2.2-2.8 3-4.4.8 1.6 1.8 3.2 3 4.4.6.6 1.2 1 1.8 1 .6 0 1-.4 1-1 0-1.1-1-2.6-2-4.1.6-.2 1.2-.4 1.7-.7 1.8-1 3.2-2.4 4.1-3.8 1.7-2.6 1.5-5.4.2-6.6-1.3-1.2-4.1-1.6-6.3-.5-1.7.8-2.9 2.3-3.5 3.8z" fill="#ffffff" />
  </g>

  <!-- 背景の蝶の周りに漂うハートの浮遊アクセント -->
  <g opacity="0.25">
    <path d="${heartPathD}" transform="translate(150, 80) scale(1.5) rotate(-15)" fill="url(#heart-grad)" />
    <path d="${heartPathD}" transform="translate(360, 240) scale(1.2) rotate(20)" fill="url(#heart-grad)" />
    <path d="${heartPathD}" transform="translate(80, 280) scale(0.9) rotate(-10)" fill="url(#heart-grad)" />
  </g>

  <!-- 抽象的な幾何学グリッドライン -->
  <g stroke="#ffffff" stroke-opacity="0.02" stroke-width="1">
    <line x1="0" y1="100" x2="1500" y2="100" />
    <line x1="0" y1="200" x2="1500" y2="200" />
    <line x1="0" y1="300" x2="1500" y2="300" />
    <line x1="0" y1="400" x2="1500" y2="400" />
    <line x1="300" y1="0" x2="300" y2="500" />
    <line x1="600" y1="0" x2="600" y2="500" />
    <line x1="900" y1="0" x2="900" y2="500" />
    <line x1="1200" y1="0" x2="1200" y2="500" />
  </g>

  <!-- メインコンテンツグループ（中央やや右〜右側に配置） -->
  <g transform="translate(600, 240)">
    <!-- 小さなアイコンとしての蝶 -->
    <g transform="translate(0, -110) scale(2.8)" opacity="0.9">
      <path d="M12 10.3c-.6-1.5-1.8-3-3.5-3.8C6.3 5.4 3.5 5.8 2.2 7c-1.3 1.2-1.5 4 .2 6.6.9 1.4 2.3 2.8 4.1 3.8.5.3 1.1.5 1.7.7-1 1.5-2 3-2 4.1 0 .6.4 1 1 1 .6 0 1.2-.4 1.8-1 1.2-1.2 2.2-2.8 3-4.4.8 1.6 1.8 3.2 3 4.4.6.6 1.2 1 1.8 1 .6 0 1-.4 1-1 0-1.1-1-2.6-2-4.1.6-.2 1.2-.4 1.7-.7 1.8-1 3.2-2.4 4.1-3.8 1.7-2.6 1.5-5.4.2-6.6-1.3-1.2-4.1-1.6-6.3-.5-1.7.8-2.9 2.3-3.5 3.8z" fill="url(#text-grad)" />
    </g>

    <!-- 小さな蝶アイコンの斜め上に小さくきらめくハート -->
    <path d="${heartPathD}" transform="translate(62, -145) scale(0.6) rotate(15)" fill="url(#heart-grad)" opacity="0.95" />

    <!-- サイトタイトル (ベクターパス化された SkLoG) -->
    <path d="${dPath}" fill="url(#text-grad)" transform="translate(80, -20)" />

    <!-- 文字のすぐ右上のキュートなアクセントハート -->
    <path d="${heartPathD}" transform="translate(500, -115) scale(1.8) rotate(15)" fill="url(#heart-grad)" opacity="0.95" />

    <!-- サブタイトル -->
    <text x="85" y="35" font-family="'Outfit', 'Segoe UI', -apple-system, sans-serif" font-weight="500" font-size="22px" fill="#94a3b8" letter-spacing="6px">Bluesky Archiver &amp; Calendar</text>
  </g>

</svg>`;

    // 1. 本命デザインの保存
    const svgDest = path.join(publicDir, 'bluesky_header_heart.svg');
    fs.writeFileSync(svgDest, svgContent);
    console.log(`Vector SVG saved successfully at ${svgDest}`);

    // 2. 別案デザインの保存
    const svgGradDest = path.join(publicDir, 'bluesky_header_heart_grad.svg');
    fs.writeFileSync(svgGradDest, svgGradContent);
    console.log(`Vector SVG saved successfully at ${svgGradDest}`);

    // 両方を resvg で高精度 PNG にラスタライズする
    const renderTargets = [
      { svg: svgContent, dest: path.join(publicDir, 'bluesky_header_heart.png') },
      { svg: svgGradContent, dest: path.join(publicDir, 'bluesky_header_heart_grad.png') }
    ];

    for (const target of renderTargets) {
      console.log(`Rendering SVG to ${target.dest} using resvg...`);
      const resvg = new Resvg(target.svg, {
        font: {
          fontFiles: [fontPath], // サブタイトル用のフォントをロード
          loadSystemFonts: true,
          defaultFontFamily: 'Outfit',
        },
      });

      const pngData = resvg.render();
      const pngBuffer = pngData.asPng();

      fs.writeFileSync(target.dest, pngBuffer);
      console.log(`PNG rendered successfully at ${target.dest}!`);
    }

    // 一時フォントファイルの削除
    if (fs.existsSync(fontPath)) {
      fs.unlinkSync(fontPath);
    }
  } catch (err) {
    console.error('Error during vector rendering:', err);
    if (fs.existsSync(fontPath)) {
      fs.unlinkSync(fontPath);
    }
    process.exit(1);
  }
}

run();
