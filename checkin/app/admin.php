<?php
declare(strict_types=1);

require_once __DIR__ . '/documents.php';
require_once __DIR__ . '/pdf.php';

function admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('ps_checkin_admin');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => rtrim((string)cfg('base_path'), '/') . '/admin',
        'secure' => $https, 'httponly' => true, 'samesite' => 'Strict',
    ]);
    session_start();
}

function admin_csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function admin_check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        exit('Сессия устарела — обновите страницу.');
    }
}

function admin_page(string $title, string $view, array $vars = []): void
{
    echo render('admin/layout', ['title' => $title, 'body' => render('admin/' . $view, $vars + ['csrf' => admin_csrf()])]);
}

function flash(?string $msg = null): ?string
{
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function admin_route(array $parts): void
{
    admin_session();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $parts[0] ?? '';

    if ($action === 'login') { admin_login(); return; }
    if (empty($_SESSION['admin'])) redirect('admin/login');
    if ($method === 'POST') admin_check_csrf();

    switch (true) {
        case $action === '':
            admin_list(); return;
        case $action === 'logout' && $method === 'POST':
            audit(null, 'admin', 'logout');
            session_destroy();
            redirect('admin/login');
        case $action === 'new':
            admin_new(); return;
        case $action === 'audit':
            admin_page('Журнал событий', 'audit', ['verify' => audit_verify(),
                'rows' => db()->query('SELECT * FROM audit ORDER BY id DESC LIMIT 300')->fetchAll()]);
            return;
        case $action === 'pdf' && ctype_digit($parts[1] ?? ''):
            $st = db()->prepare('SELECT * FROM signatures WHERE id = ?');
            $st->execute([(int)$parts[1]]);
            $sig = $st->fetch() ?: not_found('Документ не найден');
            audit((int)$sig['stay_id'], 'admin', 'pdf_downloaded', ['signature_id' => (int)$sig['id']]);
            send_pdf($sig);
        case $action === 'stay' && ctype_digit($parts[1] ?? ''):
            $stay = stay_by_id((int)$parts[1]) ?? not_found('Заезд не найден');
            $sub = $parts[2] ?? '';
            if ($method === 'POST') { admin_stay_post($stay, $sub, $parts); return; }
            admin_stay($stay);
            return;
    }
    not_found();
}

function admin_login(): void
{
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ip = client_ip();
        $pdo = db();
        $pdo->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - 900]);
        $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
        $st->execute([$ip]);
        if ((int)$st->fetchColumn() >= 8) {
            $error = 'Слишком много попыток. Подождите 15 минут.';
        } elseif (hash_equals((string)cfg('admin_login'), (string)($_POST['login'] ?? ''))
            && password_verify((string)($_POST['password'] ?? ''), (string)cfg('admin_password_hash'))) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            audit(null, 'admin', 'login');
            redirect('admin');
        } else {
            $pdo->prepare('INSERT INTO login_attempts (ip, at) VALUES (?, ?)')->execute([$ip, time()]);
            audit(null, 'admin', 'login_failed');
            $error = 'Неверный логин или пароль.';
        }
    }
    echo render('admin/layout', ['title' => 'Вход', 'bare' => true, 'body' => render('admin/login', ['error' => $error])]);
}

function admin_list(): void
{
    $filter = (string)($_GET['status'] ?? 'active');
    $q = trim((string)($_GET['q'] ?? ''));
    $where = match ($filter) {
        'active' => "status NOT IN ('checked_out','cancelled')",
        'all' => '1=1',
        default => array_key_exists($filter, STATUS_LABELS) ? 'status = :status' : '1=1',
    };
    $sql = "SELECT * FROM stays WHERE $where";
    $params = [];
    if (str_contains($where, ':status')) $params['status'] = $filter;
    if ($q !== '') {
        $sql .= ' AND (guest_label LIKE :q OR tenant_short LIKE :q OR guest_phone LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }
    $sql .= ' ORDER BY checkin_at ' . ($filter === 'active' ? 'ASC' : 'DESC') . ' LIMIT 500';
    $st = db()->prepare($sql);
    $st->execute($params);
    admin_page('Журнал заездов', 'list', ['stays' => $st->fetchAll(), 'filter' => $filter, 'q' => $q, 'flash' => flash()]);
}

/** @return array{0: array, 1: array} */
function stay_form_input(array $in): array
{
    $v = [
        'house_id' => (string)($in['house_id'] ?? ''),
        'checkin_at' => str_replace('T', ' ', (string)($in['checkin_at'] ?? '')),
        'checkout_at' => str_replace('T', ' ', (string)($in['checkout_at'] ?? '')),
        'price' => (int)preg_replace('/\D/', '', (string)($in['price'] ?? '')),
        'deposit' => (int)preg_replace('/\D/', '', (string)($in['deposit'] ?? '')),
        'guest_label' => trim((string)($in['guest_label'] ?? '')),
        'guest_phone' => trim((string)($in['guest_phone'] ?? '')),
        'admin_note' => trim((string)($in['admin_note'] ?? '')),
    ];
    $e = [];
    if (!isset(houses()[$v['house_id']])) $e['house_id'] = 'Выберите дом';
    foreach (['checkin_at', 'checkout_at'] as $k) {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $v[$k]);
        if (!$d) $e[$k] = 'Укажите дату и время';
    }
    if (!$e && strtotime($v['checkout_at']) <= strtotime($v['checkin_at'])) $e['checkout_at'] = 'Выезд должен быть позже заезда';
    if ($v['price'] <= 0) $e['price'] = 'Укажите стоимость';
    if ($v['deposit'] < 0) $e['deposit'] = 'Неверная сумма';
    return [$v, $e];
}

