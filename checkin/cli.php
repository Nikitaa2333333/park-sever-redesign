<?php
declare(strict_types=1);

// Служебные команды (запускать из корня репозитория или из папки checkin):
//   php checkin/cli.php check               — проверить PHP и расширения
//   php checkin/cli.php init [пароль]       — создать config.php для локального запуска
//   php checkin/cli.php stay                — создать тестовый заезд и вывести ссылку гостя
//   php checkin/cli.php key                 — сгенерировать encryption_key и app_secret
//   php checkin/cli.php password [пароль]   — хэш пароля администратора (admin_password_hash)
//   php checkin/cli.php verify              — проверить целостность журнала и всех PDF
if (PHP_SAPI !== 'cli') exit;

$cmd = $argv[1] ?? '';

function ask_password(?string $given): string
{
    if ($given !== null && $given !== '') return $given;
    echo 'Пароль администратора: ';
    $win = DIRECTORY_SEPARATOR === '\\';
    if (!$win) system('stty -echo 2>/dev/null'); // в Windows скрыть ввод нельзя — просто вводим
    $p = trim((string)fgets(STDIN));
    if (!$win) system('stty echo 2>/dev/null');
    echo "\n";
    if (strlen($p) < 6) { fwrite(STDERR, "Пароль короче 6 символов.\n"); exit(1); }
    return $p;
}

if ($cmd === 'check') {
    $ok = true;
    $v = PHP_VERSION;
    $good = version_compare($v, '8.1.0', '>=');
    $ok = $ok && $good;
    echo ($good ? '[ok]  ' : '[НЕТ] ') . "PHP $v (нужен 8.1+)\n";
    foreach (['pdo_sqlite' => 'база данных', 'sodium' => 'шифрование', 'mbstring' => 'кириллица', 'curl' => 'Telegram', 'openssl' => 'HTTPS для Telegram'] as $ext => $why) {
        $has = extension_loaded($ext);
        if ($ext !== 'curl' && $ext !== 'openssl') $ok = $ok && $has;
        echo ($has ? '[ok]  ' : '[НЕТ] ') . "$ext — $why" . ($has ? '' : '  → включите extension=' . $ext . ' в php.ini') . "\n";
    }
    echo is_file(__DIR__ . '/config.php') ? "[ok]  config.php есть\n" : "[--]  config.php нет — выполните: php checkin/cli.php init\n";
    echo $ok ? "\nВсё готово к запуску.\n" : "\nИсправьте пункты [НЕТ] и запустите проверку снова. php.ini: php --ini\n";
    exit($ok ? 0 : 1);
}

if ($cmd === 'init') {
    $file = __DIR__ . '/config.php';
    if (is_file($file)) { fwrite(STDERR, "config.php уже есть — не перезаписываю. Удалите его, если нужно создать заново.\n"); exit(1); }
    $pass = ask_password($argv[2] ?? null);
    $cfg = file_get_contents(__DIR__ . '/config.sample.php');
    $cfg = str_replace("'public_url' => 'https://park-sever.ru',", "'public_url' => 'http://127.0.0.1:4391', // локально; на сервере — https://park-sever.ru", $cfg);
    $cfg = str_replace("'encryption_key' => '',", "'encryption_key' => '" . base64_encode(random_bytes(32)) . "',", $cfg);
    $cfg = str_replace("'app_secret' => '',", "'app_secret' => '" . base64_encode(random_bytes(32)) . "',", $cfg);
    $cfg = str_replace("'admin_password_hash' => '',", "'admin_password_hash' => '" . password_hash($pass, PASSWORD_DEFAULT) . "',", $cfg);
    file_put_contents($file, $cfg);
    echo "config.php создан (логин: admin). Ключ шифрования внутри — это локальный ключ, на сервере будет свой.\n";
    exit;
}

if ($cmd === 'stay') {
    require __DIR__ . '/app/bootstrap.php';
    $t = new_token();
    $now = now_local();
    $in = date('Y-m-d', strtotime('+3 days')) . ' 17:00';
    $out = date('Y-m-d', strtotime('+5 days')) . ' 12:00';
    db()->prepare('INSERT INTO stays (token, house_id, checkin_at, checkout_at, price, deposit, guest_label, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$t, array_key_first(houses()), $in, $out, 48000, 5000, 'Тестовый заезд', $now, $now]);
    $id = (int)db()->lastInsertId();
    audit($id, 'admin', 'stay_created', ['source' => 'cli']);
    echo "Заезд #$id создан.\nСсылка гостя:   " . public_link($t) . "\nКарточка:       " . rtrim((string)cfg('public_url'), '/') . url('admin/stay/' . $id) . "\n";
    exit;
}

if ($cmd === 'key') {
    echo "'encryption_key' => '" . base64_encode(random_bytes(32)) . "',\n";
    echo "'app_secret' => '" . base64_encode(random_bytes(32)) . "',\n";
    exit;
}

if ($cmd === 'password') {
    echo "'admin_password_hash' => '" . password_hash(ask_password($argv[2] ?? null), PASSWORD_DEFAULT) . "',\n";
    exit;
}

if ($cmd === 'verify') {
    require __DIR__ . '/app/bootstrap.php';
    require __DIR__ . '/app/pdf.php';
    $v = audit_verify();
    echo $v['ok'] ? "Журнал: цепочка цела, записей {$v['count']}\n" : "Журнал: НАРУШЕНИЕ цепочки на записи #{$v['broken_at']}\n";
    $bad = 0;
    foreach (db()->query('SELECT * FROM signatures') as $sig) {
        try { read_pdf($sig); } catch (Throwable $e) { $bad++; echo "PDF подписи #{$sig['id']}: {$e->getMessage()}\n"; }
    }
    echo $bad ? "PDF с ошибками: $bad\n" : "Все PDF совпадают с контрольными суммами\n";
    exit($v['ok'] && !$bad ? 0 : 1);
}

echo "Команды: check | init [пароль] | stay | key | password [пароль] | verify\n";
