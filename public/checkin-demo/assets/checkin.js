// Экран гостя. Без JS форма работает одной страницей — все шаги и документы видны,
// проверку делает сервер.
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
      try { sessionStorage.removeItem(draftKey); } catch (err) {}
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
  function mask(name, fn) {
    var i = form.querySelector('[name=' + name + ']');
    i.addEventListener('input', function () { i.value = fn(i.value); });
  }
  mask('passport_code', function (v) { v = v.replace(/\D/g, '').slice(0, 6); return v.length > 3 ? v.slice(0, 3) + '-' + v.slice(3) : v; });
  mask('passport_series', function (v) { v = v.replace(/\D/g, '').slice(0, 4); return v.length > 2 ? v.slice(0, 2) + ' ' + v.slice(2) : v; });
  mask('passport_number', function (v) { return v.replace(/\D/g, '').slice(0, 6); });
  mask('car_plate', function (v) { return v.toUpperCase().replace(/\s/g, ''); });

  // ── проверка шага ──
  function showErr(input, msg) {
    var f = input.closest('.fld') || input.closest('.agree');
    if (!f) return;
    f.classList.toggle(f.classList.contains('agree') ? 'agree--err' : 'fld--err', !!msg);
    var p = f.querySelector('.fld__err');
    if (p) p.textContent = msg || '';
  }
  function validateStep(n, silent) {
    var first = null;
    steps[n - 1].querySelectorAll('input, textarea').forEach(function (i) {
      if (i.type === 'hidden' || i.closest('[hidden]') || i.name === 'has_guest2') return;
      var msg = '';
      if (!i.checkValidity()) {
        msg = i.validity.valueMissing ? (i.type === 'checkbox' ? 'Нужна отметка' : 'Заполните поле')
          : i.validity.patternMismatch ? (i.placeholder ? 'Формат: ' + i.placeholder : 'Проверьте формат')
          : i.validity.rangeOverflow ? 'Проверьте дату'
          : i.validity.typeMismatch ? 'Проверьте формат' : i.validationMessage;
      }
      if (!silent) showErr(i, msg);
      if (msg && !first) first = i;
    });
    if (first && !silent) {
      (first.closest('.agree') || first).scrollIntoView({ block: 'center' });
      if (first.type !== 'checkbox') first.focus({ preventScroll: true });
    }
    return !first;
  }
  form.addEventListener('input', function (e) {
    if (e.target.closest('.fld--err') && e.target.checkValidity()) showErr(e.target, '');
    saveDraft();
  });
  form.addEventListener('change', function (e) {
    if (e.target.type === 'checkbox' && e.target.checked) showErr(e.target, '');
  });

  // ── шаги ──
  function go(n, scroll) {
    current = n;
    steps.forEach(function (s, i) { s.hidden = i !== n - 1; });
    bars.forEach(function (b, i) { b.classList.toggle('is-on', i < n); });
    label.textContent = 'Шаг ' + n + ' из ' + steps.length + ' · ' + steps[n - 1].getAttribute('data-title');
    prev.style.visibility = n === 1 ? 'hidden' : 'visible';
    next.hidden = n === steps.length;
    if (n === steps.length) buildReview();
    if (scroll !== false) {
      var top = form.getBoundingClientRect().top + window.pageYOffset;
      if (window.pageYOffset > top || n > 1) window.scrollTo(0, top);
    }
  }
  next.addEventListener('click', function () { if (validateStep(current)) go(current + 1); });
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
    if (form.querySelector('[name=last_name]').value) return; // сервер уже вернул значения
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
})();
