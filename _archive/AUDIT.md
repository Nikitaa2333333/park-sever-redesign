# Технический аудит сайта «Парк Север»

> Источник: приватный репозиторий `github.com/nikkorfed/park-sever` (ветка `main`, последний push 2026-02-20).
> Production: `park-sever.ru`. Документ подготовлен для редизайна — фиксирует текущее состояние стека, тех-долг и план оптимизации.

---

## 1. Стек и сборка

| Слой | Технология | Комментарий |
|------|------------|-------------|
| Шаблоны | **Pug** (`gulp-pug`) | 13 страниц + ~30 секций-партиалов |
| Стили | **Sass** (indented `.sass`, `gulp-sass` + `sass`) | 43 файла, компонентная структура |
| Сборка | **Gulp 4** + `browser-sync` + `gulp-connect-php` | dev-сервер `localhost:3000`, прокси `:8080` |
| Бэкенд | **PHP** | только `reviews.php` (парсер отзывов) |
| JS | **jQuery** + сторонние плагины | ES-модули смешаны с jQuery |
| Деплой | **GitHub Actions → FTP** | push в `main` → build → заливка `./build` |

### Поток сборки

```
src/pages/**/*.pug ──┐
src/template/*.pug ──┤
                     ├─(gulp-pug)──▶ build/*.html
src/styles/**/*.sass ─(gulp-sass)──▶ build/styles/*.css
src/scripts/**       ─(copy)──────▶ build/scripts/**
src/libraries/**     ─(copy)──────▶ build/libraries/**
прочее (images, videos, fonts, files, icons) ─(copy)──▶ build/**
                     │
                     ▼
       GitHub Actions: npm ci → npm run build
                     │
                     ▼
          FTP-Deploy-Action → /www/park-sever.ru/
```

Скрипты npm: `start` → `gulp` (dev watch), `build` → `gulp build`.

---

## 2. Инвентарь зависимостей

### JavaScript (`src/scripts/javascript/`, 15 файлов — все на jQuery)

| Файл | Назначение | Статус |
|------|-----------|--------|
| `slider.js` | owl.carousel — ~6 каруселей (главная, дома, преимущества, животные, ретривер-клуб) | ✅ активен |
| `header.js` | бургер-меню (toggle класса на body) | ✅ активен |
| `popups.js` | fancybox для модалок/lightbox | ✅ активен |
| `panorama.js` | 360°-панорама (pannellum), демо-картинка | ✅ активен (нишевый) |
| `reviews.js` | читает `/data/reviews.json`, рендерит карусель | ✅ активен |
| `booking.js` | внешний виджет бронирования `znms` | ✅ активен |
| `news.js` | VK Groups виджет | ✅ активен |
| `animations.js` | fade-in по скроллу (без debounce) | ✅ активен |
| `fixed-buttons.js` | показ плавающих кнопок при scroll > 200px | ✅ активен |
| `yandex-metrika.js` | Яндекс.Метрика (ID 99962092) | ✅ активен |
| `photos.js` | Instagram-лента (`$.instagramFeed`) | ❌ **сломан** (API IG) |
| `scrolling.js` | инициализация fullpage.js | ❌ **закомментирован** |
| `houses.js` | табы домов | ❌ отключён |
| `spoilers.js` | аккордеон | ❌ отключён |

### Библиотеки (`src/libraries/`)

| Библиотека | Назначение | Вердикт |
|------------|-----------|---------|
| `jquery.min.js` | основа всего JS | ⚠️ **заменить** (vanilla JS) |
| `owl.carousel` (js+css) | карусели/слайдеры | 🔁 заменить на vanilla-слайдер (Embla/Swiper) |
| `jquery.fancybox` (js+css) | модалки/lightbox | 🔁 заменить (GLightbox и т.п.) |
| `pannellum` (js+css) | 360°-панорамы | ✅ оставить (или нативный 3D-тур share360) |
| `fullpage.min` (js+css) | полноэкранный скролл | 🗑 **удалить** (код закомментирован, но грузится) |
| `jquery.instagramFeed` | лента Instagram | 🗑 **удалить** (API сломан) |
| `animate.min.css` | CSS-анимации | 🗑 **удалить** (не используется, анимации в Sass) |

