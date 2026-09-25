<?php
declare(strict_types=1);

// PDF договора: полный текст оферты + приложения + анкета + отдельное согласие на ПДн
// + штамп простой электронной подписи. Файл шифруется на диске; его SHA-256 (до шифрования)
// пишется в БД и в журнал — любая правка файла задним числом обнаруживается.

function pdf_lib(): void
{
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    // TTF читаются из поставки (только чтение), кэш метрик шрифта пишется в data_dir
    define('_SYSTEM_TTFONTS', CHECKIN_ROOT . '/lib/tfpdf/font/unifont/');
    $cache = data_dir('fontcache/unifont');
    define('FPDF_FONTPATH', dirname($cache) . '/');
    require_once CHECKIN_ROOT . '/lib/tfpdf/tfpdf.php';
    require_once CHECKIN_ROOT . '/lib/tfpdf/font/unifont/ttfonts.php';
    require_once __DIR__ . '/ContractPdf.php';
}

function build_contract_pdf(array $doc, array $terms, array $f, array $acceptance, array $sig, array $stay): string
{
    pdf_lib();
    $pdf = new ContractPdf('P', 'mm', 'A4');
    $pdf->AddFont('Serif', '', 'DejaVuSerif.ttf', true);
    $pdf->AddFont('Serif', 'B', 'DejaVuSerif-Bold.ttf', true);
    $pdf->AddFont('Serif', 'I', 'DejaVuSerif-Italic.ttf', true);
    $pdf->SetMargins(18, 16, 18);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AliasNbPages();
    $pdf->SetTitle($doc['title'] . ' № ' . $doc['number'], true);
    $pdf->SetAuthor(LANDLORD['full'], true);
    $pdf->SetCreator('Парк Север · онлайн-регистрация', true);
    $pdf->footerText = '№ ' . $doc['number'] . ' · подписан простой электронной подписью '
        . (new DateTimeImmutable($sig['signed_at_utc']))->setTimezone(new DateTimeZone(APP_TZ))->format('d.m.Y H:i:s') . ' МСК';
    $pdf->SetTextColor(40, 38, 42);
    $pdf->AddPage();

    $w = $pdf->GetPageWidth() - 36;
    $h1 = function (string $t) use ($pdf) { $pdf->SetFont('Serif', 'B', 13); $pdf->MultiCell(0, 6.5, $t, 0, 'C'); };
    $h2 = function (string $t) use ($pdf) { $pdf->Ln(2.5); $pdf->SetFont('Serif', 'B', 10.5); $pdf->MultiCell(0, 5.5, $t); $pdf->Ln(0.5); };
    $p = function (string $t, float $size = 9.5) use ($pdf) { $pdf->SetFont('Serif', '', $size); $pdf->MultiCell(0, 4.6, $t, 0, 'J'); $pdf->Ln(0.8); };
    $kv = function (string $k, string $v) use ($pdf) {
        $pdf->SetFont('Serif', 'B', 9.5); $pdf->Write(5, $k . ': ');
        $pdf->SetFont('Serif', '', 9.5); $pdf->Write(5, $v !== '' ? $v : '—'); $pdf->Ln(5.2);
    };

    $isContract = !empty($doc['rules']);
    $fio = trim($f['last_name'] . ' ' . $f['first_name'] . ' ' . $f['middle_name']);

    $h1($doc['title']);
    $pdf->SetFont('Serif', '', 10);
    $pdf->MultiCell(0, 5.5, '№ ' . $doc['number'], 0, 'C');
    $pdf->Ln(2);
    $pdf->SetFont('Serif', '', 9);
    $pdf->Cell($w / 2, 5, 'Московская обл., Дмитровский г.о., дер. Василёво');
    $pdf->Cell($w / 2, 5, (new DateTimeImmutable($sig['signed_at_utc']))->setTimezone(new DateTimeZone(APP_TZ))->format('d.m.Y'), 0, 1, 'R');
    $pdf->Ln(3);

    // Стороны
    $p(LANDLORD['full'] . ' (ИНН ' . LANDLORD['inn'] . '), именуемый в дальнейшем «Арендодатель», и гражданин(ка) '
        . $fio . ', ' . fmt_date($f['birth_date']) . ' г. р., паспорт ' . $f['passport_series'] . ' ' . $f['passport_number']
        . ', выдан ' . $f['passport_issuer'] . ' ' . fmt_date($f['passport_date']) . ', код подразделения ' . $f['passport_code']
        . ', зарегистрированный(ая) по адресу: ' . $f['reg_address'] . ', именуемый(ая) в дальнейшем «Арендатор», '
        . ($isContract ? 'заключили настоящий договор о нижеследующем.' : 'заключили настоящее соглашение о нижеследующем.'));

    // Зафиксированные условия
    $pdf->Ln(1);
    $pdf->SetDrawColor(15, 42, 71);
    $pdf->SetLineWidth(0.3);
    $y0 = $pdf->GetY();
    $pdf->SetX(22);
    $pdf->Ln(2);
    $kv('Объект', $terms['house_name'] . ', ' . PLACE_NAME);
    $kv('Заезд', fmt_date($terms['checkin_at']) . ' с ' . fmt_time($terms['checkin_at']));
    if (!empty($doc['new_checkout_at'])) {
        $kv('Выезд (после продления)', fmt_date($doc['new_checkout_at']) . ' до ' . fmt_time($doc['new_checkout_at']));
        $kv('Доплата за продление', fmt_rub((int)$doc['surcharge']));
    } else {
        $kv('Выезд', fmt_date($terms['checkout_at']) . ' до ' . fmt_time($terms['checkout_at']) . ' · ' . $terms['nights'] . ' ' . plural($terms['nights'], 'сутки', 'суток', 'суток'));
        $kv('Плата за наём', fmt_rub($terms['price']) . ' · обеспечительный депозит ' . fmt_rub($terms['deposit']));
    }
    $pdf->Ln(1);
    $pdf->Rect(18, $y0, $w, $pdf->GetY() - $y0);
    $pdf->Ln(3);

    foreach ($doc['contract'] as $s) {
        $h2($s['title']);
        foreach ($s['points'] as $pt) $p($pt);
    }

    if ($doc['inventory']) {
        $pdf->AddPage();
        $h1('Приложение № 1. Опись имущества Дома');
        $pdf->SetFont('Serif', '', 9);
        $pdf->MultiCell(0, 5, 'к договору № ' . $doc['number'] . ' · ' . $terms['house_name'], 0, 'C');
        $pdf->Ln(2);
        foreach ($doc['inventory'] as $g) {
            $h2($g['group']);
            foreach ($g['items'] as $it) {
                $pdf->SetFont('Serif', '', 9.5);
                $pdf->MultiCell(0, 4.8, '•  ' . $it['name'] . ($it['qty'] !== '—' ? ' — ' . $it['qty'] . ' шт.' : '') . '. Компенсация ущерба: ' . inventory_value($it['value']) . '.');
            }
        }
        $h2('Дефекты, зафиксированные до заезда');
        $p($doc['defects'] ? implode(' ', array_map(fn($d) => '•  ' . $d, $doc['defects'])) . ' Претензии по ним к Арендатору не предъявляются.' : 'Не выявлены.');
    }

    if ($doc['rules']) {
        $pdf->Ln(3);
        $h1('Приложение № 2. Правила безопасности фермы');
        $pdf->Ln(1);
        foreach ($doc['rules'] as $r) {
            $h2($r['title']);
            $p($r['text']);
        }
    }

    // Анкета
    $pdf->AddPage();
    $h1($isContract ? 'Анкета Арендатора' : 'Данные Арендатора');
    $pdf->Ln(2);
    $kv('ФИО', $fio);
    $kv('Дата рождения', fmt_date($f['birth_date']));
    $kv('Паспорт', $f['passport_series'] . ' ' . $f['passport_number'] . ', выдан ' . fmt_date($f['passport_date']) . ', код ' . $f['passport_code']);
    $kv('Кем выдан', $f['passport_issuer']);
    $kv('Адрес регистрации', $f['reg_address']);
    $kv('Телефон', $f['phone']);
    $kv('Email', $f['email']);
    $kv('Автомобиль', trim($f['car_brand'] . ' ' . $f['car_plate']));
    if (!empty($f['has_guest2'])) {
        $h2('Гость № 2');
        $kv('ФИО', trim($f['g2_last_name'] . ' ' . $f['g2_first_name'] . ' ' . $f['g2_middle_name']));
        $kv('Дата рождения', $f['g2_birth_date'] ? fmt_date($f['g2_birth_date']) : '');
        $kv('Документ', $f['g2_doc']);
    }

    $h2('Отметки, проставленные Арендатором');
    foreach ($acceptance as $text) $p('[X]  ' . $text);

    // Штамп ПЭП
    $pdf->Ln(3);
    $y0 = $pdf->GetY();
    if ($y0 > 200) { $pdf->AddPage(); $y0 = $pdf->GetY(); }
    $pdf->SetLineWidth(0.6);
    $pdf->Ln(3);
    $pdf->SetFont('Serif', 'B', 10.5);
    $pdf->MultiCell(0, 5.5, 'ПОДПИСАНО ПРОСТОЙ ЭЛЕКТРОННОЙ ПОДПИСЬЮ', 0, 'C');
    $pdf->SetFont('Serif', '', 8.5);
    $pdf->MultiCell(0, 4.2, '(ст. 434, 438 ГК РФ; ст. 5, 6, 9 Федерального закона № 63-ФЗ)', 0, 'C');
    $pdf->Ln(2);
    $small = function (string $k, string $v) use ($pdf) {
        $pdf->SetX(22);
        $pdf->SetFont('Serif', 'B', 8.5); $pdf->Write(4.4, $k . ': ');
        $pdf->SetFont('Serif', '', 8.5); $pdf->Write(4.4, $v); $pdf->Ln(4.6);
    };
    $small('Подписант', $fio . ' (Арендатор)');
    $small('Дата и время подписания', fmt_utc_as_msk($sig['signed_at_utc']) . ' · UTC ' . str_replace(['T', 'Z'], [' ', ''], $sig['signed_at_utc']));
    $small('IP-адрес', $sig['ip'] . ($sig['forwarded_for'] ? ' (X-Forwarded-For: ' . $sig['forwarded_for'] . ')' : ''));
    $small('Устройство (User-Agent)', $sig['user_agent'] ?: '—');
    $meta = $sig['client_meta'];
    $small('Параметры устройства', 'часовой пояс ' . ($meta['timezone'] ?: '—') . ', экран ' . ($meta['screen'] ?: '—') . ', язык ' . ($meta['accept_language'] ?: '—'));
    $small('Ключ ПЭП', 'персональная ссылка ' . $sig['token_hint'] . ', направленная на телефон Арендатора' . ($stay['sent_at'] ? ' ' . fmt_dt($stay['sent_at']) : ''));
    $small('Редакция документа', $sig['doc_version']);
    $small('SHA-256 текста оферты', $sig['doc_sha256']);
    $small('Арендодатель', LANDLORD['full'] . ', ИНН ' . LANDLORD['inn'] . ' — оферта направлена через персональную ссылку');
    $pdf->Ln(2);
    $pdf->Rect(18, $y0, $w, $pdf->GetY() - $y0);

    // Согласие на ПДн — отдельным документом (отдельная страница)
    if ($doc['consent']) {
        $pdf->AddPage();
        $h1($doc['consent']['title']);
        $pdf->Ln(2);
        $p('Субъект персональных данных: ' . $fio . ', ' . fmt_date($f['birth_date']) . ' г. р., паспорт ' . $f['passport_series'] . ' ' . $f['passport_number'] . '.');
        foreach ($doc['consent']['points'] as $pt) $p($pt);
        $pdf->Ln(2);
        $pdf->SetFont('Serif', 'I', 9);
        $pdf->MultiCell(0, 4.6, 'Согласие дано отдельной отметкой «' . ($acceptance['agree_pd'] ?? '') . '» и подписано простой электронной подписью '
            . fmt_utc_as_msk($sig['signed_at_utc']) . ', IP ' . $sig['ip'] . '.');
    }

    return $pdf->Output('S');
}

