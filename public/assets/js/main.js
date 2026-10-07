/* SAPiENZA – kleine Interaktionen, ohne Bibliotheken */
(function () {
  'use strict';

  var doc = document.documentElement;
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Header: beim Runterscrollen ausblenden, beim Hochscrollen zeigen */
  var header = document.querySelector('.site-header');
  var lastY = window.scrollY;

  /* Fortschritt für Weg-Linie und Timeline (--p von 0 bis 1) */
  var progressEls = Array.prototype.slice.call(document.querySelectorAll('[data-progress]'));

  function updateProgress() {
    var vh = window.innerHeight;
    progressEls.forEach(function (el) {
      var rect = el.getBoundingClientRect();
      var anchor = parseFloat(el.getAttribute('data-progress')) || 0.7;
      var p = (vh * anchor - rect.top) / rect.height;
      p = Math.min(1, Math.max(0, p));
      el.style.setProperty('--p', p.toFixed(4));
    });
  }

  var ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      var y = window.scrollY;
      if (header) {
        header.classList.toggle('is-scrolled', y > 8);
        var modalOpen = document.querySelector('dialog[open]');
        header.classList.toggle('is-hidden', !modalOpen && y > lastY && y > 320 && !header.contains(document.activeElement));
      }
      lastY = y;
      if (!reduceMotion) updateProgress();
      ticking = false;
    });
  }

  if (reduceMotion) {
    progressEls.forEach(function (el) { el.style.setProperty('--p', '1'); });
  } else {
    updateProgress();
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll, { passive: true });
  onScroll();

  /* Scroll-Reveal */
  var groups = document.querySelectorAll('[data-stagger]');
  Array.prototype.forEach.call(groups, function (group) {
    Array.prototype.forEach.call(group.querySelectorAll('[data-reveal]'), function (el, i) {
      el.style.setProperty('--d', i);
    });
  });

  var revealEls = document.querySelectorAll('[data-reveal], .step, .format--feature');
  if ('IntersectionObserver' in window && !reduceMotion) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });
    Array.prototype.forEach.call(revealEls, function (el) { io.observe(el); });
  } else {
    Array.prototype.forEach.call(revealEls, function (el) { el.classList.add('is-visible'); });
  }

  /* Kontakt-Dialog */
  var dialog = document.getElementById('kontakt');
  if (!dialog || typeof dialog.showModal !== 'function') return;

  var form = dialog.querySelector('form');
  var formView = dialog.querySelector('[data-view="form"]');
  var successView = dialog.querySelector('[data-view="success"]');
  var status = dialog.querySelector('.form-status');
  var submitBtn = form.querySelector('button[type="submit"]');
  var tsField = form.querySelector('input[name="ts"]');
  var opener = null;

  function openDialog(trigger) {
    opener = trigger || document.activeElement;
    if (tsField) tsField.value = String(Date.now());
    dialog.classList.remove('is-closing');
    dialog.showModal();
    doc.style.overflow = 'hidden';
    var first = dialog.querySelector('[data-view]:not([hidden]) input:not([type="hidden"]):not([tabindex="-1"]), [data-view]:not([hidden]) .btn');
    if (first) first.focus();
  }

  function closeDialog() {
    if (!dialog.open) return;
    var finish = function () {
      dialog.classList.remove('is-closing');
      dialog.close();
    };
    if (reduceMotion) { finish(); return; }
    dialog.classList.add('is-closing');
    setTimeout(finish, 260);
  }

  dialog.addEventListener('close', function () {
    doc.style.overflow = '';
    if (successView && !successView.hidden) {
      form.reset();
      successView.hidden = true;
      formView.hidden = false;
    }
    if (opener && typeof opener.focus === 'function') opener.focus();
  });

  dialog.addEventListener('cancel', function (e) { e.preventDefault(); closeDialog(); });
  dialog.addEventListener('click', function (e) { if (e.target === dialog) closeDialog(); });
  Array.prototype.forEach.call(dialog.querySelectorAll('[data-close]'), function (btn) {
    btn.addEventListener('click', closeDialog);
  });

  Array.prototype.forEach.call(document.querySelectorAll('[data-open-contact]'), function (trigger) {
    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      openDialog(trigger);
    });
  });
  if (location.hash === '#kontakt') openDialog();

  /* Formular: Validierung + Versand */
  var messages = {
    vorname: 'Bitte geben Sie Ihren Vornamen an.',
    telefon: 'Bitte geben Sie eine Telefonnummer an, unter der ich Sie erreiche.',
    telefonFormat: 'Bitte prüfen Sie die Telefonnummer (nur Ziffern, Leerzeichen, +, -, /).',
    einwilligung: 'Bitte stimmen Sie der Verarbeitung Ihrer Angaben zu.'
  };

  function setError(field, msg) {
    var input = form.elements[field];
    var err = form.querySelector('[data-error-for="' + field + '"]');
    if (!input || !err) return;
    err.textContent = msg || '';
    if (msg) input.setAttribute('aria-invalid', 'true');
    else input.removeAttribute('aria-invalid');
  }

  function validate() {
    var ok = true;
    var vorname = form.elements.vorname.value.trim();
    var telefon = form.elements.telefon.value.trim();
    setError('vorname', vorname ? '' : messages.vorname);
    if (!vorname) ok = false;
    if (!telefon) { setError('telefon', messages.telefon); ok = false; }
    else if (!/^[0-9+()\/\-\s]{6,30}$/.test(telefon)) { setError('telefon', messages.telefonFormat); ok = false; }
    else setError('telefon', '');
    var consent = form.elements.einwilligung.checked;
    setError('einwilligung', consent ? '' : messages.einwilligung);
    if (!consent) ok = false;
    return ok;
  }

  ['vorname', 'telefon', 'einwilligung'].forEach(function (name) {
    var el = form.elements[name];
    el.addEventListener(el.type === 'checkbox' ? 'change' : 'input', function () {
      if (el.getAttribute('aria-invalid') === 'true') validate();
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    status.textContent = '';
    status.classList.remove('is-error');
    if (!validate()) {
      var firstInvalid = form.querySelector('[aria-invalid="true"]');
      if (firstInvalid) firstInvalid.focus();
      return;
    }
    submitBtn.disabled = true;
    var label = submitBtn.querySelector('.label');
    var original = label.textContent;
    label.textContent = 'Wird gesendet …';

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'Accept': 'application/json' }
    })
      .then(function (res) { return res.json().catch(function () { return { ok: false }; }); })
      .then(function (data) {
        if (data && data.ok) {
          formView.hidden = true;
          successView.hidden = false;
          var closeBtn = successView.querySelector('.btn');
          if (closeBtn) closeBtn.focus();
        } else {
          throw new Error((data && data.message) || 'send');
        }
      })
      .catch(function (err) {
        status.classList.add('is-error');
        status.textContent = err && err.message && err.message !== 'send' && err.message !== 'Failed to fetch'
          ? err.message
          : 'Das hat leider nicht geklappt. Bitte versuchen Sie es später erneut oder schreiben Sie eine E-Mail an csapienza@gmx.de.';
      })
      .then(function () {
        submitBtn.disabled = false;
        label.textContent = original;
      });
  });
})();
