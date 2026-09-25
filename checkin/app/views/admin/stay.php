<?php
/** @var array $stay @var array $sigs @var ?array $guest @var array $extensions @var array $audit @var string $link @var array $errors @var array $v */
$terms = stay_terms($stay);
$msg = share_message($stay, $link);
$phone = preg_replace('/\D/', '', $stay['guest_phone']);
if (strlen($phone) === 11 && $phone[0] === '8') $phone = '7' . substr($phone, 1);
$pending = array_values(array_filter($extensions, fn($e) => $e['status'] === 'pending'))[0] ?? null;
$signed = (bool)$sigs;
$eventNames = [
    'stay_created' => 'Заезд создан', 'link_shared' => 'Ссылка отправлена', 'link_opened' => 'Гость открыл ссылку',
    'sign_validation_failed' => 'Ошибки в анкете', 'sign_rejected' => 'Отправка отклонена', 'contract_signed' => 'Договор подписан',
    'stay_edited' => 'Условия изменены', 'status_changed' => 'Статус изменён', 'extension_created' => 'Продление сформировано',
    'extension_cancelled' => 'Продление отменено', 'extension_signed' => 'Продление подписано', 'extension_sign_rejected' => 'Продление отклонено',
    'pdf_downloaded' => 'PDF скачан',
];
?>
<h1 class="a-h1">Заезд <?= h(contract_number($stay)) ?></h1>
<?php if ($flash): ?><p class="a-flash"><?= h($flash) ?></p><?php endif; ?>

