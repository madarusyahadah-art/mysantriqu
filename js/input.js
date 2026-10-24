// js/input.js
let santriDB = [];           // diisi dari data/dummy.json
let terpilih = null;
let daftar = [];

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, character =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]
  );
}

fetch('data/dummy.json').then(r => r.json()).then(d => santriDB = d.santri);

function cariSantri(q) {
  const hasil = santriDB.filter(s =>
    s.nama.toLowerCase().includes(q.toLowerCase()) || String(s.nis).includes(q)).slice(0, 5);
  const container = document.getElementById('hasil');
  container.replaceChildren();
  hasil.forEach(s => {
    const item = document.createElement('div');
    item.className = 'card';
    item.style.margin = '6px 0';
    item.style.cursor = 'pointer';
    item.addEventListener('click', () => pilih(s));
    const name = document.createTextNode(s.nama + ' ');
    const nis = document.createElement('small');
    nis.textContent = 'NIS: ' + s.nis;
    item.append(name, nis);
    container.appendChild(item);
  });
}
function pilih(s) { terpilih = s; document.getElementById('cari').value = s.nama; document.getElementById('hasil').replaceChildren(); }

function tambah() {
  if (!terpilih) return alert('Pilih santri dulu');
  if (daftar.some(d => d.nis === terpilih.nis)) return alert('Santri sudah ada di daftar');
  daftar.push({ ...terpilih, alasan: document.getElementById('alasan').value });
  terpilih = null; document.getElementById('cari').value = '';
  render();
}
function hapus(i) { daftar.splice(i, 1); render(); }
function batal() { daftar = []; render(); }

function render() {
  document.getElementById('jml').textContent = daftar.length;
  document.getElementById('daftar').innerHTML = daftar.map((d, i) =>
    `<tr><td>${i + 1}</td><td>${escapeHtml(d.nama)}</td><td>${escapeHtml(d.alasan)}</td>
     <td><button onclick="hapus(${i})" style="border:0;background:none;color:var(--red);cursor:pointer">🗑</button></td></tr>`).join('');
}

async function simpan() {
  if (!daftar.length) return alert('Daftar masih kosong');
  const payload = { kegiatanId: document.getElementById('kegiatan').value,
                    tanggal: document.getElementById('tanggal').value,
                    santri: daftar.map(d => ({ nis: d.nis, alasan: d.alasan })) };
  // Nanti: await fetch('/api/pelanggaran/bulk', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
  console.log(payload);
  alert('Tersimpan!'); batal();
}