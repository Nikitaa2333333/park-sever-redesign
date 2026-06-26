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
  server: { port: 4321 },
});
