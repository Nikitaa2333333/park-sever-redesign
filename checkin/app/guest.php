<?php
declare(strict_types=1);

require_once __DIR__ . '/documents.php';
require_once __DIR__ . '/pdf.php';
require_once __DIR__ . '/notify.php';

const TENANT_FIELDS = [
    'last_name' => 'Фамилия',
    'first_name' => 'Имя',
    'middle_name' => 'Отчество',
    'birth_date' => 'Дата рождения',
    'passport_series' => 'Серия паспорта',
    'passport_number' => 'Номер паспорта',
    'passport_issuer' => 'Кем выдан',
    'passport_date' => 'Дата выдачи',
    'passport_code' => 'Код подразделения',
    'reg_address' => 'Адрес регистрации',
    'phone' => 'Телефон',
    'email' => 'Email',
    'car_brand' => 'Марка автомобиля',
    'car_plate' => 'Госномер',
];

const GUEST2_FIELDS = [
    'g2_last_name' => 'Фамилия',
    'g2_first_name' => 'Имя',
    'g2_middle_name' => 'Отчество',
    'g2_birth_date' => 'Дата рождения',
    'g2_doc' => 'Серия и номер паспорта (свидетельства о рождении)',
];

function guest_route(string $token, string $action): void
{
    $stay = stay_by_token($token);
    if (!$stay) not_found('Ссылка не найдена. Проверьте, что она скопирована полностью, или напишите нам.');
    if (!link_active($stay)) not_found('Срок действия ссылки истёк или бронирование отменено. Если это ошибка — напишите нам.');

    if ($action === 'pdf') { guest_pdf($stay); return; }

    if (!$stay['opened_at']) {
        db()->prepare('UPDATE stays SET opened_at = ? WHERE id = ?')->execute([now_local(), $stay['id']]);
        audit((int)$stay['id'], 'guest', 'link_opened');
    }

    $needsContract = in_array($stay['status'], ['created', 'sent'], true) || $stay['resign_required'];
    $ext = pending_extension((int)$stay['id']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($needsContract) guest_sign_contract($stay);
        elseif ($ext) guest_sign_extension($stay, $ext);
        redirect($token);
    }

    if ($needsContract) {
        $prefill = [];
        if ($stay['resign_required'] && ($prev = latest_contract_signature((int)$stay['id']))) {
            // условия изменились — анкету заполнять заново не нужно, только проверить и переподписать
            $prefill = json_decode(decrypt_str($prev['payload_enc']), true)['fields'] ?? [];
        }
        guest_show_form($stay, $prefill, []);
        return;
    }
    if ($ext) {
        echo render('guest/layout', ['title' => 'Продление проживания', 'body' => render('guest/extension', [
            'stay' => $stay, 'ext' => $ext,
            'doc' => extension_document(stay_terms($stay), $ext, latest_contract_signature((int)$stay['id'])),
            'formToken' => form_token('ext|' . $stay['token'] . '|' . $ext['id']),
            'error' => null,
        ])]);
        return;
    }
    $firstName = '';
    if ($sig = latest_contract_signature((int)$stay['id'])) {
        $firstName = (string)(json_decode(decrypt_str($sig['payload_enc']), true)['fields']['first_name'] ?? '');
    }
    echo render('guest/layout', ['title' => 'Договор подписан', 'body' => render('guest/done', [
        'stay' => $stay, 'signatures' => signatures_for((int)$stay['id']), 'firstName' => $firstName,
    ])]);
}

function guest_show_form(array $stay, array $values, array $errors): void
{
    $terms = stay_terms($stay);
    echo render('guest/layout', ['title' => 'Регистрация и договор найма', 'body' => render('guest/form', [
        'stay' => $stay,
        'terms' => $terms,
        'doc' => offer_document($terms),
        'v' => $values,
        'errors' => $errors,
        'formToken' => form_token('contract|' . $stay['token']),
    ])]);
}

