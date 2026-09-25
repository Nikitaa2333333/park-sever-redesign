<?php
/** @var array $stay @var array $terms @var array $doc @var array $v @var array $errors @var string $formToken */
$e = $errors;
$step1 = ['last_name','first_name','middle_name','birth_date','passport_series','passport_number','passport_issuer','passport_date','passport_code','reg_address','phone','email','car_brand','car_plate'];
$firstErrStep = null;
foreach ($e as $k => $_) {
    $s = in_array($k, $step1, true) ? 1 : (str_starts_with($k, 'g2_') ? 2 : 4);
    $firstErrStep = $firstErrStep === null ? $s : min($firstErrStep, $s);
}
$acc = acceptance_texts(true);
?>
<section class="g-hero">
  <div class="g-wrap">
    <h1 class="g-h1"><?= $stay['resign_required'] ? 'Условия обновлены' : 'Добро пожаловать в Парк Север' ?></h1>
    <p class="g-lead"><?= $stay['resign_required']
        ? 'Администратор изменил условия проживания. Ваши данные уже заполнены — проверьте их и подпишите новую редакцию договора.'
        : 'Заполните анкету и подпишите договор найма — это займёт 3–4 минуты. На въезде ничего оформлять не придётся.' ?></p>
    <?php require __DIR__ . '/_terms.php'; ?>
    <p class="g-hero__note">Условия зафиксированы администратором. Если что-то не так — напишите нам до подписания.</p>
  </div>
</section>

