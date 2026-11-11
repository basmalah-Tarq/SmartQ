/* SmartQ UI â€” vanilla JS interactions (no frameworks).
   Scroll reveal, counters, live queue ticker, navbar shrink, menus,
   button ripple, page fade transitions, toasts, confetti. */
(function () {
  'use strict';

  var doc = document;
  var root = doc.documentElement;
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  root.classList.add('sq-js');

  function T(k, fb, v) { var I = window.SQ_I18N; if (I) return I.t(k, fb, v); return v ? fb.replace(/\{(\w+)\}/g, function (m, x) { return v[x] != null ? v[x] : m; }) : fb; }
  function $all(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }

  /* ---------- Scroll reveal with stagger ---------- */
  function initReveal() {
    var items = $all('[data-reveal]');
    if (!items.length) return;

    // stagger children of [data-stagger] containers
    $all('[data-stagger]').forEach(function (group) {
      var step = parseInt(group.getAttribute('data-stagger'), 10) || 80;
      $all('[data-reveal]', group).forEach(function (el, i) {
        if (!el.style.getPropertyValue('--d')) el.style.setProperty('--d', (i * step) + 'ms');
      });
    });

    if (reduceMotion || !('IntersectionObserver' in window)) {
      items.forEach(function (el) { el.classList.add('is-in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    items.forEach(function (el) { io.observe(el); });
  }

  /* ---------- Animated counters ---------- */
  function animateCount(el) {
    var target = parseFloat(el.getAttribute('data-count'));
    if (isNaN(target)) return;
    var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10);
    var prefix = el.getAttribute('data-prefix') || '';
    var suffix = el.getAttribute('data-suffix') || '';
    if (reduceMotion || target === 0) { el.textContent = prefix + target.toFixed(decimals) + suffix; return; }
    var duration = Math.min(1800, 700 + target * 12);
    var start = null;
    function frame(ts) {
      if (!start) start = ts;
      var p = Math.min(1, (ts - start) / duration);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = prefix + (target * eased).toFixed(decimals) + suffix;
      if (p < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }
  function initCounters() {
    var els = $all('[data-count]');
    if (!els.length) return;
    if (!('IntersectionObserver' in window)) { els.forEach(animateCount); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { animateCount(entry.target); io.unobserve(entry.target); }
      });
    }, { threshold: 0.4 });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ---------- Live queue ticker: Q-041 -> Q-042 ---------- */
  function pad(n) { return (n < 10 ? '00' : n < 100 ? '0' : '') + n; }
  function initTickers() {
    $all('[data-sq-ticker]').forEach(function (el) {
      var n = parseInt(el.getAttribute('data-sq-ticker'), 10) || 41;
      var max = parseInt(el.getAttribute('data-max'), 10) || n + 8;
      var startN = n;
      var interval = parseInt(el.getAttribute('data-interval'), 10) || 2600;
      var ringSel = el.getAttribute('data-ring');
      var ring = ringSel ? doc.querySelector(ringSel) : null;
      var aheadSel = el.getAttribute('data-ahead');
      var ahead = aheadSel ? doc.querySelector(aheadSel) : null;
      var barSel = el.getAttribute('data-bar');
      var bar = barSel ? doc.querySelector(barSel) : null;
      var mine = parseInt(el.getAttribute('data-mine'), 10) || max;
      var span = doc.createElement('span');
      span.textContent = 'Q-' + pad(n);
      el.textContent = '';
      el.appendChild(span);

      function sync() {
        var left = Math.max(0, mine - n);
        var pct = Math.round(100 - (left / Math.max(1, mine - startN)) * 100);
        if (ring) { ring.style.setProperty('--p', Math.max(8, pct)); var b = ring.querySelector('b'); if (b) b.textContent = left; }
        if (ahead) ahead.textContent = left === 0 ? T('js.turn', "It's your turn!") : (left === 1 ? T('js.ahead_one', '1 person ahead') : T('js.ahead_many', '{n} people ahead', { n: left }));
        if (bar) bar.style.setProperty('--w', Math.max(8, pct) + '%');
      }
      sync();
      if (reduceMotion) return;
      setInterval(function () {
        if (doc.hidden) return;
        n = n >= mine ? startN : n + 1;
        el.classList.remove('is-ticking');
        void el.offsetWidth; // restart animation
        span.textContent = 'Q-' + pad(n);
        el.classList.add('is-ticking');
        sync();
      }, interval);
    });
  }

  /* ---------- Navbar / topbar shrink on scroll ---------- */
  function initScrollState() {
    var bars = $all('.sq-nav, .lx-nav, .sq-topbar');
    if (!bars.length) return;
    var ticking = false;
    function update() {
      var y = window.scrollY || window.pageYOffset;
      bars.forEach(function (b) { b.classList.toggle('is-scrolled', y > 12); });
      ticking = false;
    }
    window.addEventListener('scroll', function () {
      if (!ticking) { ticking = true; requestAnimationFrame(update); }
    }, { passive: true });
    update();

    // highlight active landing link
    var links = $all('.sq-nav__links a[href^="#"], .lx-nav__links a[href^="#"]');
    if (links.length && 'IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) return;
          links.forEach(function (l) { l.classList.toggle('is-active', l.getAttribute('href') === '#' + e.target.id); });
        });
      }, { rootMargin: '-40% 0px -55% 0px' });
      links.forEach(function (l) { var s = doc.querySelector(l.getAttribute('href')); if (s) io.observe(s); });
    }
  }

  /* ---------- Menus: landing nav, app sidebar, user dropdown ---------- */
  function initMenus() {
    doc.addEventListener('click', function (e) {
      var t = e.target;
      var toggle = t.closest && t.closest('[data-sq-toggle]');
      if (toggle) {
        var what = toggle.getAttribute('data-sq-toggle');
        var cls = what === 'sidebar' ? 'sq-sidebar-open' : 'sq-nav-open';
        var open = doc.body.classList.toggle(cls);
        toggle.setAttribute('aria-expanded', String(open));
        var icon = toggle.querySelector('i');
        if (icon && what === 'nav') { icon.className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars'; }
        return;
      }
      if (t.closest && (t.closest('[data-sq-close]') || t.closest('.sq-nav__links a, .lx-nav__links a, .lx-nav__actions a'))) {
        doc.body.classList.remove('sq-sidebar-open', 'sq-nav-open');
        $all('[data-sq-toggle="nav"] i').forEach(function (i) { i.className = 'fa-solid fa-bars'; });
      }
      var userBtn = t.closest && t.closest('.sq-user__btn');
      $all('.sq-user').forEach(function (u) {
        if (userBtn && u.contains(userBtn)) {
          var o = u.classList.toggle('is-open');
          userBtn.setAttribute('aria-expanded', String(o));
        } else if (!u.contains(t)) {
          u.classList.remove('is-open');
        }
      });
    });
    doc.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        doc.body.classList.remove('sq-sidebar-open', 'sq-nav-open');
        $all('.sq-user').forEach(function (u) { u.classList.remove('is-open'); });
      }
    });
  }
  /* ---------- Modals: [data-sq-modal="id"] opens #id; logout links ask first ---------- */
  var openModalEl = null, lastTrigger = null;
  function focusables(box) {
    return $all('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])', box)
      .filter(function (el) { return el.offsetParent !== null || el === doc.activeElement; });
  }
  function openModal(modal, trigger) {
    if (!modal) return;
    if (openModalEl && openModalEl !== modal) closeModal(openModalEl, true);
    $all('.sq-user').forEach(function (u) { u.classList.remove('is-open'); });
    lastTrigger = trigger || doc.activeElement;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    void modal.offsetWidth;
    modal.classList.add('is-open');
    doc.body.classList.add('sq-modal-open');
    openModalEl = modal;
    var first = modal.querySelector('[data-sq-autofocus]') || focusables(modal)[0];
    setTimeout(function () { if (first) first.focus(); }, 30);
  }
  function closeModal(modal, silent) {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    doc.body.classList.remove('sq-modal-open');
    if (openModalEl === modal) openModalEl = null;
    if (location.hash && modal.id && location.hash === '#' + modal.id && history.replaceState) {
      history.replaceState(null, '', location.pathname + location.search);
    }
    if (!silent && lastTrigger && lastTrigger.focus) lastTrigger.focus();
  }
  function logoutModal(href) {
    var m = doc.getElementById('sqLogoutModal');
    if (!m) {
      m = doc.createElement('div');
      m.className = 'sq-modal';
      m.id = 'sqLogoutModal';
      m.setAttribute('role', 'dialog');
      m.setAttribute('aria-modal', 'true');
      m.setAttribute('aria-labelledby', 'sqLogoutTitle');
      m.setAttribute('aria-describedby', 'sqLogoutDesc');
      m.innerHTML =
        '<div class="sq-modal__scrim" data-sq-modal-close></div>' +
        '<div class="sq-modal__box">' +
          '<span class="sq-modal__icon sq-modal__icon--danger" aria-hidden="true"><i class="fa-solid fa-right-from-bracket"></i></span>' +
          '<h3 id="sqLogoutTitle">' + T('js.logout_title', 'Log out of SmartQ?') + '</h3>' +
          '<p id="sqLogoutDesc">' + T('js.logout_desc', 'You will need to sign in again to see your bookings and queue.') + '</p>' +
          '<div class="sq-modal__actions">' +
            '<button type="button" class="btn btn-secondary" data-sq-modal-close data-sq-autofocus>' + T('js.cancel', 'Cancel') + '</button>' +
            '<a class="btn sq-btn--danger-solid" data-sq-logout-confirm data-no-fade><i class="fa-solid fa-right-from-bracket"></i> ' + T('js.logout', 'Log out') + '</a>' +
          '</div>' +
        '</div>';
      doc.body.appendChild(m);
    }
    m.querySelector('[data-sq-logout-confirm]').setAttribute('href', href);
    return m;
  }
  function initModals() {
    // capture phase: runs before the page-fade handler and the menu handler
    doc.addEventListener('click', function (e) {
      if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var t = e.target;
      var out = t.closest && t.closest('a[href$="logout.php"]:not([data-sq-logout-confirm])');
      if (out) { e.preventDefault(); e.stopPropagation(); openModal(logoutModal(out.getAttribute('href')), out); return; }
      var opener = t.closest && t.closest('[data-sq-modal]');
      if (opener) {
        var target = doc.getElementById(opener.getAttribute('data-sq-modal'));
        if (target) { e.preventDefault(); e.stopPropagation(); openModal(target, opener); }
        return;
      }
      var closer = t.closest && t.closest('[data-sq-modal-close]');
      if (closer) {
        var modal = closer.closest('.sq-modal');
        if (modal) { e.preventDefault(); closeModal(modal); }
      }
    }, true);
    doc.addEventListener('keydown', function (e) {
      if (!openModalEl) return;
      if (e.key === 'Escape') { e.preventDefault(); closeModal(openModalEl); return; }
      if (e.key === 'Tab') {
        var f = focusables(openModalEl);
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && doc.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
    // deep link / no-JS fallback (#install-help) -> open as a real modal
    if (location.hash) {
      var h = doc.getElementById(location.hash.slice(1));
      if (h && h.classList.contains('sq-modal')) openModal(h);
    }
  }

  // legacy API used by older markup
  window.toggleMenu = function () {
    var u = doc.querySelector('.sq-user');
    if (u) u.classList.toggle('is-open');
  };

  /* ---------- Ripple on buttons ---------- */
  function initRipple() {
    if (reduceMotion) return;
    doc.addEventListener('pointerdown', function (e) {
      var btn = e.target.closest && e.target.closest('.btn, .sq-btn');
      if (!btn || btn.disabled) return;
      var r = btn.getBoundingClientRect();
      var s = doc.createElement('span');
      s.className = 'sq-ripple';
      s.style.left = (e.clientX - r.left) + 'px';
      s.style.top = (e.clientY - r.top) + 'px';
      btn.appendChild(s);
      setTimeout(function () { s.remove(); }, 650);
    });
  }

  /* ---------- Page fade transitions ---------- */
  function initPageTransitions() {
    if (reduceMotion) return;
    doc.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var a = e.target.closest && e.target.closest('a[href]');
      if (!a || a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-no-fade')) return;
      var href = a.getAttribute('href');
      if (!href || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) return;
      if (a.onclick) return; // inline confirm handlers
      var url;
      try { url = new URL(a.href, location.href); } catch (err) { return; }
      if (url.origin !== location.origin) return;
      if (url.pathname === location.pathname && url.search === location.search && url.hash) return;
      if (/\.(apk|pdf|csv|zip)$/i.test(url.pathname) || /export-/.test(url.pathname)) return;
      e.preventDefault();
      doc.body.classList.add('sq-leaving');
      setTimeout(function () { location.href = a.href; }, 170);
    });
    window.addEventListener('pageshow', function () { doc.body.classList.remove('sq-leaving'); });
  }

  /* ---------- Toasts ---------- */
  function toast(message, type) {
    var host = doc.querySelector('.sq-toasts');
    if (!host) { host = doc.createElement('div'); host.className = 'sq-toasts'; host.setAttribute('aria-live', 'polite'); doc.body.appendChild(host); }
    var t = doc.createElement('div');
    t.className = 'sq-toast' + (type === 'error' ? ' sq-toast--error' : '');
    var i = doc.createElement('i');
    i.className = type === 'error' ? 'fa-solid fa-circle-exclamation' : 'fa-solid fa-circle-check';
    var span = doc.createElement('span');
    span.textContent = message;
    t.appendChild(i); t.appendChild(span);
    host.appendChild(t);
    setTimeout(function () { t.classList.add('is-out'); setTimeout(function () { t.remove(); }, 320); }, 3600);
  }

  /* ---------- Confetti ---------- */
  function confetti() {
    if (reduceMotion) return;
    var colors = ['#1F6FE0', '#14A39A', '#F5B82E', '#3B82F6', '#16A36B', '#8EC5FF'];
    for (var k = 0; k < 44; k++) {
      var c = doc.createElement('i');
      c.className = 'sq-confetti';
      var angle = Math.random() * Math.PI * 2;
      var dist = 160 + Math.random() * 320;
      c.style.setProperty('--dx', Math.cos(angle) * dist + 'px');
      c.style.setProperty('--dy', (Math.sin(angle) * dist * 0.7 + 220) + 'px');
      c.style.setProperty('--rot', (Math.random() * 720 - 360) + 'deg');
      c.style.background = colors[k % colors.length];
      c.style.animationDelay = (Math.random() * 180) + 'ms';
      if (k % 3 === 0) c.style.borderRadius = '50%';
      doc.body.appendChild(c);
      (function (node) { setTimeout(function () { node.remove(); }, 2100); })(c);
    }
  }

  /* ---------- Password visibility toggles ---------- */
  function initPasswordToggles() {
    $all('[data-sq-pw]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = btn.parentNode.querySelector('input');
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.querySelector('i').className = show ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
        btn.setAttribute('aria-label', show ? T('js.hide_password', 'Hide password') : T('js.show_password', 'Show password'));
      });
    });
  }

  /* ---------- Filter chips that drive a <select> ---------- */
  function initChipSelects() {
    $all('[data-sq-chips]').forEach(function (wrap) {
      var select = doc.getElementById(wrap.getAttribute('data-sq-chips'));
      if (!select) return;
      $all('[data-value]', wrap).forEach(function (chip) {
        chip.addEventListener('click', function () {
          select.value = chip.getAttribute('data-value');
          if (select.form) select.form.submit();
        });
      });
    });
  }

  /* ---------- Decorative sparklines on stat cards (aria-hidden) ---------- */
  function initSparklines() {
    $all('.dashboard-card, .manager-stat-card, .admin-stat-card').forEach(function (card, idx) {
      var num = card.querySelector('[data-count]');
      if (!num || card.querySelector('.sq-spark')) return;
      var seed = (parseInt(num.getAttribute('data-count'), 10) || 0) + idx * 7 + 3;
      var pts = [], n = 8;
      for (var i = 0; i < n; i++) {
        var wave = Math.sin((i + seed) * 1.3) * 0.18 + Math.cos((i * seed) % 5) * 0.08;
        pts.push(Math.max(0.08, Math.min(0.95, 0.25 + (i / (n - 1)) * 0.5 + wave)));
      }
      var w = 96, h = 34, step = w / (n - 1);
      var d = pts.map(function (p, i) { return (i ? 'L' : 'M') + (i * step).toFixed(1) + ' ' + (h - p * (h - 4) - 2).toFixed(1); }).join(' ');
      var svgNS = 'http://www.w3.org/2000/svg';
      var svg = doc.createElementNS(svgNS, 'svg');
      svg.setAttribute('class', 'sq-spark'); svg.setAttribute('viewBox', '0 0 ' + w + ' ' + h); svg.setAttribute('aria-hidden', 'true'); svg.setAttribute('preserveAspectRatio', 'none');
      var area = doc.createElementNS(svgNS, 'path'); area.setAttribute('class', 'a'); area.setAttribute('d', d + ' L' + w + ' ' + h + ' L0 ' + h + ' Z');
      var line = doc.createElementNS(svgNS, 'path'); line.setAttribute('class', 'l'); line.setAttribute('d', d);
      svg.appendChild(area); svg.appendChild(line);
      card.appendChild(svg);
    });
  }

  /* ---------- Place photos: shimmer until loaded, then fade in ---------- */
  function initPlaceImages() {
    $all('.sq-place-media img').forEach(function (img) {
      var box = img.parentNode;
      function done() { box.classList.add('is-loaded'); }
      if (img.complete && img.naturalWidth) done();
      else { img.addEventListener('load', done); img.addEventListener('error', done); }
    });
  }

  function init() {
    initSparklines();
    initPlaceImages();
    initReveal();
    initCounters();
    initTickers();
    initScrollState();
    initMenus();
    initModals();
    initRipple();
    initPageTransitions();
    initPasswordToggles();
    initChipSelects();
    if (doc.querySelector('[data-sq-confetti]')) setTimeout(confetti, 650);
    var flash = doc.querySelector('[data-sq-toast]');
    if (flash) toast(flash.getAttribute('data-sq-toast'), flash.getAttribute('data-sq-toast-type'));
  }

  window.SQ = { toast: toast, confetti: confetti, openModal: openModal, closeModal: closeModal };
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init); else init();
})();
