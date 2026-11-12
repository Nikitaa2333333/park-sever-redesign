<?php
/** @var array $stay @var string $booked @var int $nights @var array $data @var array $errors @var ?string $flash @var ?array $order @var array $paid @var bool $open @var string $formToken @var bool $hidePrices */
$cat = menu_catalog();
$terms = stay_terms($stay);
$tariff = effective_tariff($booked, $nights, $data);
[$total, $unknown] = menu_total($data, $booked, $nights);
$paidTotal = (int)($order['paid_total'] ?? 0);
$pending = (int)($order['pending_total'] ?? 0);
$status = (string)($order['status'] ?? 'draft');
$due = max(0, $total - $paidTotal);
$pkgOptions = package_options($booked, $nights);

// блоки экрана: [id => [настройки блока, [видимые разделы]]]
$groups = [];
foreach ($cat['groups'] as $gid => $g) $groups[$gid] = [$g, []];
foreach (menu_sections($tariff, $nights, $data, true) as $s) $groups[$s['group']][1][] = $s;
$groups = array_filter($groups, fn($g, $gid) => !group_hidden($gid) && (!empty($g[0]['kind']) || $g[1]), ARRAY_FILTER_USE_BOTH);
$choiceTitles = array_map(fn($g) => '«' . $g[0]['title'] . '»', array_filter($groups, fn($g) => empty($g[0]['kind']) || $g[0]['kind'] === 'package'));

