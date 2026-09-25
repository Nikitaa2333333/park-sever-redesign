// Экран гостя: пошаговая анкета. Без JS форма работает одной страницей —
// все шаги видны, проверку делает сервер.
(function () {
  var d = document;
  d.documentElement.classList.add('js');
  var draftKey = 'ps-checkin:' + location.pathname;

  // Параметры устройства — уходят в журнал подписи вместе с IP и User-Agent
  d.querySelectorAll('input[name=client_tz]').forEach(function (i) {
    try { i.value = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
  });
  d.querySelectorAll('input[name=client_screen]').forEach(function (i) {
    i.value = screen.width + 'x' + screen.height + '@' + (window.devicePixelRatio || 1);
  });
  d.querySelectorAll('input[name=client_opened]').forEach(function (i) { i.value = new Date().toISOString(); });

  // Кнопка «Подписать» активна только когда стоят все обязательные галочки
  function syncSign(form) {
    var btn = form.querySelector('[data-sign]');
    if (!btn) return;
    var ok = Array.prototype.every.call(form.querySelectorAll('[data-accept]'), function (c) {
      return !c.required || c.checked;
    });
    btn.disabled = !ok;
  }
  d.querySelectorAll('form').forEach(function (form) {
    syncSign(form);
    form.addEventListener('change', function (e) { if (e.target.matches('[data-accept]')) syncSign(form); });
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      var btn = form.querySelector('[data-sign]');
      if (btn) { btn.disabled = true; btn.textContent = 'Подписываем…'; }
      try { sessionStorage.removeItem(draftKey); } catch (e) {}
    });
  });

  var form = d.getElementById('checkin-form');
  if (!form) return;

  var steps = Array.prototype.slice.call(form.querySelectorAll('.g-step'));
  var nav = form.querySelector('[data-nav]');
  var prev = form.querySelector('[data-prev]');
  var next = form.querySelector('[data-next]');
  var links = form.querySelectorAll('[data-step-link]');
  var current = 1;
  nav.hidden = false;
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
  syncGuest2();

  // ── маски ──
  var code = form.querySelector('[name=passport_code]');
  code.addEventListener('input', function () {
    var v = code.value.replace(/\D/g, '').slice(0, 6);
    code.value = v.length > 3 ? v.slice(0, 3) + '-' + v.slice(3) : v;
  });
  ['passport_number'].forEach(function (n) {
    var i = form.querySelector('[name=' + n + ']');
    i.addEventListener('input', function () { i.value = i.value.replace(/\D/g, '').slice(0, 6); });
  });
  var ser = form.querySelector('[name=passport_series]');
  ser.addEventListener('input', function () {
    var v = ser.value.replace(/\D/g, '').slice(0, 4);
    ser.value = v.length > 2 ? v.slice(0, 2) + ' ' + v.slice(2) : v;
  });
  var plate = form.querySelector('[name=car_plate]');
  plate.addEventListener('input', function () { plate.value = plate.value.toUpperCase().replace(/\s/g, ''); });

  // ── проверка шага ──
  function showErr(input, msg) {
    var f = input.closest('.fld');
    if (!f) return;
    f.classList.toggle('fld--err', !!msg);
    var p = f.querySelector('.fld__err');
    if (p) p.textContent = msg || '';
  }
  function validateStep(n) {
    var first = null;
    steps[n - 1].querySelectorAll('input, textarea').forEach(function (i) {
      if (i.type === 'hidden' || i.closest('[hidden]')) return;
      var msg = '';
      if (!i.checkValidity()) {
        msg = i.validity.valueMissing ? (i.type === 'checkbox' ? 'Нужна отметка' : 'Заполните поле')
          : i.validity.patternMismatch ? (i.placeholder ? 'Формат: ' + i.placeholder : 'Проверьте формат')
          : i.validity.rangeOverflow ? 'Проверьте дату'
          : i.validity.typeMismatch ? 'Проверьте формат' : i.validationMessage;
      }
      if (i.type !== 'checkbox') showErr(i, msg);
      if (msg && !first) first = i;
    });
    if (first) { first.focus(); first.scrollIntoView({ block: 'center' }); }
    return !first;
  }
  form.addEventListener('input', function (e) {
    var f = e.target.closest('.fld--err');
    if (f && e.target.checkValidity()) showErr(e.target, '');
    saveDraft();
  });

  // ── шаги ──
  function go(n, focusTop) {
    current = n;
    steps.forEach(function (s, i) { s.hidden = i !== n - 1; });
    links.forEach(function (l) {
      var k = +l.getAttribute('data-step-link');
      l.classList.toggle('is-active', k === n);
      l.classList.toggle('is-done', k < n);
    });
    prev.hidden = n === 1;
    next.hidden = n === steps.length;
    if (n === steps.length) buildReview();
    if (focusTop !== false) window.scrollTo(0, form.getBoundingClientRect().top + window.pageYOffset - 8);
  }
  next.addEventListener('click', function () { if (validateStep(current)) go(current + 1); });
  prev.addEventListener('click', function () { go(current - 1); });
  links.forEach(function (l) {
    l.addEventListener('click', function () {
      var k = +l.getAttribute('data-step-link');
      if (k < current) return go(k);
      for (var s = current; s < k; s++) { if (!validateStep(s)) return go(s, false); }
      go(k);
    });
  });
  form.querySelectorAll('[data-goto]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      go(+a.getAttribute('data-goto'), false);
      var t = d.querySelector(a.getAttribute('href'));
      if (t) { if (t.tagName === 'DETAILS') t.open = true; t.scrollIntoView({ block: 'start' }); }
    });
  });
  form.addEventListener('submit', function (e) {
    for (var s = 1; s <= steps.length; s++) {
      if (!validateStep(s)) { e.preventDefault(); go(s, false); validateStep(s); return; }
    }
  }, true);

  // ── сводка перед подписью ──
  function val(n) { var i = form.querySelector('[name=' + n + ']'); return i ? i.value.trim() : ''; }
  function dmy(s) { return s ? s.split('-').reverse().join('.') : ''; }
  function buildReview() {
    var rows = [
      ['Арендатор', [val('last_name'), val('first_name'), val('middle_name')].join(' ').trim()],
      ['Дата рождения', dmy(val('birth_date'))],
      ['Паспорт', val('passport_series') + ' ' + val('passport_number') + ', выдан ' + dmy(val('passport_date')) + ', код ' + val('passport_code')],
      ['Кем выдан', val('passport_issuer')],
      ['Адрес регистрации', val('reg_address')],
      ['Телефон, email', val('phone') + ', ' + val('email')],
      ['Автомобиль', (val('car_brand') + ' ' + val('car_plate')).trim() || '—']
    ];
    if (g2box.checked) {
      rows.push(['Второй гость', [val('g2_last_name'), val('g2_first_name'), val('g2_middle_name')].join(' ').trim() + ', ' + dmy(val('g2_birth_date')) + ', ' + val('g2_doc')]);
    }
    var box = form.querySelector('[data-review]');
    box.innerHTML = '';
    var h = d.createElement('h3'); h.className = 'g-h3'; h.textContent = 'Проверьте данные'; box.appendChild(h);
    var dl = d.createElement('dl');
    rows.forEach(function (r) {
      var w = d.createElement('div');
      var dt = d.createElement('dt'); dt.textContent = r[0];
      var dd = d.createElement('dd'); dd.textContent = r[1];
      w.appendChild(dt); w.appendChild(dd); dl.appendChild(w);
    });
    box.appendChild(dl);
    var edit = d.createElement('button');
    edit.type = 'button'; edit.className = 'linkish'; edit.textContent = 'Исправить';
    edit.addEventListener('click', function () { go(1); });
    box.appendChild(edit);
    box.hidden = false;
  }

  // ── черновик: только в этой вкладке (sessionStorage), стирается после подписи ──
  function saveDraft() {
    var data = {};
    form.querySelectorAll('input[type=text], input[type=tel], input[type=email], input[type=date], textarea').forEach(function (i) {
      if (i.name && i.name.indexOf('client_') !== 0) data[i.name] = i.value;
    });
    data.has_guest2 = g2box.checked;
    try { sessionStorage.setItem(draftKey, JSON.stringify(data)); } catch (e) {}
  }
  (function restoreDraft() {
    var hasServerValues = !!form.querySelector('[name=last_name]').value;
    if (hasServerValues) return;
    var raw = null;
    try { raw = sessionStorage.getItem(draftKey); } catch (e) {}
    if (!raw) return;
    try {
      var data = JSON.parse(raw);
      Object.keys(data).forEach(function (k) {
        var i = form.querySelector('[name=' + k + ']');
        if (!i) return;
        if (i.type === 'checkbox') i.checked = !!data[k]; else i.value = data[k];
      });
      syncGuest2();
    } catch (e) {}
  })();

  var errStep = +form.getAttribute('data-first-error-step');
  go(errStep || 1, !!errStep);
  if (errStep) {
    var bad = steps[errStep - 1].querySelector('.fld--err input, .check--err input');
    if (bad) bad.focus();
  }
})();
