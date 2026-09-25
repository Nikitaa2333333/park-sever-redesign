<?php /** @var array $stay @var array $ext @var array $doc @var string $formToken */ $terms = stay_terms($stay); ?>
<section class="welcome">
  <h1 class="g-h1">Остаётесь ещё?</h1>
  <p class="g-lead">Прекрасно. Подтвердите продление — одним нажатием.</p>
  <?php $photo = house($stay['house_id'])['photo'] ?? null; ?>
  <div class="ticket">
    <div class="ticket__cover"><?php if ($photo): ?><img src="<?= h(asset($photo)) ?>" alt=""><?php endif; ?><p class="ticket__house"><?= h($terms['house_name']) ?></p></div>
    <div class="ticket__dates">
      <div><span class="ticket__lbl">Было</span><b><?= h(fmt_dm($ext['old_checkout_at'])) ?></b><span>выезд до <?= h(fmt_time($ext['old_checkout_at'])) ?></span></div>
      <div class="ticket__mid"><span>+<?= nights($ext['old_checkout_at'], $ext['new_checkout_at']) ?></span></div>
      <div><span class="ticket__lbl">Стало</span><b><?= h(fmt_dm($ext['new_checkout_at'])) ?></b><span>выезд до <?= h(fmt_time($ext['new_checkout_at'])) ?></span></div>
    </div>
    <div class="ticket__foot"><span>Доплата <b><?= h(fmt_rub((int)$ext['surcharge'])) ?></b></span></div>
  </div>
</section>
<form class="g-form g-form--static" method="post" action="">
  <input type="hidden" name="_ft" value="<?= h($formToken) ?>">
  <input type="hidden" name="client_tz" value="">
  <input type="hidden" name="client_screen" value="">
  <input type="hidden" name="client_opened" value="">
  <div class="docs-list">
    <button type="button" class="doc-row" data-sheet="sheet-contract">
      <span class="doc-row__t"><b><?= h($doc['title']) ?></b><span>№ <?= h($doc['number']) ?></span></span>
      <span class="doc-row__go">Читать</span>
    </button>
  </div>
  <?php require __DIR__ . '/_doc.php'; ?>
  <div class="agrees">
    <label class="agree">
      <input type="checkbox" name="agree_extension" value="1" required data-accept>
      <span class="agree__text"><?= h(extension_acceptance_text()) ?></span>
    </label>
  </div>
  <button class="btn btn--gold btn--wide btn--sign" type="submit" data-sign>Подписать продление</button>
</form>
