import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { Resvg } from '@resvg/resvg-js';

// 実行ディレクトリに依存しない絶対パス解決
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const publicDir = path.resolve(__dirname, '../public');

async function run() {
  try {
    // 【蝶自体は元の完璧なシルエットロゴのパスをそのまま使用】
    const originalButterflyPath = "M12 10.3c-.6-1.5-1.8-3-3.5-3.8C6.3 5.4 3.5 5.8 2.2 7c-1.3 1.2-1.5 4 .2 6.6.9 1.4 2.3 2.8 4.1 3.8.5.3 1.1.5 1.7.7-1 1.5-2 3-2 4.1 0 .6.4 1 1 1 .6 0 1.2-.4 1.8-1 1.2-1.2 2.2-2.8 3-4.4.8 1.6 1.8 3.2 3 4.4.6.6 1.2 1 1.8 1 .6 0 1-.4 1-1 0-1.1-1-2.6-2-4.1.6-.2 1.2-.4 1.7-.7 1.8-1 3.2-2.4 4.1-3.8 1.7-2.6 1.5-5.4.2-6.6-1.3-1.2-4.1-1.6-6.3-.5-1.7.8-2.9 2.3-3.5 3.8z";

    // 1. 新ロゴ (SVG)
    // 蝶自体の完璧なシルエットはそのまま維持し、
    // 右上にそっと寄り添うように「"（〃）」の形のスタイリッシュな漫画記法（漫符）を添えました。
    // これにより、蝶自体が愛らしく照れて鼓動が高鳴っている（ハートに寄り添った）ような印象を、極めてミニマルかつクリーンに表現しています。
    const logoSvgContent = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="512" height="512">
  <defs>
    <!-- ネオン系のダークなサイトデザインに完全に調和する上品な3色グラデーション -->
    <linearGradient id="logo-neon-grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#38bdf8" />     <!-- スカイブルー -->
      <stop offset="60%" stop-color="#818cf8" />     <!-- インディゴ -->
      <stop offset="100%" stop-color="#c084fc" />    <!-- パープル -->
    </linearGradient>
  </defs>

  <!-- 1. 元の完璧な蝶のシルエット -->
  <path d="${originalButterflyPath}" fill="url(#logo-neon-grad)" />

  <!-- 2. 右上に添えられた「"（〃）」の可愛いアクセントライン (同色グラデーションで統一感と高級感を維持) -->
  <g stroke="url(#logo-neon-grad)" stroke-width="1.5" stroke-linecap="round" opacity="0.95">
    <!-- 1つめの斜め線 -->
    <line x1="18.2" y1="6.8" x2="19.7" y2="3.8" />
    <!-- 2つめの斜め線 -->
    <line x1="20.7" y1="7.8" x2="22.2" y2="4.8" />
  </g>
</svg>`;

    // 2. Favicon用 (192x192)
    // 極小サイズでも「"」のアクセントが綺麗に識別できるよう、ストローク幅をわずかに太め（1.8）に最適化
    const faviconSvgContent = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="192" height="192">
  <defs>
    <linearGradient id="fav-neon-grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#38bdf8" />
      <stop offset="60%" stop-color="#818cf8" />
      <stop offset="100%" stop-color="#c084fc" />
    </linearGradient>
  </defs>

  <!-- 元の完璧な蝶のシルエット -->
  <path d="${originalButterflyPath}" fill="url(#fav-neon-grad)" />

  <!-- 右上の「"（〃）」のアクセントライン -->
  <g stroke="url(#fav-neon-grad)" stroke-width="1.8" stroke-linecap="round" opacity="0.95">
    <line x1="18.2" y1="6.8" x2="19.7" y2="3.8" />
    <line x1="20.7" y1="7.8" x2="22.2" y2="4.8" />
  </g>
</svg>`;

    // 保存先パス
    const logoSvgDest = path.join(publicDir, 'logo_heart.svg');
    const logoPngDest = path.join(publicDir, 'logo_heart.png');
    const faviconSvgDest = path.join(publicDir, 'favicon_heart.svg');
    const faviconPngDest = path.join(publicDir, 'favicon_heart.png');

    // SVGファイルの書き出し
    fs.writeFileSync(logoSvgDest, logoSvgContent);
    fs.writeFileSync(faviconSvgDest, faviconSvgContent);
    console.log(`SVG logos saved successfully at:\n- ${logoSvgDest}\n- ${faviconSvgDest}`);

    // resvgを使ってPNG画像を生成
    const renderTargets = [
      { svg: logoSvgContent, dest: logoPngDest, size: 512 },
      { svg: faviconSvgContent, dest: faviconPngDest, size: 192 }
    ];

    for (const target of renderTargets) {
      console.log(`Rendering SVG to ${target.dest} (${target.size}x${target.size}) using resvg...`);
      const resvg = new Resvg(target.svg, {
        fitTo: {
          mode: 'width',
          value: target.size
        }
      });

      const pngData = resvg.render();
      const pngBuffer = pngData.asPng();

      fs.writeFileSync(target.dest, pngBuffer);
      console.log(`PNG rendered successfully at ${target.dest}!`);
    }

    console.log('All ditto-accented logos generated successfully!');
  } catch (err) {
    console.error('Error generating ditto-accented logos:', err);
    process.exit(1);
  }
}

run();
