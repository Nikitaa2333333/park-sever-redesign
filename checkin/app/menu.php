<?php
declare(strict_types=1);

// «Меню и допы» — СКЕЛЕТ. Гость по своей ссылке (/checkin/<токен>/menu) выбирает
// позиции своего тарифа; администратор задаёт тариф и уже оформленные допы в карточке
// заезда и видит выбор гостя. Каталог — app/data/menu.php.
//
// Не сделано (ждёт решений заказчицы): загрузка фото-референсов, оплата/итог к оплате,
// уведомление в Telegram, срок, до которого гость может менять выбор, праздничный тариф.

function menu_catalog(): array
{
    static $m = null;
    return $m ??= require __DIR__ . '/data/menu.php';
}

function tariff_cfg(string $tariff): ?array
{
    return menu_catalog()['tariffs'][$tariff] ?? null;
}

function tariff_label(string $tariff): string
{
    return tariff_cfg($tariff)['label'] ?? '';
}

/** Разделы, видимые в тарифе. */
function menu_sections(string $tariff): array
{
    $food = (bool)(tariff_cfg($tariff)['food'] ?? false);
    return array_values(array_filter(menu_catalog()['sections'],
        fn($s) => !isset($s['food']) || $s['food'] === $food));
}

/** Раздел входит в стоимость тарифа: выбор есть, цены нет. */
function section_free(array $s, string $tariff): bool
{
    return in_array($s['id'], tariff_cfg($tariff)['free'] ?? [], true);
}

/** Разделы тарифа по блокам: [id блока => [название, [разделы]]], пустые блоки пропущены. */
function menu_groups(string $tariff): array
{
    $out = [];
    foreach (menu_catalog()['groups'] as $gid => $title) $out[$gid] = [$title, []];
    foreach (menu_sections($tariff) as $s) $out[$s['group'] ?? 'custom'][1][] = $s;
    return array_filter($out, fn($g) => $g[1]);
}

/** Сколько позиций можно выбрать в группе (0 — без ограничения). */
function pick_max(array $pick, int $nights): int
{
    $m = $pick['max'] ?? 0;
    if (!is_array($m)) return (int)$m;
    return (int)($m['nights'][$nights] ?? $m['default'] ?? 0);
}

/** Опция входит в тариф — показывается без цены и без выбора. */
function option_in_tariff(array $opt, string $tariff): bool
{
    return !empty($opt['gastro']) && !empty(tariff_cfg($tariff)['food']);
}

/**
 * Разбор формы гостя → [данные, ошибки].
 * Данные: [раздел => ['picks' => [группа => [id опций]], 'fields' => [поле => значение]]].
 */
function menu_parse(array $in, string $tariff, int $nights): array
{
    $data = [];
    $errors = [];
    foreach (menu_sections($tariff) as $s) {
        $row = ['picks' => [], 'fields' => []];
        foreach ($s['picks'] ?? [] as $p) {
            $valid = array_column(array_filter($p['options'], fn($o) => !option_in_tariff($o, $tariff)), 'id');
            $got = $in['m'][$s['id']][$p['id']] ?? [];
            $got = array_values(array_intersect(array_map('strval', (array)$got), $valid));
            $max = pick_max($p, $nights);
            if ($max > 0 && count($got) > $max) {
                $errors[$s['id']] = 'Можно выбрать не больше ' . $max . ' ' . plural($max, 'позиции', 'позиций', 'позиций');
                $got = array_slice($got, 0, $max);
            }
            if ($got) $row['picks'][$p['id']] = $got;
        }
        foreach ($s['fields'] ?? [] as $f) {
            $val = $in['m'][$s['id']]['f'][$f['id']] ?? '';
            $val = $f['type'] === 'check' ? (!empty($val) ? '1' : '') : mb_substr(trim((string)$val), 0, 1000);
            if ($val !== '') $row['fields'][$f['id']] = $val;
        }
        if ($row['picks'] || $row['fields']) $data[$s['id']] = $row;
    }
    return [$data, $errors];
}

