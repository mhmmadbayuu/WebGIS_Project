// ================= CONFIG =================
const INITIAL_CENTER = [-0.0263, 109.3425];
const INITIAL_ZOOM = 13;

// ================= MAP =================
const map = L.map('map').setView(INITIAL_CENTER, INITIAL_ZOOM);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 20,
  attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// ================= GROUPS =================
const editableGroup = L.featureGroup().addTo(map); // pending draw layer (unsaved)
const points24Layer = L.layerGroup().addTo(map);
const pointsNon24Layer = L.layerGroup().addTo(map);
const jalanLayer = L.layerGroup().addTo(map);
const parsilLayer = L.layerGroup().addTo(map);
const pemukimanLayer = L.layerGroup().addTo(map);
const rumahIbadahLayer = L.layerGroup().addTo(map);
const rumahIbadahRadiusLayer = L.layerGroup().addTo(map);
const sektorLayer = L.layerGroup().addTo(map);

// ================= STATE =================
const state = {
  featureType: null, // point | jalan | parsil
  currentLayer: null, // LatLng | L.Layer
  pendingLayer: null, // L.Layer (polyline/polygon) before saved
  activeDrawer: null,
  pointFilter: 'all', // all | 24 | non24
  pointsData: [],
  pemukimanData: [],
  rumahIbadahData: [],
  counts: { points: 0, jalan: 0, parsil: 0, pemukiman: 0, rumahIbadah: 0, sektor: 0 },
  indices: {
    points: new Map(),
    jalan: new Map(),
    parsil: new Map(),
    pemukiman: new Map(),
    rumahIbadah: new Map(),
    sektor: new Map()
  },
  selected: {
    points: null,
    jalan: null,
    parsil: null,
    pemukiman: null,
    rumahIbadah: null
  },
  editing: null, // { type, id, layer, backup } for Edit Data (modal)
  locationEditing: null // { type, id, layer, backup, attrs, translate, vertexDeleteMode }
};

const warned = new Set();
function warnOnce(key, message) {
  if (warned.has(key)) return;
  warned.add(key);
  showToast(message, 'error', 6500);
}

// ================= DRAW (kept, but toolbar is hidden via CSS) =================
const drawControl = new L.Control.Draw({
  draw: {
    polyline: true,
    polygon: true,
    marker: false,
    rectangle: false,
    circle: false,
    circlemarker: false
  },
  edit: { featureGroup: editableGroup }
});
map.addControl(drawControl);

// ================= DOM HELPERS =================
const el = {
  modal: document.getElementById('featureModal'),
  modalTitle: document.getElementById('modalTitle'),
  modeInfo: document.getElementById('modeInfo'),
  toast: document.getElementById('toast'),
  countPoints: document.getElementById('countPoints'),
  countJalan: document.getElementById('countJalan'),
  countParsil: document.getElementById('countParsil'),
  countRumahIbadah: document.getElementById('countRumahIbadah'),
  countPemukiman: document.getElementById('countPemukiman'),
  countSektor: document.getElementById('countSektor'),
  togglePoints: document.getElementById('togglePoints'),
  toggleJalan: document.getElementById('toggleJalan'),
  toggleParsil: document.getElementById('toggleParsil'),
  toggleRumahIbadah: document.getElementById('toggleRumahIbadah'),
  togglePemukiman: document.getElementById('togglePemukiman'),
  toggleSektor: document.getElementById('toggleSektor'),
  pointsList: document.getElementById('pointsList'),
  jalanList: document.getElementById('jalanList'),
  parsilList: document.getElementById('parsilList'),
  rumahIbadahList: document.getElementById('rumahIbadahList'),
  pemukimanList: document.getElementById('pemukimanList'),
  sektorList: document.getElementById('sektorList'),
  btnModePoint: document.getElementById('btnModePoint'),
  btnModeJalan: document.getElementById('btnModeJalan'),
  btnModeParsil: document.getElementById('btnModeParsil'),
  btnModeRumahIbadah: document.getElementById('btnModeRumahIbadah'),
  btnModePemukiman: document.getElementById('btnModePemukiman'),
  btnResetMode: document.getElementById('btnResetMode'),
  btnResetView: document.getElementById('btnResetView'),
  btnMyLocation: document.getElementById('btnMyLocation'),
  btnFilter24: document.getElementById('btnFilter24'),
  btnFilterNon24: document.getElementById('btnFilterNon24'),
  editBar: document.getElementById('editBar'),
  editBarTitle: document.getElementById('editBarTitle'),
  btnSaveLocation: document.getElementById('btnSaveLocation'),
  btnCancelLocation: document.getElementById('btnCancelLocation'),
  btnDeleteVertex: document.getElementById('btnDeleteVertex'),
  pointFields: document.getElementById('pointFields'),
  roadFields: document.getElementById('roadFields'),
  parsilFields: document.getElementById('parsilFields'),
  rumahIbadahFields: document.getElementById('rumahIbadahFields'),
  pemukimanFields: document.getElementById('pemukimanFields'),
  featureForm: document.getElementById('featureForm'),
  formNotice: document.getElementById('formNotice'),
  btnSubmitFeature: document.getElementById('btnSubmitFeature'),
  statusJalan: document.getElementById('statusJalan'),
  statusParsil: document.getElementById('statusParsil'),
  namaPoint: document.getElementById('namaPoint'),
  noPoint: document.getElementById('noPoint'),
  deskripsiPoint: document.getElementById('deskripsiPoint'),
  status24Jam: document.getElementById('status24Jam'),
  latPoint: document.getElementById('latPoint'),
  lngPoint: document.getElementById('lngPoint'),
  namaJalan: document.getElementById('namaJalan'),

  // Rumah Ibadah
  namaRumahIbadah: document.getElementById('namaRumahIbadah'),
  jenisRumahIbadah: document.getElementById('jenisRumahIbadah'),
  kontakRumahIbadah: document.getElementById('kontakRumahIbadah'),
  radiusRumahIbadah: document.getElementById('radiusRumahIbadah'),
  alamatRumahIbadah: document.getElementById('alamatRumahIbadah'),
  latRumahIbadah: document.getElementById('latRumahIbadah'),
  lngRumahIbadah: document.getElementById('lngRumahIbadah'),

  // Pemukiman Miskin
  kkNamaPemukiman: document.getElementById('kkNamaPemukiman'),
  nikPemukiman: document.getElementById('nikPemukiman'),
  jumlahAnggotaPemukiman: document.getElementById('jumlahAnggotaPemukiman'),
  statusBantuanPemukiman: document.getElementById('statusBantuanPemukiman'),
  jenisBantuanPemukiman: document.getElementById('jenisBantuanPemukiman'),
  tanggalBantuanPemukiman: document.getElementById('tanggalBantuanPemukiman'),
  alamatPemukiman: document.getElementById('alamatPemukiman'),
  kelurahanPemukiman: document.getElementById('kelurahanPemukiman'),
  kecamatanPemukiman: document.getElementById('kecamatanPemukiman'),
  latPemukiman: document.getElementById('latPemukiman'),
  lngPemukiman: document.getElementById('lngPemukiman')
};

let toastTimer = null;
function showToast(message, type = 'info', timeoutMs = 3500) {
  if (!el.toast) return;
  if (!el.toast.__dismissBound) {
    el.toast.__dismissBound = true;
    el.toast.title = 'Klik untuk menutup';
    el.toast.addEventListener('click', () => {
      el.toast.classList.add('hidden');
    });
  }
  if (toastTimer) window.clearTimeout(toastTimer);

  el.toast.classList.remove('hidden', 'toast-success', 'toast-error', 'toast-info');
  el.toast.classList.add(`toast-${type}`);
  el.toast.textContent = message;

  toastTimer = window.setTimeout(() => {
    el.toast.classList.add('hidden');
  }, timeoutMs);
}

function setFormNotice(message, type = 'info') {
  if (!el.formNotice) return;
  el.formNotice.textContent = message || '';
  el.formNotice.classList.remove('hidden');
  el.formNotice.style.borderColor =
    type === 'error' ? '#fecaca' :
    type === 'success' ? '#bbf7d0' :
    '#bfdbfe';
  el.formNotice.style.background =
    type === 'error' ? '#fef2f2' :
    type === 'success' ? '#f0fdf4' :
    '#eff6ff';
  el.formNotice.style.color =
    type === 'error' ? '#991b1b' :
    type === 'success' ? '#166534' :
    '#1d4ed8';
}

function clearFormNotice() {
  if (!el.formNotice) return;
  el.formNotice.textContent = '';
  el.formNotice.classList.add('hidden');
}

