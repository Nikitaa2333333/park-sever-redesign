# Контент-схема «Парк Север» (источник правды)

Все тексты и структура сайта хранятся в JSON в папке `content/`. Дизайн **читает** эти данные и не содержит текста сам. Правило №1: **тексты дословно** из исходных `.pug` — без пересказа, перевода, сокращений, без изменения пунктуации.

## Структура папки

```
content/
  site.json              ← глобальное: компания, контакты, соцсети, реквизиты, интеграции
  sections/*.json        ← переиспользуемые секции (header, footer, contact, map, about, gift-cards…)
  pages/*.json           ← 13 страниц; секции в порядке вывода (inline или ссылка на sections/)
```

## site.json

```json
{
  "company": "Парк Север",
  "domain": "park-sever.ru",
  "contacts": {
    "phone": "+7 929 606-00-45",
    "phoneHref": "tel:+79296060045",
    "email": "park-sever@inbox.ru",
    "emailHref": "mailto:park-sever@inbox.ru",
    "address": "Московская область, Дмитровский городской округ, деревня Василёво, 1",
    "whatsapp": "https://wa.me/79296060045",
    "bookingHref": "?znms_widget_open=5558"
  },
  "socials": [
    { "type": "vk", "href": "https://vk.com/park_sever" }
  ],
  "requisites": { "entity": "ИП Дерюгин П.С.", "inn": "773170493306", "note": "…дословно…" },
  "integrations": { "yandexMetrika": "99962092", "yandexMapsOrg": "145451177721", "jivo": true }
}
```

## Страница `pages/<id>.json`

```json
{
  "id": "home",
  "route": "/",
  "seo": { "title": "…seoTitle дословно…", "description": "…subtitle/description если есть…" },
  "options": { "transparentHeader": true },
  "sections": [
    { "...секция inline..." },
    { "ref": "contact" },
    { "ref": "map" }
  ]
}
```

- `sections` — массив **в порядке вывода на странице**.
- Элемент либо **inline-объект секции** (контент уникален для страницы), либо **ссылка** `{ "ref": "<имя файла в sections/>" }` для переиспользуемых (header/footer не входят в sections — они в layout; в sections перечисляем contact, map, about и т.п.).
- Закомментированные в pug секции (`//- include /...`) тоже фиксируем как inline-объект с `"disabled": true`, чтобы ничего не потерять.

## Объект секции

```json
{
  "id": "welcome",
  "kind": "welcome",
  "anchor": "welcome",
  "disabled": false,
  "blocks": [ /* массив блоков, см. ниже */ ],
  "items": [ /* для повторяющихся структур: карточки, дома, животные, собаки */ ],
  "media": [ /* опц. сводный список медиа этой секции */ ]
}
```

- `anchor` — если у секции есть `#id` в pug (`section.contact#contact` → `"anchor": "contact"`).
- `disabled: true` — если секция/блок закомментированы в pug.

## Типы блоков (`blocks[]`)

| type | поля | пример |
|------|------|--------|
| `overtitle` | `text` | надзаголовок «Связаться с нами» |
| `heading` | `level` (1–4), `text` | h2 «Забронировать дом» |
| `subtitle` | `text` | подзаголовок секции |
| `paragraph` | `text` | абзац |
| `list` | `items` (string[]) | маркированный список |
| `button` | `text`, `href`, `style` (`primary`/`whatsapp`/`outline`) | CTA |
| `link` | `text`, `href` | обычная ссылка |
| `image` | `src`, `alt` | `/images/welcome/1.jpg` |
| `video` | `src`, `poster`, `attrs` (autoplay/loop/muted) | `/videos/summer-desktop.mp4` |
| `iframe` | `src`, `title` | 3D-тур, карта, оплата |
| `gallery` | `images` (string[] путей) | галерея фото |
| `html` | `raw` | запасной вариант для нестандартной разметки |

## Повторяющиеся структуры (`items[]`)

Для карточек/списков объектов — массив с одинаковыми ключами. Примеры:

- **main-menu карточки:** `{ "title": "…", "href": "…", "image": "…" }`
- **дома (houses):** `{ "name": "Gold", "image": "…", "href": "…", "status": "available"|"construction" }`
- **комплектация дома:** группы → `{ "group": "Кухня-гостиная", "items": ["…", "…"] }`
- **животные:** `{ "category": "Крупные", "list": ["лось", "кабан", …], "images": ["…"] }`
- **собаки:** `{ "name": "Бэлла", "born": "30.08.2019", "text": "…дословно…", "images": ["…"] }`
- **акции/сертификаты:** `{ "title": "…", "text": "…", "value": "…" }`

## Жёсткие требования

1. **Verbatim.** Текст копируется буквально из `.pug`. Ничего не придумывать и не сокращать.
2. **Все медиа.** Каждый `img/video/iframe/background` с точным путём (как в исходнике, начиная с `/`).
3. **Все ссылки.** `href` копируются точно (телефон, whatsapp, znms, соцсети, якоря).
4. **Ничего не терять.** Закомментированный контент → `"disabled": true`, но сохраняем.
5. **Валидный JSON**, UTF-8, отступ 2 пробела.
