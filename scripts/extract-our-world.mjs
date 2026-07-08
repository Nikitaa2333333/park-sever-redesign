// Извлечение ДОСЛОВНЫХ текстов вкладки «Наш мир» → content/pages/our-world.json.
// Источники: наш мир/Наш мир.docx (ТЗ) + pug-шаблоны старого сайта (source-repo),
// на которые ТЗ прямо ссылается (ретриверы, «О нас», рыбалка, велосипеды).
// Тексты не перепечатываются руками — только программный перенос.
// Запуск: node scripts/extract-our-world.mjs
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { inflateRawSync } from 'node:zlib';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

// ── 1. docx → параграфы ──────────────────────────────────────────────
// docx = zip; достаём word/document.xml минимальным чтением central directory.
function readZipEntry(buf, wanted) {
  // End of Central Directory
  let eocd = -1;
  for (let i = buf.length - 22; i >= 0; i--) {
    if (buf.readUInt32LE(i) === 0x06054b50) { eocd = i; break; }
  }
  if (eocd < 0) throw new Error('EOCD не найден (не zip?)');
  const count = buf.readUInt16LE(eocd + 10);
  let off = buf.readUInt32LE(eocd + 16);
  for (let n = 0; n < count; n++) {
    if (buf.readUInt32LE(off) !== 0x02014b50) throw new Error('битая central directory');
    const nameLen = buf.readUInt16LE(off + 28);
    const extraLen = buf.readUInt16LE(off + 30);
    const commentLen = buf.readUInt16LE(off + 32);
    const name = buf.toString('utf8', off + 46, off + 46 + nameLen);
    if (name === wanted) {
      const lho = buf.readUInt32LE(off + 42);
      const method = buf.readUInt16LE(lho + 8);
      const compSize = buf.readUInt32LE(off + 20);
      const nLen = buf.readUInt16LE(lho + 26);
      const eLen = buf.readUInt16LE(lho + 28);
      const start = lho + 30 + nLen + eLen;
      const data = buf.subarray(start, start + compSize);
      return method === 8 ? inflateRawSync(data) : Buffer.from(data);
    }
    off += 46 + nameLen + extraLen + commentLen;
  }
  throw new Error('в zip нет ' + wanted);
}

