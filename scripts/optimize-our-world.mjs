// Оптимизация фото вкладки «Наш мир»: исходники из папки `наш мир/` (ТЗ) и
// старого сайта (source-repo/src/images) ужимаем в src/assets/images/our-world/
// (max 2000px, mozjpeg q80, всё → .jpg). Запуск: node scripts/optimize-our-world.mjs
import sharp from 'sharp';
import { mkdirSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const TZ = path.join(root, 'наш мир');
const OLD = path.join(root, 'source-repo', 'src', 'images');
const OUT = path.join(root, 'src', 'assets', 'images', 'our-world');

// [источник, подпапка вывода, имя вывода без расширения]
const jobs = [];
// ТЗ: карусель 1.1–1.6, олени 2.1–2.3, места 3.1–3.5 (3.5 — png)
for (const n of ['1.1','1.2','1.3','1.4','1.5','1.6','2.1','2.2','2.3','3.1','3.2','3.3','3.4'])
  jobs.push([path.join(TZ, n + '.jpg'), '', n]);
jobs.push([path.join(TZ, '3.5.png'), '', '3.5']);
// собаки
const dogMap = { 'Рыжуля.jpg': 'ryzhulya', 'Гайка.jpg': 'gayka' };
for (const [src, out] of Object.entries(dogMap)) jobs.push([path.join(OLD, 'dogs', src), 'dogs', out]);
for (const n of ['1.jpeg','6.jpeg','12.jpeg','17.jpeg','19.jpeg','23.jpeg','24.jpg','25.jpg','26.jpg','27.jpg','28.jpg','29.jpg'])
  jobs.push([path.join(OLD, 'dogs', n), 'dogs', n.replace(/\.\w+$/, '')]);
// территория
for (let i = 1; i <= 11; i++) jobs.push([path.join(OLD, 'territory', i + '.jpg'), 'territory', String(i)]);
// животные (карусели с подписями)
for (const n of ['los','kaban','kosulya','barsuk','lisa','zayac-belyak','gornostay','teterev','utka-kryakva','kulik','utka-kryakva-2','dupel','bekas','zhavoronok','perepel','korostel','mysh-polevka','yascherica','laska','zayac','belka','ezh','krot','zemleroyka','ryabchik','teterev-kosach','seraya-kuropatka','yastreb-stervyatnik','yastreb-perepelyatnik','korshun'])
  jobs.push([path.join(OLD, 'animals', n + '.jpeg'), 'animals', n]);
jobs.push([path.join(OLD, 'animals', 'kunica.jpg'), 'animals', 'kunica']);
// рыбалка и велосипеды
for (const n of ['1', '2']) jobs.push([path.join(OLD, 'services', 'fishing', n + '.jpg'), 'fishing', n]);
for (let i = 1; i <= 5; i++) jobs.push([path.join(OLD, 'services', 'bikes', `bike-${i}.jpg`), 'bikes', `bike-${i}`]);

let ok = 0, miss = 0;
for (const [src, sub, name] of jobs) {
  if (!existsSync(src)) { console.warn('нет исходника:', src); miss++; continue; }
  const dir = path.join(OUT, sub);
  mkdirSync(dir, { recursive: true });
  await sharp(src)
    .rotate()
    .resize({ width: 2000, height: 2000, fit: 'inside', withoutEnlargement: true })
    .jpeg({ quality: 80, mozjpeg: true })
    .toFile(path.join(dir, name + '.jpg'));
  ok++;
}
console.log(`\nГотово: ${ok} оптимизировано, ${miss} пропущено.`);
