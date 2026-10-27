// sitemap.xml для поисковиков: все страницы сайта, кроме служебных (404, демо, styleguide).
// Новая страница в src/pages попадает сюда сама.
import type { APIRoute } from 'astro';

const pages = import.meta.glob('./**/*.astro');

export const GET: APIRoute = ({ site }) => {
  const urls = Object.keys(pages)
    .map((f) => f.replace(/^\.\//, '/').replace(/\.astro$/, '').replace(/\/index$/, '/').replace(/^\/index$/, '/'))
    .filter((p) => !p.includes('[') && p !== '/404')
    .map((p) => (p.endsWith('/') ? p : p + '/'))
    .sort();
  const body = '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
    + urls.map((p) => `  <url><loc>${new URL(p, site).href}</loc></url>`).join('\n')
    + '\n</urlset>\n';
  return new Response(body, { headers: { 'Content-Type': 'application/xml; charset=utf-8' } });
};
