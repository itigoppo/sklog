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

function downloadFile(url, dest) {
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

    // SkLoG の文字列をOutfit-ExtraBold(800)に基づいて完璧なSVGベクターパスにアウトライン化
    const dPath = textToSVG.getD('SkLoG', {
      x: 0,
      y: 0,
      fontSize: 124,
      anchor: 'left baseline',
    });

    console.log('Generated D-Path length:', dPath.length);

    // 1. 本命デザイン (文字のみ・ロゴなし)
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

    <!-- 装飾用のグロー効果グラデーション -->
    <radialGradient id="glow-left" cx="20%" cy="30%" r="50%">
      <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.15" />
      <stop offset="100%" stop-color="#38bdf8" stop-opacity="0" />
    </radialGradient>
    <radialGradient id="glow-right" cx="80%" cy="70%" r="60%">
      <stop offset="0%" stop-color="#a855f7" stop-opacity="0.1" />
      <stop offset="100%" stop-color="#a855f7" stop-opacity="0" />
    </radialGradient>

    <!-- フォントのインポートとスタイル定義 -->
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

  <!-- 背景のアートワーク：うっすら巨大な蝶のロゴ（左側、不透明度を引き上げて認識しやすく調整） -->
  <g transform="translate(-100, -100) scale(18)" opacity="0.18">
    <path d="M12 10.3c-.6-1.5-1.8-3-3.5-3.8C6.3 5.4 3.5 5.8 2.2 7c-1.3 1.2-1.5 4 .2 6.6.9 1.4 2.3 2.8 4.1 3.8.5.3 1.1.5 1.7.7-1 1.5-2 3-2 4.1 0 .6.4 1 1 1 .6 0 1.2-.4 1.8-1 1.2-1.2 2.2-2.8 3-4.4.8 1.6 1.8 3.2 3 4.4.6.6 1.2 1 1.8 1 .6 0 1-.4 1-1 0-1.1-1-2.6-2-4.1.6-.2 1.2-.4 1.7-.7 1.8-1 3.2-2.4 4.1-3.8 1.7-2.6 1.5-5.4.2-6.6-1.3-1.2-4.1-1.6-6.3-.5-1.7.8-2.9 2.3-3.5 3.8z" fill="#ffffff" />
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

  <!-- メインコンテンツグループ（アバター被りを防ぐため、中央やや右〜右側に配置） -->
  <g transform="translate(600, 265)">
    <!-- サイトタイトル (ベクターパス化された SkLoG、グラデーションを確実に100%再現！) -->
    <path d="${dPath}" fill="url(#text-grad)" transform="translate(0, -20)" />

    <!-- サブタイトル (Bluesky Archiver & Calendar) -->
    <text x="5" y="35" font-family="'Outfit', 'Segoe UI', -apple-system, sans-serif" font-weight="500" font-size="22px" fill="#94a3b8" letter-spacing="6px">Bluesky Archiver &amp; Calendar</text>
  </g>

</svg>`;

    // 2. 別案デザイン (蝶のグラデーションロゴあり)
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

    <!-- 装飾用のグロー効果グラデーション -->
    <radialGradient id="glow-left" cx="20%" cy="30%" r="50%">
      <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.15" />
      <stop offset="100%" stop-color="#38bdf8" stop-opacity="0" />
    </radialGradient>
    <radialGradient id="glow-right" cx="80%" cy="70%" r="60%">
      <stop offset="0%" stop-color="#a855f7" stop-opacity="0.1" />
      <stop offset="100%" stop-color="#a855f7" stop-opacity="0" />
    </radialGradient>

    <!-- フォントのインポートとスタイル定義 -->
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

  <!-- 背景のアートワーク：うっすら巨大な蝶のロゴ（左側、不透明度を引き上げて認識しやすく調整） -->
  <g transform="translate(-100, -100) scale(18)" opacity="0.18">
    <path d="M12 10.3c-.6-1.5-1.8-3-3.5-3.8C6.3 5.4 3.5 5.8 2.2 7c-1.3 1.2-1.5 4 .2 6.6.9 1.4 2.3 2.8 4.1 3.8.5.3 1.1.5 1.7.7-1 1.5-2 3-2 4.1 0 .6.4 1 1 1 .6 0 1.2-.4 1.8-1 1.2-1.2 2.2-2.8 3-4.4.8 1.6 1.8 3.2 3 4.4.6.6 1.2 1 1.8 1 .6 0 1-.4 1-1 0-1.1-1-2.6-2-4.1.6-.2 1.2-.4 1.7-.7 1.8-1 3.2-2.4 4.1-3.8 1.7-2.6 1.5-5.4.2-6.6-1.3-1.2-4.1-1.6-6.3-.5-1.7.8-2.9 2.3-3.5 3.8z" fill="#ffffff" />
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

  <!-- メインコンテンツグループ（アバター被りを防ぐため、中央やや右〜右側に配置。アバターは左下に配置されるため） -->
  <g transform="translate(600, 240)">
    <!-- 小さなアイコンとしての蝶（サイトタイトルの上に綺麗にレイアウト） -->
    <g transform="translate(0, -110) scale(2.8)" opacity="0.9">
      <path d="M12 10.3c-.6-1.5-1.8-3-3.5-3.8C6.3 5.4 3.5 5.8 2.2 7c-1.3 1.2-1.5 4 .2 6.6.9 1.4 2.3 2.8 4.1 3.8.5.3 1.1.5 1.7.7-1 1.5-2 3-2 4.1 0 .6.4 1 1 1 .6 0 1.2-.4 1.8-1 1.2-1.2 2.2-2.8 3-4.4.8 1.6 1.8 3.2 3 4.4.6.6 1.2 1 1.8 1 .6 0 1-.4 1-1 0-1.1-1-2.6-2-4.1.6-.2 1.2-.4 1.7-.7 1.8-1 3.2-2.4 4.1-3.8 1.7-2.6 1.5-5.4.2-6.6-1.3-1.2-4.1-1.6-6.3-.5-1.7.8-2.9 2.3-3.5 3.8z" fill="url(#text-grad)" />
    </g>

    <!-- サイトタイトル (ベクターパス化された SkLoG) -->
    <path d="${dPath}" fill="url(#text-grad)" transform="translate(80, -20)" />

    <!-- サブタイトル (Bluesky Archiver & Calendar) -->
    <text x="85" y="35" font-family="'Outfit', 'Segoe UI', -apple-system, sans-serif" font-weight="500" font-size="22px" fill="#94a3b8" letter-spacing="6px">Bluesky Archiver &amp; Calendar</text>
  </g>

</svg>`;

    // 1. 本命デザインの保存
    const svgDest = path.join(publicDir, 'bluesky_header.svg');
    fs.writeFileSync(svgDest, svgContent);
    console.log(`Vector SVG saved successfully at ${svgDest}`);

    // 2. 別案デザインの保存
    const svgGradDest = path.join(publicDir, 'bluesky_header_grad.svg');
    fs.writeFileSync(svgGradDest, svgGradContent);
    console.log(`Vector SVG saved successfully at ${svgGradDest}`);

    // 両方を resvg で高精度 PNG にラスタライズする
    const renderTargets = [
      { svg: svgContent, dest: path.join(publicDir, 'bluesky_header.png') },
      { svg: svgGradContent, dest: path.join(publicDir, 'bluesky_header_grad.png') }
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
