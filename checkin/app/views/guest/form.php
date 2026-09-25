<?php
/** @var array $stay @var array $terms @var array $doc @var array $v @var array $errors @var string $formToken */
$e = $errors;
$stepOf = [
    'last_name' => 1, 'first_name' => 1, 'middle_name' => 1, 'birth_date' => 1, 'phone' => 1, 'email' => 1,
    'passport_series' => 2, 'passport_number' => 2, 'passport_date' => 2, 'passport_code' => 2, 'passport_issuer' => 2, 'reg_address' => 2,
    'car_brand' => 3, 'car_plate' => 3,
    'agree_rules' => 4,
];
$firstErrStep = null;
foreach ($e as $k => $_) {
    $s = $stepOf[$k] ?? (str_starts_with($k, 'g2_') ? 3 : 5);
    $firstErrStep = $firstErrStep === null ? $s : min($firstErrStep, $s);
}
$acc = acceptance_texts(true);
$check = function (string $name, string $html, bool $required = true, string $extra = '') use ($v, $e) {
    return '<label class="agree' . (isset($e[$name]) ? ' agree--err' : '') . '"' . $extra . '>'
        . '<input type="checkbox" name="' . $name . '" value="1"' . ($required ? ' required' : '') . ' data-accept' . (!empty($v[$name]) ? ' checked' : '') . '>'
        . '<span class="agree__text">' . $html . '</span></label>';
};
?>
<section class="welcome">
  <h1 class="g-h1"><?= $stay['resign_required'] ? 'Условия обновлены' : 'Добро пожаловать в Парк Север' ?></h1>
  <p class="g-lead"><?= $stay['resign_required']
      ? 'Мы поменяли условия проживания. Ваши данные уже заполнены — проверьте их и подпишите новую редакцию договора.'
      : 'Пара минут — и заезд оформлен. На месте ничего заполнять не придётся: просто приезжайте отдыхать.' ?></p>
  <?php require __DIR__ . '/_terms.php'; ?>
</section>