### PHP (`src/scripts/php/`)

| Файл | Назначение | Вердикт |
|------|-----------|---------|
| `reviews.php` | web-scraping отзывов Яндекс.Карт (org `145451177721`) через phpQuery → `/data/reviews.json` | ⚠️ **хрупко**, заменить |
| `libraries/phpQuery.php` | jQuery-подобный HTML-парсер (>300 КБ) | 🗑 удалить вместе со scraping |
| `libraries/PHPMailer.php`, `SMTP.php`, `Exception.php` | отправка почты | 🗑 **удалить** (подключены, но не используются) |

---

## 3. Внешние интеграции

| Интеграция | Тип | Статус | Риск |
|------------|-----|--------|------|
| Яндекс.Метрика (99962092) | аналитика | ✅ работает | низкий |
| Яндекс.Карты | iframe-карта | ✅ работает | низкий |
| Яндекс.Карты — отзывы (org `145451177721`) | **web-scraping** | ⚠️ хрупко | **высокий** — ломается при смене вёрстки Яндекса |
| Instagram-лента | `$.instagramFeed` | ❌ сломана | высокий — отключить/переделать |
| VK Groups | виджет (страница новостей) | ✅ работает | средний |
| Бронирование `znms` / bronirui-online | внешний виджет (`?znms_widget_open=5558`) | ✅ работает | низкий (чужой сервис) |
| Jivo-чат | live-chat | ✅ работает | низкий |
| Alfa-Bank | iframe оплаты (`alfa.rbsuat.com/...`) | ✅ работает | низкий |
| 3D-туры | iframe share360.ru | ✅ работает | низкий |
| WhatsApp / Telegram / соцсети | ссылки | ✅ работают | низкий |

> ⚠️ Домен оплаты `rbsuat.com` — это **тестовый** контур Сбербанка/Alfa (UAT). Проверить, что в проде используется боевой шлюз.

---

## 4. Sass-архитектура

- **Структура:** `components/` (переиспользуемый UI: buttons, forms, popups, sliders, text, tables, tabs, spoilers, icons, animations) + `blocks/` (секции страниц).
- **Брейкпоинты:** 375 / 576 / 768 / 992 / 1200 px; миксины `+small`, `+medium`, `+large`, `+extra-large`.
- **Сетка:** 12-колоночная, классы `.col-{1..12}` на каждый брейкпоинт (генерируются `@for`).
- **Палитра:** `primaryColor #0f365d`, secondary `#333`, WhatsApp `#25d366`.
- **Шрифты:** Circe (основной, Bold/ExtraBold) + декоративные Caveat, Gabriola, Lucida Calligraphy.

### Антипаттерны (исправить при редизайне)

- 🔴 **`zoom: 90%` на `body`** (и `85%` на `+medium`, `100%` на `+large`) вместо fluid-типографики — масштабирует страницу целиком, ломается на нестандартных экранах.
- 🟠 **Только Sass-переменные**, нет CSS custom properties — нельзя темизировать/менять в рантайме.
- 🟠 Нестрогий БЭМ — смешанные подходы к именованию.
- 🟠 Scroll-listener'ы без debounce (`animations.js`, `fixed-buttons.js`) — лишняя нагрузка на мобильных.

---

## 5. Медиа-аудит — главная проблема производительности

**Итого: 270 медиа-файлов, ~255 МБ.** Это критично для скорости загрузки.

### По форматам

| Формат | Файлов | Размер |
|--------|-------:|-------:|
| `.jpeg` | 132 | 91.62 МБ |
| `.mp4` | 4 | 57.70 МБ |
| `.jpg` | 96 | 49.84 МБ |
| `.mov` | 1 | 46.94 МБ |
| `.png` | 15 | 4.32 МБ |
| `.ttf` | 7 | 2.90 МБ |
| `.otf` | 3 | 1.12 МБ |
| `.pdf` | 2 | 0.47 МБ |
| `.webp` | 1 | 0.19 МБ |
| `.woff2` | 1 | 0.07 МБ |
| `.svg` | 8 | 0.01 МБ |
| **Всего** | | **255.17 МБ** |

### По разделам (топ по весу)