function setSubmitting(isSubmitting) {
  if (!el.btnSubmitFeature) return;
  el.btnSubmitFeature.disabled = Boolean(isSubmitting);
  el.btnSubmitFeature.textContent = isSubmitting ? 'Menyimpan...' : 'Simpan Data';
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function parseApiJson(response) {
  // Support both {success:true} and {status:"success"} styles.
  const success = Boolean(response?.success) || response?.status === 'success';
  const message =
    response?.message ||
    (success ? 'Berhasil' : 'Gagal memproses permintaan');
  return { success, message };
}

async function fetchMaybeJson(url, options) {
  const res = await fetch(url, options);
  const text = await res.text();
  try {
    return { res, json: JSON.parse(text), text };
  } catch {
    return { res, json: null, text };
  }
}

function getJalanColor(status) {
  if (status === 'Nasional') return '#ef4444';
  if (status === 'Provinsi') return '#3b82f6';
  if (status === 'Kabupaten') return '#22c55e';
  return '#64748b';
}

function getParsilColor(status) {
  if (status === 'SHM') return '#f59e0b';
  if (status === 'HGB') return '#8b5cf6';
  if (status === 'HGU') return '#14b8a6';
  if (status === 'HP') return '#94a3b8';
  return '#cbd5e1';
}

function makeSpbuIcon(is24) {
  const dotClass = is24 ? 'spbu-dot spbu-dot--green' : 'spbu-dot spbu-dot--red';
  return L.divIcon({
    className: 'spbu-icon',
    html: `<div class="${dotClass}"></div>`,
    iconSize: [18, 18],
    iconAnchor: [9, 9],
    popupAnchor: [0, -8]
  });
}

function makePemukimanIcon({ isEdit = false, isCovered = false } = {}) {
  // Keep it distinct from SPBU but not too big/flashy.
  const size = isEdit ? 34 : 26;
  const inner = isEdit ? 20 : 16;
  const hitClass = isCovered ? 'pm-hit pm-hit--green' : 'pm-hit pm-hit--red';
  const html = `
    <div class="${hitClass}" style="--pm-size:${size}px;--pm-inner:${inner}px" aria-hidden="true">
      <svg class="pm-svg" viewBox="0 0 24 24" width="${inner}" height="${inner}" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M4 11.5L12 5l8 6.5" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M6.5 10.8V20h11V10.8" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M10 20v-6h4v6" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
  `;

  return L.divIcon({
    className: 'pm-icon',
    html,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    popupAnchor: [0, -size / 2]
  });
}

function makeRumahIbadahIcon() {
  // Simple + recognizable icon (not too "perfect").
  const size = 24;
  const html = `
    <div class="ri-hit" aria-hidden="true">
      <svg class="ri-svg" viewBox="0 0 24 24" width="14" height="14" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M12 4v16" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
        <path d="M4 12h16" stroke="#ffffff" stroke-width="2" stroke-linecap="round"/>
      </svg>
    </div>
  `;

  return L.divIcon({
    className: 'ri-icon',
    html,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
    popupAnchor: [0, -size / 2]
  });
}

function computePemukimanCoverage(latlng) {
  if (!latlng || !Array.isArray(state.rumahIbadahData) || state.rumahIbadahData.length === 0) {
    return { covered: null, rumahIbadahId: null, jarakMeter: null };
  }

  let best = null;
  for (const row of state.rumahIbadahData) {
    const lat = Number(row?.latitude);
    const lng = Number(row?.longitude);
    const radius = Number(row?.radius_meter);
    if (!Number.isFinite(lat) || !Number.isFinite(lng) || !Number.isFinite(radius) || radius <= 0) continue;

    const d = map.distance(latlng, L.latLng(lat, lng));
    if (d <= radius) {
      if (!best || d < best.jarakMeter) {
        best = { rumahIbadahId: row?.id ?? null, jarakMeter: d };
      }
    }
  }

  if (!best) return { covered: false, rumahIbadahId: null, jarakMeter: null };
  return { covered: true, rumahIbadahId: best.rumahIbadahId, jarakMeter: best.jarakMeter };
}



function updateStats() {
  if (el.countPoints) el.countPoints.textContent = state.counts.points;
  if (el.countJalan) el.countJalan.textContent = state.counts.jalan;
  if (el.countParsil) el.countParsil.textContent = state.counts.parsil;
  if (el.countRumahIbadah) el.countRumahIbadah.textContent = state.counts.rumahIbadah;
  if (el.countPemukiman) el.countPemukiman.textContent = state.counts.pemukiman;
  if (el.countSektor) el.countSektor.textContent = state.counts.sektor;

  // Robust update for any additional stat cards (e.g., Rumah Ibadah, Pemukiman Miskin)
  try {
    const cards = document.querySelectorAll('.stat-card');
    for (const card of cards) {
      const labelEl = card.querySelector('.stat-label');
      const valueEl = card.querySelector('.stat-value');
      const label = String(labelEl?.textContent ?? '').trim().toLowerCase();
      if (!label || !valueEl) continue;

      if (label.includes('rumah') && label.includes('ibadah')) {
        valueEl.textContent = String(state.counts.rumahIbadah ?? 0);
      }
      if (label.includes('pemukiman')) {
        valueEl.textContent = String(state.counts.pemukiman ?? 0);
      }
    }
  } catch {
    // ignore
  }
}

function isSpbu24(row) {
  const v = row?.status_24jam;
  if (v === true) return true;
  if (typeof v === 'number') return v === 1;

  const s = String(v ?? '').trim().toLowerCase();
  if (!s) return false;
  if (s === '1' || s === 'ya' || s === 'yes') return true;
  if (s === '0' || s === 'tidak' || s === 'no') return false;

  const has24 = s.includes('24');
  const isNeg = s.includes('tidak') || s.includes('non');
  if (has24 && isNeg) return false;
  if (has24) return true;
  return false;
}

function applyPointFilter() {
  const mode = state.pointFilter || 'all';
  if (el.btnFilter24) el.btnFilter24.classList.toggle('is-active', mode === '24');
  if (el.btnFilterNon24) el.btnFilterNon24.classList.toggle('is-active', mode === 'non24');

  const shouldShow = (row) => {
    if (mode === '24') return isSpbu24(row);
    if (mode === 'non24') return !isSpbu24(row);
    return true;
  };

  // Map visibility is handled by applyLayerVisibility based on state.pointFilter
  applyLayerVisibility();

  const filteredRows = Array.isArray(state.pointsData)
    ? state.pointsData.filter((row) => shouldShow(row))
    : [];
  renderPointsList(filteredRows);
}

function togglePointFilter(next) {
  state.pointFilter = state.pointFilter === next ? 'all' : next;
  if (el.togglePoints) el.togglePoints.checked = true;
  applyLayerVisibility();
  applyPointFilter();

  const rows = Array.isArray(state.pointsData) ? state.pointsData : [];
  const n24 = rows.filter((r) => isSpbu24(r)).length;
  const nNon = rows.length - n24;
  if (state.pointFilter === '24') showToast(`Filter: SPBU 24 Jam (${n24})`, 'info', 2200);
  else if (state.pointFilter === 'non24') showToast(`Filter: SPBU Tidak 24 Jam (${nNon})`, 'info', 2200);
  else showToast(`Filter: Semua SPBU (${rows.length})`, 'info', 1800);
}

function applyLayerVisibility() {
  if (el.togglePoints) {
    if (!el.togglePoints.checked) {
      map.removeLayer(points24Layer);
      map.removeLayer(pointsNon24Layer);
    } else {
      const mode = state.pointFilter || 'all';
      if (mode === '24') {
        points24Layer.addTo(map);
        map.removeLayer(pointsNon24Layer);
      } else if (mode === 'non24') {
        pointsNon24Layer.addTo(map);
        map.removeLayer(points24Layer);
      } else {
        points24Layer.addTo(map);
        pointsNon24Layer.addTo(map);
      }
    }
  }

  if (el.toggleJalan) {
    if (el.toggleJalan.checked) jalanLayer.addTo(map);
    else map.removeLayer(jalanLayer);
  }

  if (el.toggleParsil) {
    if (el.toggleParsil.checked) parsilLayer.addTo(map);
    else map.removeLayer(parsilLayer);
  }

  if (el.toggleRumahIbadah) {
    if (el.toggleRumahIbadah.checked) {
      rumahIbadahLayer.addTo(map);
      rumahIbadahRadiusLayer.addTo(map);
    } else {
      map.removeLayer(rumahIbadahLayer);
      map.removeLayer(rumahIbadahRadiusLayer);
    }
  }

  if (el.togglePemukiman) {
    if (el.togglePemukiman.checked) pemukimanLayer.addTo(map);
    else map.removeLayer(pemukimanLayer);
  }

  if (el.toggleSektor) {
    if (el.toggleSektor.checked) sektorLayer.addTo(map);
    else map.removeLayer(sektorLayer);
  }

  updateStats();
}

function setActiveModeButton(type) {
  const buttons = [
    el.btnModePoint,
    el.btnModeJalan,
    el.btnModeParsil,
    el.btnModeRumahIbadah,
    el.btnModePemukiman
  ].filter(Boolean);
  for (const btn of buttons) btn.classList.remove('is-active');
  if (type === 'point' && el.btnModePoint) el.btnModePoint.classList.add('is-active');
  if (type === 'jalan' && el.btnModeJalan) el.btnModeJalan.classList.add('is-active');
  if (type === 'parsil' && el.btnModeParsil) el.btnModeParsil.classList.add('is-active');
  if (type === 'rumahIbadah' && el.btnModeRumahIbadah) el.btnModeRumahIbadah.classList.add('is-active');
  if (type === 'pemukiman' && el.btnModePemukiman) el.btnModePemukiman.classList.add('is-active');
}

function updateModeInfo() {
  if (!el.modeInfo) return;

  if (state.locationEditing?.type === 'point') {
    el.modeInfo.textContent = 'Mode: Edit Lokasi Point (geser marker)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.locationEditing?.type === 'jalan') {
    el.modeInfo.textContent = 'Mode: Edit Lokasi Jalan (drag garis / geser vertex)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.locationEditing?.type === 'parsil') {
    el.modeInfo.textContent = 'Mode: Edit Lokasi Parsil (drag area / geser vertex)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.locationEditing?.type === 'pemukiman') {
    el.modeInfo.textContent = 'Mode: Edit Lokasi Pemukiman Miskin (geser marker)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.locationEditing?.type === 'rumahIbadah') {
    el.modeInfo.textContent = 'Mode: Edit Lokasi Rumah Ibadah (geser marker)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.editing?.type === 'point') {
    el.modeInfo.textContent = 'Mode: Edit Point (geser marker / ubah atribut)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.editing?.type === 'jalan') {
    el.modeInfo.textContent = 'Mode: Edit Jalan (geser titik garis)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.editing?.type === 'parsil') {
    el.modeInfo.textContent = 'Mode: Edit Parsil (geser titik poligon)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.editing?.type === 'rumahIbadah') {
    el.modeInfo.textContent = 'Mode: Edit Rumah Ibadah (geser marker / ubah atribut)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.editing?.type === 'pemukiman') {
    el.modeInfo.textContent = 'Mode: Edit Pemukiman Miskin (geser marker / ubah atribut)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.featureType === 'point') {
    el.modeInfo.textContent = 'Mode: Tambah Point (klik peta)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.featureType === 'jalan') {
    el.modeInfo.textContent = 'Mode: Tambah Jalan (gambar garis)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.featureType === 'parsil') {
    el.modeInfo.textContent = 'Mode: Tambah Parsil (gambar poligon)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.featureType === 'rumahIbadah') {
    el.modeInfo.textContent = 'Mode: Tambah Rumah Ibadah (klik peta)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  if (state.featureType === 'pemukiman') {
    el.modeInfo.textContent = 'Mode: Tambah Pemukiman Miskin (klik peta)';
    if (el.btnResetMode) el.btnResetMode.disabled = false;
    return;
  }

  el.modeInfo.textContent = 'Mode: Tidak aktif';
  if (el.btnResetMode) el.btnResetMode.disabled = true;
}

function clearPendingLayer() {
  if (state.pendingLayer) {
    editableGroup.removeLayer(state.pendingLayer);
    state.pendingLayer = null;
  }
  state.currentLayer = null;
}

function showSection(type) {
  const setEnabled = (section, enabled) => {
    if (!section) return;
    section.querySelectorAll('input, textarea, select, button').forEach((node) => {
      node.disabled = !enabled;
    });
  };

  el.pointFields?.classList.add('hidden');
  el.roadFields?.classList.add('hidden');
  el.parsilFields?.classList.add('hidden');
  el.rumahIbadahFields?.classList.add('hidden');
  el.pemukimanFields?.classList.add('hidden');
  setEnabled(el.pointFields, false);
  setEnabled(el.roadFields, false);
  setEnabled(el.parsilFields, false);
  setEnabled(el.rumahIbadahFields, false);
  setEnabled(el.pemukimanFields, false);

  if (type === 'point') {
    el.pointFields?.classList.remove('hidden');
    setEnabled(el.pointFields, true);
  }
  if (type === 'jalan') {
    el.roadFields?.classList.remove('hidden');
    setEnabled(el.roadFields, true);
  }
  if (type === 'parsil') {
    el.parsilFields?.classList.remove('hidden');
    setEnabled(el.parsilFields, true);
  }

  if (type === 'rumahIbadah') {
    el.rumahIbadahFields?.classList.remove('hidden');
    setEnabled(el.rumahIbadahFields, true);
  }

  if (type === 'pemukiman') {
    el.pemukimanFields?.classList.remove('hidden');
    setEnabled(el.pemukimanFields, true);
  }
}

function openModal() {
  if (!el.modal) return;

  if (state.featureType === 'point') el.modalTitle.textContent = 'Input Data Point SPBU';
  if (state.featureType === 'jalan') el.modalTitle.textContent = 'Input Data Jalan';
  if (state.featureType === 'parsil') el.modalTitle.textContent = 'Input Data Parsil';
  if (state.featureType === 'rumahIbadah') el.modalTitle.textContent = 'Input Data Rumah Ibadah';
  if (state.featureType === 'pemukiman') el.modalTitle.textContent = 'Input Data Pemukiman Miskin';

  clearFormNotice();
  setSubmitting(false);
  if (el.btnSubmitFeature) {
    el.btnSubmitFeature.textContent = state.editing ? 'Update Data' : 'Simpan Data';
  }
  showSection(state.featureType);
  el.modal.classList.remove('hidden');

  // focus first input of the active section
  if (state.featureType === 'point') el.namaPoint?.focus();
  if (state.featureType === 'jalan') el.namaJalan?.focus();
  if (state.featureType === 'parsil') el.statusParsil?.focus();
  if (state.featureType === 'rumahIbadah') el.namaRumahIbadah?.focus();
  if (state.featureType === 'pemukiman') el.kkNamaPemukiman?.focus();
}

function backupLayerGeometry(layer) {
  if (layer?.getLatLng && layer?.setLatLng) {
    const ll = layer.getLatLng();
    return { kind: 'marker', latlng: [ll.lat, ll.lng] };
  }

  if (layer?.getLatLngs && layer?.setLatLngs) {
    const toTuple = (latlng) => [latlng.lat, latlng.lng];
    const clone = (latlngs) =>
      Array.isArray(latlngs)
        ? latlngs.map((v) => (Array.isArray(v) ? clone(v) : toTuple(v)))
        : latlngs;
    return { kind: 'path', latlngs: clone(layer.getLatLngs()) };
  }

  return { kind: 'unknown' };
}

function restoreLayerGeometry(layer, backup) {
  if (!layer || !backup) return;

  if (backup.kind === 'marker' && Array.isArray(backup.latlng)) {
    layer.setLatLng(L.latLng(backup.latlng[0], backup.latlng[1]));
    return;
  }

  if (backup.kind === 'path' && backup.latlngs && layer.setLatLngs) {
    const toLatLng = (pair) => L.latLng(pair[0], pair[1]);
    const rebuild = (latlngs) =>
      Array.isArray(latlngs)
        ? latlngs.map((v) => (Array.isArray(v) && typeof v[0] === 'number' ? toLatLng(v) : rebuild(v)))
        : latlngs;
    layer.setLatLngs(rebuild(backup.latlngs));
  }
}

function enableLayerEditing(layer) {
  if (!layer) return;
  if (layer.dragging && layer.dragging.enable) {
    layer.dragging.enable();
  }
  if (layer.editing && layer.editing.enable) {
    layer.editing.enable();
  }
}

function disableLayerEditing(layer) {
  if (!layer) return;
  if (layer.dragging && layer.dragging.disable) {
    layer.dragging.disable();
  }
  if (layer.editing && layer.editing.disable) {
    layer.editing.disable();
  }
}

function startEditPoint(id, marker, row) {
  resetMode();
  const backup = backupLayerGeometry(marker);
  backup.is24 = Number(row?.status_24jam) === 1;
  state.editing = { type: 'point', id: Number(id), layer: marker, backup };
  state.featureType = 'point';
  state.currentLayer = null;

  el.namaPoint.value = row?.nama ?? '';
  el.noPoint.value = row?.no ?? '';
  el.deskripsiPoint.value = row?.deskripsi ?? '';
  el.status24Jam.checked = Number(row?.status_24jam) === 1;

  const ll = marker.getLatLng();
  el.latPoint.value = ll.lat;
  el.lngPoint.value = ll.lng;

  marker.setIcon(makeSpbuIcon(el.status24Jam.checked));
  if (marker.__spbuDragHandler) {
    marker.off('drag', marker.__spbuDragHandler);
    marker.off('dragend', marker.__spbuDragHandler);
  }
  marker.__spbuDragHandler = () => {
    const current = marker.getLatLng();
    el.latPoint.value = current.lat;
    el.lngPoint.value = current.lng;
  };
  marker.on('drag', marker.__spbuDragHandler);
  marker.on('dragend', marker.__spbuDragHandler);

  enableLayerEditing(marker);
  updateModeInfo();
  setActiveListItem('points', id);
  setFormNotice('Edit: geser marker jika perlu, lalu klik "Update Data".', 'info');
  openModal();
}

function startEditJalan(id, layer, props) {
  resetMode();
  const backup = backupLayerGeometry(layer);
  backup.status = props?.status_jalan ?? 'Nasional';
  state.editing = { type: 'jalan', id: Number(id), layer, backup };
  state.featureType = 'jalan';
  state.currentLayer = null;

  el.namaJalan.value = props?.nama_jalan ?? '';
  el.statusJalan.value = props?.status_jalan ?? 'Nasional';

  enableLayerEditing(layer);
  updateModeInfo();
  setActiveListItem('jalan', id);
  setFormNotice('Edit: geser titik garis, panjang akan dihitung otomatis saat update.', 'info');
  openModal();
}

function startEditParsil(id, layer, props) {
  resetMode();
  const backup = backupLayerGeometry(layer);
  backup.status = props?.status_kepemilikan ?? 'SHM';
  state.editing = { type: 'parsil', id: Number(id), layer, backup };
  state.featureType = 'parsil';
  state.currentLayer = null;

  el.statusParsil.value = props?.status_kepemilikan ?? 'SHM';

  enableLayerEditing(layer);
  updateModeInfo();
  setActiveListItem('parsil', id);
  setFormNotice('Edit: geser titik poligon, luas akan dihitung otomatis saat update.', 'info');
  openModal();
}

function startEditRumahIbadah(id, marker, row) {
  resetMode();
  const backup = backupLayerGeometry(marker);
  state.editing = { type: 'rumahIbadah', id: Number(id), layer: marker, backup };
  state.featureType = 'rumahIbadah';
  state.currentLayer = null;

  el.namaRumahIbadah.value = row?.nama ?? '';
  el.jenisRumahIbadah.value = row?.jenis ?? 'Lainnya';
  el.kontakRumahIbadah.value = row?.kontak ?? '';
  el.radiusRumahIbadah.value = row?.radius_meter ?? '';
  el.alamatRumahIbadah.value = row?.address ?? '';

  const ll = marker.getLatLng();
  el.latRumahIbadah.value = ll.lat;
  el.lngRumahIbadah.value = ll.lng;

  if (marker.__riDragHandler) {
    marker.off('drag', marker.__riDragHandler);
    marker.off('dragend', marker.__riDragHandler);
  }
  marker.__riDragHandler = () => {
    const current = marker.getLatLng();
    el.latRumahIbadah.value = current.lat;
    el.lngRumahIbadah.value = current.lng;
  };
  marker.on('drag', marker.__riDragHandler);
  marker.on('dragend', marker.__riDragHandler);

  enableLayerEditing(marker);
  updateModeInfo();
  setActiveListItem('rumahIbadah', id);
  setFormNotice('Edit: geser marker jika perlu, lalu klik "Update Data".', 'info');
  openModal();
}

function startEditPemukiman(id, marker, row) {
  resetMode();
  const backup = backupLayerGeometry(marker);
  const ibadahId = row?.rumah_ibadah_id;
  backup.covered = ibadahId !== null && ibadahId !== undefined && String(ibadahId).trim() !== '' && String(ibadahId) !== '0';
  state.editing = { type: 'pemukiman', id: Number(id), layer: marker, backup, dragEase: null };
  state.featureType = 'pemukiman';
  state.currentLayer = null;

  el.kkNamaPemukiman.value = row?.kk_nama ?? row?.nama ?? '';
  el.nikPemukiman.value = row?.nik ?? '';
  el.jumlahAnggotaPemukiman.value = row?.jumlah_anggota ?? '';
  el.statusBantuanPemukiman.value = row?.status_bantuan ?? 'Belum dibantu';
  el.jenisBantuanPemukiman.value = row?.jenis_bantuan ?? '';
  el.tanggalBantuanPemukiman.value = row?.tanggal_bantuan ?? '';
  el.alamatPemukiman.value = row?.address ?? '';
  el.kelurahanPemukiman.value = row?.kelurahan ?? '';
  el.kecamatanPemukiman.value = row?.kecamatan ?? '';

  const ll = marker.getLatLng();
  el.latPemukiman.value = ll.lat;
  el.lngPemukiman.value = ll.lng;

  marker.setIcon(makePemukimanIcon({ isEdit: true, isCovered: Boolean(backup.covered) }));
  if (marker.__pmDragHandler) {
    marker.off('drag', marker.__pmDragHandler);
    marker.off('dragend', marker.__pmDragHandler);
  }
  marker.__currentCovered = Boolean(backup.covered);
  marker.__pmDragHandler = () => {
    const current = marker.getLatLng();
    el.latPemukiman.value = current.lat;
    el.lngPemukiman.value = current.lng;

    const cov = computePemukimanCoverage(current);
    if (cov.covered !== null && marker.__currentCovered !== cov.covered) {
      marker.__currentCovered = cov.covered;
      marker.setIcon(makePemukimanIcon({ isEdit: true, isCovered: cov.covered === true }));
    }
  };
  marker.on('drag', marker.__pmDragHandler);
  marker.on('dragend', marker.__pmDragHandler);

  enableLayerEditing(marker);
  updateModeInfo();
  setActiveListItem('pemukiman', id);
  setFormNotice('Edit: geser marker jika perlu, lalu klik "Update Data".', 'info');
  openModal();
}

async function deleteFeature(type, id) {
  const label =
    type === 'point' ? 'point SPBU' :
    type === 'jalan' ? 'jalan' :
    type === 'parsil' ? 'parsil' :
    type === 'rumahIbadah' ? 'rumah ibadah' :
    'pemukiman miskin';
  const ok = window.confirm(`Hapus data ${label} ini?`);
  if (!ok) return;

  const url =
    type === 'point' ? 'php/delete_point.php' :
    type === 'jalan' ? 'php/delete_jalan.php' :
    type === 'parsil' ? 'php/delete_parsil.php' :
    type === 'rumahIbadah' ? 'php/delete_rumah_ibadah.php' :
    'php/delete_pemukiman_miskin.php';

  try {
    const { res, json, text } = await fetchMaybeJson(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: Number(id) })
    });

    if (!json) throw new Error(text || 'Respon server tidak valid saat hapus data.');
    const api = parseApiJson(json);
    if (!api.success) throw new Error(api.message);
    if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);

    showToast('Data berhasil dihapus.', 'success');
    if (type === 'point') await loadPoints();
    if (type === 'jalan') await loadJalan();
    if (type === 'parsil') await loadParsil();
    if (type === 'rumahIbadah') {
      await loadPemukimanMiskin();
      await loadRumahIbadah();
    }
    if (type === 'pemukiman') {
      await loadPemukimanMiskin();
      await loadRumahIbadah();
    }
  } catch (e) {
    showToast(e?.message || String(e), 'error', 5000);
  }
}

