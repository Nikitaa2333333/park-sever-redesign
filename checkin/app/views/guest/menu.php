<?php
/** @var array $stay @var string $tariff @var int $nights @var array $data @var array $errors @var ?string $flash @var ?string $savedAt @var string $formToken */
$terms = stay_terms($stay);
[$total, $unknown] = menu_total($data, $tariff);
?>
<section class="welcome">
  <h1 class="g-h1">Меню и допы</h1>
  <p class="g-lead">Ваш тариф — «<?= h(tariff_label($tariff)) ?>». Отметьте, что приготовить и подготовить к вашему приезду. Выбор можно поменять по этой же ссылке.</p>
  <?php require __DIR__ . '/_terms.php'; ?>
  <?php if (trim((string)$stay['prepaid_extras']) !== ''): ?>
    <div class="m-paid">
      <p class="m-paid__t">Уже оформлено</p>
      <p><?= nl2br(h($stay['prepaid_extras'])) ?></p>
    </div>
  <?php endif; ?>
</section>

<?php if ($flash): ?><p class="g-alert"><?= h($flash) ?></p><?php endif; ?>
<?php if (!empty($errors['_form'])): ?><p class="g-alert"><?= h($errors['_form']) ?></p><?php endif; ?>

<form class="g-form m-form" method="post" action="" id="menu-form">
  <input type="hidden" name="_ft" value="<?= h($formToken) ?>">

  <?php foreach (menu_sections($tariff) as $s): $sid = $s['id']; ?>
  <section class="m-sec" id="m-<?= h($sid) ?>">
    <h2 class="m-h"><?= h($s['title']) ?></h2>
    <?php if (!empty($s['intro'])): ?><p class="m-x"><?= h($s['intro']) ?></p><?php endif; ?>
    <?php if (!empty($errors[$sid])): ?><p class="g-alert"><?= h($errors[$sid]) ?></p><?php endif; ?>

    <?php if (!empty($s['included'])): ?>
      <ul class="dots m-incl"><?php foreach ($s['included'] as $it): ?><li><?= h($it) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>

    <?php foreach ($s['picks'] ?? [] as $p):
      $max = pick_max($p, $nights);
      $type = $max === 1 ? 'radio' : 'checkbox';
      $chosen = $data[$sid]['picks'][$p['id']] ?? [];
      $name = 'm[' . $sid . '][' . $p['id'] . '][]';
      $hint = $max === 1 ? '' : ($max > 1 ? 'Можно выбрать до ' . $max . ' ' . plural($max, 'позиции', 'позиций', 'позиций') : '');
    ?>
      <div class="m-pick" data-max="<?= $max ?>">
        <?php if (!empty($p['label']) || $hint): ?><p class="m-pick__l"><?= h(trim(($p['label'] ?? '') . ($hint && !empty($p['label']) ? ' · ' : '') . $hint)) ?></p><?php endif; ?>
        <?php foreach ($p['options'] as $o):
          $inTariff = option_in_tariff($o, $tariff);
          $hasPrice = array_key_exists('price', $o);
          $priceText = $inTariff ? 'входит в тариф' : (!$hasPrice ? '' : ($o['price'] === null ? 'цена уточняется' : ($o['price'] ? fmt_rub((int)$o['price']) : 'без доплат')));
        ?>
          <?php if ($inTariff): ?>
            <div class="m-opt m-opt--in">
              <span class="m-opt__t"><span class="m-opt__n"><?= h($o['name']) ?></span><?php if (!empty($o['desc'])): ?><span class="m-opt__d"><?= h($o['desc']) ?></span><?php endif; ?></span>
              <span class="m-opt__p"><?= h($priceText) ?></span>
            </div>
          <?php else: ?>
            <label class="m-opt">
              <input type="<?= $type ?>" name="<?= h($name) ?>" value="<?= h($o['id']) ?>"<?= in_array($o['id'], $chosen, true) ? ' checked' : '' ?><?= $hasPrice && $o['price'] ? ' data-price="' . (int)$o['price'] . '"' : '' ?><?= $hasPrice && $o['price'] === null ? ' data-price-unknown' : '' ?>>
              <span class="m-opt__t"><span class="m-opt__n"><?= h($o['name']) ?></span><?php if (!empty($o['desc'])): ?><span class="m-opt__d"><?= h($o['desc']) ?></span><?php endif; ?></span>
              <?php if ($priceText !== ''): ?><span class="m-opt__p"><?= h($priceText) ?></span><?php endif; ?>
            </label>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($type === 'radio'): ?><button type="button" class="linkish m-clear" data-clear>Не выбирать</button><?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if (!empty($s['after'])): ?><p class="m-x"><?= h($s['after']) ?></p><?php endif; ?>

    <?php foreach ($s['fields'] ?? [] as $f):
      $fname = 'm[' . $sid . '][f][' . $f['id'] . ']';
      $fid = 'm-' . $sid . '-' . $f['id'];
      $val = (string)($data[$sid]['fields'][$f['id']] ?? '');
    ?>
      <?php if ($f['type'] === 'check'): ?>
        <label class="agree m-check"><input type="checkbox" name="<?= h($fname) ?>" value="1"<?= $val ? ' checked' : '' ?>><span><?= h($f['label']) ?></span></label>
      <?php else: ?>
        <div class="fld">
          <label for="<?= h($fid) ?>"><?= h($f['label']) ?> <span class="fld__opt">необязательно</span></label>
          <?php if ($f['type'] === 'textarea'): ?>
            <textarea id="<?= h($fid) ?>" name="<?= h($fname) ?>" rows="2" maxlength="1000" placeholder="<?= h($f['placeholder'] ?? '') ?>"><?= h($val) ?></textarea>
          <?php else: ?>
            <input id="<?= h($fid) ?>" type="text" name="<?= h($fname) ?>" maxlength="200" value="<?= h($val) ?>" placeholder="<?= h($f['placeholder'] ?? '') ?>">
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>

    <?php if (!empty($s['photo'])): ?>
      <p class="m-x m-photo">Фото-референс пока пришлите нам в мессенджер — загрузку прямо сюда добавим.</p>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

  <div class="g-nav m-bar" data-menu-bar>
    <div class="g-nav__in">
      <p class="m-total">Дополнительно к оплате: <b data-menu-total><?= h(fmt_rub($total)) ?></b><span data-menu-unknown<?= $unknown ? '' : ' hidden' ?>> + позиции с ценой по запросу</span></p>
      <button class="btn btn--navy btn--wide" type="submit">Сохранить выбор</button>
      <?php if ($savedAt): ?><p class="m-saved">Последнее сохранение: <?= h(fmt_dt($savedAt)) ?></p><?php endif; ?>
    </div>
  </div>
</form>
