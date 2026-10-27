<?php
declare(strict_types=1);

require_once __DIR__ . '/notify.php';

// «Меню и допы» по ТЗ заказчицы 21.10.2026. Гость по своей ссылке (/checkin/<токен>/menu)
// отмечает, что приготовить к приезду, подтверждает выбор (0 ₽) или оплачивает доплату
// через СБП; после оплаты видит заказ и может докупить и поменять время приезда до дедлайна.
// Каталог и матрица тарифов — app/data/menu.php.
//
// Оплата: автоматического эквайринга пока нет. «Оплатить через СБП» фиксирует сумму и
// показывает ссылку на оплату (config: sbp_pay_url) — администратор отмечает «Оплата
// получена» в карточке заезда. Подключение эквайринга Альфа-Банка заменит этот шаг.
//
// Не сделано (ждёт решений заказчицы): дозаказ меню на каждый день заезда от 3 ночей,
// загрузка фото-референсов, цены пакетов, Welcome-стола и шеф-повара.

function menu_catalog(): array
{
    static $m = null;
    return $m ??= require __DIR__ . '/data/menu.php';
}

/** Тариф прежней редакции → новый ('none' → 'duo' и т. д.). */
function tariff_key(string $tariff): string
{
    return menu_catalog()['legacy'][$tariff] ?? $tariff;
}

function tariff_cfg(string $tariff): ?array
{
    return menu_catalog()['tariffs'][tariff_key($tariff)] ?? null;
}

function tariff_label(string $tariff): string
{
    return tariff_cfg($tariff)['label'] ?? '';
}

/**
 * Тариф заезда из карточки с поправкой на длину: гастрономия доступна только от 2 ночей,
 * на 1 ночь «с гастрономией» превращается в тариф без еды.
 */
function booked_tariff(string $tariff, int $nights): string
{
    $t = tariff_key($tariff);
    $cfg = tariff_cfg($t);
    if (!$cfg) return '';
    if ($nights < ($cfg['min_nights'] ?? 1)) return $t === 'party_gastro' ? 'party' : 'duo';
    return $t;
}

/** Пакеты, которые гость может выбрать сам: только когда в карточке «для двоих». */
function package_options(string $booked, int $nights): array
{
    if ($booked !== 'duo') return [];
    return array_filter(menu_catalog()['tariffs'], fn($t, $id) => $id !== 'duo' && $nights >= ($t['min_nights'] ?? 1), ARRAY_FILTER_USE_BOTH);
}

/** Тариф, по которому считается экран: из карточки или пакет, выбранный гостем. */
function effective_tariff(string $booked, int $nights, array $data): string
{
    $p = (string)($data['_package'] ?? '');
    return $p !== '' && isset(package_options($booked, $nights)[$p]) ? $p : $booked;
}

function tariff_gastro(string $tariff): bool
{
    return (bool)(tariff_cfg($tariff)['gastro'] ?? false);
}

function section_visible(array $s, string $tariff, int $nights, array $data): bool
{
    $gastro = tariff_gastro($tariff);
    if (isset($s['gastro']) && $s['gastro'] !== $gastro) return false;
    if ($nights < ($s['min_nights'] ?? 1)) return false;
    // шеф-повар без гастрономии — только к нашему гриль-меню
    if (!$gastro && !empty($s['requires_unless_gastro']) && empty($data[$s['requires_unless_gastro']]['picks'])) return false;
    return true;
}

/** Разделы, видимые при тарифе и длине заезда (data — для правил «только если выбрано»). */
function menu_sections(string $tariff, int $nights, array $data = [], bool $ignoreRequires = false): array
{
    return array_values(array_filter(menu_catalog()['sections'], function ($s) use ($tariff, $nights, $data, $ignoreRequires) {
        if ($ignoreRequires) unset($s['requires_unless_gastro']);
        return section_visible($s, $tariff, $nights, $data);
    }));
}

/** Раздел входит в стоимость тарифа: выбор есть, цены нет. */
function section_free(array $s, string $tariff): bool
{
    return in_array($s['id'], tariff_cfg($tariff)['free'] ?? [], true);
}

