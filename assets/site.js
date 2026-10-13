/* Optional enhancements. The site works fully without this file. */
(function () {
  var d = document;
  function copy(text, btn) {
    var done = function () {
      btn.setAttribute('data-done', '');
      btn.textContent = 'Copied';
      setTimeout(function () { btn.removeAttribute('data-done'); btn.textContent = 'Copy'; }, 1800);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, function () {});
    }
  }
  function addButton(host, getText) {
    var b = d.createElement('button');
    b.type = 'button';
    b.className = 'copy';
    b.textContent = 'Copy';
    b.setAttribute('aria-label', 'Copy to clipboard');
    b.addEventListener('click', function () { copy(getText(), b); });
    host.appendChild(b);
  }
  d.querySelectorAll('.code').forEach(function (box) {
    var pre = box.querySelector('pre');
    if (pre) addButton(box, function () { return pre.textContent.replace(/\n$/, ''); });
  });
  d.querySelectorAll('[data-copy]').forEach(function (box) {
    addButton(box, function () { return box.getAttribute('data-copy'); });
  });
  var demo = d.querySelector('[data-yt]');
  if (demo) {
    demo.addEventListener('click', function (e) {
      e.preventDefault();
      var f = d.createElement('iframe');
      f.src = 'https://www.youtube-nocookie.com/embed/' + demo.getAttribute('data-yt') + '?autoplay=1&rel=0';
      f.title = 'Tablekit demo - Laravel Filament Plugin';
      f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      f.allowFullscreen = true;
      f.referrerPolicy = 'strict-origin-when-cross-origin';
      var box = d.createElement('div');
      box.className = 'demo playing';
      box.appendChild(f);
      demo.replaceWith(box);
      f.focus();
    });
  }
})();