const decodeXml = (s) => s
  .replace(/&#(\d+);/g, (_, d) => String.fromCodePoint(Number(d)))
  .replace(/&quot;/g, '"').replace(/&apos;/g, "'")
  .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');

const docxBuf = readFileSync(path.join(root, 'наш мир', 'Наш мир.docx'));
const xml = readZipEntry(docxBuf, 'word/document.xml').toString('utf8');
const paras = xml
  .split(/<w:p\b/).slice(1)
  .map((p) => decodeXml([...p.matchAll(/<w:t[^>]*>([^<]*)<\/w:t>/g)].map((m) => m[1]).join('')).trim())
  .filter((t) => t.length > 0);

const find = (re) => {
  const i = paras.findIndex((t) => re.test(t));
  if (i < 0) throw new Error('в docx не найден параграф: ' + re);
  return i;
};

// интро («Текст: Мир Парк Север …» — служебный префикс "Текст: " срезаем)
const intro = paras[find(/^Текст:\s*Мир Парк Север/)].replace(/^Текст:\s*/, '');

// ретриверы: «Только текст : <заголовок>» + 2 абзаца следом
const rhIdx = find(/^Только текст/);
const retrHeading = paras[rhIdx].replace(/^Только текст\s*:\s*/, '');
const retrParas = [paras[rhIdx + 1], paras[rhIdx + 2]];

// олени: «Заголовок: Ферма …» + абзацы до «Больше информации…»
const dhIdx = find(/^Заголовок:\s*Ферма/);
const deerHeading = paras[dhIdx].replace(/^Заголовок:\s*/, '');
const deerNoteIdx = find(/^Больше информации/);
const deerParas = paras.slice(dhIdx + 1, deerNoteIdx).map((t) => t.replace(/^Текст:\s*/, ''));
const deerNote = paras[deerNoteIdx];

// интересные места
const phIdx = find(/^Заголовок:\s*Когда хочется/);
const placesHeading = paras[phIdx].replace(/^Заголовок:\s*/, '').replace(/\s*\.\s*$/, '.');
const placesIntro = paras[find(/^Если захочется новых впечатлений/)];

// пять мест: параграфы-названия начинаются с эмодзи (codePoint > 0x2500)
const titleIdxs = paras
  .map((t, i) => ({ t, i }))
  .filter(({ t, i }) => i > phIdx && t.codePointAt(0) > 0x2500)
  .map(({ i }) => i);
if (titleIdxs.length !== 5) throw new Error('ожидалось 5 мест, найдено ' + titleIdxs.length);

const orudyevoRoute = paras[find(/yandex\.ru\/maps\?whatshere/)];
const orudyevoAddr = paras[find(/^село Орудьево/)] + ', ' + paras[find(/^Дмитровский муниципальный округ/)];
const placePhotos = ['3.1', '3.2', '3.3', '3.4', '3.5'];
const routeSearch = (q) => 'https://yandex.ru/maps/?text=' + encodeURIComponent(q + ', Московская область');

const places = titleIdxs.map((ti, n) => {
  const title = paras[ti].replace(/^[^\p{L}«]+/u, '');
  const item = {
    title,
    subtitle: paras[ti + 1],
    text: paras[ti + 2],
    photo: placePhotos[n],
    route: n === 4 ? orudyevoRoute : routeSearch(title),
  };
  if (n === 4) item.address = orudyevoAddr;
  return item;
});

// ── 2. pug старого сайта → дословные тексты ──────────────────────────
const pug = (f) => readFileSync(path.join(root, 'source-repo', 'src', 'template', f), 'utf8');

// строки без закомментированных блоков (`//-` скрывает и всё, что глубже отступом)
function activeLines(src) {
  const out = [];
  let skipIndent = -1;
  for (const line of src.split(/\r?\n/)) {
    const indent = line.match(/^\s*/)[0].length;
    if (skipIndent >= 0) {
      if (line.trim() === '' || indent > skipIndent) continue;
      skipIndent = -1;
    }
    if (/^\s*\/\/-?/.test(line)) { skipIndent = indent; continue; }
    out.push(line);
  }
  return out;
}
const unbold = (s) => s.replace(/#\[b ([^\]]*)\]\s*/g, '$1 ').replace(/\s+/g, ' ').trim();
const textOf = (line, tag) => {
  const m = line.match(new RegExp('^\\s*' + tag + ' (.+)$'));
  return m ? unbold(m[1]) : null;
};

// попап `.popup#id` → { h2, paras[], imgs[] }
function popup(lines, id) {
  const start = lines.findIndex((l) => new RegExp('^\\s*\\.popup#' + id + '\\b').test(l));
  if (start < 0) throw new Error('нет попапа #' + id);
  const baseIndent = lines[start].match(/^\s*/)[0].length;
  const body = [];
  for (let i = start + 1; i < lines.length; i++) {
    const l = lines[i];
    if (l.trim() !== '' && l.match(/^\s*/)[0].length <= baseIndent) break;
    body.push(l);
  }
  const h2 = body.map((l) => textOf(l, 'h2')).find(Boolean);
  const parasP = body.map((l) => textOf(l, 'p')).filter(Boolean);
  const imgs = [...new Set(body.map((l) => (l.match(/(?:src|href)='(\/images\/[^']+)'/) || [])[1]).filter(Boolean))];
  return { h2, paras: parasP, imgs };
}

// — собаки (dogs.pug) —
const dogsLines = activeLines(pug('dogs.pug'));
const dogsParas = dogsLines.map((l) => textOf(l, '\\.subtitle')).filter(Boolean).slice(0, 2);
const dogGallery = dogsLines
  .map((l) => (l.match(/data-fancybox="dogs" href='\/images\/(dogs\/[^']+)'/) || [])[1])
  .filter(Boolean);
const dogDefs = [
  { id: 'dog-ryzhulya' },
  { id: 'dog-gayka' },
  { id: 'dog-bella' },
].map(({ id }) => {
  const p = popup(dogsLines, id);
  return { name: p.h2, photo: p.imgs[0].replace('/images/', ''), paragraphs: p.paras };
});

// — территория (territory.pug) —
const terrLines = activeLines(pug('territory.pug'));
const territory = {
  heading: terrLines.map((l) => textOf(l, 'h2')).find(Boolean),
  paragraphs: terrLines.map((l) => textOf(l, 'p')).filter(Boolean),
  photos: [...new Set(terrLines.map((l) => (l.match(/href='\/images\/(territory\/[^']+)'/) || [])[1]).filter(Boolean))],
};

// — животный мир (animals.pug) —
const aLines = activeLines(pug('animals.pug'));
const animals = { heading: null, paragraphs: [], groups: [] };
let group = null;
for (const l of aLines) {
  const h2 = textOf(l, 'h2'); if (h2) { animals.heading = h2; continue; }
  const p = textOf(l, 'p'); if (p) { animals.paragraphs.push(p); continue; }
  const h3 = textOf(l, 'h3'); if (h3) { group = { title: h3, items: [] }; animals.groups.push(group); continue; }
  const img = (l.match(/img\(src='\/images\/(animals\/[^']+)'\)/) || [])[1];
  if (img && group) { group.items.push({ photo: img, caption: null }); continue; }
  const cap = textOf(l, '\\.caption');
  if (cap && group) group.items[group.items.length - 1].caption = cap;
}

// — рыбалка и велосипеды (services.pug) —
const svcLines = activeLines(pug('services.pug'));
const fishing = popup(svcLines, 'fishing');
const bikes = popup(svcLines, 'bikes');

// ── 3. сборка JSON ───────────────────────────────────────────────────
const data = {
  id: 'our-world',
  title: 'Наш мир',
  heroCarousel: ['1.1', '1.2', '1.3', '1.4', '1.5', '1.6'],
  intro,
  menu: [
    { id: 'retrievers', label: 'Ретриверы' },
    { id: 'forest', label: 'Лес' },
    { id: 'pond', label: 'Пруд' },
    { id: 'bikes', label: 'Велосипеды' },
    { id: 'deer', label: 'Олени' },
    { id: 'places', label: 'Интересные места рядом' },
  ],
  retrievers: {
    heading: retrHeading,
    paragraphs: retrParas,
    ownerPhoto: 'dogs/29.jpg',
    dogs: dogDefs,
    gallery: dogGallery,
  },
  forest: { territory, animals },
  pond: {
    heading: fishing.h2,
    paragraphs: fishing.paras,
    photos: fishing.imgs.map((s) => s.replace('/images/', '')),
  },
  bikes: {
    heading: bikes.h2,
    paragraphs: bikes.paras,
    photos: bikes.imgs.map((s) => s.replace('/images/', '')),
  },
  deer: {
    heading: deerHeading,
    paragraphs: deerParas,
    note: deerNote,
    photos: ['2.1', '2.2', '2.3'],
    links: [
      { label: 'VK-сообщество', href: 'https://vk.com/kfh_noble' },
      { label: 'Telegram-канал', href: 'https://t.me/kfhNoble' },
      { label: 'noble-farm.ru', href: 'https://noble-farm.ru' },
    ],
  },
  places: { heading: placesHeading, intro: placesIntro, items: places },
};

const out = path.join(root, 'content', 'pages', 'our-world.json');
writeFileSync(out, JSON.stringify(data, null, 2) + '\n', 'utf8');
console.log('✓', path.relative(root, out));
console.log('  интро:', intro.slice(0, 60) + '…');
console.log('  ретриверы:', retrParas.length, 'абз., собак:', dogDefs.length, ', галерея:', dogGallery.length, 'фото');
console.log('  лес:', territory.paragraphs.length, '+', animals.paragraphs.length, 'абз., групп животных:', animals.groups.length);
console.log('  пруд:', fishing.paras.length, 'абз.,', fishing.imgs.length, 'фото; велосипеды:', bikes.paras.length, 'абз.,', bikes.imgs.length, 'фото');
console.log('  олени:', deerParas.length, 'абз.; мест:', places.length);