/** Блок целиком входит в тариф — в заголовке пишем «0 ₽ · включено в тариф». */
function group_included(array $sections, string $tariff): bool
{
    $any = false;
    foreach ($sections as $s) {
        $in = section_free($s, $tariff) || ($s['gastro'] ?? null) === true;
        if (!empty($s['picks']) && !$in) return false;
        $any = $any || $in;
    }
    return $any;
}

/** Сколько позиций можно выбрать в группе (0 — без ограничения). */
function pick_max(array $pick, int $nights, ?array $s = null, string $tariff = ''): int
{
    $m = $pick['max'] ?? 0;
    $m = is_array($m) ? (int)($m['nights'][$nights] ?? $m['default'] ?? 0) : (int)$m;
    // бесплатный в тарифе раздел: входит ограниченное число позиций (например, один сет шаров)
    if ($s && !empty($s['free_max']) && section_free($s, $tariff) && ($m === 0 || $m > $s['free_max'])) $m = (int)$s['free_max'];
    return $m;
}

/** Опция входит в тариф — показывается без цены и без выбора. */
function option_in_tariff(array $opt, string $tariff): bool
{
    return !empty($opt['gastro']) && tariff_gastro($tariff);
}

// ── сроки ────────────────────────────────────────────────────────────

/** До этого момента гость может менять выбор и время приезда. */
function menu_cutoff_ts(array $stay): int
{
    return strtotime($stay['checkin_at']) - 3600 * (int)(menu_catalog()['cutoff_hours'] ?? 24);
}

function menu_open(array $stay): bool
{
    return time() < menu_cutoff_ts($stay);
}

/** Раздел со своим сроком (торт — за 5 суток) уже закрыт. */
function section_closed(array $s, array $stay): bool
{
    return !empty($s['deadline_days']) && time() >= strtotime($stay['checkin_at']) - 86400 * (int)$s['deadline_days'];
}

/** Варианты времени прибытия: [значение => подпись]. */
function arrival_options(): array
{
    $a = menu_catalog()['arrival'];
    $out = [];
    $t = strtotime('2000-01-01 ' . $a['from']);
    $last = strtotime('2000-01-01 ' . $a['last']);
    for (; $t < $last; $t += 60 * (int)$a['step']) $out[date('H:i', $t)] = date('H:i', $t);
    $out[array_key_first($out)] .= ' (стандартное время заезда)';
    $out[$a['last'] . '+'] = $a['last'] . ' и позже (бесконтактный поздний заезд)';
    return $out;
}

/** «17:30» / «после 22:00» — для статуса и уведомления. */
function arrival_text(array $data, array $stay): string
{
    $t = (string)($data['arrival']['fields']['time'] ?? '');
    if ($t === '') return fmt_time($stay['checkin_at']);
    return str_ends_with($t, '+') ? 'после ' . rtrim($t, '+') : $t;
}

// ── разбор и расчёт ──────────────────────────────────────────────────

/**
 * Разбор формы гостя → [данные, ошибки].
 * Данные: ['_package' => тариф|'', раздел => ['picks' => [группа => [id]], 'fields' => [поле => значение]]].
 * $saved — сохранённый выбор (для разделов, срок которых прошёл), $paid — оплаченный
 * снимок: оплаченные позиции нельзя снять, они возвращаются в выбор.
 */