function admin_new(): void
{
    $v = [
        'house_id' => array_key_first(houses()),
        'checkin_at' => date('Y-m-d', strtotime('+1 day')) . ' 17:00',
        'checkout_at' => date('Y-m-d', strtotime('+2 day')) . ' 12:00',
        'price' => '', 'deposit' => 5000, 'guest_label' => '', 'guest_phone' => '', 'admin_note' => '',
    ];
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        [$v, $errors] = stay_form_input($_POST);
        if (!$errors) {
            $now = now_local();
            db()->prepare('INSERT INTO stays (token, house_id, checkin_at, checkout_at, price, deposit, guest_label, guest_phone, admin_note, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                new_token(), $v['house_id'], $v['checkin_at'], $v['checkout_at'], $v['price'], $v['deposit'],
                $v['guest_label'], $v['guest_phone'], $v['admin_note'], $now, $now,
            ]);
            $id = (int)db()->lastInsertId();
            audit($id, 'admin', 'stay_created', array_diff_key($v, ['admin_note' => 1]));
            flash('Заезд создан — отправьте ссылку гостю.');
            redirect('admin/stay/' . $id);
        }
    }
    admin_page('Новый заезд', 'stay_form', ['v' => $v, 'errors' => $errors, 'stay' => null]);
}

function admin_stay(array $stay, array $errors = [], ?array $editValues = null): void
{
    $sigs = signatures_for((int)$stay['id']);
    $guestData = null;
    if ($latest = latest_contract_signature((int)$stay['id'])) {
        $guestData = json_decode(decrypt_str($latest['payload_enc']), true)['fields'] ?? null;
    }
    $st = db()->prepare('SELECT * FROM extensions WHERE stay_id = ? ORDER BY id DESC');
    $st->execute([$stay['id']]);
    $au = db()->prepare('SELECT * FROM audit WHERE stay_id = ? ORDER BY id');
    $au->execute([$stay['id']]);
    admin_page('Заезд ' . contract_number($stay), 'stay', [
        'stay' => $stay, 'sigs' => $sigs, 'guest' => $guestData, 'extensions' => $st->fetchAll(),
        'audit' => $au->fetchAll(), 'link' => public_link($stay['token']), 'errors' => $errors,
        'v' => $editValues ?? $stay, 'flash' => flash(),
    ]);
}