function closeModal({ keepMode = false } = {}) {
  if (!el.modal) return;

  // Cancel edit: restore geometry
  if (state.editing?.layer && state.editing?.backup) {
    const { type, layer, backup } = state.editing;
    restoreLayerGeometry(layer, backup);

    if (type === 'point' && layer.setIcon && typeof backup?.is24 === 'boolean') {
      layer.setIcon(makeSpbuIcon(backup.is24));
    }

    if (type === 'jalan' && layer.setStyle && backup?.status) {
      layer.setStyle({ color: getJalanColor(backup.status), weight: 4, opacity: 0.95 });
    }

    if (type === 'parsil' && layer.setStyle && backup?.status) {
      layer.setStyle({ color: '#0f172a', weight: 2, fillColor: getParsilColor(backup.status), fillOpacity: 0.35 });
    }

    if (type === 'pemukiman' && layer.setIcon) {
      layer.setIcon(makePemukimanIcon({ isCovered: Boolean(backup?.covered) }));
      if (layer.__pmDragHandler) {
        layer.off('drag', layer.__pmDragHandler);
        layer.off('dragend', layer.__pmDragHandler);
        layer.__pmDragHandler = null;
      }
      if (state.editing?.dragEase?.disable) state.editing.dragEase.disable();
    }

    if (type === 'rumahIbadah' && layer.setIcon) {
      layer.setIcon(makeRumahIbadahIcon());
      if (layer.__riDragHandler) {
        layer.off('drag', layer.__riDragHandler);
        layer.off('dragend', layer.__riDragHandler);
        layer.__riDragHandler = null;
      }
    }

    disableLayerEditing(layer);
    state.editing = null;
  }

  el.modal.classList.add('hidden');
  el.featureForm?.reset();
  clearFormNotice();
  setSubmitting(false);
  clearPendingLayer();

  if (!keepMode) resetMode({ skipModalClose: true });
}

function resetMode({ skipModalClose = false } = {}) {
  if (!skipModalClose && el.modal && !el.modal.classList.contains('hidden')) {
    // Close modal first, but don't recurse into resetMode again.
    closeModal({ keepMode: true });
  }

  if (state.locationEditing) {
    cancelLocationEdit({ silent: true });
  }

  if (state.activeDrawer) {
    state.activeDrawer.disable();
    state.activeDrawer = null;
  }

  state.featureType = null;
  document.body.classList.remove('crosshair');
  setActiveModeButton(null);
  updateModeInfo();
}

function setMode(type) {
  resetMode();
  state.editing = null;
  state.locationEditing = null;
  state.featureType = type;
  setActiveModeButton(type);
  updateModeInfo();

  if (type === 'point' || type === 'rumahIbadah' || type === 'pemukiman') {
    document.body.classList.add('crosshair');
    return;
  }

  if (type === 'jalan') {
    state.activeDrawer = new L.Draw.Polyline(map, {
      shapeOptions: { color: getJalanColor(el.statusJalan?.value), weight: 4, opacity: 0.95 }
    });
    state.activeDrawer.enable();
    return;
  }

  if (type === 'parsil') {
    state.activeDrawer = new L.Draw.Polygon(map, {
      allowIntersection: false,
      showArea: true,
      shapeOptions: { color: '#0f172a', weight: 2, fillColor: getParsilColor(el.statusParsil?.value), fillOpacity: 0.35 }
    });
    state.activeDrawer.enable();
  }
}

// expose for inline onclick
window.setMode = setMode;
window.resetMode = resetMode;
window.closeModal = closeModal;

function showEditBar(title) {
  if (!el.editBar) return;
  if (el.editBarTitle) el.editBarTitle.textContent = title || 'Edit Lokasi';
  el.editBar.classList.remove('hidden');
}

function hideEditBar() {
  if (!el.editBar) return;
  el.editBar.classList.add('hidden');
}

