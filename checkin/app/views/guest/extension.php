<?php /** @var array $stay @var array $ext @var array $doc @var string $formToken */ $terms = stay_terms($stay); ?>
<section class="g-hero">
  <div class="g-wrap">
    <h1 class="g-h1">Продление проживания</h1>
    <p class="g-lead">Остаётесь ещё? Прекрасно. Подтвердите дополнительное соглашение — одним нажатием.</p>
    <dl class="terms">
      <div><dt>Объект</dt><dd><?= h($terms['house_name']) ?></dd></div>
      <div><dt>Было</dt><dd>выезд <?= h(fmt_dt($ext['old_checkout_at'])) ?></dd></div>
      <div><dt>Стало</dt><dd>выезд <?= h(fmt_date($ext['new_checkout_at'])) ?> до <?= h(fmt_time($ext['new_checkout_at'])) ?></dd></div>
      <div><dt>Доплата</dt><dd><?= h(fmt_rub((int)$ext['surcharge'])) ?></dd></div>
    </dl>
  </div>
</section>
<form class="g-form g-form--static" method="post" action="">
  <input type="hidden" name="_ft" value="<?= h($formToken) ?>">
  <input type="hidden" name="client_tz" value="">
  <input type="hidden" name="client_screen" value="">
  <input type="hidden" name="client_opened" value="">
  <?php require __DIR__ . '/_doc.php'; ?>
  <div class="accept">
    <label class="check">
      <input type="checkbox" name="agree_extension" value="1" required data-accept>
      <span><?= h(extension_acceptance_text()) ?></span>
    </label>
  </div>
  <button class="btn btn--navy btn--wide" type="submit" data-sign>Подписать продление</button>
</form>