<div class="a-grid">
<div>
  <section class="a-card">
    <h2>Ссылка для гостя</h2>
    <div class="a-link">
      <input type="text" readonly value="<?= h($link) ?>" id="guest-link">
      <button class="btn btn--navy btn--sm" type="button" data-copy="#guest-link" data-mark-sent="copy">Скопировать</button>
    </div>
    <div class="a-share">
      <a class="btn btn--ghost btn--sm" target="_blank" rel="noopener" data-mark-sent="whatsapp"
         href="https://wa.me/<?= h($phone) ?>?text=<?= rawurlencode($msg) ?>">WhatsApp</a>
      <a class="btn btn--ghost btn--sm" target="_blank" rel="noopener" data-mark-sent="telegram"
         href="https://t.me/share/url?url=<?= rawurlencode($link) ?>&amp;text=<?= rawurlencode(str_replace(': ' . $link, '', $msg)) ?>">Telegram</a>
      <a class="btn btn--ghost btn--sm" target="_blank" rel="noopener" data-mark-sent="vk"
         href="https://vk.com/share.php?url=<?= rawurlencode($link) ?>">ВКонтакте</a>
      <button class="btn btn--ghost btn--sm" type="button" data-copy-text="<?= h($msg) ?>" data-mark-sent="max">Текст для MAX</button>
    </div>
    <p class="a-small">Ссылка — ключ простой электронной подписи гостя. Отправляйте её только на подтверждённый номер гостя. Нажатие любой кнопки отмечает ссылку отправленной.</p>
  </section>

  <section class="a-card">
    <h2>Статус</h2>
    <p>
      <span class="badge badge--<?= h($stay['status']) ?>"><?= h(STATUS_LABELS[$stay['status']]) ?></span>
      <?php if ($stay['resign_required']): ?><span class="badge badge--warn">Нужна переподпись новой редакции</span><?php endif; ?>
      <?php if ($pending): ?><span class="badge badge--warn">Ждёт подписи продления</span><?php endif; ?>
    </p>
    <dl class="a-kv">
      <div><dt>Создан</dt><dd><?= h(fmt_dt($stay['created_at'])) ?></dd></div>
      <div><dt>Отправлен</dt><dd><?= $stay['sent_at'] ? h(fmt_dt($stay['sent_at'])) : '—' ?></dd></div>
      <div><dt>Открыт гостем</dt><dd><?= $stay['opened_at'] ? h(fmt_dt($stay['opened_at'])) : '—' ?></dd></div>
      <div><dt>Подписан</dt><dd><?= $stay['signed_at'] ? h(fmt_dt($stay['signed_at'])) : '—' ?></dd></div>
    </dl>
    <div class="a-actions">
      <?php
      $btns = [];
      if ($stay['status'] === 'created') $btns['sent'] = 'Отметить: ссылка отправлена';
      if ($stay['status'] === 'signed') $btns['living'] = 'Гость заселился';
      if ($stay['status'] === 'living') $btns['checked_out'] = 'Выезд оформлен';
      if ($stay['status'] === 'checked_out') $btns['living'] = 'Вернуть: проживает';
      if (!in_array($stay['status'], ['cancelled', 'checked_out'], true)) $btns['cancelled'] = 'Отменить заезд';
      if ($stay['status'] === 'cancelled') $btns[$signed ? 'signed' : 'sent'] = 'Восстановить';
      foreach ($btns as $to => $label): ?>
        <form method="post" action="<?= h(url('admin/stay/' . $stay['id'] . '/status')) ?>">
          <input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="to" value="<?= h($to) ?>">
          <button class="btn <?= $to === 'cancelled' ? 'btn--ghost' : 'btn--navy' ?> btn--sm" <?= $to === 'cancelled' ? 'data-confirm="Отменить заезд? Ссылка гостя перестанет работать."' : '' ?>><?= h($label) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($guest): ?>
  <section class="a-card">
    <h2>Анкета Арендатора</h2>
    <dl class="a-kv">
      <div><dt>ФИО</dt><dd><?= h(trim($guest['last_name'] . ' ' . $guest['first_name'] . ' ' . $guest['middle_name'])) ?></dd></div>
      <div><dt>Дата рождения</dt><dd><?= h(fmt_date($guest['birth_date'])) ?></dd></div>
      <div><dt>Паспорт</dt><dd><?= h($guest['passport_series'] . ' ' . $guest['passport_number']) ?>, выдан <?= h(fmt_date($guest['passport_date'])) ?>, <?= h($guest['passport_issuer']) ?>, код <?= h($guest['passport_code']) ?></dd></div>
      <div><dt>Регистрация</dt><dd><?= h($guest['reg_address']) ?></dd></div>
      <div><dt>Телефон</dt><dd><a href="tel:<?= h($guest['phone']) ?>"><?= h($guest['phone']) ?></a></dd></div>
      <div><dt>Email</dt><dd><?= h($guest['email']) ?></dd></div>
      <div><dt>Автомобиль</dt><dd><?= h(trim($guest['car_brand'] . ' ' . $guest['car_plate']) ?: '—') ?></dd></div>
      <?php if (!empty($guest['has_guest2'])): ?>
        <div><dt>Гость № 2</dt><dd><?= h(trim($guest['g2_last_name'] . ' ' . $guest['g2_first_name'] . ' ' . $guest['g2_middle_name'])) ?>, <?= h($guest['g2_birth_date'] ? fmt_date($guest['g2_birth_date']) : '') ?>, <?= h($guest['g2_doc']) ?></dd></div>
      <?php endif; ?>
    </dl>
  </section>
  <?php endif; ?>

  <section class="a-card">
    <h2>Подписанные документы</h2>
    <?php if (!$sigs): ?><p>Гость ещё не подписал договор.</p><?php endif; ?>
    <?php foreach (array_reverse($sigs) as $s): $meta = json_decode($s['client_meta'], true) ?: []; ?>
      <details class="doc-more">
        <summary><?= $s['kind'] === 'contract' ? 'Договор' : 'Доп. соглашение' ?> № <?= h($s['doc_number']) ?> · <?= h(fmt_utc_as_msk($s['signed_at_utc'])) ?></summary>
        <dl class="a-kv">
          <div><dt>Время (UTC)</dt><dd class="a-mono"><?= h($s['signed_at_utc']) ?></dd></div>
          <div><dt>IP-адрес</dt><dd class="a-mono"><?= h($s['ip']) ?><?= $s['forwarded_for'] ? ' · XFF ' . h($s['forwarded_for']) : '' ?></dd></div>
          <div><dt>User-Agent</dt><dd class="a-mono"><?= h($s['user_agent']) ?></dd></div>
          <div><dt>Устройство</dt><dd><?= h(($meta['timezone'] ?? '') . ' · ' . ($meta['screen'] ?? '') . ' · ' . ($meta['accept_language'] ?? '')) ?></dd></div>
          <div><dt>Редакция</dt><dd><?= h($s['doc_version']) ?></dd></div>
          <div><dt>SHA-256 текста</dt><dd class="a-mono"><?= h($s['doc_sha256']) ?></dd></div>
          <div><dt>SHA-256 PDF</dt><dd class="a-mono"><?= h($s['pdf_sha256']) ?></dd></div>
          <div><dt>Запись журнала</dt><dd class="a-mono"><?= h($s['audit_hash']) ?></dd></div>
        </dl>
        <p><a class="btn btn--navy btn--sm" href="<?= h(url('admin/pdf/' . $s['id'])) ?>">Скачать PDF</a></p>
      </details>
    <?php endforeach; ?>
  </section>
