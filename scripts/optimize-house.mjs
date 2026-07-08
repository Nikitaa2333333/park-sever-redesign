// Оптимизация исходных фото вкладки «Дом»: тяжёлые (5–12 МБ) кадры из папки `дом/`
// ужимаем в src/assets/images/house/ (max 2000px, mozjpeg q80). Astro дальше сам
// конвертирует в webp с адаптивным srcset. Запуск: node scripts/optimize-house.mjs
import sharp from 'sharp';
import { mkdirSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const SRC = path.join(root, 'дом');
const OUT = path.join(root, 'src', 'assets', 'images', 'house');
mkdirSync(OUT, { recursive: true });

const files = [
  '1.1', '1.2', '1.3',
  '3.3',
  '4.1.1', '4.1.2', '4.1.3', '4.1.4',
  '4.3.1', '4.3.2', '4.3.3', '4.3.4',
  '4.4',
  '5.1', '5.2', '5.3', '5.4', '5.5',
  '6.1', '6.2', '6.3', '6.4', '6.5', '6.6', '6.7',
  '7', '8',
];

let ok = 0, miss = 0;
for (const name of files) {
  const inFile = path.join(SRC, `${name}.jpg`);
  const outFile = path.join(OUT, `${name}.jpg`);
  if (!existsSync(inFile)) { console.warn('нет исходника:', name); miss++; continue; }
  await sharp(inFile)
    .rotate() // учесть EXIF-ориентацию
    .resize({ width: 2000, height: 2000, fit: 'inside', withoutEnlargement: true })
    .jpeg({ quality: 80, mozjpeg: true })
    .toFile(outFile);
  ok++;
  console.log('✓', name);
}
console.log(`\nГотово: ${ok} оптимизировано, ${miss} пропущено.`);
