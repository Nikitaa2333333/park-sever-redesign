import { defineConfig } from 'astro/config';

// Минимальный каркас-пилот: рендерит контент из content/*.json.
// Дизайн добавим после design.md.
//
// Публикация на GitHub Pages (project pages): сайт отдаётся по пути
// https://nikitaa2333333.github.io/park-sever-redesign/ — поэтому задаём base.
// Все корневые пути к ассетам (/images, /videos, /fonts) прогоняем через
// withBase() из src/lib/content.ts, чтобы они резолвились под этим base.
export default defineConfig({
  site: 'https://nikitaa2333333.github.io',
  base: process.env.NODE_ENV === 'production' ? '/park-sever-redesign' : '/',
  // Отдельный фиксированный порт именно для этого проекта (Парк Север),
  // чтобы dev-сервер не пересекался с другими Astro-сайтами.
  // strictPort: не «перепрыгивать» на чужой порт — честно падать, если 4390 занят.
  server: { host: '127.0.0.1', port: 4390, strictPort: true },
});
