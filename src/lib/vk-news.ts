// Новости из ВК — общий источник для главной и вкладки «Истории» (/stories/).
// Build-time снапшот public/vk-news.json (перегенерируется парсером
// scripts/fetch-vk-posts.js в CI) → seed-фолбэк ниже → страница не ломается никогда.
// ВАЖНО: seed — это НЕ выдуманные новости, а реальные посты из vk.com/park_sever,
// чтобы фолбэк был настоящим до подключения токена. После фетча его заменяет живая лента.
import vkNewsJson from '../../public/vk-news.json';
import { site, withBase } from './content';

export type VkPost = {
  id: string; date: string; title: string; text: string; image: string; link: string;
};

export const vkGroupHref: string =
  site.integrations?.vkNews?.groupHref ?? 'https://vk.com/park_sever';

const VK_NEWS_SEED: VkPost[] = [
  {
    id: 'seed_1',
    date: '15 июня 2026',
    title: 'Как рассказать про Парк Север?',
    text: 'Мы создавали это место таким, каким хотели видеть его для себя — чтобы утром открывать глаза и видеть лес.',
    image: '/images/main-menu/deers.jpg',
    link: vkGroupHref,
  },
  {
    id: 'seed_2',
    date: 'Июнь 2026',
    title: 'Утро в стеклянном доме',
    text: 'Туман над прудом, тишина и кофе у панорамного окна — так начинается день в парке.',
    image: '/images/home/oasis/1.1.jpg',
    link: vkGroupHref,
  },
  {
    id: 'seed_3',
    date: 'Июнь 2026',
    title: 'Наши олени',
    text: 'На территории — собственная ферма благородного оленя. Гости приходят понаблюдать за стадом и покормить его.',
    image: '/images/home/world/2.3.jpg',
    link: vkGroupHref,
  },
  {
    id: 'seed_4',
    date: 'Июнь 2026',
    title: 'Вечер у огня',
    text: 'Ужин на свежем воздухе и потрескивание костра — атмосфера для двоих, ради которой возвращаются.',
    image: '/images/home/atmosphere/5.1.jpg',
    link: vkGroupHref,
  },
];

// типограф: висячие предлоги/союзы (1–2 буквы) клеим к следующему слову неразрывным
// пробелом; два прохода — для цепочек коротких слов подряд («и в лесу»)
const pass = (t: string) => t.replace(/(^|[\s(«„])([а-яёА-ЯЁ]{1,2}) /g, '$1$2 ');
export const typo = (s: string) => pass(pass(s ?? ''));

const raw: VkPost[] =
  Array.isArray(vkNewsJson) && vkNewsJson.length ? (vkNewsJson as VkPost[]) : VK_NEWS_SEED;

export const vkNews: VkPost[] = raw.map((p) => ({
  ...p, title: typo(p.title), text: typo(p.text),
}));

// картинки ВК — абсолютные URL (отдаём как есть); локальные seed-пути — через withBase
export const newsImg = (src: string) =>
  /^https?:\/\//.test(src) ? src : withBase(src);