<form class="g-form" method="post" action="" autocomplete="on" data-first-error-step="<?= (int)$firstErrStep ?>" id="checkin-form">
  <input type="hidden" name="_ft" value="<?= h($formToken) ?>">
  <input type="hidden" name="client_tz" value="">
  <input type="hidden" name="client_screen" value="">
  <input type="hidden" name="client_opened" value="">

  <div class="progress" aria-live="polite">
    <div class="progress__bars"><i></i><i></i><i></i><i></i><i></i></div>
    <div class="progress__row">
      <p class="progress__label" data-progress-label>Шаг 1 из 5</p>
      <button class="progress__back" type="button" data-prev hidden>Назад</button>
    </div>
  </div>

  <?php if (!empty($e['_form'])): ?><p class="g-alert"><?= h($e['_form']) ?></p>
  <?php elseif ($e): ?><p class="g-alert">Проверьте, пожалуйста, отмеченные поля.</p><?php endif; ?>

  <fieldset class="g-step" data-step="1" data-title="Знакомство">
    <legend class="step-head">
      <span class="g-h2">Давайте познакомимся</span>
      <span class="step-head__sub">Как в паспорте — эти данные войдут в договор.</span>
    </legend>
    <div class="grid2">
      <?= field('last_name', 'Фамилия', $v, $e, ['required' => true, 'autocomplete' => 'family-name']) ?>
      <?= field('first_name', 'Имя', $v, $e, ['required' => true, 'autocomplete' => 'given-name']) ?>
      <?= field('middle_name', 'Отчество', $v, $e, ['autocomplete' => 'additional-name']) ?>
      <?= field('birth_date', 'Дата рождения', $v, $e, ['type' => 'date', 'required' => true, 'autocomplete' => 'bday', 'max' => date('Y-m-d', strtotime('-18 years'))]) ?>
      <?= field('phone', 'Телефон', $v, $e, ['type' => 'tel', 'required' => true, 'autocomplete' => 'tel', 'placeholder' => '+7 900 000-00-00']) ?>
      <?= field('email', 'Email', $v, $e, ['type' => 'email', 'required' => true, 'autocomplete' => 'email', 'placeholder' => 'name@mail.ru'], 'Для связи по поводу заезда') ?>
    </div>
  </fieldset>

  <fieldset class="g-step" data-step="2" data-title="Паспорт">
    <legend class="step-head">
      <span class="g-h2">Паспорт</span>
      <span class="step-head__sub">Нужен для договора найма. Данные хранятся зашифрованными и видны только администратору.</span>
    </legend>
    <div class="passport">
      <p class="passport__title">Паспорт гражданина РФ</p>
      <div class="grid2">
        <?= field('passport_series', 'Серия', $v, $e, ['required' => true, 'inputmode' => 'numeric', 'pattern' => '\d{2}\s?\d{2}', 'maxlength' => 5, 'placeholder' => '00 00', 'autocomplete' => 'off']) ?>
        <?= field('passport_number', 'Номер', $v, $e, ['required' => true, 'inputmode' => 'numeric', 'pattern' => '\d{6}', 'maxlength' => 6, 'placeholder' => '000000', 'autocomplete' => 'off']) ?>
        <?= field('passport_date', 'Дата выдачи', $v, $e, ['type' => 'date', 'required' => true, 'max' => date('Y-m-d')]) ?>
        <?= field('passport_code', 'Код подразделения', $v, $e, ['required' => true, 'inputmode' => 'numeric', 'pattern' => '\d{3}-?\d{3}', 'maxlength' => 7, 'placeholder' => '000-000', 'autocomplete' => 'off']) ?>
      </div>
      <?= field('passport_issuer', 'Кем выдан', $v, $e, ['type' => 'textarea', 'required' => true, 'maxlength' => 200, 'rows' => 2, 'autocomplete' => 'off']) ?>
      <?= field('reg_address', 'Адрес регистрации', $v, $e, ['type' => 'textarea', 'required' => true, 'maxlength' => 300, 'rows' => 2, 'autocomplete' => 'street-address'], 'Со страницы «Место жительства»') ?>
    </div>
  </fieldset>

  <fieldset class="g-step" data-step="3" data-title="Поездка">
    <legend class="step-head">
      <span class="g-h2">Как приедете?</span>
      <span class="step-head__sub">Номер машины нужен для пропуска — шлагбаум откроется сам.</span>
    </legend>
    <div class="grid2">
      <?= field('car_brand', 'Марка автомобиля', $v, $e, ['maxlength' => 60, 'placeholder' => 'Например, Volkswagen']) ?>
      <?= field('car_plate', 'Госномер', $v, $e, ['maxlength' => 12, 'placeholder' => 'А123ВС777', 'autocapitalize' => 'characters']) ?>
    </div>
    <p class="note">Приедете на такси — оставьте поля пустыми.</p>

    <label class="toggle">
      <input type="checkbox" name="has_guest2" value="1" <?= !empty($v['has_guest2']) ? 'checked' : '' ?> data-toggle="guest2">
      <span class="toggle__text"><b>Со мной второй гость</b><span>Добавим его в договор — ему ничего заполнять не нужно</span></span>
      <span class="toggle__switch"></span>
    </label>
    <div class="guest2" id="guest2" <?= empty($v['has_guest2']) ? 'hidden' : '' ?>>
      <div class="grid2">
        <?= field('g2_last_name', 'Фамилия', $v, $e, ['data-req' => '1']) ?>
        <?= field('g2_first_name', 'Имя', $v, $e, ['data-req' => '1']) ?>
        <?= field('g2_middle_name', 'Отчество', $v, $e) ?>
        <?= field('g2_birth_date', 'Дата рождения', $v, $e, ['type' => 'date', 'data-req' => '1', 'max' => date('Y-m-d')]) ?>
      </div>
      <?= field('g2_doc', 'Серия и номер паспорта', $v, $e, ['data-req' => '1', 'maxlength' => 40, 'autocomplete' => 'off'], 'Для ребёнка — серия и номер свидетельства о рождении') ?>
    </div>
  </fieldset>

  <fieldset class="g-step" data-step="4" data-title="Правила фермы">
    <legend class="step-head">
      <span class="g-h2">Пара правил фермы</span>
      <span class="step-head__sub">Рядом с вами живут ретриверы и благородные олени. Чтобы всем было спокойно — вот о чём мы просим.</span>
    </legend>
    <?php require __DIR__ . '/_rules.php'; ?>
    <p class="note">Это Приложение № 2 к договору найма.</p>
    <?= $check('agree_rules', h($acc['agree_rules'])) ?>
  </fieldset>

  <fieldset class="g-step" data-step="5" data-title="Договор и подпись">
    <legend class="step-head">
      <span class="g-h2">Договор и подпись</span>
      <span class="step-head__sub">Проверьте данные, откройте документы и подпишите одной кнопкой.</span>
    </legend>

    <div class="review" data-review hidden></div>

    <div class="docs-list">
      <button type="button" class="doc-row" data-sheet="sheet-contract">
        <span class="doc-row__t"><b>Договор найма жилого дома</b><span><?= count($doc['contract']) ?> разделов · ~5 минут</span></span>
        <span class="doc-row__go">Читать</span>
      </button>
      <?php if ($doc['inventory']): ?>
      <button type="button" class="doc-row" data-sheet="sheet-inventory">
        <span class="doc-row__t"><b>Опись имущества дома</b><span>Приложение № 1<?= $doc['defects'] ? ' · есть отметки о дефектах' : '' ?></span></span>
        <span class="doc-row__go">Читать</span>
      </button>
      <?php endif; ?>
      <button type="button" class="doc-row" data-sheet="sheet-consent">
        <span class="doc-row__t"><b>Согласие на обработку данных</b><span>152-ФЗ · отдельный документ</span></span>
        <span class="doc-row__go">Читать</span>
      </button>
    </div>
    <?php require __DIR__ . '/_doc.php'; ?>

    <div class="agrees">
      <?= $check('agree_contract', 'Ознакомлен и безоговорочно принимаю условия <a href="#sheet-contract" data-sheet="sheet-contract">Договора найма жилого дома</a> и <a href="#sheet-inventory" data-sheet="sheet-inventory">Опись имущества (Приложение № 1)</a>.') ?>
      <?= $check('agree_pd', 'Даю <a href="#sheet-consent" data-sheet="sheet-consent">согласие Оператору (' . h(LANDLORD['short']) . ') на обработку персональных данных</a> по 152-ФЗ в целях заключения и исполнения договора найма.') ?>
      <?= $check('agree_guest2', h($acc['agree_guest2']), false, ' data-guest2-only' . (empty($v['has_guest2']) ? ' hidden' : '')) ?>
    </div>

    <button class="btn btn--gold btn--wide btn--sign" type="submit" data-sign>Подписать договор найма</button>
    <p class="note">Нажимая кнопку, вы подтверждаете регистрацию. Это простая электронная подпись (ст. 434, 438 ГК РФ, 63-ФЗ). Мы сохраним дату и время, IP-адрес и данные устройства, а вам — PDF-копию договора.</p>
  </fieldset>

  <div class="g-nav" data-nav hidden>
    <div class="g-nav__in"><button class="btn btn--navy btn--wide" type="button" data-next aria-disabled="true">Продолжить</button></div>
  </div>
</form>
