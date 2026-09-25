<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<meta name="theme-color" content="#ffffff">
<title><?= h($title) ?> · Парк Север</title>
<link rel="icon" href="<?= asset('favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('checkin.css') ?>">
</head>
<body class="g">
<header class="g-top"><div class="g-top__in">
  <span class="g-logo">Парк Север</span>
  <span class="g-top__note"><?= icon('lock') ?>Защищённая персональная ссылка</span>
</div></header>
<div class="g-main">
  <main><?= $body ?></main>
  <footer class="g-foot">
    <p><?= h(LANDLORD['full']) ?> · ИНН <?= h(LANDLORD['inn']) ?></p>
    <p>Данные передаются по защищённому соединению и хранятся в зашифрованном виде на серверах в России.</p>
  </footer>
</div>
<script src="<?= asset('checkin.js') ?>" defer></script>
</body>
</html>
