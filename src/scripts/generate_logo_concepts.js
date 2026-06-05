import fs from 'fs';
import path from 'path';
import https from 'https';
import { fileURLToPath } from 'url';
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
      console.log('Downloading Outfit font...');
      await downloadFile(ttfUrl, fontPath);
      console.log('Font downloaded successfully.');
    }

    // 元の美しくデフォルメされた完璧な蝶のソリッドパス (無傷・無改変)
    const originalButterflyPath = "M12 10.3c-.6-1.5-1.8-3-3.5-3.8C6.3 5.4 3.5 5.8 2.2 7c-1.3 1.2-1.5 4 .2 6.6.9 1.4 2.3 2.8 4.1 3.8.5.3 1.1.5 1.7.7-1 1.5-2 3-2 4.1 0 .6.4 1 1 1 .6 0 1.2-.4 1.8-1 1.2-1.2 2.2-2.8 3-4.4.8 1.6 1.8 3.2 3 4.4.6.6 1.2 1 1.8 1 .6 0 1-.4 1-1 0-1.1-1-2.6-2-4.1.6-.2 1.2-.4 1.7-.7 1.8-1 3.2-2.4 4.1-3.8 1.7-2.6 1.5-5.4.2-6.6-1.3-1.2-4.1-1.6-6.3-.5-1.7.8-2.9 2.3-3.5 3.8z";

    // -----------------------------------------------------------------
    // コンセプト A: 【右下の「足（尾）」のさらに下（先端側）へ完璧に白抜く、最終インプリントデザイン】
    // - 「もうちょっと下」というミリ単位の卓越したデザイン指示に完全対応！
    // - 蝶の右下に長く伸びる「白い足（尾）」のソリッドなフォルムの、
    //   ちょうど真ん中〜先端付近（x=14.25, y=18.35）の完全にスッポリ収まるベストポジションへマッピングしました。
    // -----------------------------------------------------------------
    const conceptLogTypoSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="512" height="512">
  <defs>
    <!-- ネオン系の上品な3色グラデーション -->
    <linearGradient id="su-neon-grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#38bdf8" />     <!-- スカイブルー -->
      <stop offset="60%" stop-color="#818cf8" />     <!-- インディゴ -->
      <stop offset="100%" stop-color="#c084fc" />    <!-- パープル -->
    </linearGradient>

    <style>
      @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@800&amp;display=swap');
      .log-tail-embed {
        font-family: 'Outfit', -apple-system, sans-serif;
        font-weight: 800;
        font-size: 1.55px;
        letter-spacing: 0.08px;
      }
    </style>
  </defs>

  <!-- 1. 元の完璧な蝶 of シルエット -->
  <path d="${originalButterflyPath}" fill="url(#su-neon-grad)" />

  <!-- 2. 右下の「足（尾）」の中に、背景の漆黒（#020617）でシャープに白抜き刻印された「log」 -->
  <!-- ご指摘通り、さらに「もうちょっと下（右下方向）」へスライドさせ、足の真ん中に完璧に配置 -->
  <text x="14.25" y="18.35" fill="#020617" class="log-tail-embed" transform="rotate(45 14.25 18.35)" opacity="0.98">log</text>
