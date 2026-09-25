<?php /** @var array $stays */ $tabs = ['active' => 'Актуальные'] + STATUS_LABELS + ['all' => 'Все']; ?>
<h1 class="a-h1">Журнал заездов</h1>
<?php if ($flash): ?><p class="a-flash"><?= h($flash) ?></p><?php endif; ?>
<div class="a-bar">
  <nav class="a-tabs">
    <?php foreach ($tabs as $k => $label): ?>
      <a href="?status=<?= h($k) ?>" class="<?= $filter === $k ? 'is-active' : '' ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <form class="a-search" method="get">
    <input type="hidden" name="status" value="<?= h($filter) ?>">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="Гость или телефон">
    <button class="btn btn--ghost btn--sm">Найти</button>
  </form>
</div>
<?php if (!$stays): ?>
  <div class="a-card"><p>Заездов нет. <a href="<?= h(url('admin/new')) ?>">Создать первый</a></p></div>
<?php else: ?>
<table class="a-table">
  <thead><tr><th>№</th><th>Дом</th><th>Заезд → выезд</th><th>Гость</th><th>Сумма</th><th>Статус</th></tr></thead>
  <tbody>
  <?php foreach ($stays as $s): ?>
    <tr class="<?= in_array($s['status'], ['checked_out', 'cancelled'], true) ? 'muted-row' : '' ?>">
      <td><a href="<?= h(url('admin/stay/' . $s['id'])) ?>"><?= h(contract_number($s)) ?></a></td>
      <td><?= h(house($s['house_id'])['short']) ?></td>
      <td><?= h(fmt_dt($s['checkin_at'])) ?> → <?= h(fmt_dt($s['checkout_at'])) ?></td>
      <td><?= h($s['tenant_short'] ?: $s['guest_label'] ?: '—') ?><?php if ($s['guest_phone']): ?><br><span class="a-small"><?= h($s['guest_phone']) ?></span><?php endif; ?></td>
      <td><?= h(fmt_rub((int)$s['price'])) ?></td>
      <td>
        <span class="badge badge--<?= h($s['status']) ?>"><?= h(STATUS_LABELS[$s['status']]) ?></span>
        <?php if ($s['resign_required']): ?><br><span class="badge badge--warn">Нужна переподпись</span><?php endif; ?>
        <?php if ($s['status'] === 'sent' && $s['opened_at']): ?><p class="a-small">Гость открыл ссылку</p><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
