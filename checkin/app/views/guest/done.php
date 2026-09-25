<?php /** @var array $stay @var array $signatures @var string $firstName */ $terms = stay_terms($stay); ?>
<section class="welcome welcome--done">
  <p class="done-mark">Договор подписан</p>
  <h1 class="g-h1">Спасибо<?= $firstName !== '' ? ', ' . h($firstName) : '' ?>!</h1>
  <p class="g-lead">Регистрация пройдена, договор подписан. Ждём вас в Парке Север к <?= h(fmt_time($stay['checkin_at'])) ?>! Гаечка, Рыжуля и Бэлла готовы встречать 🐾</p>
  <?php require __DIR__ . '/_terms.php'; ?>
</section>
<section class="g-block">
  <h2 class="g-h2">Ваши документы</h2>
  <p class="g-p">Копия подписана простой электронной подписью и имеет ту же силу, что и бумажный договор. Сохраните её.</p>
  <div class="docs-list">
    <?php foreach (array_reverse($signatures) as $s): ?>
      <a class="doc-row" href="<?= h(url($stay['token'] . '/pdf?s=' . $s['id'])) ?>">
        <span class="doc-row__t"><b><?= $s['kind'] === 'contract' ? 'Договор найма' : 'Доп. соглашение о продлении' ?> № <?= h($s['doc_number']) ?></b><span>Подписан <?= h(fmt_utc_as_msk($s['signed_at_utc'])) ?></span></span>
        <span class="doc-row__go">Скачать PDF</span>
      </a>
    <?php endforeach; ?>
  </div>
  <p class="note">Нашли ошибку в данных? Напишите нам — поправим, и вы переподпишете договор по этой же ссылке.</p>
</section>