/** @return array{0: array, 1: array} [значения, ошибки] */
function validate_guest_form(array $in): array
{
    $v = [];
    foreach (array_merge(array_keys(TENANT_FIELDS), array_keys(GUEST2_FIELDS)) as $k) {
        $v[$k] = trim(preg_replace('/\s+/u', ' ', (string)($in[$k] ?? '')));
    }
    $v['has_guest2'] = !empty($in['has_guest2']) ? '1' : '';
    $v['passport_series'] = preg_replace('/\D/', '', $v['passport_series']);
    $v['passport_number'] = preg_replace('/\D/', '', $v['passport_number']);
    $code = preg_replace('/\D/', '', $v['passport_code']);
    $v['passport_code'] = strlen($code) === 6 ? substr($code, 0, 3) . '-' . substr($code, 3) : $v['passport_code'];
    $v['car_plate'] = mb_strtoupper(str_replace(' ', '', $v['car_plate']));
    $v['email'] = mb_strtolower($v['email']);

    $e = [];
    $req = ['last_name', 'first_name', 'birth_date', 'passport_series', 'passport_number', 'passport_issuer', 'passport_date', 'passport_code', 'reg_address', 'phone', 'email'];
    foreach ($req as $k) if ($v[$k] === '') $e[$k] = 'Заполните поле';

    foreach (['last_name', 'first_name', 'middle_name'] as $k) {
        if ($v[$k] !== '' && !preg_match('/^[\p{L}][\p{L}\-\' ]{0,59}$/u', $v[$k])) $e[$k] = 'Только буквы, как в паспорте';
    }
    if (!isset($e['birth_date'])) {
        $bd = valid_date($v['birth_date']);
        if (!$bd) $e['birth_date'] = 'Укажите дату';
        elseif ($bd > new DateTimeImmutable('-18 years')) $e['birth_date'] = 'Арендатору должно быть 18 лет';
        elseif ($bd < new DateTimeImmutable('-110 years')) $e['birth_date'] = 'Проверьте год';
    }
    if (!isset($e['passport_series']) && strlen($v['passport_series']) !== 4) $e['passport_series'] = '4 цифры';
    if (!isset($e['passport_number']) && strlen($v['passport_number']) !== 6) $e['passport_number'] = '6 цифр';
    if (!isset($e['passport_code']) && !preg_match('/^\d{3}-\d{3}$/', $v['passport_code'])) $e['passport_code'] = 'Формат 000-000';
    if (!isset($e['passport_date'])) {
        $pd = valid_date($v['passport_date']);
        if (!$pd || $pd > new DateTimeImmutable('today')) $e['passport_date'] = 'Проверьте дату выдачи';
        elseif (!empty($bd) && $pd < $bd->modify('+14 years')) $e['passport_date'] = 'Паспорт выдаётся с 14 лет';
    }
    $digits = preg_replace('/\D/', '', $v['phone']);
    if (!isset($e['phone'])) {
        if (strlen($digits) === 11 && $digits[0] === '8') $digits = '7' . substr($digits, 1);
        if (strlen($digits) === 10) $digits = '7' . $digits;
        if (strlen($digits) < 11 || strlen($digits) > 15) $e['phone'] = 'Проверьте номер';
        else $v['phone'] = '+' . $digits;
    }
    if (!isset($e['email']) && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $e['email'] = 'Проверьте email';
    if ($v['car_plate'] !== '' && !preg_match('/^[\p{L}\d]{4,12}$/u', $v['car_plate'])) $e['car_plate'] = 'Только буквы и цифры';
    if ($v['car_plate'] !== '' && $v['car_brand'] === '') $e['car_brand'] = 'Укажите марку';

    foreach (['passport_issuer' => 200, 'reg_address' => 300, 'car_brand' => 60, 'g2_doc' => 40] as $k => $max) {
        if (mb_strlen($v[$k]) > $max) $e[$k] = 'Слишком длинно';
    }

    if ($v['has_guest2']) {
        foreach (['g2_last_name', 'g2_first_name', 'g2_birth_date', 'g2_doc'] as $k) if ($v[$k] === '') $e[$k] = 'Заполните поле';
        if ($v['g2_birth_date'] !== '' && !valid_date($v['g2_birth_date'])) $e['g2_birth_date'] = 'Укажите дату';
    } else {
        foreach (array_keys(GUEST2_FIELDS) as $k) $v[$k] = '';
    }

    foreach (array_keys(acceptance_texts((bool)$v['has_guest2'])) as $k) {
        $v[$k] = !empty($in[$k]) ? '1' : '';
        if (!$v[$k]) $e[$k] = 'Без этой отметки договор не может быть подписан';
    }
    return [$v, $e];
}

function valid_date(string $s): ?DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $d : null;
}

