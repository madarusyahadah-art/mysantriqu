// Shared Data Store with localStorage fallback
const SantriDB = {
  defaultSantri: [
    { id: 1, nis: "20240101", nama: "Ahmad Fauzi", kelas: "2B", kamar: "Abu Bakar 01", wali: "H. Abdullah", hp: "081234567890", status: "Aktif" },
    { id: 2, nis: "20240102", nama: "M. Rizki Pratama", kelas: "3A", kamar: "Umar 02", wali: "Rahmat Hidayat", hp: "081234567891", status: "Aktif" },
    { id: 3, nis: "20240103", nama: "Faris Al-Bukhari", kelas: "1A", kamar: "Abu Bakar 02", wali: "Sulaiman", hp: "081234567892", status: "Aktif" },
    { id: 4, nis: "20240104", nama: "Zaidan Nur Ikhsan", kelas: "2B", kamar: "Utsman 01", wali: "Bambang Irawan", hp: "081234567893", status: "Aktif" },
    { id: 5, nis: "20240105", nama: "Bilal Ramadhan", kelas: "1B", kamar: "Ali 03", wali: "Mukhtar", hp: "081234567894", status: "Aktif" },
    { id: 6, nis: "20240106", nama: "Hamzah Al-Farisi", kelas: "3B", kamar: "Umar 01", wali: "Drs. M. Syarif", hp: "081234567895", status: "Aktif" },
    { id: 7, nis: "20240107", nama: "Umar Al-Khattab Jr", kelas: "1A", kamar: "Ali 01", wali: "Faisal Tanjung", hp: "081234567896", status: "Aktif" },
    { id: 8, nis: "20240108", nama: "Ibrahim Khalil", kelas: "2A", kamar: "Utsman 02", wali: "Ust. Wahyudi", hp: "081234567897", status: "Aktif" }
  ],
  defaultKegiatan: [
    { id: 1, waktu: "04:30 - 05:45", nama: "Sholat Subuh & Tadarrus Al-Qur'an", pembimbing: "Ust. Zulkifli, Lc", tempat: "Masjid Jami'", status: "Selesai" },
    { id: 2, waktu: "07:00 - 12:00", nama: "KBM Sekolah Formal & Diniyah", pembimbing: "Ustadz / Guru Pengampu", tempat: "Gedung Belajar", status: "Selesai" },
    { id: 3, waktu: "15:45 - 17:15", nama: "Halaqah Tahfidz & Tasmi' Sore", pembimbing: "Ust. Farhan Al-Hafidz", tempat: "Aula Utama", status: "Berlangsung" },
    { id: 4, waktu: "19:45 - 21:00", nama: "Kajian Kitab Kuning (Fathul Qorib)", pembimbing: "K.H. Syamsul Arifin", tempat: "Masjid Jami'", status: "Terjadwal" }
  ],
  defaultPelanggaran: [
    { id: 1, tanggal: "2026-10-21", nama: "M. Rizki Pratama", kelas: "3A", jenis: "Terlambat Sholat Berjamaah", poin: 5, sanksi: "Dzikir & Membaca 1 Juz di Masjid", ustadz: "Ust. Danial", status: "Selesai" },
    { id: 2, tanggal: "2026-10-20", nama: "Bilal Ramadhan", kelas: "1B", jenis: "Tidur Saat Jam KBM", poin: 3, sanksi: "Berdiri 15 menit & cuci muka", ustadz: "Ust. Fadlan", status: "Selesai" },
    { id: 3, tanggal: "2026-10-18", nama: "Hamzah Al-Farisi", kelas: "3B", jenis: "Keluar Area Asrama Tanpa Izin", poin: 15, sanksi: "Pembersihan Halaman Pondok & Surat Peringatan", ustadz: "Ust. Zulkifli", status: "Pembinaan" }
  ],
  defaultUsers: [
    { id: 1, nama: "Ustadz H. Ahmad Darsya", email: "admin@darsya.com", role: "Super Admin", status: "Aktif", lastLogin: "Hari ini, 06:15" },
    { id: 2, nama: "Ust. Farhan Al-Hafidz", email: "farhan@pondok.id", role: "Musyrif Tahfidz", status: "Aktif", lastLogin: "Hari ini, 05:30" },
    { id: 3, nama: "Ust. Zulkifli, Lc", email: "zulkifli@pondok.id", role: "Pengasuhan & Kedisiplinan", status: "Aktif", lastLogin: "Kemarin, 21:10" },
    { id: 4, nama: "Ustadzah Nurul Latifah", email: "nurul@pondok.id", role: "Tata Usaha / Wali Kelas", status: "Aktif", lastLogin: "20 Okt 2026" }
  ],
  get(key, fallback) {
    const raw = localStorage.getItem('mysantriqu_' + key);
    if (!raw) {
      this.set(key, fallback);
      return fallback;
    }
    try { return JSON.parse(raw); } catch(e) { return fallback; }
  },
  set(key, val) {
    localStorage.setItem('mysantriqu_' + key, JSON.stringify(val));
  }
};

function toast(msg, type = "ok") {
  let box = document.getElementById('toast-box');
  if (!box) {
    box = document.createElement('div');
    box.id = 'toast-box';
    box.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none';
    document.body.appendChild(box);
  }
  const el = document.createElement('div');
  const bg = type === 'ok' ? 'var(--brand)' : (type === 'danger' ? 'var(--danger)' : 'var(--gold)');
  el.style.cssText = `background:${bg};color:#fff;padding:12px 20px;border-radius:10px;font-size:0.9rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.2);transform:translateY(10px);opacity:0;transition:all .2s ease;pointer-events:auto`;
  el.textContent = msg;
  box.appendChild(el);
  setTimeout(() => { el.style.opacity = '1'; el.style.transform = 'translateY(0)'; }, 20);
  setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateY(10px)'; setTimeout(() => el.remove(), 250); }, 3000);
}

function initNav() {
  const toggle = document.querySelector('.mobile-toggle');
  const menu = document.querySelector('.nav-menu');
  if (toggle && menu) {
    toggle.addEventListener('click', () => {
      menu.classList.toggle('open');
    });
  }
  const y = document.getElementById('year-span');
  if (y) y.textContent = new Date().getFullYear();
}

document.addEventListener('DOMContentLoaded', initNav);