function updateDeleteVertexUi() {
  if (!el.btnDeleteVertex) return;
  const type = state.locationEditing?.type ?? null;
  const shouldShow = type === 'jalan' || type === 'parsil';
  el.btnDeleteVertex.classList.toggle('hidden', !shouldShow);

  if (!shouldShow) {
    el.btnDeleteVertex.classList.remove('is-active');
    el.btnDeleteVertex.textContent = 'Hapus Vertex';
  } else {
    const enabled = Boolean(state.locationEditing?.vertexDeleteMode);
    el.btnDeleteVertex.classList.toggle('is-active', enabled);
    el.btnDeleteVertex.textContent = enabled ? 'Hapus Vertex: ON' : 'Hapus Vertex';
  }
}

function setVertexDeleteMode(enabled) {
  if (!state.locationEditing) return;
  state.locationEditing.vertexDeleteMode = Boolean(enabled);
  updateDeleteVertexUi();
  if (state.locationEditing.vertexDeleteMode) {
    showToast('Mode hapus vertex aktif: klik vertex (titik putih) untuk menghapus.', 'info', 6000);
  }
}

function refreshEditingHandles(layer) {
  if (!layer?.editing?.disable || !layer?.editing?.enable) return;
  const wasEnabled = layer.editing.enabled?.();
  if (wasEnabled === false) return;
  layer.editing.disable();
  layer.editing.enable();
}

function deleteNearestVertex(layer, latlng) {
  if (!layer?.getLatLngs || !layer?.setLatLngs) return false;

  const clickPt = map.latLngToContainerPoint(latlng);
  const maxDistPx = 18;
  const type = state.locationEditing?.type;

  if (type === 'parsil') {
    const rings = layer.getLatLngs();
    const ring = Array.isArray(rings?.[0]) ? rings[0] : null;
    if (!ring) return { ok: false, reason: 'not_found' };
    if (ring.length <= 3) return { ok: false, reason: 'min' };

    let bestIdx = -1;
    let bestDist = Infinity;
    for (let i = 0; i < ring.length; i++) {
      const d = clickPt.distanceTo(map.latLngToContainerPoint(ring[i]));
      if (d < bestDist) {
        bestDist = d;
        bestIdx = i;
      }
    }
    if (bestDist > maxDistPx || bestIdx < 0) return { ok: false, reason: 'not_found' };

    ring.splice(bestIdx, 1);
    layer.setLatLngs([ring]);
    if (layer.redraw) layer.redraw();
    refreshEditingHandles(layer);
    return { ok: true };
  }

  // polyline
  let latlngs = layer.getLatLngs();
  if (Array.isArray(latlngs?.[0])) latlngs = latlngs[0]; // guard for MultiLineString
  if (!Array.isArray(latlngs)) return { ok: false, reason: 'not_found' };
  if (latlngs.length <= 2) return { ok: false, reason: 'min' };

  let bestIdx = -1;
  let bestDist = Infinity;
  for (let i = 0; i < latlngs.length; i++) {
    const d = clickPt.distanceTo(map.latLngToContainerPoint(latlngs[i]));
    if (d < bestDist) {
      bestDist = d;
      bestIdx = i;
    }
  }
  if (bestDist > maxDistPx || bestIdx < 0) return { ok: false, reason: 'not_found' };

  latlngs.splice(bestIdx, 1);
  layer.setLatLngs(latlngs);
  if (layer.redraw) layer.redraw();
  refreshEditingHandles(layer);
  return { ok: true };
}

function enableLayerTranslation(layer) {
  if (!layer) return null;

  const ctx = {
    isDragging: false,
    startPoint: null,
    originLatLngs: null,
    originPoints: null,
    raf: null,
    pendingDelta: null,
    original: {
      dragging: map.dragging.enabled(),
      boxZoom: map.boxZoom?.enabled?.() ?? false,
      doubleClickZoom: map.doubleClickZoom?.enabled?.() ?? false,
      touchZoom: map.touchZoom?.enabled?.() ?? false
    }
  };

  const cloneLatLngs = (latlngs) => {
    if (!Array.isArray(latlngs)) return latlngs;
    return latlngs.map((v) => (Array.isArray(v) ? cloneLatLngs(v) : L.latLng(v.lat, v.lng)));
  };

  const toPoints = (latlngs) => {
    if (!Array.isArray(latlngs)) return latlngs;
    return latlngs.map((v) => (Array.isArray(v) ? toPoints(v) : map.latLngToLayerPoint(v)));
  };

  const setTranslatedFromPoints = (originPoints, delta) => {
    const apply = (pts) => {
      if (!Array.isArray(pts)) return pts;
      return pts.map((v) => {
        if (Array.isArray(v)) return apply(v);
        return map.layerPointToLatLng(v.add(delta));
      });
    };
    layer.setLatLngs(apply(originPoints));
  };

  const scheduleUpdate = () => {
    if (!ctx.isDragging || !ctx.pendingDelta) return;
    if (ctx.raf) return;
    ctx.raf = window.requestAnimationFrame(() => {
      ctx.raf = null;
      if (!ctx.isDragging || !ctx.pendingDelta) return;
      setTranslatedFromPoints(ctx.originPoints, ctx.pendingDelta);
      if (layer.redraw) layer.redraw();
    });
  };

  const onDown = (e) => {
    // Avoid capturing drag from vertex markers (edit handles)
    if (e?.originalEvent?.target && String(e.originalEvent.target.className || '').includes('leaflet-editing-icon')) {
      return;
    }
    ctx.isDragging = true;

    ctx.startPoint = map.latLngToLayerPoint(e.latlng);
    ctx.originLatLngs = cloneLatLngs(layer.getLatLngs());
    ctx.originPoints = toPoints(ctx.originLatLngs);
    ctx.pendingDelta = L.point(0, 0);

    map.dragging.disable();
    map.boxZoom?.disable?.();
    map.doubleClickZoom?.disable?.();
    map.touchZoom?.disable?.();

    if (e?.originalEvent) L.DomEvent.stop(e.originalEvent);
  };

  const onMove = (e) => {
    if (!ctx.isDragging) return;
    const current = map.latLngToLayerPoint(e.latlng);
    ctx.pendingDelta = current.subtract(ctx.startPoint);
    scheduleUpdate();
  };

  const onUp = () => {
    if (!ctx.isDragging) return;
    ctx.isDragging = false;
    ctx.startPoint = null;
    ctx.originLatLngs = null;
    ctx.originPoints = null;
    ctx.pendingDelta = null;
    if (ctx.raf) {
      window.cancelAnimationFrame(ctx.raf);
      ctx.raf = null;
    }

    if (ctx.original.dragging) map.dragging.enable();
    if (ctx.original.boxZoom) map.boxZoom?.enable?.();
    if (ctx.original.doubleClickZoom) map.doubleClickZoom?.enable?.();
    if (ctx.original.touchZoom) map.touchZoom?.enable?.();
  };

  layer.on('mousedown touchstart', onDown);
  map.on('mousemove touchmove', onMove);
  map.on('mouseup touchend touchcancel', onUp);

  return {
    disable: () => {
      layer.off('mousedown touchstart', onDown);
      map.off('mousemove touchmove', onMove);
      map.off('mouseup touchend touchcancel', onUp);
      if (ctx.raf) {
        window.cancelAnimationFrame(ctx.raf);
        ctx.raf = null;
      }
      if (ctx.original.dragging) map.dragging.enable();
      if (ctx.original.boxZoom) map.boxZoom?.enable?.();
      if (ctx.original.doubleClickZoom) map.doubleClickZoom?.enable?.();
      if (ctx.original.touchZoom) map.touchZoom?.enable?.();
    }
  };
}