function client_meta(): array
{
    return [
        'accept_language' => mb_substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 100),
        'timezone' => mb_substr((string)($_POST['client_tz'] ?? ''), 0, 60),
        'screen' => mb_substr((string)($_POST['client_screen'] ?? ''), 0, 30),
        'opened_form_at' => mb_substr((string)($_POST['client_opened'] ?? ''), 0, 40),
    ];
}

function guest_sign_contract(array $stay): void
{
    if (!form_token_valid('contract|' . $stay['token'], (string)($_POST['_ft'] ?? ''))) {
        audit((int)$stay['id'], 'guest', 'sign_rejected', ['reason' => 'form_token']);
        guest_show_form($stay, validate_guest_form($_POST)[0], ['_form' => 'Страница устарела. Проверьте данные и нажмите «Подписать» ещё раз.']);
        exit;
    }
    [$v, $errors] = validate_guest_form($_POST);
    if ($errors) {
        audit((int)$stay['id'], 'guest', 'sign_validation_failed', ['fields' => array_keys($errors)]);
        guest_show_form($stay, $v, $errors);
        exit;
    }

    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $stay = stay_by_id((int)$stay['id']); // перечитываем внутри транзакции — защита от двойного клика
        if (!in_array($stay['status'], ['created', 'sent'], true) && !$stay['resign_required']) {
            $pdo->exec('ROLLBACK');
            return;
        }
        $terms = stay_terms($stay);
        $doc = offer_document($terms);
        $docSha = hash('sha256', document_canonical_text($doc));
        $acceptance = acceptance_texts((bool)$v['has_guest2']);
        $fields = array_diff_key($v, $acceptance);

        $sig = [
            'stay_id' => (int)$stay['id'],
            'kind' => 'contract',
            'doc_number' => $terms['contract_number'],
            'signed_at_utc' => now_utc_precise(),
            'ip' => client_ip(),
            'forwarded_for' => mb_substr($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '', 0, 200),
            'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'client_meta' => client_meta(),
            'doc_version' => $doc['version'],
            'doc_sha256' => $docSha,
            'token_hint' => substr($stay['token'], 0, 6) . '…',
        ];

        $pdfBytes = build_contract_pdf($doc, $terms, $fields, $acceptance, $sig, $stay);
        [$pdfFile, $pdfSha] = store_pdf($pdfBytes);

        $auditHash = audit((int)$stay['id'], 'guest', 'contract_signed', [
            'doc_number' => $terms['contract_number'], 'doc_version' => $doc['version'], 'doc_sha256' => $docSha,
            'pdf_sha256' => $pdfSha, 'ip' => $sig['ip'], 'signed_at_utc' => $sig['signed_at_utc'],
            'acceptance' => array_keys($acceptance), 'resign' => (bool)$stay['resign_required'],
        ]);

        $pdo->prepare('INSERT INTO signatures (stay_id, kind, doc_number, signed_at_utc, ip, forwarded_for, user_agent, client_meta,
            doc_version, doc_sha256, terms_json, payload_enc, pdf_file, pdf_sha256, audit_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $sig['stay_id'], 'contract', $sig['doc_number'], $sig['signed_at_utc'], $sig['ip'], $sig['forwarded_for'],
            $sig['user_agent'], json_encode($sig['client_meta'], JSON_UNESCAPED_UNICODE), $sig['doc_version'], $docSha,
            json_encode($terms, JSON_UNESCAPED_UNICODE),
            encrypt_str(json_encode(['fields' => $fields, 'acceptance' => $acceptance], JSON_UNESCAPED_UNICODE)),
            $pdfFile, $pdfSha, $auditHash,
        ]);

        $short = tenant_short($fields);
        $pdo->prepare("UPDATE stays SET status = 'signed', resign_required = 0, signed_at = ?, tenant_short = ?, updated_at = ? WHERE id = ?")
            ->execute([now_local(), $short, now_local(), $stay['id']]);
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }

    notify_admin(sprintf(
        "✅ Договор подписан!\nДом: %s, %s–%s.\nАрендатор: %s%s.\nПравила безопасности акцептованы.\n№ %s · журнал %s…",
        house($stay['house_id'])['short'], date('d.m', strtotime($stay['checkin_at'])), date('d.m', strtotime($stay['checkout_at'])),
        cfg('telegram_pii') ? $short : ('заезд #' . $stay['id']),
        cfg('telegram_pii') && $fields['car_plate'] ? ', авто: ' . trim($fields['car_brand'] . ' ' . $fields['car_plate']) : '',
        $terms['contract_number'], substr($auditHash, 0, 12)
    ));
}

