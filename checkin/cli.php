<?php
declare(strict_types=1);

// Служебные команды:
//   php cli.php key        — сгенерировать encryption_key и app_secret
//   php cli.php password   — хэш пароля администратора (admin_password_hash)
//   php cli.php verify     — проверить целостность журнала и всех PDF
if (PHP_SAPI !== 'cli') exit;

$cmd = $argv[1] ?? '';
if ($cmd === 'key') {
    echo "'encryption_key' => '" . base64_encode(random_bytes(32)) . "',\n";
    echo "'app_secret' => '" . base64_encode(random_bytes(32)) . "',\n";
    exit;
}
if ($cmd === 'password') {
    echo 'Пароль: ';
    system('stty -echo 2>/dev/null');
    $p = trim((string)fgets(STDIN));
    system('stty echo 2>/dev/null');
    echo "\n'admin_password_hash' => '" . password_hash($p, PASSWORD_DEFAULT) . "',\n";
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
echo "Команды: key | password | verify\n";
