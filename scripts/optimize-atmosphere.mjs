// Оптимизация фото вкладки «Атмосфера для двоих»: кадры из `атмофсфера для двиох/`
// (до 8 МБ) ужимаем в src/assets/images/atmosphere/ (max 2000px, mozjpeg q80).
// Astro дальше сам конвертирует в webp с адаптивным srcset.
// Запуск: node scripts/optimize-atmosphere.mjs
import sharp from 'sharp';
import { mkdirSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const OUT = path.join(root, 'src', 'assets', 'images', 'atmosphere');

// два источника: ч.2 (сезоны/примеры) → atmosphere/, ч.1 (фото поводов
// «Для вашей истории», папка «снова что-то») → atmosphere/story/
const JOBS = [
  { src: path.join(root, 'атмофсфера для двиох'), out: OUT },
  { src: path.join(root, 'снова что-то'), out: path.join(OUT, 'story') },
];

let ok = 0;
for (const { src, out } of JOBS) {
  mkdirSync(out, { recursive: true });
  const files = readdirSync(src).filter((f) => /\.jpe?g$/i.test(f));
  for (const f of files) {
    await sharp(path.join(src, f))
      .rotate() // учесть EXIF-ориентацию
      .resize({ width: 2000, height: 2000, fit: 'inside', withoutEnlargement: true })
      .jpeg({ quality: 80, mozjpeg: true })
      .toFile(path.join(out, f.replace(/\.jpeg$/i, '.jpg')));
    ok++;
    console.log('✓', path.basename(out) + '/' + f);
  }
}
console.log(`\nГотово: ${ok} фото.`);
