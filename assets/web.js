(function () {
  'use strict';
  var R = window.RESTAURANT || {};
  var form = document.getElementById('res-form');
  var dateIn = document.getElementById('f-date');
  var peopleIn = document.getElementById('f-people');
  var timeIn = document.getElementById('f-time');
  var slotMsg = document.getElementById('slot-msg');
  var formMsg = document.getElementById('form-msg');
  var submit = document.getElementById('f-submit');
  var slots = [];

  function addDays(iso, n) {
    var d = new Date(iso + 'T12:00:00');
    d.setDate(d.getDate() + n);
    return d.toISOString().slice(0, 10);
  }

  function api(route, opts) {
    return fetch('api/?r=' + route, opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Respuesta no válida del servidor' }; });
    });
  }

  function renderTimes() {
    var people = parseInt(peopleIn.value, 10) || 1;
    var prev = timeIn.value;
    timeIn.innerHTML = '';
    var free = slots.filter(function (s) { return s.left >= people; });
    if (!slots.length) {
      timeIn.innerHTML = '<option value="">Sin horas</option>';
      timeIn.disabled = true;
      return;
    }
    if (!free.length) {
      timeIn.innerHTML = '<option value="">Completo</option>';
      timeIn.disabled = true;
      slotMsg.textContent = 'No queda sitio para ' + people + ' personas ese día. Prueba otro día o llámanos.';
      slotMsg.className = 'slot-msg warn';
      return;
    }
    timeIn.disabled = false;
    timeIn.insertAdjacentHTML('beforeend', '<option value="">Elige hora</option>');
    slots.forEach(function (s) {
      var o = document.createElement('option');
      o.value = s.time;
      o.textContent = s.time + (s.left < people ? ' (completo)' : '');
      o.disabled = s.left < people;
      timeIn.appendChild(o);
    });
    if (free.some(function (s) { return s.time === prev; })) timeIn.value = prev;
    slotMsg.textContent = '';
    slotMsg.className = 'slot-msg';
  }

  function loadSlots() {
    if (!dateIn.value) return;
    timeIn.disabled = true;
    timeIn.innerHTML = '<option>Cargando…</option>';
    slotMsg.textContent = '';
    api('slots&date=' + encodeURIComponent(dateIn.value)).then(function (res) {
      if (!res.ok) {
        slots = [];
        slotMsg.textContent = res.error;
        slotMsg.className = 'slot-msg warn';
        renderTimes();
        return;
      }
      slots = res.data.slots;
      renderTimes();
      if (!res.data.open && res.data.reason) {
        slotMsg.textContent = res.data.reason + '.';
        slotMsg.className = 'slot-msg warn';
      }
    }).catch(function () {
      slotMsg.textContent = 'No se pudieron cargar las horas. Revisa tu conexión.';
      slotMsg.className = 'slot-msg warn';
    });
  }

  if (form) {
    dateIn.min = R.today;
    dateIn.max = addDays(R.today, R.daysAhead || 60);
    dateIn.value = R.today;
    dateIn.addEventListener('change', loadSlots);
    peopleIn.addEventListener('change', renderTimes);
    loadSlots();

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      formMsg.textContent = '';
      formMsg.className = 'form-msg';
      var fd = new FormData(form);
      var data = {};
      fd.forEach(function (v, k) { data[k] = v; });
      var missing = [];
      if (!data.date) missing.push('el día');
      if (!data.time) missing.push('la hora');
      if (!String(data.name || '').trim()) missing.push('tu nombre');
      if (!String(data.phone || '').trim()) missing.push('un teléfono');
      if (missing.length) {
        formMsg.textContent = 'Falta ' + missing.join(', ') + '.';
        formMsg.className = 'form-msg error';
        return;
      }
      if (!data.privacy) {
        formMsg.textContent = 'Marca la casilla de la política de privacidad para continuar.';
        formMsg.className = 'form-msg error';
        return;
      }
      submit.disabled = true;
      submit.textContent = 'Enviando…';
      api('reserve', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).then(function (res) {
        if (!res.ok) throw new Error(res.error);
        form.innerHTML = '<div class="res-done"><h3>' +
          (res.data.status === 'confirmada' ? 'Reserva confirmada' : 'Reserva recibida') +
          '</h3><p></p><button type="button" class="btn btn-line" id="again">Hacer otra reserva</button></div>';
        form.querySelector('p').textContent = res.data.message;
        document.getElementById('again').addEventListener('click', function () { location.reload(); });
        form.scrollIntoView({ block: 'center' });
      }).catch(function (err) {
        formMsg.textContent = err.message || 'No se pudo enviar. Revisa tu conexión e inténtalo otra vez.';
        formMsg.className = 'form-msg error';
        submit.disabled = false;
        submit.textContent = 'Reservar mesa';
        loadSlots();
      });
    });
  }

  var mapBtn = document.getElementById('map-btn');
  if (mapBtn) {
    mapBtn.addEventListener('click', function () {
      var box = document.getElementById('map');
      var f = document.createElement('iframe');
      f.src = 'https://www.google.com/maps?q=' + encodeURIComponent(box.dataset.q) + '&output=embed';
      f.loading = 'lazy';
      f.title = 'Mapa';
      f.referrerPolicy = 'no-referrer-when-downgrade';
      box.innerHTML = '';
      box.appendChild(f);
    });
  }
})();