/** Итог по выбранным платным позициям: [сумма, есть ли позиции с неизвестной ценой]. */
function menu_total(array $data, string $tariff): array
{
    $sum = 0;
    $unknown = false;
    foreach (menu_sections($tariff) as $s) {
        if (section_free($s, $tariff)) continue;
        foreach ($s['picks'] ?? [] as $p) {
            $chosen = $data[$s['id']]['picks'][$p['id']] ?? [];
            foreach ($p['options'] as $o) {
                if (!in_array($o['id'], $chosen, true) || !array_key_exists('price', $o)) continue;
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
    $row['data'] = json_decode(decrypt_str($row['payload_enc']), true) ?: [];
    return $row;
}

/** Выбор гостя строками — для кабинета: [[раздел, [строки]]]. */
function menu_summary(array $data, string $tariff): array
{
    $out = [];
    foreach (menu_sections($tariff) as $s) {
        if (empty($data[$s['id']])) continue;
        $lines = [];
        foreach ($s['picks'] ?? [] as $p) {
            $chosen = $data[$s['id']]['picks'][$p['id']] ?? [];
            foreach ($p['options'] as $o) {
                if (!in_array($o['id'], $chosen, true)) continue;
                $price = section_free($s, $tariff) ? ' — входит в тариф' : (array_key_exists('price', $o) ? ($o['price'] === null ? ' — цена уточняется' : ($o['price'] ? ' — ' . fmt_rub((int)$o['price']) : '')) : '');
                $lines[] = (!empty($p['label']) && ($p['id'] !== 'items') ? $p['label'] . ': ' : '') . $o['name'] . $price;
            }
        }
        foreach ($s['fields'] ?? [] as $f) {
            $v = $data[$s['id']]['fields'][$f['id']] ?? '';
            if ($v === '') continue;
            $lines[] = $f['type'] === 'check' ? '✓ ' . $f['label'] : $f['label'] . ': ' . $v;
        }
        if ($lines) $out[] = [$s['title'], $lines];
    }
    return $out;
}

// ── экран гостя ──────────────────────────────────────────────────────

function guest_menu(array $stay): void
{
    $tariff = (string)$stay['tariff'];
    if (tariff_label($tariff) === '') {
        not_found('Выбор меню для этого заезда пока не открыт. Мы пришлём ссылку, когда всё будет готово.');
    }
    $nights = nights($stay['checkin_at'], $stay['checkout_at']);
    $saved = menu_load((int)$stay['id']);
    $data = $saved && $saved['tariff'] === $tariff ? $saved['data'] : [];
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!form_token_valid('menu|' . $stay['token'], (string)($_POST['_ft'] ?? ''), 86400 * 30)) {
            $errors['_form'] = 'Страница устарела. Проверьте выбор и нажмите «Сохранить» ещё раз.';
            [$data] = menu_parse($_POST, $tariff, $nights);
        } else {
            [$data, $errors] = menu_parse($_POST, $tariff, $nights);
            [$total] = menu_total($data, $tariff);
            db()->prepare('INSERT OR REPLACE INTO menu_orders (stay_id, tariff, payload_enc, total, updated_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$stay['id'], $tariff, encrypt_str(json_encode($data, JSON_UNESCAPED_UNICODE)), $total, now_local()]);
            audit((int)$stay['id'], 'guest', 'menu_saved', ['tariff' => $tariff, 'total' => $total]);
            if (!$errors) {
                header('Location: ' . url($stay['token'] . '/menu?saved=1'), true, 303);
                exit;
            }
        }
    }
    if (isset($_GET['saved'])) $flash = 'Выбор сохранён. Его можно поменять по этой же ссылке.';

    echo render('guest/layout', ['title' => 'Меню и допы', 'body' => render('guest/menu', [
        'stay' => $stay, 'tariff' => $tariff, 'nights' => $nights, 'data' => $data, 'errors' => $errors,
        'flash' => $flash, 'savedAt' => $saved['updated_at'] ?? null,
        'hidePrices' => (string)$stay['price_lock'] !== '',
        'formToken' => form_token('menu|' . $stay['token']),
    ])]);
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
