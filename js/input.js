// js/input.js
let santriDB = [];           // diisi dari data/dummy.json
let terpilih = null;
let daftar = [];

fetch('data/dummy.json').then(r => r.json()).then(d => santriDB = d.santri);

function cariSantri(q) {
  const hasil = santriDB.filter(s =>
    s.nama.toLowerCase().includes(q.toLowerCase()) || s.nis.includes(q)).slice(0, 5);
  document.getElementById('hasil').innerHTML = hasil.map(s =>
    `<div class="card" style="margin:6px 0;cursor:pointer" onclick='pilih(${JSON.stringify(s)})'>
       ${s.nama} <small>NIS: ${s.nis}</small></div>`).join('');
}
function pilih(s) { terpilih = s; document.getElementById('cari').value = s.nama; document.getElementById('hasil').innerHTML = ''; }

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
    `<tr><td>${i + 1}</td><td>${d.nama}</td><td>${d.alasan}</td>
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