<?php
declare(strict_types=1);

// Ядро модуля онлайн-чекина: конфиг, БД, шифрование, журнал, хелперы.
// Без фреймворков и composer — чтобы модуль работал на любом российском хостинге с PHP 8.1+.

const CHECKIN_ROOT = __DIR__ . '/..';
const APP_TZ = 'Europe/Moscow';

// Арендодатель / оператор персональных данных
const LANDLORD = [
    'short' => 'ИП Дерюгин П.С.',
    'full' => 'Индивидуальный предприниматель Дерюгин Павел Сергеевич',
    'inn' => '773170493306',
    'address' => 'Московская область, Дмитровский городской округ, вблизи дер. Василёво',
    'email' => 'park-sever@inbox.ru',
];


date_default_timezone_set(APP_TZ);

function cfg(?string $key = null, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $file = CHECKIN_ROOT . '/config.php';
        if (!is_file($file)) {
            throw new RuntimeException('Нет config.php — скопируйте config.sample.php и заполните (см. README.md).');
        }
        $config = require $file;
    }
    if ($key === null) return $config;
    return $config[$key] ?? $default;
}

function data_dir(string $sub = ''): string
{
    $dir = rtrim((string)cfg('data_dir'), '/');
    $path = $sub === '' ? $dir : $dir . '/' . $sub;
    if (!is_dir($path)) {
        mkdir($path, 0700, true);
    }
    return $path;
}

// ── база ──────────────────────────────────────────────────────────────
function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . data_dir() . '/checkin.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS stays (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        token         TEXT NOT NULL UNIQUE,
        house_id      TEXT NOT NULL,
        checkin_at    TEXT NOT NULL,          -- местное время (МСК) 'Y-m-d H:i'
        checkout_at   TEXT NOT NULL,
        price         INTEGER NOT NULL,       -- руб.
        deposit       INTEGER NOT NULL,
        guest_label   TEXT NOT NULL DEFAULT '', -- пометка администратора (кто бронировал)
        guest_phone   TEXT NOT NULL DEFAULT '',
        admin_note    TEXT NOT NULL DEFAULT '',
        status        TEXT NOT NULL DEFAULT 'created', -- created|sent|signed|living|checked_out|cancelled
        resign_required INTEGER NOT NULL DEFAULT 0,
        tenant_short  TEXT NOT NULL DEFAULT '', -- «Мирошников Д.С.» после подписи (для журнала)
        created_at    TEXT NOT NULL,
        updated_at    TEXT NOT NULL,
        sent_at       TEXT,
        opened_at     TEXT,
        signed_at     TEXT
    );
    CREATE TABLE IF NOT EXISTS extensions (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        stay_id       INTEGER NOT NULL REFERENCES stays(id),
        old_checkout_at TEXT NOT NULL,
        new_checkout_at TEXT NOT NULL,
        surcharge     INTEGER NOT NULL,
        status        TEXT NOT NULL DEFAULT 'pending', -- pending|signed|cancelled
        created_at    TEXT NOT NULL,
        signed_at     TEXT
    );
    CREATE TABLE IF NOT EXISTS signatures (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        stay_id       INTEGER NOT NULL REFERENCES stays(id),
        kind          TEXT NOT NULL,          -- contract|extension
        extension_id  INTEGER REFERENCES extensions(id),
        doc_number    TEXT NOT NULL,
        signed_at_utc TEXT NOT NULL,          -- ISO 8601 c микросекундами, UTC
        ip            TEXT NOT NULL,
        forwarded_for TEXT NOT NULL DEFAULT '',
        user_agent    TEXT NOT NULL,
        client_meta   TEXT NOT NULL DEFAULT '{}', -- часовой пояс/экран/язык устройства
        doc_version   TEXT NOT NULL,          -- версия шаблона договора
        doc_sha256    TEXT NOT NULL,          -- хэш текста оферты, показанного гостю
        terms_json    TEXT NOT NULL,          -- снимок условий (дом, даты, суммы)
        payload_enc   TEXT NOT NULL,          -- анкета гостя (зашифрована)
        pdf_file      TEXT NOT NULL,
        pdf_sha256    TEXT NOT NULL,
        audit_hash    TEXT NOT NULL DEFAULT ''
    );
    CREATE TABLE IF NOT EXISTS audit (
        id        INTEGER PRIMARY KEY AUTOINCREMENT,
        stay_id   INTEGER,
        at_utc    TEXT NOT NULL,
        actor     TEXT NOT NULL,              -- guest|admin|system
        event     TEXT NOT NULL,
        ip        TEXT NOT NULL DEFAULT '',
        user_agent TEXT NOT NULL DEFAULT '',
        data      TEXT NOT NULL DEFAULT '{}',
        prev_hash TEXT NOT NULL,
        hash      TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS login_attempts (
        ip TEXT NOT NULL, at INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS idx_audit_stay ON audit(stay_id);
    CREATE INDEX IF NOT EXISTS idx_sig_stay ON signatures(stay_id);
    CREATE INDEX IF NOT EXISTS idx_ext_stay ON extensions(stay_id);
    SQL);
}

