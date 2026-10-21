/* App de gestión del restaurante: reservas de hoy, calendario, carta y ajustes.
 * Funciona igual dentro de la app Android (Capacitor) y desde el navegador (/panel/ en iPhone). */
(function () {
  'use strict';

  /* ---------- Utilidades ---------- */
  const NATIVE = !!(window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform());
  const LS = {
    get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* sin almacenamiento */ } },
    del(k) { try { localStorage.removeItem(k); } catch (e) { /* sin almacenamiento */ } },
  };
  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const pad = (n) => String(n).padStart(2, '0');
  const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const parseISO = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d, 12); };
  const addDays = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x; };
  const todayISO = () => iso(new Date());
  const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
  const DIAS_C = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
  const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  const fmtDay = (s) => { const d = parseISO(s); return `${DIAS[d.getDay()]} ${d.getDate()} de ${MESES[d.getMonth()]}`; };
  const fmtShort = (s) => { const d = parseISO(s); return `${DIAS_C[d.getDay()]} ${d.getDate()}/${d.getMonth() + 1}`; };
  const euro = (n) => Number(n).toLocaleString('es-ES', { style: 'currency', currency: 'EUR' });
  const telHref = (t) => 'tel:' + String(t).replace(/[^\d+]/g, '');
  const waHref = (t) => { let n = String(t).replace(/\D/g, ''); if (n.length === 9) n = '34' + n; return 'https://wa.me/' + n; };
  const ACTIVE = ['pendiente', 'confirmada'];
  const alIcon = (k, size) => `<svg class="al-ic" width="${size}" height="${size}" aria-hidden="true"><use href="alergenos.svg?v=3#al-${k}"></use></svg>`;
  const STATUS = { pendiente: 'Por confirmar', confirmada: 'Confirmada', rechazada: 'Rechazada', cancelada: 'Cancelada' };
  const WEEK = { 1: 'Lunes', 2: 'Martes', 3: 'Miércoles', 4: 'Jueves', 5: 'Viernes', 6: 'Sábado', 7: 'Domingo' };

  const ICON = {
    hoy: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    cal: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>',
    carta: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v8a2 2 0 0 0 2 2v8M11 3v6M3 3v6a4 4 0 0 0 4 4M17 21V3c2.5 1 4 4 4 8h-4"/></svg>',
    ajustes: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
    refresh: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/></svg>',
    plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
    phone: '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2Z"/></svg>',
    plate: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/></svg>',
  };

  /* ---------- Estado ---------- */
  const defaultBase = NATIVE ? '' : new URL('..', location.href).href.replace(/\/$/, '');
  const S = {
    base: LS.get('base') || defaultBase,
    token: LS.get('token') || '',
    restaurant: LS.get('restaurant') || '',
    tab: LS.get('tab') || 'hoy',
    lastId: Number(LS.get('lastId')) || 0,
    settings: null,
    menu: null,
    res: {},          // reservas cargadas, por id
    calMonth: todayISO().slice(0, 7),
    calDay: todayISO(),
    pending: 0,
  };

  /* ---------- API ---------- */
  async function api(route, { body = null, form = null } = {}) {
    const opts = { method: 'GET', headers: {} };
    if (S.token) opts.headers['X-Token'] = S.token;
    if (form) { opts.method = 'POST'; opts.body = form; }
    else if (body) { opts.method = 'POST'; opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    let res;
    try {
      res = await fetch(S.base + '/api/?r=' + route, opts);
    } catch (e) {
      throw new Error('No hay conexión con la web. Revisa internet.');
    }
    let json;
    try { json = await res.json(); } catch (e) {
      throw new Error(`La web no responde bien (código ${res.status}). Revisa la dirección.`);
    }
    if (res.status === 401 && route !== 'login') {
      logoutLocal();
      throw new Error(json.error || 'Vuelve a iniciar sesión');
    }
    if (!json.ok) throw new Error(json.error || 'Algo ha fallado');
    return json.data;
  }

  async function loadSettings(force) {
    if (!S.settings || force) S.settings = await api('settings');
    return S.settings;
  }

  /* ---------- Avisos ---------- */
  function toast(msg, type = '') {
    const t = document.createElement('div');
    t.className = 't ' + type;
    t.textContent = msg;
    $('#toast').appendChild(t);
    setTimeout(() => t.remove(), type === 'new' ? 6000 : 3200);
  }

  let LocalNotif = null;
  if (NATIVE && window.Capacitor.registerPlugin) {
    try { LocalNotif = window.Capacitor.registerPlugin('LocalNotifications'); } catch (e) { LocalNotif = null; }
  }

  async function askNotifications() {
    try {
      if (LocalNotif) {
        const p = await LocalNotif.requestPermissions();
        return p.display === 'granted';
      }
      if ('Notification' in window) return (await Notification.requestPermission()) === 'granted';
    } catch (e) { /* no soportado */ }
    return false;
  }

  async function notifyNew(list) {
    const lines = list.map((r) => `${r.name} · ${r.people} pers. · ${fmtShort(r.date)} ${r.time}`);
    toast((list.length > 1 ? `${list.length} reservas nuevas: ` : 'Nueva reserva: ') + lines[0], 'new');
    if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
    try {
      if (LocalNotif) {
        await LocalNotif.schedule({
          notifications: list.map((r, i) => ({ id: r.id, title: 'Nueva reserva', body: lines[i] })),
        });
      } else if ('Notification' in window && Notification.permission === 'granted' && navigator.serviceWorker) {
        const reg = await navigator.serviceWorker.ready;
        list.forEach((r, i) => reg.showNotification('Nueva reserva', { body: lines[i], tag: 'res-' + r.id, icon: 'icons/icon-192.png' }));
      }
    } catch (e) { /* sin permiso */ }
  }

  async function poll() {
    if (!S.token) return;
    try {
      const c = await api('changes&since=' + S.lastId);
      const isNew = S.lastId > 0 && c.new.length > 0;
      S.lastId = c.last_id;
      LS.set('lastId', String(c.last_id));
      setBadge(c.pending);
      if (isNew) {
        notifyNew(c.new);
        if (S.tab === 'hoy' || S.tab === 'cal') render();
      }
    } catch (e) { /* se reintenta en el siguiente ciclo */ }
  }

  function setBadge(n) {
    S.pending = n;
    const b = $('#badge');
    if (!b) return;
    b.hidden = !n;
    b.textContent = n > 99 ? '99+' : String(n);
  }

  /* ---------- Hojas (ventanas desde abajo) ---------- */
  let sheetOpen = false;
  function openSheet(title, html, mount) {
    closeSheet(true);
    const wrap = document.createElement('div');
    wrap.className = 'sheet-wrap';
    wrap.innerHTML = `<div class="sheet" role="dialog" aria-modal="true" aria-label="${esc(title)}">
      <div class="sheet-grab"></div>
      <div class="sheet-head"><h2>${esc(title)}</h2><button class="close" type="button" aria-label="Cerrar">×</button></div>
      <div class="sheet-body">${html}</div></div>`;
    document.body.appendChild(wrap);
    wrap.addEventListener('click', (e) => { if (e.target === wrap || e.target.closest('.close')) closeSheet(); });
    if (!sheetOpen) history.pushState({ sheet: 1 }, '');
    sheetOpen = true;
    const first = $('input:not([type=hidden]):not([type=checkbox]), select, textarea', wrap);
    if (mount) mount($('.sheet-body', wrap));
    if (first && !('ontouchstart' in window)) first.focus();
    return wrap;
  }
  function closeSheet(silent) {
    const w = $('.sheet-wrap');
    if (w) w.remove();
    if (sheetOpen && !silent) { sheetOpen = false; history.back(); }
  }
  window.addEventListener('popstate', () => {
    if (sheetOpen) { sheetOpen = false; const w = $('.sheet-wrap'); if (w) w.remove(); }
  });

  function busy(btn, on, text) {
    if (!btn) return;
    if (on) { btn.dataset.label = btn.textContent; btn.textContent = text || 'Guardando…'; btn.disabled = true; }
    else { btn.textContent = btn.dataset.label || btn.textContent; btn.disabled = false; }
  }

  /* ---------- Pantallas de acceso ---------- */
  function normalizeBase(v) {
    v = v.trim().replace(/\s+/g, '');
    if (!v) return '';
    if (!/^https?:\/\//i.test(v)) v = 'https://' + v;
    return v.replace(/\/+$/, '').replace(/\/(panel|api)$/i, '').replace(/\/index\.php$/i, '');
  }

  function renderSetup(error = '') {
    $('#app').innerHTML = `<section class="gate">
      <img class="gate-logo" src="icons/icon-192.png" alt="">
      <h1>Conecta la app con la web del restaurante</h1>
      <p>Escribe la dirección de la web. Solo hay que hacerlo una vez.</p>
      <form id="setup" class="form" novalidate>
        <label>Dirección de la web
          <input name="base" type="url" inputmode="url" autocomplete="url" autocapitalize="off" placeholder="turestaurante.com" value="${esc(S.base)}" required>
        </label>
        ${error ? `<p class="err">${esc(error)}</p>` : ''}
        <button class="btn btn-main btn-block" type="submit">Conectar</button>
      </form></section>`;
    $('#setup').addEventListener('submit', async (e) => {
      e.preventDefault();
      const base = normalizeBase(e.target.base.value);
      if (!base) return renderSetup('Escribe la dirección de la web.');
      const btn = $('button', e.target);
      busy(btn, true, 'Comprobando…');
      try {
        const r = await fetch(base + '/api/?r=ping').then((x) => x.json());
        if (!r.ok) throw new Error(r.error);
        S.base = base;
        S.restaurant = r.data.restaurant;
        LS.set('base', base);
        LS.set('restaurant', r.data.restaurant);
        renderLogin();
      } catch (err) {
        renderSetup(err.message && !/fetch|JSON|token/i.test(err.message) ? err.message
          : 'No se encuentra la web en esa dirección. Comprueba que está bien escrita y que la web está instalada.');
      }
    });
  }

  function renderLogin(error = '') {
    $('#app').innerHTML = `<section class="gate">
      <img class="gate-logo" src="icons/icon-192.png" alt="">
      <h1>${esc(S.restaurant || 'Gestión del restaurante')}</h1>
      <p>Entra con el usuario que creaste al instalar la web.</p>
      <form id="login" class="form" novalidate>
        <label>Usuario <input name="username" autocomplete="username" autocapitalize="off" required value="${esc(LS.get('user') || '')}"></label>
        <label>Contraseña <input name="password" type="password" autocomplete="current-password" required></label>
        ${error ? `<p class="err">${esc(error)}</p>` : ''}
        <button class="btn btn-main btn-block" type="submit">Entrar</button>
        ${NATIVE ? `<button class="link" type="button" id="change-base">Cambiar la dirección de la web (${esc(S.base.replace(/^https?:\/\//, ''))})</button>` : ''}
      </form></section>`;
    const f = $('#login');
    f.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = $('button[type=submit]', f);
      busy(btn, true, 'Entrando…');
      try {
        const r = await api('login', { body: { username: f.username.value, password: f.password.value } });
        S.token = r.token;
        S.restaurant = r.restaurant;
        LS.set('token', r.token);
        LS.set('user', r.user);
        LS.set('restaurant', r.restaurant);
        startApp();
        askNotifications();
      } catch (err) {
        renderLogin(err.message);
      }
    });
    const cb = $('#change-base');
    if (cb) cb.addEventListener('click', () => renderSetup());
  }

  function logoutLocal() {
    S.token = '';
    S.settings = null;
    LS.del('token');
    renderLogin();
  }

  /* ---------- Estructura principal ---------- */
  const TABS = [
    ['hoy', 'Hoy', ICON.hoy],
    ['cal', 'Calendario', ICON.cal],
    ['carta', 'Carta', ICON.carta],
    ['ajustes', 'Ajustes', ICON.ajustes],
  ];

  function startApp() {
    $('#app').innerHTML = `
      <header class="bar">
        <div><p class="bar-sub" id="bar-sub">${esc(S.restaurant)}</p><h1 id="title"></h1></div>
        <button class="icon-btn" id="refresh" type="button" aria-label="Actualizar">${ICON.refresh}</button>
      </header>
      <main id="view"></main>
      <div id="fab-slot"></div>
      <nav class="tabs" aria-label="Secciones">
        ${TABS.map(([k, label, icon]) => `<button type="button" data-tab="${k}">${icon}<span>${label}</span>${k === 'hoy' ? '<b class="badge" id="badge" hidden></b>' : ''}</button>`).join('')}
      </nav>`;
    $('.tabs').addEventListener('click', (e) => {
      const b = e.target.closest('[data-tab]');
      if (b) go(b.dataset.tab);
    });
    $('#refresh').addEventListener('click', async (e) => {
      const b = e.currentTarget;
      b.classList.add('busy');
      S.settings = null;
      await Promise.all([render(), poll()]);
      b.classList.remove('busy');
    });
    $('#view').addEventListener('click', onViewClick);
    $('#view').addEventListener('change', onViewChange);
    go(S.tab);
    poll();
  }

  function go(tab) {
    if (!TABS.some((t) => t[0] === tab)) tab = 'hoy';
    S.tab = tab;
    LS.set('tab', tab);
    $$('.tabs [data-tab]').forEach((b) => { if (b.dataset.tab === tab) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current'); });
    window.scrollTo(0, 0);
    render();
  }

  function setTitle(t) { $('#title').textContent = t; }
  function setFab(label, onClick) {
    const slot = $('#fab-slot');
    slot.innerHTML = label ? `<button class="fab" type="button">${ICON.plus}<span>${esc(label)}</span></button>` : '';
    if (label) $('.fab', slot).addEventListener('click', onClick);
  }

  let renderSeq = 0;
  async function render() {
    const seq = ++renderSeq;
    const view = $('#view');
    if (!view) return;
    const titles = { hoy: 'Hoy', cal: 'Calendario', carta: 'Carta', ajustes: 'Ajustes' };
    setTitle(titles[S.tab]);
    setFab('', null);
    if (!view.dataset.tab || view.dataset.tab !== S.tab) view.innerHTML = '<div class="spin dark" aria-label="Cargando"></div>';
    view.dataset.tab = S.tab;
    try {
      const html = await ({ hoy: viewHoy, cal: viewCal, carta: viewCarta, ajustes: viewAjustes })[S.tab]();
      if (seq !== renderSeq) return;
      view.innerHTML = html;
      afterRender[S.tab] && afterRender[S.tab](view);
    } catch (err) {
      if (seq !== renderSeq || !S.token) return;
      view.innerHTML = `<div class="err-box"><p>${esc(err.message)}</p><button class="btn btn-dark btn-sm" data-retry>Reintentar</button></div>`;
    }
  }
  const afterRender = {};

  /* ---------- Tarjetas de reserva ---------- */
  function resCard(r, showDate) {
    S.res[r.id] = r;
    const actions = [];
    if (r.status === 'pendiente') {
      actions.push(`<button class="btn btn-sm b-ok" data-status="confirmada" data-id="${r.id}">Confirmar</button>`);
      actions.push(`<button class="btn btn-sm b-no" data-status="rechazada" data-id="${r.id}">Rechazar</button>`);
    }
    if (ACTIVE.includes(r.status)) actions.push(`<a class="btn btn-sm btn-quiet b-call" href="${esc(telHref(r.phone))}" aria-label="Llamar a ${esc(r.name)}">${ICON.phone}${r.status === 'pendiente' ? '' : '<span>Llamar</span>'}</a>`);
    return `<article class="res st-${r.status}" data-open="${r.id}">
      <div class="res-time">${showDate ? `<small>${esc(fmtShort(r.date))}</small>` : ''}<b>${esc(r.time)}</b></div>
      <div class="res-main">
        <p class="res-name">${esc(r.name)}</p>
        <p class="res-meta"><span>${r.people} ${r.people === 1 ? 'persona' : 'personas'}</span><span class="chip chip-${r.status}">${STATUS[r.status]}</span>${r.source === 'panel' ? '<span class="by-hand">a mano</span>' : ''}${r.customer ? '<span class="by-hand">con cuenta</span>' : ''}</p>
        ${r.notes ? `<p class="res-notes">${esc(r.notes)}</p>` : ''}
        ${actions.length ? `<div class="res-actions">${actions.join('')}</div>` : ''}
      </div></article>`;
  }

  function daySummary(list) {
    const act = list.filter((r) => ACTIVE.includes(r.status));
    const people = act.reduce((a, r) => a + r.people, 0);
    const pend = act.filter((r) => r.status === 'pendiente').length;
    if (!act.length) return 'Sin reservas';
    return `<b>${act.length}</b> ${act.length === 1 ? 'reserva' : 'reservas'} · <b>${people}</b> personas${pend ? ` · <b>${pend}</b> por confirmar` : ''}`;
  }

  function servicesHTML(list) {
    const comida = list.filter((r) => r.time < '17:00');
    const cena = list.filter((r) => r.time >= '17:00');
    let html = '';
    if (comida.length) html += `<h2 class="sec">Comida <span>${comida.filter((r) => ACTIVE.includes(r.status)).reduce((a, r) => a + r.people, 0)} pers.</span></h2>` + comida.map((r) => resCard(r)).join('');
    if (cena.length) html += `<h2 class="sec">Cena <span>${cena.filter((r) => ACTIVE.includes(r.status)).reduce((a, r) => a + r.people, 0)} pers.</span></h2>` + cena.map((r) => resCard(r)).join('');
    return html;
  }

  /* ---------- HOY ---------- */
  async function viewHoy() {
    const t = todayISO();
    const all = await api(`reservations&from=${t}&to=${iso(addDays(new Date(), 180))}`);
    const today = all.filter((r) => r.date === t);
    const pendingLater = all.filter((r) => r.date > t && r.status === 'pendiente');
    setFab('Nueva reserva', () => editReservation(null, t));
    let html = `<div class="day-head"><p class="day-date">${esc(fmtDay(t))}</p><p class="day-sum">${daySummary(today)}</p></div>`;
    if (pendingLater.length) {
      html += `<h2 class="sec">Por confirmar en otros días <span>${pendingLater.length}</span></h2>` + pendingLater.map((r) => resCard(r, true)).join('');
    }
    if (!today.length) {
      html += `<div class="empty"><p>Hoy no hay reservas todavía.</p><button class="btn btn-line btn-sm" data-new="${t}">Apuntar una reserva</button></div>`;
    } else {
      html += servicesHTML(today);
    }
    return html;
  }

  /* ---------- CALENDARIO ---------- */
  async function viewCal() {
    const [settings, month, dayList] = await Promise.all([
      loadSettings(),
      api('month&month=' + S.calMonth),
      api(`reservations&from=${S.calDay}&to=${S.calDay}`),
    ]);
    const [y, m] = S.calMonth.split('-').map(Number);
    const first = new Date(y, m - 1, 1, 12);
    const offset = (first.getDay() + 6) % 7;
    const days = new Date(y, m, 0).getDate();
    const t = todayISO();
    const closedDates = settings.closed_dates || [];
    const isClosed = (d) => closedDates.includes(d) || !(settings.hours[String(((parseISO(d).getDay() + 6) % 7) + 1)] || []).length;

    let cells = ['L', 'M', 'X', 'J', 'V', 'S', 'D'].map((d) => `<div class="cal-wd">${d}</div>`).join('');
    for (let i = 0; i < offset; i++) cells += '<div class="cd blank"></div>';
    for (let d = 1; d <= days; d++) {
      const ds = `${S.calMonth}-${pad(d)}`;
      const info = month.days[ds];
      const cls = ['cd', ds === S.calDay && 'sel', ds === t && 'today', ds < t && 'past', isClosed(ds) && 'closed'].filter(Boolean).join(' ');
      const label = `${d} de ${MESES[m - 1]}${info ? `, ${info.count} reservas, ${info.people} personas` : ''}${info && info.pending ? `, ${info.pending} por confirmar` : ''}${isClosed(ds) ? ', cerrado' : ''}`;
      cells += `<button type="button" class="${cls}" data-day="${ds}" aria-label="${esc(label)}"><span class="n">${d}</span>${info ? `<span class="p">${info.people}p</span>` : ''}${info && info.pending ? '<i class="pd"></i>' : ''}</button>`;
    }

    const dayClosed = closedDates.includes(S.calDay);
    const weekClosed = !(settings.hours[String(((parseISO(S.calDay).getDay() + 6) % 7) + 1)] || []).length;
    setFab('Nueva reserva', () => editReservation(null, S.calDay));

    return `<section class="cal">
        <div class="cal-head">
          <button class="cal-nav" type="button" data-month="-1" aria-label="Mes anterior">‹</button>
          <h2>${MESES[m - 1]} ${y}</h2>
          <button class="cal-nav" type="button" data-month="1" aria-label="Mes siguiente">›</button>
        </div>
        <div class="cal-grid">${cells}</div>
        <div class="cal-legend"><span><i style="background:var(--alga)"></i>personas reservadas</span><span><i style="background:#D89A2B;border-radius:50%"></i>por confirmar</span><span><i style="background:repeating-linear-gradient(-45deg,#EEF1F0 0 3px,#D2D8D5 3px 6px)"></i>cerrado</span></div>
      </section>
      <div class="day-head" style="margin-top:20px"><p class="day-date">${esc(fmtDay(S.calDay))}</p><p class="day-sum">${weekClosed ? 'Día de cierre semanal' : dayClosed ? 'Cerrado este día' : daySummary(dayList)}</p></div>
      <div class="day-tools">
        ${S.calDay !== t ? '<button class="btn btn-quiet btn-sm" data-goto-today>Ir a hoy</button>' : ''}
        ${!weekClosed && S.calDay >= t ? `<button class="btn btn-line btn-sm" data-close-day="${dayClosed ? 'open' : 'close'}">${dayClosed ? 'Abrir este día' : 'Cerrar este día (festivo, vacaciones…)'}</button>` : ''}
      </div>
      ${dayList.length ? servicesHTML(dayList) : '<div class="empty"><p>No hay reservas este día.</p></div>'}`;
  }

  async function toggleClosedDay(action) {
    const s = await loadSettings();
    let list = (s.closed_dates || []).filter((d) => d !== S.calDay);
    if (action === 'close') {
      const n = (await api(`reservations&from=${S.calDay}&to=${S.calDay}`)).filter((r) => ACTIVE.includes(r.status)).length;
      if (n && !confirm(`Ya hay ${n} reservas ese día. Si lo cierras no entrarán más, pero las que hay siguen ahí. ¿Cerrar?`)) return;
      list.push(S.calDay);
    }
    S.settings = await api('settings_save', { body: { closed_dates: list } });
    toast(action === 'close' ? 'Día cerrado: no se aceptan reservas online' : 'Día abierto otra vez');
    render();
  }

  /* ---------- Ficha y edición de reservas ---------- */
  function showReservation(id) {
    const r = S.res[id];
    if (!r) return;
    const btns = [];
    if (r.status === 'pendiente') {
      btns.push(`<button class="btn b-ok" data-s="confirmada">Confirmar</button>`, `<button class="btn btn-quiet" data-s="rechazada">Rechazar</button>`);
    } else if (r.status === 'confirmada') {
      btns.push(`<button class="btn btn-quiet" data-s="cancelada">Cancelar reserva</button>`);
    } else {
      btns.push(`<button class="btn btn-quiet" data-s="confirmada">Volver a confirmar</button>`);
    }
    openSheet(r.name, `
      <dl class="detail">
        <dt>Día</dt><dd>${esc(fmtDay(r.date))}</dd>
        <dt>Hora</dt><dd>${esc(r.time)}</dd>
        <dt>Personas</dt><dd>${r.people}</dd>
        <dt>Estado</dt><dd><span class="chip chip-${r.status}">${STATUS[r.status]}</span></dd>
        <dt>Teléfono</dt><dd>${esc(r.phone)}</dd>
        ${r.email ? `<dt>Email</dt><dd>${esc(r.email)}</dd>` : ''}
        ${r.notes ? `<dt>Notas</dt><dd>${esc(r.notes)}</dd>` : ''}
        <dt>Entró</dt><dd>${r.source === 'web' ? (r.customer ? 'Por la web, cliente con cuenta' : 'Por la web') : 'Apuntada a mano'}, ${esc(fmtShort(r.created_at.slice(0, 10)))} ${esc(r.created_at.slice(11, 16))}</dd>
      </dl>
      <div class="actions">
        <div class="actions-row">${btns.join('')}</div>
        <div class="actions-row">
          <a class="btn btn-line" href="${esc(telHref(r.phone))}">Llamar</a>
          <a class="btn btn-line" href="${esc(waHref(r.phone))}" target="_blank" rel="noopener">WhatsApp</a>
        </div>
        ${r.status === 'confirmada' && r.email === '' && r.source === 'web' ? '<p class="help">Este cliente no dejó email: avísale por teléfono o WhatsApp.</p>' : ''}
        <button class="btn btn-quiet" data-edit>Editar datos</button>
        <button class="btn btn-danger" data-del>Borrar reserva</button>
      </div>`, (body) => {
      $$('[data-s]', body).forEach((b) => b.addEventListener('click', async () => {
        busy(b, true, '…');
        try { await setStatus(r.id, b.dataset.s); closeSheet(); } catch (err) { toast(err.message, 'err'); busy(b, false); }
      }));
      $('[data-edit]', body).addEventListener('click', () => editReservation(r));
      $('[data-del]', body).addEventListener('click', async () => {
        if (!confirm('¿Borrar esta reserva para siempre? Si solo no vienen, mejor «Cancelar».')) return;
        try { await api('reservation_delete', { body: { id: r.id } }); closeSheet(); toast('Reserva borrada'); render(); }
        catch (err) { toast(err.message, 'err'); }
      });
    });
  }

  async function setStatus(id, status) {
    const r = await api('reservation_status', { body: { id, status } });
    S.res[id] = r;
    const msg = { confirmada: 'Reserva confirmada', rechazada: 'Reserva rechazada', cancelada: 'Reserva cancelada' }[status];
    toast(msg + (r.email && r.source === 'web' ? '. Le llega un email al cliente.' : ''));
    render();
    poll();
  }

  function editReservation(r, date) {
    const isNew = !r;
    r = r || { name: '', phone: '', email: '', date: date || todayISO(), time: '', people: 2, notes: '' };
    openSheet(isNew ? 'Nueva reserva' : 'Editar reserva', `
      <form class="form" id="res-edit" novalidate>
        <div class="row">
          <label>Día <input type="date" name="date" value="${esc(r.date)}" required></label>
          <label>Hora <input type="time" name="time" value="${esc(r.time)}" step="900" required list="slot-list"></label>
        </div>
        <datalist id="slot-list"></datalist>
        <label>Personas <input type="number" name="people" min="1" max="200" inputmode="numeric" value="${r.people}" required></label>
        <label>Nombre <input name="name" value="${esc(r.name)}" autocomplete="off" required></label>
        <label>Teléfono <input name="phone" type="tel" value="${esc(r.phone)}" required></label>
        <label>Email <span class="help">(opcional)</span><input name="email" type="email" value="${esc(r.email)}"></label>
        <label>Notas <textarea name="notes" rows="2">${esc(r.notes)}</textarea></label>
        <p class="help" id="slot-info"></p>
        <div class="actions"><button class="btn btn-main" type="submit">${isNew ? 'Guardar reserva' : 'Guardar cambios'}</button></div>
      </form>`, (body) => {
      const f = $('#res-edit', body);
      const info = $('#slot-info', body);
      const loadSlots = async () => {
        try {
          const d = await api('slots_admin&date=' + f.date.value);
          $('#slot-list', body).innerHTML = d.slots.map((s) => `<option value="${s.time}">${s.left} libres</option>`).join('');
          info.textContent = d.slots.length ? 'Plazas libres: ' + d.slots.map((s) => `${s.time} (${s.left})`).join(', ') : (d.reason || 'Ese día no hay horario de reservas.');
        } catch (e) { info.textContent = ''; }
      };
      f.date.addEventListener('change', loadSlots);
      loadSlots();
      f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $('button[type=submit]', f);
        const data = Object.fromEntries(new FormData(f));
        if (!isNew) data.id = r.id;
        busy(btn, true);
        try {
          await api('reservation_save', { body: data });
          closeSheet();
          toast(isNew ? 'Reserva apuntada' : 'Cambios guardados');
          render();
        } catch (err) { toast(err.message, 'err'); busy(btn, false); }
      });
    });
  }

  /* ---------- CARTA ---------- */
  async function viewCarta() {
    S.menu = await api('admin_menu');
    setFab('Nuevo plato', () => editDish(null));
    const cats = S.menu.categories;
    if (!cats.length) {
      return `<div class="empty"><p>La carta está vacía. Empieza creando una sección, por ejemplo «Entrantes».</p><button class="btn btn-main btn-sm" data-cat-new>Crear sección</button></div>`;
    }
    const names = Object.fromEntries(S.menu.allergens.map((a) => [a.key, a.name]));
    const off = cats.reduce((a, c) => a + c.dishes.filter((d) => !d.available).length, 0);
    let html = `<div class="toolbar"><button class="btn btn-quiet btn-sm" data-cat-new>Nueva sección</button>${S.base ? `<a class="btn btn-quiet btn-sm" href="${esc(S.base)}/#carta" target="_blank" rel="noopener">Ver en la web</a>` : ''}</div>`;
    if (off) html += `<p class="alert">${off} ${off === 1 ? 'plato está marcado' : 'platos están marcados'} como agotado y no ${off === 1 ? 'sale' : 'salen'} en la web.</p>`;
    html += '<p class="help" style="margin:4px 0 0;color:var(--granito)">El interruptor quita o pone un plato en la web al momento (por ejemplo, si se acaba).</p>';
    for (const c of cats) {
      html += `<section class="mcat"><div class="mcat-head"><h2>${esc(c.name)}</h2><button class="link-btn" data-cat="${c.id}">Editar sección</button></div>`;
      if (!c.dishes.length) html += '<p class="help" style="color:var(--granito)">Sin platos todavía.</p>';
      for (const d of c.dishes) {
        const al = d.allergens.map((k) => names[k]).filter(Boolean);
        html += `<div class="mdish${d.available ? '' : ' off'}">
          ${d.photo ? `<img class="md-img" src="${esc(d.photo)}" alt="" loading="lazy">` : `<div class="md-img">${ICON.plate}</div>`}
          <button class="md-body" type="button" data-dish="${d.id}">
            <p class="md-name">${esc(d.name)}</p>
            <p class="md-meta">${euro(d.price)}${d.featured ? ' · recomendado' : ''}${d.available ? '' : ' · <span class="md-off">agotado</span>'}</p>
            ${d.allergens.length ? `<p class="md-alg" aria-label="Alérgenos: ${esc(al.join(', '))}">${d.allergens.map((k) => alIcon(k, 20)).join('')}</p>` : ''}
          </button>
          <label class="sw" title="Disponible"><input type="checkbox" data-toggle="${d.id}" ${d.available ? 'checked' : ''} aria-label="${esc(d.name)} disponible"><span></span></label>
        </div>`;
      }
      html += '</section>';
    }
    return html;
  }

  function findDish(id) {
    for (const c of S.menu.categories) for (const d of c.dishes) if (d.id === id) return d;
    return null;
  }

  async function shrinkImage(file) {
    try {
      const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
      const scale = Math.min(1, 1600 / Math.max(bmp.width, bmp.height));
      const c = document.createElement('canvas');
      c.width = Math.round(bmp.width * scale);
      c.height = Math.round(bmp.height * scale);
      c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
      const blob = await new Promise((res) => c.toBlob(res, 'image/jpeg', 0.85));
      return blob || file;
    } catch (e) {
      return file;
    }
  }

  function editDish(d) {
    const isNew = !d;
    const cats = S.menu.categories;
    if (!cats.length) { editCategory(null); return; }
    d = d || { name: '', description: '', price: '', category_id: cats[0].id, allergens: [], photo: '', available: true, featured: false };
    let newPhoto = null;
    let removePhoto = false;
    openSheet(isNew ? 'Nuevo plato' : 'Editar plato', `
      <form class="form" id="dish-edit" novalidate>
        <div class="photo">
          ${d.photo ? `<img src="${esc(d.photo)}" alt="" id="ph-img">` : `<div class="ph" id="ph-img">${ICON.plate}</div>`}
          <div class="photo-btns">
            <span class="btn btn-quiet btn-sm file-btn">${d.photo ? 'Cambiar foto' : 'Añadir foto'}<input type="file" accept="image/*" id="ph-in" aria-label="Foto del plato"></span>
            ${d.photo ? '<button type="button" class="link-btn" id="ph-del">Quitar foto</button>' : ''}
          </div>
        </div>
        <label>Nombre <input name="name" value="${esc(d.name)}" required maxlength="120"></label>
        <div class="row">
          <label>Precio (€) <input name="price" inputmode="decimal" value="${d.price === '' ? '' : Number(d.price).toFixed(2).replace('.', ',')}" placeholder="12,50" required></label>
          <label>Sección <select name="category_id">${cats.map((c) => `<option value="${c.id}" ${c.id === d.category_id ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}</select></label>
        </div>
        <label>Descripción <span class="help">(opcional)</span><textarea name="description" rows="2" maxlength="400">${esc(d.description)}</textarea></label>
        <div class="field">Alérgenos
          <div class="chips">${S.menu.allergens.map((a) => `<label><input type="checkbox" name="allergens" value="${a.key}" ${d.allergens.includes(a.key) ? 'checked' : ''}><span>${alIcon(a.key, 22)}${esc(a.name)}</span></label>`).join('')}</div>
        </div>
        <label class="switch-row">Disponible (sale en la web) <span class="sw"><input type="checkbox" name="available" ${d.available ? 'checked' : ''}><span></span></span></label>
        <label class="switch-row">Recomendado de la casa <span class="sw"><input type="checkbox" name="featured" ${d.featured ? 'checked' : ''}><span></span></span></label>
        <div class="actions">
          <button class="btn btn-main" type="submit">${isNew ? 'Añadir a la carta' : 'Guardar cambios'}</button>
          ${isNew ? '' : `<div class="actions-row"><button type="button" class="btn btn-quiet btn-sm" data-move="-1">Subir</button><button type="button" class="btn btn-quiet btn-sm" data-move="1">Bajar</button></div>
          <button type="button" class="btn btn-danger" data-del>Borrar plato</button>`}
        </div>
      </form>`, (body) => {
      const f = $('#dish-edit', body);
      $('#ph-in', body).addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        newPhoto = await shrinkImage(file);
        removePhoto = false;
        const img = document.createElement('img');
        img.id = 'ph-img';
        img.alt = '';
        img.src = URL.createObjectURL(newPhoto);
        $('#ph-img', body).replaceWith(img);
      });
      const del = $('#ph-del', body);
      if (del) del.addEventListener('click', () => {
        removePhoto = true;
        newPhoto = null;
        $('#ph-img', body).outerHTML = `<div class="ph" id="ph-img">${ICON.plate}</div>`;
        del.remove();
      });
      f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $('button[type=submit]', f);
        const fd = new FormData();
        if (!isNew) fd.append('id', d.id);
        fd.append('name', f.name.value);
        fd.append('price', f.price.value);
        fd.append('category_id', f.category_id.value);
        fd.append('description', f.description.value);
        fd.append('allergens', $$('input[name=allergens]:checked', f).map((x) => x.value).join(','));
        fd.append('available', f.available.checked ? '1' : '0');
        fd.append('featured', f.featured.checked ? '1' : '0');
        if (removePhoto) fd.append('remove_photo', '1');
        if (newPhoto) fd.append('photo', newPhoto, 'foto.jpg');
        busy(btn, true, newPhoto ? 'Subiendo foto…' : 'Guardando…');
        try {
          await api('dish_save', { form: fd });
          closeSheet();
          toast(isNew ? 'Plato añadido a la carta' : 'Plato guardado');
          render();
        } catch (err) { toast(err.message, 'err'); busy(btn, false); }
      });
      $$('[data-move]', body).forEach((b) => b.addEventListener('click', async () => {
        await api('dish_move', { body: { id: d.id, dir: Number(b.dataset.move) } });
        toast(b.dataset.move === '-1' ? 'Plato subido' : 'Plato bajado');
        render();
      }));
      const dd = $('[data-del]', body);
      if (dd) dd.addEventListener('click', async () => {
        if (!confirm(`¿Borrar «${d.name}» de la carta? Si solo se ha acabado, mejor usa el interruptor de disponible.`)) return;
        try { await api('dish_delete', { body: { id: d.id } }); closeSheet(); toast('Plato borrado'); render(); }
        catch (err) { toast(err.message, 'err'); }
      });
    });
  }

  function editCategory(c) {
    const isNew = !c;
    openSheet(isNew ? 'Nueva sección' : 'Editar sección', `
      <form class="form" id="cat-edit" novalidate>
        <label>Nombre de la sección <input name="name" value="${esc(c ? c.name : '')}" placeholder="Entrantes, Postres, Vinos…" required maxlength="80"></label>
        <div class="actions">
          <button class="btn btn-main" type="submit">${isNew ? 'Crear sección' : 'Guardar'}</button>
          ${isNew ? '' : `<div class="actions-row"><button type="button" class="btn btn-quiet btn-sm" data-move="-1">Subir</button><button type="button" class="btn btn-quiet btn-sm" data-move="1">Bajar</button></div>
          <button type="button" class="btn btn-danger" data-del>Borrar sección</button>`}
        </div>
      </form>`, (body) => {
      const f = $('#cat-edit', body);
      f.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $('button[type=submit]', f);
        busy(btn, true);
        try {
          await api('category_save', { body: { id: c ? c.id : 0, name: f.name.value } });
          closeSheet();
          toast(isNew ? 'Sección creada' : 'Sección guardada');
          render();
        } catch (err) { toast(err.message, 'err'); busy(btn, false); }
      });
      $$('[data-move]', body).forEach((b) => b.addEventListener('click', async () => {
        await api('category_move', { body: { id: c.id, dir: Number(b.dataset.move) } });
        render();
      }));
      const dd = $('[data-del]', body);
      if (dd) dd.addEventListener('click', async () => {
        if (!confirm(`¿Borrar la sección «${c.name}»?`)) return;
        try { await api('category_delete', { body: { id: c.id } }); closeSheet(); toast('Sección borrada'); render(); }
        catch (err) { toast(err.message, 'err'); }
      });
    });
  }

  /* ---------- AJUSTES ---------- */
  async function viewAjustes() {
    const s = await loadSettings(true);
    const field = (k, label, type = 'text', extra = '') =>
      `<label>${label}<input name="${k}" type="${type}" value="${esc(s[k])}" ${extra}></label>`;
    const num = (k, label, help, min = 0) =>
      `<label>${label}<input name="${k}" type="number" inputmode="numeric" min="${min}" value="${esc(s[k])}">${help ? `<span class="help">${help}</span>` : ''}</label>`;
    const hoursRows = Object.keys(WEEK).map((d) => {
      const r = s.hours[d] || [];
      const rng = (i) => `<div class="range"><input type="time" data-h="${d}-${i}-0" value="${esc(r[i] ? r[i][0] : '')}" aria-label="${WEEK[d]} franja ${i + 1} desde"> a <input type="time" data-h="${d}-${i}-1" value="${esc(r[i] ? r[i][1] : '')}" aria-label="${WEEK[d]} franja ${i + 1} hasta"></div>`;
      return `<div class="hours-row"><span>${WEEK[d]}</span><div class="hours-day">
        <label class="switch-row" style="padding:6px 10px">Abre <span class="sw"><input type="checkbox" data-open="${d}" ${r.length ? 'checked' : ''}><span></span></span></label>
        <div data-ranges="${d}" ${r.length ? '' : 'hidden'}>${rng(0)}${rng(1)}<p class="help">Deja vacía la segunda franja si solo abres a una hora.</p></div>
      </div></div>`;
    }).join('');

    return `
      <form class="card form" data-form="reservas" novalidate>
        <h2>Reservas online</h2>
        <label class="switch-row">Confirmar automáticamente <span class="sw"><input type="checkbox" name="auto_confirm" ${s.auto_confirm ? 'checked' : ''}><span></span></span></label>
        <p class="help">Si está apagado, cada reserva llega «por confirmar» y la confirmas tú desde Hoy.</p>
        <div class="row">
          ${num('capacity', 'Comensales por franja', 'Máximo de personas que entran a la misma hora', 1)}
          ${num('slot_minutes', 'Cada cuántos minutos', 'Ej.: 30 → 13:00, 13:30…', 5)}
        </div>
        <div class="row">
          ${num('max_people', 'Máx. personas por reserva', 'Grupos más grandes, por teléfono', 1)}
          ${num('days_ahead', 'Días de antelación máx.', '', 1)}
        </div>
        <div class="row">
          ${num('min_notice', 'Antelación mínima (min)', 'Ej.: 60 = no se reserva con menos de 1 h')}
          ${num('last_before', 'Última reserva antes del cierre (min)', '')}
        </div>
        <button class="btn btn-main" type="submit">Guardar</button>
      </form>

      <form class="card form" data-form="politicas" novalidate>
        <h2>Políticas de reserva</h2>
        <p>Salen en la web y el cliente tiene que aceptarlas al reservar.</p>
        <div class="row">
          ${num('cancel_hours', 'Cancelación gratis hasta (horas antes)', 'Con cuenta, el cliente cancela solo hasta entonces')}
          ${num('late_minutes', 'Mesa guardada si llegan tarde (min)', '')}
        </div>
        <label>Otras normas <textarea name="policies" rows="5">${esc(s.policies)}</textarea><span class="help">Una por línea, con el formato «Título: texto». Ej.: «Mascotas: perros pequeños en la terraza».</span></label>
        <button class="btn btn-main" type="submit">Guardar políticas</button>
      </form>

      <form class="card form" data-form="horario" novalidate>
        <h2>Horario</h2>
        <p>Es el horario que sale en la web y en el que se puede reservar. Para cerrar un día suelto (festivo, vacaciones), hazlo desde el Calendario.</p>
        ${hoursRows}
        <button class="btn btn-main" type="submit">Guardar horario</button>
      </form>

      <form class="card form" data-form="datos" novalidate>
        <h2>Datos del restaurante</h2>
        ${field('name', 'Nombre', 'text', 'required')}
        ${field('tagline', 'Frase corta', 'text', 'placeholder="Cocina gallega de mercado"')}
        <label>Presentación <textarea name="description" rows="3">${esc(s.description)}</textarea></label>
        ${field('address', 'Dirección', 'text', 'placeholder="Rúa do Príncipe, 10"')}
        ${field('city', 'Código postal y ciudad', 'text', 'placeholder="36202 Vigo"')}
        <div class="row">${field('phone', 'Teléfono', 'tel')}${field('whatsapp', 'WhatsApp', 'tel')}</div>
        ${field('email', 'Email de contacto', 'email')}
        ${field('notify_email', 'Email para avisos de reservas', 'email')}
        ${field('instagram', 'Instagram', 'text', 'placeholder="@turestaurante"')}
        <h2 style="margin-top:8px">Datos legales</h2>
        ${field('owner_name', 'Titular (nombre o empresa)')}
        ${field('owner_nif', 'NIF / CIF')}
        <button class="btn btn-main" type="submit">Guardar datos</button>
      </form>

      <section class="card">
        <h2>Avisos de reservas nuevas</h2>
        <p>Con la app abierta (o en segundo plano) revisa cada 30 segundos si hay reservas nuevas y te avisa. Además, cada reserva llega por email a «${esc(s.notify_email || s.email || 'el email de avisos')}».</p>
        <button class="btn btn-line btn-sm" type="button" data-notif>Activar avisos en este móvil</button>
      </section>

      <form class="card form" data-form="password" novalidate>
        <h2>Cambiar contraseña</h2>
        <label>Contraseña actual <input name="current" type="password" autocomplete="current-password"></label>
        <label>Nueva contraseña <input name="new" type="password" autocomplete="new-password" minlength="8"><span class="help">Mínimo 8 caracteres</span></label>
        <button class="btn btn-dark" type="submit">Cambiar contraseña</button>
      </form>

      <section class="card">
        <h2>Sesión</h2>
        <p>Conectado a ${esc(S.base.replace(/^https?:\/\//, ''))} como ${esc(LS.get('user') || '')}.</p>
        <div class="actions-row">
          <a class="btn btn-quiet btn-sm" href="${esc(S.base)}/" target="_blank" rel="noopener">Ver la web</a>
          <button class="btn btn-danger btn-sm" type="button" data-logout>Cerrar sesión</button>
        </div>
      </section>`;
  }

  afterRender.ajustes = (view) => {
    $$('[data-open]', view).forEach((cb) => cb.addEventListener('change', () => {
      const box = $(`[data-ranges="${cb.dataset.open}"]`, view);
      box.hidden = !cb.checked;
      if (cb.checked && !$(`[data-h="${cb.dataset.open}-0-0"]`, view).value) {
        $(`[data-h="${cb.dataset.open}-0-0"]`, view).value = '13:00';
        $(`[data-h="${cb.dataset.open}-0-1"]`, view).value = '16:00';
      }
    }));
    $$('form[data-form]', view).forEach((f) => f.addEventListener('submit', (e) => saveSettingsForm(e, f)));
    $('[data-notif]', view).addEventListener('click', async () => {
      const ok = await askNotifications();
      toast(ok ? 'Avisos activados' : 'El móvil no ha dado permiso. Actívalo en Ajustes del móvil → Notificaciones.', ok ? '' : 'err');
    });
    $('[data-logout]', view).addEventListener('click', async () => {
      if (!confirm('¿Cerrar sesión en este móvil?')) return;
      try { await api('logout', { body: {} }); } catch (e) { /* da igual */ }
      logoutLocal();
    });
  };

  async function saveSettingsForm(e, f) {
    e.preventDefault();
    const btn = $('button[type=submit]', f);
    const kind = f.dataset.form;
    let body = {};
    if (kind === 'password') {
      busy(btn, true);
      try {
        await api('password', { body: { current: f.current.value, new: f.new.value } });
        f.reset();
        toast('Contraseña cambiada');
      } catch (err) { toast(err.message, 'err'); }
      busy(btn, false);
      return;
    }
    if (kind === 'horario') {
      const hours = {};
      for (let d = 1; d <= 7; d++) {
        hours[d] = [];
        if (!$(`[data-open="${d}"]`, f).checked) continue;
        for (let i = 0; i < 2; i++) {
          const a = $(`[data-h="${d}-${i}-0"]`, f).value;
          const b = $(`[data-h="${d}-${i}-1"]`, f).value;
          if (!a && !b) continue;
          if (!a || !b || b <= a) { toast(`Revisa el horario del ${WEEK[d].toLowerCase()}: la hora de cierre tiene que ser posterior a la de apertura.`, 'err'); return; }
          hours[d].push([a, b]);
        }
      }
      body = { hours };
    } else {
      $$('input[name], textarea[name]', f).forEach((el) => { body[el.name] = el.type === 'checkbox' ? el.checked : el.value; });
    }
    busy(btn, true);
    try {
      S.settings = await api('settings_save', { body });
      if (body.name) { S.restaurant = body.name; LS.set('restaurant', body.name); $('#bar-sub').textContent = body.name; }
      toast('Guardado. Ya se ve en la web.');
    } catch (err) { toast(err.message, 'err'); }
    busy(btn, false);
  }

  /* ---------- Clics generales ---------- */
  function onViewClick(e) {
    const t = e.target;
    if (t.closest('[data-retry]')) return render();
    const st = t.closest('[data-status]');
    if (st) {
      e.stopPropagation();
      busy(st, true, '…');
      setStatus(Number(st.dataset.id), st.dataset.status).catch((err) => { toast(err.message, 'err'); busy(st, false); });
      return;
    }
    if (t.closest('a')) return;
    const open = t.closest('[data-open]');
    if (open && open.classList.contains('res')) return showReservation(Number(open.dataset.open));
    const nw = t.closest('[data-new]');
    if (nw) return editReservation(null, nw.dataset.new);
    const day = t.closest('[data-day]');
    if (day) { S.calDay = day.dataset.day; return render(); }
    const mon = t.closest('[data-month]');
    if (mon) {
      const [y, m] = S.calMonth.split('-').map(Number);
      const d = new Date(y, m - 1 + Number(mon.dataset.month), 1, 12);
      S.calMonth = iso(d).slice(0, 7);
      S.calDay = S.calMonth === todayISO().slice(0, 7) ? todayISO() : iso(d);
      return render();
    }
    if (t.closest('[data-goto-today]')) { S.calDay = todayISO(); S.calMonth = S.calDay.slice(0, 7); return render(); }
    const cd = t.closest('[data-close-day]');
    if (cd) return toggleClosedDay(cd.dataset.closeDay).catch((err) => toast(err.message, 'err'));
    const dish = t.closest('[data-dish]');
    if (dish) return editDish(findDish(Number(dish.dataset.dish)));
    if (t.closest('[data-cat-new]')) return editCategory(null);
    const cat = t.closest('[data-cat]');
    if (cat) return editCategory(S.menu.categories.find((c) => c.id === Number(cat.dataset.cat)));
  }

  async function onViewChange(e) {
    const tg = e.target.closest('[data-toggle]');
    if (!tg) return;
    const id = Number(tg.dataset.toggle);
    const on = tg.checked;
    tg.disabled = true;
    try {
      const d = await api('dish_toggle', { body: { id, available: on } });
      toast(on ? `«${d.name}» vuelve a salir en la web` : `«${d.name}» marcado como agotado`);
      render();
    } catch (err) {
      tg.checked = !on;
      tg.disabled = false;
      toast(err.message, 'err');
    }
  }

  /* ---------- Arranque ---------- */
  function boot() {
    if (!S.base) return renderSetup();
    if (!S.token) return renderLogin();
    startApp();
  }

  setInterval(poll, 30000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden && S.token) { poll(); if (S.tab === 'hoy') render(); } });

  if (!NATIVE && 'serviceWorker' in navigator && location.protocol === 'https:') {
    navigator.serviceWorker.register('sw.js').catch(() => { /* sin modo offline */ });
  }
  boot();
})();
