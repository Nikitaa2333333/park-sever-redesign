<h1 class="a-h1">Журнал событий</h1>
<div class="a-card">
  <?php if ($verify['ok']): ?>
    <p><span class="badge badge--signed">Цепочка цела</span> Проверено записей: <?= (int)$verify['count'] ?>. Ни одна запись не изменена и не удалена задним числом.</p>
  <?php else: ?>
    <p><span class="badge badge--warn">Цепочка нарушена</span> на записи #<?= (int)$verify['broken_at'] ?> — журнал изменяли в обход системы.</p>
  <?php endif; ?>
  <p class="a-small">Каждая запись содержит SHA-256 предыдущей. Последний хэш при подписании уходит в Telegram — внешнее подтверждение, которое нельзя переписать на сервере.</p>
</div>
<table class="a-table">
  <thead><tr><th>#</th><th>Время (МСК)</th><th>Кто</th><th>Событие</th><th>Заезд</th><th>IP</th><th>Хэш</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= h(fmt_utc_as_msk($r['at_utc'])) ?></td>
      <td><?= h($r['actor']) ?></td>
      <td><?= h($r['event']) ?></td>
      <td><?= $r['stay_id'] ? '<a href="' . h(url('admin/stay/' . $r['stay_id'])) . '">#' . (int)$r['stay_id'] . '</a>' : '—' ?></td>
      <td class="a-mono"><?= h($r['ip']) ?></td>
      <td class="a-mono"><?= h(substr($r['hash'], 0, 16)) ?>…</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
