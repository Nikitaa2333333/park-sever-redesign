<?php /** @var array $stay @var array $signatures */ $terms = stay_terms($stay); ?>
<section class="g-hero">
  <div class="g-wrap">
    <h1 class="g-h1">Спасибо!</h1>
    <p class="g-lead">Регистрация пройдена, договор подписан. Ждём вас в Парке Север к <?= h(fmt_time($stay['checkin_at'])) ?>! Гаечка, Рыжуля и Бэлла готовы встречать 🐾</p>
    <?php require __DIR__ . '/_terms.php'; ?>
  </div>
</section>
<section class="g-sec">
  <div class="g-wrap">
    <h2 class="g-h2">Ваши документы</h2>
    <p class="g-p">Сохраните копию — она подписана простой электронной подписью и имеет ту же силу, что и бумажный договор.</p>
    <ul class="docs">
      <?php foreach (array_reverse($signatures) as $s): ?>
        <li>
          <div>
            <p class="docs__t"><?= $s['kind'] === 'contract' ? 'Договор найма' : 'Доп. соглашение о продлении' ?> № <?= h($s['doc_number']) ?></p>
            <p class="docs__m">Подписан <?= h(fmt_utc_as_msk($s['signed_at_utc'])) ?></p>
          </div>
          <a class="btn btn--ghost" href="<?= h(url($stay['token'] . '/pdf?s=' . $s['id'])) ?>">Скачать PDF</a>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="g-p g-p--sm">Нашли ошибку в данных? Напишите нам — администратор поправит условия, и вы переподпишете договор по этой же ссылке.</p>
  </div>
</section>