function guest_sign_extension(array $stay, array $ext): void
{
    if (!form_token_valid('ext|' . $stay['token'] . '|' . $ext['id'], (string)($_POST['_ft'] ?? '')) || empty($_POST['agree_extension'])) {
        audit((int)$stay['id'], 'guest', 'extension_sign_rejected');
        return;
    }
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $ext = pending_extension((int)$stay['id']);
        if (!$ext) { $pdo->exec('ROLLBACK'); return; }
        $contractSig = latest_contract_signature((int)$stay['id']);
        $terms = stay_terms($stay);
        $doc = extension_document($terms, $ext, $contractSig);
        $docSha = hash('sha256', document_canonical_text($doc));
        $fields = json_decode(decrypt_str($contractSig['payload_enc']), true)['fields'];
        $acceptance = ['agree_extension' => extension_acceptance_text()];
        $sig = [
            'signed_at_utc' => now_utc_precise(),
            'ip' => client_ip(),
            'forwarded_for' => mb_substr($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '', 0, 200),
            'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'client_meta' => client_meta(),
            'doc_version' => $doc['version'],
            'doc_sha256' => $docSha,
            'token_hint' => substr($stay['token'], 0, 6) . '…',
        ];
        $pdfBytes = build_contract_pdf($doc, $terms, $fields, $acceptance, $sig, $stay);
        [$pdfFile, $pdfSha] = store_pdf($pdfBytes);
        $auditHash = audit((int)$stay['id'], 'guest', 'extension_signed', [
            'extension_id' => (int)$ext['id'], 'doc_number' => $doc['number'], 'doc_sha256' => $docSha, 'pdf_sha256' => $pdfSha,
            'ip' => $sig['ip'], 'signed_at_utc' => $sig['signed_at_utc'],
        ]);
        $pdo->prepare('INSERT INTO signatures (stay_id, kind, extension_id, doc_number, signed_at_utc, ip, forwarded_for, user_agent, client_meta,
            doc_version, doc_sha256, terms_json, payload_enc, pdf_file, pdf_sha256, audit_hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $stay['id'], 'extension', $ext['id'], $doc['number'], $sig['signed_at_utc'], $sig['ip'], $sig['forwarded_for'],
            $sig['user_agent'], json_encode($sig['client_meta'], JSON_UNESCAPED_UNICODE), $doc['version'], $docSha,
            json_encode($terms + ['new_checkout_at' => $ext['new_checkout_at'], 'surcharge' => (int)$ext['surcharge']], JSON_UNESCAPED_UNICODE),
            encrypt_str(json_encode(['fields' => $fields, 'acceptance' => $acceptance], JSON_UNESCAPED_UNICODE)),
            $pdfFile, $pdfSha, $auditHash,
        ]);
        $pdo->prepare("UPDATE extensions SET status = 'signed', signed_at = ? WHERE id = ?")->execute([now_local(), $ext['id']]);
        $pdo->prepare('UPDATE stays SET checkout_at = ?, updated_at = ? WHERE id = ?')->execute([$ext['new_checkout_at'], now_local(), $stay['id']]);
        $pdo->exec('COMMIT');
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
    notify_admin(sprintf("🔁 Продление подписано!\nДом: %s, новый выезд %s.\nДоплата: %s.\n№ %s · журнал %s…",
        house($stay['house_id'])['short'], fmt_dt($ext['new_checkout_at']), fmt_rub((int)$ext['surcharge']),
        $doc['number'], substr($auditHash, 0, 12)));
}

function tenant_short(array $f): string
{
    $ini = fn(string $s) => $s === '' ? '' : mb_strtoupper(mb_substr($s, 0, 1)) . '.';
    return trim($f['last_name'] . ' ' . $ini($f['first_name']) . $ini($f['middle_name']));
}

function guest_pdf(array $stay): void
{
    $id = (int)($_GET['s'] ?? 0);
    $st = db()->prepare('SELECT * FROM signatures WHERE id = ? AND stay_id = ?');
    $st->execute([$id, $stay['id']]);
    $sig = $st->fetch();
    if (!$sig) not_found('Документ не найден.');
    audit((int)$stay['id'], 'guest', 'pdf_downloaded', ['signature_id' => $id]);
    send_pdf($sig);
}