</div>

<div>
  <details class="a-card a-form" <?= (!$signed || $errors) ? 'open' : '' ?>>
    <summary>Условия заезда</summary>
    <?php if ($signed): ?><p class="a-small">Договор уже подписан. Если изменить дом, даты или суммы, прежний договор останется в архиве, а гость переподпишет новую редакцию по той же ссылке (его данные подставятся автоматически).</p><?php endif; ?>
    <form method="post" action="<?= h(url('admin/stay/' . $stay['id'] . '/edit')) ?>">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <?php require __DIR__ . '/_stay_fields.php'; ?>
      <button class="btn btn--navy">Сохранить</button>
    </form>
  </details>

  <section class="a-card a-form">
    <h2>Продление проживания</h2>
    <?php if ($pending): ?>
      <p>Ждёт подписи гостя: выезд <b><?= h(fmt_dt($pending['new_checkout_at'])) ?></b>, доплата <b><?= h(fmt_rub((int)$pending['surcharge'])) ?></b>. Отправьте гостю ту же ссылку.</p>
      <form method="post" action="<?= h(url('admin/stay/' . $stay['id'] . '/extension-cancel')) ?>">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>"><input type="hidden" name="ext" value="<?= (int)$pending['id'] ?>">
        <button class="btn btn--ghost btn--sm">Отменить продление</button>
      </form>
    <?php elseif ($signed && !$stay['resign_required'] && in_array($stay['status'], ['signed', 'living'], true)): ?>
      <form method="post" action="<?= h(url('admin/stay/' . $stay['id'] . '/extend')) ?>">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <div class="grid2">
          <?= field('new_checkout_at', 'Новая дата выезда', ['new_checkout_at' => date('Y-m-d', strtotime($stay['checkout_at'] . ' +1 day')) . 'T12:00'], [], ['type' => 'datetime-local', 'required' => true]) ?>
          <?= field('surcharge', 'Доплата, ₽', [], [], ['required' => true, 'inputmode' => 'numeric']) ?>
        </div>
        <button class="btn btn--navy">Продлить проживание</button>
      </form>
    <?php else: ?>
      <p class="a-small">Доступно после подписания договора.</p>
    <?php endif; ?>
    <?php foreach ($extensions as $e): if ($e['status'] === 'pending') continue; ?>
      <p class="a-small"><?= $e['status'] === 'signed' ? 'Подписано' : 'Отменено' ?>: выезд <?= h(fmt_dt($e['old_checkout_at'])) ?> → <?= h(fmt_dt($e['new_checkout_at'])) ?>, доплата <?= h(fmt_rub((int)$e['surcharge'])) ?></p>
    <?php endforeach; ?>
  </section>

  <section class="a-card">
    <h2>История</h2>
    <ul class="a-log">
      <?php foreach (array_reverse($audit) as $a): ?>
        <li><b><?= h($eventNames[$a['event']] ?? $a['event']) ?></b> · <?= h(fmt_utc_as_msk($a['at_utc'])) ?><br>
          <span class="a-small"><?= h($a['actor'] === 'guest' ? 'гость' : ($a['actor'] === 'admin' ? 'администратор' : 'система')) ?> · IP <?= h($a['ip']) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>
</div>
