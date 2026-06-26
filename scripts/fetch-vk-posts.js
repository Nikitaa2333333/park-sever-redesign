// Build-time VK wall parser → public/vk-news.json. No backend, never fails the build.
// Запускается перед сборкой (npm run fetch-news). Токен читается из окружения
// (VK_SERVICE_TOKEN), в репозиторий не попадает. Любая ошибка → exit 0, сайт
// собирается на закоммиченном seed/фолбэке. Лента «Парк Север», плоская (без категорий).
import fs from 'fs';
import path from 'path';
import https from 'https';

// ─── CONFIG ──────────────────────────────────────────────────────────────────
const SCREEN_NAME = 'park_sever';                         // VK screen-name группы
const OUTPUT_PATH = path.join('public', 'vk-news.json');  // статика Astro
const MAX_POSTS = 6;                                       // сколько карточек отдаём
const FETCH_WINDOW = 25;                                   // сколько последних постов сканируем
// ─────────────────────────────────────────────────────────────────────────────

const token = process.env.VK_SERVICE_TOKEN;

function fetchUrl(url) {
  return new Promise((resolve, reject) => {
    https.get(url, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => {
        try { resolve(JSON.parse(data)); } catch (e) { reject(e); }
      });
    }).on('error', reject);
  });
}

function formatDate(timestamp) {
  return new Date(timestamp * 1000).toLocaleDateString('ru-RU', {
    day: 'numeric', month: 'long', year: 'numeric',
  });
}

async function resolveOwnerId() {
  const url = `https://api.vk.com/method/utils.resolveScreenName?screen_name=${SCREEN_NAME}&access_token=${token}&v=5.131`;
  const data = await fetchUrl(url);
  if (data.error) throw new Error(`resolveScreenName: ${data.error.error_msg} (${data.error.error_code})`);
  const obj = data.response;
  if (!obj || !obj.object_id) throw new Error(`не удалось разрешить screen_name «${SCREEN_NAME}»`);
  // для групп owner_id отрицательный
  return obj.type === 'group' ? -obj.object_id : obj.object_id;
}

async function run() {
  if (!token) {
    console.log('VK_SERVICE_TOKEN не задан — пропускаю фетч, сайт использует seed/фолбэк.');
    process.exit(0); // НИКОГДА не валим сборку
  }

  try {
    const ownerId = await resolveOwnerId();
    const apiUrl = `https://api.vk.com/method/wall.get?owner_id=${ownerId}&count=${FETCH_WINDOW}&access_token=${token}&v=5.131`;
    const data = await fetchUrl(apiUrl);
    if (data.error) throw new Error(`wall.get: ${data.error.error_msg} (${data.error.error_code})`);

    const posts = [];
    for (const item of data.response.items) {
      if (!item.text) continue;

      let imageUrl = null;
      const photo = (item.attachments || []).find((a) => a.type === 'photo');
      if (photo?.photo?.sizes?.length) {
        imageUrl = [...photo.photo.sizes].sort((a, b) => b.width - a.width)[0].url;
      }
      if (!imageUrl) continue; // карточке нужна картинка

      const text = item.text.trim();
      const lines = text.split('\n').map((l) => l.trim()).filter(Boolean);
      let title = lines[0] || 'Новость';
      let body = lines.slice(1).join('\n') || title;
      if (title.length > 80) {
        const sp = title.lastIndexOf(' ', 80);
        title = title.substring(0, sp > 20 ? sp : 80) + '…';
        body = text;
      }

      posts.push({
        id: `vk_post_${item.id}`,
        date: formatDate(item.date),
        title,
        text: body,
        image: imageUrl,
        link: `https://vk.com/wall${ownerId}_${item.id}`,
        category: 'Новости', // плоская лента — категория одна, UI её не использует
      });
      if (posts.length >= MAX_POSTS) break;
    }

    fs.writeFileSync(OUTPUT_PATH, JSON.stringify(posts, null, 2), 'utf-8');
    console.log(`Записал ${posts.length} постов ВК → ${OUTPUT_PATH}`);
  } catch (err) {
    console.warn('Фетч ВК не удался, продолжаю на seed/фолбэке:', err.message);
    process.exit(0); // НИКОГДА не валим сборку
  }
}

run();
