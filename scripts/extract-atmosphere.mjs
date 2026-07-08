// Извлечение ДОСЛОВНЫХ текстов вкладки «Атмосфера для двоих» → src/data/atmosphere.json.
// Источники:
//   1) атмофсфера для двиох/Атмосфера для двоих ч.2 04.07.docx — тексты ч.2 (сезоны,
//      «отдых до приезда», форма истории, примеры работ) тянутся программно из docx;
//   2) встроенный в docx макет (image1.png) — тексты ч.1 (hero, «Для вашей истории»,
//      иконки, баннер сезона) перенесены с макета дословно, см. блок MOCKUP ниже.
// Тексты не перепечатываются руками — только программный перенос + транскрипция макета.
// Запуск: node scripts/extract-atmosphere.mjs
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { inflateRawSync } from 'node:zlib';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

// ── docx → параграфы (docx = zip, читаем central directory) ──────────
function readZipEntry(buf, wanted) {
  let eocd = -1;
  for (let i = buf.length - 22; i >= 0; i--) {
    if (buf.readUInt32LE(i) === 0x06054b50) { eocd = i; break; }
  }
  if (eocd < 0) throw new Error('EOCD не найден (не zip?)');
  const count = buf.readUInt16LE(eocd + 10);
  let off = buf.readUInt32LE(eocd + 16);
  for (let n = 0; n < count; n++) {
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

const docxBuf = readFileSync(path.join(root, 'атмофсфера для двиох', 'Атмосфера для двоих ч.2 04.07.docx'));
const xml = readZipEntry(docxBuf, 'word/document.xml').toString('utf8');
const paras = xml
  .split(/<w:p\b/).slice(1)
  .map((p) => decodeXml([...p.matchAll(/<w:t[^>]*>([^<]*)<\/w:t>/g)].map((m) => m[1]).join('')).trim())
  .filter((t) => t.length > 0);

const find = (re, from = 0) => {
  const i = paras.findIndex((t, n) => n >= from && re.test(t));
  if (i < 0) throw new Error('в docx не найден параграф: ' + re);
  return i;
};

// ── сезоны: 8 праздников «<Название> Фото N.1 на главную» ────────────
const holidayIdx = paras
  .map((t, i) => ({ t, i }))
  .filter(({ t }) => /Фото\s+\d+\.1\s+на главную/.test(t))
  .map(({ i }) => i);
if (holidayIdx.length !== 8) throw new Error('ожидалось 8 праздников, найдено ' + holidayIdx.length);

const holidayIds = ['new-year', 'valentine', 'march-8', 'february-23', 'maslenitsa', 'easter', 'may-9', 'pumpkin'];

const seasonsItems = holidayIdx.map((hi, n) => {
  const label = paras[hi].replace(/[.\s]*Фото\s+\d+\.1\s+на главную.*$/, '').trim();
  const cover = paras[hi].match(/Фото\s+(\d+\.1)/)[1];
  // после якоря: «Заголовок[:]» → заголовок; «Текст карточки[:]» → абзацы до «Внизу…»
  const hIdx = find(/^Заголовок:?$/, hi);
  const title = paras[hIdx + 1];
  const tIdx = find(/^Текст карточки:?$/, hi);
  const bottomIdx = find(/^Внизу/, hi);
  const paragraphs = paras.slice(tIdx + 1, bottomIdx);
  const tags = paras[bottomIdx].replace(/^Внизу[^:]*:\s*/, '').split('•').map((s) => s.trim()).filter(Boolean);
  const photos = (paras[find(/^Внутри Фото/, hi)].match(/\d+\.\d+/g)) ?? [];
  return { id: holidayIds[n], label, cover, title, paragraphs, tags, photos };
});

// ── блок «Отдых начинается ещё до вашего приезда» ────────────────────
const prepHeadIdx = find(/^Текст: Заголовок\./);
const prepared = {
  layout: paras[find(/^Слева фото, справа текст/)],
  heading: paras[prepHeadIdx].replace(/^Текст: Заголовок\.\s*/, ''),
  paragraphs: paras.slice(find(/^Текстовка:$/, prepHeadIdx) + 1, find(/^А если повод особенный/)),
  extrasIntro: paras[find(/^А если повод особенный/)],
  extras: paras.slice(find(/^А если повод особенный/) + 1, find(/^Вам остаётся только приехать/)),
  accent: paras[find(/^Вам остаётся только приехать/)].replace(/\.\s*[–-]\s*эту фразу.*$/, '.'),
  accentNote: (paras[find(/^Вам остаётся только приехать/)].match(/[–-]\s*(эту фразу.*)$/) ?? [])[1],
  button: paras[find(/^Ниже кнопка:/)].replace(/^Ниже кнопка:\s*/, ''),
  carousel: paras[find(/^Фото\. 13\.1/)].match(/\d+\.\d+/g).flatMap((a, i, arr) =>
    i === 0 ? Array.from({ length: Number(arr[1].split('.')[1]) - Number(a.split('.')[1]) + 1 },
      (_, k) => `13.${Number(a.split('.')[1]) + k}`) : []),
  carouselNote: paras[find(/^Фото\. 13\.1/)],
};

// ── блок «Каждая история…» + форма ───────────────────────────────────
const storyHeadIdx = find(/^Заголовок:\s*Каждая история/);
const story = {
  layout: paras[find(/^Ниже слева не крупно фото 9/)],
  photo: '9',
  heading: paras[storyHeadIdx].replace(/^Заголовок:\s*/, '').replace(/\.\s*$/, ''),
  text: paras[storyHeadIdx + 1].replace(/^Текстовка:\s*/, ''),
  button: 'Рассказать о своей истории',
  buttonNote: paras[find(/^Справа Кнопка/)],
  formFields: ['Имя', 'Телефон', 'Эл. почта', 'Текст'],
  email: paras[find(/park-sever@inbox\.ru/)].match(/[\w.-]+@[\w.-]+/)[0],
  messengersNote: paras[find(/^Или напишите нам в мессенджере/)],
  messengers: ['whatsapp', 'telegram', 'vk'],
};

// ── примеры работ ────────────────────────────────────────────────────
const exIdx = paras
  .map((t, i) => ({ t, i }))
  .filter(({ t }) => /^3\.\d\s+(Карточка|Фото)/i.test(t))
  .map(({ i }) => i);
if (exIdx.length !== 3) throw new Error('ожидалось 3 примера, найдено ' + exIdx.length);

const expandRange = (s) => {
  const m = s.match(/(\d+)\.(\d+)\s*[–—-]\s*(?:\d+\.)?(\d+)/);
  return Array.from({ length: Number(m[3]) - Number(m[2]) + 1 }, (_, k) => `${m[1]}.${Number(m[2]) + k}`);
};

const examples = exIdx.map((xi, n) => {
  const head = paras[xi];
  const title = head.replace(/^3\.\d\s+(Карточка\s+)?[Фф]ото\s+[\d.\s–—-]+\.?\s*/i, '').replace(/\.\s*$/, '');
  const photos = expandRange(head);
  const end = n + 1 < exIdx.length ? exIdx[n + 1] : paras.length;
  const totalIdx = find(/^Общая стоимость/, xi);
  const prepared = paras.slice(find(/^Подготовлено:/, xi) + 1, totalIdx)
    .map((t) => t.replace(/^-\s*/, '').replace(/[;.]\s*$/, ''));
  const total = paras.slice(totalIdx, end).map((t) => t.trim()).join(' ');
  return { title, photos, prepared, total };
});

// ── MOCKUP: тексты ч.1, дословно с макета image1.png из docx ─────────
const MOCKUP = {
  hero: {
    eyebrow: 'Атмосфера для двоих',
    title: 'Атмосфера для двоих',
    lead: 'Каждая история заслуживает своей атмосферы.',
    text: 'Пока вы в дороге, мы можем подготовить дом именно для вашего повода.',
    button: 'Рассказать о своей истории',
  },
  forYourStory: {
    heading: 'Для вашей истории',
    intro: 'Будь то важное событие или просто желание сделать друг другу приятное — мы подготовим атмосферу, о которой вы мечтаете.',
    cards: [
      { icon: 'ring', title: 'Предложение руки и сердца', text: 'Момент, который останется в памяти на всю жизнь.' },
      { icon: 'cake', title: 'День рождения', text: 'Подарите эмоции и время вместе.' },
      { icon: 'hearts', title: 'Годовщина', text: 'Вспомнить, как всё начиналось.' },
      { icon: 'heart', title: 'Романтический сюрприз', text: 'Иногда лучший повод — это просто любовь.' },
    ],
    icons: [
      'Цветы', 'Свечи и декор', 'Торт и сладости', 'Послание или записка',
      'Украшение дома', 'Сюрприз для второй половины', 'Помощь в организации предложения',
    ],
  },
  seasonBanner: {
    eyebrow: 'Сейчас в Парк Север',
    // на макете — весенний вариант; тексты по сезону, актуальный выбирается на странице
    variants: {
      spring: { title: 'Весна в лесу', text: 'Свежая зелень, пение птиц и первые тёплые вечера. Идеальное время, чтобы замедлиться и насладиться природой.' },
    },
    button: 'Узнать больше о сезоне',
  },
};

// ── сборка ───────────────────────────────────────────────────────────
const data = {
  id: 'atmosphere',
  title: 'Атмосфера для двоих',
  sources: {
    part2: 'атмофсфера для двиох/Атмосфера для двоих ч.2 04.07.docx (программное извлечение)',
    part1: 'встроенный в docx макет word/media/image1.png (дословная транскрипция)',
  },
  important: paras[find(/^ВАЖНО/)],
  hero: MOCKUP.hero,
  forYourStory: MOCKUP.forYourStory,
  seasons: {
    heading: (paras[0].match(/«([^»]+)»/) ?? [])[1],
    subtitle: (paras[1].match(/«([^»]+)»/) ?? [])[1],
    layoutNote: paras[find(/^Принцип вкладок/)],
    items: seasonsItems,
  },
  seasonBanner: MOCKUP.seasonBanner,
  prepared,
  story,
  examples: {
    heading: 'Примеры работ',
    layoutNote: paras[find(/^Примеры работ/)],
    items: examples,
  },
};

mkdirSync(path.join(root, 'src', 'data'), { recursive: true });
const out = path.join(root, 'src', 'data', 'atmosphere.json');
writeFileSync(out, JSON.stringify(data, null, 2) + '\n', 'utf8');
console.log('✓', path.relative(root, out));
console.log('  сезоны:', seasonsItems.map((s) => `${s.label} (${s.photos.length} фото)`).join(' · '));
console.log('  подготовка:', prepared.paragraphs.length, 'абз. +', prepared.extras.length, 'доп.; карусель:', prepared.carousel.join(', '));
console.log('  история:', story.heading, '| email:', story.email);
console.log('  примеры:', examples.map((e) => `${e.title} (${e.photos.length} фото, ${e.prepared.length} поз.)`).join(' · '));