async function saveLocationEdit() {
  if (!state.locationEditing) return;

  const { type, id, layer, attrs } = state.locationEditing;
  try {
    if (type === 'point') {
      const ll = layer.getLatLng();
      const payload = {
        id,
        nama: attrs.nama,
        no: attrs.no,
        deskripsi: attrs.deskripsi,
        status_24jam: attrs.status_24jam,
        latitude: ll.lat,
        longitude: ll.lng
      };

      const { res, json, text } = await fetchMaybeJson('php/update_point.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!json) throw new Error(text || 'Respon server tidak valid saat update lokasi point.');
      const api = parseApiJson(json);
      if (!api.success) throw new Error(api.message);
      if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
    }

    if (type === 'jalan') {
      const payload = {
        id,
        nama_jalan: attrs.nama_jalan,
        status_jalan: attrs.status_jalan,
        panjang_meter: hitungPanjang(layer),
        geometry: layer.toGeoJSON().geometry
      };

      const { res, json, text } = await fetchMaybeJson('php/update_jalan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!json) throw new Error(text || 'Respon server tidak valid saat update lokasi jalan.');
      const api = parseApiJson(json);
      if (!api.success) throw new Error(api.message);
      if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
    }

    if (type === 'parsil') {
      const payload = {
        id,
        status_kepemilikan: attrs.status_kepemilikan,
        luas_m2: hitungLuas(layer),
        geometry: layer.toGeoJSON().geometry
      };

      const { res, json, text } = await fetchMaybeJson('php/update_parsil.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!json) throw new Error(text || 'Respon server tidak valid saat update lokasi parsil.');
      const api = parseApiJson(json);
      if (!api.success) throw new Error(api.message);
      if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
    }

    if (type === 'pemukiman') {
      const ll = layer.getLatLng();
      const payload = { id, latitude: ll.lat, longitude: ll.lng };

      const { res, json, text } = await fetchMaybeJson('php/update_pemukiman_miskin_location.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!json) throw new Error(text || 'Respon server tidak valid saat update lokasi pemukiman miskin.');
      const api = parseApiJson(json);
      if (!api.success) throw new Error(api.message);
      if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
    }

    if (type === 'rumahIbadah') {
      const ll = layer.getLatLng();
      const payload = {
        id,
        nama: attrs.nama,
        jenis: attrs.jenis,
        kontak: attrs.kontak,
        radius_meter: attrs.radius_meter,
        latitude: ll.lat,
        longitude: ll.lng,
        address: attrs.address
      };

      const { res, json, text } = await fetchMaybeJson('php/update_rumah_ibadah.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!json) throw new Error(text || 'Respon server tidak valid saat update lokasi rumah ibadah.');
      const api = parseApiJson(json);
      if (!api.success) throw new Error(api.message);
      if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
    }

    showToast('Lokasi berhasil diupdate.', 'success');
    state.locationEditing.vertexDeleteMode = false;
    cancelLocationEdit({ silent: true });

    if (type === 'point') await loadPoints();
    if (type === 'jalan') await loadJalan();
    if (type === 'parsil') await loadParsil();
    if (type === 'pemukiman') {
      await loadPemukimanMiskin();
      await loadRumahIbadah();
    }
    if (type === 'rumahIbadah') {
      await loadPemukimanMiskin();
      await loadRumahIbadah();
    }
  } catch (e) {
    showToast(e?.message || String(e), 'error', 5000);
  }
}

function cancelLocationEdit({ silent = false } = {}) {
  if (!state.locationEditing) return;
  const { type, layer, backup, translate, dragEase } = state.locationEditing;
  if (translate && translate.disable) translate.disable();
  if (dragEase && dragEase.disable) dragEase.disable();
  state.locationEditing.vertexDeleteMode = false;
  updateDeleteVertexUi();
  restoreLayerGeometry(layer, backup);

  if (type === 'jalan' && layer.setStyle && backup?.status) {
    layer.setStyle({ color: getJalanColor(backup.status), weight: 4, opacity: 0.95 });
  }
  if (type === 'parsil' && layer.setStyle && backup?.status) {
    layer.setStyle({ color: '#0f172a', weight: 2, fillColor: getParsilColor(backup.status), fillOpacity: 0.35 });
  }
  if (type === 'point' && layer.setIcon && typeof backup?.is24 === 'boolean') {
    layer.setIcon(makeSpbuIcon(backup.is24));
  }

  if (type === 'pemukiman' && layer.setIcon) {
    layer.setIcon(makePemukimanIcon({ isCovered: Boolean(backup?.covered) }));
    if (layer.__pmCoverHandler) {
      layer.off('drag', layer.__pmCoverHandler);
      layer.off('dragend', layer.__pmCoverHandler);
      layer.__pmCoverHandler = null;
    }
  }

  if (type === 'rumahIbadah' && layer.setIcon) {
    layer.setIcon(makeRumahIbadahIcon());
  }

  disableLayerEditing(layer);
  hideEditBar();
  state.locationEditing = null;
  updateDeleteVertexUi();
  updateModeInfo();
  if (!silent) showToast('Edit lokasi dibatalkan.', 'info');
}

function startEditLocationPoint(id, marker, row) {
  resetMode();
  const backup = backupLayerGeometry(marker);
  backup.is24 = Number(row?.status_24jam) === 1;
  state.locationEditing = {
    type: 'point',
    id: Number(id),
    layer: marker,
    backup,
    vertexDeleteMode: false,
    attrs: {
      nama: row?.nama ?? '',
      no: row?.no ?? '',
      deskripsi: row?.deskripsi ?? '',
      status_24jam: Number(row?.status_24jam) === 1 ? 1 : 0
    }
  };

  enableLayerEditing(marker); // enables dragging
  showEditBar('Edit Lokasi: Point');
  updateDeleteVertexUi();
  updateModeInfo();
  showToast('Geser marker ke posisi baru, lalu klik "Simpan Lokasi".', 'info', 6000);
}

function startEditLocationPemukiman(id, marker, row) {
  resetMode();
  const backup = backupLayerGeometry(marker);
  const ibadahId = row?.rumah_ibadah_id;
  backup.covered = ibadahId !== null && ibadahId !== undefined && String(ibadahId).trim() !== '' && String(ibadahId) !== '0';
  state.locationEditing = {
    type: 'pemukiman',
    id: Number(id),
    layer: marker,
    backup,
    vertexDeleteMode: false,
    attrs: {}
  };

  marker.setIcon(makePemukimanIcon({ isEdit: true, isCovered: Boolean(backup.covered) }));
  enableLayerEditing(marker);

  if (marker.__pmCoverHandler) {
    marker.off('drag', marker.__pmCoverHandler);
    marker.off('dragend', marker.__pmCoverHandler);
  }
  marker.__currentCovered = Boolean(backup.covered);
  marker.__pmCoverHandler = () => {
    const current = marker.getLatLng();
    const cov = computePemukimanCoverage(current);
    if (cov.covered !== null && marker.__currentCovered !== cov.covered) {
      marker.__currentCovered = cov.covered;
      marker.setIcon(makePemukimanIcon({ isEdit: true, isCovered: cov.covered === true }));
    }
  };
  marker.on('drag', marker.__pmCoverHandler);
  marker.on('dragend', marker.__pmCoverHandler);
  marker.__pmCoverHandler();

  showEditBar('Edit Lokasi: Pemukiman Miskin');
  updateDeleteVertexUi();
  updateModeInfo();

  const label = escapeHtml(row?.kk_nama ?? row?.nama ?? `ID ${id}`);
  showToast(`Geser marker pemukiman (${label}) ke posisi baru, lalu klik "Simpan Lokasi".`, 'info', 6500);
}

function startEditLocationRumahIbadah(id, marker, row) {
  resetMode();
  const radiusVal = Number(row?.radius_meter);
  if (!Number.isFinite(radiusVal) || radiusVal <= 0) {
    showToast('Radius rumah ibadah tidak valid. Silakan Edit Data dulu untuk isi radius.', 'error', 5500);
    return;
  }
  const backup = backupLayerGeometry(marker);
  state.locationEditing = {
    type: 'rumahIbadah',
    id: Number(id),
    layer: marker,
    backup,
    vertexDeleteMode: false,
    attrs: {
      nama: row?.nama ?? 'Rumah Ibadah',
      jenis: row?.jenis ?? 'Lainnya',
      kontak: row?.kontak ?? '',
      radius_meter: radiusVal,
      address: row?.address ?? ''
    }
  };

  enableLayerEditing(marker);
  showEditBar('Edit Lokasi: Rumah Ibadah');
  updateDeleteVertexUi();
  updateModeInfo();

  const label = escapeHtml(row?.nama ?? `ID ${id}`);
  showToast(`Geser marker rumah ibadah (${label}) ke posisi baru, lalu klik "Simpan Lokasi".`, 'info', 6500);
}

function startEditLocationJalan(id, layer, props) {
  resetMode();
  const backup = backupLayerGeometry(layer);
  backup.status = props?.status_jalan ?? 'Nasional';
  state.locationEditing = {
    type: 'jalan',
    id: Number(id),
    layer,
    backup,
    vertexDeleteMode: false,
    attrs: {
      nama_jalan: props?.nama_jalan ?? 'Jalan',
      status_jalan: props?.status_jalan ?? 'Nasional'
    }
  };

  enableLayerEditing(layer);
  layer.setStyle?.({ color: getJalanColor(props?.status_jalan), weight: 4, opacity: 0.95 });
  state.locationEditing.translate = enableLayerTranslation(layer);

  showEditBar('Edit Lokasi: Jalan');
  updateDeleteVertexUi();
  updateModeInfo();
  showToast('Geser garis untuk pindah, atau geser vertex untuk ubah bentuk. Lalu simpan.', 'info', 7000);
}

function startEditLocationParsil(id, layer, props) {
  resetMode();
  const backup = backupLayerGeometry(layer);
  backup.status = props?.status_kepemilikan ?? 'SHM';
  state.locationEditing = {
    type: 'parsil',
    id: Number(id),
    layer,
    backup,
    vertexDeleteMode: false,
    attrs: {
      status_kepemilikan: props?.status_kepemilikan ?? 'SHM'
    }
  };

  enableLayerEditing(layer);
  layer.setStyle?.({ color: '#0f172a', weight: 2, fillColor: getParsilColor(props?.status_kepemilikan), fillOpacity: 0.35 });
  state.locationEditing.translate = enableLayerTranslation(layer);

  showEditBar('Edit Lokasi: Parsil');
  updateDeleteVertexUi();
  updateModeInfo();
  showToast('Geser area untuk pindah, atau geser vertex untuk ubah bentuk. Lalu simpan.', 'info', 7000);
}

// ================= DRAW EVENT =================
map.on(L.Draw.Event.CREATED, function (e) {
  state.currentLayer = e.layer;
  state.pendingLayer = e.layer;
  editableGroup.addLayer(e.layer);

  // Disable drawer while modal is open
  if (state.activeDrawer) {
    state.activeDrawer.disable();
    state.activeDrawer = null;
  }

  if (!state.featureType) {
    if (e.layerType === 'polyline') state.featureType = 'jalan';
    if (e.layerType === 'polygon') state.featureType = 'parsil';
    setActiveModeButton(state.featureType);
    updateModeInfo();
  }

  // Apply initial style based on selected status
  if (state.featureType === 'jalan' && e.layer.setStyle) {
    e.layer.setStyle({ color: getJalanColor(el.statusJalan?.value), weight: 4, opacity: 0.95 });
  }
  if (state.featureType === 'parsil' && e.layer.setStyle) {
    e.layer.setStyle({ color: '#0f172a', weight: 2, fillColor: getParsilColor(el.statusParsil?.value), fillOpacity: 0.35 });
  }

  openModal();
});

// ================= POINT =================
map.on('click', function (e) {
  if (state.locationEditing?.vertexDeleteMode && (state.locationEditing.type === 'jalan' || state.locationEditing.type === 'parsil')) {
    const result = deleteNearestVertex(state.locationEditing.layer, e.latlng);
    if (result?.ok) {
      showToast('Vertex dihapus.', 'success');
    } else if (result?.reason === 'min') {
      const min = state.locationEditing.type === 'jalan' ? 2 : 3;
      showToast(`Tidak bisa menghapus (minimal ${min} vertex).`, 'error', 2500);
    }
    return;
  }

  if (state.featureType !== 'point' && state.featureType !== 'rumahIbadah' && state.featureType !== 'pemukiman') return;
  if (el.modal && !el.modal.classList.contains('hidden')) return;

  state.currentLayer = e.latlng;
  if (state.featureType === 'point') {
    el.latPoint.value = e.latlng.lat;
    el.lngPoint.value = e.latlng.lng;
  }
  if (state.featureType === 'rumahIbadah') {
    el.latRumahIbadah.value = e.latlng.lat;
    el.lngRumahIbadah.value = e.latlng.lng;
  }
  if (state.featureType === 'pemukiman') {
    el.latPemukiman.value = e.latlng.lat;
    el.lngPemukiman.value = e.latlng.lng;
  }
  openModal();
});

// ================= SUBMIT =================
el.featureForm.addEventListener('submit', async function (e) {
  e.preventDefault();

  const submittingType = state.featureType;

  if (!submittingType) {
    showToast('Mode belum dipilih.', 'error');
    return;
  }

  if (!state.currentLayer && !state.editing?.layer) {
    showToast('Layer tidak ada!', 'error');
    return;
  }

  try {
    clearFormNotice();
    setSubmitting(true);

    // UPDATE mode
    if (state.editing && state.editing.type === submittingType) {
      if (submittingType === 'point') {
        const marker = state.editing.layer;
        const ll = marker.getLatLng();
        const payload = {
          id: state.editing.id,
          nama: el.namaPoint.value,
          no: el.noPoint.value,
          deskripsi: el.deskripsiPoint.value,
          status_24jam: el.status24Jam.checked ? 1 : 0,
          latitude: ll.lat,
          longitude: ll.lng
        };

        const { res, json, text } = await fetchMaybeJson('php/update_point.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        if (!json) throw new Error(text || 'Respon server tidak valid saat update point.');
        const api = parseApiJson(json);
        if (!api.success) throw new Error(api.message);
        if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
      }

      if (submittingType === 'jalan') {
        const layer = state.editing.layer;
        const payload = {
          id: state.editing.id,
          nama_jalan: el.namaJalan.value,
          status_jalan: el.statusJalan.value,
          panjang_meter: hitungPanjang(layer),
          geometry: layer.toGeoJSON().geometry
        };

        const { res, json, text } = await fetchMaybeJson('php/update_jalan.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        if (!json) throw new Error(text || 'Respon server tidak valid saat update jalan.');
        const api = parseApiJson(json);
        if (!api.success) throw new Error(api.message);
        if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
      }

      if (submittingType === 'parsil') {
        const layer = state.editing.layer;
        const payload = {
          id: state.editing.id,
          status_kepemilikan: el.statusParsil.value,
          luas_m2: hitungLuas(layer),
          geometry: layer.toGeoJSON().geometry
        };

        const { res, json, text } = await fetchMaybeJson('php/update_parsil.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        if (!json) throw new Error(text || 'Respon server tidak valid saat update parsil.');
        const api = parseApiJson(json);
        if (!api.success) throw new Error(api.message);
        if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
      }

      if (submittingType === 'rumahIbadah') {
        const marker = state.editing.layer;
        const ll = marker.getLatLng();
        const payload = {
          id: state.editing.id,
          nama: el.namaRumahIbadah.value,
          jenis: el.jenisRumahIbadah.value,
          kontak: el.kontakRumahIbadah.value,
          radius_meter: el.radiusRumahIbadah.value,
          latitude: ll.lat,
          longitude: ll.lng,
          address: el.alamatRumahIbadah.value
        };

        const { res, json, text } = await fetchMaybeJson('php/update_rumah_ibadah.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        if (!json) throw new Error(text || 'Respon server tidak valid saat update rumah ibadah.');
        const api = parseApiJson(json);
        if (!api.success) throw new Error(api.message);
        if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
      }

      if (submittingType === 'pemukiman') {
        const marker = state.editing.layer;
        const ll = marker.getLatLng();
        const payload = {
          id: state.editing.id,
          kk_nama: el.kkNamaPemukiman.value,
          nik: el.nikPemukiman.value,
          jumlah_anggota: el.jumlahAnggotaPemukiman.value,
          latitude: ll.lat,
          longitude: ll.lng,
          address: el.alamatPemukiman.value,
          kelurahan: el.kelurahanPemukiman.value,
          kecamatan: el.kecamatanPemukiman.value,
          status_bantuan: el.statusBantuanPemukiman.value,
          jenis_bantuan: el.jenisBantuanPemukiman.value,
          tanggal_bantuan: el.tanggalBantuanPemukiman.value,
          anggota: []
        };

        const { res, json, text } = await fetchMaybeJson('php/update_pemukiman_miskin.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });

        if (!json) throw new Error(text || 'Respon server tidak valid saat update pemukiman miskin.');
        const api = parseApiJson(json);
        if (!api.success) throw new Error(api.message);
        if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
      }

      showToast('Data berhasil diupdate.', 'success');
      if (state.editing?.dragEase?.disable) state.editing.dragEase.disable();
      disableLayerEditing(state.editing.layer);
      state.editing = null;
      closeModal();

      if (submittingType === 'point') await loadPoints();
      if (submittingType === 'jalan') await loadJalan();
      if (submittingType === 'parsil') await loadParsil();
      if (submittingType === 'rumahIbadah') {
        await loadPemukimanMiskin();
        await loadRumahIbadah();
      }
      if (submittingType === 'pemukiman') await loadPemukimanMiskin();
      return;
    }

    // POINT
    if (submittingType === 'point') {
      const payload = {
        nama: el.namaPoint.value,
        no: el.noPoint.value,
        deskripsi: el.deskripsiPoint.value,
        status_24jam: el.status24Jam.checked ? 1 : 0,
        latitude: el.latPoint.value,
        longitude: el.lngPoint.value
      };

      const { res, json, text } = await fetchMaybeJson('php/create_point.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!json) throw new Error(text || 'Respon server tidak valid saat menyimpan point.');
      const api = parseApiJson(json);
      if (!api.success) throw new Error(api.message);
      if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
    }

    // JALAN
    if (submittingType === 'jalan') {
      const layer = state.currentLayer;

      const payload = {
        nama_jalan: el.namaJalan.value,
        status_jalan: el.statusJalan.value,
        panjang_meter: hitungPanjang(layer),
        geometry: layer.toGeoJSON().geometry
      };

      const { res, json, text } = await fetchMaybeJson('php/create_jalan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!json) throw new Error(text || 'Respon server tidak valid saat menyimpan jalan.');
      const api = parseApiJson(json);
      if (!api.success) throw new Error(api.message);
      if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
    }

    // PARSIL
    if (submittingType === 'parsil') {
      const layer = state.currentLayer;

      const payload = {
        status_kepemilikan: el.statusParsil.value,
        luas_m2: hitungLuas(layer),
        geometry: layer.toGeoJSON().geometry
      };

      const { res, json, text } = await fetchMaybeJson('php/create_parsil.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!json) throw new Error(text || 'Respon server tidak valid saat menyimpan parsil.');
      const api = parseApiJson(json);
      if (!api.success) throw new Error(api.message);
      if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
    }

    // RUMAH IBADAH
    if (submittingType === 'rumahIbadah') {
      const payload = {
        nama: el.namaRumahIbadah.value,
        jenis: el.jenisRumahIbadah.value,
        kontak: el.kontakRumahIbadah.value,
        radius_meter: el.radiusRumahIbadah.value,
        latitude: el.latRumahIbadah.value,
        longitude: el.lngRumahIbadah.value,
        address: el.alamatRumahIbadah.value
      };

      const { res, json, text } = await fetchMaybeJson('php/create_rumah_ibadah.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!json) throw new Error(text || 'Respon server tidak valid saat menyimpan rumah ibadah.');
      const api = parseApiJson(json);
      if (!api.success) throw new Error(api.message);
      if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
    }

    // PEMUKIMAN MISKIN
    if (submittingType === 'pemukiman') {
      const payload = {
        kk_nama: el.kkNamaPemukiman.value,
        nik: el.nikPemukiman.value,
        jumlah_anggota: el.jumlahAnggotaPemukiman.value,
        latitude: el.latPemukiman.value,
        longitude: el.lngPemukiman.value,
        address: el.alamatPemukiman.value,
        kelurahan: el.kelurahanPemukiman.value,
        kecamatan: el.kecamatanPemukiman.value,
        status_bantuan: el.statusBantuanPemukiman.value,
        jenis_bantuan: el.jenisBantuanPemukiman.value,
        tanggal_bantuan: el.tanggalBantuanPemukiman.value,
        anggota: []
      };

      const { res, json, text } = await fetchMaybeJson('php/create_pemukiman_miskin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (!json) throw new Error(text || 'Respon server tidak valid saat menyimpan pemukiman miskin.');
      const api = parseApiJson(json);
      if (!api.success) throw new Error(api.message);
      if (!res.ok) throw new Error(api.message || `HTTP ${res.status}`);
    }

    showToast('Data berhasil disimpan.', 'success');
    closeModal();

    if (submittingType === 'point') await loadPoints();
    if (submittingType === 'jalan') await loadJalan();
    if (submittingType === 'parsil') await loadParsil();
    if (submittingType === 'rumahIbadah') {
      await loadPemukimanMiskin();
      await loadRumahIbadah();
    }
    if (submittingType === 'pemukiman') await loadPemukimanMiskin();
  } catch (err) {
    const msg = err?.message || String(err);
    setFormNotice(msg, 'error');
    showToast(msg, 'error', 5000);
  } finally {
    setSubmitting(false);
  }
});

// live style update for pending layer
el.statusJalan?.addEventListener('change', function () {
  const target =
    state.pendingLayer && state.featureType === 'jalan'
      ? state.pendingLayer
      : state.editing?.type === 'jalan'
        ? state.editing.layer
        : null;

  if (target && target.setStyle) {
    target.setStyle({ color: getJalanColor(el.statusJalan.value), weight: 4, opacity: 0.95 });
  }
});

el.statusParsil?.addEventListener('change', function () {
  const target =
    state.pendingLayer && state.featureType === 'parsil'
      ? state.pendingLayer
      : state.editing?.type === 'parsil'
        ? state.editing.layer
        : null;

  if (target && target.setStyle) {
    target.setStyle({ fillColor: getParsilColor(el.statusParsil.value), fillOpacity: 0.35 });
  }
});

el.status24Jam?.addEventListener('change', function () {
  if (state.editing?.type === 'point' && state.editing.layer?.setIcon) {
    state.editing.layer.setIcon(makeSpbuIcon(el.status24Jam.checked));
  }
});

// ================= LOAD LAYERS =================
function setActiveListItem(kind, id) {
  if (kind === 'points' && el.pointsList) {
    const items = el.pointsList.querySelectorAll('.list-item');
    items.forEach((node) => node.classList.toggle('is-active', node.dataset.id === String(id)));
    state.selected.points = id;
  }
  if (kind === 'jalan' && el.jalanList) {
    const items = el.jalanList.querySelectorAll('.list-item');
    items.forEach((node) => node.classList.toggle('is-active', node.dataset.id === String(id)));
    state.selected.jalan = id;
  }
  if (kind === 'parsil' && el.parsilList) {
    const items = el.parsilList.querySelectorAll('.list-item');
    items.forEach((node) => node.classList.toggle('is-active', node.dataset.id === String(id)));
    state.selected.parsil = id;
  }

  if (kind === 'rumahIbadah' && el.rumahIbadahList) {
    const items = el.rumahIbadahList.querySelectorAll('.list-item');
    items.forEach((node) => node.classList.toggle('is-active', node.dataset.id === String(id)));
    state.selected.rumahIbadah = id;
  }

  if (kind === 'pemukiman' && el.pemukimanList) {
    const items = el.pemukimanList.querySelectorAll('.list-item');
    items.forEach((node) => node.classList.toggle('is-active', node.dataset.id === String(id)));
    state.selected.pemukiman = id;
  }
}

function renderEmptyList(container, text) {
  if (!container) return;
  container.innerHTML = '';
  const div = document.createElement('div');
  div.className = 'list-empty';
  div.textContent = text;
  container.appendChild(div);
}

function renderPointsList(rows) {
  if (!el.pointsList) return;
  if (!Array.isArray(rows) || rows.length === 0) {
    renderEmptyList(el.pointsList, 'Belum ada data SPBU.');
    return;
  }

  el.pointsList.innerHTML = '';
  for (const row of rows) {
    const id = row?.id ?? '';
    const nama = row?.nama ?? 'SPBU';
    const no = row?.no ?? '';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'list-item';
    btn.dataset.id = String(id);

    const title = document.createElement('div');
    title.className = 'list-title';
    title.textContent = nama;

    const meta = document.createElement('div');
    meta.className = 'list-meta';
    meta.textContent = no ? `No: ${no}` : 'Klik untuk zoom';

    btn.appendChild(title);
    btn.appendChild(meta);

    btn.addEventListener('click', () => {
      const lat = parseFloat(row?.latitude);
      const lng = parseFloat(row?.longitude);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

      if (el.togglePoints) el.togglePoints.checked = true;
      applyLayerVisibility();

      const record = state.indices.points.get(String(id));
      map.flyTo([lat, lng], Math.max(map.getZoom(), 17), { duration: 0.9 });
      if (record?.layer?.openPopup) record.layer.openPopup();
      if (record?.layer?.setStyle) {
        record.layer.setStyle({ radius: 10 });
        window.setTimeout(() => record.layer.setStyle({ radius: 7 }), 900);
      }
      setActiveListItem('points', id);
    });

    el.pointsList.appendChild(btn);
  }
}

function renderJalanList(features) {
  if (!el.jalanList) return;
  if (!Array.isArray(features) || features.length === 0) {
    renderEmptyList(el.jalanList, 'Belum ada data jalan.');
    return;
  }

  el.jalanList.innerHTML = '';
  for (const f of features) {
    const props = f?.properties ?? {};
    const id = props?.id ?? '';
    const nama = props?.nama_jalan ?? 'Jalan';
    const status = props?.status_jalan ?? '';
    const panjang = Number.isFinite(Number(props?.panjang_meter))
      ? `${Number(props.panjang_meter).toFixed(0)} m`
      : '';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'list-item';
    btn.dataset.id = String(id);

    const title = document.createElement('div');
    title.className = 'list-title';
    title.textContent = nama;

    const meta = document.createElement('div');
    meta.className = 'list-meta';
    meta.textContent = [status, panjang].filter(Boolean).join(' • ') || 'Klik untuk zoom';

    btn.appendChild(title);
    btn.appendChild(meta);

    btn.addEventListener('click', () => {
      if (el.toggleJalan) el.toggleJalan.checked = true;
      applyLayerVisibility();

      const record = state.indices.jalan.get(String(id));
      const layer = record?.layer;
      if (layer?.getBounds) {
        map.fitBounds(layer.getBounds(), { padding: [30, 30] });
      }
      if (layer?.openPopup) layer.openPopup();
      setActiveListItem('jalan', id);
    });

    el.jalanList.appendChild(btn);
  }
}

function renderParsilList(features) {
  if (!el.parsilList) return;
  if (!Array.isArray(features) || features.length === 0) {
    renderEmptyList(el.parsilList, 'Belum ada data parsil.');
    return;
  }

  el.parsilList.innerHTML = '';
  for (const f of features) {
    const props = f?.properties ?? {};
    const id = props?.id ?? '';
    const status = props?.status_kepemilikan ?? '';
    const luas = Number.isFinite(Number(props?.luas_m2))
      ? `${Number(props.luas_m2).toFixed(0)} m²`
      : '';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'list-item';
    btn.dataset.id = String(id);

    const title = document.createElement('div');
    title.className = 'list-title';
    title.textContent = `Parsil ${status || ''}`.trim();

    const meta = document.createElement('div');
    meta.className = 'list-meta';
    meta.textContent = luas ? `Luas: ${luas}` : 'Klik untuk zoom';

    btn.appendChild(title);
    btn.appendChild(meta);

    btn.addEventListener('click', () => {
      if (el.toggleParsil) el.toggleParsil.checked = true;
      applyLayerVisibility();

      const record = state.indices.parsil.get(String(id));
      const layer = record?.layer;
      if (layer?.getBounds) {
        map.fitBounds(layer.getBounds(), { padding: [30, 30] });
      }
      if (layer?.openPopup) layer.openPopup();
      setActiveListItem('parsil', id);
    });

    el.parsilList.appendChild(btn);
  }
}

function renderRumahIbadahList(rows) {
  if (!el.rumahIbadahList) return;
  if (!Array.isArray(rows) || rows.length === 0) {
    renderEmptyList(el.rumahIbadahList, 'Belum ada data rumah ibadah.');
    return;
  }

  el.rumahIbadahList.innerHTML = '';
  for (const row of rows) {
    const lat = parseFloat(row?.latitude);
    const lng = parseFloat(row?.longitude);
    const id = String(row?.id ?? (Number.isFinite(lat) && Number.isFinite(lng) ? `${lat},${lng}` : ''));
    const nama = row?.nama ?? 'Rumah Ibadah';
    const jenis = row?.jenis ?? '';
    const radius = Number(row?.radius_meter);
    const metaText = [jenis, Number.isFinite(radius) && radius > 0 ? `Radius: ${radius} m` : ''].filter(Boolean).join(' • ') || 'Klik untuk zoom';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'list-item';
    btn.dataset.id = String(id);

    const title = document.createElement('div');
    title.className = 'list-title';
    title.textContent = nama;

    const meta = document.createElement('div');
    meta.className = 'list-meta';
    meta.textContent = metaText;

    btn.appendChild(title);
    btn.appendChild(meta);

    btn.addEventListener('click', () => {
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

      if (el.toggleRumahIbadah) el.toggleRumahIbadah.checked = true;
      applyLayerVisibility();

      const record = state.indices.rumahIbadah.get(String(id));
      map.flyTo([lat, lng], Math.max(map.getZoom(), 17), { duration: 0.9 });
      if (record?.layer?.openPopup) record.layer.openPopup();
      setActiveListItem('rumahIbadah', id);
    });

    el.rumahIbadahList.appendChild(btn);
  }
}

function renderPemukimanList(rows) {
  if (!el.pemukimanList) return;
  if (!Array.isArray(rows) || rows.length === 0) {
    renderEmptyList(el.pemukimanList, 'Belum ada data pemukiman miskin.');
    return;
  }

  el.pemukimanList.innerHTML = '';
  for (const row of rows) {
    const lat = parseFloat(row?.latitude);
    const lng = parseFloat(row?.longitude);
    const id = String(row?.id ?? (Number.isFinite(lat) && Number.isFinite(lng) ? `${lat},${lng}` : ''));
    const nama = row?.kk_nama ?? row?.nama ?? 'Pemukiman Miskin';
    const status = row?.status_bantuan ?? '';
    const ibadah = row?.rumah_ibadah_nama ?? '';
    const metaText = [status, ibadah ? `Binaan: ${ibadah}` : ''].filter(Boolean).join(' • ') || 'Klik untuk zoom';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'list-item';
    btn.dataset.id = String(id);

    const title = document.createElement('div');
    title.className = 'list-title';
    title.textContent = nama;

    const meta = document.createElement('div');
    meta.className = 'list-meta';
    meta.textContent = metaText;

    btn.appendChild(title);
    btn.appendChild(meta);

    btn.addEventListener('click', () => {
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

      if (el.togglePemukiman) el.togglePemukiman.checked = true;
      applyLayerVisibility();

      const record = state.indices.pemukiman.get(String(id));
      map.flyTo([lat, lng], Math.max(map.getZoom(), 17), { duration: 0.9 });
      if (record?.layer?.openPopup) record.layer.openPopup();
      setActiveListItem('pemukiman', id);
    });

    el.pemukimanList.appendChild(btn);
  }
}

async function loadPoints() {
  points24Layer.clearLayers();
  pointsNon24Layer.clearLayers();
  state.counts.points = 0;
  state.indices.points.clear();

  let rows = [];
  try {
    const res = await fetch('php/read_point.php', { cache: 'no-store' });
    rows = await res.json();
  } catch {
    rows = [];
  }

  let count = 0;
  if (Array.isArray(rows)) {
    for (const row of rows) {
      const lat = parseFloat(row?.latitude);
      const lng = parseFloat(row?.longitude);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;

      const id = String(row?.id ?? `${lat},${lng}`);
      const nama = escapeHtml(row?.nama ?? 'SPBU');
      const no = escapeHtml(row?.no ?? '-');
      const deskripsi = escapeHtml(row?.deskripsi ?? '');
      const is24 = isSpbu24(row);
      const status24 = is24 ? 'Ya' : 'Tidak';

      const marker = L.marker([lat, lng], { icon: makeSpbuIcon(is24), draggable: true });
      marker.on('add', () => {
        if (marker.dragging && marker.dragging.disable) {
          marker.dragging.disable();
        }
      });

      const popupEl = document.createElement('div');
      popupEl.innerHTML = `
        <div style="font-weight:800;margin-bottom:6px;">${nama}</div>
        <div><b>No:</b> ${no}</div>
        <div><b>24 Jam:</b> ${status24}</div>
        ${deskripsi ? `<div style="margin-top:6px;color:#475569;">${deskripsi}</div>` : ''}
      `;

      const actions = document.createElement('div');
      actions.className = 'popup-actions';
      const btnEditData = document.createElement('button');
      btnEditData.type = 'button';
      btnEditData.className = 'popup-btn edit';
      btnEditData.textContent = 'Edit Data';
      const btnEditLoc = document.createElement('button');
      btnEditLoc.type = 'button';
      btnEditLoc.className = 'popup-btn spatial';
      btnEditLoc.textContent = 'Edit Lokasi';
      const btnDelete = document.createElement('button');
      btnDelete.type = 'button';
      btnDelete.className = 'popup-btn delete';
      btnDelete.textContent = 'Hapus';
      actions.appendChild(btnEditData);
      actions.appendChild(btnEditLoc);
      actions.appendChild(btnDelete);
      popupEl.appendChild(actions);

      btnEditData.addEventListener('click', () => startEditPoint(id, marker, row));
      btnEditLoc.addEventListener('click', () => startEditLocationPoint(id, marker, row));
      btnDelete.addEventListener('click', () => deleteFeature('point', id));

      marker.bindPopup(popupEl);
      marker.addTo(is24 ? points24Layer : pointsNon24Layer);
      marker.on('click', () => setActiveListItem('points', id));
      state.indices.points.set(String(id), { layer: marker, data: row });
      count += 1;
    }
  }

  state.counts.points = count;
  state.pointsData = Array.isArray(rows) ? rows : [];
  updateStats();
  applyPointFilter();
}

async function loadJalan() {
  jalanLayer.clearLayers();
  state.counts.jalan = 0;
  state.indices.jalan.clear();

  let fc = null;
  try {
    const res = await fetch('php/read_jalan.php', { cache: 'no-store' });
    fc = await res.json();
  } catch {
    fc = null;
  }
  if (!fc || fc.type !== 'FeatureCollection' || !Array.isArray(fc.features)) {
    updateStats();
    renderJalanList([]);
    return;
  }

  const geo = L.geoJSON(fc, {
    style: (feature) => {
      const status = feature?.properties?.status_jalan;
      return { color: getJalanColor(status), weight: 4, opacity: 0.95 };
    },
    onEachFeature: (feature, layer) => {
      const props = feature?.properties ?? {};
      const id = String(props?.id ?? '');
      const nama = escapeHtml(props.nama_jalan ?? 'Jalan');
      const status = escapeHtml(props.status_jalan ?? '-');
      const panjang = Number.isFinite(Number(props.panjang_meter))
        ? `${Number(props.panjang_meter).toFixed(1)} m`
        : '-';

      const popupEl = document.createElement('div');
      popupEl.innerHTML = `
        <div style="font-weight:800;margin-bottom:6px;">${nama}</div>
        <div><b>Status:</b> ${status}</div>
        <div><b>Panjang:</b> ${panjang}</div>
      `;

      const actions = document.createElement('div');
      actions.className = 'popup-actions';
      const btnEditData = document.createElement('button');
      btnEditData.type = 'button';
      btnEditData.className = 'popup-btn edit';
      btnEditData.textContent = 'Edit Data';
      const btnEditLoc = document.createElement('button');
      btnEditLoc.type = 'button';
      btnEditLoc.className = 'popup-btn spatial';
      btnEditLoc.textContent = 'Edit Lokasi';
      const btnDelete = document.createElement('button');
      btnDelete.type = 'button';
      btnDelete.className = 'popup-btn delete';
      btnDelete.textContent = 'Hapus';
      actions.appendChild(btnEditData);
      actions.appendChild(btnEditLoc);
      actions.appendChild(btnDelete);
      popupEl.appendChild(actions);

      btnEditData.addEventListener('click', () => startEditJalan(id, layer, props));
      btnEditLoc.addEventListener('click', () => startEditLocationJalan(id, layer, props));
      btnDelete.addEventListener('click', () => deleteFeature('jalan', id));

      layer.bindPopup(popupEl);

      if (id) {
        state.indices.jalan.set(id, { layer, data: props });
        layer.on('click', () => setActiveListItem('jalan', id));
      }
    }
  });

  geo.addTo(jalanLayer);
  state.counts.jalan = fc.features.length;
  updateStats();
  renderJalanList(fc.features);
}

async function loadParsil() {
  parsilLayer.clearLayers();
  state.counts.parsil = 0;
  state.indices.parsil.clear();

  let fc = null;
  try {
    const res = await fetch('php/read_parsil.php', { cache: 'no-store' });
    fc = await res.json();
  } catch {
    fc = null;
  }
  if (!fc || fc.type !== 'FeatureCollection' || !Array.isArray(fc.features)) {
    updateStats();
    renderParsilList([]);
    return;
  }

  const geo = L.geoJSON(fc, {
    style: (feature) => {
      const status = feature?.properties?.status_kepemilikan;
      return {
        color: '#0f172a',
        weight: 2,
        fillColor: getParsilColor(status),
        fillOpacity: 0.35
      };
    },
    onEachFeature: (feature, layer) => {
      const props = feature?.properties ?? {};
      const status = escapeHtml(props.status_kepemilikan ?? '-');
      const luas = Number.isFinite(Number(props.luas_m2))
        ? `${Number(props.luas_m2).toFixed(1)} m²`
        : '-';

      const id = String(props?.id ?? '');
      const popupEl = document.createElement('div');
      popupEl.innerHTML = `
        <div style="font-weight:800;margin-bottom:6px;">Parsil</div>
        <div><b>Status:</b> ${status}</div>
        <div><b>Luas:</b> ${luas}</div>
      `;

      const actions = document.createElement('div');
      actions.className = 'popup-actions';
      const btnEditData = document.createElement('button');
      btnEditData.type = 'button';
      btnEditData.className = 'popup-btn edit';
      btnEditData.textContent = 'Edit Data';
      const btnEditLoc = document.createElement('button');
      btnEditLoc.type = 'button';
      btnEditLoc.className = 'popup-btn spatial';
      btnEditLoc.textContent = 'Edit Lokasi';
      const btnDelete = document.createElement('button');
      btnDelete.type = 'button';
      btnDelete.className = 'popup-btn delete';
      btnDelete.textContent = 'Hapus';
      actions.appendChild(btnEditData);
      actions.appendChild(btnEditLoc);
      actions.appendChild(btnDelete);
      popupEl.appendChild(actions);

      btnEditData.addEventListener('click', () => startEditParsil(id, layer, props));
      btnEditLoc.addEventListener('click', () => startEditLocationParsil(id, layer, props));
      btnDelete.addEventListener('click', () => deleteFeature('parsil', id));

      layer.bindPopup(popupEl);
      if (id) {
        state.indices.parsil.set(id, { layer, data: props });
        layer.on('click', () => setActiveListItem('parsil', id));
      }
    }
  });

  geo.addTo(parsilLayer);
  state.counts.parsil = fc.features.length;
  updateStats();
  renderParsilList(fc.features);
}

async function loadPemukimanMiskin() {
  pemukimanLayer.clearLayers();
  state.counts.pemukiman = 0;
  state.indices.pemukiman.clear();

  let rows = [];
  try {
    const { res, json, text } = await fetchMaybeJson('php/read_pemukiman_miskin.php', { cache: 'no-store' });
    if (!res.ok) {
      warnOnce('pemukiman_fetch', `Gagal load Pemukiman Miskin (HTTP ${res.status}). Cek koneksi database / endpoint.`);
      rows = [];
    } else if (!Array.isArray(json)) {
      warnOnce('pemukiman_parse', text ? `Respon pemukiman bukan JSON: ${text.slice(0, 120)}` : 'Respon pemukiman bukan JSON.');
      rows = [];
    } else {
      rows = json;
    }
  } catch (e) {
    warnOnce('pemukiman_err', `Gagal load Pemukiman Miskin: ${e?.message || String(e)}`);
    rows = [];
  }

  let count = 0;
  if (Array.isArray(rows)) {
    for (const row of rows) {
      const lat = parseFloat(row?.latitude);
      const lng = parseFloat(row?.longitude);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;

      const id = String(row?.id ?? `${lat},${lng}`);
      const nama = escapeHtml(row?.kk_nama ?? row?.nama ?? 'Pemukiman Miskin');
      const status = escapeHtml(row?.status_bantuan ?? '-');
      const ibadah = escapeHtml(row?.rumah_ibadah_nama ?? '-');
      const jarakNum = Number(row?.jarak_meter);
      const jarak = Number.isFinite(jarakNum) ? `${jarakNum.toFixed(0)} m` : '-';
      const ibadahId = row?.rumah_ibadah_id;
      const covered = ibadahId !== null && ibadahId !== undefined && String(ibadahId).trim() !== '' && String(ibadahId) !== '0';

      const marker = L.marker([lat, lng], { icon: makePemukimanIcon({ isCovered: covered }), draggable: true, autoPan: true, riseOnHover: true });
      marker.on('add', () => {
        if (marker.dragging && marker.dragging.disable) marker.dragging.disable();
      });

      const popupEl = document.createElement('div');
      popupEl.innerHTML = `
        <div style="font-weight:800;margin-bottom:6px;">${nama}</div>
        <div><b>Status:</b> ${status}</div>
        <div><b>Rumah Ibadah:</b> ${ibadah}</div>
        <div><b>Jarak:</b> ${jarak}</div>
      `;

      const actions = document.createElement('div');
      actions.className = 'popup-actions';
      const btnEditData = document.createElement('button');
      btnEditData.type = 'button';
      btnEditData.className = 'popup-btn edit';
      btnEditData.textContent = 'Edit Data';
      const btnEditLoc = document.createElement('button');
      btnEditLoc.type = 'button';
      btnEditLoc.className = 'popup-btn spatial';
      btnEditLoc.textContent = 'Edit Lokasi';
      const btnDelete = document.createElement('button');
      btnDelete.type = 'button';
      btnDelete.className = 'popup-btn delete';
      btnDelete.textContent = 'Hapus';

      actions.appendChild(btnEditData);
      actions.appendChild(btnEditLoc);
      actions.appendChild(btnDelete);
      popupEl.appendChild(actions);

      btnEditData.addEventListener('click', () => startEditPemukiman(id, marker, row));
      btnEditLoc.addEventListener('click', () => startEditLocationPemukiman(id, marker, row));
      btnDelete.addEventListener('click', () => deleteFeature('pemukiman', id));

      marker.bindPopup(popupEl);
      marker.addTo(pemukimanLayer);
      state.indices.pemukiman.set(String(id), { layer: marker, data: row });
      marker.on('click', () => setActiveListItem('pemukiman', id));
      count += 1;
    }
  }

  state.counts.pemukiman = count;
  state.pemukimanData = Array.isArray(rows) ? rows : [];
  updateStats();
  renderPemukimanList(state.pemukimanData);
}

async function loadRumahIbadah() {
  rumahIbadahLayer.clearLayers();
  rumahIbadahRadiusLayer.clearLayers();
  state.indices.rumahIbadah.clear();

  let rows = [];
  try {
    const { res, json, text } = await fetchMaybeJson('php/read_rumah_ibadah.php', { cache: 'no-store' });
    if (!res.ok) {
      warnOnce('ibadah_fetch', `Gagal load Rumah Ibadah (HTTP ${res.status}). Cek koneksi database / endpoint.`);
      rows = [];
    } else if (!Array.isArray(json)) {
      warnOnce('ibadah_parse', text ? `Respon rumah ibadah bukan JSON: ${text.slice(0, 120)}` : 'Respon rumah ibadah bukan JSON.');
      rows = [];
    } else {
      rows = json;
    }
  } catch (e) {
    warnOnce('ibadah_err', `Gagal load Rumah Ibadah: ${e?.message || String(e)}`);
    rows = [];
  }

  if (!Array.isArray(rows)) rows = [];
  state.rumahIbadahData = rows;

  let count = 0;
  for (const row of rows) {
    const lat = parseFloat(row?.latitude);
    const lng = parseFloat(row?.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;

    const id = String(row?.id ?? `${lat},${lng}`);
    const nama = escapeHtml(row?.nama ?? 'Rumah Ibadah');
    const jenis = escapeHtml(row?.jenis ?? '-');
    const kontak = escapeHtml(row?.kontak ?? '-');
    const radius = Number(row?.radius_meter);
    const binaan = row?.binaan_count != null ? escapeHtml(String(row.binaan_count)) : null;

    const marker = L.marker([lat, lng], { icon: makeRumahIbadahIcon(), riseOnHover: true });
    const popupEl = document.createElement('div');
    popupEl.innerHTML = `
      <div style="font-weight:800;margin-bottom:6px;">${nama}</div>
      <div><b>Jenis:</b> ${jenis}</div>
      <div><b>Kontak:</b> ${kontak}</div>
      ${Number.isFinite(radius) ? `<div><b>Radius:</b> ${radius} m</div>` : ''}
      ${binaan !== null ? `<div><b>Binaan:</b> ${binaan}</div>` : ''}
    `;

    const actions = document.createElement('div');
    actions.className = 'popup-actions';
    const btnEditData = document.createElement('button');
    btnEditData.type = 'button';
    btnEditData.className = 'popup-btn edit';
    btnEditData.textContent = 'Edit Data';
    const btnEditLoc = document.createElement('button');
    btnEditLoc.type = 'button';
    btnEditLoc.className = 'popup-btn spatial';
    btnEditLoc.textContent = 'Edit Lokasi';
    const btnDelete = document.createElement('button');
    btnDelete.type = 'button';
    btnDelete.className = 'popup-btn delete';
    btnDelete.textContent = 'Hapus';

    actions.appendChild(btnEditData);
    actions.appendChild(btnEditLoc);
    actions.appendChild(btnDelete);
    popupEl.appendChild(actions);

    btnEditData.addEventListener('click', () => startEditRumahIbadah(id, marker, row));
    btnEditLoc.addEventListener('click', () => startEditLocationRumahIbadah(id, marker, row));
    btnDelete.addEventListener('click', () => deleteFeature('rumahIbadah', id));

    marker.bindPopup(popupEl);
    marker.addTo(rumahIbadahLayer);

    if (Number.isFinite(radius) && radius > 0) {
      const circle = L.circle([lat, lng], {
        radius,
        color: '#2563eb',
        weight: 2,
        opacity: 0.7,
        fillColor: '#60a5fa',
        fillOpacity: 0.08
      });
      circle.addTo(rumahIbadahRadiusLayer);
    }

    state.indices.rumahIbadah.set(id, { layer: marker, data: row });
    marker.on('click', () => setActiveListItem('rumahIbadah', id));
    count += 1;
  }

  state.counts.rumahIbadah = count;
  updateStats();
  renderRumahIbadahList(rows);
}

async function loadSektor() {
  sektorLayer.clearLayers();
  state.counts.sektor = 0;
  state.indices.sektor.clear();

  let fc = null;
  try {
    const res = await fetch('js/pontianak_kecamatan.geojson', { cache: 'no-store' });
    if (!res.ok) throw new Error('Network response was not ok');
    fc = await res.json();
  } catch (e) {
    console.error('Failed to load Sektor Kecamatan:', e);
    fc = null;
  }
  
  if (!fc || fc.type !== 'FeatureCollection' || !Array.isArray(fc.features)) {
    updateStats();
    renderSektorList([]);
    return;
  }

  const colors = ['#f43f5e', '#a855f7', '#3b82f6', '#10b981', '#f59e0b', '#64748b'];
  let count = 0;

  const geo = L.geoJSON(fc, {
    style: (feature) => {
      return {
        color: '#1e293b',
        weight: 1,
        fillColor: colors[count % colors.length],
        fillOpacity: 0.25
      };
    },
    onEachFeature: (feature, layer) => {
      const name = feature?.properties?.Kecamatan ?? feature?.properties?.Name ?? 'Kecamatan';
      const id = name.replace(/\s+/g, '-').toLowerCase();
      
      const popupEl = document.createElement('div');
      popupEl.innerHTML = `
        <div style="font-weight:800;margin-bottom:6px;">${name}</div>
        <div><b>Tipe:</b> Sektor Kecamatan</div>
      `;
      
      layer.bindPopup(popupEl);
      
      if (id) {
        state.indices.sektor.set(id, { layer, data: feature.properties });
      }
      count++;
    }
  });

  geo.addTo(sektorLayer);
  state.counts.sektor = count;
  updateStats();
  renderSektorList(fc.features);
}

function renderSektorList(features) {
  if (!el.sektorList) return;
  if (!Array.isArray(features) || features.length === 0) {
    renderEmptyList(el.sektorList, 'Belum ada data sektor kecamatan.');
    return;
  }

  el.sektorList.innerHTML = '';
  for (const f of features) {
    const props = f?.properties ?? {};
    const name = props?.Kecamatan ?? props?.Name ?? 'Kecamatan';
    const id = name.replace(/\s+/g, '-').toLowerCase();

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'list-item';
    btn.dataset.id = String(id);

    const title = document.createElement('div');
    title.className = 'list-title';
    title.textContent = name;

    const meta = document.createElement('div');
    meta.className = 'list-meta';
    meta.textContent = 'Klik untuk zoom';

    btn.appendChild(title);
    btn.appendChild(meta);

    btn.addEventListener('click', () => {
      if (el.toggleSektor) el.toggleSektor.checked = true;
      applyLayerVisibility();

      const record = state.indices.sektor.get(String(id));
      const layer = record?.layer;
      if (layer?.getBounds) {
        map.fitBounds(layer.getBounds(), { padding: [30, 30] });
      }
      if (layer?.openPopup) layer.openPopup();
      setActiveListItem('sektor', id);
    });

    el.sektorList.appendChild(btn);
  }
}

async function loadAll() {
  try {
    await Promise.all([loadPoints(), loadJalan(), loadParsil(), loadPemukimanMiskin(), loadRumahIbadah(), loadSektor()]);
  } catch {
    updateStats();
  }
}

// ================= MAP TOOLBAR =================
el.btnResetView?.addEventListener('click', function () {
  map.setView(INITIAL_CENTER, INITIAL_ZOOM);
});

el.btnMyLocation?.addEventListener('click', function () {
  if (!navigator.geolocation) {
    showToast('Browser tidak mendukung geolocation.', 'error');
    return;
  }

  el.btnMyLocation.disabled = true;
  el.btnMyLocation.textContent = 'Mencari...';

  const restore = () => {
    el.btnMyLocation.disabled = false;
    el.btnMyLocation.textContent = 'Lokasi Saya';
  };

  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      map.flyTo([lat, lng], Math.max(map.getZoom(), 15), { duration: 1.2 });
      showToast('Lokasi ditemukan.', 'success');
      restore();
    },
    (err) => {
      if (err?.code === 1) {
        showToast('Permintaan lokasi dibatalkan.', 'info');
      } else {
        showToast(err?.message || 'Gagal mendapatkan lokasi.', 'error');
      }
      restore();
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
});

el.btnFilter24?.addEventListener('click', () => togglePointFilter('24'));
el.btnFilterNon24?.addEventListener('click', () => togglePointFilter('non24'));

// ================= EDIT BAR =================
el.btnSaveLocation?.addEventListener('click', saveLocationEdit);
el.btnCancelLocation?.addEventListener('click', () => cancelLocationEdit());
el.btnDeleteVertex?.addEventListener('click', () => {
  if (!state.locationEditing) return;
  setVertexDeleteMode(!state.locationEditing.vertexDeleteMode);
});

// ================= UI EVENTS =================
el.togglePoints?.addEventListener('change', applyLayerVisibility);
el.toggleJalan?.addEventListener('change', applyLayerVisibility);
el.toggleParsil?.addEventListener('change', applyLayerVisibility);
el.toggleRumahIbadah?.addEventListener('change', applyLayerVisibility);
el.togglePemukiman?.addEventListener('change', applyLayerVisibility);
el.toggleSektor?.addEventListener('change', applyLayerVisibility);

// Prevent toolbar clicks from being treated as map clicks/drag.
try {
  const toolbar = document.querySelector('.map-toolbar');
  if (toolbar && window.L?.DomEvent) {
    L.DomEvent.disableClickPropagation(toolbar);
    L.DomEvent.disableScrollPropagation(toolbar);
  }
} catch {
  // ignore
}

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') resetMode();
});

// ================= INIT =================
updateModeInfo();
applyLayerVisibility();
loadAll();

if (window.location.protocol === 'file:') {
  showToast('Buka aplikasi lewat server (contoh: http://localhost/webgis-spbu/) supaya tombol Simpan & Load data berfungsi.', 'error', 7000);
}

// ================= UTIL =================
function hitungPanjang(layer) {
  const latlngs = layer.getLatLngs();
  let total = 0;
  for (let i = 0; i < latlngs.length - 1; i++) {
    total += map.distance(latlngs[i], latlngs[i + 1]);
  }
  return total;
}

function hitungLuas(layer) {
  return L.GeometryUtil.geodesicArea(layer.getLatLngs()[0]);
}
