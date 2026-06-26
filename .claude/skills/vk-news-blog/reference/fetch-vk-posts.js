// Build-time VK wall parser → static JSON. No backend, never fails the build.
// Copy into the host project's scripts/ and edit the marked CONFIG below.
import fs from 'fs';
import path from 'path';
import https from 'https';

// ─── CONFIG — change these per site ──────────────────────────────────────────
const ownerId = -000000000;                  // negative numeric id of the VK group
const OUTPUT_PATH = path.join('public', 'vk-news.json'); // host static-assets dir
const MAX_POSTS = 6;                          // how many cards to ship
const FETCH_WINDOW = 20;                      // how many recent posts to scan

// hashtag → category. Return one constant for a flat feed (no categories).
function detectCategory(text) {
  if (!text) return 'General';
  const t = text.toLowerCase();
  // EXAMPLE buckets — replace with the new site's taxonomy:
  // if (t.includes('#tag')) return 'CategoryKey';
  return 'General';
}
// ─────────────────────────────────────────────────────────────────────────────

const token = process.env.VK_SERVICE_TOKEN;

function fetchUrl(url) {
  return new Promise((resolve, reject) => {
    https.get(url, (res) => {
      let data = '';
      res.on('data', (chunk) => { data += chunk; });
      res.on('end', () => resolve(JSON.parse(data)));
    }).on('error', reject);
  });
}

function formatDate(timestamp) {
  return new Date(timestamp * 1000).toLocaleDateString('ru-RU', {
    day: 'numeric', month: 'long', year: 'numeric',
  });
}

async function run() {
  if (!token) {
    console.log('VK_SERVICE_TOKEN not set — skipping fetch, site uses seed/fallback data.');
    process.exit(0); // NEVER fail the build
  }

  const apiUrl = `https://api.vk.com/method/wall.get?owner_id=${ownerId}&count=${FETCH_WINDOW}&access_token=${token}&v=5.131`;
  try {
    const data = await fetchUrl(apiUrl);
    if (data.error) throw new Error(`VK API: ${data.error.error_msg} (${data.error.error_code})`);

    const posts = [];
    for (const item of data.response.items) {
      if (!item.text) continue;

      let imageUrl = null;
      const photo = (item.attachments || []).find(a => a.type === 'photo');
      if (photo?.photo?.sizes?.length) {
        imageUrl = [...photo.photo.sizes].sort((a, b) => b.width - a.width)[0].url;
      }
      if (!imageUrl) continue; // cards need an image

      const text = item.text.trim();
      const lines = text.split('\n').map(l => l.trim()).filter(Boolean);
      let title = lines[0] || 'Новость';
      let body = lines.slice(1).join('\n') || title;
      if (title.length > 80) {
        const sp = title.lastIndexOf(' ', 80);
        title = title.substring(0, sp > 20 ? sp : 80) + '...';
        body = text;
      }

      posts.push({
        id: `vk_post_${item.id}`,
        date: formatDate(item.date),
        title,
        text: body,
        image: imageUrl,
        link: `https://vk.com/wall${ownerId}_${item.id}`,
        category: detectCategory(text),
      });
      if (posts.length >= MAX_POSTS) break;
    }

    fs.writeFileSync(OUTPUT_PATH, JSON.stringify(posts, null, 2), 'utf-8');
    console.log(`Wrote ${posts.length} VK posts → ${OUTPUT_PATH}`);
  } catch (err) {
    console.warn('VK fetch failed, continuing with existing/seed data:', err.message);
    process.exit(0); // NEVER fail the build
  }
}

run();