$hasChoices = (bool)$choiceTitles; // все блоки выбора скрыты — страница «к приезду»: без цен и слов про меню
// регистрационная карта: анкета и договор подписаны — или ещё нет
$checkinDone = !in_array($stay['status'], ['created', 'sent'], true) && empty($stay['resign_required']);
$indicator = $nights >= 2 ? 'Ваш тариф: ' . tariff_label($tariff) : tariff_label($tariff);
$confirmed = in_array($status, ['confirmed', 'paid'], true);
$homeReady = fmt_dm($stay['checkin_at']) . ' в ' . arrival_text($data, $stay);
$summary = menu_summary($data, $booked, $nights, ['prices' => !$hidePrices]);
$c = $cat['contacts'];
$guests = (int)($stay['guests'] ?? 2);
$cutoffText = fmt_dm(date('Y-m-d H:i', menu_cutoff_ts($stay))) . ', ' . fmt_time(date('Y-m-d H:i', menu_cutoff_ts($stay)));
// фото блока/раздела: горизонтальные кадры, несколько — листаются свайпом
$gallery = function (array $photos): string {
    if (!$photos) return '';
    $out = '<div class="m-gal' . (count($photos) > 1 ? ' m-gal--multi' : '') . '">';
    foreach ($photos as [$src, $alt]) {
        $out .= '<figure class="m-gal__i"><img src="' . h(asset($src)) . '" alt="' . h($alt) . '" loading="lazy" decoding="async"></figure>';
    }
    return $out . '</div>';
};
?>
<section class="welcome">
  <p class="m-tariff"><?= h($indicator) ?></p>
  <?php // «Скрыть цены» — подарок: прячем ценники кодом и пересылаем ссылку ?>
  <?php if ($hasChoices): ?>
  <details class="m-lock"<?= !empty($errors['_form']) && str_contains($errors['_form'], 'Код') ? ' open' : '' ?>>
    <summary class="m-lock__chip"><span class="m-lock__ico<?= $hidePrices ? '' : ' m-lock__ico--off' ?>" aria-hidden="true">₽</span><?= $hidePrices ? 'Показать цены' : 'Скрыть цены' ?></summary>
    <form class="m-lock__f" method="post" action="">
      <input type="hidden" name="_ft" value="<?= h($formToken) ?>">
      <input type="hidden" name="_do" value="<?= $hidePrices ? 'unlock' : 'lock' ?>">
      <?php if ($hidePrices): ?>
        <p>Введите код, которым скрывали цены.</p>
      <?php else: ?>
        <p>Хотите сделать подарок? Придумайте код, скройте цены и перешлите ссылку — по ней можно будет выбрать допы, не видя ценников. Вернуть цены — тем же кодом. Сначала сохраните свой выбор.</p>
      <?php endif; ?>
      <div class="m-lock__row">
        <input type="password" name="code" minlength="4" maxlength="40" required autocomplete="off" placeholder="<?= $hidePrices ? 'Код' : 'Код, от 4 символов' ?>" aria-label="Код">
        <button class="btn btn--navy btn--sm"><?= $hidePrices ? 'Показать цены' : 'Скрыть' ?></button>
      </div>
    </form>
  </details>
  <?php endif; ?>

  <?php if ($confirmed && !$pending): ?>
    <div class="m-status">
      <p class="m-status__t">Бронь подтверждена</p>
      <p>Дом будет готов к Вашему приезду: <b><?= h($homeReady) ?></b></p>
      <?php if ($hasChoices): ?><p>Меню зафиксировано<?= $paidTotal && !$hidePrices ? ' · оплачено ' . h(fmt_rub($paidTotal)) : '' ?> · <button type="button" class="linkish" data-sheet="sheet-order">посмотреть заказ</button></p><?php endif; ?>
      <p class="m-status__n"><?= $open
        ? 'До ' . h(fmt_dm(date('Y-m-d H:i', menu_cutoff_ts($stay)))) . ', ' . h(fmt_time(date('Y-m-d H:i', menu_cutoff_ts($stay)))) . ($hasChoices ? ' можно докупить допы и поменять время приезда — ниже, в блоках.' : ' время приезда можно поменять ниже.')
        : 'Заказ передан на кухню. Для срочных изменений свяжитесь с нами напрямую.' ?></p>
    </div>
  <?php elseif (!$open): ?>
    <div class="m-status"><p class="m-status__t">Заказ передан на кухню</p><p>Для срочных изменений свяжитесь с нами напрямую — контакты внизу, в блоке «Маршрут».</p></div>
  <?php else: ?>
    <?php if ($hasChoices): ?>
    <p class="g-lead">Настройте детали Вашего отдыха в блоках <?= h(implode(', ', $choiceTitles)) ?>.</p>
    <p class="g-lead m-lead-2">Выбор можно поменять по этой же ссылке до <?= h($cutoffText) ?>.</p>
    <?php else: ?>
    <p class="g-lead">Заполните регистрационную карту гостя и сориентируйте нас по времени прибытия — мы подготовим дом к Вашему приезду.</p>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($pending && !$hidePrices): ?>
    <div class="m-status m-status--pay" id="m-pay">
      <p class="m-status__t">Ожидает оплаты: <?= h(fmt_rub($pending)) ?></p>
      <?php if ((string)cfg('sbp_pay_url', '') !== ''): ?>
        <p>Оплатите доплату за допы через СБП — после оплаты мы подтвердим получение, и статус здесь сменится на «Оплачено».</p>
        <a class="btn btn--gold btn--wide" href="<?= h((string)cfg('sbp_pay_url')) ?>" target="_blank" rel="noopener">Оплатить через СБП</a>
      <?php else: ?>
        <p>Мы пришлём ссылку для оплаты через СБП в мессенджер. После оплаты статус здесь сменится на «Оплачено».</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php $noDeposit = true; require __DIR__ . '/_terms.php'; ?>
  <?php if (trim((string)$stay['prepaid_extras']) !== '' && !$hidePrices): // в режиме подарка не раскрываем уже заказанное ?>
    <div class="m-paid">
      <p class="m-paid__t">Уже оформлено</p>
      <p><?= nl2br(h($stay['prepaid_extras'])) ?></p>
    </div>
  <?php endif; ?>
</section>

<?php if ($flash): ?><p class="g-alert"><?= h($flash) ?></p><?php endif; ?>
<?php if (!empty($errors['_form'])): ?><p class="g-alert"><?= h($errors['_form']) ?></p><?php endif; ?>

