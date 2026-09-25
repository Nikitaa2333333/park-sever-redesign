// Демо-режим: вместо отправки на сервер показываем экран «Спасибо».
(function () {
  var d = document;
  var form = d.getElementById('checkin-form');
  if (form) form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (form.querySelector('[data-sign]').textContent.indexOf('Подписываем') === -1) return; // не прошла проверка
    var name = (form.querySelector('[name=first_name]').value || '').trim();
    setTimeout(function () {
      var main = d.querySelector('main');
      main.innerHTML = d.getElementById('demo-done').innerHTML
        .replace(/Спасибо, [^!<]+!/, name ? 'Спасибо, ' + name.replace(/[<>&"]/g, '') + '!' : 'Спасибо!');
      window.scrollTo(0, 0);
    }, 700);
  });
  d.addEventListener('click', function (e) {
    if (e.target.closest('[data-demo-pdf]')) {
      e.preventDefault();
      var row = e.target.closest('[data-demo-pdf]');
      if (!row.nextElementSibling || !row.nextElementSibling.classList.contains('demo-note')) {
        var p = d.createElement('p'); p.className = 'note demo-note';
        p.textContent = 'В демо PDF не скачивается. На сайте здесь скачается подписанный договор.';
        row.after(p);
      }
    }
    if (e.target.closest('[data-demo-reset]')) {
      try { sessionStorage.clear(); } catch (err) {}
      location.reload();
    }
  });
})();
