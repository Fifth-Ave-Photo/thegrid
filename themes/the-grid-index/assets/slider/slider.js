/* The Grid Index — Featured slider runtime (v1.10.15) */
(function () {
  function init(root) {
    var slides   = Array.prototype.slice.call(root.querySelectorAll('.gi-slider__slide'));
    if (slides.length < 2) return;
    var track    = root.querySelector('.gi-slider__track');
    var dots     = Array.prototype.slice.call(root.querySelectorAll('.gi-slider__dots button'));
    var prev     = root.querySelector('.gi-slider__btn--prev');
    var next     = root.querySelector('.gi-slider__btn--next');
    var fade     = root.getAttribute('data-transition') === 'fade';
    var auto     = root.getAttribute('data-autoplay') === '1';
    var interval = parseInt(root.getAttribute('data-interval'), 10) || 5000;
    var i        = 0;
    var timer    = null;
    var paused   = false;

    function go(n) {
      i = (n + slides.length) % slides.length;
      slides.forEach(function (s, idx) { s.classList.toggle('is-active', idx === i); });
      dots.forEach(function (d, idx) { d.setAttribute('aria-selected', idx === i ? 'true' : 'false'); });
      if (!fade && track) {
        track.style.transform = 'translateX(-' + (i * 100) + '%)';
      }
    }
    function start() {
      if (!auto || paused) return;
      stop();
      timer = setInterval(function () { go(i + 1); }, interval);
    }
    function stop() {
      if (timer) { clearInterval(timer); timer = null; }
    }

    if (prev) prev.addEventListener('click', function () { go(i - 1); start(); });
    if (next) next.addEventListener('click', function () { go(i + 1); start(); });
    dots.forEach(function (d, idx) {
      d.addEventListener('click', function () { go(idx); start(); });
    });

    root.addEventListener('mouseenter', function () { paused = true;  stop();  });
    root.addEventListener('mouseleave', function () { paused = false; start(); });
    root.addEventListener('focusin',    function () { paused = true;  stop();  });
    root.addEventListener('focusout',   function () { paused = false; start(); });

    // Pause when tab hidden (saves battery, avoids the "jumped 6 slides" effect)
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) stop(); else start();
    });

    // Touch swipe
    var startX = null;
    root.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    root.addEventListener('touchend',   function (e) {
      if (startX === null) return;
      var dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 40) { go(i + (dx < 0 ? 1 : -1)); start(); }
      startX = null;
    });

    // Keyboard arrows when slider is focused
    root.tabIndex = root.tabIndex || 0;
    root.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft')  { go(i - 1); start(); }
      if (e.key === 'ArrowRight') { go(i + 1); start(); }
    });

    go(0);
    start();
  }

  function boot() {
    document.querySelectorAll('.gi-slider').forEach(init);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
