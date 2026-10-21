(function () {
  'use strict';
  var R = window.RESTAURANT || {};
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var DIAS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
  var DIAS_L = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parse(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2], 12); }
  function addDays(s, n) { var d = parse(s); d.setDate(d.getDate() + n); return iso(d); }
  function longDay(s) { var d = parse(s); return DIAS_L[d.getDay()] + ' ' + d.getDate() + ' de ' + MESES[d.getMonth()]; }

  function api(route, body) {
    var opts = { credentials: 'same-origin', headers: {} };
    if (body !== undefined) {
      opts.method = 'POST';
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    return fetch('api/?r=' + route, opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'El servidor no responde bien. Inténtalo de nuevo.' }; });
    }, function () {
      return { ok: false, error: 'Sin conexión. Revisa internet e inténtalo de nuevo.' };
    }).then(function (j) {
      if (!j.ok) throw new Error(j.error || 'Algo ha fallado');
      return j.data;
    });
  }

  function toast(msg) {
    var t = document.createElement('div');
    t.className = 'toast';
    t.setAttribute('role', 'status');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 3200);
  }

  /* ---------- Movimiento: entrada, apariciones y parallax ---------- */
  window.__motionOK = true;
  var motion = document.documentElement.classList.contains('js-motion');
  if (motion) {
    var hero = $('.hero');
    if (hero) requestAnimationFrame(function () { requestAnimationFrame(function () { hero.classList.add('is-in'); }); });

    // Títulos con máscara
    $$('.title').forEach(function (t) {
      if (t.querySelector('.mask')) return;
      t.innerHTML = '<span class="mask"><span class="mask-in">' + t.innerHTML + '</span></span>';
      t.classList.add('rv-mask');
    });
    // Bloques que aparecen desde abajo, escalonados entre hermanos
    var groups = ['.carta-intro', '.book-lead', '.book .btn-lg', '.book-alt', '.book-facts > div', '.pol-list details',
      '.app-grid > div > p', '.app-actions', '.ft-cols > div', '.libro', '.ft-big', '.acc-lead', '.acc-box', '.acc-sec', '.legal h2', '.legal p'];
    groups.forEach(function (sel) {
      $$(sel).forEach(function (el, i) {
        if (el.closest('.hero')) return;
        el.classList.add('rv');
        el.style.setProperty('--d', Math.min(i, 5) * 0.08 + 's');
      });
    });
    // Texto de «Nosotros» palabra a palabra
    var about = $('.about-text'), pals = [];
    if (about) {
      Array.prototype.slice.call(about.childNodes).forEach(function (n) {
        if (n.nodeType !== 3) return;
        var frag = document.createDocumentFragment();
        n.textContent.split(/(\s+)/).forEach(function (w) {
          if (!w) return;
          if (/^\s+$/.test(w)) { frag.appendChild(document.createTextNode(w)); return; }
          var sp = document.createElement('span');
          sp.className = 'pal';
          sp.textContent = w;
          frag.appendChild(sp);
        });
        about.replaceChild(frag, n);
      });
      pals = $$('.pal', about);
    }

    var targets = $$('.rv, .rv-img, .rv-mask');
    if ('IntersectionObserver' in window) {
      var io2 = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (en.isIntersecting) { en.target.classList.add('is-in'); io2.unobserve(en.target); }
        });
      }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
      targets.forEach(function (t) { io2.observe(t); });
    } else {
      targets.forEach(function (t) { t.classList.add('is-in'); });
    }

    // Efectos ligados al scroll (solo transform y opacidad)
    var media = $('#hero-media'), brand = $('.hero-brand'), skew = $('.ribbon-skew'), par = $$('[data-speed]');
    var lastY = window.scrollY, vel = 0, queued = false;
    var frame = function () {
      queued = false;
      var y = window.scrollY, vh = window.innerHeight;
      if (media && y < vh * 1.3) {
        media.style.transform = 'translate3d(0,' + (y * 0.35).toFixed(1) + 'px,0)';
        if (brand) {
          brand.style.transform = 'translate3d(0,' + (y * 0.16).toFixed(1) + 'px,0)';
          brand.style.opacity = Math.max(0, 1 - y / (vh * 0.9)).toFixed(3);
        }
      }
      if (pals.length) {
        var r = about.getBoundingClientRect();
        var p = Math.max(0, Math.min(1, (vh * 0.88 - r.top) / (r.height + vh * 0.3)));
        var n = Math.round(p * pals.length);
        for (var i = 0; i < pals.length; i++) pals[i].classList.toggle('on', i < n);
      }
      if (par.length && window.innerWidth > 640) {
        par.forEach(function (f) {
          var rr = f.getBoundingClientRect();
          if (rr.bottom < -100 || rr.top > vh + 100) return;
          var max = f.offsetHeight * 0.08;
          var off = Math.max(-max, Math.min(max, (rr.top + rr.height / 2 - vh / 2) * +f.dataset.speed));
          f.querySelector('img').style.translate = '0 ' + off.toFixed(1) + 'px';
        });
      }
      if (skew) {
        vel += ((y - lastY) - vel) * 0.25;
        var k = Math.max(-9, Math.min(9, vel * 0.4));
        skew.style.transform = 'skewX(' + (-k).toFixed(2) + 'deg)';
        if (Math.abs(vel) > 0.05) { queued = true; requestAnimationFrame(frame); }
      }
      lastY = y;
    };
    window.addEventListener('scroll', function () { if (!queued) { queued = true; requestAnimationFrame(frame); } }, { passive: true });
    window.addEventListener('resize', frame);
    frame();
  }

  /* ---------- Cabecera y menú ---------- */
  var hd = $('#hd');
  if (hd && hd.classList.contains('hd-over')) {
    var onScroll = function () {
      var past = window.scrollY > window.innerHeight * 0.6;
      hd.classList.toggle('is-solid', past);
      document.body.classList.toggle('is-scrolled', past);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
  var menu = $('#menu'), toggle = $('[data-menu]');
  function setMenu(open) {
    if (!menu) return;
    menu.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Cerrar el menú' : 'Abrir el menú');
    document.body.classList.toggle('menu-open', open);
    if (hd) hd.classList.toggle('is-solid', open || window.scrollY > window.innerHeight * 0.6);
  }
  if (toggle) toggle.addEventListener('click', function () { setMenu(menu.hidden); });
  if (menu) menu.addEventListener('click', function (e) { if (e.target.closest('a')) setMenu(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menu && !menu.hidden) setMenu(false); });

  /* ---------- Reserva en 3 pasos ---------- */
  var dlg = $('#bk');
  var B = { people: 2, date: R.today, time: '', slots: [] };

  function isClosed(day) {
    if ((R.closed || []).indexOf(day) !== -1) return true;
    var wd = String(((parse(day).getDay() + 6) % 7) + 1);
    return !((R.hours || {})[wd] || []).length;
  }

  function summary() {
    var parts = [B.people + (B.people === 1 ? ' persona' : ' personas')];
    if (B.date) parts.push(longDay(B.date));
    if (B.time) parts.push(B.time);
    $('#bk-sum').textContent = parts.join(' · ');
  }

  function showPane(n) {
    $$('.bk-pane', dlg).forEach(function (p) { p.hidden = p.dataset.pane !== String(n); });
    $('#bk-step').textContent = n <= 3 ? 'Paso ' + n + ' de 3' : 'Hecho';
    $('#bk-title').textContent = ['', 'Reservar mesa', 'Elige la hora', 'Tus datos', 'Petición enviada'][n];
    summary();
    var focusable = $('.bk-pane:not([hidden]) button, .bk-pane:not([hidden]) input', dlg);
    if (n > 1 && focusable) focusable.focus({ preventScroll: true });
    if (n === 2) loadSlots();
  }

  function renderDays() {
    var box = $('#bk-days');
    box.innerHTML = '';
    for (var i = 0; i < 14; i++) {
      var day = addDays(R.today, i);
      var d = parse(day);
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'day';
      b.setAttribute('role', 'radio');
      b.dataset.day = day;
      b.disabled = isClosed(day);
      b.setAttribute('aria-checked', String(day === B.date));
      b.setAttribute('aria-label', longDay(day) + (b.disabled ? ', cerrado' : ''));
      b.innerHTML = '<small>' + (i === 0 ? 'hoy' : i === 1 ? 'mañana' : DIAS[d.getDay()]) + '</small><b>' + d.getDate() + '</b><small>' + MESES[d.getMonth()].slice(0, 3) + '</small>';
      box.appendChild(b);
    }
  }

  function pickDate(day) {
    B.date = day;
    B.time = '';
    $$('.day', dlg).forEach(function (b) { b.setAttribute('aria-checked', String(b.dataset.day === day)); });
    $('#bk-date').value = day;
    summary();
  }

  function slotButtons(list) {
    return '<div class="slot-grid">' + list.map(function (s) {
      var off = s.left < B.people;
      return '<button type="button" class="slot" data-time="' + s.time + '" aria-pressed="' + (s.time === B.time) + '"' + (off ? ' disabled aria-label="' + s.time + ', completo"' : '') + '>' + s.time + '</button>';
    }).join('') + '</div>';
  }

  function renderSlots() {
    var box = $('#bk-slots');
    var free = B.slots.filter(function (s) { return s.left >= B.people; });
    if (!free.length) {
      box.innerHTML = '<p class="slots-msg">' + (B.reason || ('No queda sitio para ' + B.people + ' personas ese día.')) + ' Prueba otro día' + (R.phone ? ' o llámanos.' : '.') + '</p>';
    } else {
      var lunch = B.slots.filter(function (s) { return s.time < '17:00'; });
      var dinner = B.slots.filter(function (s) { return s.time >= '17:00'; });
      box.innerHTML = (lunch.length ? '<h3>Comida</h3>' + slotButtons(lunch) : '') + (dinner.length ? '<h3>Cena</h3>' + slotButtons(dinner) : '');
    }
    $('#bk-to3').disabled = !B.time;
  }

  function loadSlots() {
    var box = $('#bk-slots');
    box.innerHTML = '<p class="slots-msg">Buscando horas libres…</p>';
    $('#bk-to3').disabled = true;
    api('slots&date=' + encodeURIComponent(B.date)).then(function (d) {
      B.slots = d.slots;
      B.reason = d.open ? '' : d.reason + '.';
      if (B.time && !B.slots.some(function (s) { return s.time === B.time && s.left >= B.people; })) B.time = '';
      renderSlots();
    }).catch(function (e) {
      box.innerHTML = '<p class="slots-msg">' + e.message + '</p>';
    });
  }

  function openBooking() {
    if (!dlg) return;
    if (!$('.bk-pane[data-pane="4"]', dlg).hidden) resetBooking();
    renderDays();
    if (isClosed(B.date)) {
      for (var i = 0; i < 14; i++) { var d = addDays(R.today, i); if (!isClosed(d)) { pickDate(d); break; } }
    }
    $('#bk-date').min = R.today;
    $('#bk-date').max = addDays(R.today, R.daysAhead || 60);
    $('#bk-date').value = B.date;
    if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
    showPane(1);
  }

  function resetBooking() {
    B.time = '';
    var f = $('#bk-form');
    f.notes.value = '';
    f.privacy.checked = false;
    $('#bk-err').textContent = '';
    $('#bk-send').disabled = false;
    $('#bk-send').textContent = 'Enviar petición';
  }

  if (dlg) {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-book]');
      if (!b) return;
      e.preventDefault();
      setMenu(false);
      openBooking();
    });
    dlg.addEventListener('click', function (e) {
      if (e.target === dlg || e.target.closest('[data-close]')) { dlg.close(); return; }
      var day = e.target.closest('.day');
      if (day && !day.disabled) return pickDate(day.dataset.day);
      var slot = e.target.closest('.slot');
      if (slot && !slot.disabled) {
        B.time = slot.dataset.time;
        $$('.slot', dlg).forEach(function (s) { s.setAttribute('aria-pressed', String(s === slot)); });
        $('#bk-to3').disabled = false;
        summary();
        return;
      }
      var nx = e.target.closest('[data-next]');
      if (nx && !nx.disabled) showPane(+nx.dataset.next);
    });
    dlg.addEventListener('change', function (e) {
      if (e.target.name === 'people') {
        B.people = +e.target.value;
        var more = $('[name=people_more]', dlg);
        if (more) { more.value = ''; more.classList.remove('is-on'); }
        summary();
      } else if (e.target.name === 'people_more' && e.target.value) {
        B.people = +e.target.value;
        $$('[name=people]', dlg).forEach(function (r) { r.checked = false; });
        e.target.classList.add('is-on');
        summary();
      } else if (e.target.id === 'bk-date' && e.target.value) {
        if (isClosed(e.target.value)) { toast('Ese día el restaurante está cerrado'); e.target.value = B.date; return; }
        pickDate(e.target.value);
      }
    });
    var editWho = $('#bk-edit-who');
    if (editWho) editWho.addEventListener('click', function () { $('#bk-fields').hidden = false; editWho.parentNode.hidden = true; });

    $('#bk-send').addEventListener('click', function () {
      var f = $('#bk-form');
      var err = $('#bk-err');
      var data = {
        people: B.people, date: B.date, time: B.time,
        name: f.name.value.trim(), phone: f.phone.value.trim(), email: f.email.value.trim(),
        notes: f.notes.value.trim(), website: f.website.value, privacy: f.privacy.checked ? 1 : 0
      };
      err.textContent = '';
      if (!data.phone && R.customer) { $('#bk-fields').hidden = false; }
      if (!R.customer || !$('#bk-fields').hidden) {
        var miss = [];
        if (!data.name) miss.push('tu nombre');
        if (!data.phone) miss.push('un teléfono móvil');
        if (miss.length) { err.textContent = 'Falta ' + miss.join(' y ') + '.'; $('#bk-fields').hidden = false; return; }
      }
      if (!data.privacy) { err.textContent = 'Marca la casilla para aceptar las políticas.'; return; }
      var btn = this;
      btn.disabled = true;
      btn.textContent = 'Enviando…';
      api('reserve', data).then(function (r) {
        $('#bk-done-t').textContent = 'Petición realizada';
        $('#bk-done-msg').textContent = r.message;
        var more = $('#bk-done-more');
        if (r.account) more.innerHTML = 'Esperando confirmación. La verás en <a href="cuenta.php">tu cuenta</a>, donde también podrás confirmarla o cancelarla.';
        else more.innerHTML = 'Esperando confirmación. Mientras no toques el enlace del WhatsApp, la reserva queda pendiente.';
        showPane(4);
      }).catch(function (e) {
        err.textContent = e.message;
        btn.disabled = false;
        btn.textContent = 'Enviar petición';
      });
    });

    if (location.hash === '#reservar' && /[?&]app=1/.test(location.search)) setTimeout(openBooking, 300);
  }

  /* ---------- Cuenta del cliente ---------- */
  $$('.tabs [role=tab]').forEach(function (tab) {
    tab.addEventListener('click', function () {
      $$('.tabs [role=tab]').forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', String(on));
        document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
      });
    });
  });

  $$('form[data-api]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      if (f.dataset.confirm && !confirm(f.dataset.confirm)) return;
      var data = {};
      new FormData(f).forEach(function (v, k) { data[k] = v; });
      var btn = $('button[type=submit]', f), err = $('.form-err', f), label = btn.textContent;
      err.textContent = '';
      btn.disabled = true;
      btn.textContent = 'Un momento…';
      api(f.dataset.api, data).then(function (r) {
        if (f.dataset.api === 'c_register' && r && r.pending) {
          f.hidden = true;
          $('.tabs') && ($('.tabs').hidden = true);
          $('#acc-pend-email').textContent = r.email;
          $('#acc-pendiente').hidden = false;
          $('#acc-reenviar').dataset.email = r.email;
          return;
        }
        if (f.dataset.api === 'c_profile') {
          toast('Datos guardados');
          btn.disabled = false;
          btn.textContent = label;
          $$('input[type=password]', f).forEach(function (i) { i.value = ''; });
        } else {
          location.href = f.dataset.api === 'c_delete' ? './' : 'cuenta.php';
        }
      }).catch(function (ex) {
        err.textContent = ex.message;
        btn.disabled = false;
        btn.textContent = label;
      });
    });
  });

  var resend = $('#acc-reenviar');
  if (resend) resend.addEventListener('click', function () {
    resend.disabled = true;
    api('c_resend', { email: resend.dataset.email }).then(function () { toast('Email enviado otra vez'); }, function (e) { toast(e.message); })
      .then(function () { setTimeout(function () { resend.disabled = false; }, 30000); });
  });

  var logout = $('#logout');
  if (logout) logout.addEventListener('click', function () {
    api('c_logout', {}).then(function () { location.href = './'; }, function (e) { toast(e.message); });
  });

  $$('[data-confirm-res]').forEach(function (b) {
    b.addEventListener('click', function () {
      b.disabled = true;
      api('c_confirm', { id: +b.dataset.confirmRes }).then(function () {
        toast('Reserva confirmada');
        setTimeout(function () { location.reload(); }, 700);
      }).catch(function (e) { toast(e.message); b.disabled = false; });
    });
  });

  $$('[data-cancel]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm('¿Cancelar la reserva del ' + b.dataset.when + '?')) return;
      b.disabled = true;
      api('c_cancel', { id: +b.dataset.cancel }).then(function () {
        toast('Reserva cancelada');
        setTimeout(function () { location.reload(); }, 700);
      }).catch(function (e) { toast(e.message); b.disabled = false; });
    });
  });

  /* ---------- Carta: librito que se hojea ---------- */
  var libro = $('#libro');
  if (libro) {
    var stage = $('.hojas', libro);
    var hojas = $$('.hoja', libro);
    var marcas = $$('.marcas a', libro);
    var flechas = $$('.libro-flecha', libro);
    var pagina = $('#libro-pagina');
    var cur = 0, turning = false;
    libro.classList.add('is-book');
    // En el ordenador la carta se abre a doble página; en el móvil, de una en una
    var mq = window.matchMedia ? matchMedia('(min-width: 1100px)') : { matches: false };
    var spread = mq.matches;

    // Hojas que se ven a la vez a partir de la hoja i (portada sola; luego parejas 1-2, 3-4…)
    var visible = function (i) {
      if (!spread || i === 0) return [i];
      var l = i % 2 === 1 ? i : i - 1;
      return l + 1 < hojas.length ? [l, l + 1] : [l];
    };
    var start = function (i) { return visible(i)[0]; };
    var next = function () { return spread ? (cur === 0 ? 1 : cur + 2) : cur + 1; };
    var prev = function () { return spread ? (cur <= 1 ? 0 : cur - 2) : cur - 1; };

    var layout = function () {
      var vis = visible(cur);
      hojas.forEach(function (h, k) {
        h.classList.toggle('is-on', vis.indexOf(k) !== -1);
        h.classList.remove('lado-izq', 'lado-der');
        h.style.minHeight = '';
        if (spread) h.classList.add(k === 0 || k % 2 === 0 ? 'lado-der' : 'lado-izq');
      });
      libro.classList.toggle('is-doble', spread);
      libro.classList.toggle('is-cerrado', spread && cur === 0);
    };
    var fitHeight = function () {
      var vis = visible(cur);
      vis.forEach(function (k) { hojas[k].style.minHeight = ''; });
      var hgt = Math.max.apply(null, vis.map(function (k) { return hojas[k].offsetHeight; }));
      if (spread) vis.forEach(function (k) { hojas[k].style.minHeight = hgt + 'px'; });
      stage.style.height = hgt + 'px';
    };
    var update = function () {
      var vis = visible(cur);
      var nums = hojas.filter(function (h) { return h.dataset.num; }).length;
      var txt = vis.map(function (k) {
        var hj = hojas[k];
        return hj.dataset.num && !spread ? hj.dataset.label + ' · página ' + hj.dataset.num + ' de ' + nums : hj.dataset.label;
      });
      pagina.textContent = txt.join(' y ');
      flechas[0].disabled = cur === 0;
      flechas[1].disabled = vis[vis.length - 1] === hojas.length - 1;
      marcas.forEach(function (m) { m.classList.toggle('is-on', vis.indexOf(+m.dataset.goto) !== -1); });
      var on = marcas.filter(function (m) { return m.classList.contains('is-on'); })[0];
      if (on) on.scrollIntoView({ block: 'nearest', inline: 'center', behavior: reduce ? 'auto' : 'smooth' });
    };
    var go = function (i) {
      if (i < 0 || i >= hojas.length || turning) return;
      i = start(i);
      if (i === cur) return;
      var forward = i > cur;
      var oldVis = visible(cur), newVis = visible(i);
      // La hoja que gira: al avanzar, la de la derecha que se va; al volver, la que llega
      var moving = forward ? hojas[oldVis[oldVis.length - 1]] : hojas[spread ? newVis[newVis.length - 1] : newVis[0]];
      // Al volver, las hojas de antes se quedan debajo mientras gira la nueva
      var stay = forward || reduce ? [] : oldVis.filter(function (k) { return newVis.indexOf(k) === -1; }).map(function (k) { return hojas[k]; });
      cur = i;
      layout();
      stay.forEach(function (h) { h.classList.add('queda'); });
      fitHeight();
      update();
      var top = libro.getBoundingClientRect().top;
      if (top < 0) window.scrollTo({ top: window.scrollY + top - 90, behavior: reduce ? 'auto' : 'smooth' });
      if (reduce) return;
      turning = true;
      moving.classList.add(forward ? 'pasa-ida' : 'pasa-vuelta');
      setTimeout(function () {
        moving.classList.remove('pasa-ida', 'pasa-vuelta');
        stay.forEach(function (h) { h.classList.remove('queda'); });
        turning = false;
      }, 620);
    };

    libro.addEventListener('click', function (e) {
      // Tocar en cualquier parte de la portada abre la carta
      if (cur === 0 && e.target.closest('.hoja-portada')) { go(next()); return; }
      var d = e.target.closest('[data-dir]');
      if (d) { go(+d.dataset.dir > 0 ? next() : prev()); return; }
      var m = e.target.closest('[data-goto]');
      if (m) { e.preventDefault(); go(+m.dataset.goto); }
    });
    stage.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { e.preventDefault(); go(next()); }
      if (e.key === 'ArrowLeft') { e.preventDefault(); go(prev()); }
    });
    // Deslizar con el dedo
    var x0 = null, y0 = 0;
    stage.addEventListener('pointerdown', function (e) { if (e.pointerType !== 'mouse') { x0 = e.clientX; y0 = e.clientY; } });
    stage.addEventListener('pointerup', function (e) {
      if (x0 === null) return;
      var dx = e.clientX - x0, dy = e.clientY - y0;
      x0 = null;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.4) go(dx < 0 ? next() : prev());
    });
    stage.addEventListener('pointercancel', function () { x0 = null; });

    var onMode = function () {
      spread = mq.matches;
      cur = start(cur);
      layout(); fitHeight(); update();
    };
    if (mq.addEventListener) mq.addEventListener('change', onMode);
    layout();
    window.addEventListener('resize', fitHeight);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitHeight);
    $$('img', stage).forEach(function (img) { img.addEventListener('load', fitHeight); });
    fitHeight();
    update();
    // Enlaces a una sección concreta (#cat-3)
    var fromHash = function () {
      var target = location.hash && document.getElementById(location.hash.slice(1));
      var idx = hojas.indexOf(target);
      if (idx > 0) { go(idx); }
    };
    fromHash();
    window.addEventListener('hashchange', fromHash);
  }

  /* ---------- Instalar como app ---------- */
  var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
  var installBtn = $('#install-btn'), iosTip = $('#install-ios'), help = $('#install-help'), done = $('#install-done');
  var deferred = null;
  if (done && standalone) { done.hidden = false; if (help) help.hidden = true; }
  else if (iosTip && /iphone|ipad|ipod/i.test(navigator.userAgent)) { iosTip.hidden = false; if (help) help.hidden = true; }
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferred = e;
    if (installBtn) { installBtn.hidden = false; if (help) help.hidden = true; }
  });
  if (installBtn) installBtn.addEventListener('click', function () {
    if (!deferred) return;
    deferred.prompt();
    deferred.userChoice.then(function (c) {
      if (c.outcome === 'accepted') { installBtn.hidden = true; if (done) done.hidden = false; }
      deferred = null;
    });
  });
  window.addEventListener('appinstalled', function () { if (installBtn) installBtn.hidden = true; if (done) done.hidden = false; });

  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    navigator.serviceWorker.register('sw.js').catch(function () { /* sin modo app */ });
  }
})();
