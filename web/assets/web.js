(function () {
  'use strict';
  var R = window.RESTAURANT || {};
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  /* Idioma: window.I18N trae las traducciones del idioma actual (vacío en español) */
  var I = window.I18N || {};
  var LANG = document.documentElement.lang.slice(0, 2);
  function T(str) {
    var x = I[str] || str, args = Array.prototype.slice.call(arguments, 1), k = 0;
    return args.length ? x.replace(/%[sd]/g, function () { return String(args[k++]); }) : x;
  }
  var DIAS_L = I._days ? [I._days[6]].concat(I._days.slice(0, 6)) : ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  var DIAS = I._days ? DIAS_L.map(function (d) { return d.slice(0, 3); }) : ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
  var MESES = I._months || ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function parse(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2], 12); }
  function addDays(s, n) { var d = parse(s); d.setDate(d.getDate() + n); return iso(d); }
  function longDay(s) {
    var d = parse(s), dn = DIAS_L[d.getDay()], n = d.getDate(), m = MESES[d.getMonth()];
    return LANG === 'en' ? dn + ' ' + n + ' ' + m : LANG === 'pt' ? dn + ', ' + n + ' de ' + m : dn + ' ' + n + ' de ' + m;
  }

  function api(route, body) {
    var opts = { credentials: 'same-origin', headers: {} };
    if (body !== undefined) {
      opts.method = 'POST';
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    return fetch('api/?r=' + route, opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: T('El servidor no responde bien. Inténtalo de nuevo.') }; });
    }, function () {
      return { ok: false, error: T('Sin conexión. Revisa internet e inténtalo de nuevo.') };
    }).then(function (j) {
      if (!j.ok) throw new Error(j.error || T('Algo ha fallado'));
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
    var groups = ['.go-card', '.sub-cta-in', '.carta-intro', '.book-lead', '.book .btn-lg', '.book-alt', '.book-facts > div', '.pol-list details',
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
    toggle.setAttribute('aria-label', open ? T('Cerrar el menú') : T('Abrir el menú'));
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
    if (B.pane === 0) { $('#bk-sum').textContent = ''; return; }
    var parts = [B.people + ' ' + (B.people === 1 ? T('persona') : T('personas'))];
    if (B.date) parts.push(longDay(B.date));
    if (B.time) parts.push(B.time);
    $('#bk-sum').textContent = parts.join(' · ');
  }

  function showPane(n) {
    B.pane = n;
    $$('.bk-pane', dlg).forEach(function (p) { p.hidden = p.dataset.pane !== String(n); });
    $('#bk-step').textContent = n === 0 ? '' : n <= 3 ? T('Paso %d de 3', n) : T('Hecho');
    $('#bk-title').textContent = T(['¿Cómo quieres reservar?', 'Reservar mesa', 'Elige la hora', 'Tus datos', 'Petición enviada'][n]);
    summary();
    if (n === 0) updateCall();
    var focusable = $('.bk-pane:not([hidden]) button, .bk-pane:not([hidden]) input', dlg);
    if (n > 1 && focusable) focusable.focus({ preventScroll: true });
    if (n === 2) loadSlots();
  }

  /* ---------- ¿Está abierto ahora? (hora del restaurante, no la del móvil) ---------- */
  var NOW0 = R.now && R.now.match(/(\d+)-(\d+)-(\d+) (\d+):(\d+)/);
  var OFFSET = NOW0 ? Date.UTC(+NOW0[1], +NOW0[2] - 1, +NOW0[3], +NOW0[4], +NOW0[5]) - Date.now() : null;
  function restNow() {
    if (OFFSET === null) { var n = new Date(); return { day: iso(n), time: pad(n.getHours()) + ':' + pad(n.getMinutes()) }; }
    var d = new Date(Date.now() + OFFSET);
    return { day: d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()), time: pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) };
  }
  function rangesOf(day) {
    if (isClosed(day)) return [];
    return (R.hours || {})[String(((parse(day).getDay() + 6) % 7) + 1)] || [];
  }
  function whenText(k, time, day) {
    return k === 0 ? T('hoy a las %s', time) : k === 1 ? T('mañana a las %s', time) : T('el %s a las %s', DIAS_L[parse(day).getDay()], time);
  }
  function callStatus() {
    var n = restNow(), r = rangesOf(n.day), i;
    for (i = 0; i < r.length; i++) if (n.time >= r[i][0] && n.time < r[i][1]) return { open: true };
    for (i = 0; i < r.length; i++) if (r[i][0] > n.time) return { open: false, next: whenText(0, r[i][0], n.day) };
    for (var k = 1; k <= 14; k++) {
      var day = addDays(n.day, k), rr = rangesOf(day);
      if (rr.length) return { open: false, next: whenText(k, rr[0][0], day) };
    }
    return { open: false, next: '' };
  }
  function updateCall() {
    var a = $('#bk-call'), txt = $('#bk-call-txt');
    if (!a) return;
    var st = callStatus();
    a.classList.toggle('is-off', !st.open);
    if (st.open) {
      a.setAttribute('href', a.dataset.href);
      a.removeAttribute('aria-disabled');
      txt.textContent = T('Estamos abiertos ahora: %s', R.phone);
    } else {
      a.removeAttribute('href');
      a.setAttribute('aria-disabled', 'true');
      txt.textContent = st.next ? T('Ahora estamos cerrados. Podrás llamarnos %s.', st.next) : T('Ahora estamos cerrados.');
    }
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
      b.setAttribute('aria-label', longDay(day) + (b.disabled ? ', ' + T('cerrado') : ''));
      b.innerHTML = '<small>' + (i === 0 ? T('hoy') : i === 1 ? T('mañana') : DIAS[d.getDay()]) + '</small><b>' + d.getDate() + '</b><small>' + MESES[d.getMonth()].slice(0, 3) + '</small>';
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
      return '<button type="button" class="slot" data-time="' + s.time + '" aria-pressed="' + (s.time === B.time) + '"' + (off ? ' disabled aria-label="' + s.time + ', ' + T('completo') + '"' : '') + '>' + s.time + '</button>';
    }).join('') + '</div>';
  }

  function renderSlots() {
    var box = $('#bk-slots');
    var free = B.slots.filter(function (s) { return s.left >= B.people; });
    if (!free.length) {
      box.innerHTML = '<p class="slots-msg">' + (B.reason || T('No queda sitio para %d personas ese día.', B.people)) + ' ' + (R.phone ? T('Prueba otro día o llámanos.') : T('Prueba otro día.')) + '</p>';
    } else {
      var lunch = B.slots.filter(function (s) { return s.time < '17:00'; });
      var dinner = B.slots.filter(function (s) { return s.time >= '17:00'; });
      box.innerHTML = (lunch.length ? '<h3>' + T('Comida') + '</h3>' + slotButtons(lunch) : '') + (dinner.length ? '<h3>' + T('Cena') + '</h3>' + slotButtons(dinner) : '');
    }
    $('#bk-to3').disabled = !B.time;
  }

  function loadSlots() {
    var box = $('#bk-slots');
    box.innerHTML = '<p class="slots-msg">' + T('Buscando horas libres…') + '</p>';
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
    showPane($('.bk-pane[data-pane="0"]', dlg) ? 0 : 1);
  }

  function resetBooking() {
    B.time = '';
    var f = $('#bk-form');
    f.notes.value = '';
    f.privacy.checked = false;
    $('#bk-err').textContent = '';
    $('#bk-send').disabled = false;
    $('#bk-send').textContent = T('Enviar petición');
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
      if (e.target.closest('.bk-opt.is-off')) { e.preventDefault(); return; }
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
        if (isClosed(e.target.value)) { toast(T('Ese día el restaurante está cerrado')); e.target.value = B.date; return; }
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
        if (!data.name && !data.phone) { err.textContent = T('Faltan tu nombre y un teléfono móvil.'); $('#bk-fields').hidden = false; return; }
        if (!data.name) { err.textContent = T('Falta tu nombre.'); $('#bk-fields').hidden = false; return; }
        if (!data.phone) { err.textContent = T('Falta un teléfono móvil.'); $('#bk-fields').hidden = false; return; }
      }
      if (!data.privacy) { err.textContent = T('Marca la casilla para aceptar las políticas.'); return; }
      var btn = this;
      btn.disabled = true;
      btn.textContent = T('Enviando…');
      api('reserve', data).then(function (r) {
        $('#bk-done-t').textContent = T('Petición realizada');
        $('#bk-done-msg').textContent = r.message;
        var more = $('#bk-done-more');
        var wait = T('Esperando confirmación. Mientras no abras el enlace del WhatsApp y pulses «Confirmar mi reserva», la reserva queda pendiente.');
        if (r.linked) more.innerHTML = T('Esperando confirmación. La verás en <a href="cuenta.php">tu cuenta</a>, donde también podrás cancelarla.');
        else if (r.email) more.textContent = wait + ' ' + T('Si creas una cuenta con el mismo email, también la verás allí.');
        else more.textContent = wait + ' ' + T('Como no has puesto email, esta reserva no aparecerá en ninguna cuenta.');
        showPane(4);
      }).catch(function (e) {
        err.textContent = e.message;
        btn.disabled = false;
        btn.textContent = T('Enviar petición');
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
      btn.textContent = T('Un momento…');
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
          toast(T('Datos guardados'));
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
    api('c_resend', { email: resend.dataset.email }).then(function () { toast(T('Email enviado otra vez')); }, function (e) { toast(e.message); })
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
        toast(T('Reserva confirmada'));
        setTimeout(function () { location.reload(); }, 700);
      }).catch(function (e) { toast(e.message); b.disabled = false; });
    });
  });

  $$('[data-cancel]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm(T('¿Cancelar la reserva del %s?', b.dataset.when))) return;
      b.disabled = true;
      api('c_cancel', { id: +b.dataset.cancel }).then(function () {
        toast(T('Reserva cancelada'));
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
        return hj.dataset.num && !spread ? T('%s · página %s de %s', hj.dataset.label, hj.dataset.num, nums) : hj.dataset.label;
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

  /* ---------- Ampliar fotos ---------- */
  var ZOOM = '.about figure img, .gallery img, .plato img';
  var lb, lbImg, lbCap, lbList = [], lbI = 0, lbX0 = null;

  function zoomCaption(img) {
    var pl = img.closest('.plato'), fig = img.closest('figure');
    if (pl) { var n = $('.plato-nombre', pl); return n ? n.textContent.trim() : ''; }
    var fc = fig && $('figcaption', fig);
    return fc ? fc.textContent.trim() : (img.alt || '');
  }
  function zoomSrc(img) {
    var ss = img.getAttribute('srcset');
    if (!ss) return img.currentSrc || img.src;
    var best = '', w = 0;
    ss.split(/,\s+/).forEach(function (c) {
      var p = c.trim().split(/\s+/), n = parseInt(p[1], 10) || 0;
      if (p[0] && n >= w) { w = n; best = p[0]; }
    });
    return best || img.src;
  }
  function lbShow(i) {
    lbI = (i + lbList.length) % lbList.length;
    var img = lbList[lbI];
    lbImg.src = zoomSrc(img);
    lbImg.alt = img.alt || zoomCaption(img);
    lbCap.textContent = zoomCaption(img);
  }
  function lbBuild() {
    lb = document.createElement('dialog');
    lb.className = 'lb';
    lb.setAttribute('aria-label', T('Ampliar foto'));
    lb.innerHTML = '<div class="lb-in"><button type="button" class="lb-x" aria-label="' + T('Cerrar') + '">×</button>' +
      '<button type="button" class="lb-nav lb-prev" aria-label="' + T('Anterior') + '">‹</button>' +
      '<figure><img alt=""><figcaption></figcaption></figure>' +
      '<button type="button" class="lb-nav lb-next" aria-label="' + T('Siguiente') + '">›</button></div>';
    document.body.appendChild(lb);
    lbImg = $('img', lb);
    lbCap = $('figcaption', lb);
    lb.addEventListener('click', function (e) {
      if (e.target.closest('.lb-prev')) return lbShow(lbI - 1);
      if (e.target.closest('.lb-next')) return lbShow(lbI + 1);
      lb.close();
    });
    lb.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') lbShow(lbI - 1);
      else if (e.key === 'ArrowRight') lbShow(lbI + 1);
    });
    lb.addEventListener('pointerdown', function (e) { lbX0 = e.clientX; });
    lb.addEventListener('pointerup', function (e) {
      if (lbX0 !== null && lbList.length > 1 && Math.abs(e.clientX - lbX0) > 50) lbShow(lbI + (e.clientX < lbX0 ? 1 : -1));
      lbX0 = null;
    });
    lb.addEventListener('close', function () { lbImg.removeAttribute('src'); });
  }
  function zoomOpen(img) {
    if (!lb) lbBuild();
    lbList = $$(ZOOM, img.closest('.hoja') || document).filter(function (x) { return x.offsetParent !== null; });
    var i = lbList.indexOf(img);
    if (i < 0) { lbList = [img]; i = 0; }
    lb.classList.toggle('lb-one', lbList.length < 2);
    lbShow(i);
    if (typeof lb.showModal === 'function') lb.showModal(); else lb.setAttribute('open', '');
  }
  $$(ZOOM).forEach(function (img) {
    img.setAttribute('tabindex', '0');
    img.setAttribute('role', 'button');
    var c = zoomCaption(img);
    img.setAttribute('aria-label', T('Ampliar foto') + (c ? ': ' + c : ''));
  });
  document.addEventListener('click', function (e) {
    var img = e.target.closest && e.target.closest(ZOOM);
    if (img && img.tagName === 'IMG') zoomOpen(img);
  });
  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches(ZOOM)) { e.preventDefault(); zoomOpen(e.target); }
  });

  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    navigator.serviceWorker.register('sw.js').catch(function () { /* sin modo app */ });
  }
})();
