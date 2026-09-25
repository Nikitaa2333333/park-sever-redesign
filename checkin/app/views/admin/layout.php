<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> · Кабинет · Парк Север</title>
<link rel="icon" href="<?= asset('favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('checkin.css') ?>">
</head>
<body class="a">
<?php if (empty($bare)): $p = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(url('admin'))), '/'); ?>
<header class="a-top">
  <a class="g-logo" href="<?= h(url('admin')) ?>">Парк Север</a>
  <nav class="a-nav">
    <a href="<?= h(url('admin')) ?>" class="<?= $p === '' || str_starts_with($p, 'stay') ? 'is-active' : '' ?>">Журнал заездов</a>
    <a href="<?= h(url('admin/new')) ?>" class="<?= $p === 'new' ? 'is-active' : '' ?>">+ Новый заезд</a>
    <a href="<?= h(url('admin/audit')) ?>" class="<?= $p === 'audit' ? 'is-active' : '' ?>">Журнал событий</a>
  </nav>
  <form method="post" action="<?= h(url('admin/logout')) ?>"><input type="hidden" name="_csrf" value="<?= h(admin_csrf()) ?>"><button class="linkish">Выйти</button></form>
</header>
<?php endif; ?>
<main class="<?= empty($bare) ? 'a-main' : '' ?>"><?= $body ?></main>
<script src="<?= asset('admin.js') ?>" defer></script>
</body>
</html>