function now_local(): string { return date('Y-m-d H:i:s'); }

function now_utc_precise(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
}

// ── шифрование (libsodium, XSalsa20-Poly1305) ─────────────────────────
function enc_key(): string
{
    $key = base64_decode((string)cfg('encryption_key'), true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('encryption_key в config.php не задан или неверной длины (php cli.php key).');
    }
    return $key;
}

function encrypt_str(string $plain): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, enc_key()));
}

function decrypt_str(string $blob): string
{
    $raw = base64_decode($blob, true);
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, enc_key());
    if ($plain === false) throw new RuntimeException('Не удалось расшифровать данные (неверный ключ?)');
    return $plain;
}

// ── журнал событий с хэш-цепочкой ─────────────────────────────────────
// Каждая запись содержит хэш предыдущей: подмена/удаление любой строки задним числом
// ломает цепочку, что видно при проверке (audit_verify). Последний хэш уходит
// в Telegram при подписании — внешний «якорь», который нельзя переписать на сервере.
function audit(?int $stayId, string $actor, string $event, array $data = []): string
{
    $pdo = db();
    $prev = $pdo->query('SELECT hash FROM audit ORDER BY id DESC LIMIT 1')->fetchColumn() ?: str_repeat('0', 64);
    $row = [
        'stay_id' => $stayId,
        'at_utc' => now_utc_precise(),
        'actor' => $actor,
        'event' => $event,
        'ip' => PHP_SAPI === 'cli' ? 'cli' : client_ip(),
        'user_agent' => PHP_SAPI === 'cli' ? 'cli' : mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
    $hash = audit_hash($prev, $row);
    $st = $pdo->prepare('INSERT INTO audit (stay_id, at_utc, actor, event, ip, user_agent, data, prev_hash, hash)
        VALUES (:stay_id, :at_utc, :actor, :event, :ip, :user_agent, :data, :prev_hash, :hash)');
    $st->execute($row + ['prev_hash' => $prev, 'hash' => $hash]);
    return $hash;
}

function audit_hash(string $prev, array $row): string
{
    return hash('sha256', $prev . '|' . json_encode([
        $row['stay_id'], $row['at_utc'], $row['actor'], $row['event'], $row['ip'], $row['user_agent'], $row['data'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** @return array{ok:bool, count:int, broken_at:?int} */
function audit_verify(): array
{
    $prev = str_repeat('0', 64);
    $n = 0;
    foreach (db()->query('SELECT * FROM audit ORDER BY id') as $row) {
        $n++;
        if ($row['prev_hash'] !== $prev || audit_hash($prev, $row) !== $row['hash']) {
            return ['ok' => false, 'count' => $n, 'broken_at' => (int)$row['id']];
        }
        $prev = $row['hash'];
    }
    return ['ok' => true, 'count' => $n, 'broken_at' => null];
}

// ── запрос/ответ ──────────────────────────────────────────────────────
function client_ip(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $trusted = (array)cfg('trusted_proxies', []);
    if ($trusted && in_array($remote, $trusted, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return $remote;
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim((string)cfg('base_path', ''), '/') . '/' . ltrim($path, '/');
}

function public_link(string $token): string
{
    return rtrim((string)cfg('public_url'), '/') . url($token);
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)), true, 303);
    exit;
}

function render(string $view, array $vars = []): string
{
    extract($vars, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/views/' . $view . '.php';
    return (string)ob_get_clean();
}

function asset(string $file): string
{
    $path = CHECKIN_ROOT . '/assets/' . $file;
    return url('assets/' . $file) . (is_file($path) ? '?v=' . filemtime($path) : '');
}

function not_found(string $message = 'Страница не найдена'): never
{
    http_response_code(404);
    echo render('guest/layout', ['title' => 'Ссылка недействительна', 'body' => render('guest/closed', ['message' => $message])]);
    exit;
}

// Подпись форм без cookie: HMAC(секрет, контекст|время). Гостю не нужны куки,
// ссылка работает в любом встроенном браузере мессенджера.
function form_token(string $context): string
{
    $ts = (string)time();
    return $ts . '.' . hash_hmac('sha256', $context . '|' . $ts, app_secret());
}

function form_token_valid(string $context, string $token, int $maxAge = 86400): bool
{
    [$ts, $mac] = array_pad(explode('.', $token, 2), 2, '');
    if (!ctype_digit($ts) || time() - (int)$ts > $maxAge) return false;
    return hash_equals(hash_hmac('sha256', $context . '|' . $ts, app_secret()), $mac);
}

function app_secret(): string
{
    $s = (string)cfg('app_secret');
    if (strlen($s) < 32) throw new RuntimeException('app_secret в config.php не задан (php cli.php key).');
    return $s;
}

function new_token(): string
{
    // 128 бит случайности → 22 символа base64url. Угадать ссылку перебором невозможно.
    return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
}

// ── форматирование ───────────────────────────────────────────────────
function fmt_date(string $dt): string { return date('d.m.Y', strtotime($dt)); }
function fmt_time(string $dt): string { return date('H:i', strtotime($dt)); }
function fmt_dt(string $dt): string { return date('d.m.Y H:i', strtotime($dt)); }
function fmt_rub(int $n): string { return number_format($n, 0, ',', "\u{202F}") . "\u{00A0}₽"; }

function fmt_utc_as_msk(string $utc): string
{
    $d = new DateTimeImmutable($utc);
    return $d->setTimezone(new DateTimeZone(APP_TZ))->format('d.m.Y H:i:s') . ' (МСК, UTC+3)';
}

function nights(string $in, string $out): int
{
    $a = new DateTimeImmutable(substr($in, 0, 10));
    $b = new DateTimeImmutable(substr($out, 0, 10));
    return max(1, (int)$a->diff($b)->days);
}

function plural(int $n, string $one, string $few, string $many): string
{
    $n10 = $n % 10; $n100 = $n % 100;
    if ($n10 === 1 && $n100 !== 11) return $one;
    if ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) return $few;
    return $many;
}

function houses(): array
{
    static $h = null;
    return $h ??= require __DIR__ . '/data/houses.php';
}

function house(string $id): array
{
    return houses()[$id] ?? ['name' => $id, 'short' => $id, 'inventory' => [], 'defects' => []];
}

const STATUS_LABELS = [
    'created' => 'Ссылка создана',
    'sent' => 'Ссылка отправлена',
    'signed' => 'Договор подписан',
    'living' => 'Гость проживает',
    'checked_out' => 'Выезд оформлен',
    'cancelled' => 'Отменено',
];

function stay_by_token(string $token): ?array
{
    $st = db()->prepare('SELECT * FROM stays WHERE token = ?');
    $st->execute([$token]);
    return $st->fetch() ?: null;
}

function stay_by_id(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM stays WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function pending_extension(int $stayId): ?array
{
    $st = db()->prepare("SELECT * FROM extensions WHERE stay_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
    $st->execute([$stayId]);
    return $st->fetch() ?: null;
}

function signatures_for(int $stayId): array
{
    $st = db()->prepare('SELECT * FROM signatures WHERE stay_id = ? ORDER BY id');
    $st->execute([$stayId]);
    return $st->fetchAll();
}

function latest_contract_signature(int $stayId): ?array
{
    $st = db()->prepare("SELECT * FROM signatures WHERE stay_id = ? AND kind = 'contract' ORDER BY id DESC LIMIT 1");
    $st->execute([$stayId]);
    return $st->fetch() ?: null;
}

function contract_number(array $stay): string
{
    return 'ПС-' . substr($stay['created_at'], 0, 4) . '-' . str_pad((string)$stay['id'], 4, '0', STR_PAD_LEFT);
}

/** Условия, которые фиксируются в оферте и не редактируются гостем. */
function stay_terms(array $stay): array
{
    $house = house($stay['house_id']);
    return [
        'contract_number' => contract_number($stay),
        'house_id' => $stay['house_id'],
        'house_name' => $house['name'],
        'checkin_at' => $stay['checkin_at'],
        'checkout_at' => $stay['checkout_at'],
        'nights' => nights($stay['checkin_at'], $stay['checkout_at']),
        'price' => (int)$stay['price'],
        'deposit' => (int)$stay['deposit'],
    ];
}

function link_active(array $stay): bool
{
    if ($stay['status'] === 'cancelled') return false;
    $until = strtotime($stay['checkout_at']) + 86400 * (int)cfg('link_days_after_checkout', 30);
    return time() <= $until;
}

function security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    // Токен живёт в URL — не отдаём его третьим сайтам через Referer
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
}

// ── поле формы (общая разметка для анкеты и кабинета) ─────────────────
function field(string $name, string $label, array $v, array $errors, array $attrs = [], string $hint = ''): string
{
    $type = $attrs['type'] ?? 'text';
    $req = !empty($attrs['required']);
    unset($attrs['type'], $attrs['required']);
    $a = '';
    foreach ($attrs as $k => $val) $a .= ' ' . $k . '="' . h((string)$val) . '"';
    $err = $errors[$name] ?? null;
    $id = 'f-' . $name;
    $value = (string)($v[$name] ?? '');
    $html = '<div class="fld' . ($err ? ' fld--err' : '') . '">'
        . '<label for="' . $id . '">' . h($label) . ($req ? '' : ' <span class="fld__opt">необязательно</span>') . '</label>';
    if ($type === 'textarea') {
        $html .= '<textarea id="' . $id . '" name="' . $name . '"' . ($req ? ' required' : '') . $a . '>' . h($value) . '</textarea>';
    } else {
        $html .= '<input id="' . $id . '" name="' . $name . '" type="' . $type . '" value="' . h($value) . '"' . ($req ? ' required' : '') . $a . '>';
    }
    if ($hint) $html .= '<p class="fld__hint">' . h($hint) . '</p>';
    $html .= '<p class="fld__err" data-err-for="' . $name . '">' . h($err ?? '') . '</p></div>';
    return $html;
}