</svg>`;

    // -----------------------------------------------------------------
    // コンセプト B: 【バリエーション：少し太めの「log」をさらに下へ刻印したバージョン】
    // -----------------------------------------------------------------
    const conceptLogDotsSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="512" height="512">
  <defs>
    <linearGradient id="su-neon-grad-2" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#38bdf8" />
      <stop offset="60%" stop-color="#818cf8" />
      <stop offset="100%" stop-color="#c084fc" />
    </linearGradient>
    
    <style>
      @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@800&amp;display=swap');
      .log-tail-embed-bold {
        font-family: 'Outfit', -apple-system, sans-serif;
        font-weight: 800;
        font-size: 1.7px;
        letter-spacing: 0.1px;
      }
    </style>
  </defs>

  <!-- 1. 元の完璧な蝶 of シルエット -->
  <path d="${originalButterflyPath}" fill="url(#su-neon-grad-2)" />

  <!-- 右下の足（尾）のさらに下部（先端側）の中に刻印された、視認性を高めた「log」 -->
  <text x="14.1" y="18.3" fill="#020617" class="log-tail-embed-bold" transform="rotate(45 14.1 18.3)" opacity="0.98">log</text>
</svg>`;

    // -----------------------------------------------------------------
    // 新アセット: 【Favicon_concept (青背景 ＋ 白い蝶の「右足」のさらに下部の中に青の「log」を完璧に白抜きマッピング)】
    // - ご指摘の通り、位置をさらにもう少し右下（先端側）へ調整！
    // - 蝶本体と「log」テキストを同一スケール空間（scale 0.5833）で描画し、
    //   白くて長い「右下の足（尾）」の【完璧な中央・先端のソリッド面】（x=14.25, y=18.35）へ、
    //   背景の青グラデーションと同色の「log」が45度の傾きでスッポリと収まるように精密にマッピングしました！
    // -----------------------------------------------------------------
    const faviconConceptSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 26 26" width="26" height="26">
  <defs>
    <!-- 元のfaviconと同様の美しい青・インディゴのグラデーション背景 -->
    <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#38bdf8" />
      <stop offset="100%" stop-color="#4f46e5" />
    </linearGradient>
    
    <style>
      @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@800&amp;display=swap');
      .fav-log-tail-embed {
        font-family: 'Outfit', -apple-system, sans-serif;
        font-weight: 800;
        font-size: 1.55px;
        letter-spacing: 0.08px;
      }
    </style>
  </defs>

  <!-- 角丸背景 -->
  <rect width="26" height="26" rx="7.5" fill="url(#grad)" />

  <!-- 中央の蝶 ＆ 右足の真ん中に完璧に収まる白抜き「log」 -->
  <g transform="translate(6, 6)">
    <g transform="translate(0, -0.7) scale(0.5833)">
      <!-- 蝶本体 -->
      <path d="${originalButterflyPath}" fill="#ffffff" />
      
      <!-- 白く長い「右下の足（尾）」のさらに下側（先端側）に、背景の青グラデーションと同色でスッポリと美しく刻印！ -->
      <text x="14.25" y="18.35" fill="url(#grad)" class="fav-log-tail-embed" transform="rotate(45 14.25 18.35)" opacity="0.98">log</text>
    </g>
  </g>
</svg>`;

    // 保存先パス
    const bookSvgDest = path.join(publicDir, 'logo_concept_book.svg');
    const bookPngDest = path.join(publicDir, 'logo_concept_book.png');
    const infSvgDest = path.join(publicDir, 'logo_concept_infinity.svg');
    const infPngDest = path.join(publicDir, 'logo_concept_infinity.png');
    const favSvgDest = path.join(publicDir, 'favicon_concept.svg');
    const favPngDest = path.join(publicDir, 'favicon_concept.png');

    // SVGファイルの書き出し
    fs.writeFileSync(bookSvgDest, conceptLogTypoSvg);
    fs.writeFileSync(infSvgDest, conceptLogDotsSvg);
    fs.writeFileSync(favSvgDest, faviconConceptSvg);
    console.log(`All Homage SVG concepts (Fixed tail embed coordinate) saved successfully.`);

    // resvgを使ってPNG画像を生成
    const renderTargets = [
      { svg: conceptLogTypoSvg, dest: bookPngDest, size: 512 },
      { svg: conceptLogDotsSvg, dest: infPngDest, size: 512 },
      { svg: faviconConceptSvg, dest: favPngDest, size: 192 }
    ];

    for (const target of renderTargets) {
      console.log(`Rendering Solid SVG to ${target.dest}...`);
      const resvg = new Resvg(target.svg, {
        font: {
          fontFiles: [fontPath], // サブタイトル用のフォントをロード
          loadSystemFonts: true,
          defaultFontFamily: 'Outfit',
        },
        fitTo: {
          mode: 'width',
          value: target.size
        }
      });

      const pngData = resvg.render();
      const pngBuffer = pngData.asPng();

      fs.writeFileSync(target.dest, pngBuffer);
    }

    // 一時フォントファイルの削除
    if (fs.existsSync(fontPath)) {
      fs.unlinkSync(fontPath);
    }

    console.log('All minimal butterfly + tail embedded "log" concept logos generated successfully with updated coordinates!');
  } catch (err) {
    console.error('Error generating homage logos:', err);
    if (fs.existsSync(fontPath)) {
      fs.unlinkSync(fontPath);
    }
    process.exit(1);
  }
}

run();
