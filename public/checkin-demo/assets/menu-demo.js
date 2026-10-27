// Демо «Меню и допы»: выбор никуда не отправляется — вместо сервера показываем подсказку.
(function () {
  var d = document;
  var form = d.getElementById('menu-form');
  function note(text) {
    var p = d.querySelector('.demo-toast');
    if (!p) { p = d.createElement('p'); p.className = 'g-alert demo-toast'; d.querySelector('main').insertBefore(p, d.querySelector('main').children[1]); }
    p.textContent = text;
    window.scrollTo(0, p.getBoundingClientRect().top + window.pageYOffset - 20);
  }
  if (form) {
    // смена пакета на сайте сохраняет выбор и перерисовывает блоки — в демо только подсказка
    form.submit = function () { note('На сайте страница обновится: блоки, входящие в выбранный пакет, отметятся «0 ₽ · включено в тариф».'); };
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var b = e.submitter;
      note(b && b.value === 'pay'
        ? 'Демо: на сайте здесь появится «Ожидает оплаты» и ссылка на оплату через СБП, а администратору придёт сводка в Telegram.'
        : 'Демо: на сайте выбор подтвердится — появится «Бронь подтверждена», а администратору придёт сводка в Telegram.');
    });
  }
  d.querySelectorAll('.m-lock form').forEach(function (f) {
    f.addEventListener('submit', function (e) { e.preventDefault(); note('Демо: на сайте цены скроются кодом.'); });
  });
})();
