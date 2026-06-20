// Загрузка контент-слоя (источник правды — content/*.json) и резолв ref-ссылок.
import site from '../../content/site.json';
import seo from '../../content/seo.json';
import reviews from '../../content/reviews.json';

const pageModules = import.meta.glob('../../content/pages/*.json', { eager: true });
const sectionModules = import.meta.glob('../../content/sections/*.json', { eager: true });

const nameOf = (path: string) => path.split('/').pop()!.replace('.json', '');

const sections: Record<string, any> = {};
for (const [path, mod] of Object.entries(sectionModules)) {
  sections[nameOf(path)] = (mod as any).default;
}

const pages: Record<string, any> = {};
for (const [path, mod] of Object.entries(pageModules)) {
  pages[nameOf(path)] = (mod as any).default;
}

export { site, seo, reviews, sections, pages };

/**
 * Префикс base-пути для корневых ссылок на ассеты/страницы.
 * Нужно для GitHub Pages, где сайт отдаётся по подпути (/park-sever-redesign/).
 * Внешние ссылки (http, //, mailto, tel, #) не трогаем.
 */
export function withBase(path?: string | null): string {
  if (!path) return path ?? '';
  if (/^(?:[a-z]+:|\/\/|#)/i.test(path)) return path; // http(s):, mailto:, tel:, //cdn, #anchor
  if (path.startsWith('/')) {
    const base = import.meta.env.BASE_URL.replace(/\/$/, '');
    return base + path;
  }
  return path;
}

/** Вернуть страницу с резолвнутыми секциями ({ ref } → секция из sections/). */
export function getPage(id: string) {
  const page = pages[id];
  if (!page) throw new Error(`Нет страницы content/pages/${id}.json`);
  const resolved = (page.sections ?? [])
    .map((s: any) => (s && s.ref ? sections[s.ref] : s))
    .filter(Boolean);
  return { ...page, sections: resolved };
}
