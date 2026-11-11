/* SmartQ auth (login + signup) — no libraries.
   1. Panes: "Continue with email" (one-time code, default) <-> "Use password instead".
   2. Inline validation for [data-validate] forms (labels stay visible, messages under fields).
   3. Email code flow -> auth/email.php (action=start / action=verify) -> data.redirect.
   4. Google Identity Services button (only rendered when the page includes it) -> auth/google.php. */
(function () {
  'use strict';

  var doc = document;
  function T(k, fb, v) { var I = window.SQ_I18N; if (I) return I.t(k, fb, v); return v ? fb.replace(/\{(\w+)\}/g, function (m, x) { return v[x] != null ? v[x] : m; }) : fb; }
  function tm(m) { var I = window.SQ_I18N; return I ? I.tm(m) : m; }
  function $(sel, ctx) { return (ctx || doc).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }
  function csrf() { var m = $('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }
  function toast(msg, type) { if (window.SQ && window.SQ.toast) window.SQ.toast(msg, type); }

  function post(url, data) {
    data.csrf_token = csrf();
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      body: new URLSearchParams(data)
    }).then(function (r) {
      return r.json().catch(function () { return { status: false, message: T('js.err_generic', 'Something went wrong. Please try again.') }; });
    }, function () {
      return { status: false, message: T('js.err_network', 'Network error. Check your connection and try again.') };
    });
  }

  function setLoading(btn, on, text) {
    if (!btn) return;
    var label = btn.querySelector('[data-label]');
    if (label && !label.hasAttribute('data-orig')) label.setAttribute('data-orig', label.textContent);
    btn.classList.toggle('is-loading', !!on);
    btn.disabled = !!on;
    if (label) label.textContent = on && text ? text : label.getAttribute('data-orig');
  }

  /* ---------- 1. Panes ---------- */
  var card = $('[data-auth]');
  function setMode(mode, focus) {
    if (!card) return;
    if (mode === 'code' && !$('.ax-pane--code', card)) mode = 'password';
    card.setAttribute('data-mode', mode);
    if (focus) {
      var pane = $('.ax-pane--' + mode, card);
      var first = pane && $('input:not([type="hidden"]):not([tabindex="-1"]), .role-card', pane);
      if (first && first.offsetParent !== null) setTimeout(function () { first.focus(); }, 40);
    }
  }
  function initPanes() {
    if (!card) return;
    var pw = $('.ax-pane--password', card);
    var hasServerError = pw && $('.message.error', pw);
    var wantsPw = location.hash === '#password' || location.hash === '#manager' || (card.getAttribute('data-start') === 'password');
    setMode(hasServerError || wantsPw ? 'password' : 'code', false);
    if (location.hash === '#manager') {
      var mgr = $('input[name="role"][value="manager"]', card);
      if (mgr) { mgr.checked = true; mgr.dispatchEvent(new Event('change', { bubbles: true })); }
    }
    doc.addEventListener('click', function (e) {
      var b = e.target.closest && e.target.closest('[data-show-pane]');
      if (!b) return;
      e.preventDefault();
      var role = b.getAttribute('data-role');
      if (role) {
        var r = $('input[name="role"][value="' + role + '"]', card);
        if (r) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); }
      }
      setMode(b.getAttribute('data-show-pane'), true);
    });
  }

  /* ---------- 2. Inline validation ---------- */
  function fieldWrap(input) { return input.closest('.fl'); }
  function errEl(input) { var w = fieldWrap(input); return w ? $('.fl__err', w) : null; }
  function labelText(input) {
    var l = input.id ? $('label[for="' + input.id + '"]') : null;
    var t = l ? (l.childNodes[0] && l.childNodes[0].nodeType === 3 ? l.childNodes[0].textContent : l.textContent) : T('js.this_field', 'this field');
    return t.trim().toLowerCase();
  }
  function setError(input, msg) {
    var w = fieldWrap(input), e = errEl(input);
    if (w) w.classList.toggle('is-invalid', !!msg);
    if (e) e.textContent = msg || '';
    input.setAttribute('aria-invalid', msg ? 'true' : 'false');
    if (e && e.id) input.setAttribute('aria-describedby', e.id);
  }
  function messageFor(input) {
    var v = input.validity;
    if (v.valid) {
      var same = input.getAttribute('data-match');
      if (same) { var o = doc.getElementById(same); if (o && o.value !== input.value) return T('js.pw_mismatch', 'Passwords do not match.'); }
      return '';
    }
    if (v.valueMissing) return input.getAttribute('data-err-required') || (input.tagName === 'SELECT' ? T('js.choose_x', 'Please choose a {x}.', { x: labelText(input) }) : T('js.enter_x', 'Please enter your {x}.', { x: labelText(input) }));
    if (v.typeMismatch && input.type === 'email') return T('js.email_invalid', 'Enter a valid email address, like name@example.com.');
    if (v.tooShort) return T('js.min_chars', 'Use at least {n} characters.', { n: input.getAttribute('minlength') });
    if (v.patternMismatch) return input.getAttribute('title') || T('js.check_format', 'Please check the format.');
    return (window.SQ_I18N && SQ_I18N.lang === 'ar') ? T('js.check_field', 'Please check this field.') : (input.validationMessage || 'Please check this field.');
  }
  function validateForm(form) {
    var firstBad = null;
    $$('input, select, textarea', form).forEach(function (input) {
      if (input.type === 'hidden' || input.disabled || !fieldWrap(input)) return;
      var msg = messageFor(input);
      setError(input, msg);
      if (msg && !firstBad) firstBad = input;
    });
    return firstBad;
  }
  function initValidation() {
    $$('form[data-validate]').forEach(function (form) {
      form.noValidate = true;
      form.addEventListener('input', function (e) {
        var t = e.target;
        if (fieldWrap(t) && fieldWrap(t).classList.contains('is-invalid')) setError(t, messageFor(t));
      });
      form.addEventListener('focusout', function (e) {
        var t = e.target;
        if (fieldWrap(t) && t.value) setError(t, messageFor(t));
      });
    });
    // capture: runs before inline onsubmit handlers and the loading-state listener
    doc.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form.matches || !form.matches('form[data-validate]')) return;
      var bad = validateForm(form);
      if (bad) { e.preventDefault(); e.stopImmediatePropagation(); bad.focus(); }
    }, true);
  }

  /* ---------- 3. Email one-time code ---------- */
  function initCodeFlow() {
    var pane = $('.ax-pane--code');
    if (!pane) return;
    var endpoint = pane.getAttribute('data-endpoint') || 'auth/email.php';
    var startForm = $('[data-code-start]', pane);
    var step1 = $('[data-code-step1]', pane);
    var step2 = $('[data-code-verify]', pane);
    var verifyForm = $('[data-code-form]', pane);
    var otpWrap = $('.ax-otp', pane);
    var boxes = $$('.ax-otp input', pane);
    var codeErr = $('[data-code-err]', pane);
    var resendBtn = $('[data-code-resend]', pane);
    var timerEl = $('[data-code-timer]', pane);
    var emailOut = $('[data-code-email]', pane);
    var emailIn = $('input[name="email"]', startForm);
    var nameIn = $('input[name="full_name"]', startForm);
    var current = { email: '', name: '' };
    var timer = null, busy = false;

    function showStep(n) {
      step1.hidden = n !== 1;
      step2.hidden = n !== 2;
      if (card) card.classList.toggle('is-verifying', n === 2);
      $$('[data-code-only-step1]', pane).forEach(function (el) { el.hidden = n !== 1; });
      if (n === 2) setTimeout(function () { boxes[0].focus(); }, 40);
      else setTimeout(function () { emailIn.focus(); }, 40);
    }
    function countdown(sec) {
      clearInterval(timer);
      var left = Math.max(0, sec | 0);
      function paint() {
        if (left <= 0) {
          clearInterval(timer);
          resendBtn.disabled = false;
          resendBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> ' + T('js.resend', 'Resend code');
          return;
        }
        resendBtn.disabled = true;
        var m = Math.floor(left / 60), s = left % 60;
        resendBtn.textContent = T('js.resend_in', 'Resend code in {t}', { t: m + ':' + (s < 10 ? '0' : '') + s });
        left--;
      }
      paint();
      timer = setInterval(paint, 1000);
    }
    function codeError(msg) {
      codeErr.textContent = tm(msg) || '';
      otpWrap.classList.toggle('is-invalid', !!msg);
      if (msg) { otpWrap.classList.remove('is-shake'); void otpWrap.offsetWidth; otpWrap.classList.add('is-shake'); }
    }
    function clearBoxes() { boxes.forEach(function (b) { b.value = ''; b.classList.remove('is-filled'); }); }
    function code() { return boxes.map(function (b) { return b.value; }).join(''); }

    function requestCode(isResend) {
      if (busy) return;
      busy = true;
      var btn = isResend ? null : $('.ax-submit', startForm);
      setLoading(btn, true, T('js.sending_code', 'Sending code…'));
      if (isResend) { resendBtn.disabled = true; resendBtn.textContent = T('js.sending', 'Sending…'); }
      post(endpoint, { action: 'start', email: current.email }).then(function (res) {
        busy = false;
        setLoading(btn, false);
        if (res.status) {
          var d = res.data || {};
          emailOut.textContent = d.email || current.email;
          clearBoxes(); codeError('');
          showStep(2);
          countdown(d.resend_in || 60);
          if (isResend) toast(T('js.new_code', 'We sent you a new code.'));
        } else if (isResend) {
          codeError(res.message);
          countdown(30);
        } else {
          setError(emailIn, tm(res.message) || T('js.send_failed', 'We could not send the code. Please try again.'));
          emailIn.focus();
        }
      });
    }

    startForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (validateForm(startForm)) return;
      current.email = emailIn.value.trim();
      current.name = nameIn ? nameIn.value.trim() : '';
      requestCode(false);
    });

    function verify() {
      if (busy) return;
      var c = code();
      if (c.length !== 6) { codeError(T('js.enter_digits', 'Enter all 6 digits from the email.')); boxes[c.length] && boxes[c.length].focus(); return; }
      busy = true;
      var btn = $('.ax-submit', verifyForm);
      setLoading(btn, true, T('js.checking', 'Checking code…'));
      codeError('');
      var payload = { action: 'verify', email: current.email, code: c };
      var nextParam = new URLSearchParams(window.location.search).get('next');
      if (nextParam) payload.next = nextParam;
      if (current.name) payload.full_name = current.name;
      post(endpoint, payload).then(function (res) {
        if (res.status && res.data && res.data.redirect) {
          otpWrap.classList.add('is-ok');
          setLoading(btn, true, T('js.signed_in', 'Signed in — opening your workspace…'));
          window.location.href = res.data.redirect;
          return;
        }
        busy = false;
        setLoading(btn, false);
        codeError(res.message || T('js.code_failed', 'That code did not work. Please try again.'));
        clearBoxes();
        boxes[0].focus();
      });
    }
    verifyForm.addEventListener('submit', function (e) { e.preventDefault(); verify(); });

    function fillFrom(i, str) {
      var digits = String(str).replace(/\D/g, '').split('');
      if (!digits.length) return;
      for (var k = 0; k < digits.length && i + k < boxes.length; k++) {
        boxes[i + k].value = digits[k];
        boxes[i + k].classList.add('is-filled');
      }
      var next = Math.min(boxes.length - 1, i + digits.length);
      boxes[next].focus();
      if (code().length === 6) verify();
    }
    boxes.forEach(function (box, i) {
      box.addEventListener('input', function () {
        var v = box.value.replace(/\D/g, '');
        if (otpWrap.classList.contains('is-invalid')) codeError('');
        if (!v) { box.value = ''; box.classList.remove('is-filled'); return; }
        if (v.length > 1) { box.value = ''; fillFrom(i, v); return; }   // autofill / fast typing
        box.value = v;
        box.classList.add('is-filled');
        if (i < boxes.length - 1) boxes[i + 1].focus();
        else if (code().length === 6) verify();
      });
      box.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !box.value && i > 0) { e.preventDefault(); boxes[i - 1].value = ''; boxes[i - 1].classList.remove('is-filled'); boxes[i - 1].focus(); }
        else if (e.key === 'ArrowLeft' && i > 0) { e.preventDefault(); boxes[i - 1].focus(); }
        else if (e.key === 'ArrowRight' && i < boxes.length - 1) { e.preventDefault(); boxes[i + 1].focus(); }
      });
      box.addEventListener('focus', function () { box.select(); });
      box.addEventListener('paste', function (e) {
        var text = (e.clipboardData || window.clipboardData).getData('text');
        if (!text) return;
        e.preventDefault();
        fillFrom(text.replace(/\D/g, '').length >= 6 ? 0 : i, text);
      });
    });

    resendBtn.addEventListener('click', function () { if (!resendBtn.disabled) requestCode(true); });
    $$('[data-code-change]', pane).forEach(function (b) {
      b.addEventListener('click', function () { clearInterval(timer); codeError(''); showStep(1); });
    });
  }

  /* ---------- 4. Google Identity Services ---------- */
  function initGoogle() {
    var el = $('[data-google-btn]');
    if (!el || el.__done || !(window.google && window.google.accounts && window.google.accounts.id)) return;
    el.__done = true;
    var err = $('[data-google-err]');
    window.google.accounts.id.initialize({
      client_id: el.getAttribute('data-client-id'),
      context: el.getAttribute('data-context') || 'signin',
      ux_mode: 'popup',
      callback: function (resp) {
        if (err) err.textContent = '';
        post(el.getAttribute('data-endpoint') || 'auth/google.php', { credential: resp.credential }).then(function (res) {
          if (res.status && res.data && res.data.redirect) { window.location.href = res.data.redirect; return; }
          if (err) err.textContent = tm(res.message) || T('js.google_failed', 'Google sign-in failed. Please try again.');
          else toast(tm(res.message) || T('js.google_failed', 'Google sign-in failed.'), 'error');
        });
      }
    });
    window.google.accounts.id.renderButton(el, {
      type: 'standard', theme: 'outline', size: 'large', shape: 'pill', logo_alignment: 'center',
      text: el.getAttribute('data-text') || 'continue_with',
      width: Math.max(240, Math.min(400, el.clientWidth || 360))
    });
  }
  window.sqGoogleReady = initGoogle;

  function init() {
    initPanes();
    initValidation();
    initCodeFlow();
    initGoogle();
  }
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init); else init();
})();