function menu_parse(array $in, array $stay, string $booked, int $nights, array $saved, array $paid): array
{
    $data = [];
    $errors = [];
    $pkg = (string)($in['package'] ?? '');
    $data['_package'] = isset(package_options($booked, $nights)[$pkg]) ? $pkg : '';
    $tariff = effective_tariff($booked, $nights, $data);

    foreach (menu_catalog()['sections'] as $s) {
        if (!section_visible($s, $tariff, $nights, $data)) continue;
        $sid = $s['id'];
        if (section_closed($s, $stay)) {
            if (!empty($saved[$sid])) $data[$sid] = $saved[$sid];
            continue;
        }
        $free = section_free($s, $tariff);
        $row = ['picks' => [], 'fields' => []];
        foreach ($s['picks'] ?? [] as $p) {
            $valid = array_column(array_filter($p['options'], fn($o) => !option_in_tariff($o, $tariff)), 'id');
            $got = $in['m'][$sid][$p['id']] ?? [];
            $got = array_values(array_intersect(array_map('strval', (array)$got), $valid));
            $locked = $free ? [] : array_values(array_intersect($paid[$sid]['picks'][$p['id']] ?? [], $valid));
            $max = pick_max($p, $nights, $s, $tariff);
            if ($locked) $got = $max === 1 ? $locked : array_values(array_unique(array_merge($locked, $got)));
            if ($max > 0 && count($got) > $max) {
                $errors[$sid] = 'Можно выбрать не больше ' . $max . ' ' . plural($max, 'позиции', 'позиций', 'позиций');
                $got = array_slice($got, 0, $max);
            }
            if ($got) $row['picks'][$p['id']] = $got;
        }
        foreach ($s['fields'] ?? [] as $f) {
            $val = $in['m'][$sid]['f'][$f['id']] ?? '';
            $val = match ($f['type']) {
                'check' => !empty($val) ? '1' : '',
                'select' => array_key_exists((string)$val, arrival_options()) ? (string)$val : '',
                default => mb_substr(trim((string)$val), 0, 1000),
            };
            if (!empty($f['when']) && empty($row['fields'][$f['when']])) $val = '';
            if ($val !== '') $row['fields'][$f['id']] = $val;
        }
        if ($row['picks'] || $row['fields']) $data[$sid] = $row;
    }
    return [$data, $errors];
}

/** Итог по выбранным платным позициям: [сумма, есть ли позиции с неизвестной ценой]. */
function menu_total(array $data, string $booked, int $nights): array
{
    $tariff = effective_tariff($booked, $nights, $data);
    $sum = 0;
    $unknown = false;
    if ($tariff !== $booked) {
        $price = tariff_cfg($tariff)['price'] ?? null;
        if ($price === null) $unknown = true; else $sum += (int)$price;
    }
    foreach (menu_sections($tariff, $nights, $data) as $s) {
        if (section_free($s, $tariff)) continue;
        foreach ($s['picks'] ?? [] as $p) {
            $chosen = $data[$s['id']]['picks'][$p['id']] ?? [];
            foreach ($p['options'] as $o) {
                if (!in_array($o['id'], $chosen, true) || !array_key_exists('price', $o) || option_in_tariff($o, $tariff)) continue;
                if ($o['price'] === null) $unknown = true; else $sum += (int)$o['price'];
            }
        }
    }
    return [$sum, $unknown];
}

function menu_load(int $stayId): ?array
{
    $st = db()->prepare('SELECT * FROM menu_orders WHERE stay_id = ?');
    $st->execute([$stayId]);
    $row = $st->fetch();
    if (!$row) return null;
    $dec = fn(string $blob) => $blob === '' ? [] : (json_decode(decrypt_str($blob), true) ?: []);
    $row['data'] = $dec($row['payload_enc']);
    $row['paid'] = $dec($row['paid_enc']);
    $row['pending'] = $dec($row['pending_enc']);
    return $row;
}