<form class="g-form" method="post" action="" autocomplete="on" data-first-error-step="<?= (int)$firstErrStep ?>" id="checkin-form">
  <input type="hidden" name="_ft" value="<?= h($formToken) ?>">
  <input type="hidden" name="client_tz" value="">
  <input type="hidden" name="client_screen" value="">
  <input type="hidden" name="client_opened" value="">

  <nav class="steps" aria-label="Шаги">
    <ol>
      <li data-step-link="1"><span>1</span>Арендатор</li>
      <li data-step-link="2"><span>2</span>Второй гость</li>
      <li data-step-link="3"><span>3</span>Договор</li>
      <li data-step-link="4"><span>4</span>Подпись</li>
    </ol>
  </nav>

  <?php if (!empty($e['_form'])): ?><p class="g-alert"><?= h($e['_form']) ?></p><?php endif; ?>
  <?php if ($e && empty($e['_form'])): ?><p class="g-alert">Проверьте отмеченные поля.</p><?php endif; ?>

  <fieldset class="g-step" data-step="1">
    <legend class="g-h2">Анкета Арендатора</legend>
    <p class="g-p">Данные — как в паспорте. Они нужны для договора найма и пропуска на территорию.</p>
    <div class="grid3">
      <?= field('last_name', 'Фамилия', $v, $e, ['required' => true, 'autocomplete' => 'family-name']) ?>
      <?= field('first_name', 'Имя', $v, $e, ['required' => true, 'autocomplete' => 'given-name']) ?>
      <?= field('middle_name', 'Отчество', $v, $e, ['autocomplete' => 'additional-name']) ?>
    </div>
    <div class="grid3">
      <?= field('birth_date', 'Дата рождения', $v, $e, ['type' => 'date', 'required' => true, 'autocomplete' => 'bday', 'max' => date('Y-m-d', strtotime('-18 years'))]) ?>
    </div>

    <h3 class="g-h3">Паспорт</h3>
    <div class="grid3">
      <?= field('passport_series', 'Серия', $v, $e, ['required' => true, 'inputmode' => 'numeric', 'pattern' => '\d{2}\s?\d{2}', 'maxlength' => 5, 'placeholder' => '00 00', 'autocomplete' => 'off']) ?>
      <?= field('passport_number', 'Номер', $v, $e, ['required' => true, 'inputmode' => 'numeric', 'pattern' => '\d{6}', 'maxlength' => 6, 'placeholder' => '000000', 'autocomplete' => 'off']) ?>
      <?= field('passport_date', 'Дата выдачи', $v, $e, ['type' => 'date', 'required' => true, 'max' => date('Y-m-d')]) ?>
    </div>
    <?= field('passport_issuer', 'Кем выдан', $v, $e, ['required' => true, 'maxlength' => 200, 'autocomplete' => 'off']) ?>
    <div class="grid3">
      <?= field('passport_code', 'Код подразделения', $v, $e, ['required' => true, 'inputmode' => 'numeric', 'pattern' => '\d{3}-?\d{3}', 'maxlength' => 7, 'placeholder' => '000-000', 'autocomplete' => 'off']) ?>
    </div>
    <?= field('reg_address', 'Адрес регистрации (прописки)', $v, $e, ['required' => true, 'maxlength' => 300, 'autocomplete' => 'street-address']) ?>

    <h3 class="g-h3">Контакты</h3>
    <div class="grid2">
      <?= field('phone', 'Телефон', $v, $e, ['type' => 'tel', 'required' => true, 'autocomplete' => 'tel', 'placeholder' => '+7 900 000-00-00']) ?>
      <?= field('email', 'Email', $v, $e, ['type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
    </div>

    <h3 class="g-h3">Автомобиль</h3>
    <p class="g-p g-p--sm">Для пропуска на территорию. Если приедете на такси — оставьте пустым.</p>
    <div class="grid2">
      <?= field('car_brand', 'Марка', $v, $e, ['maxlength' => 60, 'placeholder' => 'Volkswagen']) ?>
      <?= field('car_plate', 'Госномер', $v, $e, ['maxlength' => 12, 'placeholder' => 'М246ХУ799', 'autocapitalize' => 'characters']) ?>
    </div>
  </fieldset>

  <fieldset class="g-step" data-step="2">
    <legend class="g-h2">Второй гость</legend>
    <label class="check">
      <input type="checkbox" name="has_guest2" value="1" <?= !empty($v['has_guest2']) ? 'checked' : '' ?> data-toggle="guest2">
      <span>Со мной будет второй гость</span>
    </label>
    <div class="guest2" id="guest2" <?= empty($v['has_guest2']) ? 'data-hidden' : '' ?>>
      <div class="grid3">
        <?= field('g2_last_name', 'Фамилия', $v, $e, ['data-req' => '1']) ?>
        <?= field('g2_first_name', 'Имя', $v, $e, ['data-req' => '1']) ?>
        <?= field('g2_middle_name', 'Отчество', $v, $e) ?>
      </div>
      <div class="grid2">
        <?= field('g2_birth_date', 'Дата рождения', $v, $e, ['type' => 'date', 'data-req' => '1', 'max' => date('Y-m-d')]) ?>
        <?= field('g2_doc', 'Серия и номер паспорта', $v, $e, ['data-req' => '1', 'maxlength' => 40, 'autocomplete' => 'off'], 'Для ребёнка — серия и номер свидетельства о рождении') ?>
      </div>
    </div>
    <p class="g-p g-p--sm">Если едете один — просто нажмите «Далее».</p>
  </fieldset>

  <fieldset class="g-step" data-step="3">
    <legend class="g-h2">Договор найма</legend>
    <p class="g-p">Прочитайте договор и приложения. Опись и согласие раскрываются по нажатию.</p>
    <?php require __DIR__ . '/_doc.php'; ?>
  </fieldset>

  <fieldset class="g-step" data-step="4">
    <legend class="g-h2">Проверка и подпись</legend>
    <div class="review" data-review hidden></div>

    <div class="accept">
      <?php foreach (['agree_contract', 'agree_rules', 'agree_pd'] as $k): ?>
        <label class="check<?= isset($e[$k]) ? ' check--err' : '' ?>">
          <input type="checkbox" name="<?= $k ?>" value="1" required data-accept <?= !empty($v[$k]) ? 'checked' : '' ?>>
          <span><?php
            echo match ($k) {
              'agree_contract' => 'Ознакомлен и безоговорочно принимаю условия <a href="#doc-contract" data-goto="3">Договора найма жилого дома</a> и <a href="#doc-inventory" data-goto="3">Опись имущества (Приложение № 1)</a>.',
              'agree_rules' => 'Ознакомлен с <a href="#doc-rules" data-goto="3">правилами безопасности</a>: подтверждаю запрет на заход собак в дом и запрет на вход на пастбища к оленям.',
              'agree_pd' => 'Даю <a href="#doc-consent" data-goto="3">согласие Оператору (' . h(LANDLORD['short']) . ') на обработку персональных данных</a> по 152-ФЗ в целях заключения и исполнения договора найма.',
            };
          ?></span>
        </label>
      <?php endforeach; ?>
      <label class="check<?= isset($e['agree_guest2']) ? ' check--err' : '' ?>" data-guest2-only <?= empty($v['has_guest2']) ? 'hidden' : '' ?>>
        <input type="checkbox" name="agree_guest2" value="1" data-accept <?= !empty($v['agree_guest2']) ? 'checked' : '' ?>>
        <span><?= h($acc['agree_guest2']) ?></span>
      </label>
    </div>

    <button class="btn btn--navy btn--wide" type="submit" data-sign>Подписать договор найма и подтвердить регистрацию</button>
    <p class="g-p g-p--sm sign-note">Нажимая кнопку, вы подписываете договор простой электронной подписью (ст. 434, 438 ГК РФ, 63-ФЗ). Мы сохраним дату и время, IP-адрес и данные устройства, а вы получите PDF-копию договора.</p>
  </fieldset>

  <div class="g-nav" data-nav hidden>
    <button class="btn btn--ghost" type="button" data-prev>Назад</button>
    <button class="btn btn--navy" type="button" data-next>Далее</button>
  </div>
</form>
