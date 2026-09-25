// Кабинет: копирование ссылки, отметка «отправлено», подтверждения.
(function () {
  var d = document;
  var csrf = d.querySelector('input[name=_csrf]');
  var markUrl = location.pathname.replace(/\/$/, '') + '/mark-sent';

  function markSent(channel) {
    if (!csrf || !/\/admin\/stay\/\d+$/.test(location.pathname.replace(/\/$/, ''))) return;
    var body = new URLSearchParams({ _csrf: csrf.value, channel: channel });
    fetch(markUrl, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true }).catch(function () {});
  }
  function copy(text, btn) {
    var done = function () { var t = btn.textContent; btn.textContent = 'Скопировано'; setTimeout(function () { btn.textContent = t; }, 1600); };
    if (navigator.clipboard) navigator.clipboard.writeText(text).then(done, function () { prompt('Скопируйте:', text); });
    else prompt('Скопируйте:', text);
  }
  d.addEventListener('click', function (e) {
    var el = e.target.closest('[data-copy], [data-copy-text], [data-mark-sent], [data-confirm]');
    if (!el) return;
    if (el.hasAttribute('data-confirm') && !confirm(el.getAttribute('data-confirm'))) { e.preventDefault(); return; }
    if (el.hasAttribute('data-copy')) copy(d.querySelector(el.getAttribute('data-copy')).value, el);
    if (el.hasAttribute('data-copy-text')) copy(el.getAttribute('data-copy-text'), el);
    if (el.hasAttribute('data-mark-sent')) markSent(el.getAttribute('data-mark-sent'));
  });
})();
