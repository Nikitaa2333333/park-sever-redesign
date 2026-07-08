// Разовый дамп докса «Атмосфера для двоих ч.2» — параграфы + встроенные картинки.
// Запуск: node scripts/dump-atmosphere-docx.mjs <outDir>
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { inflateRawSync } from 'node:zlib';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const outDir = process.argv[2] || path.join(root, '_docx-dump');
mkdirSync(outDir, { recursive: true });

const buf = readFileSync(path.join(root, 'атмофсфера для двиох', 'Атмосфера для двоих ч.2 04.07.docx'));

// перечисляем все записи zip
function* zipEntries(b) {
  let eocd = -1;
  for (let i = b.length - 22; i >= 0; i--) {
    if (b.readUInt32LE(i) === 0x06054b50) { eocd = i; break; }
  }
  if (eocd < 0) throw new Error('не zip');
  const count = b.readUInt16LE(eocd + 10);
  let off = b.readUInt32LE(eocd + 16);
  for (let n = 0; n < count; n++) {
    const nameLen = b.readUInt16LE(off + 28);
    const extraLen = b.readUInt16LE(off + 30);
    const commentLen = b.readUInt16LE(off + 32);
    const name = b.toString('utf8', off + 46, off + 46 + nameLen);
    const lho = b.readUInt32LE(off + 42);
    const method = b.readUInt16LE(lho + 8);
    const compSize = b.readUInt32LE(off + 20);
    const nLen = b.readUInt16LE(lho + 26);
    const eLen = b.readUInt16LE(lho + 28);
    const start = lho + 30 + nLen + eLen;
    const data = b.subarray(start, start + compSize);
    yield { name, data: () => (method === 8 ? inflateRawSync(data) : Buffer.from(data)) };
    off += 46 + nameLen + extraLen + commentLen;
  }
}

const decodeXml = (s) => s
  .replace(/&#(\d+);/g, (_, d) => String.fromCodePoint(Number(d)))
  .replace(/&quot;/g, '"').replace(/&apos;/g, "'")
  .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');

let xml = null;
const media = [];
for (const e of zipEntries(buf)) {
  if (e.name === 'word/document.xml') xml = e.data().toString('utf8');
  else if (e.name.startsWith('word/media/')) media.push({ name: path.basename(e.name), data: e.data() });
}

// параграфы: текст + маркер картинки, если внутри есть <w:drawing>
const paras = xml.split(/<w:p\b/).slice(1).map((p) => {
  const text = decodeXml([...p.matchAll(/<w:t[^>]*>([^<]*)<\/w:t>/g)].map((m) => m[1]).join('')).trim();
  const hasImg = /<w:drawing|<w:pict/.test(p);
  return { text, hasImg };
});

const lines = [];
paras.forEach((p, i) => {
  if (p.hasImg) lines.push(`[${i}] <<IMAGE>> ${p.text}`);
  else if (p.text) lines.push(`[${i}] ${p.text}`);
});
writeFileSync(path.join(outDir, 'paragraphs.txt'), lines.join('\n'), 'utf8');

for (const m of media) writeFileSync(path.join(outDir, m.name), m.data);
console.log('параграфов с текстом:', lines.length, '| картинок в доксе:', media.length);
console.log('media:', media.map((m) => m.name).join(', '));