function admin_stay_post(array $stay, string $sub, array $parts): void
{
    $id = (int)$stay['id'];
    $pdo = db();
    switch ($sub) {
        case 'edit':
            [$v, $errors] = stay_form_input($_POST);
            if ($errors) { admin_stay($stay, $errors, $v + ['id' => $id]); return; }
            $termsChanged = $v['house_id'] !== $stay['house_id'] || $v['checkin_at'] !== $stay['checkin_at']
                || $v['checkout_at'] !== $stay['checkout_at'] || $v['price'] !== (int)$stay['price'] || $v['deposit'] !== (int)$stay['deposit'];
            $wasSigned = latest_contract_signature($id) !== null;
            $pdo->prepare('UPDATE stays SET house_id = ?, checkin_at = ?, checkout_at = ?, price = ?, deposit = ?, guest_label = ?, guest_phone = ?, admin_note = ?, updated_at = ? WHERE id = ?')
                ->execute([$v['house_id'], $v['checkin_at'], $v['checkout_at'], $v['price'], $v['deposit'], $v['guest_label'], $v['guest_phone'], $v['admin_note'], now_local(), $id]);
            if ($termsChanged) {
                $pdo->prepare("UPDATE extensions SET status = 'cancelled' WHERE stay_id = ? AND status = 'pending'")->execute([$id]);
                if ($wasSigned) {
                    // Подписанный договор не переписывается: гость переподписывает новую редакцию по той же ссылке
                    $pdo->prepare("UPDATE stays SET resign_required = 1, status = CASE WHEN status IN ('signed','living') THEN 'sent' ELSE status END WHERE id = ?")->execute([$id]);
                }
            }
            audit($id, 'admin', 'stay_edited', ['terms_changed' => $termsChanged, 'new' => array_diff_key($v, ['admin_note' => 1])]);
            flash($termsChanged && $wasSigned
                ? 'Условия изменены. Прежний договор сохранён в архиве; гость должен переподписать новую редакцию по той же ссылке.'
                : 'Сохранено. Ссылка гостя уже показывает новые условия.');
            break;

        case 'status':
            $to = (string)($_POST['to'] ?? '');
            $allowed = ['sent', 'living', 'checked_out', 'cancelled', 'signed'];
            if (!in_array($to, $allowed, true)) break;
            if (in_array($to, ['living', 'checked_out'], true) && !latest_contract_signature($id)) {
                flash('Сначала гость должен подписать договор.');
                break;
            }
            $pdo->prepare('UPDATE stays SET status = ?, updated_at = ?' . ($to === 'sent' && !$stay['sent_at'] ? ', sent_at = ?' : '') . ' WHERE id = ?')
                ->execute($to === 'sent' && !$stay['sent_at'] ? [$to, now_local(), now_local(), $id] : [$to, now_local(), $id]);
            audit($id, 'admin', 'status_changed', ['from' => $stay['status'], 'to' => $to]);
            flash('Статус: ' . STATUS_LABELS[$to]);
            break;

        case 'mark-sent':
            // Вызывается из кабинета при нажатии «Отправить в WhatsApp/Telegram/…» или «Скопировать»
            $channel = mb_substr((string)($_POST['channel'] ?? ''), 0, 20);
            if ($stay['status'] === 'created') {
                $pdo->prepare("UPDATE stays SET status = 'sent', sent_at = ?, updated_at = ? WHERE id = ?")->execute([now_local(), now_local(), $id]);
            }
            audit($id, 'admin', 'link_shared', ['channel' => $channel]);
            header('Content-Type: application/json');
            echo '{"ok":true}';
            exit;

        case 'extend':
            if (!latest_contract_signature($id) || $stay['resign_required']) { flash('Продление возможно только после подписания договора.'); break; }
            $new = str_replace('T', ' ', (string)($_POST['new_checkout_at'] ?? ''));
            $sur = (int)preg_replace('/\D/', '', (string)($_POST['surcharge'] ?? ''));
            if (!DateTimeImmutable::createFromFormat('!Y-m-d H:i', $new) || strtotime($new) <= strtotime($stay['checkout_at'])) {
                flash('Новая дата выезда должна быть позже текущей.');
                break;
            }
            $pdo->prepare("UPDATE extensions SET status = 'cancelled' WHERE stay_id = ? AND status = 'pending'")->execute([$id]);
            $pdo->prepare('INSERT INTO extensions (stay_id, old_checkout_at, new_checkout_at, surcharge, created_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$id, $stay['checkout_at'], $new, $sur, now_local()]);
            audit($id, 'admin', 'extension_created', ['new_checkout_at' => $new, 'surcharge' => $sur]);
            flash('Доп. соглашение сформировано. Отправьте гостю ту же ссылку — он подпишет продление в 1 клик.');
            break;

        case 'extension-cancel':
            $pdo->prepare("UPDATE extensions SET status = 'cancelled' WHERE stay_id = ? AND id = ? AND status = 'pending'")->execute([$id, (int)($_POST['ext'] ?? 0)]);
            audit($id, 'admin', 'extension_cancelled', ['extension_id' => (int)($_POST['ext'] ?? 0)]);
            flash('Продление отменено.');
            break;
    }
    redirect('admin/stay/' . $id);
}

function share_message(array $stay, string $link): string
{
    return "Здравствуйте! Это Парк Север. Чтобы мы подготовили дом и пропуск на въезд, заполните, пожалуйста, анкету и подпишите договор найма по персональной ссылке (не пересылайте её другим): $link";
}
