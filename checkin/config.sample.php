<?php
// Образец настроек. config.php в git НЕ коммитится.
// Быстрее всего создать его командой (ключи и пароль подставятся сами):
//   php checkin/cli.php init мойПароль
// Вручную: скопировать этот файл в config.php и заполнить секреты командами
//   php checkin/cli.php key        → encryption_key / app_secret
//   php checkin/cli.php password   → admin_password_hash
// Подробно — checkin/README.md.
return [
    // URL-префикс, под которым модуль открыт на сайте: https://park-sever.ru/checkin/...
    'base_path' => '/checkin',
    // Полный адрес сайта — из него собираются ссылки для гостей
    'public_url' => 'https://park-sever.ru',

    // Папка с базой и PDF. В ПРОДАКШЕНЕ — ВНЕ папки сайта (www), иначе
    // файлы могут отдаваться веб-сервером напрямую. Сервер — в РФ (152-ФЗ, ч.5 ст.18).
    // Пример для Reg.ru: '/var/www/u0124240/data/checkin-data'
    'data_dir' => __DIR__ . '/data',

    // 32 байта в base64: шифрование паспортных данных и PDF на диске (libsodium).
    // ПОТЕРЯ КЛЮЧА = ПОТЕРЯ ВСЕХ ДОГОВОРОВ. Храните копию отдельно от сервера.
    'encryption_key' => '',
    // Отдельный секрет для подписи форм (base64, 32 байта)
    'app_secret' => '',

    // Вход в кабинет администратора
    'admin_login' => 'admin',
    'admin_password_hash' => '',

    // Telegram-уведомления администратору (бот через @BotFather, chat_id — ваш чат)
    'telegram_bot_token' => '',
    'telegram_chat_id' => '',
    // true — в уведомление попадают ФИО Арендатора и госномер (как в ТЗ).
    // Telegram — иностранный сервис: это трансграничная передача ПДн (ст. 12 152-ФЗ),
    // перед включением нужно уведомить Роскомнадзор. По умолчанию — без ПДн.
    'telegram_pii' => false,

    // Доверять X-Forwarded-For только от этих адресов (ваш nginx/прокси). Пусто — брать REMOTE_ADDR.
    'trusted_proxies' => [],

    // Сколько дней после выезда ссылка гостя остаётся рабочей (скачать копию договора)
    'link_days_after_checkout' => 30,
];
