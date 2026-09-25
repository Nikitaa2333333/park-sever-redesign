<dl class="terms">
  <div><dt>Объект</dt><dd><?= h($terms['house_name']) ?></dd></div>
  <div><dt>Заезд</dt><dd><?= h(fmt_date($terms['checkin_at'])) ?> с <?= h(fmt_time($terms['checkin_at'])) ?></dd></div>
  <div><dt>Выезд</dt><dd><?= h(fmt_date($terms['checkout_at'])) ?> до <?= h(fmt_time($terms['checkout_at'])) ?></dd></div>
  <div><dt>Стоимость</dt><dd><?= h(fmt_rub($terms['price'])) ?> <span class="terms__sub">за <?= $terms['nights'] ?> <?= plural($terms['nights'], 'сутки', 'суток', 'суток') ?></span></dd></div>
  <div><dt>Депозит</dt><dd><?= h(fmt_rub($terms['deposit'])) ?> <span class="terms__sub">возвратный</span></dd></div>
</dl>
