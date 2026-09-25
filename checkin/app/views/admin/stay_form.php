<h1 class="a-h1">Новый заезд</h1>
<form class="a-card a-form a-narrow" method="post">
  <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
  <?php require __DIR__ . '/_stay_fields.php'; ?>
  <button class="btn btn--navy">Создать и получить ссылку</button>
</form>
