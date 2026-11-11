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
  // «Скрыть цены»: в демо прячем ценники и стоимость проживания прямо на странице; вернуть — тем же кодом
  var st = d.createElement('style');
  st.textContent = '.demo-noprice .ticket__foot, .demo-noprice .m-opt__p, .demo-noprice .m-grp:not(.m-grp--info) .m-grp__inc,'
    + ' .demo-noprice .m-h__inc, .demo-noprice [data-menu-line], .demo-noprice .m-grp__more, .demo-noprice .m-more{ display:none !important }';
  d.head.appendChild(st);
  var lockCode = null;
  d.querySelectorAll('.m-lock form').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var code = f.querySelector('[name=code]').value;
      var chip = f.closest('.m-lock').querySelector('summary');
      var hint = f.querySelector('p');
      var btn = f.querySelector('button');
      if (lockCode === null) {
        lockCode = code;
        d.body.classList.add('demo-noprice');
        chip.lastChild.textContent = 'Показать цены';
        hint.textContent = 'Введите код, которым скрывали цены.';
        btn.textContent = 'Показать цены';
        note('Цены скрыты. Теперь ссылку можно переслать — ценников по ней не видно. Вернуть цены — тем же кодом.');
      } else if (code === lockCode) {
        lockCode = null;
        d.body.classList.remove('demo-noprice');
        chip.lastChild.textContent = 'Скрыть цены';
        hint.textContent = 'Хотите сделать подарок? Придумайте код, скройте цены и перешлите ссылку — по ней можно будет выбрать допы, не видя ценников. Вернуть цены — тем же кодом.';
        btn.textContent = 'Скрыть';
        note('Цены снова видны.');
      } else {
        note('Код не подошёл.');
      }
      f.querySelector('[name=code]').value = '';
      f.closest('details').open = false;
    });
  });
})();