<form class="g-form m-form" method="post" action="" id="menu-form" data-paid="<?= $hidePrices ? 0 : $paidTotal ?>" data-tariff="<?= h(tariff_label($tariff)) ?>">
  <input type="hidden" name="_ft" value="<?= h($formToken) ?>">
  <input type="hidden" name="_anchor" value="">
  <fieldset class="m-fs"<?= $open ? '' : ' disabled' ?>>

  <?php foreach ($groups as $gid => [$g, $gSections]):
    $kind = $g['kind'] ?? '';
    // разделы с правилом «только если выбрано» (шеф-повар) показываем/прячем на месте
    $gSectionsShown = array_filter($gSections, fn($s) => section_visible($s, $tariff, $nights, $data));
  ?>

  <?php if ($kind === 'info'): // Блок 1 — что уже есть в доме; сворачивается, как остальные (ТЗ 29.10) ?>
    <details class="m-grp m-grp--info" id="m-<?= h($gid) ?>">
      <summary class="m-grp__s">
        <span class="m-grp__h">
          <span class="m-grp__t"><?= h($g['title']) ?></span>
          <span class="m-grp__inc"><?= h($g['sub']) ?></span>
        </span>
      </summary>
      <?= $gallery($g['photos'] ?? []) ?>
      <section class="m-sec">
        <?php foreach ($cat['home'] as [$hTitle, $items]): ?>
          <h3 class="m-h"><?= h($hTitle) ?></h3>
          <ul class="dots m-incl"><?php foreach ($items as $it): ?><li><?= h($it) ?></li><?php endforeach; ?></ul>
        <?php endforeach; ?>
      </section>
    </details>
    <?php continue; endif; ?>

  <?php
    $chosenCount = 0;
    $hasError = false;
    foreach ($gSections as $s) {
      foreach ($data[$s['id']]['picks'] ?? [] as $ids) $chosenCount += count($ids);
      $hasError = $hasError || !empty($errors[$s['id']]);
    }
    $included = !$kind && group_included($gSectionsShown ?: $gSections, $tariff);
    $single = count($gSections) === 1;
    $isOpen = $hasError || ($kind === 'package' && $tariff !== $booked) || in_array($gid, ['arrival', 'route'], true) || ($gid === 'checkin' && !$checkinDone);
  ?>
  <details class="m-grp" id="m-<?= h($gid) ?>" data-grp data-grp-title="<?= h($g['title']) ?>"<?= $isOpen ? ' open' : '' ?><?= !$kind && !$gSectionsShown ? ' hidden' : '' ?>>
    <summary class="m-grp__s">
      <span class="m-grp__h">
        <span class="m-grp__t"><?= h($g['title']) ?></span>
        <?php if ($included && !$hidePrices): ?><span class="m-grp__inc">0 ₽ · включено в тариф</span><?php endif; ?>
      </span>
      <span class="m-grp__n" data-grp-count><?= $chosenCount ? 'выбрано: ' . $chosenCount : '' ?></span>
    </summary>
    <?php
      // одна страница «Подробнее» на весь блок — ссылка в самой плашке; разные страницы — у своих разделов
      $mores = array_values(array_unique(array_filter(array_map(fn($x) => $x['more'] ?? '', $gSections))));
      $grpMore = count($mores) === 1 ? $mores[0] : '';
    ?>
    <?php if ($grpMore !== '' && !$hidePrices): ?>
      <p class="m-grp__more"><a href="<?= h($cat['site'] . $grpMore) ?>" target="_blank" rel="noopener" data-sheet="sheet-more" data-more="<?= h(more_embed_url($grpMore)) ?>" data-more-title="<?= h($g['title']) ?>">Узнать подробнее и посмотреть фото</a></p>
    <?php endif; ?>
    <?= $gallery($g['photos'] ?? []) ?>

    <?php if ($kind === 'package'): // Блок 2 — пакеты ?>
      <section class="m-sec">
        <?php if (!$pkgOptions): $tc = tariff_cfg($booked); ?>
          <div class="m-opt m-opt--in m-opt--pkg">
            <span class="m-opt__t"><span class="m-opt__n">«<?= h($tc['short']) ?>» <span class="m-pkg__in">(включено в Ваш тариф)</span></span><span class="m-opt__d"><?= h($tc['desc']) ?></span>
              <?php if (!empty($tc['note'])): ?><span class="m-opt__d m-opt__note"><?= h($tc['note']) ?></span><?php endif; ?></span>
          </div>
        <?php else: ?>
          <p class="m-x">Выберите пакет — нужные блоки ниже сразу отметятся как включённые в тариф.<?= $nights < 2 ? ' Пакеты с гастрономией доступны при бронировании от 2 ночей.' : '' ?></p>
          <div class="m-pick" data-max="1" data-package>
            <?php foreach ($pkgOptions as $pid => $pc):
              $pPrice = $hidePrices ? '' : (($pc['price'] ?? null) === null ? 'цена уточняется' : fmt_rub((int)$pc['price'])); ?>
              <label class="m-opt">
                <input type="radio" name="package" value="<?= h($pid) ?>"<?= $tariff === $pid ? ' checked' : '' ?><?= $hidePrices ? '' : (($pc['price'] ?? null) === null ? ' data-price-unknown' : ' data-price="' . (int)$pc['price'] . '"') ?>>
                <span class="m-opt__t"><span class="m-opt__n">«<?= h($pc['short']) ?>»<?= ($pc['min_nights'] ?? 1) > 1 ? ' <span class="m-pkg__in">(доступен только при бронировании от 2-х ночей)</span>' : '' ?></span><span class="m-opt__d"><?= h($pc['desc']) ?></span>
                  <?php if (!empty($pc['note'])): ?><span class="m-opt__d m-opt__note"><?= h($pc['note']) ?></span><?php endif; ?>
                  <?php if ($pPrice !== ''): ?><span class="m-opt__p<?= ($pc['price'] ?? null) === null ? ' m-opt__p--note' : '' ?>"><?= h($pPrice) ?></span><?php endif; ?></span>
              </label>
            <?php endforeach; ?>
            <label class="m-opt">
              <input type="radio" name="package" value=""<?= $tariff === $booked ? ' checked' : '' ?>>
              <span class="m-opt__t"><span class="m-opt__n">Без пакета</span></span>
            </label>
          </div>
          <noscript><p class="m-x">После выбора пакета нажмите кнопку внизу — блоки обновятся.</p></noscript>
        <?php endif; ?>
      </section>

    <?php elseif ($kind === 'checkin'): // Регистрационная карта гостя — анкета и договор по той же персональной ссылке ?>
      <section class="m-sec m-card">
        <?php if ($checkinDone): ?>
          <p class="m-card__ok">Карта заполнена, договор подписан</p>
          <p class="m-x">Копию договора можно скачать на странице регистрации.</p>
          <a class="btn btn--ghost btn--wide" href="<?= h(url($stay['token'])) ?>">Открыть регистрационную карту</a>
        <?php else: ?>
          <p class="m-x">Заполните анкету и подпишите договор проживания — это нужно для заселения и пропуска на въезд. Займёт около 5 минут, данные хранятся в зашифрованном виде.</p>
          <a class="btn btn--navy btn--wide" href="<?= h(url($stay['token'])) ?>">Заполнить регистрационную карту</a>
        <?php endif; ?>
      </section>

    <?php elseif ($kind === 'route'): // Блок 10 — маршрут и связь ?>
      <section class="m-sec m-route">
        <p class="m-x"><?= h($cat['route']['text']) ?></p>
        <img class="m-route__img" src="<?= h(asset($cat['route']['image'])) ?>" alt="Схема проезда: от Никитино по дороге 46К-0210, объезд деревни Василёво слева, вдоль ЛЭП к воротам Парка Север" loading="lazy">
        <a class="btn btn--navy btn--wide" href="<?= h($cat['route_url']) ?>" target="_blank" rel="noopener">Построить маршрут</a>
        <p class="m-h m-route__h">Связаться с Парк Север</p>
        <div class="m-contacts">
          <?php foreach (['telegram' => 'Telegram', 'vk' => 'ВКонтакте', 'whatsapp' => 'WhatsApp', 'max' => 'MAX'] as $k => $label): if (($c[$k] ?? '') === '') continue; ?>
            <a class="m-contact" href="<?= h($c[$k]) ?>" target="_blank" rel="noopener"><?= contact_icon($k) ?><span><?= h($label) ?></span></a>
          <?php endforeach; ?>
          <a class="m-contact" href="tel:<?= h($c['phone']) ?>"><?= contact_icon('phone') ?><span>Позвонить</span></a>
        </div>
      </section>

    <?php else: foreach ($gSections as $s):
      $sid = $s['id'];
      $free = section_free($s, $tariff);
      $closed = section_closed($s, $stay);
      $shown = section_visible($s, $tariff, $nights, $data);
    ?>
    <section class="m-sec" id="ms-<?= h($sid) ?>"<?= !empty($s['requires_unless_gastro']) && !tariff_gastro($tariff) ? ' data-requires="' . h($s['requires_unless_gastro']) . '"' : '' ?><?= $shown ? '' : ' hidden' ?>>
      <?php if (!$single): ?><h3 class="m-h"><?= h($s['title']) ?><?php if ($free && !$included && !$hidePrices): ?> <span class="m-h__inc">· 0 ₽, включено в тариф</span><?php endif; ?></h3><?php endif; ?>
      <?php if (!empty($s['intro'])): ?><p class="m-x"><?= h($s['intro']) ?></p><?php endif; ?>
      <?php if ($closed): ?><p class="g-alert">Срок заказа прошёл — до заезда меньше <?= (int)$s['deadline_days'] ?> суток. Напишите нам в мессенджер, постараемся помочь.</p><?php endif; ?>
      <?php if (!$closed && section_deadline_passed($s, $stay) && !empty($s['closed_note'])): ?><p class="g-alert"><?= h($s['closed_note']) ?></p><?php endif; ?>
      <?php // на сайте у услуг есть цены — при скрытых ценах ссылку не показываем ?>
      <?php if (!empty($s['more']) && $grpMore === '' && !$hidePrices): ?><p class="m-more"><a href="<?= h($cat['site'] . $s['more']) ?>" target="_blank" rel="noopener" data-sheet="sheet-more" data-more="<?= h(more_embed_url($s['more'])) ?>" data-more-title="<?= h($s['title']) ?>">Узнать подробнее и посмотреть фото</a></p><?php endif; ?>
      <?= $gallery($s['photos'] ?? []) ?>
      <?php if (!empty($errors[$sid])): ?><p class="g-alert"><?= h($errors[$sid]) ?></p><?php endif; ?>

      <?php if (!empty($s['included'])): ?>
        <ul class="dots m-incl"><?php foreach ($s['included'] as $it): ?><li><?= h($it) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>

      <fieldset class="m-fs"<?= $closed ? ' disabled' : '' ?>>
      <?php foreach ($s['picks'] ?? [] as $p):
        $max = pick_max($p, $nights, $s, $tariff, $guests);
        $type = $max === 1 ? 'radio' : 'checkbox';
        $chosen = $data[$sid]['picks'][$p['id']] ?? [];
        $locked = $free ? [] : ($paid[$sid]['picks'][$p['id']] ?? []);
        $name = 'm[' . $sid . '][' . $p['id'] . '][]';
        $hint = $max === 1 ? '' : ($max > 1 ? 'Можно выбрать до ' . $max . ' ' . plural($max, 'позиции', 'позиций', 'позиций') : '');
        if ($free && !empty($s['free_max']) && !$hidePrices && empty($p['when'])) $hint = 'В тариф входит ' . $s['free_max'] . ' ' . plural((int)$s['free_max'], 'позиция', 'позиции', 'позиций');
        // группа, которая раскрывается по выбору в другой группе (вкус — у торта, «к чаю» — у десерта)
        $ifAttr = '';
        if (!empty($p['when'])) {
            $wp = array_key_first($p['when']);
            $ifAttr = ' data-if-name="' . h('m[' . $sid . '][' . $wp . '][]') . '" data-if-ids="' . h(implode(',', $p['when'][$wp])) . '"'
                . (pick_when_ok($p['when'], $data[$sid]['picks'] ?? []) ? '' : ' hidden');
        }
      ?>
        <div class="m-pick" data-max="<?= $max ?>"<?= $ifAttr ?>>
          <?php if (!empty($p['label']) || $hint): ?><p class="m-pick__l"><?= h(trim(($p['label'] ?? '') . ($hint && !empty($p['label']) ? ' · ' : '') . $hint)) ?></p><?php endif; ?>
          <?php foreach ($p['options'] as $o):
            $inTariff = option_in_tariff($o, $tariff);
            $isLocked = in_array($o['id'], $locked, true);
            $hasPrice = array_key_exists('price', $o) && !$free;
            $priceText = $isLocked && !$hidePrices ? 'оплачено'
              : (($inTariff || ($free && array_key_exists('price', $o))) ? 'входит в тариф'
              : (!$hasPrice || $hidePrices ? '' : ($o['price'] === null ? 'цена уточняется' : ($o['price'] ? fmt_rub((int)$o['price']) : 'без доплат'))));
            $priceAttr = $hasPrice && !$hidePrices ? ($o['price'] ? ' data-price="' . (int)$o['price'] . '"' : ($o['price'] === null ? ' data-price-unknown' : '')) : '';
            $off = $isLocked || ($type === 'radio' && $locked && !$isLocked);
            // торт после срока: выбрать нельзя (если выбран раньше — остаётся)
            if (option_closed($o, $s, $stay) && !in_array($o['id'], $chosen, true)) { $off = true; $priceText = 'уточните у нас'; $priceAttr = ''; }
          ?>
            <?php if ($inTariff): ?>
              <div class="m-opt m-opt--in">
                <span class="m-opt__t"><span class="m-opt__n"><?= h($o['name']) ?></span><?php if (!empty($o['desc'])): ?><span class="m-opt__d"><?= h($o['desc']) ?></span><?php endif; ?>
                  <span class="m-opt__p m-opt__p--note"><?= h($priceText) ?></span></span>
              </div>
            <?php else: ?>
              <label class="m-opt<?= $isLocked ? ' m-opt--paid' : '' ?>">
                <input type="<?= $type ?>" name="<?= h($name) ?>" value="<?= h($o['id']) ?>"<?= in_array($o['id'], $chosen, true) || $isLocked ? ' checked' : '' ?><?= $off ? ' disabled' : '' ?><?= $priceAttr ?>>
                <?php if ($isLocked): // заблокированная галочка не отправляется — дублируем значение ?><input type="hidden" name="<?= h($name) ?>" value="<?= h($o['id']) ?>"><?php endif; ?>
                <span class="m-opt__t"><span class="m-opt__n"><?= h($o['name']) ?></span><?php if (!empty($o['desc'])): ?><span class="m-opt__d"><?= h($o['desc']) ?></span><?php endif; ?>
                  <?php if ($priceText !== ''): ?><span class="m-opt__p<?= ($priceText !== '' && !preg_match('/\\d/', $priceText) ? ' m-opt__p--note' : '') ?>"><?= h($priceText) ?></span><?php endif; ?></span>
              </label>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if ($type === 'radio' && !$locked): ?><button type="button" class="linkish m-clear" data-clear<?= $chosen ? '' : ' hidden' ?>>Снять выбор</button><?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if (!empty($s['after'])): ?><p class="m-x"><?= h($s['after']) ?></p><?php endif; ?>

      <?php foreach ($s['fields'] ?? [] as $f):
        $fname = 'm[' . $sid . '][f][' . $f['id'] . ']';
        $fid = 'm-' . $sid . '-' . $f['id'];
        $val = (string)($data[$sid]['fields'][$f['id']] ?? '');
        $when = !empty($f['when']) ? ' data-when="m-' . h($sid . '-' . $f['when']) . '"' . (empty($data[$sid]['fields'][$f['when']]) ? ' hidden' : '') : '';
        if (!empty($f['when_pick'])) {
            $wp = array_key_first($f['when_pick']);
            $when .= ' data-if-name="' . h('m[' . $sid . '][' . $wp . '][]') . '" data-if-ids="' . h(implode(',', $f['when_pick'][$wp])) . '"'
                . (pick_when_ok($f['when_pick'], $data[$sid]['picks'] ?? []) ? '' : ' hidden');
        }
      ?>
        <?php if ($f['type'] === 'check'): ?>
          <label class="agree m-check"><input type="checkbox" id="<?= h($fid) ?>" name="<?= h($fname) ?>" value="1"<?= $val ? ' checked' : '' ?>><span><?= h($f['label']) ?></span></label>
        <?php elseif ($f['type'] === 'select'): $val = $val !== '' ? $val : fmt_time($stay['checkin_at']); ?>
          <div class="fld">
            <label for="<?= h($fid) ?>"><?= h($f['label']) ?></label>
            <select id="<?= h($fid) ?>" name="<?= h($fname) ?>" data-arrival>
              <?php foreach (arrival_options() as $ov => $ol): ?><option value="<?= h($ov) ?>"<?= $ov === $val ? ' selected' : '' ?>><?= h($ol) ?></option><?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <div class="fld"<?= $when ?>>
            <label for="<?= h($fid) ?>"><?= h($f['label']) ?><?php if (empty($f['when'])): ?> <span class="fld__opt">необязательно</span><?php endif; ?></label>
            <?php if (!empty($f['hint'])): ?><p class="fld__hint m-x"><?= h($f['hint']) ?></p><?php endif; ?>
            <?php if ($f['type'] === 'textarea'): ?>
              <textarea id="<?= h($fid) ?>" name="<?= h($fname) ?>" rows="2" maxlength="1000" placeholder="<?= h($f['placeholder'] ?? '') ?>"><?= h($val) ?></textarea>
            <?php else: ?>
              <input id="<?= h($fid) ?>" type="text" name="<?= h($fname) ?>" maxlength="200" value="<?= h($val) ?>" placeholder="<?= h($f['placeholder'] ?? '') ?>">
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
      </fieldset>

      <?php if (!empty($s['photo'])): ?>
        <p class="m-x m-photo">Фото-референс пока пришлите нам в мессенджер — загрузку прямо сюда добавим.</p>
      <?php endif; ?>
      <?php // ТЗ 29.10: определились с тортом — можно сразу отправить выбор (сервер сам решит: подтвердить или к оплате) ?>
      <?php if (!empty($s['send_button']) && $open && !$closed): ?>
        <button class="btn btn--ghost btn--wide m-send" type="submit" name="_do" value="confirm">Отправить выбор в Парк Север</button>
      <?php endif; ?>
    </section>
    <?php endforeach; endif; ?>
  </details>
  <?php endforeach; ?>
  </fieldset>

  <?php // Липкий подвал: что к доплате + действие. Пересчитывается на лету (checkin.js). ?>
  <div class="g-nav m-bar" data-menu-bar>
    <div class="g-nav__in">
      <?php if (!$hidePrices && $hasChoices): // без блоков выбора доплаты не бывает — строку не показываем ?>
        <p class="m-total" data-menu-line>
          <?php if ($due > 0): ?>Дополнительно: <b>+ <?= h(fmt_rub($due)) ?></b><?php else: ?>Тариф: <?= h(tariff_label($tariff)) ?> · <b>0 ₽</b> к доплате<?php endif; ?><span data-menu-unknown<?= $unknown ? '' : ' hidden' ?>> + позиции с ценой по запросу</span>
        </p>
      <?php endif; ?>
      <?php if ($open): ?>
        <?php if ($hidePrices && $hasChoices): ?>
          <button class="btn btn--navy btn--wide" type="submit" name="_do" value="save">Сохранить выбор</button>
        <?php else: ?>
          <button class="btn btn--navy btn--wide" type="submit" name="_do" value="<?= $due > 0 ? 'pay' : 'confirm' ?>" data-menu-submit data-label-zero="<?= $hasChoices ? 'Подтвердить выбор меню' : 'Подтвердить время прибытия' ?>"><?= $due > 0 ? 'Оплатить через СБП (' . h(fmt_rub($due)) . ')' : ($hasChoices ? 'Подтвердить выбор меню' : 'Подтвердить время прибытия') ?></button>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($hasChoices): ?><p class="m-saved"><button type="button" class="linkish" data-sheet="sheet-order">Посмотреть состав заказа</button></p><?php endif; ?>
    </div>
  </div>
