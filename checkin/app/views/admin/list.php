<?php /** @var array $stays @var array $f */
$statusOpts = ['active' => 'Актуальные'] + STATUS_LABELS + ['all' => 'Все'];
$filtered = $f['status'] !== 'active' || $f['house'] !== '' || $f['from'] || $f['to'] || $f['q'] !== '';
?>
<h1 class="a-h1">Журнал заездов</h1>
<?php if ($flash): ?><p class="a-flash"><?= h($flash) ?></p><?php endif; ?>
<form class="a-filters" method="get">
  <label>Статус
    <select name="status">
      <?php foreach ($statusOpts as $k => $label): ?><option value="<?= h($k) ?>"<?= $f['status'] === $k ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Дом
    <select name="house">
      <option value="">Все дома</option>
      <?php foreach (houses() as $id => $hs): ?><option value="<?= h($id) ?>"<?= $f['house'] === $id ? ' selected' : '' ?>><?= h($hs['short']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Заезд с<input type="date" name="from" value="<?= h($f['from']) ?>"></label>
  <label>по<input type="date" name="to" value="<?= h($f['to']) ?>"></label>
  <label class="a-filters__q">Поиск<input type="search" name="q" value="<?= h($f['q']) ?>" placeholder="Гость или телефон"></label>
  <div class="a-filters__btns">
    <button class="btn btn--navy btn--sm">Показать</button>
    <?php if ($filtered): ?><a class="linkish" href="<?= h(url('admin')) ?>">Сбросить</a><?php endif; ?>
  </div>
</form>
<?php if (!$stays): ?>
  <div class="a-card"><p><?= $filtered ? 'По этим фильтрам заездов нет.' : 'Актуальных заездов нет.' ?> <a href="<?= h(url('admin/new')) ?>">Создать заезд</a></p></div>
<?php else: ?>
<table class="a-table">
  <thead><tr><th>№</th><th>Дом</th><th>Заезд → выезд</th><th>Гость</th><th>Сумма</th><th>Статус</th></tr></thead>
  <tbody>
  <?php foreach ($stays as $s): ?>
    <tr class="<?= $s['status'] === 'cancelled' || $s['checkout_at'] < date('Y-m-d H:i') ? 'muted-row' : '' ?>">
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
