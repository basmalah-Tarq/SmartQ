/* SmartQ Motion v3 — public pages (landing + auth). No libraries.
   Landing: intro skip + live-queue flaps. Auth: tilt, live feed, title reveal, date picker,
   password meter, signup stepper, submit loading. No JS => static readable layout;
   prefers-reduced-motion => no intro, no tilt, no flipping. */
(function () {
  'use strict';

  var doc = document;
  var root = doc.documentElement;
  var reduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)').matches : false;
  var finePointer = window.matchMedia && window.matchMedia('(pointer: fine)').matches;

  function T(k, fb, v) { var I = window.SQ_I18N; if (I) return I.t(k, fb, v); return v ? fb.replace(/\{(\w+)\}/g, function (m, x) { return v[x] != null ? v[x] : m; }) : fb; }
  function $(sel, ctx) { return (ctx || doc).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }
  function onVisible(el, cb) {
    if (!('IntersectionObserver' in window)) { cb(true); return; }
    new IntersectionObserver(function (es) { es.forEach(function (e) { cb(e.isIntersecting); }); }, { rootMargin: '80px' }).observe(el);
  }

  /* ---------- Intro: CSS runs it; JS only lets the visitor skip it ---------- */
  function initIntro() {
    var pre = $('[data-pre]');
    if (!pre || !root.classList.contains('sq-intro')) return;
    function skip() { pre.classList.add('is-done'); }
    pre.addEventListener('click', skip);
    doc.addEventListener('keydown', function onKey(e) { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') { skip(); doc.removeEventListener('keydown', onKey); } });
  }

  /* ---------- Gentle 3D tilt toward the pointer (no library) ---------- */
  function initTilt(areaSel, rigSel, base) {
    var area = $(areaSel), rig = $(rigSel);
    if (!area || !rig || reduce) return;
    var tx = 0, ty = 0, cx = 0, cy = 0, running = false;
    if (finePointer) {
      area.addEventListener('pointermove', function (e) {
        var r = area.getBoundingClientRect();
        tx = (e.clientX - r.left) / r.width - .5;
        ty = (e.clientY - r.top) / r.height - .5;
      }, { passive: true });
      area.addEventListener('pointerleave', function () { tx = 0; ty = 0; });
    }
    function frame(t) {
      if (!running) return;
      cx += (tx - cx) * .06; cy += (ty - cy) * .06;
      rig.style.setProperty('--ry', (base.ry + cx * base.range + Math.sin(t / 3200) * 1.5).toFixed(2) + 'deg');
      rig.style.setProperty('--rx', (base.rx - cy * base.range * .6 + Math.cos(t / 3800) * 1).toFixed(2) + 'deg');
      requestAnimationFrame(frame);
    }
    onVisible(area, function (v) { if (v && !running) { running = true; requestAnimationFrame(frame); } if (!v) running = false; });
  }

  /* ---------- Departure-board flaps for the live "now serving" number ---------- */
  function pad3(n) { return (n < 10 ? '00' : n < 100 ? '0' : '') + n; }
  function initFlaps() {
    $$('[data-flap]').forEach(function (el) {
      var from = parseInt(el.getAttribute('data-from'), 10) || 41;
      var to = parseInt(el.getAttribute('data-to'), 10) || from + 5;
      var n = from;
      var label = 'Q-' + pad3(n);
      el.textContent = '';
      el.setAttribute('role', 'img');
      el.setAttribute('aria-label', T('js.now_serving', 'Now serving {n}', { n: label }));
      var cells = label.split('').map(function (ch) {
        var s = doc.createElement('span');
        s.className = 'lx-flap' + (ch === '-' ? ' lx-flap--dash' : '');
        var b = doc.createElement('b');
        b.textContent = ch;
        s.appendChild(b); el.appendChild(s);
        return b;
      });
      var scope = el.closest('[data-flap-scope]') || doc;
      var ahead = $('[data-ahead]', scope), ring = $('[data-ring]', scope), ringN = $('[data-ring-n]', scope);
      function sync() {
        var left = Math.max(0, to - n);
        if (ahead) ahead.textContent = left === 0 ? T('js.turn', "It's your turn!") : (left === 1 ? T('js.ahead_one', '1 person ahead') : T('js.ahead_many', '{n} people ahead', { n: left }));
        if (ring) ring.style.setProperty('--p', Math.max(.06, 1 - left / Math.max(1, to - from)).toFixed(3));
        if (ringN) ringN.textContent = left;
      }
      function flip(b, ch, delay) {
        if (!window.gsap) { b.textContent = ch; b.classList.remove('is-flip'); void b.offsetWidth; b.classList.add('is-flip'); return; }
        window.gsap.timeline({ delay: delay })
          .to(b, { rotationX: -90, duration: .16, ease: 'power1.in', onComplete: function () { b.textContent = ch; } })
          .fromTo(b, { rotationX: 90 }, { rotationX: 0, duration: .42, ease: 'back.out(1.8)' });
      }
      sync();
      if (reduce) return;
      var visible = true;
      onVisible(el, function (v) { visible = v; });
      setInterval(function () {
        if (doc.hidden || !visible) return;
        n = n >= to ? from : n + 1;
        var next = 'Q-' + pad3(n);
        el.setAttribute('aria-label', T('js.now_serving', 'Now serving {n}', { n: next }));
        next.split('').forEach(function (ch, i) { if (cells[i] && cells[i].textContent !== ch) flip(cells[i], ch, i * .05); });
        sync();
      }, 3000);
    });
  }

  /* ---------- Split headings into masked words ---------- */
  function splitWords(el) {
    if (el.__split) return el.__split;
    var words = [];
    Array.prototype.slice.call(el.childNodes).forEach(function (node) {
      if (node.nodeType === 3) {
        var frag = doc.createDocumentFragment();
        node.textContent.split(/(\s+)/).forEach(function (p) {
          if (!p) return;
          if (/^\s+$/.test(p)) { frag.appendChild(doc.createTextNode(' ')); return; }
          var span = doc.createElement('span'); span.className = 'lx-w';
          var inner = doc.createElement('span'); inner.textContent = p;
          span.appendChild(inner); frag.appendChild(span); words.push(inner);
        });
        node.parentNode.replaceChild(frag, node);
      } else if (node.nodeType === 1 && node.tagName !== 'BR') {
        var span = doc.createElement('span'); span.className = 'lx-w';
        var inner = doc.createElement('span');
        node.parentNode.insertBefore(span, node);
        span.appendChild(inner); inner.appendChild(node); words.push(inner);
      }
    });
    el.__split = words;
    return words;
  }

  /* ---------- AUTH ---------- */
  function initFeed() {
    var items = $$('.ax-feed__item');
    if (items.length < 2 || reduce) return;
    var i = 0;
    setInterval(function () {
      if (doc.hidden) return;
      var cur = items[i];
      cur.classList.remove('is-on'); cur.classList.add('is-out');
      setTimeout(function () { cur.classList.remove('is-out'); }, 700);
      i = (i + 1) % items.length;
      items[i].classList.add('is-on');
    }, 3600);
  }

  var MONTHS = T('js.months', 'January|February|March|April|May|June|July|August|September|October|November|December').split('|');
  function two(n) { return (n < 10 ? '0' : '') + n; }
  /* Replaces the text birth_date input (YYYY-MM-DD fallback) with Day / Month / Year selects
     that write the same YYYY-MM-DD value into a hidden input with the same name. */
  function initDateField() {
    var src = $('input[data-datefield]');
    if (!src) return;
    var wrap = src.closest('.fl') || src;
    var name = src.name, initial = src.value, now = new Date();
    var hidden = doc.createElement('input');
    hidden.type = 'hidden'; hidden.name = name; hidden.id = src.id;
    var box = doc.createElement('div');
    box.className = 'ax-date';
    box.setAttribute('role', 'group');
    box.setAttribute('aria-labelledby', 'bdLabel');
    box.innerHTML = '<span class="ax-label" id="bdLabel">' + T('js.birth_date', 'Birth date') + ' <small>' + T('js.optional', '(optional)') + '</small></span><div class="ax-date__grid"></div>';
    var grid = box.querySelector('.ax-date__grid');
    function makeSelect(id, label, opts) {
      var f = doc.createElement('div'); f.className = 'fl fl--noicon';
      var s = doc.createElement('select'); s.id = id; s.setAttribute('aria-label', label);
      var o0 = doc.createElement('option'); o0.value = ''; o0.textContent = label; s.appendChild(o0);
      opts.forEach(function (o) { var op = doc.createElement('option'); op.value = o[0]; op.textContent = o[1]; s.appendChild(op); });
      var c = doc.createElement('div'); c.className = 'fl__ctl';
      c.appendChild(s); f.appendChild(c); grid.appendChild(f);
      return s;
    }
    var days = [], months = [], years = [], k;
    for (k = 1; k <= 31; k++) days.push([String(k), String(k)]);
    for (k = 1; k <= 12; k++) months.push([String(k), MONTHS[k - 1]]);
    for (k = now.getFullYear(); k >= now.getFullYear() - 100; k--) years.push([String(k), String(k)]);
    var sd = makeSelect('bdDay', T('js.day', 'Day'), days), sm = makeSelect('bdMonth', T('js.month', 'Month'), months), sy = makeSelect('bdYear', T('js.year', 'Year'), years);
    var hint = doc.createElement('small'); hint.className = 'ax-date__hint'; hint.setAttribute('aria-live', 'polite');
    box.appendChild(hint);
    function update() {
      var dd = parseInt(sd.value, 10), mm = parseInt(sm.value, 10), yy = parseInt(sy.value, 10);
      var dim = new Date(yy || 2000, mm || 1, 0).getDate();
      if (mm) { for (var i = 1; i < sd.options.length; i++) sd.options[i].disabled = parseInt(sd.options[i].value, 10) > dim; }
      if (dd > dim) { sd.value = String(dim); dd = dim; }
      if (dd && mm && yy) { hidden.value = yy + '-' + two(mm) + '-' + two(dd); hint.textContent = ''; }
      else { hidden.value = ''; hint.textContent = (sd.value || sm.value || sy.value) ? T('js.date_hint', 'Pick day, month and year — or leave all three empty.') : ''; }
    }
    [sd, sm, sy].forEach(function (s) { s.addEventListener('change', update); });
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(initial || '');
    if (m) { sy.value = String(parseInt(m[1], 10)); sm.value = String(parseInt(m[2], 10)); sd.value = String(parseInt(m[3], 10)); }
    wrap.parentNode.replaceChild(box, wrap);
    box.appendChild(hidden);
    update();
  }

  function pwScore(v) {
    if (!v) return { s: 0, t: '' };
    var L = T('js.pw_levels', 'Too short|Weak|Fair|Good|Strong').split('|');
    if (v.length < 6) return { s: 0, t: L[0] };
    var s = 1;
    if (v.length >= 10) s++;
    if ((/[a-z]/.test(v) && /[A-Z]/.test(v)) || (/\d/.test(v) && /[a-zA-Z]/.test(v))) s++;
    if (/[^a-zA-Z0-9]/.test(v)) s++;
    s = Math.min(4, s);
    return { s: s, t: L[s] };
  }
  function initPasswordUX() {
    $$('[data-meter-for]').forEach(function (meter) {
      var input = doc.getElementById(meter.getAttribute('data-meter-for'));
      var label = meter.querySelector('span');
      if (!input) return;
      input.addEventListener('input', function () { var r = pwScore(input.value); meter.setAttribute('data-score', r.s); label.textContent = r.t; });
    });
    var p1 = doc.getElementById('suPass'), p2 = doc.getElementById('suPass2');
    if (p1 && p2) {
      var f2 = p2.closest('.fl');
      var check = function () {
        f2.classList.toggle('is-match', p2.value.length > 0 && p1.value === p2.value && p1.value.length >= 6);
        
      };
      p1.addEventListener('input', check); p2.addEventListener('input', check);
    }
  }

  function initStepper() {
    var form = $('#signupForm');
    var steps = $$('.ax-steps li');
    if (!form || !steps.length) return;
    var card = form.closest('.ax-card');
    var bar = $('.ax-progress');
    function val(n) { var el = form.elements[n]; return el ? String(el.value || '').trim() : ''; }
    function update() {
      var roleEl = form.querySelector('input[name="role"]:checked');
      var manager = !!roleEl && roleEl.value === 'manager';
      if (card) card.classList.toggle('is-manager', manager);
      var done = {
        role: !!roleEl,
        profile: val('full_name').length > 1 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val('email')),
        security: val('password').length >= 6 && val('password') === val('confirm_password'),
        place: !!(val('manager_place_name') && val('manager_category') && val('manager_city'))
      };
      var active = steps.filter(function (li) { return manager || li.getAttribute('data-s') !== 'place'; });
      var firstOpen = -1, count = 0;
      active.forEach(function (li, i) {
        var ok = done[li.getAttribute('data-s')];
        if (ok) count++;
        if (!ok && firstOpen < 0) firstOpen = i;
        li.classList.toggle('is-done', !!ok);
        li.classList.remove('is-current');
      });
      if (firstOpen >= 0) active[firstOpen].classList.add('is-current');
      if (bar) bar.style.setProperty('--p', Math.max(.05, count / active.length).toFixed(3));
    }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
  }

  function initSubmitLoading() {
    $$('.ax-submit [data-label]').forEach(function (t) { t.setAttribute('data-orig', t.textContent); });
    $$('form[data-loading]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        var btn = form.querySelector('.ax-submit');
        if (!btn) return;
        btn.classList.add('is-loading');
        var t = btn.querySelector('[data-label]');
        if (t && btn.getAttribute('data-loading-text')) t.textContent = btn.getAttribute('data-loading-text');
      });
    });
    window.addEventListener('pageshow', function () {
      $$('.ax-submit.is-loading').forEach(function (b) {
        b.classList.remove('is-loading');
        var t = b.querySelector('[data-label]');
        if (t && t.getAttribute('data-orig')) t.textContent = t.getAttribute('data-orig');
      });
    });
  }

  function initAuthTitle() {
    var t = $('.ax-title[data-split]');
    if (!t || reduce) return;
    var words = splitWords(t);
    words.forEach(function (w, i) {
      w.style.transform = 'translateY(105%)';
      w.style.transition = 'transform 1s cubic-bezier(.16,1,.3,1) ' + (0.1 + i * 0.05).toFixed(2) + 's';
    });
    requestAnimationFrame(function () { requestAnimationFrame(function () { words.forEach(function (w) { w.style.transform = 'none'; }); }); });
  }

  function init() {
    initIntro();
    initFlaps();
    initTilt('.ax-brand', '.ax-scene__rig', { rx: 4, ry: -8, range: 12 });
    initFeed();
    initAuthTitle();
    initDateField();
    initPasswordUX();
    initStepper();
    initSubmitLoading();
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init); else init();
})();
