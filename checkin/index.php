<?php
declare(strict_types=1);

// Единая точка входа модуля онлайн-чекина.
//   /checkin/{token}            — экран гостя (анкета → договор → подпись)
//   /checkin/{token}/pdf?s=ID   — копия подписанного документа для гостя
//   /checkin/admin/...          — кабинет администратора

require __DIR__ . '/app/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = rtrim((string)cfg('base_path', ''), '/');
if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
$path = trim($path, '/');

// встроенный dev-сервер PHP: отдаём статику сами
if (PHP_SAPI === 'cli-server' && str_starts_with($path, 'assets/')) {
    $file = realpath(__DIR__ . '/' . $path);
    if ($file && str_starts_with($file, realpath(__DIR__ . '/assets'))) {
        $types = ['css' => 'text/css', 'js' => 'text/javascript', 'woff2' => 'font/woff2', 'svg' => 'image/svg+xml'];
        header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        readfile($file);
        exit;
    }
}

security_headers();
$parts = $path === '' ? [] : explode('/', $path);

try {
    if (($parts[0] ?? '') === 'admin') {
        require __DIR__ . '/app/admin.php';
        admin_route(array_slice($parts, 1));
    } elseif (isset($parts[0]) && preg_match('/^[A-Za-z0-9_-]{22}$/', $parts[0]) && count($parts) <= 2) {
        require __DIR__ . '/app/guest.php';
        guest_route($parts[0], $parts[1] ?? '');
    } else {
        not_found('Откройте персональную ссылку, которую мы прислали вам в мессенджере.');
    }
} catch (Throwable $e) {
    error_log('[checkin] ' . $e);
    http_response_code(500);
    echo render('guest/layout', ['title' => 'Ошибка', 'body' => render('guest/closed', [
        'message' => 'Что-то пошло не так. Ваши данные не потеряны — попробуйте ещё раз через минуту или напишите нам.',
    ])]);
}
