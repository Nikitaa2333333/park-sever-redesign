<?php /** @var array $v @var array $errors */ ?>
<div class="fld<?= isset($errors['house_id']) ? ' fld--err' : '' ?>">
  <label for="f-house_id">Дом</label>
  <select id="f-house_id" name="house_id" required>
    <?php foreach (houses() as $id => $hs): ?>
      <option value="<?= h($id) ?>" <?= $v['house_id'] === $id ? 'selected' : '' ?>><?= h($hs['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <p class="fld__err"><?= h($errors['house_id'] ?? '') ?></p>
</div>
<div class="grid2">
  <?= field('checkin_at', 'Заезд', ['checkin_at' => str_replace(' ', 'T', substr((string)$v['checkin_at'], 0, 16))], $errors, ['type' => 'datetime-local', 'required' => true], 'По умолчанию с 17:00') ?>
  <?= field('checkout_at', 'Выезд', ['checkout_at' => str_replace(' ', 'T', substr((string)$v['checkout_at'], 0, 16))], $errors, ['type' => 'datetime-local', 'required' => true], 'По умолчанию до 12:00') ?>
</div>
<div class="grid2">
  <?= field('price', 'Согласованная стоимость, ₽', $v, $errors, ['required' => true, 'inputmode' => 'numeric']) ?>
  <?= field('deposit', 'Обеспечительный депозит, ₽', $v, $errors, ['required' => true, 'inputmode' => 'numeric']) ?>
</div>
<div class="grid2">
  <?= field('guest_label', 'Кто бронировал (пометка для себя)', $v, $errors, ['placeholder' => 'Дмитрий, из ВК']) ?>
  <?= field('guest_phone', 'Телефон гостя', $v, $errors, ['type' => 'tel', 'placeholder' => '+7…']) ?>
</div>
<?php require_once __DIR__ . '/../../menu.php'; ?>
<div class="fld">
  <label for="f-tariff">Тариф <span class="fld__opt">необязательно</span></label>
  <select id="f-tariff" name="tariff">
    <option value="">Не выбран — меню гостю не открыто</option>
    <?php foreach (menu_catalog()['tariffs'] as $tid => $tl): ?>
      <option value="<?= h($tid) ?>" <?= ($v['tariff'] ?? '') === $tid ? 'selected' : '' ?>><?= h($tl) ?></option>
    <?php endforeach; ?>
  </select>
  <p class="fld__hint">С тарифом гость получит страницу «Меню и допы» по своей ссылке</p>
</div>
<?= field('prepaid_extras', 'Уже оформленные допы (гость видит)', $v, $errors, ['type' => 'textarea', 'placeholder' => 'Например: торт бенто 800 г, шары «Сердце»']) ?>
<?= field('admin_note', 'Заметка (гость не видит)', $v, $errors, ['type' => 'textarea']) ?>
