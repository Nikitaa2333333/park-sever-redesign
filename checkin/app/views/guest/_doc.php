<?php /** @var array $doc */ ?>
<article class="doc" id="doc-contract" tabindex="-1">
  <header class="doc__head">
    <h3 class="doc__title"><?= h($doc['title']) ?></h3>
    <p class="doc__meta">№ <?= h($doc['number']) ?> · редакция <?= h($doc['version']) ?></p>
  </header>
  <div class="doc__scroll">
    <?php foreach ($doc['contract'] as $s): ?>
      <h4><?= h($s['title']) ?></h4>
      <?php foreach ($s['points'] as $pt): ?><p><?= h($pt) ?></p><?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</article>

<?php if ($doc['inventory']): ?>
<details class="doc-more" id="doc-inventory">
  <summary>Приложение № 1. Опись имущества дома</summary>
  <div class="doc-more__body">
    <?php foreach ($doc['inventory'] as $g): ?>
      <h4><?= h($g['group']) ?></h4>
      <ul class="inv">
        <?php foreach ($g['items'] as $it): ?>
          <li><span><?= h($it['name']) ?><?= $it['qty'] !== '—' ? ' · ' . h($it['qty']) . ' шт.' : '' ?></span><span class="inv__v"><?= h(inventory_value($it['value'])) ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
    <h4>Дефекты, зафиксированные до вашего заезда</h4>
    <?php if ($doc['defects']): ?>
      <ul class="dots"><?php foreach ($doc['defects'] as $d): ?><li><?= h($d) ?></li><?php endforeach; ?></ul>
      <p class="doc-note">Претензии по этим дефектам к вам не предъявляются.</p>
    <?php else: ?><p>Не выявлены.</p><?php endif; ?>
  </div>
</details>
<?php endif; ?>

<?php if ($doc['rules']): ?>
<section class="rules" id="doc-rules">
  <h3 class="rules__title">Приложение № 2. Правила безопасности фермы</h3>
  <?php foreach ($doc['rules'] as $r): ?>
    <div class="rules__item">
      <h4><?= h($r['title']) ?></h4>
      <p><?= h($r['text']) ?></p>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($doc['consent']): ?>
<details class="doc-more" id="doc-consent">
  <summary><?= h($doc['consent']['title']) ?></summary>
  <div class="doc-more__body">
    <?php foreach ($doc['consent']['points'] as $pt): ?><p><?= h($pt) ?></p><?php endforeach; ?>
  </div>
</details>
<?php endif; ?>
