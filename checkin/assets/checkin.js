// Экран гостя. Без JS форма работает одной страницей — все шаги и документы видны,
// проверку делает сервер.
(function () {
  var d = document;
  d.documentElement.classList.add('js');

  // Параметры устройства — уходят в журнал подписи вместе с IP и User-Agent
  d.querySelectorAll('input[name=client_tz]').forEach(function (i) {
    try { i.value = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
  });
  d.querySelectorAll('input[name=client_screen]').forEach(function (i) {
    i.value = screen.width + 'x' + screen.height + '@' + (window.devicePixelRatio || 1);
  });
  d.querySelectorAll('input[name=client_opened]').forEach(function (i) { i.value = new Date().toISOString(); });

  // ── шторки с документами ──
  var lastFocus = null;
  function openSheet(id) {
    var s = d.getElementById(id);
    if (!s) return;
    lastFocus = d.activeElement;
    s.classList.add('is-open');
    d.body.classList.add('sheet-lock');
    s.querySelector('.sheet__body').scrollTop = 0;
    s.querySelector('.sheet__close').focus();
  }
  function closeSheets() {
    var open = d.querySelectorAll('.sheet.is-open');
    if (!open.length) return;
    open.forEach(function (s) { s.classList.remove('is-open'); });
    d.body.classList.remove('sheet-lock');
    if (lastFocus) lastFocus.focus();
  }
  d.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-sheet]');
    if (opener) { e.preventDefault(); openSheet(opener.getAttribute('data-sheet')); return; }
    if (e.target.closest('[data-sheet-close]') || e.target.classList.contains('sheet')) closeSheets();
  });
  d.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSheets(); });

  // ── кнопка «Подписать» активна только при всех обязательных галочках ──
  function syncSign(form) {
    var btn = form.querySelector('[data-sign]');
    if (!btn) return;
    btn.disabled = !Array.prototype.every.call(form.querySelectorAll('[data-accept]'), function (c) {
      return !c.required || c.checked;
    });
  }
  d.querySelectorAll('form').forEach(function (form) {
    syncSign(form);
    form.addEventListener('change', function (e) { if (e.target.matches('[data-accept]')) syncSign(form); });
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      var btn = form.querySelector('[data-sign]');
      if (btn) { btn.disabled = true; btn.lastChild.textContent = 'Подписываем…'; }
    });
  });

  var form = d.getElementById('checkin-form');
  if (!form) return;

  var steps = Array.prototype.slice.call(form.querySelectorAll('.g-step'));
  var bars = form.querySelectorAll('.progress__bars i');
  var label = form.querySelector('[data-progress-label]');
  var nav = form.querySelector('[data-nav]');
  var prev = form.querySelector('[data-prev]');
  var next = form.querySelector('[data-next]');
  var current = 1;
  form.setAttribute('novalidate', '');

  // ── второй гость ──
  var g2box = form.querySelector('[data-toggle=guest2]');
  var g2 = d.getElementById('guest2');
  function syncGuest2() {
    var on = g2box.checked;
    g2.hidden = !on;
    g2.querySelectorAll('[data-req]').forEach(function (i) { i.required = on; });
    var acc = form.querySelector('[data-guest2-only]');
    acc.hidden = !on;
    acc.querySelector('input').required = on;
    syncSign(form);
  }
  g2box.addEventListener('change', syncGuest2);

  // ── маски ──
  function mask(name, fn) {
    var i = form.querySelector('[name=' + name + ']');
    i.addEventListener('input', function () { i.value = fn(i.value); });
  }
  mask('passport_code', function (v) { v = v.replace(/\D/g, '').slice(0, 6); return v.length > 3 ? v.slice(0, 3) + '-' + v.slice(3) : v; });
  mask('passport_series', function (v) { v = v.replace(/\D/g, '').slice(0, 4); return v.length > 2 ? v.slice(0, 2) + ' ' + v.slice(2) : v; });
  mask('passport_number', function (v) { return v.replace(/\D/g, '').slice(0, 6); });
  mask('car_plate', function (v) { return v.toUpperCase().replace(/\s/g, ''); });

  // ── проверка шага ──
  // Пустое обязательное поле — только подсветка, без подписи «Заполните поле».
  // Текст показываем лишь когда поле заполнено, но неверно (формат, дата).
  function showErr(input, bad, msg) {
    var f = input.closest('.fld') || input.closest('.agree');
    if (!f) return;
    f.classList.toggle(f.classList.contains('agree') ? 'agree--err' : 'fld--err', !!bad);
    var p = f.querySelector('.fld__err');
    if (p) p.textContent = bad ? (msg || '') : '';
  }
  function problem(i) {
    if (i.checkValidity()) return null;
    if (i.validity.valueMissing) return '';
    if (i.validity.patternMismatch) return i.placeholder ? 'Формат: ' + i.placeholder : 'Проверьте формат';
    if (i.validity.rangeOverflow) return i.name === 'birth_date' ? 'Арендатору должно быть 18 лет' : 'Проверьте дату';
    if (i.validity.typeMismatch) return i.type === 'email' ? 'Проверьте email' : 'Проверьте формат';
    return i.validationMessage;
  }
  function fields(n) {
    return Array.prototype.filter.call(steps[n - 1].querySelectorAll('input, textarea'), function (i) {
      return i.type !== 'hidden' && !i.closest('[hidden]') && i.name !== 'has_guest2';
    });
  }
  function validateStep(n, silent) {
    var first = null;
    fields(n).forEach(function (i) {
      var pr = problem(i);
      if (!silent) showErr(i, pr !== null, pr);
      if (pr !== null && !first) first = i;
    });
    if (first && !silent) {
      (first.closest('.agree') || first).scrollIntoView({ block: 'center' });
      if (first.type !== 'checkbox') first.focus({ preventScroll: true });
    }
    return !first;
  }
  // «Продолжить» активна, только когда все поля шага заполнены верно
  function syncNext() {
    next.setAttribute('aria-disabled', validateStep(current, true) ? 'false' : 'true');
  }
  form.addEventListener('input', function (e) {
    if (e.target.closest('.fld--err') && e.target.checkValidity()) showErr(e.target, false);
    syncNext();
    markDirty(e.target);
  });
  form.addEventListener('change', function (e) {
    if (e.target.type === 'checkbox' && e.target.checked) showErr(e.target, false);
    syncNext();
    markDirty(e.target);
  });
  // неверный формат подсказываем, когда человек ушёл с поля, а не на каждой букве
  form.addEventListener('blur', function (e) {
    var i = e.target;
    if (!i.matches || !i.matches('input, textarea') || i.type === 'checkbox' || !i.value) return;
    var pr = problem(i);
    showErr(i, pr !== null && pr !== '', pr);
  }, true);

  // ── шаги ──
  function go(n, scroll) {
    current = n;
    steps.forEach(function (s, i) { s.hidden = i !== n - 1; });
    bars.forEach(function (b, i) { b.classList.toggle('is-on', i < n); });
    label.textContent = 'Шаг ' + n + ' из ' + steps.length + ' · ' + steps[n - 1].getAttribute('data-title');
    prev.hidden = n === 1;
    nav.hidden = n === steps.length; // на последнем шаге — своя кнопка «Подписать»
    syncNext();
    if (n === steps.length) buildReview();
    if (scroll !== false) {
      var top = form.getBoundingClientRect().top + window.pageYOffset;
      if (window.pageYOffset > top || n > 1) window.scrollTo(0, top);
    }
  }
  next.addEventListener('click', function () { if (validateStep(current)) { saveDraft(); go(current + 1); } }); // неактивная — подсветит, что не так
  prev.addEventListener('click', function () { go(current - 1); });
  form.addEventListener('submit', function (e) {
    for (var s = 1; s <= steps.length; s++) {
      if (!validateStep(s, true)) { e.preventDefault(); go(s, false); validateStep(s); return; }
    }
  }, true);

  // ── сводка перед подписью ──
  function val(n) { var i = form.querySelector('[name=' + n + ']'); return i ? i.value.trim() : ''; }
  function dmy(s) { return s ? s.split('-').reverse().join('.') : ''; }
  function buildReview() {
    var rows = [
      ['Арендатор', [val('last_name'), val('first_name'), val('middle_name')].join(' ').trim() + ', ' + dmy(val('birth_date'))],
      ['Контакты', val('phone') + ' · ' + val('email')],
      ['Паспорт', val('passport_series') + ' ' + val('passport_number') + ', выдан ' + dmy(val('passport_date')) + ', ' + val('passport_issuer') + ', код ' + val('passport_code')],
      ['Регистрация', val('reg_address')],
      ['Автомобиль', (val('car_brand') + ' ' + val('car_plate')).trim() || 'без автомобиля']
    ];
    if (g2box.checked) {
      rows.push(['Второй гость', [val('g2_last_name'), val('g2_first_name'), val('g2_middle_name')].join(' ').trim() + ', ' + dmy(val('g2_birth_date')) + ', ' + val('g2_doc')]);
    }
    var box = form.querySelector('[data-review]');
    box.innerHTML = '';
    var h = d.createElement('h3'); h.textContent = 'Проверьте данные'; box.appendChild(h);
    var dl = d.createElement('dl');
    rows.forEach(function (r) {
      var w = d.createElement('div');
      var dt = d.createElement('dt'); dt.textContent = r[0];
      var dd = d.createElement('dd'); dd.textContent = r[1];
      w.appendChild(dt); w.appendChild(dd); dl.appendChild(w);
    });
    box.appendChild(dl);
    var edits = d.createElement('div'); edits.className = 'review__edit';
    [['Изменить личные данные', 1], ['Изменить паспорт', 2], ['Изменить поездку', 3]].forEach(function (x) {
      var b = d.createElement('button');
      b.type = 'button'; b.className = 'linkish'; b.textContent = x[0];
      b.addEventListener('click', function () { go(x[1]); });
      edits.appendChild(b);
    });
    box.appendChild(edits);
    box.hidden = false;
  }

  // ── черновик на сервере: общий для всех, кто открыл ссылку, стирается после подписи ──
  // Уходят только изменённые поля — если анкету параллельно дописывает второй гость,
  // его поля не затираются. Галочки согласий в черновик не попадают: их ставит тот, кто подписывает.
  var draftBox = form.querySelector('[data-draft]');
  var draftStatus = form.querySelector('[data-draft-status]');
  var draftUrl = location.pathname.replace(/\/$/, '') + '/draft';
  var dirty = {};
  var draftTimer = null;
  draftBox.hidden = false;

  function markDirty(i) {
    if (!i.name || i.name.indexOf('client_') === 0 || i.name === '_ft' || i.matches('[data-accept]')) return;
    dirty[i.name] = true;
    clearTimeout(draftTimer);
    draftTimer = setTimeout(saveDraft, 1500);
  }
  function setStatus(t) { draftStatus.textContent = t; }
  function saveDraft(done) {
    clearTimeout(draftTimer);
    var names = Object.keys(dirty);
    if (!names.length) { if (done) done(true); return; }
    dirty = {};
    var body = new URLSearchParams({ _dt: form.getAttribute('data-draft-token') });
    names.forEach(function (n) {
      var i = form.querySelector('[name=' + n + ']');
      if (i) body.append('d[' + n + ']', i.type === 'checkbox' ? (i.checked ? '1' : '') : i.value);
    });
    fetch(draftUrl, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true })
      .then(function (r) { return r.json().then(function (j) { return { status: r.status, j: j }; }); })
      .then(function (res) {
        if (res.j.ok) { setStatus('Черновик сохранён в ' + res.j.at); if (done) done(true); return; }
        names.forEach(function (n) { dirty[n] = true; });
        setStatus(res.status === 409 ? 'Страница устарела — обновите её, чтобы сохранить черновик.' : 'Не удалось сохранить. Проверьте интернет.');
        if (done) done(false);
      })
      .catch(function () {
        names.forEach(function (n) { dirty[n] = true; });
        setStatus('Не удалось сохранить. Проверьте интернет.');
        if (done) done(false);
      });
  }
  // ушёл из вкладки / свернул браузер — досохраняем
  d.addEventListener('visibilitychange', function () { if (d.visibilityState === 'hidden') saveDraft(); });

  form.querySelector('[data-draft-save]').addEventListener('click', function () {
    setStatus('Сохраняем…');
    saveDraft(function (ok) { if (ok && !Object.keys(dirty).length && draftStatus.textContent === 'Сохраняем…') setStatus('Черновик сохранён'); });
  });
  form.querySelector('[data-draft-share]').addEventListener('click', function () {
    saveDraft(function () {
      var url = location.href.split('#')[0];
      var text = 'Заполни, пожалуйста, свои данные для заезда в Парк Север — анкета уже начата:';
      if (navigator.share) {
        navigator.share({ title: 'Регистрация — Парк Север', text: text, url: url }).catch(function () {});
      } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text + ' ' + url).then(function () {
          setStatus('Ссылка скопирована — отправьте её второму гостю в мессенджере.');
        }, function () { prompt('Скопируйте ссылку:', url); });
      } else {
        prompt('Скопируйте ссылку:', url);
      }
    });
  });

  syncGuest2();
  var errStep = +form.getAttribute('data-first-error-step');
  if (!errStep && form.getAttribute('data-resume')) {
    // вернулись к черновику — открываем первый незаполненный шаг
    var s = 1;
    while (s < steps.length && validateStep(s, true)) s++;
    go(s, false);
  } else {
    go(errStep || 1, !!errStep);
  }
})();
