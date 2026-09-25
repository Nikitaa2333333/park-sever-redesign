<?php /** @var array $terms */ ?>
<div class="ticket">
  <p class="ticket__house"><?= icon('house') ?><?= h($terms['house_name']) ?></p>
  <div class="ticket__dates">
    <div>
      <span class="ticket__lbl">Заезд</span>
      <b><?= h(fmt_dm($terms['checkin_at'])) ?></b>
      <span><?= h(fmt_wd($terms['checkin_at'])) ?>, с <?= h(fmt_time($terms['checkin_at'])) ?></span>
    </div>
    <div class="ticket__mid"><span><?= h(nights_label($terms['nights'])) ?></span><?= icon('arrow-right') ?></div>
    <div>
      <span class="ticket__lbl">Выезд</span>
      <b><?= h(fmt_dm($terms['checkout_at'])) ?></b>
      <span><?= h(fmt_wd($terms['checkout_at'])) ?>, до <?= h(fmt_time($terms['checkout_at'])) ?></span>
    </div>
  </div>
  <div class="ticket__foot">
    <span>Стоимость <b><?= h(fmt_rub($terms['price'])) ?></b></span>
    <span>Депозит <b><?= h(fmt_rub($terms['deposit'])) ?></b> · вернём после выезда</span>
  </div>
</div>