| Размер | Файлов | Папка |
|-------:|-------:|-------|
| 66.78 МБ | 3 | `src/videos` |
| 55.66 МБ | 26 | `src/images/accommodation/house-1` |
| 37.85 МБ | 2 | `src/videos/new-year` |
| 16.93 МБ | 14 | `src/images/new-year` |
| 15.10 МБ | 41 | `src/images/animals` |
| 10.63 МБ | 11 | `src/images/accommodation/house-2` |
| 10.27 МБ | 33 | `src/images/dogs` |
| 9.13 МБ | 18 | `src/images` |
| 5.67 МБ | 17 | `src/images/services/walking` |
| 5.66 МБ | 10 | `src/images/services/buggy` |

### Файлы > 1 МБ (кандидаты на немедленную оптимизацию)

| Размер | Файл |
|-------:|------|
| 46.94 МБ | `src/videos/territory.mov` ← **`.mov`, не для web** |
| 31.32 МБ | `src/videos/new-year/2.mp4` |
| 16.03 МБ | `src/images/accommodation/house-1/12.jpeg` ← **аномалия** |
| 12.38 МБ | `src/images/accommodation/house-1/14.jpeg` |
| 10.02 МБ | `src/videos/summer-mobile.mp4` |
| 9.82 МБ | `src/videos/summer-desktop.mp4` |
| 6.99 МБ | `src/images/accommodation/house-1/22.jpeg` |
| 6.54 МБ | `src/videos/new-year/1.mp4` |
| 5.55 МБ | `src/images/accommodation/house-1/9.jpeg` |
| 5.44 МБ | `src/images/accommodation/house-2/main.jpg` |
| 3.52 / 3.04 МБ | `house-1/10.jpeg`, `house-1/11.jpeg` |
| 2.84 МБ | `src/images/summer-desktop.png` |
| 2.52 МБ | `src/images/new-year/3.jpg` |
| … | + ещё ~18 фото `new-year/`, `animals/`, `services/` по 1–2.3 МБ |
| 1.72 МБ | `src/fonts/Gabriola-Regular.ttf` ← декоративный шрифт, очень тяжёлый |

### План оптимизации медиа

| Действие | Ожидаемая экономия |
|----------|-------------------:|
| JPEG → quality 80–85 (mozjpeg) | ~−45 МБ |
| Видео → H.264/HEVC + битрейт под web, отдельные mobile/desktop версии, `territory.mov` → `.mp4` | ~−50 МБ |
| Генерация **WebP/AVIF** + `<picture>`/`srcset` | ~−30 МБ |
| Шрифты TTF/OTF → **WOFF2**, subset кириллицы | ~−2.5 МБ |
| `loading="lazy"` + responsive `srcset` для всех `<img>` | (ускорение, не вес) |
| **Итого** | **~−125 МБ (≈50%)** |

---

## 6. Деплой

`.github/workflows/deploy-production.yml`:

- Триггер: push в `main` или ручной (`workflow_dispatch`).
- Node 18 → `npm ci` → `npm run build` → `SamKirkland/FTP-Deploy-Action@v4.3.5`.
- Заливка `./build` → `/www/park-sever.ru/`, креды в GitHub Secrets.

**Проблемы:** FTP (не SFTP/SSH); каждый push перетирает прод без версионирования и rollback; нет staging/preview.

---

## 7. Тех-долг по приоритетам

### 🔴 Критично
1. **Медиа ~255 МБ** — главный тормоз; внедрить пайплайн оптимизации (см. §5).
2. **Web-scraping отзывов Яндекса** — заменить на кэш/официальный путь.
3. **`zoom`-вёрстка** — перевести на fluid-типографику и нормальный адаптив.

### 🟠 Высокий
4. Убрать мёртвый код: fullpage.js, Instagram-лента, animate.css, неиспользуемый PHPMailer/phpQuery.
5. Уйти от jQuery (vanilla JS / лёгкие острова).
6. FTP → современный хостинг статики или SFTP + preview-окружение.
7. Проверить платёжный шлюз (UAT `rbsuat.com` в проде?).

### 🟡 Средний
8. Sass-переменные → CSS custom properties.
9. Debounce на scroll-listener'ах.
10. Захардкоженные ID/URL (Яндекс org, VK group, znms widget) → в конфиг.
11. Нет автотестов — заложить Playwright для ключевых сценариев.