</form>

<?php // «Подробнее и фото» — страница сайта в окне поверх формы (?embed=1: без шапки и подвала) ?>
<section class="sheet sheet--wide" id="sheet-more" role="dialog" aria-modal="true" aria-label="Подробнее">
  <div class="sheet__panel">
    <header class="sheet__head"><h3 data-more-head>Подробнее</h3><button type="button" class="sheet__close" data-sheet-close aria-label="Закрыть">×</button></header>
    <div class="sheet__body m-more__body"><iframe class="m-more__frame" title="Подробнее и фото" data-more-frame></iframe></div>
    <footer class="sheet__foot"><button type="button" class="btn btn--navy btn--wide" data-sheet-close>Вернуться к выбору</button></footer>
  </div>
</section>

<?php // Состав заказа — шторка. Без JS — сохранённый выбор; с JS — пересобирается из отмеченного на экране. ?>
<section class="sheet" id="sheet-order" role="dialog" aria-modal="true" aria-label="Состав заказа">
  <div class="sheet__panel">
    <header class="sheet__head"><h3>Состав заказа</h3><button type="button" class="sheet__close" data-sheet-close aria-label="Закрыть">×</button></header>
    <div class="sheet__body" data-order-body>
      <p class="doc-meta"><?= h(tariff_label($tariff)) ?> · <?= h(menu_dates($stay)) ?>, приезд ~<?= h(arrival_text($data, $stay)) ?></p>
      <?php if (!$summary): ?><p>Пока ничего не выбрано.</p><?php endif; ?>
      <?php foreach ($summary as [$title, , $lines]): ?>
        <h4><?= h($title) ?></h4>
        <ul class="dots"><?php foreach ($lines as $l): ?><li><?= h($l) ?></li><?php endforeach; ?></ul>
      <?php endforeach; ?>
    </div>
    <footer class="sheet__foot"><button type="button" class="btn btn--navy btn--wide" data-sheet-close>Закрыть</button></footer>
  </div>
</section>