/** Выбор гостя по блокам: [[блок, emoji, [строки]]] — для кабинета, шторки «Состав заказа» и Telegram. */
function menu_summary(array $data, string $booked, int $nights, array $opts = []): array
{
    $tariff = effective_tariff($booked, $nights, $data);
    $prices = $opts['prices'] ?? true;
    $comments = $opts['comments'] ?? true; // свободный текст (аллергии и т. п.) — не в Telegram без telegram_pii
    $groups = menu_catalog()['groups'];
    $out = [];
    if ($tariff !== $booked) {
        $out['package'] = [$groups['package']['title'], $groups['package']['emoji'] ?? '', ['«' . tariff_cfg($tariff)['short'] . '»' . ($prices ? ' — цена уточняется' : '')]];
    }
    foreach (menu_sections($tariff, $nights, $data) as $s) {
        if (empty($data[$s['id']])) continue;
        $free = section_free($s, $tariff);
        $lines = [];
        foreach ($s['picks'] ?? [] as $p) {
            $chosen = $data[$s['id']]['picks'][$p['id']] ?? [];
            foreach ($p['options'] as $o) {
                if (!in_array($o['id'], $chosen, true)) continue;
                $price = '';
                if ($prices) {
                    $price = ($free || ($s['gastro'] ?? null) === true) ? ' — входит в тариф'
                        : (array_key_exists('price', $o) ? ($o['price'] === null ? ' — цена уточняется' : ($o['price'] ? ' — ' . fmt_rub((int)$o['price']) : '')) : '');
                }
                $lines[] = (!empty($p['label']) && !in_array($p['id'], ['items', 'size', 'set'], true) ? $p['label'] . ': ' : '') . $o['name'] . $price;
            }
        }
        foreach ($s['fields'] ?? [] as $f) {
            $v = $data[$s['id']]['fields'][$f['id']] ?? '';
            if ($v === '') continue;
            if ($f['type'] === 'check') $lines[] = '✓ ' . ($f['short'] ?? $f['label']);
            elseif ($f['type'] === 'select') $lines[] = $f['label'] . ': ' . (arrival_options()[$v] ?? $v);
            else $lines[] = $f['label'] . ': ' . ($comments || !empty($f['safe']) ? $v : 'есть, см. кабинет');
        }
        if (!$lines) continue;
        $gid = $s['group'];
        $out[$gid] ??= [$groups[$gid]['title'], $groups[$gid]['emoji'] ?? '', []];
        array_push($out[$gid][2], ...$lines);
    }
    // порядок блоков — как на экране
    return array_values(array_replace(array_intersect_key($groups, $out), $out));
}

/** «17–19 октября» / «31 октября – 2 ноября». */
function menu_dates(array $stay): string
{
    $in = strtotime($stay['checkin_at']);
    $out = strtotime($stay['checkout_at']);
    return date('n', $in) === date('n', $out)
        ? date('j', $in) . '–' . fmt_dm($stay['checkout_at'])
        : fmt_dm($stay['checkin_at']) . ' – ' . fmt_dm($stay['checkout_at']);
}

/** Уведомление администратору: 'confirm' — выбор подтверждён, 'pay' — ждёт оплаты, 'paid' — оплата получена. */
function menu_notify(array $stay, array $order, string $kind): void
{
    $nights = nights($stay['checkin_at'], $stay['checkout_at']);
    $booked = booked_tariff((string)$stay['tariff'], $nights);
    $data = $order['data'];
    $head = match ($kind) {
        'pay' => 'ВЫБОР МЕНЮ — ЖДЁТ ОПЛАТЫ',
        'paid' => 'ОПЛАТА ДОПОВ ПОЛУЧЕНА',
        default => empty($order['confirmed_at']) ? 'НОВЫЙ ВЫБОР МЕНЮ' : 'ИЗМЕНЕНИЕ ВЫБОРА МЕНЮ',
    };
    $t = "🌲 $head: Заезд " . contract_number($stay) . "\n\n";
    $t .= '📅 Дата: ' . menu_dates($stay) . ' | Время: ~' . arrival_text($data, $stay) . "\n";
    $t .= 'Дом: ' . house($stay['house_id'])['name'] . "\n";
    $t .= 'Тариф: ' . tariff_label(effective_tariff($booked, $nights, $data)) . "\n";
    $t .= "---------------------------------\n";
    foreach (menu_summary($data, $booked, $nights, ['comments' => (bool)cfg('telegram_pii', false)]) as [$title, $emoji, $lines]) {
        $t .= "\n" . ($emoji ? "$emoji " : '') . mb_strtoupper($title) . ":\n";
        foreach ($lines as $l) $t .= "- $l\n";
    }
    [$total, $unknown] = menu_total($data, $booked, $nights);
    $paid = (int)$order['paid_total'];
    $t .= "\n💰 ";
    $t .= match ($kind) {
        'pay' => 'К оплате через СБП: ' . fmt_rub((int)$order['pending_total']) . ' (ожидаем оплату)' . ($paid ? ', ранее оплачено ' . fmt_rub($paid) : ''),
        'paid' => 'Оплата допов: ' . fmt_rub($paid) . ' (оплачено СБП)',
        default => $paid ? 'Оплата допов: ' . fmt_rub($paid) . ' (оплачено СБП)' : 'Доплата: 0 ₽',
    };
    if ($unknown) $t .= "\n⚠ Есть позиции с ценой по запросу — уточнить с гостем.";
    notify_admin($t);
}

