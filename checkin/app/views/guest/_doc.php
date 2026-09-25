<?php
/** @var array $doc */
// Документы открываются «шторкой» поверх экрана. Без JS — показываются обычным текстом.
$sheet = function (string $id, string $title, string $html) {
    return '<section class="sheet" id="' . $id . '" role="dialog" aria-modal="true" aria-label="' . h($title) . '">'
        . '<div class="sheet__panel"><header class="sheet__head"><h3>' . h($title) . '</h3>'
        . '<button type="button" class="sheet__close" data-sheet-close aria-label="Закрыть">×</button></header>'
        . '<div class="sheet__body">' . $html . '</div>'
        . '<footer class="sheet__foot"><button type="button" class="btn btn--navy btn--wide" data-sheet-close>Прочитал(а)</button></footer></div></section>';
};

ob_start(); ?>
  <p class="doc-meta">№ <?= h($doc['number']) ?> · редакция <?= h($doc['version']) ?></p>
  <?php foreach ($doc['contract'] as $s): ?>
    <h4><?= h($s['title']) ?></h4>
    <?php foreach ($s['points'] as $pt): ?><p><?= h($pt) ?></p><?php endforeach; ?>
  <?php endforeach; ?>
<?php echo $sheet('sheet-contract', $doc['title'], (string)ob_get_clean());

if ($doc['inventory']) {
    ob_start(); ?>
    <p class="doc-meta">Приложение № 1 к договору № <?= h($doc['number']) ?></p>
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
      <p><b>Претензии по этим дефектам к вам не предъявляются.</b></p>
    <?php else: ?><p>Не выявлены.</p><?php endif;
    echo $sheet('sheet-inventory', 'Опись имущества дома', (string)ob_get_clean());
}

if ($doc['consent']) {
    ob_start();
    foreach ($doc['consent']['points'] as $pt) echo '<p>' . h($pt) . '</p>';
    echo $sheet('sheet-consent', $doc['consent']['title'], (string)ob_get_clean());
}
