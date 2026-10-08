/* SAPiENZA – kleine Interaktionen, ohne Bibliotheken */
(function () {
  'use strict';

  var doc = document.documentElement;
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* Header: beim Runterscrollen ausblenden, beim Hochscrollen zeigen */
  var header = document.querySelector('.site-header');
  var lastY = window.scrollY;

  /* Fortschritt für Timeline (--p von 0 bis 1) */
  var progressEls = Array.prototype.slice.call(document.querySelectorAll('[data-progress]'));

  /* Weg-Linie: Spitze folgt exakt der Bildschirmmitte.
     Die Kurve ist in y monoton – per Tabelle wird zu jeder Höhe die passende Pfadlänge gesucht. */
  var path = document.querySelector('.path-wrap');
  var pathSvg = path && path.querySelector('.path-line');
  var pathDraw = path && path.querySelector('.path-line .draw');
  var pathTrack = path && path.querySelector('.path-line .track');
  var pathDot = path && path.querySelector('.path-dot');
  var pathTable = null;
  var pathTotal = 0;

  var pathBase = pathTrack && pathTrack.getAttribute('d');
  var pathW = 0, pathH = 0;

  /* Pfad in echte Pixel umrechnen (Basis: 100 × 1000), damit Strichlänge und Position übereinstimmen */
  function buildPathTable() {
    if (!pathTrack || !pathDraw || typeof pathTrack.getTotalLength !== 'function') return;
    var box = pathSvg.getBoundingClientRect();
    pathW = box.width; pathH = box.height;
    if (!pathW || !pathH) return;
    var i = 0;
    var d = pathBase.replace(/-?\d*\.?\d+/g, function (n) {
      return (parseFloat(n) * (i++ % 2 === 0 ? pathW / 100 : pathH / 1000)).toFixed(2);
    });
    pathSvg.setAttribute('viewBox', '0 0 ' + pathW.toFixed(2) + ' ' + pathH.toFixed(2));
    pathTrack.setAttribute('d', d);
    pathDraw.setAttribute('d', d);
    pathTotal = pathTrack.getTotalLength();
    pathDraw.style.strokeDasharray = pathTotal.toFixed(2) + ' ' + (pathTotal + 10).toFixed(2);
    pathTable = [];
    var steps = Math.max(400, Math.round(pathH / 6));
    for (var k = 0; k <= steps; k++) {
      var len = pathTotal * k / steps;
      var pt = pathTrack.getPointAtLength(len);
      pathTable.push({ len: len, x: pt.x, y: pt.y });
    }
  }

  function lookup(y) {
    var lo = 0, hi = pathTable.length - 1;
    if (y <= pathTable[0].y) return pathTable[0];
    if (y >= pathTable[hi].y) return pathTable[hi];
    while (hi - lo > 1) {
      var mid = (lo + hi) >> 1;
      if (pathTable[mid].y < y) lo = mid; else hi = mid;
    }
    var a = pathTable[lo], b = pathTable[hi];
    var t = (y - a.y) / ((b.y - a.y) || 1);
    return { len: a.len + (b.len - a.len) * t, x: a.x + (b.x - a.x) * t, y: y };
  }

  function updatePath() {
    if (!pathTable) return;
    var rect = path.getBoundingClientRect();
    if (Math.abs(rect.height - pathH) > 1) { buildPathTable(); if (!pathTable) return; }
    var target = window.innerHeight * 0.5 - rect.top;           // Bildschirmmitte, relativ zur Linie
    var hit = lookup(Math.min(pathH, Math.max(0, target)));
    pathDraw.style.strokeDashoffset = (pathTotal - hit.len).toFixed(2);
    if (pathDot) {
      pathDot.style.opacity = target > 0 && target < pathH ? '1' : '0';
      pathDot.style.transform = 'translate(' + hit.x.toFixed(1) + 'px,' + hit.y.toFixed(1) + 'px)';
    }
  }

  function updateProgress() {
    var vh = window.innerHeight;
    progressEls.forEach(function (el) {
      var rect = el.getBoundingClientRect();
      var anchor = parseFloat(el.getAttribute('data-progress')) || 0.5;
      var p = (vh * anchor - rect.top) / rect.height;
      p = Math.min(1, Math.max(0, p));
      el.style.setProperty('--p', p.toFixed(4));
    });
    updatePath();
  }

  var ticking = false;
  function onScroll() {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      var y = window.scrollY;
      if (header) {
        header.classList.toggle('is-scrolled', y > 8);
      }
      lastY = y;
      if (!reduceMotion) updateProgress();
      ticking = false;
    });
  }

  if (reduceMotion) {
    progressEls.forEach(function (el) { el.style.setProperty('--p', '1'); });
    if (pathDraw) pathDraw.style.strokeDashoffset = '0';
  } else {
    buildPathTable();
    updateProgress();
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', function () { pathH = -9; onScroll(); }, { passive: true });
  window.addEventListener('load', onScroll);
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

  /* Maus: warmer Lichtschein, magnetischer Button, Goldschimmer auf Karten.
     Nur mit echter Maus und ohne „Bewegung reduzieren“. */
  var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  if (finePointer && !reduceMotion) {
    // Warmer Lichtschein folgt der Maus weich verzögert
    var glow = document.createElement('div');
    glow.className = 'cursor-glow';
    glow.setAttribute('aria-hidden', 'true');
    document.body.appendChild(glow);

    var mx = 0, my = 0, gx = 0, gy = 0, glowRaf = 0, started = false;
    function glowLoop() {
      gx += (mx - gx) * 0.085;
      gy += (my - gy) * 0.085;
      glow.style.transform = 'translate3d(' + gx.toFixed(1) + 'px,' + gy.toFixed(1) + 'px,0)';
      glowRaf = Math.abs(mx - gx) + Math.abs(my - gy) > 0.3 ? requestAnimationFrame(glowLoop) : 0;
    }
    document.addEventListener('pointermove', function (e) {
      if (e.pointerType !== 'mouse') return;
      mx = e.clientX; my = e.clientY;
      if (!started) { gx = mx; gy = my; started = true; }
      glow.classList.add('is-on');
      if (!glowRaf) glowRaf = requestAnimationFrame(glowLoop);
    }, { passive: true });
    document.documentElement.addEventListener('mouseleave', function () { glow.classList.remove('is-on'); });

    // Haupt-Buttons ziehen sich minimal zur Maus
    Array.prototype.forEach.call(document.querySelectorAll('.closing .btn'), function (btn) {
      btn.addEventListener('pointermove', function (e) {
        var r = btn.getBoundingClientRect();
        var dx = (e.clientX - (r.left + r.width / 2)) / (r.width / 2);
        var dy = (e.clientY - (r.top + r.height / 2)) / (r.height / 2);
        btn.style.transform = 'translate(' + (dx * 4).toFixed(2) + 'px,' + (dy * 3).toFixed(2) + 'px)';
      });
      btn.addEventListener('pointerleave', function () { btn.style.transform = ''; });
    });

    // Karten: Goldschimmer folgt der Maus
    Array.prototype.forEach.call(document.querySelectorAll('.format, .flow li, .box'), function (card) {
      card.classList.add('has-sheen');
      card.addEventListener('pointermove', function (e) {
        var r = card.getBoundingClientRect();
        card.style.setProperty('--mx', (e.clientX - r.left).toFixed(0) + 'px');
        card.style.setProperty('--my', (e.clientY - r.top).toFixed(0) + 'px');
      });
    });
  }

  /* Hero-Blume: neigt sich zur Maus – links/rechts (Drehung um den Stielansatz)
     und vor/zurück (3D-Kippung), je nach Mausposition oben/unten.
     Beim Hover wird sie scharf und farbig; auf Touch-Geräten per Antippen. */
  var flower = document.querySelector('.hero-flower');
  var sway = flower && flower.querySelector('.hero-flower__sway');
  if (flower && sway) {
    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
      if (!reduceMotion) {
        var rz = 0, rx = 0, tz = 0, tx = 0, swayRaf = 0;
        var MAX_SIDE = 1.7, MAX_TILT = 4;   // dezent: ca. ein Drittel der ursprünglichen Werte
        var swayLoop = function () {
          rz += (tz - rz) * 0.06;
          rx += (tx - rx) * 0.06;
          sway.style.transform = 'rotate(' + rz.toFixed(3) + 'deg) rotateX(' + rx.toFixed(3) + 'deg)';
          swayRaf = Math.abs(tz - rz) + Math.abs(tx - rx) > 0.01 ? requestAnimationFrame(swayLoop) : 0;
        };
        document.addEventListener('pointermove', function (e) {
          if (e.pointerType !== 'mouse') return;
          var r = flower.getBoundingClientRect();
          if (r.bottom < 0) return;                         // Hero nicht sichtbar
          var h = (e.clientX - (r.left + r.width * 0.5)) / (window.innerWidth * 0.5);
          var v = (e.clientY - (r.top + r.height * 0.45)) / (window.innerHeight * 0.5);
          tz = Math.max(-1, Math.min(1, h)) * MAX_SIDE;
          tx = Math.max(-1, Math.min(1, -v)) * MAX_TILT;    // Maus oben: kippt nach hinten, unten: nach vorne
          if (!swayRaf) swayRaf = requestAnimationFrame(swayLoop);
        }, { passive: true });
      }
    } else {
      flower.addEventListener('click', function () { flower.classList.toggle('is-sharp'); });
    }
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
          : 'Das hat leider nicht geklappt. Bitte versuchen Sie es später erneut oder schreiben Sie eine E-Mail an kontakt@concetta-sapienza.com.';
      })
      .then(function () {
        submitBtn.disabled = false;
        label.textContent = original;
      });
  });
})();