// ── экран гостя ──────────────────────────────────────────────────────

function guest_menu(array $stay): void
{
    $nights = nights($stay['checkin_at'], $stay['checkout_at']);
    $booked = booked_tariff((string)$stay['tariff'], $nights);
    if ($booked === '') {
        not_found('Выбор меню для этого заезда пока не открыт. Мы пришлём ссылку, когда всё будет готово.');
    }
    $order = menu_load((int)$stay['id']);
    $data = $order['data'] ?? [];
    $paid = $order['paid'] ?? [];
    $hidePrices = (string)$stay['price_lock'] !== '';
    $open = menu_open($stay);
    $errors = [];
    $flash = null;

    $do = (string)($_POST['_do'] ?? 'save');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($do, ['lock', 'unlock'], true)) {
        menu_price_lock($stay, $do);
        return;
    }
    if (isset($_GET['locked'])) $flash = 'Цены скрыты. Теперь ссылку можно переслать — ценников по ней не видно. Вернуть цены — тем же кодом.';
    if (isset($_GET['unlocked'])) $flash = 'Цены снова видны.';
    if (isset($_GET['badcode'])) $errors['_form'] = 'Код не подошёл.';
    if (isset($_GET['saved'])) $flash = 'Выбор сохранён. Его можно поменять по этой же ссылке.';
    if (isset($_GET['confirmed'])) $flash = 'Спасибо! Выбор подтверждён — мы получили его и начнём подготовку.';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$open) {
            $errors['_form'] = 'Заказ уже передан на кухню — изменения по ссылке закрыты. Для срочных изменений свяжитесь с нами напрямую.';
        } elseif (!form_token_valid('menu|' . $stay['token'], (string)($_POST['_ft'] ?? ''), 86400 * 30)) {
            $errors['_form'] = 'Страница устарела. Проверьте выбор и нажмите кнопку ещё раз.';
            [$data] = menu_parse($_POST, $stay, $booked, $nights, $data, $paid);
        } else {
            [$data, $errors] = menu_parse($_POST, $stay, $booked, $nights, $data, $paid);
            if ($hidePrices) $do = 'save'; // режим подарка: выбирают без цен, платит тот, кто скрыл
            if (!$errors && in_array($do, ['confirm', 'pay'], true)) {
                menu_submit($stay, $order, $data, $booked, $nights, $do);
                return;
            }
            menu_store($stay, $order, $data, $booked, $nights);
            audit((int)$stay['id'], 'guest', 'menu_saved', ['tariff' => effective_tariff($booked, $nights, $data)]);
            if (!$errors) redirect($stay['token'] . '/menu?saved=1' . (($_POST['_anchor'] ?? '') === 'package' ? '#m-package' : ''));
        }
    }

    echo render('guest/layout', ['title' => 'Меню и допы', 'body' => render('guest/menu', [
        'stay' => $stay, 'booked' => $booked, 'nights' => $nights, 'data' => $data, 'errors' => $errors,
        'flash' => $flash, 'order' => $order, 'paid' => $paid, 'open' => $open,
        'hidePrices' => $hidePrices,
        'formToken' => form_token('menu|' . $stay['token']),
    ])]);
}