/** @return array{0:string,1:string} [относительный путь, sha256] */
function store_pdf(string $bytes): array
{
    $sha = hash('sha256', $bytes);
    $rel = 'pdf/' . date('Y') . '/' . bin2hex(random_bytes(12)) . '.pdf.enc';
    $dir = data_dir(dirname($rel));
    $path = $dir . '/' . basename($rel);
    if (file_put_contents($path, encrypt_str($bytes), LOCK_EX) === false) {
        throw new RuntimeException('Не удалось сохранить PDF');
    }
    chmod($path, 0400); // только чтение: файл не перезаписывается
    return [$rel, $sha];
}

function read_pdf(array $sig): string
{
    $bytes = decrypt_str((string)file_get_contents(data_dir() . '/' . $sig['pdf_file']));
    if (!hash_equals($sig['pdf_sha256'], hash('sha256', $bytes))) {
        throw new RuntimeException('Контрольная сумма PDF не совпадает с записанной при подписании!');
    }
    return $bytes;
}

function send_pdf(array $sig): never
{
    $bytes = read_pdf($sig);
    $name = ($sig['kind'] === 'contract' ? 'Договор-найма-' : 'Доп-соглашение-') . $sig['doc_number'] . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($bytes));
    header("Content-Disposition: attachment; filename=\"contract.pdf\"; filename*=UTF-8''" . rawurlencode(str_replace('/', '-', $name)));
    echo $bytes;
    exit;
}
