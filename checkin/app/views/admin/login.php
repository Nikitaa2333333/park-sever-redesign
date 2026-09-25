<div class="a-login">
  <h1 class="a-h1">Парк Север</h1>
  <form class="a-card a-form" method="post">
    <h2>Вход в кабинет</h2>
    <?php if ($error): ?><p class="a-flash"><?= h($error) ?></p><?php endif; ?>
    <?= field('login', 'Логин', [], [], ['required' => true, 'autocomplete' => 'username']) ?>
    <?= field('password', 'Пароль', [], [], ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
    <button class="btn btn--navy btn--wide">Войти</button>
  </form>
</div>