/** Сохранить выбор, статус заказа не меняется. */
function menu_store(array $stay, ?array $order, array $data, string $booked, int $nights): void
{
    [$total] = menu_total($data, $booked, $nights);
    if ($order) {
        db()->prepare('UPDATE menu_orders SET tariff = ?, payload_enc = ?, total = ?, updated_at = ? WHERE stay_id = ?')
            ->execute([$booked, encrypt_str(json_encode($data, JSON_UNESCAPED_UNICODE)), $total, now_local(), $stay['id']]);
    } else {
        db()->prepare('INSERT INTO menu_orders (stay_id, tariff, payload_enc, total, updated_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$stay['id'], $booked, encrypt_str(json_encode($data, JSON_UNESCAPED_UNICODE)), $total, now_local()]);
    }
}

/**
 * «Подтвердить выбор» (доплаты нет) или «Оплатить через СБП» (есть доплата).
 * Доплата = итог − уже оплачено. Сумма к оплате фиксируется вместе со снимком выбора:
 * когда администратор отметит оплату, этот снимок станет «оплаченным» и позиции из него
 * гость уже не снимет.
 */
function menu_submit(array $stay, ?array $order, array $data, string $booked, int $nights, string $do): void
{
    menu_store($stay, $order, $data, $booked, $nights);
    $order = menu_load((int)$stay['id']);
    [$total] = menu_total($data, $booked, $nights);
    $due = max(0, $total - (int)$order['paid_total']);
    $now = now_local();
    if ($due === 0) {
        db()->prepare("UPDATE menu_orders SET status = CASE WHEN status = 'paid' THEN 'paid' ELSE 'confirmed' END, pending_total = 0, pending_enc = '', confirmed_at = ? WHERE stay_id = ?")
            ->execute([$now, $stay['id']]);
        audit((int)$stay['id'], 'guest', 'menu_confirmed', ['total' => $total]);
        menu_notify($stay, $order, 'confirm');
        redirect($stay['token'] . '/menu?confirmed=1');
    }
    db()->prepare('UPDATE menu_orders SET pending_total = ?, pending_enc = ?, confirmed_at = ? WHERE stay_id = ?')
        ->execute([$due, encrypt_str(json_encode($data, JSON_UNESCAPED_UNICODE)), $now, $stay['id']]);
    audit((int)$stay['id'], 'guest', 'menu_payment_requested', ['due' => $due]);
    menu_notify($stay, ['pending_total' => $due] + $order, 'pay');
    redirect($stay['token'] . '/menu?pay=1#m-pay');
}

/** Кабинет: оплата получена — снимок выбора становится оплаченным. */
function menu_mark_paid(array $stay): bool
{
    $order = menu_load((int)$stay['id']);
    if (!$order || (int)$order['pending_total'] <= 0) return false;
    $paidTotal = (int)$order['paid_total'] + (int)$order['pending_total'];
    db()->prepare("UPDATE menu_orders SET status = 'paid', paid_total = ?, paid_enc = pending_enc, pending_total = 0, pending_enc = '', paid_at = ? WHERE stay_id = ?")
        ->execute([$paidTotal, now_local(), $stay['id']]);
    audit((int)$stay['id'], 'admin', 'menu_paid', ['amount' => (int)$order['pending_total'], 'paid_total' => $paidTotal]);
    $order = menu_load((int)$stay['id']);
    $order['data'] = $order['paid'];
    menu_notify($stay, $order, 'paid');
    return true;
}

/**
 * «Скрыть цены» — подарок: гость прячет ценники кодом и пересылает ссылку, второй
 * человек выбирает допы без цен; вернуть цены можно только тем же кодом.
 * Состояние хранится у заезда — по пересланной ссылке цены тоже скрыты.
 */
function menu_price_lock(array $stay, string $do): void
{
    $back = $stay['token'] . '/menu';
    if (!form_token_valid('menu|' . $stay['token'], (string)($_POST['_ft'] ?? ''), 86400 * 30)) redirect($back);
    $code = trim((string)($_POST['code'] ?? ''));
    if ($do === 'lock') {
        if (mb_strlen($code) < 4) redirect($back . '?badcode=1');
        db()->prepare('UPDATE stays SET price_lock = ? WHERE id = ?')->execute([password_hash($code, PASSWORD_DEFAULT), $stay['id']]);
        audit((int)$stay['id'], 'guest', 'menu_prices_hidden');
        redirect($back . '?locked=1');
    }
    if ((string)$stay['price_lock'] === '' || !password_verify($code, (string)$stay['price_lock'])) redirect($back . '?badcode=1');
    db()->prepare("UPDATE stays SET price_lock = '' WHERE id = ?")->execute([$stay['id']]);
    audit((int)$stay['id'], 'guest', 'menu_prices_shown');
    redirect($back . '?unlocked=1');
}
